<?php
/**
 * Silent editor-assist quality correlation for WordPress AI suggestions.
 *
 * @package NpcinkCloudAddon
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Npcink_Cloud_Editor_Assist_Quality' ) ) {
	/**
	 * Correlates generated suggestions with later explicit local saves.
	 *
	 * Raw prompts, generated text, post IDs, and user IDs never leave WordPress.
	 */
	final class Npcink_Cloud_Editor_Assist_Quality {
		public const CONTRACT_VERSION = 'editor_assist_quality.v1';
		public const PENDING_OPTION = 'npcink_cloud_addon_editor_assist_pending';

		private const PENDING_TTL = HOUR_IN_SECONDS;
		private const REPEAT_WINDOW = 10 * MINUTE_IN_SECONDS;
		private const MAX_PENDING = 100;
		private const TRACKED_TASKS = array(
			'title_generation',
			'content_summary',
			'content_rewrite',
		);

		/**
		 * Registers local lifecycle hooks.
		 *
		 * @return void
		 */
		public static function register(): void {
			add_action( 'wp_after_insert_post', array( __CLASS__, 'observe_post_save' ), 10, 4 );
			add_action( Npcink_Cloud_Observability_Collector::CRON_HOOK, array( __CLASS__, 'expire_pending' ), 5 );
		}

		/**
		 * Records one successful text generation without retaining its content.
		 *
		 * @param string              $ability_id WordPress AI ability name.
		 * @param string              $task_key Cloud task key.
		 * @param array<string,mixed> $ability_input Validated local ability input.
		 * @param string              $run_id Cloud run ID.
		 * @param string              $output_text Generated suggestion.
		 * @param int                 $latency_ms Cloud runtime latency.
		 * @return void
		 */
		public static function record_generation(
			string $ability_id,
			string $task_key,
			array $ability_input,
			string $run_id,
			string $output_text,
			int $latency_ms
		): void {
			if (
				! Npcink_Cloud_Addon_Settings::is_monitoring_enabled()
				|| ! in_array( $task_key, self::TRACKED_TASKS, true )
			) {
				return;
			}

			$post_id = self::post_id_from_input( $ability_input );
			$output_hash = self::content_hash( $output_text );
			if ( $post_id <= 0 || '' === $output_hash ) {
				return;
			}

			self::expire_pending();

			$now = time();
			$actor_id = function_exists( 'get_current_user_id' ) ? get_current_user_id() : 0;
			$records = self::pending_records();
			$latest_index = null;
			foreach ( $records as $index => $record ) {
				if (
					$post_id === absint( $record['post_id'] ?? 0 )
					&& $actor_id === absint( $record['actor_id'] ?? 0 )
					&& $task_key === (string) ( $record['task_key'] ?? '' )
					&& $now - absint( $record['generated_at'] ?? 0 ) <= self::REPEAT_WINDOW
				) {
					$latest_index = $index;
				}
			}

			$quality_session_id = 'quality_' . wp_generate_uuid4();
			$generation_sequence = 1;
			if ( null !== $latest_index ) {
				$quality_session_id = (string) ( $records[ $latest_index ]['quality_session_id'] ?? $quality_session_id );
				$generation_sequence = absint( $records[ $latest_index ]['generation_sequence'] ?? 0 ) + 1;
			}

			$scope_context = $post_id . '|' . $task_key;
			$record = array(
				'quality_session_id' => $quality_session_id,
				'generation_sequence' => $generation_sequence,
				'post_id'            => $post_id,
				'actor_id'           => $actor_id,
				'task_key'           => $task_key,
				'ability_id'         => sanitize_text_field( $ability_id ),
				'run_id'             => sanitize_text_field( $run_id ),
				'output_hash'        => $output_hash,
				'object_scope_hash'  => self::scope_hash( $scope_context ),
				'actor_scope_hash'   => self::scope_hash( (string) $actor_id ),
				'generated_at'       => $now,
				'latency_ms'         => max( 0, $latency_ms ),
			);
			$records[] = $record;
			if ( count( $records ) > self::MAX_PENDING ) {
				$records = array_slice( $records, -1 * self::MAX_PENDING );
			}
			update_option( self::PENDING_OPTION, array_values( $records ), false );

			self::emit_event(
				$record,
				'addon.editor_assist.generation.completed',
				array(
					'status' => 'ok',
				)
			);
			if ( $generation_sequence > 1 ) {
				self::emit_event(
					$record,
					'addon.editor_assist.generation.repeated',
					array(
						'status' => 'warning',
					)
				);
			}
		}

		/**
		 * Observes a real main-post save and classifies pending suggestions.
		 *
		 * @param int   $post_id Post ID.
		 * @param mixed $post Saved post object.
		 * @param bool  $update Whether this updated an existing post.
		 * @param mixed $post_before Previous post object.
		 * @return void
		 */
		public static function observe_post_save( int $post_id, $post, bool $update, $post_before = null ): void {
			unset( $update, $post_before );
			if (
				$post_id <= 0
				|| ! is_object( $post )
				|| ( function_exists( 'wp_is_post_revision' ) && wp_is_post_revision( $post_id ) )
				|| ( function_exists( 'wp_is_post_autosave' ) && wp_is_post_autosave( $post_id ) )
				|| ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
			) {
				return;
			}

			self::expire_pending();
			$records = self::pending_records();
			$matching = array_filter(
				$records,
				static function ( array $record ) use ( $post_id ): bool {
					return $post_id === absint( $record['post_id'] ?? 0 );
				}
			);
			if ( empty( $matching ) ) {
				return;
			}

			$groups = array();
			foreach ( $matching as $record ) {
				$key = (string) ( $record['quality_session_id'] ?? '' ) . '|' . (string) ( $record['task_key'] ?? '' );
				$groups[ $key ][] = $record;
			}

			$resolved_sessions = array();
			foreach ( $groups as $group ) {
				usort(
					$group,
					static function ( array $left, array $right ): int {
						return absint( $left['generation_sequence'] ?? 0 ) <=> absint( $right['generation_sequence'] ?? 0 );
					}
				);
				$latest = $group[ count( $group ) - 1 ];
				$candidate_hashes = self::saved_candidate_hashes(
					$post,
					(string) ( $latest['task_key'] ?? '' )
				);
				$outcome = 'saved_after_generation_unmatched';
				$confidence = 'medium';
				foreach ( $group as $record ) {
					if ( in_array( (string) ( $record['output_hash'] ?? '' ), $candidate_hashes, true ) ) {
						$latest = $record;
						$outcome = 'saved_exact_output';
						$confidence = 'high';
						break;
					}
				}

				self::emit_event(
					$latest,
					'addon.editor_assist.outcome.observed',
					array(
						'status'                 => 'ok',
						'outcome'                => $outcome,
						'outcome_confidence'     => $confidence,
						'save_kind'              => 'publish' === (string) ( $post->post_status ?? '' ) ? 'publish' : 'save',
						'time_to_outcome_bucket' => self::time_bucket( time() - absint( $latest['generated_at'] ?? time() ) ),
					)
				);
				$resolved_sessions[] = (string) ( $latest['quality_session_id'] ?? '' );
			}

			$remaining = array_filter(
				$records,
				static function ( array $record ) use ( $resolved_sessions ): bool {
					return ! in_array( (string) ( $record['quality_session_id'] ?? '' ), $resolved_sessions, true );
				}
			);
			update_option( self::PENDING_OPTION, array_values( $remaining ), false );
		}

		/**
		 * Emits one no-save outcome for expired quality sessions.
		 *
		 * @return void
		 */
		public static function expire_pending(): void {
			$records = self::pending_records();
			if ( empty( $records ) ) {
				return;
			}

			$now = time();
			$expired_groups = array();
			$remaining = array();
			foreach ( $records as $record ) {
				if ( $now - absint( $record['generated_at'] ?? 0 ) > self::PENDING_TTL ) {
					$key = (string) ( $record['quality_session_id'] ?? '' ) . '|' . (string) ( $record['task_key'] ?? '' );
					$expired_groups[ $key ][] = $record;
					continue;
				}
				$remaining[] = $record;
			}

			foreach ( $expired_groups as $group ) {
				usort(
					$group,
					static function ( array $left, array $right ): int {
						return absint( $left['generation_sequence'] ?? 0 ) <=> absint( $right['generation_sequence'] ?? 0 );
					}
				);
				$latest = $group[ count( $group ) - 1 ];
				self::emit_event(
					$latest,
					'addon.editor_assist.outcome.expired',
					array(
						'status'                 => 'warning',
						'outcome'                => 'expired_without_save',
						'outcome_confidence'     => 'medium',
						'save_kind'              => 'none',
						'time_to_outcome_bucket' => 'over_60m',
					)
				);
			}

			if ( count( $remaining ) !== count( $records ) ) {
				update_option( self::PENDING_OPTION, array_values( $remaining ), false );
			}
		}

		/**
		 * Deletes addon-owned local correlation state.
		 *
		 * @return void
		 */
		public static function delete_data(): void {
			delete_option( self::PENDING_OPTION );
		}

		/**
		 * @return array<int,array<string,mixed>>
		 */
		private static function pending_records(): array {
			$records = get_option( self::PENDING_OPTION, array() );
			if ( ! is_array( $records ) ) {
				return array();
			}

			return array_values(
				array_filter(
					$records,
					static function ( $record ): bool {
						return is_array( $record );
					}
				)
			);
		}

		/**
		 * @param array<string,mixed> $input Ability input.
		 */
		private static function post_id_from_input( array $input ): int {
			$post_id = absint( $input['post_id'] ?? 0 );
			if ( $post_id > 0 ) {
				return $post_id;
			}

			$context = $input['context'] ?? 0;
			if ( is_int( $context ) || ( is_string( $context ) && 1 === preg_match( '/^[0-9]+$/', $context ) ) ) {
				return absint( $context );
			}

			return 0;
		}

		/**
		 * @param mixed  $post Saved post object.
		 * @param string $task_key Quality task key.
		 * @return array<int,string>
		 */
		private static function saved_candidate_hashes( $post, string $task_key ): array {
			$values = array();
			$title = (string) ( $post->post_title ?? '' );
			$content = (string) ( $post->post_content ?? '' );
			if ( 'title_generation' === $task_key && '' !== trim( $title ) ) {
				$values[] = $title;
			}
			if ( 'title_generation' !== $task_key && '' !== trim( $content ) ) {
				$values[] = $content;
			}
			if ( 'title_generation' !== $task_key && function_exists( 'parse_blocks' ) ) {
				self::collect_block_texts( parse_blocks( $content ), $values );
			}

			$hashes = array();
			foreach ( $values as $value ) {
				$hash = self::content_hash( (string) $value );
				if ( '' !== $hash ) {
					$hashes[] = $hash;
				}
			}

			return array_values( array_unique( $hashes ) );
		}

		/**
		 * @param mixed             $blocks Parsed block list.
		 * @param array<int,string> $values Collected text values.
		 * @return void
		 */
		private static function collect_block_texts( $blocks, array &$values ): void {
			if ( ! is_array( $blocks ) ) {
				return;
			}
			foreach ( $blocks as $block ) {
				if ( ! is_array( $block ) ) {
					continue;
				}
				$inner_html = (string) ( $block['innerHTML'] ?? '' );
				if ( '' !== trim( wp_strip_all_tags( $inner_html ) ) ) {
					$values[] = $inner_html;
				}
				self::collect_block_texts( $block['innerBlocks'] ?? array(), $values );
			}
		}

		private static function content_hash( string $content ): string {
			$plain = html_entity_decode(
				wp_strip_all_tags( $content ),
				ENT_QUOTES | ENT_HTML5,
				'UTF-8'
			);
			$normalized = trim( preg_replace( '/\s+/u', ' ', $plain ) ?? '' );
			if ( '' === $normalized ) {
				return '';
			}

			return hash_hmac( 'sha256', $normalized, wp_salt( 'auth' ) );
		}

		private static function scope_hash( string $value ): string {
			return hash_hmac( 'sha256', $value, wp_salt( 'auth' ) );
		}

		private static function time_bucket( int $seconds ): string {
			if ( $seconds <= 60 ) {
				return 'under_1m';
			}
			if ( $seconds <= 5 * MINUTE_IN_SECONDS ) {
				return '1_to_5m';
			}
			if ( $seconds <= 15 * MINUTE_IN_SECONDS ) {
				return '5_to_15m';
			}
			if ( $seconds <= 30 * MINUTE_IN_SECONDS ) {
				return '15_to_30m';
			}

			return '30_to_60m';
		}

		/**
		 * @param array<string,mixed> $record Local correlation record.
		 * @param array<string,mixed> $extra Additional safe event fields.
		 */
		private static function emit_event( array $record, string $event_kind, array $extra ): void {
			Npcink_Cloud_Observability_Collector::capture_event(
				array_merge(
					array(
						'schema_version'      => '2026-07-26',
						'plugin_slug'        => 'npcink-cloud-addon',
						'plugin_version'     => defined( 'NPCINK_CLOUD_ADDON_VERSION' ) ? NPCINK_CLOUD_ADDON_VERSION : '',
						'source'             => 'wordpress_local',
						'event_kind'         => $event_kind,
						'event_id'           => 'evt_editor_assist_' . wp_generate_uuid4(),
						'quality_contract'   => self::CONTRACT_VERSION,
						'quality_session_id' => (string) ( $record['quality_session_id'] ?? '' ),
						'task_key'           => (string) ( $record['task_key'] ?? '' ),
						'object_scope_hash'  => (string) ( $record['object_scope_hash'] ?? '' ),
						'actor_scope_hash'   => (string) ( $record['actor_scope_hash'] ?? '' ),
						'generation_sequence' => absint( $record['generation_sequence'] ?? 0 ),
						'ability_id'         => (string) ( $record['ability_id'] ?? '' ),
						'correlation_id'     => (string) ( $record['run_id'] ?? '' ),
						'latency_ms'         => absint( $record['latency_ms'] ?? 0 ),
						'content_storage'    => 'omitted_metadata_only',
					),
					$extra
				)
			);
		}
	}
}
