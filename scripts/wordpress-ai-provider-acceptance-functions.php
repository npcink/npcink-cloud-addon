<?php
/** Acceptance checks only; no runtime, transport, or WordPress writes. */

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
 * Classifies contract projection state for the development-only report.
 *
 * @param mixed $contract Projected contract or WP_Error.
 * @return string
 */
function npcink_cloud_acceptance_contract_status( $contract ): string {
	if ( is_array( $contract ) ) {
		return (string) ( $contract['verification_state'] ?? 'mapping_current' );
	}
	if ( is_wp_error( $contract ) ) {
		$code = (string) $contract->get_error_code();
		return ( false !== strpos( $code, 'contract' ) || false !== strpos( $code, 'schema' ) ) ? 'contract_drift' : 'unsupported';
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
		$error_code = npcink_cloud_acceptance_remote_error_code( $data );
		if ( 'ability_invalid_input' === $error_code ) {
			return 'input_projection_invalid';
		}
		if ( 'provider.output_quality_rejected' === $error_code || false !== strpos( $error_code, 'translation_' ) ) {
			return 'output_quality_rejected';
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

/**
 * Returns the first bounded Cloud/REST diagnostic code without retaining content.
 *
 * Cloud errors may be wrapped several times by REST and the Addon transport.
 * Acceptance reports need the machine code, while the official WordPress AI
 * result must continue to receive its existing shape and message.
 *
 * @param mixed $data Error or response data.
 * @param int   $depth Recursion guard.
 * @return string
 */
function npcink_cloud_acceptance_remote_error_code( $data, $depth = 0 ) {
	if ( $depth > 3 || ! is_array( $data ) ) {
		return '';
	}

	foreach ( array( 'output_quality_reason', 'cloud_error_code', 'error_code', 'code', 'error' ) as $key ) {
		if ( isset( $data[ $key ] ) && is_scalar( $data[ $key ] ) && '' !== trim( (string) $data[ $key ] ) ) {
			$code = preg_replace( '/[^a-zA-Z0-9_.-]+/', '_', trim( (string) $data[ $key ] ) );
			return is_string( $code ) ? strtolower( $code ) : '';
		}
	}

	foreach ( array( 'data', 'cloud_error_data', 'error', 'result' ) as $key ) {
		if ( isset( $data[ $key ] ) ) {
			$code = npcink_cloud_acceptance_remote_error_code( $data[ $key ], $depth + 1 );
			if ( '' !== $code ) {
				return $code;
			}
		}
	}

	return '';
}

/**
 * Returns the raw machine diagnostic code for a response, when one exists.
 *
 * This field is intended for developer acceptance evidence only. It is never
 * copied into the official WordPress AI result or editor notice.
 *
 * @param int   $status HTTP status.
 * @param mixed $data Response data.
 * @return string|null
 */
function npcink_cloud_acceptance_diagnostic_code( $status, $data ) {
	$code = is_array( $data ) ? npcink_cloud_acceptance_output_quality_reason( $data ) : '';
	if ( '' === $code && is_array( $data ) ) {
		$code = npcink_cloud_acceptance_remote_error_code( $data );
	}
	return '' !== $code ? $code : null;
}

/**
 * Finds a nested Cloud output-quality subtype before the generic rejection code.
 *
 * @param mixed $data Error or response data.
 * @param int   $depth Recursion guard.
 * @return string
 */
function npcink_cloud_acceptance_output_quality_reason( $data, $depth = 0 ) {
	if ( $depth > 3 || ! is_array( $data ) ) {
		return '';
	}
	if ( isset( $data['output_quality_reason'] ) && is_scalar( $data['output_quality_reason'] ) ) {
		$reason = preg_replace( '/[^a-zA-Z0-9_.-]+/', '_', trim( (string) $data['output_quality_reason'] ) );
		return is_string( $reason ) ? strtolower( $reason ) : '';
	}
	foreach ( array( 'data', 'cloud_error_data', 'error', 'result' ) as $key ) {
		if ( isset( $data[ $key ] ) ) {
			$reason = npcink_cloud_acceptance_output_quality_reason( $data[ $key ], $depth + 1 );
			if ( '' !== $reason ) {
				return $reason;
			}
		}
	}
	return '';
}

/**
 * Classifies one block outcome using the official Content Translation rules.
 *
 * @param int   $status HTTP status.
 * @param mixed $data Response data.
 * @param bool  $shape_valid Whether the Ability output schema matched.
 * @param bool  $has_result Whether the output was non-empty.
 * @return string
 */
function npcink_cloud_acceptance_translation_block_status( $status, $data, $shape_valid, $has_result ) {
	if ( $has_result ) {
		return 'translated';
	}

	$diagnostic_code = npcink_cloud_acceptance_diagnostic_code( $status, $data );
	if ( 'provider.output_quality_rejected' === $diagnostic_code || ( is_string( $diagnostic_code ) && 0 === strpos( $diagnostic_code, 'translation_' ) ) ) {
		return 'output_quality_rejected';
	}
	if ( $status >= 500 || ( is_string( $diagnostic_code ) && false !== strpos( $diagnostic_code, 'provider' ) ) ) {
		return 'provider_failed';
	}
	if ( $status >= 400 ) {
		return 'request_failed';
	}
	if ( ! $shape_valid || ! $has_result ) {
		return 'output_empty_or_invalid';
	}

	return 'request_failed';
}

/**
 * Builds a development-only block diagnostic report without exposing content.
 *
 * The official plugin only submits non-empty core/paragraph and core/heading
 * blocks with at least five characters. Unsupported and short blocks are
 * classified locally and never sent to Cloud.
 *
 * @param array<int,array<string,mixed>> $blocks Block fixtures.
 * @param callable                       $runner Runs one eligible translation request.
 * @param string                         $target_language Target language.
 * @param int                            $minimum_length Official minimum.
 * @return array<string,mixed>
 */
function npcink_cloud_acceptance_translation_block_diagnostics( array $blocks, callable $runner, $target_language = 'en-us', $minimum_length = 5 ) {
	$records = array();
	$summary = array(
		'total_blocks'          => count( $blocks ),
		'eligible_blocks'       => 0,
		'translated'            => 0,
		'skipped_too_short'     => 0,
		'unsupported_block'     => 0,
		'provider_failed'       => 0,
		'output_quality_rejected' => 0,
		'output_empty_or_invalid' => 0,
		'request_failed'        => 0,
	);

	foreach ( array_values( $blocks ) as $index => $block ) {
		$block_type = strtolower( (string) preg_replace( '/[^a-zA-Z0-9_\/-]+/', '', (string) ( $block['block_type'] ?? $block['name'] ?? '' ) ) );
		$content = trim( (string) ( $block['content'] ?? '' ) );
		$length = function_exists( 'mb_strlen' ) ? mb_strlen( $content, 'UTF-8' ) : strlen( $content );
		$record = array(
			'index'             => $index,
			'block_type'        => $block_type,
			'source_length'     => (int) $length,
			'target_language'   => sanitize_key( (string) $target_language ),
			'status'            => '',
			'failure_code'      => null,
			'diagnostic_code'   => null,
			'http_status'       => null,
			'provider_run_id'   => null,
		);

		if ( ! in_array( $block_type, array( 'core/paragraph', 'core/heading' ), true ) ) {
			$record['status'] = 'unsupported_block';
			$record['failure_code'] = 'unsupported_block_type';
			$summary['unsupported_block']++;
			$records[] = $record;
			continue;
		}
		if ( '' === $content ) {
			$record['status'] = 'unsupported_block';
			$record['failure_code'] = 'empty_block_content';
			$summary['unsupported_block']++;
			$records[] = $record;
			continue;
		}
		if ( $length < (int) $minimum_length ) {
			$record['status'] = 'skipped_too_short';
			$record['failure_code'] = 'below_minimum_length';
			$summary['skipped_too_short']++;
			$records[] = $record;
			continue;
		}

		$summary['eligible_blocks']++;
		$response = call_user_func( $runner, array( 'content' => $content, 'target_language' => $target_language ) );
		$response_status = is_array( $response ) ? (int) ( $response['http_status'] ?? 0 ) : 0;
		$response_data = is_array( $response ) ? ( $response['data'] ?? null ) : null;
		$shape_valid = is_array( $response ) && ! empty( $response['output_shape_valid'] );
		$has_result = is_array( $response ) && ! empty( $response['non_empty_result'] );
		$status = npcink_cloud_acceptance_translation_block_status( $response_status, $response_data, $shape_valid, $has_result );
		$record['status'] = $status;
		$record['failure_code'] = $has_result ? null : ( $response['failure_code'] ?? npcink_cloud_acceptance_failure_code( $response_status, $response_data, $shape_valid ) );
		$record['diagnostic_code'] = $response['diagnostic_code'] ?? npcink_cloud_acceptance_diagnostic_code( $response_status, $response_data );
		$record['http_status'] = $response_status;
		$record['provider_run_id'] = $response['provider_run_id'] ?? null;
		if ( isset( $summary[ $status ] ) ) {
			$summary[ $status ]++;
		}
		$records[] = $record;
	}

	return array(
		'contract_version' => 'wordpress_ai_translation_block_diagnostics.v1',
		'source'           => 'official_wordpress_ai_content_translation_projection',
		'min_content_length' => (int) $minimum_length,
		'batch_size'       => 4,
		'summary'          => $summary,
		'blocks'           => $records,
	);
}

/** Fixed-fixture quality checks; passing these is not human acceptance. */
function npcink_cloud_acceptance_quality_failure( $ability, $data ) {
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
		foreach ( $data['slugs'] ?? array() as $slug ) {
			if ( ! is_string( $slug ) || 1 !== preg_match( '/^(?=.*[a-z])[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug ) ) {
				return 'slug_format_invalid';
			}
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
		foreach ( $data['suggestions'] ?? array() as $suggestion ) {
			if ( ! is_array( $suggestion ) || '' === trim( (string) ( $suggestion['term'] ?? '' ) ) ) {
				return 'classification_suggestion_invalid';
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
	return null;
}
