<?php
/**
 * Runtime request payload guards.
 *
 * @package NpcinkCloudAddon
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Npcink_Cloud_Runtime_Request_Guards' ) ) {
	/**
	 * Normalizes runtime request payloads before the Runtime Client signs and sends them.
	 *
	 * Every method is pure: no instance state and no environment reads. The
	 * connector validator receives the site id, site URL, and addon version from
	 * its caller so request validation stays deterministic in isolation.
	 */
	final class Npcink_Cloud_Runtime_Request_Guards {

		public const MEDIA_ARTIFACT_ID_PATTERN = '/^art_[0-9a-f]{32}$/';
		public const WP_AI_CONNECTOR_MAX_TIMEOUT_SECONDS = 60;
		public const WP_AI_IMAGE_GENERATION_MAX_TIMEOUT_SECONDS = 90;
		private const CLOUD_CONNECTOR_RUNTIME_CONTRACT = 'cloud_connector_runtime.v1';
		private const WORDPRESS_OPERATION_CONTRACT = 'wordpress_operation.v1';
		private const WP_AI_CONNECTOR_MAX_REQUEST_BYTES = 24000;
		private const WP_AI_CONNECTOR_MAX_SCENE_TEXT_CHARS = 12000;
		private const WP_AI_CONNECTOR_MAX_RETENTION_TTL = 86400;
		private const WP_AI_IMAGE_GENERATION_CONTRACT = 'image_generation_request.v1';
		private const WP_AI_IMAGE_GENERATION_MAX_REQUEST_BYTES = 12000;
		private const WP_AI_IMAGE_GENERATION_MAX_PROMPT_CHARS = 4000;
		private const WP_AI_IMAGE_GENERATION_MAX_RETENTION_TTL = 86400;
		private const WP_AI_IMAGE_GENERATION_ALLOWED_ASPECT_RATIOS = array( '1:1', '2:3', '3:2', '3:4', '4:3', '4:5', '5:4', '9:16', '16:9', '21:9' );
		private const TOOLBOX_IMAGE_GENERATION_ALLOWED_SOURCE_SURFACES = array( 'toolbox_featured_image', 'toolbox_editor_featured_image', 'toolbox_editor_image_modal', 'toolbox_ai_image_generation' );
		private const TOOLBOX_AUDIO_GENERATION_CONTRACT = 'audio_generation_request.v1';
		private const TOOLBOX_AUDIO_GENERATION_MAX_REQUEST_BYTES = 24000;
		private const TOOLBOX_AUDIO_GENERATION_MAX_TEXT_CHARS = 5000;
		private const TOOLBOX_AUDIO_GENERATION_MAX_TIMEOUT_SECONDS = 90;
		private const TOOLBOX_AUDIO_GENERATION_MAX_RETENTION_TTL = 86400;
		private const TOOLBOX_AUDIO_GENERATION_ALLOWED_INTENTS = array( 'article_narration', 'article_audio_summary' );
		private const TOOLBOX_AUDIO_GENERATION_ALLOWED_FORMATS = array( 'mp3', 'wav', 'pcm' );
		private const TOOLBOX_AUDIO_GENERATION_ALLOWED_SOURCE_SURFACES = array( 'toolbox_article_audio_candidates', 'toolbox_editor_content_support' );
		private const TOOLBOX_SITE_OPS_CLOUD_ANALYSIS_CONTRACT = 'site_ops_cloud_analysis_request.v1';
		private const TOOLBOX_SITE_OPS_CLOUD_ANALYSIS_RESULT_CONTRACT = 'site_ops_cloud_analysis_result.v1';
		private const TOOLBOX_SITE_OPS_CLOUD_ANALYSIS_MAX_REQUEST_BYTES = 750000;
		private const TOOLBOX_SITE_OPS_CLOUD_ANALYSIS_MAX_TIMEOUT_SECONDS = 90;
		private const TOOLBOX_SITE_OPS_CLOUD_ANALYSIS_MAX_RETENTION_TTL = 86400;
		private const TOOLBOX_MEDIA_GOVERNANCE_AUDIT_CONTRACT = 'media_governance_audit_request.v1';
		private const TOOLBOX_MEDIA_GOVERNANCE_AUDIT_MAX_REQUEST_BYTES = 750000;
		private const TOOLBOX_MEDIA_GOVERNANCE_AUDIT_MAX_ITEMS = 500;
		private const TOOLBOX_WEB_SEARCH_CONTRACT = 'web_search.v1';
		private const TOOLBOX_WEB_SEARCH_MAX_REQUEST_BYTES = 24000;
		private const TOOLBOX_WEB_SEARCH_MAX_QUERY_CHARS = 1000;
		private const TOOLBOX_WEB_SEARCH_MAX_TIMEOUT_SECONDS = 60;
		private const TOOLBOX_WEB_SEARCH_MAX_RETENTION_TTL = 86400;
		private const TOOLBOX_WEB_SEARCH_ALLOWED_INTENTS = array( 'general_research', 'article_background', 'fact_check', 'news', 'writing_context', 'competitor_research', 'pricing_snapshot', 'product_comparison', 'source_discovery', 'source_extraction_preview', 'external_links', 'zhihu_global_search', 'zhihu_research', 'zhihu_hot_topics', 'zhida_simple', 'zhida_deep', 'zhida_deepsearch' );
		private const TOOLBOX_IMAGE_SOURCE_CONTRACT = 'image_source_cloud_request.v1';
		private const TOOLBOX_IMAGE_SOURCE_MAX_REQUEST_BYTES = 120000;
		private const TOOLBOX_IMAGE_SOURCE_MAX_QUERY_CHARS = 1000;
		private const TOOLBOX_IMAGE_SOURCE_MAX_TIMEOUT_SECONDS = 60;
		private const TOOLBOX_IMAGE_SOURCE_MAX_RETENTION_TTL = 86400;
		private const TOOLBOX_IMAGE_SOURCE_ALLOWED_PROVIDERS = array( 'auto', 'cloud', 'unsplash', 'pixabay', 'pexels' );
		private const TOOLBOX_IMAGE_SOURCE_ALLOWED_LATENCY_MODES = array( 'fast_first', 'complete' );
		private const AGENT_FEEDBACK_ALLOWED_OUTCOMES = array( 'accepted', 'rejected', 'edited_before_accept', 'ignored', 'expired', 'blocked_by_policy', 'blocked_by_missing_input' );
		private const AGENT_FEEDBACK_ALLOWED_LABELS = array(
			'evidence_useful',
			'evidence_weak',
			'wrong_intent',
			'wrong_next_step',
			'missing_context',
			'wrong_priority',
			'already_handled',
			'unsafe_or_overreaching',
			'too_generic',
			'duplicate_suggestion',
			'good_but_needs_human_draft',
			'not_relevant_to_site',
			'source_or_license_risk',
			'visual_quality_low',
			'operator_confidence_high',
			'operator_confidence_low',
			'media_search_has_results',
			'media_search_no_results',
			'media_search_runtime_error',
			'media_candidate_adopted',
			'alt_suggestion_applied',
			'alt_saved_unchanged',
			'alt_saved_edited',
			'alt_saved_decorative',
			'alt_saved_cleared',
			'alt_suggestion_not_saved',
		);
		private const AGENT_FEEDBACK_FORBIDDEN_KEYS = array(
			'approval_policy',
			'approval_truth',
			'approve',
			'approved',
			'commit',
			'confirm_token',
			'direct_publish',
			'direct_wordpress_write',
			'execute',
			'final_write_policy',
			'final_write_target',
			'preflight_policy',
			'publish',
			'router_adoption',
			'set_post_content',
			'update_post',
			'wordpress_write_policy',
			'wordpress_write_target',
			'write_confirmed',
			'write_control',
			'write_controls',
		);
		private const WP_AI_CONNECTOR_ALLOWED_TASKS = array(
			'alt_text_suggest',
			'comment_moderation',
			'comment_reply_suggest',
			'content_translation',
			'content_classification',
			'content_rewrite',
			'content_summary',
			'editorial_notes',
			'editorial_updates',
			'excerpt_generation',
			'meta_description',
			'slug_generation',
			'title_generation',
		);
		private const WP_AI_CONNECTOR_SOURCE_TEXT_TASKS = array(
			'content_rewrite',
			'content_summary',
			'editorial_updates',
			'title_generation',
		);
		private const WP_AI_CONNECTOR_FORBIDDEN_KEYS = array(
			'api_key',
			'authorization',
			'base64',
			'b64',
			'b64_json',
			'callback_secret',
			'chat_id',
			'conversation_id',
			'cookie',
			'credentials',
			'function_call',
			'functions',
			'headers',
			'image_base64',
			'image_data',
			'messages',
			'nonce',
			'password',
			'provider_key',
			'provider_secret',
			'secret',
			'session_id',
			'stream',
			'thread_id',
			'tool_calls',
			'tools',
			'update_attachment_metadata',
			'wordpress_write_policy',
			'wordpress_write_target',
			'write_control',
			'write_controls',
			'x_npcink_signature',
			'x_magick_signature',
		);

		/**
		 * Normalizes a WordPress AI connector request into a bounded runtime payload.
		 *
		 * @param array<string,mixed> $request Raw connector request.
		 * @param string              $site_id Raw verified Cloud site id from client config.
		 * @param string              $site_url Canonical WordPress site URL.
		 * @param string              $connector_version Active addon version.
		 * @return array<string,mixed>|WP_Error
		 */
		public static function normalize_wordpress_ai_connector_request( array $request, string $site_id, string $site_url, string $connector_version ) {
			$contract_version = (string) ( $request['contract_version'] ?? '' );
			if ( self::CLOUD_CONNECTOR_RUNTIME_CONTRACT !== $contract_version ) {
				return new WP_Error(
					'cloud_wp_ai_connector_contract_invalid',
					__( 'WordPress AI connector requests require the cloud_connector_runtime.v1 contract.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}

			$operation_contract = is_array( $request['operation_contract'] ?? null ) ? $request['operation_contract'] : array();
			if (
				self::WORDPRESS_OPERATION_CONTRACT !== (string) ( $operation_contract['contract_version'] ?? '' )
				|| array() !== array_diff( array( 'contract_version', 'task', 'request' ), array_keys( $operation_contract ) )
				|| array() !== array_diff( array_keys( $operation_contract ), array( 'contract_version', 'task', 'request' ) )
				|| ! is_array( $operation_contract['request'] ?? null )
			) {
				return new WP_Error(
					'cloud_wp_ai_connector_operation_contract_invalid',
					__( 'WordPress AI connector requests require one wordpress_operation.v1 operation contract.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}

			$task          = sanitize_key( (string) ( $operation_contract['task'] ?? '' ) );
			$scene_request = $operation_contract['request'];
			$task_contract = null;
			if ( is_array( $scene_request['task_contract'] ?? null ) ) {
				$task_contract = Npcink_Cloud_AI_Task_Contract::normalize( $scene_request['task_contract'] );
				if ( is_wp_error( $task_contract ) ) {
					return $task_contract;
				}
				if ( $task !== (string) $task_contract['task'] ) {
					return new WP_Error(
						'cloud_ai_task_contract_task_mismatch',
						__( 'The AI task contract does not match the requested task.', 'npcink-cloud-addon' ),
						array( 'status' => 400 )
					);
				}
			}
			if ( null === $task_contract && ! in_array( $task, self::WP_AI_CONNECTOR_ALLOWED_TASKS, true ) ) {
				return new WP_Error(
					'cloud_wp_ai_connector_task_not_allowed',
					__( 'WordPress AI connector requests require a supported site-task surface.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}

			$forbidden_key = self::find_forbidden_wordpress_ai_connector_key( $request );
			if ( '' !== $forbidden_key ) {
				return new WP_Error(
					'cloud_wp_ai_connector_chat_shape_not_allowed',
					__( 'WordPress AI connector requests do not support generic chat sessions, tool calls, streams, or credential fields.', 'npcink-cloud-addon' ),
					array(
						'status' => 400,
						'key'    => $forbidden_key,
					)
				);
			}

			$encoded_request = wp_json_encode( $request );
			if ( ! is_string( $encoded_request ) || '' === $encoded_request ) {
				return new WP_Error(
					'cloud_wp_ai_connector_encode_failed',
					__( 'WordPress AI connector request could not be encoded.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}
			if ( strlen( $encoded_request ) > self::WP_AI_CONNECTOR_MAX_REQUEST_BYTES ) {
				return new WP_Error(
					'cloud_wp_ai_connector_request_too_large',
					__( 'WordPress AI connector request exceeds the scene runtime size limit.', 'npcink-cloud-addon' ),
					array( 'status' => 413 )
				);
			}

			if ( 'alt_text_suggest' === $task ) {
				$scene_request = self::normalize_wordpress_ai_alt_text_request( $scene_request );
				if ( is_wp_error( $scene_request ) ) {
					return $scene_request;
				}
			}

			if ( in_array( $task, self::WP_AI_CONNECTOR_SOURCE_TEXT_TASKS, true ) ) {
				foreach ( array( 'prompt', 'post_title', 'post_excerpt' ) as $forbidden_text_field ) {
					if ( array_key_exists( $forbidden_text_field, $scene_request ) ) {
						return new WP_Error(
							'cloud_wp_ai_connector_source_text_shape_invalid',
							__( 'WordPress AI title, summary, and rewrite requests require source_text without legacy prompt or post fields.', 'npcink-cloud-addon' ),
							array( 'status' => 400 )
						);
					}
				}
				if ( ! is_string( $scene_request['source_text'] ?? null ) || '' === trim( $scene_request['source_text'] ) ) {
					return new WP_Error(
						'cloud_wp_ai_connector_source_text_required',
						__( 'WordPress AI title, summary, and rewrite requests require nonempty source_text.', 'npcink-cloud-addon' ),
						array( 'status' => 400 )
					);
				}
				$scene_request['source_text'] = trim( $scene_request['source_text'] );
				if ( self::text_length( $scene_request['source_text'] ) > self::WP_AI_CONNECTOR_MAX_SCENE_TEXT_CHARS ) {
					return new WP_Error(
						'cloud_wp_ai_connector_source_text_too_large',
						__( 'WordPress AI source_text exceeds the scene runtime size limit.', 'npcink-cloud-addon' ),
						array( 'status' => 413 )
					);
				}
				if ( array_key_exists( 'existing_title', $scene_request ) ) {
					if ( 'title_generation' !== $task || ! is_string( $scene_request['existing_title'] ) ) {
						return new WP_Error(
							'cloud_wp_ai_connector_existing_title_invalid',
							__( 'WordPress AI existing_title context is supported only for title generation.', 'npcink-cloud-addon' ),
							array( 'status' => 400 )
						);
					}
					$scene_request['existing_title'] = self::bounded_text( wp_strip_all_tags( $scene_request['existing_title'] ), 160 );
				}
				if ( array_key_exists( 'system_instruction', $scene_request ) ) {
					if ( ! is_string( $scene_request['system_instruction'] ) ) {
						return new WP_Error(
							'cloud_wp_ai_connector_system_instruction_invalid',
							__( 'WordPress AI system_instruction must be a string.', 'npcink-cloud-addon' ),
							array( 'status' => 400 )
						);
					}
					$scene_request['system_instruction'] = trim( $scene_request['system_instruction'] );
					if ( self::text_length( $scene_request['system_instruction'] ) > self::WP_AI_CONNECTOR_MAX_SCENE_TEXT_CHARS ) {
						return new WP_Error(
							'cloud_wp_ai_connector_system_instruction_too_large',
							__( 'WordPress AI system_instruction exceeds the scene runtime size limit.', 'npcink-cloud-addon' ),
							array( 'status' => 413 )
						);
					}
				}
			}

			$prompt = (string) ( $scene_request['prompt'] ?? '' );
			if ( '' !== $prompt && self::text_length( $prompt ) > self::WP_AI_CONNECTOR_MAX_SCENE_TEXT_CHARS ) {
				return new WP_Error(
					'cloud_wp_ai_connector_prompt_too_large',
					__( 'WordPress AI connector prompt exceeds the scene runtime size limit.', 'npcink-cloud-addon' ),
					array( 'status' => 413 )
				);
			}

			$timeout_seconds = absint( $request['timeout_seconds'] ?? 20 );
			$retention_ttl   = absint( $request['retention_ttl'] ?? self::WP_AI_CONNECTOR_MAX_RETENTION_TTL );
			$retry_max       = absint( $request['retry_max'] ?? 0 );
			$profile_id      = self::normalize_identifier( (string) ( $request['profile_id'] ?? 'text.balanced' ) );
			if ( null !== $task_contract ) {
				$scene_request['task_contract'] = $task_contract;
			}
			$site_knowledge_reference = self::normalize_wordpress_ai_site_knowledge_reference(
				$scene_request['site_knowledge_reference'] ?? null,
				$task,
				is_array( $task_contract ) ? $task_contract : array()
			);
			if ( is_wp_error( $site_knowledge_reference ) ) {
				return $site_knowledge_reference;
			}
			if ( null !== $site_knowledge_reference ) {
				$scene_request['site_knowledge_reference'] = $site_knowledge_reference;
			}

			$site_id = self::normalize_identifier( $site_id );
			if ( '' === $site_id ) {
				return new WP_Error(
					'cloud_wp_ai_connector_site_id_required',
					__( 'WordPress AI connector requests require a verified Cloud site_id.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}
			if ( '' === $site_url ) {
				return new WP_Error(
					'cloud_wp_ai_connector_site_url_required',
					__( 'WordPress AI connector requests require the canonical WordPress site URL.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}
			if ( '' === $connector_version ) {
				return new WP_Error(
					'cloud_wp_ai_connector_version_required',
					__( 'WordPress AI connector requests require the active addon version.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}
			$is_alt_text = 'alt_text_suggest' === $task;
			$contains_pii = self::wordpress_ai_scene_contains_obvious_pii( $scene_request );

			return array(
				'site_id'             => $site_id,
				'ability_name'        => 'npcink-cloud/connector-runtime',
				'ability_family'      => $is_alt_text ? 'vision' : 'text',
				'contract_version'    => self::CLOUD_CONNECTOR_RUNTIME_CONTRACT,
				'channel'             => 'editor',
				'execution_kind'      => $is_alt_text ? 'vision' : 'text',
				'execution_pattern'   => 'inline',
				'profile_id'          => '' !== $profile_id ? $profile_id : 'text.balanced',
				'input'               => array(
					'site_url'           => $site_url,
					'platform_kind'      => 'wordpress',
					'connector_id'       => 'npcink-cloud-addon',
					'connector_version'  => $connector_version,
					'suggestion_only'    => true,
					'operation_contract' => array(
						'contract_version' => self::WORDPRESS_OPERATION_CONTRACT,
						'task'             => $task,
						'request'          => $scene_request,
					),
				),
				'data_classification' => $contains_pii ? 'pii' : 'internal',
				'storage_mode'        => $contains_pii ? 'no_store' : 'result_only',
				'retention_ttl'       => $contains_pii ? 0 : min( self::WP_AI_CONNECTOR_MAX_RETENTION_TTL, max( 0, $retention_ttl ) ),
				'timeout_seconds'     => min( self::WP_AI_CONNECTOR_MAX_TIMEOUT_SECONDS, max( 1, $timeout_seconds ) ),
				'retry_max'           => min( 1, $retry_max ),
			);
		}

		/**
		 * Detects obvious personal-data values before dispatching an editor scene.
		 *
		 * This intentionally mirrors the Cloud runtime's lightweight PII backstop.
		 * It is not a general DLP classifier; it only selects the stricter request
		 * posture for clear email, phone-number, or national-id-like values.
		 *
		 * @param mixed $value Normalized scene value.
		 * @return bool
		 */
		private static function wordpress_ai_scene_contains_obvious_pii( $value ): bool {
			if ( is_array( $value ) ) {
				foreach ( $value as $item ) {
					if ( self::wordpress_ai_scene_contains_obvious_pii( $item ) ) {
						return true;
					}
				}
				return false;
			}
			if ( ! is_string( $value ) || '' === $value ) {
				return false;
			}
			if ( 1 === preg_match( '/^art_[0-9a-f]{32}$/', $value ) ) {
				return false;
			}

			$patterns = array(
				'/\b[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}\b/',
				'/(?<!\d)(?:\+?\d{1,3}[\s.\-]?)?\(?\d{3}\)?[\s.\-]?\d{3}[\s.\-]?\d{4}(?!\d)/',
				'/\b[1-9]\d{5}(?:18|19|20)\d{2}(?:0[1-9]|1[0-2])(?:0[1-9]|[12]\d|3[01])\d{3}[\dXx]\b/',
			);
			foreach ( $patterns as $pattern ) {
				if ( 1 === preg_match( $pattern, $value ) ) {
					return true;
				}
			}

			return false;
		}

		/**
		 * Normalizes the Artifact-id-only WordPress AI alt-text scene request.
		 *
		 * @param array<string,mixed> $request Raw alt-text scene request.
		 * @return array<string,mixed>|WP_Error
		 */
		private static function normalize_wordpress_ai_alt_text_request( array $request ) {
			$allowed_fields = array( 'source_artifact_id', 'prompt', 'filename', 'title', 'existing_alt', 'existing_caption', 'locale', 'max_tokens', 'task_contract' );
			if ( array() !== array_diff( array_keys( $request ), $allowed_fields ) ) {
				return new WP_Error(
					'cloud_wp_ai_alt_text_request_fields_not_allowed',
					__( 'WordPress AI alt-text requests accept only an Artifact id and bounded text context.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}

			$source_artifact_id = $request['source_artifact_id'] ?? null;
			if ( ! is_string( $source_artifact_id ) || 1 !== preg_match( '/^art_[0-9a-f]{32}$/', $source_artifact_id ) ) {
				return new WP_Error(
					'cloud_wp_ai_alt_text_source_artifact_id_invalid',
					__( 'WordPress AI alt-text requests require a valid source_artifact_id.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}

			$prompt = $request['prompt'] ?? null;
			if ( ! is_string( $prompt ) || '' === trim( $prompt ) || self::text_length( trim( $prompt ) ) > 500 ) {
				return new WP_Error(
					'cloud_wp_ai_alt_text_prompt_invalid',
					__( 'WordPress AI alt-text prompt must be a nonempty string of at most 500 characters.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}

			$normalized = array(
				'source_artifact_id' => $source_artifact_id,
				'prompt'              => trim( $prompt ),
			);
			if ( array_key_exists( 'task_contract', $request ) ) {
				if ( ! is_array( $request['task_contract'] ) ) {
					return new WP_Error(
						'cloud_wp_ai_alt_text_task_contract_invalid',
						__( 'WordPress AI alt-text task_contract must be a normalized object.', 'npcink-cloud-addon' ),
						array( 'status' => 400 )
					);
				}
				$normalized['task_contract'] = $request['task_contract'];
			}
			$field_limits = array(
				'filename'         => 160,
				'title'            => 160,
				'existing_alt'     => 240,
				'existing_caption' => 240,
				'locale'           => 32,
			);
			foreach ( $field_limits as $field => $limit ) {
				if ( ! array_key_exists( $field, $request ) ) {
					continue;
				}
				if ( ! is_string( $request[ $field ] ) || self::text_length( $request[ $field ] ) > $limit ) {
					return new WP_Error(
						'cloud_wp_ai_alt_text_context_invalid',
						__( 'WordPress AI alt-text context fields must be bounded strings.', 'npcink-cloud-addon' ),
						array( 'status' => 400, 'field' => $field )
					);
				}
				$normalized[ $field ] = $request[ $field ];
			}

			if ( array_key_exists( 'max_tokens', $request ) ) {
				if ( ! is_int( $request['max_tokens'] ) || $request['max_tokens'] < 1 || $request['max_tokens'] > 96 ) {
					return new WP_Error(
						'cloud_wp_ai_alt_text_max_tokens_invalid',
						__( 'WordPress AI alt-text max_tokens must be an integer from 1 through 96.', 'npcink-cloud-addon' ),
						array( 'status' => 400 )
					);
				}
				$normalized['max_tokens'] = $request['max_tokens'];
			}

			return $normalized;
		}

		/**
		 * Normalizes the optional Site Knowledge reference for supported WordPress AI tasks.
		 *
		 * @param mixed  $reference Raw reference value.
		 * @param string $task WordPress AI scene task.
		 * @param array<string,mixed> $task_contract Optional registered Ability projection.
		 * @return array<string,mixed>|null|WP_Error
		 */
		private static function normalize_wordpress_ai_site_knowledge_reference( $reference, string $task, array $task_contract = array() ) {
			if ( null === $reference ) {
				return null;
			}
			if ( ! is_array( $reference ) ) {
				return new WP_Error(
					'cloud_wp_ai_connector_site_knowledge_reference_invalid',
					__( 'WordPress AI Site Knowledge reference must be a bounded object.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}

			$unknown_fields = array_diff( array_keys( $reference ), array( 'enabled', 'mode' ) );
			if ( ! empty( $unknown_fields ) ) {
				return new WP_Error(
					'cloud_wp_ai_connector_site_knowledge_reference_fields_not_allowed',
					__( 'WordPress AI Site Knowledge reference accepts only enabled and mode.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}

			$enabled = $reference['enabled'] ?? null;
			if ( ! is_bool( $enabled ) ) {
				return new WP_Error(
					'cloud_wp_ai_connector_site_knowledge_reference_enabled_invalid',
					__( 'WordPress AI Site Knowledge reference enabled value must be boolean.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}
			$task_modes = array(
				'title_generation' => 'site_title_style',
				'content_summary'  => 'site_summary_style',
			);
			$expected_mode = (string) ( $task_modes[ $task ] ?? '' );
			$mode = sanitize_key( (string) ( $reference['mode'] ?? ( '' !== $expected_mode ? $expected_mode : 'site_title_style' ) ) );
			if ( $enabled && '' === $expected_mode ) {
				return new WP_Error(
					'cloud_wp_ai_connector_site_knowledge_reference_task_not_allowed',
					__( 'WordPress AI Site Knowledge reference is not supported for this task.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}
			if ( ( '' !== $expected_mode && $mode !== $expected_mode ) || ( '' === $expected_mode && 'site_title_style' !== $mode ) ) {
				return new WP_Error(
					'cloud_wp_ai_connector_site_knowledge_reference_mode_invalid',
					__( 'WordPress AI Site Knowledge reference mode is not supported.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}
			return array(
				'enabled' => $enabled,
				'mode'    => $mode,
			);
		}

		/**
		 * Normalizes a WordPress AI image generation request into a bounded runtime payload.
		 *
		 * @param array<string,mixed> $request Raw image generation request.
		 * @return array<string,mixed>|WP_Error
		 */
		public static function normalize_wordpress_ai_image_generation_request( array $request ) {
			$contract_version = (string) ( $request['contract_version'] ?? self::WP_AI_IMAGE_GENERATION_CONTRACT );
			if ( self::WP_AI_IMAGE_GENERATION_CONTRACT !== $contract_version ) {
				return new WP_Error(
					'cloud_wp_ai_image_generation_contract_invalid',
					__( 'WordPress AI image generation requests require the image_generation_request.v1 contract.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}

			$task = sanitize_key( (string) ( $request['task'] ?? 'image_generation' ) );
			if ( 'image_generation' !== $task ) {
				return new WP_Error(
					'cloud_wp_ai_image_generation_task_not_allowed',
					__( 'WordPress AI image generation requests require the supported image_generation task surface.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}

			if ( array_key_exists( 'response_format', $request ) ) {
				return new WP_Error(
					'cloud_wp_ai_image_generation_provider_media_field_forbidden',
					__( 'WordPress AI image generation requests may not select a provider response format.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}

			$forbidden_key = self::find_forbidden_wordpress_ai_connector_key( $request );
			if ( '' !== $forbidden_key ) {
				return new WP_Error(
					'cloud_wp_ai_image_generation_shape_not_allowed',
					__( 'WordPress AI image generation requests do not support generic chat sessions, tool calls, streams, or credential fields.', 'npcink-cloud-addon' ),
					array(
						'status' => 400,
						'key'    => $forbidden_key,
					)
				);
			}

			$encoded_request = wp_json_encode( $request );
			if ( ! is_string( $encoded_request ) || '' === $encoded_request ) {
				return new WP_Error(
					'cloud_wp_ai_image_generation_encode_failed',
					__( 'WordPress AI image generation request could not be encoded.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}
			if ( strlen( $encoded_request ) > self::WP_AI_IMAGE_GENERATION_MAX_REQUEST_BYTES ) {
				return new WP_Error(
					'cloud_wp_ai_image_generation_request_too_large',
					__( 'WordPress AI image generation request exceeds the scene runtime size limit.', 'npcink-cloud-addon' ),
					array( 'status' => 413 )
				);
			}

			$prompt = trim( (string) ( $request['prompt'] ?? '' ) );
			if ( '' === $prompt ) {
				return new WP_Error(
					'cloud_wp_ai_image_generation_prompt_required',
					__( 'WordPress AI image generation requires text scene input.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}
			if ( self::text_length( $prompt ) > self::WP_AI_IMAGE_GENERATION_MAX_PROMPT_CHARS ) {
				return new WP_Error(
					'cloud_wp_ai_image_generation_prompt_too_large',
					__( 'WordPress AI image generation prompt exceeds the scene runtime size limit.', 'npcink-cloud-addon' ),
					array( 'status' => 413 )
				);
			}

			$aspect_ratio = (string) ( $request['aspect_ratio'] ?? '1:1' );
			if ( ! in_array( $aspect_ratio, self::WP_AI_IMAGE_GENERATION_ALLOWED_ASPECT_RATIOS, true ) ) {
				$aspect_ratio = '1:1';
			}

			$image_count = absint( $request['n'] ?? 1 );
			$image_count = min( 4, max( 1, $image_count ) );
			$timeout_seconds = absint( $request['timeout_seconds'] ?? self::WP_AI_IMAGE_GENERATION_MAX_TIMEOUT_SECONDS );
			$retention_ttl   = absint( $request['retention_ttl'] ?? self::WP_AI_IMAGE_GENERATION_MAX_RETENTION_TTL );

			return array(
				'ability_name'        => 'npcink-cloud/generate-image',
				'ability_family'      => 'vision',
				'contract_version'    => self::WP_AI_IMAGE_GENERATION_CONTRACT,
				'channel'             => 'wordpress_ai_connector',
				'execution_kind'      => 'image_generation',
				'execution_pattern'   => 'inline',
				'input'               => array(
					'contract_version' => self::WP_AI_IMAGE_GENERATION_CONTRACT,
					'source_surface'   => 'wordpress_ai_connector',
					'connector_id'      => 'npcink-cloud',
					'task'              => 'image_generation',
					'prompt'            => $prompt,
					'n'                 => $image_count,
					'aspect_ratio'      => $aspect_ratio,
					'resolution'        => sanitize_key( (string) ( $request['resolution'] ?? 'medium' ) ),
				),
				'data_classification' => 'internal',
				'storage_mode'        => 'result_only',
				'retention_ttl'       => min( self::WP_AI_IMAGE_GENERATION_MAX_RETENTION_TTL, max( 0, $retention_ttl ) ),
				'timeout_seconds'     => min( self::WP_AI_IMAGE_GENERATION_MAX_TIMEOUT_SECONDS, max( 1, $timeout_seconds ) ),
				'retry_max'           => 0,
				'policy'              => array(
					'allow_fallback' => false,
				),
			);
		}

		/**
		 * Normalizes a Toolbox AI image generation request into a bounded runtime payload.
		 *
		 * @param array<string,mixed> $request Raw Toolbox image generation request.
		 * @return array<string,mixed>|WP_Error
		 */
		public static function normalize_toolbox_image_generation_request( array $request ) {
			$contract_version = (string) ( $request['contract_version'] ?? self::WP_AI_IMAGE_GENERATION_CONTRACT );
			if ( self::WP_AI_IMAGE_GENERATION_CONTRACT !== $contract_version ) {
				return new WP_Error(
					'cloud_toolbox_image_generation_contract_invalid',
					__( 'Toolbox image generation requests require the image_generation_request.v1 contract.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}

			$task = sanitize_key( (string) ( $request['task'] ?? 'image_generation' ) );
			if ( 'image_generation' !== $task ) {
				return new WP_Error(
					'cloud_toolbox_image_generation_task_not_allowed',
					__( 'Toolbox image generation requests require the supported image_generation task surface.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}

			if ( array_key_exists( 'response_format', $request ) ) {
				return new WP_Error(
					'cloud_toolbox_image_generation_provider_media_field_forbidden',
					__( 'Toolbox image generation requests may not select a provider response format.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}

			$forbidden_key = self::find_forbidden_wordpress_ai_connector_key( $request );
			if ( '' !== $forbidden_key ) {
				return new WP_Error(
					'cloud_toolbox_image_generation_shape_not_allowed',
					__( 'Toolbox image generation requests do not support generic chat sessions, tool calls, streams, or credential fields.', 'npcink-cloud-addon' ),
					array(
						'status' => 400,
						'key'    => $forbidden_key,
					)
				);
			}

			$encoded_request = wp_json_encode( $request );
			if ( ! is_string( $encoded_request ) || '' === $encoded_request ) {
				return new WP_Error(
					'cloud_toolbox_image_generation_encode_failed',
					__( 'Toolbox image generation request could not be encoded.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}
			if ( strlen( $encoded_request ) > self::WP_AI_IMAGE_GENERATION_MAX_REQUEST_BYTES ) {
				return new WP_Error(
					'cloud_toolbox_image_generation_request_too_large',
					__( 'Toolbox image generation request exceeds the scene runtime size limit.', 'npcink-cloud-addon' ),
					array( 'status' => 413 )
				);
			}

			$prompt = trim( (string) ( $request['prompt'] ?? '' ) );
			if ( '' === $prompt ) {
				return new WP_Error(
					'cloud_toolbox_image_generation_prompt_required',
					__( 'Toolbox image generation requires text scene input.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}
			if ( self::text_length( $prompt ) > self::WP_AI_IMAGE_GENERATION_MAX_PROMPT_CHARS ) {
				return new WP_Error(
					'cloud_toolbox_image_generation_prompt_too_large',
					__( 'Toolbox image generation prompt exceeds the scene runtime size limit.', 'npcink-cloud-addon' ),
					array( 'status' => 413 )
				);
			}

			$aspect_ratio = (string) ( $request['aspect_ratio'] ?? '16:9' );
			if ( ! in_array( $aspect_ratio, self::WP_AI_IMAGE_GENERATION_ALLOWED_ASPECT_RATIOS, true ) ) {
				$aspect_ratio = '16:9';
			}

			$source_surface = sanitize_key( (string) ( $request['source_surface'] ?? 'toolbox_featured_image' ) );
			if ( ! in_array( $source_surface, self::TOOLBOX_IMAGE_GENERATION_ALLOWED_SOURCE_SURFACES, true ) ) {
				$source_surface = 'toolbox_featured_image';
			}

			$image_count = absint( $request['n'] ?? 1 );
			$image_count = min( 4, max( 1, $image_count ) );
			$review = is_array( $request['review'] ?? null ) ? $request['review'] : array();
			$source_locale = sanitize_key( (string) ( $review['source_prompt_locale'] ?? '' ) );
			$translation_mode = sanitize_key( (string) ( $review['prompt_translation_mode'] ?? 'none' ) );
			if ( ! in_array( $translation_mode, array( 'none', 'preplanned_pair', 'required' ), true ) ) {
				$translation_mode = 'none';
			}

			$timeout_seconds = absint( $request['timeout_seconds'] ?? self::WP_AI_IMAGE_GENERATION_MAX_TIMEOUT_SECONDS );
			$retention_ttl   = absint( $request['retention_ttl'] ?? self::WP_AI_IMAGE_GENERATION_MAX_RETENTION_TTL );

			return array(
				'ability_name'        => 'npcink-cloud/generate-image',
				'ability_family'      => 'vision',
				'contract_version'    => self::WP_AI_IMAGE_GENERATION_CONTRACT,
				'channel'             => 'toolbox_image_generation',
				'execution_kind'      => 'image_generation',
				'profile_id'          => 'wp-ai.image-generation',
				'execution_pattern'   => 'inline',
				'input'               => array(
					'contract_version' => self::WP_AI_IMAGE_GENERATION_CONTRACT,
					'source_surface'   => $source_surface,
					'connector_id'      => 'npcink-cloud-addon',
					'task'              => 'image_generation',
					'prompt'            => $prompt,
					'n'                 => $image_count,
					'aspect_ratio'      => $aspect_ratio,
					'resolution'        => sanitize_key( (string) ( $request['resolution'] ?? 'high' ) ),
					'review'            => array(
						'source_prompt_reviewed_by_operator' => ! empty( $review['source_prompt_reviewed_by_operator'] ),
						'source_prompt_locale'               => $source_locale,
						'prompt_translation_mode'            => $translation_mode,
						'provider_prompt_reviewed_by_operator' => ! empty( $review['provider_prompt_reviewed_by_operator'] ),
					),
				),
				'data_classification' => 'internal',
				'storage_mode'        => 'result_only',
				'retention_ttl'       => min( self::WP_AI_IMAGE_GENERATION_MAX_RETENTION_TTL, max( 0, $retention_ttl ) ),
				'timeout_seconds'     => min( self::WP_AI_IMAGE_GENERATION_MAX_TIMEOUT_SECONDS, max( 1, $timeout_seconds ) ),
				'retry_max'           => 0,
				'policy'              => array(
					'allow_fallback' => false,
				),
			);
		}

		/**
		 * Normalizes a Toolbox article audio candidate runtime request.
		 *
		 * @param array<string,mixed> $request Raw Toolbox audio request.
		 * @return array<string,mixed>|WP_Error
		 */
		public static function normalize_toolbox_audio_generation_request( array $request ) {
			$contract_version = (string) ( $request['contract_version'] ?? self::TOOLBOX_AUDIO_GENERATION_CONTRACT );
			if ( self::TOOLBOX_AUDIO_GENERATION_CONTRACT !== $contract_version ) {
				return new WP_Error(
					'cloud_toolbox_audio_generation_contract_invalid',
					__( 'Toolbox audio generation requests require the audio_generation_request.v1 contract.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}

			$intent = sanitize_key( (string) ( $request['intent'] ?? 'article_narration' ) );
			if ( ! in_array( $intent, self::TOOLBOX_AUDIO_GENERATION_ALLOWED_INTENTS, true ) ) {
				return new WP_Error(
					'cloud_toolbox_audio_generation_intent_not_allowed',
					__( 'Toolbox audio generation requests require a supported article audio intent.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}

			$forbidden_key = self::find_forbidden_wordpress_ai_connector_key( $request );
			if ( '' !== $forbidden_key ) {
				return new WP_Error(
					'cloud_toolbox_audio_generation_shape_not_allowed',
					__( 'Toolbox audio generation requests do not support generic chat sessions, tool calls, streams, or credential fields.', 'npcink-cloud-addon' ),
					array(
						'status' => 400,
						'key'    => $forbidden_key,
					)
				);
			}

			$encoded_request = wp_json_encode( $request );
			if ( ! is_string( $encoded_request ) || '' === $encoded_request ) {
				return new WP_Error(
					'cloud_toolbox_audio_generation_encode_failed',
					__( 'Toolbox audio generation request could not be encoded.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}
			if ( strlen( $encoded_request ) > self::TOOLBOX_AUDIO_GENERATION_MAX_REQUEST_BYTES ) {
				return new WP_Error(
					'cloud_toolbox_audio_generation_request_too_large',
					__( 'Toolbox audio generation request exceeds the scene runtime size limit.', 'npcink-cloud-addon' ),
					array( 'status' => 413 )
				);
			}

			$raw_text = trim( wp_strip_all_tags( (string) ( $request['text'] ?? ( $request['script'] ?? ( $request['summary_text'] ?? '' ) ) ) ) );
			if ( '' === $raw_text ) {
				return new WP_Error(
					'cloud_toolbox_audio_generation_text_required',
					__( 'Toolbox audio generation requires reviewed narration text or a summary script.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}
			if ( self::text_length( $raw_text ) > self::TOOLBOX_AUDIO_GENERATION_MAX_TEXT_CHARS ) {
				return new WP_Error(
					'cloud_toolbox_audio_generation_text_too_large',
					__( 'Toolbox audio generation text exceeds the scene runtime size limit.', 'npcink-cloud-addon' ),
					array( 'status' => 413 )
				);
			}
			$text = self::bounded_text( $raw_text, self::TOOLBOX_AUDIO_GENERATION_MAX_TEXT_CHARS );

			$format = sanitize_key( (string) ( $request['format'] ?? 'mp3' ) );
			if ( ! in_array( $format, self::TOOLBOX_AUDIO_GENERATION_ALLOWED_FORMATS, true ) ) {
				$format = 'mp3';
			}

			$source_surface = sanitize_key( (string) ( $request['source_surface'] ?? 'toolbox_article_audio_candidates' ) );
			if ( ! in_array( $source_surface, self::TOOLBOX_AUDIO_GENERATION_ALLOWED_SOURCE_SURFACES, true ) ) {
				$source_surface = 'toolbox_article_audio_candidates';
			}

			$timeout_seconds = absint( $request['timeout_seconds'] ?? self::TOOLBOX_AUDIO_GENERATION_MAX_TIMEOUT_SECONDS );
			$retention_ttl   = absint( $request['retention_ttl'] ?? 3600 );
			$summary_text    = 'article_audio_summary' === $intent ? self::bounded_text( (string) ( $request['summary_text'] ?? $text ), self::TOOLBOX_AUDIO_GENERATION_MAX_TEXT_CHARS ) : '';

			return array(
				'ability_name'        => 'npcink-toolbox/generate-audio',
				'contract_version'    => self::TOOLBOX_AUDIO_GENERATION_CONTRACT,
				'channel'             => 'toolbox_audio_generation',
				'execution_kind'      => 'audio_generation',
				'execution_pattern'   => 'inline',
				'profile_id'          => sanitize_text_field( (string) ( $request['profile_id'] ?? 'audio.narration.default' ) ),
				'input'               => array(
					'contract_version'  => self::TOOLBOX_AUDIO_GENERATION_CONTRACT,
					'source_surface'    => $source_surface,
					'connector_id'      => 'npcink-cloud-addon',
					'intent'            => $intent,
					'text'              => $text,
					'summary_text'      => $summary_text,
					'script'            => self::bounded_text( (string) ( $request['script'] ?? $text ), self::TOOLBOX_AUDIO_GENERATION_MAX_TEXT_CHARS ),
					'voice_id'          => sanitize_text_field( (string) ( $request['voice_id'] ?? '' ) ),
					'format'            => $format,
					'response_format'   => 'url',
					'purpose'           => 'article_audio_summary' === $intent ? 'longform_audio_summary' : 'article_narration',
					'user_instruction'  => self::bounded_text( (string) ( $request['user_instruction'] ?? '' ), 1200 ),
					'audio_preferences' => is_array( $request['audio_preferences'] ?? null ) ? self::sanitize_payload( $request['audio_preferences'] ) : array(),
					'context'           => is_array( $request['context'] ?? null ) ? self::sanitize_payload( $request['context'] ) : array(),
					'review'            => array(
						'script_review_required' => true,
						'write_posture'          => 'candidate_only',
						'direct_wordpress_write' => false,
					),
				),
				'data_classification' => 'public_site_content',
				'storage_mode'        => 'result_only',
				'retention_ttl'       => min( self::TOOLBOX_AUDIO_GENERATION_MAX_RETENTION_TTL, max( 0, $retention_ttl ) ),
				'timeout_seconds'     => min( self::TOOLBOX_AUDIO_GENERATION_MAX_TIMEOUT_SECONDS, max( 1, $timeout_seconds ) ),
				'retry_max'           => 0,
				'policy'              => array(
					'allow_fallback' => false,
				),
			);
		}

		/**
		 * Normalizes a Toolbox Site Ops Cloud analysis request.
		 *
		 * @param array<string,mixed> $request Raw Toolbox Site Ops request artifact.
		 * @return array<string,mixed>|WP_Error
		 */
		public static function normalize_toolbox_site_ops_cloud_analysis_request( array $request ) {
			$contract_version = (string) ( $request['contract_version'] ?? self::TOOLBOX_SITE_OPS_CLOUD_ANALYSIS_CONTRACT );
			if ( self::TOOLBOX_SITE_OPS_CLOUD_ANALYSIS_CONTRACT !== $contract_version ) {
				return new WP_Error(
					'cloud_toolbox_site_ops_cloud_analysis_contract_invalid',
					__( 'Toolbox Site Check Cloud detail requests require the site_ops_cloud_analysis_request.v1 contract.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}

			if ( self::TOOLBOX_SITE_OPS_CLOUD_ANALYSIS_RESULT_CONTRACT !== (string) ( $request['expected_result_contract'] ?? '' ) ) {
				return new WP_Error(
					'cloud_toolbox_site_ops_cloud_analysis_result_contract_invalid',
					__( 'Toolbox Site Check Cloud detail requests require the site_ops_cloud_analysis_result.v1 result contract.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}

			if (
				'runtime_detail' !== (string) ( $request['cloud_role'] ?? '' )
				|| 'whole_run_offload' !== (string) ( $request['execution_pattern'] ?? '' )
				|| 'suggestion_only' !== (string) ( $request['write_posture'] ?? '' )
				|| false !== (bool) ( $request['direct_wordpress_write'] ?? true )
				|| false !== (bool) ( $request['core_proposal_created'] ?? true )
			) {
				return new WP_Error(
					'cloud_toolbox_site_ops_cloud_analysis_request_invalid',
					__( 'Toolbox Site Check Cloud detail requests must remain runtime-detail, suggestion-only, and no-write.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}

			if ( ! is_array( $request['input'] ?? null ) ) {
				return new WP_Error(
					'cloud_toolbox_site_ops_cloud_analysis_input_required',
					__( 'Toolbox Site Check Cloud detail requests require bounded local analysis input.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}

			$forbidden_key = self::find_forbidden_wordpress_ai_connector_key( $request );
			if ( '' !== $forbidden_key ) {
				return new WP_Error(
					'cloud_toolbox_site_ops_cloud_analysis_shape_not_allowed',
					__( 'Toolbox Site Check Cloud detail requests do not support generic chat sessions, tool calls, streams, or credential fields.', 'npcink-cloud-addon' ),
					array(
						'status' => 400,
						'key'    => $forbidden_key,
					)
				);
			}

			$encoded_request = wp_json_encode( $request );
			if ( ! is_string( $encoded_request ) || '' === $encoded_request ) {
				return new WP_Error(
					'cloud_toolbox_site_ops_cloud_analysis_encode_failed',
					__( 'Toolbox Site Check Cloud detail request could not be encoded.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}
			if ( strlen( $encoded_request ) > self::TOOLBOX_SITE_OPS_CLOUD_ANALYSIS_MAX_REQUEST_BYTES ) {
				return new WP_Error(
					'cloud_toolbox_site_ops_cloud_analysis_request_too_large',
					__( 'Toolbox Site Check Cloud detail request exceeds the runtime size limit.', 'npcink-cloud-addon' ),
					array( 'status' => 413 )
				);
			}

			$timeout_seconds = absint( $request['timeout_seconds'] ?? self::TOOLBOX_SITE_OPS_CLOUD_ANALYSIS_MAX_TIMEOUT_SECONDS );
			$retention_ttl   = absint( $request['retention_ttl'] ?? 3600 );

			return array(
				'ability_name'        => 'npcink-toolbox/analyze-site-ops',
				'contract_version'    => self::TOOLBOX_SITE_OPS_CLOUD_ANALYSIS_CONTRACT,
				'channel'             => 'toolbox_site_ops_cloud_analysis',
				'execution_kind'      => 'site_ops_cloud_analysis',
				'execution_pattern'   => 'whole_run_offload',
				'profile_id'          => sanitize_text_field( (string) ( $request['profile_id'] ?? 'site-ops-analysis.managed' ) ),
				'input'               => self::sanitize_payload( $request ),
				'data_classification' => 'public_site_aggregate',
				'storage_mode'        => 'result_only',
				'retention_ttl'       => min( self::TOOLBOX_SITE_OPS_CLOUD_ANALYSIS_MAX_RETENTION_TTL, max( 0, $retention_ttl ) ),
				'timeout_seconds'     => min( self::TOOLBOX_SITE_OPS_CLOUD_ANALYSIS_MAX_TIMEOUT_SECONDS, max( 1, $timeout_seconds ) ),
				'retry_max'           => 0,
				'policy'              => array(
					'allow_fallback' => false,
				),
			);
		}

		/**
		 * Normalizes one exact metadata-only media governance audit request.
		 *
		 * @param array<string,mixed> $request Raw request.
		 * @return array<string,mixed>|WP_Error
		 */
		public static function normalize_toolbox_media_governance_audit_request( array $request ) {
			$expected_request_keys = array( 'contract_version', 'snapshot' );
			$snapshot = is_array( $request['snapshot'] ?? null ) ? $request['snapshot'] : array();
			$expected_snapshot_keys = array( 'snapshot_id', 'captured_at', 'inventory_complete', 'capacity', 'coverage', 'items' );
			if (
				count( $expected_request_keys ) !== count( $request )
				|| array() !== array_diff( $expected_request_keys, array_keys( $request ) )
				|| array() !== array_diff( array_keys( $request ), $expected_request_keys )
				|| self::TOOLBOX_MEDIA_GOVERNANCE_AUDIT_CONTRACT !== (string) ( $request['contract_version'] ?? '' )
				|| count( $expected_snapshot_keys ) !== count( $snapshot )
				|| array() !== array_diff( $expected_snapshot_keys, array_keys( $snapshot ) )
				|| array() !== array_diff( array_keys( $snapshot ), $expected_snapshot_keys )
			) {
				return new WP_Error(
					'cloud_toolbox_media_governance_audit_contract_invalid',
					__( 'Media governance audits require the exact metadata-only request contract.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}

			$snapshot_id = sanitize_text_field( (string) $snapshot['snapshot_id'] );
			$captured_at = sanitize_text_field( (string) $snapshot['captured_at'] );
			$capacity = is_array( $snapshot['capacity'] ) ? $snapshot['capacity'] : array();
			$coverage = is_array( $snapshot['coverage'] ) ? $snapshot['coverage'] : array();
			$items = is_array( $snapshot['items'] ) ? $snapshot['items'] : array();
			$capacity_keys = array( 'uploads_bytes', 'backup_bytes', 'logs_bytes', 'filesystem_used_bytes', 'filesystem_available_bytes' );
			$coverage_keys = array( 'complete', 'sources' );
			if (
				1 !== preg_match( '/^[A-Za-z0-9][A-Za-z0-9._:-]{0,159}$/', $snapshot_id )
				|| '' === $captured_at
				|| ! is_bool( $snapshot['inventory_complete'] )
				|| ! isset( $capacity['uploads_bytes'] )
				|| array() !== array_diff( array_keys( $capacity ), $capacity_keys )
				|| count( $coverage_keys ) !== count( $coverage )
				|| array() !== array_diff( $coverage_keys, array_keys( $coverage ) )
				|| array() !== array_diff( array_keys( $coverage ), $coverage_keys )
				|| ! is_bool( $coverage['complete'] )
				|| ! is_array( $coverage['sources'] )
				|| empty( $items )
				|| count( $items ) > self::TOOLBOX_MEDIA_GOVERNANCE_AUDIT_MAX_ITEMS
			) {
				return new WP_Error(
					'cloud_toolbox_media_governance_audit_snapshot_invalid',
					__( 'Media governance audit snapshot facts are invalid or incomplete.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}

			$normalized_capacity = array();
			foreach ( $capacity as $key => $value ) {
				if ( is_bool( $value ) || ! is_int( $value ) || $value < 0 ) {
					return new WP_Error( 'cloud_toolbox_media_governance_audit_capacity_invalid', __( 'Media governance capacity facts must be non-negative integers.', 'npcink-cloud-addon' ), array( 'status' => 400 ) );
				}
				$normalized_capacity[ sanitize_key( (string) $key ) ] = $value;
			}

			$normalized_sources = array();
			foreach ( array_slice( $coverage['sources'], 0, 32 ) as $source ) {
				if ( ! is_string( $source ) || '' === trim( $source ) || strlen( trim( $source ) ) > 80 ) {
					return new WP_Error( 'cloud_toolbox_media_governance_audit_coverage_invalid', __( 'Media governance coverage sources are invalid.', 'npcink-cloud-addon' ), array( 'status' => 400 ) );
				}
				$normalized_sources[] = sanitize_text_field( $source );
			}

			$normalized_items = array();
			$item_keys = array( 'item_id', 'source_sha256', 'filesize_bytes', 'format', 'width', 'height', 'animated', 'reference_state', 'evidence_revision', 'evidence_sources' );
			$allowed_formats = array( 'jpeg', 'jpg', 'png', 'webp', 'gif', 'avif', 'svg', 'unknown', 'other' );
			$allowed_reference_states = array( 'referenced', 'no_known_reference', 'coverage_incomplete', 'dynamic_reference_possible', 'externally_observed' );
			foreach ( $items as $item ) {
				if ( ! is_array( $item ) || count( $item_keys ) !== count( $item ) || array() !== array_diff( $item_keys, array_keys( $item ) ) || array() !== array_diff( array_keys( $item ), $item_keys ) ) {
					return new WP_Error( 'cloud_toolbox_media_governance_audit_item_invalid', __( 'Media governance audit items require exact evidence fields.', 'npcink-cloud-addon' ), array( 'status' => 400 ) );
				}
				$item_id = sanitize_text_field( (string) $item['item_id'] );
				$source_sha256 = strtolower( preg_replace( '/^sha256:/i', '', trim( (string) $item['source_sha256'] ) ) );
				$format = sanitize_key( (string) $item['format'] );
				$reference_state = sanitize_key( (string) $item['reference_state'] );
				$evidence_revision = sanitize_text_field( (string) $item['evidence_revision'] );
				$evidence_sources = is_array( $item['evidence_sources'] ) ? array_values( array_filter( array_map( 'sanitize_text_field', array_slice( $item['evidence_sources'], 0, 32 ) ) ) ) : array();
				if (
					1 !== preg_match( '/^[A-Za-z0-9][A-Za-z0-9._:-]{0,159}$/', $item_id )
					|| 1 !== preg_match( '/^[0-9a-f]{64}$/', $source_sha256 )
					|| ! is_int( $item['filesize_bytes'] )
					|| $item['filesize_bytes'] <= 0
					|| ! in_array( $format, $allowed_formats, true )
					|| ! in_array( $reference_state, $allowed_reference_states, true )
					|| 1 !== preg_match( '/^[A-Za-z0-9][A-Za-z0-9._:-]{0,159}$/', $evidence_revision )
					|| ! is_bool( $item['animated'] )
					|| ( null !== $item['width'] && ( ! is_int( $item['width'] ) || $item['width'] <= 0 ) )
					|| ( null !== $item['height'] && ( ! is_int( $item['height'] ) || $item['height'] <= 0 ) )
				) {
					return new WP_Error( 'cloud_toolbox_media_governance_audit_item_invalid', __( 'Media governance audit item evidence is invalid.', 'npcink-cloud-addon' ), array( 'status' => 400 ) );
				}
				$normalized_items[] = array(
					'item_id'          => $item_id,
					'source_sha256'     => 'sha256:' . $source_sha256,
					'filesize_bytes'    => $item['filesize_bytes'],
					'format'            => $format,
					'width'             => $item['width'],
					'height'            => $item['height'],
					'animated'          => $item['animated'],
					'reference_state'   => $reference_state,
					'evidence_revision' => $evidence_revision,
					'evidence_sources'  => $evidence_sources,
				);
			}

			$input = array(
				'contract_version' => self::TOOLBOX_MEDIA_GOVERNANCE_AUDIT_CONTRACT,
				'snapshot'         => array(
					'snapshot_id'       => $snapshot_id,
					'captured_at'       => $captured_at,
					'inventory_complete' => $snapshot['inventory_complete'],
					'capacity'          => $normalized_capacity,
					'coverage'          => array( 'complete' => $coverage['complete'], 'sources' => $normalized_sources ),
					'items'             => $normalized_items,
				),
			);
			$encoded_input = wp_json_encode( $input );
			if ( ! is_string( $encoded_input ) || strlen( $encoded_input ) > self::TOOLBOX_MEDIA_GOVERNANCE_AUDIT_MAX_REQUEST_BYTES ) {
				return new WP_Error( 'cloud_toolbox_media_governance_audit_request_too_large', __( 'Media governance audit request exceeds the runtime size limit.', 'npcink-cloud-addon' ), array( 'status' => 413 ) );
			}

			return array(
				'ability_name'        => 'npcink-toolbox/audit-media-governance',
				'ability_family'      => 'vision',
				'contract_version'    => self::TOOLBOX_MEDIA_GOVERNANCE_AUDIT_CONTRACT,
				'channel'             => 'toolbox_media_governance',
				'execution_kind'      => 'media_governance_audit',
				'execution_pattern'   => 'inline',
				'profile_id'          => 'media-governance-audit.managed',
				'input'               => $input,
				'data_classification' => 'internal',
				'storage_mode'        => 'result_only',
				'retention_ttl'       => 3600,
				'timeout_seconds'     => 30,
				'retry_max'           => 0,
				'policy'              => array( 'allow_fallback' => false ),
			);
		}

		/**
		 * Normalizes a Toolbox managed web search request.
		 *
		 * @param array<string,mixed> $request Raw Toolbox web search request.
		 * @return array<string,mixed>|WP_Error
		 */
		public static function normalize_toolbox_web_search_request( array $request ) {
			$contract_version = (string) ( $request['contract_version'] ?? self::TOOLBOX_WEB_SEARCH_CONTRACT );
			if ( self::TOOLBOX_WEB_SEARCH_CONTRACT !== $contract_version ) {
				return new WP_Error(
					'cloud_toolbox_web_search_contract_invalid',
					__( 'Toolbox web search requests require the web_search.v1 contract.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}

			$forbidden_key = self::find_forbidden_wordpress_ai_connector_key( $request );
			if ( '' !== $forbidden_key ) {
				return new WP_Error(
					'cloud_toolbox_web_search_shape_not_allowed',
					__( 'Toolbox web search requests do not support generic chat sessions, tool calls, streams, or credential fields.', 'npcink-cloud-addon' ),
					array(
						'status' => 400,
						'key'    => $forbidden_key,
					)
				);
			}

			$encoded_request = wp_json_encode( $request );
			if ( ! is_string( $encoded_request ) || '' === $encoded_request ) {
				return new WP_Error(
					'cloud_toolbox_web_search_encode_failed',
					__( 'Toolbox web search request could not be encoded.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}
			if ( strlen( $encoded_request ) > self::TOOLBOX_WEB_SEARCH_MAX_REQUEST_BYTES ) {
				return new WP_Error(
					'cloud_toolbox_web_search_request_too_large',
					__( 'Toolbox web search request exceeds the runtime size limit.', 'npcink-cloud-addon' ),
					array( 'status' => 413 )
				);
			}

			$query = self::bounded_text( (string) ( $request['query'] ?? '' ), self::TOOLBOX_WEB_SEARCH_MAX_QUERY_CHARS );
			if ( '' === $query ) {
				return new WP_Error(
					'cloud_toolbox_web_search_query_required',
					__( 'Toolbox web search requires a query.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}

			$intent = sanitize_key( (string) ( $request['intent'] ?? 'news' ) );
			if ( ! in_array( $intent, self::TOOLBOX_WEB_SEARCH_ALLOWED_INTENTS, true ) ) {
				$intent = 'news';
			}

			$input = self::sanitize_payload( $request );
			if ( ! is_array( $input ) ) {
				$input = array();
			}
			$input['contract_version']       = self::TOOLBOX_WEB_SEARCH_CONTRACT;
			$input['query']                  = $query;
			$input['intent']                 = $intent;
			$input['max_results']            = max( 1, min( 5, absint( $request['max_results'] ?? 3 ) ) );
			$input['recency_days']           = max( 0, min( 30, absint( $request['recency_days'] ?? 7 ) ) );
			$input['write_posture']          = 'suggestion_only';
			$input['direct_wordpress_write'] = false;
			$input['connector_id']           = 'npcink-cloud-addon';
			if ( ! is_array( $input['evidence_policy'] ?? null ) ) {
				$input['evidence_policy'] = array(
					'required_sources' => 1,
					'no_hit_policy'    => 'abstain',
				);
			}

			$timeout_seconds = absint( $request['timeout_seconds'] ?? self::TOOLBOX_WEB_SEARCH_MAX_TIMEOUT_SECONDS );
			$retention_ttl   = absint( $request['retention_ttl'] ?? 3600 );

			return array(
				'ability_name'        => 'npcink-cloud/web-search',
				'ability_family'      => 'knowledge',
				'contract_version'    => self::TOOLBOX_WEB_SEARCH_CONTRACT,
				'channel'             => 'toolbox_web_search',
				'execution_kind'      => 'web_search',
				'execution_pattern'   => 'inline',
				'profile_id'          => sanitize_text_field( (string) ( $request['profile_id'] ?? 'web-search.managed' ) ),
				'input'               => $input,
				'data_classification' => 'public',
				'storage_mode'        => 'result_only',
				'retention_ttl'       => min( self::TOOLBOX_WEB_SEARCH_MAX_RETENTION_TTL, max( 0, $retention_ttl ) ),
				'timeout_seconds'     => min( self::TOOLBOX_WEB_SEARCH_MAX_TIMEOUT_SECONDS, max( 1, $timeout_seconds ) ),
				'retry_max'           => 0,
				'policy'              => array(
					'allow_fallback' => true,
				),
			);
		}

		/**
		 * Normalizes a Toolbox image-source candidate request.
		 *
		 * @param array<string,mixed> $request Raw Toolbox image-source request.
		 * @return array<string,mixed>|WP_Error
		 */
		public static function normalize_toolbox_image_source_request( array $request ) {
			$contract_version = (string) ( $request['contract_version'] ?? self::TOOLBOX_IMAGE_SOURCE_CONTRACT );
			if ( self::TOOLBOX_IMAGE_SOURCE_CONTRACT !== $contract_version ) {
				return new WP_Error(
					'cloud_toolbox_image_source_contract_invalid',
					__( 'Toolbox image-source requests require the image_source_cloud_request.v1 contract.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}

			$forbidden_key = self::find_forbidden_wordpress_ai_connector_key( $request );
			if ( '' !== $forbidden_key ) {
				return new WP_Error(
					'cloud_toolbox_image_source_shape_not_allowed',
					__( 'Toolbox image-source requests do not support generic chat sessions, tool calls, streams, or credential fields.', 'npcink-cloud-addon' ),
					array(
						'status' => 400,
						'key'    => $forbidden_key,
					)
				);
			}

			$encoded_request = wp_json_encode( $request );
			if ( ! is_string( $encoded_request ) || '' === $encoded_request ) {
				return new WP_Error(
					'cloud_toolbox_image_source_encode_failed',
					__( 'Toolbox image-source request could not be encoded.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}
			if ( strlen( $encoded_request ) > self::TOOLBOX_IMAGE_SOURCE_MAX_REQUEST_BYTES ) {
				return new WP_Error(
					'cloud_toolbox_image_source_request_too_large',
					__( 'Toolbox image-source request exceeds the runtime size limit.', 'npcink-cloud-addon' ),
					array( 'status' => 413 )
				);
			}

			$query = self::bounded_text( (string) ( $request['query'] ?? '' ), self::TOOLBOX_IMAGE_SOURCE_MAX_QUERY_CHARS );
			if ( '' === $query ) {
				return new WP_Error(
					'cloud_toolbox_image_source_query_required',
					__( 'Toolbox image-source search requires a query.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}

			$provider = sanitize_key( (string) ( $request['provider'] ?? 'auto' ) );
			if ( ! in_array( $provider, self::TOOLBOX_IMAGE_SOURCE_ALLOWED_PROVIDERS, true ) ) {
				$provider = 'auto';
			}

			$latency_mode = sanitize_key( (string) ( $request['latency_mode'] ?? 'complete' ) );
			if ( ! in_array( $latency_mode, self::TOOLBOX_IMAGE_SOURCE_ALLOWED_LATENCY_MODES, true ) ) {
				$latency_mode = 'complete';
			}

			$input = self::sanitize_payload( $request );
			if ( ! is_array( $input ) ) {
				$input = array();
			}
			$input['contract_version']       = self::TOOLBOX_IMAGE_SOURCE_CONTRACT;
			$input['query']                  = $query;
			$input['provider']               = $provider;
			$input['provider_origin']        = 'cloud';
			$input['per_page']               = max( 1, min( 30, absint( $request['per_page'] ?? 8 ) ) );
			$input['latency_mode']           = $latency_mode;
			$input['candidate_contract']     = 'image_candidate.v1';
			$input['write_posture']          = 'suggestion_only';
			$input['direct_wordpress_write'] = false;
			$input['connector_id']           = 'npcink-cloud-addon';

			$storage_mode = sanitize_key( (string) ( $request['storage_mode'] ?? 'result_only' ) );
			if ( ! in_array( $storage_mode, array( 'result_only', 'no_store' ), true ) ) {
				$storage_mode = 'result_only';
			}
			$data_classification = sanitize_key( (string) ( $request['data_classification'] ?? 'public_reference_media' ) );
			if ( '' === $data_classification ) {
				$data_classification = 'public_reference_media';
			}
			$default_timeout = 'fast_first' === $latency_mode ? 5 : self::TOOLBOX_IMAGE_SOURCE_MAX_TIMEOUT_SECONDS;
			$timeout_seconds = absint( $request['timeout_seconds'] ?? $default_timeout );
			$retention_ttl   = absint( $request['retention_ttl'] ?? 3600 );

			return array(
				'ability_name'        => 'npcink-toolbox/search-image-source',
				'contract_version'    => self::TOOLBOX_IMAGE_SOURCE_CONTRACT,
				'channel'             => 'toolbox_image_source',
				'execution_kind'      => 'image_source',
				'execution_pattern'   => 'inline',
				'profile_id'          => sanitize_text_field( (string) ( $request['profile_id'] ?? 'image-source.managed' ) ),
				'input'               => $input,
				'data_classification' => $data_classification,
				'storage_mode'        => $storage_mode,
				'retention_ttl'       => min( self::TOOLBOX_IMAGE_SOURCE_MAX_RETENTION_TTL, max( 0, $retention_ttl ) ),
				'timeout_seconds'     => min( self::TOOLBOX_IMAGE_SOURCE_MAX_TIMEOUT_SECONDS, max( 1, $timeout_seconds ) ),
				'retry_max'           => 0,
				'policy'              => array(
					'allow_fallback' => true,
				),
			);
		}

		/**
		 * Finds a forbidden chat/provider-control key in a connector request.
		 *
		 * @param mixed $value Raw request value.
		 * @return string
		 */
		private static function find_forbidden_wordpress_ai_connector_key( $value ): string {
			if ( ! is_array( $value ) ) {
				return '';
			}

			foreach ( $value as $key => $item ) {
				$normalized_key = sanitize_key( str_replace( '-', '_', (string) $key ) );
				if ( in_array( $normalized_key, self::WP_AI_CONNECTOR_FORBIDDEN_KEYS, true ) ) {
					return $normalized_key;
				}
				$nested = self::find_forbidden_wordpress_ai_connector_key( $item );
				if ( '' !== $nested ) {
					return $nested;
				}
			}

			return '';
		}

		/**
		 * Validates one Agent feedback event against the exact Cloud-owned schema.
		 *
		 * @param array<string,mixed> $payload Raw feedback payload.
		 * @return array<string,mixed>|WP_Error
		 */
		public static function normalize_agent_feedback_payload( array $payload ) {
			$allowed_fields = array(
				'contract_version'    => 32,
				'site_id'             => 191,
				'agent_id'            => 96,
				'agent_version'       => 64,
				'source_runtime'      => 64,
				'source_run_id'       => 191,
				'handoff_id'          => 191,
				'handoff_type'        => 64,
				'local_surface'       => 96,
				'local_outcome'       => 64,
				'operator_note'       => 500,
				'local_proposal_id'   => 191,
				'source_action_id'    => 191,
				'source_object_type'  => 64,
				'source_object_id'    => 191,
				'source_severity'     => 64,
				'redaction_status'    => 64,
				'retention_class'     => 64,
				'created_at'          => 64,
			);
			$list_fields = array(
				'feedback_labels'     => array( 12, 64 ),
				'evidence_ref_ids'    => array( 24, 191 ),
				'source_reason_codes' => array( 12, 96 ),
			);
			$all_fields = array_merge( array_keys( $allowed_fields ), array_keys( $list_fields ), array( 'source_score' ) );
			$required_fields = array( 'contract_version', 'agent_id', 'source_runtime', 'handoff_type', 'local_surface', 'local_outcome', 'created_at' );

			$forbidden_key = self::find_forbidden_agent_feedback_key( $payload );
			if ( '' !== $forbidden_key ) {
				return new WP_Error(
					'cloud_agent_feedback_write_authority_not_allowed',
					__( 'Agent feedback may not carry approval, preflight, or WordPress write authority.', 'npcink-cloud-addon' ),
					array( 'status' => 400, 'key' => $forbidden_key )
				);
			}
			if ( array() !== array_diff( array_keys( $payload ), $all_fields ) || array() !== array_diff( $required_fields, array_keys( $payload ) ) ) {
				return new WP_Error(
					'cloud_agent_feedback_payload_invalid',
					__( 'Agent feedback contains missing or unsupported fields.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}

			$normalized = array();
			foreach ( $allowed_fields as $field => $max_chars ) {
				if ( ! array_key_exists( $field, $payload ) ) {
					continue;
				}
				if ( ! is_string( $payload[ $field ] ) ) {
					return new WP_Error(
						'cloud_agent_feedback_payload_invalid',
						__( 'Agent feedback scalar fields must be strings.', 'npcink-cloud-addon' ),
						array( 'status' => 400, 'field' => $field )
					);
				}
				$value = trim( $payload[ $field ] );
				if ( self::text_length( $value ) > $max_chars || ( in_array( $field, $required_fields, true ) && '' === $value ) ) {
					return new WP_Error(
						'cloud_agent_feedback_payload_invalid',
						__( 'Agent feedback contains an empty or oversized field.', 'npcink-cloud-addon' ),
						array( 'status' => 400, 'field' => $field )
					);
				}
				$normalized[ $field ] = $value;
			}

			if ( 'cloud_agent_feedback.v1' !== (string) ( $normalized['contract_version'] ?? '' ) ) {
				return new WP_Error(
					'cloud_agent_feedback_payload_invalid',
					__( 'Agent feedback requires the cloud_agent_feedback.v1 contract.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}
			if ( ! in_array( (string) $normalized['local_outcome'], self::AGENT_FEEDBACK_ALLOWED_OUTCOMES, true ) ) {
				return new WP_Error(
					'cloud_agent_feedback_payload_invalid',
					__( 'Agent feedback contains an unsupported local outcome.', 'npcink-cloud-addon' ),
					array( 'status' => 400, 'field' => 'local_outcome' )
				);
			}
			$created_at = (string) $normalized['created_at'];
			$created_at_valid = 1 === preg_match(
				'/\A\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{1,6})?(?:Z|[+-]\d{2}:\d{2})\z/',
				$created_at
			);
			try {
				$created_at_date = $created_at_valid ? new DateTimeImmutable( $created_at ) : false;
			} catch ( Exception $exception ) {
				$created_at_date = false;
			}
			if ( false === $created_at_date ) {
				return new WP_Error(
					'cloud_agent_feedback_payload_invalid',
					__( 'Agent feedback created_at must be a valid date and time.', 'npcink-cloud-addon' ),
					array( 'status' => 400, 'field' => 'created_at' )
				);
			}

			foreach ( $list_fields as $field => $limits ) {
				if ( ! array_key_exists( $field, $payload ) ) {
					continue;
				}
				$value = $payload[ $field ];
				$is_list = is_array( $value ) && ( array() === $value || array_keys( $value ) === range( 0, count( $value ) - 1 ) );
				if ( ! $is_list || count( $value ) > $limits[0] ) {
					return new WP_Error(
						'cloud_agent_feedback_payload_invalid',
						__( 'Agent feedback list fields must be bounded lists.', 'npcink-cloud-addon' ),
						array( 'status' => 400, 'field' => $field )
					);
				}
				$normalized[ $field ] = array();
				foreach ( $value as $item ) {
					if ( ! is_string( $item ) || '' === trim( $item ) || self::text_length( trim( $item ) ) > $limits[1] ) {
						return new WP_Error(
							'cloud_agent_feedback_payload_invalid',
							__( 'Agent feedback list entries must be bounded strings.', 'npcink-cloud-addon' ),
							array( 'status' => 400, 'field' => $field )
						);
					}
					$item = trim( $item );
					if ( 'feedback_labels' === $field && ! in_array( $item, self::AGENT_FEEDBACK_ALLOWED_LABELS, true ) ) {
						return new WP_Error(
							'cloud_agent_feedback_payload_invalid',
							__( 'Agent feedback contains an unsupported feedback label.', 'npcink-cloud-addon' ),
							array( 'status' => 400, 'field' => $field )
						);
					}
					if ( ! in_array( $item, $normalized[ $field ], true ) ) {
						$normalized[ $field ][] = $item;
					}
				}
			}

			if ( array_key_exists( 'source_score', $payload ) ) {
				if ( null !== $payload['source_score'] && ( ! is_int( $payload['source_score'] ) || $payload['source_score'] < 0 || $payload['source_score'] > 100 ) ) {
					return new WP_Error(
						'cloud_agent_feedback_payload_invalid',
						__( 'Agent feedback source_score must be an integer from 0 through 100.', 'npcink-cloud-addon' ),
						array( 'status' => 400, 'field' => 'source_score' )
					);
				}
				$normalized['source_score'] = $payload['source_score'];
			}

			return $normalized;
		}

		/**
		 * Finds forbidden write-authority fields anywhere in Agent feedback input.
		 *
		 * @param mixed  $value Value to inspect.
		 * @param string $prefix Current field path.
		 * @return string
		 */
		private static function find_forbidden_agent_feedback_key( $value, string $prefix = '' ): string {
			if ( ! is_array( $value ) ) {
				return '';
			}
			foreach ( $value as $key => $item ) {
				$normalized_key = strtolower( trim( (string) $key ) );
				$current_path = '' === $prefix ? $normalized_key : $prefix . '.' . $normalized_key;
				if ( in_array( $normalized_key, self::AGENT_FEEDBACK_FORBIDDEN_KEYS, true ) ) {
					return $current_path;
				}
				$nested = self::find_forbidden_agent_feedback_key( $item, $current_path );
				if ( '' !== $nested ) {
					return $nested;
				}
			}

			return '';
		}

		/**
		 * Normalizes a Toolbox image context evidence request.
		 *
		 * @param array<string,mixed> $request Raw request.
		 * @return array<string,mixed>|WP_Error
		 */
		public static function normalize_image_context_evidence_request( array $request ) {
			$dispatch_mode = sanitize_key( (string) ( $request['dispatch_mode'] ?? 'interactive' ) );
			if (
				'image_context_evidence_request.v1' !== (string) ( $request['contract_version'] ?? '' )
				|| 'suggestion_only' !== (string) ( $request['write_posture'] ?? '' )
				|| false !== (bool) ( $request['direct_wordpress_write'] ?? true )
				|| false === (bool) ( $request['no_local_model'] ?? false )
				|| false === (bool) ( $request['no_media_write'] ?? false )
				|| ! in_array( $dispatch_mode, array( 'interactive', 'background_completion' ), true )
			) {
				return new WP_Error(
					'cloud_image_context_evidence_request_invalid',
					__( 'Image context evidence requires a suggestion-only no-write request contract.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}

			$items = is_array( $request['items'] ?? null ) ? $request['items'] : array();
			$normalized_items = array();
			foreach ( $items as $item ) {
				if ( ! is_array( $item ) ) {
					continue;
				}
				$attachment_id = absint( $item['attachment_id'] ?? 0 );
				$source_artifact_id = trim( (string) ( $item['source_artifact_id'] ?? '' ) );
				$attachment_url = esc_url_raw( (string) ( $item['attachment_url'] ?? '' ) );
				$media_fingerprint = sanitize_text_field( (string) ( $item['media_fingerprint'] ?? '' ) );
				$mime_type = strtolower( sanitize_text_field( (string) ( $item['mime_type'] ?? '' ) ) );
				$url           = esc_url_raw( (string) ( $item['url'] ?? '' ) );
				$thumbnail_url = esc_url_raw( (string) ( $item['thumbnail_url'] ?? '' ) );
				if ( '' !== $source_artifact_id && ( '' !== $url || '' !== $thumbnail_url ) ) {
					return new WP_Error(
						'cloud_image_context_evidence_source_conflict',
						__( 'Image context evidence items must use either a source artifact or media URLs.', 'npcink-cloud-addon' ),
						array( 'status' => 400 )
					);
				}
				if (
					0 >= $attachment_id
					|| (
						'' === $source_artifact_id
						&& '' === $url
						&& '' === $thumbnail_url
					)
					|| (
						'' !== $source_artifact_id
						&& 1 !== preg_match( self::MEDIA_ARTIFACT_ID_PATTERN, $source_artifact_id )
					)
				) {
					continue;
				}
				if (
					'background_completion' === $dispatch_mode
					&& (
						'' === $attachment_url
						|| '' === $media_fingerprint
						|| 0 !== strpos( $mime_type, 'image/' )
					)
				) {
					return new WP_Error(
						'cloud_image_context_evidence_background_identity_invalid',
						__( 'Background image recognition requires an attachment URL, media fingerprint, and image MIME type.', 'npcink-cloud-addon' ),
						array( 'status' => 400 )
					);
				}
				$normalized_item = array(
					'attachment_id'            => $attachment_id,
					'mime_type'                => $mime_type,
					'current_alt_status'       => sanitize_key( (string) ( $item['current_alt_status'] ?? '' ) ),
					'current_caption_status'   => sanitize_key( (string) ( $item['current_caption_status'] ?? '' ) ),
					'candidate_quality_flags'  => array_slice( self::sanitize_string_list( $item['candidate_quality_flags'] ?? array() ), 0, 12 ),
					'filtered_candidate_notes' => array_slice( self::sanitize_string_list( $item['filtered_candidate_notes'] ?? array() ), 0, 12 ),
				);
				if ( 'background_completion' !== $dispatch_mode ) {
					$normalized_item['title'] = self::bounded_text( (string) ( $item['title'] ?? '' ), 160 );
					$normalized_item['filename'] = sanitize_file_name( (string) ( $item['filename'] ?? '' ) );
				} else {
					$normalized_item['attachment_url'] = $attachment_url;
					$normalized_item['media_fingerprint'] = $media_fingerprint;
				}
				if ( '' !== $source_artifact_id ) {
					$normalized_item['source_artifact_id'] = $source_artifact_id;
				} else {
					$normalized_item['thumbnail_url'] = $thumbnail_url;
					$normalized_item['url']           = $url;
				}
				$normalized_items[] = $normalized_item;
				if ( count( $normalized_items ) >= 10 ) {
					break;
				}
			}

			if ( empty( $normalized_items ) ) {
				return new WP_Error(
					'cloud_image_context_evidence_request_empty',
					__( 'Image context evidence requires at least one bounded source artifact or media URL.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}
			$source_policy = 'bounded_media_urls_for_visual_context_only';
			$artifact_count = count(
				array_filter(
					$normalized_items,
					static function ( array $item ): bool {
						return '' !== (string) ( $item['source_artifact_id'] ?? '' );
					}
				)
			);
			if ( count( $normalized_items ) === $artifact_count ) {
				$source_policy = 'bounded_source_artifacts_for_visual_context_only';
			} elseif ( 0 < $artifact_count ) {
				$source_policy = 'bounded_media_sources_for_visual_context_only';
			}

			return array(
				'contract_version'           => 'image_context_evidence_request.v1',
				'artifact_type'              => 'image_context_evidence_request',
				'runtime_owner'              => 'cloud_or_host_runtime',
				'write_posture'              => 'suggestion_only',
				'direct_wordpress_write'     => false,
				'proposal_created'           => false,
				'execution_created'          => false,
				'no_local_model'             => true,
				'no_media_write'             => true,
				'source_policy'              => $source_policy,
				'expected_response_contract' => 'image_context_evidence.v1',
				'dispatch_mode'              => $dispatch_mode,
				'requested_count'            => count( $normalized_items ),
				'max_items'                  => count( $normalized_items ),
				'items'                      => $normalized_items,
				'operator_next_action'       => 'request_cloud_image_context_evidence',
			);
		}

		/**
		 * Normalizes a Cloud response into image_context_evidence.v1.
		 *
		 * @param array<string,mixed> $response Cloud response.
		 * @param array<string,mixed> $request Normalized request.
		 * @return array<string,mixed>|WP_Error
		 */
		public static function normalize_image_context_evidence_response( array $response, array $request ) {
			$payload = self::extract_image_context_evidence_payload( $response );
			$payload_source = $payload['source'] ?? 'cloud_or_host_runtime';
			$source         = self::normalize_image_context_evidence_source( $payload_source );
			$model_id       = is_array( $payload_source )
				? sanitize_text_field( (string) ( $payload_source['model_id'] ?? ( $payload['model_id'] ?? '' ) ) )
				: sanitize_text_field( (string) ( $payload['model_id'] ?? '' ) );
			$requested_ids = array();
			foreach ( (array) ( $request['items'] ?? array() ) as $request_item ) {
				if ( is_array( $request_item ) ) {
					$requested_ids[ absint( $request_item['attachment_id'] ?? 0 ) ] = true;
				}
			}

			$items = array();
			foreach ( (array) ( $payload['items'] ?? array() ) as $item ) {
				if ( ! is_array( $item ) ) {
					continue;
				}
				$attachment_id = absint( $item['attachment_id'] ?? 0 );
				if ( 0 >= $attachment_id || empty( $requested_ids[ $attachment_id ] ) ) {
					continue;
				}
				$subject_tags = array_slice(
					self::sanitize_string_list( $item['subject_tags'] ?? ( $item['objects'] ?? array() ) ),
					0,
					12
				);
				$visible_text = array_slice(
					self::sanitize_string_list( $item['visible_text'] ?? ( $item['text_seen'] ?? array() ) ),
					0,
					8
				);
				$items[] = array(
					'attachment_id'              => $attachment_id,
					'contract_version'           => 'image_context_evidence.v1',
					'source'                     => self::normalize_image_context_evidence_source( $item['source'] ?? $payload_source ),
					'visual_summary'             => self::normalize_image_context_evidence_text( $item['visual_summary'] ?? '', 240 ),
					'scene'                      => self::normalize_image_context_evidence_text( $item['scene'] ?? '', 160 ),
					'subject_tags'               => $subject_tags,
					'visible_text'               => $visible_text,
					'alt_text_basis'             => self::normalize_image_context_evidence_text( $item['alt_text_basis'] ?? '', 240 ),
					'caption_basis'              => self::normalize_image_context_evidence_text( $item['caption_basis'] ?? '', 320 ),
					'uncertainty_flags'          => array_slice( self::sanitize_string_list( $item['uncertainty_flags'] ?? array() ), 0, 12 ),
					'requires_human_visual_check' => true,
					'objects'                    => $subject_tags,
					'text_seen'                  => $visible_text,
					'confidence'                 => is_numeric( $item['confidence'] ?? null )
						? max( 0.0, min( 1.0, (float) $item['confidence'] ) )
						: sanitize_text_field( (string) ( $item['confidence'] ?? '' ) ),
					'write_posture'              => 'suggestion_only',
					'direct_wordpress_write'     => false,
					'needs_human_visual_check'   => true,
				);
				if ( count( $items ) >= 10 ) {
					break;
				}
			}

			if ( empty( $items ) ) {
				return new WP_Error(
					'cloud_image_context_evidence_empty',
					__( 'Cloud did not return usable image context evidence.', 'npcink-cloud-addon' ),
					array( 'status' => 502 )
				);
			}

			return array(
				'contract_version'         => 'image_context_evidence.v1',
				'artifact_type'            => 'image_context_evidence',
				'runtime_owner'            => 'cloud_service',
				'source'                   => '' !== $source ? $source : 'cloud_or_host_runtime',
				'write_posture'            => 'suggestion_only',
				'direct_wordpress_write'   => false,
				'proposal_created'         => false,
				'execution_created'        => false,
				'requested_count'          => (int) ( $request['requested_count'] ?? count( $items ) ),
				'evidence_count'           => count( $items ),
				'run_id'                   => sanitize_text_field( (string) ( $response['run_id'] ?? ( $payload['run_id'] ?? '' ) ) ),
				'model_id'                 => $model_id,
				'items'                    => $items,
				'safety'                   => array(
					'local_model_used'             => false,
					'core_proposal_created'        => false,
					'direct_wordpress_write'       => false,
					'requires_human_visual_check'  => true,
				),
			);
		}

		/**
		 * Normalizes a scalar or structured runtime source into a non-secret label.
		 *
		 * @param mixed $source Raw source value.
		 * @return string
		 */
		private static function normalize_image_context_evidence_source( $source ): string {
			if ( is_array( $source ) ) {
				$source = $source['evidence_basis'] ?? 'cloud_or_host_runtime';
			}

			return is_scalar( $source ) ? sanitize_key( (string) $source ) : 'cloud_or_host_runtime';
		}

		/**
		 * Normalizes scalar or list-shaped evidence text without array coercion.
		 *
		 * @param mixed $value Raw evidence value.
		 * @param int   $max_chars Maximum characters.
		 * @return string
		 */
		private static function normalize_image_context_evidence_text( $value, int $max_chars ): string {
			if ( is_array( $value ) ) {
				$value = implode( '; ', array_slice( self::sanitize_string_list( $value ), 0, 8 ) );
			}

			return is_scalar( $value ) ? self::bounded_text( (string) $value, $max_chars ) : '';
		}

		/**
		 * Extracts image context evidence from common runtime envelopes.
		 *
		 * @param array<string,mixed> $response Cloud response.
		 * @return array<string,mixed>
		 */
		private static function extract_image_context_evidence_payload( array $response ): array {
			$candidates = array(
				$response['image_context_evidence'] ?? null,
				$response['data']['image_context_evidence'] ?? null,
				$response['result']['image_context_evidence'] ?? null,
				$response['data']['result']['image_context_evidence'] ?? null,
				$response['result'] ?? null,
				$response['data']['result'] ?? null,
				$response['data'] ?? null,
				$response,
			);

			foreach ( $candidates as $candidate ) {
				if ( ! is_array( $candidate ) ) {
					continue;
				}
				if ( 'image_context_evidence.v1' === (string) ( $candidate['contract_version'] ?? '' ) || is_array( $candidate['items'] ?? null ) ) {
					return $candidate;
				}
			}

			return array();
		}

		/**
		 * Sanitizes a scalar-or-array string list.
		 *
		 * @param mixed $value Raw list.
		 * @return array<int,string>
		 */
		private static function sanitize_string_list( $value ): array {
			$items = is_array( $value ) ? $value : preg_split( '/[\r\n,]+/', (string) $value );
			$items = is_array( $items ) ? $items : array();

			return array_values(
				array_filter(
					array_map(
						static function ( $item ): string {
							return is_scalar( $item ) ? self::bounded_text( (string) $item, 120 ) : '';
						},
						$items
					),
					static function ( string $item ): bool {
						return '' !== $item;
					}
				)
			);
		}

		/**
		 * Sanitizes and bounds text.
		 *
		 * @param string $value Raw value.
		 * @param int    $max_chars Maximum characters.
		 * @return string
		 */
		private static function bounded_text( string $value, int $max_chars ): string {
			$value = trim( sanitize_text_field( wp_strip_all_tags( $value ) ) );
			$value = preg_replace( '/\s+/u', ' ', $value );
			$value = is_string( $value ) ? trim( $value ) : '';
			$max_chars = max( 1, $max_chars );
			if ( function_exists( 'mb_strlen' ) && function_exists( 'mb_substr' ) && mb_strlen( $value, 'UTF-8' ) > $max_chars ) {
				return mb_substr( $value, 0, $max_chars, 'UTF-8' );
			}

			return strlen( $value ) > $max_chars ? substr( $value, 0, $max_chars ) : $value;
		}

		/**
		 * Sanitizes small runtime evidence arrays before projecting them to Cloud.
		 *
		 * @param mixed $value Raw payload value.
		 * @param int   $depth Recursion depth.
		 * @return mixed
		 */
		private static function sanitize_payload( $value, int $depth = 0 ) {
			if ( $depth >= 5 ) {
				return null;
			}
			if ( is_array( $value ) ) {
				$sanitized = array();
				foreach ( $value as $key => $item ) {
					$normalized_key = sanitize_key( (string) $key );
					if ( '' === $normalized_key ) {
						continue;
					}
					if ( in_array( $normalized_key, self::WP_AI_CONNECTOR_FORBIDDEN_KEYS, true ) ) {
						continue;
					}
					$sanitized[ $normalized_key ] = self::sanitize_payload( $item, $depth + 1 );
				}
				return $sanitized;
			}
			if ( is_bool( $value ) || is_int( $value ) || is_float( $value ) || null === $value ) {
				return $value;
			}

			return self::bounded_text( (string) $value, 1200 );
		}

		/**
		 * Returns the character length of a UTF-8 string when available.
		 *
		 * @param string $value Raw text.
		 * @return int
		 */
		private static function text_length( string $value ): int {
			if ( function_exists( 'mb_strlen' ) ) {
				return mb_strlen( $value, 'UTF-8' );
			}

			return strlen( $value );
		}

		/**
		 * Normalizes Cloud ids used in URL paths.
		 *
		 * @param string $value Raw identifier.
		 * @return string
		 */
		public static function normalize_identifier( string $value ): string {
			$value = sanitize_text_field( trim( $value ) );
			$value = preg_replace( '/[^A-Za-z0-9._:-]/', '', $value );

			return is_string( $value ) ? $value : '';
		}
	}
}
