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
     * Product Export instance.
     *
     * @var Woo_Nalda_Sync_Product_Export
     */
    private $product_export;

    /**
     * Order Import instance.
     *
     * @var Woo_Nalda_Sync_Order_Import
     */
    private $order_import;

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
     * @param Woo_Nalda_Sync_Product_Export    $product_export    Product export instance.
     * @param Woo_Nalda_Sync_Order_Import      $order_import      Order import instance.
     */
    public function __construct( $license_manager, $product_export = null, $order_import = null ) {
        $this->license_manager = $license_manager;
        $this->product_export    = $product_export;
        $this->order_import      = $order_import;

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
        add_action( 'wp_ajax_woo_nalda_sync_run_product_export', array( $this, 'ajax_run_product_export' ) );
        add_action( 'wp_ajax_woo_nalda_sync_run_order_import', array( $this, 'ajax_run_order_import' ) );
        add_action( 'wp_ajax_woo_nalda_sync_run_order_status_export', array( $this, 'ajax_run_order_status_export' ) );
        add_action( 'wp_ajax_woo_nalda_sync_get_upload_history', array( $this, 'ajax_get_upload_history' ) );
        add_action( 'wp_ajax_woo_nalda_sync_get_sync_logs', array( $this, 'ajax_get_sync_logs' ) );
        add_action( 'wp_ajax_woo_nalda_sync_clear_sync_logs', array( $this, 'ajax_clear_sync_logs' ) );

        // Plugin update AJAX handlers.
        add_action( 'wp_ajax_woo_nalda_sync_check_update', array( $this, 'ajax_check_update' ) );
        add_action( 'wp_ajax_woo_nalda_sync_run_update', array( $this, 'ajax_run_update' ) );

        // Delivery note PDF download handler.
        add_action( 'wp_ajax_woo_nalda_sync_download_delivery_note', array( $this, 'ajax_download_delivery_note' ) );

        // Plugin action links.
        add_filter( 'plugin_action_links_' . WOO_NALDA_SYNC_PLUGIN_BASENAME, array( $this, 'plugin_action_links' ) );

        // Order meta box for Nalda commission (admin only).
        add_action( 'add_meta_boxes', array( $this, 'add_nalda_order_meta_box' ) );
        
        // Save Nalda delivery fields when order is saved.
        add_action( 'woocommerce_process_shop_order_meta', array( $this, 'save_nalda_delivery_fields' ), 10, 1 );
        // For HPOS.
        add_action( 'woocommerce_before_order_object_save', array( $this, 'save_nalda_delivery_fields_hpos' ), 10, 1 );
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

        // Logs submenu.
        add_submenu_page(
            'woo-nalda-sync',
            __( 'Logs', 'woo-nalda-sync' ),
            __( 'Logs', 'woo-nalda-sync' ),
            'manage_woocommerce',
            'woo-nalda-sync-logs',
            array( $this, 'render_logs_page' )
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
                'exportProducts'    => __( 'Export Products', 'woo-nalda-sync' ),
                'importOrders'      => __( 'Import Orders', 'woo-nalda-sync' ),
                'exporting'         => __( 'Exporting...', 'woo-nalda-sync' ),
                'importing'         => __( 'Importing...', 'woo-nalda-sync' ),
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
                'queued'            => __( 'Queued', 'woo-nalda-sync' ),
                'processing'        => __( 'Processing...', 'woo-nalda-sync' ),
                // Sync logs strings.
                'loadingLogs'       => __( 'Loading logs...', 'woo-nalda-sync' ),
                'confirmClearLogs'  => __( 'Are you sure you want to clear all sync logs?', 'woo-nalda-sync' ),
                'noLogs'            => __( 'No sync logs yet. Run a sync to see activity here.', 'woo-nalda-sync' ),
                'logTime'           => __( 'Time', 'woo-nalda-sync' ),
                'logType'           => __( 'Type', 'woo-nalda-sync' ),
                'logTrigger'        => __( 'Trigger', 'woo-nalda-sync' ),
                'logStatus'         => __( 'Status', 'woo-nalda-sync' ),
                'logSummary'        => __( 'Summary', 'woo-nalda-sync' ),
                'productExport'     => __( 'Product Export', 'woo-nalda-sync' ),
                'orderImport'       => __( 'Order Import', 'woo-nalda-sync' ),
                'triggerManual'     => __( 'Manual', 'woo-nalda-sync' ),
                'triggerAutomatic'  => __( 'Automatic', 'woo-nalda-sync' ),
                // Order status export strings.
                'exportingOrderStatus' => __( 'Exporting...', 'woo-nalda-sync' ),
                'orderStatusExport'    => __( 'Order Status Export', 'woo-nalda-sync' ),
                // Update strings.
                'checkingUpdate'    => __( 'Checking for updates...', 'woo-nalda-sync' ),
                'checkUpdate'       => __( 'Check for Updates', 'woo-nalda-sync' ),
                'updateAvailable'   => __( 'Update Available', 'woo-nalda-sync' ),
                'noUpdateAvailable' => __( 'You are running the latest version.', 'woo-nalda-sync' ),
                'updating'          => __( 'Updating...', 'woo-nalda-sync' ),
                'updateNow'         => __( 'Update Now', 'woo-nalda-sync' ),
                'updateSuccess'     => __( 'Plugin updated successfully! Reloading...', 'woo-nalda-sync' ),
                'updateError'       => __( 'Update failed. Please try again or update manually.', 'woo-nalda-sync' ),
                'currentVersion'    => __( 'Current Version', 'woo-nalda-sync' ),
                'latestVersion'     => __( 'Latest Version', 'woo-nalda-sync' ),
                'releaseNotes'      => __( 'Release Notes', 'woo-nalda-sync' ),
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
            'product_export_schedule'  => isset( $data['product_export_schedule'] ) ? sanitize_text_field( $data['product_export_schedule'] ) : 'hourly',
            'filename_pattern'       => isset( $data['filename_pattern'] ) ? sanitize_text_field( $data['filename_pattern'] ) : 'products_{date}.csv',
            'batch_size'             => isset( $data['batch_size'] ) ? absint( $data['batch_size'] ) : 100,
            
            // Product Defaults
            'default_delivery_time'  => isset( $data['default_delivery_time'] ) ? absint( $data['default_delivery_time'] ) : 3,
            'return_period'          => isset( $data['return_period'] ) ? absint( $data['return_period'] ) : 14,
            
            // Product Export Status
            'product_export_enabled'   => isset( $data['product_export_enabled'] ) ? 'yes' : 'no',
            'sync_default_mode'      => isset( $data['sync_default_mode'] ) ? sanitize_text_field( $data['sync_default_mode'] ) : 'include_all',
            
            // Order Import Settings
            'order_import_enabled'     => isset( $data['order_import_enabled'] ) ? 'yes' : 'no',
            'order_import_schedule'    => isset( $data['order_import_schedule'] ) ? sanitize_text_field( $data['order_import_schedule'] ) : 'hourly',
            'order_import_range'     => isset( $data['order_import_range'] ) ? sanitize_text_field( $data['order_import_range'] ) : 'today',
            'order_reduce_stock'     => isset( $data['order_reduce_stock'] ) ? 'yes' : 'no',
            
            // Order Status Export Settings
            'order_status_export_enabled'  => isset( $data['order_status_export_enabled'] ) ? 'yes' : 'no',
            'order_status_export_schedule' => isset( $data['order_status_export_schedule'] ) ? sanitize_text_field( $data['order_status_export_schedule'] ) : 'hourly',
            
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

        $result = $this->product_export->validate_sftp_credentials( $credentials );

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

        $result = $this->order_import->validate_api_credentials( $api_key, $api_url );

        if ( $result['success'] ) {
            wp_send_json_success( $result );
        } else {
            wp_send_json_error( $result );
        }
    }

    /**
     * AJAX: Run product export manually.
     */
    public function ajax_run_product_export() {
        check_ajax_referer( 'woo_nalda_sync_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => __( 'You do not have permission to do this.', 'woo-nalda-sync' ) ) );
        }

        if ( ! $this->license_manager->is_valid() ) {
            wp_send_json_error( array( 'message' => __( 'Please activate your license first.', 'woo-nalda-sync' ) ) );
        }

        $result = $this->product_export->run_sync( Woo_Nalda_Sync_Logger::TRIGGER_MANUAL );

        // Verify cron is still scheduled after manual sync.
        $this->verify_cron_after_sync();

        if ( $result['success'] ) {
            wp_send_json_success( $result );
        } else {
            wp_send_json_error( $result );
        }
    }

    /**
     * AJAX: Run order import manually.
     */
    public function ajax_run_order_import() {
        check_ajax_referer( 'woo_nalda_sync_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => __( 'You do not have permission to do this.', 'woo-nalda-sync' ) ) );
        }

        if ( ! $this->license_manager->is_valid() ) {
            wp_send_json_error( array( 'message' => __( 'Please activate your license first.', 'woo-nalda-sync' ) ) );
        }

        try {
            // Get range from settings if not provided.
            $settings = woo_nalda_sync()->get_setting();
            $range    = isset( $_POST['range'] ) ? sanitize_text_field( wp_unslash( $_POST['range'] ) ) : ( isset( $settings['order_import_range'] ) ? $settings['order_import_range'] : 'today' );
            
            $result = $this->order_import->run_sync( $range, Woo_Nalda_Sync_Logger::TRIGGER_MANUAL );

            // Verify cron is still scheduled after manual sync.
            $this->verify_cron_after_sync();

            if ( $result['success'] ) {
                wp_send_json_success( $result );
            } else {
                wp_send_json_error( $result );
            }
        } catch ( Exception $e ) {
            wp_send_json_error( array( 
                'message' => sprintf( __( 'Error: %s', 'woo-nalda-sync' ), $e->getMessage() ),
            ) );
        }
    }

    /**
     * AJAX: Run order status export manually.
     */
    public function ajax_run_order_status_export() {
        check_ajax_referer( 'woo_nalda_sync_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => __( 'You do not have permission to do this.', 'woo-nalda-sync' ) ) );
        }

        if ( ! $this->license_manager->is_valid() ) {
            wp_send_json_error( array( 'message' => __( 'Please activate your license first.', 'woo-nalda-sync' ) ) );
        }

        try {
            $result = $this->order_import->run_order_status_export( Woo_Nalda_Sync_Logger::TRIGGER_MANUAL );

            if ( $result['success'] ) {
                wp_send_json_success( $result );
            } else {
                wp_send_json_error( $result );
            }
        } catch ( Exception $e ) {
            wp_send_json_error( array(
                'message' => sprintf( __( 'Error: %s', 'woo-nalda-sync' ), $e->getMessage() ),
            ) );
        }
    }

    /**
     * Verify and repair cron schedules after manual sync.
     * This ensures that manual sync operations don't inadvertently
     * break the scheduled sync functionality.
     */
    private function verify_cron_after_sync() {
        $settings = woo_nalda_sync()->get_setting();
        $needs_reschedule = false;

        // Check product export schedule.
        if ( ! empty( $settings['product_export_enabled'] ) && 'yes' === $settings['product_export_enabled'] ) {
            if ( ! wp_next_scheduled( 'woo_nalda_sync_product_export' ) ) {
                $needs_reschedule = true;
            }
        }

        // Check order import schedule.
        if ( ! empty( $settings['order_import_enabled'] ) && 'yes' === $settings['order_import_enabled'] ) {
            if ( ! wp_next_scheduled( 'woo_nalda_sync_order_import' ) ) {
                $needs_reschedule = true;
            }
        }

        // Check order status export schedule.
        if ( ! empty( $settings['order_status_export_enabled'] ) && 'yes' === $settings['order_status_export_enabled'] ) {
            if ( ! wp_next_scheduled( 'woo_nalda_sync_order_status_export' ) ) {
                $needs_reschedule = true;
            }
        }

        // Reschedule if needed.
        if ( $needs_reschedule ) {
            woo_nalda_sync()->reschedule_cron_events( $settings );
        }
    }

    /**
     * AJAX: Get sync logs.
     */
    public function ajax_get_sync_logs() {
        check_ajax_referer( 'woo_nalda_sync_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => __( 'You do not have permission to do this.', 'woo-nalda-sync' ) ) );
        }

        $limit = isset( $_POST['limit'] ) ? absint( $_POST['limit'] ) : 20;

        $logs = woo_nalda_sync_logger()->get_recent_logs( $limit );

        wp_send_json_success( array( 'logs' => $logs ) );
    }

    /**
     * AJAX: Clear sync logs.
     */
    public function ajax_clear_sync_logs() {
        check_ajax_referer( 'woo_nalda_sync_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => __( 'You do not have permission to do this.', 'woo-nalda-sync' ) ) );
        }

        woo_nalda_sync_logger()->clear_logs();

        wp_send_json_success( array( 'message' => __( 'Logs cleared successfully.', 'woo-nalda-sync' ) ) );
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
        $status   = isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : '';
        $csv_type = isset( $_POST['csv_type'] ) ? sanitize_text_field( wp_unslash( $_POST['csv_type'] ) ) : '';

        $result = $this->product_export->get_upload_history( $per_page, $page, $status, $csv_type );

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

    /**
     * Render logs page.
     */
    public function render_logs_page() {
        include WOO_NALDA_SYNC_PLUGIN_DIR . 'admin/views/logs.php';
    }

    /**
     * Add meta box for Nalda order info (commission, fees, etc.)
     * Only shows on orders imported from Nalda.
     */
    public function add_nalda_order_meta_box() {
        $screen = wc_get_container()->get( \Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController::class )->custom_orders_table_usage_is_enabled()
            ? wc_get_page_screen_id( 'shop-order' )
            : 'shop_order';

        add_meta_box(
            'woo_nalda_sync_order_info',
            __( 'Nalda Marketplace Info', 'woo-nalda-sync' ),
            array( $this, 'render_nalda_order_meta_box' ),
            $screen,
            'side',
            'high'
        );
    }

    /**
     * Render the Nalda order info meta box.
     *
     * @param WP_Post|WC_Order $post_or_order Post object or order object (HPOS).
     */
    public function render_nalda_order_meta_box( $post_or_order ) {
        // Get the order object.
        if ( $post_or_order instanceof WC_Order ) {
            $order = $post_or_order;
        } else {
            $order = wc_get_order( $post_or_order->ID );
        }

        if ( ! $order ) {
            return;
        }

        // Check if this is a Nalda order.
        $nalda_order_id = $order->get_meta( '_nalda_order_id' );

        if ( empty( $nalda_order_id ) ) {
            echo '<p style="color: #666; font-style: italic;">' . esc_html__( 'This order was not imported from Nalda.', 'woo-nalda-sync' ) . '</p>';
            return;
        }

        // Get Nalda metadata.
        $commission            = floatval( $order->get_meta( '_nalda_commission' ) );
        $commission_percentage = floatval( $order->get_meta( '_nalda_commission_percentage' ) );
        $fee                   = floatval( $order->get_meta( '_nalda_fee' ) );
        $refund                = floatval( $order->get_meta( '_nalda_refund' ) );
        $payout_status         = $order->get_meta( '_nalda_payout_status' );
        $imported_at           = $order->get_meta( '_nalda_imported_at' );
        
        // Get end customer email for reference.
        $end_customer_email = $order->get_meta( '_nalda_end_customer_email' );

        // Calculate totals.
        // Note: Order total is already the net amount (after commission deduction).
        $net_revenue    = floatval( $order->get_total() ); // This is what we receive
        $customer_total = $net_revenue + $commission; // This is what customer paid to Nalda

        // Get currency.
        $currency = $order->get_currency();

        ?>
        <style>
            .wns-order-meta-box { margin: -6px -12px -12px; }
            .wns-order-meta-row { display: flex; justify-content: space-between; padding: 8px 12px; border-bottom: 1px solid #f0f0f0; }
            .wns-order-meta-row:last-child { border-bottom: none; }
            .wns-order-meta-label { color: #646970; font-size: 12px; }
            .wns-order-meta-value { font-weight: 500; text-align: right; }
            .wns-order-meta-value.negative { color: #d63638; }
            .wns-order-meta-value.positive { color: #00a32a; }
            .wns-order-meta-divider { border-top: 2px solid #dcdcde; margin: 0; }
            .wns-order-meta-total { background: #f6f7f7; }
            .wns-order-meta-total .wns-order-meta-label { font-weight: 600; color: #1d2327; }
            .wns-order-meta-total .wns-order-meta-value { font-weight: 600; font-size: 14px; }
            .wns-order-meta-badge { display: inline-block; padding: 2px 8px; border-radius: 3px; font-size: 11px; font-weight: 500; }
            .wns-order-meta-badge.pending { background: #fcf0e3; color: #9a6700; }
            .wns-order-meta-badge.paid { background: #d4edda; color: #155724; }
        </style>
        <div class="wns-order-meta-box">
            <div class="wns-order-meta-row">
                <span class="wns-order-meta-label"><?php esc_html_e( 'Nalda Order ID', 'woo-nalda-sync' ); ?></span>
                <span class="wns-order-meta-value">#<?php echo esc_html( $nalda_order_id ); ?></span>
            </div>

            <?php if ( $end_customer_email ) : ?>
            <div class="wns-order-meta-row">
                <span class="wns-order-meta-label"><?php esc_html_e( 'End Customer', 'woo-nalda-sync' ); ?></span>
                <span class="wns-order-meta-value" style="font-size: 11px;"><?php echo esc_html( $end_customer_email ); ?></span>
            </div>
            <?php endif; ?>

            <hr class="wns-order-meta-divider">

            <div class="wns-order-meta-row">
                <span class="wns-order-meta-label"><?php esc_html_e( 'Customer Paid', 'woo-nalda-sync' ); ?></span>
                <span class="wns-order-meta-value"><?php echo wc_price( $customer_total, array( 'currency' => $currency ) ); ?></span>
            </div>

            <div class="wns-order-meta-row">
                <span class="wns-order-meta-label">
                    <?php 
                    printf( 
                        /* translators: %s: commission percentage */
                        esc_html__( 'Nalda Commission (%s%%)', 'woo-nalda-sync' ), 
                        esc_html( number_format( $commission_percentage, 1 ) ) 
                    ); 
                    ?>
                </span>
                <span class="wns-order-meta-value negative">-<?php echo wc_price( $commission, array( 'currency' => $currency ) ); ?></span>
            </div>

            <hr class="wns-order-meta-divider">

            <div class="wns-order-meta-row wns-order-meta-total">
                <span class="wns-order-meta-label"><?php esc_html_e( 'Your Revenue (Order Total)', 'woo-nalda-sync' ); ?></span>
                <span class="wns-order-meta-value positive"><?php echo wc_price( $net_revenue, array( 'currency' => $currency ) ); ?></span>
            </div>

            <div class="wns-order-meta-row">
                <span class="wns-order-meta-label"><?php esc_html_e( 'Payout Status', 'woo-nalda-sync' ); ?></span>
                <span class="wns-order-meta-value">
                    <?php
                    $status_class = 'pending';
                    $status_label = __( 'Pending', 'woo-nalda-sync' );
                    
                    $payout_status_lower = strtolower( $payout_status );
                    if ( 'paid' === $payout_status_lower || 'paid_out' === $payout_status_lower ) {
                        $status_class = 'paid';
                        $status_label = __( 'Paid', 'woo-nalda-sync' );
                    } elseif ( 'partially_paid_out' === $payout_status_lower ) {
                        $status_class = 'pending';
                        $status_label = __( 'Partially Paid', 'woo-nalda-sync' );
                    } elseif ( 'open' === $payout_status_lower ) {
                        $status_class = 'pending';
                        $status_label = __( 'Open', 'woo-nalda-sync' );
                    } elseif ( 'error' === $payout_status_lower ) {
                        $status_class = 'pending';
                        $status_label = __( 'Error', 'woo-nalda-sync' );
                    } elseif ( ! empty( $payout_status ) ) {
                        $status_label = ucfirst( str_replace( '_', ' ', $payout_status ) );
                    }
                    ?>
                    <span class="wns-order-meta-badge <?php echo esc_attr( $status_class ); ?>"><?php echo esc_html( $status_label ); ?></span>
                </span>
            </div>

            <?php if ( $imported_at ) : ?>
            <div class="wns-order-meta-row">
                <span class="wns-order-meta-label"><?php esc_html_e( 'Imported', 'woo-nalda-sync' ); ?></span>
                <span class="wns-order-meta-value" style="font-size: 11px; color: #646970;"><?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $imported_at ) ) ); ?></span>
            </div>
            <?php endif; ?>

            <hr class="wns-order-meta-divider">
            <p style="margin: 12px 12px 8px; font-weight: 600; color: #1d2327; font-size: 12px;">
                <?php esc_html_e( 'Delivery Information', 'woo-nalda-sync' ); ?>
            </p>

            <?php
            // Get delivery fields.
            $nalda_state                  = $order->get_meta( '_nalda_state' );
            $nalda_expected_delivery_date = $order->get_meta( '_nalda_expected_delivery_date' );
            $nalda_tracking_code          = $order->get_meta( '_nalda_tracking_code' );

            // Available Nalda states.
            $nalda_states = array(
                ''                 => __( '-- Select State --', 'woo-nalda-sync' ),
                'IN_PREPARATION'   => __( 'In Preparation', 'woo-nalda-sync' ),
                'IN_DELIVERY'      => __( 'In Delivery', 'woo-nalda-sync' ),
                'DELIVERED'        => __( 'Delivered', 'woo-nalda-sync' ),
                'UNDELIVERABLE'    => __( 'Undeliverable', 'woo-nalda-sync' ),
                'CANCELLED'        => __( 'Cancelled', 'woo-nalda-sync' ),
                'READY_TO_COLLECT' => __( 'Ready to Collect', 'woo-nalda-sync' ),
                'COLLECTED'        => __( 'Collected', 'woo-nalda-sync' ),
                'NOT_PICKED_UP'    => __( 'Not Picked Up', 'woo-nalda-sync' ),
                'RETURNED'         => __( 'Returned', 'woo-nalda-sync' ),
                'DISPUTE'          => __( 'Dispute', 'woo-nalda-sync' ),
            );

            wp_nonce_field( 'woo_nalda_sync_delivery_fields', 'woo_nalda_sync_delivery_nonce' );
            ?>

            <div class="wns-order-meta-row" style="flex-direction: column; gap: 4px;">
                <label class="wns-order-meta-label" for="_nalda_state"><?php esc_html_e( 'State', 'woo-nalda-sync' ); ?></label>
                <select name="_nalda_state" id="_nalda_state" style="width: 100%;">
                    <?php foreach ( $nalda_states as $value => $label ) : ?>
                        <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $nalda_state, $value ); ?>>
                            <?php echo esc_html( $label ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="wns-order-meta-row" style="flex-direction: column; gap: 4px;">
                <label class="wns-order-meta-label" for="_nalda_expected_delivery_date"><?php esc_html_e( 'Expected Delivery Date', 'woo-nalda-sync' ); ?></label>
                <input type="date" name="_nalda_expected_delivery_date" id="_nalda_expected_delivery_date" value="<?php echo esc_attr( $nalda_expected_delivery_date ); ?>" style="width: 100%;">
            </div>

            <div class="wns-order-meta-row" style="flex-direction: column; gap: 4px;">
                <label class="wns-order-meta-label" for="_nalda_tracking_code"><?php esc_html_e( 'Tracking Code', 'woo-nalda-sync' ); ?></label>
                <input type="text" name="_nalda_tracking_code" id="_nalda_tracking_code" value="<?php echo esc_attr( $nalda_tracking_code ); ?>" style="width: 100%;" placeholder="<?php esc_attr_e( 'Enter tracking code', 'woo-nalda-sync' ); ?>">
            </div>

            <hr class="wns-order-meta-divider">
            
            <div class="wns-order-meta-row" style="padding: 12px;">
                <?php
                $delivery_note_url = add_query_arg( array(
                    'action'   => 'woo_nalda_sync_download_delivery_note',
                    'order_id' => $order->get_id(),
                    'nonce'    => wp_create_nonce( 'woo_nalda_sync_delivery_note_' . $order->get_id() ),
                ), admin_url( 'admin-ajax.php' ) );
                ?>
                <a href="<?php echo esc_url( $delivery_note_url ); ?>" 
                   class="button button-secondary" 
                   style="width: 100%; text-align: center;"
                   target="_blank">
                    <span class="dashicons dashicons-media-document" style="vertical-align: middle; margin-right: 5px;"></span>
                    <?php esc_html_e( 'Download Delivery Note', 'woo-nalda-sync' ); ?>
                </a>
            </div>
        </div>
        <?php
    }

    /**
     * Save Nalda delivery fields when order is saved (legacy).
     *
     * @param int $order_id Order ID.
     */
    public function save_nalda_delivery_fields( $order_id ) {
        // Verify nonce.
        if ( ! isset( $_POST['woo_nalda_sync_delivery_nonce'] ) || ! wp_verify_nonce( $_POST['woo_nalda_sync_delivery_nonce'], 'woo_nalda_sync_delivery_fields' ) ) {
            return;
        }

        // Check permission.
        if ( ! current_user_can( 'edit_shop_orders' ) ) {
            return;
        }

        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return;
        }

        // Only save for Nalda orders.
        $nalda_order_id = $order->get_meta( '_nalda_order_id' );
        if ( empty( $nalda_order_id ) ) {
            return;
        }

        // Save state.
        if ( isset( $_POST['_nalda_state'] ) ) {
            $order->update_meta_data( '_nalda_state', sanitize_text_field( $_POST['_nalda_state'] ) );
        }

        // Save expected delivery date.
        if ( isset( $_POST['_nalda_expected_delivery_date'] ) ) {
            $order->update_meta_data( '_nalda_expected_delivery_date', sanitize_text_field( $_POST['_nalda_expected_delivery_date'] ) );
        }

        // Save tracking code.
        if ( isset( $_POST['_nalda_tracking_code'] ) ) {
            $order->update_meta_data( '_nalda_tracking_code', sanitize_text_field( $_POST['_nalda_tracking_code'] ) );
        }

        $order->save();
    }

    /**
     * Save Nalda delivery fields when order is saved (HPOS).
     *
     * @param WC_Order $order Order object.
     */
    public function save_nalda_delivery_fields_hpos( $order ) {
        // Only process in admin context with POST data.
        if ( ! is_admin() || empty( $_POST ) ) {
            return;
        }

        // Verify nonce.
        if ( ! isset( $_POST['woo_nalda_sync_delivery_nonce'] ) || ! wp_verify_nonce( $_POST['woo_nalda_sync_delivery_nonce'], 'woo_nalda_sync_delivery_fields' ) ) {
            return;
        }

        // Check permission.
        if ( ! current_user_can( 'edit_shop_orders' ) ) {
            return;
        }

        // Only save for Nalda orders.
        $nalda_order_id = $order->get_meta( '_nalda_order_id' );
        if ( empty( $nalda_order_id ) ) {
            return;
        }

        // Save state.
        if ( isset( $_POST['_nalda_state'] ) ) {
            $order->update_meta_data( '_nalda_state', sanitize_text_field( $_POST['_nalda_state'] ) );
        }

        // Save expected delivery date.
        if ( isset( $_POST['_nalda_expected_delivery_date'] ) ) {
            $order->update_meta_data( '_nalda_expected_delivery_date', sanitize_text_field( $_POST['_nalda_expected_delivery_date'] ) );
        }

        // Save tracking code.
        if ( isset( $_POST['_nalda_tracking_code'] ) ) {
            $order->update_meta_data( '_nalda_tracking_code', sanitize_text_field( $_POST['_nalda_tracking_code'] ) );
        }
    }

    /**
     * AJAX: Check for plugin updates.
     */
    public function ajax_check_update() {
        check_ajax_referer( 'woo_nalda_sync_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => __( 'You do not have permission to do this.', 'woo-nalda-sync' ) ) );
        }

        $updater = woo_nalda_sync()->updater;

        if ( ! $updater ) {
            wp_send_json_error( array( 'message' => __( 'Update system not available.', 'woo-nalda-sync' ) ) );
        }

        $result = $updater->force_check_update();

        if ( $result['success'] ) {
            wp_send_json_success( $result );
        } else {
            wp_send_json_error( $result );
        }
    }

    /**
     * AJAX: Run plugin update.
     */
    public function ajax_run_update() {
        check_ajax_referer( 'woo_nalda_sync_nonce', 'nonce' );

        if ( ! current_user_can( 'update_plugins' ) ) {
            wp_send_json_error( array( 'message' => __( 'You do not have permission to update plugins.', 'woo-nalda-sync' ) ) );
        }

        // Get the updater instance.
        $updater = woo_nalda_sync()->updater;

        if ( ! $updater ) {
            wp_send_json_error( array( 'message' => __( 'Update system not available.', 'woo-nalda-sync' ) ) );
        }

        // Force check for update to get latest info.
        $update_info = $updater->force_check_update();

        if ( ! $update_info['success'] || ! $update_info['update_available'] ) {
            wp_send_json_error( array( 'message' => __( 'No update available.', 'woo-nalda-sync' ) ) );
        }

        // Include necessary files for WordPress upgrade.
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/misc.php';

        // Create a custom skin to capture output.
        $skin = new \WP_Ajax_Upgrader_Skin();

        // Create the upgrader.
        $upgrader = new \Plugin_Upgrader( $skin );

        // Clear update cache to ensure fresh data.
        delete_site_transient( 'update_plugins' );
        wp_update_plugins();

        // Run the upgrade.
        $result = $upgrader->upgrade( WOO_NALDA_SYNC_PLUGIN_BASENAME );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        if ( $result === false ) {
            $messages = $skin->get_upgrade_messages();
            $error_message = ! empty( $messages ) ? implode( ' ', $messages ) : __( 'Update failed. Please try again.', 'woo-nalda-sync' );
            wp_send_json_error( array( 'message' => $error_message ) );
        }

        wp_send_json_success( array(
            'message' => __( 'Plugin updated successfully!', 'woo-nalda-sync' ),
            'reload'  => true,
        ) );
    }

    /**
     * AJAX: Download delivery note PDF.
     */
    public function ajax_download_delivery_note() {
        // Get order ID from request.
        $order_id = isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0;

        if ( ! $order_id ) {
            wp_die( __( 'Invalid order ID.', 'woo-nalda-sync' ) );
        }

        // Verify nonce.
        if ( ! isset( $_GET['nonce'] ) || ! wp_verify_nonce( $_GET['nonce'], 'woo_nalda_sync_delivery_note_' . $order_id ) ) {
            wp_die( __( 'Security check failed.', 'woo-nalda-sync' ) );
        }

        // Check permission.
        if ( ! current_user_can( 'edit_shop_orders' ) ) {
            wp_die( __( 'You do not have permission to do this.', 'woo-nalda-sync' ) );
        }

        // Get the order.
        $order = wc_get_order( $order_id );

        if ( ! $order ) {
            wp_die( __( 'Order not found.', 'woo-nalda-sync' ) );
        }

        // Check if this is a Nalda order.
        $nalda_order_id = $order->get_meta( '_nalda_order_id' );

        if ( empty( $nalda_order_id ) ) {
            wp_die( __( 'This is not a Nalda order.', 'woo-nalda-sync' ) );
        }

        // Load the PDF generator class if not already loaded.
        if ( ! class_exists( 'Woo_Nalda_Sync_Delivery_Note_PDF' ) ) {
            require_once WOO_NALDA_SYNC_PLUGIN_DIR . 'includes/class-delivery-note-pdf.php';
        }

        // Generate and output PDF.
        $pdf_generator = new Woo_Nalda_Sync_Delivery_Note_PDF( $order );
        $pdf_generator->generate();

        // The generate() method exits, so this won't be reached.
        exit;
    }
}
