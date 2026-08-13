<?php
/**
 * Read-only Runtime Runs admin projection.
 *
 * @package NpcinkCloudAddon
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	return;
}

if ( ! class_exists( 'Npcink_Cloud_Runtime_Runs_Presenter' ) ) {
	/**
	 * Shapes Cloud-owned run responses for the settings-page renderer.
	 */
	final class Npcink_Cloud_Runtime_Runs_Presenter {
		/**
		 * Builds display-ready recent-run rows from supported response envelopes.
		 * @param array<string,mixed> $response Cloud response.
		 * @return array<int,array<string,string>>
		 */
		public static function recent_rows( array $response ): array {
			$rows = array();
			foreach ( array_slice( self::runtime_runs_from_response( $response ), 0, 5 ) as $run ) {
				$rows[] = array(
					'run_id' => self::normalize_run_id( $run['run_id'] ?? $run['id'] ?? '' ),
					'status_label' => self::format_status_label( self::runtime_pick( $run, array( 'status', 'state' ) ) ),
					'result_status_label' => self::format_status_label( self::runtime_pick( $run, array( 'result_status', 'result' ) ) ),
					'updated_at' => self::runtime_pick( $run, array( 'updated_at', 'created_at', 'finished_at' ) ),
				);
			}

			return $rows;
		}

		/**
		 * Builds display-ready detail from one Cloud run response.
		 * @param array<string,mixed> $response Cloud response.
		 * @return array<string,mixed>
		 */
		public static function detail( array $response ): array {
			$status = sanitize_key( self::runtime_pick( $response, array( 'data.status', 'run.status', 'status' ) ) );
			$result_status = sanitize_key( self::runtime_pick( $response, array( 'data.result_status', 'result.status', 'result_status' ) ) );
			$retryable = self::runtime_value( $response, array( 'data.retryable', 'run.retryable', 'retryable', 'data.run_lifecycle.retryable' ) );
			return array(
				'run_id' => self::normalize_run_id( self::runtime_pick( $response, array( 'data.run_id', 'run.run_id', 'run_id' ) ) ),
				'status' => $status,
				'status_label' => self::format_status_label( $status ),
				'result_status' => $result_status,
				'result_status_label' => self::format_status_label( $result_status ),
				'error_code' => self::runtime_pick( $response, array( 'data.error_code', 'error_code', 'data.run_lifecycle.error_code' ) ),
				'started_at' => self::runtime_pick( $response, array( 'data.run_lifecycle.processing_started_at', 'data.started_at', 'started_at' ) ),
				'finished_at' => self::runtime_pick( $response, array( 'data.run_lifecycle.processing_finished_at', 'data.completed_at', 'completed_at' ) ),
				'has_result' => in_array( $result_status, array( 'success', 'succeeded', 'completed', 'available', 'ready' ), true ),
				'retryable' => true === $retryable
					&& in_array( $status, array( 'failed', 'error', 'cancelled', 'canceled', 'timed_out', 'timeout' ), true ),
			);
		}

		/**
		 * Normalizes a Cloud run id for display and signed reads.
		 * @param mixed $value Raw run id.
		 * @return string
		 */
		public static function normalize_run_id( $value ): string {
			return (string) preg_replace( '/[^A-Za-z0-9._:-]/', '', sanitize_text_field( (string) $value ) );
		}

		/** @return array<int,array<string,mixed>> */
		private static function runtime_runs_from_response( array $response ): array {
			$candidates = array( $response['data']['runs'] ?? null, $response['data']['items'] ?? null, $response['runs'] ?? null, $response['items'] ?? null );
			foreach ( $candidates as $candidate ) {
				if ( is_array( $candidate ) ) {
					return array_values( array_filter( $candidate, 'is_array' ) );
				}
			}

			return array();
		}

		/** @return string First non-empty scalar from the candidate paths. */
		private static function runtime_pick( array $source, array $paths ): string {
			$value = self::runtime_value( $source, $paths );
			if ( is_bool( $value ) ) {
				return $value ? __( 'yes', 'npcink-cloud-addon' ) : __( 'no', 'npcink-cloud-addon' );
			}

			return is_scalar( $value ) ? sanitize_text_field( (string) $value ) : '';
		}

		/** @return mixed First non-empty scalar from the candidate paths. */
		private static function runtime_value( array $source, array $paths ) {
			foreach ( $paths as $path ) {
				$value = $source;
				foreach ( explode( '.', $path ) as $segment ) {
					if ( ! is_array( $value ) || ! array_key_exists( $segment, $value ) ) {
						$value = null;
						break;
					}
					$value = $value[ $segment ];
				}
				if ( is_bool( $value ) ) {
					return $value;
				}
				if ( is_scalar( $value ) && '' !== trim( (string) $value ) ) {
					return $value;
				}
			}

			return null;
		}

		/** @return string Localized known state or the visible unknown state. */
		private static function format_status_label( string $status ): string {
			$status = trim( $status );
			if ( '' === $status ) {
				return __( 'unavailable', 'npcink-cloud-addon' );
			}

			$labels = array(
				'submitted'  => __( 'Submitted', 'npcink-cloud-addon' ),
				'queued'     => __( 'Queued', 'npcink-cloud-addon' ),
				'pending'    => __( 'Pending', 'npcink-cloud-addon' ),
				'running'    => __( 'Running', 'npcink-cloud-addon' ),
				'processing' => __( 'Processing', 'npcink-cloud-addon' ),
				'completed'  => __( 'Completed', 'npcink-cloud-addon' ),
				'succeeded'  => __( 'Succeeded', 'npcink-cloud-addon' ),
				'success'    => __( 'Succeeded', 'npcink-cloud-addon' ),
				'failed'     => __( 'Failed', 'npcink-cloud-addon' ),
				'error'      => __( 'Error', 'npcink-cloud-addon' ),
				'canceled'   => __( 'Canceled', 'npcink-cloud-addon' ),
				'cancelled'  => __( 'Canceled', 'npcink-cloud-addon' ),
				'ready'      => __( 'Ready', 'npcink-cloud-addon' ),
				'not_ready'  => __( 'Not ready', 'npcink-cloud-addon' ),
			);

			return $labels[ sanitize_key( $status ) ] ?? $status;
		}
	}
}
