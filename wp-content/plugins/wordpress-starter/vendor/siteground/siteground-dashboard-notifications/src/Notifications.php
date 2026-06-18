<?php
namespace SiteGround_Dashboard;

// Prevent direct access and multiple class loading.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( class_exists( 'SiteGround_Dashboard\Notifications' ) ) {
	return;
}

/**
 * SiteGround Dashboard Notifications class.
 *
 * Handles fetching per-site important notifications from SGAPI via Avalon's Request API.
 *
 * @since 1.0.0
 */
class Notifications {

	/**
	 * SiteTools Client Unix Socket.
	 *
	 * @since 1.0.0
	 *
	 * @var string Path to the SiteTools UNIX socket file.
	 */
	const SITE_TOOLS_SOCK_FILE = '/chroot/tmp/site-tools.sock';

	/**
	 * Cache expiration time in seconds (5 minutes).
	 *
	 * @since 1.0.0
	 *
	 * @var int
	 */
	const CACHE_EXPIRATION = 30;

	/**
	 * Transient key for cached notifications.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	const CACHE_KEY = 'sg_dashboard_notifications';

	/**
	 * Get the current WordPress app ID from Site Tools.
	 *
	 * @since 1.0.0
	 *
	 * @return int|false The app ID or false on failure.
	 */
	public static function get_app_id() {
		// Call the Site Tools client.
		$result = self::call_site_tools_client(
			array(
				'api'      => 'app',
				'cmd'      => 'list',
				'params'   => (object) array(),
				'settings' => array( 'json' => 1 ),
			)
		);

		// Bail if we do not get the result.
		if ( empty( $result['json'] ) ) {
			return false;
		}

		// Get the current site URL and parse it.
		$home_url    = get_home_url();
		$parsed_url  = wp_parse_url( $home_url );
		$domain_name = isset( $parsed_url['host'] ) ? str_replace( 'www.', '', $parsed_url['host'] ) : '';
		$path        = isset( $parsed_url['path'] ) ? $parsed_url['path'] : '/';

		// Normalize the path - ensure it starts with / and doesn't end with / (unless it's just /).
		if ( empty( $path ) ) {
			$path = '/';
		}

		// Search for the matching WordPress app.
		foreach ( $result['json'] as $app ) {
			// Skip if not a WordPress app.
			if ( empty( $app['app'] ) || 'wordpress' !== $app['app'] ) {
				continue;
			}

			// Skip if domain doesn't match.
			if ( empty( $app['domain_name'] ) || $app['domain_name'] !== $domain_name ) {
				continue;
			}

			// Get the app path, default to '/'.
			$app_path = isset( $app['path'] ) ? $app['path'] : '/';

			// Normalize paths for comparison.
			$normalized_path     = rtrim( $path, '/' );
			$normalized_app_path = rtrim( $app_path, '/' );

			// Handle root path case.
			if ( empty( $normalized_path ) ) {
				$normalized_path = '';
			}
			if ( empty( $normalized_app_path ) ) {
				$normalized_app_path = '';
			}

			// Check if paths match.
			if ( $normalized_path === $normalized_app_path ) {
				return isset( $app['id'] ) ? (int) $app['id'] : false;
			}
		}

		return false;
	}

	/**
	 * Get the site ID from Site Tools.
	 *
	 * @since 1.0.0
	 *
	 * @return string|false The site ID or false on failure.
	 */
	public static function get_site_id() {
		// Call the Site Tools client to get the site list.
		$result = self::call_site_tools_client(
			array(
				'api'      => 'site',
				'cmd'      => 'list',
				'params'   => (object) array(),
				'settings' => array( 'json' => 1 ),
			)
		);

		// Bail if we do not get the result.
		if ( empty( $result['json']['name'] ) ) {
			return false;
		}

		return $result['json']['name'];
	}

	/**
	 * Get the bundle ID from Site Tools.
	 *
	 * @since 1.0.0
	 *
	 * @return string|false The bundle ID or false on failure.
	 */
	public static function get_bundle_id() {
		// Call the Site Tools client to get the site list.
		$result = self::call_site_tools_client(
			array(
				'api'      => 'site',
				'cmd'      => 'list',
				'params'   => (object) array(),
				'settings' => array( 'json' => 1 ),
			)
		);

		// Bail if we do not get the result.
		if ( empty( $result['json']['bundle_id'] ) ) {
			return false;
		}

		return $result['json']['bundle_id'];
	}

	/**
	 * Get the site information needed for notifications.
	 *
	 * @since 1.0.0
	 *
	 * @return array|false The site information array or false on failure.
	 */
	public static function get_site_info() {
		// Get the app ID first.
		$app_id = self::get_app_id();

		if ( false === $app_id ) {
			return false;
		}

		// Get the site ID from Site Tools.
		$site_id = self::get_site_id();

		if ( false === $site_id ) {
			return false;
		}

		// Get the bundle ID from Site Tools.
		$bundle_id = self::get_bundle_id();

		if ( false === $bundle_id ) {
			return false;
		}

		// Get the current site URL and parse it.
		$home_url    = get_home_url();
		$parsed_url  = wp_parse_url( $home_url );
		$domain_name = isset( $parsed_url['host'] ) ? str_replace( 'www.', '', $parsed_url['host'] ) : '';

		// Get the server hostname.
		$server_hostname = gethostname();

		if ( false === $server_hostname ) {
			return false;
		}

		return array(
			'domain_name'     => $domain_name,
			'server_hostname' => $server_hostname,
			'site_id'         => $site_id,
			'bundle_id'       => strval( $bundle_id ),
		);
	}

	/**
	 * Fetch notifications from SGAPI via Avalon's request-perform API.
	 *
	 * @since 1.0.0
	 *
	 * @param bool $force_refresh Whether to bypass cache and fetch fresh data.
	 *
	 * @return array Array of notifications or empty array on failure.
	 */
	public static function get_notifications( $force_refresh = false ) {
		// Try to get cached notifications first.
		if ( ! $force_refresh ) {
			$cached = get_transient( self::CACHE_KEY );
			if ( false !== $cached ) {
				return $cached;
			}
		}

		// Get the site information.
		$site_info = self::get_site_info();

		if ( false === $site_info ) {
			return array();
		}

		// Call the Site Tools client with request-perform API.
		$result = self::call_site_tools_client(
			array(
				'api'      => 'request-perform',
				'cmd'      => 'create',
				'settings' => array( 'json' => 1 ),
				'params'   => array(
					'api_name'         => 'wp-dashboard',
					'request_method'   => 'GET',
					'token_extra_data' => $site_info,
				),
			)
		);

		// Handle failed API call.
		if ( false === $result ) {
			return array();
		}

		// Extract notifications from the result.
		$notifications = array();
		if ( ! empty( $result['json'] ) && is_array( $result['json'] ) ) {
			$notifications = $result['json'];
		}

		// Cache the notifications.
		set_transient( self::CACHE_KEY, $notifications, self::CACHE_EXPIRATION );

		return $notifications;
	}

	/**
	 * Clear the notifications cache.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True on success, false on failure.
	 */
	public static function clear_cache() {
		return delete_transient( self::CACHE_KEY );
	}

	/**
	 * Open socket and run a specific command.
	 *
	 * @since 1.0.0
	 *
	 * @param array $args        The command arguments.
	 * @param bool  $json_object Whether to force json object upon json encode.
	 *
	 * @return bool|array Array with results or false on failure.
	 */
	protected static function call_site_tools_client( $args, $json_object = false ) {
		// Bail if the socket does not exist.
		if ( ! file_exists( self::SITE_TOOLS_SOCK_FILE ) ) {
			return false;
		}

		// Bail if no arguments present.
		if ( empty( $args ) ) {
			return false;
		}

		// Open unix socket connection.
		$fp = @stream_socket_client( 'unix://' . self::SITE_TOOLS_SOCK_FILE, $errno, $errstr, 5 );

		// Bail if the connection fails.
		if ( false === $fp ) {
			return false;
		}

		// Build the request params.
		$request = array(
			'api'      => $args['api'],
			'cmd'      => $args['cmd'],
			'params'   => $args['params'],
			'settings' => $args['settings'],
		);

		// Generate the json_encode flags based on passed variable.
		$flags = ( false === $json_object ) ? 0 : JSON_FORCE_OBJECT;

		// Send the params to the Unix socket.
		fwrite( $fp, json_encode( $request, $flags ) . "\n" );

		// Fetch the response.
		$response = fgets( $fp, 32 * 1024 );

		// Close the connection.
		fclose( $fp );

		// Decode the response.
		$result = @json_decode( $response, true );

		if ( false === $result || isset( $result['err_code'] ) ) {
			return false;
		}

		return $result;
	}
}
