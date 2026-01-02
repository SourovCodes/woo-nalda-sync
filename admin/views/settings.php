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

        <!-- Export Settings -->
        <div class="wns-card">
            <div class="wns-card-header">
                <h2>
                    <span class="dashicons dashicons-media-spreadsheet"></span>
                    <?php esc_html_e( 'Export Settings', 'woo-nalda-sync' ); ?>
                </h2>
            </div>
            <div class="wns-card-body">
                <p class="wns-section-description"><?php esc_html_e( 'Configure how and when the CSV export should be generated.', 'woo-nalda-sync' ); ?></p>
                
                <div class="wns-settings-section">
                    <div class="wns-settings-row">
                        <div class="wns-settings-row-info">
                            <div class="wns-settings-row-label"><?php esc_html_e( 'Sync Schedule', 'woo-nalda-sync' ); ?></div>
                            <p class="wns-settings-row-desc"><?php esc_html_e( 'How often should the sync run automatically.', 'woo-nalda-sync' ); ?></p>
                        </div>
                        <div class="wns-settings-row-control">
                            <select name="product_sync_schedule" class="wns-form-select">
                                <option value="every_15_minutes" <?php selected( isset( $settings['product_sync_schedule'] ) ? $settings['product_sync_schedule'] : 'hourly', 'every_15_minutes' ); ?>><?php esc_html_e( 'Every 15 minutes', 'woo-nalda-sync' ); ?></option>
                                <option value="every_30_minutes" <?php selected( isset( $settings['product_sync_schedule'] ) ? $settings['product_sync_schedule'] : 'hourly', 'every_30_minutes' ); ?>><?php esc_html_e( 'Every 30 minutes', 'woo-nalda-sync' ); ?></option>
                                <option value="hourly" <?php selected( isset( $settings['product_sync_schedule'] ) ? $settings['product_sync_schedule'] : 'hourly', 'hourly' ); ?>><?php esc_html_e( 'Hourly', 'woo-nalda-sync' ); ?></option>
                                <option value="every_6_hours" <?php selected( isset( $settings['product_sync_schedule'] ) ? $settings['product_sync_schedule'] : 'hourly', 'every_6_hours' ); ?>><?php esc_html_e( 'Every 6 hours', 'woo-nalda-sync' ); ?></option>
                                <option value="every_12_hours" <?php selected( isset( $settings['product_sync_schedule'] ) ? $settings['product_sync_schedule'] : 'hourly', 'every_12_hours' ); ?>><?php esc_html_e( 'Every 12 hours', 'woo-nalda-sync' ); ?></option>
                                <option value="daily" <?php selected( isset( $settings['product_sync_schedule'] ) ? $settings['product_sync_schedule'] : 'hourly', 'daily' ); ?>><?php esc_html_e( 'Daily', 'woo-nalda-sync' ); ?></option>
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

        <!-- Sync Status -->
        <div class="wns-card">
            <div class="wns-card-header">
                <h2>
                    <span class="dashicons dashicons-controls-repeat"></span>
                    <?php esc_html_e( 'Sync Status', 'woo-nalda-sync' ); ?>
                </h2>
            </div>
            <div class="wns-card-body">
                <p class="wns-section-description"><?php esc_html_e( 'Enable or disable the automatic sync feature.', 'woo-nalda-sync' ); ?></p>
                
                <div class="wns-settings-section">
                    <div class="wns-settings-row">
                        <div class="wns-settings-row-info">
                            <div class="wns-settings-row-label"><?php esc_html_e( 'Enable Automatic Sync', 'woo-nalda-sync' ); ?></div>
                            <p class="wns-settings-row-desc"><?php esc_html_e( 'Enable or disable automatic scheduled sync.', 'woo-nalda-sync' ); ?></p>
                        </div>
                        <div class="wns-settings-row-control">
                            <label class="wns-toggle">
                                <input type="checkbox" name="product_sync_enabled" value="yes" <?php checked( isset( $settings['product_sync_enabled'] ) ? $settings['product_sync_enabled'] : 'no', 'yes' ); ?>>
                                <span class="wns-toggle-slider"></span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Order Sync Settings -->
        <div class="wns-card">
            <div class="wns-card-header">
                <h2>
                    <span class="dashicons dashicons-clipboard"></span>
                    <?php esc_html_e( 'Order Sync Settings', 'woo-nalda-sync' ); ?>
                </h2>
            </div>
            <div class="wns-card-body">
                <p class="wns-section-description"><?php esc_html_e( 'Configure how orders are imported from Nalda Marketplace to your WooCommerce store.', 'woo-nalda-sync' ); ?></p>
                
                <div class="wns-settings-section">
                    <div class="wns-settings-row">
                        <div class="wns-settings-row-info">
                            <div class="wns-settings-row-label"><?php esc_html_e( 'Enable Order Sync', 'woo-nalda-sync' ); ?></div>
                            <p class="wns-settings-row-desc"><?php esc_html_e( 'Enable automatic order import from Nalda Marketplace.', 'woo-nalda-sync' ); ?></p>
                        </div>
                        <div class="wns-settings-row-control">
                            <label class="wns-toggle">
                                <input type="checkbox" name="order_sync_enabled" value="yes" <?php checked( isset( $settings['order_sync_enabled'] ) ? $settings['order_sync_enabled'] : 'no', 'yes' ); ?>>
                                <span class="wns-toggle-slider"></span>
                            </label>
                        </div>
                    </div>

                    <div class="wns-settings-row">
                        <div class="wns-settings-row-info">
                            <div class="wns-settings-row-label"><?php esc_html_e( 'Order Sync Schedule', 'woo-nalda-sync' ); ?></div>
                            <p class="wns-settings-row-desc"><?php esc_html_e( 'How often to check Nalda for new orders.', 'woo-nalda-sync' ); ?></p>
                        </div>
                        <div class="wns-settings-row-control">
                            <select name="order_sync_schedule" class="wns-form-select">
                                <option value="every_15_minutes" <?php selected( isset( $settings['order_sync_schedule'] ) ? $settings['order_sync_schedule'] : 'hourly', 'every_15_minutes' ); ?>><?php esc_html_e( 'Every 15 minutes', 'woo-nalda-sync' ); ?></option>
                                <option value="every_30_minutes" <?php selected( isset( $settings['order_sync_schedule'] ) ? $settings['order_sync_schedule'] : 'hourly', 'every_30_minutes' ); ?>><?php esc_html_e( 'Every 30 minutes', 'woo-nalda-sync' ); ?></option>
                                <option value="hourly" <?php selected( isset( $settings['order_sync_schedule'] ) ? $settings['order_sync_schedule'] : 'hourly', 'hourly' ); ?>><?php esc_html_e( 'Hourly', 'woo-nalda-sync' ); ?></option>
                                <option value="every_6_hours" <?php selected( isset( $settings['order_sync_schedule'] ) ? $settings['order_sync_schedule'] : 'hourly', 'every_6_hours' ); ?>><?php esc_html_e( 'Every 6 hours', 'woo-nalda-sync' ); ?></option>
                                <option value="every_12_hours" <?php selected( isset( $settings['order_sync_schedule'] ) ? $settings['order_sync_schedule'] : 'hourly', 'every_12_hours' ); ?>><?php esc_html_e( 'Every 12 hours', 'woo-nalda-sync' ); ?></option>
                                <option value="daily" <?php selected( isset( $settings['order_sync_schedule'] ) ? $settings['order_sync_schedule'] : 'hourly', 'daily' ); ?>><?php esc_html_e( 'Daily', 'woo-nalda-sync' ); ?></option>
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
                            <input type="url" name="nalda_api_url" id="nalda_api_url" class="wns-form-input" value="<?php echo esc_attr( isset( $settings['nalda_api_url'] ) ? $settings['nalda_api_url'] : 'https://api.nalda.com' ); ?>">
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
