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
		private const CONTRACT_SOURCE_WORDPRESS = 'wordpress_abilities_api';
		private const CONTRACT_SOURCE_TOOLKIT = 'npcink_abilities_toolkit';
		private const TOOLKIT_CONTRACT_VERSION = 'v1';

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
			'ai/alt-text-generation' => array(
				'task'                 => 'alt_text_suggest',
				'task_family'         => 'generation',
				'context_requirements' => array(),
				'constraints'          => array( 'single_value', 'source_grounded' ),
			),
			'ai/image-prompt-generation' => array(
				'task'                 => 'image_prompt_generation',
				'task_family'         => 'generation',
				'context_requirements' => array( 'current_content', 'site_style_profile' ),
				'constraints'          => array( 'single_value', 'source_grounded' ),
			),
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
			'ai/content-translation' => array(
				'task'                 => 'content_translation',
				'task_family'          => 'transformation',
				'context_requirements' => array( 'current_content' ),
				'constraints'          => array( 'single_value', 'source_grounded' ),
			),
			'ai/slug-generation' => array(
				'task'                 => 'slug_generation',
				'task_family'          => 'generation',
				'context_requirements' => array( 'current_content' ),
				'constraints'          => array( 'json_object', 'source_grounded' ),
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
				'task'                 => 'editorial_updates',
				'task_family'          => 'transformation',
				'context_requirements' => array( 'current_content' ),
				'constraints'          => array( 'single_value', 'source_grounded', 'no_new_numbers' ),
			),
			'ai/editorial-notes' => array(
				'task'                 => 'editorial_notes',
				'task_family'          => 'analysis',
				'context_requirements' => array( 'current_content' ),
				'constraints'          => array( 'json_object', 'source_grounded' ),
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
			$projection['ability_id']       = $projection['ability_name'];
			$is_toolkit_ability = str_starts_with( $projection['ability_name'], 'npcink-abilities-toolkit/' );
			$projection['contract_source']  = $is_toolkit_ability ? self::CONTRACT_SOURCE_TOOLKIT : self::CONTRACT_SOURCE_WORDPRESS;
			$projection['verification_state'] = 'mapping_current';
			$input_schema = method_exists( $ability, 'get_input_schema' ) ? $ability->get_input_schema() : array();
			$projection['input_schema']     = is_array( $input_schema ) ? $input_schema : array();
			$projection['output_schema']    = $ability->get_output_schema();
			$projection['schema_hash']      = self::schema_hash( $projection['input_schema'], is_array( $projection['output_schema'] ) ? $projection['output_schema'] : array() );
			$projection['write_posture']    = 'suggestion_only';

			if ( $is_toolkit_ability ) {
				$toolkit_check = self::validate_toolkit_contract( $projection['ability_name'], $projection['input_schema'], $projection['output_schema'] );
				if ( is_wp_error( $toolkit_check ) ) {
					return $toolkit_check;
				}
			}

			return self::normalize( $projection );
		}

		/**
		 * Verifies that a Toolkit-owned Ability still matches its local contract.
		 *
		 * The Toolkit remains the source of truth for its own schemas and posture;
		 * Addon only projects that contract into the Cloud runtime envelope.
		 *
		 * @param string               $ability_name Ability identifier.
		 * @param array<string,mixed>  $input_schema Registered input schema.
		 * @param array<string,mixed>  $output_schema Registered output schema.
		 * @return true|WP_Error
		 */
		private static function validate_toolkit_contract( string $ability_name, array $input_schema, array $output_schema ) {
			if ( ! function_exists( 'npcink_abilities_toolkit_get_registered' ) ) {
				return self::error( 'cloud_ai_task_contract_drift', 'The Toolkit contract source is unavailable for this Ability.' );
			}

			$registered = npcink_abilities_toolkit_get_registered();
			$toolkit    = is_array( $registered ) && is_array( $registered[ $ability_name ] ?? null ) ? $registered[ $ability_name ] : null;
			if ( ! is_array( $toolkit ) ) {
				return self::error( 'cloud_ai_task_contract_drift', 'The Toolkit contract is missing for this Ability.' );
			}

			$toolkit_input  = is_array( $toolkit['input_schema'] ?? null ) ? $toolkit['input_schema'] : array();
			$toolkit_output = is_array( $toolkit['output_schema'] ?? null ) ? $toolkit['output_schema'] : array();
			$toolkit_hash   = (string) ( $toolkit['schema_hash'] ?? self::schema_hash( $toolkit_input, $toolkit_output ) );
			if ( self::schema_hash( $input_schema, $output_schema ) !== $toolkit_hash || self::schema_hash( $toolkit_input, $toolkit_output ) !== $toolkit_hash ) {
				return self::error( 'cloud_ai_task_contract_drift', 'The WordPress Ability schema does not match the Toolkit contract.' );
			}

			if ( self::TOOLKIT_CONTRACT_VERSION !== (string) ( $toolkit['contract_version'] ?? '' ) || (string) ( $toolkit['ability_id'] ?? $ability_name ) !== $ability_name ) {
				return self::error( 'cloud_ai_task_contract_drift', 'The Toolkit contract version or Ability identity is invalid.' );
			}

			$risk_level        = (string) ( $toolkit['risk_level'] ?? 'read' );
			$requires_approval = (bool) ( $toolkit['requires_approval'] ?? false );
			$expected_approval = in_array( $risk_level, array( 'write', 'destructive' ), true );
			$implementation    = is_array( $toolkit['implementation_posture'] ?? null ) ? $toolkit['implementation_posture'] : array();
			$write_posture     = (string) ( $toolkit['write_posture'] ?? $implementation['write_posture'] ?? ( 'read' === $risk_level ? 'read_only' : '' ) );
			if ( ! in_array( $risk_level, array( 'read', 'write', 'destructive' ), true ) || $requires_approval !== $expected_approval || ! in_array( $write_posture, array( 'read_only', 'host_governed_dry_run_first' ), true ) ) {
				return self::error( 'cloud_ai_task_contract_drift', 'The Toolkit contract permission or write posture is incompatible with the local Ability.' );
			}

			return true;
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
			$ability_id = trim( (string) ( $projection['ability_id'] ?? $ability_name ) );
			$contract_source = trim( (string) ( $projection['contract_source'] ?? self::CONTRACT_SOURCE_WORDPRESS ) );
			$verification_state = trim( (string) ( $projection['verification_state'] ?? 'mapping_current' ) );
			$raw_task     = (string) ( $projection['task'] ?? '' );
			$task         = sanitize_key( $raw_task );
			$family       = sanitize_key( (string) ( $projection['task_family'] ?? '' ) );
			$valid_ability_name = 1 === preg_match( '/^[a-z0-9_-]+\/[a-z0-9_-]+$/', $ability_name );
			if ( ! $valid_ability_name || $ability_id !== $ability_name || ! in_array( $contract_source, array( self::CONTRACT_SOURCE_WORDPRESS, self::CONTRACT_SOURCE_TOOLKIT ), true ) || ! in_array( $verification_state, array( 'registered', 'mapped', 'schema_valid', 'mapping_current', 'contract_drift', 'unsupported' ), true ) || '' === $task || $task !== $raw_task || strlen( $task ) > 64 || ! in_array( $family, self::ALLOWED_FAMILIES, true ) ) {
				return self::error( 'cloud_ai_task_contract_identity_invalid', 'AI task contracts require a registered ability, task, and supported task family.' );
			}

			$contexts    = self::normalize_list( $projection['context_requirements'] ?? array(), self::ALLOWED_CONTEXTS );
			$constraints = self::normalize_list( $projection['constraints'] ?? array(), self::ALLOWED_CONSTRAINTS );
			if ( is_wp_error( $contexts ) || is_wp_error( $constraints ) ) {
				return self::error( 'cloud_ai_task_contract_vocabulary_invalid', 'AI task contracts contain an unsupported context requirement or constraint.' );
			}

			$output_schema = is_array( $projection['output_schema'] ?? null ) ? $projection['output_schema'] : array();
			$input_schema  = is_array( $projection['input_schema'] ?? null ) ? $projection['input_schema'] : array();
			$schema_hash  = (string) ( $projection['schema_hash'] ?? '' );
			if ( '' !== $schema_hash && 1 !== preg_match( '/^sha256:[a-f0-9]{64}$/', $schema_hash ) ) {
				return self::error( 'cloud_ai_task_schema_hash_invalid', 'AI task contracts require a stable sha256 schema hash.' );
			}
			$encoded       = wp_json_encode( $output_schema );
			if ( ! is_string( $encoded ) || strlen( $encoded ) > 12000 ) {
				return self::error( 'cloud_ai_task_output_schema_invalid', 'The Ability output schema is too large for runtime projection.' );
			}
			if ( '' !== $schema_hash ) {
				$expected_hash = self::schema_hash( $input_schema, $output_schema );
				if ( $expected_hash !== $schema_hash ) {
					return self::error( 'cloud_ai_task_schema_hash_mismatch', 'The AI task contract schema hash does not match its input and output schemas.' );
				}
			}

			return array(
				'contract_version'     => self::VERSION,
				'ability_id'           => $ability_id,
				'contract_source'      => $contract_source,
				'ability_name'         => $ability_name,
				'task'                 => $task,
				'task_family'          => $family,
				'context_requirements' => $contexts,
				'constraints'          => $constraints,
				'input_schema'         => $input_schema,
				'output_schema'        => $output_schema,
				'schema_hash'          => $schema_hash,
				'risk_level'           => in_array( (string) ( $projection['risk_level'] ?? 'read' ), array( 'read', 'write', 'destructive' ), true ) ? (string) ( $projection['risk_level'] ?? 'read' ) : 'read',
				'requires_approval'    => (bool) ( $projection['requires_approval'] ?? false ),
				'verification_state'   => $verification_state,
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

		/** @param array<string,mixed> $input_schema @param array<string,mixed> $output_schema */
		private static function schema_hash( array $input_schema, array $output_schema ): string {
			$schemas = self::canonicalize( array( 'input_schema' => $input_schema, 'output_schema' => $output_schema ) );
			return 'sha256:' . hash( 'sha256', (string) wp_json_encode( $schemas ) );
		}

		/** @param mixed $value */
		private static function canonicalize( $value ) {
			if ( ! is_array( $value ) ) {
				return $value;
			}
			if ( array_keys( $value ) !== range( 0, count( $value ) - 1 ) ) {
				ksort( $value );
			}
			foreach ( $value as $key => $item ) {
				$value[ $key ] = self::canonicalize( $item );
			}
			return $value;
		}

		private static function error( string $code, string $message ): WP_Error {
			return new WP_Error( $code, $message, array( 'status' => 400 ) );
		}
	}
}
