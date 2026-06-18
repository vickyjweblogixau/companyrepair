<?php
namespace SiteGround_Dashboard;

// Prevent direct access and multiple class loading.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( class_exists( 'SiteGround_Dashboard\Dashboard' ) ) {
	return;
}

/**
 * Main Dashboard class.
 *
 * Initializes and coordinates all dashboard components.
 *
 * @since 1.0.0
 */
class Dashboard {

	/**
	 * Whether the dashboard has been initialized.
	 *
	 * @since 1.0.0
	 *
	 * @var bool
	 */
	private static $initialized = false;

	/**
	 * Initialize the dashboard.
	 *
	 * This should be called from the plugin that's using this vendor.
	 *
	 * @since 1.0.0
	 *
	 * @param array $config Optional configuration array.
	 */
	public static function init( $config = array() ) {
		// Prevent multiple initializations.
		if ( self::$initialized ) {
			return;
		}

		self::$initialized = true;

		// Store configuration.
		self::store_config( $config );

		// Initialize components.
		self::init_components();
	}

	/**
	 * Store configuration for later use.
	 *
	 * @since 1.0.0
	 *
	 * @param array $config Configuration array.
	 */
	private static function store_config( $config ) {
		// Allow plugins to provide their own assets.
		if ( ! empty( $config['app_js'] ) ) {
			add_filter( 'siteground_dashboard_app_js', function() use ( $config ) {
				return $config['app_js'];
			} );
		}

		if ( ! empty( $config['app_css'] ) ) {
			add_filter( 'siteground_dashboard_app_css', function() use ( $config ) {
				return $config['app_css'];
			} );
		}

		if ( ! empty( $config['plugin_data'] ) ) {
			add_filter( 'siteground_dashboard_plugin_data', function() use ( $config ) {
				return $config['plugin_data'];
			} );
		}
	}

	/**
	 * Initialize dashboard components.
	 *
	 * @since 1.0.0
	 */
	private static function init_components() {
		// Initialize admin page.
		Admin_Page::init();

		// Initialize REST API.
		Rest_Controller::init();

		// Allow other components to hook in.
		do_action( 'siteground_dashboard_init' );
	}

	/**
	 * Check if the dashboard is initialized.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if initialized.
	 */
	public static function is_initialized() {
		return self::$initialized;
	}

	/**
	 * Get the dashboard page URL.
	 *
	 * @since 1.0.0
	 *
	 * @return string The dashboard page URL.
	 */
	public static function get_page_url() {
		return Admin_Page::get_page_url();
	}
}
