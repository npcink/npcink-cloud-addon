<?php
/**
 * Cloud addon bootstrap and public seams.
 *
 * @package NpcinkCloudAddon
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/class-cloud-credential-store.php';
require_once __DIR__ . '/class-cloud-outbound-policy.php';
require_once __DIR__ . '/class-cloud-runtime-endpoint-policy.php';
require_once __DIR__ . '/class-cloud-addon-settings.php';
require_once __DIR__ . '/class-cloud-addon-cleanup.php';
require_once __DIR__ . '/class-cloud-ai-task-contract.php';
require_once __DIR__ . '/class-cloud-runtime-client.php';
require_once __DIR__ . '/class-cloud-runtime-client-factory.php';
require_once __DIR__ . '/class-cloud-media-derivative-transport.php';
require_once __DIR__ . '/class-cloud-entitlement-summary.php';
require_once __DIR__ . '/class-cloud-observability-collector.php';
require_once __DIR__ . '/class-cloud-customer-journey.php';
require_once __DIR__ . '/class-cloud-editor-assist-quality.php';
require_once __DIR__ . '/class-cloud-site-knowledge-change-bridge.php';
require_once __DIR__ . '/class-cloud-site-knowledge-runtime-bridge.php';
require_once __DIR__ . '/class-cloud-site-knowledge-admin-projection.php';
require_once __DIR__ . '/class-cloud-site-knowledge-admin-actions.php';
require_once __DIR__ . '/class-cloud-addon-localization.php';
require_once __DIR__ . '/class-ai-plugin-localization.php';
require_once __DIR__ . '/class-cloud-wordpress-ai-connector.php';
require_once __DIR__ . '/class-cloud-settings-page.php';

if ( ! function_exists( 'npcink_cloud_addon_is_configured' ) ) {
	/**
	 * Returns whether Cloud addon credentials are complete.
	 *
	 * @return bool
	 */
	function npcink_cloud_addon_is_configured(): bool {
		return Npcink_Cloud_Addon_Settings::is_configured();
	}
}

if ( ! function_exists( 'npcink_cloud_addon_get_settings' ) ) {
	/**
	 * Returns normalized Cloud addon settings.
	 *
	 * The returned array includes the stored secret for server-side callers.
	 * Do not print this array into admin HTML or logs.
	 *
	 * @deprecated 0.1.7 Use npcink_cloud_addon_get_connection_state() for non-signing integrations.
	 * @return array<string,mixed>
	 */
	function npcink_cloud_addon_get_settings(): array {
		return Npcink_Cloud_Addon_Settings::get_settings();
	}
}

if ( ! function_exists( 'npcink_cloud_addon_get_connection_state' ) ) {
	/**
	 * Returns a non-secret connection and local-permission projection.
	 *
	 * @return array<string,mixed>
	 */
	function npcink_cloud_addon_get_connection_state(): array {
		$settings = Npcink_Cloud_Addon_Settings::get_settings();
		$state = Npcink_Cloud_Addon_Settings::get_credential_state();

		return array_merge(
			$state,
			array(
				'monitoring_enabled' => ! empty( $settings['monitoring_enabled'] ),
				'site_knowledge_delivery_enabled' => ! empty( $settings['site_knowledge_delivery_enabled'] ),
				'site_knowledge_generation_reference_enabled' => ! empty( $settings['site_knowledge_generation_reference_enabled'] ),
				'wordpress_ai_connector_enabled' => ! empty( $settings['wordpress_ai_connector_enabled'] ),
			)
		);
	}
}

if ( ! function_exists( 'npcink_cloud_addon_verified_runtime_client' ) ) {
	/**
	 * Returns a verified runtime client, or null when credentials have not verified.
	 *
	 * Use this helper for Cloud jobs that move local media bytes or generated
	 * artifacts. It fails closed until Save and Verify has passed.
	 *
	 * @return Npcink_Cloud_Runtime_Client|null
	 */
	function npcink_cloud_addon_verified_runtime_client(): ?Npcink_Cloud_Runtime_Client {
		$client = Npcink_Cloud_Media_Derivative_Transport::verified_client();

		return is_wp_error( $client ) ? null : $client;
	}
}

if ( ! function_exists( 'npcink_cloud_addon_get_manual_readiness_result' ) ) {
	/**
	 * Runs the bounded manual connector readiness test.
	 *
	 * The result is non-secret, read-only, and suitable for local status
	 * projection. It does not create runtime work, queues, registries,
	 * approvals, provider logs, or WordPress writes.
	 *
	 * @return array<string,mixed>
	 */
	function npcink_cloud_addon_get_manual_readiness_result(): array {
		$client = new Npcink_Cloud_Runtime_Client( Npcink_Cloud_Addon_Settings::get_settings() );

		return $client->manual_readiness_test();
	}
}

if ( ! function_exists( 'npcink_cloud_addon_get_customer_journey_summary' ) ) {
	/**
	 * Reads the current site's metadata-only customer journey summary.
	 *
	 * This is a manual diagnostic read. It does not expose content, identity,
	 * WordPress write controls, or an analytics dashboard.
	 *
	 * @param int    $window_hours Summary window from 1 to 168 hours.
	 * @param string $cohort_id Optional bounded cohort id.
	 * @return array<string,mixed>|WP_Error
	 */
	function npcink_cloud_addon_get_customer_journey_summary( int $window_hours = 24, string $cohort_id = '' ) {
		$client = Npcink_Cloud_Runtime_Client_Factory::configured();
		if ( ! $client ) {
			return new WP_Error(
				'cloud_runtime_unconfigured',
				__( 'Npcink Cloud is not configured.', 'npcink-cloud-addon' ),
				array( 'status' => 400 )
			);
		}

		return $client->get_customer_journey_summary(
			$window_hours,
			$cohort_id,
			'trace_customer_journey_summary_' . wp_generate_uuid4()
		);
	}
}

if ( ! function_exists( 'npcink_cloud_addon_dispatch_media_derivative_cloud_request' ) ) {
	/**
	 * Dispatches a media derivative Cloud job from a local ability response.
	 *
	 * The source artifact must be created by the local host or an approved
	 * upload seam. This addon only signs and dispatches the runtime request.
	 *
	 * @param array<string,mixed> $ability_response Ability response envelope.
	 * @param array<string,mixed> $source_artifact Short TTL source artifact descriptor.
	 * @param string              $trace_id Optional trace id.
	 * @param string              $idempotency_key Optional idempotency key.
	 * @param array<string,mixed> $watermark_artifact Optional short TTL watermark artifact or upload descriptor.
	 * @return array<string,mixed>|WP_Error
	 */
	function npcink_cloud_addon_dispatch_media_derivative_cloud_request( array $ability_response, array $source_artifact, string $trace_id = '', string $idempotency_key = '', array $watermark_artifact = array() ) {
		return Npcink_Cloud_Media_Derivative_Transport::dispatch_from_ability_response(
			$ability_response,
			$source_artifact,
			$trace_id,
			$idempotency_key,
			$watermark_artifact
		);
	}
}

if ( ! function_exists( 'npcink_cloud_addon_request_image_context_evidence' ) ) {
	/**
	 * Requests Cloud-owned image context evidence for weak media metadata.
	 *
	 * This only signs and transports a bounded request artifact. It does not
	 * run a local vision model, create a proposal, or write media metadata.
	 *
	 * @param array<string,mixed> $image_context_evidence_request Toolbox image_context_evidence_request.v1 artifact.
	 * @param string              $trace_id Optional trace id.
	 * @param string              $idempotency_key Optional idempotency key.
	 * @return array<string,mixed>|WP_Error
	 */
	function npcink_cloud_addon_request_image_context_evidence( array $image_context_evidence_request, string $trace_id = '', string $idempotency_key = '' ) {
		$client = Npcink_Cloud_Runtime_Client_Factory::configured();
		if ( ! $client ) {
			return new WP_Error(
				'cloud_runtime_unconfigured',
				__( 'Npcink Cloud is not configured.', 'npcink-cloud-addon' ),
				array( 'status' => 400 )
			);
		}

		return $client->request_image_context_evidence(
			$image_context_evidence_request,
			$trace_id,
			$idempotency_key
		);
	}
}

if ( ! function_exists( 'npcink_cloud_addon_execute_wordpress_ai_connector_runtime' ) ) {
	/**
	 * Executes a bounded WordPress AI connector scene request.
	 *
	 * This helper is for a future Npcink Cloud connector/provider seam. It is
	 * intentionally scenario-bound and does not expose generic chat sessions.
	 *
	 * @param array<string,mixed> $request WordPress AI connector request.
	 * @param string              $trace_id Optional trace id.
	 * @param string              $idempotency_key Optional idempotency key.
	 * @return array<string,mixed>|WP_Error
	 */
	function npcink_cloud_addon_execute_wordpress_ai_connector_runtime( array $request, string $trace_id = '', string $idempotency_key = '' ) {
		$client = Npcink_Cloud_Runtime_Client_Factory::configured();
		if ( ! $client ) {
			return new WP_Error(
				'cloud_runtime_unconfigured',
				__( 'Npcink Cloud is not configured.', 'npcink-cloud-addon' ),
				array( 'status' => 400 )
			);
		}

		return $client->execute_wordpress_ai_connector_runtime(
			$request,
			$trace_id,
			$idempotency_key
		);
	}
}

if ( ! function_exists( 'npcink_cloud_addon_project_ai_task_contract' ) ) {
	/**
	 * Returns the bounded runtime projection for one registered Ability.
	 *
	 * @param string $ability_name Registered WordPress Ability name.
	 * @return array<string,mixed>|WP_Error
	 */
	function npcink_cloud_addon_project_ai_task_contract( string $ability_name ) {
		return Npcink_Cloud_AI_Task_Contract::project_registered_ability( $ability_name );
	}
}

if ( ! function_exists( 'npcink_cloud_addon_execute_registered_ai_task_runtime' ) ) {
	/**
	 * Executes a suggestion-only runtime request for one registered Ability.
	 *
	 * This transports an Ability-owned task contract. It does not execute the
	 * Ability, bypass its permission callback, or perform a WordPress write.
	 *
	 * @param string              $ability_name Registered WordPress Ability name.
	 * @param array<string,mixed> $request Bounded scene request.
	 * @param string              $trace_id Optional trace id.
	 * @param string              $idempotency_key Optional idempotency key.
	 * @return array<string,mixed>|WP_Error
	 */
	function npcink_cloud_addon_execute_registered_ai_task_runtime( string $ability_name, array $request, string $trace_id = '', string $idempotency_key = '' ) {
		$task_contract = npcink_cloud_addon_project_ai_task_contract( $ability_name );
		if ( is_wp_error( $task_contract ) ) {
			return $task_contract;
		}

		$request['task_contract'] = $task_contract;
		$supports_generation_reference = in_array(
			(string) $task_contract['task'],
			array( 'title_generation', 'content_summary' ),
			true
		);
		if ( $supports_generation_reference && Npcink_Cloud_Addon_Settings::is_site_knowledge_generation_reference_enabled() ) {
			if ( ! isset( $request['site_knowledge_reference'] ) ) {
				$request['site_knowledge_reference'] = array( 'enabled' => true );
			}
		}

		return npcink_cloud_addon_execute_wordpress_ai_connector_runtime(
			array(
				'contract_version'   => 'cloud_connector_runtime.v1',
				'operation_contract' => array(
					'contract_version' => 'wordpress_operation.v1',
					'task'             => $task_contract['task'],
					'request'          => $request,
				),
				'timeout_seconds'    => 60,
				'retention_ttl'      => 86400,
				'retry_max'          => 0,
			),
			$trace_id,
			$idempotency_key
		);
	}
}

if ( ! function_exists( 'npcink_cloud_addon_execute_wordpress_ai_image_generation_runtime' ) ) {
	/**
	 * Executes a bounded WordPress AI image generation scene request.
	 *
	 * This helper is scenario-bound and does not expose a generic image
	 * provider proxy or model-control surface in the addon.
	 *
	 * @param array<string,mixed> $request WordPress AI image generation request.
	 * @param string              $trace_id Optional trace id.
	 * @param string              $idempotency_key Optional idempotency key.
	 * @return array<string,mixed>|WP_Error
	 */
	function npcink_cloud_addon_execute_wordpress_ai_image_generation_runtime( array $request, string $trace_id = '', string $idempotency_key = '' ) {
		$client = Npcink_Cloud_Runtime_Client_Factory::configured();
		if ( ! $client ) {
			return new WP_Error(
				'cloud_runtime_unconfigured',
				__( 'Npcink Cloud is not configured.', 'npcink-cloud-addon' ),
				array( 'status' => 400 )
			);
		}

		return $client->execute_wordpress_ai_image_generation_runtime(
			$request,
			$trace_id,
			$idempotency_key
		);
	}
}

if ( ! function_exists( 'npcink_cloud_addon_execute_toolbox_image_generation_runtime' ) ) {
	/**
	 * Executes a bounded Toolbox AI image generation runtime request.
	 *
	 * This helper is transport-only for Toolbox candidate generation. It does
	 * not expose provider routing, store candidates, import media, or write
	 * featured images.
	 *
	 * @param array<string,mixed> $request Toolbox image generation request.
	 * @param string              $trace_id Optional trace id.
	 * @param string              $idempotency_key Optional idempotency key.
	 * @return array<string,mixed>|WP_Error
	 */
	function npcink_cloud_addon_execute_toolbox_image_generation_runtime( array $request, string $trace_id = '', string $idempotency_key = '' ) {
		$client = Npcink_Cloud_Runtime_Client_Factory::configured();
		if ( ! $client ) {
			return new WP_Error(
				'cloud_runtime_unconfigured',
				__( 'Npcink Cloud is not configured.', 'npcink-cloud-addon' ),
				array( 'status' => 400 )
			);
		}

		return $client->execute_toolbox_image_generation_runtime(
			$request,
			$trace_id,
			$idempotency_key
		);
	}
}

if ( ! function_exists( 'npcink_cloud_addon_execute_toolbox_audio_generation_runtime' ) ) {
	/**
	 * Executes a bounded Toolbox article audio generation runtime request.
	 *
	 * This helper is transport-only for Toolbox audio candidates. It does not
	 * import media, write playback metadata, create adoption plans, or manage
	 * audio regeneration jobs.
	 *
	 * @param array<string,mixed> $request Toolbox audio generation request.
	 * @param string              $trace_id Optional trace id.
	 * @param string              $idempotency_key Optional idempotency key.
	 * @return array<string,mixed>|WP_Error
	 */
	function npcink_cloud_addon_execute_toolbox_audio_generation_runtime( array $request, string $trace_id = '', string $idempotency_key = '' ) {
		$client = Npcink_Cloud_Runtime_Client_Factory::configured();
		if ( ! $client ) {
			return new WP_Error(
				'cloud_runtime_unconfigured',
				__( 'Npcink Cloud is not configured.', 'npcink-cloud-addon' ),
				array( 'status' => 400 )
			);
		}

		return $client->execute_toolbox_audio_generation_runtime(
			$request,
			$trace_id,
			$idempotency_key
		);
	}
}

if ( ! function_exists( 'npcink_cloud_addon_execute_toolbox_site_ops_cloud_analysis_runtime' ) ) {
	/**
	 * Executes a bounded Toolbox Site Check Cloud analysis runtime request.
	 *
	 * This helper is transport-only for optional Site Check Cloud detail. It
	 * does not create local run state, Core proposals, scheduled jobs, or
	 * WordPress writes.
	 *
	 * @param array<string,mixed> $request Toolbox site_ops_cloud_analysis_request.v1 artifact.
	 * @param string              $trace_id Optional trace id.
	 * @param string              $idempotency_key Optional idempotency key.
	 * @return array<string,mixed>|WP_Error
	 */
	function npcink_cloud_addon_execute_toolbox_site_ops_cloud_analysis_runtime( array $request, string $trace_id = '', string $idempotency_key = '' ) {
		$client = Npcink_Cloud_Runtime_Client_Factory::configured();
		if ( ! $client ) {
			return new WP_Error(
				'cloud_runtime_unconfigured',
				__( 'Npcink Cloud is not configured.', 'npcink-cloud-addon' ),
				array( 'status' => 400 )
			);
		}

		return $client->execute_toolbox_site_ops_cloud_analysis_runtime(
			$request,
			$trace_id,
			$idempotency_key
		);
	}
}

if ( ! function_exists( 'npcink_cloud_addon_execute_toolbox_web_search_runtime' ) ) {
	/**
	 * Executes a bounded Toolbox managed web search runtime request.
	 *
	 * This helper is transport-only for Toolbox search evidence. It does not
	 * create local provider configuration, provider keys, proposals, or
	 * WordPress writes.
	 *
	 * @param array<string,mixed> $request Toolbox web_search.v1 request.
	 * @param string              $trace_id Optional trace id.
	 * @param string              $idempotency_key Optional idempotency key.
	 * @return array<string,mixed>|WP_Error
	 */
	function npcink_cloud_addon_execute_toolbox_web_search_runtime( array $request, string $trace_id = '', string $idempotency_key = '' ) {
		$client = Npcink_Cloud_Runtime_Client_Factory::configured();
		if ( ! $client ) {
			return new WP_Error(
				'cloud_runtime_unconfigured',
				__( 'Npcink Cloud is not configured.', 'npcink-cloud-addon' ),
				array( 'status' => 400 )
			);
		}

		return $client->execute_toolbox_web_search_runtime(
			$request,
			$trace_id,
			$idempotency_key
		);
	}
}

if ( ! function_exists( 'npcink_cloud_addon_execute_toolbox_image_source_runtime' ) ) {
	/**
	 * Executes a bounded Toolbox image-source candidate runtime request.
	 *
	 * This helper is transport-only for source candidates. It does not import
	 * media, set featured images, write attribution, create proposals, or own
	 * image-source provider configuration.
	 *
	 * @param array<string,mixed> $request Toolbox image_source_cloud_request.v1 request.
	 * @param string              $trace_id Optional trace id.
	 * @param string              $idempotency_key Optional idempotency key.
	 * @return array<string,mixed>|WP_Error
	 */
	function npcink_cloud_addon_execute_toolbox_image_source_runtime( array $request, string $trace_id = '', string $idempotency_key = '' ) {
		$client = Npcink_Cloud_Runtime_Client_Factory::configured();
		if ( ! $client ) {
			return new WP_Error(
				'cloud_runtime_unconfigured',
				__( 'Npcink Cloud is not configured.', 'npcink-cloud-addon' ),
				array( 'status' => 400 )
			);
		}

		return $client->execute_toolbox_image_source_runtime(
			$request,
			$trace_id,
			$idempotency_key
		);
	}
}

if ( ! function_exists( 'npcink_cloud_addon_dispatch_site_knowledge_runtime' ) ) {
	/**
	 * Dispatches a bounded Toolbox Site Knowledge runtime request.
	 *
	 * This helper validates the known Site Knowledge ability contracts and
	 * sends them through the existing signed runtime execute endpoint. It does
	 * not own indexing, stale-index policy, approval, or WordPress writes.
	 *
	 * @param array<string,mixed> $runtime_payload Runtime execute payload.
	 * @param string              $ability_name Optional ability name.
	 * @param string              $contract_version Optional contract version.
	 * @return array<string,mixed>|WP_Error
	 */
	function npcink_cloud_addon_dispatch_site_knowledge_runtime( array $runtime_payload, string $ability_name = '', string $contract_version = '' ) {
		return Npcink_Cloud_Site_Knowledge_Runtime_Bridge::dispatch_runtime(
			$runtime_payload,
			$ability_name,
			$contract_version
		);
	}
}

if ( ! function_exists( 'npcink_cloud_addon_execute_toolbox_content_support_runtime' ) ) {
	/**
	 * Executes the fixed Toolbox content-support runtime contract.
	 *
	 * @param array<string,mixed> $runtime_payload Exact Toolbox runtime payload.
	 * @param string              $trace_id Optional trace id.
	 * @param string              $idempotency_key Optional idempotency key.
	 * @return array<string,mixed>|WP_Error
	 */
	function npcink_cloud_addon_execute_toolbox_content_support_runtime( array $runtime_payload, string $trace_id = '', string $idempotency_key = '' ) {
		if (
			'npcink-toolbox/ai-content-support' !== (string) ( $runtime_payload['ability_name'] ?? '' )
			|| 'hosted_ai_content_support.v1' !== (string) ( $runtime_payload['contract_version'] ?? '' )
		) {
			return new WP_Error( 'cloud_toolbox_content_support_contract_invalid', __( 'Toolbox content support requires its fixed runtime contract.', 'npcink-cloud-addon' ), array( 'status' => 400 ) );
		}

		$client = Npcink_Cloud_Runtime_Client_Factory::configured();
		return $client ? $client->execute_runtime( $runtime_payload, $trace_id, $idempotency_key ) : new WP_Error( 'cloud_runtime_unconfigured', __( 'Npcink Cloud is not configured.', 'npcink-cloud-addon' ), array( 'status' => 400 ) );
	}
}

if ( ! function_exists( 'npcink_cloud_addon_execute_toolbox_site_helper_runtime' ) ) {
	/**
	 * Executes the fixed Toolbox site-helper runtime contract.
	 *
	 * @param array<string,mixed> $runtime_payload Exact Toolbox runtime payload.
	 * @param string              $trace_id Optional trace id.
	 * @param string              $idempotency_key Optional idempotency key.
	 * @return array<string,mixed>|WP_Error
	 */
	function npcink_cloud_addon_execute_toolbox_site_helper_runtime( array $runtime_payload, string $trace_id = '', string $idempotency_key = '' ) {
		if (
			'npcink-toolbox/ai-site-helper' !== (string) ( $runtime_payload['ability_name'] ?? '' )
			|| 'hosted_ai_site_helper.v1' !== (string) ( $runtime_payload['contract_version'] ?? '' )
		) {
			return new WP_Error( 'cloud_toolbox_site_helper_contract_invalid', __( 'Toolbox site helpers require their fixed runtime contract.', 'npcink-cloud-addon' ), array( 'status' => 400 ) );
		}

		$client = Npcink_Cloud_Runtime_Client_Factory::configured();
		return $client ? $client->execute_runtime( $runtime_payload, $trace_id, $idempotency_key ) : new WP_Error( 'cloud_runtime_unconfigured', __( 'Npcink Cloud is not configured.', 'npcink-cloud-addon' ), array( 'status' => 400 ) );
	}
}

if ( ! function_exists( 'npcink_cloud_addon_submit_toolbox_nightly_inspection' ) ) {
	/**
	 * Submits the fixed Toolbox Nightly Inspection runtime contract.
	 *
	 * @param array<string,mixed> $runtime_payload Exact Nightly Inspection payload.
	 * @param string              $trace_id Optional trace id.
	 * @param string              $idempotency_key Optional idempotency key.
	 * @return array<string,mixed>|WP_Error
	 */
	function npcink_cloud_addon_submit_toolbox_nightly_inspection( array $runtime_payload, string $trace_id = '', string $idempotency_key = '' ) {
		if (
			'npcink-toolbox/analyze-nightly-content-batch' !== (string) ( $runtime_payload['ability_name'] ?? '' )
			|| 'cloud_batch_runtime_request.v1' !== (string) ( $runtime_payload['contract_version'] ?? '' )
		) {
			return new WP_Error( 'cloud_toolbox_nightly_inspection_contract_invalid', __( 'Nightly Inspection requires its fixed runtime contract.', 'npcink-cloud-addon' ), array( 'status' => 400 ) );
		}

		$client = Npcink_Cloud_Runtime_Client_Factory::configured();
		return $client ? $client->execute_runtime( $runtime_payload, $trace_id, $idempotency_key ) : new WP_Error( 'cloud_runtime_unconfigured', __( 'Npcink Cloud is not configured.', 'npcink-cloud-addon' ), array( 'status' => 400 ) );
	}
}

if ( ! function_exists( 'npcink_cloud_addon_get_toolbox_nightly_inspection_recent_runs' ) ) {
	/** @return array<string,mixed>|WP_Error */
	function npcink_cloud_addon_get_toolbox_nightly_inspection_recent_runs( int $limit = 10, string $trace_id = '' ) {
		$client = Npcink_Cloud_Runtime_Client_Factory::configured();
		return $client ? $client->get_recent_nightly_inspection_runs( $limit, $trace_id ) : new WP_Error( 'cloud_runtime_unconfigured', __( 'Npcink Cloud is not configured.', 'npcink-cloud-addon' ), array( 'status' => 400 ) );
	}
}

if ( ! function_exists( 'npcink_cloud_addon_get_toolbox_runtime_run' ) ) {
	/** @return array<string,mixed>|WP_Error */
	function npcink_cloud_addon_get_toolbox_runtime_run( string $run_id, string $trace_id = '' ) {
		$client = Npcink_Cloud_Runtime_Client_Factory::configured();
		return $client ? $client->get_run( $run_id, $trace_id ) : new WP_Error( 'cloud_runtime_unconfigured', __( 'Npcink Cloud is not configured.', 'npcink-cloud-addon' ), array( 'status' => 400 ) );
	}
}

if ( ! function_exists( 'npcink_cloud_addon_get_toolbox_runtime_run_result' ) ) {
	/** @return array<string,mixed>|WP_Error */
	function npcink_cloud_addon_get_toolbox_runtime_run_result( string $run_id, string $trace_id = '' ) {
		$client = Npcink_Cloud_Runtime_Client_Factory::configured();
		return $client ? $client->get_run_result( $run_id, $trace_id ) : new WP_Error( 'cloud_runtime_unconfigured', __( 'Npcink Cloud is not configured.', 'npcink-cloud-addon' ), array( 'status' => 400 ) );
	}
}

if ( ! function_exists( 'npcink_cloud_addon_retry_toolbox_nightly_inspection' ) ) {
	/** @param array<string,mixed> $input @return array<string,mixed>|WP_Error */
	function npcink_cloud_addon_retry_toolbox_nightly_inspection( string $run_id, array $input, string $trace_id = '', string $idempotency_key = '' ) {
		$client = Npcink_Cloud_Runtime_Client_Factory::configured();
		return $client ? $client->retry_run( $run_id, $input, $trace_id, $idempotency_key ) : new WP_Error( 'cloud_runtime_unconfigured', __( 'Npcink Cloud is not configured.', 'npcink-cloud-addon' ), array( 'status' => 400 ) );
	}
}

if ( ! function_exists( 'npcink_cloud_addon_get_toolbox_runtime_entitlement' ) ) {
	/** @return array<string,mixed>|WP_Error */
	function npcink_cloud_addon_get_toolbox_runtime_entitlement( string $trace_id = '' ) {
		$client = Npcink_Cloud_Runtime_Client_Factory::configured();
		return $client ? $client->get_current_entitlement( $trace_id ) : new WP_Error( 'cloud_runtime_unconfigured', __( 'Npcink Cloud is not configured.', 'npcink-cloud-addon' ), array( 'status' => 400 ) );
	}
}

if ( ! function_exists( 'npcink_cloud_addon_upload_toolbox_site_media_visual_source' ) ) {
	/** @param array<string,mixed> $file @return array<string,mixed>|WP_Error */
	function npcink_cloud_addon_upload_toolbox_site_media_visual_source( array $file, string $trace_id = '', string $idempotency_key = '' ) {
		$client = Npcink_Cloud_Runtime_Client_Factory::configured();
		return $client ? $client->upload_media_artifact( $file, $trace_id, $idempotency_key ) : new WP_Error( 'cloud_runtime_unconfigured', __( 'Npcink Cloud is not configured.', 'npcink-cloud-addon' ), array( 'status' => 400 ) );
	}
}

if ( ! function_exists( 'npcink_cloud_addon_send_agent_feedback_event' ) ) {
	/** @param array<string,mixed> $payload @return array<string,mixed>|WP_Error */
	function npcink_cloud_addon_send_agent_feedback_event( array $payload, string $trace_id = '', string $idempotency_key = '' ) {
		$client = Npcink_Cloud_Runtime_Client_Factory::configured();
		return $client ? $client->send_agent_feedback_event( $payload, $trace_id, $idempotency_key ) : new WP_Error( 'cloud_runtime_unconfigured', __( 'Npcink Cloud is not configured.', 'npcink-cloud-addon' ), array( 'status' => 400 ) );
	}
}

if ( ! function_exists( 'npcink_cloud_addon_get_agent_feedback_summary' ) ) {
	/** @return array<string,mixed>|WP_Error */
	function npcink_cloud_addon_get_agent_feedback_summary( int $window_hours = 24, string $trace_id = '' ) {
		$client = Npcink_Cloud_Runtime_Client_Factory::configured();
		return $client ? $client->get_agent_feedback_summary( $window_hours, $trace_id ) : new WP_Error( 'cloud_runtime_unconfigured', __( 'Npcink Cloud is not configured.', 'npcink-cloud-addon' ), array( 'status' => 400 ) );
	}
}

if ( ! function_exists( 'npcink_cloud_addon_build_media_derivative_proposal_payload' ) ) {
	/**
	 * Builds a Core-ready local proposal payload for a Cloud derivative artifact.
	 *
	 * This does not store a proposal, approve anything, or write WordPress media.
	 *
	 * @param array<string,mixed> $ability_response Ability response envelope.
	 * @param array<string,mixed> $cloud_result Cloud run result envelope.
	 * @param array<string,mixed> $derivative_artifact Cloud derivative artifact descriptor.
	 * @return array<string,mixed>|WP_Error
	 */
	function npcink_cloud_addon_build_media_derivative_proposal_payload( array $ability_response, array $cloud_result, array $derivative_artifact ) {
		return Npcink_Cloud_Media_Derivative_Transport::build_local_proposal_payload(
			$ability_response,
			$cloud_result,
			$derivative_artifact
		);
	}
}

if ( ! function_exists( 'npcink_cloud_addon_get_media_derivative_run' ) ) {
	/**
	 * Reads one Cloud media derivative run projection.
	 *
	 * @param string $run_id Cloud run id.
	 * @param string $trace_id Optional trace id.
	 * @return array<string,mixed>|WP_Error
	 */
	function npcink_cloud_addon_get_media_derivative_run( string $run_id, string $trace_id = '' ) {
		return Npcink_Cloud_Media_Derivative_Transport::get_run_projection( $run_id, $trace_id );
	}
}

if ( ! function_exists( 'npcink_cloud_addon_get_media_derivative_run_result' ) ) {
	/**
	 * Reads one Cloud media derivative run result projection.
	 *
	 * @param string $run_id Cloud run id.
	 * @param string $trace_id Optional trace id.
	 * @return array<string,mixed>|WP_Error
	 */
	function npcink_cloud_addon_get_media_derivative_run_result( string $run_id, string $trace_id = '' ) {
		return Npcink_Cloud_Media_Derivative_Transport::get_run_result_projection( $run_id, $trace_id );
	}
}

if ( ! function_exists( 'npcink_cloud_addon_media_derivative_run_id' ) ) {
	/**
	 * Extracts a media derivative run id from the exact local public projection.
	 *
	 * @param array<string,mixed> $cloud_response Cloud response.
	 * @return string
	 */
	function npcink_cloud_addon_media_derivative_run_id( array $cloud_response ): string {
		return Npcink_Cloud_Media_Derivative_Transport::run_id( $cloud_response );
	}
}

if ( ! function_exists( 'npcink_cloud_addon_public_media_derivative_cloud_projection' ) ) {
	/**
	 * Returns a bounded media derivative Cloud status projection.
	 *
	 * @param array<string,mixed> $cloud_response Cloud response.
	 * @return array<string,mixed>|WP_Error
	 */
	function npcink_cloud_addon_public_media_derivative_cloud_projection( array $cloud_response ) {
		return Npcink_Cloud_Media_Derivative_Transport::public_cloud_projection( $cloud_response );
	}
}

if ( ! function_exists( 'npcink_cloud_addon_media_derivative_artifact_from_cloud_result' ) ) {
	/**
	 * Extracts a derivative artifact descriptor from a Cloud result payload.
	 *
	 * @param array<string,mixed> $cloud_result Cloud result.
	 * @return array<string,mixed>|WP_Error
	 */
	function npcink_cloud_addon_media_derivative_artifact_from_cloud_result( array $cloud_result ) {
		return Npcink_Cloud_Media_Derivative_Transport::artifact_from_cloud_result( $cloud_result );
	}
}

if ( ! function_exists( 'npcink_cloud_addon_build_media_derivative_optimization_payload' ) ) {
	/**
	 * Builds a Core from-plan media optimization payload from a Cloud derivative artifact.
	 *
	 * This does not store a proposal, approve anything, or write WordPress media.
	 *
	 * @param array<string,mixed> $ability_response Ability response envelope.
	 * @param array<string,mixed> $cloud_result Cloud run result envelope.
	 * @param array<string,mixed> $derivative_artifact Cloud derivative artifact descriptor.
	 * @param array<string,mixed> $media_details_input Reviewed media metadata input.
	 * @return array<string,mixed>|WP_Error
	 */
	function npcink_cloud_addon_build_media_derivative_optimization_payload( array $ability_response, array $cloud_result, array $derivative_artifact, array $media_details_input ) {
		return Npcink_Cloud_Media_Derivative_Transport::build_media_optimization_payload(
			$ability_response,
			$cloud_result,
			$derivative_artifact,
			$media_details_input
		);
	}
}

if ( ! function_exists( 'npcink_cloud_addon_receive_media_derivative_artifact' ) ) {
	/**
	 * Receives and verifies one short-TTL derivative artifact, then ACKs transfer.
	 *
	 * This does not store, register, approve, adopt, or write the artifact.
	 *
	 * @param array<string,mixed> $derivative_artifact Cloud derivative artifact descriptor.
	 * @param string              $trace_id Optional trace id.
	 * @return array<string,mixed>|WP_Error
	 */
	function npcink_cloud_addon_receive_media_derivative_artifact( array $artifact, string $trace_id = '' ) {
		return Npcink_Cloud_Media_Derivative_Transport::receive_artifact(
			$artifact,
			$trace_id
		);
	}
}

if ( ! function_exists( 'npcink_cloud_addon_site_knowledge_change_bridge_health' ) ) {
	/**
	 * Returns local Site Knowledge change bridge health for host plugins.
	 *
	 * The bridge only reports and transports public content change hints to
	 * Cloud Site Knowledge. Consumers should treat the returned
	 * `site_knowledge_change_bridge_status.v1` payload as the
	 * `change_bridge` status projection and prefer `buffer_count` for bounded
	 * delivery-buffer depth. It is not queue truth or local index lifecycle
	 * ownership.
	 *
	 * @return array<string,mixed>
	 */
	function npcink_cloud_addon_site_knowledge_change_bridge_health(): array {
		return Npcink_Cloud_Site_Knowledge_Change_Bridge::health_snapshot();
	}
}

if ( ! function_exists( 'npcink_cloud_addon_bootstrap' ) ) {
	/**
	 * Boots the standalone Cloud addon.
	 *
	 * @return void
	 */
	function npcink_cloud_addon_bootstrap(): void {
		Npcink_Cloud_Addon_Settings::register();
		Npcink_Cloud_Observability_Collector::register();
		Npcink_Cloud_Customer_Journey::register();
		Npcink_Cloud_Editor_Assist_Quality::register();
		Npcink_Cloud_Site_Knowledge_Change_Bridge::register();
		Npcink_Cloud_Site_Knowledge_Runtime_Bridge::register();
		Npcink_Cloud_Addon_Localization::register();
		Npcink_Cloud_AI_Plugin_Localization::register();
		Npcink_Cloud_WordPress_AI_Connector::register();
		Npcink_Cloud_Settings_Page::register();
	}
}

if ( ! function_exists( 'npcink_cloud_addon_filter_plugin_action_links' ) ) {
	/**
	 * Adds a settings shortcut on the WordPress plugins screen.
	 *
	 * @param array<int|string,string> $links Existing plugin action links.
	 * @return array<int|string,string>
	 */
	function npcink_cloud_addon_filter_plugin_action_links( array $links ): array {
		$settings_url = menu_page_url( 'npcink-cloud-addon', false );
		if ( '' === $settings_url ) {
			$settings_url = admin_url( 'options-general.php?page=npcink-cloud-addon' );
		}

		array_unshift(
			$links,
			sprintf(
				'<a href="%1$s">%2$s</a>',
				esc_url( $settings_url ),
				esc_html__( 'Settings', 'npcink-cloud-addon' )
			)
		);

		return $links;
	}
}

add_action( 'plugins_loaded', 'npcink_cloud_addon_bootstrap', 20 );
add_filter( 'plugin_action_links_' . plugin_basename( NPCINK_CLOUD_ADDON_FILE ), 'npcink_cloud_addon_filter_plugin_action_links' );
