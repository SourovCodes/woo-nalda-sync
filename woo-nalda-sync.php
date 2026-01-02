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
define( 'WOO_NALDA_SYNC_LICENSE_API_URL', 'https://licence-manager.jonakyds.com/api/v1' );

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
    }

    /**
     * Initialize plugin.
     */
    public function init() {
        // Load text domain.
        load_plugin_textdomain( 'woo-nalda-sync', false, dirname( WOO_NALDA_SYNC_PLUGIN_BASENAME ) . '/languages' );

        // Initialize license manager.
        $this->license = new Woo_Nalda_Sync_License_Manager();

        // Initialize admin.
        if ( is_admin() ) {
            $this->admin = new Woo_Nalda_Sync_Admin( $this->license );
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
     * Plugin activation.
     */
    public function activate() {
        // Set default options.
        $default_settings = array(
            'sync_enabled'           => 'yes',
            'sync_interval'          => '15',
            'sync_products'          => 'yes',
            'sync_orders'            => 'yes',
            'sync_inventory'         => 'yes',
            'auto_update_prices'     => 'no',
            'log_enabled'            => 'yes',
            'notification_email'     => get_option( 'admin_email' ),
            'webhook_enabled'        => 'no',
            'api_timeout'            => '30',
        );

        if ( ! get_option( 'woo_nalda_sync_settings' ) ) {
            update_option( 'woo_nalda_sync_settings', $default_settings );
        }

        // Flush rewrite rules.
        flush_rewrite_rules();
    }

    /**
     * Plugin deactivation.
     */
    public function deactivate() {
        // Clear scheduled hooks.
        wp_clear_scheduled_hook( 'woo_nalda_sync_cron' );
        
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
        return update_option( 'woo_nalda_sync_settings', $updated_settings );
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
