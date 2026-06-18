<?php
namespace SiteGround_Dashboard;

use SiteGround_Central\Admin\Admin;

// Prevent direct access and multiple class loading.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( class_exists( 'SiteGround_Dashboard\Admin_Page' ) ) {
	return;
}

/**
 * SiteGround Dashboard Admin Page.
 *
 * Creates and manages the SiteGround admin page in WordPress.
 *
 * @since 1.0.0
 */
class Admin_Page {

	/**
	 * The page slug.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	const PAGE_SLUG = 'siteground-dashboard';

	/**
	 * The capability required to access the page.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	const CAPABILITY = 'manage_options';

	/**
	 * Initialize the admin page.
	 *
	 * @since 1.0.0
	 */
	public static function init() {
		if ( ! defined( __NAMESPACE__ . '\URL' ) ) {
			// Get URL to the plugin root directory (parent of /src/).
			define( __NAMESPACE__ . '\URL', \untrailingslashit( \plugins_url( '',  __DIR__ ) ) );
		}

		// Register admin menu.
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );

		// Enqueue scripts for the admin page.
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_scripts' ) );
		add_action( 'admin_init', array( __CLASS__, 'hide_errors_and_notices' ), PHP_INT_MAX );
	}

	/**
	 * Register the admin menu page.
	 *
	 * @since 1.0.0
	 */
	public static function register_menu() {
		add_menu_page(
			__( 'SiteGround', 'siteground-dashboard' ),
			__( 'SiteGround', 'siteground-dashboard' ),
			self::CAPABILITY,
			self::PAGE_SLUG,
			array( __CLASS__, 'render_page' ),
			\SiteGround_Dashboard\URL . '/assets/img/sg_white.svg',
			1
		);
	}

	/**
	 * Get the menu icon (base64 encoded SVG).
	 *
	 * @since 1.0.0
	 *
	 * @return string Menu icon data URL.
	 */
	private static function get_menu_icon() {
		// SiteGround logo SVG (placeholder - replace with actual logo).
		$svg = '<svg width="20" height="20" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><circle cx="10" cy="10" r="8" fill="%23a7aaad"/></svg>';
		return 'data:image/svg+xml;base64,' . base64_encode( $svg );
	}

	/**
	 * Render the admin page.
	 *
	 * @since 1.0.0
	 */
	public static function render_page() {
		?>
		<div class="wrap">
			<div id="sg-dashboard-plugin-container"></div>
		</div>

		<style>
			#wpbody-content {
				padding-bottom: 25px;
			}
		</style>

		<?php
	}

	/**
	 * Enqueue scripts and styles for the admin page.
	 *
	 * @since 1.0.0
	 *
	 * @param string $hook The current admin page hook.
	 */
	public static function enqueue_scripts( $hook ) {
		// Only load on our page.
		if ( 'toplevel_page_' . self::PAGE_SLUG !== $hook ) {
			return;
		}

		if ( ! defined( __NAMESPACE__ . '\URL' ) ) {
			// Get URL to the plugin root directory (parent of /src/).
			define( __NAMESPACE__ . '\URL', \untrailingslashit( \plugins_url( '',  __DIR__ ) ) );
		}

		// Get the plugin that's loading this vendor.
		$plugin_data = self::get_plugin_data();

		wp_enqueue_script(
			'siteground-dashboard-app-js',
			\SiteGround_Dashboard\URL . '/assets/js/main.min.js',
			array(),
			$plugin_data['version'],
			true
		);

		wp_enqueue_style(
			'siteground-dashboard-app-css',
			\SiteGround_Dashboard\URL . '/assets/css/main.min.css',
			array(),
			$plugin_data['version']
		);

		wp_localize_script(
			'siteground-dashboard-app-js',
			'SGDashboardConfig',
			array(
				'domElementId' => 'sg-dashboard-plugin-container',
				'page'         => 'dashboard',
				'config'       => array(
					'rest_base'      => untrailingslashit( get_rest_url( null, '/' ) ),
					'home_url'       => get_home_url(),
					'admin_url'      => get_admin_url(),
					'rest_namespace' => 'sg-dashboard/v1',
					'locale'         => Admin::get_i18n_data_json(),
					'localeSlug'     => join( '-', explode( '_', \get_user_locale() ) ),
					'wp_nonce'       => wp_create_nonce( 'wp_rest' ),
					'assetsPath'     => \SiteGround_Dashboard\URL . '/assets/',
				)
			)
		);
		wp_add_inline_script(
			'siteground-dashboard-app-js',
			'window.process = { browser: true, env: { ENVIRONMENT: "BROWSER" } };jQuery( document ).ready(function() {SGDashboardPlugin.createApp(SGDashboardConfig);});',
			'after'
		);

	}

	/**
	 * Get plugin data from the calling plugin.
	 *
	 * @since 1.0.0
	 *
	 * @return array Plugin data.
	 */
	private static function get_plugin_data() {
		// Try to get plugin data from filter.
		$plugin_data = apply_filters( 'siteground_dashboard_plugin_data', array() );

		// Default values.
		return wp_parse_args(
			$plugin_data,
			array(
				'version' => '1.0.0',
				'name'    => 'SiteGround Dashboard',
			)
		);
	}

	/**
	 * Get the page URL.
	 *
	 * @since 1.0.0
	 *
	 * @return string The admin page URL.
	 */
	public static function get_page_url() {
		return admin_url( 'admin.php?page=' . self::PAGE_SLUG );
	}

	/**
	 * Check if we're on the SiteGround dashboard page.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if on the dashboard page.
	 */
	public static function is_dashboard_page() {
		$screen = get_current_screen();
		return $screen && 'toplevel_page_' . self::PAGE_SLUG === $screen->id;
	}

	public static function hide_errors_and_notices() {
		// Hide all error in our page.
		if (
			isset( $_GET['page'] ) && // phpcs:ignore
			$_GET['page'] === self::PAGE_SLUG // phpcs:ignore
		) {
			remove_all_actions( 'network_admin_notices' );
			remove_all_actions( 'user_admin_notices' );
			remove_all_actions( 'admin_notices' );
			remove_all_actions( 'all_admin_notices' );

			error_reporting( 0 );
		}
	}
}
