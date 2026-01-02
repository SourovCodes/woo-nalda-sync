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

// Check configuration status.
$sftp_configured  = ! empty( $settings['sftp_host'] ) && ! empty( $settings['sftp_username'] );
$api_configured   = ! empty( $settings['nalda_api_key'] );
$setup_complete   = $is_licensed && $sftp_configured && $api_configured;
$setup_progress   = 0;
if ( $is_licensed ) $setup_progress++;
if ( $sftp_configured ) $setup_progress++;
if ( $api_configured ) $setup_progress++;
if ( $product_sync_status || $order_sync_status ) $setup_progress++;
$setup_percentage = ( $setup_progress / 4 ) * 100;
?>

<div class="wns-wrap">
    <!-- Header -->
    <div class="wns-header">
        <div class="wns-header-left">
            <div class="wns-logo">
                <span class="dashicons dashicons-update"></span>
            </div>
            <div>
                <h1><?php esc_html_e( 'Dashboard', 'woo-nalda-sync' ); ?></h1>
                <p><?php esc_html_e( 'Overview of your WooCommerce Nalda synchronization.', 'woo-nalda-sync' ); ?></p>
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
        <!-- License Required Alert -->
        <div class="wns-alert wns-alert-warning">
            <span class="wns-alert-icon dashicons dashicons-warning"></span>
            <div class="wns-alert-content">
                <div class="wns-alert-title"><?php esc_html_e( 'License Required', 'woo-nalda-sync' ); ?></div>
                <p class="wns-alert-message">
                    <?php esc_html_e( 'Activate your license to enable product exports and order imports.', 'woo-nalda-sync' ); ?>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=woo-nalda-sync-license' ) ); ?>" class="wns-btn wns-btn-primary wns-btn-sm" style="margin-left: 12px;">
                        <?php esc_html_e( 'Activate Now', 'woo-nalda-sync' ); ?>
                    </a>
                </p>
            </div>
        </div>
    <?php elseif ( ! $setup_complete ) : ?>
        <!-- Setup Incomplete Alert -->
        <div class="wns-alert wns-alert-info">
            <span class="wns-alert-icon dashicons dashicons-info"></span>
            <div class="wns-alert-content">
                <div class="wns-alert-title"><?php esc_html_e( 'Complete Your Setup', 'woo-nalda-sync' ); ?></div>
                <p class="wns-alert-message">
                    <?php esc_html_e( 'Configure your connections to start syncing data with Nalda Marketplace.', 'woo-nalda-sync' ); ?>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=woo-nalda-sync-settings' ) ); ?>" class="wns-btn wns-btn-secondary wns-btn-sm" style="margin-left: 12px;">
                        <?php esc_html_e( 'Go to Settings', 'woo-nalda-sync' ); ?>
                    </a>
                </p>
            </div>
        </div>
    <?php endif; ?>

    <!-- Connection Status Cards -->
    <div class="wns-grid wns-grid-3" style="margin-bottom: 24px;">
        <!-- License Status -->
        <div class="wns-card wns-status-card <?php echo $is_licensed ? 'wns-status-success' : 'wns-status-warning'; ?>">
            <div class="wns-card-body">
                <div class="wns-status-header">
                    <div class="wns-status-icon">
                        <span class="dashicons dashicons-<?php echo $is_licensed ? 'yes-alt' : 'warning'; ?>"></span>
                    </div>
                    <span class="wns-status-badge"><?php echo $is_licensed ? esc_html__( 'Active', 'woo-nalda-sync' ) : esc_html__( 'Inactive', 'woo-nalda-sync' ); ?></span>
                </div>
                <h3 class="wns-status-title"><?php esc_html_e( 'License', 'woo-nalda-sync' ); ?></h3>
                <p class="wns-status-desc">
                    <?php echo $is_licensed ? esc_html__( 'Your license is active and valid.', 'woo-nalda-sync' ) : esc_html__( 'License required to sync.', 'woo-nalda-sync' ); ?>
                </p>
                <?php if ( ! $is_licensed ) : ?>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=woo-nalda-sync-license' ) ); ?>" class="wns-status-link">
                        <?php esc_html_e( 'Activate License', 'woo-nalda-sync' ); ?> →
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- SFTP Connection -->
        <div class="wns-card wns-status-card <?php echo $sftp_configured ? 'wns-status-success' : 'wns-status-neutral'; ?>">
            <div class="wns-card-body">
                <div class="wns-status-header">
                    <div class="wns-status-icon">
                        <span class="dashicons dashicons-<?php echo $sftp_configured ? 'yes-alt' : 'cloud-upload'; ?>"></span>
                    </div>
                    <span class="wns-status-badge"><?php echo $sftp_configured ? esc_html__( 'Configured', 'woo-nalda-sync' ) : esc_html__( 'Not Set', 'woo-nalda-sync' ); ?></span>
                </div>
                <h3 class="wns-status-title"><?php esc_html_e( 'SFTP Connection', 'woo-nalda-sync' ); ?></h3>
                <p class="wns-status-desc">
                    <?php echo $sftp_configured ? esc_html__( 'Ready for product exports.', 'woo-nalda-sync' ) : esc_html__( 'Required for product sync.', 'woo-nalda-sync' ); ?>
                </p>
                <?php if ( ! $sftp_configured ) : ?>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=woo-nalda-sync-settings' ) ); ?>" class="wns-status-link">
                        <?php esc_html_e( 'Configure SFTP', 'woo-nalda-sync' ); ?> →
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Nalda API -->
        <div class="wns-card wns-status-card <?php echo $api_configured ? 'wns-status-success' : 'wns-status-neutral'; ?>">
            <div class="wns-card-body">
                <div class="wns-status-header">
                    <div class="wns-status-icon">
                        <span class="dashicons dashicons-<?php echo $api_configured ? 'yes-alt' : 'rest-api'; ?>"></span>
                    </div>
                    <span class="wns-status-badge"><?php echo $api_configured ? esc_html__( 'Connected', 'woo-nalda-sync' ) : esc_html__( 'Not Set', 'woo-nalda-sync' ); ?></span>
                </div>
                <h3 class="wns-status-title"><?php esc_html_e( 'Nalda API', 'woo-nalda-sync' ); ?></h3>
                <p class="wns-status-desc">
                    <?php echo $api_configured ? esc_html__( 'Ready for order imports.', 'woo-nalda-sync' ) : esc_html__( 'Required for order sync.', 'woo-nalda-sync' ); ?>
                </p>
                <?php if ( ! $api_configured ) : ?>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=woo-nalda-sync-settings' ) ); ?>" class="wns-status-link">
                        <?php esc_html_e( 'Add API Key', 'woo-nalda-sync' ); ?> →
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Sync Controls & Stats -->
    <div class="wns-grid wns-grid-2">
        <!-- Product Sync Panel -->
        <div class="wns-card">
            <div class="wns-card-header">
                <h2>
                    <span class="dashicons dashicons-upload"></span>
                    <?php esc_html_e( 'Product Export', 'woo-nalda-sync' ); ?>
                </h2>
                <div class="wns-sync-toggle">
                    <?php if ( $product_sync_status ) : ?>
                        <span class="wns-badge wns-badge-success wns-badge-sm">
                            <span class="dashicons dashicons-controls-repeat"></span>
                            <?php esc_html_e( 'Auto', 'woo-nalda-sync' ); ?>
                        </span>
                    <?php else : ?>
                        <span class="wns-badge wns-badge-neutral wns-badge-sm">
                            <?php esc_html_e( 'Manual', 'woo-nalda-sync' ); ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="wns-card-body">
                <div class="wns-sync-stats">
                    <div class="wns-sync-stat">
                        <span class="wns-sync-stat-value"><?php echo esc_html( number_format_i18n( $products_synced ) ); ?></span>
                        <span class="wns-sync-stat-label"><?php esc_html_e( 'Products Exported', 'woo-nalda-sync' ); ?></span>
                    </div>
                    <div class="wns-sync-stat">
                        <span class="wns-sync-stat-value">
                            <?php
                            if ( $last_product_sync ) {
                                echo esc_html( human_time_diff( strtotime( $last_product_sync ), current_time( 'timestamp' ) ) );
                            } else {
                                echo '—';
                            }
                            ?>
                        </span>
                        <span class="wns-sync-stat-label"><?php esc_html_e( 'Last Export', 'woo-nalda-sync' ); ?></span>
                    </div>
                    <?php if ( $product_sync_status && $next_product_sync ) : ?>
                        <div class="wns-sync-stat">
                            <span class="wns-sync-stat-value">
                                <?php echo esc_html( human_time_diff( current_time( 'timestamp' ), $next_product_sync ) ); ?>
                            </span>
                            <span class="wns-sync-stat-label"><?php esc_html_e( 'Next Export', 'woo-nalda-sync' ); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="wns-sync-actions">
                    <?php if ( $is_licensed && $sftp_configured ) : ?>
                        <button type="button" class="wns-btn wns-btn-primary wns-sync-btn" id="wns-run-product-sync">
                            <span class="dashicons dashicons-upload"></span>
                            <?php esc_html_e( 'Export Products Now', 'woo-nalda-sync' ); ?>
                        </button>
                    <?php else : ?>
                        <button type="button" class="wns-btn wns-btn-primary" disabled>
                            <span class="dashicons dashicons-upload"></span>
                            <?php esc_html_e( 'Export Products Now', 'woo-nalda-sync' ); ?>
                        </button>
                        <p class="wns-sync-disabled-note">
                            <?php
                            if ( ! $is_licensed ) {
                                esc_html_e( 'Activate your license to export products.', 'woo-nalda-sync' );
                            } else {
                                esc_html_e( 'Configure SFTP settings first.', 'woo-nalda-sync' );
                            }
                            ?>
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Order Sync Panel -->
        <div class="wns-card">
            <div class="wns-card-header">
                <h2>
                    <span class="dashicons dashicons-download"></span>
                    <?php esc_html_e( 'Order Import', 'woo-nalda-sync' ); ?>
                </h2>
                <div class="wns-sync-toggle">
                    <?php if ( $order_sync_status ) : ?>
                        <span class="wns-badge wns-badge-success wns-badge-sm">
                            <span class="dashicons dashicons-controls-repeat"></span>
                            <?php esc_html_e( 'Auto', 'woo-nalda-sync' ); ?>
                        </span>
                    <?php else : ?>
                        <span class="wns-badge wns-badge-neutral wns-badge-sm">
                            <?php esc_html_e( 'Manual', 'woo-nalda-sync' ); ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="wns-card-body">
                <div class="wns-sync-stats">
                    <div class="wns-sync-stat">
                        <span class="wns-sync-stat-value"><?php echo esc_html( number_format_i18n( $orders_synced ) ); ?></span>
                        <span class="wns-sync-stat-label"><?php esc_html_e( 'Orders Imported', 'woo-nalda-sync' ); ?></span>
                    </div>
                    <div class="wns-sync-stat">
                        <span class="wns-sync-stat-value">
                            <?php
                            if ( $last_order_sync ) {
                                echo esc_html( human_time_diff( strtotime( $last_order_sync ), current_time( 'timestamp' ) ) );
                            } else {
                                echo '—';
                            }
                            ?>
                        </span>
                        <span class="wns-sync-stat-label"><?php esc_html_e( 'Last Import', 'woo-nalda-sync' ); ?></span>
                    </div>
                    <?php if ( $order_sync_status && $next_order_sync ) : ?>
                        <div class="wns-sync-stat">
                            <span class="wns-sync-stat-value">
                                <?php echo esc_html( human_time_diff( current_time( 'timestamp' ), $next_order_sync ) ); ?>
                            </span>
                            <span class="wns-sync-stat-label"><?php esc_html_e( 'Next Import', 'woo-nalda-sync' ); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="wns-sync-actions">
                    <?php if ( $is_licensed && $api_configured ) : ?>
                        <button type="button" class="wns-btn wns-btn-primary wns-sync-btn" id="wns-run-order-sync">
                            <span class="dashicons dashicons-download"></span>
                            <?php esc_html_e( 'Import Orders Now', 'woo-nalda-sync' ); ?>
                        </button>
                    <?php else : ?>
                        <button type="button" class="wns-btn wns-btn-primary" disabled>
                            <span class="dashicons dashicons-download"></span>
                            <?php esc_html_e( 'Import Orders Now', 'woo-nalda-sync' ); ?>
                        </button>
                        <p class="wns-sync-disabled-note">
                            <?php
                            if ( ! $is_licensed ) {
                                esc_html_e( 'Activate your license to import orders.', 'woo-nalda-sync' );
                            } else {
                                esc_html_e( 'Add your Nalda API key first.', 'woo-nalda-sync' );
                            }
                            ?>
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- CSV Upload History -->
    <?php if ( $is_licensed && $sftp_configured ) : ?>
    <div class="wns-card">
        <div class="wns-card-header">
            <h2>
                <span class="dashicons dashicons-media-spreadsheet"></span>
                <?php esc_html_e( 'CSV Upload History', 'woo-nalda-sync' ); ?>
            </h2>
            <button type="button" class="wns-btn wns-btn-secondary wns-btn-sm" id="wns-refresh-upload-history">
                <span class="dashicons dashicons-update"></span>
                <?php esc_html_e( 'Refresh', 'woo-nalda-sync' ); ?>
            </button>
        </div>
        <div class="wns-card-body" id="wns-upload-history-container">
            <!-- Loading State -->
            <div id="wns-upload-history-loading" class="wns-loading-state">
                <span class="wns-spinner"></span>
                <span><?php esc_html_e( 'Loading upload history...', 'woo-nalda-sync' ); ?></span>
            </div>

            <!-- Empty State -->
            <div id="wns-upload-history-empty" class="wns-empty-state" style="display: none;">
                <span class="dashicons dashicons-media-spreadsheet"></span>
                <p><?php esc_html_e( 'No CSV uploads yet.', 'woo-nalda-sync' ); ?></p>
                <p class="wns-text-muted"><?php esc_html_e( 'Export products to see your upload history here.', 'woo-nalda-sync' ); ?></p>
            </div>

            <!-- Error State -->
            <div id="wns-upload-history-error" class="wns-error-state" style="display: none;">
                <span class="dashicons dashicons-warning"></span>
                <p class="wns-error-message"></p>
            </div>

            <!-- Table -->
            <table id="wns-upload-history-table" class="wns-table" style="display: none;">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'ID', 'woo-nalda-sync' ); ?></th>
                        <th><?php esc_html_e( 'Status', 'woo-nalda-sync' ); ?></th>
                        <th><?php esc_html_e( 'Domain', 'woo-nalda-sync' ); ?></th>
                        <th><?php esc_html_e( 'Created', 'woo-nalda-sync' ); ?></th>
                        <th><?php esc_html_e( 'Processed', 'woo-nalda-sync' ); ?></th>
                        <th><?php esc_html_e( 'Error', 'woo-nalda-sync' ); ?></th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>

            <!-- Pagination -->
            <div class="wns-upload-history-pagination" style="display: none;">
                <button type="button" class="wns-btn wns-btn-secondary wns-btn-sm wns-pagination-btn wns-pagination-prev" data-page="1">
                    <span class="dashicons dashicons-arrow-left-alt2"></span>
                    <?php esc_html_e( 'Previous', 'woo-nalda-sync' ); ?>
                </button>
                <span class="wns-pagination-info"></span>
                <button type="button" class="wns-btn wns-btn-secondary wns-btn-sm wns-pagination-btn wns-pagination-next" data-page="2">
                    <?php esc_html_e( 'Next', 'woo-nalda-sync' ); ?>
                    <span class="dashicons dashicons-arrow-right-alt2"></span>
                </button>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Setup Guide & Quick Links -->
    <div class="wns-grid wns-grid-2">
        <!-- Setup Checklist -->
        <div class="wns-card">
            <div class="wns-card-header">
                <h2>
                    <span class="dashicons dashicons-welcome-learn-more"></span>
                    <?php esc_html_e( 'Setup Checklist', 'woo-nalda-sync' ); ?>
                </h2>
                <span class="wns-progress-text"><?php echo esc_html( $setup_progress ); ?>/4</span>
            </div>
            <div class="wns-card-body">
                <div class="wns-progress-bar">
                    <div class="wns-progress-fill" style="width: <?php echo esc_attr( $setup_percentage ); ?>%;"></div>
                </div>
                
                <div class="wns-checklist">
                    <!-- Step 1: License -->
                    <div class="wns-checklist-item <?php echo $is_licensed ? 'completed' : ''; ?>">
                        <div class="wns-checklist-icon">
                            <?php if ( $is_licensed ) : ?>
                                <span class="dashicons dashicons-yes-alt"></span>
                            <?php else : ?>
                                <span class="wns-step-num">1</span>
                            <?php endif; ?>
                        </div>
                        <div class="wns-checklist-content">
                            <div class="wns-checklist-title"><?php esc_html_e( 'Activate License', 'woo-nalda-sync' ); ?></div>
                            <div class="wns-checklist-desc"><?php esc_html_e( 'Enter your license key to unlock features.', 'woo-nalda-sync' ); ?></div>
                        </div>
                        <?php if ( ! $is_licensed ) : ?>
                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=woo-nalda-sync-license' ) ); ?>" class="wns-checklist-action">
                                <?php esc_html_e( 'Activate', 'woo-nalda-sync' ); ?>
                            </a>
                        <?php endif; ?>
                    </div>

                    <!-- Step 2: SFTP -->
                    <div class="wns-checklist-item <?php echo $sftp_configured ? 'completed' : ''; ?>">
                        <div class="wns-checklist-icon">
                            <?php if ( $sftp_configured ) : ?>
                                <span class="dashicons dashicons-yes-alt"></span>
                            <?php else : ?>
                                <span class="wns-step-num">2</span>
                            <?php endif; ?>
                        </div>
                        <div class="wns-checklist-content">
                            <div class="wns-checklist-title"><?php esc_html_e( 'Configure SFTP', 'woo-nalda-sync' ); ?></div>
                            <div class="wns-checklist-desc"><?php esc_html_e( 'Connect SFTP for product CSV uploads.', 'woo-nalda-sync' ); ?></div>
                        </div>
                        <?php if ( ! $sftp_configured ) : ?>
                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=woo-nalda-sync-settings' ) ); ?>" class="wns-checklist-action">
                                <?php esc_html_e( 'Setup', 'woo-nalda-sync' ); ?>
                            </a>
                        <?php endif; ?>
                    </div>

                    <!-- Step 3: API -->
                    <div class="wns-checklist-item <?php echo $api_configured ? 'completed' : ''; ?>">
                        <div class="wns-checklist-icon">
                            <?php if ( $api_configured ) : ?>
                                <span class="dashicons dashicons-yes-alt"></span>
                            <?php else : ?>
                                <span class="wns-step-num">3</span>
                            <?php endif; ?>
                        </div>
                        <div class="wns-checklist-content">
                            <div class="wns-checklist-title"><?php esc_html_e( 'Connect Nalda API', 'woo-nalda-sync' ); ?></div>
                            <div class="wns-checklist-desc"><?php esc_html_e( 'Add API key for order imports.', 'woo-nalda-sync' ); ?></div>
                        </div>
                        <?php if ( ! $api_configured ) : ?>
                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=woo-nalda-sync-settings' ) ); ?>" class="wns-checklist-action">
                                <?php esc_html_e( 'Connect', 'woo-nalda-sync' ); ?>
                            </a>
                        <?php endif; ?>
                    </div>

                    <!-- Step 4: Auto Sync -->
                    <div class="wns-checklist-item <?php echo ( $product_sync_status || $order_sync_status ) ? 'completed' : ''; ?>">
                        <div class="wns-checklist-icon">
                            <?php if ( $product_sync_status || $order_sync_status ) : ?>
                                <span class="dashicons dashicons-yes-alt"></span>
                            <?php else : ?>
                                <span class="wns-step-num">4</span>
                            <?php endif; ?>
                        </div>
                        <div class="wns-checklist-content">
                            <div class="wns-checklist-title"><?php esc_html_e( 'Enable Auto Sync', 'woo-nalda-sync' ); ?></div>
                            <div class="wns-checklist-desc"><?php esc_html_e( 'Schedule automatic data syncing.', 'woo-nalda-sync' ); ?></div>
                        </div>
                        <?php if ( ! $product_sync_status && ! $order_sync_status ) : ?>
                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=woo-nalda-sync-settings' ) ); ?>" class="wns-checklist-action">
                                <?php esc_html_e( 'Enable', 'woo-nalda-sync' ); ?>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Links -->
        <div class="wns-card">
            <div class="wns-card-header">
                <h2>
                    <span class="dashicons dashicons-admin-links"></span>
                    <?php esc_html_e( 'Quick Links', 'woo-nalda-sync' ); ?>
                </h2>
            </div>
            <div class="wns-card-body">
                <div class="wns-quick-links">
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=woo-nalda-sync-settings' ) ); ?>" class="wns-quick-link">
                        <div class="wns-quick-link-icon">
                            <span class="dashicons dashicons-admin-generic"></span>
                        </div>
                        <div class="wns-quick-link-content">
                            <span class="wns-quick-link-title"><?php esc_html_e( 'Settings', 'woo-nalda-sync' ); ?></span>
                            <span class="wns-quick-link-desc"><?php esc_html_e( 'Configure sync options', 'woo-nalda-sync' ); ?></span>
                        </div>
                        <span class="dashicons dashicons-arrow-right-alt2"></span>
                    </a>
                    
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=woo-nalda-sync-license' ) ); ?>" class="wns-quick-link">
                        <div class="wns-quick-link-icon">
                            <span class="dashicons dashicons-admin-network"></span>
                        </div>
                        <div class="wns-quick-link-content">
                            <span class="wns-quick-link-title"><?php esc_html_e( 'License', 'woo-nalda-sync' ); ?></span>
                            <span class="wns-quick-link-desc"><?php esc_html_e( 'Manage your license', 'woo-nalda-sync' ); ?></span>
                        </div>
                        <span class="dashicons dashicons-arrow-right-alt2"></span>
                    </a>
                    
                    <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=shop_order' ) ); ?>" class="wns-quick-link">
                        <div class="wns-quick-link-icon">
                            <span class="dashicons dashicons-clipboard"></span>
                        </div>
                        <div class="wns-quick-link-content">
                            <span class="wns-quick-link-title"><?php esc_html_e( 'WooCommerce Orders', 'woo-nalda-sync' ); ?></span>
                            <span class="wns-quick-link-desc"><?php esc_html_e( 'View imported orders', 'woo-nalda-sync' ); ?></span>
                        </div>
                        <span class="dashicons dashicons-arrow-right-alt2"></span>
                    </a>
                    
                    <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=product' ) ); ?>" class="wns-quick-link">
                        <div class="wns-quick-link-icon">
                            <span class="dashicons dashicons-products"></span>
                        </div>
                        <div class="wns-quick-link-content">
                            <span class="wns-quick-link-title"><?php esc_html_e( 'Products', 'woo-nalda-sync' ); ?></span>
                            <span class="wns-quick-link-desc"><?php esc_html_e( 'Manage WooCommerce products', 'woo-nalda-sync' ); ?></span>
                        </div>
                        <span class="dashicons dashicons-arrow-right-alt2"></span>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Help & Resources -->
    <div class="wns-card">
        <div class="wns-card-header">
            <h2>
                <span class="dashicons dashicons-sos"></span>
                <?php esc_html_e( 'Help & Resources', 'woo-nalda-sync' ); ?>
            </h2>
        </div>
        <div class="wns-card-body">
            <div class="wns-resources-grid">
                <a href="https://jonakyds.com/docs/woo-nalda-sync" target="_blank" class="wns-resource-card">
                    <span class="dashicons dashicons-book"></span>
                    <h4><?php esc_html_e( 'Documentation', 'woo-nalda-sync' ); ?></h4>
                    <p><?php esc_html_e( 'Learn how to use the plugin', 'woo-nalda-sync' ); ?></p>
                </a>
                <a href="https://jonakyds.com/support" target="_blank" class="wns-resource-card">
                    <span class="dashicons dashicons-format-chat"></span>
                    <h4><?php esc_html_e( 'Support', 'woo-nalda-sync' ); ?></h4>
                    <p><?php esc_html_e( 'Get help from our team', 'woo-nalda-sync' ); ?></p>
                </a>
                <a href="https://jonakyds.com/changelog/woo-nalda-sync" target="_blank" class="wns-resource-card">
                    <span class="dashicons dashicons-list-view"></span>
                    <h4><?php esc_html_e( 'Changelog', 'woo-nalda-sync' ); ?></h4>
                    <p><?php esc_html_e( 'See what\'s new', 'woo-nalda-sync' ); ?></p>
                </a>
                <a href="https://jonakyds.com/account" target="_blank" class="wns-resource-card">
                    <span class="dashicons dashicons-admin-users"></span>
                    <h4><?php esc_html_e( 'My Account', 'woo-nalda-sync' ); ?></h4>
                    <p><?php esc_html_e( 'Manage your purchases', 'woo-nalda-sync' ); ?></p>
                </a>
            </div>
        </div>
    </div>
</div>
