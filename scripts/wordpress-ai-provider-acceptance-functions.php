<?php
/** Development acceptance helpers; no Provider execution or WordPress writes. */

/** Preserve case evidence independently from the overall acceptance outcome. */
function npcink_cloud_acceptance_finalize_report( array $report ): array {
	$report['cases'] = $report['cases'] ?? array();
	$passed = 0;
	foreach ( $report['cases'] as &$case ) {
		$quality_passed = 'passed' === ( $case['quality_status'] ?? '' );
		$passed += $quality_passed ? 1 : 0;
		$write_detected = $case['write_detected'] ?? null;
		if ( ! $quality_passed || true === $write_detected ) {
			$case['evidence_state'] = 'local_failed';
		} elseif ( false === $write_detected ) {
			$case['evidence_state'] = 'local_verified';
		} else {
			$case['evidence_state'] = 'local_executed';
		}
	}
	unset( $case );
	$report['passed'] = $passed;
	$report['failed'] = count( $report['cases'] ) - $passed;
	$report['quality_status'] = ! empty( $report['cases'] ) && 0 === $report['failed'] ? 'passed' : 'failed';
	$states = array_column( $report['cases'], 'evidence_state' );
	if ( empty( $states ) || in_array( 'local_failed', $states, true ) || true === ( $report['write_detected'] ?? null ) ) {
		$report['evidence_state'] = 'local_failed';
	} elseif ( in_array( 'local_executed', $states, true ) || false !== ( $report['write_detected'] ?? null ) ) {
		$report['evidence_state'] = 'local_executed';
	} else {
		$report['evidence_state'] = 'local_verified';
	}
	return $report;
}

/**
 * Normalizes the bounded AI-credit summary used by the acceptance preflight.
 *
 * This parser intentionally accepts only the existing entitlement projection;
 * it does not infer quota from runtime failures or expose the raw response.
 *
 * @param mixed $response Read-only entitlement response.
 * @return array<string,mixed>
 */
function npcink_cloud_acceptance_quota_preflight_from_response( $response ): array {
	$unavailable = array(
		'state'     => 'unavailable',
		'error_code' => 'entitlement_unavailable',
		'unit'      => '',
		'used'      => null,
		'limit'     => null,
		'remaining' => null,
	);
	if ( is_wp_error( $response ) ) {
		$error_code = (string) $response->get_error_code();
		return array_merge( $unavailable, array( 'error_code' => '' !== $error_code ? sanitize_key( $error_code ) : 'entitlement_unavailable' ) );
	}
	$summary = is_array( $response ) ? ( $response['data']['quota_summary']['ai_credit_usage_detail']['summary'] ?? null ) : null;
	if ( ! is_array( $summary ) || 'ai_credits' !== ( $summary['unit'] ?? null ) ) {
		return array_merge( $unavailable, array( 'error_code' => 'invalid_entitlement_summary' ) );
	}
	if ( ! is_numeric( $summary['used'] ?? null ) || ! is_numeric( $summary['limit'] ?? null ) ) {
		return array_merge( $unavailable, array( 'error_code' => 'invalid_entitlement_summary', 'unit' => 'ai_credits' ) );
	}
	if ( ! is_numeric( $summary['remaining'] ?? null ) ) {
		return array_merge( $unavailable, array( 'error_code' => 'entitlement_remaining_unknown', 'unit' => 'ai_credits' ) );
	}
	$used      = (float) $summary['used'];
	$limit     = (float) $summary['limit'];
	$remaining = (float) $summary['remaining'];
	if ( ! is_finite( $used ) || ! is_finite( $limit ) || ! is_finite( $remaining ) || $used < 0 || $limit < 0 ) {
		return array_merge( $unavailable, array( 'error_code' => 'invalid_entitlement_summary', 'unit' => 'ai_credits' ) );
	}
	return array(
		'state'      => $remaining <= 0 ? 'quota_exhausted' : 'available',
		'error_code' => null,
		'unit'       => 'ai_credits',
		'used'       => $used,
		'limit'      => $limit,
		'remaining'  => $remaining,
	);
}

/**
 * Performs a read-only quota check before a batch acceptance run.
 *
 * An unavailable entitlement does not block the run, because the existing
 * request path remains the source of runtime diagnostics. An exhausted quota
 * is safe to block before any Provider call.
 *
 * @return array<string,mixed>
 */
function npcink_cloud_acceptance_quota_preflight(): array {
	if ( ! function_exists( 'npcink_cloud_addon_get_toolbox_runtime_entitlement' ) ) {
		return npcink_cloud_acceptance_quota_preflight_from_response( new WP_Error( 'entitlement_helper_unavailable' ) );
	}
	$trace_id = function_exists( 'wp_generate_uuid4' ) ? 'wp-ai-acceptance-preflight-' . wp_generate_uuid4() : 'wp-ai-acceptance-preflight';
	return npcink_cloud_acceptance_quota_preflight_from_response( npcink_cloud_addon_get_toolbox_runtime_entitlement( $trace_id ) );
}

/** Classify contract failures for development evidence without changing the official error shape. */
function npcink_cloud_acceptance_contract_status( $contract ): string {
	if ( is_array( $contract ) ) {
		return (string) ( $contract['verification_state'] ?? 'unsupported' );
	}
	if ( is_wp_error( $contract ) ) {
		$code = (string) $contract->get_error_code();
		if ( false !== strpos( $code, 'schema_hash' ) || false !== strpos( $code, 'contract_drift' ) || 'cloud_ai_task_contract_not_current' === $code ) {
			return 'contract_drift';
		}
	}
	return 'unsupported';
}

function npcink_cloud_acceptance_string( $value ) {
	if ( is_string( $value ) ) {
		return trim( $value );
	}
	if ( is_array( $value ) ) {
		if ( isset( $value['description']['text'] ) && is_string( $value['description']['text'] ) ) {
			return trim( $value['description']['text'] );
		}
		foreach ( array( 'text', 'content', 'summary', 'title', 'description', 'slug' ) as $key ) {
			if ( isset( $value[ $key ] ) && is_string( $value[ $key ] ) ) {
				return trim( $value[ $key ] );
			}
		}
	}
	return '';
}

/**
 * Projects payload-free runtime failure evidence into one acceptance case.
 *
 * Keeping this normalization outside the WP-CLI loop makes the diagnostic
 * contract directly testable without coupling tests to source formatting.
 *
 * @param array<string,mixed> $case             Acceptance case.
 * @param array<string,mixed> $failure_evidence Connector failure evidence.
 * @return array<string,mixed>
 */
function npcink_cloud_acceptance_attach_failure_evidence( array $case, array $failure_evidence ): array {
	if ( empty( $failure_evidence ) ) {
		return $case;
	}

	if ( empty( $case['provider_run_id'] ) ) {
		$case['provider_run_id'] = (string) ( $failure_evidence['run_id'] ?? '' ) ?: null;
	}
	$case['failure_stage']    = ( (string) ( $failure_evidence['error_stage'] ?? '' ) ) ?: null;
	$case['quality_reason']   = ( (string) ( $failure_evidence['quality_reason'] ?? '' ) ) ?: null;
	$case['cloud_error_code'] = ( (string) ( $failure_evidence['cloud_error_code'] ?? '' ) ) ?: null;

	return $case;
}

/**
 * Build non-secret context for development quality reports.
 * Article text, prompts, credentials, and generated output are excluded.
 */
function npcink_cloud_acceptance_quality_context( $ability, array $input, $data = null ): array {
	$input_json = wp_json_encode( $input, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION );
	$context    = array(
		'input_fields'      => array_keys( $input ),
		'input_json_bytes'  => false === $input_json ? null : strlen( $input_json ),
		'source_characters' => isset( $input['content'] ) && is_string( $input['content'] ) ? ( function_exists( 'mb_strlen' ) ? mb_strlen( $input['content'] ) : strlen( $input['content'] ) ) : null,
	);
	sort( $context['input_fields'] );
	foreach ( array( 'target_language', 'source_language', 'taxonomy', 'strategy', 'max_suggestions', 'tone' ) as $key ) {
		if ( array_key_exists( $key, $input ) && ( is_scalar( $input[ $key ] ) || null === $input[ $key ] ) ) {
			$context[ $key ] = $input[ $key ];
		}
	}
	if ( isset( $input['review_types'] ) && is_array( $input['review_types'] ) ) {
		$context['review_types'] = array_values( array_filter( array_map( 'strval', $input['review_types'] ) ) );
	}
	if ( 'ai/alt-text-generation' === $ability ) {
		$context['media_input_kind'] = array_key_exists( 'attachment_id', $input ) ? 'attachment' : ( array_key_exists( 'image_url', $input ) ? 'url' : 'missing' );
		$context['has_context']      = isset( $input['context'] ) && is_string( $input['context'] ) && '' !== trim( $input['context'] );
	} elseif ( 'ai/image-prompt-generation' === $ability ) {
		$context['has_context'] = isset( $input['context'] ) && is_string( $input['context'] ) && '' !== trim( $input['context'] );
		$context['has_style']   = isset( $input['style'] ) && is_string( $input['style'] ) && '' !== trim( $input['style'] );
	} elseif ( 'ai/image-generation' === $ability ) {
		$context['requested_count'] = isset( $input['n'] ) && is_numeric( $input['n'] ) ? (int) $input['n'] : null;
		$context['aspect_ratio']    = isset( $input['aspect_ratio'] ) && is_scalar( $input['aspect_ratio'] ) ? (string) $input['aspect_ratio'] : null;
		$context['resolution']      = isset( $input['resolution'] ) && is_scalar( $input['resolution'] ) ? (string) $input['resolution'] : null;
	}
	if ( 'ai/content-translation' === $ability && isset( $input['content'] ) && is_string( $input['content'] ) && function_exists( 'parse_blocks' ) && false !== strpos( $input['content'], '<!-- wp:' ) ) {
		$context['structure_kind']        = 'gutenberg_blocks';
		$context['expected_block_count']  = count( parse_blocks( $input['content'] ) );
		$translated                       = npcink_cloud_acceptance_string( $data );
		$context['translated_block_count'] = false !== strpos( $translated, '<!-- wp:' ) ? count( parse_blocks( $translated ) ) : 0;
	} elseif ( 'ai/content-translation' === $ability && isset( $input['content'] ) && is_string( $input['content'] ) ) {
		$context['structure_kind'] = preg_match( '/<\/?[a-z][^>]*>/i', $input['content'] ) ? 'html' : 'plain_text';
	}
	$output_text = npcink_cloud_acceptance_string( $data );
	if ( '' !== $output_text ) {
		$context['output_characters'] = function_exists( 'mb_strlen' ) ? mb_strlen( $output_text ) : strlen( $output_text );
	}
	return $context;
}

function npcink_cloud_acceptance_shape_valid( $schema, $data ) {
	if ( ! is_array( $schema ) || empty( $schema ) || ! function_exists( 'rest_validate_value_from_schema' ) ) {
		return false;
	}
	return true === rest_validate_value_from_schema( $data, $schema, 'output' );
}

function npcink_cloud_acceptance_has_result( $ability, $data ) {
	if ( 'ai/editorial-notes' === $ability ) {
		return is_array( $data ) && ! empty( $data['suggestions'] );
	}
	if ( 'ai/comment-analysis' === $ability ) {
		return is_array( $data )
			&& array_key_exists( 'comment_id', $data )
			&& isset( $data['sentiment'] )
			&& array_key_exists( 'toxicity_score', $data );
	}
	if ( 'ai/alt-text-generation' === $ability ) {
		return is_array( $data )
			&& isset( $data['alt_text'] )
			&& is_string( $data['alt_text'] )
			&& array_key_exists( 'is_decorative', $data );
	}
	if ( 'ai/image-prompt-generation' === $ability ) {
		return '' !== npcink_cloud_acceptance_string( $data );
	}
	if ( 'ai/image-generation' === $ability ) {
		return is_array( $data ) && ! empty( $data['artifacts'] ) && is_array( $data['artifacts'] );
	}
	if ( 'ai/content-classification' === $ability ) {
		return is_array( $data ) && ! empty( $data['suggestions'] ) && is_array( $data['suggestions'] );
	}
	return '' !== npcink_cloud_acceptance_string( $data ) || ( is_array( $data ) && ! empty( $data['slugs'] ) );
}

function npcink_cloud_acceptance_failure_code( $status, $data, $shape_valid ) {
	if ( 401 === $status || 403 === $status ) {
		return 'permission_denied';
	}
	if ( 404 === $status ) {
		return 'ability_not_found';
	}
	if ( is_array( $data ) ) {
		$error_code = (string) ( $data['code'] ?? $data['error'] ?? '' );
		if ( 'ability_invalid_input' === $error_code ) {
			return 'input_projection_invalid';
		}
		if ( false !== strpos( $error_code, 'provider' ) || 'unsupported_model' === $error_code || 'no_provider' === $error_code ) {
			return 'provider_unavailable';
		}
	}
	if ( $status >= 500 ) {
		return 'provider_execution_failed';
	}
	if ( ! $shape_valid ) {
		return 'empty_or_invalid_output';
	}
	return null;
}

/** Fixed-fixture quality checks; passing these is not human acceptance. */
function npcink_cloud_acceptance_is_empty_taxonomy_error( $ability, $data ): bool {
	return 'ai/content-classification' === $ability
		&& is_array( $data )
		&& 'no_results' === (string) ( $data['code'] ?? '' );
}

function npcink_cloud_acceptance_quality_failure( $ability, $data, array $input = array() ) {
	if ( npcink_cloud_acceptance_is_empty_taxonomy_error( $ability, $data ) ) {
		return 'taxonomy_empty';
	}
	if ( 'ai/content-classification' === $ability && is_array( $data ) && array_key_exists( 'suggestions', $data ) && empty( $data['suggestions'] ) ) {
		return 'taxonomy_empty';
	}
	if ( 'ai/editorial-notes' === $ability && is_array( $data ) && array_key_exists( 'suggestions', $data ) && empty( $data['suggestions'] ) ) {
		return 'editorial_empty';
	}
	if ( 'ai/image-prompt-generation' === $ability && '' === npcink_cloud_acceptance_string( $data ) ) {
		return 'image_prompt_empty';
	}
	if ( 'ai/image-generation' === $ability ) {
		if ( ! is_array( $data )
			|| 'image_generation_result.v1' !== (string) ( $data['contract_version'] ?? '' )
			|| 'image_generation_artifacts' !== (string) ( $data['artifact_type'] ?? '' )
			|| 'image.generate.v1' !== (string) ( $data['operation'] ?? '' ) ) {
			return 'image_generation_contract_invalid';
		}
		if ( true !== ( $data['suggestion_only'] ?? false ) || true !== ( $data['requires_local_review'] ?? false ) ) {
			return 'image_generation_write_posture_invalid';
		}
		$artifacts = is_array( $data['artifacts'] ?? null ) ? $data['artifacts'] : array();
		if ( empty( $artifacts ) || count( $artifacts ) > 4 ) {
			return 'image_generation_artifact_invalid';
		}
		$forbidden_keys = array( 'b64_json', 'base64', 'bytes', 'download_url', 'source_url', 'storage_key', 'url' );
		foreach ( $artifacts as $artifact ) {
			if ( ! is_array( $artifact ) ) {
				return 'image_generation_artifact_invalid';
			}
			$artifact_id  = (string) ( $artifact['artifact_id'] ?? '' );
			$reference_id = is_array( $artifact['artifact_reference'] ?? null ) ? (string) ( $artifact['artifact_reference']['artifact_id'] ?? '' ) : '';
			$checksum     = (string) ( $artifact['checksum'] ?? '' );
			$valid        = '' !== $artifact_id
				&& $artifact_id === $reference_id
				&& 'available' === (string) ( $artifact['status'] ?? '' )
				&& 'image' === (string) ( $artifact['media_kind'] ?? '' )
				&& 'image.generate.v1' === (string) ( $artifact['operation'] ?? '' )
				&& str_starts_with( strtolower( (string) ( $artifact['content_type'] ?? '' ) ), 'image/' )
				&& is_int( $artifact['width'] ?? null ) && 0 < $artifact['width']
				&& is_int( $artifact['height'] ?? null ) && 0 < $artifact['height']
				&& is_int( $artifact['filesize_bytes'] ?? null ) && 0 < $artifact['filesize_bytes']
				&& 1 === preg_match( '/^sha256:[a-f0-9]{64}$/', $checksum );
			foreach ( $forbidden_keys as $forbidden_key ) {
				if ( array_key_exists( $forbidden_key, $artifact ) ) {
					$valid = false;
				}
			}
			if ( ! $valid ) {
				return 'image_generation_artifact_invalid';
			}
		}
	}
	if ( ! npcink_cloud_acceptance_has_result( $ability, $data ) ) {
		return 'empty_result';
	}
	$text = npcink_cloud_acceptance_string( $data );
	if ( 'ai/content-translation' === $ability && preg_match( '/[\x{4e00}-\x{9fff}]/u', $text ) ) {
		return 'target_language_mismatch';
	}
	if ( 'ai/editorial-updates' === $ability && preg_match( '/(?:please|could you|can you)\s+(?:provide|share|send|paste)|(?:no|without)\s+(?:paragraph|source|content)\b|(?:no specific|missing).{0,20}(?:block|content|notes)|\[no specific block content|请.{0,8}(?:提供|发送|粘贴).{0,8}(?:原文|段落|内容)/iu', $text ) ) {
		return 'task_not_completed';
	}
	if ( 'ai/slug-generation' === $ability ) {
		$seen_slugs = array();
		foreach ( $data['slugs'] ?? array() as $slug ) {
			if ( ! is_string( $slug ) || 1 !== preg_match( '/^(?=.*[a-z])[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug ) ) {
				return 'slug_format_invalid';
			}
			$normalized_slug = strtolower( $slug );
			if ( isset( $seen_slugs[ $normalized_slug ] ) ) {
				return 'slug_duplicate';
			}
			$seen_slugs[ $normalized_slug ] = true;
		}
	}
	if ( 'ai/comment-analysis' === $ability ) {
		if ( ! is_array( $data ) || ! in_array( (string) ( $data['sentiment'] ?? '' ), array( 'positive', 'neutral', 'negative' ), true ) ) {
			return 'comment_analysis_invalid';
		}
		$toxicity = $data['toxicity_score'] ?? null;
		if ( ! is_numeric( $toxicity ) || (float) $toxicity < 0.0 || (float) $toxicity > 1.0 ) {
			return 'comment_analysis_invalid';
		}
	}
	if ( 'ai/content-classification' === $ability ) {
		$max_suggestions = isset( $input['max_suggestions'] ) && is_numeric( $input['max_suggestions'] ) ? (int) $input['max_suggestions'] : 0;
		if ( 0 < $max_suggestions && count( $data['suggestions'] ?? array() ) > $max_suggestions ) {
			return 'classification_too_many';
		}
		$existing_only = 'existing_only' === (string) ( $input['strategy'] ?? 'existing_only' );
		foreach ( $data['suggestions'] ?? array() as $suggestion ) {
			if ( ! is_array( $suggestion ) || '' === trim( (string) ( $suggestion['term'] ?? '' ) ) ) {
				return 'classification_suggestion_invalid';
			}
			if ( $existing_only && array_key_exists( 'is_new', $suggestion ) && true === $suggestion['is_new'] ) {
				return 'classification_new_term';
			}
			$confidence = $suggestion['confidence'] ?? null;
			if ( null !== $confidence && ( ! is_numeric( $confidence ) || (float) $confidence < 0.0 || (float) $confidence > 1.0 ) ) {
				return 'classification_confidence_invalid';
			}
		}
	}
	if ( 'ai/alt-text-generation' === $ability && ( ! is_array( $data ) || ! is_bool( $data['is_decorative'] ?? null ) ) ) {
		return 'alt_text_output_invalid';
	}
	if ( 'ai/alt-text-generation' === $ability && is_array( $data ) && false === (bool) $data['is_decorative'] && '' === trim( (string) ( $data['alt_text'] ?? '' ) ) ) {
		return 'alt_text_missing';
	}
	return null;
}
