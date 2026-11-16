<?php
/**
 * Behavior tests for the WordPress AI connector registration seam.
 *
 * @package NpcinkCloudAddon
 */

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
if ( ! defined( 'NPCINK_CLOUD_ADDON_FILE' ) ) {
    define( 'NPCINK_CLOUD_ADDON_FILE', MACA_TEST_ROOT . '/npcink-cloud-addon.php' );
}
require_once MACA_TEST_ROOT . '/includes/class-cloud-credential-store.php';
require_once MACA_TEST_ROOT . '/includes/class-cloud-discoverability-pause.php';
require_once MACA_TEST_ROOT . '/includes/class-cloud-addon-settings.php';
require_once MACA_TEST_ROOT . '/includes/class-cloud-entitlement-summary.php';
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
Npcink_Cloud_WordPress_AI_Connector::register();
maca_assert(
	2 === (int) ( $GLOBALS['maca_filters']['wpai_generated_image_filename'][10][0]['accepted_args'] ?? 0 ),
	'WordPress AI connector registers the generated-image basename compatibility filter with import arguments.'
);

$generated_image_filename_examples = array(
	'npcink-cloud-adoption-smoke.png' => 'npcink-cloud-adoption-smoke',
	'editorial-image.JPG'             => 'editorial-image',
	'generated-photo.jpeg'            => 'generated-photo',
	'generated-asset.WebP'            => 'generated-asset',
	'image.release-candidate'          => 'image.release-candidate',
	'.png'                             => 'ai-generated-image',
);
foreach ( $generated_image_filename_examples as $input_filename => $expected_filename ) {
	maca_assert(
		$expected_filename === apply_filters(
			'wpai_generated_image_filename',
			$input_filename,
			array( 'mime_type' => 'image/png' )
		),
		'WordPress AI generated-image filename compatibility keeps one MIME-derived extension for ' . $input_filename . '.'
	);
}

maca_seed_settings( true );

$expected_plugin_file = defined( 'NPCINK_CLOUD_ADDON_PLUGIN_BASENAME' )
	? NPCINK_CLOUD_ADDON_PLUGIN_BASENAME
	: 'npcink-cloud-addon/npcink-cloud-addon.php';

$GLOBALS['maca_options']['active_plugins'] = array( 'renamed-addon/npcink-cloud-addon.php' );
$renamed_registry = new Maca_Connector_Registry_Stub();
Npcink_Cloud_WordPress_AI_Connector::register_connector( $renamed_registry );
$renamed_connector = $renamed_registry->connectors[ Npcink_Cloud_WordPress_AI_Connector::CONNECTOR_ID ] ?? array();
maca_assert(
	'renamed-addon/npcink-cloud-addon.php' === ( $renamed_connector['plugin']['file'] ?? '' ),
	'WordPress connector registry follows an active renamed addon directory instead of returning the packaged fallback basename.'
);
$GLOBALS['maca_options']['active_plugins'] = array();

$capability_fixture = array(
	'contract_version' => 'wordpress-ai-capabilities-v1',
	'evidence_kind' => 'configuration_snapshot',
	'runtime_admission_required' => true,
	'provider_call_performed' => false,
	'checked_at' => gmdate( 'Y-m-d\TH:i:s\Z' ),
	'max_age_seconds' => 300,
	'capabilities' => array_fill_keys( array( 'text_generation', 'image_generation', 'vision' ), array(
		'state' => 'configured', 'reason_code' => 'configured', 'configuration_state' => 'configured', 'entitlement_state' => 'configured',
	) ),
);
Npcink_Cloud_Entitlement_Summary::cache_summary_from_response( array( 'entitlement' => array( 'wordpress_ai_capabilities' => $capability_fixture ) ) );

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
		&& Npcink_Cloud_WordPress_AI_Connector::SETTING_NAME === ( $connector['authentication']['setting_name'] ?? '' )
		&& $expected_plugin_file === ( $connector['plugin']['file'] ?? '' ),
	'WordPress connector registry receives a fixed Npcink Cloud ai_provider card with a stable plugin basename and synthetic marker setting.'
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

$has_credentials_without_registry_card = Npcink_Cloud_WordPress_AI_Connector::filter_has_ai_credentials( false, array() );
maca_assert(
	true === $has_credentials_without_registry_card,
	'AI plugin credential detection remains available when connector metadata is not present in the current availability snapshot.'
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

$text_only = $capability_fixture;
foreach ( array( 'image_generation', 'vision' ) as $capability ) {
	$text_only['capabilities'][ $capability ] = array( 'state' => 'unavailable', 'reason_code' => 'no_eligible_model', 'configuration_state' => 'unavailable', 'entitlement_state' => 'configured' );
}
Npcink_Cloud_Entitlement_Summary::cache_summary_from_response( array( 'entitlement' => array( 'wordpress_ai_capabilities' => $text_only ) ) );
maca_assert(
	Npcink_Cloud_WordPress_AI_Connector::is_model_available( Npcink_Cloud_WordPress_AI_Connector::MODEL_ID )
	&& array() === Npcink_Cloud_WordPress_AI_Connector::filter_preferred_image_models( array() )
	&& array() === Npcink_Cloud_WordPress_AI_Connector::filter_preferred_vision_models( array() ),
	'Text-only Cloud configuration does not advertise image or vision models.'
);
Npcink_Cloud_Entitlement_Summary::cache_summary_from_response( array( 'entitlement' => array() ) );
maca_assert(
	array() === Npcink_Cloud_WordPress_AI_Connector::filter_preferred_text_models( array() )
	&& array() === Npcink_Cloud_WordPress_AI_Connector::filter_preferred_image_models( array() )
	&& '1' === get_option( Npcink_Cloud_WordPress_AI_Connector::SETTING_NAME, '' ),
	'Unknown capability evidence hides models while preserving the verified connection marker.'
);
Npcink_Cloud_Entitlement_Summary::cache_summary_from_response( array( 'entitlement' => array( 'wordpress_ai_capabilities' => $capability_fixture ) ) );

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

if ( ! function_exists( '__return_false' ) ) {
    function __return_false() { return false; }
}
Npcink_Cloud_Discoverability_Pause::register_hooks();
$GLOBALS['maca_options']['wpai_feature_meta-description_enabled'] = '1';
maca_assert( false === apply_filters( 'wpai_feature_meta-description_enabled', true ) && '1' === get_option( 'wpai_feature_meta-description_enabled' ), 'Official feature filter disables the entry while retaining the saved preference.' );

$pause_features = apply_filters( 'wpai_default_feature_classes', array( 'meta-description' => 'Meta_Class', 'title-generation' => 'Title_Class', 'slug-generation' => 'Slug_Class' ) );
maca_assert( array( 'title-generation' => 'Title_Class', 'slug-generation' => 'Slug_Class' ) === $pause_features, 'Only the dedicated metadata feature entry is removed from the host feature list.' );

// The active frame, rather than a PHP class name, supplies provider identity.
Npcink_Cloud_WordPress_AI_Connector::begin_wordpress_ai_ability_context( 'ai/block-container-label-generation', array( 'content' => 'Outer content' ) );
Npcink_Cloud_WordPress_AI_Connector::begin_wordpress_ai_ability_context( 'custom/unmapped', array() );
maca_assert( 'custom/unmapped' === Npcink_Cloud_WordPress_AI_Connector::current_text_ability_context()['ability_id'], 'An unknown nested Ability cannot borrow the outer text identity.' );
$error = new WP_Error( 'fixture_error', 'Failed before model generation.' );
maca_assert( $error === Npcink_Cloud_WordPress_AI_Connector::finish_wordpress_ai_ability_context( $error, 'custom/unmapped', array() ), 'Callback errors pass through unchanged while their context is released.' );
maca_assert( 'ai/block-container-label-generation' === Npcink_Cloud_WordPress_AI_Connector::current_text_ability_context()['ability_id'], 'An outer editor Ability is restored after a nested failure.' );
Npcink_Cloud_WordPress_AI_Connector::end_wordpress_ai_ability_context( 'ai/block-container-label-generation', null, $error );
maca_assert( array() === Npcink_Cloud_WordPress_AI_Connector::current_text_ability_context(), 'Failed outer execution leaves no identity for a later model call.' );

Npcink_Cloud_WordPress_AI_Connector::begin_wordpress_ai_ability_context( 'ai/title-generation', array( 'content' => 'One use' ) );
maca_assert( 'ai/title-generation' === Npcink_Cloud_WordPress_AI_Connector::consume_text_ability_context()['ability_id'] && array() === Npcink_Cloud_WordPress_AI_Connector::consume_text_ability_context(), 'A model request consumes its identity once, even when generation fails.' );
Npcink_Cloud_WordPress_AI_Connector::clear_rest_ability_context( null );
maca_assert( array() === Npcink_Cloud_WordPress_AI_Connector::current_text_ability_context(), 'REST completion clears failed legacy callback context without erasing run evidence.' );
Npcink_Cloud_WordPress_AI_Connector::begin_wordpress_ai_ability_context( 'ai/alt-text-generation', array( 'attachment_id' => 123 ) );
Npcink_Cloud_WordPress_AI_Connector::begin_wordpress_ai_ability_context( 'custom/unmapped', array() );
maca_assert( ! Npcink_Cloud_WordPress_AI_Connector::has_alt_text_ability_context() && array() === Npcink_Cloud_WordPress_AI_Connector::consume_alt_text_ability_context(), 'An unknown nested Ability cannot borrow its parent vision identity or attachment.' );
Npcink_Cloud_WordPress_AI_Connector::end_wordpress_ai_ability_context( 'custom/unmapped', null, null );
maca_assert( array( 'attachment_id' => 123 ) === Npcink_Cloud_WordPress_AI_Connector::consume_alt_text_ability_context(), 'The outer vision attachment remains available after nested execution.' );
Npcink_Cloud_WordPress_AI_Connector::end_wordpress_ai_ability_context( 'ai/alt-text-generation', null, null );

Npcink_Cloud_WordPress_AI_Connector::begin_wordpress_ai_ability_context( 'ai/alt-text-generation', array( 'attachment_id' => 123 ) );
Npcink_Cloud_WordPress_AI_Connector::begin_wordpress_ai_ability_context( 'ai/alt-text-generation', array( 'attachment_id' => 456 ) );
maca_assert( array( 'attachment_id' => 456 ) === Npcink_Cloud_WordPress_AI_Connector::consume_alt_text_ability_context() && array() === Npcink_Cloud_WordPress_AI_Connector::consume_alt_text_ability_context(), 'Nested ALT consumes only its own attachment once.' );
Npcink_Cloud_WordPress_AI_Connector::finish_wordpress_ai_ability_context( new WP_Error( 'inner_failed', 'Fixture' ), 'ai/alt-text-generation', null );
maca_assert( array( 'attachment_id' => 123 ) === Npcink_Cloud_WordPress_AI_Connector::consume_alt_text_ability_context() && array() === Npcink_Cloud_WordPress_AI_Connector::consume_alt_text_ability_context(), 'An inner ALT failure restores the outer attachment and its independent one-use state.' );
Npcink_Cloud_WordPress_AI_Connector::end_wordpress_ai_ability_context( 'ai/alt-text-generation', null, null );

$outer_request = new stdClass();
$inner_request = new stdClass();
Npcink_Cloud_WordPress_AI_Connector::begin_wordpress_ai_ability_context( 'ai/title-generation', array( 'context' => 123 ) );
Npcink_Cloud_WordPress_AI_Connector::begin_rest_ability_context( null, array(), $outer_request );
maca_assert( array() === Npcink_Cloud_WordPress_AI_Connector::current_text_ability_context(), 'A nested REST callback cannot borrow its caller Ability identity.' );
Npcink_Cloud_WordPress_AI_Connector::begin_wordpress_ai_ability_context( 'custom/legacy-failure', array() );
Npcink_Cloud_WordPress_AI_Connector::begin_rest_ability_context( null, array(), $inner_request );
Npcink_Cloud_WordPress_AI_Connector::clear_rest_ability_context( null, array(), $inner_request );
maca_assert( 'custom/legacy-failure' === Npcink_Cloud_WordPress_AI_Connector::current_text_ability_context()['ability_id'], 'A nested REST return restores its immediate caller, even with a legacy failed frame.' );
Npcink_Cloud_WordPress_AI_Connector::clear_rest_ability_context( null, array(), $outer_request );
maca_assert( 'ai/title-generation' === Npcink_Cloud_WordPress_AI_Connector::consume_text_ability_context()['ability_id'], 'REST completion discards callback frames and restores the original outer Ability.' );
Npcink_Cloud_WordPress_AI_Connector::begin_rest_ability_context( null, array(), $outer_request );
Npcink_Cloud_WordPress_AI_Connector::begin_rest_ability_context( null, array(), $inner_request );
Npcink_Cloud_WordPress_AI_Connector::clear_rest_ability_context( null, array(), $outer_request );
maca_assert( array() === Npcink_Cloud_WordPress_AI_Connector::current_text_ability_context(), 'An aborted inner REST scope is discarded without reviving consumed outer context.' );
Npcink_Cloud_WordPress_AI_Connector::reset_wordpress_ai_ability_context();
