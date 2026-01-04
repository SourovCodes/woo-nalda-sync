<?php
/**
 * License Manager Class
 *
 * Handles license activation, validation, and management.
 *
 * @package Woo_Nalda_Sync
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * License Manager class.
 */
class Woo_Nalda_Sync_License_Manager {

    /**
     * License option key.
     *
     * @var string
     */
    const LICENSE_KEY_OPTION = 'woo_nalda_sync_license_key';

    /**
     * License data option key.
     *
     * @var string
     */
    const LICENSE_DATA_OPTION = 'woo_nalda_sync_license_data';

    /**
     * Last validation option key.
     *
     * @var string
     */
    const LAST_VALIDATION_OPTION = 'woo_nalda_sync_last_validation';

    /**
     * Validation interval in seconds (24 hours).
     *
     * @var int
     */
    const VALIDATION_INTERVAL = 86400;

    /**
     * API base URL.
     *
     * @var string
     */
    private $api_url;

    /**
     * Product slug.
     *
     * @var string
     */
    private $product_slug;

    /**
     * Cached license data.
     *
     * @var array|null
     */
    private $license_data = null;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->api_url      = WOO_NALDA_SYNC_LICENSE_API_URL;
        $this->product_slug = WOO_NALDA_SYNC_PRODUCT_SLUG;

        // Schedule daily validation.
        add_action( 'woo_nalda_sync_daily_license_check', array( $this, 'validate_license' ) );

        // Schedule if not scheduled.
        if ( ! wp_next_scheduled( 'woo_nalda_sync_daily_license_check' ) ) {
            wp_schedule_event( time(), 'daily', 'woo_nalda_sync_daily_license_check' );
        }
    }

    /**
     * Get current domain.
     *
     * @return string
     */
    public function get_domain() {
        $domain = parse_url( home_url(), PHP_URL_HOST );
        return $domain ? $domain : '';
    }

    /**
     * Get stored license key.
     *
     * @return string
     */
    public function get_license_key() {
        return get_option( self::LICENSE_KEY_OPTION, '' );
    }

    /**
     * Get stored license data.
     *
     * @return array
     */
    public function get_license_data() {
        if ( is_null( $this->license_data ) ) {
            $this->license_data = get_option( self::LICENSE_DATA_OPTION, array() );
        }
        return $this->license_data;
    }

    /**
     * Check if license is valid.
     *
     * @return bool
     */
    public function is_valid() {
        $license_data = $this->get_license_data();

        if ( empty( $license_data ) ) {
            return false;
        }

        // Check stored valid flag (set by validate_license).
        if ( isset( $license_data['valid'] ) && $license_data['valid'] === false ) {
            return false;
        }

        // Check status.
        if ( ! isset( $license_data['status'] ) || ! in_array( $license_data['status'], array( 'active' ), true ) ) {
            return false;
        }

        // Check expiration.
        if ( isset( $license_data['expires_at'] ) && ! empty( $license_data['expires_at'] ) ) {
            $expires_at = strtotime( $license_data['expires_at'] );
            if ( $expires_at && $expires_at < time() ) {
                return false;
            }
        }

        // Check if validation is needed (older than 24 hours).
        $last_validation = get_option( self::LAST_VALIDATION_OPTION, 0 );
        if ( ( time() - $last_validation ) > self::VALIDATION_INTERVAL ) {
            // Trigger background validation.
            $this->validate_license();
        }

        return true;
    }

    /**
     * Get license status.
     *
     * @return string
     */
    public function get_status() {
        $license_data = $this->get_license_data();
        return isset( $license_data['status'] ) ? $license_data['status'] : 'inactive';
    }

    /**
     * Get expiration date.
     *
     * @return string|null
     */
    public function get_expiration_date() {
        $license_data = $this->get_license_data();
        return isset( $license_data['expires_at'] ) ? $license_data['expires_at'] : null;
    }

    /**
     * Get days remaining.
     *
     * @return int|null
     */
    public function get_days_remaining() {
        $license_data = $this->get_license_data();
        return isset( $license_data['days_remaining'] ) ? (int) $license_data['days_remaining'] : null;
    }

    /**
     * Activate license.
     *
     * @param string $license_key License key.
     * @return array Response data.
     */
    public function activate( $license_key ) {
        $response = $this->api_request( '/activate', array(
            'license_key'  => sanitize_text_field( $license_key ),
            'product_slug' => $this->product_slug,
            'domain'       => $this->get_domain(),
        ) );

        if ( $this->is_success_response( $response ) ) {
            // Store license key.
            update_option( self::LICENSE_KEY_OPTION, sanitize_text_field( $license_key ) );

            // Store license data from v2 API response.
            $response_data = isset( $response['data'] ) ? $response['data'] : array();
            $license_data = array(
                'valid'                    => true,
                'status'                   => 'active',
                'domain'                   => isset( $response_data['domain'] ) ? $response_data['domain'] : $this->get_domain(),
                'activated_at'             => isset( $response_data['activated_at'] ) ? $response_data['activated_at'] : null,
                'expires_at'               => isset( $response_data['expires_at'] ) ? $response_data['expires_at'] : null,
                'days_remaining'           => isset( $response_data['days_remaining'] ) ? $response_data['days_remaining'] : null,
                'domain_changes_remaining' => isset( $response_data['domain_changes_remaining'] ) ? $response_data['domain_changes_remaining'] : null,
                'product'                  => isset( $response_data['product'] ) ? $response_data['product'] : null,
            );

            update_option( self::LICENSE_DATA_OPTION, $license_data );
            update_option( self::LAST_VALIDATION_OPTION, time() );

            // Clear cache.
            $this->license_data = $license_data;

            return array(
                'success' => true,
                'message' => isset( $response['message'] ) ? $response['message'] : __( 'License activated successfully.', 'woo-nalda-sync' ),
                'data'    => $license_data,
            );
        }

        return array(
            'success' => false,
            'message' => isset( $response['message'] ) ? $response['message'] : __( 'Failed to activate license.', 'woo-nalda-sync' ),
        );
    }

    /**
     * Validate license.
     *
     * @return array Response data.
     */
    public function validate_license() {
        $license_key = $this->get_license_key();

        if ( empty( $license_key ) ) {
            return array(
                'success' => false,
                'message' => __( 'No license key found.', 'woo-nalda-sync' ),
            );
        }

        $response = $this->api_request( '/validate', array(
            'license_key'  => $license_key,
            'product_slug' => $this->product_slug,
            'domain'       => $this->get_domain(),
        ) );

        // Update validation timestamp regardless of result.
        update_option( self::LAST_VALIDATION_OPTION, time() );

        // Check for temporary errors first (network, rate limit, server errors).
        // In these cases, don't change the license status - use cached value.
        if ( $this->is_temporary_error( $response ) ) {
            return array(
                'success'        => false,
                'message'        => isset( $response['message'] ) ? $response['message'] : __( 'Temporary error. Please try again later.', 'woo-nalda-sync' ),
                'status_changed' => false,
            );
        }

        // Check API response success.
        if ( ! $this->is_success_response( $response ) ) {
            // API error (license not found, product mismatch, etc.).
            $license_data = $this->get_license_data();
            $license_data['status'] = 'invalid';
            $license_data['valid']  = false;
            update_option( self::LICENSE_DATA_OPTION, $license_data );
            $this->license_data = $license_data;

            return array(
                'success'        => false,
                'message'        => isset( $response['message'] ) ? $response['message'] : __( 'License validation failed.', 'woo-nalda-sync' ),
                'status_changed' => true,
            );
        }

        // API returned success - now check data.valid field.
        // IMPORTANT: Expired/revoked licenses return success:true but valid:false.
        $response_data = isset( $response['data'] ) ? $response['data'] : array();
        $is_valid      = isset( $response_data['valid'] ) && $response_data['valid'] === true;

        // Update license data.
        $license_data = $this->get_license_data();
        $license_data['valid']          = $is_valid;
        $license_data['status']         = isset( $response_data['status'] ) ? $response_data['status'] : ( $is_valid ? 'active' : 'invalid' );
        $license_data['expires_at']     = isset( $response_data['expires_at'] ) ? $response_data['expires_at'] : ( isset( $license_data['expires_at'] ) ? $license_data['expires_at'] : null );
        $license_data['days_remaining'] = isset( $response_data['days_remaining'] ) ? $response_data['days_remaining'] : ( isset( $license_data['days_remaining'] ) ? $license_data['days_remaining'] : null );

        update_option( self::LICENSE_DATA_OPTION, $license_data );
        $this->license_data = $license_data;

        if ( $is_valid ) {
            return array(
                'success' => true,
                'message' => isset( $response['message'] ) ? $response['message'] : __( 'License is valid.', 'woo-nalda-sync' ),
                'data'    => $license_data,
            );
        }

        // License is not valid (expired, revoked, domain mismatch, etc.).
        return array(
            'success'        => false,
            'message'        => isset( $response['message'] ) ? $response['message'] : __( 'License is not valid.', 'woo-nalda-sync' ),
            'status_changed' => true,
            'data'           => $license_data,
        );
    }

    /**
     * Deactivate license.
     *
     * @param string $reason Optional reason.
     * @return array Response data.
     */
    public function deactivate( $reason = '' ) {
        $license_key = $this->get_license_key();

        if ( empty( $license_key ) ) {
            return array(
                'success' => false,
                'message' => __( 'No license key found.', 'woo-nalda-sync' ),
            );
        }

        $request_data = array(
            'license_key'  => $license_key,
            'product_slug' => $this->product_slug,
            'domain'       => $this->get_domain(),
        );

        if ( ! empty( $reason ) ) {
            $request_data['reason'] = sanitize_text_field( $reason );
        }

        $response = $this->api_request( '/deactivate', $request_data );

        if ( $this->is_success_response( $response ) ) {
            // Clear license data.
            delete_option( self::LICENSE_KEY_OPTION );
            delete_option( self::LICENSE_DATA_OPTION );
            delete_option( self::LAST_VALIDATION_OPTION );
            $this->license_data = null;

            return array(
                'success' => true,
                'message' => isset( $response['message'] ) ? $response['message'] : __( 'License deactivated successfully.', 'woo-nalda-sync' ),
            );
        }

        return array(
            'success' => false,
            'message' => isset( $response['message'] ) ? $response['message'] : __( 'Failed to deactivate license.', 'woo-nalda-sync' ),
        );
    }

    /**
     * Get license status from API.
     *
     * @return array Response data.
     */
    public function get_status_from_api() {
        $license_key = $this->get_license_key();

        if ( empty( $license_key ) ) {
            return array(
                'success' => false,
                'message' => __( 'No license key found.', 'woo-nalda-sync' ),
            );
        }

        $response = $this->api_request( '/status', array(
            'license_key'  => $license_key,
            'product_slug' => $this->product_slug,
        ) );

        if ( $this->is_success_response( $response ) && isset( $response['data'] ) ) {
            // Update local license data with API response.
            $response_data = $response['data'];
            $license_data  = $this->get_license_data();

            // Update status from API.
            $license_data['status'] = isset( $response_data['status'] ) ? $response_data['status'] : $license_data['status'];

            // Update validity info.
            if ( isset( $response_data['validity'] ) ) {
                $license_data['expires_at']     = isset( $response_data['validity']['expires_at'] ) ? $response_data['validity']['expires_at'] : null;
                $license_data['days_remaining'] = isset( $response_data['validity']['days_remaining'] ) ? $response_data['validity']['days_remaining'] : null;
            }

            // Update domain changes info.
            if ( isset( $response_data['domain_changes'] ) ) {
                $license_data['domain_changes_remaining'] = isset( $response_data['domain_changes']['remaining'] ) ? $response_data['domain_changes']['remaining'] : null;
            }

            update_option( self::LICENSE_DATA_OPTION, $license_data );
            $this->license_data = $license_data;

            return array(
                'success' => true,
                'data'    => $response_data,
            );
        }

        return array(
            'success' => false,
            'message' => isset( $response['message'] ) ? $response['message'] : __( 'Failed to get license status.', 'woo-nalda-sync' ),
        );
    }

    /**
     * Make API request.
     *
     * @param string $endpoint API endpoint.
     * @param array  $data     Request data.
     * @return array Response data.
     */
    private function api_request( $endpoint, $data ) {
        $response = wp_remote_post( $this->api_url . $endpoint, array(
            'timeout'   => 30,
            'headers'   => array(
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json',
            ),
            'body'      => wp_json_encode( $data ),
            'sslverify' => true,
        ) );

        if ( is_wp_error( $response ) ) {
            return array(
                'error'   => true,
                'message' => $response->get_error_message(),
            );
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        $body        = wp_remote_retrieve_body( $response );
        $result      = json_decode( $body, true );

        if ( ! is_array( $result ) ) {
            return array(
                'error'   => true,
                'message' => __( 'Invalid API response.', 'woo-nalda-sync' ),
            );
        }

        $result['status_code'] = $status_code;

        // Extract error message from v2 API error response format.
        if ( isset( $result['error'] ) && is_array( $result['error'] ) && isset( $result['error']['message'] ) ) {
            $result['message'] = $result['error']['message'];
        }

        return $result;
    }

    /**
     * Check if response is successful.
     *
     * @param array $response API response.
     * @return bool
     */
    private function is_success_response( $response ) {
        // Check for network/connection errors (our internal error flag is boolean true).
        if ( isset( $response['error'] ) && $response['error'] === true ) {
            return false;
        }

        // v2 API uses 'success' field in response body.
        if ( isset( $response['success'] ) && $response['success'] === true ) {
            return true;
        }

        return false;
    }

    /**
     * Check if response is a temporary error (rate limit, network, server error).
     * These errors should not change the license status.
     *
     * @param array $response API response.
     * @return bool
     */
    private function is_temporary_error( $response ) {
        // Network errors or invalid responses (our internal error flag is boolean true).
        if ( isset( $response['error'] ) && $response['error'] === true ) {
            return true;
        }

        // Check for v2 API rate limit error code.
        if ( isset( $response['error'] ) && is_array( $response['error'] ) && isset( $response['error']['code'] ) ) {
            if ( $response['error']['code'] === 'RATE_LIMIT_EXCEEDED' ) {
                return true;
            }
        }

        // Check for temporary HTTP status codes.
        if ( isset( $response['status_code'] ) ) {
            $status_code = (int) $response['status_code'];

            // 429 = Too Many Requests (rate limit).
            // 500, 502, 503, 504 = Server errors.
            // 0 = Connection failed.
            $temporary_codes = array( 0, 429, 500, 502, 503, 504 );

            if ( in_array( $status_code, $temporary_codes, true ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Mask license key for display.
     *
     * @param string $license_key License key.
     * @return string Masked license key.
     */
    public function mask_license_key( $license_key = '' ) {
        if ( empty( $license_key ) ) {
            $license_key = $this->get_license_key();
        }

        if ( empty( $license_key ) ) {
            return '';
        }

        $length = strlen( $license_key );
        if ( $length <= 8 ) {
            return str_repeat( '*', $length );
        }

        return substr( $license_key, 0, 4 ) . str_repeat( '*', $length - 8 ) . substr( $license_key, -4 );
    }
}
