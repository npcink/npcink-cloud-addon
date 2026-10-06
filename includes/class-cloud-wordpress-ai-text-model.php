<?php
/**
 * Scene-bound text generation model for the WordPress AI PHP client.
 *
 * @package NpcinkCloudAddon
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if (
	interface_exists( 'WordPress\\AiClient\\Providers\\Models\\Contracts\\ModelInterface' )
	&& interface_exists( 'WordPress\\AiClient\\Providers\\Models\\TextGeneration\\Contracts\\TextGenerationModelInterface' )
	&& ! class_exists( 'Npcink_Cloud_WordPress_AI_Text_Model' )
) {
	/**
	 * Scene-gated text model that forwards only known AI plugin ability calls to Cloud.
	 */
	final class Npcink_Cloud_WordPress_AI_Text_Model extends Npcink_Cloud_WordPress_AI_Scene_Model implements
		\WordPress\AiClient\Providers\Models\Contracts\ModelInterface,
		\WordPress\AiClient\Providers\Models\TextGeneration\Contracts\TextGenerationModelInterface {

		/**
		 * Generates a text result through the bounded Cloud runtime seam.
		 *
		 * @param list<\WordPress\AiClient\Messages\DTO\Message> $prompt Prompt messages.
		 * @return \WordPress\AiClient\Results\DTO\GenerativeAiResult
		 */
		public function generateTextResult( array $prompt ): \WordPress\AiClient\Results\DTO\GenerativeAiResult {
			// Clear a prior ability's evidence before any validation or runtime call.
			// A failed request must never inherit the previous Cloud run ID.
			Npcink_Cloud_WordPress_AI_Connector::record_cloud_run_id( '' );
			Npcink_Cloud_WordPress_AI_Connector::reset_runtime_failure_evidence();
			$ability_name = $this->detect_scene_ability_name();
			if ( '' === $ability_name ) {
				throw new \WordPress\AiClient\Common\Exception\RuntimeException( Npcink_Cloud_WordPress_AI_Connector::user_facing_connector_error( 'scene_not_supported', 'cloud_wp_ai_scene_not_supported' ) );
			}
			$task_contract = npcink_cloud_addon_project_ai_task_contract( $ability_name );
			if ( is_wp_error( $task_contract ) ) {
				throw new \WordPress\AiClient\Common\Exception\RuntimeException( Npcink_Cloud_WordPress_AI_Connector::user_facing_connector_error( 'task_contract_rejected', (string) $task_contract->get_error_code(), (string) $task_contract->get_error_message() ) );
			}
			$task = (string) $task_contract['task'];

			if ( 1 !== count( $prompt ) ) {
				throw new \WordPress\AiClient\Common\Exception\RuntimeException( Npcink_Cloud_WordPress_AI_Connector::user_facing_connector_error( 'chat_history_not_supported', 'cloud_wp_ai_chat_history_not_supported' ) );
			}

			if ( null !== $this->config->getFunctionDeclarations() || null !== $this->config->getWebSearch() ) {
				throw new \WordPress\AiClient\Common\Exception\RuntimeException( Npcink_Cloud_WordPress_AI_Connector::user_facing_connector_error( 'tools_not_supported', 'cloud_wp_ai_tools_not_supported' ) );
			}

			$text = $this->prompt_text( $prompt );
			if ( '' === $text ) {
				throw new \WordPress\AiClient\Common\Exception\RuntimeException( Npcink_Cloud_WordPress_AI_Connector::user_facing_connector_error( 'scene_input_required', 'cloud_wp_ai_scene_input_required' ) );
			}
			$ability_context = Npcink_Cloud_WordPress_AI_Connector::current_text_ability_context();

			$scene_input = array(
				'response_format'    => $this->response_format_hint( $task, $task_contract ),
				'candidate_count'    => $this->config->getCandidateCount(),
				'max_tokens'         => $this->config->getMaxTokens(),
				'temperature'        => $this->config->getTemperature(),
				'scene_gate'         => array(
					'source' => 'wordpress_ai_plugin_ability',
					'task'   => $task,
				),
			);
			$context_input = is_array( $ability_context['input'] ?? null ) ? $ability_context['input'] : array();
			if ( in_array( $task, array( 'title_generation', 'content_summary', 'content_rewrite', 'content_translation', 'editorial_updates' ), true ) ) {
				$scene_input['source_text'] = $text;
			} elseif ( in_array( $task, array( 'excerpt_generation', 'meta_description' ), true ) ) {
				$short_text_projection = $this->project_short_text_scene_request( $task, $text, $context_input );
				$scene_input = array_merge( $scene_input, $short_text_projection );
			} else {
				$scene_input['prompt'] = $text;
			}
			if ( 'content_classification' === $task ) {
				$taxonomy = sanitize_key( (string) ( $context_input['taxonomy'] ?? '' ) );
				$strategy = sanitize_key( (string) ( $context_input['strategy'] ?? '' ) );
				$max_suggestions = absint( $context_input['max_suggestions'] ?? 0 );
				if ( '' !== $taxonomy ) {
					$scene_input['taxonomy'] = substr( $taxonomy, 0, 64 );
				}
				if ( in_array( $strategy, array( 'existing_only', 'allow_new' ), true ) ) {
					$scene_input['strategy'] = $strategy;
				}
				if ( 0 < $max_suggestions ) {
					$scene_input['max_suggestions'] = min( 10, $max_suggestions );
				}
			}
			if ( 'content_translation' === $task ) {
				$target_language = sanitize_key( (string) ( $context_input['target_language'] ?? '' ) );
				$content = trim( (string) ( $context_input['content'] ?? '' ) );
				if ( '' !== $content ) {
					$scene_input['source_text'] = $content;
				}
				if ( '' !== $target_language ) {
					$scene_input['target_language'] = substr( $target_language, 0, 20 );
				}
			}
			if ( 'editorial_updates' === $task ) {
				$block_content = trim( (string) ( $context_input['block_content'] ?? '' ) );
				$notes = is_array( $context_input['notes'] ?? null ) ? $context_input['notes'] : array();
				// Keep the official Ability prompt: it already contains the block,
				// context, and note framing. Replacing it with block_content alone
				// makes the Provider believe no Notes were supplied.
				if ( '' === trim( (string) $scene_input['source_text'] ) && '' !== $block_content ) {
					$scene_input['source_text'] = $block_content;
				}
				if ( ! empty( $notes ) ) {
					$scene_input['editorial_notes_instruction'] = 'Editorial notes to apply: ' . implode( ' | ', array_map( 'sanitize_text_field', $notes ) );
				}
			}
			if ( 'editorial_notes' === $task ) {
				$block_content = trim( (string) ( $context_input['block_content'] ?? '' ) );
				$review_types = is_array( $context_input['review_types'] ?? null ) ? $context_input['review_types'] : array();
				$scene_input['prompt'] = trim( $text . ( '' !== $block_content ? "\n\nBlock content:\n" . $block_content : '' ) . ( ! empty( $review_types ) ? "\nReview types: " . implode( ', ', array_map( 'sanitize_key', $review_types ) ) : '' ) );
			}
			if ( 'title_generation' === $task ) {
				$context_input = is_array( $ability_context['input'] ?? null ) ? $ability_context['input'] : array();
				$context_post_id = absint( $context_input['context'] ?? 0 );
				$context_post = $context_post_id > 0 ? get_post( $context_post_id ) : null;
				if ( $context_post instanceof \WP_Post && '' !== trim( (string) $context_post->post_title ) ) {
					$existing_title = wp_strip_all_tags( (string) $context_post->post_title );
					$scene_input['existing_title'] = function_exists( 'mb_substr' )
						? mb_substr( $existing_title, 0, 160 )
						: substr( $existing_title, 0, 160 );
				}
			}
			$system_instruction = (string) ( $this->config->getSystemInstruction() ?? '' );
			if ( '' !== trim( $system_instruction ) ) {
				$scene_input['system_instruction'] = trim(
					(string) ( $scene_input['system_instruction'] ?? '' ) . "\n\n" . $system_instruction
				);
			}
			if ( 'editorial_updates' === $task && '' !== (string) ( $scene_input['editorial_notes_instruction'] ?? '' ) ) {
				$scene_input['system_instruction'] = trim(
					(string) ( $scene_input['system_instruction'] ?? '' ) . "\n\n" . $scene_input['editorial_notes_instruction']
				);
				unset( $scene_input['editorial_notes_instruction'] );
			}
			$site_knowledge_reference_mode = $this->site_knowledge_reference_mode( $task );
			if ( '' !== $site_knowledge_reference_mode && Npcink_Cloud_Addon_Settings::is_site_knowledge_generation_reference_enabled() ) {
				$scene_input['site_knowledge_reference'] = array(
					'enabled' => true,
					'mode'    => $site_knowledge_reference_mode,
				);
			}

			$journey_input = $ability_name === (string) ( $ability_context['ability_id'] ?? '' )
				&& is_array( $ability_context['input'] ?? null )
				? $ability_context['input']
				: array();
			$journey_session_id = Npcink_Cloud_Customer_Journey::build_session_id( $task, $journey_input );
			Npcink_Cloud_Customer_Journey::capture_generation(
				$task,
				'started',
				$journey_session_id
			);

			$started  = Npcink_Cloud_WordPress_AI_Connector::runtime_timer_start();
			$response = npcink_cloud_addon_execute_registered_ai_task_runtime(
				$ability_name,
				$scene_input,
				'trace_wp_ai_connector_' . wp_generate_uuid4(),
				'wp_ai_connector_' . wp_generate_uuid4()
			);
			$duration_ms = Npcink_Cloud_WordPress_AI_Connector::runtime_timer_elapsed_ms( $started );
			$log_event = array(
					'type'                       => 'text',
					'operation'                  => 'npcink-cloud/connector-runtime',
					'task'                       => $task,
					'contract_version'           => 'cloud_connector_runtime.v1',
					'operation_contract_version' => 'wordpress_operation.v1',
					'response'                   => $response,
					'duration_ms'                => $duration_ms,
					'fallback_model_id'          => Npcink_Cloud_WordPress_AI_Connector::MODEL_ID,
				);

			if ( is_wp_error( $response ) ) {
				$error_code = sanitize_key( (string) $response->get_error_code() );
				$evidence = Npcink_Cloud_WordPress_AI_Connector::record_runtime_failure_from_wp_error( $response );
				$log_event['cloud_run_id'] = (string) ( $evidence['run_id'] ?? '' );
				Npcink_Cloud_WordPress_AI_Connector::maybe_log_wordpress_ai_request_evidence( $log_event );
				Npcink_Cloud_Customer_Journey::capture_generation_failure(
					$task,
					$journey_session_id,
					$duration_ms,
					$response->get_error_code()
				);
				$cloud_code = $evidence['cloud_error_code'];
				$error_stage = $evidence['error_stage'];
				$diagnostic = '' !== $cloud_code ? $cloud_code : $error_code;
				if ( '' !== $error_stage && empty( $evidence['synthetic_error_stage'] ) ) {
					$diagnostic .= ':' . $error_stage;
				}
				throw new \WordPress\AiClient\Common\Exception\RuntimeException( Npcink_Cloud_WordPress_AI_Connector::user_facing_runtime_failure( $error_code, $diagnostic, (string) $response->get_error_message() ) );
			}

			$output_text = $this->extract_text( is_array( $response ) ? $response : array(), $task );
			if ( '' === $output_text ) {
				$cloud_run_id = Npcink_Cloud_WordPress_AI_Connector::cloud_run_id_from_response( $response );
				Npcink_Cloud_WordPress_AI_Connector::record_cloud_run_id( $cloud_run_id );
				Npcink_Cloud_WordPress_AI_Connector::record_runtime_failure_evidence( array(
					'run_id'         => $cloud_run_id,
					'error_stage'    => 'output_validation',
					'quality_reason' => 'output_missing',
				) );
				$log_event['validation_error'] = new WP_Error( 'cloud_wp_ai_output_missing', 'Cloud response did not include valid text output.' );
				Npcink_Cloud_WordPress_AI_Connector::maybe_log_wordpress_ai_request_evidence( $log_event );
				Npcink_Cloud_Customer_Journey::capture_generation_failure(
					$task,
					$journey_session_id,
					$duration_ms,
					'cloud_wp_ai_output_missing'
				);
				throw new \WordPress\AiClient\Common\Exception\RuntimeException( Npcink_Cloud_WordPress_AI_Connector::user_facing_connector_error( 'output_missing', 'cloud_wp_ai_output_missing' ) );
			}

			Npcink_Cloud_WordPress_AI_Connector::maybe_log_wordpress_ai_request_evidence( $log_event );
			$cloud_run_id = Npcink_Cloud_WordPress_AI_Connector::cloud_run_id_from_response( $response );
			Npcink_Cloud_WordPress_AI_Connector::record_cloud_run_id( $cloud_run_id );
			$run_id = '' !== $cloud_run_id ? $cloud_run_id : wp_generate_uuid4();
			Npcink_Cloud_Customer_Journey::capture_generation(
				$task,
				'succeeded',
				$journey_session_id,
				$duration_ms,
				$run_id
			);
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
					$duration_ms,
					$journey_session_id
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
		 * Projects the official short-text Ability input into the Cloud scene.
		 *
		 * Excerpt and meta-description abilities carry the actual post content in
		 * validated Ability input, while their AI Client message is an instruction.
		 * Keep those roles separate so Cloud can ground the result in the content
		 * without losing the official instruction or asking the model to infer the
		 * source from a generic prompt.
		 *
		 * @param string              $task Task name.
		 * @param string              $instruction Official AI Client instruction.
		 * @param array<string,mixed> $context_input Validated Ability input.
		 * @return array<string,string>
		 */
		private function project_short_text_scene_request( string $task, string $instruction, array $context_input ): array {
			$content = trim( (string) ( $context_input['content'] ?? '' ) );
			if ( '' === $content && function_exists( 'get_post' ) ) {
				$post_id = absint( $context_input['post_id'] ?? $context_input['context'] ?? 0 );
				$post    = $post_id > 0 ? get_post( $post_id ) : null;
				if ( is_object( $post ) ) {
					$content = trim( (string) ( $post->post_content ?? '' ) );
				}
			}

			if ( '' === $content ) {
				return array( 'prompt' => $instruction );
			}

			$source_text = $content;
			if ( 'meta_description' === $task ) {
				$title = trim( (string) ( $context_input['title'] ?? '' ) );
				if ( '' === $title && function_exists( 'get_post' ) ) {
					$post_id = absint( $context_input['post_id'] ?? $context_input['context'] ?? 0 );
					$post    = $post_id > 0 ? get_post( $post_id ) : null;
					if ( is_object( $post ) ) {
						$title = trim( (string) ( $post->post_title ?? '' ) );
					}
				}
				if ( '' !== $title ) {
					$source_text = "Title:\n" . $title . "\n\nContent:\n" . $content;
				}
			}

			return array(
				'source_text'       => $source_text,
				'system_instruction' => $instruction,
			);
		}

		/**
		 * Returns a shallow response-format hint for Cloud-side scene projection.
		 *
		 * @param string $task          WordPress AI ability task.
		 * @param array  $task_contract Projected local Ability contract.
		 * @return string
		 */
		private function response_format_hint( string $task, array $task_contract = array() ): string {
			$constraints = is_array( $task_contract['constraints'] ?? null ) ? $task_contract['constraints'] : array();
			return in_array( 'json_object', $constraints, true ) || in_array( $task, array( 'content_classification', 'comment_moderation' ), true ) ? 'json' : 'text';
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
				'WordPress\\AI\\Abilities\\Image\\Generate_Image_Prompt' => 'ai/image-prompt-generation',
				'WordPress\\AI\\Abilities\\Content_Classification\\Content_Classification' => 'ai/content-classification',
				'WordPress\\AI\\Abilities\\Comment_Moderation\\Comment_Analysis'           => 'ai/comment-analysis',
				'WordPress\\AI\\Abilities\\Content_Resizing\\Content_Resizing'              => 'ai/content-resizing',
				'WordPress\\AI\\Abilities\\Content_Translation\\Content_Translation'        => 'ai/content-translation',
				'WordPress\\AI\\Abilities\\Editorial_Updates\\Editorial_Updates'            => 'ai/editorial-updates',
				'WordPress\\AI\\Abilities\\Editorial_Notes\\Editorial_Notes'                => 'ai/editorial-notes',
				'WordPress\\AI\\Abilities\\Excerpt_Generation\\Excerpt_Generation'          => 'ai/excerpt-generation',
				'WordPress\\AI\\Abilities\\Meta_Description\\Meta_Description'              => 'ai/meta-description',
				'WordPress\\AI\\Abilities\\Slug_Generation\\Slug_Generation'                => 'ai/slug-generation',
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
	}
}
