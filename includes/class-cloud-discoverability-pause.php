<?php
/**
 * Reversible specialist-task availability guard. Local Ability truth stays local.
 *
 * @package NpcinkCloudAddon
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

final class Npcink_Cloud_Discoverability_Pause {
	public const ACTIVE = true;

	/** Release policy access point shared by hooks and transport guards. */
	public static function is_active(): bool {
		return self::ACTIVE;
	}

	public static function register_hooks(): void {
		if ( self::is_active() ) {
			add_filter( 'wpai_feature_meta-description_enabled', '__return_false', PHP_INT_MAX );
			add_filter( 'gettext_ai', array( self::class, 'filter_ui_description' ), PHP_INT_MAX, 3 );
			add_filter( 'wpai_default_feature_classes', array( self::class, 'filter_features' ), PHP_INT_MAX );
		}
	}

	/** Uses the host feature filter, leaving saved options and source untouched. */
	public static function filter_features( array $features ): array {
		if ( self::is_active() ) { unset( $features['meta-description'] ); }
		return $features;
	}

	/** Fixed feature UI copy only; never alters Ability metadata or schemas. */
	public static function filter_ui_description( string $translated, string $text, string $domain ): string {
		if ( ! self::is_active() || 'ai' !== $domain ) { return $translated; }
		if ( 'Suggests SEO-friendly permalink slugs from post title or content. Requires an AI connector that includes support for text generation models.' === $text ) {
			return __( 'Suggests short, readable permalink slugs from post title or content. Requires an AI connector that includes support for text generation models.', 'npcink-cloud-addon' );
		}
		if ( 'Adds editorial suggestions to posts block-by-block, covering Accessibility, Readability, Grammar, and SEO. Requires an AI connector that includes support for text generation models.' === $text ) {
			return __( 'Adds editorial suggestions to posts block-by-block, covering Accessibility, Readability, and Grammar. Requires an AI connector that includes support for text generation models.', 'npcink-cloud-addon' );
		}
		return $translated;
	}

	public static function is_task_paused( string $task ): bool {
		return self::is_active() && in_array( strtolower( trim( $task ) ), array( 'meta_description', 'seo_title', 'seo_headline', 'seo_description', 'seo_metadata', 'aeo_summary', 'answer_summary', 'geo_summary', 'structured_data_hints', 'content_discoverability' ), true );
	}

	/** Keeps generic review types while matching specialist names case-insensitively. */
	public static function without_specialist_reviews( array $types ): array {
		return array_values( array_filter( $types, static function ( $type ): bool {
			return ! in_array( strtolower( trim( (string) $type ) ), array( 'seo', 'aeo', 'geo' ), true );
		} ) );
	}

	/** Rejects legacy runtime requests before HTTP or signing. */
	public static function runtime_error( array $payload ) {
		$operation = is_array( $payload['input']['operation_contract'] ?? null ) ? $payload['input']['operation_contract'] : array();
		$task = strtolower( trim( (string) ( $operation['task'] ?? '' ) ) );
		$types = $operation['request']['review_types'] ?? array();
		if ( self::is_task_paused( $task ) || ( self::is_active() && 'editorial_notes' === $task && is_array( $types ) && ! empty( $types ) && count( $types ) !== count( self::without_specialist_reviews( $types ) ) ) ) {
			return new WP_Error( 'cloud_feature_paused', __( 'SEO/AEO/GEO specialist assistance is paused.', 'npcink-cloud-addon' ), array( 'status' => 409 ) );
		}
		return null;
	}
}
