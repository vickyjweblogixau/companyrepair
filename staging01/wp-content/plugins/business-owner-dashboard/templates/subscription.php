<?php
/**
 * Business Owner — Billing & Subscription view
 */
defined('ABSPATH') || exit;

$owner    = bod_get_current_owner();
$payments = $owner ? bod_get_owner_payments($owner->id) : [];
$payments = array_filter($payments, fn($p) => $p->status === 'succeeded');

$sub_status       = $owner->sub_status       ?? 'active';
$sub_plan         = $owner->sub_plan         ?? 'monthly';
$sub_amount       = (float) ($owner->sub_amount      ?? 0);
$sub_renewal_date = $owner->sub_renewal_date ?? '';
$sub_start_date   = $owner->sub_start_date   ?? '';
$days_left        = ($sub_renewal_date && class_exists('CRS_Subscriptions'))
                    ? CRS_Subscriptions::days_until_renewal($owner)
                    : null;
if (!empty($_GET['cancel_boost'])) {
    $boost_key = sanitize_title($_GET['cancel_boost']);
    if (wp_verify_nonce($_GET['_wpnonce'] ?? '', 'cancel_boost_' . $business_id . '_' . $boost_key)) {
        bod_cancel_boost_renewal($business_id, $boost_key);
        wp_redirect('?view=subscription&boost_cancelled=1');
        exit;
    }
}                    
?>

<div class="bod-subscription-page">
    <!-- Payment Method -->
    <div class="card billing-payment-card" style="border-radius:12px;margin-bottom:24px;">
        <div class="card-body" style="padding:24px;">
            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
                <div>
                    <h5 style="font-weight:700;margin:0 0 6px;">
                        <i class="bi bi-credit-card" style="color:#1565d8;"></i> Payment Method
                    </h5>
                    <p style="color:#888;margin:0;font-size:13px;">
                        Your saved card is charged automatically for renewals and add-ons.
                    </p>
                </div>
                <a href="<?php echo esc_url(bod_create_billing_portal_session($owner->id)); ?>"
                class="btn btn-outline-primary" target="_blank" rel="noopener">
                    <i class="bi bi-credit-card"></i> Update Payment Method
                </a>
            </div>
        </div>
    </div>

    <div style="margin-bottom:24px;">
        <h4 style="margin:0;font-weight:700;">Billing &amp; Subscription</h4>
    </div>

    <?php
    // ── Renewal reminder banner ───────────────────────────────────────────
    if ($sub_status === 'active' && $days_left !== null) :
        if ($days_left <= 1) :
    ?>
    <div style="background:#fef2f2;border-left:4px solid #dc2626;border-radius:8px;padding:14px 18px;margin-bottom:20px;display:flex;align-items:center;gap:10px;">
        <i class="ti ti-alert-circle" style="color:#dc2626;font-size:20px;"></i>
        <span style="font-size:14px;color:#991b1b;font-weight:500;">
            <?php echo $days_left <= 0 ? 'Your renewal is due today.' : 'Your renewal is due tomorrow.'; ?>
            We will auto-charge your saved card.
        </span>
    </div>
    <?php elseif ($days_left <= 7) : ?>
    <div style="background:#fffbeb;border-left:4px solid #d97706;border-radius:8px;padding:14px 18px;margin-bottom:20px;display:flex;align-items:center;gap:10px;">
        <i class="ti ti-clock" style="color:#d97706;font-size:20px;"></i>
        <span style="font-size:14px;color:#92400e;font-weight:500;">
            Renewal in <?php echo (int) $days_left; ?> days
            (<?php echo $sub_renewal_date ? date('M j, Y', strtotime($sub_renewal_date)) : ''; ?>).
        </span>
    </div>
    <?php endif; endif; ?>

    <?php if ($sub_status === 'past_due') : ?>
    <div style="background:#fef2f2;border-left:4px solid #dc2626;border-radius:8px;padding:14px 18px;margin-bottom:20px;display:flex;align-items:center;gap:10px;">
        <i class="ti ti-alert-triangle" style="color:#dc2626;font-size:20px;"></i>
        <span style="font-size:14px;color:#991b1b;font-weight:500;">
            Payment failed. We will retry within 3 days. Please ensure your card is up to date.
        </span>
    </div>
    <?php endif; ?>

    <?php if ($sub_status === 'suspended') : ?>
    <div style="background:#fef2f2;border-left:4px solid #dc2626;border-radius:8px;padding:14px 18px;margin-bottom:20px;">
        <strong style="color:#991b1b;">Subscription Suspended.</strong>
        <span style="color:#991b1b;font-size:14px;"> Your listing is inactive. Please contact us to reactivate.</span>
    </div>
    <?php endif; ?>

    <!-- Subscription Summary -->
    <div class="card" style="border-radius:12px;margin-bottom:24px;">
        <div class="card-body" style="padding:24px;">
            <h5 style="font-weight:700;margin:0 0 20px;">Subscription Details</h5>
            <div class="row g-3">
                <div class="col-md-3 col-6">
                    <div style="font-size:12px;color:#888;margin-bottom:4px;">Status</div>
                    <div>
                        <?php echo class_exists('CRS_Subscriptions') ? CRS_Subscriptions::status_badge($sub_status) : esc_html(ucfirst($sub_status)); ?>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div style="font-size:12px;color:#888;margin-bottom:4px;">Plan</div>
                    <div style="font-weight:600;font-size:14px;"><?php echo esc_html(ucfirst($sub_plan)); ?></div>
                </div>
                <div class="col-md-3 col-6">
                    <div style="font-size:12px;color:#888;margin-bottom:4px;">Amount</div>
                    <div style="font-weight:700;font-size:18px;color:#0a2647;">
                        $<?php echo number_format($sub_amount, 2); ?> <span style="font-size:12px;font-weight:400;color:#888;">AUD incl. GST</span>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div style="font-size:12px;color:#888;margin-bottom:4px;">Next Renewal</div>
                    <div style="font-weight:600;font-size:14px;">
                        <?php echo $sub_renewal_date ? esc_html(date('M j, Y', strtotime($sub_renewal_date))) : '—'; ?>
                        <?php if ($days_left !== null && $sub_status === 'active') : ?>
                            <span style="font-size:11px;color:#888;font-weight:400;"> (<?php echo (int) $days_left; ?> days)</span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if ($sub_start_date) : ?>
                <div class="col-md-3 col-6">
                    <div style="font-size:12px;color:#888;margin-bottom:4px;">Member Since</div>
                    <div style="font-weight:600;font-size:14px;"><?php echo esc_html(date('M j, Y', strtotime($sub_start_date))); ?></div>
                </div>
                <?php endif; ?>
                <div class="col-md-3 col-6">
                    <div style="font-size:12px;color:#888;margin-bottom:4px;">Listing Credits</div>
                    <div style="font-weight:700;font-size:18px;color:#0a2647;"><?php echo (int) ($owner->available_listing_credits ?? 0); ?></div>
                </div>
            </div>

            <?php if (in_array($sub_status, ['active', 'past_due'])) : ?>
            <div style="margin-top:20px;padding-top:20px;border-top:1px solid #f0f0f0;">
                <button id="bod-cancel-sub-btn" class="btn btn-sm"
                        style="border:1px solid #dc2626;color:#dc2626;border-radius:6px;padding:6px 16px;font-size:13px;background:transparent;">
                    <i class="ti ti-x me-1"></i> Cancel Subscription
                </button>
                <span style="font-size:12px;color:#888;margin-left:10px;">Your listing stays active until <?php echo $sub_renewal_date ? esc_html(date('M j, Y', strtotime($sub_renewal_date))) : 'end of period'; ?>.</span>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Buy More Listing Credits -->
    <div class="card" style="border-radius:12px;margin-bottom:24px;">
        <div class="card-body" style="padding:24px;">
            <h5 style="font-weight:700;margin:0 0 16px;">Buy Additional Listing Credit</h5>
            <div style="display:flex;align-items:center;gap:20px;flex-wrap:wrap;">
                <div>
                    <div style="font-size:32px;font-weight:700;color:#0a2647;">$<?php echo number_format(defined('BOD_LISTING_AMOUNT_DISPLAY') ? BOD_LISTING_AMOUNT_DISPLAY : 0, 2); ?></div>
                    <div style="font-size:13px;color:#888;">AUD incl. GST — one listing credit</div>
                </div>
                <button id="bod-buy-listing-btn" class="btn btn-primary btn-lg">
                    <i class="ti ti-shopping-cart me-2"></i>Buy Listing Credit
                </button>
            </div>
        </div>
    </div>

    <!-- Boost Options -->
    <div class="card" style="border-radius:12px;margin-bottom:24px;">
        <div class="card-body" style="padding:24px;">
            <?php
            $business_id  = bod_get_owner_business_id($owner->id);
            $addon_plans  = bod_get_active_addon_plans();      // dynamic — from crs_sub_plan CPT
            $active_boosts = bod_get_active_boosts($business_id); // multiple allowed
            ?>

            <h4>Listing Boosts</h4>
            <p>One-time boost options to increase your listing visibility.</p>

            <div class="row g-3">
            <?php if (empty($addon_plans)) : ?>
                <p class="text-muted">No add-ons currently available.</p>
            <?php endif; ?>

            <?php foreach ($addon_plans as $plan) :
                $plan_key   = sanitize_title($plan->post_title); // 'featured', 'exclusive', 'homepage' etc — derived from plan title
                $charge     = get_post_meta($plan->ID, '_plan_charge_amount', true);
                $duration   = get_post_meta($plan->ID, '_plan_duration', true) ?: 30;
                $features   = get_post_meta($plan->ID, '_plan_features', true);
                $is_active  = isset($active_boosts[$plan_key]);
            ?>
                <div class="col-md-4">
                    <div class="boost-card <?php echo $is_active ? 'boost-active' : ''; ?>">
                        <h5><?php echo esc_html($plan->post_title); ?></h5>
                        <div class="boost-price">$<?php echo number_format((float) $charge, 2); ?></div>
                        <?php if ($features) : ?><p><?php echo esc_html(wp_trim_words($features, 8)); ?></p><?php endif; ?>

                        <?php if ($is_active) :
                            $boost_data = $active_boosts[$plan_key];
                        ?>
                            <div class="boost-active-badge">
                                Active until <?php echo esc_html(date('M j, Y', strtotime($boost_data['expires']))); ?>
                            </div>
                            <?php if ($boost_data['auto_renew']) : ?>
                                <a href="?view=subscription&cancel_boost=<?php echo esc_attr($plan_key); ?>&_wpnonce=<?php echo wp_create_nonce('cancel_boost_' . $business_id . '_' . $plan_key); ?>"
                                class="btn btn-outline-danger btn-sm w-100">
                                    Cancel Renewal
                                </a>
                                <small class="text-muted d-block mt-1">Active until expiry, then won't auto-renew.</small>
                            <?php else : ?>
                                <span class="text-muted small">Cancelled — won't auto-renew after expiry.</span>
                            <?php endif; ?>
                        <?php else : ?>
                           <a href="<?php echo esc_url( add_query_arg( '_wpnonce', wp_create_nonce('wp_rest'), home_url('/wp-json/business-owners-addons/v1/checkout?plan_id=' . $plan->ID) ) ); ?>"
                            class="btn btn-primary w-100">
                                Buy <?php echo esc_html($plan->post_title); ?> Boost
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Payment History -->
    <div class="card" style="border-radius:12px;">
        <div class="card-body" style="padding:24px;">
            <h5 style="font-weight:700;margin:0 0 20px;">Payment History</h5>
            <?php if (empty($payments)) : ?>
                <p style="color:#888;text-align:center;padding:20px;">No payments yet.</p>
            <?php else : ?>
                <div style="overflow-x:auto;">
                    <table style="width:100%;border-collapse:collapse;min-width:500px;">
                        <thead>
                            <tr style="border-bottom:2px solid #f0f0f0;">
                                <th style="padding:10px 8px;text-align:left;font-weight:600;font-size:13px;color:#666;">Invoice #</th>
                                <th style="padding:10px 8px;text-align:left;font-weight:600;font-size:13px;color:#666;">Date</th>
                                <th style="padding:10px 8px;text-align:left;font-weight:600;font-size:13px;color:#666;">Type</th>
                                <th style="padding:10px 8px;text-align:left;font-weight:600;font-size:13px;color:#666;">Amount</th>
                                <th style="padding:10px 8px;text-align:left;font-weight:600;font-size:13px;color:#666;">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($payments as $payment) :
                            $inv_num = !empty($payment->invoice_number)
                                ? $payment->invoice_number
                                : 'BOD-' . str_pad($payment->id, 5, '0', STR_PAD_LEFT);
                            $type_map = [
                                'signup'          => 'Subscription Signup',
                                'renewal'         => 'Monthly Renewal',
                                'listing'         => 'Listing Credit',
                                'boost_featured'  => 'Featured Boost',
                                'boost_exclusive' => 'Exclusive Boost',
                                'boost_homepage'  => 'Homepage Boost',
                            ];
                            $type_label = $type_map[$payment->payment_type] ?? ucwords(str_replace('_', ' ', $payment->payment_type));
                        ?>
                            <tr style="border-bottom:1px solid #f5f5f5;">
                                <td style="padding:12px 8px;font-weight:600;font-size:13px;color:#0a2647;"><?php echo esc_html($inv_num); ?></td>
                                <td style="padding:12px 8px;font-size:13px;"><?php echo bod_format_datetime($payment->created_at, 'M j, Y'); ?></td>
                                <td style="padding:12px 8px;font-size:13px;"><?php echo esc_html($type_label); ?></td>
                                <td style="padding:12px 8px;font-weight:600;">
                                    $<?php echo number_format((float) $payment->amount, 2); ?>
                                    <span style="font-weight:400;color:#888;"><?php echo strtoupper(esc_html($payment->currency)); ?></span>
                                </td>
                                <td style="padding:12px 8px;">
                                    <span style="display:inline-block;padding:2px 10px;border-radius:20px;font-size:11px;font-weight:600;color:#16a34a;background:#16a34a1a;">Paid</span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div style="margin-top:16px;text-align:right;font-size:14px;color:#666;">
                    Total spent: <strong style="color:#0a2647;">
                        $<?php echo number_format(array_sum(array_map(fn($p) => (float)$p->amount, $payments)), 2); ?> AUD
                    </strong>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    var ajaxUrl = '<?php echo esc_js(admin_url('admin-ajax.php')); ?>';

    // Cancel subscription
    $('#bod-cancel-sub-btn').on('click', function() {
        if (!confirm('Are you sure you want to cancel? Your listing stays active until the end of this billing period.')) return;
        $(this).prop('disabled', true).text('Cancelling...');
        $.post(ajaxUrl, {
            action: 'bod_cancel_subscription',
            nonce: '<?php echo wp_create_nonce('bod_cancel_sub'); ?>'
        }, function(res) {
            if (res.success) {
                location.reload();
            } else {
                alert(res.data.message || 'Error. Please try again.');
                $('#bod-cancel-sub-btn').prop('disabled', false).html('<i class="ti ti-x me-1"></i> Cancel Subscription');
            }
        });
    });

    // Buy listing credit
    $('#bod-buy-listing-btn').on('click', function() {
        $(this).prop('disabled', true).text('Processing...');
        $.post(ajaxUrl, {
            action: 'bod_buy_listing_credit',
            nonce: '<?php echo wp_create_nonce('bod_buy_listing'); ?>'
        }, function(res) {
            if (res.success) {
                window.location.href = res.data.redirect_url;
            } else {
                alert(res.data.message || 'Error. Please try again.');
                $('#bod-buy-listing-btn').prop('disabled', false).html('<i class="ti ti-shopping-cart me-2"></i>Buy Listing Credit');
            }
        });
    });

    // Buy boost
    $('.bod-buy-boost-btn').on('click', function() {
        var boostType = $(this).data('boost');
        $(this).prop('disabled', true).text('Processing...');
        $.post(ajaxUrl, {
            action: 'bod_buy_boost',
            boost_type: boostType,
            nonce: '<?php echo wp_create_nonce('bod_buy_boost'); ?>'
        }, function(res) {
            if (res.success) {
                window.location.href = res.data.redirect_url;
            } else {
                alert(res.data.message || 'Error. Please try again.');
                $('.bod-buy-boost-btn').prop('disabled', false);
            }
        });
    });
});
</script>