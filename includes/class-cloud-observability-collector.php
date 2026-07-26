<?php
/**
 * Optional local observability collection for Npcink plugins.
 *
 * @package NpcinkCloudAddon
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Npcink_Cloud_Observability_Collector' ) ) {
	/**
	 * Captures metadata-only plugin events after explicit Cloud Addon opt-in.
	 */
	final class Npcink_Cloud_Observability_Collector {
		public const BUFFER_OPTION = 'npcink_cloud_addon_observability_buffer';
		public const STATUS_OPTION = 'npcink_cloud_addon_observability_status';
		public const SUMMARY_OPTION = 'npcink_cloud_addon_observability_summary';
		public const AGENT_SUMMARY_OPTION = 'npcink_cloud_addon_agent_feedback_summary';
			public const CRON_HOOK = 'npcink_cloud_addon_flush_observability';
			private const MAX_BUFFER_ITEMS = 200;
			private const MAX_BATCH_ITEMS = 50;
			private const MAX_TEXT_FIELD_LENGTH = 200;

		/**
		 * Registers collection hooks.
		 *
		 * @return void
		 */
		public static function register(): void {
			add_action( 'npcink_observability_event', array( __CLASS__, 'capture_event' ), 10, 1 );
			add_action( self::CRON_HOOK, array( __CLASS__, 'flush_buffer' ) );

			self::sync_schedule();
		}

		/**
		 * Keeps the upload cron aligned with the verified monitoring setting.
		 *
		 * @return void
		 */
		public static function sync_schedule(): void {
			if ( Npcink_Cloud_Addon_Settings::is_monitoring_enabled() ) {
				self::maybe_schedule_flush();
				return;
			}

			if ( function_exists( 'wp_clear_scheduled_hook' ) ) {
				wp_clear_scheduled_hook( self::CRON_HOOK );
			}
		}

		/**
		 * Captures a local event into the bounded addon-owned observability buffer.
		 *
		 * @param mixed $event Event payload.
		 * @return void
		 */
		public static function capture_event( $event ): void {
			if ( ! is_array( $event ) || ! Npcink_Cloud_Addon_Settings::is_monitoring_enabled() ) {
				return;
			}

			$normalized = self::normalize_event( $event );
			if ( '' === (string) $normalized['plugin_slug'] || '' === (string) $normalized['event_kind'] ) {
				return;
			}

			$buffer = get_option( self::BUFFER_OPTION, array() );
			$buffer = is_array( $buffer ) ? array_values( $buffer ) : array();
			$buffer[] = $normalized;
			if ( count( $buffer ) > self::MAX_BUFFER_ITEMS ) {
				$buffer = array_slice( $buffer, -1 * self::MAX_BUFFER_ITEMS );
			}

			update_option( self::BUFFER_OPTION, $buffer, false );
			update_option(
				self::STATUS_OPTION,
				array_merge(
					self::get_raw_status(),
					array(
						'last_captured_at' => gmdate( 'c' ),
						'last_event_kind'  => (string) $normalized['event_kind'],
						'last_plugin_slug' => (string) $normalized['plugin_slug'],
						'buffer_count'     => count( $buffer ),
					)
				),
				false
			);
		}

		/**
		 * Uploads a bounded batch of buffered events to Cloud.
		 *
		 * @return array<string,mixed>
		 */
		public static function flush_buffer(): array {
			if ( ! Npcink_Cloud_Addon_Settings::is_monitoring_enabled() ) {
				return self::record_flush_result( false, 0, __( 'Monitoring is disabled or Cloud is not verified.', 'npcink-cloud-addon' ) );
			}

			$buffer = get_option( self::BUFFER_OPTION, array() );
			$buffer = is_array( $buffer ) ? array_values( $buffer ) : array();
			if ( empty( $buffer ) ) {
				return self::record_flush_result( true, 0, '' );
			}

			$batch = array_slice( $buffer, 0, self::MAX_BATCH_ITEMS );
			$client = new Npcink_Cloud_Runtime_Client();
			$result = $client->send_observability_events(
				$batch,
				'trace_cloud_observability_' . wp_generate_uuid4(),
				self::batch_idempotency_key( $batch )
			);

			if ( is_wp_error( $result ) ) {
				return self::record_flush_result( false, 0, $result->get_error_message() );
			}

			$data = is_array( $result['data'] ?? null ) ? $result['data'] : array();
			$accepted = min( count( $batch ), max( 0, absint( $data['accepted_count'] ?? count( $batch ) ) ) );
			$stored = min( $accepted, max( 0, absint( $data['stored_count'] ?? $accepted ) ) );
			$duplicate = min( $accepted, max( 0, absint( $data['duplicate_count'] ?? ( $accepted - $stored ) ) ) );
			if ( $accepted > 0 ) {
				$latest_buffer = get_option( self::BUFFER_OPTION, array() );
				$latest_buffer = is_array( $latest_buffer ) ? array_values( $latest_buffer ) : array();
				$buffer = self::remove_accepted_events( $latest_buffer, array_slice( $batch, 0, $accepted ) );
				update_option( self::BUFFER_OPTION, $buffer, false );
			}

			return self::record_flush_result( true, $accepted, '', $stored, $duplicate );
		}

		/**
		 * Returns local monitoring status for the settings UI.
		 *
		 * @return array<string,mixed>
		 */
		public static function get_status(): array {
			$buffer = get_option( self::BUFFER_OPTION, array() );
			$buffer = is_array( $buffer ) ? $buffer : array();
			$status = get_option( self::STATUS_OPTION, array() );
			$status = is_array( $status ) ? $status : array();

			return array(
				'enabled'          => Npcink_Cloud_Addon_Settings::is_monitoring_enabled(),
				'configured'       => Npcink_Cloud_Addon_Settings::is_configured(),
				'verified'         => Npcink_Cloud_Addon_Settings::is_verified(),
				'buffer_count'     => count( $buffer ),
				'last_captured_at' => sanitize_text_field( (string) ( $status['last_captured_at'] ?? '' ) ),
				'last_event_kind'  => sanitize_text_field( (string) ( $status['last_event_kind'] ?? '' ) ),
				'last_plugin_slug' => sanitize_text_field( (string) ( $status['last_plugin_slug'] ?? '' ) ),
				'last_upload_ok'   => ! empty( $status['last_upload_ok'] ),
				'last_uploaded_at' => sanitize_text_field( (string) ( $status['last_uploaded_at'] ?? '' ) ),
				'last_upload_error' => sanitize_text_field( (string) ( $status['last_upload_error'] ?? '' ) ),
				'total_uploaded'   => absint( $status['total_uploaded'] ?? 0 ),
				'last_sent_count'  => absint( $status['last_sent_count'] ?? 0 ),
				'last_stored_count' => absint( $status['last_stored_count'] ?? 0 ),
				'last_duplicate_count' => absint( $status['last_duplicate_count'] ?? 0 ),
				'total_sent'       => absint( $status['total_sent'] ?? ( $status['total_uploaded'] ?? 0 ) ),
				'total_stored'     => absint( $status['total_stored'] ?? 0 ),
				'total_duplicate'  => absint( $status['total_duplicate'] ?? 0 ),
				'remote_summary'   => self::get_summary_cache(),
				'agent_feedback_summary' => self::get_agent_feedback_summary_cache(),
				'plugins'          => self::plugin_snapshot(),
			);
		}

		/**
		 * Refreshes the Cloud-side observability summary cache.
		 *
		 * @return array<string,mixed>
		 */
		public static function refresh_summary(): array {
			if ( ! Npcink_Cloud_Addon_Settings::is_monitoring_enabled() ) {
				return self::record_summary_result( false, array(), __( 'Monitoring is disabled or Cloud is not verified.', 'npcink-cloud-addon' ) );
			}

			$client = new Npcink_Cloud_Runtime_Client();
			$result = $client->get_observability_summary( 24, 'trace_cloud_observability_summary_' . wp_generate_uuid4() );
			if ( is_wp_error( $result ) ) {
				return self::record_summary_result( false, array(), $result->get_error_message() );
			}

			$data = is_array( $result['data'] ?? null ) ? $result['data'] : array();

			return self::record_summary_result( true, $data, '' );
		}

		/**
		 * Refreshes the read-only Cloud Agent feedback quality summary cache.
		 *
		 * @return array<string,mixed>
		 */
		public static function refresh_agent_feedback_summary(): array {
			if ( ! Npcink_Cloud_Addon_Settings::is_verified() ) {
				return self::record_agent_feedback_summary_result( false, array(), __( 'Cloud Addon settings are not verified.', 'npcink-cloud-addon' ) );
			}

			$client = new Npcink_Cloud_Runtime_Client();
			$result = $client->get_agent_feedback_summary( 24, 'trace_cloud_agent_feedback_summary_' . wp_generate_uuid4() );
			if ( is_wp_error( $result ) ) {
				return self::record_agent_feedback_summary_result( false, array(), $result->get_error_message() );
			}

			$data = is_array( $result['data'] ?? null ) ? $result['data'] : array();

			return self::record_agent_feedback_summary_result( true, $data, '' );
		}

		/**
		 * Deletes addon-owned observability options.
		 *
		 * @return void
		 */
		public static function delete_data(): void {
			delete_option( self::BUFFER_OPTION );
			delete_option( self::STATUS_OPTION );
			delete_option( self::SUMMARY_OPTION );
			delete_option( self::AGENT_SUMMARY_OPTION );
			if ( class_exists( 'Npcink_Cloud_Editor_Assist_Quality' ) ) {
				Npcink_Cloud_Editor_Assist_Quality::delete_data();
			}
			wp_clear_scheduled_hook( self::CRON_HOOK );
		}

		/**
		 * Normalizes a safe event payload.
		 *
		 * @param array<string,mixed> $event Raw event.
		 * @return array<string,mixed>
		 */
		private static function normalize_event( array $event ): array {
			$allowed = array(
				'schema_version',
				'plugin_slug',
				'plugin_version',
				'source',
				'event_kind',
				'event_id',
				'emitted_at',
				'captured_at',
				'status',
				'status_detail',
				'error_code',
				'latency_ms',
				'ability_id',
				'proposal_id',
				'correlation_id',
				'adapter_request_id',
				'method',
				'route',
				'status_code',
				'mode',
				'deduplicated',
				'proposal_count',
				'blocked_count',
				'executed_count',
				'failed_count',
				'quality_contract',
				'quality_session_id',
				'task_key',
				'object_scope_hash',
				'actor_scope_hash',
				'outcome',
				'outcome_confidence',
				'save_kind',
				'time_to_outcome_bucket',
				'generation_sequence',
				'content_storage',
			);
			$normalized = array();

			foreach ( $allowed as $key ) {
				if ( ! array_key_exists( $key, $event ) ) {
					continue;
				}

				$normalized[ $key ] = self::sanitize_event_value( $event[ $key ] );
			}

			$normalized['captured_at'] = gmdate( 'c' );
			if ( empty( $normalized['event_id'] ) ) {
				$normalized['event_id'] = 'evt_' . wp_generate_uuid4();
			}

			return $normalized;
		}

		/**
		 * Schedules the bounded observability buffer flush when needed.
		 *
		 * @return void
		 */
		private static function maybe_schedule_flush(): void {
			if ( ! function_exists( 'wp_next_scheduled' ) || ! function_exists( 'wp_schedule_event' ) ) {
				return;
			}

			$next_flush = wp_next_scheduled( self::CRON_HOOK );
			if (
				false !== $next_flush
				&& function_exists( 'wp_get_schedule' )
				&& 'hourly' !== wp_get_schedule( self::CRON_HOOK )
				&& function_exists( 'wp_clear_scheduled_hook' )
			) {
				wp_clear_scheduled_hook( self::CRON_HOOK );
				$next_flush = false;
			}

			if ( false === $next_flush ) {
				wp_schedule_event( time() + 300, 'hourly', self::CRON_HOOK );
			}
		}

		/**
		 * Builds a stable key so an uncertain transport retry cannot duplicate a batch.
		 *
		 * @param array<int,array<string,mixed>> $batch Normalized event batch.
		 * @return string
		 */
		private static function batch_idempotency_key( array $batch ): string {
			$encoded = wp_json_encode( $batch );
			if ( ! is_string( $encoded ) || '' === $encoded ) {
				return 'obs_' . wp_generate_uuid4();
			}

			return 'obs_batch_' . substr( hash( 'sha256', $encoded ), 0, 40 );
		}

		/**
		 * Removes only accepted event identities from the latest stored buffer.
		 *
		 * Re-reading after HTTP preserves events captured while the request was in
		 * flight. Identity counts also keep concurrent identical flushes from
		 * removing a later batch after the first flush already removed its events.
		 *
		 * @param array<int,mixed>               $buffer Latest stored buffer.
		 * @param array<int,array<string,mixed>> $accepted_events Accepted batch prefix.
		 * @return array<int,mixed>
		 */
		private static function remove_accepted_events( array $buffer, array $accepted_events ): array {
			$accepted_identities = array();
			foreach ( $accepted_events as $event ) {
				$identity = self::event_identity( $event );
				$accepted_identities[ $identity ] = absint( $accepted_identities[ $identity ] ?? 0 ) + 1;
			}

			$remaining = array();
			foreach ( $buffer as $event ) {
				if ( is_array( $event ) ) {
					$identity = self::event_identity( $event );
					if ( ! empty( $accepted_identities[ $identity ] ) ) {
						$accepted_identities[ $identity ]--;
						continue;
					}
				}
				$remaining[] = $event;
			}

			return array_values( $remaining );
		}

		/**
		 * Returns one non-secret identity for a normalized event.
		 *
		 * @param array<string,mixed> $event Event payload.
		 * @return string
		 */
		private static function event_identity( array $event ): string {
			$event_id = sanitize_text_field( (string) ( $event['event_id'] ?? '' ) );
			if ( '' !== $event_id ) {
				return 'id:' . $event_id;
			}

			$encoded = wp_json_encode( $event );

			return 'hash:' . hash( 'sha256', is_string( $encoded ) ? $encoded : '' );
		}

		/**
		 * Stores the latest upload outcome.
		 *
		 * @param bool   $ok Whether upload passed.
		 * @param int    $sent Sent and accepted payload count.
		 * @param string $error Error message.
		 * @param int    $stored Stored Cloud event count.
		 * @param int    $duplicate Duplicate Cloud event count.
		 * @return array<string,mixed>
		 */
		private static function record_flush_result( bool $ok, int $sent, string $error, int $stored = 0, int $duplicate = 0 ): array {
			$buffer = get_option( self::BUFFER_OPTION, array() );
			$buffer = is_array( $buffer ) ? $buffer : array();
			$status = self::get_raw_status();
			$sent = max( 0, $sent );
			$stored = min( $sent, max( 0, $stored ) );
			$duplicate = min( $sent, max( 0, $duplicate ) );
			$total_sent = absint( $status['total_sent'] ?? ( $status['total_uploaded'] ?? 0 ) ) + $sent;
			$total_stored = absint( $status['total_stored'] ?? 0 ) + $stored;
			$total_duplicate = absint( $status['total_duplicate'] ?? 0 ) + $duplicate;
			$status = array_merge(
				$status,
				array(
					'last_upload_ok'       => $ok,
					'last_uploaded_at'     => $ok ? gmdate( 'c' ) : (string) ( $status['last_uploaded_at'] ?? '' ),
					'last_upload_error'    => $ok ? '' : sanitize_text_field( $error ),
					'last_sent_count'      => $ok ? $sent : 0,
					'last_stored_count'    => $ok ? $stored : 0,
					'last_duplicate_count' => $ok ? $duplicate : 0,
					'total_uploaded'       => $total_sent,
					'total_sent'           => $total_sent,
					'total_stored'         => $total_stored,
					'total_duplicate'      => $total_duplicate,
					'buffer_count'         => count( $buffer ),
				)
			);
			update_option( self::STATUS_OPTION, $status, false );

			return $status;
		}

		/**
		 * Stores the latest Cloud summary refresh result.
		 *
		 * @param bool                $ok Whether refresh passed.
		 * @param array<string,mixed> $summary Summary payload.
		 * @param string              $error Error message.
		 * @return array<string,mixed>
		 */
		private static function record_summary_result( bool $ok, array $summary, string $error ): array {
			$cache = array(
				'last_refresh_ok'    => $ok,
				'last_refreshed_at'  => $ok ? gmdate( 'c' ) : '',
				'last_refresh_error' => $ok ? '' : sanitize_text_field( $error ),
				'summary'            => $ok ? self::sanitize_summary_payload( $summary ) : self::get_summary_cache()['summary'],
			);
			update_option( self::SUMMARY_OPTION, $cache, false );

			return $cache;
		}

		/**
		 * Stores the latest Cloud Agent feedback summary refresh result.
		 *
		 * @param bool                $ok Whether refresh passed.
		 * @param array<string,mixed> $summary Summary payload.
		 * @param string              $error Error message.
		 * @return array<string,mixed>
		 */
		private static function record_agent_feedback_summary_result( bool $ok, array $summary, string $error ): array {
			$cache = array(
				'last_refresh_ok'    => $ok,
				'last_refreshed_at'  => $ok ? gmdate( 'c' ) : '',
				'last_refresh_error' => $ok ? '' : sanitize_text_field( $error ),
				'summary'            => $ok ? self::sanitize_agent_feedback_summary_payload( $summary ) : self::get_agent_feedback_summary_cache()['summary'],
			);
			update_option( self::AGENT_SUMMARY_OPTION, $cache, false );

			return $cache;
		}

		/**
		 * Returns cached Cloud Agent feedback quality summary.
		 *
		 * @return array<string,mixed>
		 */
		private static function get_agent_feedback_summary_cache(): array {
			$cache = get_option( self::AGENT_SUMMARY_OPTION, array() );
			$cache = is_array( $cache ) ? $cache : array();

			return array(
				'last_refresh_ok'    => ! empty( $cache['last_refresh_ok'] ),
				'last_refreshed_at'  => sanitize_text_field( (string) ( $cache['last_refreshed_at'] ?? '' ) ),
				'last_refresh_error' => sanitize_text_field( (string) ( $cache['last_refresh_error'] ?? '' ) ),
				'summary'            => is_array( $cache['summary'] ?? null ) ? $cache['summary'] : array(),
			);
		}

		/**
		 * Returns cached Cloud summary.
		 *
		 * @return array<string,mixed>
		 */
		private static function get_summary_cache(): array {
			$cache = get_option( self::SUMMARY_OPTION, array() );
			$cache = is_array( $cache ) ? $cache : array();

			return array(
				'last_refresh_ok'    => ! empty( $cache['last_refresh_ok'] ),
				'last_refreshed_at'  => sanitize_text_field( (string) ( $cache['last_refreshed_at'] ?? '' ) ),
				'last_refresh_error' => sanitize_text_field( (string) ( $cache['last_refresh_error'] ?? '' ) ),
				'summary'            => is_array( $cache['summary'] ?? null ) ? $cache['summary'] : array(),
			);
		}

		/**
		 * Sanitizes a nested summary payload for local option storage.
		 *
		 * @param mixed $value Raw payload.
		 * @return array<string,mixed>
		 */
		private static function sanitize_summary_payload( $value ) {
			if ( ! is_array( $value ) ) {
				return array();
			}

			$summary = array();
			if ( array_key_exists( 'generated_at', $value ) ) {
				$summary['generated_at'] = self::sanitize_summary_scalar( $value['generated_at'], 'text' );
			}

			if ( is_array( $value['window'] ?? null ) ) {
				$summary['window'] = self::sanitize_summary_fields(
					$value['window'],
					array(
						'hours'        => 'int',
						'window_hours' => 'int',
						'start_at'     => 'text',
						'end_at'       => 'text',
						'from'         => 'text',
						'to'           => 'text',
						'start'        => 'text',
						'end'          => 'text',
						'started_at'   => 'text',
						'ended_at'     => 'text',
						'generated_at' => 'text',
					)
				);
			}

			if ( is_array( $value['totals'] ?? null ) ) {
				$summary['totals'] = self::sanitize_summary_fields(
					$value['totals'],
					array(
						'events_total'     => 'int',
						'ok_total'         => 'int',
						'warning_total'    => 'int',
						'error_total'      => 'int',
						'success_rate'     => 'float',
						'avg_latency_ms'   => 'float',
						'last_seen_at'     => 'text',
						'plugin_total'     => 'int',
						'event_kind_total' => 'int',
					)
				);
			}

			if ( is_array( $value['plugins'] ?? null ) ) {
				$summary['plugins'] = self::sanitize_plugin_summary_list( $value['plugins'], 20 );
			}

			if ( is_array( $value['event_kinds'] ?? null ) ) {
				$summary['event_kinds'] = self::sanitize_summary_list(
					$value['event_kinds'],
					array(
						'plugin_slug'    => 'text',
						'event_kind'     => 'text',
						'events_total'   => 'int',
						'ok_total'       => 'int',
						'warning_total'  => 'int',
						'error_total'    => 'int',
						'success_rate'   => 'float',
						'avg_latency_ms' => 'float',
						'last_seen_at'   => 'text',
					),
					50
				);
			}

			if ( is_array( $value['hourly_timeline'] ?? null ) ) {
				$summary['hourly_timeline'] = self::sanitize_summary_list(
					$value['hourly_timeline'],
					array(
						'hour'           => 'text',
						'bucket'         => 'text',
						'events_total'   => 'int',
						'ok_total'       => 'int',
						'warning_total'  => 'int',
						'error_total'    => 'int',
						'avg_latency_ms' => 'float',
					),
					168
				);
			}

			if ( is_array( $value['timeline'] ?? null ) ) {
				$summary['timeline'] = self::sanitize_summary_list(
					$value['timeline'],
					array(
						'bucket_start_at' => 'text',
						'bucket_end_at'   => 'text',
						'bucket_hours'    => 'int',
						'events_total'    => 'int',
						'ok_total'        => 'int',
						'warning_total'   => 'int',
						'error_total'     => 'int',
						'success_rate'    => 'float',
						'avg_latency_ms'  => 'float',
					),
					168
				);
			}

			if ( is_array( $value['errors'] ?? null ) ) {
				$summary['errors'] = self::sanitize_summary_list(
					$value['errors'],
					array(
						'site_id'      => 'text',
						'plugin_slug'  => 'text',
						'event_kind'   => 'text',
						'error_code'   => 'text',
						'count'        => 'int',
						'last_seen_at' => 'text',
					),
					50
				);
			}

			if ( is_array( $value['recent_errors'] ?? null ) ) {
				$summary['recent_errors'] = self::sanitize_summary_list(
					$value['recent_errors'],
					array(
						'site_id'       => 'text',
						'error_code'    => 'text',
						'plugin_slug'   => 'text',
						'event_kind'    => 'text',
						'status'        => 'text',
						'status_detail' => 'text',
						'ability_id'    => 'text',
						'proposal_id'   => 'text',
						'route'         => 'text',
						'received_at'   => 'text',
						'emitted_at'    => 'text',
						'captured_at'   => 'text',
						'count'         => 'int',
					),
					20
				);
			}

			if ( is_array( $value['attention'] ?? null ) ) {
				$summary['attention'] = self::sanitize_attention_summary_list( $value['attention'], 20 );
			}

			if ( is_array( $value['attention_items'] ?? null ) ) {
				$summary['attention_items'] = self::sanitize_attention_summary_list( $value['attention_items'], 20 );
			}

			if ( is_array( $value['attention_workflow'] ?? null ) ) {
				$summary['attention_workflow'] = self::sanitize_summary_fields(
					$value['attention_workflow'],
					array(
						'active'          => 'int',
						'acknowledged'    => 'int',
						'muted'           => 'int',
						'resolved'        => 'int',
						'total'           => 'int',
						'needs_attention' => 'int',
					)
				);
			}

			if ( is_array( $value['digest'] ?? null ) ) {
				$digest = self::sanitize_summary_fields(
					$value['digest'],
					array(
						'period_label'    => 'text',
						'window_hours'    => 'int',
						'headline'        => 'text',
						'top_plugin_slug' => 'text',
						'top_error_code'  => 'text',
					)
				);
				if ( is_array( $value['digest']['bullets'] ?? null ) ) {
					$digest['bullets'] = self::sanitize_text_list( $value['digest']['bullets'], 10 );
				}
				$summary['digest'] = $digest;
			}

			if ( is_array( $value['health'] ?? null ) ) {
				$summary['health'] = self::sanitize_summary_fields(
					$value['health'],
					array(
						'status'       => 'text',
						'severity'     => 'text',
						'message'      => 'text',
						'score'        => 'int',
						'summary'      => 'text',
						'last_seen_at' => 'text',
					)
				);
				if ( is_array( $value['health']['reasons'] ?? null ) ) {
					$summary['health']['reasons'] = self::sanitize_text_list( $value['health']['reasons'], 10 );
				}
			}

			return $summary;
		}

		/**
		 * Sanitizes the Cloud Agent feedback quality summary projection.
		 *
		 * @param mixed $value Raw payload.
		 * @return array<string,mixed>
		 */
		private static function sanitize_agent_feedback_summary_payload( $value ): array {
			if ( ! is_array( $value ) ) {
				return array();
			}

			$summary = self::sanitize_summary_fields(
				$value,
				array(
					'artifact_type'        => 'text',
					'contract_version'    => 'text',
					'window_hours'        => 'int',
					'events_total'        => 'int',
					'production_mutation' => 'bool',
					'approval_truth'      => 'text',
					'preflight_truth'     => 'text',
					'final_write_truth'   => 'text',
				)
			);

			foreach ( array( 'outcomes', 'rates', 'nightly_inspection' ) as $key ) {
				if ( is_array( $value[ $key ] ?? null ) ) {
					$summary[ $key ] = self::sanitize_scalar_map( $value[ $key ], 20 );
				}
			}

			foreach ( array( 'source_runtimes', 'local_surfaces', 'scenarios' ) as $key ) {
				if ( is_array( $value[ $key ] ?? null ) ) {
					$summary[ $key ] = self::sanitize_text_or_named_list( $value[ $key ], 20 );
				}
			}

			foreach ( array( 'labels', 'low_quality_labels', 'rejection_reasons', 'quality_trend' ) as $key ) {
				if ( is_array( $value[ $key ] ?? null ) ) {
					$summary[ $key ] = self::sanitize_metric_list_or_map( $value[ $key ], 20 );
				}
			}

			$summary['production_mutation'] = false;
			$summary['approval_truth'] = 'wordpress_local';
			$summary['preflight_truth'] = 'wordpress_local';
			$summary['final_write_truth'] = 'wordpress_local';

			return $summary;
		}

		/**
		 * Sanitizes explicitly allowed summary fields.
		 *
		 * @param array<string,mixed>  $source Raw source.
		 * @param array<string,string> $schema Allowed field schema.
		 * @return array<string,mixed>
		 */
		private static function sanitize_summary_fields( array $source, array $schema ): array {
			$clean = array();
			foreach ( $schema as $key => $type ) {
				if ( ! array_key_exists( $key, $source ) ) {
					continue;
				}

				$clean[ $key ] = self::sanitize_summary_scalar( $source[ $key ], $type );
			}

			return $clean;
		}

		/**
		 * Sanitizes a list of explicitly allowed summary objects.
		 *
		 * @param mixed                $items Raw list.
		 * @param array<string,string> $schema Allowed field schema.
		 * @param int                  $limit Maximum retained items.
		 * @return array<int,array<string,mixed>>
		 */
		private static function sanitize_summary_list( $items, array $schema, int $limit ): array {
			$items = is_array( $items ) ? array_values( $items ) : array();
			$items = array_slice( $items, 0, max( 0, $limit ) );
			$clean = array();

			foreach ( $items as $item ) {
				if ( is_array( $item ) ) {
					$clean[] = self::sanitize_summary_fields( $item, $schema );
				}
			}

			return $clean;
		}

		/**
		 * Sanitizes plugin summary entries with nested event-kind metadata.
		 *
		 * @param mixed $items Raw plugin summaries.
		 * @param int   $limit Maximum retained items.
		 * @return array<int,array<string,mixed>>
		 */
		private static function sanitize_plugin_summary_list( $items, int $limit ): array {
			$items = is_array( $items ) ? array_values( $items ) : array();
			$items = array_slice( $items, 0, max( 0, $limit ) );
			$clean = array();
			$plugin_schema = array(
				'plugin_slug'    => 'text',
				'events_total'   => 'int',
				'ok_total'       => 'int',
				'warning_total'  => 'int',
				'error_total'    => 'int',
				'success_rate'   => 'float',
				'avg_latency_ms' => 'float',
				'last_seen_at'   => 'text',
			);
			$event_kind_schema = array(
				'event_kind'     => 'text',
				'events_total'   => 'int',
				'ok_total'       => 'int',
				'warning_total'  => 'int',
				'error_total'    => 'int',
				'success_rate'   => 'float',
				'avg_latency_ms' => 'float',
				'last_seen_at'   => 'text',
			);

			foreach ( $items as $item ) {
				if ( ! is_array( $item ) ) {
					continue;
				}

				$plugin = self::sanitize_summary_fields( $item, $plugin_schema );
				if ( is_array( $item['event_kinds'] ?? null ) ) {
					$plugin['event_kinds'] = self::sanitize_summary_list( $item['event_kinds'], $event_kind_schema, 25 );
				}
				$clean[] = $plugin;
			}

			return $clean;
		}

		/**
		 * Sanitizes attention entries without storing nested operator notes.
		 *
		 * @param mixed $items Raw attention entries.
		 * @param int   $limit Maximum retained items.
		 * @return array<int,array<string,mixed>>
		 */
		private static function sanitize_attention_summary_list( $items, int $limit ): array {
			return self::sanitize_summary_list(
				$items,
				array(
					'attention_key'    => 'text',
					'severity'         => 'text',
					'code'             => 'text',
					'title'            => 'text',
					'detail'           => 'text',
					'workflow_status'  => 'text',
					'site_id'          => 'text',
					'plugin_slug'      => 'text',
					'event_kind'       => 'text',
					'error_code'       => 'text',
					'suggested_action' => 'text',
				),
				$limit
			);
		}

		/**
		 * Sanitizes a scalar summary field by type.
		 *
		 * @param mixed  $value Raw value.
		 * @param string $type Field type.
		 * @return bool|float|int|string
		 */
		private static function sanitize_summary_scalar( $value, string $type ) {
			if ( 'int' === $type ) {
				return absint( $value );
			}
			if ( 'float' === $type ) {
				return (float) $value;
			}
			if ( 'bool' === $type ) {
				return ! empty( $value );
			}
			if ( is_array( $value ) || is_object( $value ) ) {
				return '';
			}

				return substr( sanitize_text_field( wp_unslash( (string) $value ) ), 0, self::MAX_TEXT_FIELD_LENGTH );
		}

		/**
		 * Sanitizes a bounded list of text values.
		 *
		 * @param mixed $items Raw list.
		 * @param int   $limit Maximum retained items.
		 * @return array<int,string>
		 */
		private static function sanitize_text_list( $items, int $limit ): array {
			$items = is_array( $items ) ? array_values( $items ) : array();
			$items = array_slice( $items, 0, max( 0, $limit ) );
			$clean = array();

			foreach ( $items as $item ) {
				if ( is_scalar( $item ) || null === $item ) {
						$clean[] = substr( sanitize_text_field( wp_unslash( (string) $item ) ), 0, self::MAX_TEXT_FIELD_LENGTH );
				}
			}

			return $clean;
		}

		/**
		 * Sanitizes a small scalar-only metric map.
		 *
		 * @param mixed $source Raw source map.
		 * @param int   $limit Maximum retained keys.
		 * @return array<string,bool|float|int|string>
		 */
		private static function sanitize_scalar_map( $source, int $limit ): array {
			if ( ! is_array( $source ) ) {
				return array();
			}

			$clean = array();
			foreach ( $source as $key => $value ) {
				if ( count( $clean ) >= max( 0, $limit ) ) {
					break;
				}
				if ( is_array( $value ) || is_object( $value ) ) {
					continue;
				}

				$clean[ sanitize_key( (string) $key ) ] = self::sanitize_summary_scalar( $value, is_numeric( $value ) ? 'float' : 'text' );
			}

			return $clean;
		}

		/**
		 * Sanitizes a list of text values or named aggregate objects.
		 *
		 * @param mixed $items Raw items.
		 * @param int   $limit Maximum retained items.
		 * @return array<int,string>
		 */
		private static function sanitize_text_or_named_list( $items, int $limit ): array {
			$items = is_array( $items ) ? array_values( $items ) : array();
			$items = array_slice( $items, 0, max( 0, $limit ) );
			$clean = array();

			foreach ( $items as $item ) {
				if ( is_array( $item ) ) {
					$name = (string) ( $item['source_runtime'] ?? ( $item['local_surface'] ?? ( $item['scenario'] ?? ( $item['name'] ?? '' ) ) ) );
					if ( '' !== $name ) {
						$clean[] = substr( sanitize_text_field( wp_unslash( $name ) ), 0, self::MAX_TEXT_FIELD_LENGTH );
					}
					continue;
				}
				if ( is_scalar( $item ) || null === $item ) {
					$clean[] = substr( sanitize_text_field( wp_unslash( (string) $item ) ), 0, self::MAX_TEXT_FIELD_LENGTH );
				}
			}

			return array_values( array_unique( $clean ) );
		}

		/**
		 * Sanitizes label, reason, and trend aggregate lists.
		 *
		 * @param mixed $items Raw items.
		 * @param int   $limit Maximum retained entries.
		 * @return array<int,array<string,mixed>>
		 */
		private static function sanitize_metric_list_or_map( $items, int $limit ): array {
			if ( ! is_array( $items ) ) {
				return array();
			}

			$raw_items = array();
			$is_list = array_keys( $items ) === range( 0, count( $items ) - 1 );
			if ( $is_list ) {
				$raw_items = array_values( $items );
			} else {
				foreach ( $items as $key => $value ) {
					$raw_items[] = array(
						'label' => $key,
						'count' => $value,
					);
				}
			}

			return self::sanitize_summary_list(
				$raw_items,
				array(
					'label'          => 'text',
					'reason'         => 'text',
					'code'           => 'text',
					'bucket'         => 'text',
					'period'         => 'text',
					'count'          => 'int',
					'events_total'   => 'int',
					'accepted_total' => 'int',
					'rejected_total' => 'int',
					'rate'           => 'float',
					'quality_rate'   => 'float',
				),
				$limit
			);
		}

		/**
		 * Returns raw status option.
		 *
		 * @return array<string,mixed>
		 */
		private static function get_raw_status(): array {
			$status = get_option( self::STATUS_OPTION, array() );

			return is_array( $status ) ? $status : array();
		}

		/**
		 * Sanitizes an event scalar.
		 *
		 * @param mixed $value Raw value.
		 * @return bool|int|float|string
		 */
		private static function sanitize_event_value( $value ) {
			if ( is_bool( $value ) || is_int( $value ) || is_float( $value ) ) {
				return $value;
			}

			if ( is_array( $value ) || is_object( $value ) ) {
				return '';
			}

				return substr( sanitize_text_field( wp_unslash( (string) $value ) ), 0, self::MAX_TEXT_FIELD_LENGTH );
			}

		/**
		 * Returns active/installed state for known Npcink plugins.
		 *
		 * @return array<int,array<string,mixed>>
		 */
		private static function plugin_snapshot(): array {
			$known = array(
				'npcink-governance-core'        => array(
					'label'    => __( 'Core', 'npcink-cloud-addon' ),
					'file'     => 'npcink-governance-core/npcink-governance-core.php',
					'constant' => 'NPCINK_GOVERNANCE_CORE_VERSION',
				),
				'npcink-abilities-toolkit'   => array(
					'label'    => __( 'Abilities', 'npcink-cloud-addon' ),
					'file'     => 'npcink-abilities-toolkit/npcink-abilities-toolkit.php',
					'constant' => 'NPCINK_ABILITIES_TOOLKIT_VERSION',
				),
				'npcink-openclaw-adapter'     => array(
					'label'    => __( 'Adapter', 'npcink-cloud-addon' ),
					'file'     => 'npcink-openclaw-adapter/npcink-openclaw-adapter.php',
					'constant' => 'NPCINK_OPENCLAW_ADAPTER_VERSION',
				),
				'npcink-cloud-addon' => array(
					'label'    => __( 'Cloud Addon', 'npcink-cloud-addon' ),
					'file'     => 'npcink-cloud-addon/npcink-cloud-addon.php',
					'constant' => 'NPCINK_CLOUD_ADDON_VERSION',
				),
			);

			$installed = function_exists( 'get_plugins' ) ? get_plugins() : array();
			$snapshot = array();

			foreach ( $known as $slug => $meta ) {
				$file = (string) $meta['file'];
				$constant = (string) $meta['constant'];
				$plugin_data = is_array( $installed[ $file ] ?? null ) ? $installed[ $file ] : array();
				$active = function_exists( 'is_plugin_active' ) ? is_plugin_active( $file ) : defined( $constant );
				$version = defined( $constant ) ? (string) constant( $constant ) : (string) ( $plugin_data['Version'] ?? '' );

				$snapshot[] = array(
					'slug'      => $slug,
					'label'     => (string) $meta['label'],
					'installed' => ! empty( $plugin_data ) || defined( $constant ),
					'active'    => (bool) $active,
					'version'   => sanitize_text_field( $version ),
				);
			}

			return $snapshot;
		}
	}
}
