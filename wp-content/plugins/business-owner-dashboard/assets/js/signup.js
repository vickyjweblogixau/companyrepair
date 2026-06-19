/**
 * Business Owner Dashboard — Signup Form JavaScript
 */
(function ($) {
    'use strict';

    /* ── Signup Form Submit ──────────────────────────────────────────── */
    $(document).on('submit', '#bod-signup-form', function (e) {
        e.preventDefault();

        var $form  = $(this);
        var $btn   = $form.find('button[type="submit"]');
        var $error = $form.find('.bod-form-error');
        var $info  = $form.find('.bod-form-info');

        $error.hide().text('');
        $info.hide().text('');

        // Client-side validation
        var email   = $.trim($form.find('[name="owner_email"]').val());
        var name    = $.trim($form.find('[name="owner_name"]').val());
        var phone   = $.trim($form.find('[name="owner_phone"]').val());
        var biz     = $.trim($form.find('[name="business_name"]').val());
        var agree   = $form.find('[name="agree_terms"]').is(':checked');

        if (!name) {
            showError($error, 'Please enter your full name.');
            return;
        }
        if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            showError($error, 'Please enter a valid email address.');
            return;
        }
        if (!phone) {
            showError($error, 'Please enter your phone number.');
            return;
        }
        if (!agree) {
            showError($error, 'Please agree to the terms and conditions.');
            return;
        }

        $btn.prop('disabled', true).html(
            '<span class="bod-spinner"></span> Processing…'
        );
        $info.show().text('Please wait, setting up your account…');

        $.post(bodSignup.ajaxUrl, {
            action:        'bod_initiate_signup',
            nonce:         bodSignup.nonce,
            owner_name:    name,
            owner_email:   email,
            owner_phone:   phone,
            business_name: biz,
            state:         $form.find('[name="state"]').val(),
            suburb:        $form.find('[name="suburb"]').val(),
        }, function (res) {
            if (res.success && res.data.redirect_url) {
                $info.text('Redirecting to secure payment…');
                window.location.href = res.data.redirect_url;
            } else {
                var msg = (res.data && res.data.message) ? res.data.message : 'Something went wrong. Please try again.';
                showError($error, msg);
                $btn.prop('disabled', false).text('Continue to Payment');
                $info.hide();
            }
        }).fail(function () {
            showError($error, 'Network error. Please check your connection and try again.');
            $btn.prop('disabled', false).text('Continue to Payment');
            $info.hide();
        });
    });

    function showError($el, msg) {
        $el.text(msg).show();
        $el[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    /* ── Email availability check (debounced) ────────────────────────── */
    var emailTimer;
    $(document).on('input', '#bod-signup-form [name="owner_email"]', function () {
        var $input = $(this);
        var $hint  = $input.siblings('.bod-email-hint');
        clearTimeout(emailTimer);

        var email = $.trim($input.val());
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            $hint.text('').removeClass('ok error');
            return;
        }

        emailTimer = setTimeout(function () {
            $.post(bodSignup.ajaxUrl, {
                action: 'bod_validate_email',
                email:  email,
                nonce:  bodSignup.validateNonce,
            }, function (res) {
                if (res.success) {
                    $hint.text('✓ Email available').removeClass('error').addClass('ok');
                } else {
                    $hint.text(res.data.message || 'Email already registered.').removeClass('ok').addClass('error');
                }
            });
        }, 600);
    });

    /* ── Phone formatting (AU) ───────────────────────────────────────── */
    $(document).on('input', '#bod-signup-form [name="owner_phone"]', function () {
        var v = $(this).val().replace(/[^0-9+]/g, '');
        $(this).val(v);
    });

}(jQuery));
