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
                'menu_name'          => __( 'Businesses',             'crs' ),
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
            'capability_type'    => 'business',
            'map_meta_cap'       => true,
            'menu_icon'          => 'dashicons-store',
            'menu_position'      => 5,
        ] );
    }

    /* ====================================================================
       2.  Taxonomies
       ================================================================== */
    public static function register_taxonomies() {

        register_taxonomy( 'repair-service', 'business', [
            'hierarchical'      => false,
            'public'            => true,
            'show_admin_column' => true,
            'rewrite'           => [ 'slug' => 'services', 'with_front' => false ],
            'labels' => [
                'name'          => __( 'Repair Services', 'crs' ),
                'singular_name' => __( 'Repair Service',  'crs' ),
                'add_new_item'  => __( 'Add New Service', 'crs' ),
                'all_items'     => __( 'All Services',    'crs' ),
            ],
        ] );

        register_taxonomy( 'au-state', 'business', [
            'hierarchical'      => false,
            'public'            => true,
            'show_admin_column' => true,
            'rewrite'           => [ 'slug' => 'state', 'with_front' => false ],
            'labels' => [
                'name'          => __( 'States',     'crs' ),
                'singular_name' => __( 'State',      'crs' ),
                'all_items'     => __( 'All States', 'crs' ),
            ],
        ] );

        register_taxonomy( 'au-region', 'business', [
            'hierarchical' => true,
            'public'       => true,
            'rewrite'      => [ 'slug' => 'region', 'with_front' => false ],
            'labels' => [
                'name'          => __( 'Regions',     'crs' ),
                'singular_name' => __( 'Region',      'crs' ),
                'all_items'     => __( 'All Regions', 'crs' ),
            ],
        ] );

        register_taxonomy( 'au-suburb', 'business', [
            'hierarchical' => true,
            'public'       => true,
            'rewrite'      => [ 'slug' => 'suburb', 'with_front' => false ],
            'labels' => [
                'name'          => __( 'Suburbs',     'crs' ),
                'singular_name' => __( 'Suburb',      'crs' ),
                'all_items'     => __( 'All Suburbs', 'crs' ),
            ],
        ] );

        register_taxonomy( 'device-brand', 'business', [
            'hierarchical'      => false,
            'public'            => true,
            'show_admin_column' => true,
            'rewrite'           => [ 'slug' => 'brand', 'with_front' => false ],
            'labels' => [
                'name'          => __( 'Device Brands', 'crs' ),
                'singular_name' => __( 'Device Brand',  'crs' ),
                'all_items'     => __( 'All Brands',    'crs' ),
            ],
        ] );

        register_taxonomy( 'operating-system', 'business', [
            'hierarchical'      => false,
            'public'            => true,
            'show_admin_column' => true,
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
        $data = [
            'repair-service' => [
                'computer-repairs'    => 'Computer Repairs',
                'laptop-repairs'      => 'Laptop Repairs',
                'macbook-repairs'     => 'MacBook Repairs',
                'data-recovery'       => 'Data Recovery',
                'virus-removal'       => 'Virus Removal',
                'printer-repairs'     => 'Printer Repairs',
                'business-it-support' => 'Business IT Support',
                'microsoft-365'       => 'Microsoft 365 Support',
                'network-support'     => 'Network Support',
                'server-support'      => 'Server Support',
                'managed-it-services' => 'Managed IT Services',
                'remote-it-support'   => 'Remote IT Support',
            ],
            'au-state' => [
                'vic' => 'Victoria',
                'nsw' => 'New South Wales',
                'qld' => 'Queensland',
                'wa'  => 'Western Australia',
                'sa'  => 'South Australia',
                'tas' => 'Tasmania',
                'act' => 'Australian Capital Territory',
                'nt'  => 'Northern Territory',
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

        foreach ( $data as $taxonomy => $terms ) {
            foreach ( $terms as $slug => $name ) {
                if ( ! term_exists( $slug, $taxonomy ) ) {
                    wp_insert_term( $name, $taxonomy, [ 'slug' => $slug ] );
                }
            }
        }
    }

} // end class CRS_Setup

CRS_Setup::init();
