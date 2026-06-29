/**
 * Business Owner Dashboard — Admin JavaScript
 */
(function ($) {
    'use strict';
    var ajaxUrl = bodAdmin.ajaxUrl;
    var nonces  = bodAdmin.nonces;
        // Approve
        $('.bod-approve-btn').on('click', function() {
            var id = $(this).data('id');
            if (!id) { alert('Owner ID missing. Please refresh and try again.'); return; }
            Swal.fire({
                title: 'Approve this owner?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Approve'
            }).then(function(r) {
                if (r.isConfirmed) {
                    $.post(bodAdmin.ajaxUrl, {
                        action: 'bod_approve_owner',
                        owner_id: id,
                        nonce: nonces.approve
                    }, function(res) {
                        if (res.success) {
                            Swal.fire('Approved!', '', 'success').then(function() { location.reload(); });
                        } else {
                            Swal.fire('Error', res.data.message, 'error');
                        }
                    });
                }
            });
        });
        // Reject
        $('.bod-reject-btn').on('click', function() {
            var id = $(this).data('id');
            if (!id) { alert('Owner ID missing. Please refresh and try again.'); return; }
            Swal.fire({
                title: 'Reject this owner?',
                input: 'textarea',
                inputLabel: 'Reason (optional)',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Reject'
            }).then(function(r) {
                if (r.isConfirmed) {
                    $.post(bodAdmin.ajaxUrl, {
                        action: 'bod_reject_owner',
                        owner_id: id,
                        reason: r.value,
                        nonce: nonces.reject
                    }, function(res) {
                        if (res.success) {
                            Swal.fire('Rejected', '', 'success').then(function() { location.reload(); });
                        } else {
                            Swal.fire('Error', res.data.message, 'error');
                        }
                    });
                }
            });
        });
        // Create Account
        $('.bod-create-account-btn').on('click', function() {
            var id = $(this).data('id');
            if (!id) { alert('Owner ID missing. Please refresh and try again.'); return; }
            Swal.fire({
                title: 'Create WP account?',
                text: 'This will create a WordPress user with the business_owner role and send credentials.',
                icon: 'info',
                showCancelButton: true
            }).then(function(r) {
                if (r.isConfirmed) {
                    $.post(bodAdmin.ajaxUrl, {
                        action: 'bod_create_account',
                        owner_id: id,
                        nonce: nonces.createAccount
                    }, function(res) {
                        if (res.success) {
                            Swal.fire('Account Created!', 'Username: ' + res.data.username, 'success').then(function() { location.reload(); });
                        } else {
                            Swal.fire('Error', res.data.message, 'error');
                        }
                    });
                }
            });
        });
        // Resend Credentials
        $('.bod-resend-credentials-btn').on('click', function() {
            var id = $(this).data('id');
            if (!id) { alert('Owner ID missing. Please refresh and try again.'); return; }
            $.post(bodAdmin.ajaxUrl, {
                action: 'bod_send_credentials',
                owner_id: id,
                nonce: nonces.sendEmail
            }, function(res) {
                if (res.success) {
                    Swal.fire('Sent!', 'Credentials email sent.', 'success');
                } else {
                    Swal.fire('Error', res.data.message, 'error');
                }
            });
        });
        // Grant Listing Credit
        $('.bod-grant-listing-btn').on('click', function() {
            var id = $(this).data('id');
            if (!id) { alert('Owner ID missing. Please refresh and try again.'); return; }
            Swal.fire({
                title: 'Grant listing credit?',
                text: 'This adds 1 free listing credit to this owner.',
                icon: 'question',
                showCancelButton: true
            }).then(function(r) {
                if (r.isConfirmed) {
                    $.post(bodAdmin.ajaxUrl, {
                        action: 'bod_grant_listing',
                        owner_id: id,
                        nonce: nonces.grantNewListing
                    }, function(res) {
                        if (res.success) {
                            Swal.fire('Granted!', '', 'success').then(function() { location.reload(); });
                        } else {
                            Swal.fire('Error', res.data.message, 'error');
                        }
                    });
                }
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
