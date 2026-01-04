<?php
/**
 * Plugin Name: WooCommerce Nalda Sync
 * Plugin URI: https://jonakyds.com/plugins/woo-nalda-sync
 * Description: Sync your WooCommerce store with Nalda for seamless inventory and order management.
 * Version: 1.0.0
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
define( 'WOO_NALDA_SYNC_VERSION', '1.0.0' );
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
     * Product Sync instance.
     *
     * @var Woo_Nalda_Sync_Product_Sync
     */
    public $product_sync;

    /**
     * Order Sync instance.
     *
     * @var Woo_Nalda_Sync_Order_Sync
     */
    public $order_sync;

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
        $this->includes();
        $this->init_hooks();
    }

    /**
     * Include required files.
     */
    private function includes() {
        require_once WOO_NALDA_SYNC_PLUGIN_DIR . 'includes/class-license-manager.php';
        require_once WOO_NALDA_SYNC_PLUGIN_DIR . 'includes/class-product-sync.php';
        require_once WOO_NALDA_SYNC_PLUGIN_DIR . 'includes/class-order-sync.php';
        
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
        
        register_activation_hook( __FILE__, array( $this, 'activate' ) );
        register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );

        // Cron actions.
        add_filter( 'cron_schedules', array( $this, 'add_cron_schedules' ) );
    }

    /**
     * Initialize plugin.
     */
    public function init() {
        // Load text domain.
        load_plugin_textdomain( 'woo-nalda-sync', false, dirname( WOO_NALDA_SYNC_PLUGIN_BASENAME ) . '/languages' );

        // Initialize license manager.
        $this->license = new Woo_Nalda_Sync_License_Manager();

        // Initialize sync classes.
        $this->product_sync = new Woo_Nalda_Sync_Product_Sync( $this->license );
        $this->order_sync   = new Woo_Nalda_Sync_Order_Sync( $this->license );

        // Initialize admin.
        if ( is_admin() ) {
            $this->admin = new Woo_Nalda_Sync_Admin( $this->license, $this->product_sync, $this->order_sync );
        }

        // Setup cron schedules.
        $this->setup_cron_schedules();
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
     * Setup cron schedules based on settings.
     */
    public function setup_cron_schedules() {
        $settings = $this->get_setting();

        // Product sync schedule.
        if ( isset( $settings['product_sync_enabled'] ) && 'yes' === $settings['product_sync_enabled'] ) {
            $product_schedule = isset( $settings['product_sync_schedule'] ) ? $settings['product_sync_schedule'] : 'hourly';
            $this->schedule_event( 'woo_nalda_sync_product_sync', $product_schedule );
        } else {
            wp_clear_scheduled_hook( 'woo_nalda_sync_product_sync' );
        }

        // Order sync schedule.
        if ( isset( $settings['order_sync_enabled'] ) && 'yes' === $settings['order_sync_enabled'] ) {
            $order_schedule = isset( $settings['order_sync_schedule'] ) ? $settings['order_sync_schedule'] : 'hourly';
            $this->schedule_event( 'woo_nalda_sync_order_sync', $order_schedule );
        } else {
            wp_clear_scheduled_hook( 'woo_nalda_sync_order_sync' );
        }
    }

    /**
     * Schedule a cron event with the specified recurrence.
     *
     * @param string $hook       Hook name.
     * @param string $recurrence Recurrence schedule.
     */
    private function schedule_event( $hook, $recurrence ) {
        $next_scheduled = wp_next_scheduled( $hook );

        // If already scheduled with same recurrence, do nothing.
        if ( $next_scheduled ) {
            $current_schedule = wp_get_schedule( $hook );
            if ( $current_schedule === $recurrence ) {
                return;
            }
            // Clear existing schedule if recurrence changed.
            wp_clear_scheduled_hook( $hook );
        }

        // Schedule new event.
        wp_schedule_event( time(), $recurrence, $hook );
    }

    /**
     * Plugin activation.
     */
    public function activate() {
        // Set default options.
        $default_settings = array(
            // SFTP Settings
            'sftp_host'              => '',
            'sftp_port'              => '22',
            'sftp_username'          => '',
            'sftp_password'          => '',
            
            // Export Settings
            'product_sync_schedule'  => 'hourly',
            'filename_pattern'       => 'products_{date}.csv',
            'batch_size'             => '100',
            
            // Product Defaults
            'default_delivery_time'  => '3',
            'return_period'          => '14',
            
            // Sync Status
            'product_sync_enabled'   => 'no',
            
            // Order Sync Settings
            'order_sync_enabled'     => 'no',
            'order_sync_schedule'    => 'hourly',
            
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

        // Flush rewrite rules.
        flush_rewrite_rules();
    }

    /**
     * Plugin deactivation.
     */
    public function deactivate() {
        // Clear scheduled hooks.
        wp_clear_scheduled_hook( 'woo_nalda_sync_product_sync' );
        wp_clear_scheduled_hook( 'woo_nalda_sync_order_sync' );
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
        $result = update_option( 'woo_nalda_sync_settings', $updated_settings );

        // Re-setup cron schedules when settings change.
        if ( $result ) {
            $this->setup_cron_schedules();
        }

        return $result;
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
            'product_sync' => wp_next_scheduled( 'woo_nalda_sync_product_sync' ),
            'order_sync'   => wp_next_scheduled( 'woo_nalda_sync_order_sync' ),
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
