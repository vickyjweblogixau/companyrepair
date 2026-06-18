<?php
/**
 * CRS Business Dashboard – class-crs-admin.php
 *
 * Fixes the WordPress admin sidebar so "Businesses" stays expanded and
 * the correct taxonomy submenu item is highlighted when navigating to
 * any of the 6 taxonomy admin pages.
 *
 * @package CRS
 * @author  Priya
 */
defined( 'ABSPATH' ) || exit;

class CRS_Admin {

    private static $taxonomies = [
        'repair-service',
        'au-state',
        'au-region',
        'au-suburb',
        'device-brand',
        'operating-system',
    ];

    public static function init() {
        // Set globals early (before menu HTML is built).
        add_action( 'current_screen', [ __CLASS__, 'fix_menu_globals' ] );

        // Filter approach as a second layer.
        add_filter( 'parent_file',  [ __CLASS__, 'fix_parent_file'  ] );
        add_filter( 'submenu_file', [ __CLASS__, 'fix_submenu_file' ] );
    }

    /* -----------------------------------------------------------------
     * Helpers
     * ----------------------------------------------------------------- */

    private static function get_current_taxonomy() {
        $tax = isset( $_GET['taxonomy'] ) ? sanitize_key( $_GET['taxonomy'] ) : '';
        return in_array( $tax, self::$taxonomies, true ) ? $tax : '';
    }

    /* -----------------------------------------------------------------
     * 1. Set $parent_file / $submenu_file globals via current_screen
     *    (fires before menu-header.php reads them)
     * ----------------------------------------------------------------- */
    public static function fix_menu_globals( $screen ) {
        if ( 'edit-tags' !== $screen->base ) {
            return;
        }

        $tax = self::get_current_taxonomy();
        if ( ! $tax ) {
            return;
        }

        // These globals are what menu-header.php reads when it builds the sidebar.
        $GLOBALS['parent_file']  = 'edit.php?post_type=business';
        $GLOBALS['submenu_file'] = 'edit-tags.php?taxonomy=' . $tax . '&post_type=business';
    }

    /* -----------------------------------------------------------------
     * 2. parent_file filter (belt-and-suspenders)
     * ----------------------------------------------------------------- */
    public static function fix_parent_file( $parent_file ) {
        $tax = self::get_current_taxonomy();
        return $tax ? 'edit.php?post_type=business' : $parent_file;
    }

    /* -----------------------------------------------------------------
     * 3. submenu_file filter (belt-and-suspenders)
     * ----------------------------------------------------------------- */
    public static function fix_submenu_file( $submenu_file ) {
        $tax = self::get_current_taxonomy();
        return $tax
            ? 'edit-tags.php?taxonomy=' . $tax . '&post_type=business'
            : $submenu_file;
    }

} // end class CRS_Admin

CRS_Admin::init();
