<?php
declare( strict_types=1 );

namespace SG_AI_Studio\Ai_Provider;

use WordPress\AiClient\Providers\Contracts\ProviderAvailabilityInterface;

/**
 * Availability checker for SG AI Studio provider.
 *
 * Determines whether the plugin is properly connected and has valid
 * credentials to communicate with the AI Studio backend.
 *
 * @package SG_AI_Studio
 */
class Ai_Studio_Provider_Availability implements ProviderAvailabilityInterface {

	/**
	 * Checks whether the SG AI Studio provider is configured and available.
	 *
	 * Returns true when the plugin is connected (has completed the
	 * authentication handshake and has stored client credentials).
	 *
	 * @return bool True if the provider is configured, false otherwise.
	 */
	public function isConfigured(): bool {
		// Check if the plugin has completed the authentication flow.
		$connected = get_option( 'sg_ai_studio_provider_connected', false );

		if ( ! $connected ) {
			return false;
		}

		// Verify that client credentials exist.
		$client_id  = get_option( 'sg_ai_studio_client_id', '' );
		$client_key = get_option( 'sg_ai_studio_client_key', '' );

		return ! empty( $client_id ) && ! empty( $client_key );
	}
}
