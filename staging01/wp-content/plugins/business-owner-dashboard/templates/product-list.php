<?php
/**
 * Business Owner — My Listings view
 */
defined('ABSPATH') || exit;

$owner    = bod_get_current_owner();
$listings = $owner ? bod_get_listings_by_owner($owner->id) : [];

$filter_status = sanitize_key($_GET['status'] ?? '');
if ($filter_status) {
    $listings = array_filter($listings, fn($l) => $l->listing_status === $filter_status);
}
?>

<div class="bod-listings-page">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
        <div>
            <h4 style="margin:0;font-weight:700;">My Listings</h4>
            <p style="margin:4px 0 0;color:#888;font-size:13px;"><?php echo count($listings); ?> listing(s)</p>
        </div>
        <?php if (($owner->available_listing_credits ?? 0) > 0) : ?>
            <a href="?view=add-listing" class="btn btn-primary">
                <i class="ti ti-plus me-2"></i>Add New Listing
            </a>
        <?php else : ?>
            <a href="?view=subscription" class="btn btn-outline-primary">
                <i class="ti ti-shopping-cart me-2"></i>Buy Listing Credit
            </a>
        <?php endif; ?>
    </div>

    <!-- Status Filter Tabs -->
    <div style="display:flex;gap:8px;margin-bottom:20px;flex-wrap:wrap;">
        <?php
        $tabs = ['', 'active', 'draft', 'credit', 'sold'];
        $tab_labels = ['' => 'All', 'active' => 'Active', 'draft' => 'Draft', 'credit' => 'Credits', 'sold' => 'Sold'];
        foreach ($tabs as $tab) :
            $active_tab = ($filter_status === $tab);
        ?>
            <a href="?view=listings<?php echo $tab ? '&status=' . $tab : ''; ?>"
               style="padding:6px 16px;border-radius:20px;font-size:13px;font-weight:500;text-decoration:none;
               <?php echo $active_tab ? 'background:#0a2647;color:#fff;' : 'background:#f5f5f5;color:#555;'; ?>">
                <?php echo esc_html($tab_labels[$tab]); ?>
            </a>
        <?php endforeach; ?>
    </div>

    <?php if (empty($listings)) : ?>
        <div class="card" style="border-radius:12px;">
            <div class="card-body" style="text-align:center;padding:60px 20px;">
                <i class="ti ti-list" style="font-size:48px;color:#ddd;display:block;margin-bottom:16px;"></i>
                <h5 style="color:#666;">No listings found</h5>
                <?php if (!$filter_status && ($owner->available_listing_credits ?? 0) > 0) : ?>
                    <p style="color:#888;">You have <?php echo (int) $owner->available_listing_credits; ?> listing credit(s). Create your first listing!</p>
                    <a href="?view=add-listing" class="btn btn-primary">Create Listing</a>
                <?php elseif (!$filter_status) : ?>
                    <p style="color:#888;">Purchase a listing credit to get started.</p>
                    <a href="?view=subscription" class="btn btn-primary">Buy Listing</a>
                <?php endif; ?>
            </div>
        </div>
    <?php else : ?>
        <div class="row g-3">
        <?php foreach ($listings as $listing) :
            $product    = $listing->wp_product_id ? get_post($listing->wp_product_id) : null;
            $title      = $listing->product_title ?: ($product ? $product->post_title : 'Listing #' . $listing->id);
            $thumb_url  = $listing->wp_product_id ? get_the_post_thumbnail_url($listing->wp_product_id, 'medium') : '';
            $view_url   = $listing->wp_product_id ? get_permalink($listing->wp_product_id) : '#';
            $edit_url   = '?view=listing-detail&listing_id=' . $listing->id;

            $status_cfg = [
                'active' => ['#16a34a', 'Active'],
                'draft'  => ['#d97706', 'Draft'],
                'credit' => ['#2563eb', 'Credit Ready'],
                'sold'   => ['#6b7280', 'Sold'],
            ];
            [$status_color, $status_label] = $status_cfg[$listing->listing_status] ?? ['#6b7280', ucfirst($listing->listing_status)];
        ?>
            <div class="col-md-6 col-xl-4">
                <div class="card" style="border-radius:12px;height:100%;">
                    <?php if ($thumb_url) : ?>
                        <img src="<?php echo esc_url($thumb_url); ?>" alt="<?php echo esc_attr($title); ?>"
                             style="width:100%;height:180px;object-fit:cover;border-radius:12px 12px 0 0;">
                    <?php else : ?>
                        <div style="height:140px;background:linear-gradient(135deg,#0a2647,#1565d8);border-radius:12px 12px 0 0;display:flex;align-items:center;justify-content:center;">
                            <i class="ti ti-photo-off" style="font-size:40px;color:rgba(255,255,255,0.5);"></i>
                        </div>
                    <?php endif; ?>

                    <div class="card-body" style="padding:16px;">
                        <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:8px;">
                            <h6 style="margin:0;font-weight:700;font-size:14px;flex:1;margin-right:8px;"><?php echo esc_html($title); ?></h6>
                            <span style="display:inline-block;padding:2px 8px;border-radius:20px;font-size:11px;font-weight:600;color:<?php echo $status_color; ?>;background:<?php echo $status_color; ?>1a;white-space:nowrap;">
                                <?php echo esc_html($status_label); ?>
                            </span>
                        </div>

                        <?php if ($listing->active_boost && $listing->active_boost !== 'none') : ?>
                            <div style="margin-bottom:8px;">
                                <span style="display:inline-block;padding:2px 8px;border-radius:4px;font-size:11px;font-weight:600;background:#fff3e0;color:#0a2647;">
                                    ★ <?php echo esc_html(ucfirst($listing->active_boost)); ?> Boost
                                </span>
                            </div>
                        <?php endif; ?>

                        <div style="font-size:12px;color:#888;margin-bottom:12px;">
                            Created: <?php echo bod_format_datetime($listing->created_at, 'M j, Y'); ?>
                        </div>

                        <div style="display:flex;gap:8px;flex-wrap:wrap;">
                            <a href="<?php echo esc_url($edit_url); ?>" class="btn btn-sm btn-outline-primary" style="flex:1;text-align:center;">
                                <i class="ti ti-edit me-1"></i>Manage
                            </a>
                            <?php if ($listing->listing_status === 'active' && $view_url !== '#') : ?>
                                <a href="<?php echo esc_url($view_url); ?>" target="_blank" class="btn btn-sm btn-outline-secondary" style="text-align:center;">
                                    <i class="ti ti-external-link"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
