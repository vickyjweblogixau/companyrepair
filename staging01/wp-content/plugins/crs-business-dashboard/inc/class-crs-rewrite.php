<?php
defined( 'ABSPATH' ) || exit;
class CRS_Rewrite {
    public static function init() {
        add_action( 'init', [ __CLASS__, 'add_rewrite_rules' ] );
        add_filter( 'query_vars', [ __CLASS__, 'add_query_vars' ] );
        add_filter( 'template_include', [ __CLASS__, 'load_enquiry_template' ] );
    }
    /**
     * Custom URL:
     * /enquiry/business-slug/
     */
    public static function add_rewrite_rules() {
        add_rewrite_rule(
            '^enquiry/([^/]+)/?$',
            'index.php?crs_enquiry_slug=$matches[1]',
            'top'
        );
    }
    /**
     * Register custom query var
     */
    public static function add_query_vars( $vars ) {
        $vars[] = 'crs_enquiry_slug';
        return $vars;
    }
    /**
     * Load enquiry template
     */
    public static function load_enquiry_template( $template ) {
        $slug = get_query_var( 'crs_enquiry_slug' );
        if ( empty( $slug ) ) {
            return $template;
        }
        $business = get_page_by_path( $slug, OBJECT, 'business' );
        if ( ! $business ) {
            return get_404_template();
        }
        global $post, $wp_query;
        $post = $business;
        $wp_query->post               = $business;
        $wp_query->posts              = [ $business ];
        $wp_query->post_count         = 1;
        $wp_query->queried_object     = $business;
        $wp_query->queried_object_id  = $business->ID;
        $wp_query->is_page            = false;
        $wp_query->is_single          = true;
        $wp_query->is_singular        = true;
        $wp_query->is_404             = false;
        setup_postdata( $post );
        $enquiry_template = CRS_PLUGIN_DIR . 'templates/enquiry-page.php';
        if ( file_exists( $enquiry_template ) ) {
            return $enquiry_template;
        }
        return $template;
    }
}
CRS_Rewrite::init();
/**
 * Disable canonical redirect only for enquiry URLs
 */
add_filter( 'redirect_canonical', function( $redirect_url ) {
    if ( get_query_var( 'crs_enquiry_slug' ) ) {
        return false;
    }
    return $redirect_url;
} );