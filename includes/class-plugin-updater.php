<?php
/**
 * Plugin Updater Class
 *
 * Handles plugin updates from GitHub releases.
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
     * GitHub repository owner.
     *
     * @var string
     */
    private $github_username = 'jonakyds';

    /**
     * GitHub repository name.
     *
     * @var string
     */
    private $github_repo = 'woo-nalda-sync';

    /**
     * Plugin slug.
     *
     * @var string
     */
    private $plugin_slug;

    /**
     * Plugin basename.
     *
     * @var string
     */
    private $plugin_basename;

    /**
     * Current plugin version.
     *
     * @var string
     */
    private $current_version;

    /**
     * GitHub API response cache.
     *
     * @var object|null
     */
    private $github_response = null;

    /**
     * Cache expiration time in seconds.
     *
     * @var int
     */
    private $cache_expiration = 3600; // 1 hour

    /**
     * Constructor.
     */
    public function __construct() {
        $this->plugin_slug     = WOO_NALDA_SYNC_PRODUCT_SLUG;
        $this->plugin_basename = WOO_NALDA_SYNC_PLUGIN_BASENAME;
        $this->current_version = WOO_NALDA_SYNC_VERSION;

        $this->init_hooks();
    }

    /**
     * Initialize hooks.
     */
    private function init_hooks() {
        // Check for updates.
        add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_update' ) );
        
        // Plugin information popup.
        add_filter( 'plugins_api', array( $this, 'plugin_info' ), 20, 3 );
        
        // After plugin install.
        add_filter( 'upgrader_post_install', array( $this, 'after_install' ), 10, 3 );

        // Clear update cache on plugin activation.
        register_activation_hook( WOO_NALDA_SYNC_PLUGIN_DIR . 'woo-nalda-sync.php', array( $this, 'clear_update_cache' ) );
    }

    /**
     * Get GitHub release information.
     *
     * @param bool $force_check Force fresh API call.
     * @return object|false GitHub release data or false on failure.
     */
    public function get_github_release( $force_check = false ) {
        // Check transient cache first.
        if ( ! $force_check ) {
            $cached = get_transient( 'woo_nalda_sync_github_release' );
            if ( false !== $cached ) {
                $this->github_response = $cached;
                return $this->github_response;
            }
        }

        // API URL for latest release.
        $api_url = sprintf(
            'https://api.github.com/repos/%s/%s/releases/latest',
            $this->github_username,
            $this->github_repo
        );

        // Make API request.
        $response = wp_remote_get(
            $api_url,
            array(
                'headers' => array(
                    'Accept'     => 'application/vnd.github.v3+json',
                    'User-Agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . home_url(),
                ),
                'timeout' => 15,
            )
        );

        // Check for errors.
        if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
            return false;
        }

        // Parse response.
        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body );

        if ( empty( $data ) || isset( $data->message ) ) {
            return false;
        }

        // Extract version from tag name (remove 'v' prefix if present).
        $version = ltrim( $data->tag_name, 'v' );

        // Find the zip asset.
        $download_url = '';
        if ( ! empty( $data->assets ) ) {
            foreach ( $data->assets as $asset ) {
                if ( 'application/zip' === $asset->content_type || strpos( $asset->name, '.zip' ) !== false ) {
                    $download_url = $asset->browser_download_url;
                    break;
                }
            }
        }

        // Fallback to zipball URL.
        if ( empty( $download_url ) && ! empty( $data->zipball_url ) ) {
            $download_url = $data->zipball_url;
        }

        // Build release info object.
        $release_info = (object) array(
            'version'      => $version,
            'name'         => $data->name,
            'body'         => $data->body,
            'download_url' => $download_url,
            'published_at' => $data->published_at,
            'html_url'     => $data->html_url,
            'tag_name'     => $data->tag_name,
        );

        // Cache the response.
        set_transient( 'woo_nalda_sync_github_release', $release_info, $this->cache_expiration );
        $this->github_response = $release_info;

        return $this->github_response;
    }

    /**
     * Check for plugin updates.
     *
     * @param object $transient Update transient.
     * @return object Modified transient.
     */
    public function check_update( $transient ) {
        if ( empty( $transient->checked ) ) {
            return $transient;
        }

        // Get GitHub release info.
        $release = $this->get_github_release();

        if ( ! $release || empty( $release->version ) || empty( $release->download_url ) ) {
            return $transient;
        }

        // Compare versions.
        if ( version_compare( $release->version, $this->current_version, '>' ) ) {
            $plugin = array(
                'slug'        => $this->plugin_slug,
                'plugin'      => $this->plugin_basename,
                'new_version' => $release->version,
                'url'         => $release->html_url,
                'package'     => $release->download_url,
                'icons'       => array(),
                'banners'     => array(),
                'tested'      => get_bloginfo( 'version' ),
                'requires'    => '5.8',
                'requires_php' => '7.4',
            );

            $transient->response[ $this->plugin_basename ] = (object) $plugin;
        } else {
            // No update available - add to no_update.
            $transient->no_update[ $this->plugin_basename ] = (object) array(
                'slug'        => $this->plugin_slug,
                'plugin'      => $this->plugin_basename,
                'new_version' => $this->current_version,
                'url'         => 'https://jonakyds.com/plugins/woo-nalda-sync',
            );
        }

        return $transient;
    }

    /**
     * Plugin information popup.
     *
     * @param false|object|array $result The result object or array.
     * @param string             $action The type of information being requested.
     * @param object             $args   Plugin API arguments.
     * @return false|object Plugin information or false.
     */
    public function plugin_info( $result, $action, $args ) {
        // Check if this is for our plugin.
        if ( 'plugin_information' !== $action ) {
            return $result;
        }

        if ( $this->plugin_slug !== $args->slug ) {
            return $result;
        }

        // Get GitHub release info.
        $release = $this->get_github_release();

        if ( ! $release ) {
            return $result;
        }

        $plugin_info = array(
            'name'              => 'WooCommerce Nalda Sync',
            'slug'              => $this->plugin_slug,
            'version'           => $release->version,
            'author'            => '<a href="https://jonakyds.com">Jonakyds</a>',
            'author_profile'    => 'https://jonakyds.com',
            'homepage'          => 'https://jonakyds.com/plugins/woo-nalda-sync',
            'short_description' => 'Sync your WooCommerce store with Nalda for seamless inventory and order management.',
            'sections'          => array(
                'description'  => 'Sync your WooCommerce store with Nalda for seamless inventory and order management.',
                'changelog'    => $this->parse_changelog( $release->body ),
            ),
            'download_link'     => $release->download_url,
            'last_updated'      => $release->published_at,
            'tested'            => get_bloginfo( 'version' ),
            'requires'          => '5.8',
            'requires_php'      => '7.4',
        );

        return (object) $plugin_info;
    }

    /**
     * Parse changelog from GitHub release body.
     *
     * @param string $body Release body content.
     * @return string Formatted changelog HTML.
     */
    private function parse_changelog( $body ) {
        if ( empty( $body ) ) {
            return '<p>No changelog available.</p>';
        }

        // Convert Markdown to basic HTML.
        $changelog = esc_html( $body );
        
        // Convert headers.
        $changelog = preg_replace( '/^### (.+)$/m', '<h4>$1</h4>', $changelog );
        $changelog = preg_replace( '/^## (.+)$/m', '<h3>$1</h3>', $changelog );
        $changelog = preg_replace( '/^# (.+)$/m', '<h2>$1</h2>', $changelog );
        
        // Convert lists.
        $changelog = preg_replace( '/^- (.+)$/m', '<li>$1</li>', $changelog );
        $changelog = preg_replace( '/^\\* (.+)$/m', '<li>$1</li>', $changelog );
        
        // Wrap consecutive list items.
        $changelog = preg_replace( '/(<li>.*<\\/li>)+/s', '<ul>$0</ul>', $changelog );
        
        // Convert bold.
        $changelog = preg_replace( '/\\*\\*(.+?)\\*\\*/', '<strong>$1</strong>', $changelog );
        
        // Convert line breaks.
        $changelog = nl2br( $changelog );

        return $changelog;
    }

    /**
     * After plugin install - handle directory naming.
     *
     * @param bool  $response   Install response.
     * @param array $hook_extra Extra hook data.
     * @param array $result     Install result data.
     * @return array Result data.
     */
    public function after_install( $response, $hook_extra, $result ) {
        global $wp_filesystem;

        // Check if this is our plugin.
        if ( ! isset( $hook_extra['plugin'] ) || $hook_extra['plugin'] !== $this->plugin_basename ) {
            return $result;
        }

        // Move to correct directory if needed.
        $plugin_dir = WP_PLUGIN_DIR . '/' . dirname( $this->plugin_basename );
        
        // If the result destination is different, move it.
        if ( $result['destination'] !== $plugin_dir ) {
            $wp_filesystem->move( $result['destination'], $plugin_dir );
            $result['destination'] = $plugin_dir;
        }

        // Reactivate plugin.
        activate_plugin( $this->plugin_basename );

        return $result;
    }

    /**
     * Clear update cache.
     */
    public function clear_update_cache() {
        delete_transient( 'woo_nalda_sync_github_release' );
        delete_site_transient( 'update_plugins' );
    }

    /**
     * Force check for updates.
     *
     * @return array Update check result.
     */
    public function force_check_update() {
        // Clear cache.
        $this->clear_update_cache();

        // Get fresh release info.
        $release = $this->get_github_release( true );

        if ( ! $release ) {
            return array(
                'success'         => false,
                'message'         => __( 'Unable to check for updates. Please try again later.', 'woo-nalda-sync' ),
                'current_version' => $this->current_version,
            );
        }

        $update_available = version_compare( $release->version, $this->current_version, '>' );

        return array(
            'success'          => true,
            'update_available' => $update_available,
            'current_version'  => $this->current_version,
            'latest_version'   => $release->version,
            'release_name'     => $release->name,
            'release_notes'    => $release->body,
            'download_url'     => $release->download_url,
            'release_url'      => $release->html_url,
            'published_at'     => $release->published_at,
            'message'          => $update_available
                ? sprintf(
                    /* translators: %s: New version number */
                    __( 'A new version (%s) is available!', 'woo-nalda-sync' ),
                    $release->version
                )
                : __( 'You are running the latest version.', 'woo-nalda-sync' ),
        );
    }

    /**
     * Get update info for display.
     *
     * @return array|false Update info or false if no update.
     */
    public function get_update_info() {
        $release = $this->get_github_release();

        if ( ! $release || ! version_compare( $release->version, $this->current_version, '>' ) ) {
            return false;
        }

        return array(
            'current_version' => $this->current_version,
            'new_version'     => $release->version,
            'release_name'    => $release->name,
            'release_notes'   => $release->body,
            'download_url'    => $release->download_url,
            'release_url'     => $release->html_url,
            'published_at'    => $release->published_at,
        );
    }
}
