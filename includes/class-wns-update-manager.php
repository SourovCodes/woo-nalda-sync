<?php
/**
 * Update Manager Class
 *
 * Handles plugin updates by integrating with the WordPress update system
 * and the WP Licence Manager API.
 *
 * @package Woo_Nalda_Sync
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * WNS_Update_Manager class
 */
class WNS_Update_Manager {

    /**
     * API URL
     *
     * @var string
     */
    private $api_url;

    /**
     * Product slug
     *
     * @var string
     */
    private $product_slug;

    /**
     * Plugin basename
     *
     * @var string
     */
    private $plugin_basename;

    /**
     * Current version
     *
     * @var string
     */
    private $current_version;

    /**
     * Constructor
     */
    public function __construct() {
        $this->api_url = WOO_NALDA_SYNC_API_URL;
        $this->product_slug = WOO_NALDA_SYNC_PRODUCT_SLUG;
        $this->plugin_basename = WOO_NALDA_SYNC_PLUGIN_BASENAME;
        $this->current_version = WOO_NALDA_SYNC_VERSION;

        // Hook into WordPress update system
        add_filter('pre_set_site_transient_update_plugins', array($this, 'check_for_update'));
        add_filter('plugins_api', array($this, 'plugin_info'), 10, 3);
        add_action('in_plugin_update_message-' . $this->plugin_basename, array($this, 'update_message'), 10, 2);

        // Add action link for manual update check
        add_filter('plugin_action_links_' . $this->plugin_basename, array($this, 'add_action_links'));

        // Schedule update check
        if (!wp_next_scheduled('wns_update_check')) {
            wp_schedule_event(time(), 'twicedaily', 'wns_update_check');
        }

        add_action('wns_update_check', array($this, 'scheduled_update_check'));
    }

    /**
     * Make API request
     *
     * @param string $endpoint API endpoint
     * @param array  $body     Request body
     * @return array|WP_Error
     */
    private function api_request($endpoint, $body = array()) {
        $url = trailingslashit($this->api_url) . ltrim($endpoint, '/');

        $response = wp_remote_post($url, array(
            'timeout'     => 30,
            'redirection' => 5,
            'httpversion' => '1.1',
            'blocking'    => true,
            'headers'     => array(
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json',
            ),
            'body'        => wp_json_encode($body),
            'sslverify'   => true,
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $response_body = wp_remote_retrieve_body($response);
        $data = json_decode($response_body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('json_error', __('Invalid response from update server.', 'woo-nalda-sync'));
        }

        return $data;
    }

    /**
     * Check for plugin updates
     *
     * @param object $transient Update transient
     * @return object
     */
    public function check_for_update($transient) {
        if (empty($transient->checked)) {
            return $transient;
        }

        $update_data = $this->get_update_data();

        if ($update_data && !empty($update_data['update_available'])) {
            $plugin_data = $this->get_plugin_data($update_data);
            
            if ($plugin_data) {
                $transient->response[$this->plugin_basename] = $plugin_data;
            }
        } else {
            // No update available, add to no_update list
            $transient->no_update[$this->plugin_basename] = (object) array(
                'id'            => $this->plugin_basename,
                'slug'          => $this->product_slug,
                'plugin'        => $this->plugin_basename,
                'new_version'   => $this->current_version,
                'url'           => '',
                'package'       => '',
                'icons'         => array(),
                'banners'       => array(),
                'banners_rtl'   => array(),
                'tested'        => '',
                'requires_php'  => '7.4',
                'compatibility' => new stdClass(),
            );
        }

        return $transient;
    }

    /**
     * Get update data from API
     *
     * @param bool $force Force check, bypass cache
     * @return array|false
     */
    public function get_update_data($force = false) {
        // Check cache first
        if (!$force) {
            $cached = get_transient('wns_update_check');
            if ($cached !== false) {
                return $cached;
            }
        }

        $license_key = get_option('wns_license_key', '');
        $domain = Woo_Nalda_Sync::get_site_domain();

        $response = $this->api_request('update/check', array(
            'license_key'     => $license_key,
            'domain'          => $domain,
            'product_slug'    => $this->product_slug,
            'current_version' => $this->current_version,
        ));

        if (is_wp_error($response)) {
            return false;
        }

        if (!empty($response['success'])) {
            // Cache for 12 hours
            set_transient('wns_update_check', $response, 12 * HOUR_IN_SECONDS);
            return $response;
        }

        return false;
    }

    /**
     * Get plugin data object for WordPress update system
     *
     * @param array $update_data Update data from API
     * @return object|false
     */
    private function get_plugin_data($update_data) {
        if (empty($update_data['latest_version'])) {
            return false;
        }

        // Get download URL
        $download_url = $this->get_download_url();

        return (object) array(
            'id'            => $this->plugin_basename,
            'slug'          => $this->product_slug,
            'plugin'        => $this->plugin_basename,
            'new_version'   => $update_data['latest_version'],
            'url'           => 'https://jonakyds.com/plugins/' . $this->product_slug,
            'package'       => $download_url,
            'icons'         => array(
                '1x' => WOO_NALDA_SYNC_PLUGIN_URL . 'assets/images/icon-128x128.png',
                '2x' => WOO_NALDA_SYNC_PLUGIN_URL . 'assets/images/icon-256x256.png',
            ),
            'banners'       => array(
                'low'  => WOO_NALDA_SYNC_PLUGIN_URL . 'assets/images/banner-772x250.png',
                'high' => WOO_NALDA_SYNC_PLUGIN_URL . 'assets/images/banner-1544x500.png',
            ),
            'banners_rtl'   => array(),
            'tested'        => '6.4',
            'requires_php'  => '7.4',
            'requires'      => '5.8',
            'compatibility' => new stdClass(),
        );
    }

    /**
     * Get download URL from API
     *
     * @return string
     */
    public function get_download_url() {
        $license_key = get_option('wns_license_key', '');
        $domain = Woo_Nalda_Sync::get_site_domain();

        if (empty($license_key)) {
            return '';
        }

        $response = $this->api_request('update/download', array(
            'license_key'  => $license_key,
            'domain'       => $domain,
            'product_slug' => $this->product_slug,
        ));

        if (is_wp_error($response) || empty($response['success'])) {
            return '';
        }

        return $response['download_url'] ?? '';
    }

    /**
     * Plugin info for WordPress plugin details modal
     *
     * @param object|false $result Result object
     * @param string       $action API action
     * @param object       $args   Arguments
     * @return object|false
     */
    public function plugin_info($result, $action, $args) {
        if ($action !== 'plugin_information') {
            return $result;
        }

        if (!isset($args->slug) || $args->slug !== $this->product_slug) {
            return $result;
        }

        $update_data = $this->get_update_data();

        if (!$update_data) {
            return $result;
        }

        $license_data = get_option('wns_license_data', array());
        $product_name = $license_data['product']['name'] ?? 'Woo Nalda Sync';

        return (object) array(
            'name'              => $product_name,
            'slug'              => $this->product_slug,
            'version'           => $update_data['latest_version'] ?? $this->current_version,
            'author'            => '<a href="https://jonakyds.com">Jonakyds</a>',
            'author_profile'    => 'https://jonakyds.com',
            'requires'          => '5.8',
            'tested'            => '6.4',
            'requires_php'      => '7.4',
            'sections'          => array(
                'description'  => $this->get_plugin_description(),
                'installation' => $this->get_installation_instructions(),
                'changelog'    => $this->get_changelog(),
            ),
            'download_link'     => $this->get_download_url(),
            'banners'           => array(
                'low'  => WOO_NALDA_SYNC_PLUGIN_URL . 'assets/images/banner-772x250.png',
                'high' => WOO_NALDA_SYNC_PLUGIN_URL . 'assets/images/banner-1544x500.png',
            ),
            'icons'             => array(
                '1x' => WOO_NALDA_SYNC_PLUGIN_URL . 'assets/images/icon-128x128.png',
                '2x' => WOO_NALDA_SYNC_PLUGIN_URL . 'assets/images/icon-256x256.png',
            ),
        );
    }

    /**
     * Get plugin description
     *
     * @return string
     */
    private function get_plugin_description() {
        return '<p>' . __('Woo Nalda Sync allows WooCommerce store owners to sync their products to Nalda.com and receive orders from the Nalda marketplace.', 'woo-nalda-sync') . '</p>
        <h4>' . __('Features', 'woo-nalda-sync') . '</h4>
        <ul>
            <li>' . __('Sync products to Nalda marketplace', 'woo-nalda-sync') . '</li>
            <li>' . __('Receive and process orders from Nalda', 'woo-nalda-sync') . '</li>
            <li>' . __('Automatic inventory synchronization', 'woo-nalda-sync') . '</li>
            <li>' . __('Real-time order notifications', 'woo-nalda-sync') . '</li>
        </ul>';
    }

    /**
     * Get installation instructions
     *
     * @return string
     */
    private function get_installation_instructions() {
        return '<ol>
            <li>' . __('Upload the plugin files to the /wp-content/plugins/woo-nalda-sync directory, or install the plugin through the WordPress plugins screen directly.', 'woo-nalda-sync') . '</li>
            <li>' . __('Activate the plugin through the Plugins screen in WordPress.', 'woo-nalda-sync') . '</li>
            <li>' . __('Go to WooCommerce > Nalda Sync to configure the plugin settings.', 'woo-nalda-sync') . '</li>
            <li>' . __('Enter your license key to activate the plugin.', 'woo-nalda-sync') . '</li>
        </ol>';
    }

    /**
     * Get changelog
     *
     * @return string
     */
    private function get_changelog() {
        return '<h4>1.0.0</h4>
        <ul>
            <li>' . __('Initial release', 'woo-nalda-sync') . '</li>
        </ul>';
    }

    /**
     * Show update message
     *
     * @param array  $plugin_data Plugin data
     * @param object $response    Response object
     */
    public function update_message($plugin_data, $response) {
        $license_key = get_option('wns_license_key', '');
        $license_status = get_option('wns_license_status', '');

        if (empty($license_key) || $license_status !== 'active') {
            echo '<br><span style="color: #d63638;">';
            echo esc_html__('Please activate your license to enable automatic updates.', 'woo-nalda-sync');
            echo ' <a href="' . esc_url(admin_url('admin.php?page=woo-nalda-sync')) . '">' . esc_html__('Activate License', 'woo-nalda-sync') . '</a>';
            echo '</span>';
        }
    }

    /**
     * Add action links to plugins page
     *
     * @param array $links Existing links
     * @return array
     */
    public function add_action_links($links) {
        $settings_link = '<a href="' . admin_url('admin.php?page=woo-nalda-sync') . '">' . __('Settings', 'woo-nalda-sync') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }

    /**
     * Force check for updates
     *
     * @return array
     */
    public function force_check() {
        // Clear transients
        delete_transient('wns_update_check');
        delete_site_transient('update_plugins');

        // Get fresh update data
        $update_data = $this->get_update_data(true);

        if (!$update_data) {
            return array(
                'success' => false,
                'message' => __('Unable to check for updates. Please try again later.', 'woo-nalda-sync'),
            );
        }

        if (!empty($update_data['update_available'])) {
            return array(
                'success'          => true,
                'update_available' => true,
                'current_version'  => $this->current_version,
                'latest_version'   => $update_data['latest_version'],
                'message'          => sprintf(
                    __('A new version (%s) is available. Your current version is %s.', 'woo-nalda-sync'),
                    $update_data['latest_version'],
                    $this->current_version
                ),
            );
        }

        return array(
            'success'          => true,
            'update_available' => false,
            'current_version'  => $this->current_version,
            'latest_version'   => $update_data['latest_version'] ?? $this->current_version,
            'message'          => __('You are running the latest version.', 'woo-nalda-sync'),
        );
    }

    /**
     * Scheduled update check
     */
    public function scheduled_update_check() {
        $this->get_update_data(true);
    }

    /**
     * Get current version
     *
     * @return string
     */
    public function get_current_version() {
        return $this->current_version;
    }
}
