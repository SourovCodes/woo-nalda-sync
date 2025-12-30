<?php
/**
 * License Manager Class
 *
 * Handles all license-related operations including activation, deactivation,
 * validation, and status checks.
 *
 * @package Woo_Nalda_Sync
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * WNS_License_Manager class
 */
class WNS_License_Manager {

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
     * Constructor
     */
    public function __construct() {
        $this->api_url = WOO_NALDA_SYNC_API_URL;
        $this->product_slug = WOO_NALDA_SYNC_PRODUCT_SLUG;

        // Schedule daily license check
        if (!wp_next_scheduled('wns_daily_license_check')) {
            wp_schedule_event(time(), 'daily', 'wns_daily_license_check');
        }

        add_action('wns_daily_license_check', array($this, 'daily_license_check'));
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

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $data = json_decode($response_body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('json_error', __('Invalid response from license server.', 'woo-nalda-sync'));
        }

        // Add response code to data for error handling
        $data['_response_code'] = $response_code;

        return $data;
    }

    /**
     * Activate license
     *
     * @param string $license_key License key to activate
     * @return array
     */
    public function activate($license_key) {
        $domain = Woo_Nalda_Sync::get_site_domain();

        $response = $this->api_request('license/activate', array(
            'license_key'  => $license_key,
            'domain'       => $domain,
            'product_slug' => $this->product_slug,
        ));

        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => $response->get_error_message(),
            );
        }

        if (!empty($response['success'])) {
            // Save license data
            update_option('wns_license_key', $license_key);
            update_option('wns_license_status', 'active');
            update_option('wns_license_data', $response['license'] ?? array());
            
            if (!empty($response['local_key'])) {
                update_option('wns_local_key', $response['local_key']);
            }

            // Clear transients
            delete_transient('wns_license_valid');
            delete_transient('wns_update_check');

            return array(
                'success' => true,
                'message' => $response['message'] ?? __('License activated successfully.', 'woo-nalda-sync'),
                'license' => $response['license'] ?? array(),
            );
        }

        $error_message = $response['message'] ?? __('Failed to activate license.', 'woo-nalda-sync');

        return array(
            'success' => false,
            'message' => $this->translate_api_error($error_message),
        );
    }

    /**
     * Deactivate license
     *
     * @param string $license_key License key to deactivate (optional, uses saved key if not provided)
     * @return array
     */
    public function deactivate($license_key = '') {
        if (empty($license_key)) {
            $license_key = get_option('wns_license_key', '');
        }

        if (empty($license_key)) {
            return array(
                'success' => false,
                'message' => __('No license key provided.', 'woo-nalda-sync'),
            );
        }

        $domain = Woo_Nalda_Sync::get_site_domain();

        $response = $this->api_request('license/deactivate', array(
            'license_key'  => $license_key,
            'domain'       => $domain,
            'product_slug' => $this->product_slug,
        ));

        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => $response->get_error_message(),
            );
        }

        // Clear license data regardless of response
        $this->clear_license_data();

        if (!empty($response['success'])) {
            return array(
                'success' => true,
                'message' => $response['message'] ?? __('License deactivated successfully.', 'woo-nalda-sync'),
            );
        }

        $error_message = $response['message'] ?? __('Failed to deactivate license.', 'woo-nalda-sync');

        return array(
            'success' => false,
            'message' => $this->translate_api_error($error_message),
        );
    }

    /**
     * Validate license
     *
     * @param bool $force Force validation, bypass cache
     * @return array
     */
    public function validate($force = false) {
        // Check transient cache first
        if (!$force) {
            $cached = get_transient('wns_license_valid');
            if ($cached !== false) {
                return $cached;
            }
        }

        $license_key = get_option('wns_license_key', '');

        if (empty($license_key)) {
            $result = array(
                'valid'   => false,
                'message' => __('No license key found.', 'woo-nalda-sync'),
            );
            set_transient('wns_license_valid', $result, HOUR_IN_SECONDS);
            return $result;
        }

        $domain = Woo_Nalda_Sync::get_site_domain();

        $response = $this->api_request('license/validate', array(
            'license_key'  => $license_key,
            'domain'       => $domain,
            'product_slug' => $this->product_slug,
        ));

        if (is_wp_error($response)) {
            // On connection error, use local key validation if available
            $local_valid = $this->validate_local_key();
            if ($local_valid) {
                return array(
                    'valid'   => true,
                    'message' => __('License validated locally.', 'woo-nalda-sync'),
                    'local'   => true,
                );
            }

            return array(
                'valid'   => false,
                'message' => $response->get_error_message(),
            );
        }

        $is_valid = !empty($response['success']) && !empty($response['valid']);

        // Update stored license data
        if (!empty($response['license'])) {
            update_option('wns_license_data', $response['license']);
            update_option('wns_license_status', $response['license']['status'] ?? 'inactive');
        }

        $result = array(
            'valid'   => $is_valid,
            'message' => $response['message'] ?? '',
            'license' => $response['license'] ?? array(),
        );

        // Cache for 12 hours if valid, 1 hour if invalid
        $cache_time = $is_valid ? 12 * HOUR_IN_SECONDS : HOUR_IN_SECONDS;
        set_transient('wns_license_valid', $result, $cache_time);

        return $result;
    }

    /**
     * Get license status
     *
     * @return array
     */
    public function get_status() {
        $license_key = get_option('wns_license_key', '');

        if (empty($license_key)) {
            return array(
                'success' => false,
                'message' => __('No license key found.', 'woo-nalda-sync'),
            );
        }

        $response = $this->api_request('license/status', array(
            'license_key'  => $license_key,
            'product_slug' => $this->product_slug,
        ));

        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => $response->get_error_message(),
            );
        }

        if (!empty($response['success'])) {
            // Update stored license data
            if (!empty($response['license'])) {
                update_option('wns_license_data', $response['license']);
                update_option('wns_license_status', $response['license']['status'] ?? 'inactive');
            }

            return array(
                'success' => true,
                'license' => $response['license'] ?? array(),
            );
        }

        $error_message = $response['message'] ?? __('Failed to get license status.', 'woo-nalda-sync');

        return array(
            'success' => false,
            'message' => $this->translate_api_error($error_message),
        );
    }

    /**
     * Validate local key
     *
     * @return bool
     */
    private function validate_local_key() {
        $local_key = get_option('wns_local_key', '');

        if (empty($local_key)) {
            return false;
        }

        // Decode JWT token (basic validation)
        $parts = explode('.', $local_key);
        if (count($parts) !== 3) {
            return false;
        }

        try {
            $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);
            
            if (!$payload) {
                return false;
            }

            // Check expiration
            if (!empty($payload['exp']) && $payload['exp'] < time()) {
                return false;
            }

            // Check domain
            $current_domain = Woo_Nalda_Sync::get_site_domain();
            if (!empty($payload['domain']) && $payload['domain'] !== $current_domain) {
                return false;
            }

            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Check if license is active
     *
     * @return bool
     */
    public function is_active() {
        $status = get_option('wns_license_status', '');
        return $status === 'active';
    }

    /**
     * Get stored license data
     *
     * @return array
     */
    public function get_license_data() {
        return get_option('wns_license_data', array());
    }

    /**
     * Get stored license key
     *
     * @return string
     */
    public function get_license_key() {
        return get_option('wns_license_key', '');
    }

    /**
     * Clear license data
     */
    public function clear_license_data() {
        update_option('wns_license_key', '');
        update_option('wns_license_status', '');
        update_option('wns_license_data', array());
        update_option('wns_local_key', '');
        delete_transient('wns_license_valid');
        delete_transient('wns_update_check');
    }

    /**
     * Daily license check
     */
    public function daily_license_check() {
        if (!$this->is_active()) {
            return;
        }

        // Force validation
        $this->validate(true);
    }

    /**
     * Translate API error message to user-friendly message
     *
     * @param string $api_message The error message from the API
     * @return string Translated error message
     */
    private function translate_api_error($api_message) {
        $error_map = array(
            // License/Product validation errors
            'Product not found.'                                      => __('Invalid product configuration. Please contact support.', 'woo-nalda-sync'),
            'Invalid license key.'                                    => __('The license key you entered is invalid. Please check and try again.', 'woo-nalda-sync'),

            // License status errors
            'License is inactive.'                                    => __('Your license is inactive. Please activate it to continue.', 'woo-nalda-sync'),
            'License has been revoked.'                               => __('This license has been revoked. Please contact support.', 'woo-nalda-sync'),
            'License has expired. Please renew your license.'         => __('Your license has expired. Please renew to continue receiving updates.', 'woo-nalda-sync'),

            // Domain errors
            'License is not activated on this domain.'                => __('This license is not activated on this domain.', 'woo-nalda-sync'),
            'Maximum domain changes reached. Please contact support.' => __('Maximum domain changes reached. Please contact support to reset.', 'woo-nalda-sync'),

            // Legacy error messages (for backwards compatibility)
            'This license has been revoked.'                          => __('This license has been revoked. Please contact support.', 'woo-nalda-sync'),
            'This license has expired.'                               => __('Your license has expired. Please renew to continue receiving updates.', 'woo-nalda-sync'),
            'License is not valid.'                                   => __('Your license is not valid. Please check your license status.', 'woo-nalda-sync'),
            'License is not valid. Please renew your license.'        => __('Your license has expired. Please renew to continue receiving updates.', 'woo-nalda-sync'),
        );

        return $error_map[$api_message] ?? $api_message;
    }

    /**
     * Mask license key for display
     *
     * @param string $license_key License key to mask
     * @return string
     */
    public function mask_license_key($license_key) {
        if (empty($license_key)) {
            return '';
        }

        $parts = explode('-', $license_key);
        $masked_parts = array();

        foreach ($parts as $index => $part) {
            if ($index === 0 || $index === count($parts) - 1) {
                $masked_parts[] = $part;
            } else {
                $masked_parts[] = str_repeat('*', strlen($part));
            }
        }

        return implode('-', $masked_parts);
    }
}
