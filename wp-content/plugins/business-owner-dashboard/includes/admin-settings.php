<?php
/**
 * Admin Settings Page for Business Owner Dashboard
 * Stripe credentials, API settings, product price IDs
 */
if (!defined('ABSPATH')) exit;

function bod_render_settings_page() {
    if (!current_user_can('manage_options')) wp_die('Unauthorized');

    // Save handler
    if (isset($_POST['bod_save_settings']) && check_admin_referer('bod_settings_nonce')) {
        // Stripe
        update_option('bod_stripe_secret_key',      sanitize_text_field($_POST['bod_stripe_secret_key'] ?? ''));
        update_option('bod_stripe_publishable_key', sanitize_text_field($_POST['bod_stripe_publishable_key'] ?? ''));
        update_option('bod_stripe_webhook_secret',  sanitize_text_field($_POST['bod_stripe_webhook_secret'] ?? ''));
        update_option('bod_addon_webhook_secret',   sanitize_text_field($_POST['bod_addon_webhook_secret'] ?? ''));

        // GST Tax Rate
        update_option('bod_gst_tax_rate', sanitize_text_field($_POST['bod_gst_tax_rate'] ?? ''));

        // Listing price ID
        update_option('bod_listing_price_id', sanitize_text_field($_POST['bod_listing_price_id'] ?? ''));

        // Boost price IDs
        update_option('bod_boost_featured_price_id',  sanitize_text_field($_POST['bod_boost_featured_price_id'] ?? ''));
        update_option('bod_boost_exclusive_price_id', sanitize_text_field($_POST['bod_boost_exclusive_price_id'] ?? ''));
        update_option('bod_boost_homepage_price_id',  sanitize_text_field($_POST['bod_boost_homepage_price_id'] ?? ''));

        // Boost product IDs (for addon detection)
        update_option('bod_addon_featured_product_id',  sanitize_text_field($_POST['bod_addon_featured_product_id'] ?? ''));
        update_option('bod_addon_exclusive_product_id', sanitize_text_field($_POST['bod_addon_exclusive_product_id'] ?? ''));
        update_option('bod_addon_homepage_product_id',  sanitize_text_field($_POST['bod_addon_homepage_product_id'] ?? ''));

        // Pricing display amounts
        update_option('bod_listing_amount_display',      (float) ($_POST['bod_listing_amount_display'] ?? 35));
        update_option('bod_boost_featured_display',      (float) ($_POST['bod_boost_featured_display'] ?? 50));
        update_option('bod_boost_exclusive_display',     (float) ($_POST['bod_boost_exclusive_display'] ?? 75));
        update_option('bod_boost_homepage_display',      (float) ($_POST['bod_boost_homepage_display'] ?? 100));

        // Page IDs
        update_option('bod_signup_page_id',    absint($_POST['bod_signup_page_id'] ?? 0));
        update_option('bod_success_page_id',   absint($_POST['bod_success_page_id'] ?? 0));
        update_option('bod_dashboard_page_id', absint($_POST['bod_dashboard_page_id'] ?? 0));
        update_option('bod_login_page_id',     absint($_POST['bod_login_page_id'] ?? 0));

        echo '<div class="notice notice-success is-dismissible"><p>Settings saved successfully.</p></div>';
    }

    $all_pages = get_pages(['post_status' => 'publish', 'sort_column' => 'post_title']);
    ?>
    <div class="wrap">
        <h1>Business Owner Dashboard — Settings</h1>
        <p>Configure Stripe integration, product price IDs, and page assignments for the Business Owner Dashboard plugin.</p>

        <form method="post" action="">
            <?php wp_nonce_field('bod_settings_nonce'); ?>

            <!-- =============================== -->
            <!-- STRIPE CONFIGURATION -->
            <!-- =============================== -->
            <h2 class="title">Stripe Configuration</h2>
            <table class="form-table">
                <tr>
                    <th><label for="bod_stripe_secret_key">Secret Key</label></th>
                    <td>
                        <input type="password" name="bod_stripe_secret_key" id="bod_stripe_secret_key"
                               value="<?php echo esc_attr(get_option('bod_stripe_secret_key')); ?>" class="regular-text">
                        <p class="description">Stripe secret key (starts with sk_live_ or sk_test_)</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="bod_stripe_publishable_key">Publishable Key</label></th>
                    <td>
                        <input type="text" name="bod_stripe_publishable_key" id="bod_stripe_publishable_key"
                               value="<?php echo esc_attr(get_option('bod_stripe_publishable_key')); ?>" class="regular-text">
                        <p class="description">Stripe publishable key (starts with pk_live_ or pk_test_)</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="bod_stripe_webhook_secret">Webhook Secret</label></th>
                    <td>
                        <input type="password" name="bod_stripe_webhook_secret" id="bod_stripe_webhook_secret"
                               value="<?php echo esc_attr(get_option('bod_stripe_webhook_secret')); ?>" class="regular-text">
                        <p class="description">Webhook signing secret (starts with whsec_). Configure your Stripe webhook to point to:</p>
                        <code style="display:block;background:#f1f1f1;padding:8px;margin-top:6px;"><?php echo home_url('/wp-json/business-owners/v1/webhook'); ?></code>
                    </td>
                </tr>
                <tr>
                    <th><label for="bod_addon_webhook_secret">Addon Webhook Secret</label></th>
                    <td>
                        <input type="password" name="bod_addon_webhook_secret" id="bod_addon_webhook_secret"
                               value="<?php echo esc_attr(get_option('bod_addon_webhook_secret')); ?>" class="regular-text">
                        <p class="description">Signing secret for the addon webhook at: <code><?php echo home_url('/wp-json/business-owners-addons/v1/webhook'); ?></code></p>
                    </td>
                </tr>
                <tr>
                    <th><label for="bod_gst_tax_rate">GST Tax Rate ID</label></th>
                    <td>
                        <input type="text" name="bod_gst_tax_rate" id="bod_gst_tax_rate"
                               value="<?php echo esc_attr(get_option('bod_gst_tax_rate', get_option('cf7_stripe_gst_tax_rate', ''))); ?>" class="regular-text" placeholder="txr_...">
                        <p class="description">Stripe Tax Rate ID for 10% GST. Find it in Stripe Dashboard → Products → Tax Rates.</p>
                    </td>
                </tr>
            </table>

            <!-- =============================== -->
            <!-- PRODUCT PRICE IDs -->
            <!-- =============================== -->
            <h2 class="title">Stripe Product &amp; Price IDs</h2>
            <p>Create these as <strong>one-time payment</strong> products in your Stripe Dashboard, then paste the Price IDs here.</p>
            <table class="form-table">
                <tr>
                    <th><label for="bod_listing_price_id">Listing Fee Price ID</label></th>
                    <td>
                        <input type="text" name="bod_listing_price_id" id="bod_listing_price_id"
                               value="<?php echo esc_attr(get_option('bod_listing_price_id')); ?>" class="regular-text" placeholder="price_...">
                        <p class="description">The Stripe Price ID for the listing fee (one-time payment, e.g. $35 incl. GST).</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="bod_listing_amount_display">Listing Price (incl. GST)</label></th>
                    <td>
                        <input type="number" name="bod_listing_amount_display" id="bod_listing_amount_display" step="0.01" min="0"
                               value="<?php echo esc_attr(get_option('bod_listing_amount_display', 35)); ?>" class="small-text"> AUD
                        <p class="description">Display price shown on the signup page (used for display only; actual charge comes from Stripe).</p>
                    </td>
                </tr>

                <tr><th colspan="2"><h3 style="margin:10px 0 0;">Boost Products</h3></th></tr>

                <tr>
                    <th><label for="bod_boost_featured_price_id">Featured Boost — Price ID</label></th>
                    <td>
                        <input type="text" name="bod_boost_featured_price_id" id="bod_boost_featured_price_id"
                               value="<?php echo esc_attr(get_option('bod_boost_featured_price_id')); ?>" class="regular-text" placeholder="price_...">
                        <input type="number" name="bod_boost_featured_display" step="0.01" min="0"
                               value="<?php echo esc_attr(get_option('bod_boost_featured_display', 50)); ?>" class="small-text" style="margin-left:8px;"> AUD display price
                    </td>
                </tr>
                <tr>
                    <th><label for="bod_boost_exclusive_price_id">Exclusive Boost — Price ID</label></th>
                    <td>
                        <input type="text" name="bod_boost_exclusive_price_id" id="bod_boost_exclusive_price_id"
                               value="<?php echo esc_attr(get_option('bod_boost_exclusive_price_id')); ?>" class="regular-text" placeholder="price_...">
                        <input type="number" name="bod_boost_exclusive_display" step="0.01" min="0"
                               value="<?php echo esc_attr(get_option('bod_boost_exclusive_display', 75)); ?>" class="small-text" style="margin-left:8px;"> AUD display price
                    </td>
                </tr>
                <tr>
                    <th><label for="bod_boost_homepage_price_id">Homepage Boost — Price ID</label></th>
                    <td>
                        <input type="text" name="bod_boost_homepage_price_id" id="bod_boost_homepage_price_id"
                               value="<?php echo esc_attr(get_option('bod_boost_homepage_price_id')); ?>" class="regular-text" placeholder="price_...">
                        <input type="number" name="bod_boost_homepage_display" step="0.01" min="0"
                               value="<?php echo esc_attr(get_option('bod_boost_homepage_display', 100)); ?>" class="small-text" style="margin-left:8px;"> AUD display price
                    </td>
                </tr>

                <tr><th colspan="2"><h3 style="margin:10px 0 0;">Addon Product IDs (for status detection)</h3></th></tr>
                <tr>
                    <th><label for="bod_addon_featured_product_id">Featured — Product ID</label></th>
                    <td>
                        <input type="text" name="bod_addon_featured_product_id" id="bod_addon_featured_product_id"
                               value="<?php echo esc_attr(get_option('bod_addon_featured_product_id')); ?>" class="regular-text" placeholder="prod_...">
                    </td>
                </tr>
                <tr>
                    <th><label for="bod_addon_exclusive_product_id">Exclusive — Product ID</label></th>
                    <td>
                        <input type="text" name="bod_addon_exclusive_product_id" id="bod_addon_exclusive_product_id"
                               value="<?php echo esc_attr(get_option('bod_addon_exclusive_product_id')); ?>" class="regular-text" placeholder="prod_...">
                    </td>
                </tr>
                <tr>
                    <th><label for="bod_addon_homepage_product_id">Homepage — Product ID</label></th>
                    <td>
                        <input type="text" name="bod_addon_homepage_product_id" id="bod_addon_homepage_product_id"
                               value="<?php echo esc_attr(get_option('bod_addon_homepage_product_id')); ?>" class="regular-text" placeholder="prod_...">
                    </td>
                </tr>
            </table>

            <!-- =============================== -->
            <!-- PAGE ASSIGNMENTS -->
            <!-- =============================== -->
            <h2 class="title">Page Assignments</h2>
            <p>Assign WordPress pages to each plugin shortcode. Pages are created automatically on plugin activation.</p>
            <table class="form-table">
                <?php
                $page_options = [
                    'bod_signup_page_id'    => ['Signup Page',    '[business_owner_signup]'],
                    'bod_success_page_id'   => ['Success Page',   '[business_owner_success]'],
                    'bod_dashboard_page_id' => ['Dashboard Page', '[business_owner_dashboard]'],
                    'bod_login_page_id'     => ['Login Page',     '[business_owner_login]'],
                ];
                foreach ($page_options as $option => [$label, $shortcode]) :
                    $current_id = (int) get_option($option);
                    ?>
                    <tr>
                        <th><label for="<?php echo $option; ?>"><?php echo esc_html($label); ?></label></th>
                        <td>
                            <select name="<?php echo $option; ?>" id="<?php echo $option; ?>">
                                <option value="0">— Select a page —</option>
                                <?php foreach ($all_pages as $page) : ?>
                                    <option value="<?php echo $page->ID; ?>" <?php selected($current_id, $page->ID); ?>>
                                        <?php echo esc_html($page->post_title); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <span style="margin-left:8px;color:#666;">Shortcode: <code><?php echo $shortcode; ?></code></span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>

            <!-- =============================== -->
            <!-- WEBHOOK INFO -->
            <!-- =============================== -->
            <h2 class="title">Stripe Webhook Setup</h2>
            <p>In your <a href="https://dashboard.stripe.com/webhooks" target="_blank">Stripe Dashboard → Developers → Webhooks</a>, add the endpoints below and subscribe to the <strong>checkout.session.completed</strong> event on each.</p>
            <table class="form-table">
                <tr>
                    <th>Main Webhook URL</th>
                    <td>
                        <code style="background:#f1f1f1;padding:8px;display:block;margin-bottom:4px;"><?php echo home_url('/wp-json/business-owners/v1/webhook'); ?></code>
                        <p class="description">Handles signup payments and listing purchases. Use <strong>Stripe Webhook Secret</strong> above.</p>
                    </td>
                </tr>
                <tr>
                    <th>Addon Webhook URL</th>
                    <td>
                        <code style="background:#f1f1f1;padding:8px;display:block;margin-bottom:4px;"><?php echo home_url('/wp-json/business-owners-addons/v1/webhook'); ?></code>
                        <p class="description">Handles boost and addon purchases. Use <strong>Addon Webhook Secret</strong> above. This is the URL you register in your Stripe addon/secondary webhook.</p>
                    </td>
                </tr>
                <tr>
                    <th>Required Events</th>
                    <td>
                        <code>checkout.session.completed</code>
                    </td>
                </tr>
            </table>

            <p class="submit">
                <input type="submit" name="bod_save_settings" class="button button-primary button-large" value="Save Settings">
            </p>
        </form>
    </div>
    <?php
}
