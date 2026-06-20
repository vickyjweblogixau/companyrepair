<?php
/**
 * CRS Business Dashboard – class-crs-ajax.php
 *
 * AJAX handlers: postcode → suburb cascade lookup, analytics, etc.
 *
 * @package CRS
 * @author  Priya
 */
defined( 'ABSPATH' ) || exit;

/* ======================================================================
   Postcode → Suburb cascade
   GET  admin-ajax.php?action=crs_get_suburbs_by_postcode&postcode=3000
   Returns JSON: { success: true, data: { suburbs: [ { id, name, slug, region, state, state_abbr } ] } }
   ==================================================================== */
add_action( 'wp_ajax_nopriv_crs_get_suburbs_by_postcode', 'crs_ajax_get_suburbs_by_postcode' );
add_action( 'wp_ajax_crs_get_suburbs_by_postcode',        'crs_ajax_get_suburbs_by_postcode' );

function crs_ajax_get_suburbs_by_postcode() {

    $postcode = sanitize_text_field( $_GET['postcode'] ?? '' );

    if ( ! preg_match( '/^\d{4}$/', $postcode ) ) {
        wp_send_json_error( [ 'message' => 'Invalid postcode.' ] );
    }

    // Query au-suburb terms whose au_suburb_postcode meta matches
    $terms = get_terms( [
        'taxonomy'   => 'au-suburb',
        'hide_empty' => false,
        'orderby'    => 'name',
        'order'      => 'ASC',
        'meta_query' => [
            [
                'key'     => 'au_suburb_postcode',
                'value'   => $postcode,
                'compare' => '=',
            ],
        ],
    ] );

    if ( is_wp_error( $terms ) || empty( $terms ) ) {
        wp_send_json_success( [ 'suburbs' => [] ] );
    }

    $results = [];

    foreach ( $terms as $term ) {

        $state_id  = (int) get_term_meta( $term->term_id, 'au_suburb_state',  true );
        $region_id = (int) get_term_meta( $term->term_id, 'au_suburb_region', true );

        $state_name  = '';
        $state_abbr  = '';
        $region_name = '';

        if ( $state_id ) {
            $state_term = get_term( $state_id, 'au-state' );
            if ( $state_term && ! is_wp_error( $state_term ) ) {
                $state_name = $state_term->name;
                $state_abbr = (string) get_term_meta( $state_id, 'au_state_abbreviation', true );
            }
        }

        if ( $region_id ) {
            $region_term = get_term( $region_id, 'au-region' );
            if ( $region_term && ! is_wp_error( $region_term ) ) {
                $region_name = $region_term->name;
            }
        }

        $results[] = [
            'id'         => $term->term_id,
            'name'       => $term->name,
            'slug'       => $term->slug,
            'region'     => $region_name,
            'state'      => $state_name,
            'state_abbr' => $state_abbr,
        ];
    }

    wp_send_json_success( [ 'suburbs' => $results ] );
}
