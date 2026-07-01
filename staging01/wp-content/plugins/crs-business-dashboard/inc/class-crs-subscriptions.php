<?php
/**
 * CRS Subscriptions — CPT-based implementation
 *
 * Subscription state  → crs_subscription post (post_meta)
 * Every payment       → crs_order post (post_meta)
 *
 * post_status mapping:
 *   sub_active / sub_past_due / sub_suspended / sub_cancelled
 *   ord_pending / ord_completed / ord_failed / ord_refunded
 *
 * @package CRS
 */
defined( 'ABSPATH' ) || exit;

class CRS_Subscriptions {

    /* ====================================================================
       INIT
       ==================================================================== */
    public static function init() {
        // Cron callbacks only — scheduling moved to CRS_Migrations::schedule_crons()
        add_action( 'crs_daily_renewal_check', [ __CLASS__, 'run_renewal_check' ] );
        add_action( 'crs_daily_grace_check',   [ __CLASS__, 'run_grace_check'   ] );
        add_action( 'crs_daily_boost_renewal_check', [ __CLASS__, 'run_boost_renewal_check' ] );
    }

    /* ====================================================================
       ACTIVATE — called after Stripe payment success
       Creates crs_subscription post + first crs_order post
       ==================================================================== */
    public static function activate_after_signup( $owner_id, $amount, $plan = 'monthly', $stripe_pi = '', $session_id = '' ) {

        $owner = bod_get_owner( $owner_id );
        if ( ! $owner ) return '';

        $now          = current_time( 'mysql' );
        $renewal_date = self::next_renewal_date( $plan );
        $invoice_num  = self::generate_invoice_number( $owner_id );

        // GST calculation from settings
        $gst_rate = (float) get_option( 'bod_listing_gst_rate', 10 );
        $gst      = round( $amount - ( $amount / ( 1 + $gst_rate / 100 ) ), 2 );
        $base     = round( $amount - $gst, 2 );

        // ── Create crs_subscription post ─────────────────────────────────
        $sub_id = wp_insert_post( [
            'post_type'   => 'crs_sub',
            'post_title'  => 'SUB-' . str_pad( $owner_id, 5, '0', STR_PAD_LEFT ) . '-' . date( 'Ymd' ),
            'post_status' => 'sub_active',
            'post_author' => (int) ( $owner->wp_user_id ?? 0 ),
        ] );

        if ( is_wp_error( $sub_id ) ) {
            error_log( '[CRS Sub] Failed to create subscription post for owner #' . $owner_id );
            return '';
        }
        // Look up the actual default signup plan post, so this subscription
        // links to a real crs_sub_plan post (needed for the admin dropdown
        // to show the correct selection — _sub_plan was only ever a text
        // label like "monthly" and never matched any plan post).
        $default_plan_id = (int) get_option( 'bod_default_signup_plan_id' );

        // Store all subscription info as post meta
        $sub_meta = [
            '_sub_owner_id'      => $owner_id,
            '_sub_plan_id'       => $default_plan_id,
            '_sub_plan'          => $plan,
            '_sub_base_price'    => get_option( 'bod_listing_base_price', 20 ),
            '_sub_gst_rate'      => $gst_rate,
            '_sub_gst_type'      => get_option( 'bod_listing_gst_type', 'exclude' ),
            '_sub_charge_amount' => $amount,
            '_sub_gst_amount'    => $gst,
            '_sub_start_date'    => $now,
            '_sub_renewal_date'  => $renewal_date,
            '_sub_cancelled_at'  => '',
            '_sub_grace_until'   => '',
            '_sub_stripe_cust'   => $owner->stripe_customer_id ?? '',
        ];
        foreach ( $sub_meta as $key => $val ) {
            update_post_meta( $sub_id, $key, $val );
        }

        // Link owner to this subscription
        bod_update_owner( $owner_id, [ 'crs_subscription_id' => $sub_id ] );

        // ── Create first crs_order post ───────────────────────────────────
        self::create_order( [
            'owner_id'        => $owner_id,
            'subscription_id' => $sub_id,
            'type'            => 'signup',
            'amount'          => $amount,
            'gst'             => $gst,
            'base_amount'     => $base,
            'stripe_pi'       => $stripe_pi,
            'stripe_session'  => $session_id,
            'invoice_num'     => $invoice_num,
        ] );

        error_log( "[CRS Sub] Subscription #{$sub_id} activated for owner #{$owner_id}. Renewal: {$renewal_date}" );

        return $invoice_num;
    }

    /* ====================================================================
       CREATE ORDER — one post per payment
       ==================================================================== */
    public static function create_order( $data ) {
        $invoice_num = $data['invoice_num'] ?? self::generate_invoice_number( $data['owner_id'] ?? 0 );
        $type_labels = [
            'signup'  => 'Signup Payment',
            'renewal' => 'Monthly Renewal',
            'boost'   => 'Listing Boost',
        ];

        $order_id = wp_insert_post( [
            'post_type'   => 'crs_order',
            'post_title'  => $invoice_num . ' — ' . ( $type_labels[ $data['type'] ] ?? ucfirst( $data['type'] ) ),
            'post_status' => 'ord_completed',
            'post_author' => 0,
            'post_date'   => current_time( 'mysql' ),
        ] );

        if ( is_wp_error( $order_id ) ) {
            error_log( '[CRS Order] Failed to create order post.' );
            return 0;
        }

        $order_meta = [
            '_order_owner_id'        => $data['owner_id']        ?? '',
            '_order_subscription_id' => $data['subscription_id'] ?? '',
            '_order_type'            => $data['type']            ?? 'signup',
            '_order_amount'          => $data['amount']          ?? 0,
            '_order_gst'             => $data['gst']             ?? 0,
            '_order_base_amount'     => $data['base_amount']     ?? 0,
            '_order_currency'        => 'AUD',
            '_order_stripe_pi'       => $data['stripe_pi']       ?? '',
            '_order_stripe_session'  => $data['stripe_session']  ?? '',
            '_order_invoice_num'     => $invoice_num,
            '_order_date'            => current_time( 'mysql' ),
        ];
        foreach ( $order_meta as $key => $val ) {
            update_post_meta( $order_id, $key, $val );
        }

        return $order_id;
    }

    /* ====================================================================
       GETTERS — fetch subscription / orders for an owner
       ==================================================================== */
    public static function get_subscription( $owner_id ) {
        // Try linked ID first
        $owner  = bod_get_owner( $owner_id );
        $sub_id = $owner ? (int) ( $owner->crs_subscription_id ?? 0 ) : 0;

        if ( $sub_id ) {
            $post = get_post( $sub_id );
            if ( $post && $post->post_type === 'crs_sub' ) return $post;
        }

        // Fallback: query by meta
        $posts = get_posts( [
            'post_type'      => 'crs_sub',
            'post_status'    => 'any',
            'posts_per_page' => 1,
            'meta_key'       => '_sub_owner_id',
            'meta_value'     => $owner_id,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ] );
        return $posts ? $posts[0] : null;
    }

    public static function get_orders( $owner_id, $limit = 50 ) {
        return get_posts( [
            'post_type'      => 'crs_order',
            'post_status'    => 'any',
            'posts_per_page' => $limit,
            'meta_key'       => '_order_owner_id',
            'meta_value'     => $owner_id,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ] );
    }

    public static function get_sub_meta( $sub_post, $key ) {
        if ( ! $sub_post ) return '';
        return get_post_meta( $sub_post->ID, $key, true );
    }

    /* ====================================================================
       RENEWAL — WP-Cron daily check
       ==================================================================== */
    /*public static function run_renewal_check() {
        $today = current_time( 'Y-m-d' );

        $subs = get_posts( [
            'post_type'      => 'crs_sub',
            'post_status'    => 'sub_active',
            'posts_per_page' => -1,
            'meta_query'     => [
                [
                    'key'     => '_sub_renewal_date',
                    'value'   => $today,
                    'compare' => '<=',
                    'type'    => 'DATE',
                ],
            ],
        ] );

        foreach ( $subs as $sub ) {
            $owner_id = (int) get_post_meta( $sub->ID, '_sub_owner_id', true );
            self::charge_renewal( $owner_id, $sub );
        }
    } */
    public static function run_renewal_check() {
        $today = current_time( 'Y-m-d' );
        error_log( "[CRS Cron] run_renewal_check() STARTED at " . current_time( 'mysql' ) . " (today={$today})" );

        $subs = get_posts( [
            'post_type'      => 'crs_sub',
            'post_status'    => 'sub_active',
            'posts_per_page' => -1,
            'meta_query'     => [
                [
                    'key'     => '_sub_renewal_date',
                    'value'   => $today,
                    'compare' => '<=',
                    'type'    => 'DATE',
                ],
            ],
        ] );

        error_log( "[CRS Cron] Found " . count( $subs ) . " subscription(s) due for renewal." );

        foreach ( $subs as $sub ) {
            $owner_id = (int) get_post_meta( $sub->ID, '_sub_owner_id', true );
            $renewal  = get_post_meta( $sub->ID, '_sub_renewal_date', true );
            error_log( "[CRS Cron] Processing sub #{$sub->ID} (owner #{$owner_id}), renewal_date={$renewal}" );
            self::charge_renewal( $owner_id, $sub );
        }

        error_log( "[CRS Cron] run_renewal_check() FINISHED." );
    }

    public static function charge_renewal( $owner_id, $sub_post = null ) {
        if ( ! $sub_post ) $sub_post = self::get_subscription( $owner_id );
        if ( ! $sub_post ) return;

        $owner = bod_get_owner( $owner_id );
        if ( ! $owner || empty( $owner->stripe_customer_id ) ) return;

        $amount       = (float) get_post_meta( $sub_post->ID, '_sub_charge_amount', true );
        $amount_cents = (int) round( $amount * 100 );
        if ( $amount_cents <= 0 ) return;

        if ( ! class_exists( '\Stripe\Stripe' ) ) {
            $autoload = defined( 'BOD_PLUGIN_DIR' ) ? BOD_PLUGIN_DIR . 'vendor/autoload.php' : '';
            if ( $autoload && file_exists( $autoload ) ) require_once $autoload;
        }

        $secret_key = defined( 'BOD_STRIPE_SECRET_KEY' ) ? BOD_STRIPE_SECRET_KEY : get_option( 'bod_stripe_secret_key', '' );
        if ( empty( $secret_key ) ) return;

        \Stripe\Stripe::setApiKey( $secret_key );

        try {
            $customer = \Stripe\Customer::retrieve( $owner->stripe_customer_id );
            $pm       = $customer->invoice_settings->default_payment_method ?? null;

            if ( ! $pm ) {
                $methods = \Stripe\PaymentMethod::all( [ 'customer' => $owner->stripe_customer_id, 'type' => 'card' ] );
                $pm      = ! empty( $methods->data ) ? $methods->data[0]->id : null;
            }

            if ( ! $pm ) {
                self::mark_past_due( $owner_id, $sub_post );
                return;
            }

            $plan      = get_post_meta( $sub_post->ID, '_sub_plan', true ) ?: 'monthly';
            $gst_rate  = (float) get_post_meta( $sub_post->ID, '_sub_gst_rate', true ) ?: 10;
            $gst       = round( $amount - ( $amount / ( 1 + $gst_rate / 100 ) ), 2 );
            $base      = round( $amount - $gst, 2 );

            $pi = \Stripe\PaymentIntent::create( [
                'amount'         => $amount_cents,
                'currency'       => 'aud',
                'customer'       => $owner->stripe_customer_id,
                'payment_method' => $pm,
                'confirm'        => true,
                'off_session'    => true,
                'description'    => 'Monthly renewal — ' . ( $owner->business_name ?: $owner->owner_name ),
                'metadata'       => [ 'owner_id' => $owner_id, 'source' => 'renewal' ],
            ] );

            if ( $pi->status === 'succeeded' ) {
                $invoice_num = self::generate_invoice_number( $owner_id );
                $next        = self::next_renewal_date( $plan );

                // Update subscription post
                update_post_meta( $sub_post->ID, '_sub_renewal_date', $next );
                update_post_meta( $sub_post->ID, '_sub_grace_until', '' );
                wp_update_post( [ 'ID' => $sub_post->ID, 'post_status' => 'sub_active' ] );

                // Create renewal order
                self::create_order( [
                    'owner_id'        => $owner_id,
                    'subscription_id' => $sub_post->ID,
                    'type'            => 'renewal',
                    'amount'          => $amount,
                    'gst'             => $gst,
                    'base_amount'     => $base,
                    'stripe_pi'       => $pi->id,
                    'invoice_num'     => $invoice_num,
                ] );

                if ( function_exists( 'bod_add_notification' ) ) {
                    bod_add_notification( $owner_id, 'billing', 'Renewal Successful', "Your subscription has been renewed. Invoice: {$invoice_num}" );
                }

                error_log( "[CRS Sub] Renewal success owner #{$owner_id}. Invoice: {$invoice_num}. Next: {$next}" );
            } else {
                self::mark_past_due( $owner_id, $sub_post );
            }

        } catch ( \Throwable $e ) {
            error_log( "[CRS Sub] Renewal error owner #{$owner_id}: " . $e->getMessage() );
            self::mark_past_due( $owner_id, $sub_post );
        }
    }

    /* ====================================================================
       PAST DUE / GRACE / SUSPEND / CANCEL
       ==================================================================== */
    public static function mark_past_due( $owner_id, $sub_post = null ) {
        if ( ! $sub_post ) $sub_post = self::get_subscription( $owner_id );
        if ( ! $sub_post ) return;

        $grace = date( 'Y-m-d H:i:s', strtotime( '+3 days' ) );
        update_post_meta( $sub_post->ID, '_sub_grace_until', $grace );
        wp_update_post( [ 'ID' => $sub_post->ID, 'post_status' => 'sub_past_due' ] );

        if ( function_exists( 'bod_add_notification' ) ) {
            bod_add_notification( $owner_id, 'billing', 'Payment Failed', 'Your renewal payment failed. We will retry in 3 days.' );
        }
    }

    public static function run_grace_check() {
        $now = current_time( 'mysql' );
        $subs = get_posts( [
            'post_type'      => 'crs_sub',
            'post_status'    => 'sub_past_due',
            'posts_per_page' => -1,
            'meta_query'     => [
                [ 'key' => '_sub_grace_until', 'value' => $now, 'compare' => '<=', 'type' => 'DATETIME' ],
            ],
        ] );

        foreach ( $subs as $sub ) {
            $owner_id = (int) get_post_meta( $sub->ID, '_sub_owner_id', true );
            self::charge_renewal( $owner_id, $sub );
            $refreshed = get_post( $sub->ID );
            if ( $refreshed && $refreshed->post_status !== 'sub_active' ) {
                self::suspend( $owner_id, $sub );
            }
        }
    }

    public static function suspend( $owner_id, $sub_post = null ) {
        if ( ! $sub_post ) $sub_post = self::get_subscription( $owner_id );
        if ( ! $sub_post ) return;
        wp_update_post( [ 'ID' => $sub_post->ID, 'post_status' => 'sub_suspended' ] );
        if ( function_exists( 'bod_add_notification' ) ) {
            bod_add_notification( $owner_id, 'billing', 'Subscription Suspended', 'Your subscription has been suspended due to failed payment.' );
        }
    }

    public static function cancel( $owner_id ) {
        $sub_post = self::get_subscription( $owner_id );
        if ( ! $sub_post ) return;
        update_post_meta( $sub_post->ID, '_sub_cancelled_at', current_time( 'mysql' ) );
        wp_update_post( [ 'ID' => $sub_post->ID, 'post_status' => 'sub_cancelled' ] );
        if ( function_exists( 'bod_add_notification' ) ) {
            bod_add_notification( $owner_id, 'billing', 'Subscription Cancelled', 'Your subscription has been cancelled.' );
        }
    }

    /* ====================================================================
       ADMIN EDIT
       ==================================================================== */
    public static function admin_update( $owner_id, $data ) {
        $sub_post = self::get_subscription( $owner_id );
        if ( ! $sub_post ) return;

        $meta_map = [
            'sub_status'       => null, // handled via post_status
            'sub_plan'         => '_sub_plan',
            'sub_amount'       => '_sub_charge_amount',
            'sub_renewal_date' => '_sub_renewal_date',
        ];

        foreach ( $meta_map as $key => $meta_key ) {
            if ( ! isset( $data[ $key ] ) ) continue;
            if ( $key === 'sub_status' ) {
                wp_update_post( [ 'ID' => $sub_post->ID, 'post_status' => 'sub_' . sanitize_key( $data[ $key ] ) ] );
            } elseif ( $meta_key ) {
                update_post_meta( $sub_post->ID, $meta_key, sanitize_text_field( $data[ $key ] ) );
            }
        }
    }

    /* ====================================================================
       HELPERS
       ==================================================================== */
    public static function generate_invoice_number( $owner_id ) {
        $count = (int) get_option( 'crs_invoice_seq', 0 ) + 1;
        update_option( 'crs_invoice_seq', $count );
        return 'INV-' . str_pad( $count, 5, '0', STR_PAD_LEFT );
    }

    public static function next_renewal_date( $plan = 'monthly' ) {
        return $plan === 'yearly'
            ? date( 'Y-m-d H:i:s', strtotime( '+1 year' ) )
            : date( 'Y-m-d H:i:s', strtotime( '+30 days' ) );
    }

    public static function days_until_renewal( $sub_post ) {
        if ( ! $sub_post ) return null;
        $renewal = get_post_meta( $sub_post->ID, '_sub_renewal_date', true );
        if ( ! $renewal ) return null;
        return (int) ceil( ( strtotime( $renewal ) - current_time( 'timestamp' ) ) / DAY_IN_SECONDS );
    }

    public static function status_badge( $post_status ) {
        $map = [
            'sub_active'    => [ '#16a34a', 'Active'    ],
            'sub_past_due'  => [ '#d97706', 'Past Due'  ],
            'sub_suspended' => [ '#dc2626', 'Suspended' ],
            'sub_cancelled' => [ '#6b7280', 'Cancelled' ],
        ];
        [ $color, $label ] = $map[ $post_status ] ?? [ '#6b7280', ucfirst( str_replace( 'sub_', '', $post_status ) ) ];
        return sprintf(
            '<span style="display:inline-block;padding:2px 10px;border-radius:20px;font-size:11px;font-weight:600;color:%1$s;background:%1$s1a;">%2$s</span>',
            $color, esc_html( $label )
        );
    }
    public static function run_boost_renewal_check() {
        global $wpdb;
        $today = current_time('mysql');

        $businesses = $wpdb->get_results($wpdb->prepare(
            "SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_active_boosts'"
        ));

        foreach ($businesses as $row) {
            $boosts = maybe_unserialize($row->meta_value);
            if (!is_array($boosts)) continue;

            foreach ($boosts as $boost_key => $data) {
                if (strtotime($data['renewal_date']) > strtotime($today)) continue; // not due yet
                self::charge_boost_renewal($row->post_id, $boost_key, $data);
            }
        }
    }

    public static function charge_boost_renewal($business_id, $boost_key, $data) {
        $owner_id = (int) $data['owner_id'];
        $owner    = bod_get_owner($owner_id);
        if (!$owner || empty($owner->stripe_customer_id)) return;

        $amount_cents = (int) round((float) $data['charge'] * 100);
        if ($amount_cents <= 0) return;

        if (!class_exists('\Stripe\Stripe')) {
            $autoload = defined('BOD_PLUGIN_DIR') ? BOD_PLUGIN_DIR . 'vendor/autoload.php' : '';
            if ($autoload && file_exists($autoload)) require_once $autoload;
        }
        \Stripe\Stripe::setApiKey(defined('BOD_STRIPE_SECRET_KEY') ? BOD_STRIPE_SECRET_KEY : get_option('bod_stripe_secret_key', ''));

        $boosts = get_post_meta($business_id, '_active_boosts', true);

        // If owner already cancelled auto-renew, just let it expire and clear it
        if (empty($data['auto_renew'])) {
            unset($boosts[$boost_key]);
            update_post_meta($business_id, '_active_boosts', $boosts);
            return;
        }

        try {
            $customer = \Stripe\Customer::retrieve($owner->stripe_customer_id);
            $pm = $customer->invoice_settings->default_payment_method ?? null;
            if (!$pm) {
                $methods = \Stripe\PaymentMethod::all(['customer' => $owner->stripe_customer_id, 'type' => 'card']);
                $pm = !empty($methods->data) ? $methods->data[0]->id : null;
            }
            if (!$pm) {
                error_log("[CRS Boost] No payment method for owner #{$owner_id}, boost {$boost_key} not renewed.");
                return; // boost just expires, no charge, no card on file
            }

            $pi = \Stripe\PaymentIntent::create([
                'amount'         => $amount_cents,
                'currency'       => 'aud',
                'customer'       => $owner->stripe_customer_id,
                'payment_method' => $pm,
                'confirm'        => true,
                'off_session'    => true,
                'description'    => ucfirst($boost_key) . ' boost renewal — ' . ($owner->business_name ?: $owner->owner_name),
                'metadata'       => ['owner_id' => $owner_id, 'business_id' => $business_id, 'source' => 'boost_renewal', 'boost' => $boost_key],
            ]);

            if ($pi->status === 'succeeded') {
                $duration = (int) get_post_meta($data['plan_id'], '_plan_duration', true) ?: 30;
                $boosts[$boost_key]['renewal_date'] = date('Y-m-d H:i:s', strtotime("+{$duration} days"));
                update_post_meta($business_id, '_active_boosts', $boosts);

                $invoice_num = self::generate_invoice_number($owner_id);
                self::create_order([
                    'owner_id'    => $owner_id,
                    'type'        => 'boost',
                    'amount'      => $data['charge'],
                    'gst'         => 0,
                    'base_amount' => $data['charge'],
                    'stripe_pi'   => $pi->id,
                    'invoice_num' => $invoice_num,
                ]);
                error_log("[CRS Boost] Renewed {$boost_key} for business #{$business_id}. Invoice: {$invoice_num}");
            } else {
                unset($boosts[$boost_key]); // failed charge — boost expires
                update_post_meta($business_id, '_active_boosts', $boosts);
                error_log("[CRS Boost] Renewal failed for {$boost_key}, business #{$business_id}.");
            }
        } catch (\Throwable $e) {
            error_log("[CRS Boost] Renewal error business #{$business_id}, boost {$boost_key}: " . $e->getMessage());
        }
    }
    public static function activate_boost_subscription( $owner_id, $business_id, $plan_id, $amount, $stripe_pi = '' ) {
    $owner = bod_get_owner( $owner_id );
    if ( ! $owner ) return '';

    $now          = current_time( 'mysql' );
    $renewal_date = date( 'Y-m-d H:i:s', strtotime( '+30 days' ) );
    $plan_title   = get_the_title( $plan_id );

    $sub_id = wp_insert_post( [
        'post_type'   => 'crs_sub',
        'post_title'  => 'SUB-' . str_pad( $owner_id, 5, '0', STR_PAD_LEFT ) . '-' . date( 'Ymd' ) . '-BOOST',
        'post_status' => 'sub_active',
        'post_author' => (int) ( $owner->wp_user_id ?? 0 ),
    ] );

    if ( is_wp_error( $sub_id ) ) {
        error_log( '[CRS Sub] Failed to create boost subscription post for owner #' . $owner_id );
        return '';
    }

    update_post_meta( $sub_id, '_sub_owner_id',      $owner_id );
    update_post_meta( $sub_id, '_sub_business_id',   $business_id );
    update_post_meta( $sub_id, '_sub_plan',          sanitize_title( $plan_title ) );
    update_post_meta( $sub_id, '_sub_plan_id',       $plan_id );
    update_post_meta( $sub_id, '_sub_type',          'boost' );
    update_post_meta( $sub_id, '_sub_charge_amount', $amount );
    update_post_meta( $sub_id, '_sub_renewal_date',  $renewal_date );
    update_post_meta( $sub_id, '_sub_started_at',    $now );
    update_post_meta( $sub_id, '_sub_stripe_pi',     $stripe_pi );

    error_log( "[CRS Sub] Boost subscription created: SUB post #{$sub_id} for owner #{$owner_id}, plan '{$plan_title}'." );
    return $sub_id;
}
}

CRS_Subscriptions::init();
