<?php
/**
 * CRS Theme — taxonomy-au-suburb.php
 * Template: Region / Suburb Listing Page  →  region.html prototype
 * URL: /services/{service}/{state}/{region-or-suburb}/
 *
 * Handles both au-region (L2) and au-suburb (L3) taxonomy pages.
 * A single template with conditional context detection (per spec §4).
 */

get_header();

$term    = get_queried_object();
$is_suburb = ( $term->taxonomy === 'au-suburb' );

$service_var  = get_query_var( 'repair-service', '' );
$service_term = $service_var ? get_term_by( 'slug', $service_var, 'repair-service' ) : null;
$paged        = max( 1, get_query_var( 'paged' ) );

// State context — walk up taxonomy hierarchy
$state_term = null;
if ( $is_suburb && $term->parent ) {
    $region_term = get_term( $term->parent, 'au-suburb' );
    if ( $region_term && ! is_wp_error( $region_term ) && $region_term->parent ) {
        $state_term = get_term( $region_term->parent, 'au-region' );
    }
} elseif ( ! $is_suburb && $term->parent ) {
    $state_term = get_term( $term->parent, 'au-region' );
}

$location_label = $state_term && ! is_wp_error( $state_term )
    ? $term->name . ' ' . $state_term->name
    : $term->name;

$page_title = $service_term
    ? sprintf( '%s %s', $service_term->name, $location_label )
    : sprintf( __( 'Computer Repairs %s', 'crs' ), $location_label );

$page_sub = sprintf(
    __( 'Find trusted computer repair and IT support businesses in %s.', 'crs' ),
    $location_label
);

// Tax query
$tax_q = [ [
    'taxonomy' => $term->taxonomy,
    'field'    => 'term_id',
    'terms'    => $term->term_id,
] ];
if ( $service_term ) {
    $tax_q[] = [
        'taxonomy' => 'repair-service',
        'field'    => 'term_id',
        'terms'    => $service_term->term_id,
    ];
}

$featured_q = crs_featured_query( $tax_q );
$listing_q  = crs_listing_query( $tax_q, $paged );

// Sibling suburbs for sidebar drill-down
$sibling_suburbs = $term->parent ? get_terms( [
    'taxonomy'   => $term->taxonomy,
    'parent'     => $term->parent,
    'hide_empty' => true,
    'number'     => 15,
] ) : [];
?>

<div class="sp-wrap">
<div class="container px-3 py-4">

  <?php crs_breadcrumbs( 'sp' ); ?>

  <div class="row g-4 mt-1">

    <!-- MAIN COLUMN -->
    <div class="col-lg-9">

      <div class="mb-3">
        <h1 class="sp-title"><?php echo esc_html( $page_title ); ?></h1>
        <p class="sp-sub"><?php echo esc_html( $page_sub ); ?></p>
        <p class="sp-count">
          <?php echo esc_html( $listing_q->found_posts ); ?>
          <?php esc_html_e( 'businesses found', 'crs' ); ?>
        </p>
      </div>

      <!-- Search bar with suburb AJAX -->
      <form class="sp-search mb-4" method="GET" id="crs-suburb-search">
        <div class="sp-search-field">
          <i class="fa-solid fa-magnifying-glass"></i>
          <input type="text" name="q"
                 placeholder="<?php esc_attr_e( 'What do you need help with?', 'crs' ); ?>">
        </div>
        <div class="sp-search-field">
          <i class="fa-solid fa-location-dot"></i>
          <input type="text" name="suburb"
                 id="crs-suburb-input"
                 placeholder="<?php esc_attr_e( 'Change suburb or postcode…', 'crs' ); ?>">
        </div>
        <button type="submit" class="sp-search-btn">
          <?php esc_html_e( 'Search', 'crs' ); ?>
        </button>
      </form>

      <!-- Featured cards -->
      <?php if ( $featured_q->have_posts() ) : ?>
        <h2 class="sp-h"><?php esc_html_e( 'Featured Businesses', 'crs' ); ?></h2>
        <div class="row g-3 mb-4">
          <?php while ( $featured_q->have_posts() ) : $featured_q->the_post();
              get_template_part( 'template-parts/listing-card-featured' );
          endwhile; wp_reset_postdata(); ?>
        </div>
      <?php endif; ?>

      <!-- Listing rows -->
      <h2 class="sp-h"><?php esc_html_e( 'All Businesses', 'crs' ); ?></h2>
      <div class="sp-listing">
        <?php if ( $listing_q->have_posts() ) :
            while ( $listing_q->have_posts() ) : $listing_q->the_post();
                get_template_part( 'template-parts/listing-card-row' );
            endwhile; wp_reset_postdata();
        else : ?>
          <p class="text-center py-5 text-muted-2">
            <?php printf(
                esc_html__( 'No businesses listed in %s yet. Try broadening your search.', 'crs' ),
                esc_html( $term->name )
            ); ?>
          </p>
        <?php endif; ?>
      </div>

      <!-- Pagination -->
      <?php if ( $listing_q->max_num_pages > 1 ) : ?>
        <div class="sp-pagination">
          <?php
          $big = 999999999;
          echo paginate_links( [
              'base'    => str_replace( $big, '%#%', esc_url( get_pagenum_link( $big ) ) ),
              'format'  => '?paged=%#%',
              'current' => $paged,
              'total'   => $listing_q->max_num_pages,
          ] );
          ?>
        </div>
      <?php endif; ?>

    </div><!-- /main -->

    <!-- SIDEBAR -->
    <div class="col-lg-3">

      <!-- Nearby suburbs / sibling regions -->
      <?php if ( $sibling_suburbs && ! is_wp_error( $sibling_suburbs ) ) : ?>
        <div class="sp-side">
          <h6><?php esc_html_e( 'Nearby Areas', 'crs' ); ?></h6>
          <?php foreach ( $sibling_suburbs as $sib ) :
              if ( $sib->term_id === $term->term_id ) continue; ?>
            <a href="<?php echo esc_url( get_term_link( $sib ) ); ?>">
              <?php echo esc_html( $sib->name ); ?>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <!-- Other services in this area -->
      <div class="sp-side">
        <h6><?php esc_html_e( 'Other Services in this Area', 'crs' ); ?></h6>
        <?php
        $all_services = get_terms( [ 'taxonomy' => 'repair-service', 'hide_empty' => true, 'number' => 7 ] );
        if ( $all_services && ! is_wp_error( $all_services ) ) :
            foreach ( $all_services as $svc ) : ?>
              <a href="<?php echo esc_url( get_term_link( $svc ) ); ?>">
                <?php echo esc_html( $svc->name ); ?>
                <?php if ( $state_term && ! is_wp_error( $state_term ) ) : ?>
                  <?php echo esc_html( ' ' . $term->name ); ?>
                <?php endif; ?>
              </a>
            <?php endforeach;
        endif;
        ?>
      </div>

      <!-- CTA -->
      <div class="sp-side" style="background:var(--crs-navy);color:#fff;border-color:transparent;">
        <h6 style="color:#fff;"><?php esc_html_e( 'List Your Business', 'crs' ); ?></h6>
        <p style="color:#c5d4ea;font-size:13px;margin:0 0 14px;">
          <?php printf(
              esc_html__( 'Get found by customers searching in %s.', 'crs' ),
              esc_html( $term->name )
          ); ?>
        </p>
        <a href="<?php echo esc_url( home_url( '/list-your-business/' ) ); ?>"
           class="btn-crs w-100 justify-content-center">
          <?php esc_html_e( 'Add Your Business', 'crs' ); ?>
        </a>
      </div>

    </div><!-- /sidebar -->

  </div><!-- /row -->
</div><!-- /container -->
</div><!-- /sp-wrap -->

<?php get_footer(); ?>
