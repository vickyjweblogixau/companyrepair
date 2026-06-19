<?php
/**
 * Shortcodes for Business Owner Dashboard
 */
if (!defined('ABSPATH')) exit;

// ============================================
// SIGNUP FORM [business_owner_signup]
// ============================================
add_shortcode('business_owner_signup', 'bod_render_signup_form');
function bod_render_signup_form($atts) {
    if (is_user_logged_in() && bod_is_business_owner()) {
        return '<div class="bod-notice bod-notice-info" style="padding:16px;background:#e8f4fd;border-left:4px solid #f97316;border-radius:4px;">
            <p>You are already registered as a business owner. <a href="' . home_url('/business-owner-dashboard/') . '">Go to Dashboard &rarr;</a></p>
        </div>';
    }

    $amount = BOD_LISTING_AMOUNT_DISPLAY;
    ob_start();
    ?>
    <div class="bod-signup-container" style="max-width:580px;margin:0 auto;">
        <div class="bod-signup-header" style="text-align:center;margin-bottom:28px;">
            <h2 style="font-size:28px;margin-bottom:8px;">List Your Business</h2>
            <p style="color:#666;">Get your business in front of thousands of buyers.</p>

            <div class="bod-pricing-box" style="background:#fff7f0;border:2px solid #f97316;border-radius:12px;padding:20px;margin:20px auto;max-width:320px;">
                <div style="font-size:42px;font-weight:700;color:#f97316;">$<?php echo number_format($amount, 2); ?></div>
                <div style="color:#666;margin-bottom:12px;">AUD incl. GST &mdash; one-time payment per listing</div>
                <ul style="text-align:left;margin:0;padding:0 0 0 18px;color:#444;">
                    <li style="margin-bottom:6px;">✓ 1 Active Listing</li>
                    <li style="margin-bottom:6px;">✓ Direct buyer enquiries</li>
                    <li style="margin-bottom:6px;">✓ Business Owner Dashboard</li>
                    <li style="margin-bottom:6px;">✓ Mark as Sold when done</li>
                </ul>
            </div>
        </div>

        <form id="bod-signup-form" class="bod-form" style="background:#fff;border:1px solid #e0e0e0;border-radius:12px;padding:28px;">
            <?php wp_nonce_field('bod_signup', 'bod_nonce'); ?>

            <div style="margin-bottom:20px;">
                <h3 style="margin:0 0 16px;padding-bottom:10px;border-bottom:1px solid #f0f0f0;">Your Details</h3>
                <div style="margin-bottom:14px;">
                    <label style="display:block;font-weight:600;margin-bottom:4px;">Full Name <span style="color:red;">*</span></label>
                    <input type="text" name="name" id="bod-name" required style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;font-size:15px;">
                </div>
                <div style="margin-bottom:14px;">
                    <label style="display:block;font-weight:600;margin-bottom:4px;">Business Name</label>
                    <input type="text" name="business_name" id="bod-business-name" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;font-size:15px;" placeholder="Optional">
                </div>
                <div style="margin-bottom:14px;">
                    <label style="display:block;font-weight:600;margin-bottom:4px;">Email Address <span style="color:red;">*</span></label>
                    <input type="email" name="email" id="bod-email" required style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;font-size:15px;">
                </div>
                <div style="margin-bottom:14px;">
                    <label style="display:block;font-weight:600;margin-bottom:4px;">Phone Number <span style="color:red;">*</span></label>
                    <input type="tel" name="phone" id="bod-phone" required style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;font-size:15px;" placeholder="04XX XXX XXX">
                </div>
            </div>

            <div style="margin-bottom:20px;">
                <h3 style="margin:0 0 16px;padding-bottom:10px;border-bottom:1px solid #f0f0f0;">Location</h3>
                <div style="margin-bottom:14px;">
                    <label style="display:block;font-weight:600;margin-bottom:4px;">Postcode <span style="color:red;">*</span></label>
                    <input type="text" name="postal_code" id="bod-postcode" maxlength="4" pattern="[0-9]{4}" required style="width:140px;padding:10px;border:1px solid #ddd;border-radius:6px;font-size:15px;" placeholder="e.g. 3000">
                </div>
                <div style="display:flex;gap:12px;margin-bottom:14px;">
                    <div style="flex:1;">
                        <label style="display:block;font-weight:600;margin-bottom:4px;">Suburb</label>
                        <input type="text" name="suburb" id="bod-suburb" readonly style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;background:#f9f9f9;">
                    </div>
                    <div style="flex:1;">
                        <label style="display:block;font-weight:600;margin-bottom:4px;">State</label>
                        <input type="text" name="state" id="bod-state" readonly style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;background:#f9f9f9;">
                    </div>
                </div>
                <input type="hidden" name="region" id="bod-region">
                <div style="margin-bottom:14px;">
                    <label style="display:block;font-weight:600;margin-bottom:4px;">Street Address (optional)</label>
                    <input type="text" name="address" id="bod-address" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;font-size:15px;" placeholder="For your records only">
                </div>
            </div>

            <div style="margin-bottom:20px;">
                <label style="display:flex;align-items:flex-start;gap:10px;cursor:pointer;">
                    <input type="checkbox" id="bod-terms" required style="margin-top:3px;">
                    <span>I agree to the <a href="/terms-and-conditions" target="_blank" style="color:#f97316;">Terms &amp; Conditions</a> and <a href="/privacy-policy" target="_blank" style="color:#f97316;">Privacy Policy</a></span>
                </label>
            </div>

            <div class="bod-form-error" style="display:none;padding:12px;background:#fff3f3;border:1px solid #f5c6cb;border-radius:6px;color:#721c24;margin-bottom:14px;"></div>

            <button type="submit" id="bod-submit-btn" style="width:100%;padding:14px;background:#f97316;color:#fff;border:none;border-radius:8px;font-size:17px;font-weight:700;cursor:pointer;">
                Continue to Payment — $<?php echo number_format($amount, 2); ?> AUD
            </button>
            <p style="text-align:center;margin:10px 0 0;color:#888;font-size:13px;">🔒 Secure payment powered by Stripe</p>

            <div class="bod-form-loading" style="display:none;text-align:center;padding:20px;">
                <div style="width:36px;height:36px;border:4px solid #f0f0f0;border-top:4px solid #f97316;border-radius:50%;margin:0 auto 12px;animation:bod-spin 0.8s linear infinite;"></div>
                <p>Redirecting to payment...</p>
            </div>
        </form>
    </div>

    <style>
        @keyframes bod-spin { to { transform: rotate(360deg); } }
        #bod-signup-form input:focus { outline:none; border-color:#f97316; box-shadow:0 0 0 3px rgba(249,115,22,0.15); }
        #bod-submit-btn:hover { background:#ea580c; }
    </style>

    <script>
    jQuery(document).ready(function($) {
        // Postcode lookup
        var postcodeLookupTimer;
        $('#bod-postcode').on('input', function() {
            var pc = $(this).val().trim();
            if (pc.length === 4) {
                clearTimeout(postcodeLookupTimer);
                postcodeLookupTimer = setTimeout(function() {
                    $.getJSON('https://api.postcodes.io/postcodes/' + pc, function(){}).always(function(){
                        // Fallback: try AU postcode API or inline lookup
                        // For now just clear fields if postcode changes
                    });
                }, 500);
            }
        });

        // Form submit
        $('#bod-signup-form').on('submit', function(e) {
            e.preventDefault();
            var $form   = $(this);
            var $btn    = $('#bod-submit-btn');
            var $loading = $('.bod-form-loading');
            var $error   = $('.bod-form-error');

            $error.hide();
            $btn.hide();
            $loading.show();

            var data = {
                action: 'bod_initiate_signup',
                nonce:   bodSignup.nonce,
                name:          $('#bod-name').val(),
                email:         $('#bod-email').val(),
                phone:         $('#bod-phone').val(),
                business_name: $('#bod-business-name').val(),
                postal_code:   $('#bod-postcode').val(),
                suburb:        $('#bod-suburb').val(),
                state:         $('#bod-state').val(),
                region:        $('#bod-region').val(),
                address:       $('#bod-address').val(),
            };

            $.post(bodSignup.ajaxUrl, data, function(res) {
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
    $type       = sanitize_text_field($_GET['type'] ?? '');
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
        <div style="width:64px;height:64px;background:#f97316;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 20px;font-size:32px;color:#fff;">✓</div>
        <h2 style="font-size:28px;margin-bottom:8px;">Thank You for Signing Up!</h2>
        <p style="color:#555;margin-bottom:24px;">Your payment has been processed successfully.</p>

        <?php if ($details) : ?>
        <div style="background:#fff;border:1px solid #e0e0e0;border-radius:12px;padding:24px;margin-bottom:24px;text-align:left;">
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

        <div style="background:#fff7f0;border:1px solid #fed7aa;border-radius:12px;padding:24px;margin-bottom:24px;text-align:left;">
            <h3 style="margin:0 0 16px;color:#c2410c;">What Happens Next?</h3>
            <ol style="margin:0;padding-left:20px;color:#444;line-height:1.8;">
                <li><strong>Invoice:</strong> A tax invoice has been sent to your email address.</li>
                <li><strong>Account Created:</strong> Your business owner account is ready to use.</li>
                <li><strong>Credentials:</strong> Your login username and password have been sent to your email. Please also check your spam folder.</li>
                <li><strong>Get Started:</strong> Log in to your dashboard and create your first listing!</li>
            </ol>
        </div>

        <p>
            <a href="<?php echo home_url('/business-owner-login/'); ?>" style="display:inline-block;background:#f97316;color:#fff;padding:12px 28px;border-radius:8px;text-decoration:none;font-weight:700;font-size:16px;">Login to Dashboard</a>
        </p>
        <p style="margin-top:16px;"><a href="/" style="color:#666;">← Return to Homepage</a></p>
    </div>
    <?php
    return ob_get_clean();
}

// ============================================
// DASHBOARD [business_owner_dashboard]
// ============================================
add_shortcode('business_owner_dashboard', 'bod_render_dashboard_shortcode');
function bod_render_dashboard_shortcode($atts) {
    if (!is_user_logged_in()) {
        return '<div style="padding:20px;background:#fff3f3;border-radius:8px;">
            Please <a href="' . home_url('/business-owner-login/') . '" style="color:#f97316;">login</a> to access your dashboard.
        </div>';
    }
    if (!bod_is_business_owner() && !current_user_can('manage_options')) {
        return '<div style="padding:20px;background:#fff3f3;border-radius:8px;">Access restricted.</div>';
    }

    // Load dashboard template
    $template = BOD_PLUGIN_DIR . 'templates/dashboard.php';
    if (file_exists($template)) {
        ob_start();
        include $template;
        return ob_get_clean();
    }

    // Fallback
    $owner = bod_get_current_owner();
    if (!$owner) return '<p>No owner account found.</p>';
    return '<p>Welcome, ' . esc_html($owner->owner_name) . '! Dashboard template not found.</p>';
}

// ============================================
// LOGIN [business_owner_login]
// ============================================
add_shortcode('business_owner_login', 'bod_render_login_shortcode');
function bod_render_login_shortcode($atts) {
    if (is_user_logged_in() && bod_is_business_owner()) {
        wp_redirect(home_url('/business-owner-dashboard/'));
        exit;
    }

    $error = '';
    if (!empty($_POST['bod_login_submit']) && check_admin_referer('bod_login_action')) {
        $creds = [
            'user_login'    => sanitize_text_field($_POST['log'] ?? ''),
            'user_password' => $_POST['pwd'] ?? '',
            'remember'      => !empty($_POST['rememberme']),
        ];
        $user = wp_signon($creds, is_ssl());
        if (is_wp_error($user)) {
            $error = 'Invalid username or password.';
        } else {
            wp_redirect(home_url('/business-owner-dashboard/'));
            exit;
        }
    }

    ob_start();
    ?>
    <div style="max-width:400px;margin:0 auto;padding:40px 20px;">
        <h2 style="text-align:center;margin-bottom:24px;">Business Owner Login</h2>

        <?php if ($error) : ?>
            <div style="background:#fff3f3;border:1px solid #f5c6cb;border-radius:6px;padding:12px;margin-bottom:16px;color:#721c24;"><?php echo esc_html($error); ?></div>
        <?php endif; ?>

        <form method="post" style="background:#fff;border:1px solid #e0e0e0;border-radius:12px;padding:28px;">
            <?php wp_nonce_field('bod_login_action'); ?>
            <div style="margin-bottom:14px;">
                <label style="display:block;font-weight:600;margin-bottom:6px;">Username or Email</label>
                <input type="text" name="log" required style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;font-size:15px;box-sizing:border-box;">
            </div>
            <div style="margin-bottom:20px;">
                <label style="display:block;font-weight:600;margin-bottom:6px;">Password</label>
                <input type="password" name="pwd" required style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;font-size:15px;box-sizing:border-box;">
            </div>
            <div style="margin-bottom:20px;">
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                    <input type="checkbox" name="rememberme"> Remember me
                </label>
            </div>
            <button type="submit" name="bod_login_submit" style="width:100%;padding:12px;background:#f97316;color:#fff;border:none;border-radius:8px;font-size:16px;font-weight:700;cursor:pointer;">Login</button>
            <p style="text-align:center;margin-top:16px;">
                <a href="<?php echo wp_lostpassword_url(); ?>" style="color:#f97316;">Forgot your password?</a>
            </p>
        </form>
        <p style="text-align:center;margin-top:16px;color:#666;">
            Not a business owner yet? <a href="<?php echo home_url('/list-your-business/'); ?>" style="color:#f97316;">Sign up here</a>
        </p>
    </div>
    <?php
    return ob_get_clean();
}
