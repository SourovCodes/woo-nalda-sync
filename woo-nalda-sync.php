<?php
/**
 * Plugin Name: WooCommerce Nalda Sync
 * Plugin URI: https://jonakyds.com/plugins/woo-nalda-sync
 * Description: Sync your WooCommerce store with Nalda for seamless inventory and order management.
 * Version: 1.0.6
 * Author: Jonakyds
 * Author URI: https://jonakyds.com
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: woo-nalda-sync
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define plugin constants.
define( 'WOO_NALDA_SYNC_VERSION', '1.0.6' );
define( 'WOO_NALDA_SYNC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WOO_NALDA_SYNC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WOO_NALDA_SYNC_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'WOO_NALDA_SYNC_PRODUCT_SLUG', 'woo-nalda-sync' );
define( 'WOO_NALDA_SYNC_LICENSE_API_URL', 'https://license-manager-jonakyds.vercel.app/api/v2/licenses' );

/**
 * Declare compatibility with WooCommerce features.
 */
add_action( 'before_woocommerce_init', function() {
    if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
    }
} );

/**
 * Main plugin class.
 */
final class Woo_Nalda_Sync {

    /**
     * Single instance of the class.
     *
     * @var Woo_Nalda_Sync
     */
    private static $instance = null;

    /**
     * License Manager instance.
     *
     * @var Woo_Nalda_Sync_License_Manager
     */
    public $license;

    /**
     * Admin instance.
     *
     * @var Woo_Nalda_Sync_Admin
     */
    public $admin;

    /**
     * Product Export instance.
     *
     * @var Woo_Nalda_Sync_Product_Export
     */
    public $product_export;

    /**
     * Order Import instance.
     *
     * @var Woo_Nalda_Sync_Order_Import
     */
    public $order_import;

    /**
     * Plugin Updater instance.
     *
     * @var Woo_Nalda_Sync_Plugin_Updater
     */
    public $updater;

    /**
     * Get single instance of the class.
     *
     * @return Woo_Nalda_Sync
     */
    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor.
     */
    private function __construct() {
        // Register cron schedules FIRST - must be done before any scheduling.
        add_filter( 'cron_schedules', array( $this, 'add_cron_schedules' ) );
        
        $this->includes();
        $this->init_hooks();
    }

    /**
     * Include required files.
     */
    private function includes() {
        require_once WOO_NALDA_SYNC_PLUGIN_DIR . 'includes/class-license-manager.php';
        require_once WOO_NALDA_SYNC_PLUGIN_DIR . 'includes/class-sync-logger.php';
        require_once WOO_NALDA_SYNC_PLUGIN_DIR . 'includes/class-product-export.php';
        require_once WOO_NALDA_SYNC_PLUGIN_DIR . 'includes/class-order-import.php';
        require_once WOO_NALDA_SYNC_PLUGIN_DIR . 'includes/class-product-meta.php';
        require_once WOO_NALDA_SYNC_PLUGIN_DIR . 'includes/class-plugin-updater.php';
        
        if ( is_admin() ) {
            require_once WOO_NALDA_SYNC_PLUGIN_DIR . 'admin/class-admin.php';
        }
    }

    /**
     * Initialize hooks.
     */
    private function init_hooks() {
        add_action( 'init', array( $this, 'init' ), 0 );
        add_action( 'plugins_loaded', array( $this, 'on_plugins_loaded' ) );
        
        // Use static methods for activation/deactivation.
        register_activation_hook( __FILE__, array( __CLASS__, 'activate_plugin' ) );
        register_deactivation_hook( __FILE__, array( __CLASS__, 'deactivate_plugin' ) );
    }

    /**
     * Initialize plugin.
     */
    public function init() {
        // Load text domain.
        load_plugin_textdomain( 'woo-nalda-sync', false, dirname( WOO_NALDA_SYNC_PLUGIN_BASENAME ) . '/languages' );

        // Initialize license manager.
        $this->license = new Woo_Nalda_Sync_License_Manager();

        // Initialize plugin updater.
        $this->updater = new Woo_Nalda_Sync_Plugin_Updater();

        // Initialize sync classes.
        $this->product_export = new Woo_Nalda_Sync_Product_Export( $this->license );
        $this->order_import   = new Woo_Nalda_Sync_Order_Import( $this->license );

        // Initialize product meta (for per-product export settings).
        if ( is_admin() ) {
            new Woo_Nalda_Sync_Product_Meta();
        }

        // Initialize admin.
        if ( is_admin() ) {
            $this->admin = new Woo_Nalda_Sync_Admin( $this->license, $this->product_export, $this->order_import );
        }

        // Check if we need to setup cron on first run after activation.
        if ( get_transient( 'woo_nalda_sync_activation' ) ) {
            delete_transient( 'woo_nalda_sync_activation' );
            $this->reschedule_cron_events();
        }

        // Periodically verify cron schedules are intact (runs once per hour max).
        $this->maybe_verify_cron_schedules();
    }

    /**
     * Verify and repair cron schedules if needed.
     * This runs at most once per hour to prevent performance issues.
     */
    private function maybe_verify_cron_schedules() {
        // Use a transient to limit how often this runs.
        $last_check = get_transient( 'woo_nalda_sync_cron_verified' );
        if ( $last_check ) {
            return;
        }

        // Set transient for 1 hour.
        set_transient( 'woo_nalda_sync_cron_verified', time(), HOUR_IN_SECONDS );

        $settings = $this->get_setting();
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
            $this->reschedule_cron_events( $settings );
        }
    }

    /**
     * On plugins loaded.
     */
    public function on_plugins_loaded() {
        // Check for WooCommerce.
        if ( ! class_exists( 'WooCommerce' ) ) {
            add_action( 'admin_notices', array( $this, 'woocommerce_missing_notice' ) );
            return;
        }
    }

    /**
     * WooCommerce missing notice.
     */
    public function woocommerce_missing_notice() {
        ?>
        <div class="notice notice-error">
            <p>
                <?php
                printf(
                    /* translators: %s: WooCommerce plugin name */
                    esc_html__( '%s requires WooCommerce to be installed and active.', 'woo-nalda-sync' ),
                    '<strong>WooCommerce Nalda Sync</strong>'
                );
                ?>
            </p>
        </div>
        <?php
    }

    /**
     * Add custom cron schedules.
     *
     * @param array $schedules Existing schedules.
     * @return array Modified schedules.
     */
    public function add_cron_schedules( $schedules ) {
        $schedules['every_15_minutes'] = array(
            'interval' => 15 * MINUTE_IN_SECONDS,
            'display'  => __( 'Every 15 Minutes', 'woo-nalda-sync' ),
        );

        $schedules['every_30_minutes'] = array(
            'interval' => 30 * MINUTE_IN_SECONDS,
            'display'  => __( 'Every 30 Minutes', 'woo-nalda-sync' ),
        );

        $schedules['every_6_hours'] = array(
            'interval' => 6 * HOUR_IN_SECONDS,
            'display'  => __( 'Every 6 Hours', 'woo-nalda-sync' ),
        );

        $schedules['every_12_hours'] = array(
            'interval' => 12 * HOUR_IN_SECONDS,
            'display'  => __( 'Every 12 Hours', 'woo-nalda-sync' ),
        );

        return $schedules;
    }

    /**
     * Reschedule cron events - call this when settings change.
     * Clears existing schedules and creates new ones starting 2 minutes from now.
     *
     * @param array|null $settings Optional. Settings array. If null, reads from database.
     */
    public function reschedule_cron_events( $settings = null ) {
        if ( null === $settings ) {
            $settings = $this->get_setting();
        }

        // Aggressively clear all existing schedules.
        $this->clear_all_cron_events();

        // Product export schedule.
        if ( ! empty( $settings['product_export_enabled'] ) && 'yes' === $settings['product_export_enabled'] ) {
            $recurrence = ! empty( $settings['product_export_schedule'] ) ? $settings['product_export_schedule'] : 'hourly';
            $timestamp  = time() + ( 2 * MINUTE_IN_SECONDS );
            wp_schedule_event( $timestamp, $recurrence, 'woo_nalda_sync_product_export' );
        }

        // Order import schedule.
        if ( ! empty( $settings['order_import_enabled'] ) && 'yes' === $settings['order_import_enabled'] ) {
            $recurrence = ! empty( $settings['order_import_schedule'] ) ? $settings['order_import_schedule'] : 'hourly';
            $timestamp  = time() + ( 2 * MINUTE_IN_SECONDS );
            wp_schedule_event( $timestamp, $recurrence, 'woo_nalda_sync_order_import' );
        }

        // Order status export schedule.
        if ( ! empty( $settings['order_status_export_enabled'] ) && 'yes' === $settings['order_status_export_enabled'] ) {
            $recurrence = ! empty( $settings['order_status_export_schedule'] ) ? $settings['order_status_export_schedule'] : 'hourly';
            $timestamp  = time() + ( 2 * MINUTE_IN_SECONDS );
            wp_schedule_event( $timestamp, $recurrence, 'woo_nalda_sync_order_status_export' );
        }
    }

    /**
     * Clear all plugin cron events.
     */
    private function clear_all_cron_events() {
        // Clear using both methods for reliability.
        wp_clear_scheduled_hook( 'woo_nalda_sync_product_export' );
        wp_clear_scheduled_hook( 'woo_nalda_sync_order_import' );
        wp_clear_scheduled_hook( 'woo_nalda_sync_order_status_export' );

        // Also unschedule by timestamp if still exists.
        $product_timestamp = wp_next_scheduled( 'woo_nalda_sync_product_export' );
        if ( $product_timestamp ) {
            wp_unschedule_event( $product_timestamp, 'woo_nalda_sync_product_export' );
        }

        $order_timestamp = wp_next_scheduled( 'woo_nalda_sync_order_import' );
        if ( $order_timestamp ) {
            wp_unschedule_event( $order_timestamp, 'woo_nalda_sync_order_import' );
        }

        $order_status_export_timestamp = wp_next_scheduled( 'woo_nalda_sync_order_status_export' );
        if ( $order_status_export_timestamp ) {
            wp_unschedule_event( $order_status_export_timestamp, 'woo_nalda_sync_order_status_export' );
        }
    }

    /**
     * Plugin activation - static method.
     */
    public static function activate_plugin() {
        // Set default options.
        $default_settings = array(
            // SFTP Settings
            'sftp_host'              => '',
            'sftp_port'              => '22',
            'sftp_username'          => '',
            'sftp_password'          => '',
            
            // Export Settings
            'product_export_schedule'  => 'hourly',
            'filename_pattern'       => 'products_{date}.csv',
            'batch_size'             => '100',
            
            // Product Defaults
            'default_delivery_time'  => '3',
            'return_period'          => '14',
            
            // Product Export Status
            'product_export_enabled'   => 'no',
            
            // Order Import Settings
            'order_import_enabled'     => 'no',
            'order_import_schedule'    => 'hourly',
            
            // Order Status Export Settings
            'order_status_export_enabled'  => 'no',
            'order_status_export_schedule' => 'hourly',
            
            // Nalda API Settings
            'nalda_api_key'          => '',
            'nalda_api_url'          => 'https://sellers-api.nalda.com',
            
            // Advanced
            'log_enabled'            => 'yes',
            'notification_email'     => get_option( 'admin_email' ),
        );

        if ( ! get_option( 'woo_nalda_sync_settings' ) ) {
            update_option( 'woo_nalda_sync_settings', $default_settings );
        }

        // Create upload directory.
        $upload_dir = wp_upload_dir();
        wp_mkdir_p( trailingslashit( $upload_dir['basedir'] ) . 'woo-nalda-sync' );

        // Add .htaccess to protect uploads.
        $htaccess_file = trailingslashit( $upload_dir['basedir'] ) . 'woo-nalda-sync/.htaccess';
        if ( ! file_exists( $htaccess_file ) ) {
            file_put_contents( $htaccess_file, 'deny from all' );
        }

        // Set transient to trigger cron setup on next init (after custom schedules are registered).
        set_transient( 'woo_nalda_sync_activation', 1, 60 );

        // Flush rewrite rules.
        flush_rewrite_rules();
    }

    /**
     * Plugin deactivation - static method.
     */
    public static function deactivate_plugin() {
        // Clear all scheduled hooks.
        wp_clear_scheduled_hook( 'woo_nalda_sync_product_export' );
        wp_clear_scheduled_hook( 'woo_nalda_sync_order_import' );
        wp_clear_scheduled_hook( 'woo_nalda_sync_order_status_export' );
        wp_clear_scheduled_hook( 'woo_nalda_sync_daily_license_check' );
        
        // Flush rewrite rules.
        flush_rewrite_rules();
    }

    /**
     * Check if license is valid.
     *
     * @return bool
     */
    public function is_licensed() {
        return $this->license && $this->license->is_valid();
    }

    /**
     * Get plugin settings.
     *
     * @param string $key Optional. Setting key.
     * @param mixed  $default Optional. Default value.
     * @return mixed
     */
    public function get_setting( $key = '', $default = '' ) {
        $settings = get_option( 'woo_nalda_sync_settings', array() );

        if ( empty( $key ) ) {
            return $settings;
        }

        return isset( $settings[ $key ] ) ? $settings[ $key ] : $default;
    }

    /**
     * Update plugin settings.
     *
     * @param array $settings Settings to update.
     * @return bool
     */
    public function update_settings( $settings ) {
        $current_settings = $this->get_setting();
        $updated_settings = array_merge( $current_settings, $settings );
        update_option( 'woo_nalda_sync_settings', $updated_settings );

        // Always reschedule cron events when settings are saved.
        // Pass the updated settings directly to avoid cache issues.
        $this->reschedule_cron_events( $updated_settings );

        return true;
    }

    /**
     * Get sync statistics.
     *
     * @return array
     */
    public function get_sync_stats() {
        return get_option( 'woo_nalda_sync_stats', array() );
    }

    /**
     * Get WooCommerce settings for display.
     *
     * @return array
     */
    public function get_woocommerce_settings() {
        $base_country = WC()->countries->get_base_country();
        $currency     = get_woocommerce_currency();
        
        // Get default tax rate.
        $tax_rate = 0;
        if ( wc_tax_enabled() ) {
            $tax_rates = WC_Tax::get_base_tax_rates();
            if ( ! empty( $tax_rates ) ) {
                $rate = array_shift( $tax_rates );
                $tax_rate = isset( $rate['rate'] ) ? (float) $rate['rate'] : 0;
            }
        }

        // Get country name.
        $countries    = WC()->countries->get_countries();
        $country_name = isset( $countries[ $base_country ] ) ? $countries[ $base_country ] : $base_country;

        return array(
            'country'      => $base_country,
            'country_name' => $country_name,
            'currency'     => $currency,
            'tax_rate'     => $tax_rate,
        );
    }

    /**
     * Get next scheduled sync times.
     *
     * @return array
     */
    public function get_next_sync_times() {
        return array(
            'product_export'      => wp_next_scheduled( 'woo_nalda_sync_product_export' ),
            'order_import'        => wp_next_scheduled( 'woo_nalda_sync_order_import' ),
            'order_status_export' => wp_next_scheduled( 'woo_nalda_sync_order_status_export' ),
        );
    }
}

/**
 * Returns the main instance of Woo_Nalda_Sync.
 *
 * @return Woo_Nalda_Sync
 */
function woo_nalda_sync() {
    return Woo_Nalda_Sync::instance();
}

// Initialize the plugin.
woo_nalda_sync();
