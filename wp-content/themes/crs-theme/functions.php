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
// New Role
add_action( 'after_switch_theme', 'create_business_owner_role' );

function create_business_owner_role() {
    if ( get_role( 'business_owner' ) ) {
        return; // Already exists, skip
    }

    $capabilities = [
        'read'                   => true,
        'edit_posts'             => true,
        'edit_published_posts'   => true,
        'delete_posts'           => true,
        'delete_published_posts' => true,
        'publish_posts'          => true,
        'upload_files'           => true,
    ];

    add_role(
        'business_owner',
        'Business Owner',
        $capabilities
    );
}
add_action( 'switch_theme', function() {
    remove_role( 'business_owner' );
});