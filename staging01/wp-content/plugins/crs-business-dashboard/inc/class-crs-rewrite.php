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
            '^business/([^/]+)/?$',
            'index.php?crs_enquire=1&crs_business_slug=$matches[1]',
            'top'
        );
    }

    public static function add_query_vars( $vars ) {
        $vars[] = 'crs_enquire';
        $vars[] = 'crs_business_slug';
        return $vars;
    }

    public static function load_enquiry_template() {
        if ( get_query_var( 'crs_enquire' ) ) {
            $template = CRS_PLUGIN_DIR . 'templates/enquiry-page.php';
            if ( file_exists( $template ) ) {
                include $template;
                exit;
            }
        }
    }
}

CRS_Rewrite::init();
add_filter( 'redirect_canonical', function( $redirect_url, $requested_url ) {
    if ( get_query_var( 'crs_enquire' ) ) {
        return false;
    }
    return $redirect_url;
}, 10, 2 );