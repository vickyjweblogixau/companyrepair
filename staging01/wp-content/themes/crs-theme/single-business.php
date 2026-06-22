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
$tagline   = get_field('crs_tagline', $post_id);
$about     = get_field('crs_description', $post_id);
$phone     = get_field('crs_phone', $post_id);
$email     = get_field('crs_email', $post_id);
$website   = get_field('crs_website', $post_id);
$address   = get_field('crs_address', $post_id);
$logo_id   = get_field('crs_logo', $post_id);
$gallery   = get_field('crs_gallery', $post_id);       // if exists
$hours     = get_field('crs_business_hours', $post_id);// if exists
$service_modes = get_field('crs_service_modes', $post_id);
$onsite = is_array($service_modes) && in_array('onsite', $service_modes);
$remote = is_array($service_modes) && in_array('remote', $service_modes);
$pickup = is_array($service_modes) && in_array('pickup', $service_modes);
$same_day  = get_field('crs_same_day', $post_id);
$years     = get_field('crs_year_established', $post_id);
$verified  = get_field('crs_verified', $post_id);
$top_rated = get_field('crs_top_rated', $post_id);
$avg       = get_field('crs_review_avg', $post_id);
$count     = get_field('crs_review_count', $post_id);
$tier      = get_field('crs_tier', $post_id);
$logo_url  = $logo_id
    ? wp_get_attachment_image_url( $logo_id, 'crs-thumbnail' )
    : CRS_URI . '/assets/images/logo-placeholder.png';
//$hours_arr = $hours ? json_decode( $hours, true ) : [];
$days = [
    'monday'    => 'Monday',
    'tuesday'   => 'Tuesday',
    'wednesday' => 'Wednesday',
    'thursday'  => 'Thursday',
    'friday'    => 'Friday',
    'saturday'  => 'Saturday',
    'sunday'    => 'Sunday',
];
$hours_arr = [];
foreach ($days as $key => $label) {
    $hours_arr[$label] = [
        'open'   => get_field("crs_hours_{$key}_open"),
        'close'  => get_field("crs_hours_{$key}_close"),
        'closed' => get_field("crs_hours_{$key}_closed"),
    ];
}
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
              <?php /* if ( $phone ) : ?>
                <a href="tel:<?php echo esc_attr( preg_replace( '/\D/', '', $phone ) ); ?>"
                   class="bp-btn bp-btn-primary">
                  <i class="fa-solid fa-phone"></i><?php echo esc_html( $phone ); ?>
                </a>
              <?php endif; */ ?>
              <?php //if ( in_array( $tier, [ 'standard', 'featured', 'premium' ], true ) ) : ?>
                 <a href="#" class="bp-btn bp-btn-primary" data-business-id="<?php echo get_the_ID(); ?>"
                  data-bs-toggle="modal" data-bs-target="#enquiryModal"><i
                      class="fa-solid fa-envelope"></i> Enquire Now</a>
                </a>
              <?php //endif; ?>
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
            <?php 
            $gallery_field_keys = [ 'crs_gallery_1', 'crs_gallery_2', 'crs_gallery_3', 'crs_gallery_4' ];
            $gallery = [];
            foreach ( $gallery_field_keys as $gkey ) {
                $img_id = function_exists( 'get_field' ) ? get_field( $gkey, $post_id ) : '';
                if ( $img_id ) {
                    $gallery[] = $img_id;
                }
            } ?>
          </div>
        <?php endif; ?>
      </div><!-- /header card -->
      <!-- ── About ─────────────────────────────────────────── -->
        <?php if ( $about ) : ?>
            <div class="bp-card" style=" white-space: pre-line;">
                <h2 class="bp-h"><?php esc_html_e( 'About', 'crs' ); ?> <?php the_title(); ?></h2>
                <div class="bp-about-text">
                   <?php
                  echo wp_kses_post( trim($about) );
                   ?>
                </div>
            </div>
            
        <?php endif; ?>
      <!-- ── Services Offered ──────────────────────────────── -->
        <div class="bp-card">
            <div class="row g-4">
                <!-- Services -->
                <div class="col-lg-3">
                    <h2 class="bp-h">Services Offered</h2>
                    <?php if ($services && !is_wp_error($services)) : ?>

                        <?php
                        $grouped = [];

                        foreach ($services as $service) {

                            // Find top-level parent
                            if ($service->parent) {
                                $ancestors = get_ancestors($service->term_id, 'repair-service');
                                $parent_id = end($ancestors);
                                $parent = get_term($parent_id, 'repair-service');
                            } else {
                                $parent = $service;
                            }

                            if (!isset($grouped[$parent->term_id])) {
                                $grouped[$parent->term_id] = [
                                    'parent' => $parent,
                                    'children' => [],
                                ];
                            }

                            if ($service->term_id != $parent->term_id) {
                                $grouped[$parent->term_id]['children'][] = $service;
                            }
                        }
                        ?>

                        <?php foreach ($grouped as $group) : ?>
                            <h6 class="mt-3 mb-2"><?php echo esc_html($group['parent']->name); ?></h6>

                            <?php if (!empty($group['children'])) : ?>
                                <ul class="bp-list check">
                                    <?php foreach ($group['children'] as $child) : ?>
                                        <li>
                                            <i class="fa-solid fa-check"></i>
                                            <?php echo esc_html($child->name); ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>

                        <?php endforeach; ?>

                    <?php endif; ?>
                </div>
                <!-- Brands -->
                <div class="col-lg-3">
                    <h2 class="bp-h">Brands Supported</h2>
                    <?php if ($brands && !is_wp_error($brands)) : ?>
                        <ul class="bp-list">
                            <?php foreach ($brands as $brand) :
                            $image_id = get_term_meta($brand->term_id, 'device_brand_image_id', true);
                            $image = '';
                            if ($image_id) {
                                $image = wp_get_attachment_image_url($image_id, 'thumbnail');
                            }
                            ?>
                                <li>
                                    <?php if($image): ?>
                                        <img src="<?php echo esc_url($image); ?>" alt="">
                                    <?php endif; ?>

                                    <?php echo esc_html($brand->name); ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
                <!-- OS -->
                <div class="col-lg-3">
                    <h2 class="bp-h">Operating Systems</h2>

                    <?php if ($os_list && !is_wp_error($os_list)) : ?>
                        <ul class="bp-list">
                            <?php foreach ($os_list as $os) :
                             $image_id = get_term_meta($os->term_id, 'operating_system_image_id', true);
                            $image = '';
                            if ($image_id) {
                                $image = wp_get_attachment_image_url($image_id, 'thumbnail');
                            } ?>
                                <li>
                                   <?php if ($image) : ?>
                                      <img src="<?php echo esc_url($image); ?>"
                                          alt="<?php echo esc_attr($brand->name); ?>"
                                          loading="lazy">
                                  <?php endif; ?>
                                    <?php echo esc_html($os->name); ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
                <!-- Service Options -->
                <div class="col-lg-3">
                    <h2 class="bp-h">Service Options</h2>

                    <ul class="bp-list check">
                        <?php if ($onsite) : ?>
                            <li><i class="fa-solid fa-check"></i>Onsite Service</li>
                        <?php endif; ?>

                        <?php if ($pickup) : ?>
                            <li><i class="fa-solid fa-check"></i>Pickup & Drop-off</li>
                        <?php endif; ?>

                        <?php if ($remote) : ?>
                            <li><i class="fa-solid fa-check"></i>Remote Support</li>
                        <?php endif; ?>

                        <?php if ($same_day) : ?>
                            <li><i class="fa-solid fa-check"></i>Same Day Service</li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
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
                $r_avg    = get_post_meta( get_the_ID(), 'crs_top_rated', true );
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
        <?php /*
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
        */ ?>
        <!-- Opening Hours -->
        <?php if ( $hours_arr ) : ?>
        <div class="bp-card mt-3">
            <h3 class="bp-side-title">
                <i class="fa-solid fa-clock"></i>
                <?php esc_html_e( 'Opening Hours', 'crs' ); ?>
            </h3>

            <table class="bp-hours">
                <?php
                $days = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];

                foreach ( $days as $day ) :

                    $info   = $hours_arr[$day] ?? [];
                    $closed = empty($info['open']) || empty($info['close']);
                ?>
                    <tr>
                        <td><?php echo esc_html($day); ?></td>
                        <td class="<?php echo $closed ? 'closed' : ''; ?>">
                            <?php
                            if ( $closed ) {
                                esc_html_e( 'Closed', 'crs' );
                            } else {
                                $open_time  = date('g:i A', strtotime($info['open']));
                                $close_time = date('g:i A', strtotime($info['close']));
                                echo esc_html($open_time . ' – ' . $close_time);
                            }
                            ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>
        <?php endif; ?>
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
            <?php /* if ( $phone ) : ?>
              <li>
                <i class="fa-solid fa-phone"></i>
                <a href="tel:<?php echo esc_attr( preg_replace( '/\D/', '', $phone ) ); ?>">
                  <?php echo esc_html( $phone ); ?>
                </a>
              </li>
            <?php endif; */ ?>
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

       <!-- Get a Free Quote -->
        <div class="bp-card bp-quote">
              <h3 class="bp-side-title"><i class="fa-solid fa-file-lines"></i> Get a Free Quote</h3>
              <p>Fill out the form and ABC Computers will get back to you shortly.</p>
              <button type="button"
                  class="bp-quote-btn"
                  data-business-id="<?php echo get_the_ID(); ?>"
                  data-bs-toggle="modal"
                  data-bs-target="#enquiryModal">
                  Request a Quote
              </button>
            </div>
      </div><!-- /bp-sticky -->
    </div><!-- /sidebar -->
  </div><!-- /row -->
</div><!-- /container -->
</div><!-- /bp-wrap -->
<?php get_template_part('template-parts/enquiry', 'modal'); ?>

<?php get_footer(); ?>
<script>
jQuery(function ($) {
    $('#enquiryModal').on('show.bs.modal', function (e) {
        var businessId = $(e.relatedTarget).data('business-id');
        $(this).find('#business_id').val(businessId);
    });

});
document.addEventListener('DOMContentLoaded', function () {
    const postcode = document.getElementById('crs-postcode');

    if (postcode) {
        postcode.setAttribute('maxlength', '4');
        postcode.setAttribute('inputmode', 'numeric');

        postcode.addEventListener('input', function () {
            this.value = this.value.replace(/\D/g, '').slice(0, 4);
        });
    }
});
// New Code

jQuery(document).ready(function ($) {

    var postcodeTimer;

    function resetFields() {
        $('#crs-suburb')
            .html('<option value="">— enter postcode —</option>')
            .prop('disabled', true);
        $('#crs-region').val('');
        $('#crs-state').val('');
        $('#crs-postcode-msg').text('');
    }

    // Inject status message span after postcode input
    $('#crs-postcode').after(
        '<span id="crs-postcode-msg" style="font-size:12px;color:#8a96a3;display:block;margin-top:4px;"></span>'
    );

    // Also style suburb select to match cs-control
    $('#crs-suburb').css({
        'width'       : '100%',
        'border'      : '1px solid #dce3ec',
        'border-radius': '8px',
        'padding'     : '10px',
        'font-size'   : '15px',
        'color'       : '#1b2430',
        'background'  : '#fafbfc',
        'font-family' : 'inherit',
        'outline'     : 'none'
    });

    // Make region + state readonly visually
    $('#crs-region, #crs-state').css({
        'background' : '#f4f6f8',
        'cursor'     : 'not-allowed'
    });

    // ── Postcode input handler ────────────────────────────────────────
    $(document).on('input', '#crs-postcode', function () {
        var pc = $(this).val().trim();
        resetFields();

        if (pc.length !== 4 || !/^\d{4}$/.test(pc)) return;

        clearTimeout(postcodeTimer);
        $('#crs-postcode-msg').text('Looking up…');

        postcodeTimer = setTimeout(function () {
            $.getJSON(
                crsAjax.ajaxurl,
                {
                    action   : 'crs_get_suburbs_by_postcode',
                    postcode : pc,
                    nonce    : crsAjax.nonce
                },
                function (res) {
                    $('#crs-postcode-msg').text('');
                    var suburbs = (res.success && res.data && res.data.suburbs)
                        ? res.data.suburbs : [];

                    if (suburbs.length === 0) {
                        $('#crs-postcode-msg').text('No suburbs found for this postcode.');
                        return;
                    }

                    if (suburbs.length === 1) {
                        // Single match — auto fill everything
                        var s = suburbs[0];
                        $('#crs-suburb')
                            .html('<option value="' + escHtml(s.name) + '" selected>' + escHtml(s.name) + '</option>')
                            .prop('disabled', false);
                        $('#crs-region').val(s.region);
                        $('#crs-state').val(s.state);
                    } else {
                        // Multiple suburbs — user selects
                        var opts = '<option value="">Select suburb…</option>';
                        $.each(suburbs, function (i, s) {
                            opts += '<option value="' + escHtml(s.name) + '"'
                                  + ' data-region="' + escHtml(s.region) + '"'
                                  + ' data-state="'  + escHtml(s.state)  + '">'
                                  + escHtml(s.name)
                                  + '</option>';
                        });
                        $('#crs-suburb').html(opts).prop('disabled', false);
                    }
                }
            ).fail(function () {
                $('#crs-postcode-msg').text('Could not look up postcode. Please try again.');
            });
        }, 500);
    });

    // ── Suburb select → fill Region + State ──────────────────────────
    $(document).on('change', '#crs-suburb', function () {
        var $opt = $(this).find('option:selected');
        $('#crs-region').val($opt.data('region') || '');
        $('#crs-state').val($opt.data('state')  || '');
    });

    // ── Helper: escape HTML ───────────────────────────────────────────
    function escHtml(str) {
        return $('<span>').text(str || '').html();
    }

});
</script>
