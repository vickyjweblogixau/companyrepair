<?php
namespace SiteGround_Dashboard;

// Prevent direct access and multiple class loading.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( class_exists( 'SiteGround_Dashboard\Partner_Plugins_Helper' ) ) {
	return;
}

/**
 * Helper class for Partner Plugins functionality.
 *
 * Handles partner plugin status checks and activation.
 *
 * @since 1.0.0
 */
class Partner_Plugins_Helper {

	/**
	 * Get all partner plugins data with their current status.
	 *
	 * @since 1.0.0
	 *
	 * @return array The partner plugins data with activation status.
	 */
	public static function get_partner_plugins_data() {
		$plugins_data = self::load_partner_plugins_config();

		if ( empty( $plugins_data['partner_plugins'] ) ) {
			return array();
		}

		$result = array();

		foreach ( $plugins_data['partner_plugins'] as $plugin ) {
			$plugin_status = self::get_plugin_status( $plugin['plugin_name'] );

			// Get the localized title and description.
			$title       = self::get_localized_string( $plugin['title'] );
			$description = self::get_localized_string( $plugin['description'] );

			$result[] = array(
				'plugin_name'   => $plugin['plugin_name'],
				'plugin_slug'   => $plugin['plugin_slug'],
				'title'         => $title,
				'description'   => $description,
				'icon'          => $plugin['icon'],
				'is_installed'  => $plugin_status['is_installed'],
				'is_active'     => $plugin_status['is_active'],
				'button_text'   => $plugin_status['is_active'] ? __( 'Manage', 'siteground-dashboard' ) : __( 'Activate', 'siteground-dashboard' ),
				'button_link'   => $plugin_status['is_active'] ? admin_url( $plugin['settings_link'] ) : '',
				'button_action' => $plugin_status['is_active'] ? '' : 'activate_plugin',
				'settings_url'  => admin_url( $plugin['settings_link'] ),
				'install_link'  => $plugin['install_link'],
			);
		}

		return $result;
	}

	/**
	 * Get localized string based on current WordPress locale.
	 *
	 * @since 1.0.0
	 *
	 * @param array|string $translations Array of translations with language codes as keys, or a string.
	 *
	 * @return string The localized string.
	 */
	private static function get_localized_string( $translations ) {
		// If it's already a string (backwards compatibility), return it.
		if ( is_string( $translations ) ) {
			return $translations;
		}

		// If it's not an array, return empty string.
		if ( ! is_array( $translations ) ) {
			return '';
		}

		// Get the current WordPress locale (e.g., 'en_US', 'es_ES', 'de_DE').
		$locale = get_locale();

		// Extract the language code (first 2 characters, e.g., 'en', 'es', 'de').
		$lang_code = substr( $locale, 0, 2 );

		// Return the translation for the current language, or fallback to English.
		if ( isset( $translations[ $lang_code ] ) ) {
			return $translations[ $lang_code ];
		}

		// Fallback to English if available.
		if ( isset( $translations['en'] ) ) {
			return $translations['en'];
		}

		// If no translation found, return the first available translation.
		return ! empty( $translations ) ? reset( $translations ) : '';
	}

	/**
	 * Load partner plugins configuration from JSON file.
	 *
	 * @since 1.0.0
	 *
	 * @return array The partner plugins configuration.
	 */
	private static function load_partner_plugins_config() {
		$config_file = dirname( __FILE__ ) . '/../config/partner-plugins.json';

		if ( ! file_exists( $config_file ) ) {
			return array();
		}

		$content = file_get_contents( $config_file );
		$data    = json_decode( $content, true );

		return is_array( $data ) ? $data : array();
	}

	/**
	 * Get the status of a specific plugin.
	 *
	 * @since 1.0.0
	 *
	 * @param string $plugin_name The plugin slug (e.g., 'wpforms-lite/wpforms.php').
	 *
	 * @return array Array with 'is_installed' and 'is_active' status.
	 */
	public static function get_plugin_status( $plugin_name ) {
		// Ensure plugin functions are available.
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$all_plugins  = get_plugins();
		$is_installed = isset( $all_plugins[ $plugin_name ] );
		$is_active    = is_plugin_active( $plugin_name );

		return array(
			'is_installed' => $is_installed,
			'is_active'    => $is_installed && $is_active,
		);
	}

	/**
	 * Install and activate a partner plugin.
	 *
	 * @since 1.0.0
	 *
	 * @param string $plugin_slug The plugin slug to install/activate.
	 *
	 * @return array Result array with success status and message.
	 */
	public static function install_and_activate_plugin( $plugin_slug ) {
		// Execute the installation command.
		exec(
			sprintf(
				'wp plugin install %s %s',
				escapeshellarg( $plugin_slug ),
				'--activate'
			),
			$output,
			$status
		);

		wp_clean_plugins_cache();

		// Check for errors.
		if ( ! empty( $status ) ) {
			wp_send_json_error();
		}

		wp_send_json_success();
	}
}
