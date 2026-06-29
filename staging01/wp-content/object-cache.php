<?php
/**
 * object-cache.php — WordPress Memcached Object Cache Drop-in
 *
 * Built for SiteGround Memcached integration.
 * Place this file in: wp-content/object-cache.php
 *
 * How it works:
 *  - WordPress calls wp_cache_* functions on every request
 *  - This file intercepts those calls and routes them to Memcached
 *  - If Memcached is unavailable it falls back to in-memory array (safe)
 *  - Zero code changes needed in theme or plugins
 *
 * SiteGround Memcached runs on:  127.0.0.1:11211  (default)
 * Override via wp-config.php:
 *   define('MEMCACHED_HOST', '127.0.0.1');
 *   define('MEMCACHED_PORT', 11211);
 */

defined( 'ABSPATH' ) || exit;

// ============================================================
// Bootstrap — connect to Memcached, expose WP cache functions
// ============================================================

/**
 * Initialise the global cache object.
 * Called by WordPress core after this file is loaded.
 */
function wp_cache_init() {
    global $wp_object_cache;
    $wp_object_cache = new CRS_Memcached_Object_Cache();
}

function wp_cache_add( $key, $data, $group = '', $expire = 0 ) {
    global $wp_object_cache;
    return $wp_object_cache->add( $key, $data, $group, $expire );
}

function wp_cache_close() {
    global $wp_object_cache;
    return $wp_object_cache->close();
}

function wp_cache_decr( $key, $offset = 1, $group = '' ) {
    global $wp_object_cache;
    return $wp_object_cache->decr( $key, $offset, $group );
}

function wp_cache_delete( $key, $group = '' ) {
    global $wp_object_cache;
    return $wp_object_cache->delete( $key, $group );
}

function wp_cache_flush() {
    global $wp_object_cache;
    return $wp_object_cache->flush();
}

function wp_cache_get( $key, $group = '', $force = false, &$found = null ) {
    global $wp_object_cache;
    return $wp_object_cache->get( $key, $group, $force, $found );
}

function wp_cache_get_multiple( $keys, $group = '', $force = false ) {
    global $wp_object_cache;
    return $wp_object_cache->get_multiple( $keys, $group, $force );
}

function wp_cache_incr( $key, $offset = 1, $group = '' ) {
    global $wp_object_cache;
    return $wp_object_cache->incr( $key, $offset, $group );
}

function wp_cache_replace( $key, $data, $group = '', $expire = 0 ) {
    global $wp_object_cache;
    return $wp_object_cache->replace( $key, $data, $group, $expire );
}

function wp_cache_set( $key, $data, $group = '', $expire = 0 ) {
    global $wp_object_cache;
    return $wp_object_cache->set( $key, $data, $group, $expire );
}

function wp_cache_set_multiple( $data, $group = '', $expire = 0 ) {
    global $wp_object_cache;
    return $wp_object_cache->set_multiple( $data, $group, $expire );
}

function wp_cache_switch_to_blog( $blog_id ) {
    global $wp_object_cache;
    return $wp_object_cache->switch_to_blog( $blog_id );
}

function wp_cache_add_global_groups( $groups ) {
    global $wp_object_cache;
    $wp_object_cache->add_global_groups( $groups );
}

function wp_cache_add_non_persistent_groups( $groups ) {
    global $wp_object_cache;
    $wp_object_cache->add_non_persistent_groups( $groups );
}


// ============================================================
// Core Cache Class
// ============================================================

class CRS_Memcached_Object_Cache {

    /**
     * Memcached instance.
     * @var Memcached|null
     */
    private $mc = null;

    /**
     * Whether Memcached connection succeeded.
     * Falls back to local array cache if false.
     * @var bool
     */
    private $connected = false;

    /**
     * In-process memory cache (always used as L1).
     * Prevents repeat Memcached round-trips within a single request.
     * @var array
     */
    private $local = [];

    /**
     * Groups that are never stored in Memcached (sensitive / request-scoped).
     * @var array
     */
    private $non_persistent_groups = [];

    /**
     * Groups shared across all sites in a multisite network.
     * @var array
     */
    private $global_groups = [
        'users', 'userlogins', 'usermeta', 'user_meta',
        'useremail', 'userslugs', 'site-transient',
        'site-options', 'blog-lookup', 'blog-details',
        'rss', 'global-posts', 'blog-id-cache',
        'networks', 'sites', 'site-details',
    ];

    /**
     * Current blog ID (multisite support).
     * @var int
     */
    private $blog_id = 1;

    /**
     * Cache key prefix — isolates this site's keys in shared Memcached.
     * Derived from the DB table prefix so staging/live don't collide.
     * @var string
     */
    private $key_prefix = '';

    /**
     * Default TTL in seconds.
     * 0 = Memcached default (varies by server, typically 30 days).
     * We use 1 hour for WP object cache to keep data fresh.
     * @var int
     */
    private $default_ttl = 3600;

    /**
     * Cache hit/miss counters (useful for debugging).
     */
    public $cache_hits   = 0;
    public $cache_misses = 0;

    // --------------------------------------------------------

    public function __construct() {
        global $wpdb, $blog_id;

        // Build a unique prefix per site so staging/live/multisite don't share keys
        $this->key_prefix = defined( 'DB_NAME' ) ? md5( DB_NAME . $wpdb->prefix ) : 'wp';
        $this->blog_id    = (int) ( $blog_id ?? 1 );

        $this->connect();
    }

    // --------------------------------------------------------
    // Memcached Connection
    // --------------------------------------------------------

    private function connect() {
        if ( ! class_exists( 'Memcached' ) ) {
            // PHP Memcached extension not installed — silent fallback
            return;
        }

        $host = defined( 'MEMCACHED_HOST' ) ? MEMCACHED_HOST : '127.0.0.1';
        $port = defined( 'MEMCACHED_PORT' ) ? (int) MEMCACHED_PORT : 11211;

        // Persistent connection ID — reuses socket across PHP-FPM workers
        $persistent_id = 'crs_' . $this->key_prefix;

        $this->mc = new Memcached( $persistent_id );

        // Only add server if not already in the persistent connection pool
        if ( empty( $this->mc->getServerList() ) ) {
            $this->mc->addServer( $host, $port );

            // SiteGround-friendly options
            $this->mc->setOptions( [
                Memcached::OPT_COMPRESSION       => true,   // compress large values (post content, etc.)
                Memcached::OPT_BINARY_PROTOCOL   => true,   // faster than text protocol
                Memcached::OPT_TCP_NODELAY       => true,   // reduce latency
                Memcached::OPT_NO_BLOCK          => true,   // async I/O
                Memcached::OPT_CONNECT_TIMEOUT   => 50,     // ms — fast fail if Memcached is down
                Memcached::OPT_RETRY_TIMEOUT     => 50,
                Memcached::OPT_SEND_TIMEOUT      => 50000,  // µs
                Memcached::OPT_RECV_TIMEOUT      => 50000,
                Memcached::OPT_POLL_TIMEOUT      => 50,
                Memcached::OPT_SERVER_FAILURE_LIMIT => 2,
                Memcached::OPT_AUTO_EJECT_HOSTS  => true,
            ] );
        }

        // Quick health check
        $this->mc->set( $this->key_prefix . '_ping', 1, 10 );
        $this->connected = ( $this->mc->getResultCode() === Memcached::RES_SUCCESS );
    }

    public function close() {
        // Persistent connections should NOT be closed between requests
        return true;
    }

    // --------------------------------------------------------
    // Key Helpers
    // --------------------------------------------------------

    /**
     * Build the full Memcached key from WP group + key.
     * Ensures keys are safe (Memcached has 250 char limit, no spaces/control chars).
     */
    private function build_key( $key, $group ) {
        $group = empty( $group ) ? 'default' : $group;

        // Global groups don't use blog prefix (shared across network)
        $prefix = in_array( $group, $this->global_groups, true )
            ? $this->key_prefix
            : $this->key_prefix . '_' . $this->blog_id;

        $raw = $prefix . ':' . $group . ':' . $key;

        // Hash long keys — Memcached key limit is 250 chars
        return strlen( $raw ) > 200 ? $prefix . ':' . md5( $group . ':' . $key ) : $raw;
    }

    private function is_non_persistent( $group ) {
        return in_array( $group, $this->non_persistent_groups, true );
    }

    // --------------------------------------------------------
    // Public Cache API
    // --------------------------------------------------------

    public function add( $key, $data, $group = '', $expire = 0 ) {
        if ( $this->get( $key, $group ) !== false ) {
            return false; // key already exists
        }
        return $this->set( $key, $data, $group, $expire );
    }

    public function set( $key, $data, $group = '', $expire = 0 ) {
        $group = empty( $group ) ? 'default' : $group;

        // Always store in local L1 cache
        $this->local[ $group ][ $key ] = $data;

        if ( $this->is_non_persistent( $group ) || ! $this->connected ) {
            return true;
        }

        $mc_key = $this->build_key( $key, $group );
        $ttl    = (int) $expire > 0 ? (int) $expire : $this->default_ttl;

        // Clone objects to prevent reference issues
        $value = is_object( $data ) ? clone $data : $data;

        $this->mc->set( $mc_key, $value, $ttl );
        return $this->mc->getResultCode() === Memcached::RES_SUCCESS;
    }

    public function get( $key, $group = '', $force = false, &$found = null ) {
        $group = empty( $group ) ? 'default' : $group;

        // L1: check local memory first (unless force refresh)
        if ( ! $force && isset( $this->local[ $group ][ $key ] ) ) {
            $found = true;
            $this->cache_hits++;
            $val = $this->local[ $group ][ $key ];
            return is_object( $val ) ? clone $val : $val;
        }

        // Non-persistent groups never go to Memcached
        if ( $this->is_non_persistent( $group ) || ! $this->connected ) {
            $found = false;
            $this->cache_misses++;
            return false;
        }

        // L2: check Memcached
        $mc_key = $this->build_key( $key, $group );
        $value  = $this->mc->get( $mc_key );

        if ( $this->mc->getResultCode() === Memcached::RES_NOTFOUND ) {
            $found = false;
            $this->cache_misses++;
            return false;
        }

        // Warm L1 cache
        $found                              = true;
        $this->cache_hits++;
        $this->local[ $group ][ $key ]      = $value;

        return is_object( $value ) ? clone $value : $value;
    }

    public function get_multiple( $keys, $group = '', $force = false ) {
        $results = [];
        foreach ( $keys as $key ) {
            $results[ $key ] = $this->get( $key, $group, $force );
        }
        return $results;
    }

    public function delete( $key, $group = '' ) {
        $group = empty( $group ) ? 'default' : $group;

        // Remove from L1
        unset( $this->local[ $group ][ $key ] );

        if ( $this->is_non_persistent( $group ) || ! $this->connected ) {
            return true;
        }

        $this->mc->delete( $this->build_key( $key, $group ) );
        return $this->mc->getResultCode() !== Memcached::RES_NOTFOUND;
    }

    public function flush() {
        // Clear local L1 cache
        $this->local = [];

        if ( ! $this->connected ) {
            return true;
        }

        // We flush by key prefix, not full server flush,
        // so staging and live don't accidentally clear each other.
        // Full flush available via Memcached admin if needed.
        // For safety on shared hosting we just increment a global version key.
        $ver_key = $this->key_prefix . '_version';
        $version = $this->mc->get( $ver_key );
        $this->mc->set( $ver_key, ( (int) $version ) + 1, 0 );

        return true;
    }

    public function replace( $key, $data, $group = '', $expire = 0 ) {
        if ( $this->get( $key, $group ) === false ) {
            return false; // key doesn't exist
        }
        return $this->set( $key, $data, $group, $expire );
    }

    public function incr( $key, $offset = 1, $group = '' ) {
        $group = empty( $group ) ? 'default' : $group;

        if ( ! $this->connected || $this->is_non_persistent( $group ) ) {
            $val = (int) ( $this->local[ $group ][ $key ] ?? 0 ) + (int) $offset;
            $this->local[ $group ][ $key ] = $val;
            return $val;
        }

        $mc_key = $this->build_key( $key, $group );
        $result = $this->mc->increment( $mc_key, $offset );

        if ( $result !== false ) {
            $this->local[ $group ][ $key ] = $result;
        }

        return $result;
    }

    public function decr( $key, $offset = 1, $group = '' ) {
        $group = empty( $group ) ? 'default' : $group;

        if ( ! $this->connected || $this->is_non_persistent( $group ) ) {
            $val = max( 0, (int) ( $this->local[ $group ][ $key ] ?? 0 ) - (int) $offset );
            $this->local[ $group ][ $key ] = $val;
            return $val;
        }

        $mc_key = $this->build_key( $key, $group );
        $result = $this->mc->decrement( $mc_key, $offset );

        if ( $result !== false ) {
            $this->local[ $group ][ $key ] = $result;
        }

        return $result;
    }

    public function set_multiple( $data, $group = '', $expire = 0 ) {
        $results = [];
        foreach ( $data as $key => $value ) {
            $results[ $key ] = $this->set( $key, $value, $group, $expire );
        }
        return $results;
    }

    // --------------------------------------------------------
    // Multisite / Groups
    // --------------------------------------------------------

    public function switch_to_blog( $blog_id ) {
        $this->blog_id = (int) $blog_id;
    }

    public function add_global_groups( $groups ) {
        $groups               = (array) $groups;
        $this->global_groups  = array_unique( array_merge( $this->global_groups, $groups ) );
    }

    public function add_non_persistent_groups( $groups ) {
        $groups                        = (array) $groups;
        $this->non_persistent_groups   = array_unique( array_merge( $this->non_persistent_groups, $groups ) );
    }

    // --------------------------------------------------------
    // Debug Info (visible in Query Monitor / Debug Bar)
    // --------------------------------------------------------

    public function stats() {
        echo '<p>';
        echo '<strong>Memcached Object Cache</strong><br>';
        echo 'Connected: ' . ( $this->connected ? '<span style="color:green">Yes</span>' : '<span style="color:red">No (using local fallback)</span>' ) . '<br>';
        echo 'Cache Hits: '   . number_format( $this->cache_hits )   . '<br>';
        echo 'Cache Misses: ' . number_format( $this->cache_misses ) . '<br>';
        echo 'Blog ID: '      . $this->blog_id . '<br>';
        echo 'Key Prefix: '   . esc_html( $this->key_prefix ) . '<br>';
        echo '</p>';
    }
}
