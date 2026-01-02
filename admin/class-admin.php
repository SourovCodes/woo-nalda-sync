<?php
/**
 * Admin Class
 *
 * Handles admin pages, settings, and functionality.
 *
 * @package Woo_Nalda_Sync
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Admin class.
 */
class Woo_Nalda_Sync_Admin {

    /**
     * License Manager instance.
     *
     * @var Woo_Nalda_Sync_License_Manager
     */
    private $license_manager;

    /**
     * Product Sync instance.
     *
     * @var Woo_Nalda_Sync_Product_Sync
     */
    private $product_sync;

    /**
     * Order Sync instance.
     *
     * @var Woo_Nalda_Sync_Order_Sync
     */
    private $order_sync;

    /**
     * Admin notices.
     *
     * @var array
     */
    private $notices = array();

    /**
     * Constructor.
     *
     * @param Woo_Nalda_Sync_License_Manager $license_manager License manager instance.
     * @param Woo_Nalda_Sync_Product_Sync    $product_sync    Product sync instance.
     * @param Woo_Nalda_Sync_Order_Sync      $order_sync      Order sync instance.
     */
    public function __construct( $license_manager, $product_sync = null, $order_sync = null ) {
        $this->license_manager = $license_manager;
        $this->product_sync    = $product_sync;
        $this->order_sync      = $order_sync;

        $this->init_hooks();
    }

    /**
     * Initialize hooks.
     */
    private function init_hooks() {
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
        add_action( 'admin_init', array( $this, 'handle_form_submissions' ) );
        add_action( 'admin_notices', array( $this, 'display_notices' ) );
        
        // License AJAX handlers.
        add_action( 'wp_ajax_woo_nalda_sync_activate_license', array( $this, 'ajax_activate_license' ) );
        add_action( 'wp_ajax_woo_nalda_sync_deactivate_license', array( $this, 'ajax_deactivate_license' ) );
        add_action( 'wp_ajax_woo_nalda_sync_validate_license', array( $this, 'ajax_validate_license' ) );
        
        // Settings AJAX handlers.
        add_action( 'wp_ajax_woo_nalda_sync_save_settings', array( $this, 'ajax_save_settings' ) );
        add_action( 'wp_ajax_woo_nalda_sync_validate_sftp', array( $this, 'ajax_validate_sftp' ) );
        add_action( 'wp_ajax_woo_nalda_sync_validate_nalda_api', array( $this, 'ajax_validate_nalda_api' ) );
        
        // Sync AJAX handlers.
        add_action( 'wp_ajax_woo_nalda_sync_run_product_sync', array( $this, 'ajax_run_product_sync' ) );
        add_action( 'wp_ajax_woo_nalda_sync_run_order_sync', array( $this, 'ajax_run_order_sync' ) );
        add_action( 'wp_ajax_woo_nalda_sync_get_upload_history', array( $this, 'ajax_get_upload_history' ) );

        // Plugin action links.
        add_filter( 'plugin_action_links_' . WOO_NALDA_SYNC_PLUGIN_BASENAME, array( $this, 'plugin_action_links' ) );
    }

    /**
     * Add admin menu.
     */
    public function add_admin_menu() {
        // Main menu.
        add_menu_page(
            __( 'Nalda Sync', 'woo-nalda-sync' ),
            __( 'Nalda Sync', 'woo-nalda-sync' ),
            'manage_woocommerce',
            'woo-nalda-sync',
            array( $this, 'render_dashboard_page' ),
            'dashicons-update',
            56
        );

        // Dashboard submenu.
        add_submenu_page(
            'woo-nalda-sync',
            __( 'Dashboard', 'woo-nalda-sync' ),
            __( 'Dashboard', 'woo-nalda-sync' ),
            'manage_woocommerce',
            'woo-nalda-sync',
            array( $this, 'render_dashboard_page' )
        );

        // Settings submenu.
        add_submenu_page(
            'woo-nalda-sync',
            __( 'Settings', 'woo-nalda-sync' ),
            __( 'Settings', 'woo-nalda-sync' ),
            'manage_woocommerce',
            'woo-nalda-sync-settings',
            array( $this, 'render_settings_page' )
        );

        // License submenu.
        add_submenu_page(
            'woo-nalda-sync',
            __( 'License', 'woo-nalda-sync' ),
            __( 'License', 'woo-nalda-sync' ),
            'manage_woocommerce',
            'woo-nalda-sync-license',
            array( $this, 'render_license_page' )
        );
    }

    /**
     * Enqueue admin scripts and styles.
     *
     * @param string $hook Current admin page hook.
     */
    public function enqueue_scripts( $hook ) {
        // Only load on plugin pages.
        if ( strpos( $hook, 'woo-nalda-sync' ) === false ) {
            return;
        }

        // Enqueue styles.
        wp_enqueue_style(
            'woo-nalda-sync-admin',
            WOO_NALDA_SYNC_PLUGIN_URL . 'admin/assets/css/admin.css',
            array(),
            WOO_NALDA_SYNC_VERSION
        );

        // Enqueue scripts.
        wp_enqueue_script(
            'woo-nalda-sync-admin',
            WOO_NALDA_SYNC_PLUGIN_URL . 'admin/assets/js/admin.js',
            array( 'jquery' ),
            WOO_NALDA_SYNC_VERSION,
            true
        );

        // Localize script.
        wp_localize_script( 'woo-nalda-sync-admin', 'wooNaldaSync', array(
            'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
            'nonce'     => wp_create_nonce( 'woo_nalda_sync_nonce' ),
            'strings'   => array(
                'activating'        => __( 'Activating...', 'woo-nalda-sync' ),
                'deactivating'      => __( 'Deactivating...', 'woo-nalda-sync' ),
                'validating'        => __( 'Validating...', 'woo-nalda-sync' ),
                'saving'            => __( 'Saving...', 'woo-nalda-sync' ),
                'saved'             => __( 'Settings saved!', 'woo-nalda-sync' ),
                'error'             => __( 'An error occurred.', 'woo-nalda-sync' ),
                'confirmDeactivate' => __( 'Are you sure you want to deactivate your license?', 'woo-nalda-sync' ),
                'testing'           => __( 'Testing...', 'woo-nalda-sync' ),
                'testConnection'    => __( 'Test Connection', 'woo-nalda-sync' ),
                'syncing'           => __( 'Syncing...', 'woo-nalda-sync' ),
                'syncProducts'      => __( 'Sync Products', 'woo-nalda-sync' ),
                'syncOrders'        => __( 'Sync Orders', 'woo-nalda-sync' ),
                'connectionSuccess' => __( 'Connection successful!', 'woo-nalda-sync' ),
                'connectionFailed'  => __( 'Connection failed.', 'woo-nalda-sync' ),
                'loading'           => __( 'Loading...', 'woo-nalda-sync' ),
                'pageInfo'          => __( 'Page {current} of {total}', 'woo-nalda-sync' ),
                'statusPending'     => __( 'Pending', 'woo-nalda-sync' ),
                'statusProcessing'  => __( 'Processing', 'woo-nalda-sync' ),
                'statusProcessed'   => __( 'Processed', 'woo-nalda-sync' ),
                'statusFailed'      => __( 'Failed', 'woo-nalda-sync' ),
                'download'          => __( 'Download', 'woo-nalda-sync' ),
                'view'              => __( 'View', 'woo-nalda-sync' ),
                'showMore'          => __( 'Show more', 'woo-nalda-sync' ),
                'showLess'          => __( 'Show less', 'woo-nalda-sync' ),
            ),
        ) );
    }

    /**
     * Handle form submissions.
     */
    public function handle_form_submissions() {
        // Handle settings save (non-AJAX fallback).
        if ( isset( $_POST['woo_nalda_sync_save_settings'] ) && isset( $_POST['_wpnonce'] ) ) {
            if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'woo_nalda_sync_settings' ) ) {
                $this->add_notice( __( 'Security check failed.', 'woo-nalda-sync' ), 'error' );
                return;
            }

            if ( ! current_user_can( 'manage_woocommerce' ) ) {
                $this->add_notice( __( 'You do not have permission to do this.', 'woo-nalda-sync' ), 'error' );
                return;
            }

            $this->save_settings( $_POST );
            $this->add_notice( __( 'Settings saved successfully.', 'woo-nalda-sync' ), 'success' );
        }
    }

    /**
     * Save settings.
     *
     * @param array $data Form data.
     * @return bool
     */
    private function save_settings( $data ) {
        $settings = array(
            // SFTP Settings
            'sftp_host'              => isset( $data['sftp_host'] ) ? sanitize_text_field( $data['sftp_host'] ) : '',
            'sftp_port'              => isset( $data['sftp_port'] ) ? absint( $data['sftp_port'] ) : 22,
            'sftp_username'          => isset( $data['sftp_username'] ) ? sanitize_text_field( $data['sftp_username'] ) : '',
            'sftp_password'          => isset( $data['sftp_password'] ) ? $data['sftp_password'] : '',
            
            // Export Settings
            'product_sync_schedule'  => isset( $data['product_sync_schedule'] ) ? sanitize_text_field( $data['product_sync_schedule'] ) : 'hourly',
            'filename_pattern'       => isset( $data['filename_pattern'] ) ? sanitize_text_field( $data['filename_pattern'] ) : 'products_{date}.csv',
            'batch_size'             => isset( $data['batch_size'] ) ? absint( $data['batch_size'] ) : 100,
            
            // Product Defaults
            'default_delivery_time'  => isset( $data['default_delivery_time'] ) ? absint( $data['default_delivery_time'] ) : 3,
            'return_period'          => isset( $data['return_period'] ) ? absint( $data['return_period'] ) : 14,
            
            // Sync Status
            'product_sync_enabled'   => isset( $data['product_sync_enabled'] ) ? 'yes' : 'no',
            
            // Order Sync Settings
            'order_sync_enabled'     => isset( $data['order_sync_enabled'] ) ? 'yes' : 'no',
            'order_sync_schedule'    => isset( $data['order_sync_schedule'] ) ? sanitize_text_field( $data['order_sync_schedule'] ) : 'hourly',
            
            // Nalda API Settings
            'nalda_api_key'          => isset( $data['nalda_api_key'] ) ? sanitize_text_field( $data['nalda_api_key'] ) : '',
            'nalda_api_url'          => isset( $data['nalda_api_url'] ) ? esc_url_raw( $data['nalda_api_url'] ) : 'https://sellers-api.nalda.com',
            
            // Advanced
            'log_enabled'            => isset( $data['log_enabled'] ) ? 'yes' : 'no',
            'notification_email'     => isset( $data['notification_email'] ) ? sanitize_email( $data['notification_email'] ) : '',
        );

        return woo_nalda_sync()->update_settings( $settings );
    }

    /**
     * AJAX: Validate SFTP credentials.
     */
    public function ajax_validate_sftp() {
        check_ajax_referer( 'woo_nalda_sync_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => __( 'You do not have permission to do this.', 'woo-nalda-sync' ) ) );
        }

        $credentials = array(
            'sftp_host'     => isset( $_POST['sftp_host'] ) ? sanitize_text_field( wp_unslash( $_POST['sftp_host'] ) ) : '',
            'sftp_port'     => isset( $_POST['sftp_port'] ) ? absint( $_POST['sftp_port'] ) : 22,
            'sftp_username' => isset( $_POST['sftp_username'] ) ? sanitize_text_field( wp_unslash( $_POST['sftp_username'] ) ) : '',
            'sftp_password' => isset( $_POST['sftp_password'] ) ? wp_unslash( $_POST['sftp_password'] ) : '',
        );

        if ( empty( $credentials['sftp_host'] ) || empty( $credentials['sftp_username'] ) || empty( $credentials['sftp_password'] ) ) {
            wp_send_json_error( array( 'message' => __( 'Please fill in all SFTP fields.', 'woo-nalda-sync' ) ) );
        }

        $result = $this->product_sync->validate_sftp_credentials( $credentials );

        if ( $result['success'] ) {
            wp_send_json_success( $result );
        } else {
            wp_send_json_error( $result );
        }
    }

    /**
     * AJAX: Validate Nalda API credentials.
     */
    public function ajax_validate_nalda_api() {
        check_ajax_referer( 'woo_nalda_sync_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => __( 'You do not have permission to do this.', 'woo-nalda-sync' ) ) );
        }

        $api_key = isset( $_POST['nalda_api_key'] ) ? sanitize_text_field( wp_unslash( $_POST['nalda_api_key'] ) ) : '';
        $api_url = isset( $_POST['nalda_api_url'] ) ? esc_url_raw( wp_unslash( $_POST['nalda_api_url'] ) ) : '';

        if ( empty( $api_key ) ) {
            wp_send_json_error( array( 'message' => __( 'Please enter your Nalda API key.', 'woo-nalda-sync' ) ) );
        }

        $result = $this->order_sync->validate_api_credentials( $api_key, $api_url );

        if ( $result['success'] ) {
            wp_send_json_success( $result );
        } else {
            wp_send_json_error( $result );
        }
    }

    /**
     * AJAX: Run product sync manually.
     */
    public function ajax_run_product_sync() {
        check_ajax_referer( 'woo_nalda_sync_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => __( 'You do not have permission to do this.', 'woo-nalda-sync' ) ) );
        }

        if ( ! $this->license_manager->is_valid() ) {
            wp_send_json_error( array( 'message' => __( 'Please activate your license first.', 'woo-nalda-sync' ) ) );
        }

        $result = $this->product_sync->run_sync();

        if ( $result['success'] ) {
            wp_send_json_success( $result );
        } else {
            wp_send_json_error( $result );
        }
    }

    /**
     * AJAX: Run order sync manually.
     */
    public function ajax_run_order_sync() {
        check_ajax_referer( 'woo_nalda_sync_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => __( 'You do not have permission to do this.', 'woo-nalda-sync' ) ) );
        }

        if ( ! $this->license_manager->is_valid() ) {
            wp_send_json_error( array( 'message' => __( 'Please activate your license first.', 'woo-nalda-sync' ) ) );
        }

        $range = isset( $_POST['range'] ) ? sanitize_text_field( wp_unslash( $_POST['range'] ) ) : 'today';
        
        $result = $this->order_sync->run_sync( $range );

        if ( $result['success'] ) {
            wp_send_json_success( $result );
        } else {
            wp_send_json_error( $result );
        }
    }

    /**
     * AJAX: Get CSV upload history.
     */
    public function ajax_get_upload_history() {
        check_ajax_referer( 'woo_nalda_sync_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => __( 'You do not have permission to do this.', 'woo-nalda-sync' ) ) );
        }

        if ( ! $this->license_manager->is_valid() ) {
            wp_send_json_error( array( 'message' => __( 'Please activate your license first.', 'woo-nalda-sync' ) ) );
        }

        $per_page = isset( $_POST['per_page'] ) ? absint( $_POST['per_page'] ) : 10;
        $page     = isset( $_POST['page'] ) ? absint( $_POST['page'] ) : 1;

        $result = $this->product_sync->get_upload_history( $per_page, $page );

        if ( $result['success'] ) {
            wp_send_json_success( $result );
        } else {
            wp_send_json_error( $result );
        }
    }

    /**
     * AJAX: Activate license.
     */
    public function ajax_activate_license() {
        check_ajax_referer( 'woo_nalda_sync_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => __( 'You do not have permission to do this.', 'woo-nalda-sync' ) ) );
        }

        $license_key = isset( $_POST['license_key'] ) ? sanitize_text_field( wp_unslash( $_POST['license_key'] ) ) : '';

        if ( empty( $license_key ) ) {
            wp_send_json_error( array( 'message' => __( 'Please enter a license key.', 'woo-nalda-sync' ) ) );
        }

        $result = $this->license_manager->activate( $license_key );

        if ( $result['success'] ) {
            wp_send_json_success( $result );
        } else {
            wp_send_json_error( $result );
        }
    }

    /**
     * AJAX: Deactivate license.
     */
    public function ajax_deactivate_license() {
        check_ajax_referer( 'woo_nalda_sync_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => __( 'You do not have permission to do this.', 'woo-nalda-sync' ) ) );
        }

        $reason = isset( $_POST['reason'] ) ? sanitize_text_field( wp_unslash( $_POST['reason'] ) ) : '';

        $result = $this->license_manager->deactivate( $reason );

        if ( $result['success'] ) {
            wp_send_json_success( $result );
        } else {
            wp_send_json_error( $result );
        }
    }

    /**
     * AJAX: Validate license.
     */
    public function ajax_validate_license() {
        check_ajax_referer( 'woo_nalda_sync_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => __( 'You do not have permission to do this.', 'woo-nalda-sync' ) ) );
        }

        $result = $this->license_manager->validate_license();

        if ( $result['success'] ) {
            wp_send_json_success( $result );
        } else {
            wp_send_json_error( $result );
        }
    }

    /**
     * AJAX: Save settings.
     */
    public function ajax_save_settings() {
        check_ajax_referer( 'woo_nalda_sync_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => __( 'You do not have permission to do this.', 'woo-nalda-sync' ) ) );
        }

        // Parse serialized form data.
        $form_data = array();
        if ( isset( $_POST['form_data'] ) ) {
            parse_str( wp_unslash( $_POST['form_data'] ), $form_data ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        }

        $this->save_settings( $form_data );

        wp_send_json_success( array( 'message' => __( 'Settings saved successfully.', 'woo-nalda-sync' ) ) );
    }

    /**
     * Add admin notice.
     *
     * @param string $message Notice message.
     * @param string $type    Notice type (success, error, warning, info).
     */
    private function add_notice( $message, $type = 'info' ) {
        $this->notices[] = array(
            'message' => $message,
            'type'    => $type,
        );
    }

    /**
     * Display admin notices.
     */
    public function display_notices() {
        foreach ( $this->notices as $notice ) {
            printf(
                '<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
                esc_attr( $notice['type'] ),
                esc_html( $notice['message'] )
            );
        }
    }

    /**
     * Plugin action links.
     *
     * @param array $links Existing links.
     * @return array Modified links.
     */
    public function plugin_action_links( $links ) {
        $plugin_links = array(
            '<a href="' . admin_url( 'admin.php?page=woo-nalda-sync-settings' ) . '">' . __( 'Settings', 'woo-nalda-sync' ) . '</a>',
            '<a href="' . admin_url( 'admin.php?page=woo-nalda-sync-license' ) . '">' . __( 'License', 'woo-nalda-sync' ) . '</a>',
        );

        return array_merge( $plugin_links, $links );
    }

    /**
     * Render dashboard page.
     */
    public function render_dashboard_page() {
        $is_licensed     = $this->license_manager->is_valid();
        $settings        = woo_nalda_sync()->get_setting();
        $stats           = woo_nalda_sync()->get_sync_stats();
        $next_sync_times = woo_nalda_sync()->get_next_sync_times();
        
        // Get WooCommerce settings.
        $wc_settings = array();
        if ( class_exists( 'WooCommerce' ) ) {
            $wc_settings = woo_nalda_sync()->get_woocommerce_settings();
        }

        include WOO_NALDA_SYNC_PLUGIN_DIR . 'admin/views/dashboard.php';
    }

    /**
     * Render settings page.
     */
    public function render_settings_page() {
        $settings    = woo_nalda_sync()->get_setting();
        $is_licensed = $this->license_manager->is_valid();
        
        // Get WooCommerce settings.
        $wc_settings = array();
        if ( class_exists( 'WooCommerce' ) ) {
            $wc_settings = woo_nalda_sync()->get_woocommerce_settings();
        }

        include WOO_NALDA_SYNC_PLUGIN_DIR . 'admin/views/settings.php';
    }

    /**
     * Render license page.
     */
    public function render_license_page() {
        $license_key     = $this->license_manager->get_license_key();
        $license_data    = $this->license_manager->get_license_data();
        $is_licensed     = $this->license_manager->is_valid();
        $masked_key      = $this->license_manager->mask_license_key();
        $domain          = $this->license_manager->get_domain();
        $days_remaining  = $this->license_manager->get_days_remaining();
        $expiration_date = $this->license_manager->get_expiration_date();

        include WOO_NALDA_SYNC_PLUGIN_DIR . 'admin/views/license.php';
    }
}
