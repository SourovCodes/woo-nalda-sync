<?php
/**
 * Sync Logger Class
 *
 * Handles logging of sync runs for both product exports and order imports.
 *
 * @package Woo_Nalda_Sync
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Sync Logger class.
 */
class Woo_Nalda_Sync_Logger {

    /**
     * Option name for storing logs.
     */
    const LOG_OPTION = 'woo_nalda_sync_logs';

    /**
     * Maximum number of log entries to keep.
     */
    const MAX_LOG_ENTRIES = 100;

    /**
     * Sync type constants.
     */
    const TYPE_PRODUCT_EXPORT = 'product_export';
    const TYPE_ORDER_IMPORT   = 'order_import';

    /**
     * Trigger type constants.
     */
    const TRIGGER_MANUAL    = 'manual';
    const TRIGGER_AUTOMATIC = 'automatic';

    /**
     * Status constants.
     */
    const STATUS_SUCCESS = 'success';
    const STATUS_ERROR   = 'error';
    const STATUS_WARNING = 'warning';

    /**
     * Single instance of the class.
     *
     * @var Woo_Nalda_Sync_Logger
     */
    private static $instance = null;

    /**
     * Get single instance of the class.
     *
     * @return Woo_Nalda_Sync_Logger
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
        // Nothing to do here.
    }

    /**
     * Log a sync run.
     *
     * @param string $type    Sync type (product_export or order_import).
     * @param string $trigger Trigger type (manual or automatic).
     * @param string $status  Status (success, error, warning).
     * @param string $summary Short summary of the run.
     * @param array  $details Optional. Additional details.
     * @return bool True on success, false on failure.
     */
    public function log( $type, $trigger, $status, $summary, $details = array() ) {
        $logs = $this->get_logs();

        $entry = array(
            'id'        => uniqid( 'log_' ),
            'timestamp' => time(),
            'type'      => $type,
            'trigger'   => $trigger,
            'status'    => $status,
            'summary'   => $summary,
            'details'   => $details,
        );

        // Add to beginning of array (newest first).
        array_unshift( $logs, $entry );

        // Trim to max entries.
        if ( count( $logs ) > self::MAX_LOG_ENTRIES ) {
            $logs = array_slice( $logs, 0, self::MAX_LOG_ENTRIES );
        }

        return update_option( self::LOG_OPTION, $logs, false );
    }

    /**
     * Log a product export run.
     *
     * @param string $trigger Trigger type (manual or automatic).
     * @param string $status  Status (success, error, warning).
     * @param string $summary Short summary of the run.
     * @param array  $details Optional. Additional details.
     * @return bool True on success, false on failure.
     */
    public function log_product_export( $trigger, $status, $summary, $details = array() ) {
        return $this->log( self::TYPE_PRODUCT_EXPORT, $trigger, $status, $summary, $details );
    }

    /**
     * Log an order import run.
     *
     * @param string $trigger Trigger type (manual or automatic).
     * @param string $status  Status (success, error, warning).
     * @param string $summary Short summary of the run.
     * @param array  $details Optional. Additional details.
     * @return bool True on success, false on failure.
     */
    public function log_order_import( $trigger, $status, $summary, $details = array() ) {
        return $this->log( self::TYPE_ORDER_IMPORT, $trigger, $status, $summary, $details );
    }

    /**
     * Get all logs.
     *
     * @return array Array of log entries.
     */
    public function get_logs() {
        return get_option( self::LOG_OPTION, array() );
    }

    /**
     * Get logs filtered by type.
     *
     * @param string $type Sync type to filter by.
     * @return array Filtered log entries.
     */
    public function get_logs_by_type( $type ) {
        $logs = $this->get_logs();
        return array_filter( $logs, function( $log ) use ( $type ) {
            return isset( $log['type'] ) && $log['type'] === $type;
        } );
    }

    /**
     * Get recent logs.
     *
     * @param int $count Number of logs to return.
     * @return array Recent log entries.
     */
    public function get_recent_logs( $count = 10 ) {
        $logs = $this->get_logs();
        return array_slice( $logs, 0, $count );
    }

    /**
     * Clear all logs.
     *
     * @return bool True on success, false on failure.
     */
    public function clear_logs() {
        return delete_option( self::LOG_OPTION );
    }

    /**
     * Get human-readable type label.
     *
     * @param string $type Sync type.
     * @return string Human-readable label.
     */
    public static function get_type_label( $type ) {
        $labels = array(
            self::TYPE_PRODUCT_EXPORT => __( 'Product Export', 'woo-nalda-sync' ),
            self::TYPE_ORDER_IMPORT   => __( 'Order Import', 'woo-nalda-sync' ),
        );
        return isset( $labels[ $type ] ) ? $labels[ $type ] : $type;
    }

    /**
     * Get human-readable trigger label.
     *
     * @param string $trigger Trigger type.
     * @return string Human-readable label.
     */
    public static function get_trigger_label( $trigger ) {
        $labels = array(
            self::TRIGGER_MANUAL    => __( 'Manual', 'woo-nalda-sync' ),
            self::TRIGGER_AUTOMATIC => __( 'Automatic', 'woo-nalda-sync' ),
        );
        return isset( $labels[ $trigger ] ) ? $labels[ $trigger ] : $trigger;
    }

    /**
     * Get human-readable status label.
     *
     * @param string $status Status.
     * @return string Human-readable label.
     */
    public static function get_status_label( $status ) {
        $labels = array(
            self::STATUS_SUCCESS => __( 'Success', 'woo-nalda-sync' ),
            self::STATUS_ERROR   => __( 'Error', 'woo-nalda-sync' ),
            self::STATUS_WARNING => __( 'Warning', 'woo-nalda-sync' ),
        );
        return isset( $labels[ $status ] ) ? $labels[ $status ] : $status;
    }
}

/**
 * Returns the main instance of the Sync Logger.
 *
 * @return Woo_Nalda_Sync_Logger
 */
function woo_nalda_sync_logger() {
    return Woo_Nalda_Sync_Logger::instance();
}
