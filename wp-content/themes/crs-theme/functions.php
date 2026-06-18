<?php
/**
 * CRS Theme — functions.php
 * ComputerRepairServices.com.au
 *
 * Loads all inc/ files and registers theme features.
 */

defined( 'ABSPATH' ) || exit;

define( 'CRS_VERSION', '1.0.0' );
define( 'CRS_DIR',     get_template_directory() );
define( 'CRS_URI',     get_template_directory_uri() );

/* --------------------------------------------------------------------------
 * Load includes
 * ---------------------------------------------------------------------- */
require_once CRS_DIR . '/inc/theme-setup.php';
require_once CRS_DIR . '/inc/enqueue.php';
require_once CRS_DIR . '/inc/template-helpers.php';
require_once CRS_DIR . '/inc/breadcrumbs.php';
