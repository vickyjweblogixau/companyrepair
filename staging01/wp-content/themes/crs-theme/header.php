<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
  <meta charset="<?php bloginfo( 'charset' ); ?>">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<!-- ===================== TOPBAR ===================== -->
<header class="crs-topbar py-2">
  <div class="container">
    <div class="row align-items-center g-3">

      <!-- Logo -->
      <div class="col-lg-4 col-6">
        <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="crs-logo">
          <?php
          $logo_id = get_theme_mod( 'custom_logo' );
          if ( $logo_id ) :
              $logo_url = wp_get_attachment_image_url( $logo_id, 'full' );
              ?>
              <img src="<?php echo esc_url( $logo_url ); ?>"
                   alt="<?php bloginfo( 'name' ); ?>"
                   style="height:46px;width:auto;" />
          <?php else : ?>
              <img src="<?php echo esc_url( CRS_URI . '/assets/images/logo.png' ); ?>"
                   alt="<?php bloginfo( 'name' ); ?>"
                   style="height:46px;width:auto;" />
          <?php endif; ?>
        </a>
      </div>

      <!-- Marquee tagline -->
      <div class="col-lg-8 col-6">
        <div class="crs-marquee">
          <div class="crs-marquee-track">
            <span class="crs-tagline">
              <?php echo esc_html( get_theme_mod( 'crs_tagline', 'Connecting Australians With Trusted Computer Repair &amp; IT Support Providers' ) ); ?>
            </span>
            <span class="crs-tagline" aria-hidden="true">
              <?php echo esc_html( get_theme_mod( 'crs_tagline', 'Connecting Australians With Trusted Computer Repair &amp; IT Support Providers' ) ); ?>
            </span>
          </div>
        </div>
      </div>

    </div>
  </div>
</header>
