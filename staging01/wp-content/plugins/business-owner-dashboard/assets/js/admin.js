/**
 * Business Owner Dashboard — Admin JavaScript
 */
(function ($) {
    'use strict';

    var ajaxUrl = bodAdmin.ajaxUrl;
    var nonces  = bodAdmin.nonces;

    /* ── Approve Owner ───────────────────────────────────────────────── */
    $(document).on('click', '.bod-approve-btn', function () {
        var ownerId = $(this).data('owner-id');
        var $btn    = $(this);

        Swal.fire({
            title: 'Approve Business Owner?',
            text: 'This will activate the account and send a confirmation email.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#f97316',
            confirmButtonText: 'Yes, Approve',
        }).then(function (result) {
            if (!result.isConfirmed) return;

            $btn.prop('disabled', true).text('Approving…');

            $.post(ajaxUrl, {
                action:   'bod_approve_owner',
                owner_id: ownerId,
                nonce:    nonces.approve,
            }, function (res) {
                if (res.success) {
                    Swal.fire({ icon: 'success', title: 'Approved!', text: res.data.message, confirmButtonColor: '#f97316' })
                        .then(function () { location.reload(); });
                } else {
                    Swal.fire({ icon: 'error', title: 'Error', text: res.data.message });
                    $btn.prop('disabled', false).text('Approve');
                }
            });
        });
    });

    /* ── Reject Owner ────────────────────────────────────────────────── */
    $(document).on('click', '.bod-reject-btn', function () {
        var ownerId = $(this).data('owner-id');
        var $btn    = $(this);

        Swal.fire({
            title: 'Reject Business Owner?',
            input: 'textarea',
            inputLabel: 'Reason (optional — sent to owner)',
            inputPlaceholder: 'Enter rejection reason…',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc2626',
            confirmButtonText: 'Reject',
        }).then(function (result) {
            if (!result.isConfirmed) return;

            $btn.prop('disabled', true).text('Rejecting…');

            $.post(ajaxUrl, {
                action:   'bod_reject_owner',
                owner_id: ownerId,
                reason:   result.value || '',
                nonce:    nonces.reject,
            }, function (res) {
                if (res.success) {
                    Swal.fire({ icon: 'success', title: 'Rejected', text: res.data.message, confirmButtonColor: '#f97316' })
                        .then(function () { location.reload(); });
                } else {
                    Swal.fire({ icon: 'error', title: 'Error', text: res.data.message });
                    $btn.prop('disabled', false).text('Reject');
                }
            });
        });
    });

    /* ── Create WP Account ───────────────────────────────────────────── */
    $(document).on('click', '.bod-create-account-btn', function () {
        var ownerId = $(this).data('owner-id');
        var $btn    = $(this);

        Swal.fire({
            title: 'Create WordPress Account?',
            text: 'A new WP user will be created with the business_owner role and credentials emailed.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#2563eb',
            confirmButtonText: 'Create Account',
        }).then(function (result) {
            if (!result.isConfirmed) return;

            $btn.prop('disabled', true).text('Creating…');

            $.post(ajaxUrl, {
                action:   'bod_create_account',
                owner_id: ownerId,
                nonce:    nonces.create_account,
            }, function (res) {
                if (res.success) {
                    Swal.fire({ icon: 'success', title: 'Account Created!', text: res.data.message, confirmButtonColor: '#f97316' })
                        .then(function () { location.reload(); });
                } else {
                    Swal.fire({ icon: 'error', title: 'Error', text: res.data.message });
                    $btn.prop('disabled', false).text('Create Account');
                }
            });
        });
    });

    /* ── Resend Credentials ──────────────────────────────────────────── */
    $(document).on('click', '.bod-send-credentials-btn', function () {
        var ownerId = $(this).data('owner-id');
        var $btn    = $(this);

        Swal.fire({
            title: 'Resend Credentials?',
            text: 'Login credentials will be re-sent to the owner\'s email.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#f97316',
            confirmButtonText: 'Send Email',
        }).then(function (result) {
            if (!result.isConfirmed) return;

            $btn.prop('disabled', true).text('Sending…');

            $.post(ajaxUrl, {
                action:   'bod_send_credentials',
                owner_id: ownerId,
                nonce:    nonces.send_credentials,
            }, function (res) {
                if (res.success) {
                    Swal.fire({ icon: 'success', title: 'Sent!', text: res.data.message, confirmButtonColor: '#f97316' });
                } else {
                    Swal.fire({ icon: 'error', title: 'Error', text: res.data.message });
                }
                $btn.prop('disabled', false).text('Resend Credentials');
            });
        });
    });

    /* ── Grant Listing Credit ────────────────────────────────────────── */
    $(document).on('click', '.bod-grant-listing-btn', function () {
        var ownerId = $(this).data('owner-id');
        var $btn    = $(this);

        Swal.fire({
            title: 'Grant Listing Credit?',
            input: 'number',
            inputLabel: 'Number of credits to add',
            inputValue: 1,
            inputAttributes: { min: 1, max: 20, step: 1 },
            showCancelButton: true,
            confirmButtonColor: '#16a34a',
            confirmButtonText: 'Grant',
        }).then(function (result) {
            if (!result.isConfirmed) return;

            var qty = parseInt(result.value, 10) || 1;
            $btn.prop('disabled', true).text('Granting…');

            $.post(ajaxUrl, {
                action:   'bod_grant_listing',
                owner_id: ownerId,
                quantity: qty,
                nonce:    nonces.grant_listing,
            }, function (res) {
                if (res.success) {
                    Swal.fire({ icon: 'success', title: 'Granted!', text: res.data.message, confirmButtonColor: '#f97316' })
                        .then(function () { location.reload(); });
                } else {
                    Swal.fire({ icon: 'error', title: 'Error', text: res.data.message });
                    $btn.prop('disabled', false).text('Grant Listing');
                }
            });
        });
    });

    /* ── Cancel / Deactivate Listing ─────────────────────────────────── */
    $(document).on('click', '.bod-cancel-listing-btn', function () {
        var listingId = $(this).data('listing-id');
        var $btn      = $(this);

        Swal.fire({
            title: 'Deactivate Listing?',
            text: 'The listing will be set to draft and hidden from the site.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc2626',
            confirmButtonText: 'Deactivate',
        }).then(function (result) {
            if (!result.isConfirmed) return;

            $btn.prop('disabled', true).text('Deactivating…');

            $.post(ajaxUrl, {
                action:     'bod_admin_cancel_listing',
                listing_id: listingId,
                nonce:      nonces.cancel_listing,
            }, function (res) {
                if (res.success) {
                    Swal.fire({ icon: 'success', title: 'Done', text: res.data.message, confirmButtonColor: '#f97316' })
                        .then(function () { location.reload(); });
                } else {
                    Swal.fire({ icon: 'error', title: 'Error', text: res.data.message });
                    $btn.prop('disabled', false).text('Deactivate');
                }
            });
        });
    });

    /* ── Reactivate Listing ──────────────────────────────────────────── */
    $(document).on('click', '.bod-reactivate-listing-btn', function () {
        var listingId = $(this).data('listing-id');
        var $btn      = $(this);

        $btn.prop('disabled', true).text('Reactivating…');

        $.post(ajaxUrl, {
            action:     'bod_reactivate_listing',
            listing_id: listingId,
            nonce:      nonces.reactivate_listing,
        }, function (res) {
            if (res.success) {
                Swal.fire({ icon: 'success', title: 'Reactivated!', text: res.data.message, confirmButtonColor: '#f97316' })
                    .then(function () { location.reload(); });
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: res.data.message });
                $btn.prop('disabled', false).text('Reactivate');
            }
        });
    });

    /* ── Copy Webhook URL ────────────────────────────────────────────── */
    $(document).on('click', '.bod-copy-btn', function () {
        var text = $(this).data('copy') || $(this).prev('.bod-webhook-url-text').text();
        if (navigator.clipboard && text) {
            navigator.clipboard.writeText(text.trim()).then(function () {
                Swal.fire({ icon: 'success', title: 'Copied!', timer: 1200, showConfirmButton: false });
            });
        }
    });

    /* ── Settings: Toggle password visibility ────────────────────────── */
    $(document).on('click', '.bod-toggle-secret', function () {
        var $inp = $(this).prev('input');
        if ($inp.attr('type') === 'password') {
            $inp.attr('type', 'text');
            $(this).text('Hide');
        } else {
            $inp.attr('type', 'password');
            $(this).text('Show');
        }
    });

}(jQuery));
