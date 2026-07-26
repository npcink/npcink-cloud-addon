<?php
/**
 * Read-only projection of registered WordPress AI abilities for Cloud runtime.
 *
 * @package NpcinkCloudAddon
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Npcink_Cloud_AI_Task_Contract' ) ) {
	/**
	 * Projects local Ability truth into a bounded, suggestion-only runtime contract.
	 */
	final class Npcink_Cloud_AI_Task_Contract {
		public const VERSION = 'ai_task_contract.v1';

		private const ALLOWED_FAMILIES = array( 'generation', 'classification', 'transformation', 'analysis' );
		private const ALLOWED_CONTEXTS = array( 'current_content', 'site_style_profile', 'taxonomy_candidates', 'none' );
		private const ALLOWED_CONSTRAINTS = array( 'single_value', 'source_grounded', 'no_new_numbers', 'json_object', 'existing_terms_only' );

		/**
		 * Temporary compatibility projection for ai-wp-admin abilities that do not
		 * yet publish npcink_ai_task_contract metadata themselves.
		 *
		 * @var array<string,array<string,mixed>>
		 */
		private const AI_PLUGIN_COMPATIBILITY = array(
			'ai/title-generation' => array(
				'task'                 => 'title_generation',
				'task_family'          => 'generation',
				'context_requirements' => array( 'current_content', 'site_style_profile' ),
				'constraints'          => array( 'single_value', 'source_grounded', 'no_new_numbers' ),
			),
			'ai/excerpt-generation' => array(
				'task'                 => 'excerpt_generation',
				'task_family'          => 'generation',
				'context_requirements' => array( 'current_content', 'site_style_profile' ),
				'constraints'          => array( 'single_value', 'source_grounded', 'no_new_numbers' ),
			),
			'ai/meta-description' => array(
				'task'                 => 'meta_description',
				'task_family'          => 'generation',
				'context_requirements' => array( 'current_content', 'site_style_profile' ),
				'constraints'          => array( 'single_value', 'source_grounded', 'no_new_numbers' ),
			),
			'ai/summarization' => array(
				'task'                 => 'content_summary',
				'task_family'          => 'analysis',
				'context_requirements' => array( 'current_content', 'site_style_profile' ),
				'constraints'          => array( 'single_value', 'source_grounded', 'no_new_numbers' ),
			),
			'ai/content-classification' => array(
				'task'                 => 'content_classification',
				'task_family'          => 'classification',
				'context_requirements' => array( 'current_content', 'taxonomy_candidates' ),
				'constraints'          => array( 'source_grounded', 'json_object' ),
			),
			'ai/content-resizing' => array(
				'task'                 => 'content_rewrite',
				'task_family'          => 'transformation',
				'context_requirements' => array( 'current_content' ),
				'constraints'          => array( 'single_value', 'source_grounded', 'no_new_numbers' ),
			),
			'ai/editorial-updates' => array(
				'task'                 => 'content_rewrite',
				'task_family'          => 'transformation',
				'context_requirements' => array( 'current_content' ),
				'constraints'          => array( 'single_value', 'source_grounded', 'no_new_numbers' ),
			),
			'ai/editorial-notes' => array(
				'task'                 => 'content_summary',
				'task_family'          => 'analysis',
				'context_requirements' => array( 'current_content' ),
				'constraints'          => array( 'single_value', 'source_grounded', 'no_new_numbers' ),
			),
			'ai/comment-analysis' => array(
				'task'                 => 'comment_moderation',
				'task_family'          => 'classification',
				'context_requirements' => array( 'current_content' ),
				'constraints'          => array( 'source_grounded', 'json_object' ),
			),
			'ai/suggest-reply' => array(
				'task'                 => 'comment_reply_suggest',
				'task_family'          => 'generation',
				'context_requirements' => array( 'current_content' ),
				'constraints'          => array( 'single_value', 'source_grounded', 'no_new_numbers' ),
			),
		);

		/**
		 * Projects one registered Ability without executing or mutating it.
		 *
		 * Ability authors may publish `npcink_ai_task_contract` in Ability meta.
		 * The compatibility table above is only a migration bridge for ai-wp-admin.
		 *
		 * @param string $ability_name Registered Ability name.
		 * @return array<string,mixed>|WP_Error
		 */
		public static function project_registered_ability( string $ability_name ) {
			$ability_name = trim( $ability_name );
			if ( '' === $ability_name || ! function_exists( 'wp_get_ability' ) ) {
				return self::error( 'cloud_ai_task_ability_unavailable', 'A registered WordPress Ability is required for Cloud AI task execution.' );
			}

			$ability = wp_get_ability( $ability_name );
			if ( ! is_object( $ability ) || ! method_exists( $ability, 'get_name' ) || ! method_exists( $ability, 'get_meta' ) || ! method_exists( $ability, 'get_output_schema' ) ) {
				return self::error( 'cloud_ai_task_ability_not_registered', 'The requested WordPress Ability is not registered.' );
			}

			$meta       = $ability->get_meta();
			$projection = is_array( $meta['npcink_ai_task_contract'] ?? null ) ? $meta['npcink_ai_task_contract'] : ( self::AI_PLUGIN_COMPATIBILITY[ $ability_name ] ?? array() );
			/**
			 * Filters the read-only runtime projection for a registered Ability.
			 *
			 * This is an integration seam, not a task registry. The result is still
			 * validated against the fixed v1 vocabulary below.
			 *
			 * @param array<string,mixed> $projection Raw projection.
			 * @param object              $ability Registered Ability object.
			 */
			$projection = apply_filters( 'npcink_cloud_ai_task_contract_projection', $projection, $ability );
			if ( ! is_array( $projection ) || empty( $projection ) ) {
				return self::error( 'cloud_ai_task_contract_missing', 'The registered Ability does not publish a Cloud AI task contract.' );
			}

			$projection['contract_version'] = self::VERSION;
			$projection['ability_name']     = (string) $ability->get_name();
			$projection['output_schema']    = $ability->get_output_schema();
			$projection['write_posture']    = 'suggestion_only';

			return self::normalize( $projection );
		}

		/**
		 * Validates and normalizes a projected task contract.
		 *
		 * @param array<string,mixed> $projection Raw projection.
		 * @return array<string,mixed>|WP_Error
		 */
		public static function normalize( array $projection ) {
			if ( self::VERSION !== (string) ( $projection['contract_version'] ?? '' ) ) {
				return self::error( 'cloud_ai_task_contract_version_invalid', 'AI task contracts require ai_task_contract.v1.' );
			}

			$ability_name = trim( (string) ( $projection['ability_name'] ?? '' ) );
			$raw_task     = (string) ( $projection['task'] ?? '' );
			$task         = sanitize_key( $raw_task );
			$family       = sanitize_key( (string) ( $projection['task_family'] ?? '' ) );
			$valid_ability_name = 1 === preg_match( '/^[a-z0-9_-]+\/[a-z0-9_-]+$/', $ability_name );
			if ( ! $valid_ability_name || '' === $task || $task !== $raw_task || strlen( $task ) > 64 || ! in_array( $family, self::ALLOWED_FAMILIES, true ) ) {
				return self::error( 'cloud_ai_task_contract_identity_invalid', 'AI task contracts require a registered ability, task, and supported task family.' );
			}

			$contexts    = self::normalize_list( $projection['context_requirements'] ?? array(), self::ALLOWED_CONTEXTS );
			$constraints = self::normalize_list( $projection['constraints'] ?? array(), self::ALLOWED_CONSTRAINTS );
			if ( is_wp_error( $contexts ) || is_wp_error( $constraints ) ) {
				return self::error( 'cloud_ai_task_contract_vocabulary_invalid', 'AI task contracts contain an unsupported context requirement or constraint.' );
			}

			$output_schema = is_array( $projection['output_schema'] ?? null ) ? $projection['output_schema'] : array();
			$encoded       = wp_json_encode( $output_schema );
			if ( ! is_string( $encoded ) || strlen( $encoded ) > 12000 ) {
				return self::error( 'cloud_ai_task_output_schema_invalid', 'The Ability output schema is too large for runtime projection.' );
			}

			return array(
				'contract_version'     => self::VERSION,
				'ability_name'         => $ability_name,
				'task'                 => $task,
				'task_family'          => $family,
				'context_requirements' => $contexts,
				'constraints'          => $constraints,
				'output_schema'        => $output_schema,
				'write_posture'        => 'suggestion_only',
			);
		}

		/** @return array<int,string>|WP_Error */
		private static function normalize_list( $value, array $allowed ) {
			if ( ! is_array( $value ) ) {
				return self::error( 'cloud_ai_task_contract_list_invalid', 'AI task contract list fields must be arrays.' );
			}
			$is_list = empty( $value ) || array_keys( $value ) === range( 0, count( $value ) - 1 );
			if ( ! $is_list ) {
				return self::error( 'cloud_ai_task_contract_list_invalid', 'AI task contract list fields must be arrays.' );
			}
			foreach ( $value as $item ) {
				if ( ! is_string( $item ) ) {
					return self::error( 'cloud_ai_task_contract_value_invalid', 'AI task contract list fields must contain strings.' );
				}
			}
			$normalized = array_values( array_unique( array_map( 'sanitize_key', $value ) ) );
			foreach ( $normalized as $item ) {
				if ( ! in_array( $item, $allowed, true ) ) {
					return self::error( 'cloud_ai_task_contract_value_invalid', 'AI task contract list fields contain an unsupported value.' );
				}
			}
			if ( in_array( 'none', $normalized, true ) && 1 !== count( $normalized ) ) {
				return self::error( 'cloud_ai_task_contract_none_invalid', 'AI task contract context none cannot be combined with other values.' );
			}
			return $normalized;
		}

		private static function error( string $code, string $message ): WP_Error {
			return new WP_Error( $code, $message, array( 'status' => 400 ) );
		}
	}
}
