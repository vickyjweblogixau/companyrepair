<?php
/**
 * CRS Business Dashboard – Email Helper Functions
 * Common header + footer wrapper, swappable body per email type.
 */
defined( 'ABSPATH' ) || exit;

/* ======================================================================
   1. HEADER  — same for every email
   ==================================================================== */
function crs_email_header() {
    /*$logo_id = get_theme_mod( 'custom_logo' );
    if ( $logo_id ) {
        $logo_url = wp_get_attachment_image_url( $logo_id, 'full' );
    } else {
        $logo_url = get_template_directory_uri() . '/assets/images/logo-white.png';
    } */
    $logo_url = get_template_directory_uri() . '/assets/images/logo-white.png';
    $site_url = home_url( '/' );
    ob_start();  
    ?>
    <table width="100%" cellpadding="0" cellspacing="0" style="background:#f6f8fc;padding:24px 0;font-family:Arial,sans-serif;">
      <tr>
        <td align="center">
          <table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:10px;overflow:hidden;">

            <!-- Header -->
            <tr>
              <td style="background:#0a2647;padding:22px 30px;text-align:center;">
                <a href="<?php echo esc_url( $site_url ); ?>">
                  <img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>" style="height:42px;">
                </a>
              </td>
            </tr>

            <!-- Body starts -->
            <tr>
              <td style="padding:30px;color:#1b2430;font-size:14px;line-height:1.6;">
    <?php
    return ob_get_clean();
}

/* ======================================================================
   2. FOOTER  — same for every email
   ==================================================================== */
function crs_email_footer() {
    $site_name = get_bloginfo( 'name' );
    $year      = date( 'Y' );

    ob_start();
    ?>
              </td>
            </tr>
            <!-- Body ends -->

            <!-- Footer -->
            <tr>
              <td style="background:#f6f8fc;padding:20px 30px;text-align:center;color:#6b7785;font-size:12px;line-height:1.6;">
                <p style="margin:0 0 6px;">
                  &copy; <?php echo esc_html( $year ); ?> <?php echo esc_html( $site_name ); ?>. All Rights Reserved.
                </p>
                <p style="margin:0;">
                  This is an automated message. If you have questions, contact
                  <a href="mailto:<?php echo esc_attr( get_option( 'admin_email' ) ); ?>" style="color:#1565d8;">
                    <?php echo esc_html( get_option( 'admin_email' ) ); ?>
                  </a>
                </p>
              </td>
            </tr>

          </table>
        </td>
      </tr>
    </table>
    <?php
    return ob_get_clean();
}

/* ======================================================================
   3. WRAPPER — combines header + body + footer
   ==================================================================== */
function crs_render_email( $body_html ) {
    return crs_email_header() . $body_html . crs_email_footer();
}

/* ======================================================================
   4. SEND — actual wp_mail call, always HTML content-type
   ==================================================================== */
function crs_send_email( $to, $subject, $body_html ) {
    $headers = [ 'Content-Type: text/html; charset=UTF-8' ];
    $message = crs_render_email( $body_html );
    return wp_mail( $to, $subject, $message, $headers );
}
