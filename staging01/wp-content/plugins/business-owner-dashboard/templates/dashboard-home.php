<?php
/**
 * Dashboard Home — Overview/Stats
 */
defined('ABSPATH') || exit;

$owner    = bod_get_current_owner();
$listings = $owner ? bod_get_listings_by_owner($owner->id) : [];

$active_listings = array_filter($listings, fn($l) => $l->listing_status === 'active');
$draft_listings  = array_filter($listings, fn($l) => $l->listing_status === 'draft');
$sold_listings   = array_filter($listings, fn($l) => $l->listing_status === 'sold');

// Enquiries count (via WP comments or custom table)
$enquiry_count = 0;
if ($owner) {
    global $wpdb;
    $product_ids = array_filter(array_map(fn($l) => $l->wp_product_id, $listings));
    if (!empty($product_ids)) {
        $placeholders  = implode(',', array_fill(0, count($product_ids), '%d'));
        $enquiry_count = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_post_ID IN ($placeholders) AND comment_type = 'enquiry' AND comment_approved = '1'",
            ...$product_ids
        ));
    }
}
?>

<div class="bod-dashboard-home">

    <!-- Stats Row -->
    <div class="row g-3 mb-4">
        <?php
        $stats = [
            ['Available Credits',  $owner->available_listing_credits ?? 0, 'ti ti-credit-card',   '#0a2647'],
            ['Active Listings',    count($active_listings),                 'ti ti-list-check',    '#16a34a'],
            ['Listings Sold',      $owner->total_listings_sold ?? 0,        'ti ti-check',         '#2563eb'],
            ['Enquiries',          $enquiry_count,                          'ti ti-mail-opened',   '#7c3aed'],
        ];
        foreach ($stats as [$label, $value, $icon, $color]) :
        ?>
        <div class="col-sm-6 col-xl-3">
            <div class="card" style="border-radius:12px;border:1px solid #e5e7eb;">
                <div class="card-body" style="padding:20px 24px;">
                    <div style="display:flex;justify-content:space-between;align-items:center;">
                        <div>
                            <div style="font-size:13px;color:#888;font-weight:500;text-transform:uppercase;letter-spacing:0.04em;"><?php echo esc_html($label); ?></div>
                            <div style="font-size:32px;font-weight:700;color:#1a1a1a;margin-top:4px;"><?php echo (int) $value; ?></div>
                        </div>
                        <div style="width:52px;height:52px;border-radius:12px;background:<?php echo $color; ?>1a;display:flex;align-items:center;justify-content:center;">
                            <i class="<?php echo esc_attr($icon); ?>" style="font-size:24px;color:<?php echo $color; ?>;"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Quick Actions -->
    <div class="row g-3 mb-4">
        <div class="col-12">
            <div class="card" style="border-radius:12px;">
                <div class="card-body" style="padding:20px 24px;">
                    <h5 style="margin:0 0 16px;font-weight:700;">Quick Actions</h5>
                    <div style="display:flex;flex-wrap:wrap;gap:10px;">
                        <?php if (($owner->available_listing_credits ?? 0) > 0) : ?>
                            <a href="?view=add-listing" class="btn btn-primary">
                                <i class="ti ti-plus me-2"></i>Create New Listing
                            </a>
                        <?php else : ?>
                            <a href="?view=subscription" class="btn btn-primary">
                                <i class="ti ti-shopping-cart me-2"></i>Buy Listing Credit
                            </a>
                        <?php endif; ?>
                        <a href="?view=listings" class="btn btn-outline-secondary">
                            <i class="ti ti-list me-2"></i>View All Listings
                        </a>
                        <a href="?view=enquiries" class="btn btn-outline-secondary">
                            <i class="ti ti-mail me-2"></i>View Enquiries
                        </a>
                        <a href="?view=profile" class="btn btn-outline-secondary">
                            <i class="ti ti-user me-2"></i>Edit Profile
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Listings -->
    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card" style="border-radius:12px;">
                <div class="card-body" style="padding:20px 24px;">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
                        <h5 style="margin:0;font-weight:700;">Recent Listings</h5>
                        <a href="?view=listings" style="color:#0a2647;font-size:13px;text-decoration:none;">View All →</a>
                    </div>
                    <?php if (empty($listings)) : ?>
                        <div style="text-align:center;padding:40px;color:#888;">
                            <i class="ti ti-list" style="font-size:40px;display:block;margin-bottom:12px;"></i>
                            <p>No listings yet.</p>
                            <?php if (($owner->available_listing_credits ?? 0) > 0) : ?>
                                <a href="?view=add-listing" class="btn btn-primary btn-sm">Create Your First Listing</a>
                            <?php endif; ?>
                        </div>
                    <?php else : ?>
                        <table style="width:100%;border-collapse:collapse;">
                            <thead>
                                <tr style="border-bottom:2px solid #f0f0f0;">
                                    <th style="padding:8px 0;text-align:left;font-weight:600;font-size:13px;color:#666;">Listing</th>
                                    <th style="padding:8px 0;text-align:left;font-weight:600;font-size:13px;color:#666;">Status</th>
                                    <th style="padding:8px 0;text-align:left;font-weight:600;font-size:13px;color:#666;">Boost</th>
                                    <th style="padding:8px 0;text-align:left;font-weight:600;font-size:13px;color:#666;">Date</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach (array_slice($listings, 0, 5) as $listing) :
                                $product = $listing->wp_product_id ? get_post($listing->wp_product_id) : null;
                                $title   = $listing->product_title ?: ($product ? $product->post_title : '(No Title)');
                                $status_colors = ['active' => '#16a34a', 'draft' => '#d97706', 'sold' => '#6b7280', 'credit' => '#2563eb'];
                                $status_color  = $status_colors[$listing->listing_status] ?? '#6b7280';
                            ?>
                                <tr style="border-bottom:1px solid #f5f5f5;">
                                    <td style="padding:10px 0;">
                                        <strong style="font-size:14px;"><?php echo esc_html($title); ?></strong>
                                    </td>
                                    <td style="padding:10px 0;">
                                        <span style="display:inline-block;padding:2px 10px;border-radius:20px;font-size:12px;font-weight:600;color:<?php echo $status_color; ?>;background:<?php echo $status_color; ?>1a;">
                                            <?php echo esc_html(ucfirst($listing->listing_status)); ?>
                                        </span>
                                    </td>
                                    <td style="padding:10px 0;">
                                        <?php echo $listing->active_boost !== 'none' ? '<span style="color:#0a2647;font-weight:600;font-size:12px;">' . esc_html(ucfirst($listing->active_boost)) . '</span>' : '<span style="color:#bbb;">—</span>'; ?>
                                    </td>
                                    <td style="padding:10px 0;font-size:12px;color:#888;">
                                        <?php echo bod_format_datetime($listing->created_at, 'M j, Y'); ?>
                                    </td>
                                    <td style="padding:10px 0;">
                                        <a href="?view=listing-detail&listing_id=<?php echo $listing->id; ?>" style="color:#0a2647;font-size:12px;">View</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Account Info -->
        <div class="col-lg-4">
            <div class="card" style="border-radius:12px;">
                <div class="card-body" style="padding:20px 24px;">
                    <h5 style="margin:0 0 16px;font-weight:700;">Account Info</h5>
                    <div style="display:flex;flex-direction:column;gap:10px;">
                        <div>
                            <div style="font-size:11px;color:#888;text-transform:uppercase;letter-spacing:0.05em;">Name</div>
                            <div style="font-weight:600;"><?php echo esc_html($owner->owner_name ?? '-'); ?></div>
                        </div>
                        <?php if ($owner->business_name) : ?>
                        <div>
                            <div style="font-size:11px;color:#888;text-transform:uppercase;letter-spacing:0.05em;">Business</div>
                            <div style="font-weight:600;"><?php echo esc_html($owner->business_name); ?></div>
                        </div>
                        <?php endif; ?>
                        <div>
                            <div style="font-size:11px;color:#888;text-transform:uppercase;letter-spacing:0.05em;">Email</div>
                            <div><?php echo esc_html($owner->owner_email ?? '-'); ?></div>
                        </div>
                        <div>
                            <div style="font-size:11px;color:#888;text-transform:uppercase;letter-spacing:0.05em;">Phone</div>
                            <div><?php echo esc_html($owner->owner_phone ?? '-'); ?></div>
                        </div>
                        <div>
                            <div style="font-size:11px;color:#888;text-transform:uppercase;letter-spacing:0.05em;">Location</div>
                            <div><?php echo esc_html(($owner->suburb ?? '') . ($owner->state ? ', ' . $owner->state : '')); ?></div>
                        </div>
                        <div>
                            <div style="font-size:11px;color:#888;text-transform:uppercase;letter-spacing:0.05em;">Account Status</div>
                            <div>
                                <span style="display:inline-block;padding:2px 10px;border-radius:20px;font-size:12px;font-weight:600;
                                    <?php echo $owner->approval_status === 'approved' ? 'color:#16a34a;background:#dcfce7;' : 'color:#d97706;background:#fef3c7;'; ?>">
                                    <?php echo esc_html(ucfirst($owner->approval_status ?? 'pending')); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    <a href="?view=profile" style="display:block;margin-top:16px;text-align:center;padding:8px;border:1px solid #0a2647;border-radius:6px;color:#0a2647;text-decoration:none;font-weight:600;font-size:13px;">Edit Profile</a>
                </div>
            </div>
        </div>
    </div>
</div>
