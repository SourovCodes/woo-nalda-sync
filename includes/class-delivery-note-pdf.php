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
                $logo_html = '<img src="' . esc_url( $logo_url ) . '" alt="' . esc_attr( $store_name ) . '" style="max-height: 50px; max-width: 180px;">';
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
            
            $row_bg = ( $row_num % 2 === 0 ) ? '#f8fafc' : '#ffffff';
            
            $items_html .= '<tr style="background: ' . $row_bg . ';">
                <td style="padding: 12px 10px; text-align: center; border-bottom: 1px solid #e2e8f0; color: #64748b;">' . $row_num . '</td>
                <td style="padding: 12px 10px; border-bottom: 1px solid #e2e8f0; font-weight: 500; color: #1e293b;">' . esc_html( $item->get_name() ) . '</td>
                <td style="padding: 12px 10px; text-align: center; border-bottom: 1px solid #e2e8f0; color: #64748b;">' . esc_html( $measure_unit ) . '</td>
                <td style="padding: 12px 10px; text-align: center; border-bottom: 1px solid #e2e8f0; font-weight: 600; color: #1e293b;">' . $quantity . '</td>
                <td style="padding: 12px 10px; text-align: right; border-bottom: 1px solid #e2e8f0; color: #64748b;">' . wc_price( $unit_price, array( 'currency' => $order->get_currency() ) ) . '</td>
                <td style="padding: 12px 10px; text-align: right; border-bottom: 1px solid #e2e8f0; font-weight: 600; color: #1e293b;">' . wc_price( $total, array( 'currency' => $order->get_currency() ) ) . '</td>
            </tr>';
            
            $row_num++;
        }
        
        // Currency symbol for display.
        $currency = $order->get_currency();
        
        // Build HTML with modern design.
        $html = '
        <div style="font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Oxygen, Ubuntu, sans-serif; font-size: 13px; line-height: 1.5; color: #334155; max-width: 800px; margin: 0 auto; padding: 30px;">
            
            <!-- Header with gradient accent -->
            <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 12px 12px 0 0; padding: 3px;"></div>
            <div style="background: #ffffff; border: 1px solid #e2e8f0; border-top: none; border-radius: 0 0 12px 12px; padding: 30px; margin-bottom: 25px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);">
                <table style="width: 100%;">
                    <tr>
                        <td style="width: 50%; vertical-align: middle;">
                            ' . ( $logo_html ?: '<span style="font-size: 22px; font-weight: 700; color: #1e293b;">' . esc_html( $store_name ) . '</span>' ) . '
                        </td>
                        <td style="width: 50%; text-align: right; vertical-align: middle;">
                            <span style="display: inline-block; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #fff; padding: 8px 20px; border-radius: 25px; font-size: 14px; font-weight: 600; letter-spacing: 0.5px;">
                                ' . esc_html__( 'DELIVERY NOTE', 'woo-nalda-sync' ) . '
                            </span>
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- Order Info Bar -->
            <div style="background: #f1f5f9; border-radius: 10px; padding: 15px 25px; margin-bottom: 25px; display: flex;">
                <table style="width: 100%;">
                    <tr>
                        <td style="text-align: center; padding: 0 15px; border-right: 2px solid #e2e8f0;">
                            <div style="color: #64748b; font-size: 11px; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 3px;">' . esc_html__( 'Order Number', 'woo-nalda-sync' ) . '</div>
                            <div style="color: #1e293b; font-size: 16px; font-weight: 700;">#' . esc_html( $order->get_order_number() ) . '</div>
                        </td>
                        <td style="text-align: center; padding: 0 15px; border-right: 2px solid #e2e8f0;">
                            <div style="color: #64748b; font-size: 11px; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 3px;">' . esc_html__( 'Date', 'woo-nalda-sync' ) . '</div>
                            <div style="color: #1e293b; font-size: 16px; font-weight: 700;">' . esc_html( $order->get_date_created()->date_i18n( get_option( 'date_format' ) ) ) . '</div>
                        </td>
                        ' . ( $nalda_order_id ? '
                        <td style="text-align: center; padding: 0 15px;">
                            <div style="color: #64748b; font-size: 11px; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 3px;">Nalda ID</div>
                            <div style="color: #7c3aed; font-size: 16px; font-weight: 700;">#' . esc_html( $nalda_order_id ) . '</div>
                        </td>
                        ' : '' ) . '
                    </tr>
                </table>
            </div>
            
            <!-- Addresses -->
            <table style="width: 100%; margin-bottom: 25px;">
                <tr>
                    <td style="width: 48%; vertical-align: top; padding-right: 15px;">
                        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 20px; height: 100%; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                            <div style="display: flex; align-items: center; margin-bottom: 12px; padding-bottom: 10px; border-bottom: 2px solid #667eea;">
                                <span style="background: #667eea; color: #fff; width: 28px; height: 28px; border-radius: 50%; display: inline-block; text-align: center; line-height: 28px; font-size: 12px; margin-right: 10px;">&#x2191;</span>
                                <span style="font-size: 12px; font-weight: 700; color: #667eea; text-transform: uppercase; letter-spacing: 1px;">' . esc_html__( 'From', 'woo-nalda-sync' ) . '</span>
                            </div>
                            <div style="color: #1e293b; font-weight: 600; font-size: 15px; margin-bottom: 5px;">' . esc_html( $store_name ) . '</div>
                            ' . ( $nalda_order_id ? '<div style="display: inline-block; background: linear-gradient(135deg, #f97316 0%, #ea580c 100%); color: #fff; padding: 3px 10px; border-radius: 12px; font-size: 10px; font-weight: 600; margin-bottom: 8px; letter-spacing: 0.5px;">via Nalda</div>' : '' ) . '
                            <div style="color: #64748b; font-size: 13px; line-height: 1.6;">' . $seller_address . '</div>
                        </div>
                    </td>
                    <td style="width: 48%; vertical-align: top; padding-left: 15px;">
                        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 20px; height: 100%; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                            <div style="display: flex; align-items: center; margin-bottom: 12px; padding-bottom: 10px; border-bottom: 2px solid #10b981;">
                                <span style="background: #10b981; color: #fff; width: 28px; height: 28px; border-radius: 50%; display: inline-block; text-align: center; line-height: 28px; font-size: 12px; margin-right: 10px;">&#x2193;</span>
                                <span style="font-size: 12px; font-weight: 700; color: #10b981; text-transform: uppercase; letter-spacing: 1px;">' . esc_html__( 'To', 'woo-nalda-sync' ) . '</span>
                            </div>
                            <div style="color: #1e293b; font-weight: 600; font-size: 15px; margin-bottom: 5px;">' . esc_html( $buyer_name ) . '</div>
                            <div style="color: #64748b; font-size: 13px; line-height: 1.6;">' . $buyer_address . '</div>
                        </div>
                    </td>
                </tr>
            </table>
            
            <!-- Products Table -->
            <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; overflow: hidden; margin-bottom: 25px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                            <th style="padding: 14px 10px; text-align: center; color: #fff; font-weight: 600; font-size: 11px; text-transform: uppercase; letter-spacing: 1px; width: 50px;">' . esc_html__( 'No', 'woo-nalda-sync' ) . '</th>
                            <th style="padding: 14px 10px; text-align: left; color: #fff; font-weight: 600; font-size: 11px; text-transform: uppercase; letter-spacing: 1px;">' . esc_html__( 'Description', 'woo-nalda-sync' ) . '</th>
                            <th style="padding: 14px 10px; text-align: center; color: #fff; font-weight: 600; font-size: 11px; text-transform: uppercase; letter-spacing: 1px; width: 80px;">' . esc_html__( 'Unit', 'woo-nalda-sync' ) . '</th>
                            <th style="padding: 14px 10px; text-align: center; color: #fff; font-weight: 600; font-size: 11px; text-transform: uppercase; letter-spacing: 1px; width: 60px;">' . esc_html__( 'Qty', 'woo-nalda-sync' ) . '</th>
                            <th style="padding: 14px 10px; text-align: right; color: #fff; font-weight: 600; font-size: 11px; text-transform: uppercase; letter-spacing: 1px; width: 100px;">' . esc_html__( 'Unit Price', 'woo-nalda-sync' ) . '</th>
                            <th style="padding: 14px 10px; text-align: right; color: #fff; font-weight: 600; font-size: 11px; text-transform: uppercase; letter-spacing: 1px; width: 100px;">' . esc_html__( 'Total', 'woo-nalda-sync' ) . '</th>
                        </tr>
                    </thead>
                    <tbody>
                        ' . $items_html . '
                    </tbody>
                </table>
                
                <!-- Grand Total -->
                <div style="background: #f8fafc; padding: 15px 20px; border-top: 2px solid #e2e8f0;">
                    <table style="width: 100%;">
                        <tr>
                            <td style="text-align: right; padding-right: 20px;">
                                <span style="color: #64748b; font-size: 13px;">' . esc_html__( 'Grand Total', 'woo-nalda-sync' ) . '</span>
                            </td>
                            <td style="text-align: right; width: 120px;">
                                <span style="font-size: 20px; font-weight: 700; color: #1e293b;">' . wc_price( $grand_total, array( 'currency' => $currency ) ) . '</span>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
            
            <!-- Delivery Information -->
            <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 25px; margin-bottom: 25px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                <div style="font-size: 13px; font-weight: 700; color: #1e293b; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid #e2e8f0;">
                    <span style="background: #f1f5f9; padding: 5px 12px; border-radius: 6px;">📦 ' . esc_html__( 'Delivery Information', 'woo-nalda-sync' ) . '</span>
                </div>
                
                <table style="width: 100%;">
                    <tr>
                        <td style="padding: 10px 0; color: #64748b; width: 160px; vertical-align: top;">' . esc_html__( 'Delivery type', 'woo-nalda-sync' ) . ':</td>
                        <td style="padding: 10px 0;">
                            <label style="display: inline-flex; align-items: center; margin-right: 25px; cursor: pointer;">
                                <span style="display: inline-block; width: 18px; height: 18px; border: 2px solid #cbd5e1; border-radius: 4px; margin-right: 8px; background: #fff;"></span>
                                <span style="color: #475569;">' . esc_html__( 'Delivery service', 'woo-nalda-sync' ) . '</span>
                            </label>
                            <label style="display: inline-flex; align-items: center; margin-right: 25px; cursor: pointer;">
                                <span style="display: inline-block; width: 18px; height: 18px; border: 2px solid #cbd5e1; border-radius: 4px; margin-right: 8px; background: #fff;"></span>
                                <span style="color: #475569;">' . esc_html__( 'Post', 'woo-nalda-sync' ) . '</span>
                            </label>
                            <label style="display: inline-flex; align-items: center; cursor: pointer;">
                                <span style="display: inline-block; width: 18px; height: 18px; border: 2px solid #cbd5e1; border-radius: 4px; margin-right: 8px; background: #fff;"></span>
                                <span style="color: #475569;">' . esc_html__( 'Self-collection', 'woo-nalda-sync' ) . '</span>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 10px 0; color: #64748b; vertical-align: top;">' . esc_html__( 'Number of packages', 'woo-nalda-sync' ) . ':</td>
                        <td style="padding: 10px 0;">
                            <div style="border-bottom: 1px dashed #cbd5e1; width: 100px; height: 20px;"></div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 10px 0; color: #64748b; vertical-align: top;">' . esc_html__( 'Comments', 'woo-nalda-sync' ) . ':</td>
                        <td style="padding: 10px 0;">
                            <div style="border: 1px dashed #cbd5e1; border-radius: 6px; height: 60px; background: #fafafa;"></div>
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- Signature Section -->
            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 25px; margin-bottom: 25px;">
                <table style="width: 100%;">
                    <tr>
                        <td style="width: 60%; vertical-align: bottom;">
                            <div style="color: #64748b; font-size: 12px; margin-bottom: 8px;">' . esc_html__( 'Received in good condition', 'woo-nalda-sync' ) . '</div>
                            <div style="border-bottom: 2px solid #1e293b; width: 280px; height: 50px;"></div>
                            <div style="color: #94a3b8; font-size: 11px; margin-top: 6px; font-style: italic;">' . esc_html__( 'Date & Signature', 'woo-nalda-sync' ) . '</div>
                        </td>
                        <td style="width: 40%; text-align: right; vertical-align: bottom;">
                            <div style="color: #94a3b8; font-size: 11px;">' . esc_html__( 'Stamp', 'woo-nalda-sync' ) . '</div>
                            <div style="border: 2px dashed #e2e8f0; border-radius: 8px; width: 120px; height: 80px; display: inline-block; margin-top: 8px;"></div>
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- Thank You -->
            <div style="text-align: center; padding: 20px;">
                <div style="display: inline-block; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; font-size: 16px; font-weight: 600;">
                    ✨ ' . esc_html__( 'Thank you for doing business with us!', 'woo-nalda-sync' ) . ' ✨
                </div>
            </div>
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
