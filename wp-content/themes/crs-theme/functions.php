<?php
/**
 * CRS Theme — functions.php
 * ComputerRepairServices.com.au
 *
 * Loads all inc/ files and registers theme features.
 */

defined( 'ABSPATH' ) || exit;

define( 'CRS_VERSION', '1.0.0' );
define( 'CRS_DIR',     get_template_directory() );
define( 'CRS_URI',     get_template_directory_uri() );

/* --------------------------------------------------------------------------
 * Load includes
 * ---------------------------------------------------------------------- */
require_once CRS_DIR . '/inc/theme-setup.php';
require_once CRS_DIR . '/inc/enqueue.php';
require_once CRS_DIR . '/inc/template-helpers.php';
require_once CRS_DIR . '/inc/breadcrumbs.php';
// New Role
add_action( 'after_switch_theme', 'create_business_owner_role' );

function create_business_owner_role() {
    if ( get_role( 'business_owner' ) ) {
        return; // Already exists, skip
    }

    $capabilities = [
        'read'                   => true,
        'edit_posts'             => true,
        'edit_published_posts'   => true,
        'delete_posts'           => true,
        'delete_published_posts' => true,
        'publish_posts'          => true,
        'upload_files'           => true,
    ];

    add_role(
        'business_owner',
        'Business Owner',
        $capabilities
    );
}
add_action( 'switch_theme', function() {
    remove_role( 'business_owner' );
});
function my_theme_scripts() {
    wp_enqueue_script('jquery');
}
add_filter('wpcf7_autop_or_not', '__return_false');
add_action('wp_enqueue_scripts', 'my_theme_scripts');
// Contact form service
add_action('wpcf7_init', function () {
    wpcf7_add_form_tag('repair_services', function () {
        $terms = get_terms([
            'taxonomy'   => 'repair-service',
            'hide_empty' => false,
        ]);
        $html = '<select name="service" class="cs-control">';
        $html .= '<option value="">Select Service</option>';
        foreach ($terms as $term) {
            $html .= sprintf(
                '<option value="%s">%s</option>',
                esc_attr($term->name),
                esc_html($term->name)
            );
        }
        $html .= '</select>';
        return $html;
    });
});
add_action( 'wpcf7_before_send_mail', 'crs_save_cf7_enquiry_to_db' );
function crs_save_cf7_enquiry_to_db( $contact_form ) {
    if ( $contact_form->id() != '69' ) {
        return;
    }
    $submission = WPCF7_Submission::get_instance();
    if ( ! $submission ) {
        return;
    }
    $posted = $submission->get_posted_data();
    global $wpdb;
    $table = $wpdb->prefix . 'crs_enquiries';
    $business_id = absint( $posted['business_id'] ?? 0 );
    if ( ! $business_id || get_post_type( $business_id ) !== 'business' ) {
        return; // don't insert broken data
    }
    $wpdb->insert(
        $table,
        [
            'business_id'   => $business_id,
            'name'          => sanitize_text_field( $posted['your-name']    ?? '' ),
            'email'         => sanitize_email( $posted['your-email']        ?? '' ),
            'phone'         => sanitize_text_field( $posted['your-phone']   ?? '' ),
            'postcode'      => sanitize_text_field( $posted['your-postcode']?? '' ),
            'service'       => sanitize_text_field( $posted['service']      ?? '' ),
            'subject'       => sanitize_text_field( $posted['your-subject']?? '' ),
            'message'       => sanitize_textarea_field( $posted['your-message'] ?? '' ),
        //    'finance_quote' => ! empty( $posted['finance-quote'] ) ? 1 : 0,
            'finance_amount'       => sanitize_textarea_field( $posted['finance_amount'] ?? '' ),
            'status'        => 'new',
            'created_at'    => current_time( 'mysql' ),
        ],
        [ '%d','%s','%s','%s','%s','%s','%s','%s','%d','%s','%s' ]
    );
    $enquiry_id = $wpdb->insert_id;
    do_action( 'crs_enquiry_submitted', $enquiry_id, $business_id );
}