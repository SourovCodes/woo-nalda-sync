<?php
/**
 * Order Import Class
 *
 * Handles order import from Nalda Marketplace API to WooCommerce.
 *
 * @package Woo_Nalda_Sync
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Order Import class.
 */
class Woo_Nalda_Sync_Order_Import {

    /**
     * Nalda API base URL.
     *
     * @var string
     */
    const NALDA_API_URL = 'https://sellers-api.nalda.com';

    /**
     * License Manager instance.
     *
     * @var Woo_Nalda_Sync_License_Manager
     */
    private $license_manager;

    /**
     * Constructor.
     *
     * @param Woo_Nalda_Sync_License_Manager $license_manager License manager instance.
     */
    public function __construct( $license_manager ) {
        $this->license_manager = $license_manager;
        $this->init_hooks();
    }

    /**
     * Initialize hooks.
     */
    private function init_hooks() {
        // Schedule cron events.
        add_action( 'woo_nalda_sync_order_import', array( $this, 'run_scheduled_sync' ) );
        add_action( 'woo_nalda_sync_order_status_export', array( $this, 'run_scheduled_order_status_export' ) );
        
        // Disable customer-facing emails for Nalda orders.
        add_filter( 'woocommerce_email_recipient_customer_processing_order', array( $this, 'disable_customer_emails' ), 10, 2 );
        add_filter( 'woocommerce_email_recipient_customer_completed_order', array( $this, 'disable_customer_emails' ), 10, 2 );
        add_filter( 'woocommerce_email_recipient_customer_invoice', array( $this, 'disable_customer_emails' ), 10, 2 );
        add_filter( 'woocommerce_email_recipient_customer_note', array( $this, 'disable_customer_emails' ), 10, 2 );
        add_filter( 'woocommerce_email_recipient_customer_on_hold_order', array( $this, 'disable_customer_emails' ), 10, 2 );
        add_filter( 'woocommerce_email_recipient_customer_refunded_order', array( $this, 'disable_customer_emails' ), 10, 2 );
        
        // Hide Nalda orders from My Account page.
        add_filter( 'woocommerce_my_account_my_orders_query', array( $this, 'hide_nalda_orders_from_my_account' ) );
        
        // Prevent viewing Nalda order details on frontend.
        add_action( 'woocommerce_view_order', array( $this, 'restrict_nalda_order_view' ), 1 );
    }

    /**
     * Run scheduled sync.
     */
    public function run_scheduled_sync() {
        $settings = woo_nalda_sync()->get_setting();

        // Check if sync is enabled.
        if ( isset( $settings['order_import_enabled'] ) && 'yes' !== $settings['order_import_enabled'] ) {
            $this->log( 'Order import is disabled. Skipping scheduled sync.' );
            return;
        }

        // Check license.
        if ( ! $this->license_manager->is_valid() ) {
            $this->log( 'License is not valid. Skipping scheduled sync.' );
            woo_nalda_sync_logger()->log_order_import(
                Woo_Nalda_Sync_Logger::TRIGGER_AUTOMATIC,
                Woo_Nalda_Sync_Logger::STATUS_ERROR,
                __( 'License is not valid', 'woo-nalda-sync' )
            );
            return;
        }

        // Get the configured import range.
        $range = isset( $settings['order_import_range'] ) ? $settings['order_import_range'] : 'today';

        $this->run_sync( $range, Woo_Nalda_Sync_Logger::TRIGGER_AUTOMATIC );

        // Ensure the cron event is still scheduled after sync completes.
        // This prevents the schedule from being lost if there was an issue.
        $this->ensure_cron_scheduled();
    }

    /**
     * Run scheduled order status export.
     */
    public function run_scheduled_order_status_export() {
        $settings = woo_nalda_sync()->get_setting();

        // Check if order status export is enabled.
        if ( isset( $settings['order_status_export_enabled'] ) && 'yes' !== $settings['order_status_export_enabled'] ) {
            $this->log( 'Order status export is disabled. Skipping scheduled export.' );
            return;
        }

        // Check license.
        if ( ! $this->license_manager->is_valid() ) {
            $this->log( 'License is not valid. Skipping scheduled order status export.' );
            woo_nalda_sync_logger()->log_order_status_export(
                Woo_Nalda_Sync_Logger::TRIGGER_AUTOMATIC,
                Woo_Nalda_Sync_Logger::STATUS_ERROR,
                __( 'License is not valid', 'woo-nalda-sync' )
            );
            return;
        }

        $this->run_order_status_export( Woo_Nalda_Sync_Logger::TRIGGER_AUTOMATIC );

        // Ensure the cron event is still scheduled after export completes.
        $this->ensure_order_status_export_cron_scheduled();
    }

    /**
     * Ensure order status export cron event is scheduled.
     * This is a safety measure to prevent lost schedules.
     */
    private function ensure_order_status_export_cron_scheduled() {
        $settings = woo_nalda_sync()->get_setting();

        // Only reschedule if order status export is enabled.
        if ( empty( $settings['order_status_export_enabled'] ) || 'yes' !== $settings['order_status_export_enabled'] ) {
            return;
        }

        // Check if cron is already scheduled.
        $next_scheduled = wp_next_scheduled( 'woo_nalda_sync_order_status_export' );

        if ( ! $next_scheduled ) {
            // Cron is not scheduled, reschedule it.
            $recurrence = ! empty( $settings['order_status_export_schedule'] ) ? $settings['order_status_export_schedule'] : 'hourly';
            $timestamp  = time() + ( 2 * MINUTE_IN_SECONDS );
            wp_schedule_event( $timestamp, $recurrence, 'woo_nalda_sync_order_status_export' );
            $this->log( 'Order status export cron was not scheduled. Rescheduled for ' . gmdate( 'Y-m-d H:i:s', $timestamp ) );
        }
    }

    /**
     * Ensure order import cron event is scheduled.
     * This is a safety measure to prevent lost schedules.
     */
    private function ensure_cron_scheduled() {
        $settings = woo_nalda_sync()->get_setting();

        // Only reschedule if order import is enabled.
        if ( empty( $settings['order_import_enabled'] ) || 'yes' !== $settings['order_import_enabled'] ) {
            return;
        }

        // Check if cron is already scheduled.
        $next_scheduled = wp_next_scheduled( 'woo_nalda_sync_order_import' );

        if ( ! $next_scheduled ) {
            // Cron is not scheduled, reschedule it.
            $recurrence = ! empty( $settings['order_import_schedule'] ) ? $settings['order_import_schedule'] : 'hourly';
            $timestamp  = time() + ( 2 * MINUTE_IN_SECONDS );
            wp_schedule_event( $timestamp, $recurrence, 'woo_nalda_sync_order_import' );
            $this->log( 'Order import cron was not scheduled. Rescheduled for ' . gmdate( 'Y-m-d H:i:s', $timestamp ) );
        }
    }

    /**
     * Run order import.
     *
     * @param string $range   Date range for orders (default: 'today').
     * @param string $trigger Trigger type (manual or automatic). Default: manual.
     * @return array Result with success status and message.
     */
    public function run_sync( $range = 'today', $trigger = 'manual' ) {
        $start_time = microtime( true );
        $this->log( sprintf( 'Starting order import with range: %s', $range ) );

        // Check license.
        if ( ! $this->license_manager->is_valid() ) {
            $this->log( 'License validation failed.' );
            woo_nalda_sync_logger()->log_order_import(
                $trigger,
                Woo_Nalda_Sync_Logger::STATUS_ERROR,
                __( 'License is not valid', 'woo-nalda-sync' )
            );
            return array(
                'success' => false,
                'message' => __( 'License is not valid.', 'woo-nalda-sync' ),
            );
        }

        $settings = woo_nalda_sync()->get_setting();

        // Validate Nalda API settings.
        if ( empty( $settings['nalda_api_key'] ) ) {
            $this->log( 'Nalda API key is not configured.' );
            woo_nalda_sync_logger()->log_order_import(
                $trigger,
                Woo_Nalda_Sync_Logger::STATUS_ERROR,
                __( 'Nalda API key is not configured', 'woo-nalda-sync' )
            );
            return array(
                'success' => false,
                'message' => __( 'Nalda API key is not configured.', 'woo-nalda-sync' ),
            );
        }

        $this->log( sprintf( 'Fetching orders from Nalda API with range: %s', $range ) );

        // Get orders from Nalda API.
        $orders_result = $this->fetch_orders( $range );

        if ( ! $orders_result['success'] ) {
            $this->log( sprintf( 'Failed to fetch orders: %s', $orders_result['message'] ) );
            woo_nalda_sync_logger()->log_order_import(
                $trigger,
                Woo_Nalda_Sync_Logger::STATUS_ERROR,
                $orders_result['message']
            );
            return $orders_result;
        }

        $orders = $orders_result['orders'];

        if ( empty( $orders ) ) {
            $this->log( 'No new orders found.' );
            woo_nalda_sync_logger()->log_order_import(
                $trigger,
                Woo_Nalda_Sync_Logger::STATUS_SUCCESS,
                __( 'No new orders found', 'woo-nalda-sync' ),
                array( 'range' => $range )
            );
            return array(
                'success'      => true,
                'message'      => __( 'No new orders found.', 'woo-nalda-sync' ),
                'orders_count' => 0,
            );
        }

        // Get order items for each order.
        $orders_created = 0;
        $orders_updated = 0;
        $orders_skipped = 0;

        foreach ( $orders as $order ) {
            // Check if order already exists.
            $existing_order = $this->get_existing_order( $order['orderId'] );

            if ( $existing_order ) {
                // Update existing order if needed.
                $update_result = $this->update_order( $existing_order, $order );
                if ( $update_result ) {
                    $orders_updated++;
                } else {
                    $orders_skipped++;
                }
            } else {
                // Create new order.
                $items_result = $this->fetch_order_items( $order['orderId'] );
                
                if ( $items_result['success'] && ! empty( $items_result['items'] ) ) {
                    $create_result = $this->create_order( $order, $items_result['items'] );
                    if ( $create_result ) {
                        $orders_created++;
                    } else {
                        $orders_skipped++;
                    }
                } else {
                    $orders_skipped++;
                    $this->log( sprintf( 'Failed to fetch items for order %d', $order['orderId'] ) );
                }
            }
        }

        $duration = round( microtime( true ) - $start_time, 2 );

        // Update sync stats.
        $this->update_sync_stats( $orders_created + $orders_updated );

        $this->log( sprintf(
            'Order import completed in %s seconds. Created: %d, Updated: %d, Skipped: %d',
            $duration,
            $orders_created,
            $orders_updated,
            $orders_skipped
        ) );

        // Log success.
        $summary = sprintf(
            __( 'Imported orders in %ss. Created: %d, Updated: %d, Skipped: %d', 'woo-nalda-sync' ),
            $duration,
            $orders_created,
            $orders_updated,
            $orders_skipped
        );
        woo_nalda_sync_logger()->log_order_import(
            $trigger,
            Woo_Nalda_Sync_Logger::STATUS_SUCCESS,
            $summary,
            array(
                'range'          => $range,
                'orders_created' => $orders_created,
                'orders_updated' => $orders_updated,
                'orders_skipped' => $orders_skipped,
                'duration'       => $duration,
            )
        );

        return array(
            'success'        => true,
            'message'        => sprintf(
                __( 'Sync completed in %s seconds. Created: %d, Updated: %d, Skipped: %d', 'woo-nalda-sync' ),
                $duration,
                $orders_created,
                $orders_updated,
                $orders_skipped
            ),
            'orders_created' => $orders_created,
            'orders_updated' => $orders_updated,
            'orders_skipped' => $orders_skipped,
            'duration'       => $duration,
        );
    }

    /**
     * Fetch orders from Nalda API.
     *
     * @param string      $range Date range.
     * @param string|null $from  Custom from date (YYYY-MM-DD).
     * @param string|null $to    Custom to date (YYYY-MM-DD).
     * @return array Result with orders array.
     */
    private function fetch_orders( $range = 'today', $from = null, $to = null ) {
        $settings = woo_nalda_sync()->get_setting();
        $api_url  = isset( $settings['nalda_api_url'] ) && ! empty( $settings['nalda_api_url'] )
            ? $settings['nalda_api_url']
            : self::NALDA_API_URL;

        $body = array(
            'range' => $range,
        );

        if ( $range === 'custom' && $from && $to ) {
            $body['from'] = $from;
            $body['to']   = $to;
        }

        $response = wp_remote_post( rtrim( $api_url, '/' ) . '/orders', array(
            'timeout' => 60,
            'headers' => array(
                'X-API-KEY'    => $settings['nalda_api_key'],
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json',
            ),
            'body'    => wp_json_encode( $body ),
        ) );

        if ( is_wp_error( $response ) ) {
            $this->log( 'Failed to fetch orders: ' . $response->get_error_message() );
            return array(
                'success' => false,
                'message' => sprintf( __( 'Failed to fetch orders: %s', 'woo-nalda-sync' ), $response->get_error_message() ),
            );
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        $body        = json_decode( wp_remote_retrieve_body( $response ), true );

        // Log the API response for debugging.
        $this->log( sprintf( 'Nalda API response - Status: %d, Body: %s', $status_code, wp_json_encode( $body ) ) );

        if ( $status_code !== 200 || ! isset( $body['success'] ) || ! $body['success'] ) {
            $error_message = isset( $body['message'] ) ? $body['message'] : __( 'Unknown error occurred.', 'woo-nalda-sync' );
            $this->log( 'Failed to fetch orders: ' . $error_message );
            return array(
                'success' => false,
                'message' => $error_message,
            );
        }

        $orders_count = isset( $body['result'] ) ? count( $body['result'] ) : 0;
        $this->log( sprintf( 'Successfully fetched %d orders from Nalda API', $orders_count ) );

        return array(
            'success' => true,
            'orders'  => isset( $body['result'] ) ? $body['result'] : array(),
        );
    }

    /**
     * Fetch order items from Nalda API.
     *
     * @param int $order_id Nalda order ID.
     * @return array Result with items array.
     */
    private function fetch_order_items( $order_id ) {
        $settings = woo_nalda_sync()->get_setting();
        $api_url  = isset( $settings['nalda_api_url'] ) && ! empty( $settings['nalda_api_url'] )
            ? $settings['nalda_api_url']
            : self::NALDA_API_URL;

        $response = wp_remote_get( rtrim( $api_url, '/' ) . '/orders/' . $order_id . '/items', array(
            'timeout' => 30,
            'headers' => array(
                'X-API-KEY' => $settings['nalda_api_key'],
                'Accept'    => 'application/json',
            ),
        ) );

        if ( is_wp_error( $response ) ) {
            $this->log( 'Failed to fetch order items: ' . $response->get_error_message() );
            return array(
                'success' => false,
                'message' => $response->get_error_message(),
            );
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        $body        = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $status_code !== 200 || ! isset( $body['success'] ) || ! $body['success'] ) {
            $error_message = isset( $body['message'] ) ? $body['message'] : __( 'Unknown error occurred.', 'woo-nalda-sync' );
            return array(
                'success' => false,
                'message' => $error_message,
            );
        }

        return array(
            'success' => true,
            'items'   => isset( $body['result'] ) ? $body['result'] : array(),
        );
    }

    /**
     * Get existing WooCommerce order by Nalda order ID.
     *
     * @param int $nalda_order_id Nalda order ID.
     * @return WC_Order|false WooCommerce order or false if not found.
     */
    private function get_existing_order( $nalda_order_id ) {
        $orders = wc_get_orders( array(
            'meta_key'   => '_nalda_order_id',
            'meta_value' => $nalda_order_id,
            'limit'      => 1,
        ) );

        return ! empty( $orders ) ? $orders[0] : false;
    }

    /**
     * Create WooCommerce order from Nalda order.
     *
     * @param array $nalda_order  Nalda order data.
     * @param array $order_items  Nalda order items.
     * @return WC_Order|false Created order or false on failure.
     */
    private function create_order( $nalda_order, $order_items ) {
        try {
            // Get or create customer.
            $customer_id = $this->get_or_create_customer( $nalda_order );

            // Create order with 'pending' status first (don't set 'processing' yet).
            // This prevents WooCommerce from sending the "New Order" email before items are added.
            // We'll set the status to 'processing' after all items and totals are calculated.
            $order = wc_create_order( array(
                'customer_id'   => $customer_id,
                'created_via'   => 'nalda',
                'customer_note' => sprintf( __( 'Imported from Nalda Marketplace (Order #%d)', 'woo-nalda-sync' ), $nalda_order['orderId'] ),
            ) );

            if ( is_wp_error( $order ) ) {
                $this->log( 'Failed to create order: ' . $order->get_error_message() );
                return false;
            }

            // Set billing address to Nalda (our legal customer for tax purposes).
            $order->set_billing_company( 'Nalda Marketplace AG' );
            $order->set_billing_first_name( 'Nalda' );
            $order->set_billing_last_name( 'Marketplace' );
            $order->set_billing_email( 'orders@nalda.com' );
            $order->set_billing_address_1( 'Grabenstrasse 15a' );
            $order->set_billing_city( 'Baar' );
            $order->set_billing_postcode( '6340' );
            $order->set_billing_country( 'CH' );
            $order->update_meta_data( '_billing_vat_number', 'CHE-353.496.457 MWST' );

            // Set shipping address to end customer (for fulfillment).
            $order->set_shipping_first_name( $nalda_order['firstName'] );
            $order->set_shipping_last_name( $nalda_order['lastName'] );
            $order->set_shipping_address_1( $nalda_order['street1'] );
            $order->set_shipping_city( $nalda_order['city'] );
            $order->set_shipping_postcode( $nalda_order['postalCode'] );
            $order->set_shipping_country( $nalda_order['country'] );

            // Store end customer details in metadata for reference.
            $order->update_meta_data( '_nalda_end_customer_email', $nalda_order['email'] );
            $order->update_meta_data( '_nalda_end_customer_first_name', $nalda_order['firstName'] );
            $order->update_meta_data( '_nalda_end_customer_last_name', $nalda_order['lastName'] );

            // Add order items and calculate commission totals from items.
            $total_commission = 0;
            $total_refund     = 0;
            $commission_pct   = 0;
            $payout_status    = '';

            foreach ( $order_items as $item ) {
                $this->add_order_item( $order, $item );

                // Sum up commission and refund from items.
                if ( isset( $item['commission'] ) ) {
                    $total_commission += floatval( $item['commission'] );
                }
                if ( isset( $item['refund'] ) ) {
                    $total_refund += floatval( $item['refund'] );
                }
                // Get commission percentage (should be same for all items).
                if ( isset( $item['commissionPercentage'] ) && $commission_pct === 0 ) {
                    $commission_pct = floatval( $item['commissionPercentage'] );
                }
                // Get payout status from items.
                if ( isset( $item['payoutStatus'] ) && empty( $payout_status ) ) {
                    $payout_status = $item['payoutStatus'];
                }
            }

            // Set order date.
            if ( isset( $nalda_order['createdAt'] ) ) {
                $order->set_date_created( strtotime( $nalda_order['createdAt'] ) );
            }

            // Set currency.
            if ( isset( $order_items[0]['currency'] ) ) {
                $order->set_currency( $order_items[0]['currency'] );
            }

            // Calculate totals without recalculating taxes (prices are already VAT-included).
            $order->calculate_totals( false );

            // Set payment method and status based on Nalda payout status.
            // Only set payment method and mark as paid if Nalda has actually paid us out.
            $payout_status_lower = strtolower( $payout_status );
            if ( 'paid_out' === $payout_status_lower ) {
                // Nalda has paid us - set payment method and mark order as paid.
                $order->set_payment_method( 'nalda' );
                $order->set_payment_method_title( 'Nalda Marketplace' );
                $order->set_date_paid( time() );
                $order->add_order_note(
                    __( 'Payment received from Nalda Marketplace.', 'woo-nalda-sync' ),
                    false,
                    true
                );
            } else {
                // Nalda hasn't paid us yet - leave as unpaid with no payment method.
                // Customer paid Nalda, but we're waiting for payout.
                $order->set_date_paid( null );
            }

            // Set Nalda metadata - use values from items if main order doesn't have them.
            $order->update_meta_data( '_nalda_order_id', $nalda_order['orderId'] );
            $order->update_meta_data( '_nalda_payout_status', ! empty( $payout_status ) ? $payout_status : ( isset( $nalda_order['payoutStatus'] ) ? $nalda_order['payoutStatus'] : '' ) );
            $order->update_meta_data( '_nalda_fee', isset( $nalda_order['fee'] ) ? floatval( $nalda_order['fee'] ) : 0 );
            $order->update_meta_data( '_nalda_commission', $total_commission > 0 ? $total_commission : ( isset( $nalda_order['commission'] ) ? floatval( $nalda_order['commission'] ) : 0 ) );
            $order->update_meta_data( '_nalda_commission_percentage', $commission_pct > 0 ? $commission_pct : ( isset( $nalda_order['commissionPercentage'] ) ? floatval( $nalda_order['commissionPercentage'] ) : 0 ) );
            $order->update_meta_data( '_nalda_refund', $total_refund > 0 ? $total_refund : ( isset( $nalda_order['refund'] ) ? floatval( $nalda_order['refund'] ) : 0 ) );
            $order->update_meta_data( '_nalda_imported_at', current_time( 'mysql' ) );
            $order->update_meta_data( '_order_source', 'nalda.com' );
            $order->update_meta_data( '_created_via', 'nalda' );
            $order->update_meta_data( '_paid_via', 'Nalda' );

            // Add collection info if available.
            if ( ! empty( $nalda_order['collectionId'] ) ) {
                $order->update_meta_data( '_nalda_collection_id', $nalda_order['collectionId'] );
                $order->update_meta_data( '_nalda_collection_name', $nalda_order['collectionName'] );
            }

            // Store delivery status (state) from the first item (or first non-empty one).
            // This will be used for order status export.
            $first_delivery_status = '';
            $first_delivery_date   = '';
            foreach ( $order_items as $item ) {
                if ( ! empty( $item['deliveryStatus'] ) && empty( $first_delivery_status ) ) {
                    $first_delivery_status = $item['deliveryStatus'];
                }
                if ( ! empty( $item['deliveryDatePlanned'] ) && empty( $first_delivery_date ) ) {
                    $first_delivery_date = $item['deliveryDatePlanned'];
                }
                if ( ! empty( $first_delivery_status ) && ! empty( $first_delivery_date ) ) {
                    break;
                }
            }

            // Set Nalda delivery fields from API.
            // Always set the state - use deliveryStatus from items, or default to IN_PREPARATION for new orders.
            $order->update_meta_data( '_nalda_state', ! empty( $first_delivery_status ) ? $first_delivery_status : 'IN_PREPARATION' );
            
            if ( ! empty( $first_delivery_date ) ) {
                // Convert to YYYY-MM-DD format for date input field.
                $delivery_date_formatted = date( 'Y-m-d', strtotime( $first_delivery_date ) );
                if ( $delivery_date_formatted && $delivery_date_formatted !== '1970-01-01' ) {
                    $order->update_meta_data( '_nalda_expected_delivery_date', $delivery_date_formatted );
                }
            }

            // Add order note.
            $order->add_order_note(
                sprintf(
                    __( 'Order imported from Nalda Marketplace. Nalda Order ID: %d', 'woo-nalda-sync' ),
                    $nalda_order['orderId']
                ),
                false,
                true
            );

            $order->save();

            // Now set the order status to 'processing'.
            // This triggers the "New Order" email with the correct totals.
            $order->update_status( 'processing', __( 'Order imported from Nalda Marketplace.', 'woo-nalda-sync' ) );

            $this->log( sprintf( 'Created WooCommerce order #%d from Nalda order #%d', $order->get_id(), $nalda_order['orderId'] ) );

            return $order;

        } catch ( Exception $e ) {
            $this->log( 'Exception creating order: ' . $e->getMessage() );
            return false;
        }
    }

    /**
     * Add item to order.
     *
     * @param WC_Order $order      WooCommerce order.
     * @param array    $nalda_item Nalda order item data.
     */
    private function add_order_item( $order, $nalda_item ) {
        // Try to find existing product by GTIN.
        $product = $this->find_product_by_gtin( $nalda_item['gtin'] );

        // Calculate net price (what we actually receive after Nalda's commission).
        $customer_price = floatval( $nalda_item['price'] );
        $commission     = isset( $nalda_item['commission'] ) ? floatval( $nalda_item['commission'] ) : 0;
        $quantity       = intval( $nalda_item['quantity'] );
        
        // Net price per item = customer price - (commission / quantity).
        $commission_per_item = $quantity > 0 ? ( $commission / $quantity ) : 0;
        $net_price_per_item  = $customer_price - $commission_per_item;
        
        // Ensure net price is not negative.
        if ( $net_price_per_item < 0 ) {
            $net_price_per_item = 0;
        }

        if ( $product ) {
            // Add existing product.
            $item = new WC_Order_Item_Product();
            $item->set_product( $product );
            $item->set_quantity( $quantity );
            // Use net price (after commission) for tax calculations.
            $item->set_subtotal( $net_price_per_item * $quantity );
            $item->set_total( $net_price_per_item * $quantity );
            // Set taxes to 0 since net price is already VAT-included.
            $item->set_subtotal_tax( 0 );
            $item->set_total_tax( 0 );

            // Reduce stock if enabled.
            $settings = woo_nalda_sync()->get_setting();
            if ( isset( $settings['order_reduce_stock'] ) && 'yes' === $settings['order_reduce_stock'] ) {
                if ( $product->managing_stock() ) {
                    $new_stock = wc_update_product_stock( $product, $quantity, 'decrease' );
                    $this->log( sprintf(
                        'Reduced stock for product #%d (%s) by %d. New stock: %s',
                        $product->get_id(),
                        $product->get_name(),
                        $quantity,
                        $new_stock
                    ) );
                    
                    // Add order note about stock reduction.
                    $order->add_order_note(
                        sprintf(
                            __( 'Stock reduced for %s (-%d)', 'woo-nalda-sync' ),
                            $product->get_name(),
                            $quantity
                        ),
                        false,
                        false
                    );
                }
            }
        } else {
            // Create custom line item.
            $item = new WC_Order_Item_Product();
            $item->set_name( $nalda_item['title'] );
            $item->set_quantity( $quantity );
            // Use net price (after commission) for tax calculations.
            $item->set_subtotal( $net_price_per_item * $quantity );
            $item->set_total( $net_price_per_item * $quantity );
            // Set taxes to 0 since net price is already VAT-included.
            $item->set_subtotal_tax( 0 );
            $item->set_total_tax( 0 );
        }

        // Add Nalda item metadata.
        $item->add_meta_data( '_nalda_gtin', $nalda_item['gtin'], true );
        $item->add_meta_data( '_nalda_customer_price', $customer_price, true );
        $item->add_meta_data( '_nalda_net_price', $net_price_per_item, true );
        $item->add_meta_data( '_nalda_commission_amount', $commission_per_item, true );
        $item->add_meta_data( '_nalda_condition', isset( $nalda_item['condition'] ) ? $nalda_item['condition'] : '', true );
        $item->add_meta_data( '_nalda_delivery_status', isset( $nalda_item['deliveryStatus'] ) ? $nalda_item['deliveryStatus'] : '', true );
        $item->add_meta_data( '_reduced_stock', $product ? 'yes' : 'no', true );

        if ( isset( $nalda_item['deliveryDatePlanned'] ) ) {
            $item->add_meta_data( '_nalda_delivery_date_planned', $nalda_item['deliveryDatePlanned'], true );
        }

        $order->add_item( $item );
    }

    /**
     * Find WooCommerce product by GTIN.
     *
     * @param string $gtin GTIN value.
     * @return WC_Product|false Product or false if not found.
     */
    private function find_product_by_gtin( $gtin ) {
        if ( empty( $gtin ) ) {
            return false;
        }

        // Search by common GTIN meta keys.
        $gtin_keys = array(
            '_gtin',
            '_ean',
            '_isbn',
            '_upc',
            '_global_unique_id',
            'gtin',
            'ean',
            'isbn',
            'upc',
            '_barcode',
            'barcode',
        );

        foreach ( $gtin_keys as $meta_key ) {
            $products = wc_get_products( array(
                'meta_key'   => $meta_key,
                'meta_value' => $gtin,
                'limit'      => 1,
            ) );

            if ( ! empty( $products ) ) {
                return $products[0];
            }
        }

        // Also try searching by SKU.
        $product_id = wc_get_product_id_by_sku( $gtin );
        if ( $product_id ) {
            return wc_get_product( $product_id );
        }

        return false;
    }

    /**
     * Update existing WooCommerce order with Nalda data.
     *
     * @param WC_Order $order       Existing WooCommerce order.
     * @param array    $nalda_order Nalda order data.
     * @return bool True if updated, false if no changes needed.
     */
    private function update_order( $order, $nalda_order ) {
        $updated = false;

        // Fetch order items from Nalda to get latest delivery status.
        $items_result = $this->fetch_order_items( $nalda_order['orderId'] );
        
        if ( $items_result['success'] && ! empty( $items_result['items'] ) ) {
            // Track first delivery status and date for order-level update.
            $first_delivery_status = '';
            $first_delivery_date   = '';
            
            // Update order item delivery statuses.
            $order_items = $order->get_items();
            
            foreach ( $order_items as $order_item ) {
                $gtin = $order_item->get_meta( '_nalda_gtin' );
                
                if ( $gtin ) {
                    // Find matching Nalda item.
                    foreach ( $items_result['items'] as $nalda_item ) {
                        if ( $nalda_item['gtin'] === $gtin ) {
                            // Track first delivery status and date.
                            if ( ! empty( $nalda_item['deliveryStatus'] ) && empty( $first_delivery_status ) ) {
                                $first_delivery_status = $nalda_item['deliveryStatus'];
                            }
                            if ( ! empty( $nalda_item['deliveryDatePlanned'] ) && empty( $first_delivery_date ) ) {
                                $first_delivery_date = $nalda_item['deliveryDatePlanned'];
                            }
                            
                            // Update delivery status.
                            $old_status = $order_item->get_meta( '_nalda_delivery_status' );
                            $new_status = isset( $nalda_item['deliveryStatus'] ) ? $nalda_item['deliveryStatus'] : '';
                            
                            if ( $old_status !== $new_status ) {
                                $order_item->update_meta_data( '_nalda_delivery_status', $new_status );
                                $order_item->save();
                                
                                $order->add_order_note(
                                    sprintf(
                                        __( 'Item "%s" delivery status updated: %s → %s', 'woo-nalda-sync' ),
                                        $order_item->get_name(),
                                        $old_status ?: 'None',
                                        $new_status
                                    ),
                                    false,
                                    true
                                );
                                $updated = true;
                                
                                // Restore stock if order is cancelled or returned.
                                $this->maybe_restore_stock( $order, $order_item, $new_status, $old_status );
                            }
                            
                            // Update WooCommerce order status based on delivery status.
                            if ( $new_status === 'DELIVERED' && $order->get_status() !== 'completed' ) {
                                $order->update_status( 'completed', __( 'All items delivered (updated from Nalda)', 'woo-nalda-sync' ) );
                                $updated = true;
                            } elseif ( $new_status === 'CANCELLED' && ! in_array( $order->get_status(), array( 'cancelled', 'refunded' ) ) ) {
                                $order->update_status( 'cancelled', __( 'Order cancelled in Nalda', 'woo-nalda-sync' ) );
                                $updated = true;
                            } elseif ( $new_status === 'RETURNED' && $order->get_status() !== 'refunded' ) {
                                $order->update_status( 'refunded', __( 'Order returned (updated from Nalda)', 'woo-nalda-sync' ) );
                                $updated = true;
                            }
                            
                            break;
                        }
                    }
                }
            }
            
            // Note: We don't update order-level Nalda state during sync updates.
            // State is only set during initial order creation to allow manual overrides.
            
            // Update order-level expected delivery date from API if changed.
            if ( ! empty( $first_delivery_date ) ) {
                $delivery_date_formatted = date( 'Y-m-d', strtotime( $first_delivery_date ) );
                if ( $delivery_date_formatted && $delivery_date_formatted !== '1970-01-01' ) {
                    $current_date = $order->get_meta( '_nalda_expected_delivery_date' );
                    if ( $current_date !== $delivery_date_formatted ) {
                        $order->update_meta_data( '_nalda_expected_delivery_date', $delivery_date_formatted );
                        $order->add_order_note(
                            sprintf(
                                __( 'Expected delivery date updated: %s → %s', 'woo-nalda-sync' ),
                                $current_date ?: 'None',
                                $delivery_date_formatted
                            ),
                            false,
                            true
                        );
                        $updated = true;
                    }
                }
            }
        }

        // Update payout status if changed.
        $current_payout_status = $order->get_meta( '_nalda_payout_status' );
        $new_payout_status     = isset( $nalda_order['payoutStatus'] ) ? $nalda_order['payoutStatus'] : '';

        if ( $current_payout_status !== $new_payout_status ) {
            $order->update_meta_data( '_nalda_payout_status', $new_payout_status );
            $order->add_order_note(
                sprintf(
                    __( 'Nalda payout status updated: %s → %s', 'woo-nalda-sync' ),
                    $current_payout_status ?: 'None',
                    $new_payout_status
                ),
                false,
                true
            );
            
            // Update payment status based on new payout status.
            $payout_status_lower = strtolower( $new_payout_status );
            if ( 'paid_out' === $payout_status_lower && ! $order->is_paid() ) {
                // Nalda has now paid us - set payment method and mark order as paid.
                $order->set_payment_method( 'nalda' );
                $order->set_payment_method_title( 'Nalda Marketplace' );
                $order->set_date_paid( time() );
                $order->add_order_note(
                    __( 'Payment received from Nalda Marketplace.', 'woo-nalda-sync' ),
                    false,
                    true
                );
                $this->log( sprintf( 'Order #%d marked as paid - Nalda payout received', $order->get_id() ) );
            } elseif ( 'paid_out' !== $payout_status_lower && $order->is_paid() ) {
                // Payout status changed from paid to unpaid (rare, but handle it).
                // Remove payment method and mark as unpaid.
                $order->set_payment_method( '' );
                $order->set_payment_method_title( '' );
                $order->set_date_paid( null );
                $order->add_order_note(
                    __( 'Payment status reverted - Nalda payout status changed.', 'woo-nalda-sync' ),
                    false,
                    true
                );
                $this->log( sprintf( 'Order #%d marked as unpaid - Nalda payout status changed to %s', $order->get_id(), $new_payout_status ) );
            }
            
            $updated = true;
        }

        // Update refund if present.
        if ( isset( $nalda_order['refund'] ) && $nalda_order['refund'] > 0 ) {
            $current_refund = $order->get_meta( '_nalda_refund' );
            if ( $current_refund != $nalda_order['refund'] ) {
                $order->update_meta_data( '_nalda_refund', $nalda_order['refund'] );
                $order->add_order_note(
                    sprintf(
                        __( 'Nalda refund amount updated: %s', 'woo-nalda-sync' ),
                        wc_price( $nalda_order['refund'] )
                    ),
                    false,
                    true
                );
                $updated = true;
            }
        }

        if ( $updated ) {
            $order->update_meta_data( '_nalda_last_sync', current_time( 'mysql' ) );
            $order->save();
            $this->log( sprintf( 'Updated WooCommerce order #%d from Nalda order #%d', $order->get_id(), $nalda_order['orderId'] ) );
        }

        return $updated;
    }

    /**
     * Restore stock when order is cancelled or returned.
     *
     * @param WC_Order          $order      Order object.
     * @param WC_Order_Item     $order_item Order item.
     * @param string            $new_status New delivery status.
     * @param string            $old_status Old delivery status.
     */
    private function maybe_restore_stock( $order, $order_item, $new_status, $old_status ) {
        // Only restore stock if it was previously reduced.
        if ( 'yes' !== $order_item->get_meta( '_reduced_stock' ) ) {
            return;
        }

        // Restore stock if order is cancelled or returned.
        if ( ! in_array( $new_status, array( 'CANCELLED', 'RETURNED' ) ) ) {
            return;
        }

        // Don't restore stock if already cancelled/returned.
        if ( in_array( $old_status, array( 'CANCELLED', 'RETURNED' ) ) ) {
            return;
        }

        $product = $order_item->get_product();
        if ( ! $product || ! $product->managing_stock() ) {
            return;
        }

        $quantity = $order_item->get_quantity();
        $new_stock = wc_update_product_stock( $product, $quantity, 'increase' );
        
        $this->log( sprintf(
            'Restored stock for product #%d (%s) by %d due to %s. New stock: %s',
            $product->get_id(),
            $product->get_name(),
            $quantity,
            $new_status,
            $new_stock
        ) );
        
        $order->add_order_note(
            sprintf(
                __( 'Stock restored for %s (+%d) - Order %s', 'woo-nalda-sync' ),
                $product->get_name(),
                $quantity,
                strtolower( $new_status )
            ),
            false,
            false
        );
        
        // Mark stock as restored.
        $order_item->update_meta_data( '_stock_restored', 'yes' );
        $order_item->save();
    }

    /**
     * Create or get customer for Nalda order.
     *
     * @param array $nalda_order Nalda order data.
     * @return int Customer ID (0 for guest).
     */
    private function get_or_create_customer( $nalda_order ) {
        // Since Nalda is the legal customer (set in billing address),
        // we always use guest checkout for these orders.
        // End customer details are stored in shipping address and metadata.
        // This prevents end customers from having WooCommerce accounts and seeing orders.
        $this->log( sprintf( 'Using guest checkout for Nalda order #%d (end customer: %s)', $nalda_order['orderId'], $nalda_order['email'] ) );
        return 0;
    }

    /**
     * Update sync statistics.
     *
     * @param int $order_count Number of orders synced.
     */
    private function update_sync_stats( $order_count ) {
        $stats = get_option( 'woo_nalda_sync_stats', array() );

        $stats['last_order_import']   = current_time( 'mysql' );
        $stats['orders_synced']     = isset( $stats['orders_synced'] ) ? $stats['orders_synced'] + $order_count : $order_count;
        $stats['total_order_syncs'] = isset( $stats['total_order_syncs'] ) ? $stats['total_order_syncs'] + 1 : 1;

        update_option( 'woo_nalda_sync_stats', $stats );
    }

    /**
     * Log message.
     *
     * @param string $message Message to log.
     */
    private function log( $message ) {
        $settings = woo_nalda_sync()->get_setting();

        if ( isset( $settings['log_enabled'] ) && 'yes' === $settings['log_enabled'] ) {
            if ( function_exists( 'wc_get_logger' ) ) {
                $logger = wc_get_logger();
                $logger->info( $message, array( 'source' => 'woo-nalda-sync-orders' ) );
            } else {
                error_log( '[WooCommerce Nalda Sync - Orders] ' . $message );
            }
        }
    }

    /**
     * Get sync status.
     *
     * @return array Sync status data.
     */
    public function get_sync_status() {
        $stats = get_option( 'woo_nalda_sync_stats', array() );

        return array(
            'last_sync'     => isset( $stats['last_order_import'] ) ? $stats['last_order_import'] : null,
            'orders_synced' => isset( $stats['orders_synced'] ) ? $stats['orders_synced'] : 0,
            'total_syncs'   => isset( $stats['total_order_syncs'] ) ? $stats['total_order_syncs'] : 0,
        );
    }

    /**
     * Disable customer emails for Nalda orders.
     *
     * @param string   $recipient Email recipient.
     * @param WC_Order $order     Order object.
     * @return string|false Modified recipient or false to disable email.
     */
    public function disable_customer_emails( $recipient, $order ) {
        if ( ! $order ) {
            return $recipient;
        }
        
        // Check if this is a Nalda order.
        $nalda_order_id = $order->get_meta( '_nalda_order_id' );
        
        if ( ! empty( $nalda_order_id ) ) {
            // Disable customer emails for Nalda orders.
            // Nalda handles all customer communication.
            return false;
        }
        
        return $recipient;
    }
    
    /**
     * Hide Nalda orders from My Account page.
     *
     * @param array $args Query args.
     * @return array Modified query args.
     */
    public function hide_nalda_orders_from_my_account( $args ) {
        if ( ! is_admin() ) {
            $args['meta_query'] = isset( $args['meta_query'] ) ? $args['meta_query'] : array();
            $args['meta_query'][] = array(
                'key'     => '_nalda_order_id',
                'compare' => 'NOT EXISTS',
            );
        }
        
        return $args;
    }
    
    /**
     * Restrict viewing Nalda order details on frontend.
     *
     * @param int $order_id Order ID.
     */
    public function restrict_nalda_order_view( $order_id ) {
        $order = wc_get_order( $order_id );
        
        if ( ! $order ) {
            return;
        }
        
        $nalda_order_id = $order->get_meta( '_nalda_order_id' );
        
        // If this is a Nalda order and user is not admin, show error.
        if ( ! empty( $nalda_order_id ) && ! current_user_can( 'manage_woocommerce' ) ) {
            wc_add_notice( __( 'You do not have permission to view this order.', 'woo-nalda-sync' ), 'error' );
            wp_safe_redirect( wc_get_page_permalink( 'myaccount' ) );
            exit;
        }
    }
    
    /**
     * Validate Nalda API credentials.
     *
     * @param string $api_key Nalda API key.
     * @param string $api_url Nalda API URL.
     * @return array Result with success status and message.
     */
    public function validate_api_credentials( $api_key, $api_url = null ) {
        if ( empty( $api_key ) ) {
            return array(
                'success' => false,
                'message' => __( 'API key is required.', 'woo-nalda-sync' ),
            );
        }

        $api_url = $api_url ?: self::NALDA_API_URL;

        $response = wp_remote_get( rtrim( $api_url, '/' ) . '/health-check', array(
            'timeout' => 15,
            'headers' => array(
                'X-API-KEY' => $api_key,
                'Accept'    => 'application/json',
            ),
        ) );

        if ( is_wp_error( $response ) ) {
            return array(
                'success' => false,
                'message' => sprintf( __( 'Connection failed: %s', 'woo-nalda-sync' ), $response->get_error_message() ),
            );
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        $body        = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $status_code === 200 && isset( $body['success'] ) && $body['success'] ) {
            return array(
                'success' => true,
                'message' => __( 'API credentials are valid.', 'woo-nalda-sync' ),
            );
        }

        $error_message = isset( $body['message'] ) ? $body['message'] : __( 'Validation failed.', 'woo-nalda-sync' );

        return array(
            'success' => false,
            'message' => $error_message,
        );
    }

    /**
     * CSV columns for order status export.
     *
     * @var array
     */
    private $order_status_csv_columns = array(
        'orderId',
        'gtin',
        'state',
        'expectedDeliveryDate',
        'trackingCode',
    );

    /**
     * Map WooCommerce order status to Nalda state.
     *
     * @param string $wc_status WooCommerce order status.
     * @param string $delivery_status Item delivery status from Nalda.
     * @return string Nalda state.
     */
    private function map_order_status_to_nalda_state( $wc_status, $delivery_status = '' ) {
        // If we have a Nalda delivery status, prefer it.
        if ( ! empty( $delivery_status ) ) {
            $valid_states = array( 'DELIVERED', 'IN_DELIVERY', 'READY_TO_COLLECT', 'CANCELLED', 'RETURNED' );
            if ( in_array( strtoupper( $delivery_status ), $valid_states, true ) ) {
                return strtoupper( $delivery_status );
            }
        }

        // Map WooCommerce status to Nalda state.
        $status_map = array(
            'completed'  => 'DELIVERED',
            'processing' => 'IN_DELIVERY',
            'on-hold'    => 'READY_TO_COLLECT',
            'cancelled'  => 'CANCELLED',
            'refunded'   => 'RETURNED',
            'failed'     => 'CANCELLED',
        );

        return isset( $status_map[ $wc_status ] ) ? $status_map[ $wc_status ] : 'IN_DELIVERY';
    }

    /**
     * Run order status export sync.
     *
     * Generates a CSV file with order status updates and uploads it to Nalda via SFTP.
     *
     * @param string $trigger Trigger type (manual or automatic). Default: manual.
     * @return array Result with success status and message.
     */
    public function run_order_status_export( $trigger = 'manual' ) {
        $start_time = microtime( true );
        $this->log( 'Starting order status export...' );

        // Check license.
        if ( ! $this->license_manager->is_valid() ) {
            $this->log( 'License validation failed.' );
            woo_nalda_sync_logger()->log_order_status_export(
                $trigger,
                Woo_Nalda_Sync_Logger::STATUS_ERROR,
                __( 'License is not valid', 'woo-nalda-sync' )
            );
            return array(
                'success' => false,
                'message' => __( 'License is not valid.', 'woo-nalda-sync' ),
            );
        }

        $settings = woo_nalda_sync()->get_setting();

        // Validate SFTP settings.
        if ( empty( $settings['sftp_host'] ) || empty( $settings['sftp_username'] ) || empty( $settings['sftp_password'] ) ) {
            $this->log( 'SFTP settings are not configured.' );
            woo_nalda_sync_logger()->log_order_status_export(
                $trigger,
                Woo_Nalda_Sync_Logger::STATUS_ERROR,
                __( 'SFTP settings are not configured', 'woo-nalda-sync' )
            );
            return array(
                'success' => false,
                'message' => __( 'SFTP settings are not configured.', 'woo-nalda-sync' ),
            );
        }

        // Generate CSV file.
        $csv_result = $this->generate_order_status_csv();

        if ( ! $csv_result['success'] ) {
            woo_nalda_sync_logger()->log_order_status_export(
                $trigger,
                Woo_Nalda_Sync_Logger::STATUS_ERROR,
                $csv_result['message']
            );
            return $csv_result;
        }

        // Check if there are orders to export.
        if ( $csv_result['order_count'] === 0 ) {
            $this->log( 'No Nalda orders found to export.' );
            woo_nalda_sync_logger()->log_order_status_export(
                $trigger,
                Woo_Nalda_Sync_Logger::STATUS_SUCCESS,
                __( 'No Nalda orders found to export', 'woo-nalda-sync' )
            );
            return array(
                'success'     => true,
                'message'     => __( 'No Nalda orders found to export.', 'woo-nalda-sync' ),
                'order_count' => 0,
            );
        }

        // Upload CSV via API.
        $upload_result = $this->upload_order_status_csv( $csv_result['file_path'] );

        // Clean up temporary file.
        if ( file_exists( $csv_result['file_path'] ) ) {
            unlink( $csv_result['file_path'] );
        }

        if ( ! $upload_result['success'] ) {
            woo_nalda_sync_logger()->log_order_status_export(
                $trigger,
                Woo_Nalda_Sync_Logger::STATUS_ERROR,
                $upload_result['message']
            );
            return $upload_result;
        }

        $duration = round( microtime( true ) - $start_time, 2 );

        // Update sync stats.
        $this->update_order_status_export_stats( $csv_result['order_count'] );

        $this->log( sprintf( 'Order status export completed successfully in %s seconds. %d orders exported.', $duration, $csv_result['order_count'] ) );

        // Log success.
        $summary = sprintf(
            __( 'Exported %d order statuses in %s seconds', 'woo-nalda-sync' ),
            $csv_result['order_count'],
            $duration
        );
        woo_nalda_sync_logger()->log_order_status_export(
            $trigger,
            Woo_Nalda_Sync_Logger::STATUS_SUCCESS,
            $summary,
            array(
                'order_count' => $csv_result['order_count'],
                'duration'    => $duration,
            )
        );

        return array(
            'success'     => true,
            'message'     => sprintf(
                __( 'Successfully exported %d order statuses in %s seconds.', 'woo-nalda-sync' ),
                $csv_result['order_count'],
                $duration
            ),
            'order_count' => $csv_result['order_count'],
            'duration'    => $duration,
        );
    }

    /**
     * Generate order status CSV file.
     *
     * @return array Result with file path and order count.
     */
    private function generate_order_status_csv() {
        $settings   = woo_nalda_sync()->get_setting();
        $batch_size = isset( $settings['batch_size'] ) ? absint( $settings['batch_size'] ) : 100;

        // Generate filename.
        $filename = 'order-status_' . wp_date( 'Y-m-d_H-i-s' ) . '.csv';

        // Use WordPress temp directory to avoid permission issues with cron.
        $temp_dir  = get_temp_dir();
        $file_path = trailingslashit( $temp_dir ) . $filename;

        // If temp directory is not writable, fallback to uploads directory.
        if ( ! wp_is_writable( $temp_dir ) ) {
            $upload_dir = wp_upload_dir();
            $file_path  = trailingslashit( $upload_dir['basedir'] ) . 'woo-nalda-sync/' . $filename;
            wp_mkdir_p( dirname( $file_path ) );
        }

        $this->log( sprintf( 'Creating order status CSV file at: %s', $file_path ) );

        // Open file for writing.
        $file = fopen( $file_path, 'w' );

        if ( ! $file ) {
            $this->log( sprintf( 'Failed to create CSV file at: %s', $file_path ) );
            return array(
                'success' => false,
                'message' => __( 'Failed to create CSV file.', 'woo-nalda-sync' ),
            );
        }

        // Add BOM for UTF-8.
        fwrite( $file, "\xEF\xBB\xBF" );

        // Write header row.
        fputcsv( $file, $this->order_status_csv_columns );

        // Get Nalda orders in batches.
        $page        = 1;
        $order_count = 0;

        do {
            $args = array(
                'limit'    => $batch_size,
                'page'     => $page,
                'orderby'  => 'date',
                'order'    => 'DESC',
                'meta_key' => '_nalda_order_id',
                'meta_compare' => 'EXISTS',
            );

            $orders = wc_get_orders( $args );

            if ( empty( $orders ) ) {
                break;
            }

            foreach ( $orders as $order ) {
                $rows = $this->get_order_status_rows( $order );

                foreach ( $rows as $row ) {
                    fputcsv( $file, $row );
                    $order_count++;
                }
            }

            $page++;

        } while ( count( $orders ) === $batch_size );

        fclose( $file );

        $this->log( sprintf( 'Generated order status CSV with %d entries.', $order_count ) );

        return array(
            'success'     => true,
            'file_path'   => $file_path,
            'order_count' => $order_count,
        );
    }

    /**
     * Get order status rows for CSV export.
     *
     * Each order item gets its own row in the CSV.
     *
     * @param WC_Order $order WooCommerce order.
     * @return array Array of row arrays.
     */
    private function get_order_status_rows( $order ) {
        $rows = array();

        $nalda_order_id = $order->get_meta( '_nalda_order_id' );

        if ( empty( $nalda_order_id ) ) {
            return $rows;
        }

        // Get state from order meta (set during import or manually in admin).
        $nalda_state = $order->get_meta( '_nalda_state' );
        
        // If no state is set, fall back to mapping from WooCommerce status.
        if ( empty( $nalda_state ) ) {
            $wc_status   = $order->get_status();
            $nalda_state = $this->map_order_status_to_nalda_state( $wc_status, '' );
        }

        // Get expected delivery date from order meta.
        $expected_delivery = $order->get_meta( '_nalda_expected_delivery_date' );
        if ( ! empty( $expected_delivery ) ) {
            // Format to dd.mm.yy for Nalda CSV.
            $expected_delivery = wp_date( 'd.m.y', strtotime( $expected_delivery ) );
        } else {
            // Default to order date + 3 days if not set.
            $order_date        = $order->get_date_created();
            $expected_delivery = $order_date ? $order_date->modify( '+3 days' )->format( 'd.m.y' ) : wp_date( 'd.m.y', strtotime( '+3 days' ) );
        }

        // Get tracking code from order meta.
        $tracking_code = $order->get_meta( '_nalda_tracking_code' );
        if ( empty( $tracking_code ) ) {
            // Try common tracking meta keys as fallback.
            $tracking_keys = array( '_tracking_number', '_tracking_code', 'tracking_number', 'tracking_code' );
            foreach ( $tracking_keys as $key ) {
                $tracking_code = $order->get_meta( $key );
                if ( ! empty( $tracking_code ) ) {
                    break;
                }
            }
        }

        // Get items from order.
        $order_items = $order->get_items();

        foreach ( $order_items as $item ) {
            $gtin = $item->get_meta( '_nalda_gtin' );

            // Skip items without GTIN.
            if ( empty( $gtin ) ) {
                continue;
            }

            $rows[] = array(
                'orderId'              => $nalda_order_id,
                'gtin'                 => $gtin,
                'state'                => $nalda_state,
                'expectedDeliveryDate' => $expected_delivery,
                'trackingCode'         => $tracking_code ?: '',
            );
        }

        return $rows;
    }

    /**
     * Upload order status CSV via Nalda API.
     *
     * @param string $file_path Path to CSV file.
     * @return array Result with success status and message.
     */
    private function upload_order_status_csv( $file_path ) {
        $settings    = woo_nalda_sync()->get_setting();
        $license_key = $this->license_manager->get_license_key();
        $domain      = $this->license_manager->get_domain();

        // Validate file exists.
        if ( ! file_exists( $file_path ) ) {
            $this->log( 'CSV file not found: ' . $file_path );
            return array(
                'success' => false,
                'message' => __( 'CSV file not found.', 'woo-nalda-sync' ),
            );
        }

        $file_content = file_get_contents( $file_path );
        $filename     = basename( $file_path );

        // Build multipart form data.
        $boundary = wp_generate_uuid4();
        $body     = '';

        // Add text fields - note: csv_type is 'orders' for order status updates.
        $fields = array(
            'license_key'   => $license_key,
            'domain'        => $domain,
            'csv_type'      => 'orders',
            'sftp_host'     => $settings['sftp_host'],
            'sftp_port'     => (string) ( isset( $settings['sftp_port'] ) ? absint( $settings['sftp_port'] ) : 22 ),
            'sftp_username' => $settings['sftp_username'],
            'sftp_password' => $settings['sftp_password'],
        );

        foreach ( $fields as $name => $value ) {
            $body .= "--{$boundary}\r\n";
            $body .= "Content-Disposition: form-data; name=\"{$name}\"\r\n\r\n";
            $body .= "{$value}\r\n";
        }

        // Add CSV file.
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Disposition: form-data; name=\"csv_file\"; filename=\"{$filename}\"\r\n";
        $body .= "Content-Type: text/csv\r\n\r\n";
        $body .= "{$file_content}\r\n";
        $body .= "--{$boundary}--\r\n";

        // Send request to API.
        $api_url = 'https://license-manager-jonakyds.vercel.app/api/v2/nalda/csv-upload';

        $this->log( 'Uploading order status CSV to Nalda API: ' . $filename );

        $response = wp_remote_post( $api_url, array(
            'timeout' => 60,
            'headers' => array(
                'Content-Type' => "multipart/form-data; boundary={$boundary}",
            ),
            'body'    => $body,
        ) );

        if ( is_wp_error( $response ) ) {
            $this->log( 'CSV upload failed: ' . $response->get_error_message() );
            return array(
                'success' => false,
                'message' => sprintf( __( 'Upload failed: %s', 'woo-nalda-sync' ), $response->get_error_message() ),
            );
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        $body        = json_decode( wp_remote_retrieve_body( $response ), true );

        // Check for success response (HTTP 201 for created).
        if ( isset( $body['success'] ) && $body['success'] === true ) {
            $request_id = isset( $body['data']['id'] ) ? $body['data']['id'] : 'N/A';
            $message    = isset( $body['message'] ) ? $body['message'] : __( 'Order status CSV uploaded successfully.', 'woo-nalda-sync' );

            $this->log( 'Order status CSV upload successful. Request ID: ' . $request_id );

            return array(
                'success'    => true,
                'message'    => $message,
                'request_id' => $request_id,
            );
        }

        // Handle error response.
        $error_code    = isset( $body['error']['code'] ) ? $body['error']['code'] : '';
        $error_message = isset( $body['error']['message'] )
            ? $body['error']['message']
            : ( isset( $body['message'] ) ? $body['message'] : __( 'Unknown error occurred.', 'woo-nalda-sync' ) );

        // Map error codes to user-friendly messages.
        $error_messages = array(
            'VALIDATION_ERROR'    => __( 'Invalid parameters or file type.', 'woo-nalda-sync' ),
            'LICENSE_EXPIRED'     => __( 'Your license has expired.', 'woo-nalda-sync' ),
            'LICENSE_REVOKED'     => __( 'Your license has been revoked.', 'woo-nalda-sync' ),
            'DOMAIN_MISMATCH'     => __( 'Domain is not activated for this license.', 'woo-nalda-sync' ),
            'LICENSE_NOT_FOUND'   => __( 'Invalid license key.', 'woo-nalda-sync' ),
            'RATE_LIMIT_EXCEEDED' => __( 'Too many requests. Please wait a few minutes.', 'woo-nalda-sync' ),
            'INTERNAL_ERROR'      => __( 'Server error. Please try again later.', 'woo-nalda-sync' ),
        );

        if ( isset( $error_messages[ $error_code ] ) ) {
            $error_message = $error_messages[ $error_code ];
        }

        $this->log( 'Order status CSV upload failed with status ' . $status_code . ': ' . $error_message . ' (Code: ' . $error_code . ')' );

        return array(
            'success'    => false,
            'message'    => $error_message,
            'error_code' => $error_code,
        );
    }

    /**
     * Update order status export statistics.
     *
     * @param int $order_count Number of orders exported.
     */
    private function update_order_status_export_stats( $order_count ) {
        $stats = get_option( 'woo_nalda_sync_stats', array() );

        $stats['last_order_status_export']   = current_time( 'mysql' );
        $stats['order_statuses_exported']    = $order_count;
        $stats['total_order_status_exports'] = isset( $stats['total_order_status_exports'] ) ? $stats['total_order_status_exports'] + 1 : 1;

        update_option( 'woo_nalda_sync_stats', $stats );
    }

    /**
     * Get order status export sync status.
     *
     * @return array Sync status data.
     */
    public function get_order_status_export_status() {
        $stats = get_option( 'woo_nalda_sync_stats', array() );

        return array(
            'last_sync'       => isset( $stats['last_order_status_export'] ) ? $stats['last_order_status_export'] : null,
            'orders_exported' => isset( $stats['order_statuses_exported'] ) ? $stats['order_statuses_exported'] : 0,
            'total_syncs'     => isset( $stats['total_order_status_exports'] ) ? $stats['total_order_status_exports'] : 0,
        );
    }
}
