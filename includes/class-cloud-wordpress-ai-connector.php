<?php
/**
 * WordPress AI connector registration for the Cloud addon.
 *
 * @package NpcinkCloudAddon
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Npcink_Cloud_WordPress_AI_Connector' ) ) {
	/**
	 * Projects verified Cloud settings into the WordPress Connectors / AI Client surface.
	 */
	final class Npcink_Cloud_WordPress_AI_Connector {
		public const CONNECTOR_ID = 'npcink-cloud';
		public const CONNECTOR_NAME = 'Npcink Cloud';
		public const MODEL_ID = 'npcink-cloud-scene-text';
		public const VISION_MODEL_ID = 'npcink-cloud-scene-vision';
		public const IMAGE_MODEL_ID = 'npcink-cloud-scene-image';
		public const SETTING_NAME = 'npcink_cloud_addon_wp_ai_connector_connected';

		/**
		 * Current validated WordPress AI alt-text ability input.
		 *
		 * @var array<string,mixed>
		 */
		private static $alt_text_ability_context = array();

		/**
		 * Current validated WordPress AI text ability context.
		 *
		 * @var array<string,mixed>
		 */
		private static $text_ability_context = array();

		/**
		 * Cloud run ID returned by the current text runtime call, when present.
		 *
		 * This request-scoped value is development evidence only. It is never
		 * added to an official WordPress Ability result.
		 *
		 * @var string
		 */
		private static $last_cloud_run_id = '';

		/**
		 * Payload-free evidence for the last failed Cloud runtime call.
		 *
		 * This is consumed only by development acceptance tooling. It is never
		 * projected into an official WordPress AI result or user-facing message.
		 *
		 * @var array<string,mixed>
		 */
		private static $last_runtime_failure_evidence = array();

		/**
		 * Registers hooks.
		 *
		 * @return void
		 */
		public static function register(): void {
			add_action( 'init', array( __CLASS__, 'register_ai_provider' ), 5 );
			add_action( 'wp_connectors_init', array( __CLASS__, 'register_connector' ) );
			add_action( 'admin_init', array( __CLASS__, 'sync_connected_marker' ) );
			add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_connectors_page_assets' ) );
			add_filter( 'wpai_has_ai_credentials', array( __CLASS__, 'filter_has_ai_credentials' ), 100, 2 );
			add_filter( 'wpai_preferred_text_models', array( __CLASS__, 'filter_preferred_text_models' ) );
			add_filter( 'wpai_preferred_vision_models', array( __CLASS__, 'filter_preferred_vision_models' ) );
			add_filter( 'wpai_preferred_image_models', array( __CLASS__, 'filter_preferred_image_models' ) );
			add_filter( 'wpai_generated_image_filename', array( __CLASS__, 'filter_generated_image_filename' ), 10, 2 );
			add_filter( 'wp_get_abilities_result', array( __CLASS__, 'prioritize_wordpress_ai_abilities_for_rest_list' ), 20, 2 );
			add_action( 'wp_before_execute_ability', array( __CLASS__, 'begin_wordpress_ai_ability_context' ), 10, 3 );
			add_action( 'wp_after_execute_ability', array( __CLASS__, 'end_wordpress_ai_ability_context' ), 10, 4 );
			add_action( 'shutdown', array( __CLASS__, 'reset_wordpress_ai_ability_context' ), 1 );
		}

		/**
		 * Normalizes WordPress AI image-import filenames to the filter's basename contract.
		 *
		 * WordPress AI appends the MIME-derived extension after this filter. Some
		 * callers pass a complete filename, which would otherwise produce names such
		 * as `example.png.png`.
		 *
		 * @param string              $filename Proposed image filename or basename.
		 * @param array<string,mixed> $args Image import arguments.
		 * @return string Filename without a supported image extension.
		 */
		public static function filter_generated_image_filename( string $filename, array $args = array() ): string {
			unset( $args );
			$filename = trim( $filename );
			$basename = preg_replace( '/\.(?:png|jpe?g|webp)$/i', '', $filename );

			if ( ! is_string( $basename ) || '' === trim( $basename ) ) {
				return 'ai-generated-image';
			}

			return $basename;
		}

		/**
		 * Captures the validated input for the one supported WordPress AI vision ability.
		 *
		 * WordPress fires this hook after input validation and permission checks and
		 * immediately before the registered ability callback.
		 *
		 * @param string $ability_name Ability name.
		 * @param mixed  $input Validated ability input.
		 * @param mixed  $ability Optional ability object on WordPress 7.1+.
		 * @return void
		 */
		public static function begin_wordpress_ai_ability_context( string $ability_name, $input, $ability = null ): void {
			unset( $ability );
			// Title and summary abilities invoke nested guideline abilities.
			// Preserve the outer editor context until its own after-execute hook.
			if ( 'ai/alt-text-generation' === $ability_name && is_array( $input ) ) {
				self::$alt_text_ability_context = $input;
			}
			if ( is_array( $input ) && self::is_text_scene_ability( $ability_name ) ) {
				self::$text_ability_context = array(
					'ability_id' => $ability_name,
					'input'      => $input,
				);
			}
		}

		/**
		 * Clears the one-request ability context after successful execution.
		 *
		 * @param string $ability_name Ability name.
		 * @param mixed  $input Ability input.
		 * @param mixed  $result Ability result.
		 * @param mixed  $ability Optional ability object on WordPress 7.1+.
		 * @return void
		 */
		public static function end_wordpress_ai_ability_context( string $ability_name, $input, $result, $ability = null ): void {
			unset( $input, $result, $ability );
			if ( 'ai/alt-text-generation' === $ability_name ) {
				self::$alt_text_ability_context = array();
			}
			if ( self::is_text_scene_ability( $ability_name ) && $ability_name === (string) ( self::$text_ability_context['ability_id'] ?? '' ) ) {
				self::$text_ability_context = array();
			}
		}

		/**
		 * Reports whether the current call is the supported vision ability.
		 *
		 * @return bool
		 */
		public static function has_alt_text_ability_context(): bool {
			return array() !== self::$alt_text_ability_context;
		}

		/**
		 * Returns and clears the current alt-text ability input once.
		 *
		 * @return array<string,mixed>
		 */
		public static function consume_alt_text_ability_context(): array {
			$context = self::$alt_text_ability_context;
			self::$alt_text_ability_context = array();

			return $context;
		}

		/**
		 * Clears any stale ability context.
		 *
		 * @return void
		 */
		public static function reset_wordpress_ai_ability_context(): void {
			self::$alt_text_ability_context = array();
			self::$text_ability_context = array();
			self::$last_cloud_run_id         = '';
			self::reset_runtime_failure_evidence();
		}

		/**
		 * Returns the actual Cloud run ID from the current request, if available.
		 *
		 * @return string
		 */
		public static function current_cloud_run_id(): string {
			return self::$last_cloud_run_id;
		}

		/**
		 * Records an actual Cloud run ID for same-request acceptance evidence.
		 *
		 * @param string $run_id Cloud-provided run ID.
		 * @return void
		 */
		public static function record_cloud_run_id( string $run_id ): void {
			self::$last_cloud_run_id = sanitize_text_field( $run_id );
		}

		/**
		 * Clears the request-scoped runtime failure evidence.
		 *
		 * @return void
		 */
		public static function reset_runtime_failure_evidence(): void {
			self::$last_runtime_failure_evidence = array();
		}

		/**
		 * Records bounded, payload-free evidence for the last failed runtime call.
		 *
		 * @param array<string,mixed> $evidence Failure evidence fields.
		 * @return void
		 */
		public static function record_runtime_failure_evidence( array $evidence ): void {
			$fields = array(
				'run_id'           => self::normalize_runtime_failure_field( $evidence['run_id'] ?? '' ),
				'cloud_error_code' => self::normalize_runtime_failure_field( $evidence['cloud_error_code'] ?? '' ),
				'error_stage'      => self::normalize_runtime_failure_field( $evidence['error_stage'] ?? '' ),
				'quality_reason'   => self::normalize_runtime_failure_field( $evidence['quality_reason'] ?? '' ),
			);
			if ( '' === implode( '', $fields ) ) {
				return;
			}
			self::$last_runtime_failure_evidence = $fields;
		}

		/**
		 * Records bounded evidence from a Cloud WP_Error and returns the normalized fields.
		 *
		 * @param WP_Error $error Cloud runtime error.
		 * @return array<string,string>
		 */
		public static function record_runtime_failure_from_wp_error( WP_Error $error ): array {
			$error_data        = $error->get_error_data();
			$error_data        = is_array( $error_data ) ? $error_data : array();
			$cloud_error_data  = is_array( $error_data['cloud_error_data'] ?? null ) ? $error_data['cloud_error_data'] : array();
			$run_state         = is_array( $cloud_error_data['run_state'] ?? null ) ? $cloud_error_data['run_state'] : array();
			$run_state_error   = is_array( $run_state['error'] ?? null ) ? $run_state['error'] : array();
			$cloud_run_id = self::first_response_string(
				$cloud_error_data,
				array( 'run_id', 'data.run_id', 'data.result.run_id', 'result.run_id' ),
				''
			);
			$cloud_error_stage = self::normalize_runtime_failure_field( $cloud_error_data['error_stage'] ?? '' );
			$run_state_stage   = self::normalize_runtime_failure_field( $run_state_error['error_stage'] ?? '' );
			$evidence = array(
				'run_id'           => self::normalize_runtime_failure_field( $cloud_run_id ),
				'cloud_error_code' => self::normalize_runtime_failure_field( $error_data['cloud_error_code'] ?? '' ),
				'error_stage'      => '' !== $cloud_error_stage ? $cloud_error_stage : $run_state_stage,
				'quality_reason'   => self::normalize_runtime_failure_field( $cloud_error_data['quality_reason'] ?? ( $run_state_error['quality_reason'] ?? '' ) ),
			);
			$local_error_code = sanitize_key( (string) $error->get_error_code() );
			if ( '' === implode( '', $evidence ) ) {
				$evidence['error_stage'] = '' !== $local_error_code ? 'local_' . $local_error_code : 'transport';
				$evidence['synthetic_error_stage'] = '1';
			}
			self::record_cloud_run_id( $evidence['run_id'] );
			self::record_runtime_failure_evidence( $evidence );
			return $evidence;
		}

		/**
		 * Normalizes one bounded diagnostic field without losing dotted Cloud codes.
		 *
		 * @param mixed $value Raw diagnostic field.
		 * @return string
		 */
		private static function normalize_runtime_failure_field( $value ): string {
			$value = is_scalar( $value ) ? (string) $value : '';
			if ( function_exists( 'wp_check_invalid_utf8' ) ) {
				$value = wp_check_invalid_utf8( $value );
			} elseif ( function_exists( 'mb_convert_encoding' ) ) {
				$converted = mb_convert_encoding( $value, 'UTF-8', 'UTF-8' );
				$value     = is_string( $converted ) ? $converted : '';
			} elseif ( function_exists( 'iconv' ) ) {
				$converted = iconv( 'UTF-8', 'UTF-8//IGNORE', $value );
				$value     = is_string( $converted ) ? $converted : '';
			}
			$value = sanitize_text_field( $value );
			$filtered = preg_replace( '/[^\p{L}\p{N}\p{P}\p{S}\s]+/u', ' ', $value );
			$filtered = is_string( $filtered ) ? $filtered : '';
			$collapsed = preg_replace( '/\s+/', ' ', $filtered );
			$value = is_string( $collapsed ) ? $collapsed : $filtered;
			$value = trim( $value );
			if ( function_exists( 'mb_substr' ) ) {
				return mb_substr( $value, 0, 120, 'UTF-8' );
			}
			$truncated = preg_replace( '/^(.{0,120}).*/us', '$1', $value );
			return is_string( $truncated ) ? $truncated : substr( $value, 0, 120 );
		}

		/**
		 * Returns bounded, payload-free evidence for the last failed runtime call.
		 *
		 * @return array<string,mixed>
		 */
		public static function current_runtime_failure_evidence(): array {
			return self::$last_runtime_failure_evidence;
		}

		/**
		 * Extracts a Cloud run ID from every supported runtime response envelope.
		 *
		 * @param mixed $response Runtime response.
		 * @return string
		 */
		public static function cloud_run_id_from_response( $response ): string {
			return self::first_response_string(
				is_array( $response ) ? $response : array(),
				array( 'run_id', 'data.run_id', 'data.result.run_id', 'result.run_id' ),
				''
			);
		}

		/**
		 * Returns the current validated text ability context without consuming it.
		 *
		 * @return array<string,mixed>
		 */
		public static function current_text_ability_context(): array {
			return self::$text_ability_context;
		}

		/**
		 * Registers the optional PHP AI Client provider when the AI Client is loaded.
		 *
		 * @return void
		 */
		public static function register_ai_provider(): void {
			if ( ! self::is_cloud_connector_available() ) {
				return;
			}

			if ( ! class_exists( 'WordPress\\AiClient\\AiClient' ) || ! class_exists( 'Npcink_Cloud_WordPress_AI_Provider' ) ) {
				return;
			}

			$registry = \WordPress\AiClient\AiClient::defaultRegistry();
			if ( $registry->hasProvider( self::CONNECTOR_ID ) || $registry->hasProvider( 'Npcink_Cloud_WordPress_AI_Provider' ) ) {
				return;
			}

			$registry->registerProvider( 'Npcink_Cloud_WordPress_AI_Provider' );
		}

		/**
		 * Registers the fixed Npcink Cloud connector card.
		 *
		 * @param object $registry WordPress connector registry.
		 * @return void
		 */
		public static function register_connector( $registry ): void {
			self::register_marker_setting();
			self::sync_connected_marker();

			if ( ! is_object( $registry ) || ! method_exists( $registry, 'register' ) ) {
				return;
			}

			if ( method_exists( $registry, 'is_registered' ) && $registry->is_registered( self::CONNECTOR_ID ) ) {
				if ( method_exists( $registry, 'unregister' ) ) {
					$registry->unregister( self::CONNECTOR_ID );
				} else {
					return;
				}
			}

			if ( ! self::is_cloud_connector_available() ) {
				return;
			}

			$registry->register(
				self::CONNECTOR_ID,
				array(
					'name'           => self::CONNECTOR_NAME,
					'description'    => __( 'Use verified Npcink Cloud settings for bounded WordPress AI scene tasks.', 'npcink-cloud-addon' ),
					'type'           => 'ai_provider',
					'authentication' => array(
						'method'       => 'api_key',
						'setting_name' => self::SETTING_NAME,
					),
					'plugin'         => array(
						'file'      => self::connector_plugin_file(),
						'is_active' => '__return_true',
					),
				)
			);
		}

		/**
		 * Keeps the WordPress Connectors card status-only for this fixed Cloud connector.
		 *
		 * @param string $hook_suffix Admin hook suffix.
		 * @return void
		 */
		public static function enqueue_connectors_page_assets( string $hook_suffix ): void {
			if ( 'options-connectors' !== $hook_suffix ) {
				return;
			}

			wp_enqueue_style(
				'npcink-cloud-addon-admin',
				plugins_url( 'assets/admin.css', NPCINK_CLOUD_ADDON_FILE ),
				array(),
				NPCINK_CLOUD_ADDON_VERSION
			);
		}

		/**
		 * Keeps the synthetic connector credential marker aligned with Cloud verification.
		 *
		 * @return void
		 */
		public static function sync_connected_marker(): void {
			if ( self::is_cloud_connector_available() ) {
				update_option( self::SETTING_NAME, '1', false );
				return;
			}

			delete_option( self::SETTING_NAME );
		}

		/**
		 * Lets the AI plugin see verified Cloud settings as available credentials.
		 *
		 * @param bool                 $has_credentials Existing credential state.
		 * @param array<string,mixed>  $connectors Registered connectors.
		 *
		 * The AI plugin may query credentials from an incomplete connector snapshot
		 * during bootstrap, so verified Cloud settings remain the availability source
		 * while connector registration supplies the card metadata separately.
		 * @return bool
		 */
		public static function filter_has_ai_credentials( bool $has_credentials, array $connectors ): bool {
			unset( $connectors );
			if ( $has_credentials ) {
				return true;
			}

			return self::is_cloud_connector_available();
		}

		/**
		 * Makes the scene-bound Cloud model the first preference when available.
		 *
		 * @param array<int,mixed> $preferred_models Existing preferred model list.
		 * @return array<int,mixed>
		 */
		public static function filter_preferred_text_models( array $preferred_models ): array {
			if ( ! self::is_model_available( self::MODEL_ID ) ) {
				return $preferred_models;
			}

			array_unshift( $preferred_models, array( self::CONNECTOR_ID, self::MODEL_ID ) );

			return $preferred_models;
		}

		/**
		 * Makes the scene-bound Cloud vision model the first preference when available.
		 *
		 * @param array<int,mixed> $preferred_models Existing preferred model list.
		 * @return array<int,mixed>
		 */
		public static function filter_preferred_vision_models( array $preferred_models ): array {
			if ( ! self::is_model_available( self::VISION_MODEL_ID ) ) {
				return $preferred_models;
			}

			array_unshift( $preferred_models, array( self::CONNECTOR_ID, self::VISION_MODEL_ID ) );

			return $preferred_models;
		}

		/**
		 * Makes the scene-bound Cloud image model the first preference when available.
		 *
		 * @param array<int,mixed> $preferred_models Existing preferred model list.
		 * @return array<int,mixed>
		 */
		public static function filter_preferred_image_models( array $preferred_models ): array {
			if ( ! self::is_model_available( self::IMAGE_MODEL_ID ) ) {
				return $preferred_models;
			}

			array_unshift( $preferred_models, array( self::CONNECTOR_ID, self::IMAGE_MODEL_ID ) );

			return $preferred_models;
		}

		/**
		 * Maps fresh Cloud configuration evidence to this connector's fixed models.
		 *
		 * @param string $model_id Fixed connector model ID.
		 * @return bool
		 */
		public static function is_model_available( string $model_id ): bool {
			if ( ! self::is_cloud_connector_available() || ! class_exists( 'Npcink_Cloud_Entitlement_Summary' ) ) {
				return false;
			}
			$mapping = array( self::MODEL_ID => 'text_generation', self::VISION_MODEL_ID => 'vision', self::IMAGE_MODEL_ID => 'image_generation' );
			if ( ! isset( $mapping[ $model_id ] ) ) {
				return false;
			}
			$snapshot = Npcink_Cloud_Entitlement_Summary::get_wordpress_ai_capabilities( true );
			return 'configured' === ( $snapshot['capabilities'][ $mapping[ $model_id ] ]['state'] ?? 'unknown' );
		}

		/**
		 * Keeps WordPress AI abilities discoverable on the default Abilities REST list.
		 *
		 * The AI plugin may use the unfiltered REST list as a client-side discovery
		 * cache. Busy local sites can exceed the first page before ai/* abilities are
		 * reached, which makes clients report "Ability not found" and fall back even
		 * though the individual ability endpoint and run callback work.
		 *
		 * @param array<string,mixed> $abilities Matched abilities keyed by ability name.
		 * @param array<string,mixed> $args      Query arguments passed to wp_get_abilities().
		 * @return array<string,mixed>
		 */
		public static function prioritize_wordpress_ai_abilities_for_rest_list( array $abilities, array $args ): array {
			if ( ! self::is_cloud_connector_available() || empty( $abilities ) ) {
				return $abilities;
			}

			if ( ! empty( $args['namespace'] ) || ! empty( $args['category'] ) ) {
				return $abilities;
			}

			$meta = isset( $args['meta'] ) && is_array( $args['meta'] ) ? $args['meta'] : array();
			if ( true !== ( $meta['show_in_rest'] ?? null ) ) {
				return $abilities;
			}

			$wordpress_ai = array();
			$others       = array();

			foreach ( $abilities as $key => $ability ) {
				$name = is_object( $ability ) && method_exists( $ability, 'get_name' )
					? (string) $ability->get_name()
					: (string) $key;

				if ( str_starts_with( $name, 'ai/' ) ) {
					$wordpress_ai[ $key ] = $ability;
					continue;
				}

				$others[ $key ] = $ability;
			}

			if ( empty( $wordpress_ai ) ) {
				return $abilities;
			}

			return $wordpress_ai + $others;
		}

		/**
		 * Writes metadata-only Cloud run evidence into the optional AI request log.
		 *
		 * @param array<string,mixed> $event Runtime event metadata.
		 * @return void
		 */
		public static function maybe_log_wordpress_ai_request_evidence( array $event ): void {
			if ( ! self::is_wordpress_ai_request_logging_enabled() ) {
				return;
			}

			$manager_class = 'WordPress\\AI\\Logging\\AI_Request_Log_Manager';
			if ( ! class_exists( $manager_class ) ) {
				return;
			}

			$response = $event['response'] ?? null;
			$error = $event['validation_error'] ?? $response;
			$is_error = is_wp_error( $error );
			$response_array = is_array( $response ) ? $response : array();
			$task     = self::clean_log_value( (string) ( $event['task'] ?? 'unknown' ), 80 );
			$type     = self::clean_log_value( (string) ( $event['type'] ?? 'text' ), 20 );
			$provider = self::first_response_string(
				$response_array,
				array(
					'provider',
					'provider_id',
					'selected_provider',
					'data.provider',
					'data.provider_id',
					'data.selected_provider',
					'data.result.provider',
					'data.result.provider_id',
					'data.result.provider_metadata.id',
					'result.provider',
					'result.provider_id',
					'result.provider_metadata.id',
				),
				self::CONNECTOR_ID
			);
			$model    = self::first_response_string(
				$response_array,
				array(
					'model',
					'model_id',
					'selected_model',
					'data.model',
					'data.model_id',
					'data.selected_model',
					'data.result.model',
					'data.result.model_id',
					'data.result.model_metadata.id',
					'result.model',
					'result.model_id',
					'result.model_metadata.id',
				),
				(string) ( $event['fallback_model_id'] ?? self::MODEL_ID )
			);
			$run_id   = self::first_response_string(
				$response_array,
				array( 'run_id', 'data.run_id', 'data.result.run_id', 'result.run_id' ),
				(string) ( $event['cloud_run_id'] ?? self::current_cloud_run_id() )
			);

			$context = array(
				'contract_version'           => self::clean_log_value( (string) ( $event['contract_version'] ?? '' ), 80 ),
				'operation_contract_version' => self::clean_log_value( (string) ( $event['operation_contract_version'] ?? '' ), 80 ),
				'channel'                    => 'editor',
				'connector_id'               => 'npcink-cloud-addon',
				'task'                       => $task,
				'modality'                   => $type,
				'cloud_run_id'               => $run_id,
				'suggestion_only'            => true,
				'direct_wordpress_write'     => false,
				'content_storage'            => 'omitted_metadata_only',
			);

			$log_data = array(
				// WordPress AI log types describe the caller, not the content modality.
				'type'        => 'ai_client',
				'operation'   => self::clean_log_value( (string) ( $event['operation'] ?? 'npcink-cloud/connector-runtime' ), 120 ) . ':' . $task,
				'provider'    => self::clean_log_value( $provider, 120 ),
				'model'       => self::clean_log_value( $model, 160 ),
				'duration_ms' => max( 0, (int) ( $event['duration_ms'] ?? 0 ) ),
				'status'      => $is_error ? 'error' : 'success',
				'error_message' => $is_error ? self::clean_log_value( $error->get_error_message(), 300 ) : '',
				'user_id'     => function_exists( 'get_current_user_id' ) ? get_current_user_id() : 0,
				'context'     => $context,
			);

			try {
				$manager = new $manager_class();
				if ( method_exists( $manager, 'init' ) ) {
					$manager->init();
				}
				if ( method_exists( $manager, 'log' ) ) {
					$manager->log( $log_data );
				}
			} catch ( \Throwable $error ) {
				// Keep a bounded local trace so a lost request-log entry can still
				// be diagnosed from the server log; never surface this in the UI.
				if ( function_exists( 'error_log' ) ) {
					error_log(
						'npcink-cloud-addon: WordPress AI request log write failed: '
						. self::clean_log_value( (string) $error->getMessage(), 160 )
					);
				}
				return;
			}
		}

		/**
		 * Returns a millisecond timestamp for runtime evidence.
		 *
		 * @return int
		 */
		public static function runtime_timer_start(): int {
			return function_exists( 'hrtime' ) ? (int) hrtime( true ) : (int) round( microtime( true ) * 1000 );
		}

		/**
		 * Returns elapsed milliseconds from runtime_timer_start().
		 *
		 * @param int $start Start timestamp.
		 * @return int
		 */
		public static function runtime_timer_elapsed_ms( int $start ): int {
			if ( function_exists( 'hrtime' ) ) {
				return max( 0, (int) round( ( hrtime( true ) - $start ) / 1000000 ) );
			}

			return max( 0, (int) round( microtime( true ) * 1000 ) - $start );
		}

		/**
		 * Registers the synthetic marker setting without exposing it through REST.
		 *
		 * @return void
		 */
		private static function register_marker_setting(): void {
			if ( ! function_exists( 'register_setting' ) ) {
				return;
			}

			if ( function_exists( 'get_registered_settings' ) ) {
				$registered = get_registered_settings();
				if ( isset( $registered[ self::SETTING_NAME ] ) ) {
					return;
				}
			}

			register_setting(
				'npcink_cloud_addon',
				self::SETTING_NAME,
				array(
					'type'         => 'string',
					'default'      => '',
					'show_in_rest' => false,
				)
			);
		}

		/**
		 * Builds a bounded, actionable connector failure message for the editor.
		 *
		 * The WordPress AI client escapes exception text at render time, so the
		 * returned string must stay plain text. Friendly sentence first, stable
		 * code for support, then a bounded detail excerpt.
		 *
		 * @param string $friendly_key Stable friendly-message key.
		 * @param string $stable_code Stable error code shown for support.
		 * @param string $detail Sanitized upstream or validation detail.
		 * @return string
		 */
		public static function user_facing_connector_error( string $friendly_key, string $stable_code, string $detail = '' ): string {
			$messages = array(
				'scene_not_supported'           => __( 'This request is not supported by the Npcink Cloud AI connection yet.', 'npcink-cloud-addon' ),
				'chat_history_not_supported'    => __( 'The Npcink Cloud AI connection does not support conversation history for this request.', 'npcink-cloud-addon' ),
				'tools_not_supported'           => __( 'The Npcink Cloud AI connection does not support tool calls or web search.', 'npcink-cloud-addon' ),
				'scene_input_required'          => __( 'The Npcink Cloud AI connection needs text input for this request.', 'npcink-cloud-addon' ),
				'reference_image_not_supported' => __( 'Image-to-image refinement is not supported by the Npcink Cloud AI connection yet.', 'npcink-cloud-addon' ),
				'task_contract_rejected'        => __( 'This AI feature is not currently available through the Npcink Cloud connection.', 'npcink-cloud-addon' ),
				'output_missing'                => __( 'Npcink Cloud did not return usable output for this request. Please try again.', 'npcink-cloud-addon' ),
				'artifact_contract_invalid'     => __( 'Npcink Cloud returned an unexpected image result. Please try again.', 'npcink-cloud-addon' ),
				'artifact_preview_limit'        => __( 'The generated image previews are too large to show. Try generating fewer images at once.', 'npcink-cloud-addon' ),
				'artifact_verification_failed'  => __( 'The generated image could not be verified for safe delivery. Please try again.', 'npcink-cloud-addon' ),
				'artifact_ack_invalid'          => __( 'Npcink Cloud could not confirm delivery of the generated image. Please try again.', 'npcink-cloud-addon' ),
				'artifact_expired'              => __( 'The generated image is no longer available. Please generate it again.', 'npcink-cloud-addon' ),
				'runtime_failed'                => __( 'Npcink Cloud could not complete this AI request. Please try again in a moment.', 'npcink-cloud-addon' ),
				'runtime_unreachable'           => __( 'Npcink Cloud could not be reached from this site. Please try again in a moment.', 'npcink-cloud-addon' ),
				'runtime_unauthorized'          => __( 'The Npcink Cloud connection is no longer authorized. Please reconnect the site from the Cloud Addon settings page.', 'npcink-cloud-addon' ),
				'runtime_limit_reached'         => __( 'This request reached a Npcink Cloud limit. Please wait a moment and try again, or review the plan usage in Cloud.', 'npcink-cloud-addon' ),
			);
			$message = $messages[ $friendly_key ] ?? $messages['runtime_failed'];
			$stable_code = sanitize_key( $stable_code );
			if ( '' !== $stable_code ) {
				$message .= ' (' . $stable_code . ')';
			}
			$detail = self::bound_connector_error_detail( $detail );
			if ( '' !== $detail ) {
				$message .= ' ' . $detail;
			}

			return $message;
		}

		/**
		 * Formats an already-translated attachment source error for the editor.
		 *
		 * @param WP_Error $error Attachment validation error.
		 * @return string
		 */
		public static function user_facing_attachment_error( $error ): string {
			if ( ! is_wp_error( $error ) ) {
				return '';
			}
			$message = (string) $error->get_error_message();
			$code = sanitize_key( (string) $error->get_error_code() );

			return '' !== $code ? $message . ' (' . $code . ')' : $message;
		}

		/**
		 * Classifies a runtime failure into a friendly message family.
		 *
		 * @param string $error_code Local WP_Error code.
		 * @param string $diagnostic Stable Cloud code and stage diagnostic.
		 * @param string $detail Sanitized upstream failure detail.
		 * @return string
		 */
		public static function user_facing_runtime_failure( string $error_code, string $diagnostic, string $detail ): string {
			$haystack = strtolower( $error_code . ' ' . $diagnostic . ' ' . self::bound_connector_error_detail( $detail ) );
			$friendly_key = 'runtime_failed';
			if ( 1 === preg_match( '/(?:unauthorized|forbidden|authorization[_ ]expired)/', $haystack ) ) {
				$friendly_key = 'runtime_unauthorized';
			} elseif ( 1 === preg_match( '/(?:purged|artifact[_ ]expired)/', $haystack ) ) {
				$friendly_key = 'artifact_expired';
			} elseif ( 1 === preg_match( '/(?:rate.?limit|quota|limit[_ ]exceeded|too[_ ]many[_ ]requests|insufficient[_ ]credit)/', $haystack ) ) {
				$friendly_key = 'runtime_limit_reached';
			} elseif ( 1 === preg_match( '/(?:timeout|timed[_ ]out|http_request_failed|connection|curl|dns|name[_ ]resolution|network)/', $haystack ) ) {
				$friendly_key = 'runtime_unreachable';
			}

			$stable_code = '' !== $diagnostic ? $diagnostic : $error_code;

			return self::user_facing_connector_error( $friendly_key, $stable_code, $detail );
		}

		/**
		 * Bounds an upstream detail excerpt for one-line editor display.
		 *
		 * @param string $detail Raw detail text.
		 * @return string
		 */
		private static function bound_connector_error_detail( string $detail ): string {
			$detail = function_exists( 'sanitize_text_field' ) ? sanitize_text_field( $detail ) : trim( preg_replace( '/\s+/', ' ', $detail ) );
			if ( function_exists( 'mb_substr' ) ) {
				return mb_substr( $detail, 0, 160 );
			}

			// Multibyte-safe bound without mbstring: drop invalid UTF-8 bytes,
			// slice with a UTF-8 regex, and keep a byte-level prefix if the
			// regex still cannot run so the detail is never fully discarded.
			if ( function_exists( 'mb_convert_encoding' ) ) {
				$detail = mb_convert_encoding( $detail, 'UTF-8', 'UTF-8' );
			}
			$matches = array();
			$matched = preg_match( '/\A.{0,160}/us', $detail, $matches );

			return 1 === $matched ? (string) $matches[0] : substr( $detail, 0, 160 );
		}

		/**
		 * Checks whether verified Cloud settings may be exposed to WordPress AI.
		 *
		 * @return bool
		 */
		private static function is_cloud_connector_available(): bool {
			return class_exists( 'Npcink_Cloud_Addon_Settings' )
				&& Npcink_Cloud_Addon_Settings::is_wordpress_ai_connector_enabled();
		}

		/**
		 * Returns the active-plugin identifier used by the connector registry.
		 *
		 * WordPress resolves plugin_basename() through a real-path map. A
		 * development symlink can leave that map temporarily stale, so prefer
		 * the actual active-plugin entry when it is available and use the stable
		 * packaged basename as the fallback.
		 *
		 * @return string
		 */
		private static function connector_plugin_file(): string {
			$stable_plugin_file = defined( 'NPCINK_CLOUD_ADDON_PLUGIN_BASENAME' )
				&& is_string( NPCINK_CLOUD_ADDON_PLUGIN_BASENAME )
				&& '' !== NPCINK_CLOUD_ADDON_PLUGIN_BASENAME
				? NPCINK_CLOUD_ADDON_PLUGIN_BASENAME
				: (
					defined( 'NPCINK_CLOUD_ADDON_FILE' )
						? basename( dirname( NPCINK_CLOUD_ADDON_FILE ) ) . '/' . basename( NPCINK_CLOUD_ADDON_FILE )
						: ''
				);
			$dynamic_plugin_file = '';
			if ( defined( 'NPCINK_CLOUD_ADDON_FILE' ) && function_exists( 'plugin_basename' ) ) {
				$dynamic_plugin_file = (string) plugin_basename( NPCINK_CLOUD_ADDON_FILE );
			}

			$active_plugins = function_exists( 'get_option' ) ? get_option( 'active_plugins', array() ) : array();
			if ( function_exists( 'get_site_option' ) ) {
				$active_plugins = array_merge( (array) $active_plugins, array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) ) );
			}
			$active_plugins = array_values( array_filter( (array) $active_plugins, 'is_string' ) );

			if ( '' !== $dynamic_plugin_file && in_array( $dynamic_plugin_file, $active_plugins, true ) ) {
				return $dynamic_plugin_file;
			}
			if ( in_array( $stable_plugin_file, $active_plugins, true ) ) {
				return $stable_plugin_file;
			}

			$main_file = defined( 'NPCINK_CLOUD_ADDON_FILE' ) ? basename( NPCINK_CLOUD_ADDON_FILE ) : basename( $stable_plugin_file );
			$matches = array();
			foreach ( $active_plugins as $active_plugin ) {
				if ( $main_file === basename( $active_plugin ) ) {
					$matches[] = $active_plugin;
				}
			}
			if ( 1 === count( $matches ) ) {
				return $matches[0];
			}

			return $stable_plugin_file;
		}

		/**
		 * Checks whether AI request logging is enabled by the AI plugin feature flags.
		 *
		 * @return bool
		 */
		private static function is_wordpress_ai_request_logging_enabled(): bool {
			$global_enabled = (bool) get_option( 'wpai_features_enabled', false );
			$feature_enabled = (bool) get_option( 'wpai_feature_ai-request-logging_enabled', false );
			if ( function_exists( 'apply_filters' ) ) {
				// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WordPress AI owns this feature-flag filter name.
				$feature_enabled = (bool) apply_filters( 'wpai_feature_ai-request-logging_enabled', $feature_enabled );
			}

			return $global_enabled && $feature_enabled;
		}

		/**
		 * Returns the first non-empty string at one of the dot paths.
		 *
		 * @param array<string,mixed> $source Source array.
		 * @param list<string>        $paths Dot paths.
		 * @param string              $fallback Fallback.
		 * @return string
		 */
		private static function first_response_string( array $source, array $paths, string $fallback ): string {
			foreach ( $paths as $path ) {
				$value = self::array_dot_value( $source, $path );
				if ( is_scalar( $value ) && '' !== trim( (string) $value ) ) {
					return trim( (string) $value );
				}
			}

			return $fallback;
		}

		/**
		 * Returns a nested array value by dot path.
		 *
		 * @param array<string,mixed> $source Source array.
		 * @param string              $path Dot path.
		 * @return mixed
		 */
		private static function array_dot_value( array $source, string $path ) {
			$value = $source;
			foreach ( explode( '.', $path ) as $segment ) {
				if ( ! is_array( $value ) || ! array_key_exists( $segment, $value ) ) {
					return null;
				}
				$value = $value[ $segment ];
			}

			return $value;
		}

		/**
		 * Sanitizes and bounds metadata values before writing optional logs.
		 *
		 * @param string $value Raw value.
		 * @param int    $max_length Max length.
		 * @return string
		 */
		private static function clean_log_value( string $value, int $max_length ): string {
			$value = sanitize_text_field( $value );
			if ( strlen( $value ) <= $max_length ) {
				return $value;
			}

			return substr( $value, 0, $max_length );
		}

		/**
		 * Returns whether the current WordPress AI call is a supported text scene.
		 *
		 * @param string $ability_name Registered Ability name.
		 * @return bool
		 */
		private static function is_text_scene_ability( string $ability_name ): bool {
			return in_array(
				$ability_name,
				array(
					'ai/comment-analysis',
					'ai/content-classification',
					'ai/content-resizing',
					'ai/content-translation',
					'ai/editorial-notes',
					'ai/editorial-updates',
					'ai/excerpt-generation',
					'ai/image-prompt-generation',
					'ai/meta-description',
					'ai/slug-generation',
					'ai/suggest-reply',
					'ai/summarization',
					'ai/title-generation',
				),
				true
			);
		}
	}
	}

	if (
	class_exists( 'WordPress\\AiClient\\Providers\\AbstractProvider' )
	&& interface_exists( 'WordPress\\AiClient\\Providers\\Contracts\\ProviderAvailabilityInterface' )
	&& interface_exists( 'WordPress\\AiClient\\Providers\\Contracts\\ModelMetadataDirectoryInterface' )
	&& ! class_exists( 'Npcink_Cloud_WordPress_AI_Provider' )
) {
	/**
	 * Npcink Cloud provider metadata for the PHP AI Client.
	 */
	final class Npcink_Cloud_WordPress_AI_Provider extends \WordPress\AiClient\Providers\AbstractProvider {
		/**
		 * Creates the scene-bound text model.
		 *
		 * @param \WordPress\AiClient\Providers\Models\DTO\ModelMetadata $model_metadata Model metadata.
		 * @param \WordPress\AiClient\Providers\DTO\ProviderMetadata     $provider_metadata Provider metadata.
		 * @return \WordPress\AiClient\Providers\Models\Contracts\ModelInterface
		 */
		protected static function createModel(
			\WordPress\AiClient\Providers\Models\DTO\ModelMetadata $model_metadata,
			\WordPress\AiClient\Providers\DTO\ProviderMetadata $provider_metadata
		): \WordPress\AiClient\Providers\Models\Contracts\ModelInterface {
			if ( Npcink_Cloud_WordPress_AI_Connector::IMAGE_MODEL_ID === $model_metadata->getId() ) {
				return new Npcink_Cloud_WordPress_AI_Image_Model( $model_metadata, $provider_metadata );
			}
			if ( Npcink_Cloud_WordPress_AI_Connector::VISION_MODEL_ID === $model_metadata->getId() ) {
				return new Npcink_Cloud_WordPress_AI_Vision_Text_Model( $model_metadata, $provider_metadata );
			}

			return new Npcink_Cloud_WordPress_AI_Text_Model( $model_metadata, $provider_metadata );
		}

		/**
		 * Creates provider metadata.
		 *
		 * @return \WordPress\AiClient\Providers\DTO\ProviderMetadata
		 */
		protected static function createProviderMetadata(): \WordPress\AiClient\Providers\DTO\ProviderMetadata {
			return new \WordPress\AiClient\Providers\DTO\ProviderMetadata(
				Npcink_Cloud_WordPress_AI_Connector::CONNECTOR_ID,
				Npcink_Cloud_WordPress_AI_Connector::CONNECTOR_NAME,
				\WordPress\AiClient\Providers\Enums\ProviderTypeEnum::cloud(),
				function_exists( 'admin_url' ) ? admin_url( ( defined( 'NPCINK_TOOLBOX_VERSION' ) ? 'admin.php' : 'options-general.php' ) . '?page=npcink-cloud-addon' ) : null,
				\WordPress\AiClient\Providers\Http\Enums\RequestAuthenticationMethod::apiKey(),
				__( 'Bounded WordPress AI scene tasks through verified Npcink Cloud settings.', 'npcink-cloud-addon' )
			);
		}

		/**
		 * Creates provider availability.
		 *
		 * @return \WordPress\AiClient\Providers\Contracts\ProviderAvailabilityInterface
		 */
		protected static function createProviderAvailability(): \WordPress\AiClient\Providers\Contracts\ProviderAvailabilityInterface {
			return new Npcink_Cloud_WordPress_AI_Availability();
		}

		/**
		 * Creates the fixed model metadata directory.
		 *
		 * @return \WordPress\AiClient\Providers\Contracts\ModelMetadataDirectoryInterface
		 */
		protected static function createModelMetadataDirectory(): \WordPress\AiClient\Providers\Contracts\ModelMetadataDirectoryInterface {
			return new Npcink_Cloud_WordPress_AI_Model_Metadata_Directory();
		}
	}

	/**
	 * Availability is driven by Save and Verify plus local connector exposure consent.
	 */
	final class Npcink_Cloud_WordPress_AI_Availability implements \WordPress\AiClient\Providers\Contracts\ProviderAvailabilityInterface {
		/**
		 * Checks whether verified Cloud settings may be exposed to WordPress AI.
		 *
		 * @return bool
		 */
		public function isConfigured(): bool {
			return class_exists( 'Npcink_Cloud_Addon_Settings' )
				&& Npcink_Cloud_Addon_Settings::is_wordpress_ai_connector_enabled();
		}
	}

	/**
	 * Fixed model directory for the Npcink Cloud scene text model.
	 */
	final class Npcink_Cloud_WordPress_AI_Model_Metadata_Directory implements \WordPress\AiClient\Providers\Contracts\ModelMetadataDirectoryInterface {
		/**
		 * Lists available model metadata.
		 *
		 * @return list<\WordPress\AiClient\Providers\Models\DTO\ModelMetadata>
		 */
		public function listModelMetadata(): array {
			$models = array();
			foreach ( array( Npcink_Cloud_WordPress_AI_Connector::MODEL_ID, Npcink_Cloud_WordPress_AI_Connector::VISION_MODEL_ID, Npcink_Cloud_WordPress_AI_Connector::IMAGE_MODEL_ID ) as $model_id ) {
				if ( $this->hasModelMetadata( $model_id ) ) {
					$models[] = $this->getModelMetadata( $model_id );
				}
			}
			return $models;
		}

		/**
		 * Checks if metadata exists for a model.
		 *
		 * @param string $model_id Model id.
		 * @return bool
		 */
		public function hasModelMetadata( string $model_id ): bool {
			return Npcink_Cloud_WordPress_AI_Connector::is_model_available( $model_id );
		}

		/**
		 * Gets metadata for a model.
		 *
		 * @param string $model_id Model id.
		 * @return \WordPress\AiClient\Providers\Models\DTO\ModelMetadata
		 */
		public function getModelMetadata( string $model_id ): \WordPress\AiClient\Providers\Models\DTO\ModelMetadata {
			if ( ! $this->hasModelMetadata( $model_id ) ) {
				throw new \WordPress\AiClient\Common\Exception\InvalidArgumentException( 'Npcink Cloud model metadata not found.' );
			}

			if ( Npcink_Cloud_WordPress_AI_Connector::IMAGE_MODEL_ID === $model_id ) {
				return $this->image_model_metadata();
			}
			if ( Npcink_Cloud_WordPress_AI_Connector::VISION_MODEL_ID === $model_id ) {
				return $this->vision_model_metadata();
			}

			return $this->text_model_metadata();
		}

		/**
		 * Builds the fixed model metadata.
		 *
		 * @return \WordPress\AiClient\Providers\Models\DTO\ModelMetadata
		 */
		private function text_model_metadata(): \WordPress\AiClient\Providers\Models\DTO\ModelMetadata {
			return new \WordPress\AiClient\Providers\Models\DTO\ModelMetadata(
				Npcink_Cloud_WordPress_AI_Connector::MODEL_ID,
				'Npcink Cloud Scene Text',
				array(
					\WordPress\AiClient\Providers\Models\Enums\CapabilityEnum::textGeneration(),
				),
				array(
					new \WordPress\AiClient\Providers\Models\DTO\SupportedOption(
						\WordPress\AiClient\Providers\Models\Enums\OptionEnum::inputModalities(),
						array( array( \WordPress\AiClient\Messages\Enums\ModalityEnum::text() ) )
					),
					new \WordPress\AiClient\Providers\Models\DTO\SupportedOption(
						\WordPress\AiClient\Providers\Models\Enums\OptionEnum::outputModalities(),
						array( array( \WordPress\AiClient\Messages\Enums\ModalityEnum::text() ) )
					),
					new \WordPress\AiClient\Providers\Models\DTO\SupportedOption(
						\WordPress\AiClient\Providers\Models\Enums\OptionEnum::outputMimeType(),
						array( 'text/plain', 'application/json' )
					),
					new \WordPress\AiClient\Providers\Models\DTO\SupportedOption( \WordPress\AiClient\Providers\Models\Enums\OptionEnum::outputSchema() ),
					new \WordPress\AiClient\Providers\Models\DTO\SupportedOption( \WordPress\AiClient\Providers\Models\Enums\OptionEnum::systemInstruction() ),
					new \WordPress\AiClient\Providers\Models\DTO\SupportedOption( \WordPress\AiClient\Providers\Models\Enums\OptionEnum::candidateCount() ),
					new \WordPress\AiClient\Providers\Models\DTO\SupportedOption( \WordPress\AiClient\Providers\Models\Enums\OptionEnum::maxTokens() ),
					new \WordPress\AiClient\Providers\Models\DTO\SupportedOption( \WordPress\AiClient\Providers\Models\Enums\OptionEnum::temperature() ),
				)
			);
		}

		/**
		 * Builds the fixed vision text model metadata.
		 *
		 * @return \WordPress\AiClient\Providers\Models\DTO\ModelMetadata
		 */
		private function vision_model_metadata(): \WordPress\AiClient\Providers\Models\DTO\ModelMetadata {
			return new \WordPress\AiClient\Providers\Models\DTO\ModelMetadata(
				Npcink_Cloud_WordPress_AI_Connector::VISION_MODEL_ID,
				'Npcink Cloud Scene Vision',
				array(
					\WordPress\AiClient\Providers\Models\Enums\CapabilityEnum::textGeneration(),
				),
				array(
					new \WordPress\AiClient\Providers\Models\DTO\SupportedOption(
						\WordPress\AiClient\Providers\Models\Enums\OptionEnum::inputModalities(),
						array(
							array(
								\WordPress\AiClient\Messages\Enums\ModalityEnum::text(),
								\WordPress\AiClient\Messages\Enums\ModalityEnum::image(),
							),
						)
					),
					new \WordPress\AiClient\Providers\Models\DTO\SupportedOption(
						\WordPress\AiClient\Providers\Models\Enums\OptionEnum::outputModalities(),
						array( array( \WordPress\AiClient\Messages\Enums\ModalityEnum::text() ) )
					),
					new \WordPress\AiClient\Providers\Models\DTO\SupportedOption(
						\WordPress\AiClient\Providers\Models\Enums\OptionEnum::outputMimeType(),
						array( 'text/plain' )
					),
					new \WordPress\AiClient\Providers\Models\DTO\SupportedOption( \WordPress\AiClient\Providers\Models\Enums\OptionEnum::systemInstruction() ),
					new \WordPress\AiClient\Providers\Models\DTO\SupportedOption( \WordPress\AiClient\Providers\Models\Enums\OptionEnum::maxTokens() ),
					new \WordPress\AiClient\Providers\Models\DTO\SupportedOption( \WordPress\AiClient\Providers\Models\Enums\OptionEnum::temperature() ),
				)
			);
		}

		/**
		 * Builds the fixed image generation model metadata.
		 *
		 * @return \WordPress\AiClient\Providers\Models\DTO\ModelMetadata
		 */
		private function image_model_metadata(): \WordPress\AiClient\Providers\Models\DTO\ModelMetadata {
			return new \WordPress\AiClient\Providers\Models\DTO\ModelMetadata(
				Npcink_Cloud_WordPress_AI_Connector::IMAGE_MODEL_ID,
				'Npcink Cloud Scene Image',
				array(
					\WordPress\AiClient\Providers\Models\Enums\CapabilityEnum::imageGeneration(),
				),
				array(
					new \WordPress\AiClient\Providers\Models\DTO\SupportedOption(
						\WordPress\AiClient\Providers\Models\Enums\OptionEnum::inputModalities(),
						array( array( \WordPress\AiClient\Messages\Enums\ModalityEnum::text() ) )
					),
					new \WordPress\AiClient\Providers\Models\DTO\SupportedOption(
						\WordPress\AiClient\Providers\Models\Enums\OptionEnum::outputModalities(),
						array( array( \WordPress\AiClient\Messages\Enums\ModalityEnum::image() ) )
					),
					new \WordPress\AiClient\Providers\Models\DTO\SupportedOption(
						\WordPress\AiClient\Providers\Models\Enums\OptionEnum::outputFileType(),
						array(
							\WordPress\AiClient\Files\Enums\FileTypeEnum::inline(),
							\WordPress\AiClient\Files\Enums\FileTypeEnum::remote(),
						)
					),
					new \WordPress\AiClient\Providers\Models\DTO\SupportedOption(
						\WordPress\AiClient\Providers\Models\Enums\OptionEnum::outputMimeType(),
						array( 'image/png', 'image/jpeg', 'image/webp' )
					),
					new \WordPress\AiClient\Providers\Models\DTO\SupportedOption( \WordPress\AiClient\Providers\Models\Enums\OptionEnum::candidateCount() ),
					new \WordPress\AiClient\Providers\Models\DTO\SupportedOption( \WordPress\AiClient\Providers\Models\Enums\OptionEnum::outputMediaAspectRatio() ),
					new \WordPress\AiClient\Providers\Models\DTO\SupportedOption( \WordPress\AiClient\Providers\Models\Enums\OptionEnum::outputMediaOrientation() ),
				)
			);
		}
	}

	/**
	 * Scene-gated text model that forwards only known AI plugin ability calls to Cloud.
	 */
	final class Npcink_Cloud_WordPress_AI_Text_Model implements
		\WordPress\AiClient\Providers\Models\Contracts\ModelInterface,
		\WordPress\AiClient\Providers\Models\TextGeneration\Contracts\TextGenerationModelInterface {
		/**
		 * Model metadata.
		 *
		 * @var \WordPress\AiClient\Providers\Models\DTO\ModelMetadata
		 */
		private $metadata;

		/**
		 * Provider metadata.
		 *
		 * @var \WordPress\AiClient\Providers\DTO\ProviderMetadata
		 */
		private $provider_metadata;

		/**
		 * Model config.
		 *
		 * @var \WordPress\AiClient\Providers\Models\DTO\ModelConfig
		 */
		private $config;

		/**
		 * Constructor.
		 *
		 * @param \WordPress\AiClient\Providers\Models\DTO\ModelMetadata $metadata Model metadata.
		 * @param \WordPress\AiClient\Providers\DTO\ProviderMetadata     $provider_metadata Provider metadata.
		 */
		public function __construct(
			\WordPress\AiClient\Providers\Models\DTO\ModelMetadata $metadata,
			\WordPress\AiClient\Providers\DTO\ProviderMetadata $provider_metadata
		) {
			$this->metadata          = $metadata;
			$this->provider_metadata = $provider_metadata;
			$this->config            = new \WordPress\AiClient\Providers\Models\DTO\ModelConfig();
		}

		/**
		 * Gets model metadata.
		 *
		 * @return \WordPress\AiClient\Providers\Models\DTO\ModelMetadata
		 */
		public function metadata(): \WordPress\AiClient\Providers\Models\DTO\ModelMetadata {
			return $this->metadata;
		}

		/**
		 * Gets provider metadata.
		 *
		 * @return \WordPress\AiClient\Providers\DTO\ProviderMetadata
		 */
		public function providerMetadata(): \WordPress\AiClient\Providers\DTO\ProviderMetadata {
			return $this->provider_metadata;
		}

		/**
		 * Sets model config.
		 *
		 * @param \WordPress\AiClient\Providers\Models\DTO\ModelConfig $config Model config.
		 * @return void
		 */
		public function setConfig( \WordPress\AiClient\Providers\Models\DTO\ModelConfig $config ): void {
			$this->config = $config;
		}

		/**
		 * Gets model config.
		 *
		 * @return \WordPress\AiClient\Providers\Models\DTO\ModelConfig
		 */
		public function getConfig(): \WordPress\AiClient\Providers\Models\DTO\ModelConfig {
			return $this->config;
		}

		/**
		 * Generates a text result through the bounded Cloud runtime seam.
		 *
		 * @param list<\WordPress\AiClient\Messages\DTO\Message> $prompt Prompt messages.
		 * @return \WordPress\AiClient\Results\DTO\GenerativeAiResult
		 */
		public function generateTextResult( array $prompt ): \WordPress\AiClient\Results\DTO\GenerativeAiResult {
			// Clear a prior ability's evidence before any validation or runtime call.
			// A failed request must never inherit the previous Cloud run ID.
			Npcink_Cloud_WordPress_AI_Connector::record_cloud_run_id( '' );
			Npcink_Cloud_WordPress_AI_Connector::reset_runtime_failure_evidence();
			$ability_name = $this->detect_scene_ability_name();
			if ( '' === $ability_name ) {
				throw new \WordPress\AiClient\Common\Exception\RuntimeException( Npcink_Cloud_WordPress_AI_Connector::user_facing_connector_error( 'scene_not_supported', 'cloud_wp_ai_scene_not_supported' ) );
			}
			$task_contract = npcink_cloud_addon_project_ai_task_contract( $ability_name );
			if ( is_wp_error( $task_contract ) ) {
				throw new \WordPress\AiClient\Common\Exception\RuntimeException( Npcink_Cloud_WordPress_AI_Connector::user_facing_connector_error( 'task_contract_rejected', (string) $task_contract->get_error_code(), (string) $task_contract->get_error_message() ) );
			}
			$task = (string) $task_contract['task'];

			if ( 1 !== count( $prompt ) ) {
				throw new \WordPress\AiClient\Common\Exception\RuntimeException( Npcink_Cloud_WordPress_AI_Connector::user_facing_connector_error( 'chat_history_not_supported', 'cloud_wp_ai_chat_history_not_supported' ) );
			}

			if ( null !== $this->config->getFunctionDeclarations() || null !== $this->config->getWebSearch() ) {
				throw new \WordPress\AiClient\Common\Exception\RuntimeException( Npcink_Cloud_WordPress_AI_Connector::user_facing_connector_error( 'tools_not_supported', 'cloud_wp_ai_tools_not_supported' ) );
			}

			$text = $this->prompt_text( $prompt );
			if ( '' === $text ) {
				throw new \WordPress\AiClient\Common\Exception\RuntimeException( Npcink_Cloud_WordPress_AI_Connector::user_facing_connector_error( 'scene_input_required', 'cloud_wp_ai_scene_input_required' ) );
			}
			$ability_context = Npcink_Cloud_WordPress_AI_Connector::current_text_ability_context();

			$scene_input = array(
				'response_format'    => $this->response_format_hint( $task, $task_contract ),
				'candidate_count'    => $this->config->getCandidateCount(),
				'max_tokens'         => $this->config->getMaxTokens(),
				'temperature'        => $this->config->getTemperature(),
				'scene_gate'         => array(
					'source' => 'wordpress_ai_plugin_ability',
					'task'   => $task,
				),
			);
			$context_input = is_array( $ability_context['input'] ?? null ) ? $ability_context['input'] : array();
			if ( in_array( $task, array( 'title_generation', 'content_summary', 'content_rewrite', 'content_translation', 'editorial_updates' ), true ) ) {
				$scene_input['source_text'] = $text;
			} elseif ( in_array( $task, array( 'excerpt_generation', 'meta_description' ), true ) ) {
				$short_text_projection = $this->project_short_text_scene_request( $task, $text, $context_input );
				$scene_input = array_merge( $scene_input, $short_text_projection );
			} else {
				$scene_input['prompt'] = $text;
			}
			if ( 'content_classification' === $task ) {
				$taxonomy = sanitize_key( (string) ( $context_input['taxonomy'] ?? '' ) );
				$strategy = sanitize_key( (string) ( $context_input['strategy'] ?? '' ) );
				$max_suggestions = absint( $context_input['max_suggestions'] ?? 0 );
				if ( '' !== $taxonomy ) {
					$scene_input['taxonomy'] = substr( $taxonomy, 0, 64 );
				}
				if ( in_array( $strategy, array( 'existing_only', 'allow_new' ), true ) ) {
					$scene_input['strategy'] = $strategy;
				}
				if ( 0 < $max_suggestions ) {
					$scene_input['max_suggestions'] = min( 10, $max_suggestions );
				}
			}
			if ( 'content_translation' === $task ) {
				$target_language = sanitize_key( (string) ( $context_input['target_language'] ?? '' ) );
				$content = trim( (string) ( $context_input['content'] ?? '' ) );
				if ( '' !== $content ) {
					$scene_input['source_text'] = $content;
				}
				if ( '' !== $target_language ) {
					$scene_input['target_language'] = substr( $target_language, 0, 20 );
				}
			}
			if ( 'editorial_updates' === $task ) {
				$block_content = trim( (string) ( $context_input['block_content'] ?? '' ) );
				$notes = is_array( $context_input['notes'] ?? null ) ? $context_input['notes'] : array();
				// Keep the official Ability prompt: it already contains the block,
				// context, and note framing. Replacing it with block_content alone
				// makes the Provider believe no Notes were supplied.
				if ( '' === trim( (string) $scene_input['source_text'] ) && '' !== $block_content ) {
					$scene_input['source_text'] = $block_content;
				}
				if ( ! empty( $notes ) ) {
					$scene_input['editorial_notes_instruction'] = 'Editorial notes to apply: ' . implode( ' | ', array_map( 'sanitize_text_field', $notes ) );
				}
			}
			if ( 'editorial_notes' === $task ) {
				$block_content = trim( (string) ( $context_input['block_content'] ?? '' ) );
				$review_types = is_array( $context_input['review_types'] ?? null ) ? $context_input['review_types'] : array();
				$scene_input['prompt'] = trim( $text . ( '' !== $block_content ? "\n\nBlock content:\n" . $block_content : '' ) . ( ! empty( $review_types ) ? "\nReview types: " . implode( ', ', array_map( 'sanitize_key', $review_types ) ) : '' ) );
			}
			if ( 'title_generation' === $task ) {
				$context_input = is_array( $ability_context['input'] ?? null ) ? $ability_context['input'] : array();
				$context_post_id = absint( $context_input['context'] ?? 0 );
				$context_post = $context_post_id > 0 ? get_post( $context_post_id ) : null;
				if ( $context_post instanceof \WP_Post && '' !== trim( (string) $context_post->post_title ) ) {
					$existing_title = wp_strip_all_tags( (string) $context_post->post_title );
					$scene_input['existing_title'] = function_exists( 'mb_substr' )
						? mb_substr( $existing_title, 0, 160 )
						: substr( $existing_title, 0, 160 );
				}
			}
			$system_instruction = (string) ( $this->config->getSystemInstruction() ?? '' );
			if ( '' !== trim( $system_instruction ) ) {
				$scene_input['system_instruction'] = trim(
					(string) ( $scene_input['system_instruction'] ?? '' ) . "\n\n" . $system_instruction
				);
			}
			if ( 'editorial_updates' === $task && '' !== (string) ( $scene_input['editorial_notes_instruction'] ?? '' ) ) {
				$scene_input['system_instruction'] = trim(
					(string) ( $scene_input['system_instruction'] ?? '' ) . "\n\n" . $scene_input['editorial_notes_instruction']
				);
				unset( $scene_input['editorial_notes_instruction'] );
			}
			$site_knowledge_reference_mode = $this->site_knowledge_reference_mode( $task );
			if ( '' !== $site_knowledge_reference_mode && Npcink_Cloud_Addon_Settings::is_site_knowledge_generation_reference_enabled() ) {
				$scene_input['site_knowledge_reference'] = array(
					'enabled' => true,
					'mode'    => $site_knowledge_reference_mode,
				);
			}

			$journey_input = $ability_name === (string) ( $ability_context['ability_id'] ?? '' )
				&& is_array( $ability_context['input'] ?? null )
				? $ability_context['input']
				: array();
			$journey_session_id = Npcink_Cloud_Customer_Journey::build_session_id( $task, $journey_input );
			Npcink_Cloud_Customer_Journey::capture_generation(
				$task,
				'started',
				$journey_session_id
			);

			$started  = Npcink_Cloud_WordPress_AI_Connector::runtime_timer_start();
			$response = npcink_cloud_addon_execute_registered_ai_task_runtime(
				$ability_name,
				$scene_input,
				'trace_wp_ai_connector_' . wp_generate_uuid4(),
				'wp_ai_connector_' . wp_generate_uuid4()
			);
			$duration_ms = Npcink_Cloud_WordPress_AI_Connector::runtime_timer_elapsed_ms( $started );
			$log_event = array(
					'type'                       => 'text',
					'operation'                  => 'npcink-cloud/connector-runtime',
					'task'                       => $task,
					'contract_version'           => 'cloud_connector_runtime.v1',
					'operation_contract_version' => 'wordpress_operation.v1',
					'response'                   => $response,
					'duration_ms'                => $duration_ms,
					'fallback_model_id'          => Npcink_Cloud_WordPress_AI_Connector::MODEL_ID,
				);

			if ( is_wp_error( $response ) ) {
				$error_code = sanitize_key( (string) $response->get_error_code() );
				$evidence = Npcink_Cloud_WordPress_AI_Connector::record_runtime_failure_from_wp_error( $response );
				$log_event['cloud_run_id'] = (string) ( $evidence['run_id'] ?? '' );
				Npcink_Cloud_WordPress_AI_Connector::maybe_log_wordpress_ai_request_evidence( $log_event );
				Npcink_Cloud_Customer_Journey::capture_generation_failure(
					$task,
					$journey_session_id,
					$duration_ms,
					$response->get_error_code()
				);
				$cloud_code = $evidence['cloud_error_code'];
				$error_stage = $evidence['error_stage'];
				$diagnostic = '' !== $cloud_code ? $cloud_code : $error_code;
				if ( '' !== $error_stage && empty( $evidence['synthetic_error_stage'] ) ) {
					$diagnostic .= ':' . $error_stage;
				}
				throw new \WordPress\AiClient\Common\Exception\RuntimeException( Npcink_Cloud_WordPress_AI_Connector::user_facing_runtime_failure( $error_code, $diagnostic, (string) $response->get_error_message() ) );
			}

			$output_text = $this->extract_text( is_array( $response ) ? $response : array(), $task );
			if ( '' === $output_text ) {
				$cloud_run_id = Npcink_Cloud_WordPress_AI_Connector::cloud_run_id_from_response( $response );
				Npcink_Cloud_WordPress_AI_Connector::record_cloud_run_id( $cloud_run_id );
				Npcink_Cloud_WordPress_AI_Connector::record_runtime_failure_evidence( array(
					'run_id'         => $cloud_run_id,
					'error_stage'    => 'output_validation',
					'quality_reason' => 'output_missing',
				) );
				$log_event['validation_error'] = new WP_Error( 'cloud_wp_ai_output_missing', 'Cloud response did not include valid text output.' );
				Npcink_Cloud_WordPress_AI_Connector::maybe_log_wordpress_ai_request_evidence( $log_event );
				Npcink_Cloud_Customer_Journey::capture_generation_failure(
					$task,
					$journey_session_id,
					$duration_ms,
					'cloud_wp_ai_output_missing'
				);
				throw new \WordPress\AiClient\Common\Exception\RuntimeException( Npcink_Cloud_WordPress_AI_Connector::user_facing_connector_error( 'output_missing', 'cloud_wp_ai_output_missing' ) );
			}

			Npcink_Cloud_WordPress_AI_Connector::maybe_log_wordpress_ai_request_evidence( $log_event );
			$cloud_run_id = Npcink_Cloud_WordPress_AI_Connector::cloud_run_id_from_response( $response );
			Npcink_Cloud_WordPress_AI_Connector::record_cloud_run_id( $cloud_run_id );
			$run_id = '' !== $cloud_run_id ? $cloud_run_id : wp_generate_uuid4();
			Npcink_Cloud_Customer_Journey::capture_generation(
				$task,
				'succeeded',
				$journey_session_id,
				$duration_ms,
				$run_id
			);
			if (
				class_exists( 'Npcink_Cloud_Editor_Assist_Quality' )
				&& $ability_name === (string) ( $ability_context['ability_id'] ?? '' )
				&& is_array( $ability_context['input'] ?? null )
			) {
				Npcink_Cloud_Editor_Assist_Quality::record_generation(
					$ability_name,
					$task,
					$ability_context['input'],
					$run_id,
					$output_text,
					$duration_ms,
					$journey_session_id
				);
			}

			return new \WordPress\AiClient\Results\DTO\GenerativeAiResult(
				$run_id,
				array(
					new \WordPress\AiClient\Results\DTO\Candidate(
						new \WordPress\AiClient\Messages\DTO\ModelMessage(
							array( new \WordPress\AiClient\Messages\DTO\MessagePart( $output_text ) )
						),
						\WordPress\AiClient\Results\Enums\FinishReasonEnum::stop()
					),
				),
				new \WordPress\AiClient\Results\DTO\TokenUsage( 0, 0, 0 ),
				$this->provider_metadata,
				$this->metadata,
				array(
					'contract_version' => 'cloud_connector_result.v1',
					'task'             => $task,
					'suggestion_only'  => true,
				)
			);
		}

		/**
		 * Extracts text from a single user prompt.
		 *
		 * @param list<\WordPress\AiClient\Messages\DTO\Message> $prompt Prompt messages.
		 * @return string
		 */
		private function prompt_text( array $prompt ): string {
			$message = $prompt[0];
			if ( ! $message->getRole()->isUser() ) {
				return '';
			}

			$parts = array();
			foreach ( $message->getParts() as $part ) {
				$text = $part->getText();
				if ( null !== $text && '' !== trim( $text ) ) {
					$parts[] = trim( $text );
				}
			}

			return trim( implode( "\n\n", $parts ) );
		}

		/**
		 * Projects the official short-text Ability input into the Cloud scene.
		 *
		 * Excerpt and meta-description abilities carry the actual post content in
		 * validated Ability input, while their AI Client message is an instruction.
		 * Keep those roles separate so Cloud can ground the result in the content
		 * without losing the official instruction or asking the model to infer the
		 * source from a generic prompt.
		 *
		 * @param string              $task Task name.
		 * @param string              $instruction Official AI Client instruction.
		 * @param array<string,mixed> $context_input Validated Ability input.
		 * @return array<string,string>
		 */
		private function project_short_text_scene_request( string $task, string $instruction, array $context_input ): array {
			$content = trim( (string) ( $context_input['content'] ?? '' ) );
			if ( '' === $content && function_exists( 'get_post' ) ) {
				$post_id = absint( $context_input['post_id'] ?? $context_input['context'] ?? 0 );
				$post    = $post_id > 0 ? get_post( $post_id ) : null;
				if ( is_object( $post ) ) {
					$content = trim( (string) ( $post->post_content ?? '' ) );
				}
			}

			if ( '' === $content ) {
				return array( 'prompt' => $instruction );
			}

			$source_text = $content;
			if ( 'meta_description' === $task ) {
				$title = trim( (string) ( $context_input['title'] ?? '' ) );
				if ( '' === $title && function_exists( 'get_post' ) ) {
					$post_id = absint( $context_input['post_id'] ?? $context_input['context'] ?? 0 );
					$post    = $post_id > 0 ? get_post( $post_id ) : null;
					if ( is_object( $post ) ) {
						$title = trim( (string) ( $post->post_title ?? '' ) );
					}
				}
				if ( '' !== $title ) {
					$source_text = "Title:\n" . $title . "\n\nContent:\n" . $content;
				}
			}

			return array(
				'source_text'       => $source_text,
				'system_instruction' => $instruction,
			);
		}

		/**
		 * Returns a shallow response-format hint for Cloud-side scene projection.
		 *
		 * @param string $task          WordPress AI ability task.
		 * @param array  $task_contract Projected local Ability contract.
		 * @return string
		 */
		private function response_format_hint( string $task, array $task_contract = array() ): string {
			$constraints = is_array( $task_contract['constraints'] ?? null ) ? $task_contract['constraints'] : array();
			return in_array( 'json_object', $constraints, true ) || in_array( $task, array( 'content_classification', 'comment_moderation' ), true ) ? 'json' : 'text';
		}

		/**
		 * Returns the bounded Site Knowledge reference mode for one editor task.
		 *
		 * @param string $task WordPress AI scene task.
		 * @return string
		 */
		private function site_knowledge_reference_mode( string $task ): string {
			$modes = array(
				'title_generation' => 'site_title_style',
				'content_summary'  => 'site_summary_style',
			);

			return (string) ( $modes[ $task ] ?? '' );
		}

		/**
		 * Detects the registered ai-wp-admin Ability behind a compatibility call.
		 *
		 * Future callers should use the explicit registered-task runtime helper and
		 * avoid stack inspection entirely.
		 *
		 * @return string
		 */
		private function detect_scene_ability_name(): string {
			$map = array(
				'WordPress\\AI\\Abilities\\Image\\Generate_Image_Prompt' => 'ai/image-prompt-generation',
				'WordPress\\AI\\Abilities\\Content_Classification\\Content_Classification' => 'ai/content-classification',
				'WordPress\\AI\\Abilities\\Comment_Moderation\\Comment_Analysis'           => 'ai/comment-analysis',
				'WordPress\\AI\\Abilities\\Content_Resizing\\Content_Resizing'              => 'ai/content-resizing',
				'WordPress\\AI\\Abilities\\Content_Translation\\Content_Translation'        => 'ai/content-translation',
				'WordPress\\AI\\Abilities\\Editorial_Updates\\Editorial_Updates'            => 'ai/editorial-updates',
				'WordPress\\AI\\Abilities\\Editorial_Notes\\Editorial_Notes'                => 'ai/editorial-notes',
				'WordPress\\AI\\Abilities\\Excerpt_Generation\\Excerpt_Generation'          => 'ai/excerpt-generation',
				'WordPress\\AI\\Abilities\\Meta_Description\\Meta_Description'              => 'ai/meta-description',
				'WordPress\\AI\\Abilities\\Slug_Generation\\Slug_Generation'                => 'ai/slug-generation',
				'WordPress\\AI\\Abilities\\Suggest_Reply\\Suggest_Reply'                    => 'ai/suggest-reply',
				'WordPress\\AI\\Abilities\\Title_Generation\\Title_Generation'              => 'ai/title-generation',
				'WordPress\\AI\\Abilities\\Summarization\\Summarization'                    => 'ai/summarization',
			);

			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace -- Compatibility bridge for ai-wp-admin versions without explicit task metadata.
			foreach ( debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 24 ) as $frame ) {
				$class = isset( $frame['class'] ) ? (string) $frame['class'] : '';
				if ( isset( $map[ $class ] ) ) {
					return $map[ $class ];
				}
			}

			return '';
		}

		/**
		 * Extracts text from the task-bound Cloud connector result.
		 *
		 * @param array<string,mixed> $response Cloud response.
		 * @param string              $expected_task Expected WordPress operation task.
		 * @return string
		 */
		private function extract_text( array $response, string $expected_task ): string {
			$result             = is_array( $response['data']['result'] ?? null ) ? $response['data']['result'] : array();
			$operation_contract = is_array( $result['operation_contract'] ?? null ) ? $result['operation_contract'] : array();
			if (
				'cloud_connector_result.v1' !== (string) ( $result['contract_version'] ?? '' )
				|| true !== ( $result['suggestion_only'] ?? null )
				|| 'npcink-cloud-addon' !== (string) ( $result['connector_id'] ?? '' )
				|| 'wordpress_operation.v1' !== (string) ( $operation_contract['contract_version'] ?? '' )
				|| $expected_task !== (string) ( $operation_contract['task'] ?? '' )
			) {
				return '';
			}

			$output = is_array( $result['output'] ?? null ) ? $result['output'] : array();

			return is_string( $output['output_text'] ?? null ) ? trim( $output['output_text'] ) : '';
		}
	}

	/**
	 * Bounded local attachment handoff for WordPress AI alt text generation.
	 */
	final class Npcink_Cloud_WordPress_AI_Alt_Text_Handoff {
		private const MAX_SOURCE_BYTES = 8388608;
		private const ALLOWED_MIME_TYPES = array( 'image/jpeg', 'image/png', 'image/webp' );

		/**
		 * Extracts one positive attachment ID from the WordPress AI ability input.
		 *
		 * @param array<string,mixed> $input Ability input.
		 * @return int|WP_Error
		 */
		public static function attachment_id_from_ability_input( array $input ) {
			if ( ! array_key_exists( 'attachment_id', $input ) ) {
				return self::source_error( 'cloud_wp_ai_alt_text_attachment_required', 'WordPress AI alt text generation requires a local WordPress attachment.' );
			}

			$value = $input['attachment_id'];
			if (
				( ! is_int( $value ) && ! is_string( $value ) )
				|| ( is_string( $value ) && 1 !== preg_match( '/^[0-9]+$/', $value ) )
			) {
				return self::source_error( 'cloud_wp_ai_alt_text_attachment_required', 'WordPress AI alt text generation requires a local WordPress attachment.' );
			}

			$attachment_id = absint( $value );
			if ( 0 >= $attachment_id ) {
				return self::source_error( 'cloud_wp_ai_alt_text_attachment_required', 'WordPress AI alt text generation requires a local WordPress attachment.' );
			}

			return $attachment_id;
		}

		/**
		 * Uploads one authorized local attachment and executes the suggestion-only scene.
		 *
		 * @param int         $attachment_id Attachment ID.
		 * @param string      $prompt Alt-text prompt.
		 * @return array<string,mixed>|WP_Error
		 */
		public static function dispatch( int $attachment_id, string $prompt ) {
			$source = self::local_source( $attachment_id, $prompt );
			if ( is_wp_error( $source ) ) {
				return $source;
			}

			$trace_id = 'trace_wp_ai_vision_' . wp_generate_uuid4();
			if ( ! function_exists( 'npcink_cloud_addon_upload_wordpress_ai_alt_text_source' ) || ! function_exists( 'npcink_cloud_addon_execute_wordpress_ai_connector_runtime' ) ) {
				return new WP_Error( 'cloud_wp_ai_alt_text_verified_client_required', __( 'WordPress AI alt text generation requires verified Npcink Cloud settings.', 'npcink-cloud-addon' ), array( 'status' => 503 ) );
			}
			$artifact = npcink_cloud_addon_upload_wordpress_ai_alt_text_source(
				$source['file'],
				$trace_id,
				'wp_ai_vision_upload_' . wp_generate_uuid4()
			);
			unset( $source['file'] );
			if ( is_wp_error( $artifact ) ) {
				return $artifact;
			}
			$artifact_id = is_array( $artifact ) && is_string( $artifact['artifact_id'] ?? null )
				? $artifact['artifact_id']
				: '';
			if ( 1 !== preg_match( '/^art_[0-9a-f]{32}$/', $artifact_id ) ) {
				return new WP_Error(
					'cloud_wp_ai_alt_text_artifact_invalid',
					__( 'Npcink Cloud did not return a valid source artifact for alt text generation.', 'npcink-cloud-addon' ),
					array( 'status' => 502 )
				);
			}
			$task_contract = function_exists( 'npcink_cloud_addon_project_ai_task_contract' )
				? npcink_cloud_addon_project_ai_task_contract( 'ai/alt-text-generation' )
				: null;
			if ( is_wp_error( $task_contract ) ) {
				return $task_contract;
			}
			if ( null !== $task_contract && ! is_array( $task_contract ) ) {
				return new WP_Error( 'cloud_wp_ai_alt_text_task_contract_invalid', __( 'Npcink Cloud could not project the alt-text Ability contract.', 'npcink-cloud-addon' ), array( 'status' => 500 ) );
			}

			$request = array(
				'contract_version'   => 'cloud_connector_runtime.v1',
				'profile_id'         => 'vision.ai',
				'operation_contract' => array(
					'contract_version' => 'wordpress_operation.v1',
					'task'             => 'alt_text_suggest',
					'request'          => array(
						'source_artifact_id' => $artifact_id,
						'prompt'             => $source['prompt'],
						'filename'           => $source['filename'],
						'title'              => $source['title'],
						'existing_alt'       => $source['existing_alt'],
						'existing_caption'   => $source['existing_caption'],
						'locale'             => $source['locale'],
					),
				),
				'timeout_seconds'    => 60,
				'retention_ttl'      => 86400,
				'retry_max'          => 0,
			);
			if ( is_array( $task_contract ) ) {
				$request['operation_contract']['request'] = array( 'task_contract' => $task_contract ) + $request['operation_contract']['request'];
			}

			return npcink_cloud_addon_execute_wordpress_ai_connector_runtime(
				$request,
				$trace_id,
				'wp_ai_vision_execute_' . wp_generate_uuid4()
			);
		}

		/**
		 * Validates and reads one local WordPress attachment without exposing its path.
		 *
		 * @param int    $attachment_id Attachment ID.
		 * @param string $prompt Alt-text prompt.
		 * @return array<string,mixed>|WP_Error
		 */
		private static function local_source( int $attachment_id, string $prompt ) {
			$prompt = trim( $prompt );
			if ( 0 >= $attachment_id || '' === $prompt ) {
				return self::source_error( 'cloud_wp_ai_alt_text_input_invalid', 'WordPress AI alt text generation requires one bounded attachment prompt.' );
			}
			$prompt = self::bounded_text( $prompt, 500 );
			if ( ! current_user_can( 'edit_post', $attachment_id ) ) {
				return self::source_error( 'cloud_wp_ai_alt_text_attachment_forbidden', 'You are not allowed to use this attachment for Cloud alt text generation.', 403 );
			}

			$attachment = get_post( $attachment_id );
			if ( ! is_object( $attachment ) || 'attachment' !== (string) ( $attachment->post_type ?? '' ) ) {
				return self::source_error( 'cloud_wp_ai_alt_text_attachment_invalid', 'WordPress AI alt text generation requires a local media attachment.' );
			}

			$file_path  = get_attached_file( $attachment_id );
			$upload_dir = wp_upload_dir();
			$real_path  = is_string( $file_path ) ? realpath( $file_path ) : false;
			$real_base  = is_array( $upload_dir ) && is_string( $upload_dir['basedir'] ?? null ) ? realpath( $upload_dir['basedir'] ) : false;
			if (
				false === $real_path
				|| false === $real_base
				|| 0 !== strpos( $real_path, rtrim( $real_base, DIRECTORY_SEPARATOR ) . DIRECTORY_SEPARATOR )
				|| ! is_file( $real_path )
				|| ! is_readable( $real_path )
			) {
				return self::source_error( 'cloud_wp_ai_alt_text_attachment_file_invalid', 'The local attachment file is unavailable for Cloud alt text generation.' );
			}

			$path_stat = @stat( $real_path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- A raced file removal fails closed below.
			$size      = is_array( $path_stat ) ? (int) ( $path_stat['size'] ?? 0 ) : 0;
			$is_regular_file = is_array( $path_stat )
				&& 0100000 === ( (int) ( $path_stat['mode'] ?? 0 ) & 0170000 );
			if ( ! $is_regular_file || 0 >= $size || $size > self::MAX_SOURCE_BYTES ) {
				return self::source_error( 'cloud_wp_ai_alt_text_attachment_size_invalid', 'The local attachment exceeds the Cloud alt text source size limit.', 413 );
			}

			$stored_mime = sanitize_mime_type( (string) get_post_mime_type( $attachment_id ) );

			$handle = @fopen( $real_path, 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen,WordPress.PHP.NoSilencedErrors.Discouraged -- Reads one locally authorized, size-bounded attachment; a raced removal fails closed below.
			if ( false === $handle ) {
				return self::source_error( 'cloud_wp_ai_alt_text_attachment_read_failed', 'The local attachment could not be read for Cloud alt text generation.' );
			}

			$handle_stat  = fstat( $handle );
			$stat_matches = is_array( $handle_stat );
			foreach ( array( 'dev', 'ino', 'mode', 'size' ) as $stat_field ) {
				if ( ! $stat_matches || (int) ( $path_stat[ $stat_field ] ?? -1 ) !== (int) ( $handle_stat[ $stat_field ] ?? -2 ) ) {
					$stat_matches = false;
					break;
				}
			}
			if ( ! $stat_matches ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closes the rejected local attachment handle immediately.
				fclose( $handle );
				return self::source_error( 'cloud_wp_ai_alt_text_attachment_file_changed', 'The local attachment changed before it could be read for Cloud alt text generation.', 409 );
			}

			$contents    = '';
			$read_failed = false;
			while ( ! feof( $handle ) && strlen( $contents ) <= self::MAX_SOURCE_BYTES ) {
				$remaining = self::MAX_SOURCE_BYTES + 1 - strlen( $contents );
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread -- Bounded loop prevents partial reads and caps bytes at MAX+1.
				$chunk = fread( $handle, min( 8192, $remaining ) );
				if ( false === $chunk || ( '' === $chunk && ! feof( $handle ) ) ) {
					$read_failed = true;
					break;
				}
				$contents .= $chunk;
			}
			$reached_eof = feof( $handle );
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closes the bounded local attachment handle immediately.
			fclose( $handle );
			if ( $read_failed || ! $reached_eof || '' === $contents || strlen( $contents ) !== $size || strlen( $contents ) > self::MAX_SOURCE_BYTES ) {
				return self::source_error( 'cloud_wp_ai_alt_text_attachment_read_failed', 'The local attachment could not be read for Cloud alt text generation.' );
			}

			$image_info = @getimagesizefromstring( $contents ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Invalid image bytes are expected to fail closed below.
			$detected_mime = is_array( $image_info ) ? sanitize_mime_type( (string) ( $image_info['mime'] ?? '' ) ) : '';
			if ( $stored_mime !== $detected_mime || ! in_array( $detected_mime, self::ALLOWED_MIME_TYPES, true ) ) {
				return self::source_error( 'cloud_wp_ai_alt_text_attachment_mime_invalid', 'The local attachment image type is not supported for Cloud alt text generation.' );
			}

			// Keep real editor images inside small vision models' context budgets.
			// The original attachment remains untouched; only the transient artifact
			// sent to Cloud is bounded to 768px on its longest edge.
			if ( is_array( $image_info ) && max( absint( $image_info[0] ?? 0 ), absint( $image_info[1] ?? 0 ) ) > 768 && function_exists( 'wp_get_image_editor' ) ) {
				$editor = wp_get_image_editor( $real_path );
				if ( ! is_wp_error( $editor ) && ! is_wp_error( $editor->resize( 768, 768, false ) ) ) {
					$temp_path = function_exists( 'wp_tempnam' ) ? wp_tempnam( $real_path ) : tempnam( sys_get_temp_dir(), 'npcink-alt-' );
					$saved = is_string( $temp_path ) && '' !== $temp_path ? $editor->save( $temp_path, $detected_mime ) : false;
					$saved_path = is_array( $saved ) ? (string) ( $saved['path'] ?? '' ) : '';
					if ( '' !== $saved_path && ! is_wp_error( $saved ) && is_readable( $saved_path ) ) {
						$bounded_contents = file_get_contents( $saved_path );
						$bounded_info = is_string( $bounded_contents ) ? @getimagesizefromstring( $bounded_contents ) : false;
						if ( is_array( $bounded_info ) && ( $bounded_info['mime'] ?? '' ) === $detected_mime && strlen( $bounded_contents ) <= self::MAX_SOURCE_BYTES ) {
							$contents = $bounded_contents;
						}
					}
					foreach ( array( $temp_path, $saved_path ) as $temporary_file ) {
						if ( is_string( $temporary_file ) && '' !== $temporary_file && is_file( $temporary_file ) ) {
							wp_delete_file( $temporary_file );
						}
					}
				}
			}

			return array(
				'file'             => array(
					'contents'  => $contents,
					'filename'  => sanitize_file_name( basename( $real_path ) ),
					'mime_type' => $detected_mime,
				),
				'prompt'           => $prompt,
				'filename'         => self::bounded_text( sanitize_file_name( basename( $real_path ) ), 160 ),
				'title'            => self::bounded_text( sanitize_text_field( (string) ( $attachment->post_title ?? '' ) ), 160 ),
				'existing_alt'     => self::bounded_text( sanitize_text_field( (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) ), 240 ),
				'existing_caption' => self::bounded_text( sanitize_text_field( (string) ( $attachment->post_excerpt ?? '' ) ), 240 ),
				'locale'           => self::bounded_text( function_exists( 'get_locale' ) ? sanitize_text_field( (string) get_locale() ) : '', 32 ),
			);
		}

		/** @return WP_Error */
		private static function source_error( string $code, string $message, int $status = 400 ): WP_Error {
			$translated_messages = array(
				'cloud_wp_ai_alt_text_attachment_required'     => __( 'WordPress AI alt text generation requires a local WordPress attachment.', 'npcink-cloud-addon' ),
				'cloud_wp_ai_alt_text_input_invalid'           => __( 'WordPress AI alt text generation requires one bounded attachment prompt.', 'npcink-cloud-addon' ),
				'cloud_wp_ai_alt_text_attachment_forbidden'    => __( 'You are not allowed to use this attachment for Cloud alt text generation.', 'npcink-cloud-addon' ),
				'cloud_wp_ai_alt_text_attachment_invalid'      => __( 'WordPress AI alt text generation requires a local media attachment.', 'npcink-cloud-addon' ),
				'cloud_wp_ai_alt_text_attachment_file_invalid' => __( 'The local attachment file is unavailable for Cloud alt text generation.', 'npcink-cloud-addon' ),
				'cloud_wp_ai_alt_text_attachment_size_invalid' => __( 'The local attachment exceeds the Cloud alt text source size limit.', 'npcink-cloud-addon' ),
				'cloud_wp_ai_alt_text_attachment_read_failed'  => __( 'The local attachment could not be read for Cloud alt text generation.', 'npcink-cloud-addon' ),
				'cloud_wp_ai_alt_text_attachment_file_changed' => __( 'The local attachment changed before it could be read for Cloud alt text generation.', 'npcink-cloud-addon' ),
				'cloud_wp_ai_alt_text_attachment_mime_invalid' => __( 'The local attachment image type is not supported for Cloud alt text generation.', 'npcink-cloud-addon' ),
			);

			return new WP_Error( $code, $translated_messages[ $code ] ?? $message, array( 'status' => $status ) );
		}

		private static function bounded_text( string $value, int $max_length ): string {
			if ( 0 >= $max_length || '' === $value ) {
				return '';
			}
			if ( function_exists( 'mb_substr' ) ) {
				return mb_substr( $value, 0, $max_length );
			}

			$matches = array();
			$matched = preg_match( '/\A.{0,' . $max_length . '}/us', $value, $matches );

			return 1 === $matched && is_string( $matches[0] ?? null ) ? $matches[0] : '';
		}
	}

	/**
	 * Scene-gated vision text model for WordPress AI alt text generation.
	 */
	final class Npcink_Cloud_WordPress_AI_Vision_Text_Model implements
		\WordPress\AiClient\Providers\Models\Contracts\ModelInterface,
		\WordPress\AiClient\Providers\Models\TextGeneration\Contracts\TextGenerationModelInterface {
		/**
		 * Model metadata.
		 *
		 * @var \WordPress\AiClient\Providers\Models\DTO\ModelMetadata
		 */
		private $metadata;

		/**
		 * Provider metadata.
		 *
		 * @var \WordPress\AiClient\Providers\DTO\ProviderMetadata
		 */
		private $provider_metadata;

		/**
		 * Model config.
		 *
		 * @var \WordPress\AiClient\Providers\Models\DTO\ModelConfig
		 */
		private $config;

		/**
		 * Constructor.
		 *
		 * @param \WordPress\AiClient\Providers\Models\DTO\ModelMetadata $metadata Model metadata.
		 * @param \WordPress\AiClient\Providers\DTO\ProviderMetadata     $provider_metadata Provider metadata.
		 */
		public function __construct(
			\WordPress\AiClient\Providers\Models\DTO\ModelMetadata $metadata,
			\WordPress\AiClient\Providers\DTO\ProviderMetadata $provider_metadata
		) {
			$this->metadata          = $metadata;
			$this->provider_metadata = $provider_metadata;
			$this->config            = new \WordPress\AiClient\Providers\Models\DTO\ModelConfig();
		}

		/**
		 * Gets model metadata.
		 *
		 * @return \WordPress\AiClient\Providers\Models\DTO\ModelMetadata
		 */
		public function metadata(): \WordPress\AiClient\Providers\Models\DTO\ModelMetadata {
			return $this->metadata;
		}

		/**
		 * Gets provider metadata.
		 *
		 * @return \WordPress\AiClient\Providers\DTO\ProviderMetadata
		 */
		public function providerMetadata(): \WordPress\AiClient\Providers\DTO\ProviderMetadata {
			return $this->provider_metadata;
		}

		/**
		 * Sets model config.
		 *
		 * @param \WordPress\AiClient\Providers\Models\DTO\ModelConfig $config Model config.
		 * @return void
		 */
		public function setConfig( \WordPress\AiClient\Providers\Models\DTO\ModelConfig $config ): void {
			$this->config = $config;
		}

		/**
		 * Gets model config.
		 *
		 * @return \WordPress\AiClient\Providers\Models\DTO\ModelConfig
		 */
		public function getConfig(): \WordPress\AiClient\Providers\Models\DTO\ModelConfig {
			return $this->config;
		}

		/**
		 * Generates alt text through the bounded Cloud vision runtime seam.
		 *
		 * @param list<\WordPress\AiClient\Messages\DTO\Message> $prompt Prompt messages.
		 * @return \WordPress\AiClient\Results\DTO\GenerativeAiResult
		 */
		public function generateTextResult( array $prompt ): \WordPress\AiClient\Results\DTO\GenerativeAiResult {
			// Clear a prior ability's evidence before any validation or runtime call.
			Npcink_Cloud_WordPress_AI_Connector::record_cloud_run_id( '' );
			Npcink_Cloud_WordPress_AI_Connector::reset_runtime_failure_evidence();
			$ability_input = Npcink_Cloud_WordPress_AI_Connector::consume_alt_text_ability_context();
			if ( array() === $ability_input ) {
				throw new \WordPress\AiClient\Common\Exception\RuntimeException( Npcink_Cloud_WordPress_AI_Connector::user_facing_connector_error( 'scene_not_supported', 'cloud_wp_ai_scene_not_supported' ) );
			}

			if ( 1 !== count( $prompt ) ) {
				throw new \WordPress\AiClient\Common\Exception\RuntimeException( Npcink_Cloud_WordPress_AI_Connector::user_facing_connector_error( 'chat_history_not_supported', 'cloud_wp_ai_chat_history_not_supported' ) );
			}

			if ( null !== $this->config->getFunctionDeclarations() || null !== $this->config->getWebSearch() ) {
				throw new \WordPress\AiClient\Common\Exception\RuntimeException( Npcink_Cloud_WordPress_AI_Connector::user_facing_connector_error( 'tools_not_supported', 'cloud_wp_ai_tools_not_supported' ) );
			}

			$text = $this->prompt_text( $prompt );
			if ( '' === $text ) {
				throw new \WordPress\AiClient\Common\Exception\RuntimeException( Npcink_Cloud_WordPress_AI_Connector::user_facing_connector_error( 'scene_input_required', 'cloud_wp_ai_scene_input_required' ) );
			}

			$attachment_id = Npcink_Cloud_WordPress_AI_Alt_Text_Handoff::attachment_id_from_ability_input( $ability_input );
			if ( is_wp_error( $attachment_id ) ) {
				throw new \WordPress\AiClient\Common\Exception\RuntimeException( Npcink_Cloud_WordPress_AI_Connector::user_facing_attachment_error( $attachment_id ) );
			}

			$started  = Npcink_Cloud_WordPress_AI_Connector::runtime_timer_start();
			$response = Npcink_Cloud_WordPress_AI_Alt_Text_Handoff::dispatch( $attachment_id, $text );
			$log_event = array(
					'type'                       => 'vision',
					'operation'                  => 'npcink-cloud/connector-runtime',
					'task'                       => 'alt_text_suggest',
					'contract_version'           => 'cloud_connector_runtime.v1',
					'operation_contract_version' => 'wordpress_operation.v1',
					'response'                   => $response,
					'duration_ms'                => Npcink_Cloud_WordPress_AI_Connector::runtime_timer_elapsed_ms( $started ),
					'fallback_model_id'          => Npcink_Cloud_WordPress_AI_Connector::VISION_MODEL_ID,
				);

			if ( is_wp_error( $response ) ) {
				$evidence = Npcink_Cloud_WordPress_AI_Connector::record_runtime_failure_from_wp_error( $response );
				$log_event['cloud_run_id'] = (string) ( $evidence['run_id'] ?? '' );
				Npcink_Cloud_WordPress_AI_Connector::maybe_log_wordpress_ai_request_evidence( $log_event );
				throw new \WordPress\AiClient\Common\Exception\RuntimeException( Npcink_Cloud_WordPress_AI_Connector::user_facing_runtime_failure( (string) $response->get_error_code(), (string) ( $evidence['cloud_error_code'] ?? '' ), (string) $response->get_error_message() ) );
			}

			$output_text = $this->extract_text( is_array( $response ) ? $response : array(), 'alt_text_suggest' );
			if ( '' === $output_text ) {
				$cloud_run_id = Npcink_Cloud_WordPress_AI_Connector::cloud_run_id_from_response( $response );
				Npcink_Cloud_WordPress_AI_Connector::record_cloud_run_id( $cloud_run_id );
				Npcink_Cloud_WordPress_AI_Connector::record_runtime_failure_evidence( array(
					'run_id'         => $cloud_run_id,
					'error_stage'    => 'output_validation',
					'quality_reason' => 'output_missing',
				) );
				$log_event['validation_error'] = new WP_Error( 'cloud_wp_ai_output_missing', 'Cloud response did not include valid alt text output.' );
				Npcink_Cloud_WordPress_AI_Connector::maybe_log_wordpress_ai_request_evidence( $log_event );
				throw new \WordPress\AiClient\Common\Exception\RuntimeException( Npcink_Cloud_WordPress_AI_Connector::user_facing_connector_error( 'output_missing', 'cloud_wp_ai_alt_text_output_missing' ) );
			}

			Npcink_Cloud_WordPress_AI_Connector::maybe_log_wordpress_ai_request_evidence( $log_event );
			$cloud_run_id = Npcink_Cloud_WordPress_AI_Connector::cloud_run_id_from_response( $response );
			Npcink_Cloud_WordPress_AI_Connector::record_cloud_run_id( $cloud_run_id );
			$run_id = '' !== $cloud_run_id ? $cloud_run_id : wp_generate_uuid4();
			return new \WordPress\AiClient\Results\DTO\GenerativeAiResult(
				$run_id,
				array(
					new \WordPress\AiClient\Results\DTO\Candidate(
						new \WordPress\AiClient\Messages\DTO\ModelMessage(
							array( new \WordPress\AiClient\Messages\DTO\MessagePart( $output_text ) )
						),
						\WordPress\AiClient\Results\Enums\FinishReasonEnum::stop()
					),
				),
				new \WordPress\AiClient\Results\DTO\TokenUsage( 0, 0, 0 ),
				$this->provider_metadata,
				$this->metadata,
				array(
					'contract_version'       => 'cloud_connector_result.v1',
					'task'                   => 'alt_text_suggest',
					'suggestion_only'        => true,
					'direct_wordpress_write' => false,
				)
			);
		}

		/**
		 * Extracts text from a single user prompt.
		 *
		 * @param list<\WordPress\AiClient\Messages\DTO\Message> $prompt Prompt messages.
		 * @return string
		 */
		private function prompt_text( array $prompt ): string {
			$message = $prompt[0];
			if ( ! $message->getRole()->isUser() ) {
				return '';
			}

			$parts = array();
			foreach ( $message->getParts() as $part ) {
				$text = $part->getText();
				if ( null !== $text && '' !== trim( $text ) ) {
					$parts[] = trim( $text );
				}
			}

			return trim( implode( "\n\n", $parts ) );
		}

		/**
		 * Extracts text from the task-bound Cloud connector result.
		 *
		 * @param array<string,mixed> $response Cloud response.
		 * @param string              $expected_task Expected WordPress operation task.
		 * @return string
		 */
		private function extract_text( array $response, string $expected_task ): string {
			$result             = is_array( $response['data']['result'] ?? null ) ? $response['data']['result'] : array();
			$operation_contract = is_array( $result['operation_contract'] ?? null ) ? $result['operation_contract'] : array();
			if (
				'cloud_connector_result.v1' !== (string) ( $result['contract_version'] ?? '' )
				|| true !== ( $result['suggestion_only'] ?? null )
				|| 'npcink-cloud-addon' !== (string) ( $result['connector_id'] ?? '' )
				|| 'wordpress_operation.v1' !== (string) ( $operation_contract['contract_version'] ?? '' )
				|| $expected_task !== (string) ( $operation_contract['task'] ?? '' )
			) {
				return '';
			}

			$output = is_array( $result['output'] ?? null ) ? $result['output'] : array();

			return is_string( $output['output_text'] ?? null ) ? trim( $output['output_text'] ) : '';
		}
	}

	/**
	 * Scene-gated image model that forwards text-to-image WordPress AI calls to Cloud.
	 */
	final class Npcink_Cloud_WordPress_AI_Image_Model implements
		\WordPress\AiClient\Providers\Models\Contracts\ModelInterface,
		\WordPress\AiClient\Providers\Models\ImageGeneration\Contracts\ImageGenerationModelInterface {
		private const ARTIFACT_ID_PATTERN = '/^art_[0-9a-f]{32}$/';
		private const DELIVERY_ID_PATTERN = '/^mdl_[0-9a-f]{32}$/';
		private const MAX_IMAGE_BYTES = 26214400;
		private const MAX_IMAGE_AGGREGATE_BYTES = 33554432;
		private const MAX_IMAGE_AXIS = 8192;
		private const MAX_IMAGE_AREA = 16777216;
		private const MAX_IMAGE_CANDIDATES = 4;
		private const IMAGE_ARTIFACT_KEYS = array(
			'artifact_id',
			'artifact_reference',
			'status',
			'media_kind',
			'operation',
			'content_type',
			'format',
			'width',
			'height',
			'filesize_bytes',
			'checksum',
			'expires_at',
		);

		/**
		 * Model metadata.
		 *
		 * @var \WordPress\AiClient\Providers\Models\DTO\ModelMetadata
		 */
		private $metadata;

		/**
		 * Provider metadata.
		 *
		 * @var \WordPress\AiClient\Providers\DTO\ProviderMetadata
		 */
		private $provider_metadata;

		/**
		 * Model config.
		 *
		 * @var \WordPress\AiClient\Providers\Models\DTO\ModelConfig
		 */
		private $config;

		/**
		 * Constructor.
		 *
		 * @param \WordPress\AiClient\Providers\Models\DTO\ModelMetadata $metadata Model metadata.
		 * @param \WordPress\AiClient\Providers\DTO\ProviderMetadata     $provider_metadata Provider metadata.
		 */
		public function __construct(
			\WordPress\AiClient\Providers\Models\DTO\ModelMetadata $metadata,
			\WordPress\AiClient\Providers\DTO\ProviderMetadata $provider_metadata
		) {
			$this->metadata          = $metadata;
			$this->provider_metadata = $provider_metadata;
			$this->config            = new \WordPress\AiClient\Providers\Models\DTO\ModelConfig();
		}

		/**
		 * Gets model metadata.
		 *
		 * @return \WordPress\AiClient\Providers\Models\DTO\ModelMetadata
		 */
		public function metadata(): \WordPress\AiClient\Providers\Models\DTO\ModelMetadata {
			return $this->metadata;
		}

		/**
		 * Gets provider metadata.
		 *
		 * @return \WordPress\AiClient\Providers\DTO\ProviderMetadata
		 */
		public function providerMetadata(): \WordPress\AiClient\Providers\DTO\ProviderMetadata {
			return $this->provider_metadata;
		}

		/**
		 * Sets model config.
		 *
		 * @param \WordPress\AiClient\Providers\Models\DTO\ModelConfig $config Model config.
		 * @return void
		 */
		public function setConfig( \WordPress\AiClient\Providers\Models\DTO\ModelConfig $config ): void {
			$this->config = $config;
		}

		/**
		 * Gets model config.
		 *
		 * @return \WordPress\AiClient\Providers\Models\DTO\ModelConfig
		 */
		public function getConfig(): \WordPress\AiClient\Providers\Models\DTO\ModelConfig {
			return $this->config;
		}

		/**
		 * Generates an image result through the bounded Cloud runtime seam.
		 *
		 * @param list<\WordPress\AiClient\Messages\DTO\Message> $prompt Prompt messages.
		 * @return \WordPress\AiClient\Results\DTO\GenerativeAiResult
		 */
		public function generateImageResult( array $prompt ): \WordPress\AiClient\Results\DTO\GenerativeAiResult {
			Npcink_Cloud_WordPress_AI_Connector::record_cloud_run_id( '' );
			Npcink_Cloud_WordPress_AI_Connector::reset_runtime_failure_evidence();
			if ( 1 !== count( $prompt ) ) {
				throw new \WordPress\AiClient\Common\Exception\RuntimeException( Npcink_Cloud_WordPress_AI_Connector::user_facing_connector_error( 'chat_history_not_supported', 'cloud_wp_ai_chat_history_not_supported' ) );
			}

			if ( null !== $this->config->getFunctionDeclarations() || null !== $this->config->getWebSearch() ) {
				throw new \WordPress\AiClient\Common\Exception\RuntimeException( Npcink_Cloud_WordPress_AI_Connector::user_facing_connector_error( 'tools_not_supported', 'cloud_wp_ai_tools_not_supported' ) );
			}

			$text = $this->prompt_text( $prompt );
			if ( '' === $text ) {
				throw new \WordPress\AiClient\Common\Exception\RuntimeException( Npcink_Cloud_WordPress_AI_Connector::user_facing_connector_error( 'scene_input_required', 'cloud_wp_ai_scene_input_required' ) );
			}

			$request = array(
				'contract_version' => 'image_generation_request.v1',
				'task'             => 'image_generation',
				'prompt'           => $text,
				'n'                => $this->image_count(),
				'aspect_ratio'     => $this->aspect_ratio(),
				'resolution'       => 'medium',
				'timeout_seconds'  => 90,
				'retention_ttl'    => 86400,
			);

			$trace_id = 'trace_wp_ai_image_' . wp_generate_uuid4();
			$started  = Npcink_Cloud_WordPress_AI_Connector::runtime_timer_start();
			$response = npcink_cloud_addon_execute_wordpress_ai_image_generation_runtime(
				$request,
				$trace_id,
				'wp_ai_image_' . wp_generate_uuid4()
			);
			$log_event = array(
					'type'             => 'image',
					'operation'        => 'npcink-cloud/generate-image',
					'task'             => 'image_generation',
					'contract_version' => 'image_generation_request.v1',
					'response'         => $response,
					'duration_ms'      => Npcink_Cloud_WordPress_AI_Connector::runtime_timer_elapsed_ms( $started ),
					'fallback_model_id' => Npcink_Cloud_WordPress_AI_Connector::IMAGE_MODEL_ID,
				);

			if ( is_wp_error( $response ) ) {
				$evidence = Npcink_Cloud_WordPress_AI_Connector::record_runtime_failure_from_wp_error( $response );
				$log_event['cloud_run_id'] = (string) ( $evidence['run_id'] ?? '' );
				Npcink_Cloud_WordPress_AI_Connector::maybe_log_wordpress_ai_request_evidence( $log_event );
				throw new \WordPress\AiClient\Common\Exception\RuntimeException( Npcink_Cloud_WordPress_AI_Connector::user_facing_runtime_failure( (string) $response->get_error_code(), (string) ( $evidence['cloud_error_code'] ?? '' ), (string) $response->get_error_message() ) );
			}

				$result     = $this->extract_result( is_array( $response ) ? $response : array() );
				try {
					$candidates = $this->extract_image_candidates( $result, $trace_id );
					if ( empty( $candidates ) ) {
						throw new \WordPress\AiClient\Common\Exception\RuntimeException( Npcink_Cloud_WordPress_AI_Connector::user_facing_connector_error( 'output_missing', 'cloud_wp_ai_image_output_missing' ) );
					}
				} catch ( \Throwable $error ) {
				$cloud_run_id = Npcink_Cloud_WordPress_AI_Connector::cloud_run_id_from_response( $response );
				Npcink_Cloud_WordPress_AI_Connector::record_cloud_run_id( $cloud_run_id );
				Npcink_Cloud_WordPress_AI_Connector::record_runtime_failure_evidence( array(
					'run_id'         => $cloud_run_id,
					'error_stage'    => 'artifact_validation',
					'quality_reason' => 'image_output_invalid',
				) );
				$log_event['validation_error'] = new WP_Error( 'cloud_wp_ai_image_output_invalid', 'Cloud image output could not be downloaded or verified.' );
				$log_event['duration_ms'] = Npcink_Cloud_WordPress_AI_Connector::runtime_timer_elapsed_ms( $started );
				Npcink_Cloud_WordPress_AI_Connector::maybe_log_wordpress_ai_request_evidence( $log_event );
				throw $error;
			}

			$log_event['duration_ms'] = Npcink_Cloud_WordPress_AI_Connector::runtime_timer_elapsed_ms( $started );
			Npcink_Cloud_WordPress_AI_Connector::maybe_log_wordpress_ai_request_evidence( $log_event );
			$cloud_run_id = Npcink_Cloud_WordPress_AI_Connector::cloud_run_id_from_response( $response );
			return new \WordPress\AiClient\Results\DTO\GenerativeAiResult(
				'' !== $cloud_run_id ? $cloud_run_id : wp_generate_uuid4(),
				$candidates,
				new \WordPress\AiClient\Results\DTO\TokenUsage( 0, 0, 0 ),
				$this->provider_metadata,
				$this->metadata,
				array(
					'contract_version'          => 'image_generation_result.v1',
					'task'                      => 'image_generation',
					'suggestion_only'           => true,
					'direct_wordpress_write'    => false,
					'model_id'                  => (string) ( $result['model_id'] ?? '' ),
					'provider_response_format'  => (string) ( $result['provider_response_format'] ?? '' ),
				)
			);
		}

		/**
		 * Extracts text from a single user prompt and rejects reference-image refinement.
		 *
		 * @param list<\WordPress\AiClient\Messages\DTO\Message> $prompt Prompt messages.
		 * @return string
		 */
		private function prompt_text( array $prompt ): string {
			$message = $prompt[0];
			if ( ! $message->getRole()->isUser() ) {
				return '';
			}

				$parts = array();
				foreach ( $message->getParts() as $part ) {
					if ( null !== $part->getFile() ) {
						throw new \WordPress\AiClient\Common\Exception\RuntimeException( Npcink_Cloud_WordPress_AI_Connector::user_facing_connector_error( 'reference_image_not_supported', 'cloud_wp_ai_reference_image_not_supported' ) );
					}

				$text = $part->getText();
				if ( null !== $text && '' !== trim( $text ) ) {
					$parts[] = trim( $text );
				}
			}

			return trim( implode( "\n\n", $parts ) );
		}

		/**
		 * Returns the requested image candidate count.
		 *
		 * @return int
		 */
		private function image_count(): int {
			$count = $this->config->getCandidateCount();

			return min( 4, max( 1, null === $count ? 1 : (int) $count ) );
		}

		/**
		 * Returns a Cloud-supported aspect ratio.
		 *
		 * @return string
		 */
		private function aspect_ratio(): string {
			$aspect_ratio = $this->config->getOutputMediaAspectRatio();
			if ( is_string( $aspect_ratio ) && '' !== $aspect_ratio ) {
				return $aspect_ratio;
			}

			$orientation = $this->config->getOutputMediaOrientation();
			if ( null !== $orientation ) {
				if ( $orientation->isLandscape() ) {
					return '16:9';
				}
				if ( $orientation->isPortrait() ) {
					return '9:16';
				}
			}

			return '1:1';
		}

		/**
		 * Extracts the Cloud image result payload.
		 *
		 * @param array<string,mixed> $response Cloud response.
		 * @return array<string,mixed>
		 */
		private function extract_result( array $response ): array {
			if ( isset( $response['data']['result'] ) && is_array( $response['data']['result'] ) ) {
				return $response['data']['result'];
			}
			if ( isset( $response['result'] ) && is_array( $response['result'] ) ) {
				return $response['result'];
			}
			if ( isset( $response['data'] ) && is_array( $response['data'] ) ) {
				return $response['data'];
			}

			return $response;
		}

		/**
		 * Extracts image candidates from a Cloud image generation response.
		 *
		 * @param array<string,mixed> $result Cloud result payload.
		 * @param string              $trace_id Runtime trace id.
		 * @return list<\WordPress\AiClient\Results\DTO\Candidate>
		 */
		private function extract_image_candidates( array $result, string $trace_id = '' ): array {
			$images = $this->download_artifact_images( $result, $trace_id );

			$candidates = array();
			foreach ( $images as $image ) {
				if ( ! is_array( $image ) ) {
					continue;
				}

				$mime_type = isset( $image['mime_type'] ) && is_string( $image['mime_type'] ) && '' !== $image['mime_type']
					? $image['mime_type']
					: 'image/png';
				$file_data = isset( $image['b64_json'] ) && is_string( $image['b64_json'] )
					? $image['b64_json']
					: '';

				if ( '' === $file_data ) {
					continue;
				}

				$file = new \WordPress\AiClient\Files\DTO\File( $file_data, $mime_type );
				$candidates[] = new \WordPress\AiClient\Results\DTO\Candidate(
					new \WordPress\AiClient\Messages\DTO\Message(
						\WordPress\AiClient\Messages\Enums\MessageRoleEnum::model(),
						array( new \WordPress\AiClient\Messages\DTO\MessagePart( $file ) )
					),
					\WordPress\AiClient\Results\Enums\FinishReasonEnum::stop()
				);
			}

			return $candidates;
		}

		/**
		 * Downloads, verifies, and acknowledges Cloud image-generation artifacts.
		 *
		 * This is verified transport only. It returns inline preview bytes and does
		 * not import, persist, approve, or write a WordPress media object.
		 *
		 * @param array<string,mixed> $result Cloud image result payload.
		 * @param string              $trace_id Runtime trace id.
		 * @return list<array{b64_json:string,mime_type:string}>
		 */
		private function download_artifact_images( array $result, string $trace_id ): array {
			if (
				'image_generation_result.v1' !== (string) ( $result['contract_version'] ?? '' )
				|| 'image_generation_artifacts' !== (string) ( $result['artifact_type'] ?? '' )
				|| 'image.generate.v1' !== (string) ( $result['operation'] ?? '' )
				|| true !== ( $result['suggestion_only'] ?? null )
				|| true !== ( $result['requires_local_review'] ?? null )
				|| ! is_array( $result['artifacts'] ?? null )
				|| count( $result['artifacts'] ) > self::MAX_IMAGE_CANDIDATES
			) {
				return array();
			}

			$aggregate_bytes = 0;
			foreach ( $result['artifacts'] as $artifact ) {
				$artifact_bytes = is_array( $artifact ) ? ( $artifact['filesize_bytes'] ?? null ) : null;
				if ( ! is_int( $artifact_bytes ) || $artifact_bytes < 1 || $artifact_bytes > self::MAX_IMAGE_BYTES ) {
					throw new \WordPress\AiClient\Common\Exception\RuntimeException( Npcink_Cloud_WordPress_AI_Connector::user_facing_connector_error( 'artifact_contract_invalid', 'cloud_wp_ai_image_artifact_invalid' ) );
				}
				$aggregate_bytes += $artifact_bytes;
				if ( $aggregate_bytes > self::MAX_IMAGE_AGGREGATE_BYTES ) {
					throw new \WordPress\AiClient\Common\Exception\RuntimeException( Npcink_Cloud_WordPress_AI_Connector::user_facing_connector_error( 'artifact_preview_limit', 'cloud_wp_ai_image_preview_limit' ) );
				}
			}

			$client = Npcink_Cloud_Media_Derivative_Transport::verified_client();
			if ( is_wp_error( $client ) ) {
				throw new \WordPress\AiClient\Common\Exception\RuntimeException( Npcink_Cloud_WordPress_AI_Connector::user_facing_runtime_failure( (string) $client->get_error_code(), '', (string) $client->get_error_message() ) );
			}

			$images = array();
			foreach ( $result['artifacts'] as $artifact ) {
				if ( ! is_array( $artifact ) ) {
					throw new \WordPress\AiClient\Common\Exception\RuntimeException( Npcink_Cloud_WordPress_AI_Connector::user_facing_connector_error( 'artifact_contract_invalid', 'cloud_wp_ai_image_artifact_invalid' ) );
				}

				$artifact_contract = $artifact;
				if ( array_key_exists( 'purged_at', $artifact_contract ) ) {
					if ( null !== $artifact_contract['purged_at'] ) {
						throw new \WordPress\AiClient\Common\Exception\RuntimeException( Npcink_Cloud_WordPress_AI_Connector::user_facing_connector_error( 'artifact_expired', 'cloud_wp_ai_image_artifact_purged' ) );
					}
					unset( $artifact_contract['purged_at'] );
				}
				$artifact_keys = array_keys( $artifact_contract );
				$reference     = is_array( $artifact['artifact_reference'] ?? null ) ? $artifact['artifact_reference'] : array();
				$artifact_id   = (string) ( $artifact['artifact_id'] ?? '' );
				$mime_type     = $this->normalize_image_mime_type( (string) ( $artifact['content_type'] ?? '' ) );
				$width         = $artifact['width'] ?? null;
				$height        = $artifact['height'] ?? null;
				$byte_size     = $artifact['filesize_bytes'] ?? null;
				$checksum      = strtolower( (string) ( $artifact['checksum'] ?? '' ) );
				$expires_at    = (string) ( $artifact['expires_at'] ?? '' );
				$expires_ts    = $this->strict_image_timestamp( $expires_at );

				if (
					array() !== array_diff( self::IMAGE_ARTIFACT_KEYS, $artifact_keys )
					|| array() !== array_diff( $artifact_keys, self::IMAGE_ARTIFACT_KEYS )
					|| 1 !== preg_match( self::ARTIFACT_ID_PATTERN, $artifact_id )
					|| array( 'artifact_id' ) !== array_keys( $reference )
					|| $artifact_id !== (string) ( $reference['artifact_id'] ?? '' )
					|| 'available' !== (string) ( $artifact['status'] ?? '' )
					|| 'image' !== (string) ( $artifact['media_kind'] ?? '' )
					|| 'image.generate.v1' !== (string) ( $artifact['operation'] ?? '' )
					|| '' === $mime_type
					|| $this->image_format_for_mime( $mime_type ) !== (string) ( $artifact['format'] ?? '' )
					|| ! is_int( $width )
					|| $width < 1
					|| ! is_int( $height )
					|| $height < 1
					|| $width > self::MAX_IMAGE_AXIS
					|| $height > self::MAX_IMAGE_AXIS
					|| ( $width * $height ) > self::MAX_IMAGE_AREA
					|| ! is_int( $byte_size )
					|| $byte_size < 1
					|| $byte_size > self::MAX_IMAGE_BYTES
					|| 1 !== preg_match( '/^sha256:[0-9a-f]{64}$/', $checksum )
					|| false === $expires_ts
					|| $expires_ts <= time()
				) {
					throw new \WordPress\AiClient\Common\Exception\RuntimeException( Npcink_Cloud_WordPress_AI_Connector::user_facing_connector_error( 'artifact_contract_invalid', 'cloud_wp_ai_image_artifact_invalid' ) );
				}

				$download = $client->pull_media_artifact( $artifact_id, $trace_id );
				if ( is_wp_error( $download ) ) {
					throw new \WordPress\AiClient\Common\Exception\RuntimeException( Npcink_Cloud_WordPress_AI_Connector::user_facing_runtime_failure( (string) $download->get_error_code(), '', (string) $download->get_error_message() ) );
				}

				$contents      = is_string( $download['body'] ?? null ) ? $download['body'] : '';
				$response_mime = $this->normalize_image_mime_type( (string) ( $download['content_type'] ?? '' ) );
				$actual_checksum = 'sha256:' . hash( 'sha256', $contents );
				$delivery_id     = (string) ( $download['delivery_id'] ?? '' );
				$ack_deadline_at = (string) ( $download['delivery_ack_deadline'] ?? '' );
				$ack_deadline    = $this->strict_image_timestamp( $ack_deadline_at );
				$image_info      = function_exists( 'getimagesizefromstring' ) ? @getimagesizefromstring( $contents ) : false;
				$decoded_mime    = is_array( $image_info ) ? $this->normalize_image_mime_type( (string) ( $image_info['mime'] ?? '' ) ) : '';

				if (
					'' === $contents
					|| $mime_type !== $response_mime
					|| $mime_type !== $decoded_mime
					|| $byte_size !== strlen( $contents )
					|| $byte_size !== absint( $download['content_length'] ?? 0 )
					|| $checksum !== $actual_checksum
					|| $artifact_id !== (string) ( $download['artifact_id'] ?? '' )
					|| $checksum !== strtolower( (string) ( $download['artifact_checksum'] ?? '' ) )
					|| ! is_array( $image_info )
					|| $width !== absint( $image_info[0] ?? 0 )
					|| $height !== absint( $image_info[1] ?? 0 )
					|| 1 !== preg_match( self::DELIVERY_ID_PATTERN, $delivery_id )
					|| false === $ack_deadline
					|| $ack_deadline <= time()
					|| $ack_deadline > $expires_ts
				) {
					throw new \WordPress\AiClient\Common\Exception\RuntimeException( Npcink_Cloud_WordPress_AI_Connector::user_facing_connector_error( 'artifact_verification_failed', 'cloud_wp_ai_image_artifact_verification_failed' ) );
				}

				$ack = $client->acknowledge_media_artifact_delivery(
					$artifact_id,
					array(
						'contract_version'      => 'media_artifact_delivery_ack.v1',
						'delivery_id'           => $delivery_id,
						'received_byte_size'    => $byte_size,
						'received_checksum'     => $checksum,
					),
					$trace_id
				);
				if ( is_wp_error( $ack ) ) {
					throw new \WordPress\AiClient\Common\Exception\RuntimeException( Npcink_Cloud_WordPress_AI_Connector::user_facing_runtime_failure( (string) $ack->get_error_code(), '', (string) $ack->get_error_message() ) );
				}
				$acknowledged_at = $this->strict_image_timestamp( (string) ( $ack['acknowledged_at'] ?? '' ) );
				$ack_expires_at  = $this->strict_image_timestamp( (string) ( $ack['artifact_expires_at'] ?? '' ) );
				if (
					$artifact_id !== (string) ( $ack['artifact_id'] ?? '' )
					|| $delivery_id !== (string) ( $ack['delivery_id'] ?? '' )
					|| $byte_size !== ( $ack['received_byte_size'] ?? null )
					|| $checksum !== (string) ( $ack['received_checksum'] ?? '' )
					|| true !== ( $ack['byte_size_verified'] ?? null )
					|| true !== ( $ack['checksum_verified'] ?? null )
					|| false === $acknowledged_at
					|| $acknowledged_at > $ack_deadline
					|| false === $ack_expires_at
					|| $ack_expires_at !== $expires_ts
					|| (string) ( $ack['artifact_expires_at'] ?? '' ) !== $expires_at
				) {
					throw new \WordPress\AiClient\Common\Exception\RuntimeException( Npcink_Cloud_WordPress_AI_Connector::user_facing_connector_error( 'artifact_ack_invalid', 'cloud_wp_ai_image_delivery_ack_invalid' ) );
				}

				$images[] = array(
					'b64_json' => base64_encode( $contents ),
					'mime_type' => $mime_type,
				);
			}

			return $images;
		}

		/**
		 * Normalizes supported generated-image MIME types.
		 *
		 * @param string $mime_type Raw MIME type.
		 * @return string
		 */
		private function normalize_image_mime_type( string $mime_type ): string {
			$mime_type = strtolower( trim( explode( ';', $mime_type, 2 )[0] ) );

			return in_array( $mime_type, array( 'image/jpeg', 'image/png', 'image/webp' ), true ) ? $mime_type : '';
		}

		/**
		 * Maps a supported generated-image MIME type to its Cloud format.
		 *
		 * @param string $mime_type Normalized MIME type.
		 * @return string
		 */
		private function image_format_for_mime( string $mime_type ): string {
			return array(
				'image/jpeg' => 'jpeg',
				'image/png'  => 'png',
				'image/webp' => 'webp',
			)[ $mime_type ] ?? '';
		}

		/**
		 * Parses exact canonical UTC RFC3339 image-artifact timestamps.
		 *
		 * @param string $value Timestamp value.
		 * @return int|false
		 */
		private function strict_image_timestamp( string $value ) {
			$utc     = new \DateTimeZone( 'UTC' );
			$formats = array(
				'!Y-m-d\TH:i:s\Z'   => 'Y-m-d\TH:i:s\Z',
				'!Y-m-d\TH:i:s.u\Z' => 'Y-m-d\TH:i:s.u\Z',
				'!Y-m-d\TH:i:sP'    => 'Y-m-d\TH:i:sP',
				'!Y-m-d\TH:i:s.uP'  => 'Y-m-d\TH:i:s.uP',
			);

			foreach ( $formats as $parse_format => $roundtrip_format ) {
				$timestamp = \DateTimeImmutable::createFromFormat( $parse_format, $value, $utc );
				$errors    = \DateTimeImmutable::getLastErrors();
				if (
					false !== $timestamp
					&& ( false === $errors || ( 0 === $errors['warning_count'] && 0 === $errors['error_count'] ) )
					&& $value === $timestamp->format( $roundtrip_format )
					&& '+00:00' === $timestamp->format( 'P' )
				) {
					return $timestamp->getTimestamp();
				}
			}

			return false;
		}
	}
}
