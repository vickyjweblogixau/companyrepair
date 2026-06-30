<?php
/**
 * Shortcodes for Business Owner Dashboard
 */
if (!defined('ABSPATH')) exit;

// ============================================
// SIGNUP FORM [business_owner_signup]
// ============================================

// Dynamic data for signup form fields
// ============================================
// GET ACTIVE SUBSCRIPTION PLAN FROM CPT
// Reads from crs_sub_plan post type (set by admin)
// Falls back to BOD_LISTING_AMOUNT_DISPLAY if no plan found
// ============================================
function bod_get_active_signup_plan() {
    // Use the explicitly-designated default signup plan (checkbox on the
    // plan edit screen) instead of "oldest active plan" — so price changes
    // and new plans don't silently get ignored.
    $default_plan_id = (int) get_option( 'bod_default_signup_plan_id' );
    $plans            = [];

    if ( $default_plan_id ) {
        $plan_post = get_post( $default_plan_id );
        if ( $plan_post
            && $plan_post->post_type === 'crs_sub_plan'
            && $plan_post->post_status === 'publish'
            && get_post_meta( $plan_post->ID, '_plan_status', true ) === 'active'
        ) {
            $plans = [ $plan_post ];
        }
    }

    // Fallback: no default designated (or it's been deactivated) — use the
    // most recently updated active plan, not the oldest one.
    if ( empty( $plans ) ) {
        $plans = get_posts( [
            'post_type'      => 'crs_sub_plan',
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'orderby'        => 'modified',
            'order'          => 'DESC',
            'meta_query'     => [
                [
                    'key'   => '_plan_status',
                    'value' => 'active',
                ],
            ],
        ] );
    }

    if ( empty( $plans ) ) {
        // No plan found anywhere — fallback to options
        return [
            'id'          => 0,
            'name'        => get_option( 'bod_plan_name', 'Simple Subscription' ),
            'price'       => (float) get_option( 'bod_listing_amount_display', 20.00 ),
            'tax_type'    => get_option( 'bod_plan_gst_type', 'exclude' ),
            'tax_rate'    => (float) get_option( 'bod_plan_gst_rate', 10 ),
            'charge'      => (float) get_option( 'bod_listing_amount_display', 20.00 ),
            'gst'         => 0,
            'base'        => (float) get_option( 'bod_listing_amount_display', 20.00 ),
            'features'    => [],
            'duration'    => '30',
        ];
    }

    $plan     = $plans[0];
    $price    = (float) get_post_meta( $plan->ID, '_plan_price',         true );
    $tax_rate = (float) get_post_meta( $plan->ID, '_plan_tax_rate',      true );
    $tax_type =         get_post_meta( $plan->ID, '_plan_tax_type',      true ) ?: 'exclude';
    $charge   = (float) get_post_meta( $plan->ID, '_plan_charge_amount', true );
    $gst      = (float) get_post_meta( $plan->ID, '_plan_tax_amount',    true );
    $duration =         get_post_meta( $plan->ID, '_plan_duration',      true ) ?: '30';
    $features_raw = get_post_meta( $plan->ID, '_plan_features', true ) ?: '';
    $features = array_filter( array_map( 'trim', explode( "\n", $features_raw ) ) );

    // Calculate if not stored yet
    if ( ! $charge && $price ) {
        if ( $tax_type === 'exclude' ) {
            $charge = round( $price * ( 1 + $tax_rate / 100 ), 2 );
            $gst    = round( $charge - $price, 2 );
        } else {
            $charge = $price;
            $gst    = round( $price - ( $price / ( 1 + $tax_rate / 100 ) ), 2 );
        }
    }
    $base = round( $charge - $gst, 2 );

    return [
        'id'       => $plan->ID,
        'name'     => $plan->post_title,
        'price'    => $price,
        'tax_type' => $tax_type,
        'tax_rate' => $tax_rate,
        'charge'   => $charge,
        'gst'      => $gst,
        'base'     => $base,
        'features' => array_values( $features ),
        'duration' => $duration,
    ];
}
// Dynamic data for signup form fields
function bod_get_service_radius_options() {
    return [
        ''              => 'Select service radius',
        'within_10km'   => 'Within 10 km',
        'within_25km'   => 'Within 25 km',
        'within_50km'   => 'Within 50 km',
        'statewide'     => 'Statewide',
        'australia_wide'=> 'Australia Wide',
    ];
}

function bod_get_services_list() {
    // Load dynamically from the repair-service taxonomy (parent = category, children = services)
    if ( taxonomy_exists( 'repair-service' ) ) {
        $parent_terms = get_terms( [
            'taxonomy'   => 'repair-service',
            'parent'     => 0,
            'hide_empty' => false,
            'orderby'    => 'term_id',
            'order'      => 'ASC',
        ] );

        if ( ! is_wp_error( $parent_terms ) && ! empty( $parent_terms ) ) {
            $services = [];
            foreach ( $parent_terms as $parent ) {
                $children = get_terms( [
                    'taxonomy'   => 'repair-service',
                    'parent'     => $parent->term_id,
                    'hide_empty' => false,
                    'orderby'    => 'name',
                    'order'      => 'ASC',
                ] );
                if ( ! is_wp_error( $children ) && ! empty( $children ) ) {
                    $services[ $parent->name ] = [];
                    foreach ( $children as $child ) {
                        $services[ $parent->name ][ $child->slug ] = $child->name;
                    }
                }
            }
            if ( ! empty( $services ) ) {
                return $services;
            }
        }
    }

    // Fallback: hardcoded list used before taxonomy is seeded
    return [
        'Consumer Repair Services' => [
            'computer-repairs'         => 'Computer Repairs',
            'laptop-repairs'           => 'Laptop Repairs',
            'macbook-repairs'          => 'MacBook Repairs',
            'desktop-computer-repairs' => 'Desktop Computer Repairs',
            'gaming-pc-repairs'        => 'Gaming PC Repairs',
            'data-recovery'            => 'Data Recovery',
            'virus-removal'            => 'Virus Removal',
            'malware-removal'          => 'Malware Removal',
            'printer-repairs'          => 'Printer Repairs',
            'printer-setup'            => 'Printer Setup',
            'screen-replacement'       => 'Screen Replacement',
            'battery-replacement'      => 'Battery Replacement',
            'wifi-troubleshooting'     => 'WiFi Troubleshooting',
            'software-installation'    => 'Software Installation',
            'computer-upgrades'        => 'Computer Upgrades',
        ],
        'Business IT Services' => [
            'business-it-support'      => 'Business IT Support',
            'microsoft-365'            => 'Microsoft 365 Support',
            'email-support'            => 'Email Support',
            'network-support'          => 'Network Support',
            'server-support'           => 'Server Support',
            'managed-it-services'      => 'Managed IT Services',
            'remote-it-support'        => 'Remote IT Support',
            'cloud-backup-services'    => 'Cloud Backup Services',
            'cyber-security-services'  => 'Cyber Security Services',
            'business-wifi-support'    => 'Business WiFi Support',
            'it-help-desk-services'    => 'IT Help Desk Services',
            'microsoft-teams-support'  => 'Microsoft Teams Support',
            'sharepoint-support'       => 'SharePoint Support',
            'cloud-migration-services' => 'Cloud Migration Services',
        ],
    ];
}

add_shortcode('business_owner_signup', 'bod_render_signup_form');
function bod_render_signup_form($atts) {
    if (is_user_logged_in() && bod_is_business_owner()) {
        return '<div style="padding:16px;background:#eaf1fc;border-left:4px solid #1565d8;border-radius:6px;font-family:Poppins,sans-serif;">
            <p style="margin:0;">You are already registered as a business owner. <a href="' . home_url('/business-owner-dashboard/') . '" style="color:#1565d8;font-weight:600;">Go to Dashboard &rarr;</a></p>
        </div>';
    }

    $plan     = bod_get_active_signup_plan();
    $amount   = $plan['charge'] > 0 ? $plan['charge'] : BOD_LISTING_AMOUNT_DISPLAY;
    $services = bod_get_services_list();
    $radii    = bod_get_service_radius_options();

    // Enqueue Bootstrap + FontAwesome if not already loaded
    wp_enqueue_style('bod-bootstrap',    'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css', [], '5.3.3');
    wp_enqueue_style('bod-fontawesome',  'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css', [], '6.5.2');
    wp_enqueue_style('bod-poppins',      'https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap', [], null);
    wp_enqueue_script('bod-bootstrap-js','https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js', [], '5.3.3', true);

    ob_start();
    ?>
    <style>
    :root {
        --crs-navy: #0a2647; --crs-blue: #1565d8; --crs-blue-dark: #0d4fb8;
        --crs-ink: #1b2430;  --crs-muted: #6b7785; --crs-line: #e7ecf3;
        --crs-light-blue: #eaf1fc;
    }
    .bod-su * { font-family: "Poppins", system-ui, sans-serif; box-sizing: border-box; }
    .bod-su a { text-decoration: none; }

    /* Panels */
    .su-panel { background: #fff; border: 1px solid var(--crs-line); border-radius: 16px; padding: 32px; height: 100%; }
    .su-panel-title { font-size: clamp(20px,2vw,26px); font-weight: 700; color: var(--crs-navy); margin-bottom: 8px; line-height: 1.2; }
    .su-panel-sub { color: var(--crs-muted); font-size: 14px; line-height: 1.6; margin-bottom: 28px; }

    /* Form fields */
    .su-field { display: flex; gap: 14px; margin-bottom: 20px; align-items: flex-start; }
    .su-field-ico { width: 36px; height: 36px; border-radius: 9px; background: var(--crs-light-blue);
        color: var(--crs-blue); display: flex; align-items: center; justify-content: center;
        font-size: 15px; flex: none; margin-top: 28px; }
    .su-field-body { flex: 1; }
    .su-label { display: block; font-weight: 600; font-size: 13.5px; color: var(--crs-ink); margin-bottom: 5px; }
    .su-label .req { color: #e53e3e; }
    .su-input, .su-select, .su-textarea {
        width: 100%; padding: 11px 14px; border: 1px solid var(--crs-line); border-radius: 8px;
        font-size: 14px; color: var(--crs-ink); background: #fff; transition: .2s;
        font-family: "Poppins", sans-serif;
    }
    .su-input:focus, .su-select:focus, .su-textarea:focus {
        outline: none; border-color: var(--crs-blue); box-shadow: 0 0 0 3px rgba(21,101,216,.1);
    }
    .su-textarea { resize: vertical; min-height: 120px; }
    .su-hint { font-size: 12.5px; color: var(--crs-muted); margin-bottom: 6px; }
    .su-counter { font-size: 12px; color: var(--crs-muted); text-align: right; margin-top: 4px; }

    /* Services */
    .su-services-head { font-size: 15px; font-weight: 700; color: var(--crs-navy); margin: 0 0 4px; }
    .su-services-sub  { font-size: 13px; color: var(--crs-muted); margin-bottom: 16px; }
    .su-svc-col { background: #f8fafd; border: 1px solid var(--crs-line); border-radius: 10px; padding: 18px; height: 100%; }
    .su-svc-col h4 { font-size: 13px; font-weight: 700; color: var(--crs-navy); margin-bottom: 12px; padding-bottom: 8px; border-bottom: 1px solid var(--crs-line); }
    .su-check { display: flex; align-items: center; gap: 8px; font-size: 13.5px; color: var(--crs-ink); margin-bottom: 8px; cursor: pointer; line-height: 1.3; }
    .su-check input[type="checkbox"] { accent-color: var(--crs-blue); width: 15px; height: 15px; flex: none; }

    /* Submit */
    .su-submit {
        width: 100%; padding: 14px; background: var(--crs-blue); color: #fff;
        border: none; border-radius: 10px; font-size: 16px; font-weight: 700;
        cursor: pointer; margin-top: 24px; transition: .2s; font-family: "Poppins", sans-serif;
    }
    .su-submit:hover { background: var(--crs-blue-dark); }
    .su-assure { display: flex; flex-wrap: wrap; gap: 10px 20px; margin-top: 16px; }
    .su-assure span { display: flex; align-items: center; gap: 6px; font-size: 12.5px; color: var(--crs-muted); }
    .su-assure i { color: #1f9d57; }
    .su-assure small { color: var(--crs-muted); }

    /* Benefits panel */
    .su-benefit { display: flex; gap: 14px; margin-bottom: 24px; align-items: flex-start; }
    .su-benefit-ico { width: 42px; height: 42px; border-radius: 10px; background: var(--crs-light-blue);
        color: var(--crs-blue); display: flex; align-items: center; justify-content: center;
        font-size: 18px; flex: none; }
    .su-benefit h3 { font-size: 14.5px; font-weight: 700; color: var(--crs-navy); margin: 0 0 5px; }
    .su-benefit p  { font-size: 13px; color: var(--crs-muted); margin: 0; line-height: 1.5; }
    .su-benefit-list { list-style: none; padding: 0; margin: 8px 0 0; }
    .su-benefit-list li { display: flex; align-items: center; gap: 7px; font-size: 13px; color: var(--crs-ink); margin-bottom: 6px; }
    .su-benefit-list li i { color: var(--crs-blue); font-size: 11px; }
    .su-profile-card { background: var(--crs-navy); border-radius: 12px; padding: 20px; display: flex; gap: 14px; align-items: flex-start; margin-top: 8px; }
    .su-shield { width: 42px; height: 42px; border-radius: 10px; background: rgba(255,255,255,.12);
        color: #fff; display: flex; align-items: center; justify-content: center; font-size: 18px; flex: none; }
    .su-profile-card h3 { font-size: 14px; font-weight: 700; color: #fff; margin: 0 0 5px; }
    .su-profile-card p  { font-size: 12.5px; color: rgba(255,255,255,.75); margin: 0; line-height: 1.5; }

    /* Error / loading */
    .bod-form-error { display: none; padding: 12px 16px; background: #fff3f3; border: 1px solid #f5c6cb; border-radius: 8px; color: #721c24; margin-bottom: 16px; font-size: 14px; }
    .bod-form-loading { display: none; text-align: center; padding: 24px; }
    @keyframes bod-spin { to { transform: rotate(360deg); } }
    .bod-spinner { width: 36px; height: 36px; border: 4px solid var(--crs-line); border-top-color: var(--crs-blue); border-radius: 50%; margin: 0 auto 12px; animation: bod-spin .8s linear infinite; }

    /* Disclaimer */
    .su-disclaimer { border-top: 1px solid var(--crs-line); padding: 24px 0; margin-top: 32px; }
    .su-disclaimer .lead-line { font-size: 13px; color: var(--crs-ink); margin-bottom: 8px; }
    .su-disclaimer .fine { font-size: 11.5px; color: var(--crs-muted); line-height: 1.6; margin: 0; }

    @media (max-width: 767px) {
        .su-panel { padding: 22px 18px; }
        .su-field-ico { display: none; }
    }
    </style>

    <div class="bod-su">
      <div class="container px-3 py-4">
        <div class="row g-4 align-items-stretch">

          <!-- LEFT: FORM -->
          <div class="col-lg-8">
            <div class="su-panel">
              <h1 class="su-panel-title">Join CRS &amp; Start Generating<br>More Leads Today!</h1>
              <p class="su-panel-sub">List your computer repair or IT support business and connect with customers actively searching for technical support.</p>

              <?php if (!empty($_GET['cancelled'])) : ?>
              <div style="padding:12px 16px;background:#fff8e1;border:1px solid #ffe082;border-radius:8px;margin-bottom:20px;font-size:14px;color:#7a5c00;">
                  <i class="fa-solid fa-circle-info" style="color:#f59e0b;"></i>
                  Payment was cancelled. You can try again below.
              </div>
              <?php endif; ?>

              <form id="bod-signup-form">
                <?php wp_nonce_field('bod_signup', 'bod_nonce'); ?>

                <!-- Business Name -->
                <div class="su-field">
                  <div class="su-field-ico"><i class="fa-solid fa-store"></i></div>
                  <div class="su-field-body">
                    <label class="su-label">Business Name <span class="req">*</span></label>
                    <input type="text" name="business_name" id="bod-business-name" class="su-input" placeholder="e.g. ABC Computer Repairs" required>
                  </div>
                </div>

                <!-- ABN -->
                <div class="su-field">
                  <div class="su-field-ico"><i class="fa-solid fa-id-card"></i></div>
                  <div class="su-field-body">
                    <label class="su-label">ABN <span class="req">*</span></label>
                    <input type="text" name="abn" id="bod-abn" class="su-input" placeholder="e.g. 12 345 678 901" required>
                  </div>
                </div>

                <!-- Contact Name -->
                <div class="su-field">
                  <div class="su-field-ico"><i class="fa-solid fa-user"></i></div>
                  <div class="su-field-body">
                    <label class="su-label">Contact Name <span class="req">*</span></label>
                    <input type="text" name="name" id="bod-name" class="su-input" placeholder="e.g. John Smith" required>
                  </div>
                </div>

                <!-- Email -->
                <div class="su-field">
                  <div class="su-field-ico"><i class="fa-solid fa-envelope"></i></div>
                  <div class="su-field-body">
                    <label class="su-label">Email Address <span class="req">*</span></label>
                    <input type="email" name="email" id="bod-email" class="su-input" placeholder="e.g. john@abcrepairs.com.au" required>
                    <span class="bod-email-hint" style="font-size:12px;margin-top:4px;display:block;min-height:16px;"></span>
                  </div>
                </div>

                <!-- Phone -->
                <div class="su-field">
                  <div class="su-field-ico"><i class="fa-solid fa-phone"></i></div>
                  <div class="su-field-body">
                    <label class="su-label">Phone Number <span class="req">*</span></label>
                    <input type="tel" name="phone" id="bod-phone" class="su-input" placeholder="e.g. 0412 345 678" required>
                  </div>
                </div>

                <!-- Website -->
                <div class="su-field">
                  <div class="su-field-ico"><i class="fa-solid fa-globe"></i></div>
                  <div class="su-field-body">
                    <label class="su-label">Website URL</label>
                    <input type="url" name="website_url" id="bod-website" class="su-input" placeholder="e.g. https://www.abcrepairs.com.au">
                  </div>
                </div>

                <!-- Postcode / Suburb / Region / State (auto-filled from au-suburb taxonomy) -->
                <div class="su-field">
                  <div class="su-field-ico"><i class="fa-solid fa-location-dot"></i></div>
                  <div class="su-field-body">

                    <!-- Row 1: Postcode + Suburb on same line -->
                    <div style="display:flex;gap:12px;align-items:flex-start;flex-wrap:wrap;">
                      <div style="flex:0 0 150px;min-width:120px;">
                        <label class="su-label">Postcode <span class="req">*</span></label>
                        <input type="text" name="postal_code" id="bod-postcode" class="su-input" maxlength="4" pattern="[0-9]{4}" required placeholder="e.g. 3000">
                        <div id="bod-postcode-status" style="font-size:12px;color:var(--crs-muted);margin-top:3px;min-height:16px;"></div>
                      </div>
                      <div style="flex:1;min-width:160px;">
                        <label class="su-label">Suburb <span class="req">*</span></label>
                        <select id="bod-suburb" name="suburb" class="su-select" disabled required>
                          <option value="">— enter postcode first —</option>
                        </select>
                      </div>
                    </div>

                    <!-- Row 2: Region + State on same line -->
                    <div style="display:flex;gap:12px;margin-top:12px;flex-wrap:wrap;">
                      <div style="flex:1;min-width:140px;">
                        <label class="su-label">Region</label>
                        <input type="text" name="region" id="bod-region" class="su-input" readonly placeholder="Auto-filled" style="background:#f8fafd;">
                      </div>
                      <div style="flex:1;min-width:140px;">
                        <label class="su-label">State</label>
                        <input type="text" name="state" id="bod-state" class="su-input" readonly placeholder="Auto-filled" style="background:#f8fafd;">
                      </div>
                    </div>

                  </div>
                </div>

                <!-- Primary Service Area -->
                <div class="su-field">
                  <div class="su-field-ico"><i class="fa-solid fa-map-location-dot"></i></div>
                  <div class="su-field-body">
                    <label class="su-label">Primary Service Area <span class="req">*</span></label>
                    <input type="text" name="primary_service_area" id="bod-service-area" class="su-input" placeholder="e.g. Melbourne, Geelong, Sydney" required>
                  
                  </div>
                </div>

                <!-- Service Radius -->
                <div class="su-field">
                  <div class="su-field-ico"><i class="fa-solid fa-location-crosshairs"></i></div>
                  <div class="su-field-body">
                    <label class="su-label">Service Radius <span class="req">*</span></label>
                    <select name="service_radius" id="bod-service-radius" class="su-select" required>
                      <?php foreach (bod_get_service_radius_options() as $val => $label) : ?>
                        <option value="<?php echo esc_attr($val); ?>"><?php echo esc_html($label); ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                </div>

                <!-- Business Description -->
                <div class="su-field">
                  <div class="su-field-ico"><i class="fa-solid fa-file-lines"></i></div>
                  <div class="su-field-body">
                    <label class="su-label">Business Description <span class="req">*</span></label>
                    <p class="su-hint">Tell customers about your business, experience, services offered and areas you service.</p>
                    <textarea name="description" id="bod-description" class="su-textarea" maxlength="2000" placeholder="Write a short description about your business..." required></textarea>
                    <div class="su-counter"><span id="bod-desc-count">0</span> / 2000 characters</div>
                  </div>
                </div>

                <!-- Services -->
                <div class="su-field">
                  <div class="su-field-ico" style="margin-top:4px;"></div>
                  <div class="su-field-body">
                    <h3 class="su-services-head">Select Your Services <span class="req" style="color:#e53e3e;">*</span></h3>
                    <p class="su-services-sub">Choose all services that your business provides.</p>
                    <div class="row g-3">
                      <?php foreach ($services as $category => $items) : ?>
                      <div class="col-sm-6">
                        <div class="su-svc-col">
                          <h4><?php echo esc_html($category); ?></h4>
                          <?php foreach ($items as $key => $label) : ?>
                          <label class="su-check">
                            <input type="checkbox" name="services[]" value="<?php echo esc_attr($key); ?>">
                            <?php echo esc_html($label); ?>
                          </label>
                          <?php endforeach; ?>
                        </div>
                      </div>
                      <?php endforeach; ?>
                    </div>

                    <div class="bod-form-error"></div>

                    <button type="submit" class="su-submit" id="bod-submit-btn">
                        Start My Business Listing — $<?php echo number_format($amount, 2); ?> AUD/month
                    </button>

                    <div class="su-assure">
                      <span><i class="fa-solid fa-circle-check"></i> $<?php echo number_format($amount, 2); ?>/month <small>(Inc. GST)</small></span>
                      <span><i class="fa-solid fa-circle-check"></i> Cancel Anytime</span>
                      <span><i class="fa-solid fa-circle-check"></i> No Lead Fees</span>
                    </div>

                    <div class="bod-form-loading">
                      <div class="bod-spinner"></div>
                      <p style="color:var(--crs-muted);font-size:14px;margin:0;">Redirecting to secure payment...</p>
                    </div>
                  </div>
                </div>

              </form>
            </div><!-- /.su-panel -->
          </div><!-- /.col-lg-8 -->

          <!-- RIGHT: BENEFITS -->
          <div class="col-lg-4">
            <div class="su-panel" style="position:sticky;top:20px;">
              <h2 class="su-panel-title">Maximum Visibility &amp; Lead Generation</h2>

              <div class="su-benefit mt-4">
                <div class="su-benefit-ico"><i class="fa-solid fa-chart-line"></i></div>
                <div>
                  <h3>Rank Across 100's Of High-Intent Keywords</h3>
                  <p>Get discovered by customers actively searching for:</p>
                  <ul class="su-benefit-list">
                    <li><i class="fa-solid fa-check"></i> Computer Repairs</li>
                    <li><i class="fa-solid fa-check"></i> Laptop Repairs</li>
                    <li><i class="fa-solid fa-check"></i> Macbook Repairs</li>
                    <li><i class="fa-solid fa-check"></i> Data Recovery</li>
                    <li><i class="fa-solid fa-check"></i> Business IT Support</li>
                    <li><i class="fa-solid fa-check"></i> Microsoft 365 Support</li>
                    <li><i class="fa-solid fa-check"></i> And many more...</li>
                  </ul>
                </div>
              </div>

              <div class="su-benefit">
                <div class="su-benefit-ico"><i class="fa-solid fa-map-location-dot"></i></div>
                <div>
                  <h3>Local Visibility Across Australia</h3>
                  <p>Appear on suburb, city, region and state pages throughout Australia and connect with local customers.</p>
                </div>
              </div>

              <div class="su-benefit">
                <div class="su-benefit-ico"><i class="fa-solid fa-envelope-open-text"></i></div>
                <div>
                  <h3>Leads Delivered Directly To Your Inbox</h3>
                  <p>Receive enquiries from consumers and businesses looking for computer repair and IT support services.</p>
                </div>
              </div>

              <div class="su-profile-card">
                <div class="su-shield"><i class="fa-solid fa-user-shield"></i></div>
                <div>
                  <h3>Business Profile Setup Included</h3>
                  <p>We create and publish your business profile on ComputerRepairServices.com.au and help maximise your visibility across relevant locations and service categories.</p>
                </div>
              </div>
            </div>
          </div><!-- /.col-lg-4 -->

        </div><!-- /.row -->

        <!-- DISCLAIMER -->
        <div class="su-disclaimer">
          <p class="lead-line"><strong>Disclaimer:</strong> ComputerRepairServices.com.au is an independent directory connecting consumers and businesses with computer repair and IT support providers across Australia.</p>
          <p class="fine">All product information, images, logos, trademarks, and brand names displayed on our website are the property of their respective owners and are used for identification and informational purposes only. ComputerRepairServices.com.au makes no representations or warranties regarding the accuracy, completeness, or reliability of any information published on this platform and accepts no liability for any loss or damage arising from reliance on such information. Information provided on our website should not be considered professional, financial, or purchasing advice. Users are encouraged to conduct their own due diligence and seek independent professional advice before making any decisions.</p>
        </div>
      </div>
    </div><!-- /.bod-su -->

    <script>
    jQuery(document).ready(function($) {
        $('#bod-description').on('input', function() {
            $('#bod-desc-count').text(this.value.length);
        });
        
        // ── Postcode → suburb cascade (reads local au-suburb taxonomy) ──
        var postcodeTimer;

        function resetSuburbFields() {
            $('#bod-suburb')
                .html('<option value="">— enter postcode first —</option>')
                .prop('disabled', true);
            $('#bod-region').val('');
            $('#bod-state').val('');
            $('#bod-postcode-status').text('');
        }

        $('#bod-postcode').on('input', function() {
            var pc = $(this).val().trim();
            resetSuburbFields();
            if (pc.length !== 4 || !/^\d{4}$/.test(pc)) return;

            clearTimeout(postcodeTimer);
            $('#bod-postcode-status').text('Looking up…');

            postcodeTimer = setTimeout(function() {
                $.getJSON('<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>', {
                    action:   'crs_get_suburbs_by_postcode',
                    postcode: pc,
                })
                .done(function(res) {
                    $('#bod-postcode-status').text('');
                    var suburbs = (res.success && res.data.suburbs) ? res.data.suburbs : [];

                    if (suburbs.length === 0) {
                        $('#bod-suburb').html('<option value="">No suburbs found</option>');
                        $('#bod-postcode-status').text('No suburbs found for this postcode.');
                        return;
                    }

                    if (suburbs.length === 1) {
                        // Single suburb — auto-fill everything
                        var s = suburbs[0];
                        $('#bod-suburb')
                            .html('<option value="' + $('<span>').text(s.name).html() + '" selected>' + $('<span>').text(s.name).html() + '</option>')
                            .prop('disabled', false);
                        $('#bod-region').val(s.region);
                        $('#bod-state').val(s.state);
                    } else {
                        // Multiple suburbs — let user choose, then fill region + state
                        var opts = '<option value="">Select suburb…</option>';
                        $.each(suburbs, function(i, s) {
                            opts += '<option value="' + $('<span>').text(s.name).html() + '"'
                                  + ' data-region="' + $('<span>').text(s.region).html() + '"'
                                  + ' data-state="'  + $('<span>').text(s.state).html()  + '">'
                                  + $('<span>').text(s.name).html()
                                  + '</option>';
                        });
                        $('#bod-suburb').html(opts).prop('disabled', false);
                    }
                })
                .fail(function() {
                    $('#bod-postcode-status').text('Could not look up postcode. Please try again.');
                });
            }, 500);
        });

        // When a suburb is chosen from the dropdown, fill Region + State
        $('#bod-suburb').on('change', function() {
            var $opt = $(this).find('option:selected');
            $('#bod-region').val($opt.data('region') || '');
            $('#bod-state').val($opt.data('state')  || '');
        });

        $('#bod-signup-form').on('submit', function(e) {
            e.preventDefault();
            e.stopPropagation(); // prevent any other delegated handlers (e.g. signup.js) from also firing
            var $btn     = $('#bod-submit-btn');
            var $loading = $('.bod-form-loading');
            var $error   = $('.bod-form-error');

            var services = [];
            $('input[name="services[]"]:checked').each(function() {
                services.push($(this).val());
            });

            $error.hide().text('');
            $btn.hide();
            $loading.show();

            var data = {
                action:               'bod_initiate_signup',
                nonce:                '<?php echo esc_js( wp_create_nonce( 'bod_signup' ) ); ?>',
                name:                 $('#bod-name').val(),
                email:                $('#bod-email').val(),
                phone:                $('#bod-phone').val(),
                business_name:        $('#bod-business-name').val(),
                abn:                  $('#bod-abn').val(),
                website_url:          $('#bod-website').val(),
                postal_code:          $('#bod-postcode').val(),
                suburb:               $('#bod-suburb').val(),
                state:                $('#bod-state').val(),
                region:               $('#bod-region').val(),
                primary_service_area: $('#bod-service-area').val(),
                service_radius:       $('#bod-service-radius').val(),
                description:          $('#bod-description').val(),
                services:             services.join(','),
            };

            $.post('<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>', data, function(res) {
                if (res.success && res.data.redirect_url) {
                    window.location.href = res.data.redirect_url;
                } else {
                    $loading.hide();
                    $btn.show();
                    $error.text(res.data.message || 'An error occurred. Please try again.').show();
                }
            }).fail(function() {
                $loading.hide();
                $btn.show();
                $error.text('Connection error. Please try again.').show();
            });
        });
    });
    </script>
    <?php
    return ob_get_clean();
}

// ============================================
// SUCCESS PAGE [business_owner_success]
// ============================================
add_shortcode('business_owner_success', 'bod_render_success_page');
function bod_render_success_page($atts) {
    $session_id = sanitize_text_field($_GET['session_id'] ?? '');
    $details    = null;

    if ($session_id && class_exists('\Stripe\Stripe') && !empty(BOD_STRIPE_SECRET_KEY)) {
        try {
            \Stripe\Stripe::setApiKey(BOD_STRIPE_SECRET_KEY);
            $session = \Stripe\Checkout\Session::retrieve($session_id, ['expand' => ['customer_details']]);
            $details = [
                'name'   => $session->customer_details->name ?? ($session->metadata->owner_name ?? ''),
                'email'  => $session->customer_details->email ?? ($session->metadata->owner_email ?? ''),
                'amount' => $session->amount_total ? '$' . number_format($session->amount_total / 100, 2) . ' AUD' : '',
                'status' => $session->payment_status === 'paid' ? 'Payment Successful' : ucfirst(str_replace('_', ' ', $session->payment_status ?? '')),
                'ref'    => $session->payment_intent ?? $session->id ?? '',
                'date'   => date('F j, Y, g:i a', $session->created ?? time()),
            ];
        } catch (Exception $e) {
            $details = null;
        }
    }

    ob_start();
    ?>
    <div style="max-width:580px;margin:0 auto;text-align:center;padding:40px 20px;">
        <div style="width:64px;height:64px;background:#1565d8;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 20px;font-size:32px;color:#fff;">✓</div>
        <h2 style="font-size:28px;margin-bottom:8px;">Thank You for Signing Up!</h2>
        <p style="color:#555;margin-bottom:24px;">Your payment has been processed successfully.</p>

        <?php if ($details) : ?>
        <div style="background:#fff;border:1px solid #e7ecf3;border-radius:12px;padding:24px;margin-bottom:24px;text-align:left;">
            <h3 style="margin:0 0 16px;font-size:16px;border-bottom:1px solid #f0f0f0;padding-bottom:12px;">Payment Details</h3>
            <table style="width:100%;border-collapse:collapse;">
                <tr><td style="padding:6px 0;color:#666;width:120px;">Name</td><td style="padding:6px 0;"><strong><?php echo esc_html($details['name']); ?></strong></td></tr>
                <tr><td style="padding:6px 0;color:#666;">Email</td><td style="padding:6px 0;"><?php echo esc_html($details['email']); ?></td></tr>
                <tr><td style="padding:6px 0;color:#666;">Amount</td><td style="padding:6px 0;"><?php echo esc_html($details['amount']); ?></td></tr>
                <tr><td style="padding:6px 0;color:#666;">Status</td><td style="padding:6px 0;"><strong style="color:#16a34a;"><?php echo esc_html($details['status']); ?></strong></td></tr>
                <tr><td style="padding:6px 0;color:#666;">Reference</td><td style="padding:6px 0;font-size:12px;word-break:break-all;"><?php echo esc_html($details['ref']); ?></td></tr>
            </table>
        </div>
        <?php endif; ?>

        <div style="background:#eaf1fc;border:1px solid #cfe0f7;border-radius:12px;padding:24px;margin-bottom:24px;text-align:left;">
            <h3 style="margin:0 0 16px;color:#0a2647;">What Happens Next?</h3>
            <ol style="margin:0;padding-left:20px;color:#444;line-height:1.8;">
                <li><strong>Invoice:</strong> A tax invoice has been sent to your email address.</li>
                <li><strong>Account Pending Approval:</strong> Your business owner account is currently under review by our admin team.</li>
                <li><strong>Credentials:</strong> Once approved, you'll receive your login username and password via email within 24 hours. Please also check your spam folder.</li>
                <li><strong>Get Started:</strong> After receiving your credentials, log in to your dashboard and create your first listing!</li>
            </ol>
        </div>

        <p style="margin-top:16px;"><a href="/" style="color:#666;">← Return to Homepage</a></p>
    </div>
    <?php
    return ob_get_clean();
}

// ============================================
// DASHBOARD [business_owner_dashboard]
// ============================================
// ============================================
// DASHBOARD [business_owner_dashboard]
// ============================================
add_shortcode('business_owner_dashboard', 'bod_render_dashboard_shortcode');
function bod_render_dashboard_shortcode($atts) {
    if (!is_user_logged_in()) {
        return '<div style="padding:20px;background:#eaf1fc;border-radius:8px;">
            Please <a href="' . home_url('/business-owner-login/') . '" style="color:#1565d8;">login</a> to access your dashboard.
        </div>';
    }
    if (!bod_is_business_owner() && !current_user_can('manage_options')) {
        return '<div style="padding:20px;background:#fff3f3;border-radius:8px;">Access restricted.</div>';
    }

    $template = BOD_PLUGIN_DIR . 'templates/dashboard.php';
    if (file_exists($template)) {
        ob_start();
        include $template;
        return ob_get_clean();
    }

    $owner = bod_get_current_owner();
    if (!$owner) return '<p>No owner account found.</p>';
    return '<p>Welcome, ' . esc_html($owner->owner_name) . '! Dashboard template not found.</p>';
}
// ============================================
// LOGIN AJAX HANDLER
// ============================================
add_action('wp_ajax_nopriv_bod_ajax_login', 'bod_handle_ajax_login');
add_action('wp_ajax_bod_ajax_login',        'bod_handle_ajax_login');
function bod_handle_ajax_login() {
    if (!check_ajax_referer('bod_ajax_login_nonce', 'nonce', false)) {
        wp_send_json_error(['message' => 'Security check failed. Please refresh the page.']);
    }

    $user = wp_signon([
        'user_login'    => sanitize_text_field($_POST['log'] ?? ''),
        'user_password' => $_POST['pwd'] ?? '',
        'remember'      => !empty($_POST['rememberme']),
    ], is_ssl());

    if (is_wp_error($user)) {
        wp_send_json_error(['message' => 'Invalid username or password. Please try again.']);
    }

    // Success — determine redirect
    $redirect = home_url('/business-owner-dashboard/');
    if (!empty($_POST['redirect_to'])) {
        $redirect = esc_url_raw($_POST['redirect_to']);
    }
    wp_send_json_success(['redirect' => $redirect]);
}

// ============================================
// LOGIN SHORTCODE [business_owner_login]
// ============================================
add_shortcode('business_owner_login', 'bod_render_login_shortcode');
function bod_render_login_shortcode($atts) {
    if (is_user_logged_in() && bod_is_business_owner()) {
        return '<p style="text-align:center;padding:20px;">You are already logged in. 
            <a href="' . home_url('/business-owner-dashboard/') . '" style="color:#1565d8;">Go to Dashboard →</a></p>';
    }

    $redirect_to = isset($_GET['redirect_to']) ? esc_url($_GET['redirect_to']) : '';

    ob_start(); ?>
    <div style="max-width:400px;margin:0 auto;padding:40px 20px;font-family:Poppins,sans-serif;">
        <h2 style="text-align:center;margin-bottom:24px;color:#0a2647;">Business Owner Login</h2>

        <div id="bod-login-error" style="display:none;background:#fff3f3;border:1px solid #f5c6cb;border-radius:6px;padding:12px;margin-bottom:16px;color:#721c24;"></div>

        <form id="bod-login-form" style="background:#fff;border:1px solid #e7ecf3;border-radius:12px;padding:28px;">
            <input type="hidden" name="redirect_to" value="<?php echo esc_attr($redirect_to); ?>">
            <div style="margin-bottom:14px;">
                <label style="display:block;font-weight:600;margin-bottom:6px;">Username or Email</label>
                <input type="text" name="log" id="bod-log" required
                       style="width:100%;padding:10px;border:1px solid #e7ecf3;border-radius:6px;font-size:15px;box-sizing:border-box;">
            </div>
            <div style="margin-bottom:20px;">
                <label style="display:block;font-weight:600;margin-bottom:6px;">Password</label>
                <input type="password" name="pwd" id="bod-pwd" required
                       style="width:100%;padding:10px;border:1px solid #e7ecf3;border-radius:6px;font-size:15px;box-sizing:border-box;">
            </div>
            <div style="margin-bottom:20px;">
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                    <input type="checkbox" name="rememberme"> Remember me
                </label>
            </div>
            <button type="submit" id="bod-login-btn"
                    style="width:100%;padding:12px;background:#1565d8;color:#fff;border:none;border-radius:8px;font-size:16px;font-weight:700;cursor:pointer;">
                Login
            </button>
            <p style="text-align:center;margin-top:16px;">
                <a href="<?php echo wp_lostpassword_url(); ?>" style="color:#1565d8;">Forgot your password?</a>
            </p>
        </form>
        <p style="text-align:center;margin-top:16px;color:#666;">
            Not a business owner yet? <a href="<?php echo home_url('/list-your-business/'); ?>" style="color:#1565d8;">Sign up here</a>
        </p>
    </div>

    <script>
    document.getElementById('bod-login-form').addEventListener('submit', function(e) {
        e.preventDefault();

        var btn   = document.getElementById('bod-login-btn');
        var error = document.getElementById('bod-login-error');
        var form  = this;

        btn.disabled    = true;
        btn.textContent = 'Logging in…';
        error.style.display = 'none';

        var data = new FormData();
        data.append('action',      'bod_ajax_login');
        data.append('nonce',       '<?php echo wp_create_nonce('bod_ajax_login_nonce'); ?>');
        data.append('log',         document.getElementById('bod-log').value);
        data.append('pwd',         document.getElementById('bod-pwd').value);
        data.append('rememberme',  form.querySelector('[name=rememberme]').checked ? '1' : '');
        data.append('redirect_to', form.querySelector('[name=redirect_to]').value);

        fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
            method: 'POST',
            body: data
        })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                btn.textContent = 'Redirecting…';
                window.location.href = res.data.redirect;
            } else {
                error.textContent    = res.data.message;
                error.style.display  = 'block';
                btn.disabled         = false;
                btn.textContent      = 'Login';
            }
        })
        .catch(() => {
            error.textContent   = 'Something went wrong. Please try again.';
            error.style.display = 'block';
            btn.disabled        = false;
            btn.textContent     = 'Login';
        });
    });
    </script>
    <?php
    return ob_get_clean();
}

// ============================================
// LOGIN [business_owner_login]
// ============================================
// ============================================
// LOGIN FORM PROCESSING — runs before headers sent
// ============================================
/* 
add_action('template_redirect', 'bod_process_login_form');
function bod_process_login_form() {
    // Redirect already-logged-in business owners away from login page
    $login_page_id = (int) get_option('bod_login_page_id');
    if ($login_page_id && is_page($login_page_id) && is_user_logged_in() && bod_is_business_owner()) {
        wp_redirect(home_url('/business-owner-dashboard/'));
        exit;
    }

    // Only process on POST submission
    if (empty($_POST['bod_login_submit'])) return;

    // Nonce check — show error in transient instead of wp_die
    if (empty($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'bod_login_action')) {
        set_transient('bod_login_error_' . md5(session_id() . $_SERVER['REMOTE_ADDR']), 'Security check failed. Please refresh and try again.', 60);
        return;
    }

    $creds = [
        'user_login'    => sanitize_text_field($_POST['log'] ?? ''),
        'user_password' => $_POST['pwd'] ?? '',
        'remember'      => !empty($_POST['rememberme']),
    ];
    $user = wp_signon($creds, is_ssl());

    if (is_wp_error($user)) {
        set_transient('bod_login_error_' . md5(session_id() . $_SERVER['REMOTE_ADDR']), 'Invalid username or password.', 60);
    } else {
        // Successful login — safe redirect here (before HTML)
        $redirect = isset($_GET['redirect_to']) ? esc_url_raw($_GET['redirect_to']) : home_url('/business-owner-dashboard/');
        wp_redirect($redirect);
        exit;
    }
} */


