<?php
defined( 'ABSPATH' ) || exit;

class CRS_Rewrite {

    public static function init() {
        add_action( 'init', [ __CLASS__, 'add_rewrite_rules' ] );
        add_filter( 'query_vars', [ __CLASS__, 'add_query_vars' ] );
        add_action( 'template_redirect', [ __CLASS__, 'load_enquiry_template' ] );
    }

    public static function add_rewrite_rules() {
        add_rewrite_rule(
            '^enquir[ey]/([^/]+)/?$',
            'index.php?crs_enquiry_slug=$matches[1]',
            'top'
        );
    }

    public static function add_query_vars( $vars ) {
        $vars[] = 'crs_enquiry_slug';
        return $vars;
    }

    public static function load_enquiry_template() {
        $slug = get_query_var( 'crs_enquiry_slug' );
        if ( ! $slug ) return;

        $business = get_page_by_path( $slug, OBJECT, 'business' );

        if ( ! $business ) {
            global $wp_query;
            $wp_query->set_404();
            status_header( 404 );
            get_template_part( '404' );
            exit;
        }

        $template = CRS_PLUGIN_DIR . 'templates/enquiry-page.php';

        if ( file_exists( $template ) ) {
            global $post;
            $post = $business;
            setup_postdata( $post );
            set_query_var( 'crs_enquiry_slug_business', $business );
            include $template;
            exit;
        }
    }
}

CRS_Rewrite::init();

// Prevent WordPress canonical redirect from hijacking our URL
add_filter( 'redirect_canonical', function( $redirect_url, $requested_url ) {
    if ( get_query_var( 'crs_enquiry_slug' ) ) {
        return false;
    }
    return $redirect_url;
}, 10, 2 );