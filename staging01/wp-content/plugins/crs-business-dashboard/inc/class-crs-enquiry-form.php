<?php
/**
 * CRS Custom Enquiry Form Handler
 *
 * Replaces Contact Form 7 entirely.
 * - AJAX submit via wp_ajax
 * - Saves files to server (deleted after 7 days)
 * - Inserts into wp_crs_enquiries
 * - Fires crs_enquiry_submitted → reuses existing email functions
 *
 * DB columns used (base + v1.2):
 *   enquiry_id, business_id, name, email, phone, suburb, postcode,
 *   region, state, service, message, contact_pref, status, created_at
 *   + contact_time (added by upgrade below)
 *
 * @package CRS
 */
defined( 'ABSPATH' ) || exit;

/* ======================================================================
   DB UPGRADE — add contact_time column if missing
   ==================================================================== */
add_action( 'admin_init', function () {
    if ( get_option( 'crs_enquiries_table_version' ) !== '1.3' ) {
        global $wpdb;
        $table    = $wpdb->prefix . 'crs_enquiries';
        $existing = $wpdb->get_col( "DESC {$table}", 0 );

        $upgrades = [
            'contact_time' => "ALTER TABLE {$table} ADD COLUMN contact_time VARCHAR(100) DEFAULT '' AFTER contact_pref",
            'postcode'     => "ALTER TABLE {$table} ADD COLUMN postcode VARCHAR(10) DEFAULT '' AFTER suburb",
            'region'       => "ALTER TABLE {$table} ADD COLUMN region VARCHAR(100) DEFAULT '' AFTER postcode",
            'state'        => "ALTER TABLE {$table} ADD COLUMN state VARCHAR(100) DEFAULT '' AFTER region",
        ];

        foreach ( $upgrades as $col => $sql ) {
            if ( ! in_array( $col, $existing, true ) ) {
                $wpdb->query( $sql );
            }
        }
        update_option( 'crs_enquiries_table_version', '1.3' );
    }
} );

/* ======================================================================
   ENQUEUE — nonce available to JS on enquiry pages only
   ==================================================================== */
add_action( 'wp_enqueue_scripts', function () {
    if ( ! get_query_var( 'crs_enquiry_slug' ) ) return;
    wp_localize_script( 'jquery', 'crsEnquiry', [
        'ajaxUrl' => admin_url( 'admin-ajax.php' ),
        'nonce'   => wp_create_nonce( 'crs_enquiry_nonce' ),
    ] );
} );

/* ======================================================================
   AJAX HANDLER
   ==================================================================== */
add_action( 'wp_ajax_nopriv_crs_submit_enquiry', 'crs_ajax_submit_enquiry' );
add_action( 'wp_ajax_crs_submit_enquiry',        'crs_ajax_submit_enquiry' );

function crs_ajax_submit_enquiry() {

    /* ── Security ──────────────────────────────────────────────── */
    if ( ! wp_verify_nonce(
        sanitize_text_field( $_POST['crs_nonce'] ?? '' ),
        'crs_enquiry_nonce'
    ) ) {
        wp_send_json_error( [ 'message' => 'Security check failed. Please refresh and try again.' ] );
    }

    /* ── Sanitise all POST fields ───────────────────────────────── */
    $business_id    = absint( $_POST['business_id']         ?? 0 );
    $name           = sanitize_text_field( $_POST['your-name']           ?? '' );
    $email          = sanitize_email(      $_POST['your-email']          ?? '' );
    $phone          = sanitize_text_field( $_POST['your-phone']          ?? '' );
    $postcode       = sanitize_text_field( $_POST['your-postcode']       ?? '' );
    $suburb         = sanitize_text_field( $_POST['your-suburb']         ?? '' );
    $region         = sanitize_text_field( $_POST['your-region']         ?? '' );
    $state          = sanitize_text_field( $_POST['your-state']          ?? '' );
    $message        = sanitize_textarea_field( $_POST['your-message']    ?? '' );
    $contact_method = sanitize_text_field( $_POST['your-contact-method'] ?? 'Email' );
    $contact_time   = sanitize_text_field( $_POST['your-time']           ?? '' );
    $acceptance     = ! empty( $_POST['acceptance-check'] );

    // service[] is an array of checkbox values
    $service_raw = isset( $_POST['service'] ) ? (array) $_POST['service'] : [];
    $service     = implode( ', ', array_map( 'sanitize_text_field', $service_raw ) );

    /* ── Required field validation ──────────────────────────────── */
    $errors = [];
    if ( ! $business_id )       $errors[] = 'Business not identified.';
    if ( empty( $name ) )       $errors[] = 'Your name is required.';
    if ( ! is_email( $email ) ) $errors[] = 'A valid email address is required.';
    if ( empty( $phone ) )      $errors[] = 'Your phone number is required.';
    if ( empty( $message ) )    $errors[] = 'Please describe your issue.';
    if ( ! $acceptance )        $errors[] = 'You must agree to the Privacy Policy and Terms of Use.';

    if ( $errors ) {
        wp_send_json_error( [ 'message' => implode( ' ', $errors ) ] );
    }

    /* ── Handle photo uploads (optional) ───────────────────────── */
    $saved_images = [];

    if ( ! empty( $_FILES['photos']['name'][0] ) ) {

        $upload_dir    = wp_upload_dir();
        $dest_folder   = trailingslashit( $upload_dir['basedir'] ) . 'crs-enquiry-images/';
        $dest_url_base = trailingslashit( $upload_dir['baseurl'] ) . 'crs-enquiry-images/';

        if ( ! file_exists( $dest_folder ) ) {
            wp_mkdir_p( $dest_folder );
            // Prevent directory listing and direct execution
            file_put_contents( $dest_folder . '.htaccess', "Options -Indexes\ndeny from all" );
            file_put_contents( $dest_folder . 'index.php', '<?php // Silence is golden.' );
        }

        $allowed_types = [ 'image/jpeg', 'image/jpg', 'image/png', 'application/pdf' ];
        $max_size      = 10 * 1024 * 1024; // 10 MB per file

        $file_count = is_array( $_FILES['photos']['name'] ) ? count( $_FILES['photos']['name'] ) : 1;

        for ( $i = 0; $i < min( $file_count, 3 ); $i++ ) {
            if ( $_FILES['photos']['error'][ $i ] !== UPLOAD_ERR_OK ) continue;
            if ( ! in_array( $_FILES['photos']['type'][ $i ], $allowed_types, true ) ) continue;
            if ( $_FILES['photos']['size'][ $i ] > $max_size ) continue;

            $original = sanitize_file_name( $_FILES['photos']['name'][ $i ] );
            $unique   = time() . '_' . $i . '_' . wp_generate_password( 6, false ) . '_' . $original;
            $dest     = $dest_folder . $unique;

            if ( move_uploaded_file( $_FILES['photos']['tmp_name'][ $i ], $dest ) ) {
                $saved_images[] = [
                    'path'     => $dest,
                    'url'      => $dest_url_base . $unique,
                    'name'     => $original,
                    'uploaded' => time(),
                ];
            }
        }
    }

    /* ── Insert into wp_crs_enquiries ───────────────────────────── */
    global $wpdb;
    $table = $wpdb->prefix . 'crs_enquiries';

    $inserted = $wpdb->insert(
        $table,
        [
            'business_id'  => $business_id,
            'name'         => $name,
            'email'        => $email,
            'phone'        => $phone,
            'suburb'       => $suburb,
            'postcode'     => $postcode,
            'region'       => $region,
            'state'        => $state,
            'service'      => $service,
            'message'      => $message,
            'contact_pref' => $contact_method,
            'contact_time' => $contact_time,
            'status'       => 'new',
            'created_at'   => current_time( 'mysql' ),
        ],
        [ '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ]
    );

    if ( ! $inserted ) {
        error_log( '[CRS Enquiry] DB insert failed: ' . $wpdb->last_error );
        wp_send_json_error( [ 'message' => 'Could not save your enquiry. Please try again.' ] );
    }

    $enquiry_id = (int) $wpdb->insert_id;

    /* ── Store image paths + schedule 7-day cleanup ─────────────── */
    if ( ! empty( $saved_images ) ) {
        update_option( 'crs_enquiry_images_' . $enquiry_id, $saved_images, false );
        wp_schedule_single_event(
            time() + ( 7 * DAY_IN_SECONDS ),
            'crs_delete_enquiry_images',
            [ $enquiry_id ]
        );
    }

    /* ── Fire email notifications ───────────────────────────────── */
    // Reuses crs_send_enquiry_notification() from class-crs-enquiries.php
    // which reads the row we just inserted and sends two emails.
    do_action( 'crs_enquiry_submitted', $enquiry_id, $business_id );

    wp_send_json_success( [
        'message'    => 'Thank you! Your enquiry has been sent. ' .
                        get_the_title( $business_id ) .
                        ' will get back to you shortly.',
        'enquiry_id' => $enquiry_id,
    ] );
}

/* ======================================================================
   CRON — delete uploaded images after 7 days
   ==================================================================== */
add_action( 'crs_delete_enquiry_images', 'crs_do_delete_enquiry_images' );

function crs_do_delete_enquiry_images( $enquiry_id ) {
    $images = get_option( 'crs_enquiry_images_' . $enquiry_id, [] );
    foreach ( $images as $img ) {
        if ( ! empty( $img['path'] ) && file_exists( $img['path'] ) ) {
            @unlink( $img['path'] );
        }
    }
    delete_option( 'crs_enquiry_images_' . $enquiry_id );
}