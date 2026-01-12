<?php
/**
 * Product Sync Class
 *
 * Handles product CSV generation and SFTP upload via Nalda API.
 *
 * @package Woo_Nalda_Sync
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Product Sync class.
 */
class Woo_Nalda_Sync_Product_Sync {

    /**
     * CSV columns in the required order.
     *
     * @var array
     */
    private $csv_columns = array(
        'gtin',
        'title',
        'country',
        'condition',
        'price',
        'tax',
        'currency',
        'delivery_time_days',
        'stock',
        'return_days',
        'main_image_url',
        'brand',
        'category',
        'google_category',
        'seller_category',
        'description',
        'length_mm',
        'width_mm',
        'height_mm',
        'weight_g',
        'shipping_length_mm',
        'shipping_width_mm',
        'shipping_height_mm',
        'shipping_weight_g',
        'volume_ml',
        'size',
        'colour',
        'image_2_url',
        'image_3_url',
        'image_4_url',
        'image_5_url',
        'delete_product',
        'author',
        'language',
        'format',
        'year',
        'publisher',
    );

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
        add_action( 'woo_nalda_sync_product_sync', array( $this, 'run_scheduled_sync' ) );
    }

    /**
     * Check if a product should be synced based on per-product settings.
     *
     * @param int $product_id Product ID.
     * @return bool Whether the product should be synced.
     */
    private function should_sync_product( $product_id ) {
        $meta_value = get_post_meta( $product_id, '_nalda_sync_enabled', true );

        // If explicitly set, use that value.
        if ( 'yes' === $meta_value ) {
            return true;
        }

        if ( 'no' === $meta_value ) {
            return false;
        }

        // Otherwise, use the default mode from settings.
        $settings     = woo_nalda_sync()->get_setting();
        $default_mode = isset( $settings['sync_default_mode'] ) ? $settings['sync_default_mode'] : 'include_all';

        // 'include_all' means sync by default, 'exclude_all' means don't sync by default.
        return 'include_all' === $default_mode;
    }

    /**
     * Run scheduled sync.
     */
    public function run_scheduled_sync() {
        $settings = woo_nalda_sync()->get_setting();

        // Check if sync is enabled.
        if ( isset( $settings['product_sync_enabled'] ) && 'yes' !== $settings['product_sync_enabled'] ) {
            $this->log( 'Product sync is disabled. Skipping scheduled sync.' );
            return;
        }

        // Check license.
        if ( ! $this->license_manager->is_valid() ) {
            $this->log( 'License is not valid. Skipping scheduled sync.' );
            woo_nalda_sync_logger()->log_product_export(
                Woo_Nalda_Sync_Logger::TRIGGER_AUTOMATIC,
                Woo_Nalda_Sync_Logger::STATUS_ERROR,
                __( 'License is not valid', 'woo-nalda-sync' )
            );
            return;
        }

        $this->run_sync( Woo_Nalda_Sync_Logger::TRIGGER_AUTOMATIC );

        // Ensure the cron event is still scheduled after sync completes.
        // This prevents the schedule from being lost if there was an issue.
        $this->ensure_cron_scheduled();
    }

    /**
     * Ensure product sync cron event is scheduled.
     * This is a safety measure to prevent lost schedules.
     */
    private function ensure_cron_scheduled() {
        $settings = woo_nalda_sync()->get_setting();

        // Only reschedule if product sync is enabled.
        if ( empty( $settings['product_sync_enabled'] ) || 'yes' !== $settings['product_sync_enabled'] ) {
            return;
        }

        // Check if cron is already scheduled.
        $next_scheduled = wp_next_scheduled( 'woo_nalda_sync_product_sync' );

        if ( ! $next_scheduled ) {
            // Cron is not scheduled, reschedule it.
            $recurrence = ! empty( $settings['product_sync_schedule'] ) ? $settings['product_sync_schedule'] : 'hourly';
            $timestamp  = time() + ( 2 * MINUTE_IN_SECONDS );
            wp_schedule_event( $timestamp, $recurrence, 'woo_nalda_sync_product_sync' );
            $this->log( 'Product sync cron was not scheduled. Rescheduled for ' . gmdate( 'Y-m-d H:i:s', $timestamp ) );
        }
    }

    /**
     * Run product sync.
     *
     * @param string $trigger Trigger type (manual or automatic). Default: manual.
     * @return array Result with success status and message.
     */
    public function run_sync( $trigger = 'manual' ) {
        $start_time = microtime( true );
        $this->log( 'Starting product sync...' );

        // Check license.
        if ( ! $this->license_manager->is_valid() ) {
            woo_nalda_sync_logger()->log_product_export(
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
            woo_nalda_sync_logger()->log_product_export(
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
        $csv_result = $this->generate_csv();

        if ( ! $csv_result['success'] ) {
            woo_nalda_sync_logger()->log_product_export(
                $trigger,
                Woo_Nalda_Sync_Logger::STATUS_ERROR,
                $csv_result['message']
            );
            return $csv_result;
        }

        // Upload CSV via API.
        $upload_result = $this->upload_csv( $csv_result['file_path'] );

        // Clean up temporary file.
        if ( file_exists( $csv_result['file_path'] ) ) {
            unlink( $csv_result['file_path'] );
        }

        if ( ! $upload_result['success'] ) {
            woo_nalda_sync_logger()->log_product_export(
                $trigger,
                Woo_Nalda_Sync_Logger::STATUS_ERROR,
                $upload_result['message']
            );
            return $upload_result;
        }

        $duration = round( microtime( true ) - $start_time, 2 );

        // Update sync stats.
        $this->update_sync_stats( $csv_result['product_count'] );

        $this->log( sprintf( 'Product sync completed successfully in %s seconds. %d products exported.', $duration, $csv_result['product_count'] ) );

        // Log success.
        $summary = sprintf(
            __( 'Exported %d products in %s seconds', 'woo-nalda-sync' ),
            $csv_result['product_count'],
            $duration
        );
        woo_nalda_sync_logger()->log_product_export(
            $trigger,
            Woo_Nalda_Sync_Logger::STATUS_SUCCESS,
            $summary,
            array(
                'product_count' => $csv_result['product_count'],
                'duration'      => $duration,
            )
        );

        return array(
            'success'       => true,
            'message'       => sprintf(
                __( 'Successfully exported %d products in %s seconds.', 'woo-nalda-sync' ),
                $csv_result['product_count'],
                $duration
            ),
            'product_count' => $csv_result['product_count'],
            'duration'      => $duration,
        );
    }

    /**
     * Generate CSV file.
     *
     * @return array Result with file path and product count.
     */
    private function generate_csv() {
        $settings   = woo_nalda_sync()->get_setting();
        $batch_size = isset( $settings['batch_size'] ) ? absint( $settings['batch_size'] ) : 100;

        // Get filename.
        $filename = $this->get_filename();
        
        // Use WordPress temp directory to avoid permission issues with cron.
        $temp_dir = get_temp_dir();
        $file_path = trailingslashit( $temp_dir ) . $filename;

        // If temp directory is not writable, fallback to uploads directory.
        if ( ! wp_is_writable( $temp_dir ) ) {
            $upload_dir = wp_upload_dir();
            $file_path = trailingslashit( $upload_dir['basedir'] ) . 'woo-nalda-sync/' . $filename;
            wp_mkdir_p( dirname( $file_path ) );
        }

        $this->log( sprintf( 'Creating CSV file at: %s', $file_path ) );

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
        fputcsv( $file, $this->csv_columns );

        // Get products in batches.
        $page          = 1;
        $product_count = 0;

        do {
            $args = array(
                'status'   => 'publish',
                'limit'    => $batch_size,
                'page'     => $page,
                'orderby'  => 'ID',
                'order'    => 'ASC',
            );

            $products = wc_get_products( $args );

            if ( empty( $products ) ) {
                break;
            }

            foreach ( $products as $product ) {
                // Check if product should be synced based on per-product settings.
                if ( ! $this->should_sync_product( $product->get_id() ) ) {
                    continue;
                }

                $row = $this->get_product_row( $product );
                
                if ( $row ) {
                    fputcsv( $file, $row );
                    $product_count++;
                }

                // Handle variable products.
                if ( $product->is_type( 'variable' ) ) {
                    $variations = $product->get_available_variations();
                    
                    foreach ( $variations as $variation_data ) {
                        $variation = wc_get_product( $variation_data['variation_id'] );
                        
                        if ( $variation ) {
                            $row = $this->get_product_row( $variation, $product );
                            
                            if ( $row ) {
                                fputcsv( $file, $row );
                                $product_count++;
                            }
                        }
                    }
                }
            }

            $page++;

        } while ( count( $products ) === $batch_size );

        fclose( $file );

        $this->log( sprintf( 'Generated CSV with %d products.', $product_count ) );

        return array(
            'success'       => true,
            'file_path'     => $file_path,
            'product_count' => $product_count,
        );
    }

    /**
     * Get product row data.
     *
     * @param WC_Product      $product        Product object.
     * @param WC_Product|null $parent_product Parent product for variations.
     * @return array|false Product row data or false if no GTIN.
     */
    private function get_product_row( $product, $parent_product = null ) {
        $settings = woo_nalda_sync()->get_setting();

        // Get GTIN (EAN/ISBN/UPC).
        $gtin = $this->get_product_gtin( $product );

        if ( empty( $gtin ) && $parent_product ) {
            $gtin = $this->get_product_gtin( $parent_product );
        }

        // Skip products without GTIN.
        if ( empty( $gtin ) ) {
            return false;
        }

        // Get WooCommerce settings.
        $country  = isset( $settings['wc_country'] ) ? $settings['wc_country'] : WC()->countries->get_base_country();
        $currency = get_woocommerce_currency();
        $tax_rate = $this->get_tax_rate( $product );

        // Get product data.
        $title       = $product->get_name();
        $description = $product->get_description();
        $price       = $product->get_regular_price();
        $stock       = $product->get_stock_quantity();

        // Use parent description if variation has none.
        if ( empty( $description ) && $parent_product ) {
            $description = $parent_product->get_description();
        }

        // Get images.
        $images    = $this->get_product_images( $product, $parent_product );
        $main_image = ! empty( $images ) ? array_shift( $images ) : '';

        // Get dimensions.
        $length = $product->get_length();
        $width  = $product->get_width();
        $height = $product->get_height();
        $weight = $product->get_weight();

        // Convert to mm and g if needed.
        $dimension_unit = get_option( 'woocommerce_dimension_unit' );
        $weight_unit    = get_option( 'woocommerce_weight_unit' );

        $length_mm = $this->convert_to_mm( $length, $dimension_unit );
        $width_mm  = $this->convert_to_mm( $width, $dimension_unit );
        $height_mm = $this->convert_to_mm( $height, $dimension_unit );
        $weight_g  = $this->convert_to_g( $weight, $weight_unit );

        // Get default values from settings.
        $delivery_time = isset( $settings['default_delivery_time'] ) ? absint( $settings['default_delivery_time'] ) : 3;
        $return_days   = isset( $settings['return_period'] ) ? absint( $settings['return_period'] ) : 14;

        // Check for product-specific delivery time.
        $product_delivery_time = $product->get_meta( '_nalda_delivery_time' );
        if ( ! empty( $product_delivery_time ) ) {
            $delivery_time = absint( $product_delivery_time );
        }

        // Get condition.
        $condition = $product->get_meta( '_nalda_condition' );
        if ( empty( $condition ) ) {
            $condition = 'new';
        }

        // Get brand.
        $brand = $this->get_product_brand( $product, $parent_product );

        // Get categories.
        $categories = $this->get_product_categories( $product, $parent_product );

        // Get Google category.
        $google_category = $product->get_meta( '_nalda_google_category' );
        if ( empty( $google_category ) && $parent_product ) {
            $google_category = $parent_product->get_meta( '_nalda_google_category' );
        }

        // Get attributes for size and colour.
        $size   = $this->get_product_attribute( $product, 'size' );
        $colour = $this->get_product_attribute( $product, array( 'colour', 'color' ) );

        // Book-specific fields.
        $author    = $product->get_meta( '_nalda_author' );
        $language  = $product->get_meta( '_nalda_language' );
        $format    = $product->get_meta( '_nalda_format' );
        $year      = $product->get_meta( '_nalda_year' );
        $publisher = $product->get_meta( '_nalda_publisher' );

        // Check if product should be deleted (not in stock or not published).
        $delete_product = '';
        if ( $product->get_status() === 'trash' || ( $stock !== null && $stock <= 0 && ! $product->backorders_allowed() ) ) {
            $delete_product = 'yes';
        }

        // Build row.
        $row = array(
            'gtin'                => $gtin,
            'title'               => $this->sanitize_csv_value( $title ),
            'country'             => $country,
            'condition'           => $condition,
            'price'               => number_format( (float) $price, 2, '.', '' ),
            'tax'                 => number_format( $tax_rate, 1, '.', '' ),
            'currency'            => $currency,
            'delivery_time_days'  => $delivery_time,
            'stock'               => $stock !== null ? $stock : 0,
            'return_days'         => $return_days,
            'main_image_url'      => $main_image,
            'brand'               => $this->sanitize_csv_value( $brand ),
            'category'            => $this->sanitize_csv_value( $categories ),
            'google_category'     => $this->sanitize_csv_value( $google_category ),
            'seller_category'     => '',
            'description'         => $this->sanitize_csv_value( $description ),
            'length_mm'           => $length_mm,
            'width_mm'            => $width_mm,
            'height_mm'           => $height_mm,
            'weight_g'            => $weight_g,
            'shipping_length_mm'  => $length_mm ? round( $length_mm * 1.1 ) : '',
            'shipping_width_mm'   => $width_mm ? round( $width_mm * 1.1 ) : '',
            'shipping_height_mm'  => $height_mm ? round( $height_mm * 1.1 ) : '',
            'shipping_weight_g'   => $weight_g ? round( $weight_g * 1.1, 1 ) : '',
            'volume_ml'           => '',
            'size'                => $this->sanitize_csv_value( $size ),
            'colour'              => $this->sanitize_csv_value( $colour ),
            'image_2_url'         => isset( $images[0] ) ? $images[0] : '',
            'image_3_url'         => isset( $images[1] ) ? $images[1] : '',
            'image_4_url'         => isset( $images[2] ) ? $images[2] : '',
            'image_5_url'         => isset( $images[3] ) ? $images[3] : '',
            'delete_product'      => $delete_product,
            'author'              => $this->sanitize_csv_value( $author ),
            'language'            => $this->sanitize_csv_value( $language ),
            'format'              => $this->sanitize_csv_value( $format ),
            'year'                => $year,
            'publisher'           => $this->sanitize_csv_value( $publisher ),
        );

        return array_values( $row );
    }

    /**
     * Get product GTIN (EAN, ISBN, UPC, etc.)
     *
     * @param WC_Product $product Product object.
     * @return string GTIN value or empty string.
     */
    private function get_product_gtin( $product ) {
        // First, check WooCommerce's built-in global unique ID (GTIN) field.
        // Use the getter method to avoid "is_internal_meta_key" warning.
        if ( method_exists( $product, 'get_global_unique_id' ) ) {
            $global_id = $product->get_global_unique_id();
            if ( ! empty( $global_id ) ) {
                return $global_id;
            }
        }

        // Check common meta keys for GTIN.
        $gtin_keys = array(
            '_gtin',
            '_ean',
            '_isbn',
            '_upc',
            'gtin',
            'ean',
            'isbn',
            'upc',
            '_barcode',
            'barcode',
        );

        foreach ( $gtin_keys as $key ) {
            $value = $product->get_meta( $key );
            if ( ! empty( $value ) ) {
                return $value;
            }
        }

        // Check for WooCommerce product identifier.
        $sku = $product->get_sku();
        
        // If SKU looks like a GTIN (numeric and correct length), use it.
        if ( ! empty( $sku ) && preg_match( '/^\d{8,14}$/', $sku ) ) {
            return $sku;
        }

        return '';
    }

    /**
     * Get product images.
     *
     * @param WC_Product      $product        Product object.
     * @param WC_Product|null $parent_product Parent product for variations.
     * @return array Array of image URLs.
     */
    private function get_product_images( $product, $parent_product = null ) {
        $images = array();

        // Get main image.
        $main_image_id = $product->get_image_id();
        if ( $main_image_id ) {
            $images[] = wp_get_attachment_url( $main_image_id );
        }

        // Get gallery images.
        $gallery_ids = $product->get_gallery_image_ids();
        foreach ( $gallery_ids as $gallery_id ) {
            $images[] = wp_get_attachment_url( $gallery_id );
        }

        // If variation has no images, use parent images.
        if ( empty( $images ) && $parent_product ) {
            $main_image_id = $parent_product->get_image_id();
            if ( $main_image_id ) {
                $images[] = wp_get_attachment_url( $main_image_id );
            }

            $gallery_ids = $parent_product->get_gallery_image_ids();
            foreach ( $gallery_ids as $gallery_id ) {
                $images[] = wp_get_attachment_url( $gallery_id );
            }
        }

        return array_filter( $images );
    }

    /**
     * Get product brand.
     *
     * @param WC_Product      $product        Product object.
     * @param WC_Product|null $parent_product Parent product for variations.
     * @return string Brand name.
     */
    private function get_product_brand( $product, $parent_product = null ) {
        // Check meta fields.
        $brand_keys = array( '_brand', 'brand', '_manufacturer', 'manufacturer' );

        foreach ( $brand_keys as $key ) {
            $value = $product->get_meta( $key );
            if ( ! empty( $value ) ) {
                return $value;
            }
        }

        // Check parent product.
        if ( $parent_product ) {
            foreach ( $brand_keys as $key ) {
                $value = $parent_product->get_meta( $key );
                if ( ! empty( $value ) ) {
                    return $value;
                }
            }
        }

        // Check taxonomies.
        $brand_taxonomies = array( 'product_brand', 'pa_brand', 'brand' );
        $product_id = $parent_product ? $parent_product->get_id() : $product->get_id();

        foreach ( $brand_taxonomies as $taxonomy ) {
            $terms = get_the_terms( $product_id, $taxonomy );
            if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
                return $terms[0]->name;
            }
        }

        return '';
    }

    /**
     * Get product categories.
     *
     * @param WC_Product      $product        Product object.
     * @param WC_Product|null $parent_product Parent product for variations.
     * @return string Categories string.
     */
    private function get_product_categories( $product, $parent_product = null ) {
        $product_id = $parent_product ? $parent_product->get_id() : $product->get_id();
        $terms = get_the_terms( $product_id, 'product_cat' );

        if ( empty( $terms ) || is_wp_error( $terms ) ) {
            return '';
        }

        // Build category path (e.g., "Parent > Child > Grandchild").
        $deepest_term = null;
        $max_depth = -1;

        foreach ( $terms as $term ) {
            $depth = 0;
            $parent = $term->parent;
            
            while ( $parent > 0 ) {
                $depth++;
                $parent_term = get_term( $parent, 'product_cat' );
                $parent = $parent_term ? $parent_term->parent : 0;
            }

            if ( $depth > $max_depth ) {
                $max_depth = $depth;
                $deepest_term = $term;
            }
        }

        if ( ! $deepest_term ) {
            return '';
        }

        // Build full path.
        $path = array( $deepest_term->name );
        $parent = $deepest_term->parent;

        while ( $parent > 0 ) {
            $parent_term = get_term( $parent, 'product_cat' );
            if ( $parent_term ) {
                array_unshift( $path, $parent_term->name );
                $parent = $parent_term->parent;
            } else {
                break;
            }
        }

        return implode( ' > ', $path );
    }

    /**
     * Get product attribute value.
     *
     * @param WC_Product    $product        Product object.
     * @param string|array  $attribute_name Attribute name(s) to look for.
     * @return string Attribute value.
     */
    private function get_product_attribute( $product, $attribute_name ) {
        $names = is_array( $attribute_name ) ? $attribute_name : array( $attribute_name );

        foreach ( $names as $name ) {
            // Try as global attribute.
            $value = $product->get_attribute( 'pa_' . $name );
            if ( ! empty( $value ) ) {
                return $value;
            }

            // Try as local attribute.
            $value = $product->get_attribute( $name );
            if ( ! empty( $value ) ) {
                return $value;
            }
        }

        return '';
    }

    /**
     * Get tax rate for product.
     *
     * @param WC_Product $product Product object.
     * @return float Tax rate percentage.
     */
    private function get_tax_rate( $product ) {
        if ( ! wc_tax_enabled() ) {
            return 0;
        }

        $tax_class = $product->get_tax_class();
        $tax_rates = WC_Tax::get_base_tax_rates( $tax_class );

        if ( empty( $tax_rates ) ) {
            return 0;
        }

        $rate = array_shift( $tax_rates );
        return isset( $rate['rate'] ) ? (float) $rate['rate'] : 0;
    }

    /**
     * Convert dimension to mm.
     *
     * @param float  $value Value to convert.
     * @param string $unit  Current unit.
     * @return int Value in mm or empty string.
     */
    private function convert_to_mm( $value, $unit ) {
        if ( empty( $value ) ) {
            return '';
        }

        $value = (float) $value;

        switch ( $unit ) {
            case 'm':
                return round( $value * 1000 );
            case 'cm':
                return round( $value * 10 );
            case 'mm':
                return round( $value );
            case 'in':
                return round( $value * 25.4 );
            case 'yd':
                return round( $value * 914.4 );
            default:
                return round( $value );
        }
    }

    /**
     * Convert weight to grams.
     *
     * @param float  $value Value to convert.
     * @param string $unit  Current unit.
     * @return float Value in grams or empty string.
     */
    private function convert_to_g( $value, $unit ) {
        if ( empty( $value ) ) {
            return '';
        }

        $value = (float) $value;

        switch ( $unit ) {
            case 'kg':
                return round( $value * 1000, 1 );
            case 'g':
                return round( $value, 1 );
            case 'lbs':
                return round( $value * 453.592, 1 );
            case 'oz':
                return round( $value * 28.3495, 1 );
            default:
                return round( $value, 1 );
        }
    }

    /**
     * Sanitize CSV value.
     *
     * @param string $value Value to sanitize.
     * @return string Sanitized value.
     */
    private function sanitize_csv_value( $value ) {
        if ( empty( $value ) ) {
            return '';
        }

        // Strip HTML tags.
        $value = wp_strip_all_tags( $value );

        // Convert special characters.
        $value = html_entity_decode( $value, ENT_QUOTES, 'UTF-8' );

        // Remove excess whitespace.
        $value = preg_replace( '/\s+/', ' ', $value );

        return trim( $value );
    }

    /**
     * Get CSV filename.
     *
     * @return string Filename.
     */
    private function get_filename() {
        $settings = woo_nalda_sync()->get_setting();
        $pattern  = isset( $settings['filename_pattern'] ) ? $settings['filename_pattern'] : 'products_{date}.csv';

        $replacements = array(
            '{date}'      => wp_date( 'Y-m-d' ),
            '{datetime}'  => wp_date( 'Y-m-d_H-i-s' ),
            '{timestamp}' => time(),
        );

        return str_replace( array_keys( $replacements ), array_values( $replacements ), $pattern );
    }

    /**
     * Upload CSV via Nalda API.
     *
     * Uses multipart/form-data to upload the CSV file directly to the API.
     *
     * @param string $file_path Path to CSV file.
     * @return array Result with success status and message.
     */
    private function upload_csv( $file_path ) {
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

        // Add text fields.
        $fields = array(
            'license_key'   => $license_key,
            'domain'        => $domain,
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

        $this->log( 'Uploading CSV to Nalda API: ' . $filename );

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
            $request_id   = isset( $body['data']['id'] ) ? $body['data']['id'] : 'N/A';
            $file_key     = isset( $body['data']['csv_file_key'] ) ? $body['data']['csv_file_key'] : '';
            $message      = isset( $body['message'] ) ? $body['message'] : __( 'CSV uploaded successfully.', 'woo-nalda-sync' );

            $this->log( 'CSV upload successful. Request ID: ' . $request_id );

            return array(
                'success'    => true,
                'message'    => $message,
                'request_id' => $request_id,
                'file_key'   => $file_key,
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

        $this->log( 'CSV upload failed with status ' . $status_code . ': ' . $error_message . ' (Code: ' . $error_code . ')' );

        return array(
            'success'    => false,
            'message'    => $error_message,
            'error_code' => $error_code,
        );
    }

    /**
     * Validate SFTP credentials via Nalda API.
     *
     * @param array $credentials SFTP credentials.
     * @return array Result with success status and message.
     */
    public function validate_sftp_credentials( $credentials ) {
        $license_key = $this->license_manager->get_license_key();

        if ( empty( $license_key ) ) {
            return array(
                'success' => false,
                'message' => __( 'License key is required.', 'woo-nalda-sync' ),
            );
        }

        // Use the new Nalda API v2 endpoint.
        $api_url = 'https://license-manager-jonakyds.vercel.app/api/v2/nalda/sftp-validate';

        $response = wp_remote_post( $api_url, array(
            'timeout' => 30,
            'headers' => array(
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json',
            ),
            'body'    => wp_json_encode( array(
                'license_key' => $license_key,
                'domain'      => $this->license_manager->get_domain(),
                'hostname'    => $credentials['sftp_host'],
                'port'        => isset( $credentials['sftp_port'] ) ? absint( $credentials['sftp_port'] ) : 22,
                'username'    => $credentials['sftp_username'],
                'password'    => $credentials['sftp_password'],
            ) ),
        ) );

        if ( is_wp_error( $response ) ) {
            return array(
                'success' => false,
                'message' => sprintf( __( 'Connection failed: %s', 'woo-nalda-sync' ), $response->get_error_message() ),
            );
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        $body        = json_decode( wp_remote_retrieve_body( $response ), true );

        // Check for success response.
        if ( isset( $body['success'] ) && $body['success'] === true ) {
            $server_info = isset( $body['data']['serverInfo'] ) ? $body['data']['serverInfo'] : array();
            return array(
                'success'     => true,
                'message'     => __( 'SFTP credentials are valid.', 'woo-nalda-sync' ),
                'server_info' => $server_info,
            );
        }

        // Handle error response.
        $error_code    = isset( $body['error']['code'] ) ? $body['error']['code'] : '';
        $error_message = isset( $body['error']['message'] ) ? $body['error']['message'] : __( 'Validation failed.', 'woo-nalda-sync' );

        // Map error codes to user-friendly messages.
        $error_messages = array(
            'AUTH_FAILED'         => __( 'Invalid SFTP username or password.', 'woo-nalda-sync' ),
            'HOST_NOT_FOUND'      => __( 'SFTP server hostname not found.', 'woo-nalda-sync' ),
            'CONNECTION_TIMEOUT'  => __( 'Connection timed out. Please check the hostname and port.', 'woo-nalda-sync' ),
            'CONNECTION_REFUSED'  => __( 'Connection refused by the SFTP server.', 'woo-nalda-sync' ),
            'HOST_UNREACHABLE'    => __( 'SFTP server is unreachable.', 'woo-nalda-sync' ),
            'LICENSE_NOT_FOUND'   => __( 'Invalid license key.', 'woo-nalda-sync' ),
            'LICENSE_EXPIRED'     => __( 'Your license has expired.', 'woo-nalda-sync' ),
            'DOMAIN_MISMATCH'     => __( 'Domain is not activated for this license.', 'woo-nalda-sync' ),
            'RATE_LIMIT_EXCEEDED' => __( 'Too many requests. Please wait a few minutes.', 'woo-nalda-sync' ),
        );

        if ( isset( $error_messages[ $error_code ] ) ) {
            $error_message = $error_messages[ $error_code ];
        }

        return array(
            'success'    => false,
            'message'    => $error_message,
            'error_code' => $error_code,
        );
    }

    /**
     * Update sync statistics.
     *
     * @param int $product_count Number of products synced.
     */
    private function update_sync_stats( $product_count ) {
        $stats = get_option( 'woo_nalda_sync_stats', array() );

        $stats['last_product_sync']   = current_time( 'mysql' );
        $stats['products_synced']     = $product_count;
        $stats['total_product_syncs'] = isset( $stats['total_product_syncs'] ) ? $stats['total_product_syncs'] + 1 : 1;

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
                $logger->info( $message, array( 'source' => 'woo-nalda-sync-products' ) );
            } else {
                error_log( '[WooCommerce Nalda Sync - Products] ' . $message );
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
            'last_sync'       => isset( $stats['last_product_sync'] ) ? $stats['last_product_sync'] : null,
            'products_synced' => isset( $stats['products_synced'] ) ? $stats['products_synced'] : 0,
            'total_syncs'     => isset( $stats['total_product_syncs'] ) ? $stats['total_product_syncs'] : 0,
        );
    }

    /**
     * Get CSV upload history from API.
     *
     * @param int    $per_page Number of results per page.
     * @param int    $page     Page number.
     * @param string $status   Optional status filter (pending, processing, processed, failed).
     * @return array Result with uploads data or error message.
     */
    public function get_upload_history( $per_page = 10, $page = 1, $status = '' ) {
        $license_key = $this->license_manager->get_license_key();

        if ( empty( $license_key ) ) {
            return array(
                'success' => false,
                'message' => __( 'License key is required.', 'woo-nalda-sync' ),
            );
        }

        // Use the new Nalda API v2 endpoint.
        $api_url = 'https://license-manager-jonakyds.vercel.app/api/v2/nalda/csv-upload/list';

        $query_args = array(
            'license_key' => $license_key,
            'domain'      => $this->license_manager->get_domain(),
            'page'        => absint( $page ),
            'limit'       => absint( $per_page ),
        );

        if ( ! empty( $status ) ) {
            $query_args['status'] = sanitize_text_field( $status );
        }

        $response = wp_remote_get( add_query_arg( $query_args, $api_url ), array(
            'timeout' => 30,
            'headers' => array(
                'Accept' => 'application/json',
            ),
        ) );

        if ( is_wp_error( $response ) ) {
            $this->log( 'Failed to fetch upload history: ' . $response->get_error_message() );
            return array(
                'success' => false,
                'message' => sprintf( __( 'Failed to fetch upload history: %s', 'woo-nalda-sync' ), $response->get_error_message() ),
            );
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( isset( $body['success'] ) && $body['success'] === true ) {
            return array(
                'success'    => true,
                'data'       => isset( $body['data']['requests'] ) ? $body['data']['requests'] : array(),
                'pagination' => isset( $body['data']['pagination'] ) ? $body['data']['pagination'] : array(),
            );
        }

        // Handle error response.
        $error_message = isset( $body['error']['message'] ) 
            ? $body['error']['message'] 
            : ( isset( $body['message'] ) ? $body['message'] : __( 'Unknown error occurred.', 'woo-nalda-sync' ) );
        
        $this->log( 'Failed to fetch upload history: ' . $error_message );

        return array(
            'success' => false,
            'message' => $error_message,
        );
    }

    /**
     * Get status label for CSV upload.
     *
     * @param string $status Status key.
     * @return string Localized status label.
     */
    public static function get_upload_status_label( $status ) {
        $labels = array(
            'pending'    => __( 'Pending', 'woo-nalda-sync' ),
            'processing' => __( 'Processing', 'woo-nalda-sync' ),
            'processed'  => __( 'Processed', 'woo-nalda-sync' ),
            'failed'     => __( 'Failed', 'woo-nalda-sync' ),
        );

        return isset( $labels[ $status ] ) ? $labels[ $status ] : $status;
    }

    /**
     * Get CSS class for upload status badge.
     *
     * @param string $status Status key.
     * @return string CSS class suffix.
     */
    public static function get_upload_status_class( $status ) {
        $classes = array(
            'pending'    => 'warning',
            'processing' => 'info',
            'processed'  => 'success',
            'failed'     => 'error',
        );

        return isset( $classes[ $status ] ) ? $classes[ $status ] : 'neutral';
    }
}
