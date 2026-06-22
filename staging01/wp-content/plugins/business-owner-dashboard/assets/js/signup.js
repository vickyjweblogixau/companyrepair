/**
 * Business Owner Dashboard — Signup Form JavaScript
 *
 * NOTE: Form submission is handled entirely by the inline script inside the
 * [business_owner_signup] shortcode (shortcodes.php). This file only handles
 * ancillary UX: email availability hint and phone number formatting.
 */
(function ($) {
    'use strict';

    /* ── Email availability check (debounced) ────────────────────────── */
    var emailTimer;
    $(document).on('input', '#bod-signup-form [name="email"]', function () {
        var $input = $(this);
        var $hint  = $input.siblings('.bod-email-hint');
        clearTimeout(emailTimer);

        var email = $.trim($input.val());
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            $hint.text('').removeClass('ok error');
            return;
        }

        // Only run if bodSignup is available (page has localized script)
        if (typeof bodSignup === 'undefined') return;

        emailTimer = setTimeout(function () {
            $.post(bodSignup.ajaxUrl, {
                action: 'bod_validate_email',
                email:  email,
                nonce:  bodSignup.validateNonce,
            }, function (res) {
                if (res && res.success) {
                    $hint.text('✓ Email available').removeClass('error').addClass('ok');
                } else {
                    $hint.text((res && res.data && res.data.message) || 'Email already registered.').removeClass('ok').addClass('error');
                }
            });
        }, 600);
    });

    /* ── Phone formatting (AU) — digits and + only ───────────────────── */
    $(document).on('input', '#bod-signup-form [name="phone"]', function () {
        var v = $(this).val().replace(/[^0-9+]/g, '');
        $(this).val(v);
    });

}(jQuery));
