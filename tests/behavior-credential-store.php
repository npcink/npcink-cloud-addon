<?php
/**
 * Behavior tests for authenticated Cloud credential persistence.
 *
 * @package NpcinkCloudAddon
 */

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

maca_load_addon_classes();
require_once MACA_TEST_ROOT . '/includes/class-cloud-settings-page.php';

$missing_consent_settings = Npcink_Cloud_Addon_Settings::normalize_settings(
	array(
		'base_url' => 'https://cloud.example.test',
		'site_id'  => 'site_missing_consent',
		'key_id'   => 'key_missing_consent',
		'secret'   => 'secret_missing_consent',
		'verified' => true,
	)
);
maca_assert(
	false === (bool) $missing_consent_settings['site_knowledge_delivery_enabled'],
	'Behavior: missing Site Knowledge delivery consent normalizes to disabled.'
);

maca_reset_test_state();
Npcink_Cloud_Addon_Settings::write_settings( $missing_consent_settings );
maca_assert(
	! Npcink_Cloud_Addon_Settings::is_site_knowledge_delivery_enabled(),
	'Behavior: verified Cloud credentials do not implicitly enable Site Knowledge delivery.'
);

$explicit_consent_settings = Npcink_Cloud_Addon_Settings::get_settings();
$explicit_consent_settings['site_knowledge_delivery_enabled'] = true;
Npcink_Cloud_Addon_Settings::write_settings( $explicit_consent_settings );
maca_assert(
	Npcink_Cloud_Addon_Settings::is_site_knowledge_delivery_enabled(),
	'Behavior: explicit administrator Site Knowledge delivery consent remains enabled after persistence.'
);

$credential_settings = array(
	'base_url' => 'https://cloud.example.test',
	'site_id' => 'site_at_rest_test',
	'key_id' => 'key_at_rest_test',
	'secret' => 'secret_at_rest_test',
	'timeout' => 12,
	'verified' => true,
	'verified_at' => '2026-07-14 00:00:00 UTC',
	'last_verification_error' => '',
	'monitoring_enabled' => false,
	'site_knowledge_delivery_enabled' => true,
	'site_knowledge_generation_reference_enabled' => false,
	'wordpress_ai_connector_enabled' => true,
);

maca_reset_test_state();
maca_assert(
	Npcink_Cloud_Addon_Settings::write_settings( $credential_settings ),
	'Behavior: authenticated credential settings persist successfully.'
);
$stored = get_option( Npcink_Cloud_Addon_Settings::option_name(), array() );
$stored_json = wp_json_encode( $stored );
$envelope = is_array( $stored['credential_envelope'] ?? null ) ? $stored['credential_envelope'] : array();
maca_assert(
	1 === (int) ( $envelope['version'] ?? 0 )
	&& in_array( (string) ( $envelope['algorithm'] ?? '' ), array( 'sodium_secretbox', 'aes-256-gcm' ), true )
	&& '' !== (string) ( $envelope['nonce'] ?? '' )
	&& '' !== (string) ( $envelope['ciphertext'] ?? '' )
	&& ! array_key_exists( 'site_id', $stored )
	&& ! array_key_exists( 'key_id', $stored )
	&& ! array_key_exists( 'secret', $stored )
	&& false === strpos( (string) $stored_json, 'site_at_rest_test' )
	&& false === strpos( (string) $stored_json, 'key_at_rest_test' )
	&& false === strpos( (string) $stored_json, 'secret_at_rest_test' ),
	'Behavior: wp_options stores only a versioned authenticated envelope, never plaintext signing credentials.'
);

$roundtrip = Npcink_Cloud_Addon_Settings::get_settings();
maca_assert(
	'site_at_rest_test' === (string) $roundtrip['site_id']
	&& 'key_at_rest_test' === (string) $roundtrip['key_id']
	&& 'secret_at_rest_test' === (string) $roundtrip['secret']
	&& true === (bool) $roundtrip['verified'],
	'Behavior: server-side settings retain the existing public PHP shape after an encrypted round trip.'
);

$resanitized_stored = Npcink_Cloud_Addon_Settings::sanitize_option_value( $stored );
maca_assert(
	$stored['credential_envelope'] === $resanitized_stored['credential_envelope']
	&& ! array_key_exists( 'secret', $resanitized_stored ),
	'Behavior: WordPress option sanitization accepts an authenticated internal envelope without exposing or replacing it.'
);

$sanitized = Npcink_Cloud_Addon_Settings::sanitize_option_value( $credential_settings );
$sanitized_json = wp_json_encode( $sanitized );
maca_assert(
	is_array( $sanitized['credential_envelope'] ?? null )
	&& ! array_key_exists( 'secret', $sanitized )
	&& false === strpos( (string) $sanitized_json, 'secret_at_rest_test' ),
	'Behavior: the Settings API sanitize callback also emits only the encrypted option shape.'
);

$permission_update = Npcink_Cloud_Addon_Settings::get_settings();
$permission_update['monitoring_enabled'] = true;
maca_assert(
	Npcink_Cloud_Addon_Settings::write_settings( $permission_update )
	&& Npcink_Cloud_Addon_Settings::is_monitoring_enabled()
	&& 'site_at_rest_test' === (string) Npcink_Cloud_Addon_Settings::get_settings()['site_id']
	&& 'secret_at_rest_test' === (string) Npcink_Cloud_Addon_Settings::get_settings()['secret'],
	'Behavior: non-secret permission updates preserve encrypted signing credentials.'
);

$inactive_settings = Npcink_Cloud_Addon_Settings::get_settings();
$inactive_settings['verified'] = false;
$inactive_settings['verified_at'] = '';
$inactive_settings['last_verification_error'] = '';
$inactive_settings['activation_state'] = 'inactive';
$inactive_settings['activation_reason'] = 'active_site_limit_reached';
maca_assert(
	Npcink_Cloud_Addon_Settings::write_settings( $inactive_settings ),
	'Behavior: inactive Cloud activation state persists beside the encrypted credential envelope.'
);
$inactive_state = Npcink_Cloud_Addon_Settings::get_credential_state();
maca_assert(
	'activation_required' === (string) ( $inactive_state['code'] ?? '' )
	&& true === (bool) ( $inactive_state['configured'] ?? false )
	&& false === (bool) ( $inactive_state['verified'] ?? true )
	&& 'Connected, activation required' === (string) ( $inactive_state['label'] ?? '' ),
	'Behavior: a bound inactive site is shown as connected with activation required, not as a missing credential.'
);
Npcink_Cloud_Addon_Settings::mark_verification_result( true, '' );
maca_assert(
	'active' === (string) Npcink_Cloud_Addon_Settings::get_settings()['activation_state'],
	'Behavior: a later successful verification clears the inactive projection and marks the site active.'
);

$before_verification_write_failure = get_option( Npcink_Cloud_Addon_Settings::option_name(), array() );
$GLOBALS['maca_option_update_failures'][ Npcink_Cloud_Addon_Settings::option_name() ] = true;
$verification_write_failure = Npcink_Cloud_Addon_Settings::mark_verification_result( false, 'Probe failed.' );
maca_assert(
	is_wp_error( $verification_write_failure )
	&& 'cloud_verification_state_storage_failed' === $verification_write_failure->get_error_code()
	&& $before_verification_write_failure === get_option( Npcink_Cloud_Addon_Settings::option_name(), array() ),
	'Behavior: verification state persistence fails explicitly without replacing the previous connection state.'
);
unset( $GLOBALS['maca_option_update_failures'][ Npcink_Cloud_Addon_Settings::option_name() ] );

$valid_stored = get_option( Npcink_Cloud_Addon_Settings::option_name(), array() );
$tampered = $valid_stored;
$ciphertext = (string) ( $tampered['credential_envelope']['ciphertext'] ?? '' );
$tampered['credential_envelope']['ciphertext'] = ( 'A' === substr( $ciphertext, 0, 1 ) ? 'B' : 'A' ) . substr( $ciphertext, 1 );
$GLOBALS['maca_options'][ Npcink_Cloud_Addon_Settings::option_name() ] = $tampered;
$tampered_settings = Npcink_Cloud_Addon_Settings::get_settings();
maca_assert(
	'' === (string) $tampered_settings['site_id']
	&& '' === (string) $tampered_settings['key_id']
	&& '' === (string) $tampered_settings['secret']
	&& false === (bool) $tampered_settings['verified']
	&& ! Npcink_Cloud_Addon_Settings::is_configured(),
	'Behavior: ciphertext tampering fails closed as an unconfigured connection.'
);

$truncated = $valid_stored;
$truncated['credential_envelope']['ciphertext'] = base64_encode( 'x' );
$GLOBALS['maca_options'][ Npcink_Cloud_Addon_Settings::option_name() ] = $truncated;
maca_assert(
	'' === (string) Npcink_Cloud_Addon_Settings::get_settings()['secret']
	&& ! Npcink_Cloud_Addon_Settings::is_configured(),
	'Behavior: malformed authenticated ciphertext fails closed without a decryption fatal.'
);

$GLOBALS['maca_options'][ Npcink_Cloud_Addon_Settings::option_name() ] = $valid_stored;
$GLOBALS['maca_wp_salt'] = 'changed-maca-test-auth-salt';
$changed_salt_settings = Npcink_Cloud_Addon_Settings::get_settings();
$changed_salt_update = $changed_salt_settings;
$changed_salt_update['monitoring_enabled'] = false;
maca_assert(
	'' === (string) $changed_salt_settings['secret']
	&& false === (bool) $changed_salt_settings['verified']
	&& ! Npcink_Cloud_Addon_Settings::is_configured()
	&& ! Npcink_Cloud_Addon_Settings::write_settings( $changed_salt_settings )
	&& $valid_stored === get_option( Npcink_Cloud_Addon_Settings::option_name(), array() )
	&& ! Npcink_Cloud_Addon_Settings::write_settings( $changed_salt_update )
	&& $valid_stored === get_option( Npcink_Cloud_Addon_Settings::option_name(), array() ),
	'Behavior: changing WordPress security salts rejects unchanged and partial writes without replacing the envelope.'
);

$reconnect_settings = $changed_salt_settings;
$reconnect_settings['site_id'] = 'site_reconnected';
$reconnect_settings['key_id'] = 'key_reconnected';
$reconnect_settings['secret'] = 'secret_reconnected';
$reconnect_settings['verified'] = false;
maca_assert(
	Npcink_Cloud_Addon_Settings::write_settings( $reconnect_settings )
	&& 'site_reconnected' === (string) Npcink_Cloud_Addon_Settings::get_settings()['site_id']
	&& 'secret_reconnected' === (string) Npcink_Cloud_Addon_Settings::get_settings()['secret'],
	'Behavior: a complete newly issued credential set can reconnect under the rotated salt.'
);
$GLOBALS['maca_wp_salt'] = 'maca-test-auth-salt';
$GLOBALS['maca_options'][ Npcink_Cloud_Addon_Settings::option_name() ] = $valid_stored;

$GLOBALS['maca_options'][ Npcink_Cloud_Addon_Settings::option_name() ] = array(
	'base_url' => 'https://legacy.example.test',
	'site_id' => 'legacy_site_plaintext',
	'key_id' => 'legacy_key_plaintext',
	'secret' => 'legacy_secret_plaintext',
	'verified' => true,
);
$legacy = Npcink_Cloud_Addon_Settings::get_settings();
maca_assert(
	'https://legacy.example.test' === (string) $legacy['base_url']
	&& '' === (string) $legacy['site_id']
	&& '' === (string) $legacy['key_id']
	&& '' === (string) $legacy['secret']
	&& false === (bool) $legacy['verified'],
	'Behavior: legacy plaintext credentials are ignored rather than migrated or accepted.'
);

$GLOBALS['maca_options'][ Npcink_Cloud_Addon_Settings::option_name() ] = $valid_stored;
$before_failed_write = get_option( Npcink_Cloud_Addon_Settings::option_name(), array() );
$settings_for_failed_write = Npcink_Cloud_Addon_Settings::get_settings();
$settings_for_failed_write['monitoring_enabled'] = false;
$GLOBALS['maca_wp_salt'] = '';
maca_assert(
	! Npcink_Cloud_Addon_Settings::write_settings( $settings_for_failed_write )
	&& $before_failed_write === get_option( Npcink_Cloud_Addon_Settings::option_name(), array() ),
	'Behavior: encryption failure leaves the existing option untouched.'
);

$GLOBALS['maca_http_requests'] = array();
$persist_method = new ReflectionMethod( Npcink_Cloud_Settings_Page::class, 'persist_and_verify_settings' );
$persist_method->setAccessible( true );
$persist_method->invoke( null, $settings_for_failed_write, 'Verified.' );
$notice_values = array_values( $GLOBALS['maca_transients'] );
$last_notice = end( $notice_values );
maca_assert(
	0 === count( $GLOBALS['maca_http_requests'] )
	&& $before_failed_write === get_option( Npcink_Cloud_Addon_Settings::option_name(), array() )
	&& 'error' === (string) ( $last_notice['type'] ?? '' )
	&& false !== strpos( (string) ( $last_notice['message'] ?? '' ), 'could not be stored securely' ),
	'Behavior: admin persistence failure stops verification and reports a non-secret error.'
);
$GLOBALS['maca_wp_salt'] = 'maca-test-auth-salt';

maca_reset_test_state();
maca_seed_settings( true );
$stored_before_replacement_probe = get_option( Npcink_Cloud_Addon_Settings::option_name(), array() );
$replacement_settings = Npcink_Cloud_Addon_Settings::get_settings();
$replacement_settings['base_url'] = 'https://replacement.example.test';
$replacement_settings['site_id'] = 'site_replacement';
$replacement_settings['key_id'] = 'key_replacement';
$replacement_settings['secret'] = 'secret_replacement';
$replacement_settings['verified'] = false;
$GLOBALS['maca_http_response_queue'][] = new WP_Error( 'replacement_unavailable', 'Replacement unavailable.' );
$persist_method->invoke( null, $replacement_settings, 'Verified.' );
$replacement_notice = get_transient( 'npcink_cloud_notice_1' );
maca_assert(
	$stored_before_replacement_probe === get_option( Npcink_Cloud_Addon_Settings::option_name(), array() )
	&& Npcink_Cloud_Addon_Settings::is_verified()
	&& 'site_test' === (string) Npcink_Cloud_Addon_Settings::get_settings()['site_id']
	&& false !== strpos( (string) ( $replacement_notice['message'] ?? '' ), 'existing connection was kept' ),
	'Behavior: a failed replacement credential probe leaves the verified existing connection unchanged.'
);

Npcink_Cloud_Addon_Settings::delete_settings();
maca_assert(
	! array_key_exists( Npcink_Cloud_Addon_Settings::option_name(), $GLOBALS['maca_options'] )
	&& ! Npcink_Cloud_Addon_Settings::is_configured(),
	'Behavior: disconnect deletion clears the encrypted credential option.'
);
