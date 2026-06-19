<?php
/**
 * AJAX Handlers for Business Owner Dashboard
 */
if (!defined('ABSPATH')) exit;

// ============================================
// ADMIN AJAX HANDLERS
// ============================================

add_action('wp_ajax_bod_approve_owner', function() {
    check_ajax_referer('bod_approve_owner', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error(['message' => 'Permission denied']);

    $owner_id = absint($_POST['owner_id'] ?? 0);
    $owner    = bod_get_owner($owner_id);
    if (!$owner) wp_send_json_error(['message' => 'Owner not found']);

    bod_update_approval_status($owner_id, 'approved');

    // Auto-create account if not exists
    if (!$owner->wp_user_id) {
        $result = bod_create_user_account($owner_id);
        if ($result['success']) {
            bod_send_credentials_email($owner_id, false);
        }
    }

    wp_send_json_success(['message' => 'Owner approved']);
});

add_action('wp_ajax_bod_reject_owner', function() {
    check_ajax_referer('bod_reject_owner', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error(['message' => 'Permission denied']);

    $owner_id = absint($_POST['owner_id'] ?? 0);
    $reason   = sanitize_textarea_field($_POST['reason'] ?? '');

    if (!bod_get_owner($owner_id)) wp_send_json_error(['message' => 'Owner not found']);

    bod_update_approval_status($owner_id, 'rejected', null, $reason);
    bod_send_rejection_email($owner_id, $reason);
    wp_send_json_success(['message' => 'Owner rejected']);
});

add_action('wp_ajax_bod_create_account', function() {
    check_ajax_referer('bod_create_account', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error(['message' => 'Permission denied']);

    $owner_id = absint($_POST['owner_id'] ?? 0);
    $result   = bod_create_user_account($owner_id);

    if ($result['success']) {
        bod_send_credentials_email($owner_id, false);
        wp_send_json_success(['message' => 'Account created', 'username' => $result['username'], 'user_id' => $result['user_id']]);
    } else {
        wp_send_json_error(['message' => $result['error']]);
    }
});

add_action('wp_ajax_bod_send_credentials', function() {
    check_ajax_referer('bod_send_email', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error(['message' => 'Permission denied']);

    $owner_id = absint($_POST['owner_id'] ?? 0);
    $owner    = bod_get_owner($owner_id);
    $force    = ($owner && $owner->credentials_email_sent === 'yes');
    $result   = bod_send_credentials_email($owner_id, $force);
    $result ? wp_send_json_success(['message' => 'Email sent']) : wp_send_json_error(['message' => 'Failed to send email']);
});

add_action('wp_ajax_bod_grant_listing', function() {
    check_ajax_referer('bod_grant_new_listing', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error(['message' => 'Permission denied']);

    global $wpdb;
    $owner_id = absint($_POST['owner_id'] ?? 0);
    if (!bod_get_owner($owner_id)) wp_send_json_error(['message' => 'Owner not found']);

    $wpdb->query($wpdb->prepare(
        "UPDATE " . BOD_TABLE_OWNERS . " SET available_listing_credits = available_listing_credits + 1 WHERE id = %d",
        $owner_id
    ));
    wp_send_json_success(['message' => 'Listing credit granted']);
});

add_action('wp_ajax_bod_admin_cancel_listing', function() {
    check_ajax_referer('bod_admin_cancel_listing', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error(['message' => 'Permission denied']);

    global $wpdb;
    $listing_id = absint($_POST['listing_id'] ?? 0);
    $owner_id   = absint($_POST['owner_id'] ?? 0);

    $listing = bod_get_listing($listing_id);
    if (!$listing || (int) $listing->owner_id !== $owner_id) wp_send_json_error(['message' => 'Listing not found']);

    $wpdb->update(BOD_TABLE_LISTINGS, [
        'listing_status' => 'sold',
        'expired_at'     => current_time('mysql'),
    ], ['id' => $listing_id]);

    // Set product as private
    if ($listing->wp_product_id) wp_update_post(['ID' => $listing->wp_product_id, 'post_status' => 'private']);

    wp_send_json_success(['message' => 'Listing cancelled']);
});

add_action('wp_ajax_bod_reactivate_listing', function() {
    check_ajax_referer('bod_reactivate_listing', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error(['message' => 'Permission denied']);

    global $wpdb;
    $listing_id = absint($_POST['listing_id'] ?? 0);
    $listing    = bod_get_listing($listing_id);
    if (!$listing) wp_send_json_error(['message' => 'Listing not found']);

    $wpdb->update(BOD_TABLE_LISTINGS, ['listing_status' => 'active', 'listed_at' => current_time('mysql')], ['id' => $listing_id]);
    if ($listing->wp_product_id) wp_update_post(['ID' => $listing->wp_product_id, 'post_status' => 'publish']);

    wp_send_json_success(['message' => 'Listing reactivated']);
});

// ============================================
// FRONT-END AJAX HANDLERS
// ============================================

// Initiate signup checkout
add_action('wp_ajax_nopriv_bod_initiate_signup', 'bod_ajax_initiate_signup');
add_action('wp_ajax_bod_initiate_signup',        'bod_ajax_initiate_signup');
function bod_ajax_initiate_signup() {
    check_ajax_referer('bod_signup', 'nonce');

    $owner_data = [
        'name'          => sanitize_text_field($_POST['name'] ?? ''),
        'email'         => sanitize_email($_POST['email'] ?? ''),
        'phone'         => sanitize_text_field($_POST['phone'] ?? ''),
        'business_name' => sanitize_text_field($_POST['business_name'] ?? ''),
        'postal_code'   => sanitize_text_field($_POST['postal_code'] ?? ''),
        'address'       => sanitize_text_field($_POST['address'] ?? ''),
        'suburb'        => sanitize_text_field($_POST['suburb'] ?? ''),
        'state'         => sanitize_text_field($_POST['state'] ?? ''),
        'region'        => sanitize_text_field($_POST['region'] ?? ''),
        'promotion_code'=> sanitize_text_field($_POST['promotion_code'] ?? ''),
    ];

    if (empty($owner_data['name']) || empty($owner_data['email']) || empty($owner_data['phone'])) {
        wp_send_json_error(['message' => 'Name, email and phone are required.']);
    }
    if (!is_email($owner_data['email'])) {
        wp_send_json_error(['message' => 'Please enter a valid email address.']);
    }

    $result = bod_create_signup_checkout($owner_data);

    if ($result['success']) {
        wp_send_json_success(['redirect_url' => $result['url']]);
    } else {
        wp_send_json_error(['message' => $result['error'] ?? 'Unable to create payment session. Please try again.']);
    }
}

// Buy additional listing credit (logged-in owner)
add_action('wp_ajax_bod_buy_listing_credit', function() {
    if (!is_user_logged_in()) wp_send_json_error(['message' => 'Not logged in']);
    check_ajax_referer('bod_buy_listing', 'nonce');

    $owner_id = bod_get_current_owner_id();
    if (!$owner_id) wp_send_json_error(['message' => 'No owner account found']);

    $result = bod_create_buy_listing_checkout($owner_id);
    $result['success'] ? wp_send_json_success(['redirect_url' => $result['url']]) : wp_send_json_error(['message' => $result['error']]);
});

// Buy boost (logged-in owner)
add_action('wp_ajax_bod_buy_boost', function() {
    if (!is_user_logged_in()) wp_send_json_error(['message' => 'Not logged in']);
    check_ajax_referer('bod_buy_boost', 'nonce');

    $owner_id   = bod_get_current_owner_id();
    $boost_type = sanitize_key($_POST['boost_type'] ?? '');
    if (!$owner_id) wp_send_json_error(['message' => 'No owner account found']);

    $result = bod_create_boost_checkout($owner_id, $boost_type);
    $result['success'] ? wp_send_json_success(['redirect_url' => $result['url']]) : wp_send_json_error(['message' => $result['error']]);
});

// Mark listing as sold (owner)
add_action('wp_ajax_bod_mark_listing_sold', function() {
    if (!is_user_logged_in()) wp_send_json_error(['message' => 'Not logged in']);
    check_ajax_referer('bod_mark_sold', 'nonce');

    global $wpdb;
    $listing_id = absint($_POST['listing_id'] ?? 0);
    $owner_id   = bod_get_current_owner_id();
    $listing    = bod_get_listing($listing_id);

    if (!$listing || (int) $listing->owner_id !== $owner_id) wp_send_json_error(['message' => 'Listing not found']);

    $wpdb->update(BOD_TABLE_LISTINGS, [
        'listing_status' => 'sold',
        'sold_at'        => current_time('mysql'),
        'active_boost'   => 'none',
    ], ['id' => $listing_id]);

    // Increment sold count
    $wpdb->query($wpdb->prepare("UPDATE " . BOD_TABLE_OWNERS . " SET total_listings_sold = total_listings_sold + 1 WHERE id = %d", $owner_id));

    if ($listing->wp_product_id) {
        update_post_meta($listing->wp_product_id, '_bod_listing_status', 'sold');
    }

    wp_send_json_success(['message' => 'Listing marked as sold']);
});
