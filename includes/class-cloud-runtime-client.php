<?php
/**
 * Cloud runtime client.
 *
 * @package NpcinkCloudAddon
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Npcink_Cloud_Runtime_Client' ) ) {
	/**
	 * Signs and dispatches requests to the Npcink Cloud runtime plane.
	 */
	final class Npcink_Cloud_Runtime_Client {
		private const MAX_JSON_RESPONSE_BYTES = 1048576;
		private const MAX_ERROR_MESSAGE_CHARS = 4096;
		private const MAX_DOWNLOAD_BYTES = 26214400;
		private const MEDIA_ARTIFACT_ID_PATTERN = '/^art_[0-9a-f]{32}$/';
		private const MEDIA_DELIVERY_ID_PATTERN = '/^mdl_[0-9a-f]{32}$/';
		private const MEDIA_UPLOAD_FORMATS = array(
			'image/avif' => 'avif',
			'image/jpeg' => 'jpeg',
			'image/png'  => 'png',
			'image/webp' => 'webp',
		);
		private const CLOUD_CONNECTOR_RUNTIME_CONTRACT = 'cloud_connector_runtime.v1';
		private const WORDPRESS_OPERATION_CONTRACT = 'wordpress_operation.v1';
		private const WP_AI_CONNECTOR_MAX_REQUEST_BYTES = 24000;
		private const WP_AI_CONNECTOR_MAX_SCENE_TEXT_CHARS = 12000;
		private const WP_AI_CONNECTOR_MAX_TIMEOUT_SECONDS = 60;
		private const WP_AI_CONNECTOR_MAX_RETENTION_TTL = 86400;
		private const WP_AI_ALT_TEXT_MAX_UPLOAD_BYTES = 8388608;
		private const WP_AI_ALT_TEXT_MIN_ARTIFACT_TTL_SECONDS = 120;
		private const WP_AI_ALT_TEXT_UPLOAD_FORMATS = array(
			'image/jpeg' => 'jpeg',
			'image/png'  => 'png',
			'image/webp' => 'webp',
		);
		private const WP_AI_IMAGE_GENERATION_CONTRACT = 'image_generation_request.v1';
		private const WP_AI_IMAGE_GENERATION_MAX_REQUEST_BYTES = 12000;
		private const WP_AI_IMAGE_GENERATION_MAX_PROMPT_CHARS = 4000;
		private const WP_AI_IMAGE_GENERATION_MAX_TIMEOUT_SECONDS = 90;
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
		private const REQUEST_IDENTIFIER_MAX_CHARS = 128;
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
			'content_classification',
			'content_rewrite',
			'content_summary',
			'excerpt_generation',
			'meta_description',
			'title_generation',
		);
		private const WP_AI_CONNECTOR_SOURCE_TEXT_TASKS = array(
			'content_rewrite',
			'content_summary',
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
		 * Normalized client configuration.
		 *
		 * @var array<string,mixed>
		 */
		private $config = array();

		/**
		 * Constructor.
		 *
		 * @param array<string,mixed> $config Optional settings override.
		 */
		public function __construct( array $config = array() ) {
			$base = class_exists( 'Npcink_Cloud_Addon_Settings' )
				? Npcink_Cloud_Addon_Settings::get_settings()
				: array();

			$this->config = array_merge( is_array( $base ) ? $base : array(), $config );
		}

		/**
		 * Returns whether credentials are complete.
		 *
		 * @return bool
		 */
		public function is_configured(): bool {
			return '' !== (string) ( $this->config['base_url'] ?? '' )
				&& '' !== (string) ( $this->config['site_id'] ?? '' )
				&& '' !== (string) ( $this->config['key_id'] ?? '' )
				&& '' !== (string) ( $this->config['secret'] ?? '' );
		}

		/**
		 * Probes liveness and one signed read endpoint.
		 *
		 * @return array<string,mixed>
		 */
		public function probe_connectivity(): array {
			$live_probe = $this->request_live_probe();
			$auth_probe = array(
				'ok' => false,
				'message' => '',
				'error_code' => '',
				'error_data' => array(),
			);

			if ( empty( $live_probe['ok'] ) ) {
				$auth_probe['message'] = __( 'Signed verification was not attempted because the Cloud service is not reachable.', 'npcink-cloud-addon' );
			} elseif ( ! $this->is_configured() ) {
				$auth_probe['message'] = __( 'Cloud credentials are incomplete.', 'npcink-cloud-addon' );
			} else {
				$result = $this->get_current_entitlement( 'trace_cloud_probe_' . wp_generate_uuid4() );
				if ( is_wp_error( $result ) ) {
					$auth_probe['message'] = $result->get_error_message();
					$error_data = $result->get_error_data();
					$auth_probe['error_code'] = $this->normalize_remote_error_code( is_array( $error_data ) ? ( $error_data['cloud_error_code'] ?? '' ) : '' );
					$auth_probe['error_data'] = is_array( $error_data ) ? $error_data : array();
				} else {
					$auth_probe['ok'] = true;
					$auth_probe['message'] = __( 'Signed Cloud request verified.', 'npcink-cloud-addon' );
					$auth_probe['entitlement_response'] = $result;
				}
			}

			$probe = array(
				'ok' => ! empty( $live_probe['ok'] ) && ! empty( $auth_probe['ok'] ),
				'live_ok' => ! empty( $live_probe['ok'] ),
				'auth_ok' => ! empty( $auth_probe['ok'] ),
				'live_message' => sanitize_text_field( (string) ( $live_probe['message'] ?? '' ) ),
				'auth_message' => sanitize_text_field( (string) ( $auth_probe['message'] ?? '' ) ),
				'auth_error_code' => (string) ( $auth_probe['error_code'] ?? '' ),
				'auth_error_data' => is_array( $auth_probe['error_data'] ?? null ) ? $auth_probe['error_data'] : array(),
				'entitlement_response' => is_array( $auth_probe['entitlement_response'] ?? null ) ? $auth_probe['entitlement_response'] : array(),
			);
			$probe['readiness_result'] = $this->build_readiness_result( $probe );

			return $probe;
		}

		/**
		 * Runs the bounded manual connector readiness test.
		 *
		 * This is the same liveness plus signed-read check used by Save and
		 * Verify. It returns a non-secret support shape for local status and
		 * optional read-only consumers.
		 *
		 * @return array<string,mixed>
		 */
		public function manual_readiness_test(): array {
			$probe = $this->probe_connectivity();

			return is_array( $probe['readiness_result'] ?? null ) ? $probe['readiness_result'] : $this->build_readiness_result( $probe );
		}

		/**
		 * Executes one runtime request.
		 *
		 * @param array<string,mixed> $payload Runtime execute payload.
		 * @param string              $trace_id Optional trace id.
		 * @param string              $idempotency_key Optional idempotency key.
		 * @deprecated 0.1.7 Prefer a scenario-specific runtime method or public facade.
		 * @return array<string,mixed>|WP_Error
		 */
		public function execute_runtime( array $payload, string $trace_id = '', string $idempotency_key = '' ) {
			if ( '' === $idempotency_key ) {
				$idempotency_key = 'runtime_' . wp_generate_uuid4();
			}

			return $this->request( 'POST', '/v1/runtime/execute', $payload, $idempotency_key, $trace_id );
		}

		/**
		 * Registers or disables the current site's signed terminal callback.
		 *
		 * @param array<string,mixed> $payload Callback registration contract.
		 * @param string              $idempotency_key Stable registration identity.
		 * @return array<string,mixed>|WP_Error
		 */
		public function register_terminal_callback( array $payload, string $idempotency_key ) {
			return $this->request(
				'POST',
				'/v1/runtime/callbacks/terminal',
				$payload,
				$idempotency_key,
				'trace_callback_' . wp_generate_uuid4()
			);
		}

		/**
		 * Executes one bounded WordPress AI connector scene request.
		 *
		 * This method is intentionally not a generic chat transport. Callers
		 * must provide a known WordPress task surface, and the addon projects it
		 * into a suggestion-only runtime contract for Cloud execution.
		 *
		 * @param array<string,mixed> $request WordPress AI connector request.
		 * @param string              $trace_id Optional trace id.
		 * @param string              $idempotency_key Optional idempotency key.
		 * @return array<string,mixed>|WP_Error
		 */
		public function execute_wordpress_ai_connector_runtime( array $request, string $trace_id = '', string $idempotency_key = '' ) {
			$payload = $this->normalize_wordpress_ai_connector_request( $request );
			if ( is_wp_error( $payload ) ) {
				return $payload;
			}

			if ( '' === $idempotency_key ) {
				$idempotency_key = 'wp_ai_connector_' . wp_generate_uuid4();
			}

			return $this->request( 'POST', '/v1/runtime/execute', $payload, $idempotency_key, $trace_id );
		}

		/**
		 * Executes one bounded WordPress AI image generation scene request.
		 *
		 * This method is not a generic image provider proxy. It only transports
		 * text-to-image requests coming from the WordPress AI image generation
		 * feature and lets Cloud own provider routing and model choice.
		 *
		 * @param array<string,mixed> $request WordPress AI image generation request.
		 * @param string              $trace_id Optional trace id.
		 * @param string              $idempotency_key Optional idempotency key.
		 * @return array<string,mixed>|WP_Error
		 */
		public function execute_wordpress_ai_image_generation_runtime( array $request, string $trace_id = '', string $idempotency_key = '' ) {
			$payload = $this->normalize_wordpress_ai_image_generation_request( $request );
			if ( is_wp_error( $payload ) ) {
				return $payload;
			}

			if ( '' === $idempotency_key ) {
				$idempotency_key = 'wp_ai_image_generation_' . wp_generate_uuid4();
			}

			return $this->request( 'POST', '/v1/runtime/execute', $payload, $idempotency_key, $trace_id );
		}

		/**
		 * Executes one bounded Toolbox AI image generation runtime request.
		 *
		 * This method is a transport seam for Toolbox image candidates. The
		 * addon signs and dispatches the Cloud runtime request only; Toolbox
		 * keeps candidate UX/normalization, and Core/Abilities keep adoption.
		 *
		 * @param array<string,mixed> $request Toolbox image generation request.
		 * @param string              $trace_id Optional trace id.
		 * @param string              $idempotency_key Optional idempotency key.
		 * @return array<string,mixed>|WP_Error
		 */
		public function execute_toolbox_image_generation_runtime( array $request, string $trace_id = '', string $idempotency_key = '' ) {
			$payload = $this->normalize_toolbox_image_generation_request( $request );
			if ( is_wp_error( $payload ) ) {
				return $payload;
			}

			if ( '' === $idempotency_key ) {
				$idempotency_key = 'toolbox_ai_image_generation_' . wp_generate_uuid4();
			}

			$response = $this->request( 'POST', '/v1/runtime/execute', $payload, $idempotency_key, $trace_id );

			return $this->project_runtime_execute_failure( $response );
		}

		/**
		 * Executes one bounded Toolbox article audio generation runtime request.
		 *
		 * This method signs and dispatches the Cloud runtime request only.
		 * Toolbox keeps review UX and Core-governed adoption planning; the addon
		 * must not import audio, write playback metadata, or own regeneration
		 * jobs.
		 *
		 * @param array<string,mixed> $request Toolbox audio generation request.
		 * @param string              $trace_id Optional trace id.
		 * @param string              $idempotency_key Optional idempotency key.
		 * @return array<string,mixed>|WP_Error
		 */
		public function execute_toolbox_audio_generation_runtime( array $request, string $trace_id = '', string $idempotency_key = '' ) {
			$payload = $this->normalize_toolbox_audio_generation_request( $request );
			if ( is_wp_error( $payload ) ) {
				return $payload;
			}

			if ( '' === $idempotency_key ) {
				$idempotency_key = 'toolbox_audio_generation_' . wp_generate_uuid4();
			}

			return $this->request( 'POST', '/v1/runtime/execute', $payload, $idempotency_key, $trace_id );
		}

		/**
		 * Executes one bounded Toolbox Site Ops Cloud analysis request.
		 *
		 * The addon only signs and dispatches the Cloud runtime/detail request.
		 * Toolbox keeps the local Site Check product surface, and Core remains
		 * the owner for any later proposal or WordPress write.
		 *
		 * @param array<string,mixed> $request Toolbox site_ops_cloud_analysis_request.v1 artifact.
		 * @param string              $trace_id Optional trace id.
		 * @param string              $idempotency_key Optional idempotency key.
		 * @return array<string,mixed>|WP_Error
		 */
		public function execute_toolbox_site_ops_cloud_analysis_runtime( array $request, string $trace_id = '', string $idempotency_key = '' ) {
			$payload = $this->normalize_toolbox_site_ops_cloud_analysis_request( $request );
			if ( is_wp_error( $payload ) ) {
				return $payload;
			}

			if ( '' === $idempotency_key ) {
				$idempotency_key = 'toolbox_site_ops_cloud_analysis_' . wp_generate_uuid4();
			}

			return $this->request( 'POST', '/v1/runtime/execute', $payload, $idempotency_key, $trace_id );
		}

		/**
		 * Executes one metadata-only media governance audit.
		 *
		 * @param array<string,mixed> $request Exact media governance audit request.
		 * @param string              $trace_id Optional trace id.
		 * @param string              $idempotency_key Optional idempotency key.
		 * @return array<string,mixed>|WP_Error
		 */
		public function execute_toolbox_media_governance_audit_runtime( array $request, string $trace_id = '', string $idempotency_key = '' ) {
			$payload = $this->normalize_toolbox_media_governance_audit_request( $request );
			if ( is_wp_error( $payload ) ) {
				return $payload;
			}
			if ( '' === $idempotency_key ) {
				$idempotency_key = 'toolbox_media_governance_audit_' . wp_generate_uuid4();
			}

			return $this->request( 'POST', '/v1/runtime/execute', $payload, $idempotency_key, $trace_id );
		}

		/**
		 * Executes one bounded Toolbox managed web search runtime request.
		 *
		 * The addon only signs and dispatches the Cloud request. Toolbox keeps
		 * the operator-facing result UX and evidence normalization.
		 *
		 * @param array<string,mixed> $request Toolbox web_search.v1 request.
		 * @param string              $trace_id Optional trace id.
		 * @param string              $idempotency_key Optional idempotency key.
		 * @return array<string,mixed>|WP_Error
		 */
		public function execute_toolbox_web_search_runtime( array $request, string $trace_id = '', string $idempotency_key = '' ) {
			$payload = $this->normalize_toolbox_web_search_request( $request );
			if ( is_wp_error( $payload ) ) {
				return $payload;
			}

			if ( '' === $idempotency_key ) {
				$idempotency_key = 'toolbox_web_search_' . wp_generate_uuid4();
			}

			return $this->request( 'POST', '/v1/runtime/execute', $payload, $idempotency_key, $trace_id );
		}

		/**
		 * Executes one bounded Toolbox image-source candidate runtime request.
		 *
		 * The addon only signs and dispatches the Cloud request. Toolbox keeps
		 * image-source UX, candidate normalization, attribution handling, and
		 * any Core-governed adoption path.
		 *
		 * @param array<string,mixed> $request Toolbox image_source_cloud_request.v1 request.
		 * @param string              $trace_id Optional trace id.
		 * @param string              $idempotency_key Optional idempotency key.
		 * @return array<string,mixed>|WP_Error
		 */
		public function execute_toolbox_image_source_runtime( array $request, string $trace_id = '', string $idempotency_key = '' ) {
			$payload = $this->normalize_toolbox_image_source_request( $request );
			if ( is_wp_error( $payload ) ) {
				return $payload;
			}

			if ( '' === $idempotency_key ) {
				$idempotency_key = 'toolbox_image_source_' . wp_generate_uuid4();
			}

			return $this->request( 'POST', '/v1/runtime/execute', $payload, $idempotency_key, $trace_id );
		}

		/**
		 * Requests Cloud-owned image context evidence for weak media metadata.
		 *
		 * The addon only signs and transports the bounded request. Image
		 * recognition execution, provider routing, and model ownership remain in
		 * Cloud. Returned evidence is suggestion-only and never authorizes local
		 * WordPress media writes.
		 *
		 * @param array<string,mixed> $image_context_evidence_request Toolbox image_context_evidence_request.v1 artifact.
		 * @param string              $trace_id Optional trace id.
		 * @param string              $idempotency_key Optional idempotency key.
		 * @return array<string,mixed>|WP_Error
		 */
		public function request_image_context_evidence( array $image_context_evidence_request, string $trace_id = '', string $idempotency_key = '' ) {
			$request = $this->normalize_image_context_evidence_request( $image_context_evidence_request );
			if ( is_wp_error( $request ) ) {
				return $request;
			}
			if ( '' === $idempotency_key ) {
				$idempotency_key = 'image_context_evidence_' . wp_generate_uuid4();
			}
			$uses_artifacts = false;
			foreach ( (array) ( $request['items'] ?? array() ) as $item ) {
				if ( is_array( $item ) && '' !== (string) ( $item['source_artifact_id'] ?? '' ) ) {
					$uses_artifacts = true;
					break;
				}
			}

			$runtime_payload = array(
				'ability_name'        => 'npcink-cloud/image-context-evidence',
				'contract_version'    => 'image_context_evidence_request.v1',
				'profile_id'          => 'vision.ai',
				'execution_kind'      => 'image_context_evidence',
				'execution_pattern'   => 'inline',
				'input'               => array(
					'image_context_evidence_request' => $request,
				),
				'data_classification' => $uses_artifacts ? 'internal' : 'public_site_media_metadata',
				'storage_mode'        => 'result_only',
				'retention_ttl'       => 86400,
				'timeout_seconds'     => 30,
				'retry_max'           => 0,
				'policy'              => array(
					'allow_fallback' => false,
				),
			);

			$response = $this->request( 'POST', '/v1/runtime/execute', $runtime_payload, $idempotency_key, $trace_id );
			if ( is_wp_error( $response ) ) {
				return $response;
			}
			$run_id = sanitize_text_field( (string) ( $response['data']['run_id'] ?? ( $response['run_id'] ?? '' ) ) );
			$response_status = sanitize_key( (string) ( $response['data']['status'] ?? ( $response['status'] ?? '' ) ) );
			if ( '' !== $run_id && in_array( $response_status, array( 'submitted', 'queued', 'running', 'processing', 'pending' ), true ) ) {
				return array(
					'contract_version' => 'image_context_evidence.v1',
					'artifact_type' => 'image_context_evidence',
					'run_id' => $run_id,
					'status' => $response_status,
					'items' => array(),
					'write_posture' => 'suggestion_only',
					'direct_wordpress_write' => false,
				);
			}

			return $this->normalize_image_context_evidence_response( is_array( $response ) ? $response : array(), $request );
		}

		/**
		 * Uploads one bounded source image for a media job.
		 *
		 * @param array<string,mixed> $file Exact contents, filename, and mime_type fields.
		 * @param string              $trace_id Optional trace id.
		 * @param string              $idempotency_key Optional idempotency key.
		 * @return array<string,mixed>|WP_Error
		 */
		public function upload_media_artifact( array $file, string $trace_id = '', string $idempotency_key = '' ) {
			$allowed_file_fields = array( 'contents', 'filename', 'mime_type' );
			if (
				array() !== array_diff( $allowed_file_fields, array_keys( $file ) )
				|| array() !== array_diff( array_keys( $file ), $allowed_file_fields )
			) {
				return new WP_Error(
					'cloud_media_upload_file_invalid',
					__( 'Media uploads require exact contents, filename, and mime_type fields.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}

			$contents = $file['contents'];
			if ( ! is_string( $contents ) || '' === $contents ) {
				return new WP_Error(
					'cloud_media_upload_contents_invalid',
					__( 'Media upload contents must be a nonempty byte string.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}
			if ( strlen( $contents ) > self::MAX_DOWNLOAD_BYTES ) {
				return new WP_Error(
					'cloud_media_upload_too_large',
					__( 'Media upload contents exceed the 25 MiB connector limit.', 'npcink-cloud-addon' ),
					array( 'status' => 413 )
				);
			}

			$filename = is_string( $file['filename'] ) ? sanitize_file_name( $file['filename'] ) : '';
			if ( '' === $filename || strlen( $filename ) > 160 ) {
				return new WP_Error(
					'cloud_media_upload_filename_invalid',
					__( 'Media upload filename must be a nonempty safe filename of at most 160 characters.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}

			$mime_type = is_string( $file['mime_type'] ) ? strtolower( trim( $file['mime_type'] ) ) : '';
			if ( ! isset( self::MEDIA_UPLOAD_FORMATS[ $mime_type ] ) ) {
				return new WP_Error(
					'cloud_media_upload_mime_type_invalid',
					__( 'Media uploads allow only AVIF, JPEG, PNG, or WebP images.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}

			$multipart = $this->build_media_upload_multipart_body(
				array(
					'request_contract_version' => 'media_upload_request.v1',
					'media_kind'              => 'image',
					'ttl_minutes'             => 30,
				),
				$contents,
				$filename,
				$mime_type
			);
			if ( is_wp_error( $multipart ) ) {
				return $multipart;
			}
			if ( '' === $idempotency_key ) {
				$idempotency_key = 'media_upload_' . wp_generate_uuid4();
			}

			$response = $this->request(
				'POST',
				'/v1/runtime/media/uploads',
				null,
				$idempotency_key,
				$trace_id,
				(string) $multipart['body'],
				(string) $multipart['content_type']
			);
			if ( is_wp_error( $response ) ) {
				return $response;
			}

			return $this->normalize_media_upload_response( $response, $mime_type, $contents );
		}

		/**
		 * Creates one artifact-referenced media job.
		 *
		 * @param array<string,mixed> $payload Exact media_job_request.v1 payload.
		 * @param string              $trace_id Optional trace id.
		 * @param string              $idempotency_key Optional idempotency key.
		 * @return array<string,mixed>|WP_Error
		 */
		public function create_media_job( array $payload, string $trace_id = '', string $idempotency_key = '' ) {
			$required_keys = array( 'request_contract_version', 'operation', 'source_artifact_id', 'params', 'result_ttl_minutes' );
			$allowed_keys  = array_merge( $required_keys, array( 'watermark_artifact_id', 'batch_context', 'governance' ) );
			$has_governance = array_key_exists( 'governance', $payload );
			$has_batch_context = array_key_exists( 'batch_context', $payload );
			if (
				array() !== array_diff( $required_keys, array_keys( $payload ) )
				|| array() !== array_diff( array_keys( $payload ), $allowed_keys )
				|| 'media_job_request.v1' !== (string) ( $payload['request_contract_version'] ?? '' )
				|| 'image.transform.v1' !== (string) ( $payload['operation'] ?? '' )
				|| 1 !== preg_match( self::MEDIA_ARTIFACT_ID_PATTERN, (string) ( $payload['source_artifact_id'] ?? '' ) )
				|| ! is_array( $payload['params'] ?? null )
				|| 30 !== ( $payload['result_ttl_minutes'] ?? null )
				|| ( isset( $payload['watermark_artifact_id'] ) && 1 !== preg_match( self::MEDIA_ARTIFACT_ID_PATTERN, (string) $payload['watermark_artifact_id'] ) )
				|| $has_governance !== $has_batch_context
			) {
				return new WP_Error(
					'cloud_media_job_contract_invalid',
					__( 'Media jobs require the exact artifact-referenced media_job_request.v1 contract.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}
			if ( $has_governance ) {
				$params = $payload['params'];
				$batch = is_array( $payload['batch_context'] ) ? $payload['batch_context'] : array();
				$governance = is_array( $payload['governance'] ) ? $payload['governance'] : array();
				$batch_keys = array( 'batch_id', 'item_index', 'item_count', 'chunk_size' );
				$governance_keys = array( 'contract_version', 'candidate_id', 'snapshot_id', 'source_sha256', 'evidence_revision', 'minimum_savings_basis_points', 'require_dimensions_unchanged', 'skip_if_not_beneficial', 'retain_originals' );
				if (
					count( $batch_keys ) !== count( $batch )
					|| array() !== array_diff( $batch_keys, array_keys( $batch ) )
					|| array() !== array_diff( array_keys( $batch ), $batch_keys )
					|| count( $governance_keys ) !== count( $governance )
					|| array() !== array_diff( $governance_keys, array_keys( $governance ) )
					|| array() !== array_diff( array_keys( $governance ), $governance_keys )
					|| 'webp' !== ( $params['target_format'] ?? null )
					|| 'preserve' !== ( $params['resize_mode'] ?? null )
					|| isset( $params['crop'], $params['watermark'], $payload['watermark_artifact_id'] )
					|| 'media_governance_canary.v1' !== ( $governance['contract_version'] ?? null )
					|| 1500 !== ( $governance['minimum_savings_basis_points'] ?? null )
					|| true !== ( $governance['require_dimensions_unchanged'] ?? null )
					|| true !== ( $governance['skip_if_not_beneficial'] ?? null )
					|| true !== ( $governance['retain_originals'] ?? null )
					|| (int) ( $batch['item_count'] ?? 0 ) < 1
					|| (int) ( $batch['item_count'] ?? 0 ) > 10
				) {
					return new WP_Error(
						'cloud_media_governance_canary_contract_invalid',
						__( 'Media governance canary jobs require the exact bounded preserve-WebP contract.', 'npcink-cloud-addon' ),
						array( 'status' => 400 )
					);
				}
			}
			if ( '' === $idempotency_key ) {
				$idempotency_key = 'media_job_' . wp_generate_uuid4();
			}

			return $this->request( 'POST', '/v1/runtime/media/jobs', $payload, $idempotency_key, $trace_id );
		}

		/**
		 * Uploads one bounded WordPress AI alt-text source image.
		 *
		 * This internal transport seam accepts bytes only from the authorized local
		 * attachment handoff. It is not a generic caller-supplied upload API.
		 *
		 * @param array<string,mixed> $file Exact contents, filename, and mime_type fields.
		 * @param string              $trace_id Optional trace id.
		 * @param string              $idempotency_key Optional idempotency key.
		 * @return array<string,mixed>|WP_Error
		 * @internal Authorized local attachment handoff only.
		 */
		public function upload_wordpress_ai_alt_text_source( array $file, string $trace_id = '', string $idempotency_key = '' ) {
			if ( ! class_exists( 'Npcink_Cloud_Addon_Settings' ) || ! Npcink_Cloud_Addon_Settings::is_verified() ) {
				return new WP_Error(
					'cloud_runtime_unverified',
					__( 'Verify Npcink Cloud settings before uploading a WordPress AI alt-text source.', 'npcink-cloud-addon' ),
					array( 'status' => 403 )
				);
			}

			$allowed_file_fields = array( 'contents', 'filename', 'mime_type' );
			if (
				array() !== array_diff( $allowed_file_fields, array_keys( $file ) )
				|| array() !== array_diff( array_keys( $file ), $allowed_file_fields )
			) {
				return new WP_Error(
					'cloud_wp_ai_alt_text_upload_file_invalid',
					__( 'WordPress AI alt-text uploads require exact contents, filename, and mime_type fields.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}

			$contents = $file['contents'];
			if ( ! is_string( $contents ) || '' === $contents ) {
				return new WP_Error(
					'cloud_wp_ai_alt_text_upload_contents_invalid',
					__( 'WordPress AI alt-text upload contents must be a nonempty byte string.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}
			if ( strlen( $contents ) > self::WP_AI_ALT_TEXT_MAX_UPLOAD_BYTES ) {
				return new WP_Error(
					'cloud_wp_ai_alt_text_upload_too_large',
					__( 'WordPress AI alt-text upload contents exceed the 8 MiB limit.', 'npcink-cloud-addon' ),
					array( 'status' => 413 )
				);
			}

			if ( ! is_string( $file['filename'] ) ) {
				return new WP_Error(
					'cloud_wp_ai_alt_text_upload_filename_invalid',
					__( 'WordPress AI alt-text upload filename must be a nonempty safe filename.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}
			$filename = sanitize_file_name( $file['filename'] );
			if ( '' === $filename || strlen( $filename ) > 160 ) {
				return new WP_Error(
					'cloud_wp_ai_alt_text_upload_filename_invalid',
					__( 'WordPress AI alt-text upload filename must be a nonempty safe filename of at most 160 characters.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}

			$mime_type = $file['mime_type'];
			if ( ! is_string( $mime_type ) || ! isset( self::WP_AI_ALT_TEXT_UPLOAD_FORMATS[ $mime_type ] ) ) {
				return new WP_Error(
					'cloud_wp_ai_alt_text_upload_mime_type_invalid',
					__( 'WordPress AI alt-text uploads allow only JPEG, PNG, or WebP images.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}

			$multipart = $this->build_media_upload_multipart_body(
				array(
					'request_contract_version' => 'media_upload_request.v1',
					'media_kind'              => 'image',
					'ttl_minutes'             => 30,
				),
				$contents,
				$filename,
				$mime_type
			);
			if ( is_wp_error( $multipart ) ) {
				return $multipart;
			}
			if ( '' === $idempotency_key ) {
				$idempotency_key = 'wp_ai_alt_text_upload_' . wp_generate_uuid4();
			}

			$response = $this->request(
				'POST',
				'/v1/runtime/media/uploads',
				null,
				$idempotency_key,
				$trace_id,
				(string) $multipart['body'],
				(string) $multipart['content_type']
			);
			if ( is_wp_error( $response ) ) {
				return $response;
			}

			return $this->normalize_wordpress_ai_alt_text_upload_response(
				is_array( $response ) ? $response : array(),
				$mime_type,
				$contents
			);
		}

		/**
		 * Reads one runtime run.
		 *
		 * @param string $run_id Cloud run id.
		 * @param string $trace_id Optional trace id.
		 * @return array<string,mixed>|WP_Error
		 */
		public function get_run( string $run_id, string $trace_id = '' ) {
			$run_id = $this->normalize_identifier( $run_id );
			if ( '' === $run_id ) {
				return new WP_Error(
					'cloud_runtime_run_missing',
					__( 'Cloud run_id is required.', 'npcink-cloud-addon' )
				);
			}

			return $this->request( 'GET', '/v1/runs/' . rawurlencode( $run_id ), null, '', $trace_id );
		}

		/**
		 * Reads one runtime run result.
		 *
		 * @param string $run_id Cloud run id.
		 * @param string $trace_id Optional trace id.
		 * @return array<string,mixed>|WP_Error
		 */
		public function get_run_result( string $run_id, string $trace_id = '' ) {
			$run_id = $this->normalize_identifier( $run_id );
			if ( '' === $run_id ) {
				return new WP_Error(
					'cloud_runtime_run_missing',
					__( 'Cloud run_id is required.', 'npcink-cloud-addon' )
				);
			}

			return $this->request( 'GET', '/v1/runs/' . rawurlencode( $run_id ) . '/result', null, '', $trace_id );
		}

		/**
		 * Reads recent Nightly Inspection run cards for the current site.
		 *
		 * @param int    $limit Maximum run cards to read.
		 * @param string $trace_id Optional trace id.
		 * @return array<string,mixed>|WP_Error
		 */
		public function get_recent_nightly_inspection_runs( int $limit = 10, string $trace_id = '' ) {
			$limit = max( 1, min( 50, absint( $limit ) ) );

			return $this->request( 'GET', '/v1/runs/nightly-inspection/recent?limit=' . rawurlencode( (string) $limit ), null, '', $trace_id );
		}

		/**
		 * Queues a Cloud-owned retry for one terminal Nightly Inspection run.
		 *
		 * @param string              $run_id Cloud source run id.
		 * @param array<string,mixed> $input Runtime input payload for the retry.
		 * @param string              $trace_id Optional trace id.
		 * @param string              $idempotency_key Required idempotency key.
		 * @return array<string,mixed>|WP_Error
		 */
		public function retry_run( string $run_id, array $input, string $trace_id = '', string $idempotency_key = '' ) {
			$run_id = $this->normalize_identifier( $run_id );
			if ( '' === $run_id ) {
				return new WP_Error(
					'cloud_runtime_run_missing',
					__( 'Cloud run_id is required.', 'npcink-cloud-addon' )
				);
			}
			if ( '' === $idempotency_key ) {
				$idempotency_key = 'runtime_retry_' . wp_generate_uuid4();
			}

			return $this->request(
				'POST',
				'/v1/runs/' . rawurlencode( $run_id ) . '/retry',
				array(
					'input' => $input,
				),
				$idempotency_key,
				$trace_id
			);
		}

		/**
		 * Pulls one short-TTL media artifact through the nonce-protected signed route.
		 *
		 * @param string $artifact_id Cloud artifact id.
		 * @param string $trace_id Optional trace id.
		 * @return array<string,mixed>|WP_Error
		 */
		public function pull_media_artifact( string $artifact_id, string $trace_id = '' ) {
			$artifact_id = sanitize_text_field( trim( $artifact_id ) );
			if ( 1 !== preg_match( self::MEDIA_ARTIFACT_ID_PATTERN, $artifact_id ) ) {
				return new WP_Error(
					'cloud_media_artifact_id_invalid',
					__( 'Cloud media artifact_id must use the canonical art_<32 lowercase hex> shape.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}

			return $this->request_raw(
				'GET',
				'/v1/runtime/media/artifacts/' . rawurlencode( $artifact_id ) . '/download',
				'',
				$trace_id,
				'image/*'
			);
		}

		/**
		 * Acknowledges one independently verified media artifact transfer.
		 *
		 * @param string              $artifact_id Canonical Cloud artifact id.
		 * @param array<string,mixed> $payload Exact media_artifact_delivery_ack.v1 body.
		 * @param string              $trace_id Optional trace id.
		 * @param string              $idempotency_key Optional independent ACK idempotency key.
		 * @return array<string,mixed>|WP_Error
		 */
		public function acknowledge_media_artifact_delivery( string $artifact_id, array $payload, string $trace_id = '', string $idempotency_key = '' ) {
			$artifact_id = sanitize_text_field( trim( $artifact_id ) );
			$exact_keys  = array( 'contract_version', 'delivery_id', 'received_byte_size', 'received_checksum' );
			if (
				1 !== preg_match( self::MEDIA_ARTIFACT_ID_PATTERN, $artifact_id )
				|| array() !== array_diff( $exact_keys, array_keys( $payload ) )
				|| array() !== array_diff( array_keys( $payload ), $exact_keys )
				|| 'media_artifact_delivery_ack.v1' !== (string) ( $payload['contract_version'] ?? '' )
				|| 1 !== preg_match( self::MEDIA_DELIVERY_ID_PATTERN, (string) ( $payload['delivery_id'] ?? '' ) )
				|| ! is_int( $payload['received_byte_size'] ?? null )
				|| (int) $payload['received_byte_size'] <= 0
				|| 1 !== preg_match( '/^sha256:[0-9a-f]{64}$/', (string) ( $payload['received_checksum'] ?? '' ) )
			) {
				return new WP_Error(
					'cloud_media_delivery_ack_contract_invalid',
					__( 'Media delivery acknowledgement requires the exact verified-transfer contract.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}
			if ( '' === $idempotency_key ) {
				$idempotency_key = 'media_delivery_ack_' . wp_generate_uuid4();
			}

			$response = $this->request(
				'POST',
				'/v1/runtime/media/artifacts/' . rawurlencode( $artifact_id ) . '/delivery-ack',
				$payload,
				$idempotency_key,
				$trace_id
			);
			if ( is_wp_error( $response ) ) {
				return $response;
			}

			return $this->normalize_media_delivery_ack_response( $response, $artifact_id, $payload );
		}

		/**
		 * Reads the current site entitlement projection.
		 *
		 * @param string $trace_id Optional trace id.
		 * @return array<string,mixed>|WP_Error
		 */
		public function get_current_entitlement( string $trace_id = '' ) {
			$site_id = $this->normalize_identifier( (string) ( $this->config['site_id'] ?? '' ) );
			if ( '' === $site_id ) {
				return new WP_Error(
					'cloud_runtime_site_missing',
					__( 'Cloud site_id is required.', 'npcink-cloud-addon' )
				);
			}

			$path = '/v1/entitlements/current?object_type=site&object_id=' . rawurlencode( $site_id );

			return $this->request( 'GET', $path, null, '', $trace_id );
		}

		/**
		 * Sends a batch of plugin observability events.
		 *
		 * @param array<int,array<string,mixed>> $events Event batch.
		 * @param string                         $trace_id Optional trace id.
		 * @param string                         $idempotency_key Optional idempotency key.
		 * @return array<string,mixed>|WP_Error
		 */
		public function send_observability_events( array $events, string $trace_id = '', string $idempotency_key = '' ) {
			if ( empty( $events ) ) {
				return new WP_Error(
					'cloud_observability_events_empty',
					__( 'No observability events are ready to upload.', 'npcink-cloud-addon' )
				);
			}

			if ( '' === $idempotency_key ) {
				$idempotency_key = 'obs_' . wp_generate_uuid4();
			}

			return $this->request(
				'POST',
				'/v1/observability/plugin-events',
				array(
					'contract_version' => 'magick-plugin-observability-v1',
					'source'           => 'npcink-cloud-addon',
					'events'           => array_values( $events ),
				),
				$idempotency_key,
				$trace_id
			);
		}

		/**
		 * Sends a metadata-only customer journey batch.
		 *
		 * @param array<int,array<string,mixed>> $events Closed journey event batch.
		 * @param string                         $trace_id Optional trace id.
		 * @param string                         $idempotency_key Optional idempotency key.
		 * @return array<string,mixed>|WP_Error
		 */
		public function send_customer_journey_events( array $events, string $trace_id = '', string $idempotency_key = '' ) {
			if ( empty( $events ) || count( $events ) > 100 ) {
				return new WP_Error(
					'cloud_customer_journey_events_invalid',
					__( 'Customer journey upload requires between 1 and 100 metadata-only events.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}
			if ( '' === $idempotency_key ) {
				$idempotency_key = 'journey_' . wp_generate_uuid4();
			}

			return $this->request(
				'POST',
				'/v1/customer-journey/events',
				array(
					'contract_version' => 'customer_journey_event.v1',
					'events'           => array_values( $events ),
				),
				$idempotency_key,
				$trace_id
			);
		}

		/**
		 * Sends one local Agent handoff feedback event for Cloud eval rollups.
		 *
		 * @param array<string,mixed> $payload Agent feedback payload.
		 * @param string              $trace_id Optional trace id.
		 * @param string              $idempotency_key Optional idempotency key.
		 * @return array<string,mixed>|WP_Error
		 */
		public function send_agent_feedback_event( array $payload, string $trace_id = '', string $idempotency_key = '' ) {
			$payload = $this->normalize_agent_feedback_payload( $payload );
			if ( is_wp_error( $payload ) ) {
				return $payload;
			}

			if ( empty( $payload ) ) {
				return new WP_Error(
					'cloud_agent_feedback_payload_invalid',
					__( 'Agent feedback requires a valid cloud_agent_feedback.v1 payload.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}

			if ( '' === $idempotency_key ) {
				$idempotency_key = 'agent_feedback_' . wp_generate_uuid4();
			}

			return $this->request(
				'POST',
				'/v1/agent-feedback/events',
				$payload,
				$idempotency_key,
				$trace_id
			);
		}

		/**
		 * Reads the Cloud Agent feedback eval summary.
		 *
		 * @param int    $window_hours Summary window in hours.
		 * @param string $trace_id Optional trace id.
		 * @return array<string,mixed>|WP_Error
		 */
		public function get_agent_feedback_summary( int $window_hours = 24, string $trace_id = '' ) {
			$window_hours = min( 168, max( 1, absint( $window_hours ) ) );

			return $this->request( 'GET', '/v1/agent-feedback/summary?window_hours=' . rawurlencode( (string) $window_hours ), null, '', $trace_id );
		}

		/**
		 * Reads the Cloud plugin observability summary.
		 *
		 * @param int    $window_hours Summary window in hours.
		 * @param string $trace_id Optional trace id.
		 * @return array<string,mixed>|WP_Error
		 */
		public function get_observability_summary( int $window_hours = 24, string $trace_id = '' ) {
			$window_hours = min( 168, max( 1, absint( $window_hours ) ) );

			return $this->request( 'GET', '/v1/observability/plugin-summary?window_hours=' . rawurlencode( (string) $window_hours ), null, '', $trace_id );
		}

		/**
		 * Reads the current site's customer journey summary.
		 *
		 * @param int    $window_hours Summary window in hours.
		 * @param string $cohort_id Optional cohort id.
		 * @param string $trace_id Optional trace id.
		 * @return array<string,mixed>|WP_Error
		 */
		public function get_customer_journey_summary( int $window_hours = 24, string $cohort_id = '', string $trace_id = '' ) {
			$window_hours = min( 168, max( 1, absint( $window_hours ) ) );
			$cohort_id = trim( $cohort_id );
			if ( strlen( $cohort_id ) > 64 || ( '' !== $cohort_id && 1 !== preg_match( '/^[A-Za-z0-9._:-]+$/', $cohort_id ) ) ) {
				return new WP_Error(
					'cloud_customer_journey_cohort_invalid',
					__( 'Customer journey cohort id is invalid.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}

			$path = '/v1/customer-journey/summary?window_hours=' . rawurlencode( (string) $window_hours );
			if ( '' !== $cohort_id ) {
				$path .= '&cohort_id=' . rawurlencode( $cohort_id );
			}

			return $this->request( 'GET', $path, null, '', $trace_id );
		}

		/**
		 * Executes one signed Cloud request.
		 *
		 * @param string              $method HTTP method.
		 * @param string              $path Relative path with optional query.
		 * @param array<string,mixed>|null $payload Optional JSON payload.
		 * @param string              $idempotency_key Optional idempotency key.
		 * @param string              $trace_id Optional trace id.
		 * @return array<string,mixed>|WP_Error
		 */
		private function request( string $method, string $path, ?array $payload = null, string $idempotency_key = '', string $trace_id = '', ?string $raw_body = null, string $content_type = 'application/json' ) {
			if ( ! $this->is_configured() ) {
				return new WP_Error(
					'cloud_runtime_unconfigured',
					__( 'Npcink Cloud is not configured.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}

			$method = strtoupper( trim( $method ) );
			if ( ! Npcink_Cloud_Runtime_Endpoint_Policy::allows( $method, $path ) ) {
				return new WP_Error(
					'cloud_runtime_endpoint_not_allowed',
					__( 'This Cloud endpoint is not allowed by the Cloud Addon runtime contract.', 'npcink-cloud-addon' ),
					array( 'status' => 403 )
				);
			}

			$trace_id = $this->normalize_request_identifier( $trace_id, 'trace' );
			if ( is_wp_error( $trace_id ) ) {
				return $trace_id;
			}
			$idempotency_key = $this->normalize_request_identifier( $idempotency_key, 'idempotency' );
			if ( is_wp_error( $idempotency_key ) ) {
				return $idempotency_key;
			}
			if ( '' === $trace_id ) {
				$trace_id = 'trace_cloud_' . wp_generate_uuid4();
			}
			$body = '';

			if ( null !== $raw_body ) {
				$body = $raw_body;
			} elseif ( is_array( $payload ) ) {
				$encoded = wp_json_encode( $payload );
				if ( ! is_string( $encoded ) || '' === $encoded ) {
					return new WP_Error(
						'cloud_runtime_encode_failed',
						__( 'Cloud runtime request payload could not be encoded.', 'npcink-cloud-addon' )
					);
				}
				$body = $encoded;
			}

			$timeout = max( 5, absint( $this->config['timeout'] ?? 8 ) );
			if ( 'POST' === $method && '/v1/runtime/execute' === $path && is_array( $payload ) ) {
				$requested_timeout = absint( $payload['timeout_seconds'] ?? 0 );
				if ( $requested_timeout > 0 ) {
					$timeout_cap = 'npcink-cloud/generate-image' === (string) ( $payload['ability_name'] ?? '' )
						? self::WP_AI_IMAGE_GENERATION_MAX_TIMEOUT_SECONDS
						: self::WP_AI_CONNECTOR_MAX_TIMEOUT_SECONDS;
					$timeout = max( $timeout, min( $timeout_cap, $requested_timeout ) );
				}
			}

			$args = array(
				'method' => $method,
				'timeout' => $timeout,
				'limit_response_size' => self::MAX_JSON_RESPONSE_BYTES,
				'headers' => $this->build_signed_headers( $method, $path, $body, $idempotency_key, $trace_id, $content_type ),
			);

			if ( '' !== $body ) {
				$args['body'] = $body;
			}

			$response = Npcink_Cloud_Outbound_Policy::request_json(
				$this->build_request_url( $path ),
				$args,
				self::MAX_JSON_RESPONSE_BYTES
			);
			if ( is_wp_error( $response ) ) {
				if ( 'cloud_outbound_response_too_large' === $response->get_error_code() ) {
					return new WP_Error(
						'cloud_runtime_response_too_large',
						__( 'Cloud runtime response exceeds the local size limit.', 'npcink-cloud-addon' ),
						array( 'status' => 413 )
					);
				}
				if ( 'cloud_outbound_response_type_invalid' === $response->get_error_code() ) {
					return new WP_Error(
						'cloud_runtime_response_invalid',
						__( 'Cloud runtime response was not valid JSON.', 'npcink-cloud-addon' ),
						array( 'status' => 502 )
					);
				}
				return new WP_Error(
					'cloud_runtime_request_failed',
					$this->format_transport_error_message( $response->get_error_message() ),
					array( 'status' => 502 )
				);
			}

			return $this->decode_response( $response );
		}

		/**
		 * Executes one signed Cloud request and returns raw response bytes.
		 *
		 * @param string $method HTTP method.
		 * @param string $path Relative path with optional query.
		 * @param string $idempotency_key Optional idempotency key.
		 * @param string $trace_id Optional trace id.
		 * @param string $accept Accept header.
		 * @return array<string,mixed>|WP_Error
		 */
		private function request_raw( string $method, string $path, string $idempotency_key = '', string $trace_id = '', string $accept = '*/*' ) {
			if ( ! $this->is_configured() ) {
				return new WP_Error(
					'cloud_runtime_unconfigured',
					__( 'Npcink Cloud is not configured.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}

			$method = strtoupper( trim( $method ) );
			if ( ! Npcink_Cloud_Runtime_Endpoint_Policy::allows( $method, $path ) ) {
				return new WP_Error(
					'cloud_runtime_endpoint_not_allowed',
					__( 'This Cloud endpoint is not allowed by the Cloud Addon runtime contract.', 'npcink-cloud-addon' ),
					array( 'status' => 403 )
				);
			}

			$trace_id = $this->normalize_request_identifier( $trace_id, 'trace' );
			if ( is_wp_error( $trace_id ) ) {
				return $trace_id;
			}
			$idempotency_key = $this->normalize_request_identifier( $idempotency_key, 'idempotency' );
			if ( is_wp_error( $idempotency_key ) ) {
				return $idempotency_key;
			}
			if ( '' === $trace_id ) {
				$trace_id = 'trace_cloud_' . wp_generate_uuid4();
			}
			$headers         = $this->build_signed_headers( $method, $path, '', $idempotency_key, $trace_id, 'application/octet-stream' );
			$headers['Accept'] = sanitize_text_field( $accept );
			unset( $headers['Content-Type'] );

			$response = Npcink_Cloud_Outbound_Policy::request_raw(
				$this->build_request_url( $path ),
				array(
					'method'  => $method,
					'timeout' => max( 5, absint( $this->config['timeout'] ?? 8 ) ),
					'limit_response_size' => self::MAX_DOWNLOAD_BYTES,
					'headers' => $headers,
				),
				self::MAX_DOWNLOAD_BYTES
			);
			if ( is_wp_error( $response ) ) {
				if ( 'cloud_outbound_response_too_large' === $response->get_error_code() ) {
					return new WP_Error(
						'cloud_runtime_artifact_too_large',
						__( 'Cloud artifact download exceeds the local preview size limit.', 'npcink-cloud-addon' ),
						array( 'status' => 413 )
					);
				}
				return new WP_Error(
					'cloud_runtime_request_failed',
					$this->format_transport_error_message( $response->get_error_message() ),
					array( 'status' => 502 )
				);
			}

			return $this->decode_raw_response( $response );
		}

		/**
		 * Requests the public liveness endpoint.
		 *
		 * @return array<string,mixed>
		 */
		private function request_live_probe(): array {
			$base_url = untrailingslashit( (string) ( $this->config['base_url'] ?? '' ) );
			if ( '' === $base_url ) {
				return array(
					'ok' => false,
					'message' => __( 'Cloud Base URL is required.', 'npcink-cloud-addon' ),
				);
			}

			$response = Npcink_Cloud_Outbound_Policy::request_json(
				$base_url . '/health/live',
				array(
					'method'  => 'GET',
					'timeout' => max( 5, absint( $this->config['timeout'] ?? 8 ) ),
					'limit_response_size' => self::MAX_JSON_RESPONSE_BYTES,
					'headers' => array(
						'Accept' => 'application/json',
					),
				),
				self::MAX_JSON_RESPONSE_BYTES
			);

			if ( is_wp_error( $response ) ) {
				return array(
					'ok' => false,
					'message' => $this->format_transport_error_message( $response->get_error_message() ),
				);
			}

			$status = absint( wp_remote_retrieve_response_code( $response ) );
			$raw_body = wp_remote_retrieve_body( $response );
			$body = is_string( $raw_body ) ? $raw_body : '';
			if ( strlen( $body ) > self::MAX_JSON_RESPONSE_BYTES ) {
				return array(
					'ok' => false,
					'message' => __( 'Cloud liveness response exceeds the local size limit.', 'npcink-cloud-addon' ),
				);
			}
			$decoded = json_decode( $body, true );
			$decoded = is_array( $decoded ) ? $decoded : array();
			$message = sanitize_text_field( (string) ( $decoded['message'] ?? '' ) );

			if ( $status < 200 || $status >= 300 ) {
				return array(
					'ok' => false,
					'message' => '' !== $message ? $message : __( 'Cloud liveness check failed.', 'npcink-cloud-addon' ),
				);
			}

			return array(
				'ok' => true,
				'message' => '' !== $message ? $message : __( 'Cloud service is live.', 'npcink-cloud-addon' ),
			);
		}

		/**
		 * Builds a bounded non-secret readiness result for diagnostics.
		 *
		 * @param array<string,mixed> $probe Connectivity probe result.
		 * @return array<string,mixed>
		 */
		private function build_readiness_result( array $probe ): array {
			$status = 'failed';
			$owner_label = 'cloud_addon';
			$blocked_reason = '';
			$next_action = 'retry_test';
			$base_url = untrailingslashit( (string) ( $this->config['base_url'] ?? '' ) );
			$base_url_present = '' !== $base_url;
			$site_id_present = '' !== (string) ( $this->config['site_id'] ?? '' );
			$key_id_present = '' !== (string) ( $this->config['key_id'] ?? '' );
			$secret_present = '' !== (string) ( $this->config['secret'] ?? '' );
			$credential_slots_complete = $base_url_present && $site_id_present && $key_id_present && $secret_present;
			$credential_slot_readiness = 'not_configured';
			if ( $credential_slots_complete ) {
				$credential_slot_readiness = 'ready';
			} elseif ( $base_url_present || $site_id_present || $key_id_present || $secret_present ) {
				$credential_slot_readiness = 'partial';
			}
			$service_liveness_status = $base_url_present ? ( ! empty( $probe['live_ok'] ) ? 'ready' : 'unavailable' ) : 'not_configured';
			$signed_transport_status = 'not_configured';
			if ( 'ready' === $credential_slot_readiness ) {
				if ( 'ready' !== $service_liveness_status ) {
					$signed_transport_status = 'unavailable';
				} elseif ( ! empty( $probe['auth_ok'] ) ) {
					$signed_transport_status = 'ready';
				} else {
					$signed_transport_status = 'failed';
				}
			}
			$connector_diagnostic_category = $this->classify_connector_diagnostic_category(
				$base_url_present,
				$credential_slot_readiness,
				$service_liveness_status,
				$signed_transport_status
			);

			if ( ! $this->is_configured() ) {
				$status = 'not_configured';
				$owner_label = 'operator';
				$blocked_reason = __( 'Cloud settings are incomplete.', 'npcink-cloud-addon' );
				$next_action = 'open_settings';
			} elseif ( ! empty( $probe['ok'] ) ) {
				$status = 'ready';
				$owner_label = 'cloud_addon';
				$blocked_reason = '';
				$next_action = 'continue';
			} elseif ( empty( $probe['live_ok'] ) ) {
				$status = 'unavailable';
				$owner_label = 'cloud';
				$blocked_reason = $this->redact_support_text( (string) ( $probe['live_message'] ?? '' ) );
				$next_action = 'check_cloud_status';
			} else {
				$status = 'failed';
				$owner_label = 'cloud';
				$blocked_reason = $this->redact_support_text( (string) ( $probe['auth_message'] ?? '' ) );
				$next_action = 'retry_test';
			}

			if ( '' === $blocked_reason && 'ready' !== $status ) {
				$blocked_reason = __( 'Connector readiness could not be verified.', 'npcink-cloud-addon' );
			}

			$host = '' !== $base_url ? sanitize_text_field( (string) wp_parse_url( $base_url, PHP_URL_HOST ) ) : '';
			$support_facts = array(
				'contract_version' => 'cloud_addon_readiness_result.v1',
				'connector_slot' => 'npcink_cloud_runtime',
				'connector_diagnostic_category' => $connector_diagnostic_category,
				'credential_slot_readiness' => $credential_slot_readiness,
				'signed_transport_status' => $signed_transport_status,
				'service_liveness_status' => $service_liveness_status,
				'base_url_host' => '' !== $host ? $host : 'not_set',
				'base_url_present' => $base_url_present ? 'yes' : 'no',
				'site_id_present' => $site_id_present ? 'yes' : 'no',
				'key_id_present' => $key_id_present ? 'yes' : 'no',
				'signing_secret_slot_present' => $secret_present ? 'yes' : 'no',
				'signing_credentials_complete' => $credential_slots_complete ? 'yes' : 'no',
				'timeout_seconds' => (string) max( 5, absint( $this->config['timeout'] ?? 8 ) ),
				'live_ok' => ! empty( $probe['live_ok'] ) ? 'yes' : 'no',
				'signed_read_ok' => ! empty( $probe['auth_ok'] ) ? 'yes' : 'no',
				'signed_read_endpoint' => 'GET /v1/entitlements/current',
				'write_posture' => 'read_only',
			);
			$diagnostic_panel_groups = $this->build_diagnostic_panel_groups(
				$probe,
				$support_facts,
				$credential_slot_readiness,
				$service_liveness_status,
				$signed_transport_status
			);

			return array(
				'contract_version' => 'cloud_addon_readiness_result.v1',
				'manual_test_action' => 'probe_connectivity',
				'connector_slot' => 'npcink_cloud_runtime',
				'connector_diagnostic_category' => $connector_diagnostic_category,
				'credential_slot_readiness' => $credential_slot_readiness,
				'signed_transport_status' => $signed_transport_status,
				'service_liveness_status' => $service_liveness_status,
				'status' => $status,
				'bounded_status' => $status,
				'owner_label' => $owner_label,
				'blocked_reason' => sanitize_text_field( $blocked_reason ),
				'next_action' => $next_action,
				'next_safe_action' => $next_action,
				'support_facts' => $support_facts,
				'copyable_support_facts' => $support_facts,
				'diagnostic_panel_groups' => $diagnostic_panel_groups,
				'write_posture' => 'read_only',
				'tested_at' => gmdate( 'c' ),
			);
		}

		/**
		 * Projects one readiness result into bounded operator diagnostic groups.
		 *
		 * @param array<string,mixed> $probe Connectivity probe result.
		 * @param array<string,string> $support_facts Bounded support facts.
		 * @param string               $credential_status Credential-slot status.
		 * @param string               $liveness_status Service liveness status.
		 * @param string               $signed_status Signed transport status.
		 * @return array<int,array<string,mixed>>
		 */
		private function build_diagnostic_panel_groups(
			array $probe,
			array $support_facts,
			string $credential_status,
			string $liveness_status,
			string $signed_status
		): array {
			$configuration_status = 'ready' === $credential_status ? 'ready' : 'not_configured';
			$configuration_reason = 'ready' === $configuration_status ? '' : __( 'Cloud settings are incomplete.', 'npcink-cloud-addon' );
			$liveness_reason = 'ready' === $liveness_status ? '' : $this->redact_support_text( (string) ( $probe['live_message'] ?? '' ) );
			$signed_reason = 'ready' === $signed_status ? '' : $this->redact_support_text( (string) ( $probe['auth_message'] ?? $probe['live_message'] ?? '' ) );

			return array(
				$this->build_diagnostic_panel_group(
					'local_configuration',
					'credential_slot_readiness',
					$configuration_status,
					'operator',
					$configuration_reason,
					array(
						'base_url_host' => $support_facts['base_url_host'],
						'base_url_present' => $support_facts['base_url_present'],
						'site_id_present' => $support_facts['site_id_present'],
						'key_id_present' => $support_facts['key_id_present'],
						'signing_secret_slot_present' => $support_facts['signing_secret_slot_present'],
						'signing_credentials_complete' => $support_facts['signing_credentials_complete'],
					),
					'ready' === $configuration_status ? 'continue' : 'open_settings'
				),
				$this->build_diagnostic_panel_group(
					'cloud_connectivity',
					'service_liveness',
					$liveness_status,
					'cloud',
					$liveness_reason,
					array(
						'base_url_host' => $support_facts['base_url_host'],
						'service_liveness_status' => $support_facts['service_liveness_status'],
						'live_ok' => $support_facts['live_ok'],
					),
					$this->diagnostic_next_safe_action( $liveness_status )
				),
				$this->build_diagnostic_panel_group(
					'signed_transport',
					'signed_entitlement_read',
					$signed_status,
					'cloud_addon',
					$signed_reason,
					array(
						'connector_slot' => $support_facts['connector_slot'],
						'signed_transport_status' => $support_facts['signed_transport_status'],
						'signed_read_ok' => $support_facts['signed_read_ok'],
						'signed_read_endpoint' => $support_facts['signed_read_endpoint'],
					),
					$this->diagnostic_next_safe_action( $signed_status )
				),
				$this->build_diagnostic_panel_group(
					'entitlement_readiness',
					'entitlement_readiness',
					$signed_status,
					'cloud',
					$signed_reason,
					array(
						'signed_read_endpoint' => $support_facts['signed_read_endpoint'],
						'signed_read_ok' => $support_facts['signed_read_ok'],
						'write_posture' => $support_facts['write_posture'],
					),
					$this->diagnostic_next_safe_action( $signed_status )
				),
				$this->build_diagnostic_panel_group(
					'support_facts',
					'bounded_support_facts',
					'ready',
					'cloud_addon',
					'',
					$support_facts,
					'continue'
				),
			);
		}

		/**
		 * Builds one bounded diagnostic group.
		 *
		 * @param string               $group Group identifier.
		 * @param string               $category Diagnostic category.
		 * @param string               $status Bounded status.
		 * @param string               $owner_label Owning system or operator.
		 * @param string               $blocked_reason Bounded blocked reason.
		 * @param array<string,string> $safe_support_facts Non-secret support facts.
		 * @param string               $next_safe_action Next safe operator action.
		 * @return array<string,mixed>
		 */
		private function build_diagnostic_panel_group(
			string $group,
			string $category,
			string $status,
			string $owner_label,
			string $blocked_reason,
			array $safe_support_facts,
			string $next_safe_action
		): array {
			if ( 'ready' !== $status && '' === $blocked_reason ) {
				$blocked_reason = __( 'Connector readiness could not be verified.', 'npcink-cloud-addon' );
			}

			return array(
				'diagnostic_panel_group' => $group,
				'diagnostic_category' => $category,
				'severity' => $this->diagnostic_severity( $status ),
				'owner_label' => $owner_label,
				'bounded_status' => $status,
				'blocked_reason' => sanitize_text_field( $blocked_reason ),
				'safe_support_facts' => $safe_support_facts,
				'next_safe_action' => $next_safe_action,
				'visibility' => 'administrator_only',
				'write_posture' => 'read_only',
			);
		}

		/**
		 * Maps one bounded status to the small admin severity vocabulary.
		 *
		 * @param string $status Bounded status.
		 * @return string
		 */
		private function diagnostic_severity( string $status ): string {
			if ( 'ready' === $status ) {
				return 'ok';
			}

			if ( 'failed' === $status ) {
				return 'error';
			}

			return 'not_configured' === $status ? 'inactive' : 'warning';
		}

		/**
		 * Returns the bounded next action for signed-read-derived groups.
		 *
		 * @param string $status Bounded status.
		 * @return string
		 */
		private function diagnostic_next_safe_action( string $status ): string {
			if ( 'ready' === $status ) {
				return 'continue';
			}

			if ( 'not_configured' === $status ) {
				return 'open_settings';
			}

			return 'unavailable' === $status ? 'check_cloud_status' : 'retry_test';
		}

		/**
		 * Classifies connector readiness into one bounded operator diagnostic bucket.
		 *
		 * @param bool   $base_url_present Whether the Cloud Base URL slot is present.
		 * @param string $credential_slot_readiness Credential-slot readiness.
		 * @param string $service_liveness_status Service liveness status.
		 * @param string $signed_transport_status Signed-read transport status.
		 * @return string
		 */
		private function classify_connector_diagnostic_category(
			bool $base_url_present,
			string $credential_slot_readiness,
			string $service_liveness_status,
			string $signed_transport_status
		): string {
			if ( ! $base_url_present && 'not_configured' === $credential_slot_readiness ) {
				return 'not_configured';
			}

			if ( in_array( $credential_slot_readiness, array( 'partial', 'not_configured' ), true ) ) {
				return 'credential_missing';
			}

			if ( 'unavailable' === $service_liveness_status || 'unavailable' === $signed_transport_status ) {
				return 'cloud_unavailable';
			}

			if ( 'failed' === $signed_transport_status ) {
				return 'signed_transport_failed';
			}

			if ( 'ready' === $service_liveness_status && 'ready' === $signed_transport_status ) {
				return 'ready';
			}

			return 'unknown';
		}

		/**
		 * Redacts sensitive token shapes from readiness support text.
		 *
		 * @param string $message Raw support message.
		 * @return string
		 */
		private function redact_support_text( string $message ): string {
			foreach ( array( 'secret', 'authorization' ) as $sensitive_config_key ) {
				$sensitive_value = $this->config[ $sensitive_config_key ] ?? '';
				if ( is_string( $sensitive_value ) && strlen( $sensitive_value ) >= 8 ) {
					$message = str_replace( $sensitive_value, '[redacted]', $message );
				}
			}

			$message = preg_replace(
				"~(?<![A-Za-z0-9_-])[A-Za-z0-9_-]*(?:authorizations?|api[_-]?keys?|provider[_-]?keys?|tokens?|credentials?|cookies?|passwords?|secrets?|signatures?|nonces?)\\s*[:=]\\s*(?:\"[^\"]*\"|'[^']*'|[A-Za-z][A-Za-z0-9_-]*\\s+[^\\s,;]+|[^\\s,;]+)~i",
				'[redacted]',
				$message
			);
			$message = preg_replace( '/mak1_[A-Za-z0-9_-]+/', '[redacted]', $message );
			$message = preg_replace( '/Bearer\s+[A-Za-z0-9._~+\/=-]+/i', 'Bearer [redacted]', (string) $message );
			$message = preg_replace( '/secret[_-]?[A-Za-z0-9._:-]*/i', '[redacted]', (string) $message );

			$message = sanitize_text_field( (string) $message );
			if ( function_exists( 'mb_strlen' ) && function_exists( 'mb_substr' ) ) {
				return mb_strlen( $message, 'UTF-8' ) > self::MAX_ERROR_MESSAGE_CHARS
					? mb_substr( $message, 0, self::MAX_ERROR_MESSAGE_CHARS, 'UTF-8' ) . '…'
					: $message;
			}

			return strlen( $message ) > self::MAX_ERROR_MESSAGE_CHARS
				? substr( $message, 0, self::MAX_ERROR_MESSAGE_CHARS ) . '...'
				: $message;
		}

		/**
		 * Decodes a WordPress HTTP response into the local client envelope.
		 *
		 * @param array<string,mixed> $response WP HTTP response.
		 * @return array<string,mixed>|WP_Error
		 */
		private function decode_response( array $response ) {
			$status = absint( wp_remote_retrieve_response_code( $response ) );
			$raw_body = wp_remote_retrieve_body( $response );
			$body = is_string( $raw_body ) ? $raw_body : '';
			$content_len = absint( $this->response_header( $response, 'content-length' ) );
			if ( $content_len > self::MAX_JSON_RESPONSE_BYTES || strlen( $body ) > self::MAX_JSON_RESPONSE_BYTES ) {
				return new WP_Error(
					'cloud_runtime_response_too_large',
					__( 'Cloud runtime response exceeds the local size limit.', 'npcink-cloud-addon' ),
					array( 'status' => 413 )
				);
			}

			$decoded = json_decode( $body, true );
			if ( ! is_array( $decoded ) && '' !== trim( $body ) ) {
				$is_likely_truncated = strlen( $body ) >= self::MAX_JSON_RESPONSE_BYTES;
				return new WP_Error(
					$is_likely_truncated ? 'cloud_runtime_response_too_large' : 'cloud_runtime_response_invalid',
					$is_likely_truncated
						? __( 'Cloud runtime response exceeds the local size limit.', 'npcink-cloud-addon' )
						: __( 'Cloud runtime response was not valid JSON.', 'npcink-cloud-addon' ),
					array( 'status' => $is_likely_truncated ? 413 : 502 )
				);
			}
			$decoded = is_array( $decoded ) ? $decoded : array();
			$envelope_status = sanitize_key( (string) ( $decoded['status'] ?? '' ) );

			$successful_envelope_statuses = array( '', 'ok', 'ready', 'submitted', 'queued', 'running', 'completed', 'success' );
			if ( $status < 200 || $status >= 300 || ! in_array( $envelope_status, $successful_envelope_statuses, true ) ) {
				$error_code = $this->normalize_remote_error_code( $decoded['error_code'] ?? $decoded['code'] ?? '' );
				$message = $this->normalize_error_message( $decoded['message'] ?? $decoded['detail'] ?? '' );
				$local_status = $this->normalize_remote_failure_status( $status );
				if ( '' === $message ) {
					$message = __( 'Cloud runtime request failed.', 'npcink-cloud-addon' );
				}

				return new WP_Error(
					$this->map_remote_error_code( $error_code ),
					$message,
					array(
						'status'            => $local_status,
						'cloud_http_status' => $status,
						'cloud_error_code'  => $error_code,
						'cloud_error_data'  => is_array( $decoded['data'] ?? null ) ? $decoded['data'] : array(),
					)
				);
			}

			return $decoded;
		}

		/**
		 * Projects an inline runtime business failure without changing run-status reads.
		 *
		 * @param array<string,mixed>|WP_Error $response Runtime execute response.
		 * @return array<string,mixed>|WP_Error
		 */
		private function project_runtime_execute_failure( $response ) {
			if ( is_wp_error( $response ) || ! is_array( $response ) ) {
				return $response;
			}

			$data = is_array( $response['data'] ?? null ) ? $response['data'] : array();
			$status = sanitize_key( (string) ( $data['status'] ?? '' ) );
			$error_code = $this->normalize_remote_error_code( $data['error_code'] ?? '' );
			if ( ! in_array( $status, array( 'failed', 'error', 'canceled' ), true ) && '' === $error_code ) {
				return $response;
			}

			$message = $this->normalize_error_message( $data['error_message'] ?? $data['message'] ?? '' );
			if ( '' === $message ) {
				$message = __( 'Cloud runtime request failed.', 'npcink-cloud-addon' );
			}

			return new WP_Error(
				$this->map_remote_error_code( $error_code ),
				$message,
				array(
					'status'            => 502,
					'cloud_http_status' => 200,
					'cloud_error_code'  => $error_code,
					'cloud_error_data'  => $data,
				)
			);
		}

		/**
		 * Decodes a raw byte response with bounded size checks.
		 *
		 * @param array<string,mixed> $response WP HTTP response.
		 * @return array<string,mixed>|WP_Error
		 */
		private function decode_raw_response( array $response ) {
			$status       = absint( wp_remote_retrieve_response_code( $response ) );
			$raw_body     = wp_remote_retrieve_body( $response );
			$body         = is_string( $raw_body ) ? $raw_body : '';
			$content_type = $this->response_header( $response, 'content-type' );
			$content_len  = absint( $this->response_header( $response, 'content-length' ) );

			if ( $status < 200 || $status >= 300 ) {
				$decoded = json_decode( $body, true );
				$decoded = is_array( $decoded ) ? $decoded : array();
				$error_code = $this->normalize_remote_error_code( $decoded['error_code'] ?? $decoded['code'] ?? '' );
				$message = $this->normalize_error_message( $decoded['message'] ?? $decoded['detail'] ?? '' );
				if ( '' === $message ) {
					$message = __( 'Cloud runtime artifact download failed.', 'npcink-cloud-addon' );
				}

				return new WP_Error(
					$this->map_remote_error_code( $error_code ),
					$message,
					array(
						'status'           => $this->normalize_remote_failure_status( $status ),
						'cloud_http_status' => $status,
						'cloud_error_code'  => $error_code,
					)
				);
			}

			if ( $content_len > self::MAX_DOWNLOAD_BYTES || strlen( $body ) > self::MAX_DOWNLOAD_BYTES ) {
				return new WP_Error(
					'cloud_runtime_artifact_too_large',
					__( 'Cloud artifact download exceeds the local preview size limit.', 'npcink-cloud-addon' ),
					array( 'status' => 413 )
				);
			}

			return array(
				'status'         => $status,
				'body'           => $body,
				'content_type'   => $content_type,
				'content_length' => $content_len,
				'artifact_id'    => $this->response_header( $response, 'x-npcink-artifact-id' ),
				'artifact_checksum' => $this->response_header( $response, 'x-npcink-artifact-checksum' ),
				'delivery_id'    => $this->response_header( $response, 'x-npcink-delivery-id' ),
				'delivery_ack_deadline' => $this->response_header( $response, 'x-npcink-delivery-ack-deadline' ),
			);
		}

		/**
		 * Normalizes scalar or structured Cloud error detail into readable text.
		 *
		 * @param mixed $value Cloud error message/detail value.
		 * @return string
		 */
		private function normalize_error_message( $value ): string {
			$parts = array();
			$this->collect_error_message_parts( $value, $parts );

			return $this->redact_support_text( implode( '; ', array_unique( array_filter( $parts ) ) ) );
		}

		/**
		 * Keeps only a bounded, non-secret Cloud error-code identifier.
		 *
		 * @param mixed $value Raw Cloud error code.
		 * @return string
		 */
		private function normalize_remote_error_code( $value ): string {
			if ( ! is_string( $value ) ) {
				return '';
			}

			$code = strtolower( trim( (string) $value ) );
			if ( strlen( $code ) > 120 || 1 !== preg_match( '/\A[a-z0-9]+(?:[._-][a-z0-9]+)*\z/', $code ) ) {
				return '';
			}

			return $code;
		}

		/**
		 * Maps an upstream failure into a non-successful local REST status.
		 *
		 * HTTP 2xx error envelopes remain upstream evidence only; projecting 2xx in
		 * WP_Error data would make a failed local request look successful.
		 *
		 * @param int $status Upstream HTTP status.
		 * @return int
		 */
		private function normalize_remote_failure_status( int $status ): int {
			return $status >= 400 && $status <= 599 ? $status : 502;
		}

		/**
		 * Recursively collects Cloud error text without casting arrays to strings.
		 *
		 * @param mixed         $value Error value.
		 * @param array<int,string> $parts Message parts.
		 * @param int           $depth Recursion depth.
		 * @return void
		 */
		private function collect_error_message_parts( $value, array &$parts, int $depth = 0 ): void {
			if ( $depth > 6 || '' === $value || null === $value ) {
				return;
			}

			if ( is_scalar( $value ) ) {
				$parts[] = $this->redact_support_text( (string) $value );
				return;
			}

			if ( ! is_array( $value ) ) {
				return;
			}

			$message_value = null;
			foreach ( array( 'msg', 'message', 'detail', 'error', 'error_message' ) as $key ) {
				if ( array_key_exists( $key, $value ) ) {
					$message_value = $value[ $key ];
					break;
				}
			}

			if ( null !== $message_value ) {
				$nested_parts = array();
				$this->collect_error_message_parts( $message_value, $nested_parts, $depth + 1 );
				$message = implode( '; ', array_filter( $nested_parts ) );
				$path    = $this->normalize_error_path( $value['loc'] ?? $value['path'] ?? array() );
				if ( '' !== $message && '' !== $path ) {
					$parts[] = $path . ': ' . $message;
				} elseif ( '' !== $message ) {
					$parts[] = $message;
				}
				return;
			}

			$is_list = array() === $value || array_keys( $value ) === range( 0, count( $value ) - 1 );
			if ( $is_list ) {
				foreach ( $value as $item ) {
					$this->collect_error_message_parts( $item, $parts, $depth + 1 );
				}
			}
		}

		/**
		 * Normalizes structured Cloud error location into a dotted path.
		 *
		 * @param mixed $value Error location value.
		 * @return string
		 */
		private function normalize_error_path( $value ): string {
			if ( is_scalar( $value ) || null === $value ) {
				return sanitize_text_field( (string) $value );
			}

			if ( ! is_array( $value ) ) {
				return '';
			}

			return sanitize_text_field( implode( '.', array_map( 'strval', $value ) ) );
		}

		/**
		 * Retrieves one response header without depending on WP internals in tests.
		 *
		 * @param array<string,mixed> $response WP HTTP response.
		 * @param string              $header Header name.
		 * @return string
		 */
		private function response_header( array $response, string $header ): string {
			if ( function_exists( 'wp_remote_retrieve_header' ) ) {
				return sanitize_text_field( (string) wp_remote_retrieve_header( $response, $header ) );
			}

			$headers = is_array( $response['headers'] ?? null ) ? $response['headers'] : array();
			foreach ( $headers as $name => $value ) {
				if ( strtolower( (string) $name ) === strtolower( $header ) ) {
					return sanitize_text_field( (string) $value );
				}
			}

			return '';
		}

		/**
		 * Builds signed Cloud headers.
		 *
		 * @param string $method HTTP method.
		 * @param string $path Relative path with optional query.
		 * @param string $body JSON request body.
		 * @param string $idempotency_key Idempotency key.
		 * @param string $trace_id Trace id.
		 * @return array<string,string>
		 */
		private function build_signed_headers( string $method, string $path, string $body, string $idempotency_key, string $trace_id, string $content_type = 'application/json' ): array {
			$timestamp = (string) time();
			$traceparent = $this->build_traceparent( $trace_id );
			$nonce = $this->build_request_nonce( $method, $path );
			$body_digest = hash( 'sha256', $body );
			$canonical = implode(
				"\n",
				array(
					strtoupper( $method ),
					$path,
					(string) ( $this->config['site_id'] ?? '' ),
					(string) ( $this->config['key_id'] ?? '' ),
					$timestamp,
					$nonce,
					$idempotency_key,
					$traceparent,
					$body_digest,
				)
			);
			$signature = hash_hmac( 'sha256', $canonical, (string) ( $this->config['secret'] ?? '' ) );

			$headers = array(
				'Accept' => 'application/json',
				'Content-Type' => sanitize_text_field( $content_type ),
				'X-Npcink-Site-Id' => (string) ( $this->config['site_id'] ?? '' ),
				'X-Npcink-Key-Id' => (string) ( $this->config['key_id'] ?? '' ),
				'X-Npcink-Timestamp' => $timestamp,
				'X-Npcink-Signature' => strtolower( $signature ),
				'X-Npcink-Trace-Id' => $trace_id,
				'traceparent' => $traceparent,
			);

			if ( '' !== $nonce ) {
				$headers['X-Npcink-Nonce'] = $nonce;
			}
			if ( '' !== $idempotency_key ) {
				$headers['Idempotency-Key'] = $idempotency_key;
			}

			return $headers;
		}

		/**
		 * Builds the fixed two-part media upload body.
		 *
		 * @param array<string,mixed> $payload Upload request payload.
		 * @param string              $contents Image bytes.
		 * @param string              $filename Sanitized filename.
		 * @param string              $mime_type Allowed image MIME type.
		 * @return array{body:string,content_type:string}|WP_Error
		 */
		private function build_media_upload_multipart_body( array $payload, string $contents, string $filename, string $mime_type ) {
			$encoded = wp_json_encode( $payload );
			if ( ! is_string( $encoded ) || '' === $encoded ) {
				return new WP_Error(
					'cloud_runtime_encode_failed',
					__( 'Cloud media upload request could not be encoded.', 'npcink-cloud-addon' )
				);
			}

			$boundary = 'npcink-cloud-addon-media-' . wp_generate_uuid4();
			$body = '--' . $boundary . "\r\n";
			$body .= "Content-Disposition: form-data; name=\"request\"\r\n";
			$body .= "Content-Type: application/json\r\n\r\n";
			$body .= $encoded . "\r\n";
			$body .= '--' . $boundary . "\r\n";
			$body .= 'Content-Disposition: form-data; name="file"; filename="' . $filename . "\"\r\n";
			$body .= 'Content-Type: ' . $mime_type . "\r\n\r\n";
			$body .= $contents . "\r\n";
			$body .= '--' . $boundary . "--\r\n";

			return array(
				'body'         => $body,
				'content_type' => 'multipart/form-data; boundary=' . $boundary,
			);
		}

		/**
		 * Validates one exact media_upload_result.v1 response.
		 *
		 * @param array<string,mixed> $response Decoded Cloud response.
		 * @param string              $mime_type Requested MIME type.
		 * @param string              $contents Uploaded bytes.
		 * @return array<string,mixed>|WP_Error
		 */
		private function normalize_media_upload_response( array $response, string $mime_type, string $contents ) {
			$result   = $response['data']['result'] ?? null;
			$artifact = is_array( $result ) ? ( $result['artifact'] ?? null ) : null;
			$expires_at = is_array( $artifact ) && is_string( $artifact['expires_at'] ?? null )
				? $artifact['expires_at']
				: '';
			$expires_timestamp = 1 === preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:\d{2})$/', $expires_at )
				? strtotime( $expires_at )
				: false;
			$expected_format = self::MEDIA_UPLOAD_FORMATS[ $mime_type ] ?? '';
			$result_keys = array( 'artifact_type', 'contract_version', 'artifact' );
			$artifact_keys = array( 'artifact_id', 'media_kind', 'status', 'content_type', 'format', 'width', 'height', 'filesize_bytes', 'checksum', 'expires_at', 'purged_at' );
			$is_valid = is_array( $result )
				&& count( $result_keys ) === count( $result )
				&& array() === array_diff( $result_keys, array_keys( $result ) )
				&& array() === array_diff( array_keys( $result ), $result_keys )
				&& 'media_upload_artifact' === ( $result['artifact_type'] ?? null )
				&& 'media_upload_result.v1' === ( $result['contract_version'] ?? null )
				&& is_array( $artifact )
				&& count( $artifact_keys ) === count( $artifact )
				&& array() === array_diff( $artifact_keys, array_keys( $artifact ) )
				&& array() === array_diff( array_keys( $artifact ), $artifact_keys )
				&& is_string( $artifact['artifact_id'] ?? null )
				&& 1 === preg_match( self::MEDIA_ARTIFACT_ID_PATTERN, $artifact['artifact_id'] )
				&& 'image' === ( $artifact['media_kind'] ?? null )
				&& 'available' === ( $artifact['status'] ?? null )
				&& null === ( $artifact['purged_at'] ?? null )
				&& $mime_type === ( $artifact['content_type'] ?? null )
				&& $expected_format === ( $artifact['format'] ?? null )
				&& is_int( $artifact['width'] ?? null )
				&& $artifact['width'] > 0
				&& is_int( $artifact['height'] ?? null )
				&& $artifact['height'] > 0
				&& is_int( $artifact['filesize_bytes'] ?? null )
				&& strlen( $contents ) === $artifact['filesize_bytes']
				&& is_string( $artifact['checksum'] ?? null )
				&& 'sha256:' . hash( 'sha256', $contents ) === $artifact['checksum']
				&& false !== $expires_timestamp
				&& $expires_timestamp > time();

			if ( ! $is_valid ) {
				return new WP_Error(
					'cloud_media_upload_artifact_invalid',
					__( 'Cloud returned an invalid media upload artifact.', 'npcink-cloud-addon' ),
					array( 'status' => 502 )
				);
			}

			return $artifact;
		}

		/**
		 * Validates the exact Cloud transfer-only acknowledgement projection.
		 *
		 * @param array<string,mixed> $response Decoded Cloud response.
		 * @param string              $artifact_id Expected artifact id.
		 * @param array<string,mixed> $request Exact ACK request.
		 * @return array<string,mixed>|WP_Error
		 */
		private function normalize_media_delivery_ack_response( array $response, string $artifact_id, array $request ) {
			$data = $response['data'] ?? null;
			$keys = array(
				'contract_version',
				'delivery_id',
				'artifact_id',
				'status',
				'received_byte_size',
				'received_checksum',
				'byte_size_verified',
				'checksum_verified',
				'acknowledged_at',
				'artifact_expires_at',
				'idempotent_replay',
				'acknowledgement_scope',
			);
			$is_valid = is_array( $data )
				&& count( $keys ) === count( $data )
				&& array() === array_diff( $keys, array_keys( $data ) )
				&& array() === array_diff( array_keys( $data ), $keys )
				&& 'media_artifact_delivery_ack.v1' === ( $data['contract_version'] ?? null )
				&& (string) ( $request['delivery_id'] ?? '' ) === ( $data['delivery_id'] ?? null )
				&& $artifact_id === ( $data['artifact_id'] ?? null )
				&& 'acknowledged' === ( $data['status'] ?? null )
				&& (int) ( $request['received_byte_size'] ?? -1 ) === ( $data['received_byte_size'] ?? null )
				&& (string) ( $request['received_checksum'] ?? '' ) === ( $data['received_checksum'] ?? null )
				&& true === ( $data['byte_size_verified'] ?? null )
				&& true === ( $data['checksum_verified'] ?? null )
				&& is_string( $data['acknowledged_at'] ?? null )
				&& false !== self::strict_media_timestamp( (string) $data['acknowledged_at'] )
				&& is_string( $data['artifact_expires_at'] ?? null )
				&& false !== self::strict_media_timestamp( (string) $data['artifact_expires_at'] )
				&& is_bool( $data['idempotent_replay'] ?? null )
				&& 'verified_transfer_only' === ( $data['acknowledgement_scope'] ?? null );

			if ( ! $is_valid ) {
				return new WP_Error(
					'cloud_media_delivery_ack_response_invalid',
					__( 'Cloud returned an invalid media delivery acknowledgement.', 'npcink-cloud-addon' ),
					array( 'status' => 502 )
				);
			}

			return $data;
		}

		/**
		 * Parses exact canonical UTC RFC3339 media timestamps.
		 *
		 * @param string $value Timestamp.
		 * @return int|false
		 */
		private static function strict_media_timestamp( string $value ) {
			$utc = new DateTimeZone( 'UTC' );
			$formats = array(
				'!Y-m-d\TH:i:s\Z'   => 'Y-m-d\TH:i:s\Z',
				'!Y-m-d\TH:i:sP'    => 'Y-m-d\TH:i:sP',
				'!Y-m-d\TH:i:s.u\Z' => 'Y-m-d\TH:i:s.u\Z',
				'!Y-m-d\TH:i:s.uP'  => 'Y-m-d\TH:i:s.uP',
			);

			foreach ( $formats as $parse_format => $roundtrip_format ) {
				$timestamp = DateTimeImmutable::createFromFormat( $parse_format, $value, $utc );
				$errors    = DateTimeImmutable::getLastErrors();
				if (
					false === $timestamp
					|| ( is_array( $errors ) && ( $errors['warning_count'] > 0 || $errors['error_count'] > 0 ) )
					|| 0 !== $timestamp->getOffset()
					|| $value !== $timestamp->format( $roundtrip_format )
				) {
					continue;
				}

				return $timestamp->getTimestamp();
			}

			return false;
		}

		/**
		 * Validates one Cloud alt-text upload artifact response.
		 *
		 * @param array<string,mixed> $response Decoded Cloud response.
		 * @param string              $mime_type Requested MIME type.
		 * @param string              $contents Uploaded image bytes.
		 * @return array<string,mixed>|WP_Error
		 */
		private function normalize_wordpress_ai_alt_text_upload_response( array $response, string $mime_type, string $contents ) {
			$result   = $response['data']['result'] ?? null;
			$artifact = is_array( $result ) ? ( $result['artifact'] ?? null ) : null;
			$expires_at = is_array( $artifact ) && is_string( $artifact['expires_at'] ?? null )
				? $artifact['expires_at']
				: '';
			$expires_timestamp = 1 === preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:\d{2})$/', $expires_at )
				? strtotime( $expires_at )
				: false;
			$expected_format = self::WP_AI_ALT_TEXT_UPLOAD_FORMATS[ $mime_type ];
			$is_valid = is_array( $result )
				&& 'media_upload_artifact' === ( $result['artifact_type'] ?? null )
				&& 'media_upload_result.v1' === ( $result['contract_version'] ?? null )
				&& is_array( $artifact )
				&& is_string( $artifact['artifact_id'] ?? null )
				&& 1 === preg_match( '/^art_[0-9a-f]{32}$/', $artifact['artifact_id'] )
				&& 'image' === ( $artifact['media_kind'] ?? null )
				&& 'available' === ( $artifact['status'] ?? null )
				&& $mime_type === ( $artifact['content_type'] ?? null )
				&& $expected_format === ( $artifact['format'] ?? null )
				&& is_int( $artifact['width'] ?? null )
				&& $artifact['width'] > 0
				&& is_int( $artifact['height'] ?? null )
				&& $artifact['height'] > 0
				&& is_int( $artifact['filesize_bytes'] ?? null )
				&& strlen( $contents ) === $artifact['filesize_bytes']
				&& is_string( $artifact['checksum'] ?? null )
				&& 'sha256:' . hash( 'sha256', $contents ) === $artifact['checksum']
				&& false !== $expires_timestamp
				&& $expires_timestamp > time() + self::WP_AI_ALT_TEXT_MIN_ARTIFACT_TTL_SECONDS;

			if ( ! $is_valid ) {
				return new WP_Error(
					'cloud_wp_ai_alt_text_upload_artifact_invalid',
					__( 'Cloud returned an invalid WordPress AI alt-text source artifact.', 'npcink-cloud-addon' ),
					array( 'status' => 502 )
				);
			}

			return array(
				'artifact_id'    => $artifact['artifact_id'],
				'media_kind'     => 'image',
				'status'         => 'available',
				'content_type'   => $mime_type,
				'format'         => $expected_format,
				'width'          => $artifact['width'],
				'height'         => $artifact['height'],
				'filesize_bytes' => $artifact['filesize_bytes'],
				'checksum'       => $artifact['checksum'],
				'expires_at'     => $expires_at,
			);
		}

		/**
		 * Normalizes a WordPress AI connector request into a bounded runtime payload.
		 *
		 * @param array<string,mixed> $request Raw connector request.
		 * @return array<string,mixed>|WP_Error
		 */
		private function normalize_wordpress_ai_connector_request( array $request ) {
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

			$forbidden_key = $this->find_forbidden_wordpress_ai_connector_key( $request );
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
				$scene_request = $this->normalize_wordpress_ai_alt_text_request( $scene_request );
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
				if ( $this->text_length( $scene_request['source_text'] ) > self::WP_AI_CONNECTOR_MAX_SCENE_TEXT_CHARS ) {
					return new WP_Error(
						'cloud_wp_ai_connector_source_text_too_large',
						__( 'WordPress AI source_text exceeds the scene runtime size limit.', 'npcink-cloud-addon' ),
						array( 'status' => 413 )
					);
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
					if ( $this->text_length( $scene_request['system_instruction'] ) > self::WP_AI_CONNECTOR_MAX_SCENE_TEXT_CHARS ) {
						return new WP_Error(
							'cloud_wp_ai_connector_system_instruction_too_large',
							__( 'WordPress AI system_instruction exceeds the scene runtime size limit.', 'npcink-cloud-addon' ),
							array( 'status' => 413 )
						);
					}
				}
			}

			$prompt = (string) ( $scene_request['prompt'] ?? '' );
			if ( '' !== $prompt && $this->text_length( $prompt ) > self::WP_AI_CONNECTOR_MAX_SCENE_TEXT_CHARS ) {
				return new WP_Error(
					'cloud_wp_ai_connector_prompt_too_large',
					__( 'WordPress AI connector prompt exceeds the scene runtime size limit.', 'npcink-cloud-addon' ),
					array( 'status' => 413 )
				);
			}

			$timeout_seconds = absint( $request['timeout_seconds'] ?? 20 );
			$retention_ttl   = absint( $request['retention_ttl'] ?? self::WP_AI_CONNECTOR_MAX_RETENTION_TTL );
			$retry_max       = absint( $request['retry_max'] ?? 0 );
			$profile_id      = $this->normalize_identifier( (string) ( $request['profile_id'] ?? 'text.balanced' ) );
			if ( null !== $task_contract ) {
				$scene_request['task_contract'] = $task_contract;
			}
			$site_knowledge_reference = $this->normalize_wordpress_ai_site_knowledge_reference(
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

			$site_id = $this->normalize_identifier( (string) ( $this->config['site_id'] ?? '' ) );
			if ( '' === $site_id ) {
				return new WP_Error(
					'cloud_wp_ai_connector_site_id_required',
					__( 'WordPress AI connector requests require a verified Cloud site_id.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}
			$site_url = function_exists( 'home_url' ) ? untrailingslashit( home_url( '/' ) ) : '';
			if ( '' === $site_url ) {
				return new WP_Error(
					'cloud_wp_ai_connector_site_url_required',
					__( 'WordPress AI connector requests require the canonical WordPress site URL.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}
			$connector_version = defined( 'NPCINK_CLOUD_ADDON_VERSION' ) ? (string) NPCINK_CLOUD_ADDON_VERSION : '';
			if ( '' === $connector_version ) {
				return new WP_Error(
					'cloud_wp_ai_connector_version_required',
					__( 'WordPress AI connector requests require the active addon version.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}
			$is_alt_text = 'alt_text_suggest' === $task;
			$contains_pii = $this->wordpress_ai_scene_contains_obvious_pii( $scene_request );

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
		private function wordpress_ai_scene_contains_obvious_pii( $value ): bool {
			if ( is_array( $value ) ) {
				foreach ( $value as $item ) {
					if ( $this->wordpress_ai_scene_contains_obvious_pii( $item ) ) {
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
		private function normalize_wordpress_ai_alt_text_request( array $request ) {
			$allowed_fields = array( 'source_artifact_id', 'prompt', 'filename', 'title', 'existing_alt', 'existing_caption', 'locale', 'max_tokens' );
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
			if ( ! is_string( $prompt ) || '' === trim( $prompt ) || $this->text_length( trim( $prompt ) ) > 500 ) {
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
				if ( ! is_string( $request[ $field ] ) || $this->text_length( $request[ $field ] ) > $limit ) {
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
		private function normalize_wordpress_ai_site_knowledge_reference( $reference, string $task, array $task_contract = array() ) {
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
		private function normalize_wordpress_ai_image_generation_request( array $request ) {
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

			$forbidden_key = $this->find_forbidden_wordpress_ai_connector_key( $request );
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
			if ( $this->text_length( $prompt ) > self::WP_AI_IMAGE_GENERATION_MAX_PROMPT_CHARS ) {
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
		private function normalize_toolbox_image_generation_request( array $request ) {
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

			$forbidden_key = $this->find_forbidden_wordpress_ai_connector_key( $request );
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
			if ( $this->text_length( $prompt ) > self::WP_AI_IMAGE_GENERATION_MAX_PROMPT_CHARS ) {
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
		private function normalize_toolbox_audio_generation_request( array $request ) {
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

			$forbidden_key = $this->find_forbidden_wordpress_ai_connector_key( $request );
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
			if ( $this->text_length( $raw_text ) > self::TOOLBOX_AUDIO_GENERATION_MAX_TEXT_CHARS ) {
				return new WP_Error(
					'cloud_toolbox_audio_generation_text_too_large',
					__( 'Toolbox audio generation text exceeds the scene runtime size limit.', 'npcink-cloud-addon' ),
					array( 'status' => 413 )
				);
			}
			$text = $this->bounded_text( $raw_text, self::TOOLBOX_AUDIO_GENERATION_MAX_TEXT_CHARS );

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
			$summary_text    = 'article_audio_summary' === $intent ? $this->bounded_text( (string) ( $request['summary_text'] ?? $text ), self::TOOLBOX_AUDIO_GENERATION_MAX_TEXT_CHARS ) : '';

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
					'script'            => $this->bounded_text( (string) ( $request['script'] ?? $text ), self::TOOLBOX_AUDIO_GENERATION_MAX_TEXT_CHARS ),
					'voice_id'          => sanitize_text_field( (string) ( $request['voice_id'] ?? '' ) ),
					'format'            => $format,
					'response_format'   => 'url',
					'purpose'           => 'article_audio_summary' === $intent ? 'longform_audio_summary' : 'article_narration',
					'user_instruction'  => $this->bounded_text( (string) ( $request['user_instruction'] ?? '' ), 1200 ),
					'audio_preferences' => is_array( $request['audio_preferences'] ?? null ) ? $this->sanitize_payload( $request['audio_preferences'] ) : array(),
					'context'           => is_array( $request['context'] ?? null ) ? $this->sanitize_payload( $request['context'] ) : array(),
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
		private function normalize_toolbox_site_ops_cloud_analysis_request( array $request ) {
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

			$forbidden_key = $this->find_forbidden_wordpress_ai_connector_key( $request );
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
				'input'               => $this->sanitize_payload( $request ),
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
		private function normalize_toolbox_media_governance_audit_request( array $request ) {
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
		private function normalize_toolbox_web_search_request( array $request ) {
			$contract_version = (string) ( $request['contract_version'] ?? self::TOOLBOX_WEB_SEARCH_CONTRACT );
			if ( self::TOOLBOX_WEB_SEARCH_CONTRACT !== $contract_version ) {
				return new WP_Error(
					'cloud_toolbox_web_search_contract_invalid',
					__( 'Toolbox web search requests require the web_search.v1 contract.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}

			$forbidden_key = $this->find_forbidden_wordpress_ai_connector_key( $request );
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

			$query = $this->bounded_text( (string) ( $request['query'] ?? '' ), self::TOOLBOX_WEB_SEARCH_MAX_QUERY_CHARS );
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

			$input = $this->sanitize_payload( $request );
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
		private function normalize_toolbox_image_source_request( array $request ) {
			$contract_version = (string) ( $request['contract_version'] ?? self::TOOLBOX_IMAGE_SOURCE_CONTRACT );
			if ( self::TOOLBOX_IMAGE_SOURCE_CONTRACT !== $contract_version ) {
				return new WP_Error(
					'cloud_toolbox_image_source_contract_invalid',
					__( 'Toolbox image-source requests require the image_source_cloud_request.v1 contract.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}

			$forbidden_key = $this->find_forbidden_wordpress_ai_connector_key( $request );
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

			$query = $this->bounded_text( (string) ( $request['query'] ?? '' ), self::TOOLBOX_IMAGE_SOURCE_MAX_QUERY_CHARS );
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

			$input = $this->sanitize_payload( $request );
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
		private function find_forbidden_wordpress_ai_connector_key( $value ): string {
			if ( ! is_array( $value ) ) {
				return '';
			}

			foreach ( $value as $key => $item ) {
				$normalized_key = sanitize_key( str_replace( '-', '_', (string) $key ) );
				if ( in_array( $normalized_key, self::WP_AI_CONNECTOR_FORBIDDEN_KEYS, true ) ) {
					return $normalized_key;
				}
				$nested = $this->find_forbidden_wordpress_ai_connector_key( $item );
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
		private function normalize_agent_feedback_payload( array $payload ) {
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

			$forbidden_key = $this->find_forbidden_agent_feedback_key( $payload );
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
				if ( $this->text_length( $value ) > $max_chars || ( in_array( $field, $required_fields, true ) && '' === $value ) ) {
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
					if ( ! is_string( $item ) || '' === trim( $item ) || $this->text_length( trim( $item ) ) > $limits[1] ) {
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
		private function find_forbidden_agent_feedback_key( $value, string $prefix = '' ): string {
			if ( ! is_array( $value ) ) {
				return '';
			}
			foreach ( $value as $key => $item ) {
				$normalized_key = strtolower( trim( (string) $key ) );
				$current_path = '' === $prefix ? $normalized_key : $prefix . '.' . $normalized_key;
				if ( in_array( $normalized_key, self::AGENT_FEEDBACK_FORBIDDEN_KEYS, true ) ) {
					return $current_path;
				}
				$nested = $this->find_forbidden_agent_feedback_key( $item, $current_path );
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
		private function normalize_image_context_evidence_request( array $request ) {
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
					'candidate_quality_flags'  => array_slice( $this->sanitize_string_list( $item['candidate_quality_flags'] ?? array() ), 0, 12 ),
					'filtered_candidate_notes' => array_slice( $this->sanitize_string_list( $item['filtered_candidate_notes'] ?? array() ), 0, 12 ),
				);
				if ( 'background_completion' !== $dispatch_mode ) {
					$normalized_item['title'] = $this->bounded_text( (string) ( $item['title'] ?? '' ), 160 );
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
		private function normalize_image_context_evidence_response( array $response, array $request ) {
			$payload = $this->extract_image_context_evidence_payload( $response );
			$payload_source = $payload['source'] ?? 'cloud_or_host_runtime';
			$source         = $this->normalize_image_context_evidence_source( $payload_source );
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
					$this->sanitize_string_list( $item['subject_tags'] ?? ( $item['objects'] ?? array() ) ),
					0,
					12
				);
				$visible_text = array_slice(
					$this->sanitize_string_list( $item['visible_text'] ?? ( $item['text_seen'] ?? array() ) ),
					0,
					8
				);
				$items[] = array(
					'attachment_id'              => $attachment_id,
					'contract_version'           => 'image_context_evidence.v1',
					'source'                     => $this->normalize_image_context_evidence_source( $item['source'] ?? $payload_source ),
					'visual_summary'             => $this->normalize_image_context_evidence_text( $item['visual_summary'] ?? '', 240 ),
					'scene'                      => $this->normalize_image_context_evidence_text( $item['scene'] ?? '', 160 ),
					'subject_tags'               => $subject_tags,
					'visible_text'               => $visible_text,
					'alt_text_basis'             => $this->normalize_image_context_evidence_text( $item['alt_text_basis'] ?? '', 240 ),
					'caption_basis'              => $this->normalize_image_context_evidence_text( $item['caption_basis'] ?? '', 320 ),
					'uncertainty_flags'          => array_slice( $this->sanitize_string_list( $item['uncertainty_flags'] ?? array() ), 0, 12 ),
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
		private function normalize_image_context_evidence_source( $source ): string {
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
		private function normalize_image_context_evidence_text( $value, int $max_chars ): string {
			if ( is_array( $value ) ) {
				$value = implode( '; ', array_slice( $this->sanitize_string_list( $value ), 0, 8 ) );
			}

			return is_scalar( $value ) ? $this->bounded_text( (string) $value, $max_chars ) : '';
		}

		/**
		 * Extracts image context evidence from common runtime envelopes.
		 *
		 * @param array<string,mixed> $response Cloud response.
		 * @return array<string,mixed>
		 */
		private function extract_image_context_evidence_payload( array $response ): array {
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
		private function sanitize_string_list( $value ): array {
			$items = is_array( $value ) ? $value : preg_split( '/[\r\n,]+/', (string) $value );
			$items = is_array( $items ) ? $items : array();

			return array_values(
				array_filter(
					array_map(
						function ( $item ): string {
							return is_scalar( $item ) ? $this->bounded_text( (string) $item, 120 ) : '';
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
		private function bounded_text( string $value, int $max_chars ): string {
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
		private function sanitize_payload( $value, int $depth = 0 ) {
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
					$sanitized[ $normalized_key ] = $this->sanitize_payload( $item, $depth + 1 );
				}
				return $sanitized;
			}
			if ( is_bool( $value ) || is_int( $value ) || is_float( $value ) || null === $value ) {
				return $value;
			}

			return $this->bounded_text( (string) $value, 1200 );
		}

		/**
		 * Returns the character length of a UTF-8 string when available.
		 *
		 * @param string $value Raw text.
		 * @return int
		 */
		private function text_length( string $value ): int {
			if ( function_exists( 'mb_strlen' ) ) {
				return mb_strlen( $value, 'UTF-8' );
			}

			return strlen( $value );
		}

		/**
		 * Builds full request URL.
		 *
		 * @param string $path Relative path.
		 * @return string
		 */
		private function build_request_url( string $path ): string {
			return untrailingslashit( (string) ( $this->config['base_url'] ?? '' ) ) . $path;
		}

		/**
		 * Builds a fresh request nonce for signed POST and media pull calls.
		 *
		 * @param string $method HTTP method.
		 * @param string $path Signed request path.
		 * @return string
		 */
		private function build_request_nonce( string $method, string $path ): string {
			$is_post       = 'POST' === strtoupper( $method );
			$is_media_pull = 'GET' === strtoupper( $method )
				&& 1 === preg_match( '#^/v1/runtime/media/artifacts/art_[0-9a-f]{32}/download$#', $path );
			if ( ! $is_post && ! $is_media_pull ) {
				return '';
			}

			return 'nonce-' . strtolower( str_replace( '-', '', wp_generate_uuid4() ) );
		}

		/**
		 * Builds a W3C traceparent header from a local trace id.
		 *
		 * @param string $trace_id Trace id.
		 * @return string
		 */
		private function build_traceparent( string $trace_id ): string {
			$normalized = strtolower( preg_replace( '/[^a-f0-9]/', '', $trace_id ) );
			$normalized = is_string( $normalized ) ? $normalized : '';
			if ( 32 !== strlen( $normalized ) ) {
				$normalized = substr( hash( 'sha256', $trace_id ), 0, 32 );
			}
			$parent_id = substr( hash( 'sha256', $normalized . '|parent' ), 0, 16 );

			return '00-' . $normalized . '-' . $parent_id . '-01';
		}

		/**
		 * Validates one caller-supplied trace or idempotency identifier.
		 *
		 * @param string $value Raw identifier.
		 * @param string $kind Identifier kind for the error code.
		 * @return string|WP_Error
		 */
		private function normalize_request_identifier( string $value, string $kind ) {
			$value = trim( $value );
			if ( '' === $value ) {
				return '';
			}
			if ( strlen( $value ) > self::REQUEST_IDENTIFIER_MAX_CHARS || 1 !== preg_match( '/\A[A-Za-z0-9._:-]+\z/', $value ) ) {
				$error_code = 'idempotency' === $kind
					? 'cloud_runtime_idempotency_key_invalid'
					: 'cloud_runtime_trace_id_invalid';
				return new WP_Error(
					$error_code,
					__( 'Cloud request identifiers must use only letters, numbers, dot, underscore, colon, or hyphen and contain at most 128 characters.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}

			return $value;
		}

		/**
		 * Normalizes Cloud ids used in URL paths.
		 *
		 * @param string $value Raw identifier.
		 * @return string
		 */
		private function normalize_identifier( string $value ): string {
			$value = sanitize_text_field( trim( $value ) );
			$value = preg_replace( '/[^A-Za-z0-9._:-]/', '', $value );

			return is_string( $value ) ? $value : '';
		}

		/**
		 * Maps Cloud error codes into the addon namespace.
		 *
		 * @param string $cloud_error_code Raw Cloud error code.
		 * @return string
		 */
		private function map_remote_error_code( string $cloud_error_code ): string {
			$cloud_error_code = sanitize_text_field( $cloud_error_code );
			if ( '' === $cloud_error_code ) {
				return 'cloud_runtime_failed';
			}

			return 'cloud_' . sanitize_key( str_replace( '.', '_', $cloud_error_code ) );
		}

		/**
		 * Formats a transport error for operators.
		 *
		 * @param string $message Raw transport error.
		 * @return string
		 */
		private function format_transport_error_message( string $message ): string {
			$message = trim( wp_strip_all_tags( $message ) );
			if ( '' === $message ) {
				return __( 'Cannot connect to Npcink Cloud. Check the Cloud Base URL.', 'npcink-cloud-addon' );
			}

			return sprintf(
				/* translators: 1: Cloud base URL, 2: transport error. */
				__( 'Cannot connect to %1$s. Original error: %2$s', 'npcink-cloud-addon' ),
				(string) ( $this->config['base_url'] ?? '' ),
				$message
			);
		}
	}
}
