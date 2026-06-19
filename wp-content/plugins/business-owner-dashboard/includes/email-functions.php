<?php
/**
 * Email Functions for Business Owner Dashboard
 * Handles invoice + credentials auto-send on signup
 */
if (!defined('ABSPATH')) exit;

/**
 * Get standard email wrapper HTML
 */
function bod_get_email_template($content, $title = '', $icon_url = '') {
    $site_name = get_bloginfo('name');
    $site_url  = home_url('/');

    return '<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>' . esc_html($title) . '</title>
</head>
<body style="margin:0;padding:0;background:#f4f4f4;font-family:Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f4;padding:30px 0;">
  <tr>
    <td align="center">
      <table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.08);">
        <!-- Header -->
        <tr>
          <td style="background:#f97316;padding:28px 32px;text-align:center;">
            ' . ($icon_url ? '<img src="' . esc_url($icon_url) . '" alt="icon" style="height:48px;margin-bottom:10px;"><br>' : '') . '
            <h1 style="margin:0;color:#ffffff;font-size:22px;font-weight:700;">' . esc_html($title ?: $site_name) . '</h1>
          </td>
        </tr>
        <!-- Body -->
        <tr>
          <td style="padding:32px;color:#333333;font-size:15px;line-height:1.6;">
            ' . $content . '
          </td>
        </tr>
        <!-- Footer -->
        <tr>
          <td style="background:#f8f8f8;padding:20px 32px;text-align:center;color:#888888;font-size:13px;border-top:1px solid #eeeeee;">
            <p style="margin:0;">© ' . date('Y') . ' <a href="' . esc_url($site_url) . '" style="color:#f97316;">' . esc_html($site_name) . '</a>. All rights reserved.</p>
          </td>
        </tr>
      </table>
    </td>
  </tr>
</table>
</body>
</html>';
}

/**
 * Send email using WordPress wp_mail
 */
function bod_send_email($to, $subject, $message, $headers = [], $attachments = []) {
    $default_headers = [
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>',
    ];
    $headers = array_merge($default_headers, (array) $headers);
    return wp_mail($to, $subject, $message, $headers, $attachments);
}

/**
 * Generate invoice PDF for listing payment
 */
function bod_generate_invoice_pdf($owner_name, $owner_email, $owner_phone, $amount, $reference_id = '', $listing_row_id = 0) {
    // Attempt to use the invoice functions from private-seller-dashboard if present
    $inv_file = WP_PLUGIN_DIR . '/private-seller-dashboard/includes/invoice-functions.php';
    if (file_exists($inv_file)) {
        if (!defined('PSD_PLUGIN_DIR')) {
            define('PSD_PLUGIN_DIR', trailingslashit(WP_PLUGIN_DIR) . 'private-seller-dashboard/');
        }
        if (!defined('PSD_PLUGIN_URL')) {
            $psd_main = WP_PLUGIN_DIR . '/private-seller-dashboard/private-seller-dashboard.php';
            define('PSD_PLUGIN_URL', file_exists($psd_main) ? trailingslashit(plugins_url('', $psd_main)) : '');
        }
        require_once $inv_file;
        if (function_exists('create_ps_invoice_data_for_listing_payment') && function_exists('generate_ps_invoice_pdf')) {
            $data = create_ps_invoice_data_for_listing_payment(
                $owner_name, $owner_email, $owner_phone, $amount,
                'Business Owner Listing Fee', $reference_id, (int) $listing_row_id
            );
            return $data ? generate_ps_invoice_pdf($data) : false;
        }
    }

    // Fallback: try local dompdf vendor if bundled
    $dompdf_autoload = BOD_PLUGIN_DIR . 'vendor/autoload.php';
    if (file_exists($dompdf_autoload)) {
        require_once $dompdf_autoload;
        if (class_exists('Dompdf\Dompdf')) {
            return bod_generate_invoice_pdf_dompdf($owner_name, $owner_email, $owner_phone, $amount, $reference_id, $listing_row_id);
        }
    }

    error_log('[BOD Email] Invoice PDF generation not available');
    return false;
}

/**
 * Inline PDF generation using local dompdf
 */
function bod_generate_invoice_pdf_dompdf($owner_name, $owner_email, $owner_phone, $amount, $reference_id, $listing_row_id) {
    $site_name  = get_bloginfo('name');
    $gst        = round($amount - ($amount / 1.1), 2);
    $subtotal   = round($amount - $gst, 2);
    $invoice_no = 'BOD-' . date('Ymd') . '-' . ($listing_row_id ?: rand(1000, 9999));
    $date       = date('d M Y');

    $html = '<!DOCTYPE html><html><head><meta charset="UTF-8">
    <style>body{font-family:Arial,sans-serif;font-size:13px;color:#333;}
    .header{background:#f97316;color:#fff;padding:20px;text-align:center;}
    table{width:100%;border-collapse:collapse;margin-top:16px;}
    th,td{border:1px solid #ddd;padding:8px;text-align:left;}
    th{background:#f5f5f5;}</style>
    </head><body>
    <div class="header"><h2>' . esc_html($site_name) . ' — Tax Invoice</h2></div>
    <p><strong>Invoice #:</strong> ' . esc_html($invoice_no) . '&nbsp;&nbsp; <strong>Date:</strong> ' . esc_html($date) . '</p>
    <p><strong>Billed To:</strong> ' . esc_html($owner_name) . ' (' . esc_html($owner_email) . ')</p>
    <table><thead><tr><th>Description</th><th>Amount (AUD)</th></tr></thead>
    <tbody>
    <tr><td>Business Owner Listing Fee (excl. GST)</td><td>$' . number_format($subtotal, 2) . '</td></tr>
    <tr><td>GST (10%)</td><td>$' . number_format($gst, 2) . '</td></tr>
    <tr><td><strong>Total (incl. GST)</strong></td><td><strong>$' . number_format($amount, 2) . '</strong></td></tr>
    </tbody></table>
    <p style="margin-top:20px;font-size:12px;color:#888;">Reference: ' . esc_html($reference_id) . '</p>
    </body></html>';

    $dompdf = new \Dompdf\Dompdf();
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    $upload_dir = wp_upload_dir();
    $pdf_dir    = $upload_dir['basedir'] . '/bod-invoices/';
    if (!is_dir($pdf_dir)) wp_mkdir_p($pdf_dir);

    $filename = $pdf_dir . 'invoice-' . $invoice_no . '.pdf';
    file_put_contents($filename, $dompdf->output());

    return file_exists($filename) ? $filename : false;
}

/**
 * Send invoice + credentials email (called right after account creation)
 */
function bod_send_invoice_and_credentials_email($owner_id, $session = null) {
    $owner = bod_get_owner($owner_id);
    if (!$owner || empty($owner->username)) {
        error_log('[BOD Email] Cannot send credentials — no username for owner ' . $owner_id);
        return false;
    }

    $password      = $owner->user_password_plain;
    $login_url     = home_url('/business-owner-login/');
    $support_email = get_option('admin_email');
    $site_name     = get_bloginfo('name');

    // Generate invoice PDF
    $amount   = defined('BOD_LISTING_AMOUNT_DISPLAY') ? BOD_LISTING_AMOUNT_DISPLAY : 35.00;
    if ($session && isset($session->amount_total)) {
        $amount = ($session->amount_total) / 100;
    }
    $ref        = $session ? ($session->payment_intent ?? $session->id ?? '') : '';
    $attachments = [];
    $pdf_path   = bod_generate_invoice_pdf($owner->owner_name, $owner->owner_email, $owner->owner_phone, $amount, $ref, $owner_id);
    if ($pdf_path && file_exists($pdf_path)) {
        $attachments[] = $pdf_path;
    }

    // --- Invoice email (payment confirmation) ---
    $invoice_content = '
        <p>Hello <strong>' . esc_html($owner->owner_name) . '</strong>,</p>
        <p>Thank you for signing up! Your payment of <strong>$' . number_format($amount, 2) . ' AUD</strong> has been received successfully.</p>

        <table style="border-collapse:collapse;width:100%;margin:12px 0;">
            <tr><th colspan="2" style="border:1px solid #ddd;background:#f5f5f5;padding:8px;text-align:left;">Payment Summary</th></tr>
            <tr><td style="border:1px solid #ddd;padding:8px;">Amount Paid</td><td style="border:1px solid #ddd;padding:8px;"><strong>$' . number_format($amount, 2) . ' AUD (incl. GST)</strong></td></tr>
            <tr><td style="border:1px solid #ddd;padding:8px;">Business Name</td><td style="border:1px solid #ddd;padding:8px;">' . esc_html($owner->business_name ?: '-') . '</td></tr>
            <tr><td style="border:1px solid #ddd;padding:8px;">Reference</td><td style="border:1px solid #ddd;padding:8px;">' . esc_html($ref) . '</td></tr>
        </table>

        <p>Your tax invoice is attached to this email as a PDF.</p>
        <p style="color:#555;">If you have any questions, contact us at <a href="mailto:' . esc_attr($support_email) . '">' . esc_html($support_email) . '</a>.</p>
    ';

    // --- Credentials email ---
    $creds_content = '
        <p>Hello <strong>' . esc_html($owner->owner_name) . '</strong>,</p>
        <p>Your Business Owner account has been created and is ready to use. Here are your login credentials:</p>

        <table style="border-collapse:collapse;width:100%;margin:12px 0;">
            <tr><th colspan="2" style="border:1px solid #8eaadb;background:#dce6f7;padding:8px;text-align:left;">Your Login Credentials</th></tr>
            <tr><td style="border:1px solid #8eaadb;padding:8px;width:140px;">Username:</td><td style="border:1px solid #8eaadb;padding:8px;"><strong>' . esc_html($owner->username) . '</strong></td></tr>
            <tr><td style="border:1px solid #8eaadb;padding:8px;">Email:</td><td style="border:1px solid #8eaadb;padding:8px;">' . esc_html($owner->owner_email) . '</td></tr>
            <tr><td style="border:1px solid #8eaadb;padding:8px;">Password:</td><td style="border:1px solid #8eaadb;padding:8px;"><strong>' . esc_html($password) . '</strong></td></tr>
        </table>

        <p style="text-align:center;margin:24px 0;">
            <a href="' . esc_url($login_url) . '" style="display:inline-block;background:#f97316;color:#fff;padding:12px 28px;border-radius:6px;text-decoration:none;font-weight:bold;font-size:16px;">Login to Your Dashboard</a>
        </p>

        <p><strong>Getting Started:</strong></p>
        <ol>
            <li>Click the login button above or visit <a href="' . esc_url($login_url) . '">' . esc_url($login_url) . '</a></li>
            <li>Enter your username and password above</li>
            <li>Create your first listing from the dashboard</li>
            <li>Please change your password after your first login for security.</li>
        </ol>

        <p style="color:#555;">If you have any questions, contact us at <a href="mailto:' . esc_attr($support_email) . '">' . esc_html($support_email) . '</a>.</p>
    ';

    // Send invoice email (with PDF attachment)
    $invoice_html = bod_get_email_template($invoice_content, 'Payment Confirmed — ' . $site_name);
    bod_send_email($owner->owner_email, 'Payment Confirmed & Tax Invoice — ' . $site_name, $invoice_html, [], $attachments);

    // Send credentials email (separate email)
    $creds_html = bod_get_email_template($creds_content, 'Your Account Credentials — ' . $site_name);
    $result     = bod_send_email($owner->owner_email, 'Your Business Owner Account Credentials — ' . $site_name, $creds_html);

    if ($result) {
        bod_update_owner($owner_id, [
            'credentials_email_sent'    => 'yes',
            'credentials_email_sent_at' => current_time('mysql'),
        ]);
    }

    error_log('[BOD Email] Invoice + credentials sent for owner ' . $owner_id . ': ' . ($result ? 'OK' : 'FAILED'));
    return $result;
}

/**
 * Send invoice only (for repeat listing purchases by existing owners)
 */
function bod_send_listing_invoice_email($owner_id, $session = null) {
    $owner  = bod_get_owner($owner_id);
    if (!$owner) return false;

    $amount = $session ? (($session->amount_total ?? 0) / 100) : BOD_LISTING_AMOUNT_DISPLAY;
    $ref    = $session ? ($session->payment_intent ?? $session->id ?? '') : '';

    $attachments = [];
    $pdf_path    = bod_generate_invoice_pdf($owner->owner_name, $owner->owner_email, $owner->owner_phone, $amount, $ref, $owner_id);
    if ($pdf_path && file_exists($pdf_path)) {
        $attachments[] = $pdf_path;
    }

    $content = '
        <p>Hello <strong>' . esc_html($owner->owner_name) . '</strong>,</p>
        <p>Your payment of <strong>$' . number_format($amount, 2) . ' AUD</strong> has been received. A new listing credit has been added to your account.</p>
        <p>Your tax invoice is attached to this email. Log in to your dashboard to start using your new listing credit.</p>
        <p><a href="' . esc_url(home_url('/business-owner-dashboard/')) . '" style="background:#f97316;color:#fff;padding:10px 24px;border-radius:6px;text-decoration:none;font-weight:bold;display:inline-block;">Go to Dashboard</a></p>
    ';

    $html = bod_get_email_template($content, 'Listing Credit Added — ' . get_bloginfo('name'));
    return bod_send_email($owner->owner_email, 'Listing Credit Added — ' . get_bloginfo('name'), $html, [], $attachments);
}

/**
 * Resend credentials email (admin-triggered)
 */
function bod_send_credentials_email($owner_id, $force_new_password = false) {
    $owner = bod_get_owner($owner_id);
    if (!$owner || empty($owner->username) || !$owner->wp_user_id) {
        error_log('[BOD Email] Cannot send credentials — owner not ready: ' . $owner_id);
        return false;
    }

    $password = $owner->user_password_plain;
    if (empty($password) || $force_new_password) {
        $password = wp_generate_password(12, true, false);
        wp_set_password($password, $owner->wp_user_id);
        bod_update_owner($owner_id, ['user_password_plain' => $password]);
    }

    $login_url     = home_url('/business-owner-login/');
    $support_email = get_option('admin_email');
    $site_name     = get_bloginfo('name');

    $content = '
        <p>Hello <strong>' . esc_html($owner->owner_name) . '</strong>,</p>
        <p>Here are your login credentials for the ' . esc_html($site_name) . ' Business Owner Dashboard:</p>

        <table style="border-collapse:collapse;width:100%;margin:12px 0;">
            <tr><th colspan="2" style="border:1px solid #8eaadb;background:#dce6f7;padding:8px;text-align:left;">Your Login Credentials</th></tr>
            <tr><td style="border:1px solid #8eaadb;padding:8px;width:140px;">Username:</td><td style="border:1px solid #8eaadb;padding:8px;"><strong>' . esc_html($owner->username) . '</strong></td></tr>
            <tr><td style="border:1px solid #8eaadb;padding:8px;">Email:</td><td style="border:1px solid #8eaadb;padding:8px;">' . esc_html($owner->owner_email) . '</td></tr>
            <tr><td style="border:1px solid #8eaadb;padding:8px;">Password:</td><td style="border:1px solid #8eaadb;padding:8px;"><strong>' . esc_html($password) . '</strong></td></tr>
        </table>

        <p style="text-align:center;margin:24px 0;">
            <a href="' . esc_url($login_url) . '" style="display:inline-block;background:#f97316;color:#fff;padding:12px 28px;border-radius:6px;text-decoration:none;font-weight:bold;font-size:16px;">Login to Your Dashboard</a>
        </p>

        <p style="color:#555;">If you did not request this email, please contact us at <a href="mailto:' . esc_attr($support_email) . '">' . esc_html($support_email) . '</a>.</p>
    ';

    $html   = bod_get_email_template($content, 'Your Account Credentials — ' . $site_name);
    $result = bod_send_email($owner->owner_email, 'Your Business Owner Account Credentials — ' . $site_name, $html);

    if ($result) {
        bod_update_owner($owner_id, [
            'credentials_email_sent'    => 'yes',
            'credentials_email_sent_at' => current_time('mysql'),
        ]);
    }
    return $result;
}

/**
 * Send "submission received, pending approval" email to owner after successful payment.
 * No credentials — account hasn't been created yet.
 */
function bod_send_submission_received_email($owner_id, $session = null) {
    $owner = bod_get_owner($owner_id);
    if (!$owner) return false;

    $site_name = get_bloginfo('name');
    $amount    = $session ? number_format(($session->amount_total ?? 0) / 100, 2) : '0.00';
    $ref       = $session ? ($session->payment_intent ?? ($session->id ?? '')) : '';

    $subject = 'Submission Received – ' . $site_name;

    $body = '<!DOCTYPE html><html><head><meta charset="utf-8"></head><body style="margin:0;padding:0;background:#f5f5f5;font-family:Arial,sans-serif;">';
    $body .= '<div style="max-width:600px;margin:40px auto;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,0.08);">';
    $body .= '<div style="background:#2563eb;padding:28px 32px;">';
    $body .= '<h1 style="color:#fff;margin:0;font-size:22px;">' . esc_html($site_name) . '</h1>';
    $body .= '</div>';
    $body .= '<div style="padding:32px;">';
    $body .= '<h2 style="margin:0 0 8px;font-size:20px;color:#111;">We\'ve received your submission!</h2>';
    $body .= '<p style="color:#555;margin:0 0 24px;">Hi ' . esc_html($owner->owner_name) . ', thank you for signing up. Your payment has been processed and your submission is now pending review.</p>';

    // Payment summary box
    $body .= '<div style="background:#f0f9ff;border:1px solid #bae6fd;border-radius:10px;padding:20px;margin-bottom:24px;">';
    $body .= '<table style="width:100%;border-collapse:collapse;">';
    $body .= '<tr><td style="padding:6px 0;color:#6b7280;font-size:13px;width:140px;">Business Name</td><td style="font-weight:600;font-size:13px;">' . esc_html($owner->business_name ?: '-') . '</td></tr>';
    $body .= '<tr><td style="padding:6px 0;color:#6b7280;font-size:13px;">Email</td><td style="font-size:13px;">' . esc_html($owner->owner_email) . '</td></tr>';
    $body .= '<tr><td style="padding:6px 0;color:#6b7280;font-size:13px;">Amount Paid</td><td style="font-weight:700;font-size:13px;">$' . $amount . ' AUD</td></tr>';
    if ($ref) $body .= '<tr><td style="padding:6px 0;color:#6b7280;font-size:13px;">Reference</td><td style="font-size:11px;color:#9ca3af;">' . esc_html(substr($ref, 0, 40)) . '</td></tr>';
    $body .= '</table></div>';

    // Steps
    $body .= '<h3 style="font-size:15px;margin:0 0 14px;color:#1e3a5f;">What Happens Next?</h3>';
    $body .= '<div style="display:flex;flex-direction:column;gap:0;">';
    $steps = [
        ['1', 'Admin Review', 'Our team will review and verify your business details. This typically takes 1–2 business days.'],
        ['2', 'Account Created', 'Once approved, your Business Owner account will be created and your login credentials will be sent to this email address.'],
        ['3', 'Your Business Goes Live', 'Log in to your dashboard, complete your profile and start receiving leads!'],
    ];
    foreach ($steps as [$num, $title, $desc]) {
        $body .= '<div style="display:flex;gap:14px;padding:12px 0;border-bottom:1px solid #f3f4f6;">';
        $body .= '<div style="width:28px;height:28px;border-radius:50%;background:#2563eb;color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:13px;flex-shrink:0;">' . $num . '</div>';
        $body .= '<div><strong style="font-size:13px;">' . $title . '</strong><br><span style="font-size:12px;color:#6b7280;">' . $desc . '</span></div>';
        $body .= '</div>';
    }
    $body .= '</div>';

    $body .= '<p style="margin:24px 0 0;padding:16px;background:#fffbeb;border:1px solid #fde68a;border-radius:8px;font-size:13px;color:#78350f;">';
    $body .= '<strong>⏳ Your account is pending admin approval.</strong> You will receive another email with your login credentials once your submission has been approved.';
    $body .= '</p>';

    $body .= '<p style="margin-top:24px;font-size:13px;color:#6b7280;">If you have any questions, please reply to this email or contact us at ' . esc_html(get_option('admin_email')) . '</p>';
    $body .= '</div>';
    $body .= '<div style="background:#f9f9f9;padding:20px 32px;text-align:center;font-size:12px;color:#999;">&copy; ' . date('Y') . ' ' . esc_html($site_name) . ' | ' . esc_html(home_url()) . '</div>';
    $body .= '</div></body></html>';

    $headers = ['Content-Type: text/html; charset=UTF-8', 'From: ' . $site_name . ' <' . get_option('admin_email') . '>'];
    return wp_mail($owner->owner_email, $subject, $body, $headers);
}

/**
 * Send rejection email to business owner
 */
function bod_send_rejection_email($owner_id, $reason = '') {
    $owner         = bod_get_owner($owner_id);
    if (!$owner) return false;
    $support_email = get_option('admin_email');
    $site_name     = get_bloginfo('name');

    $content = '
        <p>Hello <strong>' . esc_html($owner->owner_name) . '</strong>,</p>
        <p>Unfortunately, your business owner application for ' . esc_html($site_name) . ' has not been approved at this time.</p>
        ' . ($reason ? '<p><strong>Reason:</strong> ' . esc_html($reason) . '</p>' : '') . '
        <p>If you believe this is an error or would like to discuss your application, please contact us at <a href="mailto:' . esc_attr($support_email) . '">' . esc_html($support_email) . '</a>.</p>
    ';

    $html = bod_get_email_template($content, 'Application Update — ' . $site_name);
    return bod_send_email($owner->owner_email, 'Business Owner Application Update — ' . $site_name, $html);
}

/**
 * Send admin notification when new owner signs up
 */
function bod_send_new_owner_admin_notification($owner_id) {
    $owner       = bod_get_owner($owner_id);
    if (!$owner) return false;
    $admin_email = get_option('admin_email');
    $site_name   = get_bloginfo('name');

    $content = '
        <p>A new business owner has signed up on <strong>' . esc_html($site_name) . '</strong>.</p>
        <table style="border-collapse:collapse;width:100%;margin:12px 0;">
            <tr><th colspan="2" style="border:1px solid #ddd;background:#f5f5f5;padding:8px;text-align:left;">Owner Details</th></tr>
            <tr><td style="border:1px solid #ddd;padding:8px;">Name</td><td style="border:1px solid #ddd;padding:8px;">' . esc_html($owner->owner_name) . '</td></tr>
            <tr><td style="border:1px solid #ddd;padding:8px;">Email</td><td style="border:1px solid #ddd;padding:8px;">' . esc_html($owner->owner_email) . '</td></tr>
            <tr><td style="border:1px solid #ddd;padding:8px;">Phone</td><td style="border:1px solid #ddd;padding:8px;">' . esc_html($owner->owner_phone) . '</td></tr>
            <tr><td style="border:1px solid #ddd;padding:8px;">Business</td><td style="border:1px solid #ddd;padding:8px;">' . esc_html($owner->business_name ?: '-') . '</td></tr>
            <tr><td style="border:1px solid #ddd;padding:8px;">Location</td><td style="border:1px solid #ddd;padding:8px;">' . esc_html(($owner->suburb ?? '') . ', ' . ($owner->state ?? '')) . '</td></tr>
        </table>
        <p><a href="' . esc_url(admin_url('admin.php?page=business-owners&action=view&id=' . $owner->id)) . '" style="background:#f97316;color:#fff;padding:10px 22px;border-radius:6px;text-decoration:none;font-weight:bold;display:inline-block;">Review Application</a></p>
    ';

    $html = bod_get_email_template($content, 'New Business Owner Application');
    return bod_send_email($admin_email, 'New Business Owner Application — ' . $site_name, $html);
}
