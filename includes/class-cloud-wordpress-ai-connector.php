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
			// Keep the code:stage separator readable; sanitize_key() strips ':'.
			$stable_code = sanitize_key( str_replace( ':', '-', $stable_code ) );
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
			// Classify only on structured codes; free-form upstream detail can
			// mention network words for unrelated failures (e.g. "connection
			// pool" in an application error) and must not drive the family.
			$haystack = strtolower( $error_code . ' ' . $diagnostic );
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
			// Transport and upstream messages can embed request URLs with
			// signed tokens; editors only need the surrounding text.
			$detail = (string) preg_replace( '#https?://\S+#i', '[url]', $detail );
			if ( function_exists( 'mb_substr' ) ) {
				return mb_substr( $detail, 0, 160 );
			}

			// Multibyte-safe bound without mbstring: drop invalid UTF-8 bytes,
			// slice with a UTF-8 regex, and strip a trailing partial sequence
			// via wp_html_excerpt() when the regex cannot run.
			if ( function_exists( 'mb_convert_encoding' ) ) {
				$detail = mb_convert_encoding( $detail, 'UTF-8', 'UTF-8' );
			}
			$matches = array();
			$matched = preg_match( '/\A.{0,160}/us', $detail, $matches );
			if ( 1 === $matched ) {
				return (string) $matches[0];
			}

			return function_exists( 'wp_html_excerpt' ) ? wp_html_excerpt( $detail, 160, '' ) : substr( $detail, 0, 160 );
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
