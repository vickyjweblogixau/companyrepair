<?php
/**
 * CRS Business Dashboard — class-crs-acf-fields.php
 *
 * Registers all ACF field groups for the Business CPT programmatically
 * using acf_add_local_field_group(). This means:
 *
 *  ✓ No manual ACF UI clicking — fields appear automatically
 *  ✓ Fields are version-controlled with the plugin
 *  ✓ Safe to deploy across staging and production
 *  ✓ Works with ACF Free and ACF Pro
 *
 * Groups registered:
 *  1. Business Details       (contact info, about, logo, gallery, hours)
 *  2. Service Options        (onsite, remote, pickup, same-day)
 *  3. Subscription           (tier, status, dates, payment ref)
 *  4. Review Stats           (avg rating, count — set by admin/cron)
 */

defined( 'ABSPATH' ) || exit;

class CRS_ACF_Fields {

    public static function init() {
        // acf/init fires after ACF is fully loaded
        add_action( 'acf/init', [ __CLASS__, 'register_all_field_groups' ] );
    }

    public static function register_all_field_groups() {
        if ( ! function_exists( 'acf_add_local_field_group' ) ) {
            return; // ACF not active — fields will be unavailable but plugin won't break
        }

        self::group_business_details();
        self::group_service_options();
        self::group_subscription();
        self::group_review_stats();
    }

    /* ======================================================================
       GROUP 1 — Business Details
       ==================================================================== */

    private static function group_business_details() {
        acf_add_local_field_group( [
            'key'      => 'group_crs_business_details',
            'title'    => 'Business Details',
            'location' => [ [ [
                'param'    => 'post_type',
                'operator' => '==',
                'value'    => 'business',
            ] ] ],
            'menu_order'            => 10,
            'position'              => 'normal',
            'style'                 => 'default',
            'label_placement'       => 'top',
            'instruction_placement' => 'label',
            'hide_on_screen'        => [ 'the_content', 'excerpt', 'discussion', 'comments', 'revisions', 'slug', 'author', 'format', 'page_attributes', 'tags' ],
            'fields' => [

                // ── Tagline ──────────────────────────────────────────────
                [
                    'key'          => 'field_business_tagline',
                    'label'        => 'Tagline',
                    'name'         => 'business_tagline',
                    'type'         => 'text',
                    'instructions' => 'One-line description shown in search results.',
                    'maxlength'    => 120,
                    'placeholder'  => 'e.g. Fast, affordable laptop repairs in Melbourne',
                ],

                // ── About ─────────────────────────────────────────────────
                [
                    'key'          => 'field_business_about',
                    'label'        => 'About',
                    'name'         => 'business_about',
                    'type'         => 'textarea',
                    'instructions' => 'Full description shown on the business profile page.',
                    'rows'         => 6,
                    'maxlength'    => 1500,
                ],

                // ── Contact ───────────────────────────────────────────────
                [
                    'key'         => 'field_business_phone',
                    'label'       => 'Phone',
                    'name'        => 'business_phone',
                    'type'        => 'text',
                    'placeholder' => '(03) 9000 0000',
                ],
                [
                    'key'         => 'field_business_email',
                    'label'       => 'Email',
                    'name'        => 'business_email',
                    'type'        => 'email',
                    'placeholder' => 'info@yourbusiness.com.au',
                ],
                [
                    'key'         => 'field_business_website',
                    'label'       => 'Website',
                    'name'        => 'business_website',
                    'type'        => 'url',
                    'placeholder' => 'https://yourbusiness.com.au',
                ],
                [
                    'key'         => 'field_business_address',
                    'label'       => 'Address',
                    'name'        => 'business_address',
                    'type'        => 'text',
                    'instructions'=> 'Street address displayed on the profile. Leave blank to hide.',
                    'placeholder' => '123 Collins St, Melbourne VIC 3000',
                ],

                // ── Logo ──────────────────────────────────────────────────
                [
                    'key'           => 'field_business_logo',
                    'label'         => 'Logo',
                    'name'          => 'business_logo',
                    'type'          => 'image',
                    'instructions'  => 'Recommended: 300×300px PNG with transparent background.',
                    'return_format' => 'id',
                    'preview_size'  => 'thumbnail',
                    'library'       => 'uploadedTo',
                    'min_width'     => 100,
                    'min_height'    => 100,
                    'max_size'      => 2,    // MB
                    'mime_types'    => 'jpg,jpeg,png,gif,webp',
                ],

                // ── Gallery ───────────────────────────────────────────────
                [
                    'key'           => 'field_business_gallery',
                    'label'         => 'Gallery Photos',
                    'name'          => 'business_gallery',
                    'type'          => 'gallery',
                    'instructions'  => 'Up to 4 photos of your workshop, team or work. Shown on Featured/Premium profiles.',
                    'return_format' => 'id',
                    'preview_size'  => 'medium',
                    'library'       => 'uploadedTo',
                    'max'           => 4,
                    'min_width'     => 400,
                    'max_size'      => 5,
                    'mime_types'    => 'jpg,jpeg,png,webp',
                ],

                // ── Opening Hours (repeater) ───────────────────────────────
                [
                    'key'          => 'field_business_hours',
                    'label'        => 'Opening Hours',
                    'name'         => 'business_hours',
                    'type'         => 'repeater',
                    'instructions' => 'Add one row per day. Leave blank rows for closed days.',
                    'min'          => 0,
                    'max'          => 7,
                    'layout'       => 'table',
                    'button_label' => 'Add Day',
                    'sub_fields'   => [
                        [
                            'key'     => 'field_hours_day',
                            'label'   => 'Day',
                            'name'    => 'day',
                            'type'    => 'select',
                            'choices' => [
                                'Monday'    => 'Monday',
                                'Tuesday'   => 'Tuesday',
                                'Wednesday' => 'Wednesday',
                                'Thursday'  => 'Thursday',
                                'Friday'    => 'Friday',
                                'Saturday'  => 'Saturday',
                                'Sunday'    => 'Sunday',
                            ],
                            'column_width' => 30,
                        ],
                        [
                            'key'          => 'field_hours_open',
                            'label'        => 'Opens',
                            'name'         => 'open',
                            'type'         => 'time_picker',
                            'display_format' => 'g:i a',
                            'return_format'  => 'H:i',
                            'column_width' => 30,
                        ],
                        [
                            'key'          => 'field_hours_close',
                            'label'        => 'Closes',
                            'name'         => 'close',
                            'type'         => 'time_picker',
                            'display_format' => 'g:i a',
                            'return_format'  => 'H:i',
                            'column_width' => 30,
                        ],
                        [
                            'key'          => 'field_hours_closed',
                            'label'        => 'Closed',
                            'name'         => 'closed',
                            'type'         => 'true_false',
                            'ui'           => 1,
                            'column_width' => 10,
                        ],
                    ],
                ],

                // ── Trust Signals ─────────────────────────────────────────
                [
                    'key'          => 'field_business_years',
                    'label'        => 'Years in Business',
                    'name'         => 'business_years',
                    'type'         => 'number',
                    'instructions' => 'Shown as "X+ Years in Business" on the profile.',
                    'min'          => 0,
                    'max'          => 100,
                    'step'         => 1,
                ],
                [
                    'key'   => 'field_business_verified',
                    'label' => 'Verified Business',
                    'name'  => 'business_verified',
                    'type'  => 'true_false',
                    'ui'    => 1,
                    'instructions' => 'Set by admin after verifying ABN/identity.',
                ],
                [
                    'key'   => 'field_business_top_rated',
                    'label' => 'Top Rated Badge',
                    'name'  => 'business_top_rated',
                    'type'  => 'true_false',
                    'ui'    => 1,
                    'instructions' => 'Set by admin. Requires 4.5+ avg rating with 20+ reviews.',
                ],

                // ── WP User Link ──────────────────────────────────────────
                [
                    'key'          => 'field_business_wp_user_id',
                    'label'        => 'Linked WP User',
                    'name'         => 'business_wp_user_id',
                    'type'         => 'user',
                    'instructions' => 'The business_owner account that manages this listing.',
                    'return_format'=> 'id',
                    'role'         => [ 'business_owner' ],
                ],

            ], // end fields
        ] );
    }

    /* ======================================================================
       GROUP 2 — Service Options
       ==================================================================== */

    private static function group_service_options() {
        acf_add_local_field_group( [
            'key'      => 'group_crs_service_options',
            'title'    => 'Service Options',
            'location' => [ [ [
                'param'    => 'post_type',
                'operator' => '==',
                'value'    => 'business',
            ] ] ],
            'menu_order' => 20,
            'position'   => 'normal',
            'fields'     => [
                [
                    'key'          => 'field_business_onsite',
                    'label'        => 'Onsite Service',
                    'name'         => 'business_onsite',
                    'type'         => 'true_false',
                    'ui'           => 1,
                    'instructions' => 'Technician travels to the customer.',
                ],
                [
                    'key'          => 'field_business_remote',
                    'label'        => 'Remote Support',
                    'name'         => 'business_remote',
                    'type'         => 'true_false',
                    'ui'           => 1,
                    'instructions' => 'Support delivered via internet (TeamViewer, AnyDesk, etc.).',
                ],
                [
                    'key'          => 'field_business_pickup',
                    'label'        => 'Pickup & Drop-off',
                    'name'         => 'business_pickup',
                    'type'         => 'true_false',
                    'ui'           => 1,
                    'instructions' => 'Business picks up and returns devices.',
                ],
                [
                    'key'          => 'field_business_same_day',
                    'label'        => 'Same-Day Service',
                    'name'         => 'business_same_day',
                    'type'         => 'true_false',
                    'ui'           => 1,
                    'instructions' => 'Same-day repairs available.',
                ],
            ],
        ] );
    }

    /* ======================================================================
       GROUP 3 — Subscription
       ==================================================================== */

    private static function group_subscription() {
        acf_add_local_field_group( [
            'key'      => 'group_crs_subscription',
            'title'    => 'Subscription',
            'location' => [ [ [
                'param'    => 'post_type',
                'operator' => '==',
                'value'    => 'business',
            ] ] ],
            'menu_order' => 30,
            'position'   => 'side',
            'fields'     => [
                [
                    'key'           => 'field_subscription_status',
                    'label'         => 'Status',
                    'name'          => 'subscription_status',
                    'type'          => 'select',
                    'instructions'  => 'Controls whether the listing is publicly visible.',
                    'choices'       => [
                        'pending'   => '⏳ Pending Review',
                        'active'    => '✅ Active',
                        'inactive'  => '⏸ Inactive',
                        'expired'   => '❌ Expired',
                    ],
                    'default_value' => 'pending',
                    'allow_null'    => 0,
                    'ui'            => 1,
                ],
                [
                    'key'           => 'field_subscription_tier',
                    'label'         => 'Tier',
                    'name'          => 'subscription_tier',
                    'type'          => 'select',
                    'instructions'  => 'Controls placement and features in search results.',
                    'choices'       => [
                        'basic'    => 'Basic (Free)',
                        'standard' => 'Standard',
                        'featured' => 'Featured',
                        'premium'  => 'Premium',
                    ],
                    'default_value' => 'basic',
                    'allow_null'    => 0,
                    'ui'            => 1,
                ],
                [
                    'key'             => 'field_subscription_start',
                    'label'           => 'Start Date',
                    'name'            => 'subscription_start',
                    'type'            => 'date_picker',
                    'display_format'  => 'd/m/Y',
                    'return_format'   => 'Y-m-d',
                    'first_day'       => 1,
                ],
                [
                    'key'             => 'field_subscription_expiry',
                    'label'           => 'Expiry Date',
                    'name'            => 'subscription_expiry',
                    'type'            => 'date_picker',
                    'instructions'    => 'Cron job auto-sets status to "expired" after this date.',
                    'display_format'  => 'd/m/Y',
                    'return_format'   => 'Y-m-d',
                    'first_day'       => 1,
                ],
                [
                    'key'         => 'field_subscription_payment_ref',
                    'label'       => 'Payment Reference',
                    'name'        => 'subscription_payment_ref',
                    'type'        => 'text',
                    'placeholder' => 'Stripe charge ID or invoice number',
                ],
            ],
        ] );
    }

    /* ======================================================================
       GROUP 4 — Review Stats (admin / cron managed)
       ==================================================================== */

    private static function group_review_stats() {
        acf_add_local_field_group( [
            'key'      => 'group_crs_review_stats',
            'title'    => 'Review Stats',
            'location' => [ [ [
                'param'    => 'post_type',
                'operator' => '==',
                'value'    => 'business',
            ] ] ],
            'menu_order'  => 40,
            'position'    => 'side',
            'description' => 'These values are updated automatically when reviews are approved.',
            'fields'      => [
                [
                    'key'           => 'field_review_avg',
                    'label'         => 'Average Rating',
                    'name'          => 'review_avg',
                    'type'          => 'number',
                    'instructions'  => 'Auto-calculated. Range: 0.0 – 5.0',
                    'min'           => 0,
                    'max'           => 5,
                    'step'          => 0.1,
                    'readonly'      => 0,
                ],
                [
                    'key'          => 'field_review_count',
                    'label'        => 'Review Count',
                    'name'         => 'review_count',
                    'type'         => 'number',
                    'instructions' => 'Total approved reviews. Auto-calculated.',
                    'min'          => 0,
                    'step'         => 1,
                    'readonly'     => 0,
                ],
            ],
        ] );
    }

} // end class CRS_ACF_Fields

CRS_ACF_Fields::init();
