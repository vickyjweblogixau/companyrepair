<?php
declare( strict_types=1 );

namespace SG_AI_Studio\Ai_Provider;

use SG_AI_Studio\Helper\Helper;
use WordPress\AiClient\Messages\DTO\Message;
use WordPress\AiClient\Messages\DTO\MessagePart;
use WordPress\AiClient\Messages\Enums\MessageRoleEnum;
use WordPress\AiClient\Providers\ApiBasedImplementation\AbstractApiBasedModel;
use WordPress\AiClient\Providers\Models\TextGeneration\Contracts\TextGenerationModelInterface;
use WordPress\AiClient\Results\DTO\Candidate;
use WordPress\AiClient\Results\DTO\GenerativeAiResult;
use WordPress\AiClient\Results\DTO\TokenUsage;
use WordPress\AiClient\Results\Enums\FinishReasonEnum;

/**
 * Text generation model for SG AI Studio.
 *
 * Transforms the WordPress AI Client SDK message format into the AI Studio
 * API request format and parses the NDJSON streaming response back into
 * the SDK's GenerativeAiResult structure.
 *
 * @package SG_AI_Studio
 */
class Ai_Studio_Text_Generation_Model extends AbstractApiBasedModel implements TextGenerationModelInterface {

	/**
	 * Streaming text generation is not supported by the AI Studio backend.
	 *
	 * This method exists to satisfy newer versions of TextGenerationModelInterface
	 * that require it. It falls back to the non-streaming generateTextResult().
	 *
	 * @param list<Message> $prompt The prompt messages.
	 * @return \Generator<GenerativeAiResult> Yields a single result.
	 */
	public function streamGenerateTextResult( array $prompt ): \Generator {
		yield $this->generateTextResult( $prompt );
	}

	/**
	 * Generates a text result from the given prompt.
	 *
	 * Uses wp_remote_post() directly instead of the SDK's HTTP transporter
	 * because the AI Studio backend returns NDJSON streaming responses
	 * rather than standard JSON.
	 *
	 * @param list<Message> $prompt The prompt messages.
	 * @return GenerativeAiResult The generated result.
	 */
	final public function generateTextResult( array $prompt ): GenerativeAiResult {
		$token = Helper::generate_ai_studio_token();

		if ( false === $token || empty( $token ) ) {
			return $this->buildErrorResult( 'Failed to generate authentication token.' );
		}

		$config = $this->getConfig();

		// Build the question parts array for the new API format.
		$question_parts = $this->buildQuestionParts( $prompt, $config );

		// Extract model configuration for WP 7.0 compatibility.
		$model_config = $this->extractModelConfig( $config );

		// Determine chat_source based on calling context.
		$chat_source = $this->determine_chat_source();

		$response = Helper::send_to_text_generation_api( $question_parts, $token, $model_config, false, $chat_source );

		if ( is_wp_error( $response ) ) {
			return $this->buildErrorResult( $response->get_error_message() );
		}

		$response_code = wp_remote_retrieve_response_code( $response );

		// Detect token depletion (402 Payment Required).
		if ( 402 === $response_code ) {
			throw new \WordPress\AiClient\Common\Exception\RuntimeException(
				__( 'Insufficient AI tokens. Please upgrade your plan to continue.', 'sg-ai-studio' )
			);
		}

		if ( 200 !== $response_code ) {
			return $this->buildErrorResult(
				sprintf( 'Unexpected response code: %d', $response_code )
			);
		}

		$raw_body    = wp_remote_retrieve_body( $response );
		$parsed_data = $this->parseResponse( $raw_body );

		// Strip markdown code fences when JSON output is expected.
		if ( 'application/json' === $config->getOutputMimeType() && ! empty( $parsed_data['text'] ) ) {
			$parsed_data['text'] = $this->stripCodeFences( $parsed_data['text'] );
		}

		return $this->buildGenerativeAiResult( $parsed_data );
	}

	/**
	 * Builds question parts array for the new text generation API.
	 *
	 * Converts SDK Message objects into the new structured format that supports
	 * both text and image content types.
	 *
	 * @param list<Message>                                        $messages The SDK message objects.
	 * @param \WordPress\AiClient\Providers\Models\DTO\ModelConfig $config   The model configuration.
	 * @return array Array of question parts with 'type' and content fields.
	 */
	protected function buildQuestionParts( array $messages, $config ): array {
		$question_parts = array();

		// System instruction first — sets the role/behaviour.
		$system_instruction = $config->getSystemInstruction();
		if ( $system_instruction ) {
			$question_parts[] = array(
				'type' => 'text',
				'text' => $system_instruction,
			);
		}

		// JSON output constraint — placed before content so it isn't buried.
		if ( 'application/json' === $config->getOutputMimeType() ) {
			$schema = $config->getOutputSchema();
			if ( $schema ) {
				$question_parts[] = array(
					'type' => 'text',
					'text' => 'You MUST respond ONLY with valid JSON (no markdown, no code fences) matching this schema: ' . wp_json_encode( $schema ),
				);
			} else {
				$question_parts[] = array(
					'type' => 'text',
					'text' => 'You MUST respond ONLY with valid JSON. No markdown, no code fences, just raw JSON.',
				);
			}
		}

		// Process all messages and their parts.
		foreach ( $messages as $message ) {
			$role       = $message->getRole();
			$role_label = $role === MessageRoleEnum::model() ? 'Assistant' : 'User';

			foreach ( $message->getParts() as $part ) {
				$type = $part->getType();

				if ( $type->isText() ) {
					$text = $part->getText();
					if ( ! empty( $text ) ) {
						// Only add role label for multi-message conversations.
						if ( count( $messages ) === 1 ) {
							$question_parts[] = array(
								'type' => 'text',
								'text' => $text,
							);
						} else {
							$question_parts[] = array(
								'type' => 'text',
								'text' => $role_label . ': ' . $text,
							);
						}
					}
				} elseif ( $type->isFile() ) {
					$file = $part->getFile();
					if ( $file && $file->isImage() ) {
						$url = $file->getUrl();
						if ( ! $url && $file->isInline() && $file->getBase64Data() ) {
							$url = $this->saveBase64AsTemp( $file->getBase64Data(), $file->getMimeType() );
						}
						if ( $url ) {
							$question_parts[] = array(
								'type'      => 'image_url',
								'image_url' => array(
									'url' => $url,
								),
							);
						}
					}
				}
			}
		}

		return $question_parts;
	}

	/**
	 * Extracts text content from SDK Message objects into a single question string.
	 *
	 * The AI Studio API expects a single `question` field rather than an array
	 * of structured messages. This method concatenates all text parts from all
	 * messages into one string, preserving conversation context.
	 *
	 * @param list<Message> $messages The SDK message objects.
	 * @return string The flattened question string.
	 */
	protected function flattenMessagesToQuestion( array $messages ): string {
		$parts = array();

		foreach ( $messages as $message ) {
			$role       = $message->getRole();
			$role_label = $role === MessageRoleEnum::model() ? 'Assistant' : 'User';

			foreach ( $message->getParts() as $part ) {
				$type = $part->getType();

				if ( $type->isText() ) {
					$text = $part->getText();
					if ( ! empty( $text ) ) {
						if ( count( $messages ) === 1 ) {
							$parts[] = $text;
						} else {
							$parts[] = $role_label . ': ' . $text;
						}
					}
				} elseif ( $type->isFile() ) {
					$file = $part->getFile();
					if ( $file && $file->isImage() ) {
						$url = $file->getUrl();
						if ( ! $url && $file->isInline() && $file->getBase64Data() ) {
							$url = $this->saveBase64AsTemp( $file->getBase64Data(), $file->getMimeType() );
						}
						if ( $url ) {
							$parts[] = '[Image URL: ' . $url . ']';
						}
					}
				}
			}
		}

		return implode( "\n\n", $parts );
	}

	/**
	 * Parses the JSON response body from the AI Studio API.
	 *
	 * The API returns a simple JSON object with a `content` field containing
	 * the generated text response.
	 *
	 * @param string $raw_body The raw response body.
	 * @return array{text: string, thread_id: string, has_error: bool} Parsed response data.
	 */
	protected function parseResponse( string $raw_body ): array {
		$decoded = json_decode( $raw_body, true );

		if ( null === $decoded ) {
			return array(
				'text'      => '',
				'thread_id' => '',
				'has_error' => true,
			);
		}

		// Check for error response.
		if ( ! empty( $decoded['error'] ) ) {
			return array(
				'text'      => $decoded['error']['message'] ?? 'Unknown error from AI Studio.',
				'thread_id' => '',
				'has_error' => true,
			);
		}

		// Extract the content from the response.
		$text = $decoded['content'] ?? '';

		// Extract thread_id if present (for conversation continuity).
		$thread_id = $decoded['thread_id'] ?? $decoded['chat_id'] ?? '';

		return array(
			'text'      => $text,
			'thread_id' => $thread_id,
			'has_error' => false,
		);
	}

	/**
	 * Builds a GenerativeAiResult from parsed response data.
	 *
	 * @param array{text: string, thread_id: string, has_error: bool} $parsed_data Parsed NDJSON data.
	 * @return GenerativeAiResult The SDK-compatible result.
	 */
	protected function buildGenerativeAiResult( array $parsed_data ): GenerativeAiResult {
		$text         = $parsed_data['text'];
		$has_error    = $parsed_data['has_error'];
		$finish_reason = $has_error ? FinishReasonEnum::error() : FinishReasonEnum::stop();

		$message_part = new MessagePart( $text );
		$message      = new Message( MessageRoleEnum::model(), array( $message_part ) );
		$candidate    = new Candidate( $message, $finish_reason );

		// AI Studio API does not currently return token usage data.
		$token_usage = new TokenUsage( 0, 0, 0 );

		// Include thread_id as additional data for conversation continuity.
		$additional_data = array();
		if ( ! empty( $parsed_data['thread_id'] ) ) {
			$additional_data['thread_id'] = $parsed_data['thread_id'];
		}

		return new GenerativeAiResult(
			'', // No response ID from the API.
			array( $candidate ),
			$token_usage,
			$this->providerMetadata(),
			$this->metadata(),
			$additional_data
		);
	}

	/**
	 * Builds an error result when the request cannot be completed.
	 *
	 * @param string $error_message The error message.
	 * @return GenerativeAiResult A result indicating an error.
	 */
	protected function buildErrorResult( string $error_message ): GenerativeAiResult {
		$message_part = new MessagePart( $error_message );
		$message      = new Message( MessageRoleEnum::model(), array( $message_part ) );
		$candidate    = new Candidate( $message, FinishReasonEnum::error() );

		return new GenerativeAiResult(
			'',
			array( $candidate ),
			new TokenUsage( 0, 0, 0 ),
			$this->providerMetadata(),
			$this->metadata(),
			array()
		);
	}

	/**
	 * Strips markdown code fences from a string to extract raw content.
	 *
	 * @param string $text The text potentially wrapped in code fences.
	 * @return string The unwrapped text.
	 */
	protected function stripCodeFences( string $text ): string {
		$text = trim( $text );
		if ( preg_match( '/^```(?:json)?\s*\n?(.*?)\n?\s*```$/s', $text, $matches ) ) {
			return trim( $matches[1] );
		}
		return $text;
	}

	/**
	 * Saves base64 image data to a temporary file in the uploads directory
	 * and returns a publicly accessible URL.
	 *
	 * @param string $base64_data The base64-encoded image data.
	 * @param string $mime_type   The MIME type (e.g. image/jpeg).
	 * @return string|null The public URL, or null on failure.
	 */
	protected function saveBase64AsTemp( string $base64_data, string $mime_type ): ?string {
		$ext_map = array(
			'image/jpeg' => 'jpg',
			'image/png'  => 'png',
			'image/gif'  => 'gif',
			'image/webp' => 'webp',
		);

		$ext = $ext_map[ $mime_type ] ?? 'jpg';

		$upload_dir = wp_upload_dir();
		$tmp_dir    = $upload_dir['basedir'] . '/sg-ai-studio-tmp';

		if ( ! file_exists( $tmp_dir ) ) {
			wp_mkdir_p( $tmp_dir );
		}

		$filename = 'ai-img-' . wp_generate_password( 12, false ) . '.' . $ext;
		$filepath = $tmp_dir . '/' . $filename;

		$decoded = base64_decode( $base64_data, true );
		if ( false === $decoded ) {
			return null;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		if ( false === file_put_contents( $filepath, $decoded ) ) {
			return null;
		}

		return $upload_dir['baseurl'] . '/sg-ai-studio-tmp/' . $filename;
	}

	/**
	 * Determines the chat source based on the calling context.
	 *
	 * Analyzes the debug backtrace to identify which WordPress AI feature
	 * is making the request and returns the appropriate chat_source identifier.
	 *
	 * @return string The chat_source identifier for the request.
	 */
	protected function determine_chat_source(): string {
		// Map of function/class patterns to chat_source values.
		$patterns = array(
			'excerpt'            => 'wp_admin_excerpt_generation',
			'alt_text'           => 'wp_admin_alt_text_generation',
			'alt-text'           => 'wp_admin_alt_text_generation',
			'meta_description'   => 'wp_admin_meta_description_generation',
			'meta-description'   => 'wp_admin_meta_description_generation',
			'title'              => 'wp_admin_title_generation',
			'summarize'          => 'wp_admin_content_summarization',
			'summarization'      => 'wp_admin_content_summarization',
			'review_notes'       => 'wp_admin_review_notes',
			'review-notes'       => 'wp_admin_review_notes',
			'refine_from_notes'  => 'wp_admin_refine_from_notes',
			'refine-from-notes'  => 'wp_admin_refine_from_notes',
			'resize'             => 'wp_admin_content_resizing',
			'resizing'           => 'wp_admin_content_resizing',
			'comment_moderation' => 'wp_admin_comment_moderation',
			'comment-moderation' => 'wp_admin_comment_moderation',
			'moderate_comment'   => 'wp_admin_comment_moderation',
			'moderate-comment'   => 'wp_admin_comment_moderation',
		);

		foreach ( $backtrace as $trace ) {
			$function_name = isset( $trace['function'] ) ? strtolower( $trace['function'] ) : '';
			$class_name    = isset( $trace['class'] ) ? strtolower( $trace['class'] ) : '';
			$combined      = $function_name . ' ' . $class_name;

			foreach ( $patterns as $pattern => $chat_source ) {
				if ( strpos( $combined, $pattern ) !== false ) {
					return $chat_source;
				}
			}
		}

		// Default to generic WP Admin text generation if no specific feature is identified.
		return 'wp_admin_text_generation';
	}

	/**
	 * Extracts model configuration parameters for the AI Studio backend.
	 *
	 * Maps WordPress 7.0 ModelConfig options to AI Studio API parameters.
	 * Only includes parameters that are set (non-null) to avoid overriding
	 * backend defaults.
	 *
	 * @param \WordPress\AiClient\Providers\Models\DTO\ModelConfig $config The model configuration.
	 * @return array Model configuration parameters for the API.
	 */
	protected function extractModelConfig( $config ): array {
		$model_config = array();

		// Temperature (0.0 to 2.0) - controls randomness.
		if ( null !== $config->getTemperature() ) {
			$model_config['temperature'] = $config->getTemperature();
		}

		// Max tokens - limits response length.
		if ( null !== $config->getMaxTokens() ) {
			$model_config['max_tokens'] = $config->getMaxTokens();
		}

		// Top-P (nucleus sampling) - controls diversity.
		if ( null !== $config->getTopP() ) {
			$model_config['top_p'] = $config->getTopP();
		}

		// Top-K sampling - limits token choices.
		if ( null !== $config->getTopK() ) {
			$model_config['top_k'] = $config->getTopK();
		}

		// Stop sequences - strings that halt generation.
		$stop_sequences = $config->getStopSequences();
		if ( null !== $stop_sequences && ! empty( $stop_sequences ) ) {
			$model_config['stop_sequences'] = $stop_sequences;
		}

		// Presence penalty - reduces repetition of topics.
		if ( null !== $config->getPresencePenalty() ) {
			$model_config['presence_penalty'] = $config->getPresencePenalty();
		}

		// Frequency penalty - reduces repetition of tokens.
		if ( null !== $config->getFrequencyPenalty() ) {
			$model_config['frequency_penalty'] = $config->getFrequencyPenalty();
		}

		return $model_config;
	}

}
