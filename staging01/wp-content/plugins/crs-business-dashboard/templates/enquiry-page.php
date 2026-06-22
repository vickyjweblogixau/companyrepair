<?php
defined( 'ABSPATH' ) || exit;

// ── Get business from URL slug ──────────────────────────────────
$business_slug = sanitize_text_field( get_query_var( 'crs_business_slug' ) );
$business      = get_page_by_path( $business_slug, OBJECT, 'business' );

if ( ! $business ) {
    wp_redirect( home_url( '/' ) );
    exit;
}

$post_id       = $business->ID;
$logo_id       = get_field( 'crs_logo',           $post_id );
$phone         = get_field( 'crs_phone',           $post_id );
$mobile        = get_field( 'crs_mobile',          $post_id ); // add if you have this field
$email         = get_field( 'crs_email',           $post_id );
$website       = get_field( 'crs_website',         $post_id );
$address       = get_field( 'crs_address',         $post_id );
$about         = get_field( 'crs_description',     $post_id );
$tagline       = get_field( 'crs_tagline',         $post_id );
$avg           = get_field( 'crs_review_avg',      $post_id );
$count         = get_field( 'crs_review_count',    $post_id );
$verified      = get_field( 'crs_verified',        $post_id );
$years         = get_field( 'crs_year_established',$post_id );
$tier          = get_field( 'crs_tier',            $post_id );
$service_modes = get_field( 'crs_service_modes',   $post_id );
$services      = get_the_terms( $post_id, 'repair-service' );
$suburb_term   = get_the_terms( $post_id, 'au-suburb' );
$state_term    = get_the_terms( $post_id, 'au-state'  );

$logo_url = $logo_id
    ? wp_get_attachment_image_url( $logo_id, 'thumbnail' )
    : CRS_URI . '/assets/images/logo-placeholder.png';

$business_name = $business->post_title;

// ── Opening hours for sidebar ───────────────────────────────────
$days_map = [
    'monday'    => 'Monday',    'tuesday'  => 'Tuesday',
    'wednesday' => 'Wednesday', 'thursday' => 'Thursday',
    'friday'    => 'Friday',    'saturday' => 'Saturday',
    'sunday'    => 'Sunday',
];
$open_days = [];
foreach ( $days_map as $key => $label ) {
    $open  = get_field( "crs_hours_{$key}_open",   $post_id );
    $close = get_field( "crs_hours_{$key}_close",  $post_id );
    $closed= get_field( "crs_hours_{$key}_closed", $post_id );
    if ( $open && $close && ! $closed ) {
        $open_days[] = $label . ': ' . date( 'g:i a', strtotime( $open ) )
                     . ' - ' . date( 'g:i a', strtotime( $close ) );
    }
}

// ── Location label ──────────────────────────────────────────────
$location_label = '';
if ( $suburb_term && ! is_wp_error( $suburb_term ) ) {
    $location_label = $suburb_term[0]->name;
}
if ( $state_term && ! is_wp_error( $state_term ) ) {
    $abbr = get_term_meta( $state_term[0]->term_id, 'au_state_abbreviation', true );
    if ( $abbr ) $location_label .= ', ' . $abbr;
}
if ( ! $location_label ) $location_label = $address;

// ── CF7 form ID — set your actual form ID here ──────────────────
// Go to Contact → Contact Forms → your enquiry form → note the ID in the URL
$cf7_form_id = get_option( 'crs_enquiry_cf7_id', '8dc5fe9' ); // same as your modal

// ── Breadcrumb ancestors ────────────────────────────────────────
$state_name  = ( $state_term  && ! is_wp_error( $state_term  ) ) ? $state_term[0]->name  : '';
$suburb_name = ( $suburb_term && ! is_wp_error( $suburb_term ) ) ? $suburb_term[0]->name : '';

get_header();
?>

<main class="enq-wrap py-4">
<div class="container px-3">

  <!-- ── Breadcrumb ───────────────────────────────────────────── -->
  <nav class="enq-breadcrumb mb-3">
    <a href="<?php echo esc_url( home_url( '/' ) ); ?>">Home</a>
    <i class="fa-solid fa-chevron-right"></i>

    <?php if ( $state_name ) : ?>
      <a href="<?php echo esc_url( home_url( '/computer-repairs/' . $state_term[0]->slug . '/' ) ); ?>">
        <?php echo esc_html( $state_name ); ?>
      </a>
      <i class="fa-solid fa-chevron-right"></i>
    <?php endif; ?>

    <?php if ( $suburb_name ) : ?>
      <a href="<?php echo esc_url( get_term_link( $suburb_term[0] ) ); ?>">
        <?php echo esc_html( $suburb_name ); ?>
      </a>
      <i class="fa-solid fa-chevron-right"></i>
    <?php endif; ?>

    <a href="<?php echo esc_url( get_permalink( $post_id ) ); ?>">
      <?php echo esc_html( $business_name ); ?>
    </a>
    <i class="fa-solid fa-chevron-right"></i>
    <span class="current">Enquire</span>
  </nav>

  <!-- ── Business Hero ────────────────────────────────────────── -->
  <section class="enq-hero">
    <div class="row g-4 align-items-center">

      <div class="col-lg-8">
        <div class="d-flex gap-4 flex-column flex-sm-row align-items-sm-center">

          <div class="enq-hero-logo">
            <img src="<?php echo esc_url( $logo_url ); ?>"
                 alt="<?php echo esc_attr( $business_name ); ?>">
          </div>

          <div>
            <div class="enq-hero-pre">Enquire with</div>
            <h1 class="enq-hero-name"><?php echo esc_html( $business_name ); ?></h1>

            <?php if ( $avg ) : ?>
              <div class="enq-hero-rating">
                <span class="stars"><?php crs_render_stars( $avg ); ?></span>
                <strong><?php echo esc_html( number_format( $avg, 1 ) ); ?></strong>
                <span class="reviews">(<?php echo esc_html( $count ); ?> reviews)</span>
              </div>
            <?php endif; ?>

            <div class="enq-hero-meta">
              <?php if ( $location_label ) : ?>
                <span>
                  <i class="fa-solid fa-location-dot"></i>
                  <?php echo esc_html( $location_label ); ?>
                </span>
              <?php endif; ?>
              <?php if ( ! empty( $open_days ) ) : ?>
                <span>
                  <i class="fa-solid fa-clock"></i>
                  <?php echo esc_html( $open_days[0] ); ?>
                </span>
              <?php endif; ?>
            </div>
          </div>

        </div>
      </div>

      <!-- Trust items -->
      <div class="col-lg-4">
        <div class="enq-trust">
          <div class="enq-trust-item">
            <span class="tick"><i class="fa-solid fa-check"></i></span>
            <div>
              <h4>Local &amp; Trusted</h4>
              <p>
                <?php if ( $years ) : ?>
                  <?php echo esc_html( 'In business since ' . $years . '.' ); ?>
                <?php else : ?>
                  Serving the local community.
                <?php endif; ?>
              </p>
            </div>
          </div>
          <div class="enq-trust-item">
            <span class="tick"><i class="fa-solid fa-check"></i></span>
            <div>
              <h4>Fast Response</h4>
              <p>We aim to respond to all enquiries within 1 hour.</p>
            </div>
          </div>
          <div class="enq-trust-item">
            <span class="tick"><i class="fa-solid fa-check"></i></span>
            <div>
              <h4>No Obligation</h4>
              <p>Send an enquiry. It's free and without obligation.</p>
            </div>
          </div>
        </div>
      </div>

    </div>
  </section>

  <!-- ── Body Grid ─────────────────────────────────────────────── -->
  <div class="row g-4 mt-1">

    <!-- LEFT: CF7 Form -->
    <div class="col-lg-7">
      <div class="enq-card">
        <h2 class="enq-h">Send an Enquiry</h2>
        <p class="enq-sub">
          Complete the form below and
          <strong><?php echo esc_html( $business_name ); ?></strong>
          will get back to you shortly.
        </p>

        <?php
        // Pass business ID and name to CF7 via hidden fields
        // This JS method injects hidden fields after CF7 renders
        ?>
        <div class="crs-cf7-wrap"
             data-business-id="<?php echo esc_attr( $post_id ); ?>"
             data-business-name="<?php echo esc_attr( $business_name ); ?>">
          <?php echo do_shortcode( '[contact-form-7 id="' . esc_attr( $cf7_form_id ) . '" title="Enquiry Form"]' ); ?>
        </div>

      </div>
    </div>

    <!-- RIGHT: Sidebar -->
    <div class="col-lg-5">

      <!-- About -->
      <?php if ( $about || $tagline ) : ?>
      <div class="enq-card">
        <h3 class="enq-side-title">About <?php echo esc_html( $business_name ); ?></h3>
        <p class="enq-about-text">
          <?php echo esc_html( $tagline ?: wp_trim_words( wp_strip_all_tags( $about ), 40 ) ); ?>
        </p>
        <a href="<?php echo esc_url( get_permalink( $post_id ) ); ?>" class="enq-link">
          View full profile <i class="fa-solid fa-arrow-right"></i>
        </a>
      </div>
      <?php endif; ?>

      <!-- Contact -->
      <div class="enq-card">
        <h3 class="enq-side-title">Contact Details</h3>
        <ul class="enq-contact">
          <?php if ( $phone ) : ?>
            <li class="phone-number-box">
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
          <?php if ( $address ) : ?>
            <li>
              <i class="fa-solid fa-location-dot"></i>
              <span><?php echo esc_html( $address ); ?></span>
            </li>
          <?php endif; ?>
        </ul>
      </div>

      <!-- Services -->
      <?php if ( $services && ! is_wp_error( $services ) ) : ?>
      <div class="enq-card">
        <h3 class="enq-side-title">We Specialise In</h3>
        <ul class="enq-spec">
          <?php foreach ( array_slice( $services, 0, 8 ) as $svc ) : ?>
            <li>
              <i class="fa-solid fa-circle-check"></i>
              <?php echo esc_html( $svc->name ); ?>
            </li>
          <?php endforeach; ?>
        </ul>
        <?php if ( count( $services ) > 8 ) : ?>
          <a href="<?php echo esc_url( get_permalink( $post_id ) ); ?>" class="enq-link">
            View all services (<?php echo count( $services ); ?>)
            <i class="fa-solid fa-arrow-right"></i>
          </a>
        <?php endif; ?>
      </div>
      <?php endif; ?>

      <!-- Why Choose -->
      <div class="enq-card">
        <h3 class="enq-side-title">Why Choose <?php echo esc_html( $business_name ); ?>?</h3>
        <div class="enq-why">
          <div class="enq-why-ico"><i class="fa-solid fa-user-gear"></i></div>
          <div>
            <h4>Experienced Technicians</h4>
            <p>Qualified and experienced professionals.</p>
          </div>
        </div>
        <div class="enq-why">
          <div class="enq-why-ico"><i class="fa-solid fa-gear"></i></div>
          <div>
            <h4>Quality Parts</h4>
            <p>We use high-quality parts with warranty.</p>
          </div>
        </div>
        <div class="enq-why">
          <div class="enq-why-ico"><i class="fa-solid fa-tag"></i></div>
          <div>
            <h4>Affordable Pricing</h4>
            <p>Upfront, competitive pricing. No hidden fees.</p>
          </div>
        </div>
        <?php if ( $location_label ) : ?>
        <div class="enq-why">
          <div class="enq-why-ico"><i class="fa-solid fa-location-dot"></i></div>
          <div>
            <h4>Local Support</h4>
            <p>Proudly supporting <?php echo esc_html( $location_label ); ?>.</p>
          </div>
        </div>
        <?php endif; ?>
      </div>

    </div>
  </div><!-- /row -->

  <!-- ── How It Works ─────────────────────────────────────────── -->
  <div class="enq-card mt-4">
    <h2 class="enq-how-title">How It Works</h2>
    <div class="row g-4 align-items-stretch">

      <?php
      $steps = [
          [ 'Send Enquiry',       'Fill out the form and send your enquiry to the business.' ],
          [ 'Business Responds',  esc_html( $business_name ) . ' will contact you to discuss.' ],
          [ 'Get Help',           'Receive a quote or book a service at a time that suits you.' ],
          [ 'Problem Solved',     'Get your computer fixed and back up and running!' ],
      ];
      foreach ( $steps as $i => $step ) : ?>
        <div class="col-md-6 col-lg-3 position-relative">
          <?php if ( $i > 0 ) : ?>
            <i class="fa-solid fa-chevron-right enq-step-arrow position-absolute"></i>
          <?php endif; ?>
          <div class="enq-step">
            <div class="enq-step-num"><?php echo $i + 1; ?></div>
            <div>
              <h4><?php echo esc_html( $step[0] ); ?></h4>
              <p><?php echo esc_html( $step[1] ); ?></p>
            </div>
          </div>
        </div>
      <?php endforeach; ?>

    </div>
  </div>

</div>
</main>

<?php get_footer(); ?>

<style>
/* ── Breadcrumb ── */
.enq-breadcrumb {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 6px;
    font-size: 13px;
    color: #888;
}
.enq-breadcrumb a { color: var(--crs-navy,#14213d); text-decoration: none; }
.enq-breadcrumb a:hover { text-decoration: underline; }
.enq-breadcrumb .fa-chevron-right { font-size: 10px; color: #bbb; }
.enq-breadcrumb .current { color: #555; }

/* ── Hero ── */
.enq-hero {
    background: #fff;
    border-radius: 14px;
    padding: 28px;
    margin-bottom: 4px;
    box-shadow: 0 2px 12px rgba(0,0,0,.06);
}
.enq-hero-logo {
    width: 90px;
    height: 90px;
    border-radius: 10px;
    border: 1px solid #eee;
    overflow: hidden;
    flex-shrink: 0;
    background: #fafafa;
    display: flex;
    align-items: center;
    justify-content: center;
}
.enq-hero-logo img { width: 100%; height: 100%; object-fit: contain; padding: 8px; }
.enq-hero-pre { font-size: 13px; color: #888; margin-bottom: 2px; }
.enq-hero-name { font-size: clamp(20px,3vw,26px); font-weight: 800; color: var(--crs-navy,#14213d); margin: 0 0 8px; }
.enq-hero-rating {
    display: flex; align-items: center; gap: 6px;
    font-size: 14px; color: var(--crs-navy,#14213d); margin-bottom: 10px;
}
.enq-hero-rating .fa-star,
.enq-hero-rating .fa-star-half-stroke { color: var(--crs-star,#f59e0b); }
.enq-hero-rating .reviews { color: #888; font-size: 13px; }
.enq-hero-meta { display: flex; flex-wrap: wrap; gap: 14px; font-size: 13px; color: #555; }
.enq-hero-meta span { display: flex; align-items: center; gap: 5px; }
.enq-hero-meta .fa-location-dot { color: #e63946; }
.enq-hero-meta .fa-clock { color: #2563eb; }

/* ── Trust ── */
.enq-trust { display: flex; flex-direction: column; gap: 14px; }
.enq-trust-item { display: flex; gap: 12px; align-items: flex-start; }
.enq-trust-item .tick {
    width: 28px; height: 28px; border-radius: 50%;
    background: #dcfce7; color: #16a34a;
    display: flex; align-items: center; justify-content: center;
    font-size: 13px; flex-shrink: 0; margin-top: 2px;
}
.enq-trust-item h4 { font-size: 14px; font-weight: 700; margin: 0 0 2px; color: var(--crs-navy,#14213d); }
.enq-trust-item p  { font-size: 13px; color: #666; margin: 0; }

/* ── Cards ── */
.enq-card {
    background: #fff;
    border-radius: 14px;
    padding: 28px;
    box-shadow: 0 2px 12px rgba(0,0,0,.06);
    margin-bottom: 20px;
}
.enq-h { font-size: 20px; font-weight: 800; color: var(--crs-navy,#14213d); margin: 0 0 6px; }
.enq-sub { font-size: 14px; color: #666; margin: 0 0 20px; }
.enq-side-title { font-size: 16px; font-weight: 700; color: var(--crs-navy,#14213d); margin: 0 0 14px; }

/* ── CF7 overrides ── */
.crs-cf7-wrap .wpcf7-form { margin: 0; }
.crs-cf7-wrap .wpcf7-form p { margin: 0 0 16px; }

.crs-cf7-wrap label {
    display: block;
    font-size: 12px;
    font-weight: 600;
    color: #374151;
    text-transform: uppercase;
    letter-spacing: .5px;
    margin-bottom: 6px;
}
.crs-cf7-wrap input[type="text"],
.crs-cf7-wrap input[type="email"],
.crs-cf7-wrap input[type="tel"],
.crs-cf7-wrap select,
.crs-cf7-wrap textarea {
    width: 100%;
    border: 1.5px solid #e5e7eb;
    border-radius: 8px;
    padding: 11px 14px;
    font-size: 15px;
    color: #1b2430;
    background: #fafbfc;
    font-family: inherit;
    outline: none;
    transition: border-color .2s;
    box-sizing: border-box;
    appearance: none;
}
.crs-cf7-wrap input:focus,
.crs-cf7-wrap select:focus,
.crs-cf7-wrap textarea:focus {
    border-color: var(--crs-navy,#14213d);
    background: #fff;
}
.crs-cf7-wrap textarea { resize: vertical; min-height: 120px; }

.crs-cf7-wrap input[type="submit"] {
    width: 100%;
    background: var(--crs-navy,#14213d);
    color: #fff;
    border: none;
    border-radius: 10px;
    padding: 16px;
    font-size: 16px;
    font-weight: 700;
    cursor: pointer;
    transition: background .2s;
    font-family: inherit;
}
.crs-cf7-wrap input[type="submit"]:hover { background: #0a1c30; }

.crs-cf7-wrap .wpcf7-not-valid-tip {
    color: #dc2626; font-size: 12px; margin-top: 4px;
}
.crs-cf7-wrap .wpcf7-response-output {
    border-radius: 8px; padding: 12px 16px;
    font-size: 14px; margin-top: 12px;
}

/* ── Sidebar elements ── */
.enq-about-text { font-size: 14px; color: #555; line-height: 1.7; margin: 0 0 12px; }
.enq-link { font-size: 13px; font-weight: 600; color: var(--crs-navy,#14213d); text-decoration: none; }
.enq-link:hover { text-decoration: underline; }

.enq-contact { list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 12px; }
.enq-contact li { display: flex; align-items: flex-start; gap: 10px; font-size: 14px; color: #333; }
.enq-contact li i { color: var(--crs-navy,#14213d); width: 16px; margin-top: 2px; flex-shrink: 0; }
.enq-contact a { color: #14213d; text-decoration: none; }
.enq-contact a:hover { text-decoration: underline; }

.enq-spec { list-style: none; padding: 0; margin: 0 0 12px; display: flex; flex-direction: column; gap: 10px; }
.enq-spec li { display: flex; align-items: center; gap: 8px; font-size: 14px; color: #333; }
.enq-spec .fa-circle-check { color: #16a34a; }

.enq-why { display: flex; gap: 12px; align-items: flex-start; margin-bottom: 14px; }
.enq-why:last-child { margin-bottom: 0; }
.enq-why-ico {
    width: 36px; height: 36px; border-radius: 8px;
    background: #f0f4ff; color: var(--crs-navy,#14213d);
    display: flex; align-items: center; justify-content: center;
    font-size: 16px; flex-shrink: 0;
}
.enq-why h4 { font-size: 14px; font-weight: 700; margin: 0 0 2px; color: var(--crs-navy,#14213d); }
.enq-why p  { font-size: 13px; color: #666; margin: 0; }

/* ── How it works ── */
.enq-how-title { font-size: 20px; font-weight: 800; color: var(--crs-navy,#14213d); margin: 0 0 20px; }
.enq-step {
    background: #f6f8fc;
    border-radius: 12px;
    padding: 20px;
    height: 100%;
    display: flex;
    gap: 14px;
    align-items: flex-start;
}
.enq-step-num {
    width: 36px; height: 36px; border-radius: 50%;
    background: var(--crs-navy,#14213d); color: #fff;
    display: flex; align-items: center; justify-content: center;
    font-size: 16px; font-weight: 800; flex-shrink: 0;
}
.enq-step h4 { font-size: 15px; font-weight: 700; margin: 0 0 4px; color: var(--crs-navy,#14213d); }
.enq-step p  { font-size: 13px; color: #666; margin: 0; }
.enq-step-arrow {
    top: 50%; left: -14px;
    transform: translateY(-50%);
    color: #ccc; font-size: 18px;
    z-index: 1;
}
@media (max-width: 767px) { .enq-step-arrow { display: none; } }
</style>

<script>
// Inject hidden fields into CF7 form with business data
document.addEventListener( 'DOMContentLoaded', function () {
    var wrap = document.querySelector( '.crs-cf7-wrap' );
    if ( ! wrap ) return;

    var form = wrap.querySelector( 'form.wpcf7-form' );
    if ( ! form ) return;

    var businessId   = wrap.dataset.businessId;
    var businessName = wrap.dataset.businessName;

    function addHidden( name, value ) {
        var el = document.createElement( 'input' );
        el.type  = 'hidden';
        el.name  = name;
        el.value = value;
        form.appendChild( el );
    }

    addHidden( 'business_id',   businessId );
    addHidden( 'business_name', businessName );
} );
</script>