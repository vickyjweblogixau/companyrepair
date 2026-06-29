<?php
/**
 * CRS Theme — Template Helper Functions
 *
 * Reusable functions called by template files to render
 * dynamic data from the crs-business-dashboard plugin.
 */

defined( 'ABSPATH' ) || exit;


/* ==========================================================================
   BUSINESS META HELPERS
   ========================================================================== */

/**
 * Get a single meta value for a business CPT post.
 *
 * @param  string $key   Meta key (without leading underscore — added automatically).
 * @param  int    $post_id  Defaults to current post.
 * @return mixed
 */
function crs_get_meta( $key, $post_id = null ) {
    $post_id = $post_id ?: get_the_ID();
    return get_post_meta( $post_id, '_' . $key, true );
}

/**
 * Render the star rating HTML (filled + half + empty).
 *
 * @param  float $avg   Rating average (0–5).
 * @param  bool  $echo  Whether to echo or return.
 */
function crs_render_stars( $avg, $echo = true ) {
    $avg    = floatval( $avg );
    $full   = floor( $avg );
    $half   = ( $avg - $full ) >= 0.5 ? 1 : 0;
    $empty  = 5 - $full - $half;
    $html   = '';

    for ( $i = 0; $i < $full;  $i++ ) { $html .= '<i class="fa-solid fa-star"></i>'; }
    if ( $half )                       { $html .= '<i class="fa-solid fa-star-half-stroke"></i>'; }
    for ( $i = 0; $i < $empty; $i++ ) { $html .= '<i class="fa-regular fa-star"></i>'; }

    if ( $echo ) { echo $html; } else { return $html; }
}

/**
 * Render the subscription tier badge.
 *
 * @param  string $tier  basic | standard | featured | premium
 */
function crs_tier_badge( $tier ) {
    $labels = [
        'premium'  => [ 'Premium',  'bg-warning text-dark' ],
        'featured' => [ 'Featured', 'bg-primary text-white' ],
        'standard' => [ 'Standard', 'bg-secondary text-white' ],
        'basic'    => [ 'Basic',    'bg-light text-muted' ],
    ];
    $t = $labels[ $tier ] ?? $labels['basic'];
    return '<span class="badge ' . esc_attr( $t[1] ) . '">' . esc_html( $t[0] ) . '</span>';
}

/**
 * Get the count of active businesses for a taxonomy term.
 *
 * @param  string $taxonomy  Taxonomy slug.
 * @param  int    $term_id
 * @return int
 */
function crs_business_count_by_term( $taxonomy, $term_id ) {
    $cache_key = 'crs_bc_' . $taxonomy . '_' . $term_id;
    $cached    = get_transient( $cache_key );
    if ( false !== $cached ) return (int) $cached;

    $q = new WP_Query( [
        'post_type'      => 'business',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'tax_query'      => [ [ 'taxonomy' => $taxonomy, 'field' => 'term_id', 'terms' => $term_id ] ],
        'meta_query'     => [ [ 'key' => '_subscription_status', 'value' => 'active' ] ],
    ] );

    set_transient( $cache_key, $q->found_posts, HOUR_IN_SECONDS );
    return $q->found_posts;
}

/**
 * Get businesses for a listing page with tier-based ordering.
 *
 * Order: premium → featured → standard → basic, then by review_avg DESC.
 *
 * @param  array $tax_query  WP tax_query array.
 * @param  int   $paged
 * @param  int   $per_page
 * @return WP_Query
 */
function crs_listing_query( $tax_query, $paged = 1, $per_page = 10 ) {
    $cache_key = 'crs_lq_' . md5( serialize( $tax_query ) . $paged . $per_page );
    $cached    = get_transient( $cache_key );
    if ( false !== $cached ) return $cached;

    $q = new WP_Query( [
        'post_type'      => 'business',
        'post_status'    => 'publish',
        'posts_per_page' => $per_page,
        'paged'          => $paged,
        'tax_query'      => $tax_query,
        'meta_query'     => [
            'relation' => 'AND',
            [ 'key' => '_subscription_status', 'value' => 'active' ],
            'tier_clause'   => [ 'key' => '_subscription_tier', 'type' => 'CHAR' ],
            'rating_clause' => [ 'key' => '_review_avg', 'type' => 'DECIMAL(3,1)', 'compare' => 'EXISTS' ],
        ],
        'orderby' => [ 'tier_clause' => 'DESC', 'rating_clause' => 'DESC' ],
    ] );

    set_transient( $cache_key, $q, 30 * MINUTE_IN_SECONDS );
    return $q;
}

function crs_featured_query( $tax_query ) {
    $cache_key = 'crs_fq_' . md5( serialize( $tax_query ) );
    $cached    = get_transient( $cache_key );
    if ( false !== $cached ) return $cached;

    // Replace rand() with tier+rating order for cache-friendliness
    $q = new WP_Query( [
        'post_type'      => 'business',
        'post_status'    => 'publish',
        'posts_per_page' => 4,
        'tax_query'      => $tax_query,
        'meta_query'     => [
            'relation' => 'AND',
            [ 'key' => '_subscription_status', 'value' => 'active' ],
            'tier_clause' => [
                'key'     => '_subscription_tier',
                'value'   => [ 'featured', 'premium' ],
                'compare' => 'IN',
                'type'    => 'CHAR',
            ],
            'rating_clause' => [ 'key' => '_review_avg', 'type' => 'DECIMAL(3,1)', 'compare' => 'EXISTS' ],
        ],
        'orderby' => [ 'tier_clause' => 'DESC', 'rating_clause' => 'DESC' ],
    ] );

    set_transient( $cache_key, $q, 30 * MINUTE_IN_SECONDS );
    return $q;
}


/* ==========================================================================
   DYNAMIC HOMEPAGE COUNTS (cached via WP transients)
   ========================================================================== */

/**
 * Total active business count — shown in trust bar.
 */
function crs_active_business_count() {
    $key = 'crs_active_count';
    $val = get_transient( $key );
    if ( false === $val ) {
        $q   = new WP_Query( [
            'post_type'      => 'business',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_query'     => [ [ 'key' => '_subscription_status', 'value' => 'active' ] ],
        ] );
        $val = $q->found_posts;
        set_transient( $key, $val, HOUR_IN_SECONDS );
    }
    return $val;
}

/**
 * Total active repair-service taxonomy term count.
 */
function crs_service_count() {
    $key = 'crs_service_count';
    $val = get_transient( $key );
    if ( false === $val ) {
        $terms = get_terms( [ 'taxonomy' => 'repair-service', 'hide_empty' => true ] );
        $val   = is_wp_error( $terms ) ? 0 : count( $terms );
        set_transient( $key, $val, HOUR_IN_SECONDS );
    }
    return $val;
}

function crs_get_terms_cached( $taxonomy, $args = [] ) {
    $cache_key = 'crs_terms_' . $taxonomy . '_' . md5( serialize( $args ) );
    $cached    = get_transient( $cache_key );
    if ( false !== $cached ) return $cached;

    $terms = get_terms( array_merge( [ 'taxonomy' => $taxonomy, 'hide_empty' => true ], $args ) );
    set_transient( $cache_key, $terms, 2 * HOUR_IN_SECONDS );
    return $terms;
}
// In crs-business-dashboard plugin (e.g. class-crs-setup.php or functions.php)
add_action( 'save_post_business', 'crs_flush_listing_cache' );
add_action( 'updated_post_meta',  'crs_flush_listing_cache_on_meta', 10, 4 );

function crs_flush_listing_cache() {
    global $wpdb;
    // Delete all crs_ transients
    $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_crs_%' OR option_name LIKE '_transient_timeout_crs_%'" );
}

function crs_flush_listing_cache_on_meta( $meta_id, $post_id, $meta_key, $meta_value ) {
    if ( in_array( $meta_key, [ '_subscription_status', '_subscription_tier', '_review_avg' ], true ) ) {
        crs_flush_listing_cache();
    }
}