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
// ── File upload size fixes ──────────────────────────────────────
// Raise PHP limits via ini (works on most shared hosts)
@ini_set( 'upload_max_filesize', '10M' );
@ini_set( 'post_max_size',       '12M' );

// Raise WordPress core upload cap
add_filter( 'upload_size_limit', function( $size ) {
    return 10 * 1024 * 1024;
});

// Raise CF7-specific file size validation
add_filter( 'wpcf7_upload_file_size_limit', function( $bytes ) {
    return 10 * 1024 * 1024;
});

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
    // Register CPT + taxonomies first so rewrite rules + CPTs exist
    require_once CRS_PLUGIN_DIR . 'inc/class-crs-setup.php';
    require_once CRS_PLUGIN_DIR . 'inc/class-crs-migrations.php';

    CRS_Setup::register_cpt();
    CRS_Setup::register_taxonomies();
    CRS_Setup::register_subscription_plan_cpt();
    CRS_Setup::register_subscription_cpts();
    CRS_Setup::create_roles();
    CRS_Setup::create_enquiries_table();
    CRS_Setup::insert_default_terms();
    update_option( 'crs_repair_service_parents_v1', true );

    // All one-time migrations — runs everything in class-crs-migrations.php
    CRS_Migrations::run_all();

    // Ensure admin caps are set on activation
    crs_ensure_admin_caps();

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

// One-time migration: update au-state slugs to full names
/* add_action( 'admin_init', function () {
    if ( ! get_option( 'crs_state_slugs_v2' ) && class_exists( 'CRS_Setup' ) ) {
        CRS_Setup::migrate_state_slugs();
    }
} ); */

// One-time migration: seed repair-service taxonomy with parent categories
/* 
add_action( 'admin_init', function () {
    if ( ! get_option( 'crs_repair_service_parents_v1' ) && class_exists( 'CRS_Setup' ) ) {
        CRS_Setup::insert_default_terms();
        update_option( 'crs_repair_service_parents_v1', true );
    }
} );
*/
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
        'inc/class-crs-email.php',      // Custom rewrite rules
        'inc/class-crs-image-handler.php',  // Enquiry image handler
        'inc/class-crs-enquiry-form.php',  // Enquiry image handler
         'inc/class-crs-cache-manager.php', // Cache manager
        'inc/class-crs-migrations.php', // Migrations

    ];

    foreach ( $includes as $file ) {
        $path = CRS_PLUGIN_DIR . $file;
        if ( file_exists( $path ) ) {
            require_once $path;
        }
    }

    // Hook CPT + taxonomy registration onto every page load.
    // (activation hook alone is not enough — WP requires re-registration each request)
    CRS_Setup::init();
}

/* --------------------------------------------------------------------------
 * Ensure administrator always has the custom business capabilities.
 * Runs once ever (on activation). Gated by option so admin_init is free.
 * ---------------------------------------------------------------------- */
add_action( 'admin_init', 'crs_ensure_admin_caps' );

function crs_ensure_admin_caps() {
    if ( get_option( 'crs_admin_caps_v1' ) ) {
        return; // already set — zero work on every admin request
    }

    $admin = get_role( 'administrator' );
    if ( ! $admin ) {
        return;
    }

    $caps = [
        'read_business', 'edit_business', 'edit_businesses',
        'edit_others_businesses', 'edit_published_businesses',
        'publish_businesses', 'delete_business', 'delete_businesses',
        'delete_others_businesses', 'delete_published_businesses',
        'read_private_businesses', 'crs_dashboard_access', 'crs_admin_access',
    ];

    foreach ( $caps as $cap ) {
        if ( ! $admin->has_cap( $cap ) ) {
            $admin->add_cap( $cap );
        }
    }

    update_option( 'crs_admin_caps_v1', true );
}
add_action('wp_enqueue_scripts', function () {
    if (get_query_var('crs_enquiry_slug')) {

        wp_enqueue_style(
            'crs-enquiry-page',
            CRS_PLUGIN_URL . 'assets/css/business-enquiry.css',
            [],
            CRS_PLUGIN_VERSION
        );

        wp_enqueue_script(
            'crs-enquiry-page',
            CRS_PLUGIN_URL . 'assets/js/business-enquiry.js',
            [],
            CRS_PLUGIN_VERSION,
            true // Load in footer
        );
    }
});
/* --------------------------------------------------------------------------
 * CF7 — Enquiry photo upload: raise size limit + make field optional
 * ---------------------------------------------------------------------- */
// Raise CF7 internal file size limit to match server capacity
add_filter( 'wpcf7_upload_file_size_limit', function () {
    return 256 * 1024 * 1024; // 256 MB — matches server upload_max_filesize
} );

// Make photos field optional (no error when no file uploaded)
add_filter( 'wpcf7_validate_file*', function ( $result, $tag ) {
    if ( $tag->name === 'photos' && empty( $_FILES['photos']['name'][0] ) ) {
        return $result; // skip validation — field is optional
    }
    return $result;
}, 20, 2 );
function wpcf7_upload_file_size_limit() {
    $max_file_size = wp_max_upload_size(); // WordPress cap
    return apply_filters( 'wpcf7_upload_file_size_limit', $max_file_size );
}
