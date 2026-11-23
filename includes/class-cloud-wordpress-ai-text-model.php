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

		private const TITLE_RECOVERY_TTL = 900;
		private const TITLE_RECOVERY_PENDING = 'cloud_wp_ai_title_recovery_pending';
		private const TITLE_RECOVERY_UNAVAILABLE = 'cloud_wp_ai_title_recovery_unavailable';

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
			$ability_context = Npcink_Cloud_WordPress_AI_Connector::consume_text_ability_context();
			self::assert_connector_approved();
			$ability_name = (string) ( $ability_context['ability_id'] ?? '' );
			if ( '' === $ability_name || 'ai/alt-text-generation' === $ability_name ) {
				self::throw_user_facing( Npcink_Cloud_WordPress_AI_Connector::user_facing_connector_error( 'scene_not_supported', 'cloud_wp_ai_scene_not_supported' ) );
			}
			$task_contract = npcink_cloud_addon_project_ai_task_contract( $ability_name );
			if ( is_wp_error( $task_contract ) ) {
				self::throw_user_facing( Npcink_Cloud_WordPress_AI_Connector::user_facing_connector_error( 'task_contract_rejected', (string) $task_contract->get_error_code(), (string) $task_contract->get_error_message() ) );
			}
			$task = (string) $task_contract['task'];

			if ( 1 !== count( $prompt ) ) {
				self::throw_user_facing( Npcink_Cloud_WordPress_AI_Connector::user_facing_connector_error( 'chat_history_not_supported', 'cloud_wp_ai_chat_history_not_supported' ) );
			}

			if ( null !== $this->config->getFunctionDeclarations() || null !== $this->config->getWebSearch() ) {
				self::throw_user_facing( Npcink_Cloud_WordPress_AI_Connector::user_facing_connector_error( 'tools_not_supported', 'cloud_wp_ai_tools_not_supported' ) );
			}

			$text = $this->prompt_text( $prompt );
			if ( '' === $text ) {
				self::throw_user_facing( Npcink_Cloud_WordPress_AI_Connector::user_facing_connector_error( 'scene_input_required', 'cloud_wp_ai_scene_input_required' ) );
			}
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
				$source_prompt = 'excerpt_generation' === $task && defined( 'WPAI_VERSION' ) && version_compare( WPAI_VERSION, '1.5.0', '>=' );
				$short_text_projection = $this->project_short_text_scene_request( $task, $text, $context_input, $source_prompt );
				$scene_input = array_merge( $scene_input, $short_text_projection );
			} else {
				$scene_input['prompt'] = $text;
			}
			if ( 'content_rewrite' === $task ) {
				$scene_input['rewrite_action'] = $context_input['action'] ?? 'rephrase';
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
				$scene_input['review_types'] = is_array( $context_input['review_types'] ?? null ) ? $context_input['review_types'] : array();
				$scene_input['prompt'] = trim( $text . ( '' !== $block_content ? "\n\nBlock content:\n" . $block_content : '' ) . ( ! empty( $scene_input['review_types'] ) ? "\nReview types: " . implode( ', ', array_map( 'sanitize_key', $scene_input['review_types'] ) ) : '' ) );
			}
			if ( 'title_generation' === $task ) {
				$context_post_id = absint( $context_input['context'] ?? 0 );
				$context_post = $context_post_id > 0 ? get_post( $context_post_id ) : null;
				$existing_title = (string) ( $context_input['current_title'] ?? ( $context_post instanceof \WP_Post ? $context_post->post_title : '' ) );
				if ( '' !== trim( $existing_title ) ) {
					$existing_title = wp_strip_all_tags( $existing_title );
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

			$started = Npcink_Cloud_WordPress_AI_Connector::runtime_timer_start();
			$trace_id = 'trace_wp_ai_connector_' . wp_generate_uuid4();
			$response = 'ai/title-generation' === $ability_name && 'title_generation' === $task
				? $this->execute_title_runtime( $ability_name, $scene_input, $trace_id, absint( $context_input['context'] ?? 0 ) )
				: npcink_cloud_addon_execute_registered_ai_task_runtime( $ability_name, $scene_input, $trace_id, 'wp_ai_connector_' . wp_generate_uuid4() );
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
				if ( self::TITLE_RECOVERY_PENDING === $error_code ) {
					self::throw_user_facing( $response->get_error_message() );
				}
				Npcink_Cloud_Customer_Journey::capture_generation_failure(
					$task,
					$journey_session_id,
					$duration_ms,
					$response->get_error_code()
				);
				if ( self::TITLE_RECOVERY_UNAVAILABLE === $error_code ) {
					self::throw_user_facing( $response->get_error_message() );
				}
				$cloud_code = $evidence['cloud_error_code'];
				$error_stage = $evidence['error_stage'];
				$diagnostic = '' !== $cloud_code ? $cloud_code : $error_code;
				if ( '' !== $error_stage && empty( $evidence['synthetic_error_stage'] ) ) {
					$diagnostic .= ':' . $error_stage;
				}
				self::throw_user_facing( Npcink_Cloud_WordPress_AI_Connector::user_facing_runtime_failure( $error_code, $diagnostic, (string) $response->get_error_message() ) );
			}

			$output_text = $this->extract_text( is_array( $response ) ? $response : array(), $task );
			$output_valid = '' !== $output_text && $this->valid_editor_output( $task, $output_text );
			if ( ! $output_valid ) {
				$cloud_run_id = Npcink_Cloud_WordPress_AI_Connector::cloud_run_id_from_response( $response );
				Npcink_Cloud_WordPress_AI_Connector::record_cloud_run_id( $cloud_run_id );
				$run_in_progress = Npcink_Cloud_WordPress_AI_Connector::response_indicates_in_progress_run( $response );
				Npcink_Cloud_WordPress_AI_Connector::record_runtime_failure_evidence( array(
					'run_id'         => $cloud_run_id,
					'error_stage'    => 'output_validation',
					'quality_reason' => $run_in_progress ? 'run_in_progress' : ( '' === $output_text ? 'output_missing' : 'output_invalid' ),
				) );
				$log_event['validation_error'] = new WP_Error(
					$run_in_progress ? 'cloud_wp_ai_run_in_progress' : 'cloud_wp_ai_output_missing',
					$run_in_progress ? 'Cloud run was still in progress.' : 'Cloud response did not include valid text output.'
				);
				Npcink_Cloud_WordPress_AI_Connector::maybe_log_wordpress_ai_request_evidence( $log_event );
				Npcink_Cloud_Customer_Journey::capture_generation_failure(
					$task,
					$journey_session_id,
					$duration_ms,
					$run_in_progress ? 'cloud_wp_ai_run_in_progress' : 'cloud_wp_ai_output_missing'
				);
				if ( $run_in_progress ) {
					self::throw_user_facing( Npcink_Cloud_WordPress_AI_Connector::user_facing_connector_error( 'runtime_run_in_progress', 'cloud_wp_ai_run_in_progress' ) );
				}
				self::throw_user_facing( Npcink_Cloud_WordPress_AI_Connector::user_facing_connector_error( 'output_missing', 'cloud_wp_ai_output_missing' ) );
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
		 * Retains only a short-lived key for an ambiguous title dispatch.
		 *
		 * @param string              $ability_name Native title Ability.
		 * @param array<string,mixed> $input Projected scene request.
		 * @param string              $trace_id Request trace.
		 * @param int                 $post_id Editor context.
		 * @return array<string,mixed>|\WP_Error
		 */
		private function execute_title_runtime( string $ability_name, array $input, string $trace_id, int $post_id ) {
			$user_id = get_current_user_id();
			$settings = Npcink_Cloud_Addon_Settings::get_settings();
			$scope = wp_json_encode( array( $settings['base_url'] ?? '', $settings['site_id'] ?? '', $settings['key_id'] ?? '', $user_id, $post_id, $input ) );
			$cache_key = $user_id > 0 && $post_id > 0 && is_string( $scope )
				? 'npcink_cloud_title_recovery_' . hash_hmac( 'sha256', $scope, wp_salt( 'auth' ) )
				: '';
			$pending_key = '' !== $cache_key ? get_transient( $cache_key ) : false;
			if ( is_string( $pending_key ) && '' !== $pending_key ) {
				return $this->recover_title_result( $cache_key, $pending_key, $trace_id );
			}

			$key = 'wp_ai_connector_' . wp_generate_uuid4();
			$response = npcink_cloud_addon_execute_registered_ai_task_runtime( $ability_name, $input, $trace_id, $key );
			if ( '' !== $cache_key && is_wp_error( $response ) ) {
				$data = $response->get_error_data();
				if ( is_array( $data ) && ! empty( $data['dispatch_unconfirmed'] )
					&& set_transient( $cache_key, $key, self::TITLE_RECOVERY_TTL ) ) {
					return $this->title_recovery_pending();
				}
			}
			return $response;
		}

		/**
		 * Reads only the original receipt/result; missing is not permission to replay.
		 *
		 * @param string $cache_key Local transient key.
		 * @param string $key Original idempotency key.
		 * @param string $trace_id Request trace.
		 * @return array<string,mixed>|\WP_Error
		 */
		private function recover_title_result( string $cache_key, string $key, string $trace_id ) {
			$client = Npcink_Cloud_Runtime_Client_Factory::configured();
			$receipt = $client ? $client->get_run_by_idempotency( $key, $trace_id ) : null;
			$data = is_array( $receipt ) && is_array( $receipt['data'] ?? null ) ? $receipt['data'] : array();
			$run_id = is_string( $data['run_id'] ?? null ) ? $data['run_id'] : '';
			if ( '' === $run_id || $key !== ( $data['idempotency_key'] ?? null ) ) {
				return $this->title_recovery_pending();
			}

			if ( in_array( $data['status'] ?? null, array( 'failed', 'canceled' ), true ) ) {
				delete_transient( $cache_key );
				return new \WP_Error( 'cloud_runtime_request_failed', '', array(
					'cloud_error_code' => $data['error_code'] ?? '',
					'cloud_error_data' => array( 'run_id' => $run_id, 'error_stage' => $data['error_stage'] ?? '' ),
				) );
			}
			if ( $client && 'succeeded' === ( $data['status'] ?? null ) ) {
				$result = $client->get_run_result( $run_id, $trace_id );
				$error_data = is_wp_error( $result ) ? $result->get_error_data() : null;
				if ( is_array( $error_data ) && in_array( $error_data['cloud_error_code'] ?? null, array( 'runtime.result_not_ready', 'runtime.result_expired' ), true ) ) {
					// The successful run is already terminal: no retained result will arrive.
					delete_transient( $cache_key );
					return new \WP_Error(
						self::TITLE_RECOVERY_UNAVAILABLE,
						__( 'The earlier title result is no longer available. Generate a new title to continue.', 'npcink-cloud-addon' ),
						array( 'cloud_error_data' => array( 'run_id' => $run_id ) )
					);
				}
				if ( is_array( $result ) && $run_id === ( $result['data']['run_id'] ?? null )
					&& 'succeeded' === ( $result['data']['status'] ?? null ) && '' !== $this->extract_text( $result, 'title_generation' ) ) {
					delete_transient( $cache_key );
					return $result;
				}
			}
			return $this->title_recovery_pending( $run_id );
		}

		/** Returns a nonterminal notice without exposing the idempotency key. */
		private function title_recovery_pending( string $run_id = '' ): \WP_Error {
			return new \WP_Error(
				self::TITLE_RECOVERY_PENDING,
				__( 'The title result is not available yet. Wait a moment, then try again with the same draft to check the original request.', 'npcink-cloud-addon' ),
				array( 'cloud_error_data' => array( 'run_id' => $run_id ) )
			);
		}

		/**
		 * Projects the official short-text Ability input into the Cloud scene.
		 *
		 * AI 1.5+ excerpt messages already contain source and additional context;
		 * preserve that opaque message. Legacy excerpt and meta-description
		 * messages are instructions, with source in validated Ability input.
		 * Keep both projections separate from the actual system instruction.
		 *
		 * @param string              $task Task name.
		 * @param string              $instruction Official AI Client instruction.
		 * @param array<string,mixed> $context_input Validated Ability input.
		 * @param bool                $source_prompt Whether the upstream message already owns the source.
		 * @return array<string,string>
		 */
		private function project_short_text_scene_request( string $task, string $instruction, array $context_input, bool $source_prompt = false ): array {
			if ( $source_prompt ) {
				return array( 'source_text' => $instruction );
			}
			$content = trim( (string) ( $context_input['content'] ?? '' ) );
			if ( '' === $content && function_exists( 'get_post' ) ) {
				$post_id = absint( $context_input['post_id'] ?? $context_input['context'] ?? 0 );
				$post    = $post_id > 0 ? get_post( $post_id ) : null;
				if ( is_object( $post ) ) {
					$content = trim( (string) ( $post->post_content ?? '' ) );
				}
			}

			// Match the Ability's source cleanup before projecting raw editor HTML.
			if ( '' !== $content && function_exists( 'WordPress\\AI\\normalize_content' ) ) {
				$content = \WordPress\AI\normalize_content( $content );
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
		 * Rejects malformed new editor outputs before the official Ability consumes them.
		 * The host still owns revision IDs, truncation state, sanitization and writes.
		 *
		 * @param string $task Task identifier.
		 * @param string $text Generated text.
		 * @return bool
		 */
		private function valid_editor_output( string $task, string $text ): bool {
			if ( 'block_container_label_generation' === $task ) {
				return 1 === preg_match( '/^[^<>\r\n`#*]{1,80}$/u', $text )
					&& ! in_array( strtolower( trim( $text ) ), array( 'content', 'section', 'text', 'group', 'columns', 'container' ), true );
			}
			if ( 'revision_change_summary' !== $task ) {
				return true;
			}
			// The official parser consumes only summary/changes and supplies all revision fields itself.
			$value = json_decode( $text, true );
			if ( ! is_array( $value ) || ! is_string( $value['summary'] ?? null ) || '' === trim( $value['summary'] )
				|| ! is_array( $value['changes'] ?? null ) || array_values( $value['changes'] ) !== $value['changes']
				|| array_diff( array_keys( $value ), array( 'summary', 'changes', 'truncated', 'revision_id', 'from_revision_id' ) ) ) {
				return false;
			}
			foreach ( $value['changes'] as $change ) {
				if ( ! is_string( $change ) || '' === trim( $change ) ) {
					return false;
				}
			}
			return true;
		}
	}
}
