<?php
/**
 * CRS Business Dashboard — class-crs-setup.php
 *
 * Handles everything that must exist before any other plugin code runs:
 *  1. Business Custom Post Type
 *  2. All 6 taxonomies
 *  3. business_owner user role + capabilities
 *  4. crs_enquiries custom DB table
 *  5. Default taxonomy terms (states, services, brands, OS)
 *
 * All public methods are static so they can be called safely from the
 * activation hook (before the full plugin is loaded).
 */

defined( 'ABSPATH' ) || exit;

class CRS_Setup {

    public static function init() {
        add_action( 'init', [ __CLASS__, 'register_cpt' ],        5 );
        add_action( 'init', [ __CLASS__, 'register_taxonomies' ], 5 );
    }

    /* ======================================================================
       1. CUSTOM POST TYPE — business
       ==================================================================== */

    public static function register_cpt() {
        register_post_type( 'business', [
            'labels' => [
                'name'               => __( 'Businesses',        'crs' ),
                'singular_name'      => __( 'Business',          'crs' ),
                'add_new'            => __( 'Add New',           'crs' ),
                'add_new_item'       => __( 'Add New Business',  'crs' ),
                'edit_item'          => __( 'Edit Business',     'crs' ),
                'view_item'          => __( 'View Business',     'crs' ),
                'search_items'       => __( 'Search Businesses', 'crs' ),
                'not_found'          => __( 'No businesses found', 'crs' ),
                'not_found_in_trash' => __( 'No businesses in trash', 'crs' ),
                'all_items'          => __( 'All Businesses',    'crs' ),
                'menu_name'          => __( 'Businesses',        'crs' ),
            ],
            'description'        => __( 'Computer repair and IT support business listings.', 'crs' ),
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'show_in_rest'       => false,   // no Gutenberg editor
            'has_archive'        => 'services',
            'rewrite'            => [
                'slug'       => 'business',
                'with_front' => false,
            ],
            'supports'           => [ 'title', 'thumbnail' ],
            'capability_type'    => 'business',
            'map_meta_cap'       => true,
            'menu_icon'          => 'dashicons-store',
            'menu_position'      => 5,
        ] );
    }

    /* ======================================================================
       2. TAXONOMIES
       ==================================================================== */

    public static function register_taxonomies() {

        /* --- Repair Service -------------------------------------------- */
        register_taxonomy( 'repair-service', 'business', [
            'hierarchical'      => true,
            'public'            => true,
            'show_admin_column' => true,
            'show_in_rest'      => false,
            'rewrite'           => [ 'slug' => 'services', 'with_front' => false ],
            'labels'            => [
                'name'          => __( 'Repair Services',  'crs' ),
                'singular_name' => __( 'Repair Service',   'crs' ),
                'add_new_item'  => __( 'Add New Service',  'crs' ),
                'search_items'  => __( 'Search Services',  'crs' ),
                'all_items'     => __( 'All Services',     'crs' ),
            ],
        ] );

        /* --- State ------------------------------------------------------ */
        register_taxonomy( 'au-state', 'business', [
            'hierarchical'      => false,
            'public'            => true,
            'show_admin_column' => true,
            'show_in_rest'      => false,
            'rewrite'           => [ 'slug' => 'state', 'with_front' => false ],
            'labels'            => [
                'name'          => __( 'States',      'crs' ),
                'singular_name' => __( 'State',       'crs' ),
                'add_new_item'  => __( 'Add State',   'crs' ),
                'all_items'     => __( 'All States',  'crs' ),
            ],
        ] );

        /* --- Region (child of State) ------------------------------------ */
        register_taxonomy( 'au-region', 'business', [
            'hierarchical'      => true,
            'public'            => true,
            'show_admin_column' => false,
            'show_in_rest'      => false,
            'rewrite'           => [ 'slug' => 'region', 'with_front' => false ],
            'labels'            => [
                'name'          => __( 'Regions',     'crs' ),
                'singular_name' => __( 'Region',      'crs' ),
                'add_new_item'  => __( 'Add Region',  'crs' ),
                'all_items'     => __( 'All Regions', 'crs' ),
            ],
        ] );

        /* --- Suburb (child of Region) ----------------------------------- */
        register_taxonomy( 'au-suburb', 'business', [
            'hierarchical'      => true,
            'public'            => true,
            'show_admin_column' => false,
            'show_in_rest'      => false,
            'rewrite'           => [ 'slug' => 'suburb', 'with_front' => false ],
            'labels'            => [
                'name'          => __( 'Suburbs',     'crs' ),
                'singular_name' => __( 'Suburb',      'crs' ),
                'add_new_item'  => __( 'Add Suburb',  'crs' ),
                'all_items'     => __( 'All Suburbs', 'crs' ),
            ],
        ] );

        /* --- Device Brand ---------------------------------------------- */
        register_taxonomy( 'device-brand', 'business', [
            'hierarchical'      => false,
            'public'            => true,
            'show_admin_column' => true,
            'show_in_rest'      => false,
            'rewrite'           => [ 'slug' => 'brand', 'with_front' => false ],
            'labels'            => [
                'name'          => __( 'Device Brands',  'crs' ),
                'singular_name' => __( 'Device Brand',   'crs' ),
                'add_new_item'  => __( 'Add Brand',      'crs' ),
                'all_items'     => __( 'All Brands',     'crs' ),
            ],
        ] );

        /* --- Operating System ------------------------------------------ */
        register_taxonomy( 'operating-system', 'business', [
            'hierarchical'      => false,
            'public'            => true,
            'show_admin_column' => true,
            'show_in_rest'      => false,
            'rewrite'           => [ 'slug' => 'os', 'with_front' => false ],
            'labels'            => [
                'name'          => __( 'Operating Systems',  'crs' ),
                'singular_name' => __( 'Operating System',   'crs' ),
                'add_new_item'  => __( 'Add OS',             'crs' ),
                'all_items'     => __( 'All OS',             'crs' ),
            ],
        ] );
    }

    /* ======================================================================
       3. USER ROLE — business_owner
       ==================================================================== */

    public static function create_roles() {

        // Remove first to avoid stale capabilities from a previous version
        remove_role( 'business_owner' );

        add_role( 'business_owner', __( 'Business Owner', 'crs' ), [
            // WordPress core caps
            'read'                   => true,

            // Business CPT caps (mapped via capability_type = 'business')
            'read_business'          => true,
            'edit_business'          => true,
            'delete_business'        => false,  // owners cannot delete their own listing
            'publish_businesses'     => false,  // admin approves

            // Dashboard access flag (checked by class-crs-dashboard.php)
            'crs_dashboard_access'   => true,
        ] );

        // Give admins all business caps
        $admin = get_role( 'administrator' );
        if ( $admin ) {
            $admin_caps = [
                'read_business',
                'edit_business',
                'edit_businesses',
                'edit_others_businesses',
                'edit_published_businesses',
                'publish_businesses',
                'delete_business',
                'delete_businesses',
                'delete_others_businesses',
                'delete_published_businesses',
                'read_private_businesses',
                'crs_dashboard_access',
                'crs_admin_access',
            ];
            foreach ( $admin_caps as $cap ) {
                $admin->add_cap( $cap );
            }
        }
    }

    /* ======================================================================
       4. ENQUIRIES DATABASE TABLE
       ==================================================================== */

    public static function create_enquiries_table() {
        global $wpdb;

        $table   = $wpdb->prefix . 'crs_enquiries';
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            enquiry_id    BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            business_id   BIGINT(20) UNSIGNED NOT NULL,
            name          VARCHAR(100)        NOT NULL DEFAULT '',
            email         VARCHAR(200)        NOT NULL DEFAULT '',
            phone         VARCHAR(50)                  DEFAULT '',
            suburb        VARCHAR(100)                 DEFAULT '',
            service       VARCHAR(100)                 DEFAULT '',
            message       TEXT                NOT NULL,
            contact_pref  VARCHAR(50)                  DEFAULT 'email',
            status        VARCHAR(20)         NOT NULL DEFAULT 'new',
            created_at    DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
            replied_at    DATETIME                     DEFAULT NULL,
            PRIMARY KEY  (enquiry_id),
            KEY business_id (business_id),
            KEY status      (status),
            KEY created_at  (created_at)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );

        update_option( 'crs_enquiries_table_version', '1.0' );
    }

    /* ======================================================================
       5. DEFAULT TAXONOMY TERMS
       ==================================================================== */

    public static function insert_default_terms() {

        // ── Repair Services (hierarchical: parent categories + children) ──
        $service_groups = [
            'consumer-repair-services' => [
                'name'     => 'Consumer Repair Services',
                'children' => [
                    'computer-repairs'         => 'Computer Repairs',
                    'laptop-repairs'           => 'Laptop Repairs',
                    'macbook-repairs'          => 'MacBook Repairs',
                    'desktop-computer-repairs' => 'Desktop Computer Repairs',
                    'gaming-pc-repairs'        => 'Gaming PC Repairs',
                    'data-recovery'            => 'Data Recovery',
                    'virus-removal'            => 'Virus Removal',
                    'malware-removal'          => 'Malware Removal',
                    'printer-repairs'          => 'Printer Repairs',
                    'printer-setup'            => 'Printer Setup',
                    'screen-replacement'       => 'Screen Replacement',
                    'battery-replacement'      => 'Battery Replacement',
                    'wifi-troubleshooting'     => 'WiFi Troubleshooting',
                    'software-installation'    => 'Software Installation',
                    'computer-upgrades'        => 'Computer Upgrades',
                ],
            ],
            'business-it-services' => [
                'name'     => 'Business IT Services',
                'children' => [
                    'business-it-support'       => 'Business IT Support',
                    'microsoft-365'             => 'Microsoft 365 Support',
                    'email-support'             => 'Email Support',
                    'network-support'           => 'Network Support',
                    'server-support'            => 'Server Support',
                    'managed-it-services'       => 'Managed IT Services',
                    'remote-it-support'         => 'Remote IT Support',
                    'cloud-backup-services'     => 'Cloud Backup Services',
                    'cyber-security-services'   => 'Cyber Security Services',
                    'business-wifi-support'     => 'Business WiFi Support',
                    'it-help-desk-services'     => 'IT Help Desk Services',
                    'microsoft-teams-support'   => 'Microsoft Teams Support',
                    'sharepoint-support'        => 'SharePoint Support',
                    'cloud-migration-services'  => 'Cloud Migration Services',
                ],
            ],
        ];

        foreach ( $service_groups as $parent_slug => $group ) {
            // Insert parent term if not exists, or get existing
            $parent_term = term_exists( $parent_slug, 'repair-service' );
            if ( ! $parent_term ) {
                $parent_term = wp_insert_term( $group['name'], 'repair-service', [ 'slug' => $parent_slug ] );
            }
            if ( is_wp_error( $parent_term ) ) {
                continue;
            }
            $parent_id = is_array( $parent_term ) ? (int) $parent_term['term_id'] : (int) $parent_term;

            foreach ( $group['children'] as $slug => $name ) {
                $existing = get_term_by( 'slug', $slug, 'repair-service' );
                if ( ! $existing ) {
                    // New term — insert with parent
                    wp_insert_term( $name, 'repair-service', [
                        'slug'   => $slug,
                        'parent' => $parent_id,
                    ] );
                } elseif ( (int) $existing->parent !== $parent_id ) {
                    // Existing flat term — assign to parent
                    wp_update_term( $existing->term_id, 'repair-service', [
                        'parent' => $parent_id,
                    ] );
                }
            }
        }

        // ── Australian States ─────────────────────────────────────────────
        $states = [
            'vic' => 'Victoria',
            'nsw' => 'New South Wales',
            'qld' => 'Queensland',
            'wa'  => 'Western Australia',
            'sa'  => 'South Australia',
            'tas' => 'Tasmania',
            'act' => 'Australian Capital Territory',
            'nt'  => 'Northern Territory',
        ];

        foreach ( $states as $slug => $name ) {
            if ( ! term_exists( $slug, 'au-state' ) ) {
                wp_insert_term( $name, 'au-state', [ 'slug' => $slug ] );
            }
        }

        // ── Device Brands ─────────────────────────────────────────────────
        $brands = [
            'apple'  => 'Apple',
            'dell'   => 'Dell',
            'hp'     => 'HP',
            'lenovo' => 'Lenovo',
            'asus'   => 'ASUS',
            'acer'   => 'Acer',
            'msi'    => 'MSI',
            'toshiba'=> 'Toshiba',
            'samsung'=> 'Samsung',
        ];

        foreach ( $brands as $slug => $name ) {
            if ( ! term_exists( $slug, 'device-brand' ) ) {
                wp_insert_term( $name, 'device-brand', [ 'slug' => $slug ] );
            }
        }

        // ── Operating Systems ─────────────────────────────────────────────
        $os_list = [
            'windows'   => 'Windows',
            'macos'     => 'macOS',
            'linux'     => 'Linux',
            'chrome-os' => 'Chrome OS',
        ];

        foreach ( $os_list as $slug => $name ) {
            if ( ! term_exists( $slug, 'operating-system' ) ) {
                wp_insert_term( $name, 'operating-system', [ 'slug' => $slug ] );
            }
        }
    }

} // end class CRS_Setup

// Hook init so CPT + taxonomies register on every page load
CRS_Setup::init();
