<?php
/**
 * CRS Theme — page.php
 * Template for all standard WordPress pages.
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<main class="container px-3 py-4">
  <?php
  while ( have_posts() ) :
    the_post();
    the_content();
  endwhile;
  ?>
</main>

<?php get_footer(); ?>
