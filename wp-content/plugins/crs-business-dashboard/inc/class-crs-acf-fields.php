<?php
/**
 * CRS Business Dashboard – class-crs-acf-fields.php
 *
 * Registers all ACF field groups programmatically via acf_add_local_field_group().
 * Groups: Business Details, Contact & Location, Opening Hours, Subscription.
 *
 * Address sub-fields: Street Address, State (au-state taxonomy), Region (au-region taxonomy),
 *                     Suburb (au-suburb taxonomy), Postcode.
 * Opening Hours: ACF Repeater with Day Name, Open Time, Close Time, Leave (or) Holidays.
 *
 * @package CRS
 * @author  Priya
 */
defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'acf_add_local_field_group' ) ) {
    return; // ACF not active
}

/* ======================================================================
   Group 1 – Business Details
   ==================================================================== */
acf_add_local_field_group( [
    'key'      => 'group_crs_business_details',
    'title'    => 'Business Details',
    'location' => [ [ [ 'param' => 'post_type', 'operator' => '==', 'value' => 'business' ] ] ],
    'position' => 'normal',
    'style'    => 'default',
    'fields'   => [
        [
            'key'          => 'field_crs_tagline',
            'name'         => 'crs_tagline',
            'label'        => 'Tagline',
            'type'         => 'text',
            'instructions' => 'Short description shown on listing cards (max 100 chars).',
            'maxlength'    => 100,
        ],
        [
            'key'          => 'field_crs_description',
            'name'         => 'crs_description',
            'label'        => 'Full Description',
            'type'         => 'wysiwyg',
            'tabs'         => 'all',
            'toolbar'      => 'full',
            'media_upload' => 1,
            'instructions' => 'Shown on the business profile page.',
        ],
        [
            'key'           => 'field_crs_logo',
            'name'          => 'crs_logo',
            'label'         => 'Business Logo',
            'type'          => 'image',
            'return_format' => 'id',
            'preview_size'  => 'medium',
        ],
        /*[
            'key'           => 'field_crs_gallery',
            'name'          => 'crs_gallery',
            'label'         => 'Gallery (featured/premium only)',
            'type'          => 'gallery',
            'return_format' => 'id',
            'preview_size'  => 'medium',
            'instructions'  => 'Upload up to 8 images. Only visible to featured & premium tiers.',
            'max'           => 8,
        ], */
        [
            'key'           => 'field_crs_gallery_1',
            'name'          => 'crs_gallery_1',
            'label'         => 'Gallery Photo 1',
            'type'          => 'image',
            'return_format' => 'id',
            'preview_size'  => 'medium',
            'instructions'  => 'Only visible to featured & premium tiers.',
        ],
        [
            'key'           => 'field_crs_gallery_2',
            'name'          => 'crs_gallery_2',
            'label'         => 'Gallery Photo 2',
            'type'          => 'image',
            'return_format' => 'id',
            'preview_size'  => 'medium',
        ],
        [
            'key'           => 'field_crs_gallery_3',
            'name'          => 'crs_gallery_3',
            'label'         => 'Gallery Photo 3',
            'type'          => 'image',
            'return_format' => 'id',
            'preview_size'  => 'medium',
        ],
        [
            'key'           => 'field_crs_gallery_4',
            'name'          => 'crs_gallery_4',
            'label'         => 'Gallery Photo 4',
            'type'          => 'image',
            'return_format' => 'id',
            'preview_size'  => 'medium',
        ],
        [
            'key'          => 'field_crs_google_review_url',
            'name'         => 'crs_google_review_url',
            'label'        => 'Google Reviews Link',
            'type'         => 'url',
            'instructions' => 'Paste the Google Maps / Google Business Profile reviews URL. Displayed as a "View on Google" link on the business profile page.',
        ],
        [
            'key'          => 'field_crs_review_avg',
            'name'         => 'crs_review_avg',
            'label'        => 'Average Review Score',
            'type'         => 'number',
            'min'          => 0,
            'max'          => 5,
            'step'         => 0.1,
            'instructions' => 'e.g. 4.8 — enter manually from Google.',
        ],
        [
            'key'          => 'field_crs_review_count',
            'name'         => 'crs_review_count',
            'label'        => 'Review Count',
            'type'         => 'number',
            'min'          => 0,
            'instructions' => 'e.g. 120 — enter manually from Google.',
        ],
        [
            'key'           => 'field_crs_service_modes',
            'name'          => 'crs_service_modes',
            'label'         => 'Service Modes',
            'type'          => 'checkbox',
            'choices'       => [
                'onsite'  => 'On-site (we come to you)',
                'remote'  => 'Remote Support',
                'pickup'  => 'Drop-off / Pick-up',
                'instore' => 'In-store',
            ],
            'layout'        => 'horizontal',
            'return_format' => 'value',
        ],
        [
            'key'          => 'field_crs_abn',
            'name'         => 'crs_abn',
            'label'        => 'ABN Number',
            'type'         => 'text',
            'instructions' => 'Australian Business Number (11 digits, no spaces).',
            'maxlength'    => 11,
        ],
        [
            'key'   => 'field_crs_year_established',
            'name'  => 'crs_year_established',
            'label' => 'Year Established',
            'type'  => 'number',
            'min'   => 1970,
            'max'   => 2100,
        ],
        [
            'key'           => 'field_crs_verified',
            'name'          => 'crs_verified',
            'label'         => 'Verified Business',
            'type'          => 'true_false',
            'ui'            => 1,
            'default_value' => 0,
        ],
        [
            'key'           => 'field_crs_top_rated',
            'name'          => 'crs_top_rated',
            'label'         => 'Rating',
            'type'          => 'select',
            'choices'       => [
                ''  => '— Not Rated —',
                '1' => '⭐ 1 Star',
                '2' => '⭐⭐ 2 Stars',
                '3' => '⭐⭐⭐ 3 Stars',
                '4' => '⭐⭐⭐⭐ 4 Stars',
                '5' => '⭐⭐⭐⭐⭐ 5 Stars',
            ],
            'default_value' => '',
            'allow_null'    => 1,
            'return_format' => 'value',
            'instructions'  => 'Admin-assigned star rating displayed as a trust badge on the profile.',
        ],
        [
            'key'           => 'field_crs_same_day',
            'name'          => 'crs_same_day',
            'label'         => 'Same Day Service',
            'type'          => 'true_false',
            'ui'            => 1,
            'default_value' => 0,
        ],
    ],
] );

/* ======================================================================
   Group 2 – Contact & Location
   ==================================================================== */
acf_add_local_field_group( [
    'key'      => 'group_crs_contact_location',
    'title'    => 'Contact & Location',
    'location' => [ [ [ 'param' => 'post_type', 'operator' => '==', 'value' => 'business' ] ] ],
    'position' => 'normal',
    'style'    => 'default',
    'fields'   => [
        [
            'key'   => 'field_crs_phone',
            'name'  => 'crs_phone',
            'label' => 'Phone',
            'type'  => 'text',
        ],
        [
            'key'   => 'field_crs_email',
            'name'  => 'crs_email',
            'label' => 'Business Email',
            'type'  => 'email',
        ],
        [
            'key'   => 'field_crs_website',
            'name'  => 'crs_website',
            'label' => 'Website URL',
            'type'  => 'url',
        ],
        [
            'key'          => 'field_crs_address',
            'name'         => 'crs_address',
            'label'        => 'Address',
            'type'         => 'text',
            'instructions' => 'Street address (e.g. 12 Example St).',
        ],
        [
            'key'           => 'field_crs_state_tax',
            'name'          => 'crs_state_tax',
            'label'         => 'State',
            'type'          => 'taxonomy',
            'taxonomy'      => 'au-state',
            'field_type'    => 'select',
            'allow_null'    => 1,
            'add_term'      => 0,
            'save_terms'    => 1,
            'load_terms'    => 1,
            'return_format' => 'id',
            'instructions'  => 'Select from existing State taxonomy terms.',
        ],
        [
            'key'           => 'field_crs_region_tax',
            'name'          => 'crs_region_tax',
            'label'         => 'Region',
            'type'          => 'taxonomy',
            'taxonomy'      => 'au-region',
            'field_type'    => 'select',
            'allow_null'    => 1,
            'add_term'      => 0,
            'save_terms'    => 1,
            'load_terms'    => 1,
            'return_format' => 'id',
            'instructions'  => 'Select from existing Region taxonomy terms.',
        ],
        [
            'key'           => 'field_crs_suburb_tax',
            'name'          => 'crs_suburb_tax',
            'label'         => 'Suburb',
            'type'          => 'taxonomy',
            'taxonomy'      => 'au-suburb',
            'field_type'    => 'select',
            'allow_null'    => 1,
            'add_term'      => 0,
            'save_terms'    => 1,
            'load_terms'    => 1,
            'return_format' => 'id',
            'instructions'  => 'Select from existing Suburb taxonomy terms.',
        ],
        [
            'key'       => 'field_crs_postcode',
            'name'      => 'crs_postcode',
            'label'     => 'Postcode',
            'type'      => 'text',
            'maxlength' => 4,
        ],
        [
            'key'   => 'field_crs_lat',
            'name'  => 'crs_lat',
            'label' => 'Latitude',
            'type'  => 'number',
            'step'  => 'any',
        ],
        [
            'key'   => 'field_crs_lng',
            'name'  => 'crs_lng',
            'label' => 'Longitude',
            'type'  => 'number',
            'step'  => 'any',
        ],
        [
            'key'        => 'field_crs_social_links',
            'name'       => 'crs_social_links',
            'label'      => 'Social Media Links',
            'type'       => 'group',
            'layout'     => 'table',
            'sub_fields' => [
                [ 'key' => 'field_crs_facebook',  'name' => 'facebook',  'label' => 'Facebook',  'type' => 'url' ],
                [ 'key' => 'field_crs_instagram', 'name' => 'instagram', 'label' => 'Instagram', 'type' => 'url' ],
                [ 'key' => 'field_crs_linkedin',  'name' => 'linkedin',  'label' => 'LinkedIn',  'type' => 'url' ],
                [ 'key' => 'field_crs_twitter',   'name' => 'twitter',   'label' => 'X/Twitter', 'type' => 'url' ],
            ],
        ],
    ],
] );
/* ======================================================================
   Group 3 – Opening Hours  (ACF FREE compatible — flat fields, no repeater)
   ==================================================================== */
function crs_build_hours_fields_for_day( $day_key, $day_label ) {
    return [
        [
            'key'   => 'field_crs_hours_' . $day_key . '_open',
            'name'  => 'crs_hours_' . $day_key . '_open',
            'label' => $day_label . ' — Open',
            'type'  => 'time_picker',
            'display_format' => 'g:i a',
            'return_format'  => 'H:i',
            'wrapper' => [ 'width' => 33 ],
        ],
        [
            'key'   => 'field_crs_hours_' . $day_key . '_close',
            'name'  => 'crs_hours_' . $day_key . '_close',
            'label' => $day_label . ' — Close',
            'type'  => 'time_picker',
            'display_format' => 'g:i a',
            'return_format'  => 'H:i',
            'wrapper' => [ 'width' => 33 ],
        ],
        [
            'key'           => 'field_crs_hours_' . $day_key . '_closed',
            'name'          => 'crs_hours_' . $day_key . '_closed',
            'label'         => 'Closed?',
            'type'          => 'true_false',
            'ui'            => 1,
            'default_value' => 0,
            'wrapper'       => [ 'width' => 34 ],
        ],
    ];
}

$crs_days = [
    'monday'    => 'Monday',
    'tuesday'   => 'Tuesday',
    'wednesday' => 'Wednesday',
    'thursday'  => 'Thursday',
    'friday'    => 'Friday',
    'saturday'  => 'Saturday',
    'sunday'    => 'Sunday',
];

$crs_hours_fields = [];
foreach ( $crs_days as $key => $label ) {
    $crs_hours_fields = array_merge( $crs_hours_fields, crs_build_hours_fields_for_day( $key, $label ) );
}

acf_add_local_field_group( [
    'key'      => 'group_crs_opening_hours',
    'title'    => 'Opening Hours',
    'location' => [ [ [ 'param' => 'post_type', 'operator' => '==', 'value' => 'business' ] ] ],
    'position' => 'normal',
    'style'    => 'default',
    'fields'   => $crs_hours_fields,
] );
/* ======================================================================
   Group 3 – Opening Hours  (ACF Repeater, table layout)
   Columns: Day Name | Open Time | Close Time | Leave (or) Holidays
   ==================================================================== */
/* acf_add_local_field_group( [
    'key'      => 'group_crs_opening_hours',
    'title'    => 'Opening Hours',
    'location' => [ [ [ 'param' => 'post_type', 'operator' => '==', 'value' => 'business' ] ] ],
    'position' => 'normal',
    'style'    => 'default',
    'fields'   => [
        [
            'key'          => 'field_crs_business_hours',
            'name'         => 'crs_business_hours',
            'label'        => 'Business Hours',
            'type'         => 'repeater',
            'layout'       => 'table',
            'button_label' => 'Add Row',
            'min'          => 0,
            'max'          => 14,
            'sub_fields'   => [
                [
                    'key'           => 'field_crs_hours_day',
                    'name'          => 'day_name',
                    'label'         => 'Day Name',
                    'type'          => 'select',
                    'choices'       => [
                        'Monday'    => 'Monday',
                        'Tuesday'   => 'Tuesday',
                        'Wednesday' => 'Wednesday',
                        'Thursday'  => 'Thursday',
                        'Friday'    => 'Friday',
                        'Saturday'  => 'Saturday',
                        'Sunday'    => 'Sunday',
                    ],
                    'allow_null'    => 0,
                    'return_format' => 'value',
                ],
                [
                    'key'            => 'field_crs_hours_open',
                    'name'           => 'open_time',
                    'label'          => 'Open Time',
                    'type'           => 'time_picker',
                    'display_format' => 'g:i a',
                    'return_format'  => 'H:i',
                ],
                [
                    'key'            => 'field_crs_hours_close',
                    'name'           => 'close_time',
                    'label'          => 'Close Time',
                    'type'           => 'time_picker',
                    'display_format' => 'g:i a',
                    'return_format'  => 'H:i',
                ],
                [
                    'key'           => 'field_crs_hours_holiday',
                    'name'          => 'leave_or_holiday',
                    'label'         => 'Leave (or) Holidays',
                    'type'          => 'true_false',
                    'ui'            => 1,
                    'default_value' => 0,
                    'instructions'  => 'Tick if closed or public holiday.',
                ],
            ],
        ],
    ],
] );*/

/* ======================================================================
   Group 4 – Subscription
   ==================================================================== */
acf_add_local_field_group( [
    'key'      => 'group_crs_subscription',
    'title'    => 'Subscription',
    'location' => [ [ [ 'param' => 'post_type', 'operator' => '==', 'value' => 'business' ] ] ],
    'position' => 'side',
    'style'    => 'default',
    'fields'   => [
        [
            'key'           => 'field_crs_sub_status',
            'name'          => 'crs_subscription_status',
            'label'         => 'Subscription Status',
            'type'          => 'select',
            'choices'       => [
                'active'   => 'Active',
                'inactive' => 'Inactive',
                'pending'  => 'Pending',
                'expired'  => 'Expired',
            ],
            'default_value' => 'pending',
            'return_format' => 'value',
            'allow_null'    => false,
        ],
        [
            'key'           => 'field_crs_tier',
            'name'          => 'crs_tier',
            'label'         => 'Subscription Tier',
            'type'          => 'select',
            'choices'       => [
                'basic'    => 'Basic (free)',
                'standard' => 'Standard',
                'featured' => 'Featured',
                'premium'  => 'Premium',
            ],
            'default_value' => 'basic',
            'return_format' => 'value',
            'allow_null'    => false,
        ],
        [
            'key'            => 'field_crs_sub_start',
            'name'           => 'crs_subscription_start',
            'label'          => 'Subscription Start',
            'type'           => 'date_picker',
            'display_format' => 'd/m/Y',
            'return_format'  => 'Y-m-d',
        ],
        [
            'key'            => 'field_crs_tier_expiry',
            'name'           => 'crs_tier_expiry',
            'label'          => 'Subscription Expiry',
            'type'           => 'date_picker',
            'display_format' => 'd/m/Y',
            'return_format'  => 'Y-m-d',
        ],
        [
            'key'          => 'field_crs_payment_ref',
            'name'         => 'crs_payment_ref',
            'label'        => 'Payment Reference',
            'type'         => 'text',
            'instructions' => 'Stripe or invoice reference number.',
        ],
        [
            'key'           => 'field_crs_owner_user_id',
            'name'          => 'crs_owner_user_id',
            'label'         => 'Owner (WP User)',
            'type'          => 'user',
            'role'          => [ 'business_owner', 'administrator' ],
            'return_format' => 'id',
        ],
    ],
] );
