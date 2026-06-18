# SiteGround Dashboard Notifications

A complete, reusable dashboard system for SiteGround WordPress plugins. Provides:

1. **Notification Fetching** - Per-site important notifications from SGAPI via Avalon
2. **Admin Page** - Complete SiteGround dashboard with SPA support
3. **REST API** - Comprehensive endpoints for dashboard data

## Features

### Notifications System
- Fetches notifications specific to the current WordPress site
- Automatic caching with WordPress transients (5-minute TTL)
- Bulletproof class loading to prevent conflicts when used across multiple plugins
- Graceful error handling - returns empty array on failures
- Follows the same Avalon Request API pattern used in other SiteGround integrations

### Admin Page
- Self-contained admin menu page under "SiteGround"
- Empty div container for SPA app loading
- Automatic script/style enqueuing
- Localized REST API data for frontend

### REST API
- `/wp-json/siteground-dashboard/v1/dashboard` - Complete dashboard data
- `/wp-json/siteground-dashboard/v1/notifications` - Notifications only
- `/wp-json/siteground-dashboard/v1/notifications/refresh` - Clear cache and refresh
- `/wp-json/siteground-dashboard/v1/site-info` - Site information

## Requirements

- PHP 7.0 or higher
- WordPress environment with access to Avalon socket (`/chroot/tmp/site-tools.sock`)
- SiteGround hosting environment

## Installation

Add this package to your `composer.json`:

```json
{
  "require": {
    "siteground/siteground-dashboard-notifications": "^1.0"
  }
}
```

Then run:

```bash
composer install
```

## Quick Start

### Initialize the Complete Dashboard

In your main plugin file:

```php
require_once __DIR__ . '/vendor/autoload.php';

use SiteGround_Dashboard\Dashboard;

add_action( 'plugins_loaded', function() {
    Dashboard::init( array(
        'plugin_data' => array(
            'name'    => 'Your Plugin Name',
            'version' => '1.0.0',
        ),
    ) );
} );
```

This creates:
- ✅ Admin menu page under "SiteGround"
- ✅ REST API endpoints at `/wp-json/siteground-dashboard/v1/`
- ✅ Empty div (`#siteground-dashboard-root`) ready for your SPA

See [ADMIN_PAGE_INTEGRATION.md](ADMIN_PAGE_INTEGRATION.md) for complete admin page documentation.

## Usage

### Admin Page & REST API

See [ADMIN_PAGE_INTEGRATION.md](ADMIN_PAGE_INTEGRATION.md) for complete documentation on:
- Creating the admin page
- Using REST API endpoints
- Building SPA frontends
- Customization options

### Notifications API Only

If you only need the notifications API without the admin page:

```php
use SiteGround_Dashboard\Notifications;

// Get notifications (uses cache if available)
$notifications = Notifications::get_notifications();

if ( ! empty( $notifications ) ) {
    foreach ( $notifications as $notification ) {
        // Process each notification
        echo $notification['title'];
        echo $notification['message'];
    }
}
```

### Force Refresh

```php
// Bypass cache and fetch fresh notifications
$notifications = Notifications::get_notifications( true );
```

### Clear Cache

```php
// Clear the cached notifications
Notifications::clear_cache();
```

### Get Site Information

```php
// Get the current site's information used for API calls
$site_info = Notifications::get_site_info();

// Returns:
// array(
//     'id'              => 123,
//     'domain_name'     => 'example.com',
//     'server_hostname' => 'server123.siteground.com',
//     'site_id'         => 'site_name',
//     'bundle_id'       => 'bundle_identifier',
// )
```

## Integration Example

### REST API Endpoint

```php
namespace YourPlugin\Rest;

use SiteGround_Dashboard\Notifications;

class Rest_Dashboard {
    
    public function get_dashboard_data() {
        $data = array(
            'notices' => $this->get_notices_data(),
            // ... other dashboard data
        );
        
        return rest_ensure_response( $data );
    }
    
    public function get_notices_data() {
        // Get notifications from SGAPI
        $notifications = Notifications::get_notifications();
        
        // Return empty object if no notifications
        if ( empty( $notifications ) ) {
            return (object) array();
        }
        
        // Format notifications for frontend
        return array(
            'section_title' => __( 'Important Notifications', 'your-plugin' ),
            'notifications' => $notifications,
        );
    }
}
```

### Conditional Rendering

```php
// Only show notifications section if notifications exist
$notifications = Notifications::get_notifications();

if ( ! empty( $notifications ) ) {
    // Render notifications UI
    include 'templates/notifications.php';
}
```

## API Details

### Avalon Request API

The library calls the Avalon socket using the `request-perform` API with `api_name=wp-notifications`:

```php
array(
    'api'      => 'request-perform',
    'cmd'      => 'create',
    'settings' => array( 'json' => 1 ),
    'params'   => array(
        'api_name'       => 'wp-notifications',
        'request_params' => array(
            'id'              => <app_id>,
            'domain_name'     => '<domain_name>',
            'server_hostname' => '<server_hostname>',
            'site_id'         => '<site_id>',
            'bundle_id'       => '<bundle_id>',
        ),
    ),
)
```

The exact SGAPI endpoint is configured on the Avalon side. The WordPress side only needs to call `request-perform` with the correct `api_name` and parameters.

## Error Handling

The library handles errors gracefully:

- **Socket unavailable**: Returns empty array
- **API call fails**: Returns empty array
- **Timeout**: Returns empty array (5-second timeout)
- **Invalid response**: Returns empty array

No exceptions are thrown, ensuring the dashboard doesn't break if notifications are unavailable.

## Caching

Notifications are cached using WordPress transients:

- **Cache key**: `sg_dashboard_notifications`
- **Expiration**: 300 seconds (5 minutes)
- **Bypass cache**: Pass `true` to `get_notifications()` to force refresh
- **Clear cache**: Call `Notifications::clear_cache()` to manually clear

This prevents hammering the socket on rapid page reloads while ensuring notifications stay relatively fresh.

## Class Loading Safety

The library uses multiple safety mechanisms to prevent conflicts:

1. `class_exists()` check before class definition
2. Namespace isolation (`SiteGround_Dashboard\`)
3. Returns early if class already exists

This allows multiple plugins to safely include the package without conflicts.

## License

Proprietary - SiteGround

## Support

For issues or questions, contact the SiteGround WordPress team.
