<?php
defined( 'ABSPATH' ) || exit;
class CRS_Rewrite {
    public static function init() {
        add_action( 'init', [ __CLASS__, 'add_rewrite_rules' ] );
        add_filter( 'query_vars', [ __CLASS__, 'add_query_vars' ] );
        add_filter( 'template_include', [ __CLASS__, 'load_enquiry_template' ] );
            add_filter( 'template_include', [ __CLASS__, 'load_location_template' ] );

    }
    /**
     * Custom URL:
     * /enquiry/business-slug/
     */
    public static function add_rewrite_rules() {

        // ── 6. ENQUIRY PAGES ─────────────────────────────────────────
        // /enquire/abc-computer-repairs/
        add_rewrite_rule(
            '^enquire/([^/]+)/?$',
            'index.php?crs_enquiry_slug=$matches[1]',
            'top'
        );

        // ── 5. BUSINESS PROFILE + LOCATION ───────────────────────────
        // /business/abc-computer-repairs/melbourne-region/
        add_rewrite_rule(
            '^business/([^/]+)/([^/]+)/?$',
            'index.php?business=$matches[1]&crs_location_ctx=$matches[2]',
            'top'
        );
        // /business/abc-computer-repairs/
        add_rewrite_rule(
            '^business/([^/]+)/?$',
            'index.php?business=$matches[1]',
            'top'
        );

        // ── 4. BRAND + STATE + REGION + SUBURB ───────────────────────
        // /apple-repairs/victoria/melbourne-region/
        add_rewrite_rule(
            '^([a-z0-9-]+-repairs)/([a-z-]+)/([a-z-]+-region)/?$',
            'index.php?device-brand=$matches[1]&au-state=$matches[2]&au-region=$matches[3]',
            'top'
        );
        // /apple-repairs/roxburgh-park-vic-3064/
        add_rewrite_rule(
            '^([a-z0-9-]+-repairs)/([a-z][a-z0-9-]+-vic-[0-9]{4})/?$',
            'index.php?device-brand=$matches[1]&au-suburb=$matches[2]',
            'top'
        );
        // /apple-repairs/victoria/
        add_rewrite_rule(
            '^([a-z0-9-]+-repairs)/([a-z-]+)/?$',
            'index.php?device-brand=$matches[1]&au-state=$matches[2]',
            'top'
        );

        // ── 3. BUSINESS IT + STATE + REGION + SUBURB ─────────────────
        // /managed-it-services/victoria/melbourne-region/
        add_rewrite_rule(
            '^(business-it-support|managed-it-services|microsoft-365-support|email-support|network-support|server-support|remote-it-support)/([a-z-]+)/([a-z-]+-region)/?$',
            'index.php?repair-service=$matches[1]&au-state=$matches[2]&au-region=$matches[3]',
            'top'
        );
        // /business-it-support/melbourne-cbd-vic-3000/
        add_rewrite_rule(
            '^(business-it-support|managed-it-services|microsoft-365-support|email-support|network-support|server-support|remote-it-support)/([a-z][a-z0-9-]+-vic-[0-9]{4})/?$',
            'index.php?repair-service=$matches[1]&au-suburb=$matches[2]',
            'top'
        );
        // /business-it-support/victoria/
        add_rewrite_rule(
            '^(business-it-support|managed-it-services|microsoft-365-support|email-support|network-support|server-support|remote-it-support)/([a-z-]+)/?$',
            'index.php?repair-service=$matches[1]&au-state=$matches[2]',
            'top'
        );

        // ── 2. SERVICE + STATE + REGION + SUBURB ─────────────────────
        // /laptop-repairs/victoria/melbourne-region/
        add_rewrite_rule(
            '^([a-z0-9-]+)/([a-z-]+)/([a-z-]+-region)/?$',
            'index.php?repair-service=$matches[1]&au-state=$matches[2]&au-region=$matches[3]',
            'top'
        );
        // /laptop-repairs/roxburgh-park-vic-3064/
        add_rewrite_rule(
            '^([a-z0-9-]+)/([a-z][a-z0-9-]+-[a-z]{2,3}-[0-9]{4})/?$',
            'index.php?repair-service=$matches[1]&au-suburb=$matches[2]',
            'top'
        );
        // /laptop-repairs/victoria/
        add_rewrite_rule(
            '^([a-z0-9-]+)/([a-z-]+)/?$',
            'index.php?repair-service=$matches[1]&au-state=$matches[2]',
            'top'
        );

        // ── 1. CORE LOCATION — State / Region / Suburb ───────────────
        // /victoria/melbourne-region/
        add_rewrite_rule(
            '^([a-z-]+)/([a-z-]+-region)/?$',
            'index.php?au-state=$matches[1]&au-region=$matches[2]',
            'top'
        );
        // /roxburgh-park-vic-3064/
        add_rewrite_rule(
            '^([a-z][a-z0-9-]+-[a-z]{2,3}-[0-9]{4})/?$',
            'index.php?au-suburb=$matches[1]',
            'top'
        );
        // /victoria/
        add_rewrite_rule(
            '^(victoria|new-south-wales|queensland|western-australia|south-australia|tasmania|australian-capital-territory|northern-territory)/?$',
            'index.php?au-state=$matches[1]',
            'top'
        );
    }
    /**
     * Register custom query var
     */
    public static function add_query_vars( $vars ) {
        $vars[] = 'crs_enquiry_slug';
        $vars[] = 'crs_location_ctx';   // business profile + location context
        $vars[] = 'repair-service';     // service slug
        $vars[] = 'au-state';           // state slug
        $vars[] = 'au-region';          // region slug
        $vars[] = 'au-suburb';          // suburb slug
        $vars[] = 'device-brand';       // brand slug
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

    /**
     * Route query vars → correct taxonomy template
     * Handles: service+state, service+region, service+suburb,
     *          brand+state/region/suburb, location-only pages
     */
    public static function load_location_template( $template ) {

        $service = get_query_var( 'repair-service' );
        $brand   = get_query_var( 'device-brand' );
        $state   = get_query_var( 'au-state' );
        $region  = get_query_var( 'au-region' );
        $suburb  = get_query_var( 'au-suburb' );

        // Nothing CRS-related — skip
        if ( ! $service && ! $brand && ! $state && ! $region && ! $suburb ) {
            return $template;
        }

        // Resolve term objects from slugs
        $state_term  = $state  ? get_term_by( 'slug', $state,  'au-state'       ) : null;
        $region_term = $region ? get_term_by( 'slug', $region, 'au-region'      ) : null;
        $suburb_term = $suburb ? get_term_by( 'slug', $suburb, 'au-suburb'      ) : null;
        $svc_term    = $service? get_term_by( 'slug', $service,'repair-service' ) : null;
        $brand_term  = $brand  ? get_term_by( 'slug', $brand,  'device-brand'   ) : null;

        // 404 if slugs don't resolve
        if ( ( $state   && ! $state_term  ) ||
             ( $region  && ! $region_term ) ||
             ( $suburb  && ! $suburb_term ) ||
             ( $service && ! $svc_term    ) ||
             ( $brand   && ! $brand_term  ) ) {
            return get_404_template();
        }

        global $wp_query;

        // Set the queried object to the most-specific location term
        $location_term = $suburb_term ?: $region_term ?: $state_term;

        if ( $location_term ) {
            $wp_query->queried_object    = $location_term;
            $wp_query->queried_object_id = $location_term->term_id;
            $wp_query->is_tax            = true;
            $wp_query->is_archive        = true;
        }

        // Store service/brand on query for templates to read
        if ( $svc_term  ) set_query_var( 'repair-service', $svc_term->slug  );
        if ( $brand_term) set_query_var( 'device-brand',   $brand_term->slug );

        // Pick template file
        $theme_dir = get_template_directory();

        if ( $brand_term ) {
            // Brand pages reuse taxonomy-device-brand.php
            $tpl = $theme_dir . '/taxonomy-device-brand.php';
        } elseif ( $suburb_term ) {
            $tpl = $theme_dir . '/taxonomy-au-suburb.php';
        } elseif ( $region_term ) {
            $tpl = $theme_dir . '/taxonomy-au-suburb.php'; // handles both region+suburb
        } elseif ( $state_term ) {
            $tpl = $theme_dir . '/taxonomy-au-state.php';
        } else {
            return $template;
        }

        return file_exists( $tpl ) ? $tpl : $template;
    }

} // end class CRS_Rewrite

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