<?php
/**
 * Site Knowledge admin display projection.
 *
 * @package NpcinkCloudAddon
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Npcink_Cloud_Site_Knowledge_Admin_Projection' ) ) {
	/**
	 * Builds the bounded Site Knowledge quota projection used by wp-admin.
	 */
	final class Npcink_Cloud_Site_Knowledge_Admin_Projection {
		private const DATETIME_DISPLAY_FORMAT = 'Y-m-d H:i:s';

		/**
		 * Builds a bounded display projection from Cloud-owned Site Knowledge quota truth.
		 *
		 * @param array<string,mixed> $summary Normalized Cloud status summary.
		 * @return array<string,mixed>
		 */
		public static function build( array $summary ): array {
			$state = sanitize_key( (string) ( $summary['state'] ?? 'not_refreshed' ) );
			$initial_label = 'not_refreshed' === $state
				? __( 'Loading Site Knowledge usage…', 'npcink-cloud-addon' )
				: __( 'Site Knowledge usage is temporarily unavailable.', 'npcink-cloud-addon' );
			$projection = array(
				'available' => false,
				'state' => $state,
				'label' => $initial_label,
				'value_label' => $initial_label,
				'status_label' => '',
				'tooltip' => '',
				'percent' => null,
				'severity' => 'ok',
				'retrieval_acceptance' => self::build_retrieval_acceptance( array() ),
				'details' => array(
					'chunks' => array( 'available' => false, 'label' => __( 'Indexed chunks', 'npcink-cloud-addon' ), 'value' => '' ),
					'sync' => array( 'available' => false, 'label' => __( 'Per-sync limit', 'npcink-cloud-addon' ), 'value' => '' ),
					'truncated' => array( 'available' => false, 'label' => __( 'Truncated documents', 'npcink-cloud-addon' ), 'value' => '' ),
					'skipped' => array( 'available' => false, 'label' => __( 'Skipped documents', 'npcink-cloud-addon' ), 'value' => '' ),
					'lastSync' => array( 'available' => false, 'label' => __( 'Last Cloud sync', 'npcink-cloud-addon' ), 'value' => '' ),
				),
			);

			if ( empty( $summary['available'] ) || absint( $summary['max_documents'] ?? 0 ) < 1 ) {
				return $projection;
			}

			$indexed = absint( $summary['indexed_documents'] ?? 0 );
			$limit = absint( $summary['max_documents'] ?? 0 );
			$remaining = min( $limit, absint( $summary['remaining_documents'] ?? max( 0, $limit - $indexed ) ) );
			$used_percent = min( 100, absint( $summary['document_percent'] ?? 0 ) );
			$remaining_percent = (int) round( max( 0, min( 100, ( $remaining / $limit ) * 100 ) ) );
			$warning_ratio = is_numeric( $summary['warning_ratio'] ?? null ) ? (float) $summary['warning_ratio'] : 0.85;
			$quota_status = sanitize_key( (string) ( $summary['quota_status'] ?? '' ) );
			$severity = 'limited' === $quota_status || $indexed >= $limit
				? 'error'
				: ( 'near_limit' === $quota_status || ( $used_percent / 100 ) >= $warning_ratio ? 'warning' : 'ok' );

			$projection['available'] = true;
			$projection['value_label'] = self::format_number( $remaining ) . ' / ' . self::format_number( $limit );
			$projection['status_label'] = sprintf(
				/* translators: %d: remaining percentage. */
				__( '%d%% remaining', 'npcink-cloud-addon' ),
				$remaining_percent
			);
			$projection['label'] = $projection['value_label'] . ' · ' . $projection['status_label'];
			$projection['tooltip'] = sprintf(
				/* translators: 1: indexed documents, 2: remaining documents, 3: document limit. */
				__( 'Indexed %1$s documents; remaining %2$s documents; limit %3$s documents.', 'npcink-cloud-addon' ),
				self::format_number( $indexed ),
				self::format_number( $remaining ),
				self::format_number( $limit )
			);
			$projection['percent'] = $remaining_percent;
			$projection['severity'] = $severity;
			$projection['retrieval_acceptance'] = self::build_retrieval_acceptance(
				is_array( $summary['retrieval_acceptance'] ?? null ) ? $summary['retrieval_acceptance'] : array()
			);

			$indexed_chunks = absint( $summary['indexed_chunks'] ?? 0 );
			$max_chunks = absint( $summary['max_chunks'] ?? 0 );
			if ( $max_chunks > 0 ) {
				$projection['details']['chunks']['available'] = true;
				$projection['details']['chunks']['value'] = sprintf(
					/* translators: 1: indexed chunks, 2: chunk limit. */
					__( '%1$s / %2$s', 'npcink-cloud-addon' ),
					self::format_number( $indexed_chunks ),
					self::format_number( $max_chunks )
				);
			}

			$max_sync_documents = absint( $summary['max_sync_documents'] ?? 0 );
			$max_sync_chunks = absint( $summary['max_sync_chunks'] ?? 0 );
			if ( $max_sync_documents > 0 || $max_sync_chunks > 0 ) {
				$projection['details']['sync']['available'] = true;
				$projection['details']['sync']['value'] = sprintf(
					/* translators: 1: per-sync document limit, 2: per-sync chunk limit. */
					__( '%1$s documents / %2$s chunks', 'npcink-cloud-addon' ),
					self::format_number( $max_sync_documents ),
					self::format_number( $max_sync_chunks )
				);
			}

			$projection['details']['truncated']['available'] = true;
			$projection['details']['truncated']['value'] = self::format_number( absint( $summary['truncated_documents'] ?? 0 ) );
			$projection['details']['skipped']['available'] = true;
			$projection['details']['skipped']['value'] = sprintf(
				/* translators: 1: skipped documents, 2: documents skipped due to quota. */
				__( '%1$s skipped / %2$s due to quota', 'npcink-cloud-addon' ),
				self::format_number( absint( $summary['skipped_documents'] ?? 0 ) ),
				self::format_number( absint( $summary['skipped_due_to_quota'] ?? 0 ) )
			);

			$last_sync_at = trim( (string) ( $summary['last_sync_at'] ?? '' ) );
			if ( '' !== $last_sync_at ) {
				$projection['details']['lastSync']['available'] = true;
				$projection['details']['lastSync']['value'] = self::format_datetime( $last_sync_at );
			}

			return $projection;
		}

		/**
		 * Builds a bounded automatic retrieval-validation projection.
		 *
		 * @param array<string,mixed> $acceptance Cloud acceptance status.
		 * @return array<string,mixed>
		 */
		private static function build_retrieval_acceptance( array $acceptance ): array {
			$status = sanitize_key( (string) ( $acceptance['status'] ?? 'pending' ) );
			$labels = array(
				'passed' => __( 'Passed automatically', 'npcink-cloud-addon' ),
				'no_hit' => __( 'Expected document was not matched', 'npcink-cloud-addon' ),
				'failed' => __( 'Automatic retrieval failed', 'npcink-cloud-addon' ),
				'not_applicable' => __( 'No public document is available for validation', 'npcink-cloud-addon' ),
				'pending' => __( 'Waiting for automatic validation', 'npcink-cloud-addon' ),
			);
			if ( ! isset( $labels[ $status ] ) ) {
				$status = 'pending';
			}
			$verified_at = trim( (string) ( $acceptance['verified_at'] ?? '' ) );

			return array(
				'status' => $status,
				'label' => $labels[ $status ],
				'verified_at' => $verified_at,
				'verified_label' => '' !== $verified_at
					/* translators: %s: formatted date and time of the last Site Knowledge verification. */
					? sprintf( __( 'Last verified: %s', 'npcink-cloud-addon' ), self::format_datetime( $verified_at ) )
					: '',
			);
		}

		/**
		 * Formats a bounded quota number.
		 *
		 * @param mixed $value Numeric projection value.
		 * @return string
		 */
		private static function format_number( $value ): string {
			$number = is_numeric( $value ) ? (float) $value : 0.0;
			return function_exists( 'number_format_i18n' )
				? number_format_i18n( $number, floor( $number ) === $number ? 0 : 2 )
				: number_format( $number, floor( $number ) === $number ? 0 : 2, '.', ',' );
		}

		/**
		 * Formats a Cloud UTC timestamp in the WordPress site timezone.
		 *
		 * @param string $value Cloud timestamp.
		 * @return string
		 */
		private static function format_datetime( string $value ): string {
			$has_timezone = (bool) preg_match( '/(?:Z|UTC|[+-]\d{2}:?\d{2})$/i', $value );
			$timestamp = strtotime( $has_timezone ? $value : $value . ' UTC' );
			if ( false === $timestamp ) {
				return $value;
			}
			if ( function_exists( 'wp_date' ) ) {
				return wp_date( self::DATETIME_DISPLAY_FORMAT, $timestamp );
			}
			if ( function_exists( 'date_i18n' ) ) {
				return date_i18n( self::DATETIME_DISPLAY_FORMAT, $timestamp, true );
			}

			return gmdate( self::DATETIME_DISPLAY_FORMAT, $timestamp );
		}
	}
}
