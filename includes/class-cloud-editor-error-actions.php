<?php
/**
 * Navigation for exact Cloud-owned errors in native WordPress editor notices.
 *
 * @package NpcinkCloudAddon
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Npcink_Cloud_Editor_Error_Actions {
	/** Enqueues navigation only; never invokes AI or changes saved error evidence. */
	public static function enqueue( string $failure_guidance ): void {
		$can_manage = current_user_can( Npcink_Cloud_Settings_Page::MENU_CAPABILITY );
		$checks = add_query_arg( array( 'tab' => 'checks', 'support' => 'ai-error' ), Npcink_Cloud_Settings_Actions::page_url() );
		$copy = $checks . '#npcink-cloud-copy-troubleshooting';
		$check_label = __( 'Check connection (new tab)', 'npcink-cloud-addon' );
		$troubleshoot_label = __( 'Open troubleshooting (new tab)', 'npcink-cloud-addon' );
		$billing = Npcink_Cloud_Settings_Page::get_portal_billing_url();
		$rules = array( array(
			'messages' => array(
				__( 'The AI request did not succeed. Try again later; if it keeps failing, copy the information on the Checks tab and contact support.', 'npcink-cloud-addon' ),
				'The AI request did not succeed. Try again later; if it keeps failing, copy the information on the Checks tab and contact support.',
				'AI 请求没有成功。请稍后再试，若一直失败请到「检查」页复制信息联系客服。',
			),
			'message' => $failure_guidance,
			'label' => $troubleshoot_label,
			'url' => $can_manage ? $copy : '',
		) );
		// Keep the host's error reason. Each exact message gets its own destination;
		// rate-limit, in-progress and input errors retain their existing wait/input steps.
		$families = array(
			array( __( 'The Npcink Cloud connection is no longer authorized. Please reconnect the site from the Cloud Addon settings page.', 'npcink-cloud-addon' ), $check_label, $checks . '#npcink-cloud-checks-actions', true ),
			array( __( 'This site is no longer connected to Npcink Cloud. Reconnect it on the Npcink AI > Cloud Addon page, then try again.', 'npcink-cloud-addon' ), $check_label, $checks . '#npcink-cloud-checks-actions', true ),
			array( __( 'This site is turned off in Npcink Cloud. Turn it on in Npcink Cloud, then try again.', 'npcink-cloud-addon' ), $check_label, $checks . '#npcink-cloud-checks-actions', true ),
			array( __( 'Your AI credits have run out. Top up on Npcink Cloud, then try again.', 'npcink-cloud-addon' ), __( 'Open plans and credits (new tab)', 'npcink-cloud-addon' ), $billing, true ),
			array( __( 'This request reached a Npcink Cloud limit. Please wait a moment and try again, or review the plan usage in Cloud.', 'npcink-cloud-addon' ), __( 'Open plans and credits (new tab)', 'npcink-cloud-addon' ), $billing, true ),
			array( __( 'Npcink Cloud could not be reached from this site. Please try again in a moment.', 'npcink-cloud-addon' ), $troubleshoot_label, $copy, false ),
			array( __( 'Npcink Cloud is temporarily unavailable. Try again in a few minutes.', 'npcink-cloud-addon' ), $troubleshoot_label, $copy, false ),
			array( __( 'Npcink Cloud did not return usable output for this request. Please try again.', 'npcink-cloud-addon' ), $troubleshoot_label, $copy, false ),
			array( __( 'Npcink Cloud returned an unexpected image result. Please try again.', 'npcink-cloud-addon' ), $troubleshoot_label, $copy, false ),
			array( __( 'The generated image could not be verified for safe delivery. Please try again.', 'npcink-cloud-addon' ), $troubleshoot_label, $copy, false ),
			array( __( 'Npcink Cloud could not confirm delivery of the generated image. Please try again.', 'npcink-cloud-addon' ), $troubleshoot_label, $copy, false ),
		);
		foreach ( $families as [ $message, $label, $url, $requires_admin ] ) {
			$rules[] = array(
				'messages' => array( $message ),
				'message' => ! $can_manage && $requires_admin ? $message . ' ' . __( 'Ask your site administrator to open Cloud Addon > Checks to resolve the connection or account issue.', 'npcink-cloud-addon' ) : $message,
				'label' => $label,
				'url' => $can_manage ? $url : '',
			);
		}
		$handle = 'npcink-cloud-addon-editor-error-actions';
		wp_enqueue_script( $handle, plugins_url( 'assets/editor-error-actions.js', NPCINK_CLOUD_ADDON_FILE ), array( 'wp-data', 'wp-notices' ), NPCINK_CLOUD_ADDON_VERSION, true );
		wp_localize_script( $handle, 'NpcinkCloudEditorErrorActions', array( 'rules' => $rules ) );
	}
}
