<?php
/**
 * Behavior: local disconnect keeps the last Cloud Base URL for reconnect.
 *
 * @package NpcinkCloudAddon
 */

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

maca_load_addon_classes();
maca_reset_test_state();

$custom_base_url_input = 'http://127.0.0.1:18010/';
Npcink_Cloud_Addon_Settings::write_settings(
	array(
		'base_url' => $custom_base_url_input,
		'site_id' => 'site_self_hosted',
		'key_id' => 'key_self_hosted',
		'secret' => 'secret_self_hosted',
		'timeout' => 8,
		'verified' => true,
		'verified_at' => '2026-10-20 00:00:00 UTC',
		'last_verification_error' => '',
		'monitoring_enabled' => false,
		'site_knowledge_delivery_enabled' => false,
		'site_knowledge_generation_reference_enabled' => false,
		'wordpress_ai_connector_enabled' => true,
	)
);

$settings = Npcink_Cloud_Addon_Settings::get_settings();
$remembered_base_url = (string) ( $settings['base_url'] ?? '' );
Npcink_Cloud_Addon_Cleanup::delete_all( $settings );
if ( '' !== $remembered_base_url ) {
	Npcink_Cloud_Addon_Settings::write_settings(
		array(
			'base_url' => $remembered_base_url,
		)
	);
}

$after = Npcink_Cloud_Addon_Settings::get_settings();
$effective = Npcink_Cloud_Addon_Settings::get_effective_base_url( $after );
$expected_base_url = 'http://127.0.0.1:18010';

maca_assert(
	$expected_base_url === (string) ( $after['base_url'] ?? '' )
	&& $expected_base_url === $effective
	&& '' === (string) ( $after['site_id'] ?? '' )
	&& '' === (string) ( $after['key_id'] ?? '' )
	&& '' === (string) ( $after['secret'] ?? '' )
	&& ! Npcink_Cloud_Addon_Settings::is_configured()
	&& ! Npcink_Cloud_Addon_Settings::is_verified(),
	'Behavior: disconnect wipe keeps custom Cloud Base URL and clears credentials so reconnect uses the remembered endpoint.'
);

maca_reset_test_state();
Npcink_Cloud_Addon_Settings::write_settings(
	array(
		'base_url' => '',
		'site_id' => '',
		'key_id' => '',
		'secret' => '',
	)
);
$empty_settings = Npcink_Cloud_Addon_Settings::get_settings();
$remembered_empty = (string) ( $empty_settings['base_url'] ?? '' );
Npcink_Cloud_Addon_Cleanup::delete_all( $empty_settings );
if ( '' !== $remembered_empty ) {
	Npcink_Cloud_Addon_Settings::write_settings(
		array(
			'base_url' => $remembered_empty,
		)
	);
}
$fallback = Npcink_Cloud_Addon_Settings::get_effective_base_url();
maca_assert(
	'' === (string) Npcink_Cloud_Addon_Settings::get_settings()['base_url']
	&& Npcink_Cloud_Addon_Settings::get_default_base_url() === $fallback,
	'Behavior: disconnect with no stored Base URL still falls back to the default endpoint.'
);

$clear_method = new ReflectionMethod( Npcink_Cloud_Settings_Actions::class, 'clear_local_connection_preserving_base_url' );
if ( PHP_VERSION_ID < 80100 ) {
	$clear_method->setAccessible( true );
}

$swap_base_url_input = 'https://cloud.example.test/';
Npcink_Cloud_Addon_Settings::write_settings(
	array(
		'base_url' => $swap_base_url_input,
		'site_id' => 'site_swap',
		'key_id' => 'key_swap',
		'secret' => 'secret_swap',
		'timeout' => 8,
		'verified' => true,
		'verified_at' => '2026-10-20 00:00:00 UTC',
		'last_verification_error' => '',
		'monitoring_enabled' => false,
		'site_knowledge_delivery_enabled' => false,
		'site_knowledge_generation_reference_enabled' => false,
		'wordpress_ai_connector_enabled' => true,
	)
);
$expected_swap_base_url = (string) Npcink_Cloud_Addon_Settings::get_settings()['base_url'];
$remembered = (string) $clear_method->invoke( null );
$after_clear = Npcink_Cloud_Addon_Settings::get_settings();
maca_assert(
	$expected_swap_base_url === $remembered
	&& $expected_swap_base_url === (string) ( $after_clear['base_url'] ?? '' )
	&& '' === (string) ( $after_clear['site_id'] ?? '' )
	&& '' === (string) ( $after_clear['key_id'] ?? '' )
	&& '' === (string) ( $after_clear['secret'] ?? '' )
	&& ! Npcink_Cloud_Addon_Settings::is_configured()
	&& ! Npcink_Cloud_Addon_Settings::is_verified(),
	'Behavior: change-account wipe helper clears credentials while remembering Cloud Base URL.'
);

