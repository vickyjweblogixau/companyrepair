<?php
/**
 * CRS Business Dashboard – class-crs-enquiries.php
 * @package CRS
 * @author  Priya
 */
defined( 'ABSPATH' ) || exit;

/* ======================================================================
   EMAIL BODY — Business Owner Notification
   ==================================================================== */
function crs_email_body_enquiry_received( $enquiry, $business ) {
    $is_active = ( $business['subscription_status'] === 'active' );

    if ( $is_active ) {
        $name     = esc_html( $enquiry['name'] );
        $email    = esc_html( $enquiry['email'] );
        $phone    = esc_html( $enquiry['phone'] );
        $message  = nl2br( esc_html( $enquiry['message'] ) );
        $service  = esc_html( $enquiry['service'] );
        $suburb   = esc_html( $enquiry['suburb']   ?? '' );
        $postcode = esc_html( $enquiry['postcode']  ?? '' );
        $region   = esc_html( $enquiry['region']   ?? '' );
        $state    = esc_html( $enquiry['state']    ?? '' );
        $contact_method     = esc_html( $enquiry['your-contact-method'] ?? '' );
        $time     = esc_html( $enquiry['your_time'] ?? '' ); // 
    } else {
        $name     = crs_mask_name( $enquiry['name'] );
        $email    = crs_mask_email( $enquiry['email'] );
        $phone    = crs_mask_phone( $enquiry['phone'] );
        $message  = 'Activate your subscription to view this message';
        $service  = 'Activate your subscription to view this service';
        $suburb   = '****';
        $postcode = '****';
        $region   = '****';
        $state    = '****';
        $time     = '****';
    }

    ob_start();
    ?>
    <h2 style="margin:0 0 12px;font-size:18px;">New Enquiry Received</h2>
    <p>Hello <?php echo esc_html( $business['owner_name'] ); ?>,</p>
    <p><?php echo $name; ?> sent an enquiry about "<strong><?php echo esc_html( $business['title'] ); ?></strong>"</p>

    <table width="100%" cellpadding="8" cellspacing="0" style="border:1px solid #e7ecf3;margin:16px 0;border-collapse:collapse;">
        <tr><td style="background:#eaf1fc;font-weight:bold;width:140px;">Name</td><td><?php echo $name; ?></td></tr>
        <tr><td style="background:#eaf1fc;font-weight:bold;">Email</td><td><?php echo $email; ?></td></tr>
        <tr><td style="background:#eaf1fc;font-weight:bold;">Phone</td><td><?php echo $phone; ?></td></tr>
        <?php if ( $is_active ) : ?>
        <tr><td style="background:#eaf1fc;font-weight:bold;">Service</td><td><?php echo $service; ?></td></tr>
        <?php if ( $suburb || $postcode ) : ?>
        <tr>
            <td style="background:#eaf1fc;font-weight:bold;">Location</td>
            <td>
                <?php
                $loc = array_filter( [ $suburb, $postcode, $region, $state ] );
                echo implode( ', ', $loc );
                ?>
            </td>
        </tr>
        <?php endif; ?>
        <tr><td style="background:#eaf1fc;font-weight:bold;">Message</td><td><?php echo $message; ?></td></tr>
        <?php else : ?>
        <tr><td style="background:#eaf1fc;font-weight:bold;">Details</td><td>Activate your subscription to view customer enquiry details.</td></tr>
        <?php endif; ?>
    </table>

    <?php if ( ! $is_active ) : ?>
    <div style="background:#fff8e6;border:1px solid #f5a623;border-radius:8px;padding:16px;margin-top:10px;">
        <strong style="color:#b5780b;">⚠ Your subscription is inactive</strong>
        <p style="margin:8px 0;color:#7a5a10;">Customer contact details have been hidden. Activate your subscription to access the complete enquiry.</p>
        <a href="<?php echo esc_url( home_url( '/dashboard/?reactivate=' . $business['id'] ) ); ?>"
           style="display:inline-block;background:#1565d8;color:#fff;padding:10px 20px;border-radius:6px;text-decoration:none;">
            Activate Subscription
        </a>
    </div>
    <?php endif; ?>
    <?php
    return ob_get_clean();
}

/* ======================================================================
   EMAIL BODY — Customer Confirmation
   ==================================================================== */
function crs_email_body_enquiry_confirmation( $enquiry, $business ) {
    ob_start();
    ?>
    <h2 style="margin:0 0 12px;font-size:18px;">Thanks for your enquiry!</h2>
    <p>Hi <?php echo esc_html( $enquiry['name'] ); ?>,</p>
    <p>Thank you for reaching out to <strong><?php echo esc_html( $business['title'] ); ?></strong> through ComputerRepairServices.com.au.</p>
    <p>Your enquiry has been received and the business will get back to you shortly.</p>

    <table width="100%" cellpadding="8" cellspacing="0" style="border:1px solid #e7ecf3;margin:16px 0;border-collapse:collapse;">
        <tr><td style="background:#eaf1fc;font-weight:bold;width:140px;">Business</td><td><?php echo esc_html( $business['title'] ); ?></td></tr>
        <?php if ( ! empty( $enquiry['service'] ) ) : ?>
        <tr><td style="background:#eaf1fc;font-weight:bold;">Service</td><td><?php echo esc_html( $enquiry['service'] ); ?></td></tr>
        <?php endif; ?>
        <?php
        $loc = array_filter( [
            $enquiry['suburb']   ?? '',
            $enquiry['postcode'] ?? '',
            $enquiry['region']   ?? '',
            $enquiry['state']    ?? '',
        ] );
        if ( $loc ) : ?>
        <tr><td style="background:#eaf1fc;font-weight:bold;">Your Location</td><td><?php echo esc_html( implode( ', ', $loc ) ); ?></td></tr>
        <?php endif; ?>
        <tr><td style="background:#eaf1fc;font-weight:bold;">Your Message</td><td><?php echo nl2br( esc_html( $enquiry['message'] ) ); ?></td></tr>
    </table>

    <p>If you have any further questions, simply reply to this email.</p>
    <?php
    return ob_get_clean();
}

/* ======================================================================
   MASK HELPERS
   ==================================================================== */
function crs_mask_name( $name ) {
    $parts = explode( ' ', trim( $name ) );
    $first = array_shift( $parts );
    return esc_html( $first ) . ' ****';
}
function crs_mask_email( $email ) {
    [ $user, $domain ] = array_pad( explode( '@', $email ), 2, '' );
    return esc_html( mb_substr( $user, 0, 2 ) . '****@' . $domain );
}
function crs_mask_phone( $phone ) {
    $digits = preg_replace( '/\D/', '', $phone );
    return '**** *** ' . esc_html( substr( $digits, -3 ) );
}

/* ======================================================================
   SEND NOTIFICATION — hooked to crs_enquiry_submitted
   ==================================================================== */
add_action( 'crs_enquiry_submitted', 'crs_send_enquiry_notification', 10, 2 );

function crs_send_enquiry_notification( $enquiry_id, $business_id ) {
    global $wpdb;

    $enquiry = $wpdb->get_row(
        $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}crs_enquiries WHERE enquiry_id = %d", $enquiry_id ),
        ARRAY_A
    );
    if ( ! $enquiry ) return;

    $owner_id    = (int) get_field( 'crs_owner_user_id', $business_id );
    $owner_email = get_field( 'crs_email', $business_id );
    if ( ! $owner_email && $owner_id ) {
        $owner_user  = get_userdata( $owner_id );
        $owner_email = $owner_user ? $owner_user->user_email : '';
    }
    if ( ! $owner_email ) {
        $owner_email = get_option( 'admin_email' );
    }

    $owner_name = ( $owner_id && get_userdata( $owner_id ) )
        ? ( get_userdata( $owner_id )->display_name ?: 'Business Owner' )
        : 'Business Owner';

    $business = [
        'id'                  => $business_id,
        'title'               => get_the_title( $business_id ),
        'owner_name'          => $owner_name,
        'subscription_status' => get_field( 'crs_subscription_status', $business_id ) ?: 'active',
    ];

    // Admin / owner email
    if ( $owner_email ) {
        crs_send_email(
            $owner_email,
            'New Enquiry: ' . $business['title'],
            crs_email_body_enquiry_received( $enquiry, $business )
        );
    }

    // Customer confirmation
    if ( ! empty( $enquiry['email'] ) ) {
        crs_send_email(
            $enquiry['email'],
            'Thanks for your enquiry — ' . $business['title'],
            crs_email_body_enquiry_confirmation( $enquiry, $business )
        );
    }
}

/* ======================================================================
   DB UPGRADE — adds subject, postcode, region, state, finance_amount
   Version 1.2
   ==================================================================== */


