<?php
/**
 * CRS – Enquiry Image Handler
 * - Saves CF7 file uploads to server (wp-content/uploads/crs-enquiry-images/)
 * - Strips the attachment from CF7 notification emails
 * - Deletes stored images after 7 days via WP-Cron
 *
 * @package CRS
 */
defined( 'ABSPATH' ) || exit;
/* =====================================================================
   1. INTERCEPT CF7 SUBMISSION — save image, strip attachment from email
   =================================================================== */
add_action( 'wpcf7_before_send_mail', 'crs_handle_enquiry_image_upload', 10, 3 );
function crs_handle_enquiry_image_upload( $contact_form, &$abort, $submission ) {
    $uploaded_files = $submission->uploaded_files();
    // No files uploaded — nothing to do.
    if ( empty( $uploaded_files ) ) {
        return;
    }
    // Destination folder
    $upload_dir  = wp_upload_dir();
    $dest_folder = trailingslashit( $upload_dir['basedir'] ) . 'crs-enquiry-images/';
    if ( ! file_exists( $dest_folder ) ) {
        wp_mkdir_p( $dest_folder );
        // Prevent direct browsing
        file_put_contents( $dest_folder . '.htaccess', 'deny from all' );
        file_put_contents( $dest_folder . 'index.php', '<?php // Silence is golden.' );
    }
    $saved_paths = [];
    foreach ( $uploaded_files as $field_name => $file_path ) {
        if ( empty( $file_path ) || ! file_exists( $file_path ) ) {
            continue;
        }
        $original_name = sanitize_file_name( basename( $file_path ) );
        $unique_name   = time() . '_' . wp_generate_password( 8, false ) . '_' . $original_name;
        $dest_path     = $dest_folder . $unique_name;
        if ( copy( $file_path, $dest_path ) ) {
            $saved_paths[ $field_name ] = [
                'path'      => $dest_path,
                'name'      => $original_name,
                'uploaded'  => time(),
            ];
        }
    }
    if ( ! empty( $saved_paths ) ) {
        // Store image paths in a transient keyed to this submission so the
        // CF7 wpcf7_mail_sent hook can write them into comment meta.
        $key = 'crs_enq_img_' . md5( $submission->get_posted_data()['business_id'] ?? '' . microtime() );
        set_transient( $key, $saved_paths, HOUR_IN_SECONDS );
        $submission->set( 'crs_image_transient_key', $key );
    }
    // ── Strip the file attachment from CF7's outgoing mail ──────────────
    $mail     = $contact_form->prop( 'mail' );
    $mail_2   = $contact_form->prop( 'mail_2' );
    // CF7 puts file tags like [your-file] in the "attachments" field of mail.
    // Clear it so no file is attached to either mail template.
    if ( ! empty( $mail['attachments'] ) ) {
        $mail['attachments'] = '';
        $contact_form->set_properties( [ 'mail' => $mail ] );
    }
    if ( ! empty( $mail_2['attachments'] ) ) {
        $mail_2['attachments'] = '';
        $contact_form->set_properties( [ 'mail_2' => $mail_2 ] );
    }
}
/* =====================================================================
   2. AFTER MAIL SENT — write image paths into comment meta
   =================================================================== */
add_action( 'wpcf7_mail_sent', 'crs_save_enquiry_image_meta' );
function crs_save_enquiry_image_meta( $contact_form ) {
    $submission = WPCF7_Submission::get_instance();
    if ( ! $submission ) return;
    $key = $submission->get( 'crs_image_transient_key' );
    if ( ! $key ) return;
    $saved_paths = get_transient( $key );
    delete_transient( $key );
    if ( empty( $saved_paths ) ) return;
    // Find the most recent comment (enquiry) just created for this business.
    $posted = $submission->get_posted_data();
    $business_id = absint( $posted['business_id'] ?? 0 );
    if ( ! $business_id ) return;
    $comments = get_comments( [
        'post_id' => $business_id,
        'type'    => 'comment',
        'number'  => 1,
        'orderby' => 'comment_date_gmt',
        'order'   => 'DESC',
        'status'  => 'approve',
    ] );
    if ( empty( $comments ) ) return;
    $comment_id = $comments[0]->comment_ID;
    foreach ( $saved_paths as $field_name => $info ) {
        add_comment_meta( $comment_id, 'enquiry_image_' . sanitize_key( $field_name ), $info );
    }
    // Also store all paths as a JSON blob for easy querying by the cron.
    $existing = get_comment_meta( $comment_id, 'enquiry_images', true ) ?: [];
    $existing  = array_merge( $existing, $saved_paths );
    update_comment_meta( $comment_id, 'enquiry_images', $existing );
}
/* =====================================================================
   3. WP-CRON — delete images older than 7 days
   =================================================================== */
// Register schedule on plugin load
add_filter( 'cron_schedules', 'crs_add_cron_daily_schedule' );
function crs_add_cron_daily_schedule( $schedules ) {
    if ( ! isset( $schedules['daily'] ) ) {
        $schedules['daily'] = [
            'interval' => DAY_IN_SECONDS,
            'display'  => __( 'Once Daily', 'crs' ),
        ];
    }
    return $schedules;
}
// Schedule the event if not already scheduled
add_action( 'init', 'crs_schedule_image_cleanup' );
function crs_schedule_image_cleanup() {
    if ( ! wp_next_scheduled( 'crs_cleanup_enquiry_images' ) ) {
        wp_schedule_event( time(), 'daily', 'crs_cleanup_enquiry_images' );
    }
}
// The cleanup callback
add_action( 'crs_cleanup_enquiry_images', 'crs_do_cleanup_enquiry_images' );
function crs_do_cleanup_enquiry_images() {
    $upload_dir  = wp_upload_dir();
    $folder      = trailingslashit( $upload_dir['basedir'] ) . 'crs-enquiry-images/';
    if ( ! is_dir( $folder ) ) return;
    $cutoff = time() - ( 7 * DAY_IN_SECONDS );
    foreach ( glob( $folder . '*' ) as $file ) {
        if ( ! is_file( $file ) ) continue;
        if ( filemtime( $file ) < $cutoff ) {
            @unlink( $file );
        }
    }
}
// Clean up cron on plugin deactivation (call from your crs_deactivate())
// add this line inside crs_deactivate():
//   wp_clear_scheduled_hook( 'crs_cleanup_enquiry_images' );