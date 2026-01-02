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
     * Admin notices.
     *
     * @var array
     */
    private $notices = array();

    /**
     * Constructor.
     *
     * @param Woo_Nalda_Sync_License_Manager $license_manager License manager instance.
     */
    public function __construct( $license_manager ) {
        $this->license_manager = $license_manager;

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
        add_action( 'wp_ajax_woo_nalda_sync_activate_license', array( $this, 'ajax_activate_license' ) );
        add_action( 'wp_ajax_woo_nalda_sync_deactivate_license', array( $this, 'ajax_deactivate_license' ) );
        add_action( 'wp_ajax_woo_nalda_sync_validate_license', array( $this, 'ajax_validate_license' ) );
        add_action( 'wp_ajax_woo_nalda_sync_save_settings', array( $this, 'ajax_save_settings' ) );

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
                'activating'    => __( 'Activating...', 'woo-nalda-sync' ),
                'deactivating'  => __( 'Deactivating...', 'woo-nalda-sync' ),
                'validating'    => __( 'Validating...', 'woo-nalda-sync' ),
                'saving'        => __( 'Saving...', 'woo-nalda-sync' ),
                'saved'         => __( 'Settings saved!', 'woo-nalda-sync' ),
                'error'         => __( 'An error occurred.', 'woo-nalda-sync' ),
                'confirmDeactivate' => __( 'Are you sure you want to deactivate your license?', 'woo-nalda-sync' ),
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
            'sync_enabled'           => isset( $data['sync_enabled'] ) ? 'yes' : 'no',
            'sync_interval'          => isset( $data['sync_interval'] ) ? absint( $data['sync_interval'] ) : 15,
            'sync_products'          => isset( $data['sync_products'] ) ? 'yes' : 'no',
            'sync_orders'            => isset( $data['sync_orders'] ) ? 'yes' : 'no',
            'sync_inventory'         => isset( $data['sync_inventory'] ) ? 'yes' : 'no',
            'auto_update_prices'     => isset( $data['auto_update_prices'] ) ? 'yes' : 'no',
            'log_enabled'            => isset( $data['log_enabled'] ) ? 'yes' : 'no',
            'notification_email'     => isset( $data['notification_email'] ) ? sanitize_email( $data['notification_email'] ) : '',
            'webhook_enabled'        => isset( $data['webhook_enabled'] ) ? 'yes' : 'no',
            'api_timeout'            => isset( $data['api_timeout'] ) ? absint( $data['api_timeout'] ) : 30,
        );

        return woo_nalda_sync()->update_settings( $settings );
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
        $is_licensed = $this->license_manager->is_valid();
        include WOO_NALDA_SYNC_PLUGIN_DIR . 'admin/views/dashboard.php';
    }

    /**
     * Render settings page.
     */
    public function render_settings_page() {
        $settings = woo_nalda_sync()->get_setting();
        $is_licensed = $this->license_manager->is_valid();
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
