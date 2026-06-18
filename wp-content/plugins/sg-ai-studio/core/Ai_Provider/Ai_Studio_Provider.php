<?php
declare( strict_types=1 );

namespace SG_AI_Studio\Ai_Provider;

use WordPress\AiClient\Common\Exception\RuntimeException;
use WordPress\AiClient\Providers\ApiBasedImplementation\AbstractApiProvider;
use WordPress\AiClient\Providers\Contracts\ModelMetadataDirectoryInterface;
use WordPress\AiClient\Providers\Contracts\ProviderAvailabilityInterface;
use WordPress\AiClient\Providers\DTO\ProviderMetadata;
use WordPress\AiClient\Providers\Enums\ProviderTypeEnum;
use WordPress\AiClient\Providers\Http\Enums\RequestAuthenticationMethod;
use WordPress\AiClient\Providers\Models\Contracts\ModelInterface;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;

/**
 * AI provider for SG AI Studio.
 *
 * Registers SG AI Studio as a provider in the WordPress PHP AI Client SDK,
 * enabling the standardized AI framework to use the AI Studio backend for
 * text generation.
 *
 * @package SG_AI_Studio
 */
class Ai_Studio_Provider extends AbstractApiProvider {

	/**
	 * Returns the base API URL for SG AI Studio.
	 *
	 * Switches between staging and production based on the SG_AI_STUDIO_ENV constant.
	 *
	 * @return string The base API URL.
	 */
	protected static function baseUrl(): string {
		if ( defined( '\SG_AI_STUDIO_ENV' ) && \SG_AI_STUDIO_ENV === 'staging' ) {
			return 'https://api.staging.studio.siteground.ai';
		}

		return 'https://api.studio.siteground.ai';
	}

	/**
	 * Creates a model instance based on the model metadata.
	 *
	 * Supports text generation and image generation.
	 *
	 * @param ModelMetadata    $modelMetadata    The model metadata.
	 * @param ProviderMetadata $providerMetadata The provider metadata.
	 * @return ModelInterface The created model instance.
	 * @throws RuntimeException If the model capabilities are not supported.
	 */
	protected static function createModel(
		ModelMetadata $modelMetadata,
		ProviderMetadata $providerMetadata
	): ModelInterface {
		$capabilities = $modelMetadata->getSupportedCapabilities();

		foreach ( $capabilities as $capability ) {
			if ( $capability->isTextGeneration() ) {
				return new Ai_Studio_Text_Generation_Model( $modelMetadata, $providerMetadata );
			}
			if ( $capability->isImageGeneration() ) {
				return new Ai_Studio_Image_Generation_Model( $modelMetadata, $providerMetadata );
			}
		}

		throw new RuntimeException(
			'Unsupported model capabilities: ' . implode( ', ', $capabilities )
		);
	}

	/**
	 * Creates the provider metadata for SG AI Studio.
	 *
	 * @return ProviderMetadata The provider metadata.
	 */
	protected static function createProviderMetadata(): ProviderMetadata {
		// Build path using WP_PLUGIN_DIR to match what WordPress expects
		$logo_path = WP_PLUGIN_DIR . '/sg-ai-studio/assets/images/logo-siteground-compact-dark.svg';

		// ProviderMetadata constructor signature (added in AiClient 1.3.0):
		// __construct($id, $name, $type, $credentialsUrl, $authenticationMethod, $description, $logoPath)
		return new ProviderMetadata(
			'ai_studio_siteground',
			'SiteGround AI',
			ProviderTypeEnum::cloud(),
			admin_url( 'admin.php?page=sg-ai-studio' ),
			RequestAuthenticationMethod::apiKey(),
			__( 'Multi-model AI service for text, image, translation, research & more.', 'sg-ai-studio' ),
			$logo_path
		);
	}

	/**
	 * Creates the provider availability checker.
	 *
	 * Checks whether the plugin is connected and has valid credentials
	 * by verifying the sg_ai_studio_connected option.
	 *
	 * @return ProviderAvailabilityInterface The availability checker.
	 */
	protected static function createProviderAvailability(): ProviderAvailabilityInterface {
		return new Ai_Studio_Provider_Availability();
	}

	/**
	 * Creates the model metadata directory.
	 *
	 * Returns a directory with hardcoded model definitions since the
	 * AI Studio backend does not expose a model listing endpoint.
	 *
	 * @return ModelMetadataDirectoryInterface The model metadata directory.
	 */
	protected static function createModelMetadataDirectory(): ModelMetadataDirectoryInterface {
		return new Ai_Studio_Model_Metadata_Directory();
	}
}
