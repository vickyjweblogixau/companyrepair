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
function create_subscriber_role() {
    remove_role( 'subscriber' );
    $subscriber = get_role( 'subscriber' );
    $caps = $subscriber ? $subscriber->capabilities : [];
    $post_caps = [
        'read'                   => true,
        'edit_posts'             => true,  // Edit own posts
        'edit_published_posts'   => true,  // Edit own published posts
        'delete_posts'           => true,  // Delete own posts
        'delete_published_posts' => true,  // Delete own published posts
        'publish_posts'          => true,  // Publish own posts
        'upload_files'           => true,  // Upload media
    ];

    $capabilities = array_merge( $caps, $post_caps );

    add_role(
        'subscriber',          // Role slug
        'Subscriber',          // Display name
        $capabilities
    );
}
add_action( 'init', 'create_subscriber_role' );
function remove_subscriber_editor_role() {
    remove_role( 'subscriber' );
}
register_deactivation_hook( __FILE__, 'remove_subscriber_editor_role' );