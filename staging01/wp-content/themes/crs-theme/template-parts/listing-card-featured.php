<?php
/**
 * CRS Template Part — Featured Listing Card  (sp-feat)
 *
 * Used in service/state/region listing pages for tier=featured|premium businesses.
 * Expects: WP loop is active.
 */

$post_id   = get_the_ID();
$logo_id   = crs_get_meta( 'business_logo',  $post_id );
$avg       = crs_get_meta( 'review_avg',     $post_id );
$count     = crs_get_meta( 'review_count',   $post_id );
$location  = crs_get_meta( 'business_address', $post_id );
$logo_url  = $logo_id
    ? wp_get_attachment_image_url( $logo_id, 'thumbnail' )
    : CRS_URI . '/assets/images/logo-placeholder.png';
?>

<div class="col-6 col-md-3">
  <a href="<?php the_permalink(); ?>" class="sp-feat">

    <div class="sp-feat-logo">
      <img src="<?php echo esc_url( $logo_url ); ?>"
           alt="<?php the_title_attribute(); ?>"
           loading="lazy">
    </div>

    <div class="sp-feat-name"><?php the_title(); ?></div>

    <?php if ( $avg ) : ?>
      <div class="sp-stars">
        <?php crs_render_stars( $avg ); ?>
        <span><?php echo esc_html( number_format( $avg, 1 ) ); ?>
          (<?php echo esc_html( $count ); ?>)
        </span>
      </div>
    <?php endif; ?>

    <?php if ( $location ) : ?>
      <div class="sp-feat-loc">
        <i class="fa-solid fa-location-dot me-1"></i>
        <?php echo esc_html( $location ); ?>
      </div>
    <?php endif; ?>

  </a>
</div>
