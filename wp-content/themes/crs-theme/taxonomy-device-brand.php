<?php
/**
 * CRS Theme — taxonomy-device-brand.php
 * Template: Brand Taxonomy Archive
 * URL: /brand/{brand-slug}/  e.g. /brand/apple/
 *
 * Reuses sp- listing classes — same structure as archive-business.php.
 */

get_header();

$term   = get_queried_object();
$paged  = max( 1, get_query_var( 'paged' ) );

$page_title = sprintf( __( '%s Repairs Australia Wide', 'crs' ), $term->name );
$page_sub   = sprintf( __( 'Find certified %s repair specialists across Australia.', 'crs' ), $term->name );

$tax_q = [ [
    'taxonomy' => 'device-brand',
    'field'    => 'term_id',
    'terms'    => $term->term_id,
] ];

$featured_q = crs_featured_query( $tax_q );
$listing_q  = crs_listing_query( $tax_q, $paged );
?>

<div class="sp-wrap">
<div class="container px-3 py-4">

  <?php crs_breadcrumbs( 'sp' ); ?>

  <div class="row g-4 mt-1">

    <div class="col-lg-9">

      <h1 class="sp-title"><?php echo esc_html( $page_title ); ?></h1>
      <p class="sp-sub"><?php echo esc_html( $page_sub ); ?></p>
      <p class="sp-count">
        <?php echo esc_html( $listing_q->found_posts ); ?>
        <?php esc_html_e( 'businesses found', 'crs' ); ?>
      </p>

      <?php if ( $featured_q->have_posts() ) : ?>
        <h2 class="sp-h mt-3"><?php esc_html_e( 'Featured', 'crs' ); ?></h2>
        <div class="row g-3 mb-4">
          <?php while ( $featured_q->have_posts() ) : $featured_q->the_post();
              get_template_part( 'template-parts/listing-card-featured' );
          endwhile; wp_reset_postdata(); ?>
        </div>
      <?php endif; ?>

      <h2 class="sp-h"><?php esc_html_e( 'All Businesses', 'crs' ); ?></h2>
      <div class="sp-listing">
        <?php if ( $listing_q->have_posts() ) :
            while ( $listing_q->have_posts() ) : $listing_q->the_post();
                get_template_part( 'template-parts/listing-card-row' );
            endwhile; wp_reset_postdata();
        else : ?>
          <p class="text-center py-5 text-muted-2">
            <?php printf( esc_html__( 'No %s repair businesses listed yet.', 'crs' ), esc_html( $term->name ) ); ?>
          </p>
        <?php endif; ?>
      </div>

      <?php if ( $listing_q->max_num_pages > 1 ) : ?>
        <div class="sp-pagination">
          <?php $big = 999999999; echo paginate_links( [
              'base'    => str_replace( $big, '%#%', esc_url( get_pagenum_link( $big ) ) ),
              'format'  => '?paged=%#%',
              'current' => $paged,
              'total'   => $listing_q->max_num_pages,
          ] ); ?>
        </div>
      <?php endif; ?>

    </div>

    <div class="col-lg-3">
      <!-- All Brands sidebar -->
      <div class="sp-side">
        <h6><?php esc_html_e( 'All Brands', 'crs' ); ?></h6>
        <?php
        $all_brands = get_terms( [ 'taxonomy' => 'device-brand', 'hide_empty' => true ] );
        foreach ( $all_brands as $b ) : ?>
          <a href="<?php echo esc_url( get_term_link( $b ) ); ?>"
             <?php echo ( $b->term_id === $term->term_id ) ? 'style="font-weight:700;"' : ''; ?>>
            <?php echo esc_html( $b->name ); ?>
          </a>
        <?php endforeach; ?>
      </div>
    </div>

  </div>
</div>
</div>

<?php get_footer(); ?>
