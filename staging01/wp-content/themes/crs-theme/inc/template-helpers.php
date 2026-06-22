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
    $q = new WP_Query( [
        'post_type'      => 'business',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'tax_query'      => [ [
            'taxonomy' => $taxonomy,
            'field'    => 'term_id',
            'terms'    => $term_id,
        ] ],
        'meta_query'     => [ [
            'key'   => '_subscription_status',
            'value' => 'active',
        ] ],
    ] );
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
    return new WP_Query( [
        'post_type'      => 'business',
        'post_status'    => 'publish',
        'posts_per_page' => $per_page,
        'paged'          => $paged,
        'tax_query'      => $tax_query,
        'meta_query'     => [
            'relation' => 'AND',
            [
                'key'   => '_subscription_status',
                'value' => 'active',
            ],
            'tier_clause' => [
                'key'  => '_subscription_tier',
                'type' => 'CHAR',
            ],
            'rating_clause' => [
                'key'     => '_review_avg',
                'type'    => 'DECIMAL(3,1)',
                'compare' => 'EXISTS',
            ],
        ],
        'orderby' => [
            'tier_clause'   => 'DESC',
            'rating_clause' => 'DESC',
        ],
    ] );
}

/**
 * Get featured businesses for sp-feat cards.
 * Returns up to 4 premium/featured businesses for a given tax context.
 *
 * @param  array $tax_query
 * @return WP_Query
 */
function crs_featured_query( $tax_query ) {
    $tq = $tax_query;
    return new WP_Query( [
        'post_type'      => 'business',
        'post_status'    => 'publish',
        'posts_per_page' => 4,
        'tax_query'      => $tq,
        'meta_query'     => [
            'relation' => 'AND',
            [
                'key'   => '_subscription_status',
                'value' => 'active',
            ],
            [
                'key'     => '_subscription_tier',
                'value'   => [ 'featured', 'premium' ],
                'compare' => 'IN',
            ],
        ],
        'orderby'        => 'rand',
    ] );
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
