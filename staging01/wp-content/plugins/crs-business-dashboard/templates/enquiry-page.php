<?php
/**
 * CRS Enquiry Page Template
 * Custom form — no Contact Form 7 dependency.
 *
 * @package CRS
 */
defined( 'ABSPATH' ) || exit;
global $post;

/* ── Get business from URL slug ───────────────────────────────── */
$business_slug = sanitize_text_field( get_query_var( 'crs_business_slug' ) );
$business      = get_page_by_path( $business_slug, OBJECT, 'business' );

if ( ! $business ) {
    wp_redirect( home_url( '/' ) );
    exit;
}

$post_id = $post->ID;

$logo_id       = get_field( 'crs_logo',            $post_id );
$phone         = get_field( 'crs_phone',            $post_id );
$email         = get_field( 'crs_email',            $post_id );
$website       = get_field( 'crs_website',          $post_id );
$address       = get_field( 'crs_address',          $post_id );
$about         = get_field( 'crs_description',      $post_id );
$tagline       = get_field( 'crs_tagline',          $post_id );
$avg           = get_field( 'crs_review_avg',       $post_id );
$count         = get_field( 'crs_review_count',     $post_id );
$years         = get_field( 'crs_year_established', $post_id );
$tier          = get_field( 'crs_tier',             $post_id );
$services      = get_the_terms( $post_id, 'repair-service' );
$suburb_term   = get_the_terms( $post_id, 'au-suburb' );
$state_term    = get_the_terms( $post_id, 'au-state' );

$logo_url = $logo_id
    ? wp_get_attachment_image_url( $logo_id, 'thumbnail' )
    : CRS_URI . '/assets/images/logo-placeholder.png';

$business_name = $post->post_title;

/* ── Opening hours ────────────────────────────────────────────── */
$days_map = [
    'monday' => 'Monday', 'tuesday' => 'Tuesday', 'wednesday' => 'Wednesday',
    'thursday' => 'Thursday', 'friday' => 'Friday', 'saturday' => 'Saturday',
    'sunday' => 'Sunday',
];
$open_days = [];
foreach ( $days_map as $key => $label ) {
    $open  = get_field( "crs_hours_{$key}_open",   $post_id );
    $close = get_field( "crs_hours_{$key}_close",  $post_id );
    $closed= get_field( "crs_hours_{$key}_closed", $post_id );
    if ( $open && $close && ! $closed ) {
        $open_days[] = $label . ': ' . date( 'g:i a', strtotime( $open ) )
                     . ' – ' . date( 'g:i a', strtotime( $close ) );
    }
}

/* ── Location label ───────────────────────────────────────────── */
$location_label = '';
if ( $suburb_term && ! is_wp_error( $suburb_term ) ) {
    $location_label = $suburb_term[0]->name;
}
if ( $state_term && ! is_wp_error( $state_term ) ) {
    $abbr = get_term_meta( $state_term[0]->term_id, 'au_state_abbreviation', true );
    if ( $abbr ) $location_label .= ', ' . $abbr;
}
if ( ! $location_label ) $location_label = $address;

/* ── Breadcrumb data ──────────────────────────────────────────── */
$state_name  = ( $state_term  && ! is_wp_error( $state_term  ) ) ? $state_term[0]->name  : '';
$suburb_name = ( $suburb_term && ! is_wp_error( $suburb_term ) ) ? $suburb_term[0]->name : '';

/* ── Service taxonomy for checkboxes ─────────────────────────── */
$svc_parents = get_terms( [
    'taxonomy'   => 'repair-service',
    'parent'     => 0,
    'hide_empty' => false,
    'orderby'    => 'term_id',
    'order'      => 'ASC',
] );

get_header();
?>

<main class="enq-wrap py-4">
<div class="container px-3">

  <!-- ── Breadcrumb ───────────────────────────────────────────── -->
  <nav class="enq-breadcrumb mb-3">
    <a href="<?php echo esc_url( home_url( '/' ) ); ?>">Home</a>
    <i class="fa-solid fa-chevron-right"></i>
    <?php if ( $state_name ) : ?>
      <a href="<?php echo esc_url( home_url( '/computer-repairs/' . $state_term[0]->slug . '/' ) ); ?>"><?php echo esc_html( $state_name ); ?></a>
      <i class="fa-solid fa-chevron-right"></i>
    <?php endif; ?>
    <?php if ( $suburb_name ) : ?>
      <a href="<?php echo esc_url( get_term_link( $suburb_term[0] ) ); ?>"><?php echo esc_html( $suburb_name ); ?></a>
      <i class="fa-solid fa-chevron-right"></i>
    <?php endif; ?>
    <a href="<?php echo esc_url( get_permalink( $post_id ) ); ?>"><?php echo esc_html( $business_name ); ?></a>
    <i class="fa-solid fa-chevron-right"></i>
    <span class="current">Enquire</span>
  </nav>

  <!-- ── Business Hero ────────────────────────────────────────── -->
  <section class="enq-hero">
    <div class="row g-4 align-items-center">

      <div class="col-lg-8">
        <div class="d-flex gap-4 flex-column flex-sm-row align-items-sm-center">
          <div class="enq-hero-logo">
            <img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo esc_attr( $business_name ); ?>">
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
                <span><i class="fa-solid fa-location-dot"></i> <?php echo esc_html( $location_label ); ?></span>
              <?php endif; ?>
              <?php if ( ! empty( $open_days ) ) : ?>
                <span><i class="fa-solid fa-clock"></i> <?php echo esc_html( $open_days[0] ); ?></span>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>

      <div class="col-lg-4">
        <div class="enq-trust">
          <div class="enq-trust-item">
            <span class="tick"><i class="fa-solid fa-check"></i></span>
            <div>
              <h4>Local &amp; Trusted</h4>
              <p><?php echo $years ? esc_html( 'In business since ' . $years . '.' ) : 'Serving the local community.'; ?></p>
            </div>
          </div>
          <div class="enq-trust-item">
            <span class="tick"><i class="fa-solid fa-check"></i></span>
            <div><h4>Fast Response</h4><p>We aim to respond to all enquiries within 1 hour.</p></div>
          </div>
          <div class="enq-trust-item">
            <span class="tick"><i class="fa-solid fa-check"></i></span>
            <div><h4>No Obligation</h4><p>Send an enquiry. It's free and without obligation.</p></div>
          </div>
        </div>
      </div>

    </div>
  </section>

  <!-- ── Body Grid ─────────────────────────────────────────────── -->
  <div class="row g-4 mt-1">

    <!-- LEFT: Custom Enquiry Form -->
    <div class="col-lg-7">
      <div class="enq-card">
        <h2 class="enq-h">Send an Enquiry</h2>
        <p class="enq-sub">Complete the form below and <strong><?php echo esc_html( $business_name ); ?></strong> will get back to you shortly.</p>

        <form id="crsEnquiryForm" enctype="multipart/form-data" novalidate>

          <?php wp_nonce_field( 'crs_enquiry_nonce', 'crs_nonce' ); ?>
          <input type="hidden" name="business_id"         value="<?php echo esc_attr( $post_id ); ?>">
          <input type="hidden" name="your-suburb"         id="crsSuburb">
          <input type="hidden" name="your-region"         id="crsRegion">
          <input type="hidden" name="your-state"          id="crsState">
          <input type="hidden" name="your-contact-method" id="crsContactMethod" value="Phone">

          <div class="row g-3">

            <!-- Name -->
            <div class="col-md-6">
              <label class="enq-label">Your Name <span class="enq-req">*</span></label>
              <input type="text" name="your-name" class="enq-input" placeholder="Enter your full name" required>
            </div>

            <!-- Email -->
            <div class="col-md-6">
              <label class="enq-label">Your Email <span class="enq-req">*</span></label>
              <input type="email" name="your-email" class="enq-input" placeholder="Enter your email address" required>
            </div>

            <!-- Phone -->
            <div class="col-md-6">
              <label class="enq-label">Your Phone Number <span class="enq-req">*</span></label>
              <input type="tel" name="your-phone" class="enq-input" placeholder="Enter your phone number" required>
            </div>

            <!-- Postcode -->
            <div class="col-md-6">
              <label class="enq-label">Suburb</label>
              <input type="text" name="your-postcode" id="crsPostcode" class="enq-input" maxlength="4" placeholder="Enter postcode e.g. 3064">
              <div id="crsPostcodeStatus" style="font-size:12px;color:#6b7785;margin-top:4px;min-height:16px;"></div>
            </div>

            <!-- Suburb dropdown (shown after postcode lookup) -->
            <div class="col-12" id="crsSuburbRow" style="display:none;">
              <label class="enq-label">Select Suburb</label>
              <select id="crsSuburbSelect" class="enq-input">
                <option value="">— Select your suburb —</option>
              </select>
            </div>
            <!-- Services — only what this business offers, grouped by parent -->
            <div class="col-12">
            <label class="enq-label">What do you need help with?</label>
            <?php
            // $services = get_the_terms( $post_id, 'repair-service' ) — already fetched at top of file
            // Group the business's own services by their parent term
            $business_services_grouped = [];

            if ( $services && ! is_wp_error( $services ) ) {
                foreach ( $services as $svc ) {
                    // Skip parent-level terms (parent = 0), only show child services
                    if ( (int) $svc->parent === 0 ) continue;

                    $parent_term = get_term( $svc->parent, 'repair-service' );
                    if ( ! $parent_term || is_wp_error( $parent_term ) ) {
                        // No parent — list under generic group
                        $group_name = 'Other Services';
                    } else {
                        $group_name = $parent_term->name;
                    }
                    $business_services_grouped[ $group_name ][] = $svc;
                }
            }

            if ( ! empty( $business_services_grouped ) ) : ?>
            <div class="enq-svc-wrap">
                <?php foreach ( $business_services_grouped as $group_name => $group_services ) : ?>
                <div class="enq-svc-group">
                <div class="enq-svc-group-title"><?php echo esc_html( $group_name ); ?></div>
                <div class="enq-svc-list">
                    <?php foreach ( $group_services as $svc ) : ?>
                    <label class="enq-svc-item">
                    <input type="checkbox" name="service[]" value="<?php echo esc_attr( $svc->name ); ?>">
                    <span><?php echo esc_html( $svc->name ); ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else : ?>
            <p style="font-size:13px;color:#6b7785;padding:12px;background:#f8fafd;border-radius:8px;margin:0;">
                No specific services listed — describe your issue in the message below.
            </p>
            <?php endif; ?>
            </div>

            <!-- Message -->
            <div class="col-12">
              <label class="enq-label">Please describe your issue or request <span class="enq-req">*</span></label>
              <textarea name="your-message" class="enq-input" rows="5" placeholder="Please provide as much detail as possible about the issue or service you need..." required></textarea>
              <p style="font-size:12px;color:#6b7785;margin-top:4px;">The more details you provide, the better we can help.</p>
            </div>

            <!-- Contact method -->
            <div class="col-12">
              <label class="enq-label">Preferred Contact Method</label>
              <div class="enq-toggle">
                <button type="button" class="enq-toggle-btn active" data-value="Phone">
                  <i class="fa-solid fa-phone"></i> Phone
                </button>
                <button type="button" class="enq-toggle-btn" data-value="Email">
                  <i class="fa-solid fa-envelope"></i> Email
                </button>
              </div>
            </div>

            <!-- Preferred time -->
            <div class="col-12">
              <label class="enq-label">Preferred Time to Contact You</label>
              <select name="your-time" class="enq-input">
                <option value="">Select a time</option>
                <option>Morning (8am – 12pm)</option>
                <option>Afternoon (12pm – 5pm)</option>
                <option>Evening (5pm – 8pm)</option>
                <option>Anytime</option>
              </select>
            </div>

            <!-- Photo upload -->
            <div class="col-12">
              <label class="enq-label">Upload Photos <span style="font-weight:400;color:#6b7785;">(optional)</span></label>
              <div id="crsZone" class="enq-upload-zone">
                <div id="crsEmpty">
                  <svg width="38" height="38" viewBox="0 0 24 24" fill="none" stroke="#1565d8" stroke-width="1.5" style="margin-bottom:10px;display:block;margin-left:auto;margin-right:auto">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 16V8m0 0-3 3m3-3 3 3M6.5 19a4.5 4.5 0 0 1 0-9h.5A5 5 0 0 1 17 13"/>
                  </svg>
                  <p style="font-size:14px;font-weight:600;color:#1b2430;margin:0 0 4px;">
                    Drag &amp; drop files here or
                    <span id="crsTrigger" style="color:#1565d8;text-decoration:underline;cursor:pointer;">click to upload</span>
                  </p>
                  <p style="font-size:12px;color:#6b7785;margin:0;">JPG, PNG or PDF &nbsp;·&nbsp; max 10 MB each &nbsp;·&nbsp; up to 3 files</p>
                </div>
                <div id="crsGrid" style="display:none;grid-template-columns:repeat(3,1fr);gap:10px;"></div>
              </div>
              <div id="crsMsg" style="font-size:12px;margin-top:6px;min-height:16px;"></div>
              <div id="crsCnt" style="font-size:11px;color:#6b7785;text-align:right;margin-top:2px;"></div>
              <!-- Real file input — appended to FormData by JS, not submitted natively -->
              <input type="file" id="crsFileReal" accept="image/jpeg,image/png,application/pdf" multiple style="display:none;" aria-hidden="true">
            </div>

            <!-- Acceptance -->
            <div class="col-12">
              <label style="display:flex;align-items:flex-start;gap:10px;font-size:13px;color:#1b2430;cursor:pointer;">
                <input type="checkbox" name="acceptance-check" value="1" style="margin-top:2px;accent-color:#1565d8;width:15px;height:15px;flex-shrink:0;" required>
                <span>
                  I agree to the
                  <a href="/privacy-policy/" target="_blank" style="color:#1565d8;">Privacy Policy</a>
                  and
                  <a href="/terms/" target="_blank" style="color:#1565d8;">Terms of Use</a>
                </span>
              </label>
            </div>

            <!-- Form-level message (success / error) -->
            <div class="col-12">
              <div id="crsFormMsg" style="display:none;padding:14px 16px;border-radius:8px;font-size:14px;"></div>
            </div>

            <!-- Submit -->
            <div class="col-12">
              <button type="submit" id="crsSubmitBtn" style="width:100%;padding:14px;background:#1565d8;color:#fff;border:none;border-radius:8px;font-size:15px;font-weight:600;cursor:pointer;font-family:inherit;transition:.2s;">
                <i class="fa-solid fa-paper-plane" style="margin-right:8px;"></i>Send Enquiry
              </button>
              <p style="font-size:12px;color:#6b7785;text-align:center;margin-top:8px;">
                <i class="fa-solid fa-lock" style="margin-right:4px;"></i>
                Your information is secure and will only be shared with this business.
              </p>
            </div>

          </div><!-- /row -->
        </form>

      </div>
    </div>

    <!-- RIGHT: Sidebar -->
    <div class="col-lg-5">

      <?php if ( $about || $tagline ) : ?>
      <div class="enq-card">
        <h3 class="enq-side-title">About <?php echo esc_html( $business_name ); ?></h3>
        <p class="enq-about-text"><?php echo esc_html( $tagline ?: wp_trim_words( wp_strip_all_tags( $about ), 40 ) ); ?></p>
        <a href="<?php echo esc_url( get_permalink( $post_id ) ); ?>" class="enq-link">
          View full profile <i class="fa-solid fa-arrow-right"></i>
        </a>
      </div>
      <?php endif; ?>

      <div class="enq-card">
        <h3 class="enq-side-title">Contact Details</h3>
        <ul class="enq-contact">
          <?php if ( $phone ) : ?>
            <li class="phone-number-box">
              <i class="fa-solid fa-phone"></i>
              <a href="tel:<?php echo esc_attr( preg_replace( '/\D/', '', $phone ) ); ?>"><?php echo esc_html( $phone ); ?></a>
            </li>
          <?php endif; ?>
          <?php if ( $email && in_array( $tier, [ 'featured', 'premium' ], true ) ) : ?>
            <li>
              <i class="fa-solid fa-envelope"></i>
              <a href="mailto:<?php echo esc_attr( $email ); ?>"><?php echo esc_html( $email ); ?></a>
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
            <li><i class="fa-solid fa-location-dot"></i><span><?php echo esc_html( $address ); ?></span></li>
          <?php endif; ?>
        </ul>
      </div>

      <?php if ( $services && ! is_wp_error( $services ) ) : ?>
      <div class="enq-card">
        <h3 class="enq-side-title">We Specialise In</h3>
        <ul class="enq-spec">
          <?php foreach ( array_slice( $services, 0, 8 ) as $svc ) : ?>
            <li><i class="fa-solid fa-circle-check"></i><?php echo esc_html( $svc->name ); ?></li>
          <?php endforeach; ?>
        </ul>
        <?php if ( count( $services ) > 8 ) : ?>
          <a href="<?php echo esc_url( get_permalink( $post_id ) ); ?>" class="enq-link">
            View all services (<?php echo count( $services ); ?>) <i class="fa-solid fa-arrow-right"></i>
          </a>
        <?php endif; ?>
      </div>
      <?php endif; ?>

      <div class="enq-card">
        <h3 class="enq-side-title">Why Choose <?php echo esc_html( $business_name ); ?>?</h3>
        <div class="enq-why"><div class="enq-why-ico"><i class="fa-solid fa-user-gear"></i></div><div><h4>Experienced Technicians</h4><p>Qualified and experienced professionals.</p></div></div>
        <div class="enq-why"><div class="enq-why-ico"><i class="fa-solid fa-gear"></i></div><div><h4>Quality Parts</h4><p>We use high-quality parts with warranty.</p></div></div>
        <div class="enq-why"><div class="enq-why-ico"><i class="fa-solid fa-tag"></i></div><div><h4>Affordable Pricing</h4><p>Upfront, competitive pricing. No hidden fees.</p></div></div>
        <?php if ( $location_label ) : ?>
        <div class="enq-why"><div class="enq-why-ico"><i class="fa-solid fa-location-dot"></i></div><div><h4>Local Support</h4><p>Proudly supporting <?php echo esc_html( $location_label ); ?>.</p></div></div>
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
          [ 'Send Enquiry',      'Fill out the form and send your enquiry to the business.' ],
          [ 'Business Responds', esc_html( $business_name ) . ' will contact you to discuss.' ],
          [ 'Get Help',          'Receive a quote or book a service at a time that suits you.' ],
          [ 'Problem Solved',    'Get your computer fixed and back up and running!' ],
      ];
      foreach ( $steps as $i => $step ) : ?>
        <div class="col-md-6 col-lg-3 position-relative">
          <?php if ( $i > 0 ) : ?><i class="fa-solid fa-chevron-right enq-step-arrow position-absolute"></i><?php endif; ?>
          <div class="enq-step">
            <div class="enq-step-num"><?php echo $i + 1; ?></div>
            <div><h4><?php echo esc_html( $step[0] ); ?></h4><p><?php echo esc_html( $step[1] ); ?></p></div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

</div>
</main>

<?php get_footer(); ?>
<script>
document.addEventListener('DOMContentLoaded', function () {

/* ── Inline styles ───────────────────────────────────────────── */
var s = document.createElement('style');
s.textContent =
    '.enq-label{display:block;font-size:13px;font-weight:600;color:#1b2430;margin-bottom:6px}' +
    '.enq-req{color:#dc3545}' +
    '.enq-input{width:100%;padding:11px 14px;border:1px solid #e7ecf3;border-radius:8px;font-size:14px;color:#1b2430;background:#fff;box-sizing:border-box;transition:.2s;font-family:inherit}' +
    '.enq-input:focus{outline:none;border-color:#1565d8;box-shadow:0 0 0 3px rgba(21,101,216,.1)}' +
    'textarea.enq-input{resize:vertical;min-height:120px}' +
    /* Service checkboxes */
    '.enq-svc-wrap{border:1px solid #e7ecf3;border-radius:8px;overflow:hidden}' +
    '.enq-svc-group{padding:14px 16px;border-bottom:1px solid #e7ecf3}' +
    '.enq-svc-group:last-child{border-bottom:none}' +
    '.enq-svc-group-title{font-size:12px;font-weight:700;color:#0a2647;margin-bottom:10px;text-transform:uppercase;letter-spacing:.4px}' +
    '.enq-svc-list{display:grid;grid-template-columns:repeat(auto-fill,minmax(175px,1fr));gap:6px}' +
    '.enq-svc-item{display:flex;align-items:center;gap:8px;font-size:13px;color:#1b2430;cursor:pointer;padding:6px 8px;border-radius:6px;transition:.15s}' +
    '.enq-svc-item:hover{background:#eaf1fc}' +
    '.enq-svc-item input[type="checkbox"]{accent-color:#1565d8;width:15px;height:15px;flex-shrink:0;cursor:pointer;margin:0}' +
    '.enq-svc-item input:checked + span{color:#1565d8;font-weight:600}' +
    /* Upload zone */
    '.enq-upload-zone{border:2px dashed #c8cdd6;border-radius:12px;padding:28px 20px;text-align:center;background:#f8fafd;cursor:pointer;transition:.15s;box-sizing:border-box}' +
    '.enq-upload-zone.drag{border-color:#1565d8;background:#eaf1fc}' +
    '.enq-upload-zone.filled{padding:14px;cursor:default}' +
    '#crsGrid{display:none;grid-template-columns:repeat(3,1fr);gap:10px}' +
    '.crs-pcell{position:relative;border-radius:8px;overflow:hidden;border:1px solid #e7ecf3;background:#f0f4f9;aspect-ratio:1/1}' +
    '.crs-pcell img{width:100%;height:100%;object-fit:cover;display:block}' +
    '.crs-pdf-cell{display:flex;flex-direction:column;align-items:center;justify-content:center;height:100%;gap:4px;padding:8px}' +
    '.crs-rm{position:absolute;top:4px;right:4px;width:22px;height:22px;border-radius:50%;background:rgba(0,0,0,.55);border:none;cursor:pointer;color:#fff;font-size:16px;line-height:22px;text-align:center;padding:0}' +
    '.crs-rm:hover{background:rgba(200,30,30,.85)}' +
    '.crs-addmore{border:2px dashed #c8cdd6;border-radius:8px;aspect-ratio:1/1;display:flex;flex-direction:column;align-items:center;justify-content:center;cursor:pointer;color:#6b7785;font-size:11px;gap:4px;background:transparent;transition:.15s}' +
    '.crs-addmore:hover{border-color:#1565d8;color:#1565d8}';
document.head.appendChild(s);

/* ── Contact method toggle ───────────────────────────────────── */
document.addEventListener('click', function(e) {
    var btn = e.target.closest('.enq-toggle-btn');
    if (!btn) return;
    document.querySelectorAll('.enq-toggle-btn').forEach(function(b){ b.classList.remove('active'); });
    btn.classList.add('active');
    document.getElementById('crsContactMethod').value = btn.dataset.value;
});

/* ── Postcode → suburb cascade ───────────────────────────────── */
(function(){
    var pcInput    = document.getElementById('crsPostcode');
    var pcStatus   = document.getElementById('crsPostcodeStatus');
    var suburbRow  = document.getElementById('crsSuburbRow');
    var suburbSel  = document.getElementById('crsSuburbSelect');
    var hidSuburb  = document.getElementById('crsSuburb');
    var hidRegion  = document.getElementById('crsRegion');
    var hidState   = document.getElementById('crsState');
    var timer;
    if (!pcInput) return;

    pcInput.addEventListener('input', function(){
        var pc = this.value.trim();
        clearTimeout(timer);
        hidSuburb.value = ''; hidRegion.value = ''; hidState.value = '';
        suburbRow.style.display = 'none';
        suburbSel.innerHTML = '<option value="">— Select your suburb —</option>';
        pcStatus.textContent = '';
        if (!/^\d{4}$/.test(pc)) return;
        pcStatus.textContent = 'Looking up…';
        timer = setTimeout(function(){
            var ajaxUrl = (typeof crsEnquiry !== 'undefined') ? crsEnquiry.ajaxUrl : '/wp-admin/admin-ajax.php';
            fetch(ajaxUrl + '?action=crs_get_suburbs_by_postcode&postcode=' + pc)
            .then(function(r){ return r.json(); })
            .then(function(res){
                pcStatus.textContent = '';
                var suburbs = (res.success && res.data.suburbs) ? res.data.suburbs : [];
                if (!suburbs.length){ pcStatus.textContent = 'No suburbs found for this postcode.'; return; }
                suburbs.forEach(function(s){
                    var o = document.createElement('option');
                    o.value = s.name;
                    o.textContent = s.name + (s.state_abbr ? ' ' + s.state_abbr : '');
                    o.dataset.region = s.region || '';
                    o.dataset.state  = s.state  || '';
                    suburbSel.appendChild(o);
                });
                suburbRow.style.display = '';
                if (suburbs.length === 1){
                    suburbSel.value = suburbs[0].name;
                    suburbSel.dispatchEvent(new Event('change'));
                }
            })
            .catch(function(){ pcStatus.textContent = ''; });
        }, 500);
    });

    suburbSel.addEventListener('change', function(){
        var opt = this.options[this.selectedIndex];
        hidSuburb.value = opt.value;
        hidRegion.value = opt.dataset.region || '';
        hidState.value  = opt.dataset.state  || '';
    });
})();

/* ── Upload zone ─────────────────────────────────────────────── */
var uploadFiles = []; // module-scoped so form submit can access

(function(){
    var zone    = document.getElementById('crsZone');
    var empty   = document.getElementById('crsEmpty');
    var grid    = document.getElementById('crsGrid');
    var trigger = document.getElementById('crsTrigger');
    var msgEl   = document.getElementById('crsMsg');
    var cntEl   = document.getElementById('crsCnt');
    if (!zone) return;

    var MAX = 3, MAXMB = 10;

    function fmt(b){ return b < 1048576 ? Math.round(b/1024)+' KB' : (b/1048576).toFixed(1)+' MB'; }

    function setMsg(text, type){
        msgEl.textContent = text;
        msgEl.style.color = type === 'err' ? '#dc3545' : type === 'ok' ? '#198754' : '#6b7785';
    }

    function render(){
        grid.innerHTML = '';
        if (!uploadFiles.length){
            empty.style.display = ''; grid.style.display = 'none';
            zone.classList.remove('filled'); cntEl.textContent = ''; return;
        }
        empty.style.display = 'none'; grid.style.display = 'grid';
        zone.classList.add('filled');

        uploadFiles.forEach(function(f, i){
            var cell = document.createElement('div'); cell.className = 'crs-pcell';
            if (f.type === 'application/pdf'){
                cell.innerHTML =
                    '<div class="crs-pdf-cell">' +
                    '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#dc3545" stroke-width="1.5">' +
                    '<path stroke-linecap="round" d="M19 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2z"/>' +
                    '<path stroke-linecap="round" d="M9 7h6M9 12h6M9 17h4"/></svg>' +
                    '<span style="font-size:10px;color:#555;text-align:center;word-break:break-all;line-height:1.3">' + f.name + '</span>' +
                    '<span style="font-size:10px;color:#6b7785">' + fmt(f.size) + '</span></div>';
            } else {
                var img = document.createElement('img');
                img.src = URL.createObjectURL(f); img.alt = f.name; img.loading = 'lazy';
                cell.appendChild(img);
            }
            var btn = document.createElement('button');
            btn.type = 'button'; btn.className = 'crs-rm'; btn.innerHTML = '&times;';
            btn.setAttribute('aria-label', 'Remove ' + f.name);
            btn.onclick = (function(idx){ return function(){ uploadFiles.splice(idx,1); render(); setMsg(''); }; })(i);
            cell.appendChild(btn);
            grid.appendChild(cell);
        });

        if (uploadFiles.length < MAX){
            var add = document.createElement('div');
            add.className = 'crs-addmore';
            add.setAttribute('role','button'); add.setAttribute('tabindex','0');
            add.innerHTML = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg><span>Add more</span>';
            add.onclick = openPicker;
            add.onkeydown = function(e){ if(e.key==='Enter'||e.key===' ') openPicker(); };
            grid.appendChild(add);
        }
        cntEl.textContent = uploadFiles.length + ' of ' + MAX + ' files';
    }

    function validate(f){
        var ok = ['image/jpeg','image/jpg','image/png','application/pdf'];
        if (ok.indexOf(f.type) === -1) return 'Only JPG, PNG or PDF files are allowed.';
        if (f.size > MAXMB * 1048576) return '"' + f.name + '" is too large (' + fmt(f.size) + '). Max ' + MAXMB + ' MB.';
        return null;
    }

    function addFiles(list){
        var added = 0, lastErr = '';
        for (var i = 0; i < list.length; i++){
            if (uploadFiles.length >= MAX){ lastErr = 'Maximum 3 files allowed.'; break; }
            var err = validate(list[i]);
            if (err){ lastErr = err; continue; }
            if (uploadFiles.some(function(x){ return x.name===list[i].name && x.size===list[i].size; })) continue;
            uploadFiles.push(list[i]); added++;
        }
        if (lastErr) setMsg(lastErr,'err');
        else if (added) setMsg(added+' file'+(added>1?'s':'')+' added','ok');
        render();
    }

    function openPicker(){
        var inp = document.createElement('input');
        inp.type = 'file'; inp.accept = 'image/jpeg,image/png,application/pdf';
        inp.multiple = uploadFiles.length < MAX - 1;
        inp.onchange = function(){ addFiles(inp.files); };
        inp.click();
    }

    trigger.addEventListener('click', function(e){ e.stopPropagation(); openPicker(); });
    zone.addEventListener('click', function(){ if(!zone.classList.contains('filled')) openPicker(); });
    zone.addEventListener('dragover',  function(e){ e.preventDefault(); zone.classList.add('drag'); });
    zone.addEventListener('dragleave', function(){ zone.classList.remove('drag'); });
    zone.addEventListener('drop', function(e){ e.preventDefault(); zone.classList.remove('drag'); addFiles(e.dataTransfer.files); });
    render();
})();

/* ── Form submission ─────────────────────────────────────────── */
document.getElementById('crsEnquiryForm').addEventListener('submit', function(e){
    e.preventDefault();

    var form    = this;
    var btn     = document.getElementById('crsSubmitBtn');
    var formMsg = document.getElementById('crsFormMsg');
    var ajaxUrl = (typeof crsEnquiry !== 'undefined') ? crsEnquiry.ajaxUrl : '/wp-admin/admin-ajax.php';
    var nonce   = (typeof crsEnquiry !== 'undefined') ? crsEnquiry.nonce   : '';

    btn.disabled = true;
    btn.textContent = 'Sending…';
    formMsg.style.display = 'none';

    /* Build FormData from the form */
    var fd = new FormData(form);
    fd.append('action',    'crs_submit_enquiry');
    fd.append('crs_nonce', nonce);

    /* Append photos directly from our JS array — bypasses any DataTransfer issues */
    fd.delete('photos[]');
    uploadFiles.forEach(function(f){ fd.append('photos[]', f, f.name); });

    fetch(ajaxUrl, {
        method: 'POST',
        body: fd,
        credentials: 'same-origin'
    })
    .then(function(r){ return r.json(); })
    .then(function(res){
        if (res.success){
            /* Success state */
            formMsg.style.cssText = 'display:block;padding:14px 16px;border-radius:8px;font-size:14px;background:#d1e7dd;color:#0a3622;border:1px solid #a3cfbb;margin-top:8px;';
            formMsg.textContent = res.data.message;
            form.reset();
            /* Reset upload zone */
            uploadFiles.length = 0;
            document.getElementById('crsGrid').innerHTML = '';
            document.getElementById('crsGrid').style.display = 'none';
            document.getElementById('crsEmpty').style.display = '';
            document.getElementById('crsZone').classList.remove('filled');
            document.getElementById('crsCnt').textContent = '';
            document.getElementById('crsMsg').textContent = '';
            /* Reset contact method to default */
            document.getElementById('crsContactMethod').value = 'Phone';
            document.querySelectorAll('.enq-toggle-btn').forEach(function(b){ b.classList.remove('active'); });
            var firstToggle = document.querySelector('.enq-toggle-btn');
            if (firstToggle) firstToggle.classList.add('active');
            /* Hide suburb row */
            var suburbRow = document.getElementById('crsSuburbRow');
            if (suburbRow) suburbRow.style.display = 'none';
        } else {
            formMsg.style.cssText = 'display:block;padding:14px 16px;border-radius:8px;font-size:14px;background:#f8d7da;color:#58151c;border:1px solid #f1aeb5;margin-top:8px;';
            formMsg.textContent = res.data.message || 'Something went wrong. Please try again.';
        }
    })
    .catch(function(){
        formMsg.style.cssText = 'display:block;padding:14px 16px;border-radius:8px;font-size:14px;background:#f8d7da;color:#58151c;border:1px solid #f1aeb5;margin-top:8px;';
        formMsg.textContent = 'Connection error. Please check your internet and try again.';
    })
    .finally(function(){
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-paper-plane" style="margin-right:8px;"></i>Send Enquiry';
        formMsg.scrollIntoView({ behavior:'smooth', block:'nearest' });
    });
});

}); // end DOMContentLoaded
</script>