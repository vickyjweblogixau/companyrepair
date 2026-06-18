<?php
/**
 * CRS Theme — single-business.php
 * Template: Business Profile Page  →  single-page.html prototype
 * URL: /business/{business-slug}/
 */

get_header();

if ( ! have_posts() ) {
    get_footer();
    exit;
}

the_post();

$post_id  = get_the_ID();

// Meta fields
$tagline   = crs_get_meta( 'business_tagline',  $post_id );
$about     = crs_get_meta( 'business_about',    $post_id );
$phone     = crs_get_meta( 'business_phone',    $post_id );
$email     = crs_get_meta( 'business_email',    $post_id );
$website   = crs_get_meta( 'business_website',  $post_id );
$address   = crs_get_meta( 'business_address',  $post_id );
$logo_id   = crs_get_meta( 'business_logo',     $post_id );
$gallery   = crs_get_meta( 'business_gallery',  $post_id );  // array of image IDs
$hours     = crs_get_meta( 'business_hours',    $post_id );  // JSON
$onsite    = crs_get_meta( 'business_onsite',   $post_id );
$remote    = crs_get_meta( 'business_remote',   $post_id );
$pickup    = crs_get_meta( 'business_pickup',   $post_id );
$same_day  = crs_get_meta( 'business_same_day', $post_id );
$years     = crs_get_meta( 'business_years',    $post_id );
$verified  = crs_get_meta( 'business_verified', $post_id );
$top_rated = crs_get_meta( 'business_top_rated',$post_id );
$avg       = crs_get_meta( 'review_avg',        $post_id );
$count     = crs_get_meta( 'review_count',      $post_id );
$tier      = crs_get_meta( 'subscription_tier', $post_id );

$logo_url  = $logo_id
    ? wp_get_attachment_image_url( $logo_id, 'crs-thumbnail' )
    : CRS_URI . '/assets/images/logo-placeholder.png';

$hours_arr = $hours ? json_decode( $hours, true ) : [];

// Taxonomies
$services = get_the_terms( $post_id, 'repair-service' );
$brands   = get_the_terms( $post_id, 'device-brand' );
$os_list  = get_the_terms( $post_id, 'operating-system' );
$gallery  = is_array( $gallery ) ? $gallery : [];
?>

<div class="bp-wrap">
<div class="container px-3">

  <!-- BREADCRUMBS -->
  <div class="pt-3">
    <?php crs_breadcrumbs( 'bp' ); ?>
  </div>

  <div class="row g-4 mt-0">

    <!-- ============================================================
         MAIN COLUMN
         ============================================================ -->
    <div class="col-lg-8">

      <!-- ── Business Header Card ─────────────────────────── -->
      <div class="bp-card bp-card-1">

        <div class="d-flex gap-4 align-items-start flex-wrap">

          <!-- Logo -->
          <div class="bp-logo">
            <img src="<?php echo esc_url( $logo_url ); ?>"
                 alt="<?php the_title_attribute(); ?>"
                 loading="eager">
          </div>

          <!-- Name + rating + trust -->
          <div class="flex-grow-1">

            <h1 class="bp-name"><?php the_title(); ?></h1>

            <?php if ( $avg ) : ?>
              <div class="bp-rating">
                <span class="stars"><?php crs_render_stars( $avg ); ?></span>
                <span class="score"><?php echo esc_html( number_format( $avg, 1 ) ); ?></span>
                <span class="reviews">
                  (<?php echo esc_html( $count ); ?> <?php esc_html_e( 'reviews', 'crs' ); ?>)
                </span>
              </div>
            <?php endif; ?>

            <div class="bp-trust">
              <?php if ( $verified ) : ?>
                <span><i class="fa-solid fa-circle-check"></i><?php esc_html_e( 'Verified Business', 'crs' ); ?></span>
              <?php endif; ?>
              <?php if ( $top_rated ) : ?>
                <span><i class="fa-solid fa-award"></i><?php esc_html_e( 'Top Rated', 'crs' ); ?></span>
              <?php endif; ?>
              <?php if ( $years ) : ?>
                <span><i class="fa-solid fa-clock"></i>
                  <?php printf( esc_html__( '%d+ Years in Business', 'crs' ), intval( $years ) ); ?>
                </span>
              <?php endif; ?>
              <?php if ( $onsite ) : ?>
                <span><i class="fa-solid fa-house"></i><?php esc_html_e( 'Onsite Service', 'crs' ); ?></span>
              <?php endif; ?>
              <?php if ( $remote ) : ?>
                <span><i class="fa-solid fa-wifi"></i><?php esc_html_e( 'Remote Support', 'crs' ); ?></span>
              <?php endif; ?>
            </div>

            <!-- Action buttons -->
            <div class="bp-actions">
              <?php if ( $phone ) : ?>
                <a href="tel:<?php echo esc_attr( preg_replace( '/\D/', '', $phone ) ); ?>"
                   class="bp-btn bp-btn-primary">
                  <i class="fa-solid fa-phone"></i><?php echo esc_html( $phone ); ?>
                </a>
              <?php endif; ?>
              <?php if ( in_array( $tier, [ 'standard', 'featured', 'premium' ], true ) ) : ?>
                <a href="#enquiry" class="bp-btn bp-btn-outline">
                  <i class="fa-solid fa-envelope"></i><?php esc_html_e( 'Enquiry Now', 'crs' ); ?>
                </a>
              <?php endif; ?>
              <?php if ( $website ) : ?>
                <a href="<?php echo esc_url( $website ); ?>"
                   class="bp-btn bp-btn-ghost"
                   target="_blank" rel="noopener noreferrer">
                  <i class="fa-solid fa-globe"></i><?php esc_html_e( 'Website', 'crs' ); ?>
                </a>
              <?php endif; ?>
              <?php if ( $address ) : ?>
                <a href="https://maps.google.com/?q=<?php echo urlencode( $address ); ?>"
                   class="bp-btn bp-btn-ghost"
                   target="_blank" rel="noopener noreferrer">
                  <i class="fa-solid fa-location-dot"></i><?php esc_html_e( 'Get Directions', 'crs' ); ?>
                </a>
              <?php endif; ?>
            </div>

          </div>
        </div>

        <!-- Gallery (featured/premium tier: 4 photos) -->
        <?php if ( $gallery && in_array( $tier, [ 'featured', 'premium' ], true ) ) : ?>
          <div class="bp-gallery mt-4">
            <?php foreach ( array_slice( $gallery, 0, 4 ) as $img_id ) :
                $img_url = wp_get_attachment_image_url( $img_id, 'crs-gallery' );
                if ( ! $img_url ) continue;
                ?>
                <div class="bp-shot">
                  <img src="<?php echo esc_url( $img_url ); ?>"
                       alt=""
                       loading="lazy">
                </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

      </div><!-- /header card -->

      <!-- ── About ─────────────────────────────────────────── -->
      <?php if ( $about ) : ?>
        <div class="bp-card">
          <h2 class="bp-h"><?php esc_html_e( 'About', 'crs' ); ?> <?php the_title(); ?></h2>
          <?php echo wpautop( esc_html( $about ) ); ?>
        </div>
      <?php endif; ?>

      <!-- ── Services Offered ──────────────────────────────── -->
      <?php if ( $services && ! is_wp_error( $services ) ) : ?>
        <div class="bp-card">
          <h2 class="bp-h"><?php esc_html_e( 'Services Offered', 'crs' ); ?></h2>
          <ul class="bp-list check bp-collist">
            <?php foreach ( $services as $svc ) : ?>
              <li>
                <i class="fa-solid fa-circle-check"></i>
                <a href="<?php echo esc_url( get_term_link( $svc ) ); ?>">
                  <?php echo esc_html( $svc->name ); ?>
                </a>
              </li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <!-- ── Brands + OS ───────────────────────────────────── -->
      <?php if ( ( $brands && ! is_wp_error( $brands ) ) || ( $os_list && ! is_wp_error( $os_list ) ) ) : ?>
        <div class="bp-card">
          <div class="row g-4">
            <?php if ( $brands && ! is_wp_error( $brands ) ) : ?>
              <div class="col-md-6">
                <h2 class="bp-h"><?php esc_html_e( 'Brands Supported', 'crs' ); ?></h2>
                <ul class="bp-list">
                  <?php foreach ( $brands as $brand ) :
                      $logo_url_b = get_term_meta( $brand->term_id, 'brand_logo', true );
                      ?>
                      <li>
                        <?php if ( $logo_url_b ) : ?>
                          <img src="<?php echo esc_url( $logo_url_b ); ?>"
                               alt="<?php echo esc_attr( $brand->name ); ?>"
                               loading="lazy">
                        <?php endif; ?>
                        <?php echo esc_html( $brand->name ); ?>
                      </li>
                  <?php endforeach; ?>
                </ul>
              </div>
            <?php endif; ?>

            <?php if ( $os_list && ! is_wp_error( $os_list ) ) : ?>
              <div class="col-md-6">
                <h2 class="bp-h"><?php esc_html_e( 'Operating Systems', 'crs' ); ?></h2>
                <ul class="bp-list">
                  <?php foreach ( $os_list as $os ) : ?>
                    <li>
                      <i class="fa-brands fa-windows"></i>
                      <?php echo esc_html( $os->name ); ?>
                    </li>
                  <?php endforeach; ?>
                </ul>
              </div>
            <?php endif; ?>
          </div>
        </div>
      <?php endif; ?>

      <!-- ── Service Options ───────────────────────────────── -->
      <div class="bp-card">
        <h2 class="bp-h"><?php esc_html_e( 'Service Options', 'crs' ); ?></h2>
        <ul class="bp-list check">
          <?php if ( $onsite )   : ?><li><i class="fa-solid fa-house"></i><?php esc_html_e( 'Onsite Service — comes to you', 'crs' ); ?></li><?php endif; ?>
          <?php if ( $remote )   : ?><li><i class="fa-solid fa-wifi"></i><?php esc_html_e( 'Remote Support — via internet', 'crs' ); ?></li><?php endif; ?>
          <?php if ( $pickup )   : ?><li><i class="fa-solid fa-bag-shopping"></i><?php esc_html_e( 'Pickup & Drop-off Available', 'crs' ); ?></li><?php endif; ?>
          <?php if ( $same_day ) : ?><li><i class="fa-solid fa-bolt"></i><?php esc_html_e( 'Same-Day Service Available', 'crs' ); ?></li><?php endif; ?>
        </ul>
      </div>

      <!-- ── Reviews ───────────────────────────────────────── -->
      <div class="bp-card">
        <h2 class="bp-h">
          <?php esc_html_e( 'Reviews', 'crs' ); ?>
          <?php if ( $count ) : ?>
            <span style="font-size:14px;font-weight:500;color:var(--crs-muted);">
              (<?php echo esc_html( $count ); ?>)
            </span>
          <?php endif; ?>
        </h2>

        <?php
        // Reviews are stored as a custom post type 'crs_review' linked to this business
        $reviews = new WP_Query( [
            'post_type'      => 'crs_review',
            'post_status'    => 'publish',
            'posts_per_page' => 5,
            'meta_query'     => [ [
                'key'   => '_review_business_id',
                'value' => $post_id,
            ] ],
            'orderby' => 'date',
            'order'   => 'DESC',
        ] );

        if ( $reviews->have_posts() ) :
            while ( $reviews->have_posts() ) : $reviews->the_post();
                $r_avg    = get_post_meta( get_the_ID(), '_review_rating', true );
                $r_author = get_post_meta( get_the_ID(), '_reviewer_name', true ) ?: __( 'Anonymous', 'crs' );
                ?>
                <div class="bp-review">
                  <div class="bp-review-head">
                    <div class="bp-review-name"><?php echo esc_html( $r_author ); ?></div>
                    <div class="bp-review-stars"><?php crs_render_stars( $r_avg ); ?></div>
                    <div class="bp-review-score"><?php echo esc_html( number_format( $r_avg, 1 ) ); ?></div>
                    <div class="bp-review-score" style="margin-left:auto;">
                      <?php echo get_the_date(); ?>
                    </div>
                  </div>
                  <p class="bp-review-text"><?php the_content(); ?></p>
                </div>
                <?php
            endwhile;
            wp_reset_postdata();
        else : ?>
          <p class="text-muted-2"><?php esc_html_e( 'No reviews yet. Be the first!', 'crs' ); ?></p>
        <?php endif; ?>

      </div><!-- /reviews -->

    </div><!-- /col-lg-8 -->

    <!-- ============================================================
         SIDEBAR
         ============================================================ -->
    <div class="col-lg-4">
      <div class="bp-sticky">

        <!-- Request a Quote -->
        <div class="bp-card bp-quote" id="enquiry">
          <h3 class="bp-side-title">
            <i class="fa-solid fa-paper-plane"></i>
            <?php esc_html_e( 'Request a Quote', 'crs' ); ?>
          </h3>
          <p><?php esc_html_e( 'Send a message directly to this business. They will reply to your email within 24 hours.', 'crs' ); ?></p>

          <?php
          // Enquiry form shortcode — powered by crs-business-dashboard plugin
          if ( shortcode_exists( 'crs_enquiry_form' ) ) :
              echo do_shortcode( '[crs_enquiry_form business_id="' . $post_id . '"]' );
          else : ?>
            <form class="mt-2" method="POST">
              <?php wp_nonce_field( 'crs_enquiry_' . $post_id ); ?>
              <input type="hidden" name="business_id" value="<?php echo esc_attr( $post_id ); ?>">
              <div class="mb-2">
                <input type="text" name="enquiry_name" required
                       class="form-control form-control-sm"
                       placeholder="<?php esc_attr_e( 'Your Name', 'crs' ); ?>">
              </div>
              <div class="mb-2">
                <input type="email" name="enquiry_email" required
                       class="form-control form-control-sm"
                       placeholder="<?php esc_attr_e( 'Your Email', 'crs' ); ?>">
              </div>
              <div class="mb-2">
                <input type="tel" name="enquiry_phone"
                       class="form-control form-control-sm"
                       placeholder="<?php esc_attr_e( 'Your Phone', 'crs' ); ?>">
              </div>
              <div class="mb-3">
                <textarea name="enquiry_message" rows="3" required
                          class="form-control form-control-sm"
                          placeholder="<?php esc_attr_e( 'Describe what you need…', 'crs' ); ?>"></textarea>
              </div>
              <button type="submit" class="bp-quote-btn">
                <?php esc_html_e( 'Send Enquiry', 'crs' ); ?>
              </button>
            </form>
          <?php endif; ?>
        </div><!-- /enquiry -->

        <!-- Contact Information -->
        <div class="bp-card mt-3">
          <h3 class="bp-side-title">
            <i class="fa-solid fa-address-card"></i>
            <?php esc_html_e( 'Contact Information', 'crs' ); ?>
          </h3>
          <ul class="bp-contact">
            <?php if ( $address ) : ?>
              <li>
                <i class="fa-solid fa-location-dot"></i>
                <span><?php echo esc_html( $address ); ?></span>
              </li>
            <?php endif; ?>
            <?php if ( $phone ) : ?>
              <li>
                <i class="fa-solid fa-phone"></i>
                <a href="tel:<?php echo esc_attr( preg_replace( '/\D/', '', $phone ) ); ?>">
                  <?php echo esc_html( $phone ); ?>
                </a>
              </li>
            <?php endif; ?>
            <?php if ( $email && in_array( $tier, [ 'featured', 'premium' ], true ) ) : ?>
              <li>
                <i class="fa-solid fa-envelope"></i>
                <a href="mailto:<?php echo esc_attr( $email ); ?>">
                  <?php echo esc_html( $email ); ?>
                </a>
              </li>
            <?php endif; ?>
            <?php if ( $website ) : ?>
              <li>
                <i class="fa-solid fa-globe"></i>
                <a href="<?php echo esc_url( $website ); ?>" target="_blank" rel="noopener noreferrer">
                  <?php echo esc_html( str_replace( [ 'https://', 'http://' ], '', $website ) ); ?>
                </a>
              </li>
            <?php endif; ?>
          </ul>
        </div>

        <!-- Opening Hours -->
        <?php if ( $hours_arr ) : ?>
          <div class="bp-card mt-3">
            <h3 class="bp-side-title">
              <i class="fa-solid fa-clock"></i>
              <?php esc_html_e( 'Opening Hours', 'crs' ); ?>
            </h3>
            <table class="bp-hours w-100">
              <?php
              $days = [ 'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday' ];
              foreach ( $days as $day ) :
                  $info   = $hours_arr[ $day ] ?? null;
                  $closed = ! $info || empty( $info['open'] );
                  ?>
                  <tr>
                    <td><?php echo esc_html( $day ); ?></td>
                    <td class="<?php echo $closed ? 'closed' : ''; ?>">
                      <?php if ( $closed ) : ?>
                        <?php esc_html_e( 'Closed', 'crs' ); ?>
                      <?php else : ?>
                        <?php echo esc_html( $info['open'] . ' – ' . $info['close'] ); ?>
                      <?php endif; ?>
                    </td>
                  </tr>
              <?php endforeach; ?>
            </table>
          </div>
        <?php endif; ?>

      </div><!-- /bp-sticky -->
    </div><!-- /sidebar -->

  </div><!-- /row -->
</div><!-- /container -->
</div><!-- /bp-wrap -->

<?php get_footer(); ?>
