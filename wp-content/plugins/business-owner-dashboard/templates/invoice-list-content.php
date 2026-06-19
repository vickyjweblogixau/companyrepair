<?php
/**
 * Business Owner — Invoices View
 */
defined('ABSPATH') || exit;

$owner    = bod_get_current_owner();
$payments = $owner ? bod_get_owner_payments($owner->id) : [];
// Only show payments that succeeded
$payments = array_filter($payments, fn($p) => $p->status === 'succeeded');
?>

<div class="bod-invoices-page">
    <div style="margin-bottom:24px;">
        <h4 style="margin:0;font-weight:700;">Invoices</h4>
        <p style="margin:4px 0 0;color:#888;font-size:13px;"><?php echo count($payments); ?> invoice(s)</p>
    </div>

    <?php if (empty($payments)) : ?>
        <div class="card" style="border-radius:12px;">
            <div class="card-body" style="text-align:center;padding:60px 20px;">
                <i class="ti ti-receipt-2" style="font-size:48px;color:#ddd;display:block;margin-bottom:16px;"></i>
                <h5 style="color:#666;">No invoices yet</h5>
                <p style="color:#888;">Your payment receipts will appear here after successful transactions.</p>
            </div>
        </div>
    <?php else : ?>
        <div class="card" style="border-radius:12px;">
            <div class="card-body" style="padding:24px;">
                <div style="overflow-x:auto;">
                    <table style="width:100%;border-collapse:collapse;min-width:600px;">
                        <thead>
                            <tr style="border-bottom:2px solid #f0f0f0;">
                                <th style="padding:10px 12px;text-align:left;font-weight:600;font-size:13px;color:#666;">Invoice #</th>
                                <th style="padding:10px 12px;text-align:left;font-weight:600;font-size:13px;color:#666;">Date</th>
                                <th style="padding:10px 12px;text-align:left;font-weight:600;font-size:13px;color:#666;">Description</th>
                                <th style="padding:10px 12px;text-align:left;font-weight:600;font-size:13px;color:#666;">Amount</th>
                                <th style="padding:10px 12px;text-align:left;font-weight:600;font-size:13px;color:#666;">Status</th>
                                <th style="padding:10px 12px;text-align:left;font-weight:600;font-size:13px;color:#666;">Download</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($payments as $i => $payment) :
                            $invoice_num = 'BOD-' . str_pad($payment->id, 5, '0', STR_PAD_LEFT);
                            $desc_map    = [
                                'signup'    => 'Listing Package — Signup',
                                'listing'   => 'Additional Listing Credit',
                                'boost_featured'  => 'Featured Listing Boost',
                                'boost_exclusive' => 'Exclusive Listing Boost',
                                'boost_homepage'  => 'Homepage Listing Boost',
                            ];
                            $description = $desc_map[$payment->payment_type] ?? ucwords(str_replace('_', ' ', $payment->payment_type));
                        ?>
                            <tr style="border-bottom:1px solid #f5f5f5;">
                                <td style="padding:14px 12px;font-weight:600;font-size:13px;color:#f97316;"><?php echo esc_html($invoice_num); ?></td>
                                <td style="padding:14px 12px;font-size:13px;"><?php echo bod_format_datetime($payment->created_at, 'M j, Y'); ?></td>
                                <td style="padding:14px 12px;font-size:13px;"><?php echo esc_html($description); ?></td>
                                <td style="padding:14px 12px;font-size:13px;font-weight:600;">
                                    $<?php echo number_format((float) $payment->amount, 2); ?>
                                    <span style="font-weight:400;color:#888;"><?php echo strtoupper(esc_html($payment->currency)); ?></span>
                                </td>
                                <td style="padding:14px 12px;">
                                    <span style="display:inline-block;padding:2px 10px;border-radius:20px;font-size:11px;font-weight:600;color:#16a34a;background:#16a34a1a;">
                                        Paid
                                    </span>
                                </td>
                                <td style="padding:14px 12px;">
                                    <a href="<?php echo esc_url(add_query_arg([
                                        'bod_action'  => 'download_invoice',
                                        'payment_id'  => $payment->id,
                                        'nonce'       => wp_create_nonce('bod_invoice_' . $payment->id),
                                    ])); ?>"
                                       class="btn btn-sm"
                                       style="border:1px solid #e0e0e0;border-radius:6px;padding:4px 12px;font-size:12px;color:#555;text-decoration:none;display:inline-flex;align-items:center;gap:4px;">
                                        <i class="ti ti-download"></i> PDF
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Summary Card -->
        <div class="card" style="border-radius:12px;margin-top:16px;">
            <div class="card-body" style="padding:20px 24px;">
                <div style="display:flex;justify-content:space-between;align-items:center;">
                    <div style="font-size:14px;color:#666;">
                        Total Spent
                    </div>
                    <div style="font-size:22px;font-weight:700;color:#f97316;">
                        $<?php
                        $total = array_sum(array_map(fn($p) => (float) $p->amount, $payments));
                        echo number_format($total, 2);
                        ?> AUD
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php
// Handle PDF download
if (!empty($_GET['bod_action']) && $_GET['bod_action'] === 'download_invoice') {
    $pid   = (int) ($_GET['payment_id'] ?? 0);
    $nonce = $_GET['nonce'] ?? '';
    if ($pid && wp_verify_nonce($nonce, 'bod_invoice_' . $pid)) {
        global $wpdb;
        $payment = $wpdb->get_row($wpdb->prepare("SELECT * FROM " . BOD_TABLE_PAYMENTS . " WHERE id = %d AND owner_id = %d", $pid, $owner->id));
        if ($payment) {
            // Try to generate a PDF (reuse email-functions if available)
            if (function_exists('bod_generate_invoice_pdf')) {
                $pdf = bod_generate_invoice_pdf($owner, $payment);
                if ($pdf && file_exists($pdf)) {
                    header('Content-Type: application/pdf');
                    header('Content-Disposition: attachment; filename="invoice-BOD-' . str_pad($pid, 5, '0', STR_PAD_LEFT) . '.pdf"');
                    readfile($pdf);
                    @unlink($pdf);
                    exit;
                }
            }
            // Fallback: basic HTML invoice
            $invoice_num = 'BOD-' . str_pad($pid, 5, '0', STR_PAD_LEFT);
            header('Content-Type: text/html; charset=utf-8');
            echo bod_render_invoice_html($owner, $payment, $invoice_num);
            exit;
        }
    }
}

if (!function_exists('bod_render_invoice_html')) {
    function bod_render_invoice_html($owner, $payment, $invoice_num) {
        ob_start(); ?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Invoice <?php echo esc_html($invoice_num); ?></title>
<style>
body { font-family: Arial, sans-serif; color: #333; margin: 0; padding: 0; }
.invoice-wrap { max-width: 700px; margin: 40px auto; padding: 40px; border: 1px solid #eee; }
.header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 40px; }
.brand { font-size: 24px; font-weight: 700; color: #f97316; }
.invoice-meta { text-align: right; font-size: 13px; color: #666; }
.invoice-meta h2 { color: #333; font-size: 22px; margin: 0 0 8px; }
.parties { display: flex; gap: 40px; margin-bottom: 40px; }
.party h4 { font-size: 12px; color: #888; text-transform: uppercase; letter-spacing: 1px; margin: 0 0 8px; }
table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
th { background: #f97316; color: #fff; padding: 10px 12px; text-align: left; font-size: 13px; }
td { padding: 12px; border-bottom: 1px solid #f0f0f0; font-size: 13px; }
.total-row td { font-weight: 700; font-size: 16px; background: #fff7f0; }
.footer { text-align: center; font-size: 12px; color: #999; margin-top: 40px; padding-top: 20px; border-top: 1px solid #eee; }
</style>
</head>
<body>
<div class="invoice-wrap">
    <div class="header">
        <div class="brand"><?php echo esc_html(get_bloginfo('name')); ?></div>
        <div class="invoice-meta">
            <h2>INVOICE</h2>
            <div><?php echo esc_html($invoice_num); ?></div>
            <div>Date: <?php echo bod_format_datetime($payment->created_at, 'M j, Y'); ?></div>
        </div>
    </div>

    <div class="parties">
        <div class="party">
            <h4>Billed To</h4>
            <div style="font-weight:600;"><?php echo esc_html($owner->owner_name); ?></div>
            <?php if ($owner->business_name) : ?><div><?php echo esc_html($owner->business_name); ?></div><?php endif; ?>
            <div><?php echo esc_html($owner->owner_email); ?></div>
        </div>
    </div>

    <table>
        <thead>
            <tr><th>Description</th><th>Amount</th></tr>
        </thead>
        <tbody>
            <tr>
                <td><?php echo esc_html(ucwords(str_replace('_', ' ', $payment->payment_type))); ?></td>
                <td>$<?php echo number_format((float) $payment->amount, 2); ?> <?php echo strtoupper(esc_html($payment->currency)); ?></td>
            </tr>
            <tr class="total-row">
                <td>Total</td>
                <td>$<?php echo number_format((float) $payment->amount, 2); ?> <?php echo strtoupper(esc_html($payment->currency)); ?></td>
            </tr>
        </tbody>
    </table>

    <div style="font-size:13px;color:#666;">
        <strong>Payment Reference:</strong> <?php echo esc_html($payment->stripe_payment_intent_id ?: $payment->stripe_checkout_session_id ?: '-'); ?>
    </div>

    <div class="footer">
        Thank you for your business! &mdash; <?php echo esc_html(get_bloginfo('name')); ?>
    </div>
</div>
</body>
</html>
        <?php return ob_get_clean();
    }
}
?>
