<?php
/**
 * CRS Business Dashboard – class-crs-dashboard.php
 * @package CRS
 * @author  Priya
 * TODO: Implement this class
 */
defined( 'ABSPATH' ) || exit;

add_action('admin_init', function() {
    if (isset($_GET['fix_sub']) && current_user_can('manage_options')) {
        wp_update_post(['ID' => (int) $_GET['fix_sub'], 'post_status' => 'sub_active']);
        echo 'Fixed'; exit;
    }
});