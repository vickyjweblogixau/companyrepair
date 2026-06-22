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

    // Validate price ID before hitting Stripe — gives a clear error instead of a generic 500
    $price_id = BOD_LISTING_PRICE_ID ?: get_option('bod_listing_price_id', '');
    if (empty($price_id)) {
        error_log('[BOD Stripe] Subscription Price ID is not set in Settings.');
        return ['success' => false, 'error' => 'Subscription not yet configured. Please contact admin.'];
    }

    try {
        $tax_rate    = get_option('bod_gst_tax_rate', get_option('cf7_stripe_gst_tax_rate', ''));
        $success_url = home_url('/business-owner-signup-success/?session_id={CHECKOUT_SESSION_ID}&type=signup');
        $cancel_url  = home_url('/list-your-business/?cancelled=1');

        // Subscription mode — tax rate goes on subscription_data, not on the line item
        $line_item = ['price' => $price_id, 'quantity' => 1];

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
            $promo_params['allow_promotion_codes'] = false;
            // Validate promo code and apply as subscription discount
            try {
                $codes = \Stripe\PromotionCode::all(['code' => $owner_data['promotion_code'], 'active' => true, 'limit' => 1]);
                if (!empty($codes->data)) {
                    $promo_params['discounts'] = [['promotion_code' => $codes->data[0]->id]];
                }
            } catch (\Stripe\Exception\ApiErrorException $e) {
                // ignore invalid promo code
            }
        } else {
            $promo_params['allow_promotion_codes'] = true;
        }

        $subscription_data = [
            'metadata' => [
                'source'       => 'business_owner_signup',
                'owner_email'  => $owner_data['email'] ?? '',
                'business_name'=> $owner_data['business_name'] ?? '',
            ],
        ];
        if ($tax_rate) {
            $subscription_data['default_tax_rates'] = [$tax_rate];
        }

        $session_params = array_merge([
            'customer'          => $customer->id,
            'mode'              => 'subscription',
            'line_items'        => [$line_item],
            'success_url'       => $success_url,
            'cancel_url'        => $cancel_url,
            'subscription_data' => $subscription_data,
            'metadata'          => [
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
        return ['success' => false, 'error' => 'Payment session could not be created. Please try again.'];
    } catch (\Throwable $e) {
        // Catch any PHP Error or Exception (including "class not found", TypeError, etc.)
        // so the AJAX handler always returns JSON instead of triggering an HTTP 500.
        error_log('[BOD Stripe] Unexpected error during signup checkout: ' . get_class($e) . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        return ['success' => false, 'error' => 'An unexpected error occurred. Please try again or contact support.'];
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
        case 'customer.subscription.deleted':
            // Subscription cancelled — mark owner as inactive
            bod_webhook_handle_subscription_cancelled($event->data->object);
            break;
        case 'invoice.payment_failed':
            // Log failed renewal payment
            error_log('[BOD Webhook] Invoice payment failed for subscription: ' . ($event->data->object->subscription ?? 'unknown'));
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

    // Mark pending payment as succeeded (or insert new record)
    $existing_payment = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM " . BOD_TABLE_PAYMENTS . " WHERE stripe_checkout_session_id = %s AND payment_type = 'listing' LIMIT 1",
        $session->id
    ));

    $amount   = ($session->amount_total ?? 0) / 100;
    $gst      = round($amount - ($amount / 1.1), 2);

    if ($existing_payment) {
        $wpdb->update(BOD_TABLE_PAYMENTS, [
            'status'       => 'succeeded',
            'completed_at' => current_time('mysql'),
            'amount'       => $amount,
            'amount_gst'   => $gst,
        ], ['id' => $existing_payment->id]);

        $listing_id = (int) ($existing_payment->listing_id ?? 0);
    } else {
        // Create listing record first
        $listing_id = (int) bod_create_listing_record($owner_id, $session);
        bod_create_payment_record($owner_id, $listing_id, 'listing', $session);
    }

    // Activate the listing credit
    if ($listing_id) {
        bod_activate_listing($listing_id, $owner_id);
    } else {
        // Credit without listing record
        $wpdb->query($wpdb->prepare(
            "UPDATE " . BOD_TABLE_OWNERS . " SET available_listing_credits = available_listing_credits + 1, total_listings_purchased = total_listings_purchased + 1 WHERE id = %d",
            $owner_id
        ));
    }

    // -------------------------------------------------------
    // PENDING APPROVAL FLOW
    // Payment is confirmed. Leave approval_status = 'pending'.
    // Admin must review at:
    //   wp-admin/admin.php?page=business-owners-pending
    // and manually approve + create the account from there.
    // -------------------------------------------------------

    // Send owner a "submission received" email (payment confirmed, pending review).
    bod_send_submission_received_email($owner_id, $session);

    // Notify admin to review the new application.
    bod_send_new_owner_admin_notification($owner_id);

    error_log('[BOD Webhook] Payment confirmed for owner #' . $owner_id . ' (' . $email . '). Awaiting admin approval.');
}

/**
 * Handle subscription cancellation — mark the owner's subscription as inactive.
 */
function bod_webhook_handle_subscription_cancelled($subscription) {
    global $wpdb;

    $subscription_id = $subscription->id ?? '';
    if (!$subscription_id) return;

    $owner = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM " . BOD_TABLE_OWNERS . " WHERE stripe_subscription_id = %s LIMIT 1",
        $subscription_id
    ));

    if (!$owner) {
        error_log('[BOD Webhook] Subscription cancelled but no owner found for: ' . $subscription_id);
        return;
    }

    bod_update_owner((int) $owner->id, ['approval_status' => 'rejected']);
    bod_add_notification((int) $owner->id, 'subscription', 'Subscription Cancelled', 'Your subscription has been cancelled. Please contact us if you wish to reactivate your listing.');

    error_log('[BOD Webhook] Subscription cancelled for owner #' . (int) $owner->id);
}
