<?php
/**
 * CRS Business Dashboard – class-crs-setup.php
 *
 * Registers: CPT (menu_name = Services), 6 Taxonomies,
 *            Business Owner role, Enquiries DB table, Default terms.
 *
 * @package CRS
 * @author  Priya
 */
defined( 'ABSPATH' ) || exit;

class CRS_Setup {

    public static function init() {
        add_action( 'init', [ __CLASS__, 'register_cpt' ],        5 );
        add_action( 'init', [ __CLASS__, 'register_taxonomies' ], 5 );
    }

    /* ====================================================================
       1.  CPT  (post_type key = "business" | admin label = "Services")
       ================================================================== */
    public static function register_cpt() {
        register_post_type( 'business', [
            'labels' => [
                'name'               => __( 'Businesses',              'crs' ),
                'singular_name'      => __( 'Business',               'crs' ),
                'menu_name'          => __( 'CRS Business',             'crs' ),
                'add_new'            => __( 'Add New',                'crs' ),
                'add_new_item'       => __( 'Add New Business',       'crs' ),
                'edit_item'          => __( 'Edit Business',          'crs' ),
                'view_item'          => __( 'View Business',          'crs' ),
                'search_items'       => __( 'Search Businesses',      'crs' ),
                'not_found'          => __( 'No businesses found.',   'crs' ),
                'not_found_in_trash' => __( 'No businesses in trash.','crs' ),
                'all_items'          => __( 'All Businesses',         'crs' ),
                'archives'           => __( 'Business Archives',      'crs' ),
            ],
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'show_in_rest'       => false,
            'has_archive'        => 'services',
            'rewrite'            => [ 'slug' => 'business', 'with_front' => false ],
            'supports'           => [ 'title', 'thumbnail' ],
            'capability_type'    => 'post',
            'menu_icon'          => 'dashicons-store',
            'menu_position'      => 5,
        ] );
    }

    /* ====================================================================
       2.  Taxonomies
       ================================================================== */
    public static function register_taxonomies() {

        // Repair Services — checkbox list so admins can tick existing terms
        register_taxonomy( 'repair-service', 'business', [
            'hierarchical'      => true,                         // enables checkbox meta box
            'public'            => true,
            'show_admin_column' => true,
            'meta_box_cb'       => 'post_categories_meta_box',   // checkbox list, not tag input
            'rewrite'           => [ 'slug' => 'services', 'with_front' => false ],
            'labels' => [
                'name'          => __( 'Repair Services', 'crs' ),
                'singular_name' => __( 'Repair Service',  'crs' ),
                'add_new_item'  => __( 'Add New Service', 'crs' ),
                'all_items'     => __( 'All Services',    'crs' ),
            ],
        ] );

        // State — ACF select field handles this in the main edit area; hide default sidebar box
        register_taxonomy( 'au-state', 'business', [
            'hierarchical'      => false,
            'public'            => true,
            'show_admin_column' => true,
            'meta_box_cb'       => false,   // hidden — ACF Contact & Location field handles it
            'rewrite'           => [ 'slug' => 'state', 'with_front' => false ],
            'labels' => [
                'name'          => __( 'States',     'crs' ),
                'singular_name' => __( 'State',      'crs' ),
                'all_items'     => __( 'All States', 'crs' ),
            ],
        ] );

        // Region — ACF select field handles this; hide default sidebar box
        register_taxonomy( 'au-region', 'business', [
            'hierarchical'  => true,
            'public'        => true,
            'meta_box_cb'   => false,   // hidden — ACF Contact & Location field handles it
            'rewrite'       => [ 'slug' => 'region', 'with_front' => false ],
            'labels' => [
                'name'          => __( 'Regions',     'crs' ),
                'singular_name' => __( 'Region',      'crs' ),
                'all_items'     => __( 'All Regions', 'crs' ),
            ],
        ] );

        // Suburb — ACF select field handles this; hide default sidebar box
        register_taxonomy( 'au-suburb', 'business', [
            'hierarchical'  => true,
            'public'        => true,
            'meta_box_cb'   => false,   // hidden — ACF Contact & Location field handles it
            'rewrite'       => [ 'slug' => 'suburb', 'with_front' => false ],
            'labels' => [
                'name'          => __( 'Suburbs',     'crs' ),
                'singular_name' => __( 'Suburb',      'crs' ),
                'all_items'     => __( 'All Suburbs', 'crs' ),
            ],
        ] );

        // Device Brands — checkbox list
        register_taxonomy( 'device-brand', 'business', [
            'hierarchical'      => true,
            'public'            => true,
            'show_admin_column' => true,
            'meta_box_cb'       => 'post_categories_meta_box',
            'rewrite'           => [ 'slug' => 'brand', 'with_front' => false ],
            'labels' => [
                'name'          => __( 'Device Brands', 'crs' ),
                'singular_name' => __( 'Device Brand',  'crs' ),
                'all_items'     => __( 'All Brands',    'crs' ),
            ],
        ] );

        // Operating Systems — checkbox list
        register_taxonomy( 'operating-system', 'business', [
            'hierarchical'      => true,
            'public'            => true,
            'show_admin_column' => true,
            'meta_box_cb'       => 'post_categories_meta_box',
            'rewrite'           => [ 'slug' => 'os', 'with_front' => false ],
            'labels' => [
                'name'          => __( 'Operating Systems',     'crs' ),
                'singular_name' => __( 'Operating System',      'crs' ),
                'all_items'     => __( 'All Operating Systems', 'crs' ),
            ],
        ] );
    }

    /* ====================================================================
       3.  User Role – business_owner
       ================================================================== */
    public static function create_roles() {
        remove_role( 'business_owner' );

        add_role( 'business_owner', __( 'Business Owner', 'crs' ), [
            'read'                 => true,
            'read_business'        => true,
            'edit_business'        => true,
            'delete_business'      => false,
            'publish_businesses'   => false,
            'crs_dashboard_access' => true,
        ] );

        $admin = get_role( 'administrator' );
        if ( $admin ) {
            $caps = [
                'read_business', 'edit_business', 'edit_businesses',
                'edit_others_businesses', 'edit_published_businesses',
                'publish_businesses', 'delete_business', 'delete_businesses',
                'delete_others_businesses', 'delete_published_businesses',
                'read_private_businesses', 'crs_dashboard_access', 'crs_admin_access',
            ];
            foreach ( $caps as $cap ) {
                $admin->add_cap( $cap );
            }
        }
    }

    /* ====================================================================
       4.  Enquiries DB Table  (wp_crs_enquiries)
       ================================================================== */
    public static function create_enquiries_table() {
        global $wpdb;

        $table   = $wpdb->prefix . 'crs_enquiries';
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            enquiry_id   BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            business_id  BIGINT(20) UNSIGNED NOT NULL,
            name         VARCHAR(100)        NOT NULL DEFAULT '',
            email        VARCHAR(200)        NOT NULL DEFAULT '',
            phone        VARCHAR(50)                  DEFAULT '',
            suburb       VARCHAR(100)                 DEFAULT '',
            service      VARCHAR(100)                 DEFAULT '',
            message      TEXT                NOT NULL,
            contact_pref VARCHAR(50)                  DEFAULT 'email',
            status       VARCHAR(20)         NOT NULL DEFAULT 'new',
            created_at   DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
            replied_at   DATETIME                     DEFAULT NULL,
            PRIMARY KEY  (enquiry_id),
            KEY business_id (business_id),
            KEY status      (status),
            KEY created_at  (created_at)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );

        update_option( 'crs_enquiries_table_version', '1.0' );
    }

    /* ====================================================================
       5.  Default Terms
       ================================================================== */
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
                    wp_insert_term( $name, 'repair-service', [
                        'slug'   => $slug,
                        'parent' => $parent_id,
                    ] );
                } elseif ( (int) $existing->parent !== $parent_id ) {
                    wp_update_term( $existing->term_id, 'repair-service', [
                        'parent' => $parent_id,
                    ] );
                }
            }
        }

        $data = [
            // au-state slugs now use the full name; abbreviation stored as term meta
            'au-state' => [
                'victoria'                      => 'Victoria',
                'new-south-wales'               => 'New South Wales',
                'queensland'                    => 'Queensland',
                'western-australia'             => 'Western Australia',
                'south-australia'               => 'South Australia',
                'tasmania'                      => 'Tasmania',
                'australian-capital-territory'  => 'Australian Capital Territory',
                'northern-territory'            => 'Northern Territory',
            ],
            'device-brand' => [
                'apple'   => 'Apple',   'dell'    => 'Dell',
                'hp'      => 'HP',      'lenovo'  => 'Lenovo',
                'asus'    => 'ASUS',    'acer'    => 'Acer',
                'msi'     => 'MSI',     'toshiba' => 'Toshiba',
                'samsung' => 'Samsung',
            ],
            'operating-system' => [
                'windows'   => 'Windows',  'macos'     => 'macOS',
                'linux'     => 'Linux',    'chrome-os' => 'Chrome OS',
            ],
        ];

        // Abbreviations for au-state terms (keyed by slug)
        $state_abbrs = [
            'victoria'                     => 'VIC',
            'new-south-wales'              => 'NSW',
            'queensland'                   => 'QLD',
            'western-australia'            => 'WA',
            'south-australia'              => 'SA',
            'tasmania'                     => 'TAS',
            'australian-capital-territory' => 'ACT',
            'northern-territory'           => 'NT',
        ];

        foreach ( $data as $taxonomy => $terms ) {
            foreach ( $terms as $slug => $name ) {
                if ( ! term_exists( $slug, $taxonomy ) ) {
                    $result = wp_insert_term( $name, $taxonomy, [ 'slug' => $slug ] );
                    if ( ! is_wp_error( $result ) && $taxonomy === 'au-state' && isset( $state_abbrs[ $slug ] ) ) {
                        update_term_meta( $result['term_id'], 'au_state_abbreviation', $state_abbrs[ $slug ] );
                    }
                }
            }
        }
    }

    /* ====================================================================
       6.  Migrate existing au-state terms to full-name slugs
       ================================================================== */
    public static function migrate_state_slugs() {
        if ( get_option( 'crs_state_slugs_v2' ) ) {
            return; // Already migrated
        }

        $migrations = [
            'vic' => [ 'slug' => 'victoria',                     'abbr' => 'VIC' ],
            'nsw' => [ 'slug' => 'new-south-wales',               'abbr' => 'NSW' ],
            'qld' => [ 'slug' => 'queensland',                    'abbr' => 'QLD' ],
            'wa'  => [ 'slug' => 'western-australia',             'abbr' => 'WA'  ],
            'sa'  => [ 'slug' => 'south-australia',               'abbr' => 'SA'  ],
            'tas' => [ 'slug' => 'tasmania',                      'abbr' => 'TAS' ],
            'act' => [ 'slug' => 'australian-capital-territory',  'abbr' => 'ACT' ],
            'nt'  => [ 'slug' => 'northern-territory',            'abbr' => 'NT'  ],
        ];

        foreach ( $migrations as $old_slug => $data ) {
            $term = get_term_by( 'slug', $old_slug, 'au-state' );
            if ( $term ) {
                wp_update_term( $term->term_id, 'au-state', [ 'slug' => $data['slug'] ] );
                update_term_meta( $term->term_id, 'au_state_abbreviation', $data['abbr'] );
            }
        }

        update_option( 'crs_state_slugs_v2', true );
    }

} // end class CRS_Setup

CRS_Setup::init();
