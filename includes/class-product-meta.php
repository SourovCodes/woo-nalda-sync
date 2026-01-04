<?php
/**
 * Product Meta Class
 *
 * Handles product-level Nalda sync settings, including:
 * - Per-product sync toggle on product edit pages
 * - Bulk actions in the product list
 * - Custom column showing sync status
 *
 * @package Woo_Nalda_Sync
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Product Meta class.
 */
class Woo_Nalda_Sync_Product_Meta {

    /**
     * Meta key for Nalda sync status.
     */
    const META_KEY_SYNC_ENABLED = '_nalda_sync_enabled';

    /**
     * Constructor.
     */
    public function __construct() {
        $this->init_hooks();
    }

    /**
     * Initialize hooks.
     */
    private function init_hooks() {
        // Add product data tab.
        add_filter( 'woocommerce_product_data_tabs', array( $this, 'add_product_data_tab' ) );
        add_action( 'woocommerce_product_data_panels', array( $this, 'add_product_data_panel' ) );
        add_action( 'woocommerce_process_product_meta', array( $this, 'save_product_meta' ) );

        // Add column to products list.
        add_filter( 'manage_edit-product_columns', array( $this, 'add_product_column' ), 20 );
        add_action( 'manage_product_posts_custom_column', array( $this, 'render_product_column' ), 10, 2 );

        // Add bulk actions.
        add_filter( 'bulk_actions-edit-product', array( $this, 'add_bulk_actions' ) );
        add_filter( 'handle_bulk_actions-edit-product', array( $this, 'handle_bulk_actions' ), 10, 3 );
        add_action( 'admin_notices', array( $this, 'bulk_action_notices' ) );

        // Add quick edit support.
        add_action( 'woocommerce_product_quick_edit_end', array( $this, 'quick_edit_fields' ) );
        add_action( 'woocommerce_product_quick_edit_save', array( $this, 'quick_edit_save' ) );

        // Add filter dropdown.
        add_action( 'restrict_manage_posts', array( $this, 'add_filter_dropdown' ) );
        add_filter( 'parse_query', array( $this, 'filter_by_sync_status' ) );

        // AJAX handler for inline toggle.
        add_action( 'wp_ajax_woo_nalda_sync_toggle_product', array( $this, 'ajax_toggle_product_sync' ) );

        // Output admin styles and scripts.
        add_action( 'admin_head', array( $this, 'output_admin_styles' ) );
        add_action( 'admin_footer', array( $this, 'output_admin_scripts' ) );
    }

    /**
     * Output admin styles for product list page.
     */
    public function output_admin_styles() {
        global $post_type;

        if ( 'product' !== $post_type ) {
            return;
        }
        ?>
        <style type="text/css">
            /* Nalda Sync column styling */
            .column-nalda_sync { 
                width: 70px !important; 
                text-align: center; 
                white-space: nowrap; 
            }
            /* Ensure stock column has proper width */
            .column-is_in_stock { 
                width: 90px !important; 
            }
            .wns-sync-toggle-btn { 
                cursor: pointer; 
                padding: 4px 8px; 
                border-radius: 4px; 
                font-size: 11px;
                font-weight: 500;
                display: inline-flex;
                align-items: center;
                gap: 4px;
                border: none;
                transition: all 0.2s ease;
            }
            .wns-sync-toggle-btn.synced { 
                background: #d1fae5; 
                color: #065f46; 
            }
            .wns-sync-toggle-btn.synced:hover { 
                background: #a7f3d0; 
            }
            .wns-sync-toggle-btn.not-synced { 
                background: #f3f4f6; 
                color: #6b7280; 
            }
            .wns-sync-toggle-btn.not-synced:hover { 
                background: #e5e7eb; 
            }
            .wns-sync-toggle-btn.default-sync { 
                background: #dbeafe; 
                color: #1e40af; 
            }
            .wns-sync-toggle-btn.default-sync:hover { 
                background: #bfdbfe; 
            }
            .wns-sync-toggle-btn.default-skip { 
                background: #fef3c7; 
                color: #92400e; 
            }
            .wns-sync-toggle-btn.default-skip:hover { 
                background: #fde68a; 
            }
            .wns-sync-toggle-btn .dashicons { 
                font-size: 14px; 
                width: 14px; 
                height: 14px; 
            }
            .wns-sync-toggle-btn.loading { 
                opacity: 0.6; 
                pointer-events: none; 
            }
        </style>
        <?php
    }

    /**
     * Output admin scripts for product list page.
     */
    public function output_admin_scripts() {
        global $post_type;

        if ( 'product' !== $post_type ) {
            return;
        }
        ?>
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                $(document).on("click", ".wns-sync-toggle-btn", function(e) {
                    e.preventDefault();
                    var $btn = $(this);
                    var productId = $btn.data("product-id");
                    var currentState = $btn.data("state"); // 'yes', 'no', or 'default'
                    
                    // Cycle: default -> yes -> no -> default
                    var newState;
                    if (currentState === 'default') {
                        newState = 'yes';
                    } else if (currentState === 'yes') {
                        newState = 'no';
                    } else {
                        newState = 'default';
                    }
                    
                    $btn.addClass("loading");
                    
                    $.ajax({
                        url: ajaxurl,
                        type: "POST",
                        data: {
                            action: "woo_nalda_sync_toggle_product",
                            product_id: productId,
                            state: newState,
                            nonce: "<?php echo esc_js( wp_create_nonce( 'wns_toggle_product_sync' ) ); ?>"
                        },
                        success: function(response) {
                            $btn.removeClass("loading synced not-synced default-sync default-skip");
                            if (response.success) {
                                $btn.addClass(response.data.class);
                                $btn.data("state", response.data.state);
                                $btn.html('<span class="dashicons dashicons-' + response.data.icon + '"></span> ' + response.data.label);
                            }
                        },
                        error: function() {
                            $btn.removeClass("loading");
                        }
                    });
                });
            });
        </script>
        <?php
    }

    /**
     * Add Nalda tab to product data tabs.
     *
     * @param array $tabs Existing tabs.
     * @return array Modified tabs.
     */
    public function add_product_data_tab( $tabs ) {
        $tabs['nalda_sync'] = array(
            'label'    => __( 'Nalda Sync', 'woo-nalda-sync' ),
            'target'   => 'nalda_sync_product_data',
            'class'    => array(),
            'priority' => 80,
        );

        return $tabs;
    }

    /**
     * Add Nalda panel to product data panels.
     */
    public function add_product_data_panel() {
        global $post;

        $sync_enabled = $this->is_sync_enabled( $post->ID );
        $settings     = woo_nalda_sync()->get_setting();
        $default_mode = isset( $settings['sync_default_mode'] ) ? $settings['sync_default_mode'] : 'include_all';
        ?>
        <div id="nalda_sync_product_data" class="panel woocommerce_options_panel">
            <div class="options_group">
                <p class="form-field">
                    <label for="_nalda_sync_enabled"><?php esc_html_e( 'Nalda Marketplace Sync', 'woo-nalda-sync' ); ?></label>
                    <select name="_nalda_sync_enabled" id="_nalda_sync_enabled" class="select short">
                        <option value="" <?php selected( null === $sync_enabled, true ); ?>>
                            <?php 
                            if ( 'include_all' === $default_mode ) {
                                esc_html_e( 'Use default (Sync enabled)', 'woo-nalda-sync' );
                            } else {
                                esc_html_e( 'Use default (Sync disabled)', 'woo-nalda-sync' );
                            }
                            ?>
                        </option>
                        <option value="yes" <?php selected( true === $sync_enabled, true ); ?>><?php esc_html_e( 'Enable sync for this product', 'woo-nalda-sync' ); ?></option>
                        <option value="no" <?php selected( false === $sync_enabled, true ); ?>><?php esc_html_e( 'Disable sync for this product', 'woo-nalda-sync' ); ?></option>
                    </select>
                </p>
                <p class="description" style="padding-left: 12px; margin-top: -10px;">
                    <?php esc_html_e( 'Control whether this product is included in CSV exports to Nalda Marketplace.', 'woo-nalda-sync' ); ?>
                </p>
            </div>

            <div class="options_group">
                <h4 style="padding-left: 12px; margin-bottom: 10px;"><?php esc_html_e( 'Nalda-Specific Fields', 'woo-nalda-sync' ); ?></h4>
                
                <?php
                woocommerce_wp_text_input( array(
                    'id'          => '_nalda_delivery_time',
                    'label'       => __( 'Delivery Time (days)', 'woo-nalda-sync' ),
                    'desc_tip'    => true,
                    'description' => __( 'Override the default delivery time for this product.', 'woo-nalda-sync' ),
                    'type'        => 'number',
                    'custom_attributes' => array(
                        'min'  => '0',
                        'max'  => '60',
                        'step' => '1',
                    ),
                ) );

                woocommerce_wp_select( array(
                    'id'          => '_nalda_condition',
                    'label'       => __( 'Product Condition', 'woo-nalda-sync' ),
                    'desc_tip'    => true,
                    'description' => __( 'The condition of this product.', 'woo-nalda-sync' ),
                    'options'     => array(
                        ''            => __( 'Default (New)', 'woo-nalda-sync' ),
                        'new'         => __( 'New', 'woo-nalda-sync' ),
                        'refurbished' => __( 'Refurbished', 'woo-nalda-sync' ),
                        'used'        => __( 'Used', 'woo-nalda-sync' ),
                    ),
                ) );

                woocommerce_wp_text_input( array(
                    'id'          => '_nalda_google_category',
                    'label'       => __( 'Google Product Category', 'woo-nalda-sync' ),
                    'desc_tip'    => true,
                    'description' => __( 'Google product category ID or path for this product.', 'woo-nalda-sync' ),
                ) );
                ?>
            </div>

            <div class="options_group">
                <h4 style="padding-left: 12px; margin-bottom: 10px;"><?php esc_html_e( 'Book-Specific Fields', 'woo-nalda-sync' ); ?></h4>
                
                <?php
                woocommerce_wp_text_input( array(
                    'id'          => '_nalda_author',
                    'label'       => __( 'Author', 'woo-nalda-sync' ),
                    'desc_tip'    => true,
                    'description' => __( 'Book author name.', 'woo-nalda-sync' ),
                ) );

                woocommerce_wp_text_input( array(
                    'id'          => '_nalda_publisher',
                    'label'       => __( 'Publisher', 'woo-nalda-sync' ),
                    'desc_tip'    => true,
                    'description' => __( 'Book publisher name.', 'woo-nalda-sync' ),
                ) );

                woocommerce_wp_text_input( array(
                    'id'          => '_nalda_year',
                    'label'       => __( 'Publication Year', 'woo-nalda-sync' ),
                    'desc_tip'    => true,
                    'description' => __( 'Year of publication.', 'woo-nalda-sync' ),
                    'type'        => 'number',
                    'custom_attributes' => array(
                        'min'  => '1800',
                        'max'  => '2100',
                        'step' => '1',
                    ),
                ) );

                woocommerce_wp_text_input( array(
                    'id'          => '_nalda_language',
                    'label'       => __( 'Language', 'woo-nalda-sync' ),
                    'desc_tip'    => true,
                    'description' => __( 'Book language (e.g., English, German).', 'woo-nalda-sync' ),
                ) );

                woocommerce_wp_select( array(
                    'id'          => '_nalda_format',
                    'label'       => __( 'Format', 'woo-nalda-sync' ),
                    'desc_tip'    => true,
                    'description' => __( 'Book format.', 'woo-nalda-sync' ),
                    'options'     => array(
                        ''          => __( 'Select format', 'woo-nalda-sync' ),
                        'hardcover' => __( 'Hardcover', 'woo-nalda-sync' ),
                        'paperback' => __( 'Paperback', 'woo-nalda-sync' ),
                        'ebook'     => __( 'E-book', 'woo-nalda-sync' ),
                        'audiobook' => __( 'Audiobook', 'woo-nalda-sync' ),
                    ),
                ) );
                ?>
            </div>
        </div>
        <?php
    }

    /**
     * Save product meta.
     *
     * @param int $post_id Product ID.
     */
    public function save_product_meta( $post_id ) {
        // Sync enabled status.
        if ( isset( $_POST['_nalda_sync_enabled'] ) ) {
            $value = sanitize_text_field( wp_unslash( $_POST['_nalda_sync_enabled'] ) );
            if ( '' === $value ) {
                delete_post_meta( $post_id, self::META_KEY_SYNC_ENABLED );
            } else {
                update_post_meta( $post_id, self::META_KEY_SYNC_ENABLED, $value );
            }
        }

        // Other Nalda fields.
        $fields = array(
            '_nalda_delivery_time',
            '_nalda_condition',
            '_nalda_google_category',
            '_nalda_author',
            '_nalda_publisher',
            '_nalda_year',
            '_nalda_language',
            '_nalda_format',
        );

        foreach ( $fields as $field ) {
            if ( isset( $_POST[ $field ] ) ) {
                $value = sanitize_text_field( wp_unslash( $_POST[ $field ] ) );
                if ( '' === $value ) {
                    delete_post_meta( $post_id, $field );
                } else {
                    update_post_meta( $post_id, $field, $value );
                }
            }
        }
    }

    /**
     * Add Nalda Sync column to products list.
     *
     * @param array $columns Existing columns.
     * @return array Modified columns.
     */
    public function add_product_column( $columns ) {
        $new_columns = array();

        foreach ( $columns as $key => $label ) {
            // Change SKU column label from icon to text.
            if ( 'sku' === $key ) {
                $new_columns[ $key ] = __( 'SKU', 'woo-nalda-sync' );
            } else {
                $new_columns[ $key ] = $label;
            }

            // Add Nalda column after SKU.
            if ( 'sku' === $key ) {
                $new_columns['nalda_sync'] = __( 'Nalda', 'woo-nalda-sync' );
            }
        }

        return $new_columns;
    }

    /**
     * Render Nalda Sync column content.
     *
     * @param string $column    Column name.
     * @param int    $post_id   Product ID.
     */
    public function render_product_column( $column, $post_id ) {
        if ( 'nalda_sync' !== $column ) {
            return;
        }

        $explicit_setting = $this->is_sync_enabled( $post_id );
        $settings         = woo_nalda_sync()->get_setting();
        $default_mode     = isset( $settings['sync_default_mode'] ) ? $settings['sync_default_mode'] : 'include_all';
        $default_syncs    = 'include_all' === $default_mode;

        if ( true === $explicit_setting ) {
            // Explicitly enabled
            $class = 'synced';
            $icon  = 'yes';
            $label = __( 'Sync', 'woo-nalda-sync' );
            $state = 'yes';
        } elseif ( false === $explicit_setting ) {
            // Explicitly disabled
            $class = 'not-synced';
            $icon  = 'minus';
            $label = __( 'Skip', 'woo-nalda-sync' );
            $state = 'no';
        } else {
            // Using default
            if ( $default_syncs ) {
                $class = 'default-sync';
                $icon  = 'marker';
                $label = __( 'Default', 'woo-nalda-sync' );
            } else {
                $class = 'default-skip';
                $icon  = 'marker';
                $label = __( 'Default', 'woo-nalda-sync' );
            }
            $state = 'default';
        }

        printf(
            '<button type="button" class="wns-sync-toggle-btn %s" data-product-id="%d" data-state="%s" title="%s"><span class="dashicons dashicons-%s"></span> %s</button>',
            esc_attr( $class ),
            esc_attr( $post_id ),
            esc_attr( $state ),
            esc_attr__( 'Click to toggle Nalda sync', 'woo-nalda-sync' ),
            esc_attr( $icon ),
            esc_html( $label )
        );
    }

    /**
     * Add bulk actions.
     *
     * @param array $actions Existing actions.
     * @return array Modified actions.
     */
    public function add_bulk_actions( $actions ) {
        $actions['nalda_enable_sync']  = __( 'Enable Nalda Sync', 'woo-nalda-sync' );
        $actions['nalda_disable_sync'] = __( 'Disable Nalda Sync', 'woo-nalda-sync' );
        return $actions;
    }

    /**
     * Handle bulk actions.
     *
     * @param string $redirect_to Redirect URL.
     * @param string $action      Action name.
     * @param array  $post_ids    Selected post IDs.
     * @return string Modified redirect URL.
     */
    public function handle_bulk_actions( $redirect_to, $action, $post_ids ) {
        if ( 'nalda_enable_sync' !== $action && 'nalda_disable_sync' !== $action ) {
            return $redirect_to;
        }

        $enable = 'nalda_enable_sync' === $action;
        $count  = 0;

        foreach ( $post_ids as $post_id ) {
            update_post_meta( $post_id, self::META_KEY_SYNC_ENABLED, $enable ? 'yes' : 'no' );
            $count++;
        }

        return add_query_arg(
            array(
                'nalda_sync_updated' => $count,
                'nalda_sync_action'  => $enable ? 'enabled' : 'disabled',
            ),
            $redirect_to
        );
    }

    /**
     * Display bulk action notices.
     */
    public function bulk_action_notices() {
        if ( empty( $_GET['nalda_sync_updated'] ) ) {
            return;
        }

        $count  = absint( $_GET['nalda_sync_updated'] );
        $action = isset( $_GET['nalda_sync_action'] ) ? sanitize_text_field( wp_unslash( $_GET['nalda_sync_action'] ) ) : '';

        if ( 'enabled' === $action ) {
            $message = sprintf(
                _n(
                    'Nalda sync enabled for %d product.',
                    'Nalda sync enabled for %d products.',
                    $count,
                    'woo-nalda-sync'
                ),
                $count
            );
        } else {
            $message = sprintf(
                _n(
                    'Nalda sync disabled for %d product.',
                    'Nalda sync disabled for %d products.',
                    $count,
                    'woo-nalda-sync'
                ),
                $count
            );
        }

        printf( '<div class="notice notice-success is-dismissible"><p>%s</p></div>', esc_html( $message ) );
    }

    /**
     * Add quick edit fields.
     */
    public function quick_edit_fields() {
        ?>
        <div class="inline-edit-group">
            <label class="alignleft">
                <span class="title"><?php esc_html_e( 'Nalda Sync', 'woo-nalda-sync' ); ?></span>
                <span class="input-text-wrap">
                    <select name="_nalda_sync_enabled" class="nalda_sync_enabled">
                        <option value=""><?php esc_html_e( '— No Change —', 'woo-nalda-sync' ); ?></option>
                        <option value="yes"><?php esc_html_e( 'Enable', 'woo-nalda-sync' ); ?></option>
                        <option value="no"><?php esc_html_e( 'Disable', 'woo-nalda-sync' ); ?></option>
                    </select>
                </span>
            </label>
        </div>
        <?php
    }

    /**
     * Save quick edit fields.
     *
     * @param WC_Product $product Product object.
     */
    public function quick_edit_save( $product ) {
        if ( isset( $_REQUEST['_nalda_sync_enabled'] ) && '' !== $_REQUEST['_nalda_sync_enabled'] ) {
            $value = sanitize_text_field( wp_unslash( $_REQUEST['_nalda_sync_enabled'] ) );
            update_post_meta( $product->get_id(), self::META_KEY_SYNC_ENABLED, $value );
        }
    }

    /**
     * Add filter dropdown to products list.
     *
     * @param string $post_type Current post type.
     */
    public function add_filter_dropdown( $post_type ) {
        if ( 'product' !== $post_type ) {
            return;
        }

        $selected = isset( $_GET['nalda_sync_filter'] ) ? sanitize_text_field( wp_unslash( $_GET['nalda_sync_filter'] ) ) : '';
        ?>
        <select name="nalda_sync_filter">
            <option value=""><?php esc_html_e( 'All Nalda Sync', 'woo-nalda-sync' ); ?></option>
            <option value="explicitly_enabled" <?php selected( $selected, 'explicitly_enabled' ); ?>><?php esc_html_e( 'Explicitly Enabled', 'woo-nalda-sync' ); ?></option>
            <option value="explicitly_disabled" <?php selected( $selected, 'explicitly_disabled' ); ?>><?php esc_html_e( 'Explicitly Disabled', 'woo-nalda-sync' ); ?></option>
            <option value="using_default" <?php selected( $selected, 'using_default' ); ?>><?php esc_html_e( 'Using Default', 'woo-nalda-sync' ); ?></option>
        </select>
        <?php
    }

    /**
     * Filter products by Nalda sync status.
     *
     * @param WP_Query $query Query object.
     */
    public function filter_by_sync_status( $query ) {
        global $pagenow;

        if ( ! is_admin() || 'edit.php' !== $pagenow || ! $query->is_main_query() ) {
            return;
        }

        if ( 'product' !== $query->get( 'post_type' ) ) {
            return;
        }

        if ( empty( $_GET['nalda_sync_filter'] ) ) {
            return;
        }

        $filter = sanitize_text_field( wp_unslash( $_GET['nalda_sync_filter'] ) );

        if ( 'explicitly_enabled' === $filter ) {
            // Products explicitly set to sync (meta value = 'yes')
            $meta_query = array(
                array(
                    'key'     => self::META_KEY_SYNC_ENABLED,
                    'value'   => 'yes',
                    'compare' => '=',
                ),
            );
        } elseif ( 'explicitly_disabled' === $filter ) {
            // Products explicitly set to not sync (meta value = 'no')
            $meta_query = array(
                array(
                    'key'     => self::META_KEY_SYNC_ENABLED,
                    'value'   => 'no',
                    'compare' => '=',
                ),
            );
        } elseif ( 'using_default' === $filter ) {
            // Products using default behavior (no meta set or empty value)
            $meta_query = array(
                'relation' => 'OR',
                array(
                    'key'     => self::META_KEY_SYNC_ENABLED,
                    'compare' => 'NOT EXISTS',
                ),
                array(
                    'key'     => self::META_KEY_SYNC_ENABLED,
                    'value'   => '',
                    'compare' => '=',
                ),
            );
        } else {
            return;
        }

        $query->set( 'meta_query', $meta_query );
    }

    /**
     * AJAX handler for inline toggle.
     */
    public function ajax_toggle_product_sync() {
        check_ajax_referer( 'wns_toggle_product_sync', 'nonce' );

        if ( ! current_user_can( 'edit_products' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'woo-nalda-sync' ) ) );
        }

        $product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
        $state      = isset( $_POST['state'] ) ? sanitize_text_field( wp_unslash( $_POST['state'] ) ) : '';

        if ( ! $product_id ) {
            wp_send_json_error( array( 'message' => __( 'Invalid product.', 'woo-nalda-sync' ) ) );
        }

        // Get default mode for response
        $settings      = woo_nalda_sync()->get_setting();
        $default_mode  = isset( $settings['sync_default_mode'] ) ? $settings['sync_default_mode'] : 'include_all';
        $default_syncs = 'include_all' === $default_mode;

        // Update meta based on state
        if ( 'yes' === $state ) {
            update_post_meta( $product_id, self::META_KEY_SYNC_ENABLED, 'yes' );
            $response = array(
                'state'   => 'yes',
                'class'   => 'synced',
                'icon'    => 'yes',
                'label'   => __( 'Sync', 'woo-nalda-sync' ),
                'message' => __( 'Sync enabled.', 'woo-nalda-sync' ),
            );
        } elseif ( 'no' === $state ) {
            update_post_meta( $product_id, self::META_KEY_SYNC_ENABLED, 'no' );
            $response = array(
                'state'   => 'no',
                'class'   => 'not-synced',
                'icon'    => 'minus',
                'label'   => __( 'Skip', 'woo-nalda-sync' ),
                'message' => __( 'Sync disabled.', 'woo-nalda-sync' ),
            );
        } else {
            // Reset to default
            delete_post_meta( $product_id, self::META_KEY_SYNC_ENABLED );
            $response = array(
                'state'   => 'default',
                'class'   => $default_syncs ? 'default-sync' : 'default-skip',
                'icon'    => 'marker',
                'label'   => __( 'Default', 'woo-nalda-sync' ),
                'message' => __( 'Using default setting.', 'woo-nalda-sync' ),
            );
        }

        wp_send_json_success( $response );
    }

    /**
     * Check if sync is explicitly enabled/disabled for a product.
     *
     * @param int $product_id Product ID.
     * @return bool|null True if enabled, false if disabled, null if using default.
     */
    public function is_sync_enabled( $product_id ) {
        $value = get_post_meta( $product_id, self::META_KEY_SYNC_ENABLED, true );

        if ( 'yes' === $value ) {
            return true;
        }

        if ( 'no' === $value ) {
            return false;
        }

        return null; // Using default.
    }

    /**
     * Check if a product should be synced (considering defaults).
     *
     * @param int $product_id Product ID.
     * @return bool Whether the product should be synced.
     */
    public function should_sync_product( $product_id ) {
        $explicit_setting = $this->is_sync_enabled( $product_id );

        // If explicitly set, use that value.
        if ( null !== $explicit_setting ) {
            return $explicit_setting;
        }

        // Otherwise, use the default mode from settings.
        $settings     = woo_nalda_sync()->get_setting();
        $default_mode = isset( $settings['sync_default_mode'] ) ? $settings['sync_default_mode'] : 'include_all';

        // 'include_all' means sync by default, 'exclude_all' means don't sync by default.
        return 'include_all' === $default_mode;
    }

    /**
     * Get count of products with sync enabled.
     *
     * @return int Count of products.
     */
    public function get_sync_enabled_count() {
        global $wpdb;

        $settings     = woo_nalda_sync()->get_setting();
        $default_mode = isset( $settings['sync_default_mode'] ) ? $settings['sync_default_mode'] : 'include_all';

        if ( 'include_all' === $default_mode ) {
            // Count all products minus those explicitly disabled.
            $total = wp_count_posts( 'product' )->publish;
            $disabled = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->postmeta} pm
                    INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
                    WHERE pm.meta_key = %s AND pm.meta_value = 'no' AND p.post_status = 'publish'",
                    self::META_KEY_SYNC_ENABLED
                )
            );
            return max( 0, $total - (int) $disabled );
        } else {
            // Count only those explicitly enabled.
            return (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->postmeta} pm
                    INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
                    WHERE pm.meta_key = %s AND pm.meta_value = 'yes' AND p.post_status = 'publish'",
                    self::META_KEY_SYNC_ENABLED
                )
            );
        }
    }
}
