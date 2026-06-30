<?php
/**
 * Stripe Functions for Business Owner Dashboard
 * One-time payments for listing credits and boosts
 * Same-domain: no external API calls for listing sync
 */
if (!defined('ABSPATH')) exit;

/**
 * Track signup attempt before payment completes (creates pending owner + payment row)
 */
function bod_track_pending_signup($owner_data, $customer_id, $checkout_session) {
    global $wpdb;

    $email = sanitize_email($owner_data['email'] ?? '');
    if (!is_email($email) || empty($checkout_session->id)) return 0;

    $existing  = bod_get_owner_by_email($email);
    $owner_id  = 0;

    if ($existing) {
        $owner_id   = (int) $existing->id;
        $update     = [];
        if (empty($existing->stripe_customer_id) && $customer_id) $update['stripe_customer_id'] = $customer_id;
        if (!empty($update)) bod_update_owner($owner_id, $update);
    } else {
        $owner_id = (int) bod_insert_owner([
            'owner_name'    => sanitize_text_field($owner_data['name'] ?? ''),
            'owner_email'   => $email,
            'owner_phone'   => sanitize_text_field($owner_data['phone'] ?? ''),
            'business_name' => sanitize_text_field($owner_data['business_name'] ?? ''),
            'postal_code'   => sanitize_text_field($owner_data['postal_code'] ?? ''),
            'address'       => sanitize_text_field($owner_data['address'] ?? ''),
            'suburb'        => sanitize_text_field($owner_data['suburb'] ?? ''),
            'state'         => sanitize_text_field($owner_data['state'] ?? ''),
            'region'        => sanitize_text_field($owner_data['region'] ?? ''),
            'stripe_customer_id' => $customer_id,
        ]);
    }

    if ($owner_id <= 0) return 0;

    // Avoid duplicate pending payment row
    $exists = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM " . BOD_TABLE_PAYMENTS . " WHERE stripe_checkout_session_id = %s AND payment_type = 'listing' LIMIT 1",
        $checkout_session->id
    ));
    if ($exists > 0) return $owner_id;

    $amount   = ($checkout_session->amount_total ?? 0) / 100;
    $gst      = round($amount - ($amount / 1.1), 2);
    $promo    = sanitize_text_field((string) ($checkout_session->metadata->promotion_code ?? ''));
    $discount = (($checkout_session->total_details->amount_discount ?? 0) / 100);

    $wpdb->insert(BOD_TABLE_PAYMENTS, [
        'owner_id'                   => $owner_id,
        'listing_id'                 => null,
        'payment_type'               => 'listing',
        'stripe_checkout_session_id' => $checkout_session->id,
        'stripe_payment_intent_id'   => $checkout_session->payment_intent ?? null,
        'payment_source'             => 'stripe',
        'promotion_code'             => $promo,
        'discount_amount'            => $discount,
        'amount'                     => $amount,
        'currency'                   => $checkout_session->currency ?? 'aud',
        'amount_gst'                 => $gst,
        'status'                     => 'pending',
        'created_at'                 => current_time('mysql'),
    ]);

    return $owner_id;
}

/**
 * Create Stripe checkout session for new business owner signup
 */
function bod_create_signup_checkout($owner_data) {
    if (!class_exists('\Stripe\Stripe')) {
        bod_init_stripe();
    }
    $secret_key = BOD_STRIPE_SECRET_KEY ?: get_option('bod_stripe_secret_key', '');
    if (empty($secret_key)) {
        return ['success' => false, 'error' => 'Stripe not configured. Please contact admin.'];
    }

    \Stripe\Stripe::setApiKey($secret_key);

    try {
        $tax_rate    = get_option('bod_gst_tax_rate', get_option('cf7_stripe_gst_tax_rate', ''));
        $success_url = home_url('/business-owner-signup-success/?session_id={CHECKOUT_SESSION_ID}&type=signup');
        $cancel_url  = home_url('/list-your-business/?cancelled=1');

        // Read charge amount from active crs_sub_plan CPT — falls back to constant
        $amount_display = 0;
        $plan_name      = 'Business Listing — Monthly Subscription';

        /* $plans = get_posts( [
            'post_type'      => 'crs_sub_plan',
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'orderby'        => 'date',
            'order'          => 'ASC',
            'meta_query'     => [ [ 'key' => '_plan_status', 'value' => 'active' ] ],
        ] );

        if ( ! empty( $plans ) ) {
            $amount_display = (float) get_post_meta( $plans[0]->ID, '_plan_charge_amount', true );
            $plan_name      = $plans[0]->post_title;
        } */
        // Fetch the explicitly-designated default signup plan (set via checkbox
        // on the plan edit screen) instead of "oldest active plan" — this stays
        // correct no matter how many plans get added later.
        $default_plan_id = (int) get_option( 'bod_default_signup_plan_id' );
        $plan_post        = $default_plan_id ? get_post( $default_plan_id ) : null;

        if ( $plan_post && $plan_post->post_status === 'publish' && get_post_meta( $plan_post->ID, '_plan_status', true ) === 'active' ) {
            $amount_display = (float) get_post_meta( $plan_post->ID, '_plan_charge_amount', true );
            $plan_name      = $plan_post->post_title;
        } else {
            // Fallback: no default designated (or it got deactivated) — use
            // the most recently updated active plan rather than the oldest.
            $plans = get_posts( [
                'post_type'      => 'crs_sub_plan',
                'post_status'    => 'publish',
                'posts_per_page' => 1,
                'orderby'        => 'modified',
                'order'          => 'DESC',
                'meta_query'     => [ [ 'key' => '_plan_status', 'value' => 'active' ] ],
            ] );
            if ( ! empty( $plans ) ) {
                $amount_display = (float) get_post_meta( $plans[0]->ID, '_plan_charge_amount', true );
                $plan_name      = $plans[0]->post_title;
            }
        }

        if ( ! $amount_display ) {
            $amount_display = (float) (defined('BOD_LISTING_AMOUNT_DISPLAY') ? BOD_LISTING_AMOUNT_DISPLAY : get_option('bod_listing_amount_display', 35));
        }

        $amount_cents = (int) round( $amount_display * 100 );

        // Build line item using inline price (no price ID needed)
        $line_item = [
            'price_data' => [
                'currency'     => 'aud',
                'unit_amount'  => $amount_cents,
                'product_data' => [
                    'name'        => $plan_name,
                    'description' => 'Monthly listing on ComputerRepairServices.com.au (incl. GST)',
                ],
            ],
            'quantity' => 1,
        ];

        $customer = \Stripe\Customer::create([
            'email' => $owner_data['email'],
            'name'  => $owner_data['name'] ?? '',
            'phone' => $owner_data['phone'] ?? '',
            'metadata' => [
                'source'        => 'business_owner_signup',
                'business_name' => $owner_data['business_name'] ?? '',
                'suburb'        => $owner_data['suburb'] ?? '',
                'state'         => $owner_data['state'] ?? '',
            ],
        ]);

        $promo_params = [];
        if (!empty($owner_data['promotion_code'])) {
            try {
                $codes = \Stripe\PromotionCode::all(['code' => $owner_data['promotion_code'], 'active' => true, 'limit' => 1]);
                if (!empty($codes->data)) {
                    // discounts and allow_promotion_codes are mutually exclusive
                    $promo_params['discounts'] = [['promotion_code' => $codes->data[0]->id]];
                } else {
                    $promo_params['allow_promotion_codes'] = true;
                }
            } catch (\Stripe\Exception\ApiErrorException $e) {
                $promo_params['allow_promotion_codes'] = true;
            }
        } else {
            $promo_params['allow_promotion_codes'] = true;
        }

        $session_params = array_merge([
            'customer'                        => $customer->id,
            'mode'                            => 'payment',
            'payment_method_types'            => ['card'],
            'payment_intent_data'             => [
                'setup_future_usage' => 'off_session', // save card for future renewals
                'metadata'           => [
                    'owner_email'  => $owner_data['email'] ?? '',
                    'source'       => 'business_owner_signup',
                ],
            ],
            'line_items'                      => [$line_item],
            'success_url'                     => $success_url,
            'cancel_url'                      => $cancel_url,
            'metadata'                        => [
                'owner_name'           => $owner_data['name'] ?? '',
                'owner_email'          => $owner_data['email'] ?? '',
                'owner_phone'          => $owner_data['phone'] ?? '',
                'business_name'        => $owner_data['business_name'] ?? '',
                'abn'                  => $owner_data['abn'] ?? '',
                'website_url'          => $owner_data['website_url'] ?? '',
                'postal_code'          => $owner_data['postal_code'] ?? '',
                'suburb'               => $owner_data['suburb'] ?? '',
                'state'                => $owner_data['state'] ?? '',
                'region'               => $owner_data['region'] ?? '',
                'primary_service_area' => $owner_data['primary_service_area'] ?? '',
                'service_radius'       => $owner_data['service_radius'] ?? '',
                'services'             => substr($owner_data['services'] ?? '', 0, 490),
                'is_signup'            => 'yes',
                'source'               => 'business_owner_signup',
                'promotion_code'       => $owner_data['promotion_code'] ?? '',
            ],
        ], $promo_params);

        $session = \Stripe\Checkout\Session::create($session_params);

        // Track pending signup immediately
        bod_track_pending_signup($owner_data, $customer->id, $session);

        return [
            'success'     => true,
            'session_id'  => $session->id,
            'customer_id' => $customer->id,
            'url'         => $session->url,
        ];

    } catch (\Stripe\Exception\ApiErrorException $e) {
        error_log('[BOD Stripe] Stripe API error during signup checkout: ' . $e->getMessage());
        $debug = defined('WP_DEBUG') && WP_DEBUG ? ' (' . $e->getMessage() . ')' : '';
        return ['success' => false, 'error' => 'Payment session could not be created. Please try again.' . $debug];
    } catch (\Throwable $e) {
        error_log('[BOD Stripe] Unexpected error during signup checkout: ' . get_class($e) . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        $debug = defined('WP_DEBUG') && WP_DEBUG ? ' (' . $e->getMessage() . ')' : '';
        return ['success' => false, 'error' => 'An unexpected error occurred. Please try again or contact support.' . $debug];
    }
}

/**
 * Create boost checkout session (featured/exclusive/homepage) for existing owner
 */
function bod_create_boost_checkout($owner_id, $boost_type) {
    $price_map = [
        'featured'  => BOD_BOOST_FEATURED_PRICE_ID,
        'exclusive' => BOD_BOOST_EXCLUSIVE_PRICE_ID,
        'homepage'  => BOD_BOOST_HOMEPAGE_PRICE_ID,
    ];
    if (!isset($price_map[$boost_type]) || empty($price_map[$boost_type])) {
        return ['success' => false, 'error' => 'Invalid boost type'];
    }

    $owner = bod_get_owner($owner_id);
    if (!$owner) return ['success' => false, 'error' => 'Owner not found'];

    if (!class_exists('\Stripe\Stripe')) bod_init_stripe();
    $secret_key = BOD_STRIPE_SECRET_KEY ?: get_option('bod_stripe_secret_key', '');
    if (empty($secret_key)) return ['success' => false, 'error' => 'Stripe not configured'];

    \Stripe\Stripe::setApiKey($secret_key);

    try {
        $tax_rate  = get_option('bod_gst_tax_rate', get_option('cf7_stripe_gst_tax_rate', ''));
        $line_item = ['price' => $price_map[$boost_type], 'quantity' => 1];
        if ($tax_rate) $line_item['tax_rates'] = [$tax_rate];

        $session = \Stripe\Checkout\Session::create([
            'customer'    => $owner->stripe_customer_id,
            'mode'        => 'payment',
            'line_items'  => [$line_item],
            'success_url' => home_url('/business-owner-dashboard/?boost_success=1&boost=' . $boost_type),
            'cancel_url'  => home_url('/business-owner-dashboard/?boost_cancelled=1'),
            'metadata'    => [
                'owner_id'   => $owner_id,
                'boost_type' => $boost_type,
                'source'     => 'business_owner_boost',
            ],
        ]);

        return ['success' => true, 'url' => $session->url, 'session_id' => $session->id];

    } catch (\Stripe\Exception\ApiErrorException $e) {
        error_log('[BOD Stripe] Boost checkout error: ' . $e->getMessage());
        return ['success' => false, 'error' => 'Could not create boost session. Please try again.'];
    }
}

/**
 * Create checkout session for buying additional listing credits
 */
function bod_create_buy_listing_checkout($owner_id) {
    $owner = bod_get_owner($owner_id);
    if (!$owner) return ['success' => false, 'error' => 'Owner not found'];

    if (!class_exists('\Stripe\Stripe')) bod_init_stripe();
    $secret_key = BOD_STRIPE_SECRET_KEY ?: get_option('bod_stripe_secret_key', '');
    if (empty($secret_key)) return ['success' => false, 'error' => 'Stripe not configured'];

    \Stripe\Stripe::setApiKey($secret_key);

    try {
        $price_id  = BOD_LISTING_PRICE_ID;
        $tax_rate  = get_option('bod_gst_tax_rate', get_option('cf7_stripe_gst_tax_rate', ''));
        $line_item = ['price' => $price_id, 'quantity' => 1];
        if ($tax_rate) $line_item['tax_rates'] = [$tax_rate];

        $session = \Stripe\Checkout\Session::create([
            'customer'    => $owner->stripe_customer_id,
            'mode'        => 'payment',
            'line_items'  => [$line_item],
            'success_url' => home_url('/business-owner-dashboard/?listing_success=1'),
            'cancel_url'  => home_url('/business-owner-dashboard/'),
            'metadata'    => [
                'owner_id' => $owner_id,
                'source'   => 'business_owner_buy_listing',
            ],
        ]);

        return ['success' => true, 'url' => $session->url, 'session_id' => $session->id];

    } catch (\Stripe\Exception\ApiErrorException $e) {
        error_log('[BOD Stripe] Buy listing error: ' . $e->getMessage());
        return ['success' => false, 'error' => 'Could not create checkout session.'];
    }
}

/**
 * Handle incoming Stripe webhook
 */
function bod_handle_stripe_webhook(WP_REST_Request $request) {
    $payload    = $request->get_body();
    $sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
    $secret     = BOD_STRIPE_WEBHOOK_SECRET ?: get_option('bod_stripe_webhook_secret', '');

    if (empty($secret)) {
        error_log('[BOD Webhook] No webhook secret configured');
        return new WP_REST_Response(['error' => 'Webhook secret not configured'], 400);
    }

    if (!class_exists('\Stripe\Stripe')) bod_init_stripe();
    \Stripe\Stripe::setApiKey(BOD_STRIPE_SECRET_KEY ?: get_option('bod_stripe_secret_key', ''));

    try {
        $event = \Stripe\Webhook::constructEvent($payload, $sig_header, $secret);
    } catch (\UnexpectedValueException $e) {
        error_log('[BOD Webhook] Invalid payload: ' . $e->getMessage());
        return new WP_REST_Response(['error' => 'Invalid payload'], 400);
    } catch (\Stripe\Exception\SignatureVerificationException $e) {
        error_log('[BOD Webhook] Invalid signature: ' . $e->getMessage());
        return new WP_REST_Response(['error' => 'Invalid signature'], 400);
    }

    error_log('[BOD Webhook] Event received: ' . $event->type);

    switch ($event->type) {
        case 'checkout.session.completed':
            bod_webhook_handle_checkout_completed($event->data->object);
            break;
        case 'invoice.payment_failed':
            error_log('[BOD Webhook] Invoice payment failed: ' . ($event->data->object->subscription ?? 'unknown'));
            break;
        case 'payment_intent.succeeded':
            // handled via checkout.session.completed
            break;
    }

    return new WP_REST_Response(['received' => true], 200);
}

/**
 * Process completed checkout session
 */
function bod_webhook_handle_checkout_completed($session) {
    global $wpdb;

    $source     = $session->metadata->source ?? '';
    $is_signup  = ($source === 'business_owner_signup' || ($session->metadata->is_signup ?? '') === 'yes');
    $is_boost   = ($source === 'business_owner_boost');
    $is_listing = ($source === 'business_owner_buy_listing');

    if ($is_signup) {
        bod_webhook_process_signup($session);
    } elseif ($is_boost) {
        bod_webhook_process_boost($session);
    } elseif ($is_listing) {
        bod_webhook_process_buy_listing($session);
    } else {
        // Fallback: try to match by existing pending payment row
        $pending = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM " . BOD_TABLE_PAYMENTS . " WHERE stripe_checkout_session_id = %s AND status = 'pending' LIMIT 1",
            $session->id
        ));
        if ($pending) {
            bod_webhook_process_signup($session);
        }
    }
}

function bod_webhook_process_signup($session) {
    global $wpdb;

    $email = sanitize_email($session->customer_details->email ?? ($session->metadata->owner_email ?? ''));
    if (!$email) {
        error_log('[BOD Webhook] Signup: no email in session ' . $session->id);
        return;
    }

    $subscription_id = sanitize_text_field($session->subscription ?? '');

    $owner = bod_get_owner_by_email($email);
    if (!$owner) {
        // Create owner from metadata
        $owner_data = [
            'owner_name'           => sanitize_text_field($session->metadata->owner_name ?? ''),
            'owner_email'          => $email,
            'owner_phone'          => sanitize_text_field($session->metadata->owner_phone ?? ''),
            'business_name'        => sanitize_text_field($session->metadata->business_name ?? ''),
            'postal_code'          => sanitize_text_field($session->metadata->postal_code ?? ''),
            'suburb'               => sanitize_text_field($session->metadata->suburb ?? ''),
            'state'                => sanitize_text_field($session->metadata->state ?? ''),
            'region'               => sanitize_text_field($session->metadata->region ?? ''),
            'stripe_customer_id'      => $session->customer ?? '',
            'stripe_subscription_id'  => $subscription_id,
        ];
        $owner_id = (int) bod_insert_owner($owner_data);
        $owner    = bod_get_owner($owner_id);
    } else {
        $owner_id = (int) $owner->id;
        $updates  = [];
        if (empty($owner->stripe_customer_id) && !empty($session->customer)) {
            $updates['stripe_customer_id'] = $session->customer;
        }
        if (!empty($subscription_id)) {
            $updates['stripe_subscription_id'] = $subscription_id;
        }
        if (!empty($updates)) {
            bod_update_owner($owner_id, $updates);
        }
    }

    if (!$owner_id) {
        error_log('[BOD Webhook] Could not resolve owner for email: ' . $email);
        return;
    }

    $amount = ($session->amount_total ?? 0) / 100;
    $gst    = round($amount - ($amount / 1.1), 2);

    // Check if payment already processed
    $existing_payment = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM " . BOD_TABLE_PAYMENTS . " WHERE stripe_checkout_session_id = %s AND payment_type = 'signup' LIMIT 1",
        $session->id
    ));

    if ($existing_payment && $existing_payment->status === 'succeeded') {
        error_log('[BOD Webhook] Signup already processed for session: ' . $session->id);
        return;
    }

    // Activate subscription via CRS_Subscriptions (creates payment record + sets sub_* fields)
    $stripe_pi   = $session->payment_intent ?? '';
    $invoice_num = '';
    if (class_exists('CRS_Subscriptions')) {
        $invoice_num = CRS_Subscriptions::activate_after_signup(
            $owner_id,
            $amount,
            'monthly',
            $stripe_pi,
            $session->id
        );
         // succeeded now that the new CPT-based order exists.
        $wpdb->update(
            BOD_TABLE_PAYMENTS,
            [
                'status'       => 'succeeded',
                'completed_at' => current_time('mysql'),
            ],
            [
                'stripe_checkout_session_id' => $session->id,
                'payment_type'               => 'signup',
            ]
        );
    } else {
        // Fallback: manual insert if class not loaded
        $wpdb->insert(BOD_TABLE_PAYMENTS, [
            'owner_id'                   => $owner_id,
            'payment_type'               => 'signup',
            'stripe_checkout_session_id' => $session->id,
            'stripe_payment_intent_id'   => $stripe_pi,
            'payment_source'             => 'stripe',
            'amount'                     => $amount,
            'amount_gst'                 => $gst,
            'currency'                   => $session->currency ?? 'aud',
            'status'                     => 'succeeded',
            'completed_at'               => current_time('mysql'),
            'created_at'                 => current_time('mysql'),
        ]);
    }

    // Update listing credits
    $wpdb->query($wpdb->prepare(
        "UPDATE " . BOD_TABLE_OWNERS . " SET available_listing_credits = available_listing_credits + 1, total_listings_purchased = total_listings_purchased + 1 WHERE id = %d",
        $owner_id
    ));

    // Send confirmation emails
    bod_send_submission_received_email($owner_id, $session);
    bod_send_new_owner_admin_notification($owner_id);

    error_log('[BOD Webhook] Signup payment confirmed for owner #' . $owner_id . ' (' . $email . '). Invoice: ' . $invoice_num);
}


// Subscription cancellation is now handled by CRS_Subscriptions::cancel()
// called from the owner's dashboard — no Stripe webhook needed.