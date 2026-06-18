<?php
/**
 * CRS Theme — archive-business.php
 * Template: Service Listing (Australia-wide)  →  service.html prototype
 * URL: /services/{service-slug}/
 *
 * Also used by repair-service taxonomy archive.
 */

get_header();

$term        = get_queried_object();
$paged       = max( 1, get_query_var( 'paged' ) );
$page_title  = $term instanceof WP_Term
    ? $term->name . ' ' . __( 'Australia Wide', 'crs' )
    : __( 'Computer Repair Services Australia Wide', 'crs' );
$page_sub    = $term instanceof WP_Term
    ? sprintf( __( 'Find trusted %s specialists across Australia.', 'crs' ), strtolower( $term->name ) )
    : __( 'Find trusted computer repair and IT support specialists across Australia.', 'crs' );

// Build tax_query
$tax_q = $term instanceof WP_Term
    ? [ [ 'taxonomy' => $term->taxonomy, 'field' => 'term_id', 'terms' => $term->term_id ] ]
    : [];

$featured_q = crs_featured_query( $tax_q );
$listing_q  = crs_listing_query( $tax_q, $paged );
?>

<div class="sp-wrap">
<div class="container px-3 py-4">

  <!-- BREADCRUMBS -->
  <?php crs_breadcrumbs( 'sp' ); ?>

  <div class="row g-4 mt-1">

    <!-- ============================================================
         LEFT COLUMN: Header + Search + Featured + Listings
         ============================================================ -->
    <div class="col-lg-9">

      <!-- Page header card -->
      <div class="row align-items-center g-3 mb-3">
        <div class="col-md-8">
          <h1 class="sp-title"><?php echo esc_html( $page_title ); ?></h1>
          <p class="sp-sub"><?php echo esc_html( $page_sub ); ?></p>
          <p class="sp-count">
            <?php echo esc_html( $listing_q->found_posts ); ?>
            <?php esc_html_e( 'businesses found', 'crs' ); ?>
          </p>
        </div>
        <?php
        $header_img = $term instanceof WP_Term
            ? get_term_meta( $term->term_id, 'service_banner', true )
            : '';
        if ( $header_img ) : ?>
          <div class="col-md-4 sp-head">
            <img src="<?php echo esc_url( $header_img ); ?>"
                 class="sp-head-img"
                 alt="<?php echo esc_attr( $page_title ); ?>"
                 loading="lazy">
          </div>
        <?php endif; ?>
      </div>

      <!-- Search / filter bar -->
      <form class="sp-search mb-4" method="GET">
        <div class="sp-search-field">
          <i class="fa-solid fa-magnifying-glass"></i>
          <input type="text" name="q"
                 value="<?php echo esc_attr( get_query_var( 'q', '' ) ); ?>"
                 placeholder="<?php esc_attr_e( 'Refine service…', 'crs' ); ?>">
        </div>
        <div class="sp-search-field">
          <i class="fa-solid fa-location-dot"></i>
          <input type="text" name="location"
                 value="<?php echo esc_attr( get_query_var( 'location', '' ) ); ?>"
                 placeholder="<?php esc_attr_e( 'Suburb or Postcode', 'crs' ); ?>">
        </div>
        <button type="submit" class="sp-search-btn">
          <i class="fa-solid fa-magnifying-glass"></i>
          <?php esc_html_e( 'Search', 'crs' ); ?>
        </button>
      </form>

      <!-- ── Featured cards ─────────────────────────────── -->
      <?php if ( $featured_q->have_posts() ) : ?>
        <h2 class="sp-h"><?php esc_html_e( 'Featured Businesses', 'crs' ); ?></h2>
        <div class="row g-3 mb-4">
          <?php
          while ( $featured_q->have_posts() ) : $featured_q->the_post();
              get_template_part( 'template-parts/listing-card-featured' );
          endwhile;
          wp_reset_postdata();
          ?>
        </div>
      <?php endif; ?>

      <!-- ── Listing rows ───────────────────────────────── -->
      <h2 class="sp-h"><?php esc_html_e( 'All Businesses', 'crs' ); ?></h2>

      <div class="sp-listing">
        <?php
        if ( $listing_q->have_posts() ) :
            while ( $listing_q->have_posts() ) : $listing_q->the_post();
                get_template_part( 'template-parts/listing-card-row' );
            endwhile;
            wp_reset_postdata();
        else : ?>
          <p class="text-center py-5 text-muted-2">
            <?php esc_html_e( 'No businesses found in this category yet. Check back soon.', 'crs' ); ?>
          </p>
        <?php endif; ?>
      </div>

      <!-- Pagination -->
      <?php if ( $listing_q->max_num_pages > 1 ) : ?>
        <div class="sp-pagination">
          <?php
          $big = 999999999;
          echo paginate_links( [
              'base'      => str_replace( $big, '%#%', esc_url( get_pagenum_link( $big ) ) ),
              'format'    => '?paged=%#%',
              'current'   => $paged,
              'total'     => $listing_q->max_num_pages,
              'prev_text' => '<i class="fa-solid fa-arrow-left"></i>',
              'next_text' => '<i class="fa-solid fa-arrow-right"></i>',
          ] );
          ?>
        </div>
      <?php endif; ?>

    </div><!-- /col-lg-9 -->

    <!-- ============================================================
         RIGHT SIDEBAR
         ============================================================ -->
    <div class="col-lg-3">

      <!-- Service banner image -->
      <?php
      $side_banner = CRS_URI . '/assets/images/service-side-banner.png';
      ?>
      <div class="sp-side service-side-banner-img mb-3">
        <img src="<?php echo esc_url( $side_banner ); ?>"
             class="img-fluid w-100"
             alt=""
             loading="lazy">
      </div>

      <!-- Browse by State -->
      <div class="sp-side">
        <h6><?php esc_html_e( 'Browse by State', 'crs' ); ?></h6>
        <?php
        $states = get_terms( [ 'taxonomy' => 'au-state', 'hide_empty' => true ] );
        if ( $states && ! is_wp_error( $states ) ) :
            foreach ( $states as $state ) : ?>
              <a href="<?php echo esc_url( get_term_link( $state ) ); ?>">
                <?php echo esc_html( $state->name ); ?>
              </a>
            <?php endforeach;
        endif;
        ?>
      </div>

      <!-- Browse by Service -->
      <div class="sp-side">
        <h6><?php esc_html_e( 'Other Services', 'crs' ); ?></h6>
        <?php
        $all_services = get_terms( [ 'taxonomy' => 'repair-service', 'hide_empty' => true, 'number' => 8 ] );
        if ( $all_services && ! is_wp_error( $all_services ) ) :
            foreach ( $all_services as $svc ) : ?>
              <a href="<?php echo esc_url( get_term_link( $svc ) ); ?>">
                <?php echo esc_html( $svc->name ); ?>
              </a>
            <?php endforeach;
        endif;
        ?>
        <a href="<?php echo esc_url( home_url( '/services/' ) ); ?>" class="sp-side-all">
          <?php esc_html_e( 'View All Services', 'crs' ); ?>
          <i class="fa-solid fa-arrow-right ms-1"></i>
        </a>
      </div>

      <!-- CTA: List Your Business -->
      <div class="sp-side" style="background:var(--crs-navy);color:#fff;border-color:transparent;">
        <h6 style="color:#fff;"><?php esc_html_e( 'List Your Business', 'crs' ); ?></h6>
        <p style="color:#c5d4ea;font-size:13px;margin:0 0 14px;">
          <?php esc_html_e( 'Reach customers searching for computer repair services in your area.', 'crs' ); ?>
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
