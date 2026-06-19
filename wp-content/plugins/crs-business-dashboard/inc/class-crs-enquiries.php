<?php
/**
 * CRS Business Dashboard – class-crs-enquiries.php
 * @package CRS
 * @author  Priya
 * TODO: Implement this class
 */
defined( 'ABSPATH' ) || exit;
/**
 * BODY — Enquiry Received (sent to business owner)
 * Subscription status check pannitu mask/full decide pannudhu idhe function la.
 */
function crs_email_body_enquiry_received( $enquiry, $business ) {

    $is_active = ( $business['subscription_status'] === 'active' );

    if ( $is_active ) {
        $name    = esc_html( $enquiry['name'] );
        $email   = esc_html( $enquiry['email'] );
        $phone   = esc_html( $enquiry['phone'] );
        $message = nl2br( esc_html( $enquiry['message'] ) );
    } else {
        $name    = crs_mask_name( $enquiry['name'] );
        $email   = crs_mask_email( $enquiry['email'] );
        $phone   = crs_mask_phone( $enquiry['phone'] );
        $message = 'Reactivate your subscription to view this message';
    }

    ob_start();
    ?>
    <h2 style="margin:0 0 12px;font-size:18px;">Grand Enquiry Received</h2>
    <p>Hello <?php echo esc_html( $business['owner_name'] ); ?>,</p>
    <p><?php echo $name; ?> sent an enquiry about "<?php echo esc_html( $business['title'] ); ?>"</p>

    <table width="100%" cellpadding="8" cellspacing="0" style="border:1px solid #e7ecf3;margin:16px 0;">
      <tr><td style="background:#eaf1fc;font-weight:bold;">Name</td><td><?php echo $name; ?></td></tr>
      <tr><td style="background:#eaf1fc;font-weight:bold;">Email</td><td><?php echo $email; ?></td></tr>
      <tr><td style="background:#eaf1fc;font-weight:bold;">Phone</td><td><?php echo $phone; ?></td></tr>
      <tr><td style="background:#eaf1fc;font-weight:bold;">Message</td><td><?php echo $message; ?></td></tr>
    </table>

    <?php if ( ! $is_active ) : ?>
      <div style="background:#fff8e6;border:1px solid #f5a623;border-radius:8px;padding:16px;margin-top:10px;">
        <strong style="color:#b5780b;">⚠ Your subscription is inactive</strong>
        <p style="margin:8px 0;color:#7a5a10;">Customer contact details have been hidden. Reactivate to view full details.</p>
        <a href="<?php echo esc_url( home_url( '/dashboard/?reactivate=' . $business['id'] ) ); ?>"
           style="display:inline-block;background:#1565d8;color:#fff;padding:10px 20px;border-radius:6px;text-decoration:none;">
          Reactivate Subscription
        </a>
      </div>
    <?php endif; ?>
    <?php
    return ob_get_clean();
}
function crs_mask_name( $name ) {
    $parts = explode( ' ', trim( $name ) );
    $first = array_shift( $parts );
    return esc_html( $first ) . ' ****';
}

function crs_mask_email( $email ) {
    [ $user, $domain ] = array_pad( explode( '@', $email ), 2, '' );
    $visible = mb_substr( $user, 0, 2 );
    return esc_html( $visible . '****@' . $domain );
}

function crs_mask_phone( $phone ) {
    $digits = preg_replace( '/\D/', '', $phone );
    $last3  = substr( $digits, -3 );
    return '**** *** ' . esc_html( $last3 );
}
add_action( 'admin_init', function() {
    if ( get_option( 'crs_enquiries_table_version' ) !== '1.1' ) {
        crs_upgrade_enquiries_table_v2();
    }
});
function crs_upgrade_enquiries_table_v2() {
    global $wpdb;
    $table = $wpdb->prefix . 'crs_enquiries';
    $existing_columns = $wpdb->get_col( "DESC {$table}", 0 );
    if ( ! in_array( 'subject', $existing_columns, true ) ) {
        $wpdb->query( "ALTER TABLE {$table} ADD COLUMN subject VARCHAR(200) DEFAULT '' AFTER service" );
    }
    if ( ! in_array( 'postcode', $existing_columns, true ) ) {
        $wpdb->query( "ALTER TABLE {$table} ADD COLUMN postcode VARCHAR(10) DEFAULT '' AFTER suburb" );
    }
    if ( ! in_array( 'finance_amount', $existing_columns, true ) ) {
        $wpdb->query( "ALTER TABLE {$table} ADD COLUMN finance_amount DECIMAL(10,2) DEFAULT NULL AFTER contact_pref" );
    }
    update_option( 'crs_enquiries_table_version', '1.1' );
}