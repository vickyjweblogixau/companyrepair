<!-- ===================== FOOTER ===================== -->
<footer class="crs-footer pt-5 pb-3">
  <div class="container px-3">

    <!-- Top: brand + link columns -->
    <div class="row g-4">

      <!-- Brand -->
      <div class="col-12 col-lg-3">
        <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="crs-logo mb-3">
          <img src="<?php echo esc_url( CRS_URI . '/assets/images/logo.png' ); ?>"
               class="mb-3"
               alt="<?php bloginfo( 'name' ); ?>">
        </a>
        <p class="text-muted-2" style="font-size:13.5px;max-width:300px;">
          <?php esc_html_e( "Australia's trusted directory for computer repair and IT support services. Connect with verified local experts or get remote help fast.", 'crs' ); ?>
        </p>
        <div class="footer-social d-flex gap-2 mt-3">
          <a href="<?php echo esc_url( get_theme_mod( 'crs_facebook', '#' ) ); ?>" aria-label="Facebook">
            <i class="fa-brands fa-facebook-f"></i>
          </a>
          <a href="<?php echo esc_url( get_theme_mod( 'crs_linkedin', '#' ) ); ?>" aria-label="LinkedIn">
            <i class="fa-brands fa-linkedin-in"></i>
          </a>
          <a href="mailto:<?php echo esc_attr( get_theme_mod( 'crs_contact_email', '' ) ); ?>" aria-label="Email">
            <i class="fa-solid fa-envelope"></i>
          </a>
        </div>
      </div>

      <!-- Repair Services -->
      <div class="col-6 col-md-4 col-lg footer-col">
        <h6><?php esc_html_e( 'Repair Services', 'crs' ); ?></h6>
        <?php
        $repair_terms = get_terms( [
            'taxonomy'   => 'repair-service',
            'hide_empty' => true,
            'number'     => 7,
        ] );
        if ( $repair_terms && ! is_wp_error( $repair_terms ) ) :
            foreach ( $repair_terms as $term ) : ?>
                <a href="<?php echo esc_url( get_term_link( $term ) ); ?>">
                  <?php echo esc_html( $term->name ); ?>
                </a>
            <?php endforeach;
        endif; ?>
        <a href="<?php echo esc_url( home_url( '/services/' ) ); ?>">
          <?php esc_html_e( 'View All Services', 'crs' ); ?>
        </a>
      </div>

      <!-- Business IT Services -->
      <div class="col-6 col-md-4 col-lg footer-col">
        <h6><?php esc_html_e( 'Business IT Services', 'crs' ); ?></h6>
        <a href="#"><?php esc_html_e( 'Business IT Support',    'crs' ); ?></a>
        <a href="#"><?php esc_html_e( 'Microsoft 365 Support',  'crs' ); ?></a>
        <a href="#"><?php esc_html_e( 'Email Support',           'crs' ); ?></a>
        <a href="#"><?php esc_html_e( 'Network Support',         'crs' ); ?></a>
        <a href="#"><?php esc_html_e( 'Server Support',          'crs' ); ?></a>
        <a href="#"><?php esc_html_e( 'Managed IT Services',     'crs' ); ?></a>
        <a href="#"><?php esc_html_e( 'Remote IT Support',       'crs' ); ?></a>
      </div>

      <!-- By Brand -->
      <div class="col-6 col-md-4 col-lg footer-col">
        <h6><?php esc_html_e( 'By Brand', 'crs' ); ?></h6>
        <?php
        $brand_terms = get_terms( [ 'taxonomy' => 'device-brand', 'hide_empty' => true, 'number' => 6 ] );
        if ( $brand_terms && ! is_wp_error( $brand_terms ) ) :
            foreach ( $brand_terms as $term ) : ?>
                <a href="<?php echo esc_url( get_term_link( $term ) ); ?>">
                  <?php echo esc_html( $term->name ); ?>
                </a>
            <?php endforeach;
        endif; ?>
        <a href="#"><?php esc_html_e( 'View All Brands', 'crs' ); ?></a>
      </div>

      <!-- By OS -->
      <div class="col-6 col-md-4 col-lg footer-col">
        <h6><?php esc_html_e( 'By Operating System', 'crs' ); ?></h6>
        <?php
        $os_terms = get_terms( [ 'taxonomy' => 'operating-system', 'hide_empty' => true ] );
        if ( $os_terms && ! is_wp_error( $os_terms ) ) :
            foreach ( $os_terms as $term ) : ?>
                <a href="<?php echo esc_url( get_term_link( $term ) ); ?>">
                  <?php echo esc_html( $term->name ); ?>
                </a>
            <?php endforeach;
        endif; ?>
        <a href="#"><?php esc_html_e( 'View All OS', 'crs' ); ?></a>
      </div>

      <!-- Locations -->
      <div class="col-6 col-md-4 col-lg footer-col">
        <h6><?php esc_html_e( 'Locations', 'crs' ); ?></h6>
        <?php
        $state_terms = get_terms( [ 'taxonomy' => 'au-state', 'hide_empty' => true ] );
        if ( $state_terms && ! is_wp_error( $state_terms ) ) :
            foreach ( $state_terms as $term ) : ?>
                <a href="<?php echo esc_url( get_term_link( $term ) ); ?>">
                  <?php echo esc_html( $term->name ); ?>
                </a>
            <?php endforeach;
        endif; ?>
      </div>

    </div><!-- /row -->

    <!-- CTA cards row -->
    <div class="row g-4 mt-2">

      <!-- For Service Providers -->
      <div class="col-lg-5">
        <div class="footer-cta gap-4">
          <div class="footer-cta-icon"><i class="fa-solid fa-store"></i></div>
          <div class="list-carvans-sec">
            <h5><?php esc_html_e( 'For Service Providers', 'crs' ); ?></h5>
            <a class="list-links" href="#"><?php esc_html_e( 'List Your Business', 'crs' ); ?></a>
            <a class="list-links" href="#"><?php esc_html_e( 'Partner With Us',    'crs' ); ?></a>
            <a class="list-links" href="<?php echo esc_url( home_url( '/dashboard/' ) ); ?>">
              <?php esc_html_e( 'Login / Dashboard', 'crs' ); ?>
            </a>
            <p><?php esc_html_e( 'Get more local customers by listing your computer repair or IT support business.', 'crs' ); ?></p>
            <a href="<?php echo esc_url( home_url( '/list-your-business/' ) ); ?>" class="btn-cta">
              <span><i class="fa-solid fa-store me-2"></i><?php esc_html_e( 'Add Your Business', 'crs' ); ?></span>
              <i class="fa-solid fa-arrow-right"></i>
            </a>
          </div>
        </div>
      </div>

      <!-- About -->
      <div class="col-lg-7">
        <div class="footer-cta gap-4">
          <div class="right-box-footer-middle gap-4 flex-grow-1" style="min-width:280px;">
            <div class="footer-cta-icon"><i class="fa-solid fa-circle-info"></i></div>
            <div>
              <h5><?php esc_html_e( 'About ComputerRepairServices.com.au', 'crs' ); ?></h5>
              <p><?php esc_html_e( 'Learn more about our directory, how it works, read guides or get in touch with our team.', 'crs' ); ?></p>
              <a href="#" class="btn-cta">
                <span><?php esc_html_e( 'Learn More', 'crs' ); ?></span>
                <i class="fa-solid fa-arrow-right"></i>
              </a>
            </div>
          </div>
          <div class="footer-about-links">
            <a href="#"><?php esc_html_e( 'About Us',       'crs' ); ?></a>
            <a href="#"><?php esc_html_e( 'How It Works',   'crs' ); ?></a>
            <a href="#"><?php esc_html_e( 'FAQ',            'crs' ); ?></a>
            <a href="#"><?php esc_html_e( 'Blog',           'crs' ); ?></a>
            <a href="#"><?php esc_html_e( 'Articles',       'crs' ); ?></a>
            <a href="#"><?php esc_html_e( 'Contact Us',     'crs' ); ?></a>
            <a href="<?php echo esc_url( home_url( '/list-your-business/' ) ); ?>">
              <?php esc_html_e( 'For Businesses', 'crs' ); ?>
            </a>
          </div>
        </div>
      </div>

    </div><!-- /cta row -->

    <!-- Bottom bar -->
    <div class="footer-bottom d-flex flex-column flex-md-row justify-content-between align-items-center gap-3 text-center">
      <span>&copy; <?php echo date( 'Y' ); ?> ComputerRepairServices.com.au. <?php esc_html_e( 'All Rights Reserved.', 'crs' ); ?></span>
      <span class="d-flex align-items-center gap-3">
        <a href="#"><?php esc_html_e( 'Terms of Use',    'crs' ); ?></a>
        <span class="sep">|</span>
        <a href="#"><?php esc_html_e( 'Privacy Policy',  'crs' ); ?></a>
        <span class="sep">|</span>
        <a href="#"><?php esc_html_e( 'Sitemap',         'crs' ); ?></a>
      </span>
    </div>

  </div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
