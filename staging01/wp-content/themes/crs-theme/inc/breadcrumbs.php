<?php
/**
 * CRS Theme — Breadcrumb Helper
 *
 * Renders contextual breadcrumbs for all CRS template types.
 * Pattern:  Home > Services > {Service} > {State} > {Region/Suburb}
 */

defined( 'ABSPATH' ) || exit;

/**
 * Output breadcrumbs for the current page.
 *
 * @param  string $prefix_class  CSS class prefix (sp- or bp-).
 */
function crs_breadcrumbs( $prefix_class = 'sp' ) {
    $class = esc_attr( $prefix_class ) . '-breadcrumb';
    $sep   = '<i class="fa-solid fa-chevron-right"></i>';
    $home  = '<a href="' . esc_url( home_url( '/' ) ) . '">' . __( 'Home', 'crs' ) . '</a>';

    echo '<nav class="' . $class . '">' . $home;

    if ( is_singular( 'business' ) ) {
        // Home > Services > {Service} > Business Name
        $services = get_the_terms( get_the_ID(), 'repair-service' );
        if ( $services && ! is_wp_error( $services ) ) {
            $svc = $services[0];
            echo $sep . '<a href="' . esc_url( get_term_link( $svc ) ) . '">'
               . esc_html( $svc->name ) . '</a>';
        } else {
            echo $sep . '<a href="' . esc_url( home_url( '/services/' ) ) . '">'
               . __( 'Services', 'crs' ) . '</a>';
        }
        echo $sep . '<span>' . get_the_title() . '</span>';

    } elseif ( is_tax( 'au-suburb' ) || is_tax( 'au-region' ) ) {
        $term    = get_queried_object();
        $service = get_query_var( 'repair-service' );
        echo $sep . '<a href="' . esc_url( home_url( '/services/' ) ) . '">'
           . __( 'Services', 'crs' ) . '</a>';
        if ( $service ) {
            $svc_term = get_term_by( 'slug', $service, 'repair-service' );
            if ( $svc_term ) {
                echo $sep . '<a href="' . esc_url( get_term_link( $svc_term ) ) . '">'
                   . esc_html( $svc_term->name ) . '</a>';
            }
        }
        // State parent
        if ( $term->parent ) {
            $parent = get_term( $term->parent, $term->taxonomy );
            echo $sep . '<a href="' . esc_url( get_term_link( $parent ) ) . '">'
               . esc_html( $parent->name ) . '</a>';
        }
        echo $sep . '<span>' . esc_html( $term->name ) . '</span>';

    } elseif ( is_tax( 'au-state' ) ) {
        $term    = get_queried_object();
        $service = get_query_var( 'repair-service' );
        echo $sep . '<a href="' . esc_url( home_url( '/services/' ) ) . '">'
           . __( 'Services', 'crs' ) . '</a>';
        if ( $service ) {
            $svc_term = get_term_by( 'slug', $service, 'repair-service' );
            if ( $svc_term ) {
                echo $sep . '<a href="' . esc_url( get_term_link( $svc_term ) ) . '">'
                   . esc_html( $svc_term->name ) . '</a>';
            }
        }
        echo $sep . '<span>' . esc_html( $term->name ) . '</span>';

    } elseif ( is_post_type_archive( 'business' ) || is_tax( 'repair-service' ) ) {
        $term = get_queried_object();
        echo $sep . '<a href="' . esc_url( home_url( '/services/' ) ) . '">'
           . __( 'Services', 'crs' ) . '</a>';
        if ( $term && isset( $term->name ) ) {
            echo $sep . '<span>' . esc_html( $term->name ) . '</span>';
        }

    } elseif ( is_tax( 'device-brand' ) ) {
        $term = get_queried_object();
        echo $sep . '<span>' . esc_html( $term->name ) . ' ' . __( 'Repairs', 'crs' ) . '</span>';

    } elseif ( is_tax( 'operating-system' ) ) {
        $term = get_queried_object();
        echo $sep . '<span>' . esc_html( $term->name ) . ' ' . __( 'Support', 'crs' ) . '</span>';
    }

    echo '</nav>';
}
