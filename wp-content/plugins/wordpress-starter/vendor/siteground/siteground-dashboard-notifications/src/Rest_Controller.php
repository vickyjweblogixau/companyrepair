<?php
namespace SiteGround_Dashboard;

// Prevent direct access and multiple class loading.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( class_exists( 'SiteGround_Dashboard\Rest_Controller' ) ) {
	return;
}

/**
 * REST API Controller for SiteGround Dashboard.
 *
 * Handles REST API endpoints for the dashboard.
 *
 * @since 1.0.0
 */
class Rest_Controller extends \WP_REST_Controller {

	/**
	 * The namespace for the REST API.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected $namespace = 'siteground-dashboard/v1';

	/**
	 * Initialize the REST controller.
	 *
	 * @since 1.0.0
	 */
	public static function init() {
		$controller = new self();
		add_action( 'rest_api_init', array( $controller, 'register_routes' ) );
	}

	/**
	 * Register the REST API routes.
	 *
	 * @since 1.0.0
	 */
	public function register_routes() {
		// Get dashboard data.
		register_rest_route(
			$this->namespace,
			'/dashboard',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'get_dashboard_data' ),
					'permission_callback' => array( $this, 'check_permissions' ),
				),
			)
		);

		// Get notifications.
		register_rest_route(
			$this->namespace,
			'/notifications',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_notifications' ),
					'permission_callback' => array( $this, 'check_permissions' ),
				),
			)
		);

		// Refresh notifications (clear cache).
		register_rest_route(
			$this->namespace,
			'/notifications/refresh',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'refresh_notifications' ),
					'permission_callback' => array( $this, 'check_permissions' ),
				),
			)
		);

		// Get site info.
		register_rest_route(
			$this->namespace,
			'/site-info',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_site_info' ),
					'permission_callback' => array( $this, 'check_permissions' ),
				),
			)
		);

		// Get Speed Optimizer status.
		register_rest_route(
			$this->namespace,
			'/power-tools/speed-optimizer',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_speed_optimizer_status' ),
					'permission_callback' => array( $this, 'check_permissions' ),
				),
			)
		);

		// Get Security Optimizer status.
		register_rest_route(
			$this->namespace,
			'/power-tools/security-optimizer',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_security_optimizer_status' ),
					'permission_callback' => array( $this, 'check_permissions' ),
				),
			)
		);

		// Activate a plugin.
		register_rest_route(
			$this->namespace,
			'/plugins/(?P<plugin_slug>[^/]+)',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'activate_plugin' ),
					'permission_callback' => array( $this, 'check_permissions' ),
					'args'                => array(
						'plugin_slug' => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
			)
		);

		// Connect Email Marketing.
		register_rest_route(
			$this->namespace,
			'/email-marketing/connect',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'connect_email_marketing' ),
					'permission_callback' => array( $this, 'check_permissions' ),
					'args'                => array(
						'token' => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
			)
		);

		// Get partner plugins.
		register_rest_route(
			$this->namespace,
			'/partner-plugins',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_partner_plugins' ),
					'permission_callback' => array( $this, 'check_permissions' ),
				),
			)
		);

		// Install and activate a partner plugin.
		register_rest_route(
			$this->namespace,
			'/partner-plugins/(?P<plugin_slug>[^/]+)',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'activate_partner_plugin' ),
					'permission_callback' => array( $this, 'check_permissions' ),
					'args'                => array(
						'plugin_slug' => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
			)
		);
	}

	/**
	 * Check user permissions.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request The REST request.
	 *
	 * @return bool True if user has permission.
	 */
	public function check_permissions( $request ) {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Get complete dashboard data.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request The REST request.
	 *
	 * @return \WP_REST_Response REST response.
	 */
	public function get_dashboard_data( $request ) {
		$data = array(
			'service_data'    => $this->prepare_notifications_data(),
			'site_info'       => $this->prepare_site_info_data(),
			'power_tools'     => array(
				'security_optimization_status' => Power_Tools_Helper::get_security_optimizer_status(),
				'speed_optimization_status'    => Power_Tools_Helper::get_speed_optimizer_status(),
				'email_marketing_status'       => Power_Tools_Helper::get_email_marketing_status(),
				'sg_ai_studio_status'          => Power_Tools_Helper::get_ai_studio_status(),
			),
			'partner_plugins' => Partner_Plugins_Helper::get_partner_plugins_data(),
		);

		return rest_ensure_response( $data );
	}

	/**
	 * Get notifications.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request The REST request.
	 *
	 * @return \WP_REST_Response REST response.
	 */
	public function get_notifications( $request ) {
		$data = $this->prepare_notifications_data();

		return rest_ensure_response( $data );
	}

	/**
	 * Refresh notifications (clear cache and fetch new).
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request The REST request.
	 *
	 * @return \WP_REST_Response REST response.
	 */
	public function refresh_notifications( $request ) {
		// Clear the cache.
		Notifications::clear_cache();

		// Fetch fresh notifications.
		$notifications = Notifications::get_notifications( true );

		$data = array(
			'message'       => __( 'Notifications refreshed successfully.', 'siteground-dashboard' ),
			'notifications' => $this->prepare_notifications_data(),
		);

		return rest_ensure_response( $data );
	}

	/**
	 * Get site information.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request The REST request.
	 *
	 * @return \WP_REST_Response REST response.
	 */
	public function get_site_info( $request ) {
		$data = $this->prepare_site_info_data();

		return rest_ensure_response( $data );
	}

	/**
	 * Prepare notifications data for response.
	 *
	 * @since 1.0.0
	 *
	 * @return array Formatted notifications data.
	 */
	private function prepare_notifications_data() {
		$notifications = Notifications::get_notifications();

		// If no notifications, return empty structure.
		if ( empty( $notifications ) ) {
			return array(
				'has_notifications' => false,
				'items'             => array(),
			);
		}

		// Return notifications with metadata.
		return array(
			'has_notifications' => true,
			'count'             => count( $notifications ),
			'items'             => $notifications,
		);
	}

	/**
	 * Prepare site info data for response.
	 *
	 * @since 1.0.0
	 *
	 * @return array Formatted site info.
	 */
	private function prepare_site_info_data() {
		$site_info = Notifications::get_site_info();

		// If site info retrieval failed.
		if ( false === $site_info ) {
			return array(
				'available' => false,
				'message'   => __( 'Site information is not available.', 'siteground-dashboard' ),
			);
		}

		return array(
			'available'       => true,
			'domain_name'     => $site_info['domain_name'],
			'server_hostname' => $site_info['server_hostname'],
			'site_id'         => $site_info['site_id'],
			'bundle_id'       => $site_info['bundle_id'],
		);
	}

	/**
	 * Send success response.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $data    The response data.
	 * @param int   $status  HTTP status code (default 200).
	 *
	 * @return \WP_REST_Response REST response.
	 */
	protected function send_response( $data, $status = 200 ) {
		return new \WP_REST_Response( $data, $status );
	}

	/**
	 * Send error response.
	 *
	 * @since 1.0.0
	 *
	 * @param string $message Error message.
	 * @param int    $status  HTTP status code (default 400).
	 *
	 * @return \WP_Error WP Error object.
	 */
	protected function send_error( $message, $status = 400 ) {
		return new \WP_Error( 'siteground_dashboard_error', $message, array( 'status' => $status ) );
	}

	/**
	 * Get Speed Optimizer plugin status.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request The REST request.
	 *
	 * @return \WP_REST_Response REST response.
	 */
	public function get_speed_optimizer_status( $request ) {
		$data = Power_Tools_Helper::get_speed_optimizer_status();
		return rest_ensure_response( $data );
	}

	/**
	 * Get Security Optimizer plugin status.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request The REST request.
	 *
	 * @return \WP_REST_Response REST response.
	 */
	public function get_security_optimizer_status( $request ) {
		$data = Power_Tools_Helper::get_security_optimizer_status();
		return rest_ensure_response( $data );
	}

	/**
	 * Activate a plugin.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request The REST request.
	 *
	 * @return \WP_REST_Response|\WP_Error REST response or error.
	 */
	public function activate_plugin( $request ) {
		$plugin_slug = $request->get_param( 'plugin_slug' );

		$result = Power_Tools_Helper::activate_plugin( $plugin_slug );

		if ( ! $result['success'] ) {
			return $this->send_error( $result['message'] );
		}

		// Clear all caches.
		if( \function_exists('\sg_cachepress_purge_cache') ) {
			\sg_cachepress_purge_cache();
			\wp_cache_flush();
		} else {
			\wp_cache_flush();
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => $result['message'],
			)
		);
	}

	/**
	 * Connect to Email Marketing service.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request The REST request.
	 *
	 * @return \WP_REST_Response|\WP_Error REST response or error.
	 */
	public function connect_email_marketing( $request ) {
		$token = $request->get_param( 'token' );

		$result = Power_Tools_Helper::connect_email_marketing( $token );

		if ( ! $result['success'] ) {
			return $this->send_error( $result['message'] );
		}

		// Clear all caches.
		if( \function_exists('\sg_cachepress_purge_cache') ) {
			\sg_cachepress_purge_cache();
			\wp_cache_flush();
		} else {
			\wp_cache_flush();
		}

		return rest_ensure_response( $result );
	}

	/**
	 * Get partner plugins data.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request The REST request.
	 *
	 * @return \WP_REST_Response REST response.
	 */
	public function get_partner_plugins( $request ) {
		$data = Partner_Plugins_Helper::get_partner_plugins_data();

		return rest_ensure_response(
			array(
				'success' => true,
				'plugins' => $data,
			)
		);
	}

	/**
	 * Install and activate a partner plugin.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request The REST request.
	 *
	 * @return \WP_REST_Response|\WP_Error REST response or error.
	 */
	public function activate_partner_plugin( $request ) {
		$plugin_slug = $request->get_param( 'plugin_slug' );

		$result = Partner_Plugins_Helper::install_and_activate_plugin( $plugin_slug );

		if ( ! $result['success'] ) {
			return $this->send_error( $result['message'] );
		}

		// Clear all caches.
		if( \function_exists('\sg_cachepress_purge_cache') ) {
			\sg_cachepress_purge_cache();
			\wp_cache_flush();
		} else {
			\wp_cache_flush();
		}

		return rest_ensure_response( $result );
	}
}
