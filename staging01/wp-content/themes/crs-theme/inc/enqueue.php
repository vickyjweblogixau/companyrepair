<?php
/**
 * CRS Theme — Enqueue Scripts & Styles
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_enqueue_scripts', 'crs_enqueue_assets' );

function crs_enqueue_assets() {

    // ── Google Fonts: Poppins ──────────────────────────────────────────
    wp_enqueue_style(
        'crs-fonts',
        'https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap',
        [],
        null
    );

    // ── Bootstrap 5 ───────────────────────────────────────────────────
    wp_enqueue_style(
        'bootstrap',
        'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css',
        [],
        '5.3.3'
    );

    // ── Font Awesome 6 ────────────────────────────────────────────────
    wp_enqueue_style(
        'font-awesome',
        'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css',
        [],
        '6.5.2'
    );

    // ── Swiper (homepage blog carousel) ───────────────────────────────
    wp_enqueue_style(
        'swiper',
        'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css',
        [],
        '11'
    );

    // ── CRS Theme stylesheet ───────────────────────────────────────────
    wp_enqueue_style(
        'crs-theme',
        get_stylesheet_uri(),
        [ 'bootstrap', 'font-awesome' ],
        CRS_VERSION
    );

    // ── Bootstrap JS bundle ───────────────────────────────────────────
    wp_enqueue_script(
        'bootstrap-js',
        'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js',
        [],
        '5.3.3',
        true
    );

    // ── Swiper JS ─────────────────────────────────────────────────────
    wp_enqueue_script(
        'swiper-js',
        'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js',
        [],
        '11',
        true
    );

    // ── CRS Theme JS ──────────────────────────────────────────────────
    wp_enqueue_script(
        'crs-theme-js',
        CRS_URI . '/assets/js/theme.js',
        [ 'swiper-js' ],
        CRS_VERSION,
        true
    );

    // Pass AJAX URL to JS
    wp_localize_script( 'crs-theme-js', 'crsAjax', [
        'ajaxurl' => admin_url( 'admin-ajax.php' ),
        'nonce'   => wp_create_nonce( 'crs_nonce' ),
    ] );
}
