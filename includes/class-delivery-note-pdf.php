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
        // Check if DOMPDF is available.
        if ( class_exists( 'Dompdf\Dompdf' ) ) {
            $this->generate_with_dompdf();
            return;
        }
        
        // Check if TCPDF is available.
        if ( class_exists( 'TCPDF' ) ) {
            $this->generate_with_tcpdf();
            return;
        }
        
        // Fallback: Generate simple PDF without external library.
        $this->generate_simple_pdf();
    }

    /**
     * Generate PDF using DOMPDF.
     */
    private function generate_with_dompdf() {
        $html = $this->get_delivery_note_html();
        
        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml( $html );
        $dompdf->setPaper( 'A4', 'portrait' );
        $dompdf->render();
        
        // Force download.
        $dompdf->stream( $this->get_filename(), array( 'Attachment' => true ) );
        exit;
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
        
        // Force download.
        $pdf->Output( $this->get_filename(), 'D' );
        exit;
    }

    /**
     * Generate a simple PDF without external libraries.
     * Uses basic PDF format specification.
     */
    private function generate_simple_pdf() {
        $content = $this->get_plain_text_content();
        $filename = $this->get_filename();
        
        // PDF objects.
        $objects = array();
        $object_offsets = array();
        
        // Start building PDF.
        $pdf = "%PDF-1.4\n";
        
        // Object 1: Catalog.
        $object_offsets[1] = strlen( $pdf );
        $pdf .= "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
        
        // Object 2: Pages.
        $object_offsets[2] = strlen( $pdf );
        $pdf .= "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";
        
        // Object 3: Page.
        $object_offsets[3] = strlen( $pdf );
        $pdf .= "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents 4 0 R /Resources << /Font << /F1 5 0 R /F2 6 0 R >> >> >>\nendobj\n";
        
        // Object 4: Content stream.
        $stream = $this->build_pdf_content_stream();
        $stream_length = strlen( $stream );
        $object_offsets[4] = strlen( $pdf );
        $pdf .= "4 0 obj\n<< /Length {$stream_length} >>\nstream\n{$stream}endstream\nendobj\n";
        
        // Object 5: Font (Helvetica).
        $object_offsets[5] = strlen( $pdf );
        $pdf .= "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n";
        
        // Object 6: Font (Helvetica-Bold).
        $object_offsets[6] = strlen( $pdf );
        $pdf .= "6 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>\nendobj\n";
        
        // Cross-reference table.
        $xref_offset = strlen( $pdf );
        $pdf .= "xref\n0 7\n";
        $pdf .= "0000000000 65535 f \n";
        for ( $i = 1; $i <= 6; $i++ ) {
            $pdf .= sprintf( "%010d 00000 n \n", $object_offsets[ $i ] );
        }
        
        // Trailer.
        $pdf .= "trailer\n<< /Size 7 /Root 1 0 R >>\n";
        $pdf .= "startxref\n{$xref_offset}\n%%EOF";
        
        // Send headers for download.
        header( 'Content-Type: application/pdf' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'Content-Length: ' . strlen( $pdf ) );
        header( 'Cache-Control: private, max-age=0, must-revalidate' );
        header( 'Pragma: public' );
        
        echo $pdf;
        exit;
    }

    /**
     * Build PDF content stream for simple PDF generation.
     *
     * @return string PDF content stream.
     */
    private function build_pdf_content_stream() {
        $order = $this->order;
        $stream = "BT\n";
        
        $y = 800; // Start from top.
        $left_margin = 50;
        $line_height = 14;
        
        // Store name / Logo replacement.
        $store_name = $this->sanitize_pdf_text( get_bloginfo( 'name' ) );
        $stream .= "/F2 16 Tf\n";
        $stream .= "{$left_margin} {$y} Td\n";
        $stream .= "({$store_name}) Tj\n";
        
        // Title: Delivery Note.
        $y -= 30;
        $stream .= "/F2 18 Tf\n";
        $stream .= "350 " . ( $y + 30 ) . " Td\n";
        $stream .= "(Delivery Note) Tj\n";
        
        // Order info.
        $y -= 5;
        $stream .= "/F1 10 Tf\n";
        $stream .= "350 {$y} Td\n";
        $order_text = $this->sanitize_pdf_text( sprintf( 'Order #%s', $order->get_order_number() ) );
        $stream .= "({$order_text}) Tj\n";
        
        $y -= $line_height;
        $stream .= "350 {$y} Td\n";
        $date_text = $this->sanitize_pdf_text( sprintf( 'Date: %s', $order->get_date_created()->date_i18n( get_option( 'date_format' ) ) ) );
        $stream .= "({$date_text}) Tj\n";
        
        // Nalda order ID.
        $nalda_order_id = $order->get_meta( '_nalda_order_id' );
        if ( $nalda_order_id ) {
            $y -= $line_height;
            $stream .= "350 {$y} Td\n";
            $stream .= "(Nalda #{$nalda_order_id}) Tj\n";
        }
        
        // Separator.
        $y -= 30;
        
        // FROM (Seller).
        $stream .= "/F2 11 Tf\n";
        $stream .= "{$left_margin} {$y} Td\n";
        $stream .= "(From \\(Seller\\)) Tj\n";
        
        $y -= $line_height + 5;
        $stream .= "/F1 10 Tf\n";
        
        $seller_lines = $this->get_seller_address_lines();
        foreach ( $seller_lines as $line ) {
            $stream .= "{$left_margin} {$y} Td\n";
            $stream .= "(" . $this->sanitize_pdf_text( $line ) . ") Tj\n";
            $y -= $line_height;
        }
        
        // TO (Buyer) - on the right side.
        $buyer_y = $y + ( count( $seller_lines ) * $line_height ) + $line_height + 5;
        $stream .= "/F2 11 Tf\n";
        $stream .= "320 {$buyer_y} Td\n";
        $stream .= "(To \\(Buyer\\)) Tj\n";
        
        $buyer_y -= $line_height + 5;
        $stream .= "/F1 10 Tf\n";
        
        $buyer_lines = $this->get_buyer_address_lines();
        foreach ( $buyer_lines as $line ) {
            $stream .= "320 {$buyer_y} Td\n";
            $stream .= "(" . $this->sanitize_pdf_text( $line ) . ") Tj\n";
            $buyer_y -= $line_height;
        }
        
        // "via Nalda" label.
        if ( $nalda_order_id ) {
            $stream .= "320 {$buyer_y} Td\n";
            $stream .= "(via Nalda) Tj\n";
        }
        
        // Products table.
        $y -= 30;
        
        // Table header.
        $stream .= "/F2 10 Tf\n";
        $stream .= "{$left_margin} {$y} Td\n";
        $stream .= "(No) Tj\n";
        $stream .= "80 {$y} Td\n";
        $stream .= "(Description) Tj\n";
        $stream .= "300 {$y} Td\n";
        $stream .= "(Qty) Tj\n";
        $stream .= "350 {$y} Td\n";
        $stream .= "(Unit Price) Tj\n";
        $stream .= "450 {$y} Td\n";
        $stream .= "(Total) Tj\n";
        
        $y -= 5;
        $stream .= "ET\n";
        // Draw header line.
        $stream .= "q\n0.5 w\n{$left_margin} {$y} m\n545 {$y} l\nS\nQ\n";
        $stream .= "BT\n";
        
        $y -= $line_height;
        $stream .= "/F1 10 Tf\n";
        
        // Products.
        $row_num = 1;
        $grand_total = 0;
        $currency_symbol = get_woocommerce_currency_symbol( $order->get_currency() );
        
        foreach ( $order->get_items() as $item ) {
            $quantity = $item->get_quantity();
            $unit_price = $item->get_total() / max( 1, $quantity );
            $total = $item->get_total();
            $grand_total += $total;
            
            $stream .= "{$left_margin} {$y} Td\n";
            $stream .= "({$row_num}) Tj\n";
            
            $stream .= "80 {$y} Td\n";
            $product_name = $this->sanitize_pdf_text( mb_substr( $item->get_name(), 0, 35 ) );
            $stream .= "({$product_name}) Tj\n";
            
            $stream .= "300 {$y} Td\n";
            $stream .= "({$quantity}) Tj\n";
            
            $stream .= "350 {$y} Td\n";
            $stream .= "(" . $this->sanitize_pdf_text( $currency_symbol . number_format( $unit_price, 2 ) ) . ") Tj\n";
            
            $stream .= "450 {$y} Td\n";
            $stream .= "(" . $this->sanitize_pdf_text( $currency_symbol . number_format( $total, 2 ) ) . ") Tj\n";
            
            $y -= $line_height;
            $row_num++;
            
            if ( $y < 200 ) break; // Prevent overflow.
        }
        
        // Total line.
        $y -= 5;
        $stream .= "ET\n";
        $stream .= "q\n0.5 w\n350 {$y} m\n545 {$y} l\nS\nQ\n";
        $stream .= "BT\n";
        
        $y -= $line_height;
        $stream .= "/F2 11 Tf\n";
        $stream .= "350 {$y} Td\n";
        $stream .= "(Grand Total:) Tj\n";
        $stream .= "450 {$y} Td\n";
        $stream .= "(" . $this->sanitize_pdf_text( $currency_symbol . number_format( $grand_total, 2 ) ) . ") Tj\n";
        
        // Delivery type section.
        $y -= 40;
        $stream .= "/F2 10 Tf\n";
        $stream .= "{$left_margin} {$y} Td\n";
        $stream .= "(Delivery type:) Tj\n";
        $stream .= "/F1 10 Tf\n";
        $stream .= "150 {$y} Td\n";
        $stream .= "([  ] delivery service    [  ] post    [  ] self-collection) Tj\n";
        
        $y -= $line_height + 5;
        $stream .= "/F2 10 Tf\n";
        $stream .= "{$left_margin} {$y} Td\n";
        $stream .= "(Number of packages:) Tj\n";
        $stream .= "/F1 10 Tf\n";
        $stream .= "180 {$y} Td\n";
        $stream .= "(_____________) Tj\n";
        
        $y -= $line_height + 5;
        $stream .= "/F2 10 Tf\n";
        $stream .= "{$left_margin} {$y} Td\n";
        $stream .= "(Comments:) Tj\n";
        
        // Signature section.
        $y -= 50;
        $stream .= "/F2 10 Tf\n";
        $stream .= "{$left_margin} {$y} Td\n";
        $stream .= "(Received in good condition:) Tj\n";
        
        $y -= 30;
        $stream .= "ET\n";
        $stream .= "q\n0.5 w\n{$left_margin} {$y} m\n250 {$y} l\nS\nQ\n";
        $stream .= "BT\n";
        
        $y -= 12;
        $stream .= "/F1 8 Tf\n";
        $stream .= "{$left_margin} {$y} Td\n";
        $stream .= "(date, signature) Tj\n";
        
        // Thank you.
        $y -= 40;
        $stream .= "/F1 10 Tf\n";
        $stream .= "200 {$y} Td\n";
        $stream .= "(Thank you for doing business with us!) Tj\n";
        
        $stream .= "ET\n";
        
        return $stream;
    }

    /**
     * Get seller address lines.
     *
     * @return array Address lines.
     */
    private function get_seller_address_lines() {
        $lines = array();
        $lines[] = get_bloginfo( 'name' );
        
        $address = WC()->countries->get_base_address();
        if ( $address ) {
            $lines[] = $address;
        }
        
        $address_2 = WC()->countries->get_base_address_2();
        if ( $address_2 ) {
            $lines[] = $address_2;
        }
        
        $city_line = trim( WC()->countries->get_base_postcode() . ' ' . WC()->countries->get_base_city() );
        if ( $city_line ) {
            $lines[] = $city_line;
        }
        
        $country = WC()->countries->get_base_country();
        if ( $country && isset( WC()->countries->countries[ $country ] ) ) {
            $lines[] = WC()->countries->countries[ $country ];
        }
        
        return $lines;
    }

    /**
     * Get buyer address lines.
     *
     * @return array Address lines.
     */
    private function get_buyer_address_lines() {
        $order = $this->order;
        $lines = array();
        
        $name = trim( $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name() );
        if ( empty( $name ) ) {
            $name = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
        }
        if ( $name ) {
            $lines[] = $name;
        }
        
        $address = $order->get_shipping_address_1() ?: $order->get_billing_address_1();
        if ( $address ) {
            $lines[] = $address;
        }
        
        $address_2 = $order->get_shipping_address_2() ?: $order->get_billing_address_2();
        if ( $address_2 ) {
            $lines[] = $address_2;
        }
        
        $postcode = $order->get_shipping_postcode() ?: $order->get_billing_postcode();
        $city = $order->get_shipping_city() ?: $order->get_billing_city();
        $city_line = trim( $postcode . ' ' . $city );
        if ( $city_line ) {
            $lines[] = $city_line;
        }
        
        $country = $order->get_shipping_country() ?: $order->get_billing_country();
        if ( $country && isset( WC()->countries->countries[ $country ] ) ) {
            $lines[] = WC()->countries->countries[ $country ];
        }
        
        return $lines;
    }

    /**
     * Sanitize text for PDF.
     *
     * @param string $text Text to sanitize.
     * @return string Sanitized text.
     */
    private function sanitize_pdf_text( $text ) {
        // Convert to ASCII-safe string.
        $text = html_entity_decode( $text, ENT_QUOTES, 'UTF-8' );
        // Escape special PDF characters.
        $text = str_replace( '\\', '\\\\', $text );
        $text = str_replace( '(', '\\(', $text );
        $text = str_replace( ')', '\\)', $text );
        // Replace non-ASCII with approximations.
        $text = iconv( 'UTF-8', 'ASCII//TRANSLIT//IGNORE', $text );
        return $text;
    }

    /**
     * Get plain text content for simple PDF.
     *
     * @return string Plain text content.
     */
    private function get_plain_text_content() {
        $order = $this->order;
        $content = '';
        
        $content .= get_bloginfo( 'name' ) . "\n\n";
        $content .= "DELIVERY NOTE\n";
        $content .= "Order #" . $order->get_order_number() . "\n";
        $content .= "Date: " . $order->get_date_created()->date_i18n( get_option( 'date_format' ) ) . "\n\n";
        
        return $content;
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
                <td style="border: 1px solid #ddd; padding: 8px; text-align: center;">' . $row_num . '</td>
                <td style="border: 1px solid #ddd; padding: 8px;">' . esc_html( $item->get_name() ) . '</td>
                <td style="border: 1px solid #ddd; padding: 8px; text-align: center;">' . esc_html( $measure_unit ) . '</td>
                <td style="border: 1px solid #ddd; padding: 8px; text-align: center;">' . $quantity . '</td>
                <td style="border: 1px solid #ddd; padding: 8px; text-align: right;">' . wc_price( $unit_price, array( 'currency' => $order->get_currency() ) ) . '</td>
                <td style="border: 1px solid #ddd; padding: 8px; text-align: right;">' . wc_price( $total, array( 'currency' => $order->get_currency() ) ) . '</td>
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
            <table style="width: 100%; margin-bottom: 30px;">
                <tr>
                    <td style="width: 50%; vertical-align: top;">
                        ' . ( $logo_html ?: '<strong style="font-size: 18px;">' . esc_html( $store_name ) . '</strong>' ) . '
                    </td>
                    <td style="width: 50%; text-align: right; vertical-align: top;">
                        <h1 style="margin: 0; font-size: 24px; color: #333;">' . esc_html__( 'Delivery Note', 'woo-nalda-sync' ) . '</h1>
                        <p style="margin: 5px 0 0; color: #666;">
                            ' . esc_html__( 'Order', 'woo-nalda-sync' ) . ' #' . esc_html( $order->get_order_number() ) . '<br>
                            ' . esc_html__( 'Date', 'woo-nalda-sync' ) . ': ' . esc_html( $order->get_date_created()->date_i18n( get_option( 'date_format' ) ) ) . '
                            ' . ( $nalda_order_id ? '<br><span style="color: #e67e22;">Nalda #' . esc_html( $nalda_order_id ) . '</span>' : '' ) . '
                        </p>
                    </td>
                </tr>
            </table>
            
            <!-- Addresses -->
            <table style="width: 100%; margin-bottom: 30px;">
                <tr>
                    <td style="width: 48%; vertical-align: top; padding-right: 20px;">
                        <h3 style="margin: 0 0 10px; font-size: 14px; color: #666; border-bottom: 2px solid #333; padding-bottom: 5px;">' . esc_html__( 'From (Seller)', 'woo-nalda-sync' ) . '</h3>
                        <p style="margin: 0;">
                            <strong>' . esc_html( $store_name ) . '</strong><br>
                            ' . $seller_address . '
                        </p>
                    </td>
                    <td style="width: 48%; vertical-align: top;">
                        <h3 style="margin: 0 0 10px; font-size: 14px; color: #666; border-bottom: 2px solid #333; padding-bottom: 5px;">' . esc_html__( 'To (Buyer)', 'woo-nalda-sync' ) . '</h3>
                        <p style="margin: 0;">
                            <strong>' . esc_html( $buyer_name ) . '</strong><br>
                            ' . $buyer_address . '
                            ' . ( $nalda_order_id ? '<br><span style="color: #e67e22; font-weight: bold; font-size: 11px;">' . esc_html__( 'via Nalda', 'woo-nalda-sync' ) . '</span>' : '' ) . '
                        </p>
                    </td>
                </tr>
            </table>
            
            <!-- Products Table -->
            <table style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">
                <thead>
                    <tr>
                        <th style="background: #f5f5f5; border: 1px solid #ddd; padding: 10px 8px; text-align: center; width: 40px;">' . esc_html__( 'No', 'woo-nalda-sync' ) . '</th>
                        <th style="background: #f5f5f5; border: 1px solid #ddd; padding: 10px 8px; text-align: left;">' . esc_html__( 'Description', 'woo-nalda-sync' ) . '</th>
                        <th style="background: #f5f5f5; border: 1px solid #ddd; padding: 10px 8px; text-align: center; width: 80px;">' . esc_html__( 'Measure unit', 'woo-nalda-sync' ) . '</th>
                        <th style="background: #f5f5f5; border: 1px solid #ddd; padding: 10px 8px; text-align: center; width: 60px;">' . esc_html__( 'Quantity', 'woo-nalda-sync' ) . '</th>
                        <th style="background: #f5f5f5; border: 1px solid #ddd; padding: 10px 8px; text-align: right; width: 90px;">' . esc_html__( 'Unit price', 'woo-nalda-sync' ) . '</th>
                        <th style="background: #f5f5f5; border: 1px solid #ddd; padding: 10px 8px; text-align: right; width: 90px;">' . esc_html__( 'Total', 'woo-nalda-sync' ) . '</th>
                    </tr>
                </thead>
                <tbody>
                    ' . $items_html . '
                </tbody>
            </table>
            
            <!-- Grand Total -->
            <table style="margin-left: auto; margin-bottom: 30px;">
                <tr>
                    <td style="padding: 5px 20px; font-weight: bold; text-align: right;">' . esc_html__( 'Grand Total', 'woo-nalda-sync' ) . ':</td>
                    <td style="padding: 5px 10px; font-weight: bold; font-size: 16px; text-align: right;">' . wc_price( $grand_total, array( 'currency' => $currency ) ) . '</td>
                </tr>
            </table>
            
            <!-- Delivery Information -->
            <table style="width: 100%; margin-bottom: 30px; border: 1px solid #ddd;">
                <tr>
                    <td style="padding: 10px; width: 150px; background: #f5f5f5; font-weight: bold;">' . esc_html__( 'Delivery type', 'woo-nalda-sync' ) . ':</td>
                    <td style="padding: 10px;">
                        <span style="display: inline-block; width: 14px; height: 14px; border: 1px solid #333; margin-right: 5px; vertical-align: middle;">&#160;</span> ' . esc_html__( 'delivery service', 'woo-nalda-sync' ) . '
                        &#160;&#160;&#160;
                        <span style="display: inline-block; width: 14px; height: 14px; border: 1px solid #333; margin-right: 5px; vertical-align: middle;">&#160;</span> ' . esc_html__( 'post', 'woo-nalda-sync' ) . '
                        &#160;&#160;&#160;
                        <span style="display: inline-block; width: 14px; height: 14px; border: 1px solid #333; margin-right: 5px; vertical-align: middle;">&#160;</span> ' . esc_html__( 'self-collection', 'woo-nalda-sync' ) . '
                    </td>
                </tr>
                <tr>
                    <td style="padding: 10px; background: #f5f5f5; font-weight: bold; border-top: 1px solid #ddd;">' . esc_html__( 'Number of packages', 'woo-nalda-sync' ) . ':</td>
                    <td style="padding: 10px; border-top: 1px solid #ddd;">&#160;</td>
                </tr>
                <tr>
                    <td style="padding: 10px; background: #f5f5f5; font-weight: bold; border-top: 1px solid #ddd;">' . esc_html__( 'Comments', 'woo-nalda-sync' ) . ':</td>
                    <td style="padding: 10px; border-top: 1px solid #ddd; height: 50px;">&#160;</td>
                </tr>
            </table>
            
            <!-- Signature Section -->
            <table style="width: 100%; margin-top: 40px;">
                <tr>
                    <td style="width: 50%;">
                        <p style="margin: 0 0 5px; font-weight: bold;">' . esc_html__( 'Received in good condition', 'woo-nalda-sync' ) . ':</p>
                        <div style="border-bottom: 1px solid #333; width: 250px; height: 40px;"></div>
                        <p style="margin: 5px 0 0; font-size: 10px; color: #666;">' . esc_html__( 'date, signature', 'woo-nalda-sync' ) . '</p>
                    </td>
                    <td style="width: 50%;">&#160;</td>
                </tr>
            </table>
            
            <!-- Thank You -->
            <p style="text-align: center; margin-top: 40px; font-style: italic; color: #666;">
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
