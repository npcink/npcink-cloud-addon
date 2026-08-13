<?php
/**
 * Behavior tests for the WordPress AI connector registration seam.
 *
 * @package NpcinkCloudAddon
 */

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
require_once MACA_TEST_ROOT . '/includes/class-cloud-credential-store.php';
require_once MACA_TEST_ROOT . '/includes/class-cloud-addon-settings.php';
require_once MACA_TEST_ROOT . '/includes/class-cloud-wordpress-ai-connector.php';

if ( ! class_exists( 'Maca_Connector_Registry_Stub' ) ) {
	/**
	 * Minimal connector registry stub.
	 */
	final class Maca_Connector_Registry_Stub {
		/**
		 * Registered connectors.
		 *
		 * @var array<string,array<string,mixed>>
		 */
		public $connectors = array();

		/**
		 * Registers a connector.
		 *
		 * @param string              $id Connector id.
		 * @param array<string,mixed> $args Connector args.
		 * @return array<string,mixed>
		 */
		public function register( string $id, array $args ): array {
			$this->connectors[ $id ] = $args;
			return $args;
		}

		/**
		 * Checks whether a connector exists.
		 *
		 * @param string $id Connector id.
		 * @return bool
		 */
		public function is_registered( string $id ): bool {
			return isset( $this->connectors[ $id ] );
		}

		/**
		 * Removes a connector.
		 *
		 * @param string $id Connector id.
		 * @return array<string,mixed>|null
		 */
		public function unregister( string $id ) {
			$connector = $this->connectors[ $id ] ?? null;
			unset( $this->connectors[ $id ] );
			return $connector;
		}
	}
}

if ( ! class_exists( 'Maca_Ability_Stub' ) ) {
	/**
	 * Minimal ability stub for discovery ordering tests.
	 */
	final class Maca_Ability_Stub {
		/**
		 * Ability name.
		 *
		 * @var string
		 */
		private $name;

		/**
		 * Constructor.
		 *
		 * @param string $name Ability name.
		 */
		public function __construct( string $name ) {
			$this->name = $name;
		}

		/**
		 * Gets the ability name.
		 *
		 * @return string
		 */
		public function get_name(): string {
			return $this->name;
		}
	}
}

maca_reset_test_state();
maca_seed_settings( true );

maca_assert(
	false === Npcink_Cloud_Addon_Settings::is_site_knowledge_generation_reference_enabled(),
	'WordPress AI Site Knowledge generation reference remains opt-in by default.'
);
maca_set_site_knowledge_generation_reference_enabled( true );
maca_assert(
	true === Npcink_Cloud_Addon_Settings::is_site_knowledge_generation_reference_enabled(),
	'Verified local settings can enable Site Knowledge title-style reference.'
);
maca_set_site_knowledge_delivery_enabled( false );
maca_assert(
	false === Npcink_Cloud_Addon_Settings::is_site_knowledge_generation_reference_enabled()
	&& false === (bool) Npcink_Cloud_Addon_Settings::get_settings()['site_knowledge_generation_reference_enabled'],
	'Disabling Site Knowledge delivery also disables the hidden generation-reference permission.'
);
maca_set_site_knowledge_delivery_enabled( true );
maca_set_site_knowledge_generation_reference_enabled( false );

$registry = new Maca_Connector_Registry_Stub();
Npcink_Cloud_WordPress_AI_Connector::register_connector( $registry );
$connector = $registry->connectors[ Npcink_Cloud_WordPress_AI_Connector::CONNECTOR_ID ] ?? array();

maca_assert(
	'Npcink Cloud' === ( $connector['name'] ?? '' )
	&& 'ai_provider' === ( $connector['type'] ?? '' )
	&& 'api_key' === ( $connector['authentication']['method'] ?? '' )
	&& Npcink_Cloud_WordPress_AI_Connector::SETTING_NAME === ( $connector['authentication']['setting_name'] ?? '' ),
	'WordPress connector registry receives a fixed Npcink Cloud ai_provider card with a synthetic marker setting.'
);

maca_assert(
	'1' === get_option( Npcink_Cloud_WordPress_AI_Connector::SETTING_NAME, '' ),
	'Verified Cloud settings publish a synthetic connector marker without exposing the stored secret.'
);

$has_credentials = Npcink_Cloud_WordPress_AI_Connector::filter_has_ai_credentials( false, $registry->connectors );
maca_assert(
	true === $has_credentials,
	'AI plugin credential detection sees verified Npcink Cloud settings as available credentials.'
);

$preferred = Npcink_Cloud_WordPress_AI_Connector::filter_preferred_text_models(
	array(
		array( 'openai', 'gpt-4.1-mini' ),
	)
);
maca_assert(
	array( Npcink_Cloud_WordPress_AI_Connector::CONNECTOR_ID, Npcink_Cloud_WordPress_AI_Connector::MODEL_ID ) === $preferred[0],
	'Npcink Cloud scene text model is added as the first preferred AI text model.'
);

$preferred_images = Npcink_Cloud_WordPress_AI_Connector::filter_preferred_image_models(
	array(
		array( 'openai', 'gpt-image-2' ),
	)
);
maca_assert(
	array( Npcink_Cloud_WordPress_AI_Connector::CONNECTOR_ID, Npcink_Cloud_WordPress_AI_Connector::IMAGE_MODEL_ID ) === $preferred_images[0],
	'Npcink Cloud scene image model is added as the first preferred AI image model.'
);

$preferred_vision = Npcink_Cloud_WordPress_AI_Connector::filter_preferred_vision_models(
	array(
		array( 'openai', 'gpt-4.1-vision' ),
	)
);
maca_assert(
	array( Npcink_Cloud_WordPress_AI_Connector::CONNECTOR_ID, Npcink_Cloud_WordPress_AI_Connector::VISION_MODEL_ID ) === $preferred_vision[0],
	'Npcink Cloud scene vision model is added as the first preferred AI vision model.'
);

$abilities = array();
for ( $i = 1; $i <= 120; $i++ ) {
	$name               = 'core/test-' . $i;
	$abilities[ $name ] = new Maca_Ability_Stub( $name );
}
$abilities['ai/summarization']     = new Maca_Ability_Stub( 'ai/summarization' );
$abilities['ai/meta-description']  = new Maca_Ability_Stub( 'ai/meta-description' );
$abilities['third-party/example']  = new Maca_Ability_Stub( 'third-party/example' );
$prioritized = Npcink_Cloud_WordPress_AI_Connector::prioritize_wordpress_ai_abilities_for_rest_list(
	$abilities,
	array(
		'meta' => array(
			'show_in_rest' => true,
		),
	)
);
$prioritized_names = array_keys( $prioritized );
maca_assert(
	array( 'ai/summarization', 'ai/meta-description' ) === array_slice( $prioritized_names, 0, 2 ),
	'REST-visible WordPress AI abilities are prioritized for default Abilities API discovery when Cloud connector exposure is enabled.'
);

Npcink_Cloud_WordPress_AI_Connector::begin_wordpress_ai_ability_context(
	'ai/summarization',
	array(
		'context' => '42',
		'content' => 'Local source only',
	)
);
$text_context = Npcink_Cloud_WordPress_AI_Connector::current_text_ability_context();
maca_assert(
	'ai/summarization' === (string) ( $text_context['ability_id'] ?? '' )
	&& '42' === (string) ( $text_context['input']['context'] ?? '' ),
	'Validated WordPress AI text ability input is available only as one-request local correlation context.'
);
Npcink_Cloud_WordPress_AI_Connector::begin_wordpress_ai_ability_context(
	'ai/site-guidelines',
	array( 'category' => 'copy' )
);
Npcink_Cloud_WordPress_AI_Connector::end_wordpress_ai_ability_context(
	'ai/site-guidelines',
	array(),
	'result'
);
$text_context_after_nested_ability = Npcink_Cloud_WordPress_AI_Connector::current_text_ability_context();
maca_assert(
	'ai/summarization' === (string) ( $text_context_after_nested_ability['ability_id'] ?? '' )
	&& '42' === (string) ( $text_context_after_nested_ability['input']['context'] ?? '' ),
	'Nested guideline abilities do not erase the outer WordPress AI editor correlation context.'
);
Npcink_Cloud_WordPress_AI_Connector::end_wordpress_ai_ability_context(
	'ai/summarization',
	array(),
	'result'
);
maca_assert(
	array() === Npcink_Cloud_WordPress_AI_Connector::current_text_ability_context(),
	'WordPress AI text ability correlation context is cleared after ability execution.'
);

$namespace_scoped = Npcink_Cloud_WordPress_AI_Connector::prioritize_wordpress_ai_abilities_for_rest_list(
	$abilities,
	array(
		'namespace' => 'ai',
		'meta'      => array(
			'show_in_rest' => true,
		),
	)
);
maca_assert(
	$abilities === $namespace_scoped,
	'Namespace-scoped Abilities API queries are left untouched.'
);

maca_reset_test_state();
maca_seed_settings( false );
Npcink_Cloud_WordPress_AI_Connector::sync_connected_marker();
maca_assert(
	'' === get_option( Npcink_Cloud_WordPress_AI_Connector::SETTING_NAME, '' ),
	'Unverified Cloud settings do not publish the synthetic connector marker.'
);

$fallback_text_models = array(
	array( 'openai', 'gpt-4.1-mini' ),
);
$fallback_image_models = array(
	array( 'openai', 'gpt-image-2' ),
);
$fallback_vision_models = array(
	array( 'openai', 'gpt-4.1-vision' ),
);
maca_assert(
	$fallback_text_models === Npcink_Cloud_WordPress_AI_Connector::filter_preferred_text_models( $fallback_text_models ),
	'Unverified Cloud settings do not change the preferred AI text model list.'
);
maca_assert(
	$fallback_image_models === Npcink_Cloud_WordPress_AI_Connector::filter_preferred_image_models( $fallback_image_models ),
	'Unverified Cloud settings do not change the preferred AI image model list.'
);
maca_assert(
	$fallback_vision_models === Npcink_Cloud_WordPress_AI_Connector::filter_preferred_vision_models( $fallback_vision_models ),
	'Unverified Cloud settings do not change the preferred AI vision model list.'
);

maca_assert(
	$abilities === Npcink_Cloud_WordPress_AI_Connector::prioritize_wordpress_ai_abilities_for_rest_list(
		$abilities,
		array(
			'meta' => array(
				'show_in_rest' => true,
			),
		)
	),
	'Unverified Cloud settings do not change Abilities API discovery ordering.'
);

maca_reset_test_state();
maca_seed_settings( true );
maca_set_wordpress_ai_connector_enabled( false );
$disabled_registry = new Maca_Connector_Registry_Stub();
$disabled_registry->connectors[ Npcink_Cloud_WordPress_AI_Connector::CONNECTOR_ID ] = array(
	'name' => 'Stale Npcink Cloud',
);
Npcink_Cloud_WordPress_AI_Connector::register_connector( $disabled_registry );
maca_assert(
	! isset( $disabled_registry->connectors[ Npcink_Cloud_WordPress_AI_Connector::CONNECTOR_ID ] )
	&& '' === get_option( Npcink_Cloud_WordPress_AI_Connector::SETTING_NAME, '' ),
	'Disabled WordPress AI connector exposure removes the Npcink Cloud connector card and marker.'
);

maca_assert(
	false === Npcink_Cloud_WordPress_AI_Connector::filter_has_ai_credentials(
		false,
		array(
			Npcink_Cloud_WordPress_AI_Connector::CONNECTOR_ID => $connector,
		)
	),
	'Disabled WordPress AI connector exposure does not satisfy AI plugin credential detection.'
);

maca_assert(
	$fallback_text_models === Npcink_Cloud_WordPress_AI_Connector::filter_preferred_text_models( $fallback_text_models )
	&& $fallback_image_models === Npcink_Cloud_WordPress_AI_Connector::filter_preferred_image_models( $fallback_image_models ),
	'Disabled WordPress AI connector exposure does not change preferred AI model lists.'
);
