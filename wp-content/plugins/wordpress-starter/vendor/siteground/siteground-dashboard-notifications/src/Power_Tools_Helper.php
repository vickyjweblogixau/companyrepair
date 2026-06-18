<?php
namespace SiteGround_Dashboard;

use SiteGround_Dashboard\Partner_Plugins_Helper;

// Prevent direct access and multiple class loading.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( class_exists( 'SiteGround_Dashboard\Power_Tools_Helper' ) ) {
	return;
}

/**
 * Power Tools Helper class.
 *
 * Handles logic for Speed Optimizer and Security Optimizer plugin cards.
 *
 * @since 1.0.0
 */
class Power_Tools_Helper {

	/**
	 * Speed Optimizer plugin slug.
	 */
	const SPEED_OPTIMIZER_SLUG = 'sg-cachepress';

	/**
	 * Security Optimizer plugin slug.
	 */
	const SECURITY_OPTIMIZER_SLUG = 'sg-security';

	/**
	 * Email Marketing plugin slug.
	 */
	const EMAIL_MARKETING_SLUG = 'siteground-email-marketing';


	const SG_AI_STUDIO_SLUG = 'sg-ai-studio';
	/**
	 * Speed Optimizer full plugin path.
	 */
	const SPEED_OPTIMIZER_PATH = 'sg-cachepress/sg-cachepress.php';

	/**
	 * Security Optimizer full plugin path.
	 */
	const SECURITY_OPTIMIZER_PATH = 'sg-security/sg-security.php';

	/**
	 * Email Marketing full plugin path.
	 */
	const EMAIL_MARKETING_PATH = 'siteground-email-marketing/sg-email-marketing.php';


	const SG_AI_STUDIO_PATH = 'sg-ai-studio/sg-ai-studio.php';

	/**
	 * Speed Optimizer recommended caching options.
	 *
	 * @var array
	 */
	private static $speed_optimizer_caching_options = array(
		'enable_cache',
		'enable_memcached',
		'autoflush_cache',
		'file_caching',
	);

	/**
	 * Security Optimizer recommended site security options.
	 *
	 * @var array
	 */
	private static $security_optimizer_site_security_options = array(
		'lock_system_folders',
		'wp_remove_version',
		'disable_file_edit',
		'disable_xml_rpc',
		'disable_feed',
		'xss_protection',
	);

	/**
	 * Get Speed Optimizer status and recommended options count.
	 *
	 * @since 1.0.0
	 *
	 * @return array Status data.
	 */
	public static function get_speed_optimizer_status() {
		$is_active = self::is_plugin_active( self::SPEED_OPTIMIZER_PATH );

		return array(
			'is_active'            => $is_active,
			'plugin_slug'          => self::SPEED_OPTIMIZER_SLUG,
			'settings_url'         => $is_active ? admin_url( 'admin.php?page=sgo_caching' ) : '',
			'recommended_options'  => $is_active ? self::get_speed_optimizer_recommended_count() : array(
				'total'  => 4,
				'active' => 0,
			),
		);
	}

	/**
	 * Get Security Optimizer status and recommended options count.
	 *
	 * @since 1.0.0
	 *
	 * @return array Status data.
	 */
	public static function get_security_optimizer_status() {
		$is_active = self::is_plugin_active( self::SECURITY_OPTIMIZER_PATH );

		return array(
			'is_active'            => $is_active,
			'plugin_slug'          => self::SECURITY_OPTIMIZER_SLUG,
			'settings_url'         => $is_active ? admin_url( 'admin.php?page=site-security' ) : '',
			'recommended_options'  => $is_active ? self::get_security_optimizer_recommended_count() : array(
				'total'  => 6,
				'active' => 0,
			),
		);
	}

	/**
	 * Get Email Marketing plugin status and connection state.
	 *
	 * @since 1.0.0
	 *
	 * @return array Status data with connection_state: 'not_active', 'not_connected', or 'connected'.
	 */
	public static function get_email_marketing_status() {
		$is_installed               = self::is_plugin_installed( self::EMAIL_MARKETING_PATH );
		$is_active                  = self::is_plugin_active( self::EMAIL_MARKETING_PATH );

		$status = array(
			'plugin_slug'      => self::EMAIL_MARKETING_SLUG,
			'connection_state' => 'not_active',
		);

		// If not installed or not active, return 'not_active' state.
		if ( ! $is_installed || ! $is_active ) {
			return $status;
		}

		$status['connection_state'] = 'not_connected';

		// If installed and active, check connection status.
		if ( class_exists( 'SG_Email_Marketing\Services\Mailer_Api\Mailer_Api' ) ) {
			$mailer_api        = new \SG_Email_Marketing\Services\Mailer_Api\Mailer_Api();
			$connection_status = $mailer_api->get_status();

			// Map the connection status to our state.
			if ( isset( $connection_status['status'] ) && 'connected' === $connection_status['status'] ) {
				$status['connection_state'] = 'connected';
			}
		}

		return $status;
	}

	/**
	 * Get Email Marketing plugin status and connection state.
	 *
	 * @since 1.0.0
	 *
	 * @return array Status data with connection_state: 'not_active', 'not_connected', or 'connected'.
	 */
	public static function get_ai_studio_status() {
		$is_installed = self::is_plugin_installed( self::SG_AI_STUDIO_PATH );
		$is_active    = self::is_plugin_active( self::SG_AI_STUDIO_PATH );

		$status = array(
			'plugin_slug'      => self::SG_AI_STUDIO_SLUG,
			'connection_state' => 'not_active',
		);

		// If not installed or not active, return 'not_active' state.
		if ( ! $is_installed || ! $is_active ) {
			return $status;
		}

		// If installed and active, check connection status.
			$connection_status = get_option( 'sg_ai_studio_connected', false );

			// Map the connection status to our state.
		if ( $connection_status ) {
			$status['connection_state'] = 'connected';
		} else {
			$status['connection_state'] = 'not_connected';
		}

		return $status;
	}

	/**
	 * Connect to Email Marketing service.
	 *
	 * @since 1.0.0
	 *
	 * @param string $token The authentication token.
	 *
	 * @return array Result with success status and message.
	 */
	public static function connect_email_marketing( $token ) {
		// Validate that plugin is active.
		if ( ! self::is_plugin_active( self::EMAIL_MARKETING_PATH ) ) {
			return array(
				'success' => false,
				'message' => __( 'Email Marketing plugin is not active.', 'siteground-dashboard' ),
			);
		}

		// Validate token is provided.
		if ( empty( $token ) ) {
			return array(
				'success' => false,
				'message' => __( 'Missing token.', 'siteground-dashboard' ),
			);
		}

		// Check if the Mailer_Api class exists.
		if ( ! class_exists( 'SG_Email_Marketing\Services\Mailer_Api\Mailer_Api' ) ) {
			return array(
				'success' => false,
				'message' => __( 'Email Marketing API is not available.', 'siteground-dashboard' ),
			);
		}

		try {
			$mailer_api = new \SG_Email_Marketing\Services\Mailer_Api\Mailer_Api();
			$response   = $mailer_api->connect( $token );

			// If the connection is successful (status codes 204 or 403 mean connected or suspended).
			if ( isset( $response['status_code'] ) && in_array( $response['status_code'], array( 204, 403 ), true ) ) {
				update_option( 'sg_email_marketing_token', $token );

				return array(
					'success'     => true,
					'message'     => isset( $response['message'] ) ? $response['message'] : __( 'Connected successfully.', 'siteground-dashboard' ),
					'status'      => isset( $response['status'] ) ? $response['status'] : 'connected',
					'status_code' => $response['status_code'],
				);
			}

			return array(
				'success' => false,
				'message' => isset( $response['message'] ) ? $response['message'] : __( 'Connection failed.', 'siteground-dashboard' ),
			);
		} catch ( \Exception $e ) {
			return array(
				'success' => false,
				'message' => $e->getMessage(),
			);
		}
	}

	/**
	 * Get count of enabled recommended caching options for Speed Optimizer.
	 *
	 * @since 1.0.0
	 *
	 * @return array Total and active counts.
	 */
	private static function get_speed_optimizer_recommended_count() {
		$total_optimizations  = count( self::$speed_optimizer_caching_options );
		$active_optimizations = 0;

		// Count the enabled optimizations.
		foreach ( self::$speed_optimizer_caching_options as $option ) {
			$option_value = get_option( 'siteground_optimizer_' . $option, 0 );

			// Add to the count if the optimization is enabled.
			if ( 0 !== intval( $option_value ) ) {
				$active_optimizations++;
			}
		}

		return array(
			'total'  => $total_optimizations,
			'active' => $active_optimizations,
		);
	}

	/**
	 * Get count of enabled recommended site security options for Security Optimizer.
	 *
	 * @since 1.0.0
	 *
	 * @return array Total and active counts.
	 */
	private static function get_security_optimizer_recommended_count() {
		$total_optimizations  = count( self::$security_optimizer_site_security_options );
		$active_optimizations = 0;

		// Count the enabled optimizations.
		foreach ( self::$security_optimizer_site_security_options as $option ) {
			// Get the option value.
			$option_value = get_option( 'sg_security_' . $option, 0 );

			// Add to the count if the optimization is enabled.
			if ( 1 === intval( $option_value ) ) {
				$active_optimizations++;
			}
		}

		return array(
			'total'  => $total_optimizations,
			'active' => $active_optimizations,
		);
	}

	/**
	 * Convert short slug to full plugin path.
	 *
	 * @since 1.0.0
	 *
	 * @param string $slug Short plugin slug (e.g., 'sg-cachepress').
	 *
	 * @return string|null Full plugin path or null if invalid.
	 */
	private static function get_plugin_path( $slug ) {
		$map = array(
			self::SPEED_OPTIMIZER_SLUG    => self::SPEED_OPTIMIZER_PATH,
			self::SECURITY_OPTIMIZER_SLUG => self::SECURITY_OPTIMIZER_PATH,
			self::EMAIL_MARKETING_SLUG    => self::EMAIL_MARKETING_PATH,
			self::SG_AI_STUDIO_SLUG       => self::SG_AI_STUDIO_PATH,
		);

		return $map[ $slug ] ?? null;
	}

	/**
	 * Check if a plugin is active.
	 *
	 * @since 1.0.0
	 *
	 * @param string $plugin_path Full plugin path (e.g., 'sg-cachepress/sg-cachepress.php').
	 *
	 * @return bool True if active.
	 */
	private static function is_plugin_active( $plugin_path ) {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			include_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		return is_plugin_active( $plugin_path );
	}

	/**
	 * Check if a plugin is installed.
	 *
	 * @since 1.0.0
	 *
	 * @param string $plugin_path Full plugin path (e.g., 'sg-cachepress/sg-cachepress.php').
	 *
	 * @return bool True if installed.
	 */
	private static function is_plugin_installed( $plugin_path ) {
		$installed_plugins = get_plugins();
		return isset( $installed_plugins[ $plugin_path ] );
	}

	/**
	 * Activate a plugin.
	 *
	 * @since 1.0.0
	 *
	 * @param string $plugin_slug Short plugin slug (e.g., 'sg-cachepress').
	 *
	 * @return array Result with success status and message.
	 */
	public static function activate_plugin( $plugin_slug ) {
		// Validate plugin slug.
		if ( ! in_array( $plugin_slug, array( self::SPEED_OPTIMIZER_SLUG, self::SECURITY_OPTIMIZER_SLUG, self::SG_AI_STUDIO_SLUG, self::EMAIL_MARKETING_SLUG ), true ) ) {
			$result = Partner_Plugins_Helper::install_and_activate_plugin( $plugin_slug );
			return $result;
		}

		// Get full plugin path.
		$plugin_path = self::get_plugin_path( $plugin_slug );

		if ( ! $plugin_path ) {
			return array(
				'success' => false,
				'message' => __( 'Invalid plugin slug.', 'siteground-dashboard' ),
			);
		}

		// Check if already active.
		if ( self::is_plugin_active( $plugin_path ) ) {
			return array(
				'success' => true,
				'message' => __( 'Plugin is already active.', 'siteground-dashboard' ),
			);
		}

		// Check if plugin is installed.
		if ( ! self::is_plugin_installed( $plugin_path ) ) {
			// Try to install the plugin.
			$install_result = self::install_plugin( $plugin_slug );

			if ( ! $install_result['success'] ) {
				return $install_result;
			}
		}

		// Activate the plugin.
		if ( ! function_exists( 'activate_plugin' ) ) {
			include_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$result = activate_plugin( $plugin_path );

		if ( is_wp_error( $result ) ) {
			return array(
				'success' => false,
				'message' => $result->get_error_message(),
			);
		}

		return array(
			'success' => true,
			'message' => __( 'Plugin activated successfully.', 'siteground-dashboard' ),
		);
	}

	/**
	 * Install a plugin.
	 *
	 * @since 1.0.0
	 *
	 * @param string $plugin_slug Short plugin slug (e.g., 'sg-cachepress').
	 *
	 * @return array Result with success status and message.
	 */
	private static function install_plugin( $plugin_slug ) {
		// The short slug is the same as the WordPress.org download slug.
		$download_slug = $plugin_slug;

		// Include required WordPress files.
		if ( ! function_exists( 'plugins_api' ) ) {
			include_once ABSPATH . 'wp-admin/includes/plugin-install.php';
		}

		if ( ! class_exists( 'WP_Upgrader' ) ) {
			include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		}

		// Get plugin information from WordPress.org.
		$api = plugins_api(
			'plugin_information',
			array(
				'slug'   => $download_slug,
				'fields' => array(
					'short_description' => false,
					'sections'          => false,
					'requires'          => false,
					'rating'            => false,
					'ratings'           => false,
					'downloaded'        => false,
					'last_updated'      => false,
					'added'             => false,
					'tags'              => false,
					'compatibility'     => false,
					'homepage'          => false,
					'donate_link'       => false,
				),
			)
		);

		if ( is_wp_error( $api ) ) {
			return array(
				'success' => false,
				'message' => $api->get_error_message(),
			);
		}

		// Install the plugin.
		$upgrader = new \Plugin_Upgrader( new \WP_Ajax_Upgrader_Skin() );
		$result   = $upgrader->install( $api->download_link );

		if ( is_wp_error( $result ) ) {
			return array(
				'success' => false,
				'message' => $result->get_error_message(),
			);
		}

		if ( ! $result ) {
			return array(
				'success' => false,
				'message' => __( 'Plugin installation failed.', 'siteground-dashboard' ),
			);
		}

		return array(
			'success' => true,
			'message' => __( 'Plugin installed successfully.', 'siteground-dashboard' ),
		);
	}
}
