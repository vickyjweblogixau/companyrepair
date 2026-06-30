<?php
defined('ABSPATH') || exit;
$owner = bod_get_current_owner();
$enquiries = $owner ? bod_get_owner_enquiries($owner->id, 3) : [];
$enquiry_total = $owner ? count(bod_get_owner_enquiries($owner->id, 1000)) : 0;
?>
<div class="tfx-page-heading-row">
    <div>
        <h3 class="tfx-dashboard-title">Dashboard</h3>
        <p class="tfx-dashboard-subtitle">Here's what's happening with your business today.</p>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-12 col-sm-6 col-xl">
        <div class="tfx-stat-card">
            <div class="tfx-stat-icon tfx-green-bg"><i class="bi bi-chat-dots"></i></div>
            <div>
                <p class="tfx-stat-label">Enquiries</p>
                <h4 class="tfx-stat-number"><?php echo (int) $enquiry_total; ?></h4>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl">
        <div class="tfx-stat-card">
            <div class="tfx-stat-icon tfx-blue-bg"><i class="bi bi-card-checklist"></i></div>
            <div>
                <p class="tfx-stat-label">Listing Credits</p>
                <h4 class="tfx-stat-number"><?php echo (int) ($owner->available_listing_credits ?? 0); ?></h4>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-12 col-lg-6 col-xl-3">
        <div class="tfx-dashboard-card">
            <div class="tfx-card-title-row">
                <h4 class="tfx-card-title">Recent Enquiries</h4>
                <a href="?view=enquiries" class="tfx-view-all-link">View all</a>
            </div>
            <?php if (empty($enquiries)) : ?>
                <p class="text-muted py-3">No enquiries yet.</p>
            <?php else : foreach ($enquiries as $i => $e) :
                $initials = bod_enquiry_initials($e->name ?: 'Anon');
            ?>
            <div class="tfx-enquiry-row">
                <div class="tfx-enquiry-avatar <?php echo esc_attr(bod_enquiry_avatar_class($i)); ?>"><?php echo esc_html($initials); ?></div>
                <div class="tfx-enquiry-info">
                    <p class="tfx-enquiry-name"><?php echo esc_html($e->name ?: 'Anonymous'); ?></p>
                    <p class="tfx-enquiry-message"><?php echo esc_html($e->message); ?></p>
                </div>
                <div class="tfx-enquiry-status-box">
                    <span class="tfx-enquiry-date"><?php echo esc_html(date('d M Y', strtotime($e->created_at))); ?></span>
                    <span class="tfx-status-text <?php echo esc_attr(bod_enquiry_status_class($e->status)); ?>"><?php echo esc_html(bod_enquiry_status_label($e->status)); ?></span>
                </div>
            </div>
            <?php endforeach; endif; ?>
        </div>
    </div>
</div>