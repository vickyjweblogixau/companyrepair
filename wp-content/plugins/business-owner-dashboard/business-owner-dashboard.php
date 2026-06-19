<?php
/**
 * Plugin Name: Business Owner Dashboard
 * Description: Dashboard for business owners to manage listings, enquiries, and subscriptions on CRS.
 * Version: 1.0.0
 * Author: Priya
 * License: GPL2
 * Text Domain: business-owner-dashboard
 */

defined('ABSPATH') || exit;

// ============================================
// PLUGIN CONSTANTS
// ============================================
define('BOD_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('BOD_PLUGIN_URL', plugin_dir_url(__FILE__));
define('BOD_VERSION', '1.0.0');

// Stripe Settings (loaded from options)
define('BOD_STRIPE_SECRET_KEY', get_option('bod_stripe_secret_key', ''));
define('BOD_STRIPE_PUBLISHABLE_KEY', get_option('bod_stripe_publishable_key', ''));
define('BOD_STRIPE_WEBHOOK_SECRET', get_option('bod_stripe_webhook_secret', ''));
define('BOD_ADDON_WEBHOOK_SECRET', get_option('bod_addon_webhook_secret', ''));

// Pricing Settings (One-time payments) — Incl. GST display amounts, loaded from options
define('BOD_LISTING_AMOUNT_DISPLAY', (float) get_option('bod_listing_amount_display', 35.00));
define('BOD_LISTING_AMOUNT', round(BOD_LISTING_AMOUNT_DISPLAY / 1.1, 2)); // Excl. GST

// Boost Pricing (One-time, until sold) — Incl. GST display amounts
define('BOD_BOOST_FEATURED_DISPLAY', (float) get_option('bod_boost_featured_display', 50.00));
define('BOD_BOOST_FEATURED_AMOUNT', round(BOD_BOOST_FEATURED_DISPLAY / 1.1, 2));
define('BOD_BOOST_EXCLUSIVE_DISPLAY', (float) get_option('bod_boost_exclusive_display', 75.00));
define('BOD_BOOST_EXCLUSIVE_AMOUNT', round(BOD_BOOST_EXCLUSIVE_DISPLAY / 1.1, 2));
define('BOD_BOOST_HOMEPAGE_DISPLAY', (float) get_option('bod_boost_homepage_display', 100.00));
define('BOD_BOOST_HOMEPAGE_AMOUNT', round(BOD_BOOST_HOMEPAGE_DISPLAY / 1.1, 2));

// Stripe Price IDs (One-time products)
define('BOD_LISTING_PRICE_ID', get_option('bod_listing_price_id', ''));
define('BOD_BOOST_FEATURED_PRICE_ID', get_option('bod_boost_featured_price_id', ''));
define('BOD_BOOST_EXCLUSIVE_PRICE_ID', get_option('bod_boost_exclusive_price_id', ''));
define('BOD_BOOST_HOMEPAGE_PRICE_ID', get_option('bod_boost_homepage_price_id', ''));

// Addon Product IDs (for detecting addon status)
define('BOD_ADDON_FEATURED_PRODUCT_ID', get_option('bod_addon_featured_product_id', ''));
define('BOD_ADDON_EXCLUSIVE_PRODUCT_ID', get_option('bod_addon_exclusive_product_id', ''));
define('BOD_ADDON_HOMEPAGE_PRODUCT_ID', get_option('bod_addon_homepage_product_id', ''));

// Database table names
global $wpdb;
define('BOD_TABLE_OWNERS',       $wpdb->prefix . 'business_owners');
define('BOD_TABLE_LISTINGS',     $wpdb->prefix . 'business_owner_listings');
define('BOD_TABLE_PAYMENTS',     $wpdb->prefix . 'business_owner_payments');
define('BOD_TABLE_NOTIFICATIONS',$wpdb->prefix . 'business_owner_notifications');

// ============================================
// ACTIVATION / DEACTIVATION
// ============================================
register_activation_hook(__FILE__, 'bod_activate_plugin');
register_deactivation_hook(__FILE__, 'bod_deactivate_plugin');

function bod_activate_plugin() {
    bod_create_database_tables();
    bod_create_plugin_pages();
    flush_rewrite_rules();
    error_log('[Business Owner Dashboard] Plugin activated');
}

function bod_deactivate_plugin() {
    foreach (['bod_daily_expire_boosts', 'bod_daily_boost_reminders'] as $hook) {
        $ts = wp_next_scheduled($hook);
        if ($ts) wp_unschedule_event($ts, $hook);
    }
    error_log('[Business Owner Dashboard] Plugin deactivated');
}

// ============================================
// DATABASE TABLES
// ============================================
function bod_create_database_tables() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    // TABLE 1: business_owners
    $sql1 = "CREATE TABLE IF NOT EXISTS " . BOD_TABLE_OWNERS . " (
        id mediumint(9) NOT NULL AUTO_INCREMENT,

        owner_name varchar(255) NOT NULL,
        owner_email varchar(255) NOT NULL,
        owner_phone varchar(50) NOT NULL,
        business_name varchar(255) DEFAULT NULL,
        postal_code varchar(20) DEFAULT NULL,
        address text DEFAULT NULL,
        suburb varchar(100) DEFAULT NULL,
        region varchar(100) DEFAULT NULL,
        state varchar(100) DEFAULT NULL,

        stripe_customer_id varchar(100) DEFAULT NULL,
        stripe_default_payment_method varchar(255) DEFAULT NULL,

        wp_user_id bigint(20) DEFAULT NULL,
        username varchar(60) DEFAULT NULL,
        user_password_plain varchar(255) DEFAULT NULL,
        account_status enum('not_created','created','active') DEFAULT 'not_created',
        account_created_at datetime DEFAULT NULL,

        approval_status enum('pending','approved','rejected') DEFAULT 'pending',
        approved_at datetime DEFAULT NULL,
        approved_by varchar(100) DEFAULT NULL,
        rejection_reason text DEFAULT NULL,

        available_listing_credits int DEFAULT 0,
        total_listings_purchased int DEFAULT 0,
        total_listings_created int DEFAULT 0,
        total_listings_sold int DEFAULT 0,

        welcome_email_sent enum('no','yes') DEFAULT 'no',
        welcome_email_sent_at datetime DEFAULT NULL,
        credentials_email_sent enum('no','yes') DEFAULT 'no',
        credentials_email_sent_at datetime DEFAULT NULL,

        admin_notes text DEFAULT NULL,

        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

        PRIMARY KEY (id),
        UNIQUE KEY owner_email (owner_email),
        KEY stripe_customer_id (stripe_customer_id),
        KEY approval_status (approval_status),
        KEY wp_user_id (wp_user_id),
        KEY account_status (account_status)
    ) $charset_collate;";
    dbDelta($sql1);

    // TABLE 2: business_owner_listings
    $sql2 = "CREATE TABLE IF NOT EXISTS " . BOD_TABLE_LISTINGS . " (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        owner_id mediumint(9) NOT NULL,

        stripe_checkout_session_id varchar(100) DEFAULT NULL,
        stripe_payment_intent_id varchar(100) DEFAULT NULL,
        payment_amount decimal(10,2) DEFAULT 35.00,
        payment_status enum('pending','paid','refunded','failed') DEFAULT 'pending',
        paid_at datetime DEFAULT NULL,
        activated_at datetime DEFAULT NULL,

        wp_product_id bigint(20) DEFAULT NULL,
        product_title varchar(255) DEFAULT NULL,
        product_created_at datetime DEFAULT NULL,

        listing_status enum('credit','draft','active','sold') DEFAULT 'credit',
        listed_at datetime DEFAULT NULL,
        sold_at datetime DEFAULT NULL,
        expired_at datetime DEFAULT NULL,
        can_reactivate tinyint(1) DEFAULT 1,

        active_boost enum('none','featured','exclusive','homepage') DEFAULT 'none',
        boost_payment_intent_id varchar(100) DEFAULT NULL,
        boost_amount decimal(10,2) DEFAULT NULL,
        boost_paid_at datetime DEFAULT NULL,
        boost_cancelled_at datetime DEFAULT NULL,

        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

        PRIMARY KEY (id),
        KEY owner_id (owner_id),
        KEY wp_product_id (wp_product_id),
        KEY listing_status (listing_status),
        KEY payment_status (payment_status),
        KEY stripe_payment_intent_id (stripe_payment_intent_id)
    ) $charset_collate;";
    dbDelta($sql2);

    // TABLE 3: business_owner_payments
    $sql3 = "CREATE TABLE IF NOT EXISTS " . BOD_TABLE_PAYMENTS . " (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        owner_id mediumint(9) NOT NULL,
        listing_id mediumint(9) DEFAULT NULL,

        payment_type enum('listing','boost_featured','boost_exclusive','boost_homepage') NOT NULL,

        stripe_checkout_session_id varchar(100) DEFAULT NULL,
        stripe_payment_intent_id varchar(100) DEFAULT NULL,
        stripe_invoice_id varchar(100) DEFAULT NULL,
        payment_source varchar(20) DEFAULT 'stripe',
        promotion_code varchar(100) DEFAULT NULL,
        discount_amount decimal(10,2) DEFAULT 0.00,

        amount decimal(10,2) NOT NULL,
        currency varchar(10) DEFAULT 'aud',
        amount_gst decimal(10,2) DEFAULT NULL,

        status enum('pending','succeeded','failed','refunded','expired') DEFAULT 'pending',

        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        completed_at datetime DEFAULT NULL,
        reminder_sent_at datetime DEFAULT NULL,
        boost_reminder_sent_at datetime DEFAULT NULL,
        activated_at datetime DEFAULT NULL,
        expires_at datetime DEFAULT NULL,

        display_product_name varchar(255) DEFAULT NULL,
        display_unit_amount_cents int DEFAULT NULL,
        display_billing_interval varchar(20) DEFAULT NULL,
        display_amount_includes_gst tinyint(1) DEFAULT 1,

        PRIMARY KEY (id),
        KEY owner_id (owner_id),
        KEY listing_id (listing_id),
        KEY payment_type (payment_type),
        KEY status (status),
        KEY stripe_payment_intent_id (stripe_payment_intent_id)
    ) $charset_collate;";
    dbDelta($sql3);

    // TABLE 4: business_owner_notifications
    $sql4 = "CREATE TABLE IF NOT EXISTS " . BOD_TABLE_NOTIFICATIONS . " (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        owner_id mediumint(9) NOT NULL,
        type varchar(50) NOT NULL,
        title varchar(255) DEFAULT NULL,
        message text DEFAULT NULL,
        is_read tinyint(1) DEFAULT 0,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY owner_id (owner_id),
        KEY is_read (is_read)
    ) $charset_collate;";
    dbDelta($sql4);

    error_log('[Business Owner Dashboard] Database tables created/verified');
}

// Run table upgrades on init (for existing installs)
add_action('init', 'bod_run_db_upgrades', 5);
function bod_run_db_upgrades() {
    // Placeholder for future column migrations
}

// ============================================
// CREATE PLUGIN PAGES
// ============================================
function bod_create_plugin_pages() {
    $pages = [
        [
            'title'   => 'List Your Business',
            'content' => '[business_owner_signup]',
            'slug'    => 'list-your-business',
            'option'  => 'bod_signup_page_id',
        ],
        [
            'title'   => 'Business Owner Signup Successful',
            'content' => '[business_owner_success]',
            'slug'    => 'business-owner-signup-success',
            'option'  => 'bod_success_page_id',
        ],
        [
            'title'   => 'Business Owner Dashboard',
            'content' => '[business_owner_dashboard]',
            'slug'    => 'business-owner-dashboard',
            'option'  => 'bod_dashboard_page_id',
        ],
        [
            'title'   => 'Business Owner Login',
            'content' => '[business_owner_login]',
            'slug'    => 'business-owner-login',
            'option'  => 'bod_login_page_id',
        ],
    ];

    foreach ($pages as $page) {
        if (!get_page_by_path($page['slug'])) {
            $id = wp_insert_post([
                'post_title'   => $page['title'],
                'post_content' => $page['content'],
                'post_status'  => 'publish',
                'post_type'    => 'page',
                'post_name'    => $page['slug'],
            ]);
            update_option($page['option'], $id);
        }
    }
}

// ============================================
// INCLUDE FILES
// ============================================
require_once BOD_PLUGIN_DIR . 'includes/timezone-helpers.php';
require_once BOD_PLUGIN_DIR . 'includes/database-functions.php';
require_once BOD_PLUGIN_DIR . 'includes/stripe-functions.php';
require_once BOD_PLUGIN_DIR . 'includes/email-functions.php';
require_once BOD_PLUGIN_DIR . 'includes/user-functions.php';
require_once BOD_PLUGIN_DIR . 'includes/admin-pages.php';
require_once BOD_PLUGIN_DIR . 'includes/admin-settings.php';
require_once BOD_PLUGIN_DIR . 'includes/ajax-handlers.php';
require_once BOD_PLUGIN_DIR . 'includes/shortcodes.php';

// ============================================
// INITIALIZE STRIPE
// ============================================
add_action('plugins_loaded', 'bod_init_stripe');
function bod_init_stripe() {
    if (!class_exists('\Stripe\Stripe')) {
        // Try loading from cf7-stripe-subscriptions if present
        $stripe_path = WP_PLUGIN_DIR . '/cf7-stripe-subscriptions/stripe-php/init.php';
        if (file_exists($stripe_path)) {
            require_once $stripe_path;
        }
        // Fallback: vendor bundled in this plugin
        $local_stripe = BOD_PLUGIN_DIR . 'vendor/stripe-php/init.php';
        if (!class_exists('\Stripe\Stripe') && file_exists($local_stripe)) {
            require_once $local_stripe;
        }
    }

    if (class_exists('\Stripe\Stripe') && !empty(BOD_STRIPE_SECRET_KEY)) {
        \Stripe\Stripe::setApiKey(BOD_STRIPE_SECRET_KEY);
    }
}

// ============================================
// ADMIN MENU
// ============================================
add_action('admin_menu', 'bod_add_admin_menu');
function bod_add_admin_menu() {
    add_menu_page(
        'Business Owners',
        'Business Owners',
        'manage_options',
        'business-owners',
        'bod_render_owners_list',
        'dashicons-store',
        31
    );

    add_submenu_page('business-owners', 'All Business Owners', 'All Business Owners', 'manage_options', 'business-owners', 'bod_render_owners_list');

    add_submenu_page('business-owners', 'Pending Approval', 'Pending Approval', 'manage_options', 'business-owners-pending', 'bod_render_pending_owners', 10);

    add_submenu_page('business-owners', 'Pending Payments', 'Pending Payments', 'manage_options', 'business-owners-pending-payments', 'bod_render_pending_payments', 11);

    add_submenu_page('business-owners', 'Settings', 'Settings', 'manage_options', 'business-owners-settings', 'bod_render_settings_page', 99);
}

// ============================================
// ADMIN NOTICES
// ============================================
add_action('admin_notices', 'bod_admin_notices');
function bod_admin_notices() {
    if (!current_user_can('manage_options')) return;

    if (empty(BOD_STRIPE_SECRET_KEY)) {
        echo '<div class="notice notice-warning"><p>';
        echo '<strong>Business Owner Dashboard:</strong> Stripe is not configured. ';
        echo '<a href="' . admin_url('admin.php?page=business-owners-settings') . '">Go to Settings</a>';
        echo '</p></div>';
    }

    global $wpdb;
    $pending = (int) $wpdb->get_var("SELECT COUNT(*) FROM " . BOD_TABLE_OWNERS . " WHERE approval_status = 'pending'");
    if ($pending > 0) {
        echo '<div class="notice notice-info"><p>';
        echo '<strong>Business Owners:</strong> ' . $pending . ' owner(s) pending approval. ';
        echo '<a href="' . admin_url('admin.php?page=business-owners-pending') . '">Review Now</a>';
        echo '</p></div>';
    }
}

// ============================================
// ENQUEUE SCRIPTS & STYLES
// ============================================
add_action('admin_enqueue_scripts', 'bod_admin_enqueue_scripts');
function bod_admin_enqueue_scripts($hook) {
    if (strpos($hook, 'business-owners') === false) return;

    wp_enqueue_style('bod-admin-css', BOD_PLUGIN_URL . 'assets/css/admin.css', [], BOD_VERSION);
    wp_enqueue_script('bod-admin-js', BOD_PLUGIN_URL . 'assets/js/admin.js', ['jquery'], BOD_VERSION, true);
    wp_enqueue_script('sweetalert2', 'https://cdn.jsdelivr.net/npm/sweetalert2@11', [], null, true);

    wp_localize_script('bod-admin-js', 'bodAdmin', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonces'  => [
            'approve'         => wp_create_nonce('bod_approve_owner'),
            'reject'          => wp_create_nonce('bod_reject_owner'),
            'createAccount'   => wp_create_nonce('bod_create_account'),
            'sendEmail'       => wp_create_nonce('bod_send_email'),
            'reactivateListing' => wp_create_nonce('bod_reactivate_listing'),
            'cancelListing'   => wp_create_nonce('bod_admin_cancel_listing'),
            'saveProfile'     => wp_create_nonce('bod_admin_save_profile'),
            'grantNewListing' => wp_create_nonce('bod_grant_new_listing'),
        ],
    ]);
}

add_action('wp_enqueue_scripts', 'bod_frontend_enqueue_scripts');
function bod_frontend_enqueue_scripts() {
    // Dashboard styles (on dashboard page)
    $dashboard_page_id = (int) get_option('bod_dashboard_page_id');
    if ($dashboard_page_id && is_page($dashboard_page_id)) {
        wp_enqueue_style('bod-style', BOD_PLUGIN_URL . 'assets/css/style.css', [], BOD_VERSION);
        wp_enqueue_style('bod-dashboard', BOD_PLUGIN_URL . 'assets/css/new-dashboard.css', ['bod-style'], BOD_VERSION);
        wp_enqueue_style('bod-responsive', BOD_PLUGIN_URL . 'assets/css/responsive.css', ['bod-style'], BOD_VERSION);
        wp_enqueue_style('bod-crs-theme', BOD_PLUGIN_URL . 'assets/css/crs-theme.css', ['bod-style'], BOD_VERSION);
    }

    // Signup page scripts
    $signup_page_id = (int) get_option('bod_signup_page_id');
    if ($signup_page_id && is_page($signup_page_id)) {
        wp_enqueue_style('bod-frontend-css', BOD_PLUGIN_URL . 'assets/css/frontend.css', [], BOD_VERSION);
        wp_enqueue_script('stripe-js', 'https://js.stripe.com/v3/', [], null, true);
        wp_enqueue_script('bod-signup-js', BOD_PLUGIN_URL . 'assets/js/signup.js', ['jquery', 'stripe-js'], BOD_VERSION, true);
        wp_localize_script('bod-signup-js', 'bodSignup', [
            'ajaxUrl'    => admin_url('admin-ajax.php'),
            'stripeKey'  => BOD_STRIPE_PUBLISHABLE_KEY,
            'priceId'    => BOD_LISTING_PRICE_ID,
            'amount'     => BOD_LISTING_AMOUNT_DISPLAY,
            'nonce'      => wp_create_nonce('bod_signup'),
            'successUrl' => home_url('/business-owner-signup-success/'),
        ]);
    }
}

// ============================================
// REST API ENDPOINTS (Stripe Webhooks)
// ============================================
add_action('rest_api_init', 'bod_register_rest_routes');
function bod_register_rest_routes() {
    register_rest_route('business-owners/v1', '/webhook', [
        'methods'             => 'POST',
        'callback'            => 'bod_handle_stripe_webhook',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route('business-owners/v1', '/validate-email', [
        'methods'             => 'POST',
        'callback'            => 'bod_rest_validate_email',
        'permission_callback' => '__return_true',
    ]);
}

// Validate email endpoint
function bod_rest_validate_email(WP_REST_Request $request) {
    $email = sanitize_email($request->get_param('email') ?? '');
    if (!is_email($email)) {
        return new WP_REST_Response(['valid' => false, 'exists' => false], 200);
    }
    $exists = (bool) bod_get_owner_by_email($email);
    $wp_user_exists = email_exists($email);
    return new WP_REST_Response([
        'valid'          => true,
        'exists'         => $exists,
        'wp_user_exists' => (bool) $wp_user_exists,
    ], 200);
}
