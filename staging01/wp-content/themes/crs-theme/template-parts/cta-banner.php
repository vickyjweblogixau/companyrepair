<?php
/**
 * CRS Template Part — CTA Banner
 *
 * Usage:  get_template_part( 'template-parts/cta-banner' );
 */
?>
<section class="crs-cta-banner">
  <div class="container">
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
      <div>
        <h3><?php esc_html_e( 'Need Help Finding the Right Repair Service?', 'crs' ); ?></h3>
        <p><?php esc_html_e( 'Our directory makes it easy to find local experts you can trust.', 'crs' ); ?></p>
      </div>
      <a href="<?php echo esc_url( home_url( '/post-a-request/' ) ); ?>" class="btn-cta-request">
        <?php esc_html_e( 'Post a Request', 'crs' ); ?>
      </a>
    </div>
  </div>
</section>
