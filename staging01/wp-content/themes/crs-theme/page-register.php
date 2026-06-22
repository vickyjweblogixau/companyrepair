<?php
/**
 * CRS Theme — page-register.php
 * Template: Business Registration / List Your Business
 * URL: /list-your-business/
 *
 * Delegates to the [crs_register] shortcode provided by
 * the crs-business-dashboard plugin (onboarding wizard).
 *
 * Template Name: CRS Register Business
 */

// Redirect already-registered business owners to dashboard
if ( is_user_logged_in() ) {
    $user = wp_get_current_user();
    if ( in_array( 'business_owner', (array) $user->roles, true ) ) {
        wp_redirect( home_url( '/dashboard/' ) );
        exit;
    }
}

get_header();
?>

<div style="background:var(--crs-section-bg);min-height:80vh;">
<div class="container px-3 py-5">

  <div class="row justify-content-center">
    <div class="col-lg-8">

      <!-- Page header -->
      <div class="text-center mb-5">
        <h1 style="font-weight:800;font-size:clamp(26px,3vw,36px);">
          <?php esc_html_e( 'List Your Business', 'crs' ); ?>
        </h1>
        <p style="color:var(--crs-muted);font-size:16px;max-width:520px;margin:0 auto;">
          <?php esc_html_e( 'Get found by thousands of Australians searching for computer repair and IT support services.', 'crs' ); ?>
        </p>
      </div>

      <!-- Plugin wizard shortcode -->
      <div class="bp-card">
        <?php
        if ( shortcode_exists( 'crs_register' ) ) :
            echo do_shortcode( '[crs_register]' );
        else : ?>
          <p class="text-muted-2 text-center py-4">
            <?php esc_html_e( 'Registration form is loading. Please ensure the CRS Business Dashboard plugin is active.', 'crs' ); ?>
          </p>
        <?php endif; ?>
      </div>

    </div>
  </div>

</div>
</div>

<?php get_footer(); ?>
