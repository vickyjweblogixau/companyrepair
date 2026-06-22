<?php
/**
 * Business Owner — Enquiries View
 * Reads from WP comments (type=enquiry) for the owner's products
 */
defined('ABSPATH') || exit;

$owner    = bod_get_current_owner();
$listings = $owner ? bod_get_listings_by_owner($owner->id) : [];
$product_ids = array_filter(array_map(fn($l) => $l->wp_product_id, $listings));

$enquiries = [];
if (!empty($product_ids)) {
    $enquiries = get_comments([
        'post__in'   => $product_ids,
        'status'     => 'approve',
        'type'       => 'enquiry',
        'number'     => 50,
        'orderby'    => 'comment_date',
        'order'      => 'DESC',
    ]);
    // Fallback: also fetch regular comments if no enquiry types
    if (empty($enquiries)) {
        $enquiries = get_comments([
            'post__in' => $product_ids,
            'status'   => 'approve',
            'number'   => 50,
            'orderby'  => 'comment_date',
            'order'    => 'DESC',
        ]);
    }
}
?>

<div class="bod-enquiries-page">
    <div style="margin-bottom:20px;">
        <h4 style="margin:0;font-weight:700;">Enquiries</h4>
        <p style="margin:4px 0 0;color:#888;font-size:13px;"><?php echo count($enquiries); ?> enquiry/enquiries</p>
    </div>

    <?php if (empty($enquiries)) : ?>
        <div class="card" style="border-radius:12px;">
            <div class="card-body" style="text-align:center;padding:60px 20px;">
                <i class="ti ti-mail-opened" style="font-size:48px;color:#ddd;display:block;margin-bottom:16px;"></i>
                <h5 style="color:#666;">No enquiries yet</h5>
                <p style="color:#888;">When buyers contact you about your listings, they will appear here.</p>
            </div>
        </div>
    <?php else : ?>
        <div style="display:flex;flex-direction:column;gap:12px;">
        <?php foreach ($enquiries as $enquiry) :
            $post   = get_post($enquiry->comment_post_ID);
            $is_new = (int) $enquiry->comment_approved === 1 && get_comment_meta($enquiry->comment_ID, 'bod_read', true) !== '1';
        ?>
            <div class="card" style="border-radius:12px;<?php echo $is_new ? 'border-left:4px solid #0a2647;' : ''; ?>">
                <div class="card-body" style="padding:20px 24px;">
                    <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:12px;">
                        <div>
                            <div style="display:flex;align-items:center;gap:10px;">
                                <div style="width:38px;height:38px;border-radius:50%;background:#0a2647;color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:14px;">
                                    <?php echo strtoupper(substr($enquiry->comment_author, 0, 1)); ?>
                                </div>
                                <div>
                                    <strong style="font-size:15px;"><?php echo esc_html($enquiry->comment_author); ?></strong>
                                    <?php if ($is_new) : ?>
                                        <span style="margin-left:6px;padding:2px 8px;background:#0a2647;color:#fff;border-radius:10px;font-size:10px;font-weight:700;">NEW</span>
                                    <?php endif; ?>
                                    <div style="font-size:12px;color:#888;">
                                        <a href="mailto:<?php echo esc_attr($enquiry->comment_author_email); ?>" style="color:#0a2647;"><?php echo esc_html($enquiry->comment_author_email); ?></a>
                                        <?php if ($enquiry->comment_author_url) : ?>
                                            &nbsp;|&nbsp; <?php echo esc_html($enquiry->comment_author_url); ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div style="text-align:right;font-size:12px;color:#888;">
                            <?php echo esc_html(date('M j, Y g:i a', strtotime($enquiry->comment_date))); ?>
                        </div>
                    </div>

                    <?php if ($post) : ?>
                        <div style="font-size:12px;color:#888;margin-bottom:8px;">
                            Re: <a href="?view=listing-detail&listing_id=<?php
                            // Find listing id from product id
                            global $wpdb;
                            $l = $wpdb->get_var($wpdb->prepare("SELECT id FROM " . BOD_TABLE_LISTINGS . " WHERE wp_product_id = %d LIMIT 1", $post->ID));
                            echo (int) $l;
                            ?>" style="color:#0a2647;"><?php echo esc_html($post->post_title); ?></a>
                        </div>
                    <?php endif; ?>

                    <div style="background:#f9f9f9;border-radius:8px;padding:12px;margin-bottom:12px;font-size:14px;line-height:1.6;">
                        <?php echo nl2br(esc_html($enquiry->comment_content)); ?>
                    </div>

                    <?php
                    $phone = get_comment_meta($enquiry->comment_ID, 'enquiry_phone', true) ?: get_comment_meta($enquiry->comment_ID, 'phone', true);
                    if ($phone) :
                    ?>
                        <div style="font-size:13px;margin-bottom:10px;">
                            <strong>Phone:</strong> <a href="tel:<?php echo esc_attr($phone); ?>" style="color:#0a2647;"><?php echo esc_html($phone); ?></a>
                        </div>
                    <?php endif; ?>

                    <div style="display:flex;gap:8px;">
                        <a href="mailto:<?php echo esc_attr($enquiry->comment_author_email); ?>?subject=Re: <?php echo esc_attr($post ? $post->post_title : 'Your Enquiry'); ?>"
                           class="btn btn-sm btn-primary">
                            <i class="ti ti-mail me-1"></i>Reply by Email
                        </a>
                        <?php if ($phone) : ?>
                            <a href="tel:<?php echo esc_attr($phone); ?>" class="btn btn-sm btn-outline-secondary">
                                <i class="ti ti-phone me-1"></i>Call
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
