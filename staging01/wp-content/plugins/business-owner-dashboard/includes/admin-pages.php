<?php
/**
 * Admin Pages for Business Owner Dashboard
 * All Owners, Pending Approval, Pending Payments, Owner Detail
 */
if (!defined('ABSPATH')) exit;
// ============================================
// ALL OWNERS LIST
// ============================================
function bod_render_owners_list() {
    if (isset($_GET['action']) && $_GET['action'] === 'view' && isset($_GET['id'])) {
        bod_render_owner_detail((int) $_GET['id']);
        return;
    }
    $counts          = bod_get_dashboard_counts();
    $approval_filter = sanitize_text_field($_GET['approval'] ?? '');
    $search          = sanitize_text_field($_GET['s'] ?? '');
    $date_from       = sanitize_text_field($_GET['date_from'] ?? '');
    $date_to         = sanitize_text_field($_GET['date_to'] ?? '');
    $paged           = max(1, (int) ($_GET['paged'] ?? 1));
    $result     = bod_get_owners([
        'approval_status' => $approval_filter,
        'search' => $search,
        'date_from' => $date_from,
        'date_to' => $date_to, 
        'page' => $paged,
        'exclude_pending_payments' => false,
        ]);
    $owners     = $result['owners'];
    $total      = $result['total'];
    $total_pages = $result['pages'];
    $has_filters = ($approval_filter || $search || $date_from || $date_to);
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline">Business Owners</h1>
        <div class="ps-dashboard-stats" style="display:flex;gap:12px;margin:16px 0;">
            <?php $stat_cards = [
                ['Total Owners',    $counts->total           ?? 0, ''],
                ['Pending Approval',$counts->pending_approval ?? 0, 'background:#fff3cd'],
                ['Active',          $counts->active          ?? 0, 'background:#d4edda'],
                ['Total Listings',  $counts->total_listings  ?? 0, 'background:#cce5ff'],
            ]; ?>
            <?php foreach ($stat_cards as [$label, $count, $style]) : ?>
                <div style="background:#fff;border:1px solid #e0e0e0;border-radius:8px;padding:16px 20px;text-align:center;min-width:120px;<?php echo $style; ?>">
                    <div style="font-size:28px;font-weight:700;"><?php echo (int) $count; ?></div>
                    <div style="font-size:12px;color:#666;"><?php echo esc_html($label); ?></div>
                </div>
            <?php endforeach; ?>
        </div>
        <!-- Filters -->
        <form method="get" style="display:flex;flex-wrap:wrap;align-items:center;gap:8px;padding:10px 0;">
            <input type="hidden" name="page" value="business-owners">
            <select name="approval">
                <option value="">All Status</option>
                <option value="pending"  <?php selected($approval_filter, 'pending');  ?>>Pending</option>
                <option value="approved" <?php selected($approval_filter, 'approved'); ?>>Approved</option>
                <option value="rejected" <?php selected($approval_filter, 'rejected'); ?>>Rejected</option>
            </select>
            <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="Search owners...">
            <label>From <input type="date" name="date_from" value="<?php echo esc_attr($date_from); ?>"></label>
            <label>To   <input type="date" name="date_to"   value="<?php echo esc_attr($date_to); ?>"></label>
            <button type="submit" class="button button-primary">Filter</button>
            <?php if ($has_filters) : ?><a href="<?php echo admin_url('admin.php?page=business-owners'); ?>" class="button">Clear</a><?php endif; ?>
        </form>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th width="5%">ID</th>
                    <th width="18%">Owner / Business</th>
                    <th width="12%">Contact</th>
                    <th width="10%">Location</th>
                    <th width="12%">Signed Up</th>
                    <th width="10%">Listings</th>
                    <th width="10%">Approval</th>
                    <th width="10%">Account</th>
                    <th width="10%">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($owners)) : ?>
                <tr><td colspan="9">No business owners found.</td></tr>
            <?php else : ?>
                <?php foreach ($owners as $o) : ?>
                <tr>
                    <td><?php echo (int) $o->id; ?></td>
                    <td>
                        <strong><?php echo esc_html($o->owner_name); ?></strong><br>
                        <small><?php echo esc_html($o->owner_email); ?></small>
                        <?php if ($o->business_name) : ?><br><em style="font-size:11px;color:#888;"><?php echo esc_html($o->business_name); ?></em><?php endif; ?>
                    </td>
                    <td><?php echo esc_html($o->owner_phone); ?></td>
                    <td>
                        <?php echo esc_html($o->suburb ?? ''); ?>
                        <?php if ($o->state) : ?><br><small><?php echo esc_html($o->state . ' ' . ($o->postal_code ?? '')); ?></small><?php endif; ?>
                    </td>
                    <td>
                        <?php echo bod_format_datetime($o->created_at, 'M j, Y'); ?><br>
                        <small><?php echo bod_format_datetime($o->created_at, 'g:ia T'); ?></small>
                    </td>
                    <td>
                        <?php echo (int) $o->total_listings_purchased; ?> purchased
                        <?php if ($o->available_listing_credits > 0) : ?>
                            <br><small style="color:#28a745;"><?php echo (int) $o->available_listing_credits; ?> credit(s)</small>
                        <?php endif; ?>
                    </td>
                    <td><?php echo bod_render_approval_badge($o->approval_status); ?></td>
                    <td><?php echo bod_render_account_badge($o->account_status); ?></td>
                    <td>
                        <a href="<?php echo admin_url('admin.php?page=business-owners&action=view&id=' . $o->id); ?>" class="button button-small">View</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
        <!-- Pagination -->
        <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 0;">
            <span style="color:#666;">
                <?php
                $from = (($paged - 1) * 20) + 1;
                $to   = min($paged * 20, $total);
                echo $total > 0
                    ? 'Showing ' . $from . '–' . $to . ' of ' . $total . ' owners'
                    : 'No results';       
                ?>
            </span>
            <?php if ($total_pages > 1) :
                $base = admin_url('admin.php?page=business-owners') . ($approval_filter ? '&approval=' . urlencode($approval_filter) : '') . ($search ? '&s=' . urlencode($search) : '');
                echo paginate_links(['base' => $base . '&paged=%#%', 'format' => '', 'current' => $paged, 'total' => $total_pages]);
            endif; ?>
        </div>
    </div>
    <?php
}
function bod_render_pending_owners() {
    $_GET['approval'] = 'pending';
    bod_render_owners_list();
}
// ============================================
// PENDING PAYMENTS
// ============================================
function bod_render_pending_payments() {
    global $wpdb;
    $search    = sanitize_text_field($_GET['s'] ?? '');
    $date_from = sanitize_text_field($_GET['date_from'] ?? '');
    $date_to   = sanitize_text_field($_GET['date_to'] ?? '');
    $paged     = max(1, (int) ($_GET['paged'] ?? 1));
    $per_page  = 20;
    $offset    = ($paged - 1) * $per_page;
    $pt = BOD_TABLE_PAYMENTS;
    $ot = BOD_TABLE_OWNERS;
    $where  = ["p.payment_type = 'listing'", "p.status = 'pending'"];
    $params = [];
    if ($search !== '') {
        $like     = '%' . $wpdb->esc_like($search) . '%';
        $where[]  = '(o.owner_name LIKE %s OR o.owner_email LIKE %s OR p.stripe_checkout_session_id LIKE %s)';
        $params[] = $like; $params[] = $like; $params[] = $like;
    }
    if ($date_from !== '') { $where[] = 'p.created_at >= %s'; $params[] = $date_from . ' 00:00:00'; }
    if ($date_to   !== '') { $where[] = 'p.created_at <= %s'; $params[] = $date_to . ' 23:59:59'; }
    $where_sql = implode(' AND ', $where);
    $count_sql = "SELECT COUNT(*) FROM $pt p LEFT JOIN $ot o ON p.owner_id = o.id WHERE $where_sql";
    $total     = empty($params) ? (int) $wpdb->get_var($count_sql) : (int) $wpdb->get_var($wpdb->prepare($count_sql, $params));
    $sql_params  = array_merge($params, [$per_page, $offset]);
    $rows        = $wpdb->get_results($wpdb->prepare(
        "SELECT p.*, o.owner_name, o.owner_email, o.owner_phone FROM $pt p LEFT JOIN $ot o ON p.owner_id = o.id WHERE $where_sql ORDER BY p.created_at DESC LIMIT %d OFFSET %d",
        $sql_params
    ));
    ?>
    <div class="wrap">
        <h1>Pending Payments</h1>
        <p>Checkout sessions started but not yet completed (payment not confirmed by Stripe).</p>
        <form method="get" style="display:flex;gap:8px;padding:10px 0;">
            <input type="hidden" name="page" value="business-owners-pending-payments">
            <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="Search...">
            <label>From <input type="date" name="date_from" value="<?php echo esc_attr($date_from); ?>"></label>
            <label>To   <input type="date" name="date_to"   value="<?php echo esc_attr($date_to); ?>"></label>
            <button type="submit" class="button button-primary">Filter</button>
        </form>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>ID</th><th>Owner</th><th>Email</th><th>Amount</th><th>Checkout Session</th><th>Started</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($rows)) : ?>
                <tr><td colspan="6">No pending payments.</td></tr>
            <?php else : ?>
                <?php foreach ($rows as $r) : ?>
                <tr>
                    <td><?php echo (int) $r->id; ?></td>
                    <td><?php echo esc_html($r->owner_name ?? '-'); ?></td>
                    <td><?php echo esc_html($r->owner_email ?? '-'); ?></td>
                    <td>$<?php echo number_format((float) $r->amount, 2); ?> <?php echo strtoupper(esc_html($r->currency)); ?></td>
                    <td><code style="font-size:11px;"><?php echo esc_html($r->stripe_checkout_session_id ?: '-'); ?></code></td>
                    <td><?php echo bod_format_datetime($r->created_at); ?></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
        <p style="color:#666;margin-top:8px;">Total: <?php echo (int) $total; ?> pending</p>
    </div>
    <?php
}
// ============================================
// OWNER DETAIL VIEW
// ============================================
function bod_render_owner_detail($owner_id) {
    $owner = bod_get_owner($owner_id);
    if (!$owner) { echo '<div class="wrap"><p>Owner not found.</p></div>'; return; }
    $listings = bod_get_listings_by_owner($owner_id);
    $payments = bod_get_owner_payments($owner_id);
    ?>
    <div class="wrap">
        <h1>Business Owner: <?php echo esc_html($owner->owner_name); ?></h1>
        <a href="<?php echo admin_url('admin.php?page=business-owners'); ?>" class="button" style="margin-bottom:16px;">← Back to All Owners</a>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:24px;">
            <!-- Owner Info -->
            <div style="background:#fff;border:1px solid #e0e0e0;border-radius:8px;padding:20px;">
                <h2 style="margin-top:0;">Owner Details</h2>
                <table class="widefat" style="border:none;">
                    <tr><td><strong>Name</strong></td><td><?php echo esc_html($owner->owner_name); ?></td></tr>
                    <tr><td><strong>Email</strong></td><td><?php echo esc_html($owner->owner_email); ?></td></tr>
                    <tr><td><strong>Phone</strong></td><td><?php echo esc_html($owner->owner_phone); ?></td></tr>
                    <tr><td><strong>Business</strong></td><td><?php echo esc_html($owner->business_name ?: '-'); ?></td></tr>
                    <tr><td><strong>Location</strong></td><td><?php echo esc_html(($owner->suburb ?? '') . ', ' . ($owner->state ?? '') . ' ' . ($owner->postal_code ?? '')); ?></td></tr>
                    <tr><td><strong>Signed Up</strong></td><td><?php echo bod_format_datetime($owner->created_at); ?></td></tr>
                    <?php if ($owner->stripe_customer_id) : ?>
                    <tr><td><strong>Stripe</strong></td><td>
                        <code style="font-size:11px;"><?php echo esc_html($owner->stripe_customer_id); ?></code>
                        <a href="https://dashboard.stripe.com/customers/<?php echo esc_attr($owner->stripe_customer_id); ?>" target="_blank" class="button button-small" style="margin-left:6px;">View in Stripe</a>
                    </td></tr>
                    <?php endif; ?>
                </table>
            </div>
            <!-- Account & Actions -->
            <div style="background:#fff;border:1px solid #e0e0e0;border-radius:8px;padding:20px;">
                <h2 style="margin-top:0;">Account Status</h2>
                <p>
                    <strong>Approval:</strong> <?php echo bod_render_approval_badge($owner->approval_status); ?><br><br>
                    <strong>Account:</strong> <?php echo bod_render_account_badge($owner->account_status); ?>
                    <?php if ($owner->account_created_at) : ?>
                        <small>(<?php echo bod_format_datetime($owner->account_created_at, 'M j, Y g:ia'); ?>)</small>
                    <?php endif; ?>
                </p>
                <p>
                    <strong>Listing Credits:</strong> <?php echo (int) $owner->available_listing_credits; ?> available
                    / <?php echo (int) $owner->total_listings_purchased; ?> purchased
                </p>
                <?php if ($owner->wp_user_id) : ?>
                    <p><strong>WP User:</strong> <a href="<?php echo admin_url('user-edit.php?user_id=' . $owner->wp_user_id); ?>">#<?php echo (int) $owner->wp_user_id; ?></a>
                    (<?php echo esc_html($owner->username); ?>)</p>
                <?php endif; ?>
                <div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:16px;">
                    <?php if ($owner->approval_status === 'pending') : ?>
                        <button class="button button-primary bod-approve-btn" data-id="<?php echo  (int) $owner->id; ?>">✓ Approve</button>
                        <button class="button bod-reject-btn" data-id="<?php echo $owner->id; ?>">✗ Reject</button>
                    <?php endif; ?>
                    <?php if ($owner->approval_status === 'approved' && !$owner->wp_user_id) : ?>
                        <button class="button button-primary bod-create-account-btn" data-id="<?php echo  (int)  $owner->id; ?>">Create Account</button>
                    <?php endif; ?>
                    <?php if ($owner->wp_user_id) : ?>
                        <button class="button bod-resend-credentials-btn" data-id="<?php echo $owner->id; ?>">Resend Credentials</button>
                    <?php endif; ?>
                    <button class="button bod-grant-listing-btn" data-id="<?php echo $owner->id; ?>">+ Grant Listing Credit</button>
                </div>
                <?php if ($owner->admin_notes) : ?>
                    <p style="margin-top:12px;padding:8px;background:#fff8e1;border-radius:4px;"><strong>Notes:</strong> <?php echo nl2br(esc_html($owner->admin_notes)); ?></p>
                <?php endif; ?>
            </div>
        </div>
        <!-- Listings -->
        <h2>Listings (<?php echo count($listings); ?>)</h2>
        <table class="wp-list-table widefat fixed striped">
            <thead><tr><th>ID</th><th>Product</th><th>Status</th><th>Boost</th><th>Payment</th><th>Created</th><th>Actions</th></tr></thead>
            <tbody>
            <?php if (empty($listings)) : ?>
                <tr><td colspan="7">No listings yet.</td></tr>
            <?php else : ?>
                <?php foreach ($listings as $l) :
                    $product_link = $l->wp_product_id ? get_edit_post_link($l->wp_product_id) : '';
                    ?>
                <tr>
                    <td><?php echo (int) $l->id; ?></td>
                    <td>
                        <?php if ($product_link) : ?><a href="<?php echo esc_url($product_link); ?>"><?php echo esc_html($l->product_title ?: 'Product #' . $l->wp_product_id); ?></a>
                        <?php else : echo esc_html($l->product_title ?: '(not created)'); endif; ?>
                    </td>
                    <td><?php echo esc_html($l->listing_status); ?></td>
                    <td><?php echo $l->active_boost !== 'none' ? esc_html(ucfirst($l->active_boost)) : '—'; ?></td>
                    <td>
                        <?php echo esc_html($l->payment_status); ?>
                        <?php if ($l->paid_at) : ?><br><small><?php echo bod_format_datetime($l->paid_at, 'M j, Y'); ?></small><?php endif; ?>
                    </td>
                    <td><?php echo bod_format_datetime($l->created_at, 'M j, Y'); ?></td>
                    <td>
                        <?php if ($l->listing_status === 'active') : ?>
                            <button class="button button-small bod-cancel-listing-btn" data-id="<?php echo $l->id; ?>" data-owner="<?php echo $owner->id; ?>">Cancel</button>
                        <?php elseif (in_array($l->listing_status, ['credit', 'sold']) && $l->can_reactivate) : ?>
                            <button class="button button-small bod-reactivate-listing-btn" data-id="<?php echo $l->id; ?>">Reactivate</button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
        <!-- Payment History -->
        <h2 style="margin-top:24px;">Payment History (<?php echo count($payments); ?>)</h2>
        <table class="wp-list-table widefat fixed striped">
            <thead><tr><th>ID</th><th>Type</th><th>Amount</th><th>Status</th><th>Reference</th><th>Date</th></tr></thead>
            <tbody>
            <?php if (empty($payments)) : ?>
                <tr><td colspan="6">No payments yet.</td></tr>
            <?php else : ?>
                <?php foreach ($payments as $p) : ?>
                <tr>
                    <td><?php echo (int) $p->id; ?></td>
                    <td><?php echo esc_html(str_replace('_', ' ', $p->payment_type)); ?></td>
                    <td>$<?php echo number_format((float) $p->amount, 2); ?> <?php echo strtoupper(esc_html($p->currency)); ?></td>
                    <td><?php echo esc_html($p->status); ?></td>
                    <td><code style="font-size:11px;"><?php echo esc_html($p->stripe_payment_intent_id ?: $p->stripe_checkout_session_id ?: '-'); ?></code></td>
                    <td><?php echo bod_format_datetime($p->created_at, 'M j, Y g:ia'); ?></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
        <!-- Admin Notes -->
        <h2 style="margin-top:24px;">Admin Notes</h2>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <?php wp_nonce_field('bod_save_admin_notes_' . $owner->id); ?>
            <input type="hidden" name="action" value="bod_save_admin_notes">
            <input type="hidden" name="owner_id" value="<?php echo esc_attr((string) $owner->id); ?>">
            <textarea name="admin_notes" rows="4" style="width:100%;max-width:600px;"><?php echo esc_textarea($owner->admin_notes ?? ''); ?></textarea><br>
            <button type="submit" class="button button-secondary" style="margin-top:8px;">Save Notes</button>
        </form>
    </div>

    <?php
}
// ============================================
// ADMIN POST: Save Notes
// ============================================
add_action('admin_post_bod_save_admin_notes', 'bod_handle_save_admin_notes');
function bod_handle_save_admin_notes() {
    if (!current_user_can('manage_options')) wp_die('Unauthorized');
    $owner_id = absint($_POST['owner_id'] ?? 0);
    check_admin_referer('bod_save_admin_notes_' . $owner_id);
    bod_update_owner($owner_id, ['admin_notes' => sanitize_textarea_field($_POST['admin_notes'] ?? '')]);
    wp_safe_redirect(admin_url('admin.php?page=business-owners&action=view&id=' . $owner_id . '&saved=1'));
    exit;
}
