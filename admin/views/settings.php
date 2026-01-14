<?php
/**
 * Settings Page View
 *
 * @package Woo_Nalda_Sync
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Get update info.
$updater     = woo_nalda_sync()->updater;
$update_info = $updater ? $updater->get_update_info() : false;
?>

<div class="wns-wrap">
    <!-- Header -->
    <div class="wns-header">
        <div class="wns-header-left">
            <div class="wns-logo">
                <span class="dashicons dashicons-update"></span>
            </div>
            <div>
                <h1><?php esc_html_e( 'Settings', 'woo-nalda-sync' ); ?></h1>
                <p><?php esc_html_e( 'Configure your WooCommerce Nalda Sync settings.', 'woo-nalda-sync' ); ?></p>
            </div>
        </div>
        <div class="wns-header-right">
            <span class="wns-version"><?php echo esc_html( 'v' . WOO_NALDA_SYNC_VERSION ); ?></span>
            <?php if ( $is_licensed ) : ?>
                <span class="wns-badge wns-badge-success">
                    <span class="dashicons dashicons-yes-alt"></span>
                    <?php esc_html_e( 'Licensed', 'woo-nalda-sync' ); ?>
                </span>
            <?php else : ?>
                <span class="wns-badge wns-badge-warning">
                    <span class="dashicons dashicons-warning"></span>
                    <?php esc_html_e( 'Unlicensed', 'woo-nalda-sync' ); ?>
                </span>
            <?php endif; ?>
        </div>
    </div>

    <?php if ( ! $is_licensed ) : ?>
        <!-- License Warning -->
        <div class="wns-alert wns-alert-warning">
            <span class="wns-alert-icon dashicons dashicons-warning"></span>
            <div class="wns-alert-content">
                <div class="wns-alert-title"><?php esc_html_e( 'License Required', 'woo-nalda-sync' ); ?></div>
                <p class="wns-alert-message">
                    <?php esc_html_e( 'Please activate your license to enable all features and receive updates.', 'woo-nalda-sync' ); ?>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=woo-nalda-sync-license' ) ); ?>">
                        <?php esc_html_e( 'Activate License', 'woo-nalda-sync' ); ?>
                    </a>
                </p>
            </div>
        </div>
    <?php endif; ?>

    <!-- Plugin Update Section -->
    <div class="wns-card" id="wns-update-section">
        <div class="wns-card-header">
            <h2>
                <span class="dashicons dashicons-update"></span>
                <?php esc_html_e( 'Plugin Updates', 'woo-nalda-sync' ); ?>
            </h2>
            <button type="button" class="wns-btn wns-btn-secondary wns-btn-sm" id="wns-check-update">
                <span class="dashicons dashicons-image-rotate"></span>
                <?php esc_html_e( 'Check for Updates', 'woo-nalda-sync' ); ?>
            </button>
        </div>
        <div class="wns-card-body">
            <div class="wns-update-info">
                <div class="wns-update-status" id="wns-update-status">
                    <div class="wns-settings-row" style="border-bottom: none;">
                        <div class="wns-settings-row-info">
                            <div class="wns-settings-row-label"><?php esc_html_e( 'Current Version', 'woo-nalda-sync' ); ?></div>
                            <p class="wns-settings-row-desc"><?php esc_html_e( 'The version of the plugin currently installed.', 'woo-nalda-sync' ); ?></p>
                        </div>
                        <div class="wns-settings-row-control">
                            <span class="wns-version-badge wns-badge wns-badge-info">
                                <?php echo esc_html( 'v' . WOO_NALDA_SYNC_VERSION ); ?>
                            </span>
                        </div>
                    </div>

                    <?php if ( $update_info ) : ?>
                        <!-- Update Available Notice -->
                        <div class="wns-alert wns-alert-info" style="margin: 0 0 20px 0;">
                            <span class="wns-alert-icon dashicons dashicons-info"></span>
                            <div class="wns-alert-content">
                                <div class="wns-alert-title">
                                    <?php 
                                    printf( 
                                        /* translators: %s: New version number */
                                        esc_html__( 'Version %s is available!', 'woo-nalda-sync' ), 
                                        esc_html( $update_info['new_version'] ) 
                                    ); 
                                    ?>
                                </div>
                                <p class="wns-alert-message">
                                    <?php esc_html_e( 'A new version of WooCommerce Nalda Sync is available. Update now to get the latest features and improvements.', 'woo-nalda-sync' ); ?>
                                </p>
                            </div>
                        </div>

                        <div class="wns-settings-row" style="border-bottom: none;">
                            <div class="wns-settings-row-info">
                                <div class="wns-settings-row-label"><?php esc_html_e( 'Latest Version', 'woo-nalda-sync' ); ?></div>
                                <p class="wns-settings-row-desc">
                                    <?php 
                                    if ( ! empty( $update_info['published_at'] ) ) {
                                        printf(
                                            /* translators: %s: Release date */
                                            esc_html__( 'Released on %s', 'woo-nalda-sync' ),
                                            esc_html( wp_date( get_option( 'date_format' ), strtotime( $update_info['published_at'] ) ) )
                                        );
                                    }
                                    ?>
                                </p>
                            </div>
                            <div class="wns-settings-row-control">
                                <span class="wns-version-badge wns-badge wns-badge-success">
                                    <?php echo esc_html( 'v' . $update_info['new_version'] ); ?>
                                </span>
                            </div>
                        </div>

                        <?php if ( ! empty( $update_info['release_notes'] ) ) : ?>
                            <div class="wns-release-notes" style="margin-top: 15px; padding: 15px; background: #f8f9fa; border-radius: 6px;">
                                <h4 style="margin: 0 0 10px 0; font-size: 13px;">
                                    <span class="dashicons dashicons-editor-ul" style="font-size: 16px; vertical-align: middle;"></span>
                                    <?php esc_html_e( 'Release Notes', 'woo-nalda-sync' ); ?>
                                </h4>
                                <div class="wns-release-notes-content" style="font-size: 13px; color: #50575e; max-height: 150px; overflow-y: auto;">
                                    <?php echo wp_kses_post( nl2br( esc_html( $update_info['release_notes'] ) ) ); ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="wns-update-actions" style="margin-top: 20px; display: flex; gap: 10px;">
                            <button type="button" class="wns-btn wns-btn-primary" id="wns-run-update">
                                <span class="dashicons dashicons-update"></span>
                                <?php esc_html_e( 'Update Now', 'woo-nalda-sync' ); ?>
                            </button>
                            <?php if ( ! empty( $update_info['release_url'] ) ) : ?>
                                <a href="<?php echo esc_url( $update_info['release_url'] ); ?>" class="wns-btn wns-btn-secondary" target="_blank">
                                    <span class="dashicons dashicons-external"></span>
                                    <?php esc_html_e( 'View on GitHub', 'woo-nalda-sync' ); ?>
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php else : ?>
                        <!-- No Update Available -->
                        <div class="wns-settings-row" style="border-bottom: none;">
                            <div class="wns-settings-row-info">
                                <div class="wns-settings-row-label"><?php esc_html_e( 'Status', 'woo-nalda-sync' ); ?></div>
                            </div>
                            <div class="wns-settings-row-control">
                                <span class="wns-badge wns-badge-success">
                                    <span class="dashicons dashicons-yes" style="font-size: 14px; width: 14px; height: 14px; vertical-align: text-bottom;"></span>
                                    <?php esc_html_e( 'Up to date', 'woo-nalda-sync' ); ?>
                                </span>
                            </div>
                        </div>
                        <p class="wns-info-note" style="margin-top: 10px;">
                            <span class="dashicons dashicons-info"></span>
                            <?php esc_html_e( 'You are running the latest version of WooCommerce Nalda Sync.', 'woo-nalda-sync' ); ?>
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Settings Form -->
    <form id="wns-settings-form" method="post" action="">
        <?php wp_nonce_field( 'woo_nalda_sync_settings' ); ?>

        <!-- WooCommerce Settings (Read-only) -->
        <div class="wns-card">
            <div class="wns-card-header">
                <h2>
                    <span class="dashicons dashicons-cart"></span>
                    <?php esc_html_e( 'WooCommerce Settings', 'woo-nalda-sync' ); ?>
                </h2>
            </div>
            <div class="wns-card-body">
                <div class="wns-settings-section">
                    <div class="wns-wc-info-grid">
                        <div class="wns-wc-info-item">
                            <span class="wns-wc-info-label"><?php esc_html_e( 'Country', 'woo-nalda-sync' ); ?></span>
                            <span class="wns-wc-info-value"><?php echo esc_html( isset( $wc_settings['country_name'] ) ? $wc_settings['country_name'] : 'N/A' ); ?></span>
                        </div>
                        <div class="wns-wc-info-item">
                            <span class="wns-wc-info-label"><?php esc_html_e( 'Currency', 'woo-nalda-sync' ); ?></span>
                            <span class="wns-wc-info-value"><?php echo esc_html( isset( $wc_settings['currency'] ) ? $wc_settings['currency'] : 'N/A' ); ?></span>
                        </div>
                        <div class="wns-wc-info-item">
                            <span class="wns-wc-info-label"><?php esc_html_e( 'Tax Rate', 'woo-nalda-sync' ); ?></span>
                            <span class="wns-wc-info-value"><?php echo esc_html( isset( $wc_settings['tax_rate'] ) ? $wc_settings['tax_rate'] . '%' : '0%' ); ?></span>
                        </div>
                    </div>
                    <p class="wns-info-note">
                        <span class="dashicons dashicons-info"></span>
                        <?php esc_html_e( 'These values are automatically imported from WooCommerce settings.', 'woo-nalda-sync' ); ?>
                    </p>
                </div>
            </div>
        </div>

        <!-- SFTP Connection Settings -->
        <div class="wns-card">
            <div class="wns-card-header">
                <h2>
                    <span class="dashicons dashicons-cloud-upload"></span>
                    <?php esc_html_e( 'SFTP Connection Settings', 'woo-nalda-sync' ); ?>
                </h2>
                <button type="button" class="wns-btn wns-btn-secondary wns-btn-sm" id="wns-test-sftp">
                    <span class="dashicons dashicons-admin-plugins"></span>
                    <?php esc_html_e( 'Test Connection', 'woo-nalda-sync' ); ?>
                </button>
            </div>
            <div class="wns-card-body">
                <div class="wns-settings-section">
                    <div class="wns-settings-row">
                        <div class="wns-settings-row-info">
                            <div class="wns-settings-row-label"><?php esc_html_e( 'SFTP Host', 'woo-nalda-sync' ); ?></div>
                            <p class="wns-settings-row-desc"><?php esc_html_e( 'The SFTP server hostname or IP address.', 'woo-nalda-sync' ); ?></p>
                        </div>
                        <div class="wns-settings-row-control">
                            <input type="text" name="sftp_host" id="sftp_host" class="wns-form-input" value="<?php echo esc_attr( isset( $settings['sftp_host'] ) ? $settings['sftp_host'] : '' ); ?>" placeholder="sftp.example.com">
                        </div>
                    </div>

                    <div class="wns-settings-row">
                        <div class="wns-settings-row-info">
                            <div class="wns-settings-row-label"><?php esc_html_e( 'SFTP Port', 'woo-nalda-sync' ); ?></div>
                            <p class="wns-settings-row-desc"><?php esc_html_e( 'The SFTP server port (default: 22).', 'woo-nalda-sync' ); ?></p>
                        </div>
                        <div class="wns-settings-row-control">
                            <input type="number" name="sftp_port" id="sftp_port" class="wns-form-input" value="<?php echo esc_attr( isset( $settings['sftp_port'] ) ? $settings['sftp_port'] : '22' ); ?>" min="1" max="65535">
                        </div>
                    </div>

                    <div class="wns-settings-row">
                        <div class="wns-settings-row-info">
                            <div class="wns-settings-row-label"><?php esc_html_e( 'Username', 'woo-nalda-sync' ); ?></div>
                            <p class="wns-settings-row-desc"><?php esc_html_e( 'SFTP username for authentication.', 'woo-nalda-sync' ); ?></p>
                        </div>
                        <div class="wns-settings-row-control">
                            <input type="text" name="sftp_username" id="sftp_username" class="wns-form-input" value="<?php echo esc_attr( isset( $settings['sftp_username'] ) ? $settings['sftp_username'] : '' ); ?>" placeholder="<?php esc_attr_e( 'Enter username', 'woo-nalda-sync' ); ?>">
                        </div>
                    </div>

                    <div class="wns-settings-row">
                        <div class="wns-settings-row-info">
                            <div class="wns-settings-row-label"><?php esc_html_e( 'Password', 'woo-nalda-sync' ); ?></div>
                            <p class="wns-settings-row-desc"><?php esc_html_e( 'SFTP password for authentication.', 'woo-nalda-sync' ); ?></p>
                        </div>
                        <div class="wns-settings-row-control">
                            <input type="password" name="sftp_password" id="sftp_password" class="wns-form-input" value="<?php echo esc_attr( isset( $settings['sftp_password'] ) ? $settings['sftp_password'] : '' ); ?>" placeholder="<?php esc_attr_e( 'Enter password', 'woo-nalda-sync' ); ?>">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Product Export Settings -->
        <div class="wns-card">
            <div class="wns-card-header">
                <h2>
                    <span class="dashicons dashicons-media-spreadsheet"></span>
                    <?php esc_html_e( 'Product Export Settings', 'woo-nalda-sync' ); ?>
                </h2>
            </div>
            <div class="wns-card-body">
                <p class="wns-section-description"><?php esc_html_e( 'Configure how products are exported to Nalda Marketplace via CSV upload.', 'woo-nalda-sync' ); ?></p>
                
                <div class="wns-settings-section">
                    <div class="wns-settings-row">
                        <div class="wns-settings-row-info">
                            <div class="wns-settings-row-label"><?php esc_html_e( 'Enable Product Export', 'woo-nalda-sync' ); ?></div>
                            <p class="wns-settings-row-desc"><?php esc_html_e( 'Enable automatic product export to Nalda Marketplace.', 'woo-nalda-sync' ); ?></p>
                        </div>
                        <div class="wns-settings-row-control">
                            <label class="wns-toggle">
                                <input type="checkbox" name="product_export_enabled" value="yes" <?php checked( isset( $settings['product_export_enabled'] ) ? $settings['product_export_enabled'] : 'no', 'yes' ); ?>>
                                <span class="wns-toggle-slider"></span>
                            </label>
                        </div>
                    </div>

                    <div class="wns-settings-row">
                        <div class="wns-settings-row-info">
                            <div class="wns-settings-row-label"><?php esc_html_e( 'Default Export Behavior', 'woo-nalda-sync' ); ?></div>
                            <p class="wns-settings-row-desc"><?php esc_html_e( 'Control which products are exported by default. You can override this per product.', 'woo-nalda-sync' ); ?></p>
                        </div>
                        <div class="wns-settings-row-control">
                            <select name="sync_default_mode" class="wns-form-select">
                                <option value="include_all" <?php selected( isset( $settings['sync_default_mode'] ) ? $settings['sync_default_mode'] : 'include_all', 'include_all' ); ?>><?php esc_html_e( 'Export all products (opt-out)', 'woo-nalda-sync' ); ?></option>
                                <option value="exclude_all" <?php selected( isset( $settings['sync_default_mode'] ) ? $settings['sync_default_mode'] : 'include_all', 'exclude_all' ); ?>><?php esc_html_e( 'Export no products (opt-in)', 'woo-nalda-sync' ); ?></option>
                            </select>
                            <p class="wns-form-hint" style="margin-top: 8px;">
                                <?php esc_html_e( 'Tip: Manage individual products from Products → All Products → "Nalda" column.', 'woo-nalda-sync' ); ?>
                            </p>
                        </div>
                    </div>

                    <div class="wns-settings-row">
                        <div class="wns-settings-row-info">
                            <div class="wns-settings-row-label"><?php esc_html_e( 'Export Schedule', 'woo-nalda-sync' ); ?></div>
                            <p class="wns-settings-row-desc"><?php esc_html_e( 'How often should the export run automatically.', 'woo-nalda-sync' ); ?></p>
                        </div>
                        <div class="wns-settings-row-control">
                            <select name="product_export_schedule" class="wns-form-select">
                                <option value="every_15_minutes" <?php selected( isset( $settings['product_export_schedule'] ) ? $settings['product_export_schedule'] : 'hourly', 'every_15_minutes' ); ?>><?php esc_html_e( 'Every 15 minutes', 'woo-nalda-sync' ); ?></option>
                                <option value="every_30_minutes" <?php selected( isset( $settings['product_export_schedule'] ) ? $settings['product_export_schedule'] : 'hourly', 'every_30_minutes' ); ?>><?php esc_html_e( 'Every 30 minutes', 'woo-nalda-sync' ); ?></option>
                                <option value="hourly" <?php selected( isset( $settings['product_export_schedule'] ) ? $settings['product_export_schedule'] : 'hourly', 'hourly' ); ?>><?php esc_html_e( 'Hourly', 'woo-nalda-sync' ); ?></option>
                                <option value="every_6_hours" <?php selected( isset( $settings['product_export_schedule'] ) ? $settings['product_export_schedule'] : 'hourly', 'every_6_hours' ); ?>><?php esc_html_e( 'Every 6 hours', 'woo-nalda-sync' ); ?></option>
                                <option value="every_12_hours" <?php selected( isset( $settings['product_export_schedule'] ) ? $settings['product_export_schedule'] : 'hourly', 'every_12_hours' ); ?>><?php esc_html_e( 'Every 12 hours', 'woo-nalda-sync' ); ?></option>
                                <option value="daily" <?php selected( isset( $settings['product_export_schedule'] ) ? $settings['product_export_schedule'] : 'hourly', 'daily' ); ?>><?php esc_html_e( 'Daily', 'woo-nalda-sync' ); ?></option>
                            </select>
                        </div>
                    </div>

                    <div class="wns-settings-row">
                        <div class="wns-settings-row-info">
                            <div class="wns-settings-row-label"><?php esc_html_e( 'Filename Pattern', 'woo-nalda-sync' ); ?></div>
                            <p class="wns-settings-row-desc"><?php esc_html_e( 'Available placeholders: {date}, {datetime}, {timestamp}', 'woo-nalda-sync' ); ?></p>
                        </div>
                        <div class="wns-settings-row-control">
                            <input type="text" name="filename_pattern" class="wns-form-input" value="<?php echo esc_attr( isset( $settings['filename_pattern'] ) ? $settings['filename_pattern'] : 'products_{date}.csv' ); ?>">
                        </div>
                    </div>

                    <div class="wns-settings-row">
                        <div class="wns-settings-row-info">
                            <div class="wns-settings-row-label"><?php esc_html_e( 'Batch Size', 'woo-nalda-sync' ); ?></div>
                            <p class="wns-settings-row-desc"><?php esc_html_e( 'Number of products to process per batch. Lower values use less memory.', 'woo-nalda-sync' ); ?></p>
                        </div>
                        <div class="wns-settings-row-control">
                            <input type="number" name="batch_size" class="wns-form-input" value="<?php echo esc_attr( isset( $settings['batch_size'] ) ? $settings['batch_size'] : '100' ); ?>" min="10" max="500" step="10">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Product Default Settings -->
        <div class="wns-card">
            <div class="wns-card-header">
                <h2>
                    <span class="dashicons dashicons-products"></span>
                    <?php esc_html_e( 'Product Default Settings', 'woo-nalda-sync' ); ?>
                </h2>
            </div>
            <div class="wns-card-body">
                <p class="wns-section-description"><?php esc_html_e( 'Set default values for product export fields.', 'woo-nalda-sync' ); ?></p>
                
                <div class="wns-settings-section">
                    <div class="wns-settings-row">
                        <div class="wns-settings-row-info">
                            <div class="wns-settings-row-label"><?php esc_html_e( 'Default Delivery Time (days)', 'woo-nalda-sync' ); ?></div>
                            <p class="wns-settings-row-desc"><?php esc_html_e( 'Default delivery time in days if not set per product.', 'woo-nalda-sync' ); ?></p>
                        </div>
                        <div class="wns-settings-row-control">
                            <input type="number" name="default_delivery_time" class="wns-form-input" value="<?php echo esc_attr( isset( $settings['default_delivery_time'] ) ? $settings['default_delivery_time'] : '3' ); ?>" min="0" max="60">
                        </div>
                    </div>

                    <div class="wns-settings-row">
                        <div class="wns-settings-row-info">
                            <div class="wns-settings-row-label"><?php esc_html_e( 'Return Period (days)', 'woo-nalda-sync' ); ?></div>
                            <p class="wns-settings-row-desc"><?php esc_html_e( 'Default return period in days.', 'woo-nalda-sync' ); ?></p>
                        </div>
                        <div class="wns-settings-row-control">
                            <input type="number" name="return_period" class="wns-form-input" value="<?php echo esc_attr( isset( $settings['return_period'] ) ? $settings['return_period'] : '14' ); ?>" min="0" max="90">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Order Import Settings -->
        <div class="wns-card">
            <div class="wns-card-header">
                <h2>
                    <span class="dashicons dashicons-clipboard"></span>
                    <?php esc_html_e( 'Order Import Settings', 'woo-nalda-sync' ); ?>
                </h2>
            </div>
            <div class="wns-card-body">
                <p class="wns-section-description"><?php esc_html_e( 'Configure how orders are imported from Nalda Marketplace to your WooCommerce store.', 'woo-nalda-sync' ); ?></p>
                
                <div class="wns-settings-section">
                    <div class="wns-settings-row">
                        <div class="wns-settings-row-info">
                            <div class="wns-settings-row-label"><?php esc_html_e( 'Enable Order Import', 'woo-nalda-sync' ); ?></div>
                            <p class="wns-settings-row-desc"><?php esc_html_e( 'Enable automatic order import from Nalda Marketplace.', 'woo-nalda-sync' ); ?></p>
                        </div>
                        <div class="wns-settings-row-control">
                            <label class="wns-toggle">
                                <input type="checkbox" name="order_import_enabled" value="yes" <?php checked( isset( $settings['order_import_enabled'] ) ? $settings['order_import_enabled'] : 'no', 'yes' ); ?>>
                                <span class="wns-toggle-slider"></span>
                            </label>
                        </div>
                    </div>

                    <div class="wns-settings-row">
                        <div class="wns-settings-row-info">
                            <div class="wns-settings-row-label"><?php esc_html_e( 'Import Schedule', 'woo-nalda-sync' ); ?></div>
                            <p class="wns-settings-row-desc"><?php esc_html_e( 'How often to check Nalda for new orders.', 'woo-nalda-sync' ); ?></p>
                        </div>
                        <div class="wns-settings-row-control">
                            <select name="order_import_schedule" class="wns-form-select">
                                <option value="every_15_minutes" <?php selected( isset( $settings['order_import_schedule'] ) ? $settings['order_import_schedule'] : 'hourly', 'every_15_minutes' ); ?>><?php esc_html_e( 'Every 15 minutes', 'woo-nalda-sync' ); ?></option>
                                <option value="every_30_minutes" <?php selected( isset( $settings['order_import_schedule'] ) ? $settings['order_import_schedule'] : 'hourly', 'every_30_minutes' ); ?>><?php esc_html_e( 'Every 30 minutes', 'woo-nalda-sync' ); ?></option>
                                <option value="hourly" <?php selected( isset( $settings['order_import_schedule'] ) ? $settings['order_import_schedule'] : 'hourly', 'hourly' ); ?>><?php esc_html_e( 'Hourly', 'woo-nalda-sync' ); ?></option>
                                <option value="every_6_hours" <?php selected( isset( $settings['order_import_schedule'] ) ? $settings['order_import_schedule'] : 'hourly', 'every_6_hours' ); ?>><?php esc_html_e( 'Every 6 hours', 'woo-nalda-sync' ); ?></option>
                                <option value="every_12_hours" <?php selected( isset( $settings['order_import_schedule'] ) ? $settings['order_import_schedule'] : 'hourly', 'every_12_hours' ); ?>><?php esc_html_e( 'Every 12 hours', 'woo-nalda-sync' ); ?></option>
                                <option value="daily" <?php selected( isset( $settings['order_import_schedule'] ) ? $settings['order_import_schedule'] : 'hourly', 'daily' ); ?>><?php esc_html_e( 'Daily', 'woo-nalda-sync' ); ?></option>
                            </select>
                        </div>
                    </div>

                    <div class="wns-settings-row">
                        <div class="wns-settings-row-info">
                            <div class="wns-settings-row-label"><?php esc_html_e( 'Order Import Range', 'woo-nalda-sync' ); ?></div>
                            <p class="wns-settings-row-desc"><?php esc_html_e( 'Date range for importing orders from Nalda. Determines how far back to look for orders.', 'woo-nalda-sync' ); ?></p>
                        </div>
                        <div class="wns-settings-row-control">
                            <select name="order_import_range" class="wns-form-select">
                                <option value="today" <?php selected( isset( $settings['order_import_range'] ) ? $settings['order_import_range'] : 'today', 'today' ); ?>><?php esc_html_e( 'Today', 'woo-nalda-sync' ); ?></option>
                                <option value="yesterday" <?php selected( isset( $settings['order_import_range'] ) ? $settings['order_import_range'] : 'today', 'yesterday' ); ?>><?php esc_html_e( 'Yesterday', 'woo-nalda-sync' ); ?></option>
                                <option value="current-month" <?php selected( isset( $settings['order_import_range'] ) ? $settings['order_import_range'] : 'today', 'current-month' ); ?>><?php esc_html_e( 'Current Month', 'woo-nalda-sync' ); ?></option>
                                <option value="3m" <?php selected( isset( $settings['order_import_range'] ) ? $settings['order_import_range'] : 'today', '3m' ); ?>><?php esc_html_e( 'Last 3 Months', 'woo-nalda-sync' ); ?></option>
                                <option value="6m" <?php selected( isset( $settings['order_import_range'] ) ? $settings['order_import_range'] : 'today', '6m' ); ?>><?php esc_html_e( 'Last 6 Months', 'woo-nalda-sync' ); ?></option>
                            </select>
                        </div>
                    </div>

                    <div class="wns-settings-row">
                        <div class="wns-settings-row-info">
                            <div class="wns-settings-row-label"><?php esc_html_e( 'Reduce Stock on Import', 'woo-nalda-sync' ); ?></div>
                            <p class="wns-settings-row-desc"><?php esc_html_e( 'Automatically reduce WooCommerce stock quantities when orders are imported from Nalda.', 'woo-nalda-sync' ); ?></p>
                        </div>
                        <div class="wns-settings-row-control">
                            <label class="wns-toggle">
                                <input type="checkbox" name="order_reduce_stock" value="yes" <?php checked( isset( $settings['order_reduce_stock'] ) ? $settings['order_reduce_stock'] : 'yes', 'yes' ); ?>>
                                <span class="wns-toggle-slider"></span>
                            </label>
                        </div>
                    </div>

                </div>
            </div>
        </div>

        <!-- Order Status Export Settings -->
        <div class="wns-card">
            <div class="wns-card-header">
                <h2>
                    <span class="dashicons dashicons-update-alt"></span>
                    <?php esc_html_e( 'Order Status Export Settings', 'woo-nalda-sync' ); ?>
                </h2>
            </div>
            <div class="wns-card-body">
                <p class="wns-section-description"><?php esc_html_e( 'Configure how order status updates are exported to Nalda Marketplace via CSV upload.', 'woo-nalda-sync' ); ?></p>
                
                <div class="wns-settings-section">
                    <div class="wns-settings-row">
                        <div class="wns-settings-row-info">
                            <div class="wns-settings-row-label"><?php esc_html_e( 'Enable Order Status Export', 'woo-nalda-sync' ); ?></div>
                            <p class="wns-settings-row-desc"><?php esc_html_e( 'Enable automatic order status export to Nalda Marketplace.', 'woo-nalda-sync' ); ?></p>
                        </div>
                        <div class="wns-settings-row-control">
                            <label class="wns-toggle">
                                <input type="checkbox" name="order_status_export_enabled" value="yes" <?php checked( isset( $settings['order_status_export_enabled'] ) ? $settings['order_status_export_enabled'] : 'no', 'yes' ); ?>>
                                <span class="wns-toggle-slider"></span>
                            </label>
                        </div>
                    </div>

                    <div class="wns-settings-row">
                        <div class="wns-settings-row-info">
                            <div class="wns-settings-row-label"><?php esc_html_e( 'Export Schedule', 'woo-nalda-sync' ); ?></div>
                            <p class="wns-settings-row-desc"><?php esc_html_e( 'How often should the order status export run automatically.', 'woo-nalda-sync' ); ?></p>
                        </div>
                        <div class="wns-settings-row-control">
                            <select name="order_status_export_schedule" class="wns-form-select">
                                <option value="every_15_minutes" <?php selected( isset( $settings['order_status_export_schedule'] ) ? $settings['order_status_export_schedule'] : 'hourly', 'every_15_minutes' ); ?>><?php esc_html_e( 'Every 15 minutes', 'woo-nalda-sync' ); ?></option>
                                <option value="every_30_minutes" <?php selected( isset( $settings['order_status_export_schedule'] ) ? $settings['order_status_export_schedule'] : 'hourly', 'every_30_minutes' ); ?>><?php esc_html_e( 'Every 30 minutes', 'woo-nalda-sync' ); ?></option>
                                <option value="hourly" <?php selected( isset( $settings['order_status_export_schedule'] ) ? $settings['order_status_export_schedule'] : 'hourly', 'hourly' ); ?>><?php esc_html_e( 'Hourly', 'woo-nalda-sync' ); ?></option>
                                <option value="every_6_hours" <?php selected( isset( $settings['order_status_export_schedule'] ) ? $settings['order_status_export_schedule'] : 'hourly', 'every_6_hours' ); ?>><?php esc_html_e( 'Every 6 hours', 'woo-nalda-sync' ); ?></option>
                                <option value="every_12_hours" <?php selected( isset( $settings['order_status_export_schedule'] ) ? $settings['order_status_export_schedule'] : 'hourly', 'every_12_hours' ); ?>><?php esc_html_e( 'Every 12 hours', 'woo-nalda-sync' ); ?></option>
                                <option value="daily" <?php selected( isset( $settings['order_status_export_schedule'] ) ? $settings['order_status_export_schedule'] : 'hourly', 'daily' ); ?>><?php esc_html_e( 'Daily', 'woo-nalda-sync' ); ?></option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Nalda API Settings -->
        <div class="wns-card">
            <div class="wns-card-header">
                <h2>
                    <span class="dashicons dashicons-rest-api"></span>
                    <?php esc_html_e( 'Nalda API Settings', 'woo-nalda-sync' ); ?>
                </h2>
                <button type="button" class="wns-btn wns-btn-secondary wns-btn-sm" id="wns-test-nalda-api">
                    <span class="dashicons dashicons-admin-plugins"></span>
                    <?php esc_html_e( 'Test Connection', 'woo-nalda-sync' ); ?>
                </button>
            </div>
            <div class="wns-card-body">
                <p class="wns-section-description"><?php esc_html_e( 'Configure your Nalda Marketplace API settings. Get your API key from the Nalda Seller Portal (Orders → Settings).', 'woo-nalda-sync' ); ?></p>
                
                <div class="wns-settings-section">
                    <div class="wns-settings-row">
                        <div class="wns-settings-row-info">
                            <div class="wns-settings-row-label"><?php esc_html_e( 'Nalda API Key', 'woo-nalda-sync' ); ?></div>
                            <p class="wns-settings-row-desc"><?php esc_html_e( 'API key obtained from the Nalda Seller Portal (Orders → Settings).', 'woo-nalda-sync' ); ?></p>
                        </div>
                        <div class="wns-settings-row-control">
                            <input type="password" name="nalda_api_key" id="nalda_api_key" class="wns-form-input" value="<?php echo esc_attr( isset( $settings['nalda_api_key'] ) ? $settings['nalda_api_key'] : '' ); ?>" placeholder="<?php esc_attr_e( 'Enter API key', 'woo-nalda-sync' ); ?>">
                        </div>
                    </div>

                    <div class="wns-settings-row">
                        <div class="wns-settings-row-info">
                            <div class="wns-settings-row-label"><?php esc_html_e( 'Nalda API URL', 'woo-nalda-sync' ); ?></div>
                            <p class="wns-settings-row-desc"><?php esc_html_e( 'Leave default unless instructed otherwise.', 'woo-nalda-sync' ); ?></p>
                        </div>
                        <div class="wns-settings-row-control">
                            <input type="url" name="nalda_api_url" id="nalda_api_url" class="wns-form-input" value="<?php echo esc_attr( isset( $settings['nalda_api_url'] ) ? $settings['nalda_api_url'] : 'https://sellers-api.nalda.com' ); ?>">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Advanced Settings -->
        <div class="wns-card">
            <div class="wns-card-header">
                <h2>
                    <span class="dashicons dashicons-admin-tools"></span>
                    <?php esc_html_e( 'Advanced Settings', 'woo-nalda-sync' ); ?>
                </h2>
            </div>
            <div class="wns-card-body">
                <div class="wns-settings-section">
                    <div class="wns-settings-row">
                        <div class="wns-settings-row-info">
                            <div class="wns-settings-row-label"><?php esc_html_e( 'Enable Logging', 'woo-nalda-sync' ); ?></div>
                            <p class="wns-settings-row-desc"><?php esc_html_e( 'Log sync activities for debugging purposes.', 'woo-nalda-sync' ); ?></p>
                        </div>
                        <div class="wns-settings-row-control">
                            <label class="wns-toggle">
                                <input type="checkbox" name="log_enabled" value="yes" <?php checked( isset( $settings['log_enabled'] ) ? $settings['log_enabled'] : 'yes', 'yes' ); ?>>
                                <span class="wns-toggle-slider"></span>
                            </label>
                        </div>
                    </div>

                    <div class="wns-settings-row">
                        <div class="wns-settings-row-info">
                            <div class="wns-settings-row-label"><?php esc_html_e( 'Notification Email', 'woo-nalda-sync' ); ?></div>
                            <p class="wns-settings-row-desc"><?php esc_html_e( 'Email address for sync notifications and alerts.', 'woo-nalda-sync' ); ?></p>
                        </div>
                        <div class="wns-settings-row-control">
                            <input type="email" name="notification_email" class="wns-form-input" value="<?php echo esc_attr( isset( $settings['notification_email'] ) ? $settings['notification_email'] : get_option( 'admin_email' ) ); ?>" placeholder="<?php esc_attr_e( 'Enter email address', 'woo-nalda-sync' ); ?>">
                        </div>
                    </div>

                    <div class="wns-settings-row">
                        <div class="wns-settings-row-info">
                            <div class="wns-settings-row-label"><?php esc_html_e( 'Delivery Note Logo', 'woo-nalda-sync' ); ?></div>
                            <p class="wns-settings-row-desc"><?php esc_html_e( 'Custom logo to display on delivery note PDFs.', 'woo-nalda-sync' ); ?></p>
                        </div>
                        <div class="wns-settings-row-control">
                            <?php
                            $logo_id = isset( $settings['delivery_note_logo_id'] ) ? absint( $settings['delivery_note_logo_id'] ) : 0;
                            $logo_url = $logo_id ? wp_get_attachment_image_url( $logo_id, 'medium' ) : '';
                            ?>
                            <div class="wns-image-upload-wrapper">
                                <input type="hidden" name="delivery_note_logo_id" id="delivery_note_logo_id" value="<?php echo esc_attr( $logo_id ); ?>">
                                <div class="wns-image-preview" id="delivery_note_logo_preview" style="<?php echo $logo_url ? '' : 'display:none;'; ?>">
                                    <?php if ( $logo_url ) : ?>
                                        <img src="<?php echo esc_url( $logo_url ); ?>" alt="">
                                    <?php endif; ?>
                                </div>
                                <div class="wns-image-buttons">
                                    <button type="button" class="wns-btn wns-btn-secondary wns-btn-sm" id="delivery_note_logo_upload">
                                        <span class="dashicons dashicons-upload"></span>
                                        <?php esc_html_e( 'Upload Image', 'woo-nalda-sync' ); ?>
                                    </button>
                                    <button type="button" class="wns-btn wns-btn-secondary wns-btn-sm" id="delivery_note_logo_remove" style="<?php echo $logo_url ? '' : 'display:none;'; ?>">
                                        <span class="dashicons dashicons-trash"></span>
                                        <?php esc_html_e( 'Remove', 'woo-nalda-sync' ); ?>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Save Button -->
        <div class="wns-card">
            <div class="wns-card-footer">
                <button type="submit" name="woo_nalda_sync_save_settings" class="wns-btn wns-btn-primary wns-btn-lg">
                    <span class="dashicons dashicons-saved"></span>
                    <?php esc_html_e( 'Save Settings', 'woo-nalda-sync' ); ?>
                </button>
            </div>
        </div>
    </form>
</div>
