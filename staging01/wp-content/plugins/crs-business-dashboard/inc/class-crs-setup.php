<?php
/**
 * CRS Business Dashboard – class-crs-setup.php
 *
 * Registers: CPT (menu_name = Services), 6 Taxonomies,
 *            Business Owner role, Enquiries DB table, Default terms.
 *
 * @package CRS
 * @author  Priya
 */
defined( 'ABSPATH' ) || exit;

class CRS_Setup {

    public static function init() {
        // These 4 MUST run every request — WP rebuilds CPT/taxonomy registry each load
        add_action( 'init',      [ __CLASS__, 'register_cpt' ],                   5 );
        add_action( 'init',      [ __CLASS__, 'register_taxonomies' ],            5 );
        add_action( 'init',      [ __CLASS__, 'register_subscription_plan_cpt' ], 5 );
        add_action( 'init',      [ __CLASS__, 'register_subscription_cpts' ],     5 );
        // These run on user action — correct hooks
        add_action( 'save_post', [ __CLASS__, 'save_plan_meta' ] );
        add_filter( 'wp_count_posts', [ __CLASS__, 'fix_cpt_counts' ], 10, 2 );
        // migrate_subscription_columns and maybe_insert_default_plans
        // REMOVED from here — moved to register_activation_hook in crs-business-dashboard.php
    }

    /* ====================================================================
       Subscription columns migration — runs once via admin_init
       Adds sub_status, sub_plan, sub_amount, sub_start_date,
       sub_renewal_date, sub_cancelled_at, sub_grace_until, invoice_seq
       to wp_business_owners without touching existing data.
       ==================================================================== */
    public static function migrate_subscription_columns() {
        // v2 — added crs_subscription_id column
        if ( get_option( 'crs_sub_columns_v2' ) ) return;

        global $wpdb;

        $table = defined( 'BOD_TABLE_OWNERS' )
            ? BOD_TABLE_OWNERS
            : $wpdb->prefix . 'business_owners';

        $existing = $wpdb->get_col( "DESCRIBE {$table}", 0 );
        if ( empty( $existing ) ) return;

        $upgrades = [
            'sub_status'           => "ALTER TABLE {$table} ADD COLUMN sub_status VARCHAR(20) NOT NULL DEFAULT 'active' AFTER stripe_subscription_id",
            'sub_plan'             => "ALTER TABLE {$table} ADD COLUMN sub_plan VARCHAR(20) NOT NULL DEFAULT 'monthly' AFTER sub_status",
            'sub_amount'           => "ALTER TABLE {$table} ADD COLUMN sub_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER sub_plan",
            'sub_start_date'       => "ALTER TABLE {$table} ADD COLUMN sub_start_date DATETIME DEFAULT NULL AFTER sub_amount",
            'sub_renewal_date'     => "ALTER TABLE {$table} ADD COLUMN sub_renewal_date DATETIME DEFAULT NULL AFTER sub_start_date",
            'sub_cancelled_at'     => "ALTER TABLE {$table} ADD COLUMN sub_cancelled_at DATETIME DEFAULT NULL AFTER sub_renewal_date",
            'sub_grace_until'      => "ALTER TABLE {$table} ADD COLUMN sub_grace_until DATETIME DEFAULT NULL AFTER sub_cancelled_at",
            'invoice_seq'          => "ALTER TABLE {$table} ADD COLUMN invoice_seq INT UNSIGNED NOT NULL DEFAULT 0 AFTER sub_grace_until",
            'crs_subscription_id'  => "ALTER TABLE {$table} ADD COLUMN crs_subscription_id BIGINT(20) UNSIGNED DEFAULT NULL AFTER invoice_seq",
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

        // Clear old version flag, set new
        delete_option( 'crs_sub_columns_v1' );
        update_option( 'crs_sub_columns_v2', true );
        error_log( '[CRS] Subscription columns migration v2 complete.' );
    }

    /* ====================================================================
       1.  CPT  (post_type key = "business" | admin label = "Services")
       ================================================================== */
    public static function register_cpt() {
        register_post_type( 'business', [
            'labels' => [
                'name'               => __( 'Businesses',              'crs' ),
                'singular_name'      => __( 'Business',               'crs' ),
                'menu_name'          => __( 'CRS Business',             'crs' ),
                'add_new'            => __( 'Add New',                'crs' ),
                'add_new_item'       => __( 'Add New Business',       'crs' ),
                'edit_item'          => __( 'Edit Business',          'crs' ),
                'view_item'          => __( 'View Business',          'crs' ),
                'search_items'       => __( 'Search Businesses',      'crs' ),
                'not_found'          => __( 'No businesses found.',   'crs' ),
                'not_found_in_trash' => __( 'No businesses in trash.','crs' ),
                'all_items'          => __( 'All Businesses',         'crs' ),
                'archives'           => __( 'Business Archives',      'crs' ),
            ],
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'show_in_rest'       => false,
            'has_archive'        => 'services',
            'rewrite'            => [ 'slug' => 'business', 'with_front' => false ],
            'supports'           => [ 'title', 'thumbnail' ],
            'capability_type'    => 'post',
            'menu_icon'          => 'dashicons-store',
            'menu_position'      => 5,
        ] );
    }

    /* ====================================================================
       2a. CPT — crs_subscription_plan
           Admin creates plans here. 3 defaults auto-inserted on activation.
       ==================================================================== */
    public static function register_subscription_plan_cpt() {
        register_post_type( 'crs_sub_plan', [
            'labels' => [
                'name'          => __( 'Subscription Plans',  'crs' ),
                'singular_name' => __( 'Subscription Plan',   'crs' ),
                'menu_name'     => __( 'Subscription Plans',  'crs' ),
                'add_new_item'  => __( 'Add New Plan',        'crs' ),
                'edit_item'     => __( 'Edit Plan',           'crs' ),
                'all_items'     => __( 'All Plans',           'crs' ),
                'not_found'     => __( 'No plans found.',     'crs' ),
            ],
            'public'               => false,
            'publicly_queryable'   => false,
            'show_ui'              => true,
            'show_in_menu'         => 'edit.php?post_type=business',
            'show_in_rest'         => false,
            'supports'             => [ 'title' ],
            'register_meta_box_cb' => [ __CLASS__, 'plan_meta_boxes' ],
        ] );
    }

    /* ── Plan meta box ───────────────────────────────────────────────── */
    public static function plan_meta_boxes() {
        add_meta_box(
            'crs_plan_details',
            'Plan Configuration',
            [ __CLASS__, 'render_plan_meta_box' ],
            'crs_sub_plan',
            'normal',
            'high'
        );
    }

    public static function render_plan_meta_box( $post ) {
        wp_nonce_field( 'crs_plan_meta', 'crs_plan_meta_nonce' );

        $price        = get_post_meta( $post->ID, '_plan_price',        true );
        $tax_rate     = get_post_meta( $post->ID, '_plan_tax_rate',     true );
        $tax_type     = get_post_meta( $post->ID, '_plan_tax_type',     true ) ?: 'exclude';
        $duration     = get_post_meta( $post->ID, '_plan_duration',     true ) ?: '30';
        $renewal_type = get_post_meta( $post->ID, '_plan_renewal_type', true ) ?: 'auto';
        $features     = get_post_meta( $post->ID, '_plan_features',     true ) ?: '';
        $plan_status  = get_post_meta( $post->ID, '_plan_status',       true ) ?: 'active';

        // Calculate preview
        $p    = (float) $price;
        $r    = (float) $tax_rate;
        $charge = 0; $tax = 0;
        if ( $p && $r ) {
            if ( $tax_type === 'exclude' ) {
                $charge = round( $p * ( 1 + $r / 100 ), 2 );
                $tax    = round( $charge - $p, 2 );
            } else {
                $charge = $p;
                $tax    = round( $p - ( $p / ( 1 + $r / 100 ) ), 2 );
            }
        }
        ?>
        <style>
        .crs-plan-grid { display:grid; grid-template-columns:200px 1fr; border:1px solid #e5e5e5; border-radius:6px; overflow:hidden; }
        .crs-plan-grid .crs-pl { padding:10px 14px; font-weight:600; font-size:13px; background:#f9f9f9; border-bottom:1px solid #eee; display:flex; align-items:center; }
        .crs-plan-grid .crs-pf { padding:8px 14px; border-bottom:1px solid #eee; display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
        .crs-plan-grid input[type=number], .crs-plan-grid select, .crs-plan-grid textarea { border:1px solid #ddd; border-radius:4px; padding:5px 8px; font-size:13px; }
        #crs-plan-preview { background:#f0f7ff; border:1px solid #c3d9f5; border-radius:6px; padding:12px 16px; margin-top:12px; font-size:13px; }
        </style>

        <div class="crs-plan-grid">

            <!-- Price -->
            <div class="crs-pl">Base Price (AUD)</div>
            <div class="crs-pf">
                <input type="number" name="_plan_price" id="plan_price"
                       value="<?php echo esc_attr( (string) $price ); ?>"
                       step="0.01" min="0" style="width:120px;">
                <span style="color:#666;font-size:12px;">AUD</span>
            </div>

            <!-- Tax Rate -->
            <div class="crs-pl">Tax Rate (%)</div>
            <div class="crs-pf">
                <input type="number" name="_plan_tax_rate" id="plan_tax_rate"
                       value="<?php echo esc_attr( (string) $tax_rate ); ?>"
                       step="0.01" min="0" max="100" style="width:80px;">
                <span style="color:#666;font-size:12px;">% &nbsp;(Australia GST = 10%)</span>
            </div>

            <!-- Tax Type -->
            <div class="crs-pl">Tax Type</div>
            <div class="crs-pf" style="flex-direction:column; align-items:flex-start; gap:6px;">
                <label>
                    <input type="radio" name="_plan_tax_type" value="exclude" id="plan_tax_exclude"
                        <?php checked( $tax_type, 'exclude' ); ?>>
                    <strong>Exclude</strong>
                    <span style="color:#666;font-size:12px;"> — GST added on top (e.g. $20 + 10% = $22)</span>
                </label>
                <label>
                    <input type="radio" name="_plan_tax_type" value="include" id="plan_tax_include"
                        <?php checked( $tax_type, 'include' ); ?>>
                    <strong>Include</strong>
                    <span style="color:#666;font-size:12px;"> — GST inside price (e.g. $20 incl. GST → user pays $20)</span>
                </label>
            </div>

            <!-- Duration -->
            <div class="crs-pl">Duration</div>
            <div class="crs-pf">
                <select name="_plan_duration" style="width:150px;">
                    <?php foreach ( [ '30' => '30 days (Monthly)', '90' => '90 days (Quarterly)', '180' => '180 days (Half-yearly)', '365' => '365 days (Yearly)' ] as $val => $label ) : ?>
                        <option value="<?php echo $val; ?>" <?php selected( $duration, $val ); ?>><?php echo esc_html( $label ); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Renewal Type -->
            <div class="crs-pl">Renewal Type</div>
            <div class="crs-pf" style="flex-direction:column; align-items:flex-start; gap:6px;">
                <label>
                    <input type="radio" name="_plan_renewal_type" value="auto"
                        <?php checked( $renewal_type, 'auto' ); ?>>
                    <strong>Auto Renewal</strong>
                    <span style="color:#666;font-size:12px;"> — WP Cron charges saved card automatically</span>
                </label>
                <label>
                    <input type="radio" name="_plan_renewal_type" value="manual"
                        <?php checked( $renewal_type, 'manual' ); ?>>
                    <strong>Manual Renewal</strong>
                    <span style="color:#666;font-size:12px;"> — Email reminder sent, owner pays via dashboard</span>
                </label>
            </div>

            <!-- Features -->
            <div class="crs-pl">Features</div>
            <div class="crs-pf">
                <textarea name="_plan_features" rows="4" style="width:100%;max-width:500px;"
                          placeholder="One feature per line e.g.&#10;Standard listing&#10;Featured badge&#10;Homepage visibility"><?php echo esc_textarea( $features ); ?></textarea>
            </div>

            <!-- Status -->
            <div class="crs-pl">Plan Status</div>
            <div class="crs-pf">
                <select name="_plan_status">
                    <option value="active"   <?php selected( $plan_status, 'active' );   ?>>Active</option>
                    <option value="inactive" <?php selected( $plan_status, 'inactive' ); ?>>Inactive</option>
                </select>
            </div>

        </div>

        <!-- Live preview -->
        <div id="crs-plan-preview">
            <strong>Charge preview:</strong>
            &nbsp; Total: <strong>$<span id="pp-charge"><?php echo $charge ? number_format( $charge, 2 ) : '—'; ?></span> AUD</strong>
            &nbsp;|&nbsp; Tax: $<span id="pp-tax"><?php echo $tax ? number_format( $tax, 2 ) : '—'; ?></span>
            &nbsp;|&nbsp; Base excl. tax: $<span id="pp-base"><?php echo ( $charge && $tax ) ? number_format( $charge - $tax, 2 ) : '—'; ?></span>
        </div>

        <script>
        (function(){
            function calc(){
                var p = parseFloat(document.getElementById('plan_price').value)||0;
                var r = parseFloat(document.getElementById('plan_tax_rate').value)||0;
                var t = document.querySelector('input[name="_plan_tax_type"]:checked');
                var type = t ? t.value : 'exclude';
                var charge, tax, base;
                if(type==='exclude'){ charge=Math.round(p*(1+r/100)*100)/100; tax=Math.round((charge-p)*100)/100; base=p; }
                else { charge=p; tax=Math.round((p-p/(1+r/100))*100)/100; base=Math.round((p-tax)*100)/100; }
                document.getElementById('pp-charge').textContent = charge.toFixed(2);
                document.getElementById('pp-tax').textContent    = tax.toFixed(2);
                document.getElementById('pp-base').textContent   = base.toFixed(2);
            }
            document.getElementById('plan_price').addEventListener('input',calc);
            document.getElementById('plan_tax_rate').addEventListener('input',calc);
            document.querySelectorAll('input[name="_plan_tax_type"]').forEach(function(r){r.addEventListener('change',calc);});
            calc();
        })();
        </script>
        <?php
    }

    /* ── Save plan meta ──────────────────────────────────────────────── */
    public static function save_plan_meta( $post_id ) {
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
        if ( ! isset( $_POST['crs_plan_meta_nonce'] ) ) return;
        if ( ! wp_verify_nonce( $_POST['crs_plan_meta_nonce'], 'crs_plan_meta' ) ) return;
        if ( get_post_type( $post_id ) !== 'crs_sub_plan' ) return;

        $fields = [
            '_plan_price'        => 'float',
            '_plan_tax_rate'     => 'float',
            '_plan_tax_type'     => 'text',
            '_plan_duration'     => 'text',
            '_plan_renewal_type' => 'text',
            '_plan_features'     => 'textarea',
            '_plan_status'       => 'text',
        ];

        foreach ( $fields as $field => $type ) {
            if ( ! isset( $_POST[ $field ] ) ) continue;
            if ( $type === 'textarea' ) {
                update_post_meta( $post_id, $field, sanitize_textarea_field( $_POST[ $field ] ) );
            } elseif ( $type === 'float' ) {
                update_post_meta( $post_id, $field, (float) $_POST[ $field ] );
            } else {
                update_post_meta( $post_id, $field, sanitize_text_field( $_POST[ $field ] ) );
            }
        }

        // Auto-calculate and store charge + tax amounts
        $price    = (float) ( $_POST['_plan_price']    ?? 0 );
        $rate     = (float) ( $_POST['_plan_tax_rate'] ?? 0 );
        $tax_type = sanitize_text_field( $_POST['_plan_tax_type'] ?? 'exclude' );

        if ( $tax_type === 'exclude' ) {
            $charge = round( $price * ( 1 + $rate / 100 ), 2 );
            $tax    = round( $charge - $price, 2 );
        } else {
            $charge = $price;
            $tax    = round( $price - ( $price / ( 1 + $rate / 100 ) ), 2 );
        }
        update_post_meta( $post_id, '_plan_charge_amount', $charge );
        update_post_meta( $post_id, '_plan_tax_amount',    $tax    );
    }

    /* ── Auto-insert 3 default plans on first admin load ─────────────── */
    public static function maybe_insert_default_plans() {
        if ( get_option( 'crs_default_plans_v1' ) ) return;

        // Wait until CPT is registered
        if ( ! post_type_exists( 'crs_sub_plan' ) ) return;

        $tax_rate = (float) get_option( 'bod_listing_gst_rate',  10 );
        $tax_type = get_option( 'bod_listing_gst_type', 'exclude' );

        $plans = [
            [
                'title'        => 'Basic',
                'price'        => (float) get_option( 'bod_listing_base_price', 20 ),
                'tax_rate'     => $tax_rate,
                'tax_type'     => $tax_type,
                'duration'     => '30',
                'renewal_type' => 'auto',
                'features'     => "Standard listing
Search visibility
Enquiry form",
            ],
            [
                'title'        => 'Featured',
                'price'        => (float) get_option( 'bod_boost_featured_display', 35 ),
                'tax_rate'     => $tax_rate,
                'tax_type'     => $tax_type,
                'duration'     => '30',
                'renewal_type' => 'auto',
                'features'     => "Featured badge
Priority listing
Search visibility
Enquiry form",
            ],
            [
                'title'        => 'Exclusive',
                'price'        => (float) get_option( 'bod_boost_exclusive_display', 50 ),
                'tax_rate'     => $tax_rate,
                'tax_type'     => $tax_type,
                'duration'     => '30',
                'renewal_type' => 'auto',
                'features'     => "Exclusive spotlight
Featured badge
Priority listing
Homepage visibility
Search visibility
Enquiry form",
            ],
        ];

        foreach ( $plans as $plan ) {
            $post_id = wp_insert_post( [
                'post_type'   => 'crs_sub_plan',
                'post_title'  => $plan['title'],
                'post_status' => 'publish',
            ] );

            if ( is_wp_error( $post_id ) ) continue;

            $price = $plan['price'];
            $rate  = $plan['tax_rate'];
            $type  = $plan['tax_type'];

            if ( $type === 'exclude' ) {
                $charge = round( $price * ( 1 + $rate / 100 ), 2 );
                $tax    = round( $charge - $price, 2 );
            } else {
                $charge = $price;
                $tax    = round( $price - ( $price / ( 1 + $rate / 100 ) ), 2 );
            }

            update_post_meta( $post_id, '_plan_price',         $price              );
            update_post_meta( $post_id, '_plan_tax_rate',      $rate               );
            update_post_meta( $post_id, '_plan_tax_type',      $type               );
            update_post_meta( $post_id, '_plan_duration',      $plan['duration']   );
            update_post_meta( $post_id, '_plan_renewal_type',  $plan['renewal_type'] );
            update_post_meta( $post_id, '_plan_features',      $plan['features']   );
            update_post_meta( $post_id, '_plan_status',        'active'            );
            update_post_meta( $post_id, '_plan_charge_amount', $charge             );
            update_post_meta( $post_id, '_plan_tax_amount',    $tax                );

            error_log( '[CRS] Default plan created: ' . $plan['title'] . ' (ID: ' . $post_id . ')' );
        }

        update_option( 'crs_default_plans_v1', true );
    }

    /* ====================================================================
       2b. CPT — crs_subscription + crs_order
       ==================================================================== */
    public static function register_subscription_cpts() {

        register_post_type( 'crs_sub', [
            'labels' => [
                'name'          => __( 'Subscriptions',     'crs' ),
                'singular_name' => __( 'Subscription',      'crs' ),
                'menu_name'     => __( 'Subscriptions',     'crs' ),
                'edit_item'     => __( 'Edit Subscription', 'crs' ),
                'all_items'     => __( 'All Subscriptions', 'crs' ),
                'not_found'     => __( 'No subscriptions.', 'crs' ),
            ],
            'public'               => false,
            'publicly_queryable'   => false,
            'show_ui'              => true,
            'show_in_menu'         => 'edit.php?post_type=business',
            'show_in_rest'         => false,
            'supports'             => [ 'title', 'custom-fields' ],
            'register_meta_box_cb' => [ __CLASS__, 'subscription_meta_boxes' ],
        ] );

        register_post_type( 'crs_order', [
            'labels' => [
                'name'          => __( 'Orders',     'crs' ),
                'singular_name' => __( 'Order',      'crs' ),
                'menu_name'     => __( 'Orders',     'crs' ),
                'edit_item'     => __( 'Edit Order', 'crs' ),
                'all_items'     => __( 'All Orders', 'crs' ),
                'not_found'     => __( 'No orders.', 'crs' ),
            ],
            'public'               => false,
            'publicly_queryable'   => false,
            'show_ui'              => true,
            'show_in_menu'         => 'edit.php?post_type=business',
            'show_in_rest'         => false,
            'supports'             => [ 'title', 'custom-fields' ],
            'register_meta_box_cb' => [ __CLASS__, 'order_meta_boxes' ],
        ] );

        foreach ( [
            'sub_active' => 'Active', 'sub_past_due' => 'Past Due',
            'sub_suspended' => 'Suspended', 'sub_cancelled' => 'Cancelled',
        ] as $status => $label ) {
            register_post_status( $status, [
                'label'                     => $label,
                'public'                    => false,
                'show_in_admin_all_list'    => true,
                'show_in_admin_status_list' => true,
                'label_count'               => _n_noop( $label . ' <span class="count">(%s)</span>', $label . ' <span class="count">(%s)</span>', 'crs' ),
            ] );
        }

        foreach ( [
            'ord_pending' => 'Pending', 'ord_completed' => 'Completed',
            'ord_failed' => 'Failed',   'ord_refunded' => 'Refunded',
        ] as $status => $label ) {
            register_post_status( $status, [
                'label'                     => $label,
                'public'                    => false,
                'show_in_admin_all_list'    => true,
                'show_in_admin_status_list' => true,
                'label_count'               => _n_noop( $label . ' <span class="count">(%s)</span>', $label . ' <span class="count">(%s)</span>', 'crs' ),
            ] );
        }

        add_action( 'save_post', [ __CLASS__, 'save_cpt_meta' ] );
    }

    public static function subscription_meta_boxes() {
        add_meta_box( 'crs_sub_details', 'Subscription Details',
            [ __CLASS__, 'render_subscription_meta_box' ], 'crs_sub', 'normal', 'high' );
    }

    public static function render_subscription_meta_box( $post ) {
        wp_nonce_field( 'crs_sub_meta', 'crs_sub_meta_nonce' );

        $owner_id       = get_post_meta( $post->ID, '_sub_owner_id',       true );
        $plan_id        = get_post_meta( $post->ID, '_sub_plan_id',        true );
        $start_date     = get_post_meta( $post->ID, '_sub_start_date',     true );
        $renewal_date   = get_post_meta( $post->ID, '_sub_renewal_date',   true );
        $renewal_type   = get_post_meta( $post->ID, '_sub_renewal_type',   true );
        $charge_amount  = get_post_meta( $post->ID, '_sub_charge_amount',  true );
        $tax_amount     = get_post_meta( $post->ID, '_sub_tax_amount',     true );
        $stripe_cust    = get_post_meta( $post->ID, '_sub_stripe_cust',    true );
        $cancelled_at   = get_post_meta( $post->ID, '_sub_cancelled_at',   true );
        $grace_until    = get_post_meta( $post->ID, '_sub_grace_until',    true );

        // All business owners — no approval filter, show all
        global $wpdb;
        $table  = defined( 'BOD_TABLE_OWNERS' ) ? BOD_TABLE_OWNERS : $wpdb->prefix . 'business_owners';
        $owners = $wpdb->get_results( "SELECT id, owner_name, business_name, owner_email FROM {$table} ORDER BY owner_name ASC" );

        // Active subscription plans
        $plans = get_posts( [
            'post_type'      => 'crs_sub_plan',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
            'meta_query'     => [ [ 'key' => '_plan_status', 'value' => 'active' ] ],
        ] );
        ?>
        <style>
        .crs-sub-grid { display:grid; grid-template-columns:200px 1fr; border:1px solid #e5e5e5; border-radius:6px; overflow:hidden; margin-bottom:12px; }
        .crs-sub-grid .crs-sl { padding:10px 14px; font-weight:600; font-size:13px; background:#f9f9f9; border-bottom:1px solid #eee; display:flex; align-items:center; }
        .crs-sub-grid .crs-sf { padding:8px 14px; border-bottom:1px solid #eee; display:flex; align-items:center; gap:8px; font-size:13px; }
        .crs-sub-grid select, .crs-sub-grid input[type=text], .crs-sub-grid input[type=date] { border:1px solid #ddd; border-radius:4px; padding:5px 8px; font-size:13px; }
        .crs-plan-info { background:#f0f7ff; border:1px solid #c3d9f5; border-radius:6px; padding:10px 14px; font-size:12px; line-height:1.8; margin-top:4px; }
        </style>

        <div class="crs-sub-grid">

            <!-- Business Owner dropdown -->
            <div class="crs-sl">Business Owner <span style="color:red;margin-left:4px;">*</span></div>
            <div class="crs-sf" style="flex-direction:column;align-items:flex-start;">
                <select name="_sub_owner_id" style="min-width:300px;" id="crs_sub_owner">
                    <option value="">— Select Business Owner —</option>
                    <?php foreach ( $owners as $o ) :
                        $label = '#' . (int) $o->id . ' — ' . esc_html( $o->owner_name );
                        if ( $o->business_name ) $label .= ' (' . esc_html( $o->business_name ) . ')';
                        $label .= ' · ' . esc_html( $o->owner_email );
                    ?>
                        <option value="<?php echo (int) $o->id; ?>" <?php selected( (string) $owner_id, (string) $o->id ); ?>>
                            <?php echo esc_html( $label ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if ( empty( $owners ) ) : ?>
                    <span style="color:#dc2626;font-size:12px;">No business owners found. Create one first.</span>
                <?php endif; ?>
            </div>

            <!-- Subscription Plan dropdown -->
            <div class="crs-sl">Subscription Plan <span style="color:red;margin-left:4px;">*</span></div>
            <div class="crs-sf" style="flex-direction:column;align-items:flex-start;">
                <select name="_sub_plan_id" style="min-width:250px;" id="crs_sub_plan_id" onchange="crsLoadPlanInfo(this.value)">
                    <option value="">— Select Plan —</option>
                    <?php foreach ( $plans as $p ) :
                        $p_charge = get_post_meta( $p->ID, '_plan_charge_amount', true );
                        $p_dur    = get_post_meta( $p->ID, '_plan_duration',      true );
                    ?>
                        <option value="<?php echo (int) $p->ID; ?>"
                                data-charge="<?php echo esc_attr( (string) $p_charge ); ?>"
                                data-duration="<?php echo esc_attr( (string) $p_dur ); ?>"
                                data-tax="<?php echo esc_attr( (string) get_post_meta( $p->ID, '_plan_tax_amount', true ) ); ?>"
                                data-renewal="<?php echo esc_attr( (string) get_post_meta( $p->ID, '_plan_renewal_type', true ) ); ?>"
                                data-features="<?php echo esc_attr( (string) get_post_meta( $p->ID, '_plan_features', true ) ); ?>"
                                <?php selected( (string) $plan_id, (string) $p->ID ); ?>>
                            <?php echo esc_html( $p->post_title ); ?>
                            — $<?php echo number_format( (float) $p_charge, 2 ); ?> AUD / <?php echo esc_html( $p_dur ); ?> days
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if ( empty( $plans ) ) : ?>
                    <span style="color:#dc2626;font-size:12px;">No active plans. <a href="<?php echo admin_url( 'post-new.php?post_type=crs_subscription_plan' ); ?>">Create a plan first</a>.</span>
                <?php endif; ?>
                <div id="crs-plan-info-box" class="crs-plan-info" style="<?php echo $plan_id ? '' : 'display:none;'; ?>min-width:300px;">
                    <?php if ( $plan_id ) :
                        $sel_plan = get_post( $plan_id );
                        if ( $sel_plan ) :
                            $f = get_post_meta( $plan_id, '_plan_features', true );
                    ?>
                        <strong><?php echo esc_html( $sel_plan->post_title ); ?></strong><br>
                        Charge: <strong>$<?php echo number_format( (float) get_post_meta( $plan_id, '_plan_charge_amount', true ), 2 ); ?> AUD</strong>
                        &nbsp;|&nbsp; Tax: $<?php echo number_format( (float) get_post_meta( $plan_id, '_plan_tax_amount', true ), 2 ); ?>
                        &nbsp;|&nbsp; Duration: <?php echo esc_html( (string) get_post_meta( $plan_id, '_plan_duration', true ) ); ?> days
                        &nbsp;|&nbsp; Renewal: <?php echo esc_html( ucfirst( (string) get_post_meta( $plan_id, '_plan_renewal_type', true ) ) ); ?><br>
                        <?php if ( $f ) echo '<em>' . nl2br( esc_html( $f ) ) . '</em>'; ?>
                    <?php endif; endif; ?>
                </div>
            </div>

            <!-- Start Date -->
            <div class="crs-sl">Start Date</div>
            <div class="crs-sf">
                <input type="date" name="_sub_start_date" id="crs_sub_start"
                       value="<?php echo esc_attr( $start_date ? date( 'Y-m-d', strtotime( $start_date ) ) : date( 'Y-m-d' ) ); ?>"
                       onchange="crsCalcRenewal()">
                <span style="color:#888;font-size:12px;">Renewal date auto-calculated on plan select</span>
            </div>

            <!-- Renewal Date (auto-calculated, editable) -->
            <div class="crs-sl">Next Renewal Date</div>
            <div class="crs-sf">
                <input type="date" name="_sub_renewal_date" id="crs_sub_renewal"
                       value="<?php echo esc_attr( $renewal_date ? date( 'Y-m-d', strtotime( $renewal_date ) ) : '' ); ?>">
                <span style="color:#888;font-size:12px;">Auto-filled from plan duration. Editable.</span>
            </div>

            <!-- Stripe Customer ID (read info) -->
            <?php if ( $stripe_cust ) : ?>
            <div class="crs-sl">Stripe Customer</div>
            <div class="crs-sf">
                <code style="font-size:12px;"><?php echo esc_html( $stripe_cust ); ?></code>
                <a href="https://dashboard.stripe.com/customers/<?php echo esc_attr( $stripe_cust ); ?>"
                   target="_blank" style="font-size:12px;">View in Stripe ↗</a>
            </div>
            <?php endif; ?>

            <!-- System info (read-only) -->
            <?php if ( $charge_amount ) : ?>
            <div class="crs-sl">Charge / Tax</div>
            <div class="crs-sf">
                <strong>$<?php echo number_format( (float) $charge_amount, 2 ); ?> AUD</strong>
                <span style="color:#666;font-size:12px;">incl. tax $<?php echo number_format( (float) $tax_amount, 2 ); ?></span>
            </div>
            <?php endif; ?>

            <?php if ( $cancelled_at ) : ?>
            <div class="crs-sl">Cancelled At</div>
            <div class="crs-sf" style="color:#dc2626;"><?php echo esc_html( $cancelled_at ); ?></div>
            <?php endif; ?>

            <?php if ( $grace_until ) : ?>
            <div class="crs-sl">Grace Until</div>
            <div class="crs-sf" style="color:#d97706;"><?php echo esc_html( $grace_until ); ?></div>
            <?php endif; ?>

        </div>

        <script>
        function crsLoadPlanInfo(planId) {
            var sel    = document.getElementById('crs_sub_plan_id');
            var opt    = sel.options[sel.selectedIndex];
            var box    = document.getElementById('crs-plan-info-box');
            if (!planId || !opt.value) { box.style.display='none'; return; }
            var charge   = opt.getAttribute('data-charge')   || '—';
            var tax      = opt.getAttribute('data-tax')      || '—';
            var dur      = opt.getAttribute('data-duration') || '30';
            var renewal  = opt.getAttribute('data-renewal')  || 'auto';
            var features = opt.getAttribute('data-features') || '';
            box.innerHTML = '<strong>' + opt.text.split('—')[0].trim() + '</strong><br>'
                + 'Charge: <strong>$' + parseFloat(charge).toFixed(2) + ' AUD</strong>'
                + ' &nbsp;|&nbsp; Tax: $' + parseFloat(tax).toFixed(2)
                + ' &nbsp;|&nbsp; Duration: ' + dur + ' days'
                + ' &nbsp;|&nbsp; Renewal: ' + renewal.charAt(0).toUpperCase() + renewal.slice(1)
                + (features ? '<br><em>' + features.replace(/\n/g,'<br>') + '</em>' : '');
            box.style.display = 'block';
            // Auto-calc renewal date
            crsCalcRenewal(parseInt(dur));
        }
        function crsCalcRenewal(durOverride) {
            var sel    = document.getElementById('crs_sub_plan_id');
            var opt    = sel.options[sel.selectedIndex];
            var dur    = durOverride || parseInt(opt.getAttribute('data-duration') || '30');
            var start  = document.getElementById('crs_sub_start').value;
            if (!start || !dur) return;
            var d = new Date(start);
            d.setDate(d.getDate() + dur);
            var yyyy = d.getFullYear();
            var mm   = String(d.getMonth()+1).padStart(2,'0');
            var dd   = String(d.getDate()).padStart(2,'0');
            document.getElementById('crs_sub_renewal').value = yyyy+'-'+mm+'-'+dd;
        }
        </script>
        <?php
    }


    public static function order_meta_boxes() {
        add_meta_box( 'crs_order_details', 'Order Details',
            [ __CLASS__, 'render_order_meta_box' ], 'crs_order', 'normal', 'high' );
    }

    public static function render_order_meta_box( $post ) {
        wp_nonce_field( 'crs_order_meta', 'crs_order_meta_nonce' );
        $fields = [
            '_order_owner_id'        => 'Owner ID',
            '_order_subscription_id' => 'Subscription Post ID',
            '_order_type'            => 'Type (signup / renewal / boost)',
            '_order_amount'          => 'Total Amount AUD',
            '_order_gst'             => 'GST Amount',
            '_order_base_amount'     => 'Base Amount (excl. GST)',
            '_order_currency'        => 'Currency',
            '_order_stripe_pi'       => 'Stripe Payment Intent ID',
            '_order_stripe_session'  => 'Stripe Session ID',
            '_order_invoice_num'     => 'Invoice Number',
            '_order_date'            => 'Order Date',
        ];
        echo '<table class="widefat" style="border:none;">';
        foreach ( $fields as $key => $label ) {
            $val = get_post_meta( $post->ID, $key, true );
            printf(
                '<tr><td style="width:240px;padding:6px 8px;font-weight:600;">%s</td>
                 <td style="padding:6px 8px;"><input type="text" name="%s" value="%s" style="width:100%%"></td></tr>',
                esc_html( $label ), esc_attr( $key ), esc_attr( (string) $val )
            );
        }
        echo '</table>';
    }

    public static function save_cpt_meta( $post_id ) {
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;

        if ( isset( $_POST['crs_sub_meta_nonce'] ) &&
             wp_verify_nonce( $_POST['crs_sub_meta_nonce'], 'crs_sub_meta' ) &&
             get_post_type( $post_id ) === 'crs_sub' ) {
            // Copy plan details into subscription on save
            $plan_id = absint( $_POST['_sub_plan_id'] ?? 0 );
            if ( $plan_id ) {
                update_post_meta( $post_id, '_sub_plan_id',       $plan_id );
                update_post_meta( $post_id, '_sub_charge_amount', get_post_meta( $plan_id, '_plan_charge_amount', true ) );
                update_post_meta( $post_id, '_sub_tax_amount',    get_post_meta( $plan_id, '_plan_tax_amount',    true ) );
                update_post_meta( $post_id, '_sub_renewal_type',  get_post_meta( $plan_id, '_plan_renewal_type',  true ) );
                update_post_meta( $post_id, '_sub_duration',      get_post_meta( $plan_id, '_plan_duration',      true ) );
            }
            foreach ( [ '_sub_owner_id', '_sub_start_date', '_sub_renewal_date' ] as $field ) {
                if ( isset( $_POST[ $field ] ) ) {
                    update_post_meta( $post_id, $field, sanitize_text_field( $_POST[ $field ] ) );
                }
            }
        }

        if ( isset( $_POST['crs_order_meta_nonce'] ) &&
             wp_verify_nonce( $_POST['crs_order_meta_nonce'], 'crs_order_meta' ) &&
             get_post_type( $post_id ) === 'crs_order' ) {
            foreach ( [ '_order_owner_id','_order_subscription_id','_order_type',
                        '_order_amount','_order_gst','_order_base_amount',
                        '_order_currency','_order_stripe_pi','_order_stripe_session',
                        '_order_invoice_num','_order_date' ] as $field ) {
                if ( isset( $_POST[ $field ] ) ) {
                    update_post_meta( $post_id, $field, sanitize_text_field( $_POST[ $field ] ) );
                }
            }
        }
    }

    /* ====================================================================
       3.  Taxonomies
       ================================================================== */
    public static function register_taxonomies() {

        // Repair Services — checkbox list so admins can tick existing terms
        register_taxonomy( 'repair-service', 'business', [
            'hierarchical'      => true,                         // enables checkbox meta box
            'public'            => true,
            'show_admin_column' => true,
            'meta_box_cb'       => 'post_categories_meta_box',   // checkbox list, not tag input
            'rewrite'           => [ 'slug' => 'services', 'with_front' => false ],
            'labels' => [
                'name'          => __( 'Repair Services', 'crs' ),
                'singular_name' => __( 'Repair Service',  'crs' ),
                'add_new_item'  => __( 'Add New Service', 'crs' ),
                'all_items'     => __( 'All Services',    'crs' ),
            ],
        ] );

        // State — ACF select field handles this in the main edit area; hide default sidebar box
        register_taxonomy( 'au-state', 'business', [
            'hierarchical'      => false,
            'public'            => true,
            'show_admin_column' => true,
            'meta_box_cb'       => false,   // hidden — ACF Contact & Location field handles it
            'rewrite'           => [ 'slug' => 'state', 'with_front' => false ],
            'labels' => [
                'name'          => __( 'States',     'crs' ),
                'singular_name' => __( 'State',      'crs' ),
                'all_items'     => __( 'All States', 'crs' ),
            ],
        ] );

        // Region — ACF select field handles this; hide default sidebar box
        register_taxonomy( 'au-region', 'business', [
            'hierarchical'  => true,
            'public'        => true,
            'meta_box_cb'   => false,   // hidden — ACF Contact & Location field handles it
            'rewrite'       => [ 'slug' => 'region', 'with_front' => false ],
            'labels' => [
                'name'          => __( 'Regions',     'crs' ),
                'singular_name' => __( 'Region',      'crs' ),
                'all_items'     => __( 'All Regions', 'crs' ),
            ],
        ] );

        // Suburb — ACF select field handles this; hide default sidebar box
        register_taxonomy( 'au-suburb', 'business', [
            'hierarchical'  => true,
            'public'        => true,
            'meta_box_cb'   => false,   // hidden — ACF Contact & Location field handles it
            'rewrite'       => [ 'slug' => 'suburb', 'with_front' => false ],
            'labels' => [
                'name'          => __( 'Suburbs',     'crs' ),
                'singular_name' => __( 'Suburb',      'crs' ),
                'all_items'     => __( 'All Suburbs', 'crs' ),
            ],
        ] );

        // Device Brands — checkbox list
        register_taxonomy( 'device-brand', 'business', [
            'hierarchical'      => true,
            'public'            => true,
            'show_admin_column' => true,
            'meta_box_cb'       => 'post_categories_meta_box',
            'rewrite'           => [ 'slug' => 'brand', 'with_front' => false ],
            'labels' => [
                'name'          => __( 'Device Brands', 'crs' ),
                'singular_name' => __( 'Device Brand',  'crs' ),
                'all_items'     => __( 'All Brands',    'crs' ),
            ],
        ] );

        // Operating Systems — checkbox list
        register_taxonomy( 'operating-system', 'business', [
            'hierarchical'      => true,
            'public'            => true,
            'show_admin_column' => true,
            'meta_box_cb'       => 'post_categories_meta_box',
            'rewrite'           => [ 'slug' => 'os', 'with_front' => false ],
            'labels' => [
                'name'          => __( 'Operating Systems',     'crs' ),
                'singular_name' => __( 'Operating System',      'crs' ),
                'all_items'     => __( 'All Operating Systems', 'crs' ),
            ],
        ] );
    }

    /* ====================================================================
       3.  User Role – business_owner
       ================================================================== */
    public static function create_roles() {
        remove_role( 'business_owner' );

        add_role( 'business_owner', __( 'Business Owner', 'crs' ), [
            'read'                 => true,
            'read_business'        => true,
            'edit_business'        => true,
            'delete_business'      => false,
            'publish_businesses'   => false,
            'crs_dashboard_access' => true,
        ] );

        $admin = get_role( 'administrator' );
        if ( $admin ) {
            $caps = [
                'read_business', 'edit_business', 'edit_businesses',
                'edit_others_businesses', 'edit_published_businesses',
                'publish_businesses', 'delete_business', 'delete_businesses',
                'delete_others_businesses', 'delete_published_businesses',
                'read_private_businesses', 'crs_dashboard_access', 'crs_admin_access',
            ];
            foreach ( $caps as $cap ) {
                $admin->add_cap( $cap );
            }
        }
    }

    /* ====================================================================
       4.  Enquiries DB Table  (wp_crs_enquiries)
       ================================================================== */
    public static function create_enquiries_table() {
        global $wpdb;

        $table   = $wpdb->prefix . 'crs_enquiries';
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            enquiry_id   BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            business_id  BIGINT(20) UNSIGNED NOT NULL,
            name         VARCHAR(100)        NOT NULL DEFAULT '',
            email        VARCHAR(200)        NOT NULL DEFAULT '',
            phone        VARCHAR(50)                  DEFAULT '',
            suburb       VARCHAR(100)                 DEFAULT '',
            service      VARCHAR(100)                 DEFAULT '',
            message      TEXT                NOT NULL,
            contact_pref VARCHAR(50)                  DEFAULT 'email',
            status       VARCHAR(20)         NOT NULL DEFAULT 'new',
            created_at   DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
            replied_at   DATETIME                     DEFAULT NULL,
            PRIMARY KEY  (enquiry_id),
            KEY business_id (business_id),
            KEY status      (status),
            KEY created_at  (created_at)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );

        update_option( 'crs_enquiries_table_version', '1.0' );
    }

    /* ====================================================================
       5.  Default Terms
       ================================================================== */
    public static function insert_default_terms() {

        // ── Repair Services (hierarchical: parent categories + children) ──
        $service_groups = [
            'consumer-repair-services' => [
                'name'     => 'Consumer Repair Services',
                'children' => [
                    'computer-repairs'         => 'Computer Repairs',
                    'laptop-repairs'           => 'Laptop Repairs',
                    'macbook-repairs'          => 'MacBook Repairs',
                    'desktop-computer-repairs' => 'Desktop Computer Repairs',
                    'gaming-pc-repairs'        => 'Gaming PC Repairs',
                    'data-recovery'            => 'Data Recovery',
                    'virus-removal'            => 'Virus Removal',
                    'malware-removal'          => 'Malware Removal',
                    'printer-repairs'          => 'Printer Repairs',
                    'printer-setup'            => 'Printer Setup',
                    'screen-replacement'       => 'Screen Replacement',
                    'battery-replacement'      => 'Battery Replacement',
                    'wifi-troubleshooting'     => 'WiFi Troubleshooting',
                    'software-installation'    => 'Software Installation',
                    'computer-upgrades'        => 'Computer Upgrades',
                ],
            ],
            'business-it-services' => [
                'name'     => 'Business IT Services',
                'children' => [
                    'business-it-support'       => 'Business IT Support',
                    'microsoft-365'             => 'Microsoft 365 Support',
                    'email-support'             => 'Email Support',
                    'network-support'           => 'Network Support',
                    'server-support'            => 'Server Support',
                    'managed-it-services'       => 'Managed IT Services',
                    'remote-it-support'         => 'Remote IT Support',
                    'cloud-backup-services'     => 'Cloud Backup Services',
                    'cyber-security-services'   => 'Cyber Security Services',
                    'business-wifi-support'     => 'Business WiFi Support',
                    'it-help-desk-services'     => 'IT Help Desk Services',
                    'microsoft-teams-support'   => 'Microsoft Teams Support',
                    'sharepoint-support'        => 'SharePoint Support',
                    'cloud-migration-services'  => 'Cloud Migration Services',
                ],
            ],
        ];

        foreach ( $service_groups as $parent_slug => $group ) {
            $parent_term = term_exists( $parent_slug, 'repair-service' );
            if ( ! $parent_term ) {
                $parent_term = wp_insert_term( $group['name'], 'repair-service', [ 'slug' => $parent_slug ] );
            }
            if ( is_wp_error( $parent_term ) ) {
                continue;
            }
            $parent_id = is_array( $parent_term ) ? (int) $parent_term['term_id'] : (int) $parent_term;

            foreach ( $group['children'] as $slug => $name ) {
                $existing = get_term_by( 'slug', $slug, 'repair-service' );
                if ( ! $existing ) {
                    wp_insert_term( $name, 'repair-service', [
                        'slug'   => $slug,
                        'parent' => $parent_id,
                    ] );
                } elseif ( (int) $existing->parent !== $parent_id ) {
                    wp_update_term( $existing->term_id, 'repair-service', [
                        'parent' => $parent_id,
                    ] );
                }
            }
        }

        $data = [
            // au-state slugs now use the full name; abbreviation stored as term meta
            'au-state' => [
                'victoria'                      => 'Victoria',
                'new-south-wales'               => 'New South Wales',
                'queensland'                    => 'Queensland',
                'western-australia'             => 'Western Australia',
                'south-australia'               => 'South Australia',
                'tasmania'                      => 'Tasmania',
                'australian-capital-territory'  => 'Australian Capital Territory',
                'northern-territory'            => 'Northern Territory',
            ],
            'device-brand' => [
                'apple'   => 'Apple',   'dell'    => 'Dell',
                'hp'      => 'HP',      'lenovo'  => 'Lenovo',
                'asus'    => 'ASUS',    'acer'    => 'Acer',
                'msi'     => 'MSI',     'toshiba' => 'Toshiba',
                'samsung' => 'Samsung',
            ],
            'operating-system' => [
                'windows'   => 'Windows',  'macos'     => 'macOS',
                'linux'     => 'Linux',    'chrome-os' => 'Chrome OS',
            ],
        ];

        // Abbreviations for au-state terms (keyed by slug)
        $state_abbrs = [
            'victoria'                     => 'VIC',
            'new-south-wales'              => 'NSW',
            'queensland'                   => 'QLD',
            'western-australia'            => 'WA',
            'south-australia'              => 'SA',
            'tasmania'                     => 'TAS',
            'australian-capital-territory' => 'ACT',
            'northern-territory'           => 'NT',
        ];

        foreach ( $data as $taxonomy => $terms ) {
            foreach ( $terms as $slug => $name ) {
                if ( ! term_exists( $slug, $taxonomy ) ) {
                    $result = wp_insert_term( $name, $taxonomy, [ 'slug' => $slug ] );
                    if ( ! is_wp_error( $result ) && $taxonomy === 'au-state' && isset( $state_abbrs[ $slug ] ) ) {
                        update_term_meta( $result['term_id'], 'au_state_abbreviation', $state_abbrs[ $slug ] );
                    }
                }
            }
        }
    }

    /* ====================================================================
       6.  Migrate existing au-state terms to full-name slugs
       ================================================================== */
    public static function migrate_state_slugs() {
        if ( get_option( 'crs_state_slugs_v2' ) ) {
            return; // Already migrated
        }

        $migrations = [
            'vic' => [ 'slug' => 'victoria',                     'abbr' => 'VIC' ],
            'nsw' => [ 'slug' => 'new-south-wales',               'abbr' => 'NSW' ],
            'qld' => [ 'slug' => 'queensland',                    'abbr' => 'QLD' ],
            'wa'  => [ 'slug' => 'western-australia',             'abbr' => 'WA'  ],
            'sa'  => [ 'slug' => 'south-australia',               'abbr' => 'SA'  ],
            'tas' => [ 'slug' => 'tasmania',                      'abbr' => 'TAS' ],
            'act' => [ 'slug' => 'australian-capital-territory',  'abbr' => 'ACT' ],
            'nt'  => [ 'slug' => 'northern-territory',            'abbr' => 'NT'  ],
        ];

        foreach ( $migrations as $old_slug => $data ) {
            $term = get_term_by( 'slug', $old_slug, 'au-state' );
            if ( $term ) {
                wp_update_term( $term->term_id, 'au-state', [ 'slug' => $data['slug'] ] );
                update_term_meta( $term->term_id, 'au_state_abbreviation', $data['abbr'] );
            }
        }

        update_option( 'crs_state_slugs_v2', true );
    }

    /* ====================================================================
       Fix "All" count for CPTs with custom post statuses
       WP only counts publish/draft/pending by default — our custom
       statuses (sub_active, ord_completed etc.) are excluded unless
       we manually add them.
       ==================================================================== */
    public static function fix_cpt_counts( $counts, $type ) {
        global $wpdb;

        $custom_statuses = [
            'crs_sub'   => [ 'sub_active', 'sub_past_due', 'sub_suspended', 'sub_cancelled' ],
            'crs_order' => [ 'ord_pending', 'ord_completed', 'ord_failed', 'ord_refunded' ],
        ];

        if ( ! isset( $custom_statuses[ $type ] ) ) return $counts;

        $statuses = $custom_statuses[ $type ];
        $placeholders = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT post_status, COUNT(*) AS count
                 FROM {$wpdb->posts}
                 WHERE post_type = %s
                   AND post_status IN ( {$placeholders} )
                 GROUP BY post_status",
                array_merge( [ $type ], $statuses )
            )
        );

        $total = 0;
        foreach ( $results as $row ) {
            $counts->{ $row->post_status } = (int) $row->count;
            $total += (int) $row->count;
        }
        $counts->_total = $total;

        return $counts;
    }

} // end class CRS_Setup

CRS_Setup::init();