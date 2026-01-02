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

        <!-- Tabs -->
        <div class="wns-tabs">
            <button type="button" class="wns-tab active" data-tab="tab-general">
                <span class="dashicons dashicons-admin-generic"></span>
                <?php esc_html_e( 'General', 'woo-nalda-sync' ); ?>
            </button>
            <button type="button" class="wns-tab" data-tab="tab-sync">
                <span class="dashicons dashicons-update"></span>
                <?php esc_html_e( 'Sync Settings', 'woo-nalda-sync' ); ?>
            </button>
            <button type="button" class="wns-tab" data-tab="tab-advanced">
                <span class="dashicons dashicons-admin-tools"></span>
                <?php esc_html_e( 'Advanced', 'woo-nalda-sync' ); ?>
            </button>
        </div>

        <!-- General Tab -->
        <div id="tab-general" class="wns-tab-content active">
            <div class="wns-card">
                <div class="wns-card-header">
                    <h2>
                        <span class="dashicons dashicons-admin-settings"></span>
                        <?php esc_html_e( 'General Settings', 'woo-nalda-sync' ); ?>
                    </h2>
                </div>
                <div class="wns-card-body">
                    <div class="wns-settings-section">
                        <div class="wns-settings-row">
                            <div class="wns-settings-row-info">
                                <div class="wns-settings-row-label"><?php esc_html_e( 'Enable Sync', 'woo-nalda-sync' ); ?></div>
                                <p class="wns-settings-row-desc"><?php esc_html_e( 'Enable or disable the sync functionality globally.', 'woo-nalda-sync' ); ?></p>
                            </div>
                            <div class="wns-settings-row-control">
                                <label class="wns-toggle">
                                    <input type="checkbox" name="sync_enabled" value="yes" <?php checked( isset( $settings['sync_enabled'] ) ? $settings['sync_enabled'] : 'yes', 'yes' ); ?>>
                                    <span class="wns-toggle-slider"></span>
                                </label>
                            </div>
                        </div>

                        <div class="wns-settings-row">
                            <div class="wns-settings-row-info">
                                <div class="wns-settings-row-label"><?php esc_html_e( 'Sync Interval', 'woo-nalda-sync' ); ?></div>
                                <p class="wns-settings-row-desc"><?php esc_html_e( 'How often should the sync run automatically (in minutes).', 'woo-nalda-sync' ); ?></p>
                            </div>
                            <div class="wns-settings-row-control">
                                <select name="sync_interval" class="wns-form-select">
                                    <option value="5" <?php selected( isset( $settings['sync_interval'] ) ? $settings['sync_interval'] : '15', '5' ); ?>><?php esc_html_e( 'Every 5 minutes', 'woo-nalda-sync' ); ?></option>
                                    <option value="15" <?php selected( isset( $settings['sync_interval'] ) ? $settings['sync_interval'] : '15', '15' ); ?>><?php esc_html_e( 'Every 15 minutes', 'woo-nalda-sync' ); ?></option>
                                    <option value="30" <?php selected( isset( $settings['sync_interval'] ) ? $settings['sync_interval'] : '15', '30' ); ?>><?php esc_html_e( 'Every 30 minutes', 'woo-nalda-sync' ); ?></option>
                                    <option value="60" <?php selected( isset( $settings['sync_interval'] ) ? $settings['sync_interval'] : '15', '60' ); ?>><?php esc_html_e( 'Every hour', 'woo-nalda-sync' ); ?></option>
                                    <option value="360" <?php selected( isset( $settings['sync_interval'] ) ? $settings['sync_interval'] : '15', '360' ); ?>><?php esc_html_e( 'Every 6 hours', 'woo-nalda-sync' ); ?></option>
                                    <option value="720" <?php selected( isset( $settings['sync_interval'] ) ? $settings['sync_interval'] : '15', '720' ); ?>><?php esc_html_e( 'Every 12 hours', 'woo-nalda-sync' ); ?></option>
                                    <option value="1440" <?php selected( isset( $settings['sync_interval'] ) ? $settings['sync_interval'] : '15', '1440' ); ?>><?php esc_html_e( 'Once daily', 'woo-nalda-sync' ); ?></option>
                                </select>
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
        </div>

        <!-- Sync Settings Tab -->
        <div id="tab-sync" class="wns-tab-content">
            <div class="wns-card">
                <div class="wns-card-header">
                    <h2>
                        <span class="dashicons dashicons-update"></span>
                        <?php esc_html_e( 'Sync Configuration', 'woo-nalda-sync' ); ?>
                    </h2>
                </div>
                <div class="wns-card-body">
                    <div class="wns-settings-section">
                        <h4 class="wns-settings-section-title"><?php esc_html_e( 'Data Sync Options', 'woo-nalda-sync' ); ?></h4>

                        <div class="wns-settings-row">
                            <div class="wns-settings-row-info">
                                <div class="wns-settings-row-label"><?php esc_html_e( 'Sync Products', 'woo-nalda-sync' ); ?></div>
                                <p class="wns-settings-row-desc"><?php esc_html_e( 'Sync product data including title, description, and images.', 'woo-nalda-sync' ); ?></p>
                            </div>
                            <div class="wns-settings-row-control">
                                <label class="wns-toggle">
                                    <input type="checkbox" name="sync_products" value="yes" <?php checked( isset( $settings['sync_products'] ) ? $settings['sync_products'] : 'yes', 'yes' ); ?>>
                                    <span class="wns-toggle-slider"></span>
                                </label>
                            </div>
                        </div>

                        <div class="wns-settings-row">
                            <div class="wns-settings-row-info">
                                <div class="wns-settings-row-label"><?php esc_html_e( 'Sync Orders', 'woo-nalda-sync' ); ?></div>
                                <p class="wns-settings-row-desc"><?php esc_html_e( 'Sync order data to keep your systems in sync.', 'woo-nalda-sync' ); ?></p>
                            </div>
                            <div class="wns-settings-row-control">
                                <label class="wns-toggle">
                                    <input type="checkbox" name="sync_orders" value="yes" <?php checked( isset( $settings['sync_orders'] ) ? $settings['sync_orders'] : 'yes', 'yes' ); ?>>
                                    <span class="wns-toggle-slider"></span>
                                </label>
                            </div>
                        </div>


                    </div>
                </div>
            </div>
        </div>

        <!-- Advanced Tab -->
        <div id="tab-advanced" class="wns-tab-content">
            <div class="wns-card">
                <div class="wns-card-header">
                    <h2>
                        <span class="dashicons dashicons-admin-tools"></span>
                        <?php esc_html_e( 'Advanced Settings', 'woo-nalda-sync' ); ?>
                    </h2>
                </div>
                <div class="wns-card-body">
                    <div class="wns-settings-section">
                        <h4 class="wns-settings-section-title"><?php esc_html_e( 'Developer Options', 'woo-nalda-sync' ); ?></h4>

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
                                <div class="wns-settings-row-label"><?php esc_html_e( 'Enable Webhooks', 'woo-nalda-sync' ); ?></div>
                                <p class="wns-settings-row-desc"><?php esc_html_e( 'Enable real-time webhooks for instant sync.', 'woo-nalda-sync' ); ?></p>
                            </div>
                            <div class="wns-settings-row-control">
                                <label class="wns-toggle">
                                    <input type="checkbox" name="webhook_enabled" value="yes" <?php checked( isset( $settings['webhook_enabled'] ) ? $settings['webhook_enabled'] : 'no', 'yes' ); ?>>
                                    <span class="wns-toggle-slider"></span>
                                </label>
                            </div>
                        </div>

                        <div class="wns-settings-row">
                            <div class="wns-settings-row-info">
                                <div class="wns-settings-row-label"><?php esc_html_e( 'API Timeout', 'woo-nalda-sync' ); ?></div>
                                <p class="wns-settings-row-desc"><?php esc_html_e( 'Maximum time (in seconds) to wait for API responses.', 'woo-nalda-sync' ); ?></p>
                            </div>
                            <div class="wns-settings-row-control">
                                <select name="api_timeout" class="wns-form-select">
                                    <option value="15" <?php selected( isset( $settings['api_timeout'] ) ? $settings['api_timeout'] : '30', '15' ); ?>><?php esc_html_e( '15 seconds', 'woo-nalda-sync' ); ?></option>
                                    <option value="30" <?php selected( isset( $settings['api_timeout'] ) ? $settings['api_timeout'] : '30', '30' ); ?>><?php esc_html_e( '30 seconds', 'woo-nalda-sync' ); ?></option>
                                    <option value="60" <?php selected( isset( $settings['api_timeout'] ) ? $settings['api_timeout'] : '30', '60' ); ?>><?php esc_html_e( '60 seconds', 'woo-nalda-sync' ); ?></option>
                                    <option value="120" <?php selected( isset( $settings['api_timeout'] ) ? $settings['api_timeout'] : '30', '120' ); ?>><?php esc_html_e( '120 seconds', 'woo-nalda-sync' ); ?></option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Danger Zone -->
            <div class="wns-card">
                <div class="wns-card-header">
                    <h2 style="color: var(--wns-danger);">
                        <span class="dashicons dashicons-warning"></span>
                        <?php esc_html_e( 'Danger Zone', 'woo-nalda-sync' ); ?>
                    </h2>
                </div>
                <div class="wns-card-body">
                    <div class="wns-settings-row">
                        <div class="wns-settings-row-info">
                            <div class="wns-settings-row-label"><?php esc_html_e( 'Reset Plugin Settings', 'woo-nalda-sync' ); ?></div>
                            <p class="wns-settings-row-desc"><?php esc_html_e( 'Reset all settings to their default values. This cannot be undone.', 'woo-nalda-sync' ); ?></p>
                        </div>
                        <div class="wns-settings-row-control">
                            <button type="button" class="wns-btn wns-btn-danger wns-btn-sm" id="wns-reset-settings" disabled>
                                <span class="dashicons dashicons-trash"></span>
                                <?php esc_html_e( 'Reset Settings', 'woo-nalda-sync' ); ?>
                            </button>
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
