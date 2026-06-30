<?php
defined('ABSPATH') || exit;

if (!is_user_logged_in()) {
    wp_redirect(home_url('/business-owner-login/'));
    exit;
}

$current_user = wp_get_current_user();
$owner        = bod_get_current_owner();
$owner_name   = $owner ? $owner->owner_name : $current_user->display_name;
$business_name= $owner ? ($owner->business_name ?: $owner_name) : $owner_name;
$initials     = strtoupper(substr($business_name, 0, 1) . (strpos($business_name, ' ') ? substr(strstr($business_name, ' '), 1, 1) : ''));
$current_view = sanitize_key($_GET['view'] ?? 'dashboard');
$page_title   = $page_title ?? 'Dashboard';

$nav_items = [
    'dashboard'    => ['icon' => 'bi-grid-1x2-fill',     'label' => 'Dashboard'],
    'profile'      => ['icon' => 'bi-person-badge',      'label' => 'Business Profile'],
    'listings'     => ['icon' => 'bi-card-checklist',    'label' => 'My Listings'],
    'add-listing'  => ['icon' => 'bi-plus-circle',       'label' => 'Add Listing'],
    'enquiries'    => ['icon' => 'bi-chat-left-text',    'label' => 'Enquiries'],
    'invoices'     => ['icon' => 'bi-file-earmark-text', 'label' => 'Invoices'],
    'subscription' => ['icon' => 'bi-credit-card',       'label' => 'Billing & Plans'],
];

// Live count for enquiries nav badge
$enquiry_count = 0;
if ($owner) {
    $all_enq = bod_get_owner_enquiries($owner->id);
    $enquiry_count = count(array_filter($all_enq, fn($e) => $e->status === 'new'));
}
?>
<div class="tfx-main-wrapper" id="tfxMainWrapper">
<div class="tfx-screen-overlay" id="tfxScreenOverlay"></div>

<aside class="tfx-sidebar-panel" id="tfxSidebarPanel">
    <div class="tfx-logo-area">
        <div class="tfx-logo-wrap">
            <?php $logo = get_option('site_logo'); if ($logo) : ?>
                <img src="<?php echo esc_url(wp_get_attachment_url($logo)); ?>" class="tfx-logo" alt="<?php bloginfo('name'); ?>">
            <?php else : ?>
                <div class="tfx-logo-fallback">
                    <i class="fa-solid fa-screwdriver-wrench"></i>
                    <span><?php echo esc_html(get_bloginfo('name')); ?></span>
                </div>
            <?php endif; ?>
        </div>
        <button class="tfx-sidebar-close-btn" id="tfxSidebarCloseBtn" type="button"><i class="bi bi-x-lg"></i></button>
    </div>

    <nav class="tfx-navigation-area">
        <?php foreach ($nav_items as $view => $item) :
            $active = ($current_view === $view) ? ' tfx-navigation-active' : '';
        ?>
        <a href="?view=<?php echo esc_attr($view); ?>" class="tfx-navigation-link<?php echo $active; ?>">
            <i class="bi <?php echo esc_attr($item['icon']); ?>"></i>
            <?php echo esc_html($item['label']); ?>
            <?php if ($view === 'enquiries' && $enquiry_count > 0) : ?>
                <span class="tfx-alert-count"><?php echo (int) $enquiry_count; ?></span>
            <?php endif; ?>
        </a>
        <?php endforeach; ?>
    </nav>
</aside>

<main class="tfx-content-board">
    <header class="tfx-top-navbar">
        <div class="tfx-navbar-left">
            <button class="tfx-sidebar-toggle-btn" id="tfxSidebarToggleBtn"><i class="bi bi-list"></i></button>
            <div>
                <div class="tfx-welcome-label">Welcome back,</div>
                <h2 class="tfx-business-name"><?php echo esc_html($business_name); ?></h2>
            </div>
        </div>
        <div class="tfx-navbar-actions">
            <div class="tfx-user-dropdown-wrapper">
                <button class="tfx-user-profile-box" id="tfxUserDropdownBtn" type="button">
                    <div class="tfx-user-avatar"><?php echo esc_html($initials); ?></div>
                    <div class="tfx-user-text-box">
                        <p class="tfx-user-title"><?php echo esc_html($business_name); ?></p>
                        <span class="tfx-user-role">Business Account</span>
                    </div>
                    <i class="bi bi-chevron-down small"></i>
                </button>
                <div class="tfx-user-dropdown-menu" id="tfxUserDropdownMenu">
                    <a href="?view=profile"><i class="bi bi-person"></i> View Profile</a>
                    <a href="<?php echo wp_logout_url(home_url('/')); ?>"><i class="bi bi-box-arrow-right"></i> Logout</a>
                </div>
            </div>
        </div>
    </header>

    <section class="tfx-dashboard-content">