<?php
/**
 * Business Owner Dashboard — Main controller template
 * Routes to sub-views based on ?view= param
 */
defined('ABSPATH') || exit;

if (!is_user_logged_in()) {
    wp_redirect(home_url('/business-owner-login/'));
    exit;
}

$owner = bod_get_current_owner();
if (!$owner && !current_user_can('manage_options')) {
    echo '<div class="alert alert-warning">No business owner account found. Please <a href="' . home_url('/list-your-business/') . '">sign up</a>.</div>';
    return;
}

$current_view = sanitize_key($_GET['view'] ?? 'dashboard');
$allowed_views = ['dashboard', 'listings', 'add-listing', 'enquiries', 'invoices', 'profile', 'subscription', 'listing-detail'];

if (!in_array($current_view, $allowed_views)) $current_view = 'dashboard';

$page_titles = [
    'dashboard'      => 'Dashboard',
    'listings'       => 'My Listings',
    'add-listing'    => 'Add New Listing',
    'enquiries'      => 'Enquiries',
    'invoices'       => 'Invoices',
    'profile'        => 'My Profile',
    'subscription'   => 'Billing & Payments',
    'listing-detail' => 'Listing Details',
];
$page_title = $page_titles[$current_view] ?? 'Dashboard';

// ── Flash Messages ─────────────────────────────────────────────────────────────
$flash = '';
if (!empty($_GET['listing_success'])) $flash = '<div class="alert alert-success">Listing credit added! You can now create a new listing.</div>';
if (!empty($_GET['boost_success']))   $flash = '<div class="alert alert-success">Boost activated successfully!</div>';
if (!empty($_GET['saved']))           $flash = '<div class="alert alert-success">Changes saved.</div>';

// ── Sidebar Shell ─────────────────────────────────────────────────────────────
include BOD_PLUGIN_DIR . 'templates/sidebar-business-owner.php';

// Flash output
if ($flash) echo $flash;

// ── Route to Sub-view ─────────────────────────────────────────────────────────
switch ($current_view) {
    case 'listings':
        include BOD_PLUGIN_DIR . 'templates/product-list.php';
        break;
    case 'add-listing':
        include BOD_PLUGIN_DIR . 'templates/product-detail.php';
        break;
    case 'listing-detail':
        $listing_id = absint($_GET['listing_id'] ?? 0);
        include BOD_PLUGIN_DIR . 'templates/product-detail.php';
        break;
    case 'enquiries':
        include BOD_PLUGIN_DIR . 'templates/enquiries.php';
        break;
    case 'invoices':
        include BOD_PLUGIN_DIR . 'templates/invoice-list-content.php';
        break;
    case 'profile':
        include BOD_PLUGIN_DIR . 'templates/profile.php';
        break;
    case 'subscription':
        include BOD_PLUGIN_DIR . 'templates/subscription.php';
        break;
    default:
        // Dashboard home
        include BOD_PLUGIN_DIR . 'templates/dashboard-home.php';
        break;
}
?>
        </div><!-- .page-content -->
    </div><!-- .main-content -->
</div><!-- #app-layout -->

<?php wp_footer(); ?>

<script>
// Sidebar toggle for mobile
document.addEventListener('DOMContentLoaded', function() {
    var sidebar  = document.getElementById('sidebar');
    var toggles  = document.querySelectorAll('#sidebar-toggle, #topbar-toggle');
    toggles.forEach(function(btn) {
        btn.addEventListener('click', function() {
            sidebar.classList.toggle('open');
        });
    });
    // Close sidebar when clicking outside on mobile
    document.addEventListener('click', function(e) {
        if (window.innerWidth < 768 && !sidebar.contains(e.target) && sidebar.classList.contains('open')) {
            sidebar.classList.remove('open');
        }
    });
});
</script>
</body>
</html>
