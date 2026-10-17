<?php
/** Bounded, optional Agent feedback observation fields. @package NpcinkCloudAddon */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Npcink_Cloud_Agent_Feedback_Metadata {
	public const FIELDS = array( 'source_score', 'ranking_version', 'candidate_rank', 'score_bucket', 'retrieval_source', 'rerank_applied' );

	public static function normalize( array $payload ) {
		$metadata = array_intersect_key( $payload, array_flip( self::FIELDS ) );
		$enums = array(
			'ranking_version' => array( 'internal_links.v1', 'related_content.v1' ),
			'score_bucket' => array( 'low', 'medium', 'high', 'unknown' ),
			'retrieval_source' => array( 'cloud_vector', 'cloud_vector_rerank', 'local_fallback', 'no_evidence', 'unavailable', 'unknown' ),
		);
		foreach ( $metadata as $field => $value ) {
			$valid = true;
			if ( isset( $enums[ $field ] ) ) {
				$valid = in_array( $value, $enums[ $field ], true );
			} elseif ( 'rerank_applied' === $field ) {
				$valid = is_bool( $value );
			} elseif ( null !== $value ) {
				$valid = is_int( $value ) && $value >= ( 'source_score' === $field ? 0 : 1 ) && $value <= ( 'source_score' === $field ? 100 : 20 );
			}
			if ( ! $valid ) {
				return new WP_Error( 'cloud_agent_feedback_payload_invalid', 'source_score' === $field ? __( 'Agent feedback source_score must be an integer from 0 through 100.', 'npcink-cloud-addon' ) : __( 'Agent feedback contains missing or unsupported fields.', 'npcink-cloud-addon' ), array( 'status' => 400, 'field' => $field ) );
			}
		}
		if ( array_diff( array_keys( $metadata ), array( 'source_score' ) ) && ! isset( $metadata['ranking_version'] ) ) {
			return new WP_Error( 'cloud_agent_feedback_payload_invalid', __( 'Agent feedback contains missing or unsupported fields.', 'npcink-cloud-addon' ), array( 'status' => 400, 'field' => 'ranking_version' ) );
		}
		return $metadata;
	}
}
