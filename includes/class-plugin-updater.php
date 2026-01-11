<?php
/**
 * Plugin Updater Class
 *
 * Handles automatic plugin updates from custom server.
 *
 * @package Woo_Nalda_Sync
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Plugin Updater class.
 */
class Woo_Nalda_Sync_Plugin_Updater {

    /**
     * Plugin slug.
     *
     * @var string
     */
    private $slug;

    /**
     * Plugin basename.
     *
     * @var string
     */
    private $basename;

    /**
     * Current version.
     *
     * @var string
     */
    private $version;

    /**
     * Update API URL.
     *
     * @var string
     */
    private $update_url;

    /**
     * License Manager instance.
     *
     * @var Woo_Nalda_Sync_License_Manager
     */
    private $license;

    /**
     * Cached update data.
     *
     * @var object|null
     */
    private $update_cache = null;

    /**
     * Cache key for transient.
     *
     * @var string
     */
    private $cache_key = 'woo_nalda_sync_update_check';

    /**
     * Cache expiration in seconds (6 hours).
     *
     * @var int
     */
    private $cache_expiration = 21600;

    /**
     * Constructor.
     *
     * @param Woo_Nalda_Sync_License_Manager $license License manager instance.
     */
    public function __construct( $license ) {
        $this->slug      = WOO_NALDA_SYNC_PRODUCT_SLUG;
        $this->basename  = WOO_NALDA_SYNC_PLUGIN_BASENAME;
        $this->version   = WOO_NALDA_SYNC_VERSION;
        $this->update_url = WOO_NALDA_SYNC_UPDATE_API_URL;
        $this->license   = $license;

        $this->init_hooks();
    }

    /**
     * Initialize hooks.
     */
    private function init_hooks() {
        // Check for updates.
        add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_for_update' ) );

        // Plugin information popup.
        add_filter( 'plugins_api', array( $this, 'plugin_info' ), 20, 3 );

        // Add license key to download URL.
        add_filter( 'upgrader_package_options', array( $this, 'add_license_to_download' ) );

        // Clear cache on license changes.
        add_action( 'woo_nalda_sync_license_activated', array( $this, 'clear_update_cache' ) );
        add_action( 'woo_nalda_sync_license_deactivated', array( $this, 'clear_update_cache' ) );

        // Add update message if license is invalid.
        add_action( 'in_plugin_update_message-' . $this->basename, array( $this, 'update_message' ), 10, 2 );
    }

    /**
     * Check for plugin updates.
     *
     * @param object $transient Update transient.
     * @return object Modified transient.
     */
    public function check_for_update( $transient ) {
        if ( empty( $transient->checked ) ) {
            return $transient;
        }

        // Get remote version info.
        $remote_info = $this->get_remote_info();

        if ( ! $remote_info ) {
            return $transient;
        }

        // Compare versions.
        if ( version_compare( $this->version, $remote_info->version, '<' ) ) {
            $update_info = new stdClass();
            $update_info->slug        = $this->slug;
            $update_info->plugin      = $this->basename;
            $update_info->new_version = $remote_info->version;
            $update_info->url         = $remote_info->homepage ?? '';
            $update_info->package     = $this->get_download_url( $remote_info );
            $update_info->tested      = $remote_info->tested ?? '';
            $update_info->requires_php = $remote_info->requires_php ?? '7.4';
            $update_info->requires    = $remote_info->requires ?? '5.8';

            // Add icons if available.
            if ( ! empty( $remote_info->icons ) ) {
                $update_info->icons = (array) $remote_info->icons;
            }

            // Add banners if available.
            if ( ! empty( $remote_info->banners ) ) {
                $update_info->banners = (array) $remote_info->banners;
            }

            $transient->response[ $this->basename ] = $update_info;
        } else {
            // No update available - add to no_update array.
            $no_update = new stdClass();
            $no_update->slug        = $this->slug;
            $no_update->plugin      = $this->basename;
            $no_update->new_version = $this->version;
            $no_update->url         = $remote_info->homepage ?? '';

            $transient->no_update[ $this->basename ] = $no_update;
        }

        return $transient;
    }

    /**
     * Get plugin information for the update popup.
     *
     * @param false|object|array $result The result object or array.
     * @param string             $action The type of information being requested.
     * @param object             $args   Plugin API arguments.
     * @return false|object Plugin info or false.
     */
    public function plugin_info( $result, $action, $args ) {
        if ( 'plugin_information' !== $action ) {
            return $result;
        }

        if ( $this->slug !== $args->slug ) {
            return $result;
        }

        $remote_info = $this->get_remote_info( true );

        if ( ! $remote_info ) {
            return $result;
        }

        $info = new stdClass();
        $info->name           = $remote_info->name ?? 'WooCommerce Nalda Sync';
        $info->slug           = $this->slug;
        $info->version        = $remote_info->version;
        $info->author         = $remote_info->author ?? '<a href="https://jonakyds.com">Jonakyds</a>';
        $info->author_profile = $remote_info->author_profile ?? 'https://jonakyds.com';
        $info->homepage       = $remote_info->homepage ?? 'https://jonakyds.com/plugins/woo-nalda-sync';
        $info->requires       = $remote_info->requires ?? '5.8';
        $info->tested         = $remote_info->tested ?? '';
        $info->requires_php   = $remote_info->requires_php ?? '7.4';
        $info->downloaded     = $remote_info->downloaded ?? 0;
        $info->last_updated   = $remote_info->last_updated ?? '';
        $info->download_link  = $this->get_download_url( $remote_info );

        // Sections.
        $info->sections = array(
            'description'  => $remote_info->sections->description ?? '',
            'installation' => $remote_info->sections->installation ?? '',
            'changelog'    => $remote_info->sections->changelog ?? '',
        );

        // Add FAQ if available.
        if ( ! empty( $remote_info->sections->faq ) ) {
            $info->sections['faq'] = $remote_info->sections->faq;
        }

        // Banners.
        if ( ! empty( $remote_info->banners ) ) {
            $info->banners = (array) $remote_info->banners;
        }

        // Icons.
        if ( ! empty( $remote_info->icons ) ) {
            $info->icons = (array) $remote_info->icons;
        }

        return $info;
    }

    /**
     * Get remote plugin information.
     *
     * @param bool $force_check Whether to bypass cache.
     * @return object|false Remote info or false on failure.
     */
    private function get_remote_info( $force_check = false ) {
        // Check cache first.
        if ( ! $force_check && null !== $this->update_cache ) {
            return $this->update_cache;
        }

        // Check transient cache.
        if ( ! $force_check ) {
            $cached = get_transient( $this->cache_key );
            if ( false !== $cached ) {
                $this->update_cache = $cached;
                return $cached;
            }
        }

        // Get license info for the request.
        $license_key = $this->license->get_license_key();
        $domain      = $this->license->get_domain();

        // Build request URL.
        $request_url = add_query_arg(
            array(
                'action'      => 'info',
                'slug'        => $this->slug,
                'version'     => $this->version,
                'license_key' => $license_key,
                'domain'      => $domain,
                'php_version' => phpversion(),
                'wp_version'  => get_bloginfo( 'version' ),
                'wc_version'  => defined( 'WC_VERSION' ) ? WC_VERSION : '',
            ),
            $this->update_url
        );

        // Make the request.
        $response = wp_remote_get(
            $request_url,
            array(
                'timeout' => 15,
                'headers' => array(
                    'Accept' => 'application/json',
                ),
            )
        );

        // Check for errors.
        if ( is_wp_error( $response ) ) {
            return false;
        }

        $response_code = wp_remote_retrieve_response_code( $response );
        if ( 200 !== $response_code ) {
            return false;
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body );

        if ( ! $data || ! isset( $data->version ) ) {
            return false;
        }

        // Cache the response.
        $this->update_cache = $data;
        set_transient( $this->cache_key, $data, $this->cache_expiration );

        return $data;
    }

    /**
     * Get download URL with license key.
     *
     * @param object $remote_info Remote plugin info.
     * @return string Download URL or empty string.
     */
    private function get_download_url( $remote_info ) {
        // Check if license is valid.
        if ( ! $this->license->is_valid() ) {
            return '';
        }

        // Use download_link from API if available.
        if ( ! empty( $remote_info->download_link ) ) {
            return add_query_arg(
                array(
                    'license_key' => $this->license->get_license_key(),
                    'domain'      => $this->license->get_domain(),
                ),
                $remote_info->download_link
            );
        }

        // Build download URL.
        return add_query_arg(
            array(
                'action'      => 'download',
                'slug'        => $this->slug,
                'version'     => $remote_info->version,
                'license_key' => $this->license->get_license_key(),
                'domain'      => $this->license->get_domain(),
            ),
            $this->update_url
        );
    }

    /**
     * Add license key to download URL during upgrade.
     *
     * @param array $options Upgrader package options.
     * @return array Modified options.
     */
    public function add_license_to_download( $options ) {
        if ( empty( $options['hook_extra']['plugin'] ) ) {
            return $options;
        }

        if ( $options['hook_extra']['plugin'] !== $this->basename ) {
            return $options;
        }

        // Ensure license key is in the package URL.
        if ( ! empty( $options['package'] ) && strpos( $options['package'], 'license_key' ) === false ) {
            $options['package'] = add_query_arg(
                array(
                    'license_key' => $this->license->get_license_key(),
                    'domain'      => $this->license->get_domain(),
                ),
                $options['package']
            );
        }

        return $options;
    }

    /**
     * Show update message if license is invalid.
     *
     * @param array  $plugin_data Plugin data.
     * @param object $response    Update response.
     */
    public function update_message( $plugin_data, $response ) {
        if ( ! $this->license->is_valid() ) {
            echo '<br><span style="color: #d63638; font-weight: 600;">';
            esc_html_e( 'A valid license key is required to receive automatic updates.', 'woo-nalda-sync' );
            echo ' <a href="' . esc_url( admin_url( 'admin.php?page=woo-nalda-sync&tab=license' ) ) . '">';
            esc_html_e( 'Enter your license key', 'woo-nalda-sync' );
            echo '</a></span>';
        }
    }

    /**
     * Clear update cache.
     */
    public function clear_update_cache() {
        $this->update_cache = null;
        delete_transient( $this->cache_key );
        delete_site_transient( 'update_plugins' );
    }

    /**
     * Force check for updates.
     *
     * @return object|false Remote info or false.
     */
    public function force_check() {
        $this->clear_update_cache();
        return $this->get_remote_info( true );
    }

    /**
     * Get update status for display.
     *
     * @return array Update status info.
     */
    public function get_update_status() {
        $remote_info = $this->get_remote_info();

        $status = array(
            'current_version'   => $this->version,
            'latest_version'    => $remote_info ? $remote_info->version : $this->version,
            'update_available'  => false,
            'license_valid'     => $this->license->is_valid(),
            'last_checked'      => get_option( '_site_transient_update_plugins' ) ? time() : 0,
        );

        if ( $remote_info && version_compare( $this->version, $remote_info->version, '<' ) ) {
            $status['update_available'] = true;
            $status['changelog']        = $remote_info->sections->changelog ?? '';
        }

        return $status;
    }
}
