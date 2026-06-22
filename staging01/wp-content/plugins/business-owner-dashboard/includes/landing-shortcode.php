<?php
/**
 * Landing Page Shortcode [business_listing_landing_page]
 * Matches the reference landing-page.html design.
 */
if (!defined('ABSPATH')) exit;

// ============================================
// ENQUEUE ASSETS FOR LANDING + SIGNUP PAGES
// ============================================
add_action('wp_enqueue_scripts', 'bod_enqueue_page_assets');
function bod_enqueue_page_assets() {
    // Only load on our pages
    if (!is_page(['list-your-business', 'list-your-business-landing'])) return;

    wp_enqueue_style(
        'bod-bootstrap',
        'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css',
        [], '5.3.3'
    );
    wp_enqueue_style(
        'bod-fontawesome',
        'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css',
        [], '6.5.2'
    );
    wp_enqueue_style(
        'bod-poppins',
        'https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap',
        [], null
    );
    wp_enqueue_script(
        'bod-bootstrap-js',
        'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js',
        [], '5.3.3', true
    );
}

// ============================================
// LANDING PAGE SHORTCODE [business_listing_landing_page]
// ============================================
add_shortcode('business_listing_landing_page', 'bod_render_landing_page');
function bod_render_landing_page($atts) {
    $signup_url = home_url('/list-your-business/');
    $img_url    = plugins_url('assets/landing-page-img.png', dirname(__FILE__));

    ob_start();
    ?>
    <!-- Bootstrap + Font Awesome + Poppins loaded via wp_enqueue_scripts -->
    <style>
    :root {
        --crs-navy: #0a2647;
        --crs-blue: #1565d8;
        --crs-blue-dark: #0d4fb8;
        --crs-ink: #1b2430;
        --crs-muted: #6b7785;
        --crs-line: #e7ecf3;
        --crs-light-blue: #eaf1fc;
    }
    .bod-lp * { font-family: "Poppins", system-ui, sans-serif; box-sizing: border-box; }
    .bod-lp a { text-decoration: none; }

    /* Hero */
    .lp-hero { text-align: center; padding: 52px 0 36px; }
    .lp-hero h1 { font-size: clamp(28px,4vw,46px); font-weight: 800; color: var(--crs-navy); line-height: 1.15; margin-bottom: 14px; }
    .lp-hero-price { font-size: 20px; font-weight: 600; margin-bottom: 10px; }
    .lp-hero-price .blue { color: var(--crs-blue); }
    .lp-hero-price .gst { font-size: 15px; font-weight: 400; color: var(--crs-muted); }
    .lp-hero-sub { color: var(--crs-muted); font-size: 16px; max-width: 600px; margin: 0 auto 28px; line-height: 1.6; }
    .lp-cta {
        display: inline-flex; align-items: center; gap: 10px;
        background: var(--crs-blue); color: #fff; font-weight: 700; font-size: 16px;
        padding: 15px 36px; border-radius: 10px; transition: .2s; border: none; cursor: pointer;
    }
    .lp-cta:hover { background: var(--crs-blue-dark); color: #fff; }
    .lp-stats {
        display: flex; flex-wrap: wrap; justify-content: center; gap: 28px;
        margin-top: 36px; padding-top: 32px; border-top: 1px solid var(--crs-line);
    }
    .lp-stat { display: flex; align-items: center; gap: 12px; }
    .lp-stat i { font-size: 28px; color: var(--crs-blue); }
    .lp-stat .num { font-weight: 800; font-size: 22px; color: var(--crs-navy); line-height: 1; }
    .lp-stat .lbl { font-size: 12px; color: var(--crs-muted); margin-top: 2px; }

    /* Lead gen panel */
    .lp-leadgen { background: var(--crs-navy); border-radius: 16px; overflow: hidden; color: #fff; }
    .lp-leadgen-head { padding: 36px 36px 28px; text-align: center; }
    .lp-leadgen-head h2 { font-size: clamp(20px,2.5vw,28px); font-weight: 700; margin-bottom: 12px; color: #fff; }
    .lp-leadgen-head p { color: rgba(255,255,255,.8); font-size: 15px; margin: 0; max-width: 600px; margin: 0 auto; }
    .lp-leadgen-body { background: #fff; }
    .lp-feat { padding: 32px 28px; height: 100%; }
    .lp-feat-divider { border-left: 1px solid var(--crs-line); }
    .lp-feat-ico { width: 52px; height: 52px; border-radius: 12px; background: var(--crs-light-blue);
        color: var(--crs-blue); display: flex; align-items: center; justify-content: center;
        font-size: 22px; margin-bottom: 16px; }
    .lp-feat h3 { font-size: 16px; font-weight: 700; margin-bottom: 10px; color: var(--crs-navy); }
    .lp-feat p { color: var(--crs-muted); font-size: 14px; margin-bottom: 14px; }
    .lp-feat-list { list-style: none; padding: 0; margin: 0; }
    .lp-feat-list li { display: flex; align-items: center; gap: 8px; font-size: 13.5px; color: var(--crs-ink); margin-bottom: 7px; }
    .lp-feat-list li i { color: var(--crs-blue); font-size: 11px; }
    .lp-leadgen-foot { padding: 16px 28px; background: var(--crs-light-blue); display: flex; flex-wrap: wrap; gap: 8px 0; align-items: center; font-size: 13.5px; font-weight: 600; color: var(--crs-navy); }
    .lp-leadgen-foot .dot { margin: 0 14px; color: var(--crs-blue); }

    /* Comparison */
    .lp-compare-shell { display: flex; gap: 24px; align-items: flex-start; }
    .lp-compare { flex: 1; }
    .lp-compare-head { background: var(--crs-navy); color: #fff; font-weight: 700; font-size: 18px; padding: 18px 24px; border-radius: 12px 12px 0 0; }
    .lp-table { width: 100%; border-collapse: collapse; }
    .lp-table thead th { background: #f4f8ff; padding: 12px 16px; font-size: 13.5px; font-weight: 700; color: var(--crs-ink); border-bottom: 2px solid var(--crs-line); }
    .lp-table thead th:first-child { text-align: left; }
    .lp-table thead th:not(:first-child) { text-align: center; width: 110px; }
    .lp-table tbody tr { border-bottom: 1px solid var(--crs-line); }
    .lp-table tbody tr:last-child { border-bottom: none; }
    .lp-table tbody td { padding: 12px 16px; font-size: 13.5px; color: var(--crs-ink); }
    .lp-table tbody td:not(:first-child) { text-align: center; }
    .lp-yes { color: #1f9d57; font-size: 16px; }
    .lp-no  { color: #e53e3e; font-size: 16px; }
    .lp-float { width: 260px; flex: none; display: flex; flex-direction: column; gap: 14px; }
    .lp-float-item { background: #fff; border: 1px solid var(--crs-line); border-radius: 12px; padding: 18px; display: flex; gap: 14px; align-items: flex-start; box-shadow: 0 4px 14px rgba(10,38,71,.07); }
    .lp-float-ico { color: var(--crs-blue); font-size: 22px; flex: none; margin-top: 2px; }
    .lp-float-item h5 { font-size: 14px; font-weight: 700; margin: 0 0 4px; color: var(--crs-navy); }
    .lp-float-item p  { font-size: 13px; color: var(--crs-muted); margin: 0; }

    /* Showcase + Pricing */
    .lp-showcase-title { font-size: clamp(20px,2.4vw,28px); font-weight: 700; color: var(--crs-navy); margin-bottom: 20px; }
    .lp-mock { border-radius: 14px; overflow: hidden; border: 1px solid var(--crs-line); }
    .lp-mock img { width: 100%; display: block; }
    .lp-pricing h3 { font-size: 22px; font-weight: 700; color: var(--crs-navy); margin-bottom: 8px; }
    .lp-pricing .price { font-size: 32px; font-weight: 800; color: var(--crs-blue); margin-bottom: 20px; }
    .lp-pricing .price .gst { font-size: 15px; font-weight: 400; color: var(--crs-muted); }
    .lp-price-list { list-style: none; padding: 0; margin: 0 0 28px; }
    .lp-price-list li { display: flex; align-items: center; gap: 10px; font-size: 15px; color: var(--crs-ink); margin-bottom: 12px; }
    .lp-price-list li i { color: var(--crs-blue); }
    .secure { display: flex; align-items: center; gap: 8px; color: var(--crs-muted); font-size: 13px; margin-top: 16px; }
    .secure i { color: var(--crs-blue); }

    /* FAQ */
    .lp-faq-title { font-size: clamp(22px,2.5vw,30px); font-weight: 700; color: var(--crs-navy); margin-bottom: 24px; text-align: center; }
    .lp-accordion .accordion-item { border: 1px solid var(--crs-line); border-radius: 10px !important; margin-bottom: 10px; overflow: hidden; }
    .lp-accordion .accordion-button { font-weight: 600; font-size: 15px; color: var(--crs-navy); background: #fff; }
    .lp-accordion .accordion-button:not(.collapsed) { background: var(--crs-light-blue); color: var(--crs-blue); box-shadow: none; }
    .lp-accordion .accordion-button::after { filter: invert(26%) sepia(85%) saturate(1400%) hue-rotate(205deg); }
    .lp-accordion .accordion-body { font-size: 14.5px; color: var(--crs-muted); line-height: 1.6; }

    /* Disclaimer */
    .lp-disclaimer { border-top: 1px solid var(--crs-line); padding: 24px 0; margin-top: 36px; }
    .lp-disclaimer .lead-line { font-size: 13px; color: var(--crs-ink); margin-bottom: 8px; }
    .lp-disclaimer .fine { font-size: 11.5px; color: var(--crs-muted); line-height: 1.6; margin: 0; }

    @media (max-width: 991px) {
        .lp-compare-shell { flex-direction: column; }
        .lp-float { width: 100%; flex-direction: row; flex-wrap: wrap; }
        .lp-float-item { flex: 1 1 280px; }
        .lp-feat-divider { border-left: none; border-top: 1px solid var(--crs-line); }
    }
    @media (max-width: 575px) {
        .lp-stats { gap: 20px; }
        .lp-leadgen-head { padding: 24px 20px 18px; }
        .lp-feat { padding: 24px 20px; }
    }
    </style>

    <div class="bod-lp">
      <div class="container px-3">

        <!-- HERO -->
        <section class="lp-hero">
            <h1>Unlimited Listings. Zero Lead Fees.</h1>
            <p class="lp-hero-price">
                <span class="blue">$20 Per Month</span>
                <span class="gst"> (Inc. GST)</span> – Cancel Anytime
            </p>
            <p class="lp-hero-sub">A computer repair &amp; IT support directory built to generate consistent enquiries from Australians actively looking for technical support.</p>
            <a href="<?php echo esc_url($signup_url); ?>" class="lp-cta">
                <i class="fa-solid fa-arrow-right"></i> Start Business Signup
            </a>

            <div class="lp-stats">
                <div class="lp-stat">
                    <i class="fa-solid fa-users"></i>
                    <div><div class="num">1000+</div><div class="lbl">Repair Businesses Listed</div></div>
                </div>
                <div class="lp-stat">
                    <i class="fa-solid fa-location-dot"></i>
                    <div><div class="num">500+</div><div class="lbl">Suburbs Covered</div></div>
                </div>
                <div class="lp-stat">
                    <i class="fa-solid fa-envelope"></i>
                    <div><div class="num" style="font-size:15px;">Consumer &amp; Business Leads</div><div class="lbl">Every Day</div></div>
                </div>
                <div class="lp-stat">
                    <i class="fa-solid fa-map"></i>
                    <div><div class="num" style="font-size:15px;">Australia Wide</div><div class="lbl">Directory</div></div>
                </div>
            </div>
        </section>

        <!-- LEAD GEN PANEL -->
        <section class="lp-leadgen my-5">
            <div class="lp-leadgen-head">
                <h2>Lead Generation Built For Your Computer Repair Business</h2>
                <p>Your business deserves visibility without per-lead fees. ComputerRepairServices.com.au connects you with customers and businesses actively searching for computer repairs and IT support services.</p>
            </div>
            <div class="lp-leadgen-body">
                <div class="row g-0">
                    <div class="col-md-4">
                        <div class="lp-feat">
                            <div class="lp-feat-ico"><i class="fa-solid fa-magnifying-glass-chart"></i></div>
                            <h3>Rank Across 100's Of<br>High-Intent Keywords</h3>
                            <p>Get discovered by customers searching for:</p>
                            <ul class="lp-feat-list">
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
                    <div class="col-md-4 lp-feat-divider">
                        <div class="lp-feat">
                            <div class="lp-feat-ico"><i class="fa-solid fa-globe"></i></div>
                            <h3>Growing Australia-Wide Audience</h3>
                            <p>Reach consumers and businesses looking for local technical support services every day.</p>
                        </div>
                    </div>
                    <div class="col-md-4 lp-feat-divider">
                        <div class="lp-feat">
                            <div class="lp-feat-ico"><i class="fa-solid fa-envelope-open-text"></i></div>
                            <h3>Daily Leads Delivered To Your Inbox</h3>
                            <p>Fresh, high-quality leads sent directly to you from customers actively looking for help.</p>
                        </div>
                    </div>
                </div>
                <div class="lp-leadgen-foot">
                    <span>No Lead Fees</span><span class="dot">●</span>
                    <span>High-Intent Customers</span><span class="dot">●</span>
                    <span>Business &amp; Consumer Audience</span><span class="dot">●</span>
                    <span>Fast Setup</span>
                </div>
            </div>
        </section>

        <!-- COMPARISON -->
        <section class="lp-compare-shell my-5">
            <div class="lp-compare">
                <div class="lp-compare-head">Why Computer Repair Businesses Choose CRS</div>
                <div class="table-responsive">
                    <table class="lp-table">
                        <thead>
                            <tr>
                                <th>Comparison</th>
                                <th>CRS</th>
                                <th>Other Directories</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $rows = [
                                'Dedicated computer repair &amp; IT support directory',
                                'Business IT support categories',
                                'Unlimited business listings',
                                'No lead fees',
                                'Fixed monthly pricing',
                                'Local suburb, city, region &amp; state pages',
                                'Australia-wide coverage',
                                'Featured listing upgrades available',
                            ];
                            foreach ($rows as $row) : ?>
                            <tr>
                                <td><?php echo $row; ?></td>
                                <td><i class="fa-solid fa-check lp-yes"></i></td>
                                <td><i class="fa-solid fa-xmark lp-no"></i></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="lp-float">
                <div class="lp-float-item">
                    <i class="fa-solid fa-clock lp-float-ico"></i>
                    <div>
                        <h5>How long does setup take?</h5>
                        <p>Most businesses are live within 24 hours.</p>
                    </div>
                </div>
                <div class="lp-float-item">
                    <i class="fa-solid fa-file-lines lp-float-ico"></i>
                    <div>
                        <h5>Is there a contract?</h5>
                        <p>No. Cancel anytime.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- SHOWCASE + PRICING -->
        <section class="row g-5 align-items-center my-5">
            <div class="col-lg-6">
                <h2 class="lp-showcase-title text-center text-lg-start">Reach Customers Across Australia</h2>
                <div class="lp-mock">
                    <img src="<?php echo esc_url($img_url); ?>" class="img-fluid" alt="Reach customers across Australia" onerror="this.style.display='none'">
                </div>
            </div>
            <div class="col-lg-6 lp-pricing">
                <h3>Simple Pricing. No Surprises.</h3>
                <p class="price">$20 Per Month <span class="gst">(Inc. GST)</span></p>
                <ul class="lp-price-list">
                    <li><i class="fa-solid fa-check"></i> Unlimited business listings</li>
                    <li><i class="fa-solid fa-check"></i> Zero lead fees</li>
                    <li><i class="fa-solid fa-check"></i> Cancel anytime</li>
                    <li><i class="fa-solid fa-check"></i> No contracts</li>
                    <li><i class="fa-solid fa-check"></i> Local visibility across Australia</li>
                    <li><i class="fa-solid fa-check"></i> Consumer &amp; business leads</li>
                    <li><i class="fa-solid fa-check"></i> Featured upgrades available</li>
                </ul>
                <a href="<?php echo esc_url($signup_url); ?>" class="lp-cta w-100 justify-content-center">
                    <i class="fa-solid fa-arrow-right"></i> Start Business Signup
                </a>
                <div class="secure"><i class="fa-solid fa-lock"></i> Secure. Easy. Effective.</div>
            </div>
        </section>

        <!-- FAQ -->
        <section class="my-5">
            <h2 class="lp-faq-title">FAQ</h2>
            <div class="accordion lp-accordion" id="lpFaqAccordion">
                <?php
                $faqs = [
                    ['q' => 'How much does the subscription cost, and what\'s included?',
                     'a' => 'It\'s a flat $20 per month (inc. GST) with no contracts. That includes unlimited business listings, local suburb/city/state visibility and direct leads with zero per-lead fees.'],
                    ['q' => 'How are my business details added and kept up-to-date?',
                     'a' => 'We set up your profile from the details you submit, and you can update your services, hours and contact information any time from your dashboard.'],
                    ['q' => 'What services can I list my business for?',
                     'a' => 'Any computer repair or IT support service — from laptop and Macbook repairs to data recovery, networking, Microsoft 365 and managed IT.'],
                    ['q' => 'Will customers contact me directly?',
                     'a' => 'Yes. Enquiries come straight to your inbox and phone — we never charge per lead or take a commission.'],
                    ['q' => 'Can I list multiple locations or service areas?',
                     'a' => 'Absolutely. You can appear across multiple suburbs, cities and regions you service, all under one subscription.'],
                    ['q' => 'Can I upgrade my listing for more visibility?',
                     'a' => 'Yes — featured listing upgrades are available to push your business to the top of relevant location and service pages.'],
                    ['q' => 'How do I get started?',
                     'a' => 'Click "Start Business Signup", fill in your details and services, and your profile goes live — most businesses are live within 24 hours.'],
                ];
                foreach ($faqs as $i => $faq) :
                    $id = 'lpFaq' . ($i + 1);
                ?>
                <div class="accordion-item">
                    <h3 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#<?php echo $id; ?>" aria-expanded="false">
                            <?php echo esc_html($faq['q']); ?>
                        </button>
                    </h3>
                    <div id="<?php echo $id; ?>" class="accordion-collapse collapse" data-bs-parent="#lpFaqAccordion">
                        <div class="accordion-body"><?php echo esc_html($faq['a']); ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- DISCLAIMER -->
        <div class="lp-disclaimer">
            <p class="lead-line"><strong>Disclaimer:</strong> ComputerRepairServices.com.au is an independent directory connecting consumers and businesses with computer repair and IT support providers across Australia.</p>
            <p class="fine">All product information, images, logos, trademarks, and brand names displayed on our website are the property of their respective owners and are used for identification and informational purposes only. ComputerRepairServices.com.au makes no representations or warranties regarding the accuracy, completeness, or reliability of any information published on this platform and accepts no liability for any loss or damage arising from reliance on such information. Information provided on our website should not be considered professional, financial, or purchasing advice. Users are encouraged to conduct their own due diligence and seek independent professional advice before making any decisions.</p>
        </div>

      </div><!-- /.container -->
    </div><!-- /.bod-lp -->
    <?php
    return ob_get_clean();
}
