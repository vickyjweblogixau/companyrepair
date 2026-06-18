<?php
/**
 * CRS Theme — index.php
 * Fallback template. Redirects to homepage.
 */
get_header();
?>

<main class="container py-5 text-center">
  <h1><?php esc_html_e( 'Page Not Found', 'crs' ); ?></h1>
  <p><?php esc_html_e( 'The page you are looking for could not be found.', 'crs' ); ?></p>
  <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="btn-crs mt-3">
    <?php esc_html_e( 'Back to Home', 'crs' ); ?>
  </a>
</main>

<?php get_footer(); ?>
