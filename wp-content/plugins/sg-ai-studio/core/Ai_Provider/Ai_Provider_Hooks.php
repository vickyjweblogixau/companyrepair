<?php
declare( strict_types=1 );

namespace SG_AI_Studio\Ai_Provider;

/**
 * Handles AI Provider registration and Connectors page integration.
 *
 * Registers the SG AI Studio provider in the WordPress PHP AI Client SDK.
 * The provider uses api_key auth method so WP core's connectors.php picks it
 * up automatically via the registry and renders it on the Connectors page.
 * A synthetic API key value is returned when connected so the page shows
 * "Connected" status without the user needing to enter a key.
 *
 * @package SG_AI_Studio
 */
class Ai_Provider_Hooks {

	/**
	 * The WP option name for tracking AI provider registration status.
	 *
	 * Separate from sg_ai_studio_connected to allow disconnecting from the
	 * Connectors page without affecting the underlying AI Studio connection.
	 *
	 * @var string
	 */
	const PROVIDER_CONNECTED_OPTION = 'sg_ai_studio_provider_connected';

	/**
	 * Register the provider in the SDK registry.
	 *
	 * @return void
	 */
	public function register_provider() {
		$registry = \WordPress\AiClient\AiClient::defaultRegistry();

		if ( $registry->hasProvider( Ai_Studio_Provider::class ) ) {
			return;
		}

		$registry->registerProvider( Ai_Studio_Provider::class );
	}

	/**
	 * Move our provider to the top of the registry.
	 *
	 * Must run after all core providers (Anthropic, Google, OpenAI) have
	 * been registered so the reorder captures the full list.
	 *
	 * The SDK registry has no built-in prepend mechanism, so we use
	 * Reflection to move our entry to the front of both lookup arrays.
	 *
	 * @return void
	 */
	public function prepend_provider_in_registry() {
		$registry = \WordPress\AiClient\AiClient::defaultRegistry();

		if ( ! $registry->hasProvider( Ai_Studio_Provider::class ) ) {
			return;
		}

		$provider_id = Ai_Studio_Provider::metadata()->getId();

		$reorder = function ( string $property, string $key ) use ( $registry ) {
			$ref = new \ReflectionProperty( $registry, $property );
			// setAccessible() needed for PHP 7.4-8.0, deprecated in PHP 8.1+.
			if ( PHP_VERSION_ID < 80100 ) {
				$ref->setAccessible( true );
			}
			$array = $ref->getValue( $registry );

			if ( ! isset( $array[ $key ] ) ) {
				return;
			}

			$value = $array[ $key ];
			unset( $array[ $key ] );
			$array = array( $key => $value ) + $array;

			$ref->setValue( $registry, $array );
		};

		$reorder( 'registeredIdsToClassNames', $provider_id );
		$reorder( 'registeredClassNamesToIds', Ai_Studio_Provider::class );
	}

	/**
	 * Return synthetic API key when provider is connected.
	 *
	 * Makes the Connectors page show "Connected" status by returning
	 * a non-empty value when the provider is registered.
	 *
	 * @param mixed $pre_option The value to return instead of the database value.
	 * @return string|false Synthetic key if connected, false otherwise.
	 */
	public function filter_connector_option( $pre_option ) {
		$plugin_connected   = (bool) get_option( 'sg_ai_studio_connected', false );
		$provider_connected = (bool) get_option( self::PROVIDER_CONNECTED_OPTION, true );

		if ( $plugin_connected && $provider_connected ) {
			return 'ai_studio_siteground_connected';
		}

		// Let WordPress read actual value (likely empty/non-existent)
		return false;
	}

	/**
	 * Display admin notice when provider state changes.
	 *
	 * @return void
	 */
	public function show_provider_state_notice() {
		if ( get_transient( 'sg_ai_studio_provider_disconnected' ) ) {
			delete_transient( 'sg_ai_studio_provider_disconnected' );
			?>
			<div class="notice notice-warning is-dismissible">
				<p>
					<strong><?php esc_html_e( 'AI Studio Provider Disconnected', 'sg-ai-studio' ); ?></strong><br>
					<?php esc_html_e( 'AI Studio has been removed from WordPress AI features. Your AI Studio plugin connection and credentials remain intact.', 'sg-ai-studio' ); ?>
				</p>
			</div>
			<?php
		}

		if ( get_transient( 'sg_ai_studio_provider_reconnected' ) ) {
			delete_transient( 'sg_ai_studio_provider_reconnected' );
			?>
			<div class="notice notice-success is-dismissible">
				<p>
					<strong><?php esc_html_e( 'AI Studio Provider Reconnected', 'sg-ai-studio' ); ?></strong><br>
					<?php esc_html_e( 'AI Studio is now available for WordPress AI features.', 'sg-ai-studio' ); ?>
				</p>
			</div>
			<?php
		}
	}

	/**
	 * Handle when connector option is deleted.
	 *
	 * WordPress deletes the connector option when user clicks "Remove and replace".
	 * We treat this as a provider disconnect.
	 *
	 * @param string $option The option name that was deleted.
	 * @return void
	 */
	public function handle_connector_option_deleted( $option ) {
		if ( $option !== 'connectors_ai_ai_studio_siteground_api_key' ) {
			return;
		}

		// Check if plugin is connected
		if ( ! (bool) get_option( 'sg_ai_studio_connected', false ) ) {
			return;
		}

		// Disconnect the provider
		update_option( self::PROVIDER_CONNECTED_OPTION, false );
		set_transient( 'sg_ai_studio_provider_disconnected', true, 60 );
	}

	/**
	 * Handle when connector option is updated.
	 *
	 * If a value is set (non-empty), treat it as a provider reconnect.
	 *
	 * @param string $option    The option name.
	 * @param mixed  $old_value The old value.
	 * @param mixed  $value     The new value.
	 * @return void
	 */
	public function handle_connector_option_updated( $option, $old_value, $value ) {
		if ( $option !== 'connectors_ai_ai_studio_siteground_api_key' ) {
			return;
		}

		// Check if plugin is connected
		if ( ! (bool) get_option( 'sg_ai_studio_connected', false ) ) {
			return;
		}

		// If value is empty, treat as disconnect
		if ( empty( $value ) || '' === $value ) {
			$current_state = (bool) get_option( self::PROVIDER_CONNECTED_OPTION, true );
			if ( $current_state ) {
				update_option( self::PROVIDER_CONNECTED_OPTION, false );
				set_transient( 'sg_ai_studio_provider_disconnected', true, 60 );
			}
		} else {
			// If value is non-empty, treat as reconnect
			$current_state = (bool) get_option( self::PROVIDER_CONNECTED_OPTION, true );
			if ( ! $current_state ) {
				update_option( self::PROVIDER_CONNECTED_OPTION, true );
				set_transient( 'sg_ai_studio_provider_reconnected', true, 60 );
			}
		}
	}

	/**
	 * Allow saving empty API key for AI Studio via REST API.
	 *
	 * WordPress normally prevents saving empty API keys, but we want to allow
	 * it for AI Studio so users can disconnect the provider.
	 *
	 * @param array            $prepared_value The prepared value for the database.
	 * @param WP_REST_Request  $request        The request object.
	 * @param string           $param          The parameter name.
	 * @return array The potentially modified value.
	 */
	public function allow_empty_connector_api_key( $prepared_value, $request, $param ) {
		// Only allow empty for our connector
		if ( $param !== 'connectors_ai_ai_studio_siteground_api_key' ) {
			return $prepared_value;
		}

		// If the request is trying to set it to empty, allow it
		$body = $request->get_json_params();
		if ( isset( $body['connectors_ai_ai_studio_siteground_api_key'] ) ) {
			return $body['connectors_ai_ai_studio_siteground_api_key'];
		}

		return $prepared_value;
	}

	/**
	 * Enqueue CSS and JS to hide API key field on Connectors page.
	 *
	 * @return void
	 */
	public function enqueue_connectors_css() {
		$current_screen = get_current_screen();
		if ( ! $current_screen ) {
			return;
		}

		$is_connectors_page = (
			$current_screen->id === 'settings_page_options-connectors' ||
			$current_screen->id === 'options-connectors' ||
			( isset( $_GET['page'] ) && $_GET['page'] === 'options-connectors' )
		);

		if ( ! $is_connectors_page ) {
			return;
		}

		// Enqueue JS to add class to AI Studio connector
		wp_enqueue_script(
			'sg-ai-studio-connectors-hide',
			\SG_AI_Studio\URL . '/assets/js/connectors-hide.js',
			array(),
			\SG_AI_Studio\VERSION,
		);

		wp_localize_script(
			'sg-ai-studio-connectors-hide',
			'sg_ai_studio_connected',
			array(
				'connected' => get_option( 'sg_ai_studio_provider_connected', false ),
				'admin_url' => admin_url( 'admin.php?page=sg-ai-studio' ),
			)
		);

		// Enqueue CSS to hide the settings
		wp_enqueue_style(
			'sg-ai-studio-connectors',
			\SG_AI_Studio\URL . '/assets/css/connectors.css',
			array(),
			\SG_AI_Studio\VERSION
		);
	}
}
