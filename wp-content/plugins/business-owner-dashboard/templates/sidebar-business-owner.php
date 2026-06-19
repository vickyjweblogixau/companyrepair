<?php
/**
 * Business Owner Dashboard — Sidebar / Shell
 * Mirrors private-seller-dashboard design with CRS orange theme
 */
defined('ABSPATH') || exit;

if (!is_user_logged_in()) {
    wp_redirect(home_url('/business-owner-login/'));
    exit;
}

$current_user = wp_get_current_user();
$owner        = bod_get_current_owner();

$owner_name    = $owner ? $owner->owner_name  : $current_user->display_name;
$business_name = $owner ? ($owner->business_name ?: $owner_name) : $owner_name;
$current_view  = sanitize_key($_GET['view'] ?? 'dashboard');
$page_title    = $page_title ?? 'Dashboard';

$nav_items = [
    'dashboard'    => ['icon' => 'ti ti-layout-dashboard', 'label' => 'Dashboard',   'url' => '?view=dashboard'],
    'listings'     => ['icon' => 'ti ti-list',             'label' => 'My Listings',  'url' => '?view=listings'],
    'add-listing'  => ['icon' => 'ti ti-plus',             'label' => 'Add Listing',  'url' => '?view=add-listing'],
    'enquiries'    => ['icon' => 'ti ti-mail',             'label' => 'Enquiries',    'url' => '?view=enquiries'],
    'invoices'     => ['icon' => 'ti ti-file-invoice',     'label' => 'Invoices',     'url' => '?view=invoices'],
    'profile'      => ['icon' => 'ti ti-user',             'label' => 'Profile',      'url' => '?view=profile'],
    'subscription' => ['icon' => 'ti ti-credit-card',      'label' => 'Billing',      'url' => '?view=subscription'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo esc_html($page_title); ?> — <?php echo get_bloginfo('name'); ?></title>
    <link rel="stylesheet" href="<?php echo BOD_PLUGIN_URL; ?>assets/css/style.css">
    <link rel="stylesheet" href="<?php echo BOD_PLUGIN_URL; ?>assets/css/new-dashboard.css">
    <link rel="stylesheet" href="<?php echo BOD_PLUGIN_URL; ?>assets/css/responsive.css">
    <link rel="stylesheet" href="<?php echo BOD_PLUGIN_URL; ?>assets/css/crs-theme.css">
    <?php wp_head(); ?>
    <style>
        #wpadminbar { display: none !important; }
        html { margin-top: 0 !important; }
    </style>
</head>
<body class="ltr light">

<div id="app-layout" class="app-layout">

    <!-- ===== SIDEBAR ===== -->
    <nav id="sidebar" class="sidebar">
        <div class="sidebar-header">
            <a href="<?php echo home_url('/'); ?>" class="sidebar-brand">
                <?php
                $logo = get_option('site_logo');
                if ($logo) : ?>
                    <img src="<?php echo esc_url(wp_get_attachment_url($logo)); ?>" alt="<?php echo get_bloginfo('name'); ?>" style="max-height:40px;">
                <?php else : ?>
                    <span class="sidebar-brand-text"><?php echo get_bloginfo('name'); ?></span>
                <?php endif; ?>
            </a>
            <button class="sidebar-toggle" id="sidebar-toggle" aria-label="Toggle sidebar">
                <i class="ti ti-menu-2"></i>
            </button>
        </div>

        <!-- User Info -->
        <div class="sidebar-user">
            <div class="sidebar-user-avatar">
                <?php echo strtoupper(substr($owner_name, 0, 1)); ?>
            </div>
            <div class="sidebar-user-info">
                <div class="sidebar-user-name"><?php echo esc_html($owner_name); ?></div>
                <?php if ($business_name && $business_name !== $owner_name) : ?>
                    <div class="sidebar-user-role"><?php echo esc_html($business_name); ?></div>
                <?php else : ?>
                    <div class="sidebar-user-role">Business Owner</div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Navigation -->
        <ul class="main-nav">
            <?php foreach ($nav_items as $view => $item) :
                $is_active = ($current_view === $view);
            ?>
            <li class="<?php echo $is_active ? 'active' : ''; ?>">
                <a href="<?php echo esc_url($item['url']); ?>">
                    <i class="<?php echo esc_attr($item['icon']); ?>"></i>
                    <span><?php echo esc_html($item['label']); ?></span>
                </a>
            </li>
            <?php endforeach; ?>
        </ul>

        <!-- Quick Listing Credit Counter -->
        <?php if ($owner) : ?>
        <div class="sidebar-credits" style="margin:16px 12px;padding:12px;background:rgba(249,115,22,0.1);border-radius:8px;border:1px solid rgba(249,115,22,0.2);">
            <div style="font-size:11px;text-transform:uppercase;letter-spacing:0.05em;color:#666;margin-bottom:4px;">Listing Credits</div>
            <div style="font-size:24px;font-weight:700;color:#f97316;"><?php echo (int) $owner->available_listing_credits; ?></div>
            <div style="font-size:12px;color:#888;">available</div>
            <?php if ($owner->available_listing_credits === 0 || $owner->available_listing_credits === '0') : ?>
                <a href="?view=subscription" style="display:block;margin-top:8px;padding:6px;background:#f97316;color:#fff;border-radius:4px;text-align:center;font-size:12px;font-weight:600;text-decoration:none;">Buy Listing</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Sidebar Footer -->
        <div class="sidebar-footer">
            <a href="<?php echo wp_logout_url(home_url('/')); ?>" class="sidebar-logout">
                <i class="ti ti-logout"></i>
                <span>Logout</span>
            </a>
        </div>
    </nav>

    <!-- ===== MAIN CONTENT ===== -->
    <div class="main-content" id="main-content">

        <!-- Top Bar -->
        <div class="topbar">
            <button class="topbar-toggle" id="topbar-toggle">
                <i class="ti ti-menu-2"></i>
            </button>
            <div class="topbar-title"><?php echo esc_html($page_title); ?></div>
            <div class="topbar-actions">
                <?php if ($owner && $owner->available_listing_credits > 0) : ?>
                    <a href="?view=add-listing" class="btn btn-primary btn-sm">
                        <i class="ti ti-plus"></i> Add Listing
                    </a>
                <?php endif; ?>
                <div class="topbar-user-menu">
                    <span class="topbar-avatar"><?php echo strtoupper(substr($owner_name, 0, 1)); ?></span>
                </div>
            </div>
        </div>

        <!-- Page Content -->
        <div class="page-content">
