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
		private const MEDIA_DELIVERY_ID_PATTERN = '/^mdl_[0-9a-f]{32}$/';
		private const MEDIA_UPLOAD_FORMATS = array(
			'image/avif' => 'avif',
			'image/jpeg' => 'jpeg',
			'image/png'  => 'png',
			'image/webp' => 'webp',
		);
		private const WP_AI_ALT_TEXT_MAX_UPLOAD_BYTES = 8388608;
		private const WP_AI_ALT_TEXT_MIN_ARTIFACT_TTL_SECONDS = 120;
		private const WP_AI_ALT_TEXT_UPLOAD_FORMATS = array(
			'image/jpeg' => 'jpeg',
			'image/png'  => 'png',
			'image/webp' => 'webp',
		);
		private const REQUEST_IDENTIFIER_MAX_CHARS = 128;

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
				'auth_error_code' => (string) $auth_probe['error_code'],
				'auth_error_data' => $auth_probe['error_data'],
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
			if ( ! empty( $probe['auth_ok'] ) && is_array( $probe['entitlement_response'] ?? null ) && class_exists( 'Npcink_Cloud_Entitlement_Summary' ) ) {
				Npcink_Cloud_Entitlement_Summary::cache_summary_from_response( $probe['entitlement_response'], $this->config );
			} elseif ( class_exists( 'Npcink_Cloud_Entitlement_Summary' ) ) {
				Npcink_Cloud_Entitlement_Summary::record_capability_refresh_failure( $this->config );
			}

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
			$payload = Npcink_Cloud_Runtime_Request_Guards::normalize_wordpress_ai_connector_request(
				$request,
				(string) ( $this->config['site_id'] ?? '' ),
				function_exists( 'home_url' ) ? untrailingslashit( home_url( '/' ) ) : '',
				defined( 'NPCINK_CLOUD_ADDON_VERSION' ) ? (string) NPCINK_CLOUD_ADDON_VERSION : ''
			);
			if ( is_wp_error( $payload ) ) {
				return $payload;
			}

			if ( '' === $idempotency_key ) {
				$idempotency_key = 'wp_ai_connector_' . wp_generate_uuid4();
			}

			$response = $this->request( 'POST', '/v1/runtime/execute', $payload, $idempotency_key, $trace_id );
			return $this->project_runtime_execute_failure( $response );
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
			$payload = Npcink_Cloud_Runtime_Request_Guards::normalize_wordpress_ai_image_generation_request( $request );
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
			$payload = Npcink_Cloud_Runtime_Request_Guards::normalize_toolbox_image_generation_request( $request );
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
			$payload = Npcink_Cloud_Runtime_Request_Guards::normalize_toolbox_audio_generation_request( $request );
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
			$payload = Npcink_Cloud_Runtime_Request_Guards::normalize_toolbox_site_ops_cloud_analysis_request( $request );
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
			$payload = Npcink_Cloud_Runtime_Request_Guards::normalize_toolbox_media_governance_audit_request( $request );
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
			$payload = Npcink_Cloud_Runtime_Request_Guards::normalize_toolbox_web_search_request( $request );
			if ( is_wp_error( $payload ) ) {
				return $payload;
			}

			if ( '' === $idempotency_key ) {
				$idempotency_key = 'toolbox_web_search_' . wp_generate_uuid4();
			}

			return $this->request( 'POST', '/v1/runtime/execute', $payload, $idempotency_key, $trace_id );
		}

		/** Transport exact editor text using the fixed inline, no-store formatting contract. */
		public function execute_toolbox_content_format_runtime( array $request, string $trace_id = '', string $idempotency_key = '' ) {
			$keys = array_keys( $request );
			sort( $keys );
			$content = $request['content'] ?? null;
			if ( array( 'content', 'format', 'source_sha256' ) !== $keys
				|| ! is_string( $content ) || '' === $content || strlen( $content ) > 100000
				|| 1 !== preg_match( '//u', $content )
				|| 'html' !== ( $request['format'] ?? null )
				|| hash( 'sha256', $content ) !== ( $request['source_sha256'] ?? null ) ) {
				return new WP_Error( 'cloud_content_format_invalid', __( 'Invalid content formatting request.', 'npcink-cloud-addon' ), array( 'status' => 400 ) );
			}
			$payload = array(
				'ability_name' => 'npcink-toolbox/format-content',
				'ability_family' => 'text',
				'contract_version' => 'content_format_request.v2',
				'execution_kind' => 'content_format',
				'profile_id' => 'content-format.managed',
				'channel' => 'editor',
				'execution_pattern' => 'inline',
				'storage_mode' => 'no_store',
				'data_classification' => 'pii',
				'timeout_seconds' => 30,
				'retry_max' => 0,
				'retention_ttl' => 0,
				'input' => $request,
			);
			return $this->request( 'POST', '/v1/runtime/execute', $payload, $idempotency_key ?: 'content_format_' . wp_generate_uuid4(), $trace_id );
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
			$payload = Npcink_Cloud_Runtime_Request_Guards::normalize_toolbox_image_source_request( $request );
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
			$request = Npcink_Cloud_Runtime_Request_Guards::normalize_image_context_evidence_request( $image_context_evidence_request );
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

			return Npcink_Cloud_Runtime_Request_Guards::normalize_image_context_evidence_response( is_array( $response ) ? $response : array(), $request );
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
				|| 1 !== preg_match( Npcink_Cloud_Runtime_Request_Guards::MEDIA_ARTIFACT_ID_PATTERN, (string) ( $payload['source_artifact_id'] ?? '' ) )
				|| ! is_array( $payload['params'] ?? null )
				|| 30 !== ( $payload['result_ttl_minutes'] ?? null )
				|| ( isset( $payload['watermark_artifact_id'] ) && 1 !== preg_match( Npcink_Cloud_Runtime_Request_Guards::MEDIA_ARTIFACT_ID_PATTERN, (string) $payload['watermark_artifact_id'] ) )
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
			$run_id = Npcink_Cloud_Runtime_Request_Guards::normalize_identifier( $run_id );
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
			$run_id = Npcink_Cloud_Runtime_Request_Guards::normalize_identifier( $run_id );
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
			$run_id = Npcink_Cloud_Runtime_Request_Guards::normalize_identifier( $run_id );
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
			if ( 1 !== preg_match( Npcink_Cloud_Runtime_Request_Guards::MEDIA_ARTIFACT_ID_PATTERN, $artifact_id ) ) {
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
				1 !== preg_match( Npcink_Cloud_Runtime_Request_Guards::MEDIA_ARTIFACT_ID_PATTERN, $artifact_id )
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
			$site_id = Npcink_Cloud_Runtime_Request_Guards::normalize_identifier( (string) ( $this->config['site_id'] ?? '' ) );
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
			$payload = Npcink_Cloud_Runtime_Request_Guards::normalize_agent_feedback_payload( $payload );
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
						? Npcink_Cloud_Runtime_Request_Guards::WP_AI_IMAGE_GENERATION_MAX_TIMEOUT_SECONDS
						: Npcink_Cloud_Runtime_Request_Guards::WP_AI_CONNECTOR_MAX_TIMEOUT_SECONDS;
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
				&& 1 === preg_match( Npcink_Cloud_Runtime_Request_Guards::MEDIA_ARTIFACT_ID_PATTERN, $artifact['artifact_id'] )
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
			$normalized = strtolower( (string) preg_replace( '/[^a-f0-9]/', '', $trace_id ) );
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
