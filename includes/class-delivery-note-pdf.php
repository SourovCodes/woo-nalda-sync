<?php
/**
 * Delivery Note PDF Generator
 *
 * Generates PDF delivery notes for Nalda orders.
 *
 * @package Woo_Nalda_Sync
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Delivery Note PDF class.
 */
class Woo_Nalda_Sync_Delivery_Note_PDF {

    /**
     * Order object.
     *
     * @var WC_Order
     */
    private $order;

    /**
     * Constructor.
     *
     * @param WC_Order $order Order object.
     */
    public function __construct( $order ) {
        $this->order = $order;
    }

    /**
     * Generate and output the PDF.
     */
    public function generate() {
        // Check if TCPDF is available, otherwise use FPDF fallback.
        if ( class_exists( 'TCPDF' ) ) {
            $this->generate_with_tcpdf();
        } else {
            $this->generate_with_html_pdf();
        }
    }

    /**
     * Generate PDF using HTML to PDF conversion (built-in approach).
     */
    private function generate_with_html_pdf() {
        $html = $this->get_delivery_note_html();
        
        // Use DOMPDF if available.
        if ( class_exists( 'Dompdf\Dompdf' ) ) {
            $dompdf = new \Dompdf\Dompdf();
            $dompdf->loadHtml( $html );
            $dompdf->setPaper( 'A4', 'portrait' );
            $dompdf->render();
            $dompdf->stream( $this->get_filename(), array( 'Attachment' => true ) );
            exit;
        }
        
        // Fallback: Output HTML with print styles.
        $this->output_printable_html( $html );
    }

    /**
     * Generate PDF using TCPDF.
     */
    private function generate_with_tcpdf() {
        $pdf = new TCPDF( 'P', 'mm', 'A4', true, 'UTF-8', false );
        
        // Set document information.
        $pdf->SetCreator( get_bloginfo( 'name' ) );
        $pdf->SetAuthor( get_bloginfo( 'name' ) );
        $pdf->SetTitle( sprintf( __( 'Delivery Note #%s', 'woo-nalda-sync' ), $this->order->get_order_number() ) );
        
        // Remove default header/footer.
        $pdf->setPrintHeader( false );
        $pdf->setPrintFooter( false );
        
        // Set margins.
        $pdf->SetMargins( 15, 15, 15 );
        
        // Add a page.
        $pdf->AddPage();
        
        // Write HTML content.
        $pdf->writeHTML( $this->get_delivery_note_html( false ), true, false, true, false, '' );
        
        // Output PDF.
        $pdf->Output( $this->get_filename(), 'D' );
        exit;
    }

    /**
     * Output printable HTML (fallback when no PDF library is available).
     *
     * @param string $html HTML content.
     */
    private function output_printable_html( $html ) {
        $full_html = '<!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>' . esc_html( sprintf( __( 'Delivery Note #%s', 'woo-nalda-sync' ), $this->order->get_order_number() ) ) . '</title>
            <style>
                @media print {
                    body { margin: 0; padding: 20px; }
                    .no-print { display: none !important; }
                }
                @page { margin: 15mm; }
            </style>
        </head>
        <body>
            <div class="no-print" style="background: #f0f0f0; padding: 10px; margin-bottom: 20px; text-align: center;">
                <button onclick="window.print()" style="padding: 10px 20px; font-size: 16px; cursor: pointer;">
                    ' . esc_html__( 'Print / Save as PDF', 'woo-nalda-sync' ) . '
                </button>
            </div>
            ' . $html . '
        </body>
        </html>';
        
        echo $full_html;
        exit;
    }

    /**
     * Get delivery note HTML content.
     *
     * @param bool $include_full_styles Whether to include full CSS styles.
     * @return string HTML content.
     */
    private function get_delivery_note_html( $include_full_styles = true ) {
        $order = $this->order;
        
        // Get seller info from WooCommerce settings.
        $store_name    = get_bloginfo( 'name' );
        $store_address = WC()->countries->get_base_address();
        $store_address_2 = WC()->countries->get_base_address_2();
        $store_city    = WC()->countries->get_base_city();
        $store_postcode = WC()->countries->get_base_postcode();
        $store_country = WC()->countries->get_base_country();
        $store_state   = WC()->countries->get_base_state();
        
        // Format seller address.
        $seller_address_parts = array_filter( array(
            $store_address,
            $store_address_2,
            trim( $store_postcode . ' ' . $store_city ),
            WC()->countries->get_states( $store_country )[ $store_state ] ?? $store_state,
            WC()->countries->countries[ $store_country ] ?? $store_country,
        ) );
        $seller_address = implode( '<br>', $seller_address_parts );
        
        // Get buyer info (end customer from shipping address).
        $buyer_name = trim( $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name() );
        if ( empty( $buyer_name ) ) {
            $buyer_name = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
        }
        
        $buyer_address_parts = array_filter( array(
            $order->get_shipping_address_1() ?: $order->get_billing_address_1(),
            $order->get_shipping_address_2() ?: $order->get_billing_address_2(),
            trim( ( $order->get_shipping_postcode() ?: $order->get_billing_postcode() ) . ' ' . ( $order->get_shipping_city() ?: $order->get_billing_city() ) ),
            WC()->countries->countries[ $order->get_shipping_country() ?: $order->get_billing_country() ] ?? '',
        ) );
        $buyer_address = implode( '<br>', $buyer_address_parts );
        
        // Get Nalda order ID.
        $nalda_order_id = $order->get_meta( '_nalda_order_id' );
        
        // Get site logo.
        $logo_html = '';
        $custom_logo_id = get_theme_mod( 'custom_logo' );
        if ( $custom_logo_id ) {
            $logo_url = wp_get_attachment_image_url( $custom_logo_id, 'medium' );
            if ( $logo_url ) {
                $logo_html = '<img src="' . esc_url( $logo_url ) . '" alt="' . esc_attr( $store_name ) . '" style="max-height: 60px; max-width: 200px;">';
            }
        }
        
        // Build product rows.
        $items_html = '';
        $row_num = 1;
        $grand_total = 0;
        
        foreach ( $order->get_items() as $item ) {
            $product = $item->get_product();
            $quantity = $item->get_quantity();
            $unit_price = $item->get_total() / max( 1, $quantity );
            $total = $item->get_total();
            $grand_total += $total;
            
            // Get measure unit (weight unit or 'pc' for pieces).
            $measure_unit = __( 'pc', 'woo-nalda-sync' );
            if ( $product && $product->has_weight() ) {
                $measure_unit = get_option( 'woocommerce_weight_unit', 'kg' );
            }
            
            $items_html .= '<tr>
                <td style="border: 1px solid #ddd; padding: 6px; text-align: center; font-size: 11px;">' . $row_num . '</td>
                <td style="border: 1px solid #ddd; padding: 6px; font-size: 11px;">' . esc_html( $item->get_name() ) . '</td>
                <td style="border: 1px solid #ddd; padding: 6px; text-align: center; font-size: 11px;">' . esc_html( $measure_unit ) . '</td>
                <td style="border: 1px solid #ddd; padding: 6px; text-align: center; font-size: 11px;">' . $quantity . '</td>
                <td style="border: 1px solid #ddd; padding: 6px; text-align: right; font-size: 11px;">' . wc_price( $unit_price, array( 'currency' => $order->get_currency() ) ) . '</td>
                <td style="border: 1px solid #ddd; padding: 6px; text-align: right; font-size: 11px;">' . wc_price( $total, array( 'currency' => $order->get_currency() ) ) . '</td>
            </tr>';
            
            $row_num++;
        }
        
        // Currency symbol for display.
        $currency = $order->get_currency();
        
        // Build styles.
        $styles = $include_full_styles ? '
        <style>
            body { font-family: Arial, sans-serif; font-size: 12px; line-height: 1.4; color: #333; }
            .delivery-note { max-width: 800px; margin: 0 auto; padding: 20px; }
            .header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 30px; }
            .logo { }
            .document-title { text-align: right; }
            .document-title h1 { margin: 0; font-size: 24px; color: #333; }
            .document-title p { margin: 5px 0 0; color: #666; }
            .addresses { display: flex; justify-content: space-between; margin-bottom: 30px; }
            .address-box { width: 45%; }
            .address-box h3 { margin: 0 0 10px; font-size: 14px; color: #666; border-bottom: 2px solid #333; padding-bottom: 5px; }
            .address-box p { margin: 0; }
            .via-nalda { color: #e67e22; font-weight: bold; font-size: 11px; }
            .items-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
            .items-table th { background: #f5f5f5; border: 1px solid #ddd; padding: 10px 8px; text-align: left; font-weight: bold; }
            .items-table td { border: 1px solid #ddd; padding: 8px; }
            .grand-total { text-align: right; margin-bottom: 30px; }
            .grand-total table { margin-left: auto; }
            .grand-total td { padding: 5px 10px; }
            .grand-total .total-label { font-weight: bold; }
            .grand-total .total-value { font-weight: bold; font-size: 16px; }
            .delivery-info { margin-bottom: 30px; }
            .delivery-info table { width: 100%; }
            .delivery-info td { padding: 8px 5px; vertical-align: top; }
            .checkbox { display: inline-block; width: 14px; height: 14px; border: 1px solid #333; margin-right: 5px; vertical-align: middle; }
            .signature-section { margin-top: 40px; }
            .signature-line { border-bottom: 1px solid #333; width: 250px; margin-top: 40px; }
            .signature-label { font-size: 10px; color: #666; margin-top: 5px; }
            .thank-you { text-align: center; margin-top: 40px; font-style: italic; color: #666; }
        </style>
        ' : '';
        
        // Build HTML.
        $html = $styles . '
        <div class="delivery-note">
            <!-- Header -->
            <table style="width: 100%; margin-bottom: 20px;">
                <tr>
                    <td style="width: 50%; vertical-align: top;">
                        ' . ( $logo_html ?: '<strong style="font-size: 18px;">' . esc_html( $store_name ) . '</strong>' ) . '
                    </td>
                    <td style="width: 50%; text-align: right; vertical-align: top;">
                        <h1 style="margin: 0; font-size: 22px; color: #333;">' . esc_html__( 'Delivery Note', 'woo-nalda-sync' ) . '</h1>
                        <p style="margin: 4px 0 0; color: #666; font-size: 11px;">
                            ' . esc_html__( 'Order', 'woo-nalda-sync' ) . ' #' . esc_html( $order->get_order_number() ) . '<br>
                            ' . esc_html__( 'Date', 'woo-nalda-sync' ) . ': ' . esc_html( $order->get_date_created()->date_i18n( get_option( 'date_format' ) ) ) . '
                            ' . ( $nalda_order_id ? '<br><span style="color: #e67e22;">Nalda #' . esc_html( $nalda_order_id ) . '</span>' : '' ) . '
                        </p>
                    </td>
                </tr>
            </table>
            
            <!-- Addresses -->
            <table style="width: 100%; margin-bottom: 20px;">
                <tr>
                    <td style="width: 48%; vertical-align: top; padding-right: 15px;">
                        <h3 style="margin: 0 0 8px; font-size: 13px; color: #666; border-bottom: 2px solid #333; padding-bottom: 4px;">' . esc_html__( 'From (Seller)', 'woo-nalda-sync' ) . '</h3>
                        <p style="margin: 0;">
                            <strong>' . esc_html( $store_name ) . '</strong><br>
                            ' . $seller_address . '
                            ' . ( $nalda_order_id ? '<br><span style="color: #e67e22; font-weight: bold; font-size: 11px;">' . esc_html__( 'via Nalda', 'woo-nalda-sync' ) . '</span>' : '' ) . '
                        </p>
                    </td>
                    <td style="width: 48%; vertical-align: top;">
                        <h3 style="margin: 0 0 8px; font-size: 13px; color: #666; border-bottom: 2px solid #333; padding-bottom: 4px;">' . esc_html__( 'To (Buyer)', 'woo-nalda-sync' ) . '</h3>
                        <p style="margin: 0;">
                            <strong>' . esc_html( $buyer_name ) . '</strong><br>
                            ' . $buyer_address . '
                        </p>
                    </td>
                </tr>
            </table>
            
            <!-- Products Table -->
            <table style="width: 100%; border-collapse: collapse; margin-bottom: 15px;">
                <thead>
                    <tr>
                        <th style="background: #f5f5f5; border: 1px solid #ddd; padding: 8px 6px; text-align: center; width: 35px; font-size: 11px;">' . esc_html__( 'No', 'woo-nalda-sync' ) . '</th>
                        <th style="background: #f5f5f5; border: 1px solid #ddd; padding: 8px 6px; text-align: left; font-size: 11px;">' . esc_html__( 'Description', 'woo-nalda-sync' ) . '</th>
                        <th style="background: #f5f5f5; border: 1px solid #ddd; padding: 8px 6px; text-align: center; width: 70px; font-size: 11px;">' . esc_html__( 'Measure unit', 'woo-nalda-sync' ) . '</th>
                        <th style="background: #f5f5f5; border: 1px solid #ddd; padding: 8px 6px; text-align: center; width: 50px; font-size: 11px;">' . esc_html__( 'Quantity', 'woo-nalda-sync' ) . '</th>
                        <th style="background: #f5f5f5; border: 1px solid #ddd; padding: 8px 6px; text-align: right; width: 80px; font-size: 11px;">' . esc_html__( 'Unit price', 'woo-nalda-sync' ) . '</th>
                        <th style="background: #f5f5f5; border: 1px solid #ddd; padding: 8px 6px; text-align: right; width: 80px; font-size: 11px;">' . esc_html__( 'Total', 'woo-nalda-sync' ) . '</th>
                    </tr>
                </thead>
                <tbody>
                    ' . $items_html . '
                </tbody>
            </table>
            
            <!-- Grand Total -->
            <table style="margin-left: auto; margin-bottom: 20px;">
                <tr>
                    <td style="padding: 4px 15px; font-weight: bold; text-align: right;">' . esc_html__( 'Grand Total', 'woo-nalda-sync' ) . ':</td>
                    <td style="padding: 4px 8px; font-weight: bold; font-size: 14px; text-align: right;">' . wc_price( $grand_total, array( 'currency' => $currency ) ) . '</td>
                </tr>
            </table>
            
            <!-- Delivery Information -->
            <table style="width: 100%; margin-bottom: 20px; border: 1px solid #ddd;">
                <tr>
                    <td style="padding: 8px; width: 130px; background: #f5f5f5; font-weight: bold; font-size: 11px;">' . esc_html__( 'Delivery type', 'woo-nalda-sync' ) . ':</td>
                    <td style="padding: 8px; font-size: 11px;">
                        <span style="display: inline-block; width: 12px; height: 12px; border: 1px solid #333; margin-right: 4px; vertical-align: middle;">&#160;</span> ' . esc_html__( 'delivery service', 'woo-nalda-sync' ) . '
                        &#160;&#160;
                        <span style="display: inline-block; width: 12px; height: 12px; border: 1px solid #333; margin-right: 4px; vertical-align: middle;">&#160;</span> ' . esc_html__( 'post', 'woo-nalda-sync' ) . '
                        &#160;&#160;
                        <span style="display: inline-block; width: 12px; height: 12px; border: 1px solid #333; margin-right: 4px; vertical-align: middle;">&#160;</span> ' . esc_html__( 'self-collection', 'woo-nalda-sync' ) . '
                    </td>
                </tr>
                <tr>
                    <td style="padding: 8px; background: #f5f5f5; font-weight: bold; border-top: 1px solid #ddd; font-size: 11px;">' . esc_html__( 'Number of packages', 'woo-nalda-sync' ) . ':</td>
                    <td style="padding: 8px; border-top: 1px solid #ddd;">&#160;</td>
                </tr>
                <tr>
                    <td style="padding: 8px; background: #f5f5f5; font-weight: bold; border-top: 1px solid #ddd; font-size: 11px;">' . esc_html__( 'Comments', 'woo-nalda-sync' ) . ':</td>
                    <td style="padding: 8px; border-top: 1px solid #ddd; height: 40px;">&#160;</td>
                </tr>
            </table>
            
            <!-- Signature Section -->
            <table style="width: 100%; margin-top: 30px;">
                <tr>
                    <td style="width: 50%;">
                        <p style="margin: 0 0 4px; font-weight: bold; font-size: 11px;">' . esc_html__( 'Received in good condition', 'woo-nalda-sync' ) . ':</p>
                        <div style="border-bottom: 1px solid #333; width: 220px; height: 35px;"></div>
                        <p style="margin: 4px 0 0; font-size: 9px; color: #666;">' . esc_html__( 'date, signature', 'woo-nalda-sync' ) . '</p>
                    </td>
                    <td style="width: 50%;">&#160;</td>
                </tr>
            </table>
            
            <!-- Thank You -->
            <p style="text-align: center; margin-top: 30px; font-style: italic; color: #666; font-size: 11px;">
                ' . esc_html__( 'Thank you for doing business with us!', 'woo-nalda-sync' ) . '
            </p>
        </div>';
        
        return $html;
    }

    /**
     * Get PDF filename.
     *
     * @return string Filename.
     */
    private function get_filename() {
        return sprintf(
            'delivery-note-%s-%s.pdf',
            $this->order->get_order_number(),
            date( 'Y-m-d' )
        );
    }
}
