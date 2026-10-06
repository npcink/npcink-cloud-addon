<?php
/**
 * Local media proposal and optimization-plan projection.
 *
 * @package NpcinkCloudAddon
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Npcink_Cloud_Media_Plan_Projection' ) ) {
	/**
	 * Projects verified derivative artifacts into bounded local proposal and optimization-plan payloads without storing, approving, or writing anything. Static and instance-free.
	 */
	final class Npcink_Cloud_Media_Plan_Projection {
		private const PROPOSAL_CONTRACT_VERSION = 'media_derivative_cloud_proposal.v1';
		private const REQUEST_CONTRACT_VERSION = 'media_derivative_cloud_request.v1';

		/**
		 * Builds a local-host proposal payload from a Cloud result artifact.
		 *
		 * This method does not store a proposal and does not mutate WordPress. The
		 * returned payload is intended for Core proposal/preflight intake.
		 *
		 * @param array<string,mixed> $ability_response Ability response envelope.
		 * @param array<string,mixed> $cloud_result Cloud run result envelope.
		 * @param array<string,mixed> $derivative_artifact Downloaded or downloadable Cloud derivative artifact descriptor.
		 * @return array<string,mixed>|WP_Error
		 */
		public static function build_local_proposal_payload( array $ability_response, array $cloud_result, array $derivative_artifact ) {
			$contract = self::extract_contract_data( $ability_response );
			$validated = self::validate_request_contract( $contract );
			if ( is_wp_error( $validated ) ) {
				return $validated;
			}

			$cloud_artifact = Npcink_Cloud_Media_Artifact_Verification::normalize_artifact_descriptor( $derivative_artifact, 'derivative' );
			if ( is_wp_error( $cloud_artifact ) ) {
				return $cloud_artifact;
			}

			$cloud_data = self::extract_cloud_data( $cloud_result );
			if ( is_wp_error( $cloud_data ) ) {
				return $cloud_data;
			}
			$binding_valid = self::validate_derivative_artifact_binding( $cloud_data, $cloud_artifact );
			if ( is_wp_error( $binding_valid ) ) {
				return $binding_valid;
			}
			$artifact = Npcink_Cloud_Media_Artifact_Verification::local_proposal_artifact( $cloud_artifact );

			$original = self::normalize_media_metrics( $contract['cloud_job_payload']['source_asset'] ?? array() );
			$derivative = self::normalize_media_metrics(
				array_merge(
					is_array( $cloud_data['artifact'] ?? null ) ? $cloud_data['artifact'] : array(),
					$cloud_artifact
				)
			);
			$warnings = self::sanitize_string_list( $contract['cloud_job_payload']['warnings'] ?? array() );
			$warnings = array_merge( $warnings, self::sanitize_string_list( $cloud_data['warnings'] ?? array() ) );
			$warnings = self::append_metric_warnings( $warnings, $original, $derivative );

			return array(
				'contract_version'  => self::PROPOSAL_CONTRACT_VERSION,
				'proposal_kind'     => 'media_derivative_cloud_artifact',
				'attachment_id'     => absint( $contract['attachment_id'] ?? 0 ),
				'final_write_owner' => 'local_wordpress_host',
				'approval_required' => true,
				'adoption_allowed'  => true,
				'default_action'    => 'preview_only',
				'actions'           => array(
					'preview' => true,
					'record'  => 'requires_local_host_approval',
					'replace' => 'requires_local_host_approval',
					'rollback' => 'requires_local_host_approval',
				),
				'original'          => $original,
				'derivative'        => $derivative,
				'savings_estimate'  => self::build_savings_estimate( $original, $derivative ),
				'warnings'          => array_values( array_unique( $warnings ) ),
				'artifact'          => $artifact,
				'cloud_result'      => self::sanitize_cloud_result_summary( $cloud_data ),
				'local_adoption'    => array(
					'owner'                         => 'local_wordpress_host',
					'final_write_owner'             => 'local_wordpress_host',
					'approval_required'             => true,
					'wordpress_write_included'      => false,
					'replace_original_default'      => false,
					'attachment_metadata_write_included' => false,
				),
			);
		}

		/**
		 * Builds a Core from-plan media optimization payload for local adapters.
		 *
		 * This does not create, approve, preflight, or execute a proposal.
		 *
		 * @param array<string,mixed> $ability_response Ability response envelope.
		 * @param array<string,mixed> $cloud_result Cloud run result envelope.
		 * @param array<string,mixed> $derivative_artifact Cloud derivative artifact descriptor.
		 * @param array<string,mixed> $media_details_input Reviewed media metadata input.
		 * @return array<string,mixed>|WP_Error
		 */
		public static function build_media_optimization_payload( array $ability_response, array $cloud_result, array $derivative_artifact, array $media_details_input ) {
			if ( empty( $derivative_artifact ) ) {
				$derivative_artifact = Npcink_Cloud_Media_Artifact_Verification::artifact_from_cloud_result( $cloud_result );
			}

			$proposal_payload = self::build_local_proposal_payload( $ability_response, $cloud_result, $derivative_artifact );
			if ( is_wp_error( $proposal_payload ) ) {
				return $proposal_payload;
			}

			$ability_data = is_array( $ability_response['data'] ?? null ) ? $ability_response['data'] : array();
			if ( is_array( $ability_data['content_reference_repairs_preview'] ?? null ) && ! is_array( $proposal_payload['content_reference_repairs_preview'] ?? null ) ) {
				$proposal_payload['content_reference_repairs_preview'] = $ability_data['content_reference_repairs_preview'];
			}

			$optimization_plan = self::media_optimization_plan_from_derivative_payload( $proposal_payload, $media_details_input );
			$response_payload  = array(
				'contract_version'        => 'media_derivative_cloud_optimization_payload.v1',
				'proposal_payload'        => $proposal_payload,
				'media_optimization_plan' => $optimization_plan,
				'core_proposal_required'  => true,
				'commit_execution'        => false,
				'proposal_ready'          => true === (bool) ( $optimization_plan['proposal_ready'] ?? false ),
				'preferred_core_route'    => 'POST /proposals/from-plan',
				'required_plan_ability_id' => 'npcink-abilities-toolkit/build-media-optimization-plan',
			);

			if ( is_array( $optimization_plan['write_actions'] ?? null ) && count( (array) $optimization_plan['write_actions'] ) >= 2 ) {
				$response_payload['from_plan_request'] = array(
					'plan_ability_id' => 'npcink-abilities-toolkit/build-media-optimization-plan',
					'plan'            => $optimization_plan,
				);
				$response_payload['next_step'] = 'POST /proposals/from-plan with from_plan_request for one Core batch proposal.';
			} else {
				$response_payload['next_step'] = 'Provide reviewed media_details_input, then build the media derivative optimization payload again and submit the returned from_plan_request to Core; do not split one optimize-media intent into two proposals.';
			}

			return $response_payload;
		}

		/**
		 * Builds the Core from-plan media optimization payload shape.
		 *
		 * @param array<string,mixed> $proposal_payload Cloud derivative proposal payload.
		 * @param array<string,mixed> $media_details_input Reviewed metadata action input.
		 * @return array<string,mixed>
		 */
		private static function media_optimization_plan_from_derivative_payload( array $proposal_payload, array $media_details_input ): array {
			$attachment_id  = absint( $proposal_payload['attachment_id'] ?? 0 );
			$artifact       = is_array( $proposal_payload['artifact'] ?? null ) ? $proposal_payload['artifact'] : array();
			$original       = is_array( $proposal_payload['original'] ?? null ) ? $proposal_payload['original'] : array();
			$derivative     = is_array( $proposal_payload['derivative'] ?? null ) ? $proposal_payload['derivative'] : array();
			$metadata_input = self::sanitize_media_details_plan_input( $attachment_id, $media_details_input );

			$metadata_preview = array(
				'before' => array(),
				'after'  => array_diff_key( $metadata_input, array( 'attachment_id' => true ) ),
			);
			$derivative_preview = array(
				'before' => array(
					'mime_type'      => sanitize_text_field( (string) ( $original['mime_type'] ?? '' ) ),
					'width'          => absint( $original['width'] ?? 0 ),
					'height'         => absint( $original['height'] ?? 0 ),
					'filesize_bytes' => absint( $original['filesize_bytes'] ?? 0 ),
				),
				'after'  => array(
					'artifact_id'    => sanitize_text_field( (string) ( $artifact['artifact_id'] ?? '' ) ),
					'mime_type'      => sanitize_text_field( (string) ( $derivative['mime_type'] ?? ( $artifact['mime_type'] ?? '' ) ) ),
					'width'          => absint( $derivative['width'] ?? ( $artifact['width'] ?? 0 ) ),
					'height'         => absint( $derivative['height'] ?? ( $artifact['height'] ?? 0 ) ),
					'filesize_bytes' => absint( $derivative['filesize_bytes'] ?? ( $artifact['filesize_bytes'] ?? 0 ) ),
				),
			);
			$content_reference_repairs_preview = array();
			if ( is_array( $proposal_payload['content_reference_repairs_preview'] ?? null ) ) {
				$content_reference_repairs_preview = $proposal_payload['content_reference_repairs_preview'];
			} elseif ( is_array( $proposal_payload['derivative_preview']['content_reference_repairs'] ?? null ) ) {
				$content_reference_repairs_preview = $proposal_payload['derivative_preview']['content_reference_repairs'];
			} elseif ( is_array( $derivative['content_reference_repairs'] ?? null ) ) {
				$content_reference_repairs_preview = $derivative['content_reference_repairs'];
			}
			if ( ! empty( $content_reference_repairs_preview ) ) {
				$derivative_preview['content_reference_repairs'] = $content_reference_repairs_preview;
			}

			$plan = array(
				'artifact_type'      => 'media_optimization_plan',
				'version'            => 1,
				'batch_id'           => 'media_optimization_' . $attachment_id . '_' . gmdate( 'Ymd_His' ),
				'attachment_id'      => $attachment_id,
				'optimization_goal'  => 'image_seo_and_derivative_adoption',
				'requires_approval'  => true,
				'dry_run'            => true,
				'commit_execution'   => false,
				'proposal_mode'      => 'batch',
				'batch_approval'     => true,
				'action_count'       => 0,
				'action_ids'         => array(),
				'target_ability_ids' => array(),
				'metadata_preview'   => $metadata_preview,
				'derivative_preview' => $derivative_preview,
				'content_reference_repairs_preview' => $content_reference_repairs_preview,
				'preview'            => array(),
				'write_actions'      => array(),
				'requires_input'     => array(),
				'proposal_ready'     => false,
				'risk'               => array(
					'level'  => 'medium',
					'reason' => 'One attachment metadata update and one reviewed Cloud derivative adoption share one Core approval.',
				),
			);

			if ( $attachment_id <= 0 || empty( $artifact['artifact_id'] ) ) {
				$plan['requires_input'][] = 'valid_derivative_proposal_payload';
				return $plan;
			}

			if ( count( $metadata_input ) <= 1 ) {
				$plan['requires_input'][] = 'media_details_input';
				return $plan;
			}

			$derivative_input = array(
				'attachment_id'       => $attachment_id,
				'derivative_artifact' => $artifact,
			);
			$current_mime    = sanitize_text_field( (string) ( $original['mime_type'] ?? '' ) );
			$derivative_mime = sanitize_text_field( (string) ( $derivative['mime_type'] ?? ( $artifact['mime_type'] ?? '' ) ) );
			if ( '' !== $current_mime ) {
				$derivative_input['expected_current_mime_type'] = $current_mime;
			}
			if ( '' !== $derivative_mime ) {
				$derivative_input['expected_derivative_mime_type'] = $derivative_mime;
			}
			if ( ! empty( $content_reference_repairs_preview ) ) {
				$derivative_input['expected_content_reference_post_ids'] = array_slice(
					array_values(
						array_unique(
							array_filter(
								array_map(
									static function ( $repair ) {
										return absint( is_array( $repair ) ? ( $repair['post_id'] ?? 0 ) : 0 );
									},
									(array) ( $content_reference_repairs_preview['repairs'] ?? array() )
								)
							)
						)
					),
					0,
					50
				);
				$derivative_input['expected_content_reference_post_count'] = absint( $content_reference_repairs_preview['post_count'] ?? 0 );
				$derivative_input['expected_content_reference_replacement_count'] = absint( $content_reference_repairs_preview['replacement_count'] ?? 0 );
			}

			$write_actions = array(
				self::plan_action( 'update_media_details_' . $attachment_id, 'npcink-abilities-toolkit/update-media-details', $metadata_input, 'medium', 'Apply reviewed media SEO and source metadata as part of one media optimization approval.' ),
				self::plan_action( 'adopt_cloud_media_derivative_' . $attachment_id, 'npcink-abilities-toolkit/adopt-cloud-media-derivative', $derivative_input, 'medium', 'Adopt the reviewed Cloud derivative artifact as the attachment main file after Core approval.' ),
			);
			$action_ids = array_values(
				array_map(
					static function ( $action ) {
						return is_array( $action ) ? sanitize_key( (string) ( $action['action_id'] ?? '' ) ) : '';
					},
					$write_actions
				)
			);
			$target_ability_ids = array_values(
				array_unique(
					array_filter(
						array_map(
							static function ( $action ) {
								return is_array( $action ) ? sanitize_text_field( (string) ( $action['target_ability_id'] ?? '' ) ) : '';
							},
							$write_actions
						)
					)
				)
			);
			$plan['write_actions']      = $write_actions;
			$plan['action_count']       = count( $plan['write_actions'] );
			$plan['action_ids']         = $action_ids;
			$plan['target_ability_ids'] = $target_ability_ids;
			$plan['proposal_ready']     = true;
			$plan['preview'][]          = array(
				'attachment_id'    => $attachment_id,
				'before'           => array(
					'metadata'   => array(),
					'derivative' => $derivative_preview['before'],
				),
				'after_suggestion' => array(
					'metadata'   => $metadata_preview['after'],
					'derivative' => $derivative_preview['after'],
				),
				'action_ids'         => $action_ids,
				'target_ability_ids' => $target_ability_ids,
			);

			return $plan;
		}

		/**
		 * Sanitizes update-media-details input for a generated plan.
		 *
		 * @param int                 $attachment_id Attachment id.
		 * @param array<string,mixed> $input Raw metadata input.
		 * @return array<string,mixed>
		 */
		private static function sanitize_media_details_plan_input( int $attachment_id, array $input ): array {
			$output = array( 'attachment_id' => $attachment_id );
			foreach ( array( 'title', 'alt', 'caption', 'description', 'source_page_url', 'photographer_name', 'attribution_text', 'copyright_notice' ) as $field ) {
				if ( array_key_exists( $field, $input ) && '' !== (string) $input[ $field ] ) {
					$output[ $field ] = 'source_page_url' === $field ? esc_url_raw( (string) $input[ $field ] ) : sanitize_text_field( (string) $input[ $field ] );
				}
			}
			if ( array_key_exists( 'source_type', $input ) ) {
				$source_type = sanitize_key( (string) $input['source_type'] );
				if ( in_array( $source_type, array( 'owned', 'ai_generated', 'stock', 'external', 'test' ), true ) ) {
					$output['source_type'] = $source_type;
				}
			}
			return $output;
		}

		/**
		 * Builds one local-host plan action.
		 *
		 * @param string              $action_id Action id.
		 * @param string              $ability_id Target ability id.
		 * @param array<string,mixed> $input Target ability input.
		 * @param string              $risk Risk.
		 * @param string              $reason Reason.
		 * @return array<string,mixed>
		 */
		private static function plan_action( string $action_id, string $ability_id, array $input, string $risk, string $reason ): array {
			$input['dry_run'] = true;
			$input['commit']  = false;
			return array(
				'action_id'         => sanitize_key( $action_id ),
				'target_ability_id' => sanitize_text_field( $ability_id ),
				'input'             => $input,
				'requires_approval' => true,
				'commit_execution'  => false,
				'required_scopes'   => array( 'media.write' ),
				'risk'              => sanitize_key( $risk ),
				'reason'            => sanitize_text_field( $reason ),
				'requires_input'    => array(),
				'proposal_ready'    => true,
			);
		}

		/**
		 * Sanitizes bounded Cloud projection values recursively.
		 *
		 * @param mixed $value Raw value.
		 * @return mixed
		 */
		public static function sanitize_projection_value( $value ) {
			if ( is_array( $value ) ) {
				$clean = array();
				foreach ( $value as $key => $item ) {
					$clean[ is_int( $key ) ? $key : sanitize_key( (string) $key ) ] = self::sanitize_projection_value( $item );
				}
				return $clean;
			}

			if ( is_bool( $value ) || is_int( $value ) || is_float( $value ) || null === $value ) {
				return $value;
			}

			return sanitize_text_field( (string) $value );
		}

		/**
		 * Extracts ability response data.
		 *
		 * @param array<string,mixed> $ability_response Ability response envelope.
		 * @return array<string,mixed>
		 */
		public static function extract_contract_data( array $ability_response ): array {
			if ( is_array( $ability_response['data'] ?? null ) ) {
				return $ability_response['data'];
			}

			return $ability_response;
		}

		/**
		 * Extracts Cloud response data.
		 *
		 * @param array<string,mixed> $cloud_result Cloud response envelope.
		 * @return array<string,mixed>|WP_Error
		 */
		private static function extract_cloud_data( array $cloud_result ) {
			$expected_keys = array( 'run_id', 'status', 'job_type', 'created_at', 'updated_at', 'artifact', 'warnings', 'error' );
			$actual_keys = array_keys( $cloud_result );
			$canary_projection = $cloud_result['governance_canary'] ?? null;
			if ( is_array( $canary_projection ) ) {
				$actual_keys = array_values( array_diff( $actual_keys, array( 'governance_canary' ) ) );
			}
			if (
				count( $expected_keys ) !== count( $actual_keys )
				|| array() !== array_diff( $expected_keys, $actual_keys )
				|| array() !== array_diff( $actual_keys, $expected_keys )
				|| ! is_array( $cloud_result['artifact'] ?? null )
			) {
				return new WP_Error(
					'cloud_media_derivative_projection_invalid',
					__( 'Local media derivative proposal building requires the exact Addon run-result projection.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}
			$artifact = Npcink_Cloud_Media_Artifact_Verification::normalize_artifact_descriptor( $cloud_result['artifact'], 'derivative' );
			if ( is_wp_error( $artifact ) ) {
				return $artifact;
			}
			$data = $cloud_result;
			unset( $data['governance_canary'] );
			$data['artifact'] = $artifact;

			return $data;
		}

		/**
		 * Normalizes media metrics for proposal display.
		 *
		 * @param mixed $metrics Raw metrics.
		 * @return array<string,mixed>
		 */
		private static function normalize_media_metrics( $metrics ): array {
			$metrics = is_array( $metrics ) ? $metrics : array();
			$raw_mime_type = (string) ( $metrics['mime_type'] ?? '' );

			return array(
				'width'          => absint( $metrics['width'] ?? 0 ),
				'height'         => absint( $metrics['height'] ?? 0 ),
				'filesize_bytes' => absint( $metrics['filesize_bytes'] ?? $metrics['size_bytes'] ?? 0 ),
				'mime_type'      => Npcink_Cloud_Media_Artifact_Verification::normalize_media_type( $raw_mime_type ),
			);
		}

		/**
		 * Validates that the adopted artifact matches the Cloud result summary.
		 *
		 * @param array<string,mixed> $cloud_data Cloud result data.
		 * @param array<string,mixed> $artifact Normalized derivative artifact.
		 * @return true|WP_Error
		 */
		private static function validate_derivative_artifact_binding( array $cloud_data, array $artifact ) {
			$result_artifact = is_array( $cloud_data['artifact'] ?? null ) ? $cloud_data['artifact'] : array();
			foreach ( array( 'artifact_id', 'expires_at', 'mime_type', 'format', 'width', 'height', 'filesize_bytes', 'checksum' ) as $field ) {
				if ( ! array_key_exists( $field, $result_artifact ) || $result_artifact[ $field ] !== ( $artifact[ $field ] ?? null ) ) {
					return new WP_Error(
						'cloud_media_derivative_artifact_binding_mismatch',
						__( 'Derivative artifact facts do not match the Cloud result.', 'npcink-cloud-addon' ),
						array( 'status' => 409 )
					);
				}
			}
			if ( Npcink_Cloud_Media_Artifact_Verification::normalize_sha256( (string) $result_artifact['checksum'] ) !== Npcink_Cloud_Media_Artifact_Verification::normalize_sha256( (string) $artifact['checksum'] ) ) {
				return new WP_Error(
					'cloud_media_derivative_artifact_checksum_mismatch',
					__( 'Derivative artifact checksum does not match the Cloud result.', 'npcink-cloud-addon' ),
					array( 'status' => 409 )
				);
			}

			return true;
		}

		/**
		 * Adds explicit warnings when proposal comparison metrics are incomplete.
		 *
		 * @param array<int,string>    $warnings Existing warnings.
		 * @param array<string,mixed> $original Original metrics.
		 * @param array<string,mixed> $derivative Derivative metrics.
		 * @return array<int,string>
		 */
		private static function append_metric_warnings( array $warnings, array $original, array $derivative ): array {
			if ( ! self::has_complete_media_metrics( $original ) ) {
				$warnings[] = __( 'Original media metrics are incomplete.', 'npcink-cloud-addon' );
			}
			if ( ! self::has_complete_media_metrics( $derivative ) ) {
				$warnings[] = __( 'Derivative media metrics are incomplete.', 'npcink-cloud-addon' );
			}

			return $warnings;
		}

		/**
		 * Returns whether media comparison metrics have the fields needed by UI.
		 *
		 * @param array<string,mixed> $metrics Normalized metrics.
		 * @return bool
		 */
		private static function has_complete_media_metrics( array $metrics ): bool {
			return absint( $metrics['width'] ?? 0 ) > 0
				&& absint( $metrics['height'] ?? 0 ) > 0
				&& absint( $metrics['filesize_bytes'] ?? 0 ) > 0
				&& '' !== (string) ( $metrics['mime_type'] ?? '' );
		}

		/**
		 * Builds a conservative byte savings estimate.
		 *
		 * @param array<string,mixed> $original Original metrics.
		 * @param array<string,mixed> $derivative Derivative metrics.
		 * @return array<string,mixed>
		 */
		private static function build_savings_estimate( array $original, array $derivative ): array {
			$original_bytes = absint( $original['filesize_bytes'] ?? 0 );
			$derivative_bytes = absint( $derivative['filesize_bytes'] ?? 0 );
			$saved_bytes = max( 0, $original_bytes - $derivative_bytes );
			$ratio = $original_bytes > 0 ? $saved_bytes / $original_bytes : 0;

			return array(
				'original_bytes'   => $original_bytes,
				'derivative_bytes' => $derivative_bytes,
				'saved_bytes'      => $saved_bytes,
				'percent'          => round( $ratio * 100, 2 ),
			);
		}

		/**
		 * Sanitizes a compact Cloud result summary.
		 *
		 * @param array<string,mixed> $cloud_data Cloud data.
		 * @return array<string,mixed>
		 */
		private static function sanitize_cloud_result_summary( array $cloud_data ): array {
			$artifact = is_array( $cloud_data['artifact'] ?? null ) ? $cloud_data['artifact'] : array();

			return array(
				'run_id' => sanitize_text_field( (string) ( $cloud_data['run_id'] ?? '' ) ),
				'status' => sanitize_key( (string) ( $cloud_data['status'] ?? '' ) ),
				'warnings' => self::sanitize_string_list( $cloud_data['warnings'] ?? array() ),
				'artifact_id' => sanitize_text_field( (string) ( $artifact['artifact_id'] ?? '' ) ),
			);
		}

		/**
		 * Sanitizes a string list.
		 *
		 * @param mixed $items Raw list.
		 * @return array<int,string>
		 */
		private static function sanitize_string_list( $items ): array {
			$items = is_array( $items ) ? $items : array();
			$normalized = array();
			foreach ( $items as $item ) {
				$value = sanitize_text_field( (string) $item );
				if ( '' !== $value ) {
					$normalized[] = $value;
				}
			}

			return array_values( array_unique( $normalized ) );
		}
		/**
		 * Sanitizes and byte-bounds one public projection string.
		 *
		 * @param mixed $value Raw value.
		 * @param int   $max_bytes Maximum byte length.
		 * @return string
		 */
		public static function bounded_projection_text( $value, int $max_bytes ): string {
			$text = sanitize_text_field( is_scalar( $value ) ? (string) $value : '' );
			if ( strlen( $text ) <= $max_bytes ) {
				return $text;
			}

			return substr( $text, 0, $max_bytes );
		}

		/**
		 * Projects a bounded warning list from one status response.
		 *
		 * @param mixed $warnings Cloud warnings.
		 * @return array<int,string>
		 */
		public static function bounded_projection_warnings( $warnings ): array {
			if ( ! is_array( $warnings ) ) {
				return array();
			}

			$projection = array();
			foreach ( array_slice( $warnings, 0, Npcink_Cloud_Media_Artifact_Verification::MAX_PROCESSING_WARNINGS ) as $warning ) {
				if ( ! is_string( $warning ) ) {
					continue;
				}
					$value = self::bounded_projection_text( $warning, Npcink_Cloud_Media_Artifact_Verification::MAX_PROCESSING_WARNING_BYTES );
				if ( '' !== $value ) {
					$projection[] = $value;
				}
			}

			return array_values( array_unique( $projection ) );
		}

		/**
		 * Validates the local ability contract before any Cloud dispatch.
		 *
		 * @param array<string,mixed> $contract Ability contract data.
		 * @return true|WP_Error
		 */
		public static function validate_request_contract( array $contract ) {
			if ( self::REQUEST_CONTRACT_VERSION !== (string) ( $contract['request_contract_version'] ?? '' ) ) {
				return new WP_Error(
					'cloud_media_derivative_contract_invalid',
					__( 'Media derivative request contract version is invalid.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}
			if ( empty( $contract['readonly'] ) || empty( $contract['proposal_only'] ) ) {
				return new WP_Error(
					'cloud_media_derivative_contract_not_readonly',
					__( 'Media derivative request must be read-only and proposal-only.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}
			if ( 'local_wordpress_host' !== (string) ( $contract['local_adoption']['final_write_owner'] ?? '' ) ) {
				return new WP_Error(
					'cloud_media_derivative_write_owner_invalid',
					__( 'Media derivative final write owner must remain the local WordPress host.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}
			if ( ! empty( $contract['local_adoption']['wordpress_write_included'] ) ) {
				return new WP_Error(
					'cloud_media_derivative_wordpress_write_present',
					__( 'Media derivative request must not include WordPress writes.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}
			if ( self::contains_forbidden_secret_fields( $contract ) ) {
				return new WP_Error(
					'cloud_media_derivative_credentials_present',
					__( 'Media derivative ability payload must not include credentials or signed headers.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}

			$job_payload = is_array( $contract['cloud_job_payload'] ?? null ) ? $contract['cloud_job_payload'] : array();
			if ( 'generate_optimized_media_derivative' !== (string) ( $job_payload['job_type'] ?? '' ) ) {
				return new WP_Error(
					'cloud_media_derivative_job_type_invalid',
					__( 'Cloud media derivative job type is invalid.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}
			if ( ! empty( $job_payload['requested_derivative']['replace_original'] ) ) {
				return new WP_Error(
					'cloud_media_derivative_replace_original_requested',
					__( 'Media derivative jobs must not request original file replacement.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}

			return true;
		}
		/**
		 * Recursively detects credential or signed-header fields.
		 *
		 * @param mixed $value Payload value.
		 * @return bool
		 */
		public static function contains_forbidden_secret_fields( $value ): bool {
			if ( ! is_array( $value ) ) {
				return false;
			}

			$forbidden = array(
				'api_key',
				'authorization',
				'credentials',
				'key_id',
				'secret',
				'signed_headers',
				'signature',
				'token',
				'x_magick_key_id',
				'x_magick_signature',
				'x_magick_site_id',
			);

			foreach ( $value as $key => $item ) {
				$normalized_key = strtolower( str_replace( '-', '_', (string) $key ) );
				if ( in_array( $normalized_key, $forbidden, true ) ) {
					return true;
				}
				if ( self::contains_forbidden_secret_fields( $item ) ) {
					return true;
				}
			}

			return false;
		}
	}
}
