<?php
/**
 * CRS Theme — taxonomy-au-state.php
 * Template: State Listing Page  →  state.html prototype
 * URL: /services/{service}/{state}/
 *
 * Shares the sp- CSS class system with archive-business.php.
 * Identical structure; conditional context detection handles state vs region.
 */

get_header();

$state_term  = get_queried_object();
$service_var = get_query_var( 'repair-service', '' );
$service_term = $service_var ? get_term_by( 'slug', $service_var, 'repair-service' ) : null;

$paged = max( 1, get_query_var( 'paged' ) );

$page_title = $service_term
    ? sprintf( '%s %s', $service_term->name, $state_term->name )
    : sprintf( __( 'Computer Repairs %s', 'crs' ), $state_term->name );

$page_sub = sprintf(
    __( 'Find trusted computer repair and IT support businesses in %s.', 'crs' ),
    $state_term->name
);

// Build tax query (state + optional service)
$tax_q = [ [
    'taxonomy' => 'au-state',
    'field'    => 'term_id',
    'terms'    => $state_term->term_id,
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

// Child regions for sidebar
$regions = get_terms( [
    'taxonomy'   => 'au-region',
    'hide_empty' => true,
    'meta_query' => [ [
        'key'   => 'parent_state',
        'value' => $state_term->term_id,
    ] ],
] );
?>

<div class="sp-wrap">
<div class="container px-3 py-4">

  <?php crs_breadcrumbs( 'sp' ); ?>

  <div class="row g-4 mt-1">

    <!-- MAIN COLUMN -->
    <div class="col-lg-9">

      <!-- Page header -->
      <div class="mb-3">
        <h1 class="sp-title"><?php echo esc_html( $page_title ); ?></h1>
        <p class="sp-sub"><?php echo esc_html( $page_sub ); ?></p>
        <p class="sp-count">
          <?php echo esc_html( $listing_q->found_posts ); ?>
          <?php esc_html_e( 'businesses found', 'crs' ); ?>
        </p>
      </div>

      <!-- Search bar -->
      <form class="sp-search mb-4" method="GET">
        <div class="sp-search-field">
          <i class="fa-solid fa-magnifying-glass"></i>
          <input type="text" name="q"
                 placeholder="<?php esc_attr_e( 'Refine service…', 'crs' ); ?>">
        </div>
        <div class="sp-search-field">
          <i class="fa-solid fa-location-dot"></i>
          <input type="text" name="suburb"
                 placeholder="<?php esc_attr_e( 'Suburb or Postcode', 'crs' ); ?>">
        </div>
        <button type="submit" class="sp-search-btn">
          <?php esc_html_e( 'Search', 'crs' ); ?>
        </button>
      </form>

      <!-- Service header image (if set on taxonomy) -->
      <?php
      $header_img = get_term_meta( $state_term->term_id, 'state_banner', true );
      if ( $header_img ) : ?>
        <div class="sp-head mb-3">
          <img src="<?php echo esc_url( $header_img ); ?>"
               class="sp-head-img"
               alt="<?php echo esc_attr( $page_title ); ?>"
               loading="lazy">
        </div>
      <?php endif; ?>

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
            <?php esc_html_e( 'No businesses listed in this state yet.', 'crs' ); ?>
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

    </div><!-- /col-lg-9 -->

    <!-- SIDEBAR -->
    <div class="col-lg-3">

      <!-- Browse regions in this state -->
      <?php if ( $regions && ! is_wp_error( $regions ) ) : ?>
        <div class="sp-side">
          <h6>
            <?php printf( esc_html__( 'Regions in %s', 'crs' ), esc_html( $state_term->name ) ); ?>
          </h6>
          <?php foreach ( $regions as $region ) : ?>
            <a href="<?php echo esc_url( get_term_link( $region ) ); ?>">
              <?php echo esc_html( $region->name ); ?>
              <span class="text-muted-2 ms-1" style="font-size:11px;">
                (<?php echo esc_html( crs_business_count_by_term( 'au-region', $region->term_id ) ); ?>)
              </span>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <!-- All States -->
      <div class="sp-side">
        <h6><?php esc_html_e( 'All States', 'crs' ); ?></h6>
        <?php
        $all_states = get_terms( [ 'taxonomy' => 'au-state', 'hide_empty' => true ] );
        if ( $all_states && ! is_wp_error( $all_states ) ) :
            foreach ( $all_states as $st ) : ?>
              <a href="<?php echo esc_url( get_term_link( $st ) ); ?>"
                 <?php echo ( $st->term_id === $state_term->term_id ) ? 'style="font-weight:700;"' : ''; ?>>
                <?php echo esc_html( $st->name ); ?>
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
              esc_html( $state_term->name )
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
