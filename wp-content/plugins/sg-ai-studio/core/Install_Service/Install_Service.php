<?php

namespace SG_AI_Studio\Install_Service;

use SG_AI_Studio\Install_Service\Install_1_1_8;
use SG_AI_Studio\Install_Service\Install_1_2_1;

/**
 * Define the Install interface.
 *
 * @since 1.1.8
 */
class Install_Service {
	/**
	 * Array, containing all installs.
	 *
	 * @var array
	 */
	public $installs;

	public function __construct() {
		// Get the install services.
		$this->installs = array(
			new Install_1_1_8(),
			new Install_1_2_1(),
		);
	}

	/**
	 * Loop through all versions and install the updates.
	 *
	 * @since 1.1.8
	 *
	 * @return void
	 */
	public function install() {
		// Use a transient to avoid concurrent installation calls.
		if ( $this->install_required() && false === get_transient( '_sg_ai_studio_installing' ) ) {
			set_transient( '_sg_ai_studio_installing', true, 5 * MINUTE_IN_SECONDS );

			// Do the install.
			$this->do_install();

			// Delete the transient after the install.
			delete_transient( '_sg_ai_studio_installing' );
		}

		$this->check_current_version();
	}

	/**
	 * Perform the actual installation.
	 *
	 * @since 1.1.8
	 */
	private function do_install() {

		$version = null;

		foreach ( $this->installs as $install ) {
			// Get the install version.
			$version = $install->get_version();

			if ( version_compare( $version, $this->get_current_version(), '>' ) ) {
				// Install version.
				$install->install();

				// Bump the version.
				update_option( 'sg_ai_studio_version', $version );

				update_option( 'sg_ai_studio_update_timestamp', time() );
			}
		}
	}

	/**
	 * Retrieve the current version.
	 *
	 * @return string
	 */
	private function get_current_version() {
		return get_option( 'sg_ai_studio_version', '0.0.0' );
	}

	/**
	 * Checks whether update is required.
	 *
	 * @since 1.1.8
	 *
	 * @return bool True/false.
	 */
	private function install_required() {
		foreach ( $this->installs as $install ) {
			// Get the install version.
			$version = $install->get_version();

			if ( version_compare( $version, $this->get_current_version(), '>' ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check the current plugin version and update config if needed.
	 *
	 * @since 1.1.8
	 */
	private function check_current_version() {
		// Bail if we have the latest version.
		if ( version_compare( get_option( 'sg_ai_studio_current_version', false ), \SG_AI_Studio\VERSION, '==' ) ) {
			return;
		}

		// Update the option in the db.
		update_option( 'sg_ai_studio_current_version', \SG_AI_Studio\VERSION );
	}
}
