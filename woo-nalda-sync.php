<?php
/**
 * Plugin Name: Woo Nalda Sync
 * Plugin URI: https://jonakyds.com/plugins/woo-nalda-sync
 * Description: Sync WooCommerce products to Nalda and receive orders from Nalda.com
 * Version: 1.0.0
 * Author: Jonakyds
 * Author URI: https://jonakyds.com
 * Text Domain: woo-nalda-sync
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.4
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('WOO_NALDA_SYNC_VERSION', '1.0.0');
define('WOO_NALDA_SYNC_PLUGIN_FILE', __FILE__);
define('WOO_NALDA_SYNC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WOO_NALDA_SYNC_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WOO_NALDA_SYNC_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('WOO_NALDA_SYNC_PRODUCT_SLUG', 'woo-nalda-sync');
define('WOO_NALDA_SYNC_API_URL', 'https://wplicence.jonakyds.com/api');

/**
 * Main plugin class
 */
final class Woo_Nalda_Sync {

    /**
     * Single instance of the class
     *
     * @var Woo_Nalda_Sync
     */
    private static $instance = null;

    /**
     * License Manager instance
     *
     * @var WNS_License_Manager
     */
    public $license;

    /**
     * Update Manager instance
     *
     * @var WNS_Update_Manager
     */
    public $updater;

    /**
     * Admin instance
     *
     * @var WNS_Admin
     */
    public $admin;

    /**
     * Get single instance of the class
     *
     * @return Woo_Nalda_Sync
     */
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->includes();
        $this->init_hooks();
    }

    /**
     * Include required files
     */
    private function includes() {
        require_once WOO_NALDA_SYNC_PLUGIN_DIR . 'includes/class-wns-license-manager.php';
        require_once WOO_NALDA_SYNC_PLUGIN_DIR . 'includes/class-wns-update-manager.php';
        
        if (is_admin()) {
            require_once WOO_NALDA_SYNC_PLUGIN_DIR . 'includes/admin/class-wns-admin.php';
        }
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action('plugins_loaded', array($this, 'init'), 0);
        register_activation_hook(WOO_NALDA_SYNC_PLUGIN_FILE, array($this, 'activate'));
        register_deactivation_hook(WOO_NALDA_SYNC_PLUGIN_FILE, array($this, 'deactivate'));
    }

    /**
     * Initialize plugin
     */
    public function init() {
        // Check if WooCommerce is active
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
            return;
        }

        // Load text domain
        load_plugin_textdomain('woo-nalda-sync', false, dirname(WOO_NALDA_SYNC_PLUGIN_BASENAME) . '/languages');

        // Initialize components
        $this->license = new WNS_License_Manager();
        $this->updater = new WNS_Update_Manager();
        
        if (is_admin()) {
            $this->admin = new WNS_Admin();
        }

        // Declare HPOS compatibility
        add_action('before_woocommerce_init', array($this, 'declare_hpos_compatibility'));
    }

    /**
     * Declare High-Performance Order Storage compatibility
     */
    public function declare_hpos_compatibility() {
        if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', WOO_NALDA_SYNC_PLUGIN_FILE, true);
        }
    }

    /**
     * WooCommerce missing notice
     */
    public function woocommerce_missing_notice() {
        ?>
        <div class="notice notice-error">
            <p><?php esc_html_e('Woo Nalda Sync requires WooCommerce to be installed and active.', 'woo-nalda-sync'); ?></p>
        </div>
        <?php
    }

    /**
     * Plugin activation
     */
    public function activate() {
        // Set default options
        if (!get_option('wns_license_key')) {
            add_option('wns_license_key', '');
        }
        if (!get_option('wns_license_status')) {
            add_option('wns_license_status', '');
        }
        if (!get_option('wns_license_data')) {
            add_option('wns_license_data', array());
        }
        if (!get_option('wns_local_key')) {
            add_option('wns_local_key', '');
        }

        // Clear update transients
        delete_transient('wns_update_check');
        delete_site_transient('update_plugins');

        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Clear scheduled events
        wp_clear_scheduled_hook('wns_daily_license_check');
        wp_clear_scheduled_hook('wns_update_check');

        // Clear transients
        delete_transient('wns_update_check');
        delete_transient('wns_license_valid');
    }

    /**
     * Get the site domain
     *
     * @return string
     */
    public static function get_site_domain() {
        $site_url = get_site_url();
        $parsed = parse_url($site_url);
        return isset($parsed['host']) ? $parsed['host'] : '';
    }
}

/**
 * Main instance of Woo_Nalda_Sync
 *
 * @return Woo_Nalda_Sync
 */
function woo_nalda_sync() {
    return Woo_Nalda_Sync::instance();
}

// Initialize the plugin
woo_nalda_sync();
