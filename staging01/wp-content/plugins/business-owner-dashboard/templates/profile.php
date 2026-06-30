<?php
/**
 * Business Owner — Profile View
 */
defined('ABSPATH') || exit;

$owner   = bod_get_current_owner();
$wp_user = wp_get_current_user();

$save_msg = '';
if (!empty($_POST['bod_save_profile']) && check_admin_referer('bod_profile_nonce')) {
    $update = [
        'owner_name'    => sanitize_text_field($_POST['owner_name'] ?? ''),
        'owner_phone'   => sanitize_text_field($_POST['owner_phone'] ?? ''),
        'business_name' => sanitize_text_field($_POST['business_name'] ?? ''),
        'address'       => sanitize_text_field($_POST['address'] ?? ''),
        'suburb'        => sanitize_text_field($_POST['suburb'] ?? ''),
        'state'         => sanitize_text_field($_POST['state'] ?? ''),
        'postal_code'   => sanitize_text_field($_POST['postal_code'] ?? ''),
    ];
    bod_update_owner($owner->id, $update);

    // Also update WP display name
    if (!empty($update['owner_name'])) {
        wp_update_user(['ID' => $wp_user->ID, 'display_name' => $update['owner_name']]);
    }

    $owner   = bod_get_current_owner(); // refresh
    $save_msg = 'Profile updated successfully.';
}

// Password change
$pwd_msg   = '';
$pwd_error = '';
if (!empty($_POST['bod_change_password']) && check_admin_referer('bod_pwd_nonce')) {
    $current = $_POST['current_password'] ?? '';
    $new     = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (!wp_check_password($current, $wp_user->user_pass, $wp_user->ID)) {
        $pwd_error = 'Current password is incorrect.';
    } elseif (strlen($new) < 8) {
        $pwd_error = 'New password must be at least 8 characters.';
    } elseif ($new !== $confirm) {
        $pwd_error = 'New passwords do not match.';
    } else {
        wp_set_password($new, $wp_user->ID);
        $pwd_msg = 'Password changed successfully. Please log in again.';
        wp_logout();
        wp_redirect(home_url('/business-owner-login/?msg=password_changed'));
        exit;
    }
}
?>

<div class="bod-profile-page">
    <div style="margin-bottom:24px;">
        <h4 style="margin:0;font-weight:700;">My Profile</h4>
    </div>

    <?php if ($save_msg) : ?><div class="alert alert-success"><?php echo esc_html($save_msg); ?></div><?php endif; ?>
    <?php $pending_count = crs_has_pending_changes($business_id); ?>
    <?php if ($pending_count) : ?>
    <div class="alert alert-warning">
        <i class="bi bi-clock-history"></i>
        <strong><?php echo $pending_count; ?> change(s) pending approval</strong> — your live profile still shows the previous version.
    </div>
    <?php endif; ?>
    <div class="row g-4">
        <!-- Profile Form -->
        <div class="col-lg-8">
            <div class="card" style="border-radius:12px;">
                <div class="card-body" style="padding:24px;">
                    <h5 style="font-weight:700;margin:0 0 20px;">Personal & Business Details</h5>
                    <form method="post">
                        <?php wp_nonce_field('bod_profile_nonce'); ?>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label style="display:block;font-weight:600;margin-bottom:4px;">Full Name</label>
                                <input type="text" name="owner_name" value="<?php echo esc_attr($owner->owner_name); ?>"
                                       style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;box-sizing:border-box;">
                            </div>
                            <div class="col-md-6">
                                <label style="display:block;font-weight:600;margin-bottom:4px;">Business Name</label>
                                <input type="text" name="business_name" value="<?php echo esc_attr($owner->business_name ?? ''); ?>"
                                       style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;box-sizing:border-box;">
                            </div>
                            <div class="col-md-6">
                                <label style="display:block;font-weight:600;margin-bottom:4px;">Email</label>
                                <input type="email" value="<?php echo esc_attr($owner->owner_email); ?>" disabled
                                       style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;box-sizing:border-box;background:#f9f9f9;">
                                <small style="color:#888;">Email cannot be changed here. Contact admin.</small>
                            </div>
                            <div class="col-md-6">
                                <label style="display:block;font-weight:600;margin-bottom:4px;">Phone</label>
                                <input type="tel" name="owner_phone" value="<?php echo esc_attr($owner->owner_phone); ?>"
                                       style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;box-sizing:border-box;">
                            </div>
                            <div class="col-12">
                                <label style="display:block;font-weight:600;margin-bottom:4px;">Street Address</label>
                                <input type="text" name="address" value="<?php echo esc_attr($owner->address ?? ''); ?>"
                                       style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;box-sizing:border-box;">
                            </div>
                            <div class="col-md-5">
                                <label style="display:block;font-weight:600;margin-bottom:4px;">Suburb</label>
                                <input type="text" name="suburb" value="<?php echo esc_attr($owner->suburb ?? ''); ?>"
                                       style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;box-sizing:border-box;">
                            </div>
                            <div class="col-md-4">
                                <label style="display:block;font-weight:600;margin-bottom:4px;">State</label>
                                <select name="state" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;">
                                    <?php foreach (['ACT','NSW','NT','QLD','SA','TAS','VIC','WA'] as $st) : ?>
                                        <option value="<?php echo $st; ?>" <?php selected($owner->state, $st); ?>><?php echo $st; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label style="display:block;font-weight:600;margin-bottom:4px;">Postcode</label>
                                <input type="text" name="postal_code" maxlength="4" value="<?php echo esc_attr($owner->postal_code ?? ''); ?>"
                                       style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;box-sizing:border-box;">
                            </div>
                        </div>
                        <div style="margin-top:20px;">
                            <button type="submit" name="bod_save_profile" value="1" class="btn btn-primary">Save Profile</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Password Change -->
            <div class="card" style="border-radius:12px;margin-top:20px;">
                <div class="card-body" style="padding:24px;">
                    <h5 style="font-weight:700;margin:0 0 20px;">Change Password</h5>
                    <?php if ($pwd_error) : ?><div class="alert alert-danger"><?php echo esc_html($pwd_error); ?></div><?php endif; ?>
                    <?php if ($pwd_msg)   : ?><div class="alert alert-success"><?php echo esc_html($pwd_msg); ?></div><?php endif; ?>
                    <form method="post">
                        <?php wp_nonce_field('bod_pwd_nonce'); ?>
                        <div class="row g-3">
                            <div class="col-12">
                                <label style="display:block;font-weight:600;margin-bottom:4px;">Current Password</label>
                                <input type="password" name="current_password" required
                                       style="width:100%;max-width:360px;padding:10px;border:1px solid #ddd;border-radius:6px;">
                            </div>
                            <div class="col-md-6">
                                <label style="display:block;font-weight:600;margin-bottom:4px;">New Password</label>
                                <input type="password" name="new_password" minlength="8" required
                                       style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;box-sizing:border-box;">
                            </div>
                            <div class="col-md-6">
                                <label style="display:block;font-weight:600;margin-bottom:4px;">Confirm New Password</label>
                                <input type="password" name="confirm_password" required
                                       style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;box-sizing:border-box;">
                            </div>
                        </div>
                        <div style="margin-top:16px;">
                            <button type="submit" name="bod_change_password" value="1" class="btn btn-outline-secondary">Change Password</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Account Summary -->
        <div class="col-lg-4">
            <div class="card" style="border-radius:12px;">
                <div class="card-body" style="padding:20px;">
                    <div style="text-align:center;padding:20px 0 16px;">
                        <div style="width:72px;height:72px;border-radius:50%;background:linear-gradient(135deg,#0a2647,#1565d8);display:flex;align-items:center;justify-content:center;margin:0 auto 12px;font-size:28px;font-weight:700;color:#fff;">
                            <?php echo strtoupper(substr($owner->owner_name, 0, 1)); ?>
                        </div>
                        <h5 style="margin:0 0 2px;font-weight:700;"><?php echo esc_html($owner->owner_name); ?></h5>
                        <?php if ($owner->business_name) : ?>
                            <p style="color:#888;font-size:13px;margin:0;"><?php echo esc_html($owner->business_name); ?></p>
                        <?php endif; ?>
                    </div>
                    <hr style="margin:12px 0;">
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;text-align:center;">
                        <div style="padding:12px;background:#fff7f0;border-radius:8px;">
                            <div style="font-size:22px;font-weight:700;color:#0a2647;"><?php echo (int) $owner->total_listings_purchased; ?></div>
                            <div style="font-size:11px;color:#888;">Purchased</div>
                        </div>
                        <div style="padding:12px;background:#f0fdf4;border-radius:8px;">
                            <div style="font-size:22px;font-weight:700;color:#16a34a;"><?php echo (int) $owner->available_listing_credits; ?></div>
                            <div style="font-size:11px;color:#888;">Credits</div>
                        </div>
                        <div style="padding:12px;background:#f0f9ff;border-radius:8px;">
                            <div style="font-size:22px;font-weight:700;color:#2563eb;"><?php echo (int) $owner->total_listings_created; ?></div>
                            <div style="font-size:11px;color:#888;">Created</div>
                        </div>
                        <div style="padding:12px;background:#f5f3ff;border-radius:8px;">
                            <div style="font-size:22px;font-weight:700;color:#7c3aed;"><?php echo (int) $owner->total_listings_sold; ?></div>
                            <div style="font-size:11px;color:#888;">Sold</div>
                        </div>
                    </div>
                    <hr style="margin:12px 0;">
                    <div style="font-size:13px;">
                        <div style="display:flex;justify-content:space-between;padding:6px 0;">
                            <span style="color:#888;">Member Since</span>
                            <span><?php echo bod_format_datetime($owner->created_at, 'M Y'); ?></span>
                        </div>
                        <div style="display:flex;justify-content:space-between;padding:6px 0;">
                            <span style="color:#888;">Status</span>
                            <span style="font-weight:600;color:#16a34a;"><?php echo esc_html(ucfirst($owner->approval_status)); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
