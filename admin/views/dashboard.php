<?php
/**
 * Dashboard Page View
 *
 * @package Woo_Nalda_Sync
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Get sync status.
$product_sync_status = isset( $settings['product_sync_enabled'] ) && 'yes' === $settings['product_sync_enabled'];
$order_sync_status   = isset( $settings['order_sync_enabled'] ) && 'yes' === $settings['order_sync_enabled'];

// Format dates.
$last_product_sync = isset( $stats['last_product_sync'] ) ? $stats['last_product_sync'] : null;
$last_order_sync   = isset( $stats['last_order_sync'] ) ? $stats['last_order_sync'] : null;
$products_synced   = isset( $stats['products_synced'] ) ? $stats['products_synced'] : 0;
$orders_synced     = isset( $stats['orders_synced'] ) ? $stats['orders_synced'] : 0;

// Format next sync times.
$next_product_sync = isset( $next_sync_times['product_sync'] ) && $next_sync_times['product_sync'] ? $next_sync_times['product_sync'] : null;
$next_order_sync   = isset( $next_sync_times['order_sync'] ) && $next_sync_times['order_sync'] ? $next_sync_times['order_sync'] : null;
?>

<div class="wns-wrap">
    <!-- Header -->
    <div class="wns-header">
        <div class="wns-header-left">
            <div class="wns-logo">
                <span class="dashicons dashicons-update"></span>
            </div>
            <div>
                <h1><?php esc_html_e( 'WooCommerce Nalda Sync', 'woo-nalda-sync' ); ?></h1>
                <p><?php esc_html_e( 'Sync your WooCommerce store with Nalda effortlessly.', 'woo-nalda-sync' ); ?></p>
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
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=woo-nalda-sync-license' ) ); ?>" class="wns-btn wns-btn-primary wns-btn-sm" style="margin-left: 10px;">
                        <?php esc_html_e( 'Activate License', 'woo-nalda-sync' ); ?>
                    </a>
                </p>
            </div>
        </div>
    <?php endif; ?>

    <!-- Stats Grid -->
    <div class="wns-grid wns-grid-4">
        <div class="wns-stat-card">
            <div class="wns-stat-icon primary">
                <span class="dashicons dashicons-cart"></span>
            </div>
            <div class="wns-stat-value"><?php echo esc_html( number_format_i18n( $products_synced ) ); ?></div>
            <div class="wns-stat-label"><?php esc_html_e( 'Products Synced', 'woo-nalda-sync' ); ?></div>
        </div>
        <div class="wns-stat-card">
            <div class="wns-stat-icon success">
                <span class="dashicons dashicons-clipboard"></span>
            </div>
            <div class="wns-stat-value"><?php echo esc_html( number_format_i18n( $orders_synced ) ); ?></div>
            <div class="wns-stat-label"><?php esc_html_e( 'Orders Imported', 'woo-nalda-sync' ); ?></div>
        </div>
        <div class="wns-stat-card">
            <div class="wns-stat-icon warning">
                <span class="dashicons dashicons-calendar-alt"></span>
            </div>
            <div class="wns-stat-value">
                <?php
                if ( $last_product_sync ) {
                    echo esc_html( human_time_diff( strtotime( $last_product_sync ), current_time( 'timestamp' ) ) );
                } else {
                    echo '--';
                }
                ?>
            </div>
            <div class="wns-stat-label"><?php esc_html_e( 'Last Product Sync', 'woo-nalda-sync' ); ?></div>
        </div>
        <div class="wns-stat-card">
            <div class="wns-stat-icon info">
                <span class="dashicons dashicons-yes-alt"></span>
            </div>
            <div class="wns-stat-value">
                <?php
                if ( $product_sync_status && $order_sync_status ) {
                    echo esc_html__( 'All Active', 'woo-nalda-sync' );
                } elseif ( $product_sync_status || $order_sync_status ) {
                    echo esc_html__( 'Partial', 'woo-nalda-sync' );
                } else {
                    echo esc_html__( 'Inactive', 'woo-nalda-sync' );
                }
                ?>
            </div>
            <div class="wns-stat-label"><?php esc_html_e( 'Auto Sync', 'woo-nalda-sync' ); ?></div>
        </div>
    </div>

    <div class="wns-grid wns-grid-2">
        <!-- Quick Actions -->
        <div class="wns-card">
            <div class="wns-card-header">
                <h2>
                    <span class="dashicons dashicons-superhero"></span>
                    <?php esc_html_e( 'Quick Actions', 'woo-nalda-sync' ); ?>
                </h2>
            </div>
            <div class="wns-card-body">
                <div class="wns-quick-actions" style="grid-template-columns: 1fr;">
                    <?php if ( $is_licensed ) : ?>
                        <button type="button" class="wns-quick-action wns-sync-btn" id="wns-run-product-sync">
                            <span class="dashicons dashicons-upload"></span>
                            <span><?php esc_html_e( 'Sync Products Now', 'woo-nalda-sync' ); ?></span>
                        </button>
                        <button type="button" class="wns-quick-action wns-sync-btn" id="wns-run-order-sync">
                            <span class="dashicons dashicons-download"></span>
                            <span><?php esc_html_e( 'Import Orders Now', 'woo-nalda-sync' ); ?></span>
                        </button>
                    <?php else : ?>
                        <div class="wns-quick-action" style="opacity: 0.5; cursor: not-allowed;">
                            <span class="dashicons dashicons-upload"></span>
                            <span><?php esc_html_e( 'Sync Products Now', 'woo-nalda-sync' ); ?></span>
                        </div>
                        <div class="wns-quick-action" style="opacity: 0.5; cursor: not-allowed;">
                            <span class="dashicons dashicons-download"></span>
                            <span><?php esc_html_e( 'Import Orders Now', 'woo-nalda-sync' ); ?></span>
                        </div>
                    <?php endif; ?>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=woo-nalda-sync-settings' ) ); ?>" class="wns-quick-action">
                        <span class="dashicons dashicons-admin-generic"></span>
                        <span><?php esc_html_e( 'Configure Settings', 'woo-nalda-sync' ); ?></span>
                    </a>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=woo-nalda-sync-license' ) ); ?>" class="wns-quick-action">
                        <span class="dashicons dashicons-admin-network"></span>
                        <span><?php esc_html_e( 'Manage License', 'woo-nalda-sync' ); ?></span>
                    </a>
                </div>
            </div>
        </div>

        <!-- Sync Schedule Info -->
        <div class="wns-card">
            <div class="wns-card-header">
                <h2>
                    <span class="dashicons dashicons-clock"></span>
                    <?php esc_html_e( 'Sync Schedule', 'woo-nalda-sync' ); ?>
                </h2>
            </div>
            <div class="wns-card-body">
                <div class="wns-schedule-info">
                    <div class="wns-schedule-item">
                        <div class="wns-schedule-status <?php echo $product_sync_status ? 'active' : 'inactive'; ?>">
                            <span class="dashicons dashicons-<?php echo $product_sync_status ? 'yes' : 'no'; ?>"></span>
                        </div>
                        <div class="wns-schedule-details">
                            <div class="wns-schedule-label"><?php esc_html_e( 'Product Sync', 'woo-nalda-sync' ); ?></div>
                            <div class="wns-schedule-value">
                                <?php if ( $product_sync_status && $next_product_sync ) : ?>
                                    <?php
                                    printf(
                                        /* translators: %s: Time until next sync */
                                        esc_html__( 'Next sync in %s', 'woo-nalda-sync' ),
                                        human_time_diff( current_time( 'timestamp' ), $next_product_sync )
                                    );
                                    ?>
                                <?php elseif ( $product_sync_status ) : ?>
                                    <?php esc_html_e( 'Scheduled', 'woo-nalda-sync' ); ?>
                                <?php else : ?>
                                    <?php esc_html_e( 'Disabled', 'woo-nalda-sync' ); ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="wns-schedule-item">
                        <div class="wns-schedule-status <?php echo $order_sync_status ? 'active' : 'inactive'; ?>">
                            <span class="dashicons dashicons-<?php echo $order_sync_status ? 'yes' : 'no'; ?>"></span>
                        </div>
                        <div class="wns-schedule-details">
                            <div class="wns-schedule-label"><?php esc_html_e( 'Order Sync', 'woo-nalda-sync' ); ?></div>
                            <div class="wns-schedule-value">
                                <?php if ( $order_sync_status && $next_order_sync ) : ?>
                                    <?php
                                    printf(
                                        /* translators: %s: Time until next sync */
                                        esc_html__( 'Next sync in %s', 'woo-nalda-sync' ),
                                        human_time_diff( current_time( 'timestamp' ), $next_order_sync )
                                    );
                                    ?>
                                <?php elseif ( $order_sync_status ) : ?>
                                    <?php esc_html_e( 'Scheduled', 'woo-nalda-sync' ); ?>
                                <?php else : ?>
                                    <?php esc_html_e( 'Disabled', 'woo-nalda-sync' ); ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="wns-grid wns-grid-2">
        <!-- Getting Started -->
        <div class="wns-card">
            <div class="wns-card-header">
                <h2>
                    <span class="dashicons dashicons-welcome-learn-more"></span>
                    <?php esc_html_e( 'Getting Started', 'woo-nalda-sync' ); ?>
                </h2>
            </div>
            <div class="wns-card-body">
                <div class="wns-settings-section" style="margin-bottom: 0;">
                    <div class="wns-settings-row" style="padding-top: 0;">
                        <div class="wns-settings-row-info" style="padding-right: 0;">
                            <div class="wns-settings-row-label wns-step-label">
                                <?php if ( $is_licensed ) : ?>
                                    <span class="dashicons dashicons-yes-alt wns-step-done"></span>
                                <?php else : ?>
                                    <span class="wns-step-number">1</span>
                                <?php endif; ?>
                                <?php esc_html_e( 'Activate your license', 'woo-nalda-sync' ); ?>
                            </div>
                            <p class="wns-settings-row-desc wns-step-desc"><?php esc_html_e( 'Enter your license key to unlock all features.', 'woo-nalda-sync' ); ?></p>
                        </div>
                    </div>
                    <div class="wns-settings-row">
                        <div class="wns-settings-row-info" style="padding-right: 0;">
                            <div class="wns-settings-row-label wns-step-label">
                                <?php if ( ! empty( $settings['sftp_host'] ) && ! empty( $settings['sftp_username'] ) ) : ?>
                                    <span class="dashicons dashicons-yes-alt wns-step-done"></span>
                                <?php else : ?>
                                    <span class="wns-step-number">2</span>
                                <?php endif; ?>
                                <?php esc_html_e( 'Configure SFTP settings', 'woo-nalda-sync' ); ?>
                            </div>
                            <p class="wns-settings-row-desc wns-step-desc"><?php esc_html_e( 'Set up your SFTP connection for product exports.', 'woo-nalda-sync' ); ?></p>
                        </div>
                    </div>
                    <div class="wns-settings-row">
                        <div class="wns-settings-row-info" style="padding-right: 0;">
                            <div class="wns-settings-row-label wns-step-label">
                                <?php if ( ! empty( $settings['nalda_api_key'] ) ) : ?>
                                    <span class="dashicons dashicons-yes-alt wns-step-done"></span>
                                <?php else : ?>
                                    <span class="wns-step-number">3</span>
                                <?php endif; ?>
                                <?php esc_html_e( 'Connect to Nalda API', 'woo-nalda-sync' ); ?>
                            </div>
                            <p class="wns-settings-row-desc wns-step-desc"><?php esc_html_e( 'Add your Nalda API key for order imports.', 'woo-nalda-sync' ); ?></p>
                        </div>
                    </div>
                    <div class="wns-settings-row" style="border-bottom: none; padding-bottom: 0;">
                        <div class="wns-settings-row-info" style="padding-right: 0;">
                            <div class="wns-settings-row-label wns-step-label">
                                <?php if ( $product_sync_status || $order_sync_status ) : ?>
                                    <span class="dashicons dashicons-yes-alt wns-step-done"></span>
                                <?php else : ?>
                                    <span class="wns-step-number">4</span>
                                <?php endif; ?>
                                <?php esc_html_e( 'Enable automatic sync', 'woo-nalda-sync' ); ?>
                            </div>
                            <p class="wns-settings-row-desc wns-step-desc"><?php esc_html_e( 'Turn on scheduled syncing to keep data up to date.', 'woo-nalda-sync' ); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="wns-card">
            <div class="wns-card-header">
                <h2>
                    <span class="dashicons dashicons-backup"></span>
                    <?php esc_html_e( 'Recent Activity', 'woo-nalda-sync' ); ?>
                </h2>
            </div>
            <div class="wns-card-body">
                <?php if ( $last_product_sync || $last_order_sync ) : ?>
                    <div class="wns-activity-list">
                        <?php if ( $last_product_sync ) : ?>
                            <div class="wns-activity-item">
                                <div class="wns-activity-icon success">
                                    <span class="dashicons dashicons-upload"></span>
                                </div>
                                <div class="wns-activity-content">
                                    <div class="wns-activity-title"><?php esc_html_e( 'Product Sync Completed', 'woo-nalda-sync' ); ?></div>
                                    <div class="wns-activity-meta">
                                        <?php
                                        printf(
                                            /* translators: 1: Number of products, 2: Time ago */
                                            esc_html__( '%1$d products exported • %2$s ago', 'woo-nalda-sync' ),
                                            $products_synced,
                                            human_time_diff( strtotime( $last_product_sync ), current_time( 'timestamp' ) )
                                        );
                                        ?>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                        <?php if ( $last_order_sync ) : ?>
                            <div class="wns-activity-item">
                                <div class="wns-activity-icon primary">
                                    <span class="dashicons dashicons-download"></span>
                                </div>
                                <div class="wns-activity-content">
                                    <div class="wns-activity-title"><?php esc_html_e( 'Order Sync Completed', 'woo-nalda-sync' ); ?></div>
                                    <div class="wns-activity-meta">
                                        <?php
                                        printf(
                                            /* translators: 1: Number of orders, 2: Time ago */
                                            esc_html__( '%1$d orders imported • %2$s ago', 'woo-nalda-sync' ),
                                            $orders_synced,
                                            human_time_diff( strtotime( $last_order_sync ), current_time( 'timestamp' ) )
                                        );
                                        ?>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php else : ?>
                    <div class="wns-empty-state">
                        <div class="wns-empty-state-icon">
                            <span class="dashicons dashicons-clock"></span>
                        </div>
                        <h3><?php esc_html_e( 'No activity yet', 'woo-nalda-sync' ); ?></h3>
                        <p><?php esc_html_e( 'Your sync activity will appear here once you start syncing data.', 'woo-nalda-sync' ); ?></p>
                        <?php if ( $is_licensed ) : ?>
                            <button type="button" class="wns-btn wns-btn-primary" id="wns-run-first-sync">
                                <span class="dashicons dashicons-update"></span>
                                <?php esc_html_e( 'Run First Sync', 'woo-nalda-sync' ); ?>
                            </button>
                        <?php else : ?>
                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=woo-nalda-sync-license' ) ); ?>" class="wns-btn wns-btn-primary">
                                <span class="dashicons dashicons-admin-network"></span>
                                <?php esc_html_e( 'Activate License', 'woo-nalda-sync' ); ?>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Support Links -->
    <div class="wns-card">
        <div class="wns-card-header">
            <h2>
                <span class="dashicons dashicons-sos"></span>
                <?php esc_html_e( 'Need Help?', 'woo-nalda-sync' ); ?>
            </h2>
        </div>
        <div class="wns-card-body">
            <div class="wns-quick-actions">
                <a href="https://jonakyds.com/docs/woo-nalda-sync" target="_blank" class="wns-quick-action">
                    <span class="dashicons dashicons-book"></span>
                    <span><?php esc_html_e( 'Documentation', 'woo-nalda-sync' ); ?></span>
                </a>
                <a href="https://jonakyds.com/support" target="_blank" class="wns-quick-action">
                    <span class="dashicons dashicons-format-chat"></span>
                    <span><?php esc_html_e( 'Contact Support', 'woo-nalda-sync' ); ?></span>
                </a>
                <a href="https://jonakyds.com/changelog/woo-nalda-sync" target="_blank" class="wns-quick-action">
                    <span class="dashicons dashicons-list-view"></span>
                    <span><?php esc_html_e( 'Changelog', 'woo-nalda-sync' ); ?></span>
                </a>
                <a href="https://jonakyds.com/account" target="_blank" class="wns-quick-action">
                    <span class="dashicons dashicons-admin-users"></span>
                    <span><?php esc_html_e( 'My Account', 'woo-nalda-sync' ); ?></span>
                </a>
            </div>
        </div>
    </div>
</div>
