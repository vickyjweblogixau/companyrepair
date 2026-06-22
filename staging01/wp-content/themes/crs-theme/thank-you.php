<?php
/**
 * Template Name: CRS Thank You
 * Description: Shown after Stripe payment for business owner signup
 */

defined('ABSPATH') || exit;

get_header();

$session_id = isset($_GET['session_id']) ? sanitize_text_field($_GET['session_id']) : '';
$error      = '';
$session_data = null;

if ($session_id) {
    try {
        $secret_key = defined('BOD_STRIPE_SECRET_KEY') ? BOD_STRIPE_SECRET_KEY : get_option('bod_stripe_secret_key', '');
        if ($secret_key) {
            \Stripe\Stripe::setApiKey($secret_key);
        }

        $session = \Stripe\Checkout\Session::retrieve([
            'id'     => $session_id,
            'expand' => ['payment_intent', 'customer'],
        ]);

        date_default_timezone_set('Australia/Sydney');

        $payment_intent = $session->payment_intent;
        if (is_string($payment_intent)) {
            $payment_intent = \Stripe\PaymentIntent::retrieve($payment_intent);
        }

        $session_data = [
            'customer_email'    => $session->customer_details->email ?? $session->metadata->owner_email ?? '',
            'customer_name'     => $session->customer_details->name  ?? $session->metadata->owner_name  ?? '',
            'amount'            => number_format($session->amount_total / 100, 2),
            'currency'          => strtoupper($session->currency),
            'status'            => $payment_intent ? ucfirst(str_replace('_', ' ', $payment_intent->status)) : 'Completed',
            'payment_intent_id' => is_object($payment_intent) ? $payment_intent->id : $session->payment_intent,
            'created'           => date('F j, Y, g:i a', $session->created),
        ];

        error_log('[CRS Thank You] Session retrieved: ' . print_r($session_data, true));

    } catch (\Stripe\Exception\InvalidRequestException $e) {
        $error = 'Invalid session ID provided.';
        error_log('[CRS Thank You] Invalid request: ' . $e->getMessage());
    } catch (\Stripe\Exception\AuthenticationException $e) {
        $error = 'Payment system authentication error.';
        error_log('[CRS Thank You] Auth error: ' . $e->getMessage());
    } catch (Exception $e) {
        $error = 'Unable to retrieve payment details.';
        error_log('[CRS Thank You] Error: ' . $e->getMessage());
    }
}
?>

<style>
    .crs-thankyou-wrap {
        max-width: 700px;
        margin: 60px auto;
        padding: 0 20px 60px;
        font-family: var(--crs-font);
        color: var(--crs-ink);
        text-align: center;
    }

    .crs-thankyou-box {
        background: #fff;
        border-radius: var(--crs-radius);
        padding: 40px;
        box-shadow: 0 6px 25px rgba(0, 0, 0, 0.08);
    }

    .crs-thankyou-box h1 {
        color: var(--crs-navy);
        font-size: 24px;
        font-weight: 700;
        margin-bottom: 5px;
    }

    .crs-thankyou-box .crs-ty-amount {
        font-size: 28px;
        color: var(--crs-blue);
        font-weight: 700;
        margin: 16px 0 0;
    }

    .crs-thankyou-box .crs-ty-list {
        list-style: none;
        padding: 0;
        margin: 20px 0;
        text-align: left;
        color: var(--crs-ink);
        line-height: 1.9;
    }

    .crs-thankyou-box .crs-ty-list li::before {
        content: "✓ ";
        color: var(--crs-green);
        font-weight: 700;
    }

    .crs-thankyou-box .crs-next-steps {
        background: var(--crs-light-blue);
        border-left: 4px solid var(--crs-blue);
        border-radius: 0 var(--crs-radius-sm) var(--crs-radius-sm) 0;
        padding: 20px 24px;
        margin: 24px 0;
        text-align: left;
    }

    .crs-thankyou-box .crs-next-steps h3 {
        margin: 0 0 14px;
        font-size: 16px;
        font-weight: 700;
        color: var(--crs-navy);
    }

    .crs-thankyou-box .crs-next-steps ol {
        margin: 0;
        padding-left: 20px;
        color: #444;
        line-height: 1.9;
    }

    .crs-thankyou-box .crs-detail-box {
        background: var(--crs-section-bg);
        border-radius: var(--crs-radius-sm);
        padding: 20px 24px;
        margin: 24px 0;
        text-align: left;
    }

    .crs-thankyou-box .crs-detail-box p {
        margin-bottom: 10px;
        font-size: 14px;
        color: var(--crs-ink);
    }

    .crs-thankyou-box .crs-detail-box strong {
        display: inline-block;
        min-width: 160px;
        color: var(--crs-navy);
    }

    .crs-thankyou-box .crs-detail-box code {
        font-size: 12px;
        word-break: break-all;
        background: var(--crs-line);
        padding: 2px 6px;
        border-radius: 4px;
        color: var(--crs-ink);
    }

    .crs-thankyou-box .crs-ty-note {
        font-size: 13px;
        color: var(--crs-muted);
        margin-top: 20px;
    }

    .crs-thankyou-box .crs-ty-btn {
        display: inline-block;
        background: var(--crs-blue);
        color: #fff;
        text-decoration: none;
        padding: 14px 32px;
        border-radius: var(--crs-radius-sm);
        font-size: 15px;
        font-weight: 600;
        margin-top: 24px;
        letter-spacing: 0.3px;
        transition: background 0.25s ease;
    }

    .crs-thankyou-box .crs-ty-btn:hover {
        background: var(--crs-blue-dark);
        color: #fff;
    }

    .crs-thankyou-box .crs-ty-back {
        display: block;
        margin-top: 14px;
        font-size: 13px;
        color: var(--crs-muted);
        text-decoration: none;
    }

    .crs-thankyou-box .crs-ty-back:hover {
        color: var(--crs-blue);
    }

    .crs-error-box {
        background: #fef2f2;
        border-left: 4px solid #dc2626;
        border-radius: 0 var(--crs-radius-sm) var(--crs-radius-sm) 0;
        padding: 20px 24px;
        margin: 24px 0;
        text-align: left;
        color: #7f1d1d;
    }

    .crs-error-box h3 {
        color: #dc2626;
        margin: 0 0 8px;
        font-size: 16px;
    }

    /* Checkmark animation */
    .crs-checkmark {
        width: 90px;
        height: 90px;
        margin: 0 auto 24px;
    }

    .crs-checkmark .check-icon {
        width: 80px;
        height: 80px;
        position: relative;
        border-radius: 50%;
        box-sizing: content-box;
        border: 4px solid var(--crs-blue);
        margin: 0 auto;
    }

    .crs-checkmark .check-icon::before {
        top: 3px;
        left: -2px;
        width: 30px;
        transform-origin: 100% 50%;
        border-radius: 100px 0 0 100px;
    }

    .crs-checkmark .check-icon::after {
        top: 0;
        left: 30px;
        width: 60px;
        transform-origin: 0 50%;
        border-radius: 0 100px 100px 0;
        animation: crs-rotate-circle 4.25s ease-in;
    }

    .crs-checkmark .check-icon::before,
    .crs-checkmark .check-icon::after {
        content: "";
        height: 100px;
        position: absolute;
        background: #ffffff;
        transform: rotate(-45deg);
    }

    .crs-checkmark .check-icon .icon-line {
        height: 5px;
        background-color: var(--crs-blue);
        display: block;
        border-radius: 2px;
        position: absolute;
        z-index: 10;
    }

    .crs-checkmark .check-icon .icon-line.line-tip {
        top: 46px;
        left: 14px;
        width: 25px;
        transform: rotate(45deg);
        animation: crs-icon-line-tip 0.75s;
    }

    .crs-checkmark .check-icon .icon-line.line-long {
        top: 38px;
        right: 8px;
        width: 47px;
        transform: rotate(-45deg);
        animation: crs-icon-line-long 0.75s;
    }

    .crs-checkmark .check-icon .icon-circle {
        top: -4px;
        left: -4px;
        z-index: 10;
        width: 80px;
        height: 80px;
        border-radius: 50%;
        position: absolute;
        box-sizing: content-box;
        border: 4px solid rgba(21, 101, 216, 0.25);
    }

    .crs-checkmark .check-icon .icon-fix {
        top: 8px;
        width: 5px;
        left: 26px;
        z-index: 1;
        height: 85px;
        position: absolute;
        transform: rotate(-45deg);
        background-color: #ffffff;
    }

    @keyframes crs-rotate-circle {
        0%   { transform: rotate(-45deg); }
        5%   { transform: rotate(-45deg); }
        12%  { transform: rotate(-405deg); }
        100% { transform: rotate(-405deg); }
    }

    @keyframes crs-icon-line-tip {
        0%   { width: 0;    left: 1px;  top: 19px; }
        54%  { width: 0;    left: 1px;  top: 19px; }
        70%  { width: 50px; left: -8px; top: 37px; }
        84%  { width: 17px; left: 21px; top: 48px; }
        100% { width: 25px; left: 14px; top: 45px; }
    }

    @keyframes crs-icon-line-long {
        0%   { width: 0;    right: 46px; top: 54px; }
        65%  { width: 0;    right: 46px; top: 54px; }
        84%  { width: 55px; right: 0px;  top: 35px; }
        100% { width: 47px; right: 8px;  top: 38px; }
    }

    @media (max-width: 600px) {
        .crs-thankyou-box { padding: 28px 20px; }
        .crs-thankyou-box h1 { font-size: 20px; }
        .crs-thankyou-box .crs-ty-amount { font-size: 24px; }
    }
</style>

<div class="crs-thankyou-wrap">
<div class="crs-thankyou-box">

    <?php if (!empty($error)) : ?>

        <!-- Error State -->
        <h1>Something Went Wrong</h1>
        <div class="crs-error-box">
            <h3>⚠️ Unable to verify payment</h3>
            <p><?php echo esc_html($error); ?></p>
            <p style="margin-top:12px;font-size:13px;">
                If you completed your payment, please check your email for confirmation.
                If the issue persists, please <a href="<?php echo esc_url(home_url('/contact/')); ?>" style="color:var(--crs-blue);">contact our support team</a>.
            </p>
        </div>
        <a href="<?php echo esc_url(home_url('/')); ?>" class="crs-ty-btn">Return to Home</a>

    <?php elseif (!empty($session_data)) : ?>

        <!-- Success State -->
        <div class="crs-checkmark">
            <div class="check-icon">
                <span class="icon-line line-tip"></span>
                <span class="icon-line line-long"></span>
                <div class="icon-circle"></div>
                <div class="icon-fix"></div>
            </div>
        </div>

        <h1>Thank You for Signing Up!</h1>
        <div class="crs-ty-amount">$<?php echo esc_html($session_data['amount']); ?> (incl. GST)</div>

        <ul class="crs-ty-list">
            <li>A confirmation email has been sent to <strong><?php echo esc_html($session_data['customer_email']); ?></strong></li>
            <li>Our admin team will review your application</li>
            <li>Once approved, you'll receive your account credentials via email within 24 hours</li>
        </ul>

        <div class="crs-next-steps">
            <h3>What Happens Next?</h3>
            <ol>
                <li><strong>Invoice:</strong> A tax invoice has been sent to your email address.</li>
                <li><strong>Account Pending Approval:</strong> Your business owner account is currently under review by our admin team.</li>
                <li><strong>Credentials:</strong> Once approved, you'll receive your login username and password via email within 24 hours. Please also check your spam folder.</li>
                <li><strong>Get Started:</strong> After receiving your credentials, log in to your dashboard and create your first listing!</li>
            </ol>
        </div>

        <div class="crs-detail-box">
            <p><strong>Name:</strong> <?php echo esc_html($session_data['customer_name']); ?></p>
            <p><strong>Email:</strong> <?php echo esc_html($session_data['customer_email']); ?></p>
            <p><strong>Amount:</strong> $<?php echo esc_html($session_data['amount']); ?> <?php echo esc_html($session_data['currency']); ?></p>
            <p><strong>Status:</strong> <span style="color:var(--crs-green);font-weight:600;"><?php echo esc_html($session_data['status']); ?></span></p>
            <p><strong>Reference:</strong> <code><?php echo esc_html($session_data['payment_intent_id']); ?></code></p>
            <p><strong>Date:</strong> <?php echo esc_html($session_data['created']); ?></p>
        </div>

        <p class="crs-ty-note"><strong>Important:</strong> Please check your spam folder if you don't receive the confirmation email within a few minutes.</p>

        <a href="<?php echo esc_url(home_url('/')); ?>" class="crs-ty-btn">Return to Homepage</a>

    <?php else : ?>

        <!-- Fallback State (no session_id) -->
        <div class="crs-checkmark">
            <div class="check-icon">
                <span class="icon-line line-tip"></span>
                <span class="icon-line line-long"></span>
                <div class="icon-circle"></div>
                <div class="icon-fix"></div>
            </div>
        </div>

        <h1>Thank You for Signing Up!</h1>
        <p style="color:var(--crs-muted);">Your payment has been received successfully.</p>

        <div class="crs-next-steps">
            <h3>What Happens Next?</h3>
            <ol>
                <li><strong>Invoice:</strong> A tax invoice has been sent to your email address.</li>
                <li><strong>Account Pending Approval:</strong> Your business owner account is currently under review by our admin team.</li>
                <li><strong>Credentials:</strong> Once approved, you'll receive your login username and password via email within 24 hours. Please also check your spam folder.</li>
                <li><strong>Get Started:</strong> After receiving your credentials, log in to your dashboard and create your first listing!</li>
            </ol>
        </div>

        <p class="crs-ty-note"><strong>Important:</strong> Please check your spam folder if you don't receive the confirmation email within a few minutes.</p>

        <a href="<?php echo esc_url(home_url('/')); ?>" class="crs-ty-btn">Return to Homepage</a>

    <?php endif; ?>

</div>
</div>

<?php get_footer(); ?>
