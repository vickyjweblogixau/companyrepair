<?php
/**
 * User Functions for Business Owner Dashboard
 * Auto account creation flow — mirrors private-sellers-plugin/user-functions.php
 */
if (!defined('ABSPATH')) exit;

/**
 * Register business_owner role (if not already created on the CRS site)
 */
function bod_register_business_owner_role() {
    if (!get_role('business_owner')) {
        add_role('business_owner', 'Business Owner', [
            'read'         => true,
            'upload_files' => true,
        ]);
    }
}
add_action('init', 'bod_register_business_owner_role');

/**
 * Auto-create WordPress account for business owner.
 * Called after Stripe payment confirmed via webhook.
 */
function bod_create_user_account($owner_id) {
    $owner = bod_get_owner($owner_id);
    if (!$owner) return ['success' => false, 'error' => 'Owner not found'];
    if ($owner->wp_user_id) return ['success' => false, 'error' => 'Account already exists'];

    // Generate unique username from email
    $parts         = explode('@', $owner->owner_email);
    $base_username = sanitize_user($parts[0], true);
    $username      = $base_username;
    $i             = 1;
    while (username_exists($username)) {
        $username = $base_username . $i++;
    }

    $password = wp_generate_password(12, true, false);

    $user_data = [
        'user_login'   => $username,
        'user_email'   => $owner->owner_email,
        'user_pass'    => $password,
        'display_name' => $owner->owner_name,
        'first_name'   => explode(' ', $owner->owner_name)[0],
        'last_name'    => implode(' ', array_slice(explode(' ', $owner->owner_name), 1)),
        'role'         => 'business_owner',
    ];

    $user_id = wp_insert_user($user_data);
    if (is_wp_error($user_id)) {
        error_log('[BOD] Failed to create user: ' . $user_id->get_error_message());
        return ['success' => false, 'error' => $user_id->get_error_message()];
    }

    // Store meta
    update_user_meta($user_id, 'bod_owner_id', $owner_id);
    update_user_meta($user_id, 'bod_owner_type', 'business');

    // Update owner record
    bod_update_owner($owner_id, [
        'wp_user_id'          => $user_id,
        'username'            => $username,
        'user_password_plain' => $password,
        'account_status'      => 'created',
        'account_created_at'  => current_time('mysql'),
    ]);

    error_log('[BOD] User account created: ' . $username . ' (ID: ' . $user_id . ')');
    return ['success' => true, 'user_id' => $user_id, 'username' => $username, 'password' => $password];
}

/**
 * Get owner ID from logged-in user
 */
function bod_get_current_owner_id() {
    if (!is_user_logged_in()) return null;
    $owner_id = get_user_meta(get_current_user_id(), 'bod_owner_id', true);
    return $owner_id ? (int) $owner_id : null;
}

function bod_get_current_owner() {
    $id = bod_get_current_owner_id();
    return $id ? bod_get_owner($id) : null;
}

function bod_is_business_owner() {
    if (!is_user_logged_in()) return false;
    return in_array('business_owner', (array) wp_get_current_user()->roles);
}

/**
 * Redirect business owner after login
 */
add_filter('login_redirect', 'bod_login_redirect', 10, 3);
function bod_login_redirect($redirect_to, $requested, $user) {
    if (!is_wp_error($user) && isset($user->ID) && in_array('business_owner', (array) $user->roles)) {
        // Clear stored plain password after first login
        $owner_id = get_user_meta($user->ID, 'bod_owner_id', true);
        if ($owner_id) {
            $owner = bod_get_owner($owner_id);
            if ($owner && $owner->account_status === 'created') {
                bod_update_owner($owner_id, [
                    'account_status'      => 'active',
                    'user_password_plain' => '',
                ]);
            }
        }
        return home_url('/business-owner-dashboard/');
    }
    return $redirect_to;
}

/**
 * Restrict dashboard pages to logged-in business owners
 */
add_action('template_redirect', 'bod_restrict_dashboard_pages');
function bod_restrict_dashboard_pages() {
    $dashboard_page_id = (int) get_option('bod_dashboard_page_id');
    if (!$dashboard_page_id || !is_page($dashboard_page_id)) return;

    if (!is_user_logged_in()) {
        wp_redirect(home_url('/business-owner-login/?redirect_to=' . urlencode($_SERVER['REQUEST_URI'])));
        exit;
    }
    if (!bod_is_business_owner() && !current_user_can('manage_options')) {
        wp_redirect(home_url('/'));
        exit;
    }
}

/**
 * Show owner info on WP user profile
 */
add_action('show_user_profile', 'bod_show_owner_profile_info');
add_action('edit_user_profile', 'bod_show_owner_profile_info');
function bod_show_owner_profile_info($user) {
    $owner_id = get_user_meta($user->ID, 'bod_owner_id', true);
    if (!$owner_id) return;
    $owner = bod_get_owner($owner_id);
    if (!$owner) return;
    ?>
    <h3>Business Owner Information</h3>
    <table class="form-table">
        <tr><th>Owner ID</th><td><?php echo esc_html((string) $owner->id); ?></td></tr>
        <tr><th>Business Name</th><td><?php echo esc_html((string) ($owner->business_name ?? '-')); ?></td></tr>
        <tr><th>Approval Status</th><td><?php echo esc_html((string) $owner->approval_status); ?></td></tr>
        <tr><th>Listing Credits</th><td><?php echo esc_html((string) $owner->available_listing_credits); ?></td></tr>
        <?php if (current_user_can('manage_options')): ?>
        <tr><th>Admin Link</th><td>
            <a href="<?php echo esc_url(admin_url('admin.php?page=business-owners&action=view&id=' . $owner->id)); ?>">View in Business Owners Admin</a>
        </td></tr>
        <?php endif; ?>
    </table>
    <?php
}

/**
 * Account delete request handling (mirrors private-sellers-plugin delete flow)
 */
add_action('wp_ajax_bod_request_account_delete', 'bod_ajax_request_account_delete');
function bod_ajax_request_account_delete() {
    if (!is_user_logged_in()) wp_send_json_error(['message' => 'Not logged in']);
    $user_id = get_current_user_id();
    update_user_meta($user_id, 'bod_deletion_requested', '1');
    update_user_meta($user_id, 'bod_deletion_requested_at', current_time('mysql'));
    update_user_meta($user_id, 'bod_deletion_due_at', date('Y-m-d H:i:s', strtotime('+30 days')));
    wp_send_json_success(['message' => 'Deletion request submitted. Your account will be reviewed.']);
}
