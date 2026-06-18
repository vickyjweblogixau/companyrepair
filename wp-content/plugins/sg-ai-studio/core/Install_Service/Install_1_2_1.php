<?php
namespace SG_AI_Studio\Install_Service;

class Install_1_2_1 extends Install {

	/**
	 * The default install version. Overridden by the installation packages.
	 *
	 * @since 1.1.8
	 *
	 * @access protected
	 *
	 * @var string $version The install version.
	 */
	protected static $version = '1.2.1';

	/**
	 * Run the install procedure.
	 *
	 * @since 1.1.8
	 */
	public function install() {
		// Set the provider connected option to true.
		update_option( 'sg_ai_studio_gutenberg_actions', true, 'yes' );
	}

}
