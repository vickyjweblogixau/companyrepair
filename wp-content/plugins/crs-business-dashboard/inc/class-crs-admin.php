<?php
/**
 * CRS Business Dashboard – class-crs-admin.php
 *
 * WordPress automatically builds the "Businesses" top-level menu and all
 * taxonomy submenus from the CPT registration in class-crs-setup.php.
 * No manual add_menu_page / remove_menu_page needed — doing so breaks the
 * native inline-expand behaviour.
 *
 * Add custom admin pages / columns / notices here as needed.
 *
 * @package CRS
 * @author  Priya
 */
defined( 'ABSPATH' ) || exit;

class CRS_Admin {

    public static function init() {
        // Placeholder – add admin hooks here as the project grows.
    }

}

CRS_Admin::init();
