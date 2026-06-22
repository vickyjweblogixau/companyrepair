<?php
/**
 * Business Owner — Listing Detail / Add Listing view
 * For same-domain: creates/edits WooCommerce products locally
 */
defined('ABSPATH') || exit;

$owner      = bod_get_current_owner();
$listing_id = absint($_GET['listing_id'] ?? 0);
$listing    = $listing_id ? bod_get_listing($listing_id) : null;
$is_new     = !$listing;
$product    = ($listing && $listing->wp_product_id) ? get_post($listing->wp_product_id) : null;

// Verify ownership
if ($listing && (int) $listing->owner_id !== (int) ($owner->id ?? 0)) {
    echo '<div class="alert alert-danger">Access denied.</div>';
    return;
}

// Handle save
$save_message = '';
$save_error   = '';
if (!empty($_POST['bod_save_listing']) && check_admin_referer('bod_save_listing_' . ($listing_id ?: 'new'))) {
    $post_title   = sanitize_text_field($_POST['product_title'] ?? '');
    $post_content = wp_kses_post($_POST['product_description'] ?? '');
    $price        = (float) ($_POST['product_price'] ?? 0);
    $category     = sanitize_text_field($_POST['product_category'] ?? '');

    if (empty($post_title)) {
        $save_error = 'Please enter a listing title.';
    } elseif ($is_new && ($owner->available_listing_credits ?? 0) < 1) {
        $save_error = 'No listing credits available. Please purchase a listing credit.';
    } else {
        $post_data = [
            'post_title'   => $post_title,
            'post_content' => $post_content,
            'post_status'  => 'draft',
            'post_type'    => 'product', // WooCommerce product
            'post_author'  => get_current_user_id(),
            'meta_input'   => [
                '_price'              => $price,
                '_regular_price'      => $price,
                '_visibility'         => 'visible',
                '_stock_status'       => 'instock',
                'virtual'             => 'no',
                'bod_owner_id'        => $owner->id,
                'bod_listing_status'  => 'draft',
            ],
        ];

        if ($product) {
            $post_data['ID'] = $product->ID;
            $product_id      = wp_update_post($post_data);
        } else {
            $product_id = wp_insert_post($post_data);
        }

        if (is_wp_error($product_id)) {
            $save_error = $product_id->get_error_message();
        } else {
            // Set WooCommerce product type
            wp_set_object_terms($product_id, 'simple', 'product_type');

            // Handle category
            if ($category) {
                $term = get_term_by('name', $category, 'product_cat');
                if ($term) wp_set_object_terms($product_id, [$term->term_id], 'product_cat');
            }

            // Handle image upload
            if (!empty($_FILES['product_image']['name'])) {
                require_once ABSPATH . 'wp-admin/includes/image.php';
                require_once ABSPATH . 'wp-admin/includes/media.php';
                require_once ABSPATH . 'wp-admin/includes/file.php';
                $attachment_id = media_handle_upload('product_image', $product_id);
                if (!is_wp_error($attachment_id)) {
                    set_post_thumbnail($product_id, $attachment_id);
                }
            }

            global $wpdb;
            if ($is_new) {
                // Create listing DB record, deduct credit
                $wpdb->insert(BOD_TABLE_LISTINGS, [
                    'owner_id'         => $owner->id,
                    'wp_product_id'    => $product_id,
                    'product_title'    => $post_title,
                    'listing_status'   => 'draft',
                    'payment_status'   => 'paid',
                    'product_created_at' => current_time('mysql'),
                    'created_at'       => current_time('mysql'),
                    'updated_at'       => current_time('mysql'),
                ]);
                $new_listing_id = $wpdb->insert_id;

                // Deduct credit
                $wpdb->query($wpdb->prepare(
                    "UPDATE " . BOD_TABLE_OWNERS . " SET available_listing_credits = available_listing_credits - 1, total_listings_created = total_listings_created + 1 WHERE id = %d AND available_listing_credits > 0",
                    $owner->id
                ));

                wp_safe_redirect('?view=listing-detail&listing_id=' . $new_listing_id . '&saved=1');
                exit;
            } else {
                // Update existing listing record
                $wpdb->update(BOD_TABLE_LISTINGS, [
                    'product_title' => $post_title,
                    'updated_at'    => current_time('mysql'),
                ], ['id' => $listing_id]);

                $save_message = 'Listing saved successfully.';
            }
        }
    }
}

// Handle publish
if (!empty($_POST['bod_publish_listing']) && check_admin_referer('bod_publish_listing_' . $listing_id)) {
    if ($listing && $listing->wp_product_id) {
        wp_update_post(['ID' => $listing->wp_product_id, 'post_status' => 'publish']);
        global $wpdb;
        $wpdb->update(BOD_TABLE_LISTINGS, ['listing_status' => 'active', 'listed_at' => current_time('mysql')], ['id' => $listing_id]);
        $save_message = 'Listing published and is now live!';
        $listing = bod_get_listing($listing_id);
    }
}

// Handle mark as sold
if (!empty($_POST['bod_mark_sold']) && check_admin_referer('bod_mark_sold_' . $listing_id)) {
    if ($listing) {
        global $wpdb;
        $wpdb->update(BOD_TABLE_LISTINGS, ['listing_status' => 'sold', 'sold_at' => current_time('mysql'), 'active_boost' => 'none'], ['id' => $listing_id]);
        if ($listing->wp_product_id) update_post_meta($listing->wp_product_id, 'bod_listing_status', 'sold');
        $wpdb->query($wpdb->prepare("UPDATE " . BOD_TABLE_OWNERS . " SET total_listings_sold = total_listings_sold + 1 WHERE id = %d", $owner->id));
        $save_message = 'Listing marked as sold.';
        $listing = bod_get_listing($listing_id);
    }
}

$product_data = $product ? [
    'title'       => $product->post_title,
    'content'     => $product->post_content,
    'price'       => get_post_meta($product->ID, '_price', true),
    'status'      => $product->post_status,
    'thumbnail'   => get_the_post_thumbnail_url($product->ID, 'medium'),
] : ['title' => '', 'content' => '', 'price' => '', 'status' => '', 'thumbnail' => ''];
?>

<div class="bod-listing-detail">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
        <div>
            <h4 style="margin:0;font-weight:700;"><?php echo $is_new ? 'Create New Listing' : 'Manage Listing'; ?></h4>
            <?php if ($listing) : ?>
                <span style="font-size:12px;padding:2px 10px;border-radius:20px;background:#f0f0f0;color:#666;"><?php echo esc_html(ucfirst($listing->listing_status)); ?></span>
            <?php endif; ?>
        </div>
        <a href="?view=listings" style="color:#666;text-decoration:none;font-size:13px;">← Back to Listings</a>
    </div>

    <?php if ($save_message) : ?>
        <div class="alert alert-success"><?php echo esc_html($save_message); ?></div>
    <?php endif; ?>
    <?php if ($save_error) : ?>
        <div class="alert alert-danger"><?php echo esc_html($save_error); ?></div>
    <?php endif; ?>

    <?php if ($is_new && ($owner->available_listing_credits ?? 0) < 1) : ?>
        <div class="alert alert-warning">
            <strong>No listing credits available.</strong>
            <a href="?view=subscription" style="color:#0a2647;font-weight:600;">Purchase a listing credit</a> to create a listing.
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Main Form -->
        <div class="col-lg-8">
            <div class="card" style="border-radius:12px;">
                <div class="card-body" style="padding:24px;">
                    <form method="post" enctype="multipart/form-data">
                        <?php wp_nonce_field('bod_save_listing_' . ($listing_id ?: 'new')); ?>

                        <div style="margin-bottom:18px;">
                            <label style="display:block;font-weight:600;margin-bottom:6px;">Listing Title <span style="color:red;">*</span></label>
                            <input type="text" name="product_title" required
                                   value="<?php echo esc_attr($product_data['title']); ?>"
                                   style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;font-size:15px;box-sizing:border-box;"
                                   placeholder="e.g., 2019 Jayco Outback Camper">
                        </div>

                        <div style="margin-bottom:18px;">
                            <label style="display:block;font-weight:600;margin-bottom:6px;">Description</label>
                            <?php
                            wp_editor($product_data['content'], 'product_description', [
                                'media_buttons' => false,
                                'textarea_rows' => 8,
                                'quicktags'     => true,
                                'tinymce'       => ['toolbar1' => 'bold,italic,underline,bullist,numlist,link,unlink'],
                            ]);
                            ?>
                        </div>

                        <div style="margin-bottom:18px;">
                            <label style="display:block;font-weight:600;margin-bottom:6px;">Asking Price (AUD)</label>
                            <div style="display:flex;align-items:center;gap:8px;">
                                <span style="font-weight:600;color:#666;">$</span>
                                <input type="number" name="product_price" min="0" step="0.01"
                                       value="<?php echo esc_attr($product_data['price']); ?>"
                                       style="width:180px;padding:10px;border:1px solid #ddd;border-radius:6px;font-size:15px;"
                                       placeholder="0.00">
                                <span style="color:#888;font-size:13px;">Leave blank for POA</span>
                            </div>
                        </div>

                        <div style="margin-bottom:18px;">
                            <label style="display:block;font-weight:600;margin-bottom:6px;">Category</label>
                            <?php
                            $categories = get_terms(['taxonomy' => 'product_cat', 'hide_empty' => false]);
                            $selected_cat = $product ? wp_get_post_terms($product->ID, 'product_cat', ['fields' => 'names'])[0] ?? '' : '';
                            ?>
                            <select name="product_category" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;font-size:15px;">
                                <option value="">— Select Category —</option>
                                <?php foreach ($categories as $cat) : ?>
                                    <option value="<?php echo esc_attr($cat->name); ?>" <?php selected($selected_cat, $cat->name); ?>>
                                        <?php echo esc_html($cat->name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div style="margin-bottom:24px;">
                            <label style="display:block;font-weight:600;margin-bottom:6px;">Main Image</label>
                            <?php if ($product_data['thumbnail']) : ?>
                                <img src="<?php echo esc_url($product_data['thumbnail']); ?>" style="max-width:200px;border-radius:8px;margin-bottom:10px;display:block;">
                            <?php endif; ?>
                            <input type="file" name="product_image" accept="image/*" style="font-size:14px;">
                            <p style="font-size:12px;color:#888;margin-top:4px;">Upload a high-quality image (JPG, PNG recommended). Max 5MB.</p>
                        </div>

                        <div style="display:flex;gap:10px;">
                            <button type="submit" name="bod_save_listing" value="1" class="btn btn-primary">Save Draft</button>
                            <?php if ($listing && $listing->listing_status === 'draft') : ?>
                                <button type="submit" name="bod_publish_listing" value="1"
                                        formaction="<?php echo esc_url(add_query_arg(['view' => 'listing-detail', 'listing_id' => $listing_id])); ?>"
                                        class="btn btn-success"
                                        onclick="this.form.action=this.formAction;"
                                        style="background:#16a34a;border-color:#16a34a;">Publish Listing</button>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Sidebar Actions -->
        <div class="col-lg-4">
            <?php if ($listing) : ?>
                <!-- Status Card -->
                <div class="card" style="border-radius:12px;margin-bottom:16px;">
                    <div class="card-body" style="padding:20px;">
                        <h6 style="font-weight:700;margin:0 0 12px;">Listing Status</h6>

                        <?php if ($listing->listing_status === 'active') : ?>
                            <div style="padding:12px;background:#dcfce7;border-radius:8px;text-align:center;margin-bottom:12px;">
                                <i class="ti ti-check-circle" style="font-size:24px;color:#16a34a;display:block;"></i>
                                <strong style="color:#16a34a;">Live & Active</strong>
                            </div>
                            <?php if ($listing->wp_product_id) : ?>
                                <a href="<?php echo esc_url(get_permalink($listing->wp_product_id)); ?>" target="_blank"
                                   class="btn btn-outline-secondary btn-sm" style="display:block;text-align:center;margin-bottom:8px;">
                                    <i class="ti ti-external-link me-1"></i>View Listing
                                </a>
                            <?php endif; ?>
                            <!-- Mark as Sold -->
                            <form method="post">
                                <?php wp_nonce_field('bod_mark_sold_' . $listing_id); ?>
                                <button type="submit" name="bod_mark_sold" value="1"
                                        class="btn btn-outline-danger btn-sm" style="width:100%;"
                                        onclick="return confirm('Mark this listing as sold?');">
                                    Mark as Sold
                                </button>
                            </form>

                        <?php elseif ($listing->listing_status === 'draft') : ?>
                            <div style="padding:12px;background:#fef3c7;border-radius:8px;text-align:center;margin-bottom:12px;">
                                <i class="ti ti-pencil" style="font-size:24px;color:#d97706;display:block;"></i>
                                <strong style="color:#d97706;">Draft — Not Published</strong>
                            </div>
                            <form method="post">
                                <?php wp_nonce_field('bod_publish_listing_' . $listing_id); ?>
                                <button type="submit" name="bod_publish_listing" value="1"
                                        class="btn btn-success btn-sm" style="width:100%;background:#16a34a;border-color:#16a34a;">
                                    <i class="ti ti-send me-1"></i>Publish Listing
                                </button>
                            </form>

                        <?php elseif ($listing->listing_status === 'sold') : ?>
                            <div style="padding:12px;background:#f3f4f6;border-radius:8px;text-align:center;">
                                <i class="ti ti-check" style="font-size:24px;color:#6b7280;display:block;"></i>
                                <strong style="color:#6b7280;">Sold</strong>
                            </div>

                        <?php elseif ($listing->listing_status === 'credit') : ?>
                            <div style="padding:12px;background:#dbeafe;border-radius:8px;text-align:center;margin-bottom:12px;">
                                <i class="ti ti-credit-card" style="font-size:24px;color:#2563eb;display:block;"></i>
                                <strong style="color:#2563eb;">Credit Ready</strong>
                                <p style="font-size:12px;color:#3b82f6;margin:4px 0 0;">Fill in listing details and save as draft</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Boost Card -->
                <?php if ($listing->listing_status === 'active') : ?>
                <div class="card" style="border-radius:12px;">
                    <div class="card-body" style="padding:20px;">
                        <h6 style="font-weight:700;margin:0 0 8px;">Boost Your Listing</h6>
                        <p style="font-size:12px;color:#888;margin:0 0 12px;">Increase visibility with a paid boost add-on.</p>

                        <?php if ($listing->active_boost && $listing->active_boost !== 'none') : ?>
                            <div style="padding:8px 12px;background:#fff3e0;border-radius:6px;margin-bottom:12px;">
                                <strong style="color:#0a2647;">Active: <?php echo esc_html(ucfirst($listing->active_boost)); ?> Boost</strong>
                            </div>
                        <?php endif; ?>

                        <?php
                        $boosts = [
                            'featured'  => ['Featured', BOD_BOOST_FEATURED_DISPLAY,  '#0a2647'],
                            'exclusive' => ['Exclusive', BOD_BOOST_EXCLUSIVE_DISPLAY, '#7c3aed'],
                            'homepage'  => ['Homepage',  BOD_BOOST_HOMEPAGE_DISPLAY,  '#2563eb'],
                        ];
                        foreach ($boosts as $type => [$label, $price, $color]) :
                            if ($listing->active_boost === $type) continue;
                        ?>
                            <button class="btn btn-sm bod-buy-boost"
                                    data-boost="<?php echo $type; ?>"
                                    style="width:100%;margin-bottom:6px;border-color:<?php echo $color; ?>;color:<?php echo $color; ?>;">
                                <?php echo esc_html($label); ?> Boost — $<?php echo number_format($price, 2); ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

            <?php else : ?>
                <!-- New Listing Helper -->
                <div class="card" style="border-radius:12px;">
                    <div class="card-body" style="padding:20px;">
                        <h6 style="font-weight:700;margin:0 0 8px;">Tips for a Great Listing</h6>
                        <ul style="padding-left:18px;color:#555;font-size:13px;line-height:1.8;margin:0;">
                            <li>Use a descriptive, clear title</li>
                            <li>Include year, make, and model</li>
                            <li>Mention key features and condition</li>
                            <li>Upload high-quality photos</li>
                            <li>Set a realistic asking price</li>
                        </ul>
                        <div style="margin-top:12px;padding:10px;background:#fff7f0;border-radius:6px;">
                            <strong style="color:#0a2647;">Credits Available:</strong>
                            <span style="font-size:18px;font-weight:700;color:#0a2647;margin-left:8px;"><?php echo (int) ($owner->available_listing_credits ?? 0); ?></span>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    $('.bod-buy-boost').on('click', function() {
        var boostType = $(this).data('boost');
        $.post(bodSignup.ajaxUrl, {
            action: 'bod_buy_boost',
            boost_type: boostType,
            nonce: '<?php echo wp_create_nonce('bod_buy_boost'); ?>'
        }, function(res) {
            if (res.success) {
                window.location.href = res.data.redirect_url;
            } else {
                alert(res.data.message || 'Error. Please try again.');
            }
        });
    });
});
</script>
