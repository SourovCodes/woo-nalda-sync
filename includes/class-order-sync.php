<?php
/**
 * Order Sync Class
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
 * Order Sync class.
 */
class Woo_Nalda_Sync_Order_Sync {

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
        add_action( 'woo_nalda_sync_order_sync', array( $this, 'run_scheduled_sync' ) );
    }

    /**
     * Run scheduled sync.
     */
    public function run_scheduled_sync() {
        $settings = woo_nalda_sync()->get_setting();

        // Check if sync is enabled.
        if ( isset( $settings['order_sync_enabled'] ) && 'yes' !== $settings['order_sync_enabled'] ) {
            $this->log( 'Order sync is disabled. Skipping scheduled sync.' );
            return;
        }

        // Check license.
        if ( ! $this->license_manager->is_valid() ) {
            $this->log( 'License is not valid. Skipping scheduled sync.' );
            return;
        }

        // Get the configured import range.
        $range = isset( $settings['order_import_range'] ) ? $settings['order_import_range'] : 'today';

        $this->run_sync( $range );
    }

    /**
     * Run order sync.
     *
     * @param string $range Date range for orders (default: 'today').
     * @return array Result with success status and message.
     */
    public function run_sync( $range = 'today' ) {
        $start_time = microtime( true );
        $this->log( 'Starting order sync...' );

        // Check license.
        if ( ! $this->license_manager->is_valid() ) {
            return array(
                'success' => false,
                'message' => __( 'License is not valid.', 'woo-nalda-sync' ),
            );
        }

        $settings = woo_nalda_sync()->get_setting();

        // Validate Nalda API settings.
        if ( empty( $settings['nalda_api_key'] ) ) {
            $this->log( 'Nalda API key is not configured.' );
            return array(
                'success' => false,
                'message' => __( 'Nalda API key is not configured.', 'woo-nalda-sync' ),
            );
        }

        // Get orders from Nalda API.
        $orders_result = $this->fetch_orders( $range );

        if ( ! $orders_result['success'] ) {
            return $orders_result;
        }

        $orders = $orders_result['orders'];

        if ( empty( $orders ) ) {
            $this->log( 'No new orders found.' );
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
            'Order sync completed in %s seconds. Created: %d, Updated: %d, Skipped: %d',
            $duration,
            $orders_created,
            $orders_updated,
            $orders_skipped
        ) );

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

        if ( $status_code !== 200 || ! isset( $body['success'] ) || ! $body['success'] ) {
            $error_message = isset( $body['message'] ) ? $body['message'] : __( 'Unknown error occurred.', 'woo-nalda-sync' );
            $this->log( 'Failed to fetch orders: ' . $error_message );
            return array(
                'success' => false,
                'message' => $error_message,
            );
        }

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
            $order = wc_create_order( array(
                'status'        => 'processing',
                'customer_note' => sprintf( __( 'Imported from Nalda Marketplace (Order #%d)', 'woo-nalda-sync' ), $nalda_order['orderId'] ),
            ) );

            if ( is_wp_error( $order ) ) {
                $this->log( 'Failed to create order: ' . $order->get_error_message() );
                return false;
            }

            // Set billing address.
            $order->set_billing_first_name( $nalda_order['firstName'] );
            $order->set_billing_last_name( $nalda_order['lastName'] );
            $order->set_billing_email( $nalda_order['email'] );
            $order->set_billing_address_1( $nalda_order['street1'] );
            $order->set_billing_city( $nalda_order['city'] );
            $order->set_billing_postcode( $nalda_order['postalCode'] );
            $order->set_billing_country( $nalda_order['country'] );

            // Set shipping address (same as billing).
            $order->set_shipping_first_name( $nalda_order['firstName'] );
            $order->set_shipping_last_name( $nalda_order['lastName'] );
            $order->set_shipping_address_1( $nalda_order['street1'] );
            $order->set_shipping_city( $nalda_order['city'] );
            $order->set_shipping_postcode( $nalda_order['postalCode'] );
            $order->set_shipping_country( $nalda_order['country'] );

            // Add order items.
            foreach ( $order_items as $item ) {
                $this->add_order_item( $order, $item );
            }

            // Set order date.
            if ( isset( $nalda_order['createdAt'] ) ) {
                $order->set_date_created( strtotime( $nalda_order['createdAt'] ) );
            }

            // Set currency.
            if ( isset( $order_items[0]['currency'] ) ) {
                $order->set_currency( $order_items[0]['currency'] );
            }

            // Calculate totals.
            $order->calculate_totals();

            // Set Nalda metadata.
            $order->update_meta_data( '_nalda_order_id', $nalda_order['orderId'] );
            $order->update_meta_data( '_nalda_payout_status', isset( $nalda_order['payoutStatus'] ) ? $nalda_order['payoutStatus'] : '' );
            $order->update_meta_data( '_nalda_fee', isset( $nalda_order['fee'] ) ? $nalda_order['fee'] : 0 );
            $order->update_meta_data( '_nalda_commission', isset( $nalda_order['commission'] ) ? $nalda_order['commission'] : 0 );
            $order->update_meta_data( '_nalda_imported_at', current_time( 'mysql' ) );

            // Add collection info if available.
            if ( ! empty( $nalda_order['collectionId'] ) ) {
                $order->update_meta_data( '_nalda_collection_id', $nalda_order['collectionId'] );
                $order->update_meta_data( '_nalda_collection_name', $nalda_order['collectionName'] );
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

        if ( $product ) {
            // Add existing product.
            $item = new WC_Order_Item_Product();
            $item->set_product( $product );
            $item->set_quantity( $nalda_item['quantity'] );
            $item->set_subtotal( $nalda_item['price'] * $nalda_item['quantity'] );
            $item->set_total( $nalda_item['price'] * $nalda_item['quantity'] );
        } else {
            // Create custom line item.
            $item = new WC_Order_Item_Product();
            $item->set_name( $nalda_item['title'] );
            $item->set_quantity( $nalda_item['quantity'] );
            $item->set_subtotal( $nalda_item['price'] * $nalda_item['quantity'] );
            $item->set_total( $nalda_item['price'] * $nalda_item['quantity'] );
        }

        // Add Nalda item metadata.
        $item->add_meta_data( '_nalda_gtin', $nalda_item['gtin'], true );
        $item->add_meta_data( '_nalda_condition', isset( $nalda_item['condition'] ) ? $nalda_item['condition'] : '', true );
        $item->add_meta_data( '_nalda_delivery_status', isset( $nalda_item['deliveryStatus'] ) ? $nalda_item['deliveryStatus'] : '', true );

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

        // Update payout status if changed.
        $current_payout_status = $order->get_meta( '_nalda_payout_status' );
        $new_payout_status     = isset( $nalda_order['payoutStatus'] ) ? $nalda_order['payoutStatus'] : '';

        if ( $current_payout_status !== $new_payout_status ) {
            $order->update_meta_data( '_nalda_payout_status', $new_payout_status );
            $order->add_order_note(
                sprintf(
                    __( 'Nalda payout status updated: %s → %s', 'woo-nalda-sync' ),
                    $current_payout_status,
                    $new_payout_status
                ),
                false,
                true
            );
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
     * Update sync statistics.
     *
     * @param int $order_count Number of orders synced.
     */
    private function update_sync_stats( $order_count ) {
        $stats = get_option( 'woo_nalda_sync_stats', array() );

        $stats['last_order_sync']   = current_time( 'mysql' );
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
            'last_sync'     => isset( $stats['last_order_sync'] ) ? $stats['last_order_sync'] : null,
            'orders_synced' => isset( $stats['orders_synced'] ) ? $stats['orders_synced'] : 0,
            'total_syncs'   => isset( $stats['total_order_syncs'] ) ? $stats['total_order_syncs'] : 0,
        );
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
}
