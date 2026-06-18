<?php
/**
 * CRS Business Dashboard – class-crs-acf-fields.php
 *
 * Registers all ACF field groups programmatically via acf_add_local_field_group().
 * Groups: Business Details, Contact & Location, Opening Hours, Subscription Tier.
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
            'type'         => 'textarea',
            'rows'         => 6,
            'instructions' => 'Shown on the business profile page.',
        ],
        [
            'key'          => 'field_crs_logo',
            'name'         => 'crs_logo',
            'label'        => 'Business Logo',
            'type'         => 'image',
            'return_format'=> 'id',
            'preview_size' => 'medium',
        ],
        [
            'key'          => 'field_crs_gallery',
            'name'         => 'crs_gallery',
            'label'        => 'Gallery (featured/premium only)',
            'type'         => 'gallery',
            'return_format'=> 'id',
            'preview_size' => 'medium',
            'instructions' => 'Upload up to 8 images. Only visible to featured & premium tiers.',
            'max'          => 8,
        ],
        [
            'key'          => 'field_crs_review_avg',
            'name'         => 'crs_review_avg',
            'label'        => 'Average Review Score',
            'type'         => 'number',
            'min'          => 0,
            'max'          => 5,
            'step'         => 0.1,
            'instructions' => 'Calculated automatically. Override only if importing data.',
        ],
        [
            'key'          => 'field_crs_review_count',
            'name'         => 'crs_review_count',
            'label'        => 'Review Count',
            'type'         => 'number',
            'min'          => 0,
        ],
        [
            'key'           => 'field_crs_service_modes',
            'name'          => 'crs_service_modes',
            'label'         => 'Service Modes',
            'type'          => 'checkbox',
            'choices'       => [
                'onsite'   => 'On-site (we come to you)',
                'remote'   => 'Remote Support',
                'pickup'   => 'Drop-off / Pick-up',
                'instore'  => 'In-store',
            ],
            'layout'        => 'horizontal',
            'return_format' => 'value',
        ],
        [
            'key'          => 'field_crs_abn',
            'name'         => 'crs_abn',
            'label'        => 'ABN',
            'type'         => 'text',
            'instructions' => 'Australian Business Number (11 digits, no spaces).',
            'maxlength'    => 11,
        ],
        [
            'key'          => 'field_crs_year_established',
            'name'         => 'crs_year_established',
            'label'        => 'Year Established',
            'type'         => 'number',
            'min'          => 1970,
            'max'          => 2100,
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
            'key'   => 'field_crs_address',
            'name'  => 'crs_address',
            'label' => 'Street Address',
            'type'  => 'text',
        ],
        [
            'key'   => 'field_crs_suburb_text',
            'name'  => 'crs_suburb_text',
            'label' => 'Suburb (display)',
            'type'  => 'text',
        ],
        [
            'key'   => 'field_crs_state_text',
            'name'  => 'crs_state_text',
            'label' => 'State (display)',
            'type'  => 'text',
        ],
        [
            'key'   => 'field_crs_postcode',
            'name'  => 'crs_postcode',
            'label' => 'Postcode',
            'type'  => 'text',
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
            'key'           => 'field_crs_social_links',
            'name'          => 'crs_social_links',
            'label'         => 'Social Media Links',
            'type'          => 'group',
            'layout'        => 'table',
            'sub_fields'    => [
                [ 'key' => 'field_crs_facebook',  'name' => 'facebook',  'label' => 'Facebook',  'type' => 'url' ],
                [ 'key' => 'field_crs_instagram', 'name' => 'instagram', 'label' => 'Instagram', 'type' => 'url' ],
                [ 'key' => 'field_crs_linkedin',  'name' => 'linkedin',  'label' => 'LinkedIn',  'type' => 'url' ],
                [ 'key' => 'field_crs_twitter',   'name' => 'twitter',   'label' => 'X/Twitter', 'type' => 'url' ],
            ],
        ],
    ],
] );

/* ======================================================================
   Group 3 – Opening Hours
   ==================================================================== */
acf_add_local_field_group( [
    'key'      => 'group_crs_opening_hours',
    'title'    => 'Opening Hours',
    'location' => [ [ [ 'param' => 'post_type', 'operator' => '==', 'value' => 'business' ] ] ],
    'position' => 'side',
    'style'    => 'default',
    'fields'   => [
        [
            'key'          => 'field_crs_hours_json',
            'name'         => 'crs_hours_json',
            'label'        => 'Hours (JSON)',
            'type'         => 'textarea',
            'rows'         => 10,
            'instructions' => 'JSON object: {"mon":{"open":"09:00","close":"17:00","closed":false}, ...}. Days: mon tue wed thu fri sat sun.',
        ],
    ],
] );

/* ======================================================================
   Group 4 – Subscription Tier
   ==================================================================== */
acf_add_local_field_group( [
    'key'      => 'group_crs_subscription',
    'title'    => 'Subscription',
    'location' => [ [ [ 'param' => 'post_type', 'operator' => '==', 'value' => 'business' ] ] ],
    'position' => 'side',
    'style'    => 'default',
    'fields'   => [
        [
            'key'           => 'field_crs_tier',
            'name'          => 'crs_tier',
            'label'         => 'Listing Tier',
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
            'key'          => 'field_crs_tier_expiry',
            'name'         => 'crs_tier_expiry',
            'label'        => 'Subscription Expiry',
            'type'         => 'date_picker',
            'display_format' => 'd/m/Y',
            'return_format'  => 'Y-m-d',
        ],
        [
            'key'          => 'field_crs_owner_user_id',
            'name'         => 'crs_owner_user_id',
            'label'        => 'Owner (WP User)',
            'type'         => 'user',
            'role'         => [ 'business_owner', 'administrator' ],
            'return_format'=> 'id',
        ],
        [
            'key'           => 'field_crs_verified',
            'name'          => 'crs_verified',
            'label'         => 'Verified Business',
            'type'          => 'true_false',
            'ui'            => 1,
            'default_value' => 0,
        ],
    ],
] );
