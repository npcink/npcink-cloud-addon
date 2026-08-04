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
			add_filter( 'wp_get_abilities_result', array( __CLASS__, 'prioritize_wordpress_ai_abilities_for_rest_list' ), 20, 2 );
			add_action( 'wp_before_execute_ability', array( __CLASS__, 'begin_wordpress_ai_ability_context' ), 10, 3 );
			add_action( 'wp_after_execute_ability', array( __CLASS__, 'end_wordpress_ai_ability_context' ), 10, 4 );
			add_action( 'shutdown', array( __CLASS__, 'reset_wordpress_ai_ability_context' ), 1 );
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
			if (
				is_array( $input )
				&& in_array( $ability_name, array( 'ai/title-generation', 'ai/summarization', 'ai/content-resizing' ), true )
			) {
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
			if (
				in_array( $ability_name, array( 'ai/title-generation', 'ai/summarization', 'ai/content-resizing' ), true )
				&& $ability_name === (string) ( self::$text_ability_context['ability_id'] ?? '' )
			) {
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
						'file'      => function_exists( 'plugin_basename' ) ? plugin_basename( NPCINK_CLOUD_ADDON_FILE ) : '',
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
		 * @return bool
		 */
		public static function filter_has_ai_credentials( bool $has_credentials, array $connectors ): bool {
			if ( $has_credentials ) {
				return true;
			}

			return isset( $connectors[ self::CONNECTOR_ID ] )
				&& class_exists( 'Npcink_Cloud_Addon_Settings' )
				&& Npcink_Cloud_Addon_Settings::is_wordpress_ai_connector_enabled();
		}

		/**
		 * Makes the scene-bound Cloud model the first preference when available.
		 *
		 * @param array<int,mixed> $preferred_models Existing preferred model list.
		 * @return array<int,mixed>
		 */
		public static function filter_preferred_text_models( array $preferred_models ): array {
			if ( ! self::is_cloud_connector_available() ) {
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
			if ( ! self::is_cloud_connector_available() ) {
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
			if ( ! self::is_cloud_connector_available() ) {
				return $preferred_models;
			}

			array_unshift( $preferred_models, array( self::CONNECTOR_ID, self::IMAGE_MODEL_ID ) );

			return $preferred_models;
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
			$is_error = is_wp_error( $response );
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
				''
			);

			$context = array(
				'contract_version'           => self::clean_log_value( (string) ( $event['contract_version'] ?? '' ), 80 ),
				'operation_contract_version' => self::clean_log_value( (string) ( $event['operation_contract_version'] ?? '' ), 80 ),
				'channel'                    => 'editor',
				'connector_id'               => 'npcink-cloud-addon',
				'task'                       => $task,
				'cloud_run_id'               => $run_id,
				'suggestion_only'            => true,
				'direct_wordpress_write'     => false,
				'content_storage'            => 'omitted_metadata_only',
			);

			$log_data = array(
				'type'        => $type,
				'operation'   => self::clean_log_value( (string) ( $event['operation'] ?? 'npcink-cloud/connector-runtime' ), 120 ) . ':' . $task,
				'provider'    => self::clean_log_value( $provider, 120 ),
				'model'       => self::clean_log_value( $model, 160 ),
				'duration_ms' => max( 0, (int) ( $event['duration_ms'] ?? 0 ) ),
				'status'      => $is_error ? 'error' : 'success',
				'error_message' => $is_error ? self::clean_log_value( $response->get_error_message(), 300 ) : '',
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
			 * Checks whether verified Cloud settings may be exposed to WordPress AI.
			 *
			 * @return bool
			 */
			private static function is_cloud_connector_available(): bool {
				return class_exists( 'Npcink_Cloud_Addon_Settings' )
					&& Npcink_Cloud_Addon_Settings::is_wordpress_ai_connector_enabled();
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
			return array( $this->text_model_metadata(), $this->vision_model_metadata(), $this->image_model_metadata() );
		}

		/**
		 * Checks if metadata exists for a model.
		 *
		 * @param string $model_id Model id.
		 * @return bool
		 */
		public function hasModelMetadata( string $model_id ): bool {
			return in_array(
				$model_id,
				array(
					Npcink_Cloud_WordPress_AI_Connector::MODEL_ID,
					Npcink_Cloud_WordPress_AI_Connector::VISION_MODEL_ID,
					Npcink_Cloud_WordPress_AI_Connector::IMAGE_MODEL_ID,
				),
				true
			);
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
			$ability_name = $this->detect_scene_ability_name();
			if ( '' === $ability_name ) {
				throw new \WordPress\AiClient\Common\Exception\RuntimeException( 'Npcink Cloud AI connector only accepts known WordPress AI ability scene calls.' );
			}
			$task_contract = npcink_cloud_addon_project_ai_task_contract( $ability_name );
			if ( is_wp_error( $task_contract ) ) {
				throw new \WordPress\AiClient\Common\Exception\RuntimeException( esc_html( $task_contract->get_error_message() ) );
			}
			$task = (string) $task_contract['task'];

			if ( 1 !== count( $prompt ) ) {
				throw new \WordPress\AiClient\Common\Exception\RuntimeException( 'Npcink Cloud AI connector does not support chat history.' );
			}

			if ( null !== $this->config->getFunctionDeclarations() || null !== $this->config->getWebSearch() ) {
				throw new \WordPress\AiClient\Common\Exception\RuntimeException( 'Npcink Cloud AI connector does not support tools or web search.' );
			}

			$text = $this->prompt_text( $prompt );
			if ( '' === $text ) {
				throw new \WordPress\AiClient\Common\Exception\RuntimeException( 'Npcink Cloud AI connector requires text scene input.' );
			}

			$scene_input = array(
				'response_format'    => $this->response_format_hint( $task ),
				'candidate_count'    => $this->config->getCandidateCount(),
				'max_tokens'         => $this->config->getMaxTokens(),
				'temperature'        => $this->config->getTemperature(),
				'scene_gate'         => array(
					'source' => 'wordpress_ai_plugin_ability',
					'task'   => $task,
				),
			);
			if ( in_array( $task, array( 'title_generation', 'content_summary', 'content_rewrite' ), true ) ) {
				$scene_input['source_text'] = $text;
			} else {
				$scene_input['prompt'] = $text;
			}
			$system_instruction = (string) ( $this->config->getSystemInstruction() ?? '' );
			if ( '' !== trim( $system_instruction ) ) {
				$scene_input['system_instruction'] = $system_instruction;
			}
			$site_knowledge_reference_mode = $this->site_knowledge_reference_mode( $task );
			if ( '' !== $site_knowledge_reference_mode && Npcink_Cloud_Addon_Settings::is_site_knowledge_generation_reference_enabled() ) {
				$scene_input['site_knowledge_reference'] = array(
					'enabled' => true,
					'mode'    => $site_knowledge_reference_mode,
				);
			}

			$started  = Npcink_Cloud_WordPress_AI_Connector::runtime_timer_start();
			$response = npcink_cloud_addon_execute_registered_ai_task_runtime(
				$ability_name,
				$scene_input,
				'trace_wp_ai_connector_' . wp_generate_uuid4(),
				'wp_ai_connector_' . wp_generate_uuid4()
			);
			$duration_ms = Npcink_Cloud_WordPress_AI_Connector::runtime_timer_elapsed_ms( $started );
			Npcink_Cloud_WordPress_AI_Connector::maybe_log_wordpress_ai_request_evidence(
				array(
					'type'                       => 'text',
					'operation'                  => 'npcink-cloud/connector-runtime',
					'task'                       => $task,
					'contract_version'           => 'cloud_connector_runtime.v1',
					'operation_contract_version' => 'wordpress_operation.v1',
					'response'                   => $response,
					'duration_ms'                => $duration_ms,
					'fallback_model_id'          => Npcink_Cloud_WordPress_AI_Connector::MODEL_ID,
				)
			);

			if ( is_wp_error( $response ) ) {
				throw new \WordPress\AiClient\Common\Exception\RuntimeException( esc_html( $response->get_error_message() ) );
			}

			$output_text = $this->extract_text( is_array( $response ) ? $response : array(), $task );
			if ( '' === $output_text ) {
				throw new \WordPress\AiClient\Common\Exception\RuntimeException( 'Npcink Cloud AI connector response did not include text output.' );
			}

			$run_id = (string) ( $response['run_id'] ?? ( $response['data']['run_id'] ?? wp_generate_uuid4() ) );
			$ability_context = Npcink_Cloud_WordPress_AI_Connector::current_text_ability_context();
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
					$duration_ms
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
		 * Returns a shallow response-format hint for Cloud-side scene projection.
		 *
		 * @param string $task WordPress AI ability task.
		 * @return string
		 */
		private function response_format_hint( string $task ): string {
			return in_array( $task, array( 'content_classification', 'comment_moderation' ), true ) ? 'json' : 'text';
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
				'WordPress\\AI\\Abilities\\Content_Classification\\Content_Classification' => 'ai/content-classification',
				'WordPress\\AI\\Abilities\\Comment_Moderation\\Comment_Analysis'           => 'ai/comment-analysis',
				'WordPress\\AI\\Abilities\\Content_Resizing\\Content_Resizing'              => 'ai/content-resizing',
				'WordPress\\AI\\Abilities\\Editorial_Updates\\Editorial_Updates'            => 'ai/editorial-updates',
				'WordPress\\AI\\Abilities\\Editorial_Notes\\Editorial_Notes'                => 'ai/editorial-notes',
				'WordPress\\AI\\Abilities\\Excerpt_Generation\\Excerpt_Generation'          => 'ai/excerpt-generation',
				'WordPress\\AI\\Abilities\\Meta_Description\\Meta_Description'              => 'ai/meta-description',
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
			$client = function_exists( 'npcink_cloud_addon_verified_runtime_client' )
				? npcink_cloud_addon_verified_runtime_client()
				: null;
			if (
				! is_object( $client )
				|| ! method_exists( $client, 'upload_wordpress_ai_alt_text_source' )
				|| ! method_exists( $client, 'execute_wordpress_ai_connector_runtime' )
			) {
				return new WP_Error(
					'cloud_wp_ai_alt_text_verified_client_required',
					__( 'WordPress AI alt text generation requires verified Npcink Cloud settings.', 'npcink-cloud-addon' ),
					array( 'status' => 503 )
				);
			}

			$source = self::local_source( $attachment_id, $prompt );
			if ( is_wp_error( $source ) ) {
				return $source;
			}

			$trace_id = 'trace_wp_ai_vision_' . wp_generate_uuid4();
			$artifact = $client->upload_wordpress_ai_alt_text_source(
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

			$request = array(
				'contract_version'   => 'cloud_connector_runtime.v1',
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

			return $client->execute_wordpress_ai_connector_runtime(
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

			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Reads one locally authorized, size-bounded attachment.
			$handle = @fopen( $real_path, 'rb' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- A raced file removal fails closed below.
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
			return new WP_Error( $code, __( $message, 'npcink-cloud-addon' ), array( 'status' => $status ) );
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
			$ability_input = Npcink_Cloud_WordPress_AI_Connector::consume_alt_text_ability_context();
			if ( array() === $ability_input ) {
				throw new \WordPress\AiClient\Common\Exception\RuntimeException( 'Npcink Cloud AI vision connector only accepts WordPress AI alt text generation scene calls.' );
			}

			if ( 1 !== count( $prompt ) ) {
				throw new \WordPress\AiClient\Common\Exception\RuntimeException( 'Npcink Cloud AI vision connector does not support chat history.' );
			}

			if ( null !== $this->config->getFunctionDeclarations() || null !== $this->config->getWebSearch() ) {
				throw new \WordPress\AiClient\Common\Exception\RuntimeException( 'Npcink Cloud AI vision connector does not support tools or web search.' );
			}

			$text = $this->prompt_text( $prompt );
			if ( '' === $text ) {
				throw new \WordPress\AiClient\Common\Exception\RuntimeException( 'Npcink Cloud AI vision connector requires text scene input.' );
			}

			$attachment_id = Npcink_Cloud_WordPress_AI_Alt_Text_Handoff::attachment_id_from_ability_input( $ability_input );
			if ( is_wp_error( $attachment_id ) ) {
				throw new \WordPress\AiClient\Common\Exception\RuntimeException( esc_html( $attachment_id->get_error_message() ) );
			}

			$started  = Npcink_Cloud_WordPress_AI_Connector::runtime_timer_start();
			$response = Npcink_Cloud_WordPress_AI_Alt_Text_Handoff::dispatch( $attachment_id, $text );
			Npcink_Cloud_WordPress_AI_Connector::maybe_log_wordpress_ai_request_evidence(
				array(
					'type'                       => 'vision',
					'operation'                  => 'npcink-cloud/connector-runtime',
					'task'                       => 'alt_text_suggest',
					'contract_version'           => 'cloud_connector_runtime.v1',
					'operation_contract_version' => 'wordpress_operation.v1',
					'response'                   => $response,
					'duration_ms'                => Npcink_Cloud_WordPress_AI_Connector::runtime_timer_elapsed_ms( $started ),
					'fallback_model_id'          => Npcink_Cloud_WordPress_AI_Connector::VISION_MODEL_ID,
				)
			);

			if ( is_wp_error( $response ) ) {
				throw new \WordPress\AiClient\Common\Exception\RuntimeException( esc_html( $response->get_error_message() ) );
			}

			$output_text = $this->extract_text( is_array( $response ) ? $response : array(), 'alt_text_suggest' );
			if ( '' === $output_text ) {
				throw new \WordPress\AiClient\Common\Exception\RuntimeException( 'Npcink Cloud AI vision connector response did not include alt text output.' );
			}

			return new \WordPress\AiClient\Results\DTO\GenerativeAiResult(
				(string) ( $response['run_id'] ?? ( $response['data']['run_id'] ?? wp_generate_uuid4() ) ),
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
			if ( 1 !== count( $prompt ) ) {
				throw new \WordPress\AiClient\Common\Exception\RuntimeException( 'Npcink Cloud AI image connector does not support chat history.' );
			}

			if ( null !== $this->config->getFunctionDeclarations() || null !== $this->config->getWebSearch() ) {
				throw new \WordPress\AiClient\Common\Exception\RuntimeException( 'Npcink Cloud AI image connector does not support tools or web search.' );
			}

			$text = $this->prompt_text( $prompt );
			if ( '' === $text ) {
				throw new \WordPress\AiClient\Common\Exception\RuntimeException( 'Npcink Cloud AI image connector requires text scene input.' );
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

			$started  = Npcink_Cloud_WordPress_AI_Connector::runtime_timer_start();
			$response = npcink_cloud_addon_execute_wordpress_ai_image_generation_runtime(
				$request,
				'trace_wp_ai_image_' . wp_generate_uuid4(),
				'wp_ai_image_' . wp_generate_uuid4()
			);
			Npcink_Cloud_WordPress_AI_Connector::maybe_log_wordpress_ai_request_evidence(
				array(
					'type'             => 'image',
					'operation'        => 'npcink-cloud/generate-image',
					'task'             => 'image_generation',
					'contract_version' => 'image_generation_request.v1',
					'response'         => $response,
					'duration_ms'      => Npcink_Cloud_WordPress_AI_Connector::runtime_timer_elapsed_ms( $started ),
					'fallback_model_id' => Npcink_Cloud_WordPress_AI_Connector::IMAGE_MODEL_ID,
				)
			);

			if ( is_wp_error( $response ) ) {
				throw new \WordPress\AiClient\Common\Exception\RuntimeException( esc_html( $response->get_error_message() ) );
			}

			$result     = $this->extract_result( is_array( $response ) ? $response : array() );
			$candidates = $this->extract_image_candidates( $result );
			if ( empty( $candidates ) ) {
				throw new \WordPress\AiClient\Common\Exception\RuntimeException( 'Npcink Cloud AI image connector response did not include image output.' );
			}

			return new \WordPress\AiClient\Results\DTO\GenerativeAiResult(
				(string) ( $response['run_id'] ?? ( $response['data']['run_id'] ?? wp_generate_uuid4() ) ),
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
					throw new \WordPress\AiClient\Common\Exception\RuntimeException( 'Npcink Cloud AI image connector does not support reference image refinement yet.' );
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
		 * @return list<\WordPress\AiClient\Results\DTO\Candidate>
		 */
		private function extract_image_candidates( array $result ): array {
			$images = isset( $result['images'] ) && is_array( $result['images'] ) ? $result['images'] : array();
			if ( empty( $images ) && isset( $result['data'] ) && is_array( $result['data'] ) ) {
				$images = $result['data'];
			}

			$candidates = array();
			foreach ( $images as $image ) {
				if ( ! is_array( $image ) ) {
					continue;
				}

				$mime_type = isset( $image['mime_type'] ) && is_string( $image['mime_type'] ) && '' !== $image['mime_type']
					? $image['mime_type']
					: 'image/png';
				$file_data = '';
				if ( isset( $image['b64_json'] ) && is_string( $image['b64_json'] ) && '' !== $image['b64_json'] ) {
					$file_data = $image['b64_json'];
				} elseif ( isset( $image['url'] ) && is_string( $image['url'] ) && '' !== $image['url'] ) {
					$file_data = $image['url'];
				}

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
	}
}
