<?php
/**
 * Email Functions for Business Owner Dashboard
 *
 * All emails use get_global_email_template() from the CRS mu-plugin
 * (mu-plugins/crs-company-config.php) so branding is centralised.
 *
 * Invoice PDFs use crs_generate_invoice_html() / crs_create_invoice_data_for_listing_payment()
 * — same structure as the CFS subscriber-dashboard invoice.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

// ============================================
// SEND HELPER
// ============================================

function bod_send_email( $to, $subject, $message, $extra_headers = [], $attachments = [] ) {
    $headers = function_exists( 'crs_get_email_headers' )
        ? crs_get_email_headers()
        : [ 'Content-Type: text/html; charset=UTF-8' ];

    $headers = array_merge( $headers, (array) $extra_headers );
    return wp_mail( $to, $subject, $message, $headers, $attachments );
}

// ============================================
// INVOICE PDF GENERATION
// Uses crs_generate_invoice_html() (CFS-style) from the mu-plugin + dompdf.
// ============================================

function bod_generate_invoice_pdf( $owner_name, $owner_email, $owner_phone, $amount, $reference_id = '', $listing_row_id = 0 ) {

    // Build invoice data via centralised helper
    if ( function_exists( 'crs_create_invoice_data_for_listing_payment' ) ) {
        $invoice_data = crs_create_invoice_data_for_listing_payment(
            $owner_name, $owner_email, $owner_phone, $amount,
            'Business Owner Listing Fee', $reference_id, (int) $listing_row_id
        );
        $html = function_exists( 'crs_generate_invoice_html' )
            ? crs_generate_invoice_html( $invoice_data )
            : false;
    } else {
        $html = false;
    }

    if ( ! $html ) {
        error_log( '[BOD Email] Invoice HTML generation not available.' );
        return false;
    }

    // Render to PDF via dompdf (bundled vendor/)
    $dompdf_autoload = BOD_PLUGIN_DIR . 'vendor/autoload.php';
    if ( ! file_exists( $dompdf_autoload ) ) {
        error_log( '[BOD Email] dompdf not found at ' . $dompdf_autoload );
        return false;
    }

    require_once $dompdf_autoload;
    if ( ! class_exists( 'Dompdf\Dompdf' ) ) {
        error_log( '[BOD Email] Dompdf class not found.' );
        return false;
    }

    $invoice_number = isset( $invoice_data['invoice_number'] ) ? $invoice_data['invoice_number'] : ( 'CRS-BOD-' . date( 'Ymd' ) );

    $dompdf = new \Dompdf\Dompdf();
    $dompdf->loadHtml( $html );
    $dompdf->setPaper( 'A4', 'portrait' );
    $dompdf->render();

    $upload_dir = wp_upload_dir();
    $pdf_dir    = $upload_dir['basedir'] . '/bod-invoices/';
    if ( ! is_dir( $pdf_dir ) ) wp_mkdir_p( $pdf_dir );

    $filename = $pdf_dir . 'invoice-' . sanitize_file_name( $invoice_number ) . '.pdf';
    file_put_contents( $filename, $dompdf->output() );

    return file_exists( $filename ) ? $filename : false;
}

// ============================================
// INVOICE + CREDENTIALS (sent right after account creation by admin)
// ============================================

function bod_send_invoice_and_credentials_email( $owner_id, $session = null ) {
    $owner = bod_get_owner( $owner_id );
    if ( ! $owner || empty( $owner->username ) ) {
        error_log( '[BOD Email] Cannot send credentials — no username for owner ' . $owner_id );
        return false;
    }

    $password      = $owner->user_password_plain;
    $login_url     = function_exists( 'crs_get_login_url' )     ? crs_get_login_url()     : home_url( '/business-owner-login/' );
    $dashboard_url = function_exists( 'crs_get_dashboard_url' ) ? crs_get_dashboard_url() : home_url( '/business-owner-dashboard/' );
    $support_email = function_exists( 'crs_get_support_email' ) ? crs_get_support_email() : get_option( 'admin_email' );
    $site_name     = function_exists( 'crs_get_company_name' )  ? crs_get_company_name()  : get_bloginfo( 'name' );

    // Generate invoice PDF
    $amount = defined( 'BOD_LISTING_AMOUNT_DISPLAY' ) ? BOD_LISTING_AMOUNT_DISPLAY : 35.00;
    if ( $session && isset( $session->amount_total ) ) {
        $amount = (float) $session->amount_total / 100;
    }
    $ref         = $session ? ( $session->payment_intent ?? $session->id ?? '' ) : '';
    $attachments = [];
    $pdf_path    = bod_generate_invoice_pdf( $owner->owner_name, $owner->owner_email, $owner->owner_phone, $amount, $ref, $owner_id );
    if ( $pdf_path && file_exists( $pdf_path ) ) {
        $attachments[] = $pdf_path;
    }

    // --- Email 1: Payment confirmation + invoice PDF ---
    $invoice_content = '
        <p>Hello <strong>' . esc_html( $owner->owner_name ) . '</strong>,</p>
        <p>Thank you for signing up! Your payment of <strong>$' . number_format( $amount, 2 ) . ' AUD</strong> has been received successfully.</p>

        <table class="item-table" style="border-collapse:collapse;width:100%;margin:14px 0;">
            <tr><th colspan="2" style="padding:8px 10px;text-align:left;">Payment Summary</th></tr>
            <tr><td style="padding:8px 10px;">Amount Paid</td><td style="padding:8px 10px;"><strong>$' . number_format( $amount, 2 ) . ' AUD (incl. GST)</strong></td></tr>
            <tr><td style="padding:8px 10px;">Business Name</td><td style="padding:8px 10px;">' . esc_html( $owner->business_name ?: '-' ) . '</td></tr>
            <tr><td style="padding:8px 10px;">Reference</td><td style="padding:8px 10px;">' . esc_html( $ref ) . '</td></tr>
        </table>

        <p>Your tax invoice is attached to this email as a PDF.</p>
        <p style="color:#555;">If you have any questions contact us at <a href="mailto:' . esc_attr( $support_email ) . '">' . esc_html( $support_email ) . '</a>.</p>
    ';

    $invoice_html = get_global_email_template( $invoice_content, 'Payment Confirmed — ' . $site_name );
    bod_send_email( $owner->owner_email, 'Payment Confirmed & Tax Invoice — ' . $site_name, $invoice_html, [], $attachments );

    // --- Email 2: Login credentials ---
    $creds_content = '
        <p>Hello <strong>' . esc_html( $owner->owner_name ) . '</strong>,</p>
        <p>Your Business Owner account has been approved and is ready to use. Here are your login credentials:</p>

        <table class="item-table" style="border-collapse:collapse;width:100%;margin:14px 0;">
            <tr><th colspan="2" style="padding:8px 10px;text-align:left;">Your Login Credentials</th></tr>
            <tr><td style="padding:8px 10px;width:130px;">Username</td><td style="padding:8px 10px;"><strong>' . esc_html( $owner->username ) . '</strong></td></tr>
            <tr><td style="padding:8px 10px;">Email</td><td style="padding:8px 10px;">' . esc_html( $owner->owner_email ) . '</td></tr>
            <tr><td style="padding:8px 10px;">Password</td><td style="padding:8px 10px;"><strong>' . esc_html( $password ) . '</strong></td></tr>
        </table>

        <p style="text-align:center;margin:24px 0;">
            <a href="' . esc_url( $login_url ) . '" class="button">Login to Your Dashboard</a>
        </p>

        <p><strong>Getting Started:</strong></p>
        <ol style="font-size:14px;line-height:1.8;">
            <li>Click the login button above or visit <a href="' . esc_url( $login_url ) . '">' . esc_html( $login_url ) . '</a></li>
            <li>Enter your username and password above</li>
            <li>Create your first listing from the dashboard</li>
            <li>Please change your password after your first login for security.</li>
        </ol>

        <p style="color:#555;">Questions? Contact us at <a href="mailto:' . esc_attr( $support_email ) . '">' . esc_html( $support_email ) . '</a>.</p>
    ';

    $creds_html = get_global_email_template( $creds_content, 'Your Account Credentials — ' . $site_name );
    $result     = bod_send_email( $owner->owner_email, 'Your Business Owner Account Credentials — ' . $site_name, $creds_html );

    if ( $result ) {
        bod_update_owner( $owner_id, [
            'credentials_email_sent'    => 'yes',
            'credentials_email_sent_at' => current_time( 'mysql' ),
        ] );
    }

    error_log( '[BOD Email] Invoice + credentials sent for owner ' . $owner_id . ': ' . ( $result ? 'OK' : 'FAILED' ) );
    return $result;
}

// ============================================
// INVOICE ONLY (repeat listing purchase by existing owner)
// ============================================

function bod_send_listing_invoice_email( $owner_id, $session = null ) {
    $owner = bod_get_owner( $owner_id );
    if ( ! $owner ) return false;

    $amount = $session ? ( (float) ( $session->amount_total ?? 0 ) / 100 ) : BOD_LISTING_AMOUNT_DISPLAY;
    $ref    = $session ? ( $session->payment_intent ?? $session->id ?? '' ) : '';

    $attachments = [];
    $pdf_path    = bod_generate_invoice_pdf( $owner->owner_name, $owner->owner_email, $owner->owner_phone, $amount, $ref, $owner_id );
    if ( $pdf_path && file_exists( $pdf_path ) ) {
        $attachments[] = $pdf_path;
    }

    $dashboard_url = function_exists( 'crs_get_dashboard_url' ) ? crs_get_dashboard_url() : home_url( '/business-owner-dashboard/' );
    $site_name     = function_exists( 'crs_get_company_name' )  ? crs_get_company_name()  : get_bloginfo( 'name' );

    $content = '
        <p>Hello <strong>' . esc_html( $owner->owner_name ) . '</strong>,</p>
        <p>Your payment of <strong>$' . number_format( $amount, 2 ) . ' AUD</strong> has been received. A new listing credit has been added to your account.</p>
        <p>Your tax invoice is attached. Log in to your dashboard to start using your new listing credit.</p>
        <p style="text-align:center;margin:24px 0;">
            <a href="' . esc_url( $dashboard_url ) . '" class="button">Go to Dashboard</a>
        </p>
    ';

    $html = get_global_email_template( $content, 'Listing Credit Added — ' . $site_name );
    return bod_send_email( $owner->owner_email, 'Listing Credit Added — ' . $site_name, $html, [], $attachments );
}

// ============================================
// RESEND CREDENTIALS (admin-triggered)
// ============================================

function bod_send_credentials_email( $owner_id, $force_new_password = false ) {
    $owner = bod_get_owner( $owner_id );
    if ( ! $owner || empty( $owner->username ) || ! $owner->wp_user_id ) {
        error_log( '[BOD Email] Cannot send credentials — owner not ready: ' . $owner_id );
        return false;
    }

    $password = $owner->user_password_plain;
    if ( empty( $password ) || $force_new_password ) {
        $password = wp_generate_password( 12, true, false );
        wp_set_password( $password, $owner->wp_user_id );
        bod_update_owner( $owner_id, [ 'user_password_plain' => $password ] );
    }

    $login_url     = function_exists( 'crs_get_login_url' )     ? crs_get_login_url()     : home_url( '/business-owner-login/' );
    $support_email = function_exists( 'crs_get_support_email' ) ? crs_get_support_email() : get_option( 'admin_email' );
    $site_name     = function_exists( 'crs_get_company_name' )  ? crs_get_company_name()  : get_bloginfo( 'name' );

    $content = '
        <p>Hello <strong>' . esc_html( $owner->owner_name ) . '</strong>,</p>
        <p>Here are your login credentials for the <strong>' . esc_html( $site_name ) . '</strong> Business Owner Dashboard:</p>

        <table class="item-table" style="border-collapse:collapse;width:100%;margin:14px 0;">
            <tr><th colspan="2" style="padding:8px 10px;text-align:left;">Your Login Credentials</th></tr>
            <tr><td style="padding:8px 10px;width:130px;">Username</td><td style="padding:8px 10px;"><strong>' . esc_html( $owner->username ) . '</strong></td></tr>
            <tr><td style="padding:8px 10px;">Email</td><td style="padding:8px 10px;">' . esc_html( $owner->owner_email ) . '</td></tr>
            <tr><td style="padding:8px 10px;">Password</td><td style="padding:8px 10px;"><strong>' . esc_html( $password ) . '</strong></td></tr>
        </table>

        <p style="text-align:center;margin:24px 0;">
            <a href="' . esc_url( $login_url ) . '" class="button">Login to Your Dashboard</a>
        </p>

        <p style="color:#555;">If you did not request this email please contact us at <a href="mailto:' . esc_attr( $support_email ) . '">' . esc_html( $support_email ) . '</a>.</p>
    ';

    $html   = get_global_email_template( $content, 'Your Account Credentials — ' . $site_name );
    $result = bod_send_email( $owner->owner_email, 'Your Business Owner Account Credentials — ' . $site_name, $html );

    if ( $result ) {
        bod_update_owner( $owner_id, [
            'credentials_email_sent'    => 'yes',
            'credentials_email_sent_at' => current_time( 'mysql' ),
        ] );
    }
    return $result;
}

// ============================================
// SUBMISSION RECEIVED (sent to owner after payment, before admin approval)
// ============================================

function bod_send_submission_received_email( $owner_id, $session = null ) {
    $owner = bod_get_owner( $owner_id );
    if ( ! $owner ) return false;

    $site_name = function_exists( 'crs_get_company_name' ) ? crs_get_company_name() : get_bloginfo( 'name' );
    $amount    = $session ? number_format( (float) ( $session->amount_total ?? 0 ) / 100, 2 ) : '0.00';
    $ref       = $session ? ( $session->payment_intent ?? ( $session->id ?? '' ) ) : '';

    $content = '
        <p>Hello <strong>' . esc_html( $owner->owner_name ) . '</strong>,</p>
        <p>Thank you for signing up! Your payment has been processed and your application is now pending review.</p>

        <div class="info-box">
            <table style="width:100%;border-collapse:collapse;">
                <tr><td style="padding:5px 0;color:#555;font-size:13px;width:140px;">Business Name</td><td style="font-weight:600;font-size:13px;">' . esc_html( $owner->business_name ?: '-' ) . '</td></tr>
                <tr><td style="padding:5px 0;color:#555;font-size:13px;">Email</td><td style="font-size:13px;">' . esc_html( $owner->owner_email ) . '</td></tr>
                <tr><td style="padding:5px 0;color:#555;font-size:13px;">Amount Paid</td><td style="font-weight:700;font-size:13px;">$' . $amount . ' AUD</td></tr>
                ' . ( $ref ? '<tr><td style="padding:5px 0;color:#555;font-size:13px;">Reference</td><td style="font-size:11px;color:#999;">' . esc_html( substr( $ref, 0, 40 ) ) . '</td></tr>' : '' ) . '
            </table>
        </div>

        <p><strong>What Happens Next?</strong></p>
        <ol style="font-size:14px;line-height:2;">
            <li><strong>Admin Review</strong> — Our team will review and verify your business details (typically 1–2 business days).</li>
            <li><strong>Account Created</strong> — Once approved, your login credentials will be emailed to you.</li>
            <li><strong>Go Live</strong> — Log in, complete your profile, and start receiving leads!</li>
        </ol>

        <div class="warning-box">
            <strong>⏳ Your application is pending admin approval.</strong> You will receive another email with your login details once approved.
        </div>
    ';

    $html = get_global_email_template( $content, 'Submission Received — ' . $site_name );
    return bod_send_email( $owner->owner_email, 'Submission Received — ' . $site_name, $html );
}

// ============================================
// REJECTION EMAIL
// ============================================

function bod_send_rejection_email( $owner_id, $reason = '' ) {
    $owner = bod_get_owner( $owner_id );
    if ( ! $owner ) return false;

    $support_email = function_exists( 'crs_get_support_email' ) ? crs_get_support_email() : get_option( 'admin_email' );
    $site_name     = function_exists( 'crs_get_company_name' )  ? crs_get_company_name()  : get_bloginfo( 'name' );

    $content = '
        <p>Hello <strong>' . esc_html( $owner->owner_name ) . '</strong>,</p>
        <p>Unfortunately, your business owner application for <strong>' . esc_html( $site_name ) . '</strong> has not been approved at this time.</p>
        ' . ( $reason ? '<div class="warning-box"><strong>Reason:</strong> ' . esc_html( $reason ) . '</div>' : '' ) . '
        <p>If you believe this is an error or would like to discuss your application, please contact us at <a href="mailto:' . esc_attr( $support_email ) . '">' . esc_html( $support_email ) . '</a>.</p>
    ';

    $html = get_global_email_template( $content, 'Application Update — ' . $site_name );
    return bod_send_email( $owner->owner_email, 'Business Owner Application Update — ' . $site_name, $html );
}

// ============================================
// ADMIN NOTIFICATION (new signup)
// ============================================

function bod_send_new_owner_admin_notification( $owner_id ) {
    $owner       = bod_get_owner( $owner_id );
    if ( ! $owner ) return false;

    $admin_email = function_exists( 'crs_get_support_email' ) ? crs_get_support_email() : get_option( 'admin_email' );
    $site_name   = function_exists( 'crs_get_company_name' )  ? crs_get_company_name()  : get_bloginfo( 'name' );
    $review_url  = admin_url( 'admin.php?page=business-owners-pending' );

    $content = '
        <p>A new business owner has signed up on <strong>' . esc_html( $site_name ) . '</strong> and is awaiting your approval.</p>

        <table class="item-table" style="border-collapse:collapse;width:100%;margin:14px 0;">
            <tr><th colspan="2" style="padding:8px 10px;text-align:left;">Owner Details</th></tr>
            <tr><td style="padding:8px 10px;">Name</td>    <td style="padding:8px 10px;">' . esc_html( $owner->owner_name )                              . '</td></tr>
            <tr><td style="padding:8px 10px;">Email</td>   <td style="padding:8px 10px;">' . esc_html( $owner->owner_email )                             . '</td></tr>
            <tr><td style="padding:8px 10px;">Phone</td>   <td style="padding:8px 10px;">' . esc_html( $owner->owner_phone )                             . '</td></tr>
            <tr><td style="padding:8px 10px;">Business</td><td style="padding:8px 10px;">' . esc_html( $owner->business_name ?: '-' )                    . '</td></tr>
            <tr><td style="padding:8px 10px;">Location</td><td style="padding:8px 10px;">' . esc_html( ( $owner->suburb ?? '' ) . ', ' . ( $owner->state ?? '' ) ) . '</td></tr>
        </table>

        <p style="text-align:center;margin:24px 0;">
            <a href="' . esc_url( $review_url ) . '" class="button">Review Application</a>
        </p>
    ';

    $html = get_global_email_template( $content, 'New Business Owner Application' );
    return bod_send_email( $admin_email, 'New Business Owner Application — ' . $site_name, $html );
}
