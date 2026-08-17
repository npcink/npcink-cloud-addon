<?php
/**
 * Privacy-safe customer journey buffering for the Cloud Addon.
 *
 * @package NpcinkCloudAddon
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Npcink_Cloud_Customer_Journey' ) ) {
	/**
	 * Buffers a closed set of metadata-only journey events.
	 */
	final class Npcink_Cloud_Customer_Journey {
		public const CONTRACT_VERSION = 'customer_journey_event.v1';
		public const BUFFER_OPTION = 'npcink_cloud_addon_customer_journey_buffer';

		private const MAX_BUFFER_ITEMS = 200;
		private const MAX_BATCH_ITEMS = 50;
		private const TRACKED_TASKS = array(
			'title_generation' => 'title_generation',
			'content_summary'  => 'summary_generation',
			'content_rewrite'  => 'rewrite',
		);
		private const ALLOWED_STEPS = array(
			'started',
			'succeeded',
			'failed',
			'abandoned',
			'retried',
			'accepted',
		);

		/**
		 * Reuses the existing observability schedule; it creates no scheduler truth.
		 *
		 * @return void
		 */
		public static function register(): void {
			add_action( Npcink_Cloud_Observability_Collector::CRON_HOOK, array( __CLASS__, 'flush_buffer' ), 15 );
		}

		/**
		 * Builds one rotating opaque session id without retaining object or actor ids.
		 *
		 * @param string              $task_key WordPress AI task key.
		 * @param array<string,mixed> $ability_input Validated local ability input.
		 * @return string
		 */
		public static function build_session_id( string $task_key, array $ability_input ): string {
			$post_id = self::post_id_from_input( $ability_input );
			$actor_id = function_exists( 'get_current_user_id' ) ? absint( get_current_user_id() ) : 0;
			if ( $post_id <= 0 ) {
				return 'journey_' . str_replace( '-', '', wp_generate_uuid4() );
			}

			$window = (string) floor( time() / ( 30 * MINUTE_IN_SECONDS ) );
			$seed = implode( '|', array( $post_id, $actor_id, $task_key, $window ) );

			return 'journey_' . substr( hash_hmac( 'sha256', $seed, wp_salt( 'auth' ) ), 0, 48 );
		}

		/**
		 * Captures one generation journey step.
		 *
		 * @param string $task_key WordPress AI task key.
		 * @param string $step Journey step.
		 * @param string $session_id Opaque local session id.
		 * @param int    $duration_ms Optional duration.
		 * @param string $run_id Optional Cloud run id.
		 * @param string $error_category Optional bounded error category.
		 * @param string $error_code Optional bounded error code.
		 * @return void
		 */
		public static function capture_generation(
			string $task_key,
			string $step,
			string $session_id,
			int $duration_ms = 0,
			string $run_id = '',
			string $error_category = '',
			string $error_code = ''
		): void {
			$journey = (string) ( self::TRACKED_TASKS[ $task_key ] ?? '' );
			if ( '' === $journey ) {
				return;
			}

			self::capture(
				array(
					'anonymous_session_id' => $session_id,
					'journey'               => $journey,
					'step'                  => $step,
					'duration_ms'           => max( 0, $duration_ms ),
					'run_id'                => $run_id,
					'error_category'        => $error_category,
					'error_code'            => $error_code,
				)
			);
		}

		/**
		 * Captures one bounded failure without transporting an arbitrary message.
		 *
		 * @param string $task_key WordPress AI task key.
		 * @param string $session_id Opaque local session id.
		 * @param int    $duration_ms Runtime duration.
		 * @param string $error_code Local bounded error code.
		 * @return void
		 */
		public static function capture_generation_failure(
			string $task_key,
			string $session_id,
			int $duration_ms,
			string $error_code
		): void {
			$normalized_code = strtolower( trim( $error_code ) );
			$category = 'unknown';
			if ( false !== strpos( $normalized_code, 'auth' ) || false !== strpos( $normalized_code, 'unconfigured' ) ) {
				$category = 'auth';
			} elseif ( false !== strpos( $normalized_code, 'provider' ) ) {
				$category = 'provider';
			} elseif ( false !== strpos( $normalized_code, 'request_failed' ) || false !== strpos( $normalized_code, 'timeout' ) ) {
				$category = 'network';
			} elseif ( false !== strpos( $normalized_code, 'invalid' ) || false !== strpos( $normalized_code, 'required' ) ) {
				$category = 'validation';
			}
			if ( 1 !== preg_match( '/^[a-z0-9._:-]{1,96}$/', $normalized_code ) ) {
				$normalized_code = 'unknown';
			}

			self::capture_generation(
				$task_key,
				'failed',
				$session_id,
				$duration_ms,
				'',
				$category,
				$normalized_code
			);
		}

		/**
		 * Captures one explicit local save outcome for a generation session.
		 *
		 * @param string $step Save journey step.
		 * @param string $session_id Opaque local session id.
		 * @param string $run_id Optional Cloud run id.
		 * @return void
		 */
		public static function capture_save( string $step, string $session_id, string $run_id = '' ): void {
			self::capture(
				array(
					'anonymous_session_id' => $session_id,
					'journey'               => 'save',
					'step'                  => $step,
					'run_id'                => $run_id,
				)
			);
		}

		/**
		 * Uploads one bounded batch through the signed customer-journey endpoint.
		 *
		 * @return array<string,mixed>
		 */
		public static function flush_buffer(): array {
			$buffer = self::buffer();
			if ( ! Npcink_Cloud_Addon_Settings::is_monitoring_enabled() ) {
				return array( 'ok' => false, 'sent_count' => 0, 'buffer_count' => count( $buffer ) );
			}
			if ( empty( $buffer ) ) {
				return array( 'ok' => true, 'sent_count' => 0, 'buffer_count' => 0 );
			}

			$batch = array_slice( $buffer, 0, self::MAX_BATCH_ITEMS );
			$client = new Npcink_Cloud_Runtime_Client();
			$result = $client->send_customer_journey_events(
				$batch,
				'trace_customer_journey_' . wp_generate_uuid4(),
				self::batch_idempotency_key( $batch )
			);
			if ( is_wp_error( $result ) ) {
				return array(
					'ok'           => false,
					'sent_count'   => 0,
					'buffer_count' => count( $buffer ),
					'error'        => sanitize_text_field( $result->get_error_message() ),
				);
			}

			$data = is_array( $result['data'] ?? null ) ? $result['data'] : array();
			$accepted = min( count( $batch ), max( 0, absint( $data['accepted_count'] ?? count( $batch ) ) ) );
			if ( $accepted > 0 ) {
				$latest = self::buffer();
				$accepted_ids = array_column( array_slice( $batch, 0, $accepted ), 'event_id' );
				$latest = array_values(
					array_filter(
						$latest,
						static function ( array $event ) use ( $accepted_ids ): bool {
							return ! in_array( (string) ( $event['event_id'] ?? '' ), $accepted_ids, true );
						}
					)
				);
				update_option( self::BUFFER_OPTION, $latest, false );
				$buffer = $latest;
			}

			return array(
				'ok'              => true,
				'sent_count'      => $accepted,
				'stored_count'    => min( $accepted, max( 0, absint( $data['stored_count'] ?? $accepted ) ) ),
				'duplicate_count' => min( $accepted, max( 0, absint( $data['duplicate_count'] ?? 0 ) ) ),
				'buffer_count'    => count( $buffer ),
			);
		}

		/**
		 * Removes addon-owned journey state.
		 *
		 * @return void
		 */
		public static function delete_data(): void {
			delete_option( self::BUFFER_OPTION );
		}

		/**
		 * @param array<string,mixed> $event Closed internal event fields.
		 * @return void
		 */
		private static function capture( array $event ): void {
			if ( ! Npcink_Cloud_Addon_Settings::is_monitoring_enabled() ) {
				return;
			}

			$step = sanitize_key( (string) ( $event['step'] ?? '' ) );
			$session_id = preg_replace( '/[^A-Za-z0-9._:-]/', '', (string) ( $event['anonymous_session_id'] ?? '' ) ) ?? '';
			if ( ! in_array( $step, self::ALLOWED_STEPS, true ) || strlen( $session_id ) < 16 ) {
				return;
			}

			$normalized = array(
				'event_id'            => 'journey_event_' . str_replace( '-', '', wp_generate_uuid4() ),
				'anonymous_session_id' => substr( $session_id, 0, 128 ),
				'surface'              => 'wordpress_editor',
				'journey'              => sanitize_key( (string) ( $event['journey'] ?? '' ) ),
				'step'                 => $step,
				'occurred_at'          => self::timestamp(),
			);
			$duration_ms = absint( $event['duration_ms'] ?? 0 );
			if ( $duration_ms > 0 ) {
				$normalized['duration_ms'] = min( 86400000, $duration_ms );
			}
			$run_id = self::identifier( (string) ( $event['run_id'] ?? '' ), 191 );
			if ( '' !== $run_id ) {
				$normalized['run_id'] = $run_id;
			}
			$error_category = sanitize_key( (string) ( $event['error_category'] ?? '' ) );
			if ( in_array( $error_category, array( 'auth', 'network', 'provider', 'validation', 'storage', 'security', 'unknown' ), true ) ) {
				$normalized['error_category'] = $error_category;
			}
			$error_code = strtolower( self::identifier( (string) ( $event['error_code'] ?? '' ), 96 ) );
			if ( '' !== $error_code ) {
				$normalized['error_code'] = $error_code;
			}

			$buffer = self::buffer();
			$buffer[] = $normalized;
			if ( count( $buffer ) > self::MAX_BUFFER_ITEMS ) {
				$buffer = array_slice( $buffer, -1 * self::MAX_BUFFER_ITEMS );
			}
			update_option( self::BUFFER_OPTION, array_values( $buffer ), false );
		}

		/**
		 * @return array<int,array<string,mixed>>
		 */
		private static function buffer(): array {
			$value = get_option( self::BUFFER_OPTION, array() );
			if ( ! is_array( $value ) ) {
				return array();
			}

			return array_values(
				array_filter(
					$value,
					static function ( $event ): bool {
						return is_array( $event );
					}
				)
			);
		}

		/**
		 * @param array<int,array<string,mixed>> $batch Event batch.
		 */
		private static function batch_idempotency_key( array $batch ): string {
			$encoded = wp_json_encode( $batch );

			return 'journey_' . hash( 'sha256', is_string( $encoded ) ? $encoded : '' );
		}

		private static function identifier( string $value, int $max_length ): string {
			$value = preg_replace( '/[^A-Za-z0-9._:-]/', '_', trim( $value ) ) ?? '';

			return substr( $value, 0, $max_length );
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

		private static function timestamp(): string {
			$date = \DateTimeImmutable::createFromFormat( 'U.u', sprintf( '%.6F', microtime( true ) ) );
			if ( false === $date ) {
				return gmdate( 'Y-m-d\TH:i:s\Z' );
			}

			return $date->setTimezone( new \DateTimeZone( 'UTC' ) )->format( 'Y-m-d\TH:i:s.u\Z' );
		}
	}
}
