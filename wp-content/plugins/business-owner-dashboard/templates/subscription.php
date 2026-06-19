<?php
/**
 * Business Owner — Billing & Payments view
 */
defined('ABSPATH') || exit;

$owner    = bod_get_current_owner();
$payments = $owner ? bod_get_owner_payments($owner->id) : [];
$listings = $owner ? bod_get_listings_by_owner($owner->id) : [];
?>

<div class="bod-subscription-page">
    <div style="margin-bottom:24px;">
        <h4 style="margin:0;font-weight:700;">Billing &amp; Payments</h4>
    </div>

    <!-- Credits Summary -->
    <div class="row g-3 mb-4">
        <div class="col-md-6 col-xl-3">
            <div class="card" style="border-radius:12px;border-top:3px solid #f97316;">
                <div class="card-body" style="padding:20px;text-align:center;">
                    <div style="font-size:36px;font-weight:700;color:#f97316;"><?php echo (int) ($owner->available_listing_credits ?? 0); ?></div>
                    <div style="font-size:13px;color:#888;">Available Listing Credits</div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="card" style="border-radius:12px;">
                <div class="card-body" style="padding:20px;text-align:center;">
                    <div style="font-size:36px;font-weight:700;"><?php echo (int) ($owner->total_listings_purchased ?? 0); ?></div>
                    <div style="font-size:13px;color:#888;">Total Purchased</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Buy More -->
    <div class="card" style="border-radius:12px;margin-bottom:24px;">
        <div class="card-body" style="padding:24px;">
            <h5 style="font-weight:700;margin:0 0 16px;">Buy Additional Listing</h5>
            <div style="display:flex;align-items:center;gap:20px;flex-wrap:wrap;">
                <div>
                    <div style="font-size:32px;font-weight:700;color:#f97316;">$<?php echo number_format(BOD_LISTING_AMOUNT_DISPLAY, 2); ?></div>
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
            <h5 style="font-weight:700;margin:0 0 8px;">Listing Boosts</h5>
            <p style="color:#888;font-size:13px;margin:0 0 16px;">One-time boost options to increase your listing visibility. Applied to your most recent active listing.</p>
            <div class="row g-3">
                <?php
                $boosts = [
                    ['Featured',  BOD_BOOST_FEATURED_DISPLAY,  'featured',  '#f97316', 'Appear in featured listings section'],
                    ['Exclusive', BOD_BOOST_EXCLUSIVE_DISPLAY, 'exclusive', '#7c3aed', 'Exclusive spotlight for your listing'],
                    ['Homepage',  BOD_BOOST_HOMEPAGE_DISPLAY,  'homepage',  '#2563eb', 'Featured on the homepage banner'],
                ];
                foreach ($boosts as [$label, $price, $type, $color, $desc]) :
                ?>
                <div class="col-md-4">
                    <div style="border:2px solid <?php echo $color; ?>22;border-radius:12px;padding:20px;text-align:center;">
                        <div style="font-size:22px;font-weight:700;color:<?php echo $color; ?>"><?php echo esc_html($label); ?></div>
                        <div style="font-size:28px;font-weight:700;margin:8px 0;">$<?php echo number_format($price, 2); ?></div>
                        <div style="font-size:12px;color:#888;margin-bottom:16px;"><?php echo esc_html($desc); ?></div>
                        <button class="btn btn-sm bod-buy-boost-btn"
                                data-boost="<?php echo $type; ?>"
                                style="border-color:<?php echo $color; ?>;color:<?php echo $color; ?>;width:100%;">
                            Buy <?php echo esc_html($label); ?> Boost
                        </button>
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
                                <th style="padding:10px 8px;text-align:left;font-weight:600;font-size:13px;color:#666;">Date</th>
                                <th style="padding:10px 8px;text-align:left;font-weight:600;font-size:13px;color:#666;">Type</th>
                                <th style="padding:10px 8px;text-align:left;font-weight:600;font-size:13px;color:#666;">Amount</th>
                                <th style="padding:10px 8px;text-align:left;font-weight:600;font-size:13px;color:#666;">Status</th>
                                <th style="padding:10px 8px;text-align:left;font-weight:600;font-size:13px;color:#666;">Reference</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($payments as $payment) :
                            $status_colors = ['succeeded' => '#16a34a', 'pending' => '#d97706', 'failed' => '#dc2626', 'refunded' => '#6b7280'];
                            $sc = $status_colors[$payment->status] ?? '#6b7280';
                        ?>
                            <tr style="border-bottom:1px solid #f5f5f5;">
                                <td style="padding:12px 8px;font-size:13px;"><?php echo bod_format_datetime($payment->created_at, 'M j, Y'); ?></td>
                                <td style="padding:12px 8px;font-size:13px;"><?php echo esc_html(ucwords(str_replace('_', ' ', $payment->payment_type))); ?></td>
                                <td style="padding:12px 8px;font-weight:600;">$<?php echo number_format((float) $payment->amount, 2); ?> <?php echo strtoupper(esc_html($payment->currency)); ?></td>
                                <td style="padding:12px 8px;">
                                    <span style="display:inline-block;padding:2px 10px;border-radius:20px;font-size:11px;font-weight:600;color:<?php echo $sc; ?>;background:<?php echo $sc; ?>1a;">
                                        <?php echo esc_html(ucfirst($payment->status)); ?>
                                    </span>
                                </td>
                                <td style="padding:12px 8px;font-size:11px;color:#888;word-break:break-all;max-width:140px;">
                                    <?php echo esc_html(substr($payment->stripe_payment_intent_id ?? $payment->stripe_checkout_session_id ?? '-', 0, 30)); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    var ajaxUrl = '<?php echo esc_js(admin_url('admin-ajax.php')); ?>';

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
