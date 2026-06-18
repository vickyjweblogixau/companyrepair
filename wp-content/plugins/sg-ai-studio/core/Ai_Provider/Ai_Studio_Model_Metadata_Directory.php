<?php
declare( strict_types=1 );

namespace SG_AI_Studio\Ai_Provider;

use WordPress\AiClient\Common\Exception\InvalidArgumentException;
use WordPress\AiClient\Messages\Enums\ModalityEnum;
use WordPress\AiClient\Providers\Contracts\ModelMetadataDirectoryInterface;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;
use WordPress\AiClient\Providers\Models\DTO\SupportedOption;
use WordPress\AiClient\Providers\Models\Enums\CapabilityEnum;
use WordPress\AiClient\Providers\Models\Enums\OptionEnum;
use WordPress\AiClient\Files\Enums\FileTypeEnum;
use WordPress\AiClient\Files\Enums\MediaOrientationEnum;

/**
 * Model metadata directory for SG AI Studio.
 *
 * Provides a hardcoded registry of available models since the AI Studio backend
 * does not expose a model listing endpoint.
 *
 * @package SG_AI_Studio
 */
class Ai_Studio_Model_Metadata_Directory implements ModelMetadataDirectoryInterface {

	/**
	 * Cached list of model metadata.
	 *
	 * @var ModelMetadata[]|null
	 */
	private $models = null;

	/**
	 * Returns metadata for all available models.
	 *
	 * @return ModelMetadata[] List of model metadata objects.
	 */
	public function listModelMetadata(): array {
		if ( null === $this->models ) {
			$this->models = $this->buildModelMetadataList();
		}

		return $this->models;
	}

	/**
	 * Checks whether a model with the given ID exists.
	 *
	 * @param string $modelId The model identifier.
	 * @return bool True if the model exists, false otherwise.
	 */
	public function hasModelMetadata( string $modelId ): bool {
		foreach ( $this->listModelMetadata() as $model ) {
			if ( $model->getId() === $modelId ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Returns metadata for a specific model.
	 *
	 * @param string $modelId The model identifier.
	 * @return ModelMetadata The model metadata.
	 * @throws InvalidArgumentException If the model is not found.
	 */
	public function getModelMetadata( string $modelId ): ModelMetadata {
		foreach ( $this->listModelMetadata() as $model ) {
			if ( $model->getId() === $modelId ) {
				return $model;
			}
		}

		throw new InvalidArgumentException(
			sprintf( 'Model "%s" not found in SG AI Studio provider.', $modelId )
		);
	}

	/**
	 * Builds the list of available model metadata.
	 *
	 * @return ModelMetadata[] List of model metadata objects.
	 */
	private function buildModelMetadataList(): array {
		$text_capabilities = array(
			CapabilityEnum::textGeneration(),
			CapabilityEnum::chatHistory(),
		);

		$text_options = array(
			new SupportedOption( OptionEnum::systemInstruction() ),
			new SupportedOption( OptionEnum::maxTokens() ),
			new SupportedOption( OptionEnum::temperature() ),
			new SupportedOption( OptionEnum::candidateCount() ),
			new SupportedOption( OptionEnum::outputMimeType() ),
			new SupportedOption( OptionEnum::outputSchema() ),
			new SupportedOption( OptionEnum::customOptions() ),
			new SupportedOption(
				OptionEnum::inputModalities(),
				array(
					array( ModalityEnum::text() ),
					array( ModalityEnum::text(), ModalityEnum::image() ),
				)
			),
			new SupportedOption(
				OptionEnum::outputModalities(),
				array( array( ModalityEnum::text() ) )
			),
		);

		$image_capabilities = array(
			CapabilityEnum::imageGeneration(),
		);

		$image_options = array(
			new SupportedOption(
				OptionEnum::inputModalities(),
				array(
					array( ModalityEnum::text() ),
					array( ModalityEnum::text(), ModalityEnum::image() ),
				)
			),
			new SupportedOption(
				OptionEnum::outputModalities(),
				array( array( ModalityEnum::image() ) )
			),
			new SupportedOption( OptionEnum::candidateCount() ),
			new SupportedOption(
				OptionEnum::outputMimeType(),
				array( 'image/png' )
			),
			new SupportedOption(
				OptionEnum::outputFileType(),
				array( FileTypeEnum::inline(), FileTypeEnum::remote() )
			),
			new SupportedOption(
				OptionEnum::outputMediaOrientation(),
				array(
					MediaOrientationEnum::square(),
					MediaOrientationEnum::landscape(),
					MediaOrientationEnum::portrait(),
				)
			),
			new SupportedOption(
				OptionEnum::outputMediaAspectRatio(),
				array( '1:1', '16:9', '9:16' )
			),
			new SupportedOption( OptionEnum::customOptions() ),
		);

		return array(
			new ModelMetadata(
				'sg-ai-studio',
				'SG AI Studio',
				$text_capabilities,
				$text_options
			),
			new ModelMetadata(
				'sg-ai-studio-image',
				'SG AI Studio Image',
				$image_capabilities,
				$image_options
			),
		);
	}
}
