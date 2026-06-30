<?php
/**
 * Database Functions for Business Owner Dashboard
 */
if (!defined('ABSPATH')) exit;

// ============================================
// OWNER CRUD
// ============================================

function bod_get_owners($args = []) {
    global $wpdb;
    $table    = BOD_TABLE_OWNERS;
    $listings = BOD_TABLE_LISTINGS;
    $payments = BOD_TABLE_PAYMENTS;

    $defaults = [
        'approval_status'          => '',
        'search'                   => '',
        'date_from'                => '',
        'date_to'                  => '',
        'orderby'                  => 'created_at',
        'order'                    => 'DESC',
        'per_page'                 => 20,
        'page'                     => 1,
        'exclude_pending_payments' => true,
    ];
    $args  = wp_parse_args($args, $defaults);
    $where = ['1=1'];
    $params = [];

    if (!empty($args['approval_status'])) {
        $where[]  = 'o.approval_status = %s';
        $params[] = $args['approval_status'];
    }
    if (!empty($args['search'])) {
        $like     = '%' . $wpdb->esc_like($args['search']) . '%';
        $where[]  = '(o.owner_name LIKE %s OR o.owner_email LIKE %s OR o.owner_phone LIKE %s OR o.business_name LIKE %s)';
        $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like;
    }
    if (!empty($args['date_from'])) {
        $where[]  = 'o.created_at >= %s';
        $params[] = $args['date_from'] . ' 00:00:00';
    }
    if (!empty($args['date_to'])) {
        $where[]  = 'o.created_at <= %s';
        $params[] = $args['date_to'] . ' 23:59:59';
    }
    if (!empty($args['exclude_pending_payments'])) {
        $where[] = "NOT EXISTS (SELECT 1 FROM $payments pp WHERE pp.owner_id = o.id AND pp.payment_type = 'listing' AND pp.status = 'pending')";
    }

    $where_sql = implode(' AND ', $where);
    $orderby   = sanitize_sql_orderby($args['orderby'] . ' ' . $args['order']) ?: 'created_at DESC';
    $offset    = ($args['page'] - 1) * $args['per_page'];

    $count_sql = "SELECT COUNT(*) FROM $table o WHERE $where_sql";
    $total     = empty($params) ? (int) $wpdb->get_var($count_sql) : (int) $wpdb->get_var($wpdb->prepare($count_sql, $params));

    $sql = "SELECT o.*,
                (SELECT l.active_boost FROM $listings l WHERE l.owner_id = o.id AND l.active_boost != 'none' ORDER BY l.boost_paid_at DESC LIMIT 1) AS current_boost,
                (SELECT l.boost_paid_at FROM $listings l WHERE l.owner_id = o.id AND l.active_boost != 'none' ORDER BY l.boost_paid_at DESC LIMIT 1) AS current_boost_paid_at
            FROM $table o
            WHERE $where_sql
            ORDER BY $orderby LIMIT %d OFFSET %d";

    $params[] = $args['per_page'];
    $params[] = $offset;

    return [
        'owners'  => $wpdb->get_results($wpdb->prepare($sql, $params)),
        'total'   => $total,
        'pages'   => (int) ceil($total / $args['per_page']),
        'current' => $args['page'],
    ];
}

function bod_get_owner($id) {
    global $wpdb;
    return $wpdb->get_row($wpdb->prepare("SELECT * FROM " . BOD_TABLE_OWNERS . " WHERE id = %d", $id));
}

function bod_get_owner_by_email($email) {
    global $wpdb;
    return $wpdb->get_row($wpdb->prepare("SELECT * FROM " . BOD_TABLE_OWNERS . " WHERE owner_email = %s", $email));
}

function bod_get_owner_by_stripe_customer($customer_id) {
    global $wpdb;
    return $wpdb->get_row($wpdb->prepare("SELECT * FROM " . BOD_TABLE_OWNERS . " WHERE stripe_customer_id = %s", $customer_id));
}

function bod_get_owner_by_user_id($user_id) {
    global $wpdb;
    return $wpdb->get_row($wpdb->prepare("SELECT * FROM " . BOD_TABLE_OWNERS . " WHERE wp_user_id = %d", $user_id));
}

function bod_insert_owner($data) {
    global $wpdb;
    $defaults = [
        'approval_status'          => 'pending',
        'account_status'           => 'not_created',
        'available_listing_credits'=> 0,
        'total_listings_purchased' => 0,
        'total_listings_created'   => 0,
        'total_listings_sold'      => 0,
        'welcome_email_sent'       => 'no',
        'credentials_email_sent'   => 'no',
        'created_at'               => current_time('mysql'),
        'updated_at'               => current_time('mysql'),
    ];
    $data   = wp_parse_args($data, $defaults);
    $result = $wpdb->insert(BOD_TABLE_OWNERS, $data);
    if ($result) {
        error_log('[BOD] Owner inserted: ID ' . $wpdb->insert_id);
        return $wpdb->insert_id;
    }
    error_log('[BOD] Failed to insert owner: ' . $wpdb->last_error);
    return false;
}

function bod_update_owner($id, $data) {
    global $wpdb;
    $data['updated_at'] = current_time('mysql');
    return $wpdb->update(BOD_TABLE_OWNERS, $data, ['id' => $id]);
}

function bod_get_dashboard_counts() {
    global $wpdb;
    $t  = BOD_TABLE_OWNERS;
    $lt = BOD_TABLE_LISTINGS;
    return $wpdb->get_row("
        SELECT
            COUNT(*) as total,
            SUM(CASE WHEN approval_status = 'pending' THEN 1 ELSE 0 END) as pending_approval,
            SUM(CASE WHEN approval_status = 'approved' THEN 1 ELSE 0 END) as active,
            (SELECT COUNT(*) FROM $lt WHERE listing_status = 'active') as total_listings
        FROM $t
    ");
}

// ============================================
// APPROVAL HELPERS
// ============================================

function bod_update_approval_status($owner_id, $status, $approved_by = null, $reason = null) {
    $data = ['approval_status' => $status];
    if ($status === 'approved') {
        $data['approved_at'] = current_time('mysql');
        $data['approved_by'] = $approved_by ?: wp_get_current_user()->display_name;
    } elseif ($status === 'rejected' && $reason) {
        $data['rejection_reason'] = $reason;
    }
    return bod_update_owner($owner_id, $data);
}

function bod_render_approval_badge($status) {
    $map = [
        'pending'  => ['#ffc107', '#212529', 'Pending'],
        'approved' => ['#28a745', '#fff',     'Approved'],
        'rejected' => ['#dc3545', '#fff',     'Rejected'],
    ];
    [$bg, $color, $label] = $map[$status] ?? ['#6c757d', '#fff', ucfirst($status)];
    return sprintf('<span style="display:inline-block;padding:3px 8px;border-radius:3px;font-size:12px;font-weight:600;background:%s;color:%s;">%s</span>', $bg, $color, esc_html($label));
}

function bod_render_account_badge($status) {
    $map = [
        'not_created' => ['#6c757d', '#fff', 'Not Created'],
        'created'     => ['#17a2b8', '#fff', 'Created'],
        'active'      => ['#28a745', '#fff', 'Active'],
    ];
    [$bg, $color, $label] = $map[$status] ?? ['#6c757d', '#fff', ucfirst($status)];
    return sprintf('<span style="display:inline-block;padding:3px 8px;border-radius:3px;font-size:12px;font-weight:600;background:%s;color:%s;">%s</span>', $bg, $color, esc_html($label));
}

// ============================================
// LISTING CRUD
// ============================================

function bod_get_listings_by_owner($owner_id) {
    global $wpdb;
    return $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM " . BOD_TABLE_LISTINGS . " WHERE owner_id = %d ORDER BY created_at DESC",
        $owner_id
    ));
}

function bod_get_listing($id) {
    global $wpdb;
    return $wpdb->get_row($wpdb->prepare("SELECT * FROM " . BOD_TABLE_LISTINGS . " WHERE id = %d", $id));
}

function bod_create_listing_record($owner_id, $checkout_session) {
    global $wpdb;
    $amount       = ($checkout_session->amount_total ?? 0) / 100;
    $gst          = round($amount - ($amount / 1.1), 2);
    $promotion    = sanitize_text_field((string) ($checkout_session->metadata->promotion_code ?? ''));
    $discount     = (($checkout_session->total_details->amount_discount ?? 0) / 100);

    $result = $wpdb->insert(BOD_TABLE_LISTINGS, [
        'owner_id'                   => $owner_id,
        'stripe_checkout_session_id' => $checkout_session->id,
        'stripe_payment_intent_id'   => $checkout_session->payment_intent ?? null,
        'payment_amount'             => $amount,
        'payment_status'             => 'pending',
        'listing_status'             => 'credit',
        'created_at'                 => current_time('mysql'),
        'updated_at'                 => current_time('mysql'),
    ]);

    return $result ? $wpdb->insert_id : false;
}

function bod_activate_listing($listing_id, $owner_id) {
    global $wpdb;
    $now = current_time('mysql');
    $wpdb->update(BOD_TABLE_LISTINGS, [
        'payment_status' => 'paid',
        'listing_status' => 'credit',
        'paid_at'        => $now,
        'activated_at'   => $now,
    ], ['id' => $listing_id]);

    // Increment owner credits
    $wpdb->query($wpdb->prepare(
        "UPDATE " . BOD_TABLE_OWNERS . " SET available_listing_credits = available_listing_credits + 1, total_listings_purchased = total_listings_purchased + 1 WHERE id = %d",
        $owner_id
    ));
}

function bod_create_payment_record($owner_id, $listing_id, $type, $checkout_session) {
    global $wpdb;
    $amount   = ($checkout_session->amount_total ?? 0) / 100;
    $gst      = round($amount - ($amount / 1.1), 2);
    $src      = sanitize_text_field((string) ($checkout_session->metadata->payment_source ?? 'stripe'));
    $promo    = sanitize_text_field((string) ($checkout_session->metadata->promotion_code ?? ''));
    $discount = (($checkout_session->total_details->amount_discount ?? 0) / 100);

    $wpdb->insert(BOD_TABLE_PAYMENTS, [
        'owner_id'                   => $owner_id,
        'listing_id'                 => $listing_id ?: null,
        'payment_type'               => $type,
        'stripe_checkout_session_id' => $checkout_session->id,
        'stripe_payment_intent_id'   => $checkout_session->payment_intent ?? null,
        'payment_source'             => $src,
        'promotion_code'             => $promo,
        'discount_amount'            => $discount,
        'amount'                     => $amount,
        'currency'                   => $checkout_session->currency ?? 'aud',
        'amount_gst'                 => $gst,
        'status'                     => 'succeeded',
        'completed_at'               => current_time('mysql'),
        'created_at'                 => current_time('mysql'),
    ]);
    return $wpdb->insert_id;
}

function bod_get_owner_payments($owner_id) {
    global $wpdb;
    return $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM " . BOD_TABLE_PAYMENTS . " WHERE owner_id = %d ORDER BY created_at DESC",
        $owner_id
    ));
}

// ============================================
// NOTIFICATIONS
// ============================================
function bod_add_notification($owner_id, $type, $title, $message) {
    global $wpdb;
    $wpdb->insert(BOD_TABLE_NOTIFICATIONS, [
        'owner_id'   => $owner_id,
        'type'       => $type,
        'title'      => $title,
        'message'    => $message,
        'is_read'    => 0,
        'created_at' => current_time('mysql'),
    ]);
}

function bod_get_unread_notifications($owner_id) {
    global $wpdb;
    return $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM " . BOD_TABLE_NOTIFICATIONS . " WHERE owner_id = %d AND is_read = 0 ORDER BY created_at DESC",
        $owner_id
    ));
}
function bod_get_profile_view_series($owner_id, $days) {
    $labels = $values = [];
    for ($i = $days - 1; $i >= 0; $i--) {
        $date = date('d M', strtotime("-{$i} days"));
        $labels[] = $date;
        $values[] = 0; // wire to real analytics table here once you track views
    }
    return ['labels' => $labels, 'values' => $values];
}
/**
 * Get all enquiries for businesses owned by this owner.
 */
function bod_get_owner_enquiries($owner_id, $limit = 100) {
    global $wpdb;
    $owner = bod_get_owner($owner_id);
    if (!$owner || empty($owner->wp_user_id)) return [];

    $business_ids = $wpdb->get_col($wpdb->prepare(
        "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'business' AND post_author = %d AND post_status = 'publish'",
        $owner->wp_user_id
    ));
    if (empty($business_ids)) return [];

    $placeholders = implode(',', array_fill(0, count($business_ids), '%d'));
    $table = $wpdb->prefix . 'crs_enquiries';

    return $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$table} WHERE business_id IN ({$placeholders}) ORDER BY created_at DESC LIMIT %d",
        array_merge($business_ids, [$limit])
    ));
}

function bod_enquiry_initials($name) {
    $parts = preg_split('/\s+/', trim($name));
    $initials = '';
    foreach (array_slice($parts, 0, 2) as $p) {
        $initials .= mb_substr($p, 0, 1);
    }
    return strtoupper($initials) ?: '??';
}

function bod_enquiry_avatar_class($index) {
    $palette = ['tfx-soft-green', 'tfx-soft-purple', 'tfx-soft-orange', 'tfx-soft-blue', 'tfx-soft-pink'];
    return $palette[$index % count($palette)];
}

function bod_enquiry_status_class($status) {
    $map = [
        'new'        => 'tfx-status-new',
        'contacted'  => 'tfx-status-contacted',
        'in_progress'=> 'tfx-status-progress',
        'closed'     => 'tfx-status-closed',
    ];
    return $map[$status] ?? 'tfx-status-new';
}

function bod_enquiry_status_label($status) {
    $map = [
        'new'         => 'New',
        'contacted'   => 'Contacted',
        'in_progress' => 'In Progress',
        'closed'      => 'Closed',
    ];
    return $map[$status] ?? 'New';
}
function crs_submit_pending_field_change($business_id, $owner_id, $field_key, $new_value) {
    global $wpdb;
    $table = $wpdb->prefix . 'crs_pending_changes';
    $old_value = get_post_meta($business_id, '_' . $field_key, true);

    if ($old_value == $new_value) return false; // no actual change

    $wpdb->insert($table, [
        'business_id'  => $business_id,
        'owner_id'     => $owner_id,
        'change_type'  => 'field',
        'field_key'    => $field_key,
        'old_value'    => $old_value,
        'new_value'    => $new_value,
        'status'       => 'pending',
        'submitted_at' => current_time('mysql'),
    ]);
    return $wpdb->insert_id;
}

function crs_submit_pending_image_change($business_id, $owner_id, $attachment_id) {
    global $wpdb;
    $table = $wpdb->prefix . 'crs_pending_changes';
    $current_logo_id = get_post_meta($business_id, '_crs_logo', true);

    $wpdb->insert($table, [
        'business_id'  => $business_id,
        'owner_id'     => $owner_id,
        'change_type'  => 'image',
        'field_key'    => 'crs_logo', // or 'gallery_1' etc — pass which slot
        'old_value'    => $current_logo_id,
        'new_value'    => $attachment_id,
        'image_url'    => wp_get_attachment_url($attachment_id),
        'status'       => 'pending',
        'submitted_at' => current_time('mysql'),
    ]);

    // Notify admin
    wp_mail(get_option('admin_email'), 'New Image Pending Approval', 'Business #' . $business_id . ' uploaded a new image — review in admin.');

    return $wpdb->insert_id;
}

function crs_get_pending_changes($status = 'pending', $business_id = null) {
    global $wpdb;
    $table = $wpdb->prefix . 'crs_pending_changes';
    $where = ['status = %s'];
    $params = [$status];
    if ($business_id) {
        $where[] = 'business_id = %d';
        $params[] = $business_id;
    }
    $sql = "SELECT * FROM {$table} WHERE " . implode(' AND ', $where) . " ORDER BY submitted_at DESC";
    return $wpdb->get_results($wpdb->prepare($sql, $params));
}

function crs_has_pending_changes($business_id) {
    global $wpdb;
    $table = $wpdb->prefix . 'crs_pending_changes';
    return (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$table} WHERE business_id = %d AND status = 'pending'",
        $business_id
    ));
}

/**
 * Active boosts stored as serialized array:
 * [ 'featured' => ['expires' => '...', 'auto_renew' => 1], 'homepage' => [...] ]
 */
function bod_get_active_boosts($business_id) {
    $boosts = get_post_meta($business_id, '_active_boosts', true);
    if (!is_array($boosts)) return [];

    $live = [];
    foreach ($boosts as $type => $data) {
        if (!empty($data['expires']) && strtotime($data['expires']) > time()) {
            $live[$type] = $data;
        }
    }
    return $live; // only currently-live boosts
}

function bod_is_boost_active($business_id, $boost_type) {
    $live = bod_get_active_boosts($business_id);
    return isset($live[$boost_type]);
}

function bod_activate_boost($business_id, $owner_id, $plan_id, $boost_key) {
    $charge   = (float) get_post_meta($plan_id, '_plan_charge_amount', true);
    $duration = (int) get_post_meta($plan_id, '_plan_duration', true) ?: 30;

    $boosts = get_post_meta($business_id, '_active_boosts', true);
    if (!is_array($boosts)) $boosts = [];

    $boosts[$boost_key] = [
        'plan_id'      => $plan_id,
        'owner_id'     => $owner_id,
        'charge'       => $charge,
        'renewal_date' => date('Y-m-d H:i:s', strtotime("+{$duration} days")),
        'auto_renew'   => 1,
    ];
    update_post_meta($business_id, '_active_boosts', $boosts);
}

function bod_cancel_boost_renewal($business_id, $boost_type) {
    $boosts = get_post_meta($business_id, '_active_boosts', true);
    if (!is_array($boosts) || !isset($boosts[$boost_type])) return;

    $boosts[$boost_type]['auto_renew'] = 0; // stays live till expiry, just won't renew
    update_post_meta($business_id, '_active_boosts', $boosts);
}

/**
 * Fetch add-on plans dynamically from crs_sub_plan CPT
 * (Plan Type = addon, Plan Status = active) — admin-controlled, no static array.
 */
function bod_get_active_addon_plans() {
    return get_posts([
        'post_type'      => 'crs_sub_plan',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'orderby'        => 'menu_order title',
        'order'          => 'ASC',
        'meta_query'     => [
            'relation' => 'AND',
            ['key' => '_plan_status', 'value' => 'active'],
            ['key' => '_plan_type',   'value' => 'addon'],
        ],
    ]);
}
/**
 * Resolve the 'business' CPT post linked to this owner.
 * Falls back to querying by author/meta if no direct link exists.
 */
function bod_get_owner_business_id($owner_id) {
    $owner = bod_get_owner($owner_id);
    if (!$owner) return 0;

    // If you already store a direct link, use that first
    if (!empty($owner->business_post_id)) {
        return (int) $owner->business_post_id;
    }

    // Fallback: find the business post by post_author = wp_user_id
    if (!empty($owner->wp_user_id)) {
        $posts = get_posts([
            'post_type'      => 'business',
            'post_status'    => 'any',
            'post_author'    => $owner->wp_user_id,
            'posts_per_page' => 1,
        ]);
        if (!empty($posts)) {
            return (int) $posts[0]->ID;
        }
    }

    return 0;
}
function bod_create_billing_portal_session($owner_id) {
    $owner = bod_get_owner($owner_id);
    if (!$owner || empty($owner->stripe_customer_id)) return false;

    if (!class_exists('\Stripe\Stripe')) bod_init_stripe();
    \Stripe\Stripe::setApiKey(BOD_STRIPE_SECRET_KEY ?: get_option('bod_stripe_secret_key', ''));

    try {
        $session = \Stripe\BillingPortal\Session::create([
            'customer'   => $owner->stripe_customer_id,
            'return_url' => home_url('/business-owner-dashboard/?view=subscription'),
        ]);
        return $session->url;
    } catch (\Throwable $e) {
        error_log('[BOD Portal] Error: ' . $e->getMessage());
        return false;
    }
}