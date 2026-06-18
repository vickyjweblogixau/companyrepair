<?php
/**
 * CRS Theme Setup
 * Registers WordPress theme support and nav menus.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'after_setup_theme', 'crs_theme_setup' );

function crs_theme_setup() {

    // Make theme available for translation
    load_theme_textdomain( 'crs', CRS_DIR . '/languages' );

    // Title tag managed by WP
    add_theme_support( 'title-tag' );

    // Post thumbnails
    add_theme_support( 'post-thumbnails' );

    // HTML5 markup
    add_theme_support( 'html5', [
        'search-form', 'comment-form', 'comment-list',
        'gallery', 'caption', 'style', 'script',
    ] );

    // Register nav menus
    register_nav_menus( [
        'primary' => __( 'Primary Menu', 'crs' ),
        'footer'  => __( 'Footer Menu',  'crs' ),
    ] );

    // Image sizes
    add_image_size( 'crs-thumbnail',  400, 300, true );  // listing card thumb
    add_image_size( 'crs-gallery',    800, 600, true );  // profile gallery
    add_image_size( 'crs-blog-card',  640, 400, true );  // article cards
}
