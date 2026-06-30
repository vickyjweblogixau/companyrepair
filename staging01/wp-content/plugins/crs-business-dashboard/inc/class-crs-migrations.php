<?php
/**
 * CRS Migrations — all one-time DB and data migrations
 *
 * Rules:
 * - Called ONLY from register_activation_hook via crs_activate()
 * - Never hooked to admin_init or init
 * - Every method is idempotent (safe to call multiple times)
 *
 * @package CRS
 */
defined( 'ABSPATH' ) || exit;

class CRS_Migrations {

    /**
     * Run all migrations — called from crs_activate()
     */
    public static function run_all() {
        self::migrate_subscription_columns();
        self::migrate_enquiries_table();
        self::maybe_insert_default_plans();
        self::migrate_state_slugs();
        self::schedule_crons();
        self::seed_simple_subscription_plan();
    }

    /* ====================================================================
       1. Subscription columns — wp_business_owners table
       Moved from: class-crs-setup.php → admin_init
       ==================================================================== */
    public static function migrate_subscription_columns() {
        if ( get_option( 'crs_sub_columns_v2' ) ) return;

        global $wpdb;

        $table = defined( 'BOD_TABLE_OWNERS' )
            ? BOD_TABLE_OWNERS
            : $wpdb->prefix . 'business_owners';

        $existing = $wpdb->get_col( "DESCRIBE {$table}", 0 );
        if ( empty( $existing ) ) return;

        $upgrades = [
            'sub_status'          => "ALTER TABLE {$table} ADD COLUMN sub_status VARCHAR(20) NOT NULL DEFAULT 'active' AFTER stripe_subscription_id",
            'sub_plan'            => "ALTER TABLE {$table} ADD COLUMN sub_plan VARCHAR(20) NOT NULL DEFAULT 'monthly' AFTER sub_status",
            'sub_amount'          => "ALTER TABLE {$table} ADD COLUMN sub_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER sub_plan",
            'sub_start_date'      => "ALTER TABLE {$table} ADD COLUMN sub_start_date DATETIME DEFAULT NULL AFTER sub_amount",
            'sub_renewal_date'    => "ALTER TABLE {$table} ADD COLUMN sub_renewal_date DATETIME DEFAULT NULL AFTER sub_start_date",
            'sub_cancelled_at'    => "ALTER TABLE {$table} ADD COLUMN sub_cancelled_at DATETIME DEFAULT NULL AFTER sub_renewal_date",
            'sub_grace_until'     => "ALTER TABLE {$table} ADD COLUMN sub_grace_until DATETIME DEFAULT NULL AFTER sub_cancelled_at",
            'invoice_seq'         => "ALTER TABLE {$table} ADD COLUMN invoice_seq INT UNSIGNED NOT NULL DEFAULT 0 AFTER sub_grace_until",
            'crs_subscription_id' => "ALTER TABLE {$table} ADD COLUMN crs_subscription_id BIGINT(20) UNSIGNED DEFAULT NULL AFTER invoice_seq",
        ];

        foreach ( $upgrades as $col => $sql ) {
            if ( ! in_array( $col, $existing, true ) ) {
                $wpdb->query( $sql );
                if ( $wpdb->last_error ) {
                    error_log( '[CRS Migration] Error adding column ' . $col . ': ' . $wpdb->last_error );
                } else {
                    error_log( '[CRS Migration] Added column: ' . $col );
                }
            }
        }

        // Add invoice_number to payments table
        $payments_table = defined( 'BOD_TABLE_PAYMENTS' )
            ? BOD_TABLE_PAYMENTS
            : $wpdb->prefix . 'business_payments';

        $pay_cols = $wpdb->get_col( "DESCRIBE {$payments_table}", 0 );
        if ( ! empty( $pay_cols ) && ! in_array( 'invoice_number', $pay_cols, true ) ) {
            $wpdb->query( "ALTER TABLE {$payments_table} ADD COLUMN invoice_number VARCHAR(20) DEFAULT NULL AFTER payment_type" );
        }

        delete_option( 'crs_sub_columns_v1' );
        update_option( 'crs_sub_columns_v2', true );
        error_log( '[CRS] Subscription columns migration v2 complete.' );
    }

    /* ====================================================================
       2. Enquiries table — wp_crs_enquiries
       Consolidated v1.2 (class-crs-enquiries.php) +
                    v1.3 (class-crs-enquiry-form.php) into one
       Moved from: both files → admin_init
       ==================================================================== */
    public static function migrate_enquiries_table() {
        if ( version_compare( get_option( 'crs_enquiries_table_version', '1.0' ), '1.3', '>=' ) ) return;

        global $wpdb;
        $table    = $wpdb->prefix . 'crs_enquiries';
        $existing = $wpdb->get_col( "DESC {$table}", 0 );
        if ( empty( $existing ) ) return;

        // All columns from v1.2 + v1.3 combined — safe to run even if some exist
        $upgrades = [
            'subject'        => "ALTER TABLE {$table} ADD COLUMN subject VARCHAR(200) DEFAULT '' AFTER service",
            'finance_amount' => "ALTER TABLE {$table} ADD COLUMN finance_amount DECIMAL(10,2) DEFAULT NULL AFTER contact_pref",
            'contact_time'   => "ALTER TABLE {$table} ADD COLUMN contact_time VARCHAR(100) DEFAULT '' AFTER contact_pref",
            'postcode'       => "ALTER TABLE {$table} ADD COLUMN postcode VARCHAR(10) DEFAULT '' AFTER suburb",
            'region'         => "ALTER TABLE {$table} ADD COLUMN region VARCHAR(100) DEFAULT '' AFTER postcode",
            'state'          => "ALTER TABLE {$table} ADD COLUMN state VARCHAR(100) DEFAULT '' AFTER region",
        ];

        foreach ( $upgrades as $col => $sql ) {
            if ( ! in_array( $col, $existing, true ) ) {
                $wpdb->query( $sql );
                if ( $wpdb->last_error ) {
                    error_log( '[CRS Migration] Error adding enquiry column ' . $col . ': ' . $wpdb->last_error );
                }
            }
        }

        update_option( 'crs_enquiries_table_version', '1.3' );
        error_log( '[CRS] Enquiries table migration v1.3 complete.' );
    }

    /* ====================================================================
       3. Default subscription plans — 3 posts in crs_sub_plan CPT
       Moved from: class-crs-setup.php → admin_init
       ==================================================================== */
    public static function maybe_insert_default_plans() {
        if ( get_option( 'crs_default_plans_v1' ) ) return;

        // CPT must be registered — called after CRS_Setup::register_subscription_plan_cpt()
        if ( ! post_type_exists( 'crs_sub_plan' ) ) return;

        $tax_rate = (float) get_option( 'bod_listing_gst_rate', 10 );
        $tax_type = get_option( 'bod_listing_gst_type', 'exclude' );

        $plans = [
            [
                'title'        => 'Basic',
                'price'        => (float) get_option( 'bod_listing_base_price', 20 ),
                'tax_rate'     => $tax_rate,
                'tax_type'     => $tax_type,
                'duration'     => '30',
                'renewal_type' => 'auto',
                'features'     => "Standard listing\nSearch visibility\nEnquiry form",
            ],
            [
                'title'        => 'Featured',
                'price'        => (float) get_option( 'bod_boost_featured_display', 35 ),
                'tax_rate'     => $tax_rate,
                'tax_type'     => $tax_type,
                'duration'     => '30',
                'renewal_type' => 'auto',
                'features'     => "Featured badge\nPriority listing\nSearch visibility\nEnquiry form",
            ],
            [
                'title'        => 'Exclusive',
                'price'        => (float) get_option( 'bod_boost_exclusive_display', 50 ),
                'tax_rate'     => $tax_rate,
                'tax_type'     => $tax_type,
                'duration'     => '30',
                'renewal_type' => 'auto',
                'features'     => "Exclusive spotlight\nFeatured badge\nPriority listing\nHomepage visibility\nSearch visibility\nEnquiry form",
            ],
        ];

        foreach ( $plans as $plan ) {
            $post_id = wp_insert_post( [
                'post_type'   => 'crs_sub_plan',
                'post_title'  => $plan['title'],
                'post_status' => 'publish',
            ] );

            if ( is_wp_error( $post_id ) ) continue;

            $price  = $plan['price'];
            $rate   = $plan['tax_rate'];
            $type   = $plan['tax_type'];

            if ( $type === 'exclude' ) {
                $charge = round( $price * ( 1 + $rate / 100 ), 2 );
                $tax    = round( $charge - $price, 2 );
            } else {
                $charge = $price;
                $tax    = round( $price - ( $price / ( 1 + $rate / 100 ) ), 2 );
            }

            update_post_meta( $post_id, '_plan_price',         $price               );
            update_post_meta( $post_id, '_plan_tax_rate',      $rate                );
            update_post_meta( $post_id, '_plan_tax_type',      $type                );
            update_post_meta( $post_id, '_plan_duration',      $plan['duration']    );
            update_post_meta( $post_id, '_plan_renewal_type',  $plan['renewal_type'] );
            update_post_meta( $post_id, '_plan_features',      $plan['features']    );
            update_post_meta( $post_id, '_plan_status',        'active'             );
            update_post_meta( $post_id, '_plan_charge_amount', $charge              );
            update_post_meta( $post_id, '_plan_tax_amount',    $tax                 );

            error_log( '[CRS] Default plan created: ' . $plan['title'] . ' (ID: ' . $post_id . ')' );
        }

        update_option( 'crs_default_plans_v1', true );
    }

    /* ====================================================================
       4. Migrate au-state term slugs to full names
       Moved from: crs-business-dashboard.php → admin_init
       ==================================================================== */
    public static function migrate_state_slugs() {
        if ( get_option( 'crs_state_slugs_v2' ) ) return;

        $migrations = [
            'vic' => [ 'slug' => 'victoria',                    'abbr' => 'VIC' ],
            'nsw' => [ 'slug' => 'new-south-wales',              'abbr' => 'NSW' ],
            'qld' => [ 'slug' => 'queensland',                   'abbr' => 'QLD' ],
            'wa'  => [ 'slug' => 'western-australia',            'abbr' => 'WA'  ],
            'sa'  => [ 'slug' => 'south-australia',              'abbr' => 'SA'  ],
            'tas' => [ 'slug' => 'tasmania',                     'abbr' => 'TAS' ],
            'act' => [ 'slug' => 'australian-capital-territory', 'abbr' => 'ACT' ],
            'nt'  => [ 'slug' => 'northern-territory',           'abbr' => 'NT'  ],
        ];

        foreach ( $migrations as $old_slug => $data ) {
            $term = get_term_by( 'slug', $old_slug, 'au-state' );
            if ( $term ) {
                wp_update_term( $term->term_id, 'au-state', [ 'slug' => $data['slug'] ] );
                update_term_meta( $term->term_id, 'au_state_abbreviation', $data['abbr'] );
            }
        }

        update_option( 'crs_state_slugs_v2', true );
        error_log( '[CRS] State slug migration v2 complete.' );
    }

    /* ====================================================================
       5. Register cron schedules
       Moved from: class-crs-subscriptions.php → init (ran every request)
       ==================================================================== */
    public static function schedule_crons() {
        if ( ! wp_next_scheduled( 'crs_daily_renewal_check' ) ) {
            wp_schedule_event( strtotime( 'today 08:00:00' ), 'daily', 'crs_daily_renewal_check' );
        }
        if ( ! wp_next_scheduled( 'crs_daily_grace_check' ) ) {
            wp_schedule_event( strtotime( 'today 09:00:00' ), 'daily', 'crs_daily_grace_check' );
        }
        if ( ! wp_next_scheduled( 'crs_cleanup_enquiry_images' ) ) {
            wp_schedule_event( time(), 'daily', 'crs_cleanup_enquiry_images' );
        }
    }

    /* ====================================================================
   6. Seed simple subscription plan as crs_sub_plan post
   Plan: Simple Subscription — $20 base, 10% GST exclude = $22 charged
   Runs once on activation — idempotent
   ==================================================================== */
    public static function seed_simple_subscription_plan() {
        if ( get_option( 'bod_simple_plan_seeded_v1' ) ) return;

        // CPT must be registered first
        if ( ! post_type_exists( 'crs_sub_plan' ) ) return;

        // Avoid duplicate if already exists with same title
        $existing = get_posts( [
            'post_type'   => 'crs_sub_plan',
            'post_status' => 'publish',
            'title'       => 'Simple Subscription',
            'numberposts' => 1,
        ] );
        if ( ! empty( $existing ) ) {
            update_option( 'bod_simple_plan_seeded_v1', true );
            return;
        }

        // Base price = $20, GST type = exclude, rate = 10%
        // Charge = $20 + ($20 x 10%) = $22.00 total
        $price    = 20.00;
        $rate     = 10.00;
        $tax_type = 'exclude';
        $charge   = round( $price * ( 1 + $rate / 100 ), 2 ); // 22.00
        $tax      = round( $charge - $price, 2 );              // 2.00

        $post_id = wp_insert_post( [
            'post_type'   => 'crs_sub_plan',
            'post_title'  => 'Simple Subscription',
            'post_status' => 'publish',
        ] );

        if ( is_wp_error( $post_id ) ) {
            error_log( '[CRS Migration] Failed to create Simple Subscription plan: ' . $post_id->get_error_message() );
            return;
        }

        update_post_meta( $post_id, '_plan_price',         $price    );
        update_post_meta( $post_id, '_plan_tax_rate',      $rate     );
        update_post_meta( $post_id, '_plan_tax_type',      $tax_type );
        update_post_meta( $post_id, '_plan_duration',      '30'      );
        update_post_meta( $post_id, '_plan_renewal_type',  'auto'    );
        update_post_meta( $post_id, '_plan_charge_amount', $charge   );
        update_post_meta( $post_id, '_plan_tax_amount',    $tax      );
        update_post_meta( $post_id, '_plan_status',        'active'  );
        update_post_meta( $post_id, '_plan_features',
            "Business listing on CRS\nSearch visibility\nEnquiry form\nMonthly billing\nCancel anytime"
        );

        update_option( 'bod_simple_plan_seeded_v1', true );
        error_log( '[CRS Migration] Simple Subscription plan created. ID: ' . $post_id . ' | Charge: $' . $charge );
    }

}