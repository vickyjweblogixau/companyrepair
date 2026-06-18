<?php
/**
 * Plugin Name:  CRS Business Dashboard
 * Plugin URI:   https://computerrepairservices.com.au
 * Description:  Registers the Business CPT, all taxonomies, ACF field groups,
 *               user roles, enquiry system and subscriber dashboard for
 *               ComputerRepairServices.com.au
 * Version:      1.0.0
 * Author:       Priya
 * Author URI:   https://computerrepairservices.com.au
 * Text Domain:  crs
 * Requires WP:  6.4
 * Requires PHP: 8.0
 */

defined( 'ABSPATH' ) || exit;

/* --------------------------------------------------------------------------
 * Constants
 * ---------------------------------------------------------------------- */
define( 'CRS_PLUGIN_VERSION', '1.0.0' );
define( 'CRS_PLUGIN_FILE',    __FILE__ );
define( 'CRS_PLUGIN_DIR',     plugin_dir_path( __FILE__ ) );
define( 'CRS_PLUGIN_URL',     plugin_dir_url( __FILE__ ) );

/* --------------------------------------------------------------------------
 * Activation / Deactivation
 * ---------------------------------------------------------------------- */
register_activation_hook( __FILE__, 'crs_activate' );
register_deactivation_hook( __FILE__, 'crs_deactivate' );

function crs_activate() {
    // Register CPT + taxonomies first so rewrite rules exist
    require_once CRS_PLUGIN_DIR . 'inc/class-crs-setup.php';
    CRS_Setup::register_cpt();
    CRS_Setup::register_taxonomies();
    CRS_Setup::create_roles();
    CRS_Setup::create_enquiries_table();
    CRS_Setup::insert_default_terms();

    // Flush rewrite rules AFTER CPT/taxonomy registration
    flush_rewrite_rules();

    // Store activation version for upgrade checks
    update_option( 'crs_plugin_version', CRS_PLUGIN_VERSION );
}

function crs_deactivate() {
    flush_rewrite_rules();
}

/* --------------------------------------------------------------------------
 * Load plugin includes
 * ---------------------------------------------------------------------- */
add_action( 'plugins_loaded', 'crs_load_plugin' );

function crs_load_plugin() {
    $includes = [
        'inc/class-crs-setup.php',        // CPT, taxonomies, roles, DB
        'inc/class-crs-acf-fields.php',   // ACF field groups (local)
        'inc/class-crs-subscriptions.php',// Subscription CRUD + cron
        'inc/class-crs-enquiries.php',    // Enquiry form + notifications
        'inc/class-crs-dashboard.php',    // [crs_dashboard] shortcode
        'inc/class-crs-wizard.php',       // [crs_register] onboarding wizard
        'inc/class-crs-admin.php',        // WP Admin menu pages
        'inc/class-crs-ajax.php',         // AJAX handlers
        'inc/class-crs-rewrite.php',      // Custom rewrite rules
    ];

    foreach ( $includes as $file ) {
        $path = CRS_PLUGIN_DIR . $file;
        if ( file_exists( $path ) ) {
            require_once $path;
        }
    }
}
