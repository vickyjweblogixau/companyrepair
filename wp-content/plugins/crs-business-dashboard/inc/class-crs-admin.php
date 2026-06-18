<?php
/**
 * CRS Business Dashboard – class-crs-admin.php
 *
 * Fixes the WordPress admin sidebar so "Businesses" stays expanded and
 * the correct taxonomy submenu item is highlighted when on any of the
 * 6 taxonomy admin pages.
 *
 * Uses both PHP filters (correct approach) and a JS fallback (guaranteed).
 *
 * @package CRS
 * @author  Priya
 */
defined( 'ABSPATH' ) || exit;

class CRS_Admin {

    private static $taxonomies = [
        'repair-service',
        'au-state',
        'au-region',
        'au-suburb',
        'device-brand',
        'operating-system',
    ];

    public static function init() {
        add_filter( 'parent_file',  [ __CLASS__, 'fix_parent_file'  ] );
        add_filter( 'submenu_file', [ __CLASS__, 'fix_submenu_file' ] );
        add_action( 'admin_footer', [ __CLASS__, 'fix_menu_via_js'  ] );
    }

    private static function current_crs_taxonomy() {
        if (
            ! isset( $_GET['taxonomy'] ) ||
            ! isset( $_GET['post_type'] ) ||
            'business' !== sanitize_key( $_GET['post_type'] )
        ) {
            return '';
        }
        $tax = sanitize_key( $_GET['taxonomy'] );
        return in_array( $tax, self::$taxonomies, true ) ? $tax : '';
    }

    public static function fix_parent_file( $parent_file ) {
        return self::current_crs_taxonomy() ? 'edit.php?post_type=business' : $parent_file;
    }

    public static function fix_submenu_file( $submenu_file ) {
        $tax = self::current_crs_taxonomy();
        return $tax
            ? 'edit-tags.php?taxonomy=' . $tax . '&post_type=business'
            : $submenu_file;
    }

    /**
     * JS fallback: directly set the correct menu-open state after
     * all WordPress scripts have run.
     */
    public static function fix_menu_via_js() {
        $tax = self::current_crs_taxonomy();
        if ( ! $tax ) {
            return;
        }
        ?>
        <script>
        (function ($) {
            $(document).ready(function () {

                // Find the Businesses top-level menu item
                var $businessMenu = $('#adminmenu li.wp-has-submenu').filter(function () {
                    var href = $(this).children('a.menu-top').attr('href') || '';
                    return href.indexOf('post_type=business') !== -1;
                });

                if ( ! $businessMenu.length ) { return; }

                // Mark the parent as open/current
                $businessMenu
                    .removeClass('wp-not-current-submenu')
                    .addClass('wp-has-current-submenu wp-menu-open');

                // Show its submenu
                $businessMenu.children('ul.wp-submenu').show();

                // Highlight the active taxonomy item
                $businessMenu.find('ul.wp-submenu li a').each(function () {
                    var href = $(this).attr('href') || '';
                    if ( href.indexOf('taxonomy=<?php echo esc_js( $tax ); ?>') !== -1 ) {
                        $(this).closest('li').addClass('current');
                    }
                });
            });
        }(jQuery));
        </script>
        <?php
    }

} // end class CRS_Admin

CRS_Admin::init();


/* ============================================================
   Repair Service – Featured Image (term meta)
   ============================================================ */

class CRS_Repair_Service_Image {

    const META_KEY = 'repair_service_image_id';

    public static function init() {
        // Add-new form (no <tr> wrapper needed)
        add_action( 'repair-service_add_form_fields',  [ __CLASS__, 'add_form_field'  ] );
        // Edit form (needs <tr> wrapper)
        add_action( 'repair-service_edit_form_fields', [ __CLASS__, 'edit_form_field' ], 10, 2 );
        // Save
        add_action( 'created_repair-service', [ __CLASS__, 'save' ] );
        add_action( 'edited_repair-service',  [ __CLASS__, 'save' ] );
        // List-table column
        add_filter( 'manage_edit-repair-service_columns',        [ __CLASS__, 'add_column'    ] );
        add_filter( 'manage_repair-service_custom_column',       [ __CLASS__, 'render_column' ], 10, 3 );
        // Enqueue media uploader JS on taxonomy pages
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_scripts' ] );
    }

    public static function enqueue_scripts( $hook ) {
        // Only on repair-service taxonomy pages
        $screen = get_current_screen();
        if ( ! $screen || $screen->taxonomy !== 'repair-service' ) {
            return;
        }
        wp_enqueue_media();
        wp_add_inline_script( 'jquery-core', self::inline_js() );
    }

    private static function inline_js() {
        return <<<'JS'
jQuery(function ($) {
    // Open media uploader
    $(document).on('click', '.crs-upload-image-btn', function (e) {
        e.preventDefault();
        var $btn     = $(this);
        var $wrap    = $btn.closest('.crs-image-wrap');
        var $input   = $wrap.find('.crs-image-id');
        var $preview = $wrap.find('.crs-image-preview');
        var $remove  = $wrap.find('.crs-remove-image-btn');

        var frame = wp.media({
            title:    'Select Service Image',
            button:   { text: 'Use this image' },
            multiple: false
        });

        frame.on('select', function () {
            var attachment = frame.state().get('selection').first().toJSON();
            $input.val(attachment.id);
            $preview.html('<img src="' + attachment.url + '" style="max-width:150px;height:auto;margin-top:8px;border-radius:4px;">');
            $remove.show();
        });

        frame.open();
    });

    // Remove image
    $(document).on('click', '.crs-remove-image-btn', function (e) {
        e.preventDefault();
        var $wrap = $(this).closest('.crs-image-wrap');
        $wrap.find('.crs-image-id').val('');
        $wrap.find('.crs-image-preview').html('');
        $(this).hide();
    });
});
JS;
    }

    /** Shared markup (used by both Add and Edit forms). */
    private static function field_html( $image_id = 0 ) {
        $preview = '';
        $remove  = 'display:none';

        if ( $image_id ) {
            $src = wp_get_attachment_image_url( $image_id, 'thumbnail' );
            if ( $src ) {
                $preview = '<img src="' . esc_url( $src ) . '" style="max-width:150px;height:auto;margin-top:8px;border-radius:4px;">';
                $remove  = '';
            }
        }

        ob_start(); ?>
        <div class="crs-image-wrap">
            <input type="hidden" name="<?php echo esc_attr( self::META_KEY ); ?>"
                   class="crs-image-id" value="<?php echo esc_attr( $image_id ?: '' ); ?>">
            <div class="crs-image-preview"><?php echo $preview; ?></div>
            <button type="button" class="button crs-upload-image-btn" style="margin-top:8px;">
                <?php echo $image_id ? 'Change Image' : 'Upload / Select Image'; ?>
            </button>
            <button type="button" class="button crs-remove-image-btn" style="margin-left:6px;<?php echo $remove; ?>">
                Remove Image
            </button>
            <p class="description">This image represents the service on the website.</p>
        </div>
        <?php
        return ob_get_clean();
    }

    /** Add-new form */
    public static function add_form_field() {
        ?>
        <div class="form-field term-image-wrap">
            <label><?php esc_html_e( 'Featured Image', 'crs' ); ?></label>
            <?php echo self::field_html(); ?>
        </div>
        <?php
    }

    /** Edit form */
    public static function edit_form_field( $term ) {
        $image_id = (int) get_term_meta( $term->term_id, self::META_KEY, true );
        ?>
        <tr class="form-field term-image-wrap">
            <th scope="row"><label><?php esc_html_e( 'Featured Image', 'crs' ); ?></label></th>
            <td><?php echo self::field_html( $image_id ); ?></td>
        </tr>
        <?php
    }

    /** Save */
    public static function save( $term_id ) {
        if ( ! isset( $_POST[ self::META_KEY ] ) ) {
            return;
        }
        $image_id = absint( $_POST[ self::META_KEY ] );
        if ( $image_id ) {
            update_term_meta( $term_id, self::META_KEY, $image_id );
        } else {
            delete_term_meta( $term_id, self::META_KEY );
        }
    }

    /** Add column to list table */
    public static function add_column( $columns ) {
        // Insert after 'name'
        $new = [];
        foreach ( $columns as $key => $label ) {
            $new[ $key ] = $label;
            if ( $key === 'name' ) {
                $new['service_image'] = __( 'Image', 'crs' );
            }
        }
        return $new;
    }

    /** Render column */
    public static function render_column( $content, $column_name, $term_id ) {
        if ( $column_name !== 'service_image' ) {
            return $content;
        }
        $image_id = (int) get_term_meta( $term_id, self::META_KEY, true );
        if ( $image_id ) {
            $src = wp_get_attachment_image_url( $image_id, 'thumbnail' );
            if ( $src ) {
                return '<img src="' . esc_url( $src ) . '" style="width:50px;height:50px;object-fit:cover;border-radius:4px;">';
            }
        }
        return '—';
    }

}

CRS_Repair_Service_Image::init();


/* ============================================================
   AU State – Short Abbreviation (term meta)
   ============================================================ */

class CRS_AU_State_Fields {

    const META_KEY = 'au_state_abbreviation';

    public static function init() {
        add_action( 'au-state_add_form_fields',  [ __CLASS__, 'add_form_field'  ] );
        add_action( 'au-state_edit_form_fields', [ __CLASS__, 'edit_form_field' ], 10, 2 );
        add_action( 'created_au-state', [ __CLASS__, 'save' ] );
        add_action( 'edited_au-state',  [ __CLASS__, 'save' ] );
        add_filter( 'manage_edit-au-state_columns',     [ __CLASS__, 'add_column'    ] );
        add_filter( 'manage_au-state_custom_column',    [ __CLASS__, 'render_column' ], 10, 3 );
    }

    public static function add_form_field() {
        ?>
        <div class="form-field">
            <label for="au_state_abbreviation"><?php esc_html_e( 'Short Abbreviation', 'crs' ); ?></label>
            <input type="text" name="<?php echo esc_attr( self::META_KEY ); ?>"
                   id="au_state_abbreviation" value="" maxlength="10" style="width:120px;">
            <p class="description"><?php esc_html_e( 'Short code for this state, e.g. VIC, NSW.', 'crs' ); ?></p>
        </div>
        <?php
    }

    public static function edit_form_field( $term ) {
        $abbr = get_term_meta( $term->term_id, self::META_KEY, true );
        ?>
        <tr class="form-field">
            <th scope="row">
                <label for="au_state_abbreviation"><?php esc_html_e( 'Short Abbreviation', 'crs' ); ?></label>
            </th>
            <td>
                <input type="text" name="<?php echo esc_attr( self::META_KEY ); ?>"
                       id="au_state_abbreviation"
                       value="<?php echo esc_attr( $abbr ); ?>" maxlength="10" style="width:120px;">
                <p class="description"><?php esc_html_e( 'Short code for this state, e.g. VIC, NSW.', 'crs' ); ?></p>
            </td>
        </tr>
        <?php
    }

    public static function save( $term_id ) {
        if ( ! isset( $_POST[ self::META_KEY ] ) ) {
            return;
        }
        $abbr = strtoupper( sanitize_text_field( $_POST[ self::META_KEY ] ) );
        if ( $abbr !== '' ) {
            update_term_meta( $term_id, self::META_KEY, $abbr );
        } else {
            delete_term_meta( $term_id, self::META_KEY );
        }
    }

    public static function add_column( $columns ) {
        $new = [];
        foreach ( $columns as $key => $label ) {
            $new[ $key ] = $label;
            if ( $key === 'name' ) {
                $new['state_abbr'] = __( 'Abbreviation', 'crs' );
            }
        }
        return $new;
    }

    public static function render_column( $content, $column_name, $term_id ) {
        if ( $column_name !== 'state_abbr' ) {
            return $content;
        }
        $abbr = get_term_meta( $term_id, self::META_KEY, true );
        return $abbr ? '<strong>' . esc_html( $abbr ) . '</strong>' : '—';
    }

}

CRS_AU_State_Fields::init();


/* ============================================================
   AU Region – State field + CSV Importer
   ============================================================ */

class CRS_AU_Region_Fields {

    const STATE_META = 'au_region_state'; // stores au-state term_id

    public static function init() {
        // State dropdown field
        add_action( 'au-region_add_form_fields',  [ __CLASS__, 'add_state_field'  ] );
        add_action( 'au-region_edit_form_fields', [ __CLASS__, 'edit_state_field' ], 10, 2 );
        add_action( 'created_au-region', [ __CLASS__, 'save_state' ] );
        add_action( 'edited_au-region',  [ __CLASS__, 'save_state' ] );

        // List-table column
        add_filter( 'manage_edit-au-region_columns',     [ __CLASS__, 'add_column'    ] );
        add_filter( 'manage_au-region_custom_column',    [ __CLASS__, 'render_column' ], 10, 3 );

        // CSV importer section (appended after the add form)
        add_action( 'au-region_add_form', [ __CLASS__, 'csv_import_section' ] );

        // Handle CSV upload
        add_action( 'admin_post_crs_import_regions', [ __CLASS__, 'handle_csv_import' ] );
    }

    /* ------------------------------------------------------------------
       State dropdown helpers
    ------------------------------------------------------------------ */

    private static function state_dropdown( $selected_id = 0 ) {
        $states = get_terms( [ 'taxonomy' => 'au-state', 'hide_empty' => false ] );
        echo '<select name="' . esc_attr( self::STATE_META ) . '" id="au_region_state">';
        echo '<option value="">' . esc_html__( '— None —', 'crs' ) . '</option>';
        if ( ! is_wp_error( $states ) ) {
            foreach ( $states as $state ) {
                $abbr = get_term_meta( $state->term_id, 'au_state_abbreviation', true );
                $label = $abbr ? $state->name . ' (' . $abbr . ')' : $state->name;
                printf(
                    '<option value="%d"%s>%s</option>',
                    $state->term_id,
                    selected( $selected_id, $state->term_id, false ),
                    esc_html( $label )
                );
            }
        }
        echo '</select>';
    }

    public static function add_state_field() {
        ?>
        <div class="form-field">
            <label for="au_region_state"><?php esc_html_e( 'State', 'crs' ); ?></label>
            <?php self::state_dropdown(); ?>
            <p class="description"><?php esc_html_e( 'Which state does this region belong to?', 'crs' ); ?></p>
        </div>
        <?php
    }

    public static function edit_state_field( $term ) {
        $state_id = (int) get_term_meta( $term->term_id, self::STATE_META, true );
        ?>
        <tr class="form-field">
            <th scope="row"><label for="au_region_state"><?php esc_html_e( 'State', 'crs' ); ?></label></th>
            <td>
                <?php self::state_dropdown( $state_id ); ?>
                <p class="description"><?php esc_html_e( 'Which state does this region belong to?', 'crs' ); ?></p>
            </td>
        </tr>
        <?php
    }

    public static function save_state( $term_id ) {
        if ( ! isset( $_POST[ self::STATE_META ] ) ) {
            return;
        }
        $state_id = absint( $_POST[ self::STATE_META ] );
        if ( $state_id ) {
            update_term_meta( $term_id, self::STATE_META, $state_id );
        } else {
            delete_term_meta( $term_id, self::STATE_META );
        }
    }

    /* ------------------------------------------------------------------
       List-table column
    ------------------------------------------------------------------ */

    public static function add_column( $columns ) {
        $new = [];
        foreach ( $columns as $key => $label ) {
            $new[ $key ] = $label;
            if ( $key === 'name' ) {
                $new['region_state'] = __( 'State', 'crs' );
            }
        }
        return $new;
    }

    public static function render_column( $content, $column_name, $term_id ) {
        if ( $column_name !== 'region_state' ) {
            return $content;
        }
        $state_id = (int) get_term_meta( $term_id, self::STATE_META, true );
        if ( ! $state_id ) {
            return '—';
        }
        $state = get_term( $state_id, 'au-state' );
        if ( is_wp_error( $state ) || ! $state ) {
            return '—';
        }
        $abbr = get_term_meta( $state->term_id, 'au_state_abbreviation', true );
        return esc_html( $abbr ?: $state->name );
    }

    /* ------------------------------------------------------------------
       CSV Import section
    ------------------------------------------------------------------ */

    /**
     * Renders the CSV import box below the Add Region form.
     *
     * Expected CSV columns (first row = header, order matters):
     *   name  |  slug (optional)  |  state_slug  |  parent_slug (optional)  |  description (optional)
     *
     * Example rows:
     *   Greater Melbourne,greater-melbourne,victoria,,
     *   Inner Melbourne,inner-melbourne,victoria,greater-melbourne,Inner suburbs of Melbourne
     */
    public static function csv_import_section() {
        $result = isset( $_GET['crs_import'] ) ? sanitize_text_field( $_GET['crs_import'] ) : '';
        $count  = isset( $_GET['crs_count'] ) ? (int) $_GET['crs_count'] : 0;
        $errors = isset( $_GET['crs_errors'] ) ? (int) $_GET['crs_errors'] : 0;
        ?>
        <div class="wrap" style="margin-top:30px;padding:20px;background:#fff;border:1px solid #c3c4c7;border-radius:4px;">
            <h2 style="margin-top:0;"><?php esc_html_e( 'Import Regions via CSV', 'crs' ); ?></h2>

            <?php if ( $result === 'done' ) : ?>
                <div class="notice notice-success inline">
                    <p><?php printf( esc_html__( 'Import complete: %d region(s) added, %d skipped/errored.', 'crs' ), $count, $errors ); ?></p>
                </div>
            <?php elseif ( $result === 'error' ) : ?>
                <div class="notice notice-error inline">
                    <p><?php esc_html_e( 'Import failed. Please check your CSV file and try again.', 'crs' ); ?></p>
                </div>
            <?php endif; ?>

            <p style="color:#555;">
                <?php esc_html_e( 'Upload a CSV file to bulk-import regions. The first row must be a header row.', 'crs' ); ?>
            </p>
            <p><strong><?php esc_html_e( 'Required columns (in order):', 'crs' ); ?></strong><br>
                <code>name, slug, state_slug, parent_slug, description</code><br>
                <small><?php esc_html_e( 'slug, parent_slug and description may be left blank. state_slug must match an existing state slug (e.g. victoria).', 'crs' ); ?></small>
            </p>

            <form method="post" enctype="multipart/form-data"
                  action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <input type="hidden" name="action" value="crs_import_regions">
                <?php wp_nonce_field( 'crs_import_regions', 'crs_import_nonce' ); ?>
                <table class="form-table" style="max-width:500px;">
                    <tr>
                        <th><label for="crs_regions_csv"><?php esc_html_e( 'CSV File', 'crs' ); ?></label></th>
                        <td>
                            <input type="file" name="crs_regions_csv" id="crs_regions_csv" accept=".csv">
                            <p class="description"><?php esc_html_e( 'UTF-8 encoded .csv file.', 'crs' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="crs_csv_delimiter"><?php esc_html_e( 'Delimiter', 'crs' ); ?></label></th>
                        <td>
                            <select name="crs_csv_delimiter" id="crs_csv_delimiter">
                                <option value=","><?php esc_html_e( 'Comma  ( , )', 'crs' ); ?></option>
                                <option value=";"><?php esc_html_e( 'Semicolon  ( ; )', 'crs' ); ?></option>
                                <option value="&#9;"><?php esc_html_e( 'Tab', 'crs' ); ?></option>
                            </select>
                        </td>
                    </tr>
                </table>
                <?php submit_button( __( 'Import Regions', 'crs' ), 'secondary' ); ?>
            </form>

            <p style="margin-top:16px;">
                <a href="<?php echo esc_url( self::sample_csv_url() ); ?>" download="sample-regions.csv">
                    <?php esc_html_e( '⬇ Download sample CSV', 'crs' ); ?>
                </a>
            </p>
        </div>
        <?php
    }

    /** Generates a data-URI for the sample CSV download. */
    private static function sample_csv_url() {
        $csv = "name,slug,state_slug,parent_slug,description\n"
             . "Greater Melbourne,greater-melbourne,victoria,,\n"
             . "Inner Melbourne,inner-melbourne,victoria,greater-melbourne,Inner suburbs of Melbourne\n"
             . "Greater Sydney,greater-sydney,new-south-wales,,\n";
        return 'data:text/csv;charset=utf-8,' . rawurlencode( $csv );
    }

    /* ------------------------------------------------------------------
       CSV handler (admin-post)
    ------------------------------------------------------------------ */

    public static function handle_csv_import() {
        // Auth + nonce
        if (
            ! current_user_can( 'manage_categories' ) ||
            ! isset( $_POST['crs_import_nonce'] ) ||
            ! wp_verify_nonce( $_POST['crs_import_nonce'], 'crs_import_regions' )
        ) {
            wp_die( esc_html__( 'Permission denied.', 'crs' ) );
        }

        $redirect_base = admin_url( 'edit-tags.php?taxonomy=au-region&post_type=business' );

        // File check
        if ( empty( $_FILES['crs_regions_csv']['tmp_name'] ) ) {
            wp_safe_redirect( add_query_arg( 'crs_import', 'error', $redirect_base ) );
            exit;
        }

        $delimiter = isset( $_POST['crs_csv_delimiter'] ) ? $_POST['crs_csv_delimiter'] : ',';
        if ( ! in_array( $delimiter, [ ',', ';', "\t" ], true ) ) {
            $delimiter = ',';
        }

        $file = fopen( $_FILES['crs_regions_csv']['tmp_name'], 'r' );
        if ( ! $file ) {
            wp_safe_redirect( add_query_arg( 'crs_import', 'error', $redirect_base ) );
            exit;
        }

        // Skip header row
        fgetcsv( $file, 0, $delimiter );

        $inserted = 0;
        $errored  = 0;

        while ( ( $row = fgetcsv( $file, 0, $delimiter ) ) !== false ) {
            // Normalise: pad to 5 columns
            $row = array_pad( $row, 5, '' );
            [ $name, $slug, $state_slug, $parent_slug, $description ] = array_map( 'trim', $row );

            if ( $name === '' ) {
                continue; // skip blank rows
            }

            // Resolve parent term
            $parent_id = 0;
            if ( $parent_slug !== '' ) {
                $parent_term = get_term_by( 'slug', sanitize_title( $parent_slug ), 'au-region' );
                if ( $parent_term ) {
                    $parent_id = $parent_term->term_id;
                }
            }

            // Build insert args
            $args = [ 'parent' => $parent_id ];
            if ( $slug !== '' ) {
                $args['slug'] = sanitize_title( $slug );
            }
            if ( $description !== '' ) {
                $args['description'] = sanitize_textarea_field( $description );
            }

            // Skip if term already exists
            if ( term_exists( $name, 'au-region', $parent_id ) ) {
                $errored++;
                continue;
            }

            $result = wp_insert_term( sanitize_text_field( $name ), 'au-region', $args );

            if ( is_wp_error( $result ) ) {
                $errored++;
                continue;
            }

            $term_id = $result['term_id'];

            // Save state meta
            if ( $state_slug !== '' ) {
                $state_term = get_term_by( 'slug', sanitize_title( $state_slug ), 'au-state' );
                if ( $state_term ) {
                    update_term_meta( $term_id, self::STATE_META, $state_term->term_id );
                }
            }

            $inserted++;
        }

        fclose( $file );

        wp_safe_redirect( add_query_arg( [
            'crs_import'  => 'done',
            'crs_count'   => $inserted,
            'crs_errors'  => $errored,
        ], $redirect_base ) );
        exit;
    }

}

CRS_AU_Region_Fields::init();
