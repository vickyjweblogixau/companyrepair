<?php
/**
 * CRS Theme — page-dashboard.php
 * Template: Subscriber Dashboard  (new — not in HTML prototypes)
 * URL: /dashboard/
 *
 * Delegates rendering entirely to the [crs_dashboard] shortcode
 * registered by the crs-business-dashboard plugin.
 *
 * Template Name: CRS Dashboard
 */

// Redirect non-logged-in users
if ( ! is_user_logged_in() ) {
    wp_redirect( wp_login_url( get_permalink() ) );
    exit;
}

// Only business_owner role should access this page
$user = wp_get_current_user();
if ( ! in_array( 'business_owner', (array) $user->roles, true ) ) {
    wp_redirect( home_url( '/' ) );
    exit;
}

//get_header();
?>

<main class="container px-3 py-4" style="max-width:1140px;">
  <?php
  /* 
  // The plugin provides this shortcode. Falls back to a message if plugin not active.
 if ( shortcode_exists( 'crs_dashboard' ) ) :
      echo do_shortcode( '[crs_dashboard]' );
  else : ?>
    <div class="alert alert-warning">
      <strong><?php esc_html_e( 'Dashboard not available.', 'crs' ); ?></strong>
      <?php esc_html_e( 'Please ensure the CRS Business Dashboard plugin is installed and activated.', 'crs' ); ?>
    </div>
  <?php endif;  */ ?>
    <?php echo do_shortcode( '[business_owner_dashboard]' ); ?>

</main>

<?php //get_footer(); ?>
