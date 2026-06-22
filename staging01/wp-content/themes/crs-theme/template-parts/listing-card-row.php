<?php
/**
 * CRS Template Part — Listing Row Card  (sp-row)
 *
 * Used in service/state/region listing pages for standard list results.
 * Expects: WP loop is active.
 */

$post_id  = get_the_ID();
$logo_id  = crs_get_meta( 'business_logo',    $post_id );
$addr     = crs_get_meta( 'business_address', $post_id );
$phone    = crs_get_meta( 'business_phone',   $post_id );
$avg      = crs_get_meta( 'review_avg',       $post_id );
$count    = crs_get_meta( 'review_count',     $post_id );
$onsite   = crs_get_meta( 'business_onsite',  $post_id );
$remote   = crs_get_meta( 'business_remote',  $post_id );
$pickup   = crs_get_meta( 'business_pickup',  $post_id );
$tier     = crs_get_meta( 'subscription_tier', $post_id );

$thumb_url = $logo_id
    ? wp_get_attachment_image_url( $logo_id, 'crs-thumbnail' )
    : CRS_URI . '/assets/images/logo-placeholder.png';
?>

<div class="sp-row">

  <!-- Thumbnail -->
  <div class="sp-thumb">
    <img src="<?php echo esc_url( $thumb_url ); ?>"
         alt="<?php the_title_attribute(); ?>"
         loading="lazy">
  </div>

  <!-- Info -->
  <div class="sp-row-info">

    <div class="d-flex align-items-center gap-2">
      <div class="sp-row-name"><?php the_title(); ?></div>
      <?php if ( in_array( $tier, [ 'featured', 'premium' ], true ) ) : ?>
        <?php echo crs_tier_badge( $tier ); ?>
      <?php endif; ?>
    </div>

    <?php if ( $addr ) : ?>
      <div class="sp-row-addr">
        <i class="fa-solid fa-location-dot me-1"></i><?php echo esc_html( $addr ); ?>
      </div>
    <?php endif; ?>

    <div class="sp-badges">
      <?php if ( $onsite ) : ?>
        <span class="sp-badge"><i class="fa-solid fa-house"></i><?php esc_html_e( 'Onsite Service', 'crs' ); ?></span>
      <?php endif; ?>
      <?php if ( $remote ) : ?>
        <span class="sp-badge"><i class="fa-solid fa-wifi"></i><?php esc_html_e( 'Remote Support', 'crs' ); ?></span>
      <?php endif; ?>
      <?php if ( $pickup ) : ?>
        <span class="sp-badge"><i class="fa-solid fa-bag-shopping"></i><?php esc_html_e( 'Pickup Available', 'crs' ); ?></span>
      <?php endif; ?>
    </div>

  </div><!-- /sp-row-info -->

  <!-- Rating + Actions -->
  <div class="sp-row-actions">

    <?php if ( $avg ) : ?>
      <div class="sp-row-rating">
        <div>
          <div class="num"><?php echo esc_html( number_format( $avg, 1 ) ); ?>
            <i class="fa-solid fa-star" style="color:var(--crs-star);"></i>
          </div>
          <div class="cnt"><?php echo esc_html( $count ); ?> <?php esc_html_e( 'reviews', 'crs' ); ?></div>
        </div>
      </div>
    <?php endif; ?>

    <div class="sp-actions-btns d-flex gap-2">
      <a href="<?php the_permalink(); ?>" class="sp-btn-profile">
        <?php esc_html_e( 'View Profile', 'crs' ); ?>
      </a>
      <?php if ( in_array( $tier, [ 'standard', 'featured', 'premium' ], true ) ) : ?>
        <a href="<?php the_permalink(); ?>#enquiry" class="sp-btn-enquiry">
          <?php esc_html_e( 'Enquiry Now', 'crs' ); ?>
        </a>
      <?php endif; ?>
    </div>

  </div><!-- /sp-row-actions -->

</div><!-- /sp-row -->
