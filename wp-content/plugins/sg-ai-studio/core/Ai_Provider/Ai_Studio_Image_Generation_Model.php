<?php
declare( strict_types=1 );

namespace SG_AI_Studio\Ai_Provider;

use SG_AI_Studio\Helper\Helper;
use WordPress\AiClient\Files\DTO\File;
use WordPress\AiClient\Messages\DTO\Message;
use WordPress\AiClient\Messages\DTO\MessagePart;
use WordPress\AiClient\Messages\Enums\MessageRoleEnum;
use WordPress\AiClient\Providers\ApiBasedImplementation\AbstractApiBasedModel;
use WordPress\AiClient\Providers\Models\ImageGeneration\Contracts\ImageGenerationModelInterface;
use WordPress\AiClient\Results\DTO\Candidate;
use WordPress\AiClient\Results\DTO\GenerativeAiResult;
use WordPress\AiClient\Results\DTO\TokenUsage;
use WordPress\AiClient\Results\Enums\FinishReasonEnum;

/**
 * Image generation model for SG AI Studio.
 *
 * Sends an image generation prompt to the AI Studio chat API, which uses
 * its internal image_generation tool. The NDJSON response is parsed for
 * tool_result events containing generated image URLs.
 *
 * @package SG_AI_Studio
 */
class Ai_Studio_Image_Generation_Model extends AbstractApiBasedModel implements ImageGenerationModelInterface {

	/**
	 * Generates an image result from the given prompt.
	 *
	 * @param list<Message> $prompt The prompt messages (expects a single user message with text).
	 * @return GenerativeAiResult The generated result containing image file(s).
	 */
	final public function generateImageResult( array $prompt ): GenerativeAiResult {
		$token = Helper::generate_ai_studio_token();

		if ( false === $token || empty( $token ) ) {
			return $this->buildErrorResult( 'Failed to generate authentication token.' );
		}

		$description = $this->extractPromptText( $prompt );
		$config      = $this->getConfig();
		$has_images  = $this->hasImagesInPrompt( $prompt );

		// Build model config with aspect ratio if provided.
		$model_config = array();
		$aspect_ratio = $config->getOutputMediaAspectRatio();
		if ( $aspect_ratio ) {
			$model_config['aspect_ratio'] = $aspect_ratio;
		}

		// For image editing/refinement (when prompt contains images), use the edit endpoint.
		// For regular generation, use the dedicated image generation endpoint.
		if ( $has_images ) {
			$chat_source    = $this->determine_chat_source( true );
			$question_parts = $this->buildImageQuestionParts( $prompt, $description );
			$response       = Helper::send_to_text_generation_api( $question_parts, $token, $model_config, false, $chat_source, true );
		} else {
			$chat_source = $this->determine_chat_source( false );
			$response    = Helper::send_to_text_generation_api( $description, $token, $model_config, true, $chat_source );
		}

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

		$raw_body   = wp_remote_retrieve_body( $response );
		$image_urls = $this->parseNdjsonForImages( $raw_body );

		if ( empty( $image_urls ) ) {
			return $this->buildErrorResult( 'AI service did not generate any images.' );
		}

		return $this->buildImageResult( $image_urls );
	}

	/**
	 * Streaming image generation is not supported.
	 *
	 * Falls back to the non-streaming generateImageResult().
	 *
	 * @param list<Message> $prompt The prompt messages.
	 * @return \Generator<GenerativeAiResult> Yields a single result.
	 */
	public function streamGenerateImageResult( array $prompt ): \Generator {
		yield $this->generateImageResult( $prompt );
	}

	/**
	 * Checks if the prompt contains any images.
	 *
	 * @param list<Message> $messages The SDK message objects.
	 * @return bool True if images are present in the prompt.
	 */
	protected function hasImagesInPrompt( array $messages ): bool {
		foreach ( $messages as $message ) {
			foreach ( $message->getParts() as $part ) {
				$type = $part->getType();
				if ( $type->isFile() ) {
					$file = $part->getFile();
					if ( $file && $file->isImage() ) {
						return true;
					}
				}
			}
		}

		return false;
	}

	/**
	 * Builds question parts array for image editing/refinement requests.
	 *
	 * Handles both text prompts and reference images for image editing.
	 *
	 * @param list<Message> $messages The SDK message objects.
	 * @param string        $description The text description/instruction.
	 * @return array Array of question parts with 'type' and content fields.
	 */
	protected function buildImageQuestionParts( array $messages, string $description ): array {
		$question_parts = array();

		// Add the text instruction first.
		$question_parts[] = array(
			'type' => 'text',
			'text' => $description,
		);

		// Add any images in the prompt (for image editing/refinement).
		foreach ( $messages as $message ) {
			foreach ( $message->getParts() as $part ) {
				$type = $part->getType();

				if ( $type->isFile() ) {
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
	 * Extracts the text content from the SDK prompt messages.
	 *
	 * @param list<Message> $messages The SDK message objects.
	 * @return string The prompt text.
	 */
	protected function extractPromptText( array $messages ): string {
		foreach ( $messages as $message ) {
			foreach ( $message->getParts() as $part ) {
				$text = $part->getText();
				if ( null !== $text && '' !== $text ) {
					return $text;
				}
			}
		}

		return '';
	}

	/**
	 * Builds an enhanced image generation prompt with configuration options.
	 *
	 * Incorporates media orientation and aspect ratio from ModelConfig into
	 * the prompt so the AI Studio backend generates images matching the
	 * WordPress 7.0 developer's expectations.
	 *
	 * @param string $description The base image description.
	 * @return string The enhanced prompt for the AI Studio API.
	 */
	protected function buildEnhancedImagePrompt( string $description ): string {
		$config = $this->getConfig();
		$parts  = array( $description );

		// Add orientation constraint if specified.
		$orientation = $config->getOutputMediaOrientation();
		if ( $orientation ) {
			if ( $orientation->isLandscape() ) {
				$parts[] = 'in landscape orientation';
			} elseif ( $orientation->isPortrait() ) {
				$parts[] = 'in portrait orientation';
			} elseif ( $orientation->isSquare() ) {
				$parts[] = 'in square format';
			}
		}

		// Add aspect ratio constraint if specified.
		$aspect_ratio = $config->getOutputMediaAspectRatio();
		if ( $aspect_ratio ) {
			$parts[] = "with {$aspect_ratio} aspect ratio";
		}

		$enhanced_description = implode( ' ', $parts );

		return sprintf(
			"I need you to generate an image using the image_generation tool.\n\nDescription: %s\n\nIMPORTANT: You MUST use the image_generation tool to create this image. Do not provide placeholder images or describe the image - actually generate it using the tool.",
			$enhanced_description
		);
	}

	/**
	 * Parses the JSON response for image generation.
	 *
	 * The API returns images in this format:
	 * {"data": {"images": ["base64_encoded_image_data"]}}
	 *
	 * @param string $raw_body The raw response body.
	 * @return string[] Array of image data (base64 strings).
	 */
	protected function parseNdjsonForImages( string $raw_body ): array {
		// Try parsing as plain JSON first.
		$decoded = json_decode( $raw_body, true );

		if ( null !== $decoded && is_array( $decoded ) ) {
			// Check for images inside data object (new API format).
			if ( ! empty( $decoded['data']['images'] ) && is_array( $decoded['data']['images'] ) ) {
				// Return base64 image data as-is.
				return $decoded['data']['images'];
			}

			// Check for images at root level (alternative format).
			if ( ! empty( $decoded['images'] ) && is_array( $decoded['images'] ) ) {
				return is_string( $decoded['images'][0] ) ? $decoded['images'] : $this->extractImageUrls( $decoded['images'] );
			}

			// Check for output_resources (legacy format).
			if ( ! empty( $decoded['output_resources'] ) && is_array( $decoded['output_resources'] ) ) {
				return $this->extractImageUrls( $decoded['output_resources'] );
			}

			// Check if this is a tool_result in plain JSON format.
			if ( ! empty( $decoded['content']['response']['output_resources'] ) ) {
				return $this->extractImageUrls( $decoded['content']['response']['output_resources'] );
			}
		}

		// Fall back to NDJSON parsing for backwards compatibility.
		return $this->parseNdjsonForImagesLegacy( $raw_body );
	}

	/**
	 * Extracts image URLs from an array of resource objects.
	 *
	 * @param array $resources Array of resource objects with 'url' fields.
	 * @return string[] Array of image URLs.
	 */
	protected function extractImageUrls( array $resources ): array {
		$urls = array();

		foreach ( $resources as $resource ) {
			if ( ! empty( $resource['url'] ) ) {
				$urls[] = $resource['url'];
			}
		}

		return $urls;
	}

	/**
	 * Parses NDJSON response for image generation (legacy format).
	 *
	 * @param string $raw_body The raw NDJSON response body.
	 * @return string[] Array of image URLs.
	 */
	protected function parseNdjsonForImagesLegacy( string $raw_body ): array {
		$image_urls = array();
		$lines      = explode( "\n", $raw_body );

		foreach ( $lines as $line ) {
			$line = trim( $line );
			if ( empty( $line ) ) {
				continue;
			}

			$decoded = json_decode( $line, true );
			if ( null === $decoded ) {
				continue;
			}

			if ( empty( $decoded['type'] ) || 'tool_result' !== $decoded['type'] ) {
				continue;
			}

			if ( empty( $decoded['content']['name'] ) || 'image_generation' !== $decoded['content']['name'] ) {
				continue;
			}

			$response_data    = $decoded['content']['response'] ?? null;
			$image_data       = $response_data['output'] ?? null;
			$output_resources = $response_data['output_resources'] ?? array();

			if ( ! $image_data || 'success' !== ( $image_data['status'] ?? '' ) ) {
				continue;
			}

			foreach ( $output_resources as $resource ) {
				if ( ! empty( $resource['url'] ) ) {
					$image_urls[] = $resource['url'];
				}
			}
		}

		return $image_urls;
	}

	/**
	 * Builds a GenerativeAiResult from image data (URLs or base64).
	 *
	 * Handles both base64 encoded image data and URLs.
	 *
	 * @param string[] $image_data Array of image URLs or base64 encoded data.
	 * @return GenerativeAiResult The SDK-compatible result.
	 */
	protected function buildImageResult( array $image_data ): GenerativeAiResult {
		$candidates = array();

		foreach ( $image_data as $data ) {
			// Check if this is base64 encoded data or a URL.
			if ( $this->isBase64ImageData( $data ) ) {
				// Convert base64 to data URI.
				$data_uri = 'data:image/png;base64,' . $data;
				$file     = new File( $data_uri, 'image/png' );
			} elseif ( 0 === strpos( $data, 'http' ) ) {
				// This is a URL - download and convert to inline.
				$file = $this->downloadImageAsInlineFile( $data );
				if ( null === $file ) {
					// Fallback to remote URL if download fails.
					$file = new File( $data, 'image/png' );
				}
			} else {
				// Unknown format - skip.
				continue;
			}

			$part         = new MessagePart( $file );
			$message      = new Message( MessageRoleEnum::model(), array( $part ) );
			$candidates[] = new Candidate( $message, FinishReasonEnum::stop() );
		}

		return new GenerativeAiResult(
			'',
			$candidates,
			new TokenUsage( 0, 0, 0 ),
			$this->providerMetadata(),
			$this->metadata(),
			array()
		);
	}

	/**
	 * Determines the chat source for image generation requests.
	 *
	 * Returns the chat_source identifier for image generation or editing.
	 *
	 * @param bool $is_editing Whether this is an image editing/refinement request.
	 * @return string The chat_source identifier for the request.
	 */
	protected function determine_chat_source( bool $is_editing = false ): string {
		// Use different source for editing vs generation.
		return $is_editing ? 'wp_admin_image_editing' : 'wp_admin_image_generation';
	}

	/**
	 * Checks if a string is base64 encoded image data.
	 *
	 * @param string $data The data to check.
	 * @return bool True if it appears to be base64 encoded data.
	 */
	protected function isBase64ImageData( string $data ): bool {
		// Base64 data shouldn't start with http and should be fairly long.
		return 0 !== strpos( $data, 'http' ) && strlen( $data ) > 100 && preg_match( '/^[A-Za-z0-9+\/=]+$/', substr( $data, 0, 100 ) );
	}

	/**
	 * Downloads an image from a URL and returns it as an inline File.
	 *
	 * @param string $url The image URL to download.
	 * @return File|null The inline File, or null if download failed.
	 */
	protected function downloadImageAsInlineFile( string $url ): ?File {
		$response = wp_remote_get( $url, array( 'timeout' => 30 ) );

		if ( is_wp_error( $response ) ) {
			return null;
		}

		$body = wp_remote_retrieve_body( $response );
		if ( empty( $body ) ) {
			return null;
		}

		$content_type = wp_remote_retrieve_header( $response, 'content-type' );
		$mime_type    = ! empty( $content_type ) ? $content_type : 'image/png';

		// Strip any charset or parameters from the MIME type.
		if ( strpos( $mime_type, ';' ) !== false ) {
			$mime_type = trim( explode( ';', $mime_type )[0] );
		}

		$base64   = base64_encode( $body );
		$data_uri = 'data:' . $mime_type . ';base64,' . $base64;

		return new File( $data_uri, $mime_type );
	}

	/**
	 * Builds an error result when the request cannot be completed.
	 *
	 * @param string $error_message The error message.
	 * @return GenerativeAiResult A result indicating an error.
	 */
	protected function buildErrorResult( string $error_message ): GenerativeAiResult {
		$part      = new MessagePart( $error_message );
		$message   = new Message( MessageRoleEnum::model(), array( $part ) );
		$candidate = new Candidate( $message, FinishReasonEnum::error() );

		return new GenerativeAiResult(
			'',
			array( $candidate ),
			new TokenUsage( 0, 0, 0 ),
			$this->providerMetadata(),
			$this->metadata(),
			array()
		);
	}

}
