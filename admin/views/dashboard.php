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
$product_export_status = isset( $settings['product_export_enabled'] ) && 'yes' === $settings['product_export_enabled'];
$order_import_status   = isset( $settings['order_import_enabled'] ) && 'yes' === $settings['order_import_enabled'];

// Format dates.
$last_product_export = isset( $stats['last_product_export'] ) ? $stats['last_product_export'] : null;
$last_order_import   = isset( $stats['last_order_import'] ) ? $stats['last_order_import'] : null;
$products_synced   = isset( $stats['products_synced'] ) ? $stats['products_synced'] : 0;
$orders_synced     = isset( $stats['orders_synced'] ) ? $stats['orders_synced'] : 0;

// Format next sync times.
$next_product_export = isset( $next_sync_times['product_export'] ) && $next_sync_times['product_export'] ? $next_sync_times['product_export'] : null;
$next_order_import   = isset( $next_sync_times['order_import'] ) && $next_sync_times['order_import'] ? $next_sync_times['order_import'] : null;

// Get schedule intervals for overdue detection.
$product_export_schedule = isset( $settings['product_export_schedule'] ) ? $settings['product_export_schedule'] : 'daily';
$order_import_schedule   = isset( $settings['order_import_schedule'] ) ? $settings['order_import_schedule'] : 'hourly';
$schedules = wp_get_schedules();
$product_export_interval = isset( $schedules[ $product_export_schedule ]['interval'] ) ? $schedules[ $product_export_schedule ]['interval'] : 86400;
$order_import_interval   = isset( $schedules[ $order_import_schedule ]['interval'] ) ? $schedules[ $order_import_schedule ]['interval'] : 3600;

// Check if syncs are overdue.
$product_export_overdue = $next_product_export && $next_product_export <= time() && ( time() - $next_product_export ) >= $product_export_interval;
$order_import_overdue   = $next_order_import && $next_order_import <= time() && ( time() - $next_order_import ) >= $order_import_interval;
$wp_cron_disabled       = defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON;

// Check configuration status.
$sftp_configured  = ! empty( $settings['sftp_host'] ) && ! empty( $settings['sftp_username'] );
$api_configured   = ! empty( $settings['nalda_api_key'] );
$setup_complete   = $is_licensed && $sftp_configured && $api_configured;
$setup_progress   = 0;
if ( $is_licensed ) $setup_progress++;
if ( $sftp_configured ) $setup_progress++;
if ( $api_configured ) $setup_progress++;
if ( $product_export_status ) $setup_progress++;
if ( $order_import_status ) $setup_progress++;
$setup_percentage = ( $setup_progress / 5 ) * 100;
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
                    <?php echo $sftp_configured ? esc_html__( 'Ready for product exports.', 'woo-nalda-sync' ) : esc_html__( 'Required for product export.', 'woo-nalda-sync' ); ?>
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
                    <?php echo $api_configured ? esc_html__( 'Ready for order imports.', 'woo-nalda-sync' ) : esc_html__( 'Required for order import.', 'woo-nalda-sync' ); ?>
                </p>
                <?php if ( ! $api_configured ) : ?>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=woo-nalda-sync-settings' ) ); ?>" class="wns-status-link">
                        <?php esc_html_e( 'Add API Key', 'woo-nalda-sync' ); ?> →
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Scheduled Sync Status Cards -->
    <div class="wns-grid wns-grid-2" style="margin-bottom: 24px;">
        <!-- Product Export Schedule -->
        <div class="wns-card wns-status-card <?php echo $product_export_status ? ( $product_export_overdue ? 'wns-status-warning' : 'wns-status-success' ) : 'wns-status-neutral'; ?>">
            <div class="wns-card-body">
                <div class="wns-status-header">
                    <div class="wns-status-icon">
                        <span class="dashicons dashicons-<?php echo $product_export_status ? ( $product_export_overdue ? 'warning' : 'controls-repeat' ) : 'clock'; ?>"></span>
                    </div>
                    <span class="wns-status-badge"><?php echo $product_export_status ? esc_html__( 'Enabled', 'woo-nalda-sync' ) : esc_html__( 'Disabled', 'woo-nalda-sync' ); ?></span>
                </div>
                <h3 class="wns-status-title"><?php esc_html_e( 'Product Export Schedule', 'woo-nalda-sync' ); ?></h3>
                <p class="wns-status-desc">
                    <?php 
                    if ( $product_export_status && $next_product_export ) {
                        $current_timestamp = current_time( 'timestamp' );
                        $time_since_scheduled = time() - $next_product_export;
                        
                        if ( $next_product_export <= time() && $time_since_scheduled >= $product_export_interval ) {
                            esc_html_e( 'Export pending...', 'woo-nalda-sync' );
                        } elseif ( $next_product_export <= time() ) {
                            esc_html_e( 'Export pending...', 'woo-nalda-sync' );
                        } else {
                            printf( 
                                esc_html__( 'Next export: %s', 'woo-nalda-sync' ), 
                                esc_html( human_time_diff( time(), $next_product_export ) ) 
                            );
                        }
                    } elseif ( $product_export_status ) {
                        esc_html_e( 'Scheduled export active.', 'woo-nalda-sync' );
                    } else {
                        esc_html_e( 'Enable automatic product export.', 'woo-nalda-sync' );
                    }
                    ?>
                </p>
                <?php if ( ! $product_export_status ) : ?>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=woo-nalda-sync-settings' ) ); ?>" class="wns-status-link">
                        <?php esc_html_e( 'Enable Export', 'woo-nalda-sync' ); ?> →
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Order Import Schedule -->
        <div class="wns-card wns-status-card <?php echo $order_import_status ? ( $order_import_overdue ? 'wns-status-warning' : 'wns-status-success' ) : 'wns-status-neutral'; ?>">
            <div class="wns-card-body">
                <div class="wns-status-header">
                    <div class="wns-status-icon">
                        <span class="dashicons dashicons-<?php echo $order_import_status ? ( $order_import_overdue ? 'warning' : 'controls-repeat' ) : 'clock'; ?>"></span>
                    </div>
                    <span class="wns-status-badge"><?php echo $order_import_status ? esc_html__( 'Enabled', 'woo-nalda-sync' ) : esc_html__( 'Disabled', 'woo-nalda-sync' ); ?></span>
                </div>
                <h3 class="wns-status-title"><?php esc_html_e( 'Order Import Schedule', 'woo-nalda-sync' ); ?></h3>
                <p class="wns-status-desc">
                    <?php 
                    if ( $order_import_status && $next_order_import ) {
                        $current_timestamp = current_time( 'timestamp' );
                        $time_since_scheduled = time() - $next_order_import;
                        
                        if ( $next_order_import <= time() && $time_since_scheduled >= $order_import_interval ) {
                            esc_html_e( 'Import pending...', 'woo-nalda-sync' );
                        } elseif ( $next_order_import <= time() ) {
                            esc_html_e( 'Import pending...', 'woo-nalda-sync' );
                        } else {
                            printf( 
                                esc_html__( 'Next import: %s', 'woo-nalda-sync' ), 
                                esc_html( human_time_diff( time(), $next_order_import ) ) 
                            );
                        }
                    } elseif ( $order_import_status ) {
                        esc_html_e( 'Scheduled import active.', 'woo-nalda-sync' );
                    } else {
                        esc_html_e( 'Enable automatic order import.', 'woo-nalda-sync' );
                    }
                    ?>
                </p>
                <?php if ( ! $order_import_status ) : ?>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=woo-nalda-sync-settings' ) ); ?>" class="wns-status-link">
                        <?php esc_html_e( 'Enable Import', 'woo-nalda-sync' ); ?> →
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Cron Status Alert (if any sync is overdue) -->
    <?php if ( ( $product_export_overdue || $order_import_overdue ) && ( $product_export_status || $order_import_status ) ) : ?>
        <div class="wns-alert wns-alert-warning" style="margin-bottom: 24px;">
            <span class="wns-alert-icon dashicons dashicons-warning"></span>
            <div class="wns-alert-content">
                <div class="wns-alert-title"><?php esc_html_e( 'Scheduled Sync Pending', 'woo-nalda-sync' ); ?></div>
                <div class="wns-alert-message">
                    <p style="margin: 0 0 12px;">
                        <?php esc_html_e( 'A scheduled sync is waiting to run. WordPress uses cron jobs to trigger syncs automatically when visitors access your site.', 'woo-nalda-sync' ); ?>
                    </p>
                    <?php if ( $wp_cron_disabled ) : ?>
                        <p style="margin: 0 0 12px; font-weight: 600; color: #d63384;">
                            <?php esc_html_e( 'WordPress Cron is Disabled', 'woo-nalda-sync' ); ?>
                        </p>
                        <p style="margin: 0 0 12px;">
                            <?php esc_html_e( 'Your wp-config.php has DISABLE_WP_CRON set to true. You must set up a server cron job:', 'woo-nalda-sync' ); ?>
                        </p>
                        <div style="background: #fff; padding: 10px; border-radius: 4px; font-family: monospace; font-size: 12px; margin: 0 0 12px; overflow-x: auto;">
                            <code style="color: #d63384;">*/5 * * * * curl -s <?php echo esc_url( site_url( 'wp-cron.php?doing_wp_cron' ) ); ?> > /dev/null 2>&1</code>
                        </div>
                    <?php else : ?>
                        <p style="margin: 0;">
                            <a href="<?php echo esc_url( site_url( 'wp-cron.php?doing_wp_cron' ) ); ?>" target="_blank" rel="noopener noreferrer" class="wns-btn wns-btn-primary wns-btn-sm">
                                <?php esc_html_e( 'Trigger Cron Now', 'woo-nalda-sync' ); ?>
                            </a>
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Sync Controls & Stats -->
    <div class="wns-grid wns-grid-2">
        <!-- Product Export Panel -->
        <div class="wns-card">
            <div class="wns-card-header">
                <h2>
                    <span class="dashicons dashicons-upload"></span>
                    <?php esc_html_e( 'Product Export', 'woo-nalda-sync' ); ?>
                </h2>
                <div class="wns-sync-toggle">
                    <?php if ( $product_export_status ) : ?>
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
                            if ( $last_product_export ) {
                                echo esc_html( human_time_diff( strtotime( $last_product_export ), current_time( 'timestamp' ) ) );
                            } else {
                                echo '—';
                            }
                            ?>
                        </span>
                        <span class="wns-sync-stat-label"><?php esc_html_e( 'Last Export', 'woo-nalda-sync' ); ?></span>
                    </div>
                    <?php if ( $product_export_status && $next_product_export ) : ?>
                        <div class="wns-sync-stat">
                            <span class="wns-sync-stat-value">
                                <?php
                                if ( $product_export_overdue ) {
                                    echo '<span style="color: #f0ad4e;">' . esc_html__( 'Pending...', 'woo-nalda-sync' ) . '</span>';
                                } elseif ( $next_product_export <= time() ) {
                                    esc_html_e( 'Pending...', 'woo-nalda-sync' );
                                } else {
                                    echo esc_html( human_time_diff( time(), $next_product_export ) );
                                }
                                ?>
                            </span>
                            <span class="wns-sync-stat-label"><?php esc_html_e( 'Next Export', 'woo-nalda-sync' ); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="wns-sync-actions">
                    <?php if ( $is_licensed && $sftp_configured ) : ?>
                        <button type="button" class="wns-btn wns-btn-primary wns-sync-btn" id="wns-run-product-export">
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

        <!-- Order Import Panel -->
        <div class="wns-card">
            <div class="wns-card-header">
                <h2>
                    <span class="dashicons dashicons-download"></span>
                    <?php esc_html_e( 'Order Import', 'woo-nalda-sync' ); ?>
                </h2>
                <div class="wns-sync-toggle">
                    <?php if ( $order_import_status ) : ?>
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
                            if ( $last_order_import ) {
                                echo esc_html( human_time_diff( strtotime( $last_order_import ), current_time( 'timestamp' ) ) );
                            } else {
                                echo '—';
                            }
                            ?>
                        </span>
                        <span class="wns-sync-stat-label"><?php esc_html_e( 'Last Import', 'woo-nalda-sync' ); ?></span>
                    </div>
                    <?php if ( $order_import_status && $next_order_import ) : ?>
                        <div class="wns-sync-stat">
                            <span class="wns-sync-stat-value">
                                <?php echo esc_html( human_time_diff( time(), $next_order_import ) ); ?>
                            </span>
                            <span class="wns-sync-stat-label"><?php esc_html_e( 'Next Import', 'woo-nalda-sync' ); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="wns-sync-actions">
                    <?php if ( $is_licensed && $api_configured ) : ?>
                        <button type="button" class="wns-btn wns-btn-primary wns-sync-btn" id="wns-run-order-import">
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

    <!-- Order Status Export Panel -->
    <?php
    // Get order status export stats and settings.
    $order_status_export_stats    = woo_nalda_sync()->order_import->get_order_status_export_status();
    $last_order_status_export     = isset( $order_status_export_stats['last_sync'] ) ? $order_status_export_stats['last_sync'] : null;
    $orders_status_exported       = isset( $order_status_export_stats['orders_exported'] ) ? $order_status_export_stats['orders_exported'] : 0;
    $order_status_export_enabled  = isset( $settings['order_status_export_enabled'] ) && 'yes' === $settings['order_status_export_enabled'];
    $next_order_status_export     = isset( $next_sync_times['order_status_export'] ) && $next_sync_times['order_status_export'] ? $next_sync_times['order_status_export'] : null;
    
    // Check if order status export is overdue.
    $order_status_export_schedule = isset( $settings['order_status_export_schedule'] ) ? $settings['order_status_export_schedule'] : 'hourly';
    $order_status_export_interval = isset( $schedules[ $order_status_export_schedule ]['interval'] ) ? $schedules[ $order_status_export_schedule ]['interval'] : 3600;
    $order_status_export_overdue  = $next_order_status_export && $next_order_status_export <= time() && ( time() - $next_order_status_export ) >= $order_status_export_interval;
    ?>
    <div class="wns-card" style="margin-bottom: 24px;">
        <div class="wns-card-header">
            <h2>
                <span class="dashicons dashicons-update-alt"></span>
                <?php esc_html_e( 'Order Status Export', 'woo-nalda-sync' ); ?>
            </h2>
            <div class="wns-sync-toggle">
                <?php if ( $order_status_export_enabled ) : ?>
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
            <p class="wns-card-description" style="margin-bottom: 16px; color: #666;">
                <?php esc_html_e( 'Export order status updates (delivery status, tracking codes) to Nalda for imported orders.', 'woo-nalda-sync' ); ?>
            </p>
            <div class="wns-sync-stats">
                <div class="wns-sync-stat">
                    <span class="wns-sync-stat-value"><?php echo esc_html( number_format_i18n( $orders_status_exported ) ); ?></span>
                    <span class="wns-sync-stat-label"><?php esc_html_e( 'Statuses Exported', 'woo-nalda-sync' ); ?></span>
                </div>
                <div class="wns-sync-stat">
                    <span class="wns-sync-stat-value">
                        <?php
                        if ( $last_order_status_export ) {
                            echo esc_html( human_time_diff( strtotime( $last_order_status_export ), current_time( 'timestamp' ) ) );
                        } else {
                            echo '—';
                        }
                        ?>
                    </span>
                    <span class="wns-sync-stat-label"><?php esc_html_e( 'Last Export', 'woo-nalda-sync' ); ?></span>
                </div>
                <?php if ( $order_status_export_enabled && $next_order_status_export ) : ?>
                    <div class="wns-sync-stat">
                        <span class="wns-sync-stat-value">
                            <?php
                            if ( $order_status_export_overdue ) {
                                echo '<span style="color: #f0ad4e;">' . esc_html__( 'Pending...', 'woo-nalda-sync' ) . '</span>';
                            } elseif ( $next_order_status_export <= time() ) {
                                esc_html_e( 'Pending...', 'woo-nalda-sync' );
                            } else {
                                echo esc_html( human_time_diff( time(), $next_order_status_export ) );
                            }
                            ?>
                        </span>
                        <span class="wns-sync-stat-label"><?php esc_html_e( 'Next Export', 'woo-nalda-sync' ); ?></span>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="wns-sync-actions">
                <?php if ( $is_licensed && $sftp_configured ) : ?>
                    <button type="button" class="wns-btn wns-btn-secondary wns-sync-btn" id="wns-run-order-status-export">
                        <span class="dashicons dashicons-update-alt"></span>
                        <?php esc_html_e( 'Export Order Statuses', 'woo-nalda-sync' ); ?>
                    </button>
                <?php else : ?>
                    <button type="button" class="wns-btn wns-btn-secondary" disabled>
                        <span class="dashicons dashicons-update-alt"></span>
                        <?php esc_html_e( 'Export Order Statuses', 'woo-nalda-sync' ); ?>
                    </button>
                    <p class="wns-sync-disabled-note">
                        <?php
                        if ( ! $is_licensed ) {
                            esc_html_e( 'Activate your license to export order statuses.', 'woo-nalda-sync' );
                        } else {
                            esc_html_e( 'Configure SFTP settings first.', 'woo-nalda-sync' );
                        }
                        ?>
                    </p>
                <?php endif; ?>
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
            <div class="wns-table-responsive">
                <table id="wns-upload-history-table" class="wns-table" style="display: none;">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'ID', 'woo-nalda-sync' ); ?></th>
                            <th><?php esc_html_e( 'Type', 'woo-nalda-sync' ); ?></th>
                            <th><?php esc_html_e( 'Status', 'woo-nalda-sync' ); ?></th>
                            <th class="wns-hide-mobile"><?php esc_html_e( 'Domain', 'woo-nalda-sync' ); ?></th>
                            <th><?php esc_html_e( 'Created', 'woo-nalda-sync' ); ?></th>
                            <th class="wns-hide-mobile"><?php esc_html_e( 'Processed', 'woo-nalda-sync' ); ?></th>
                            <th><?php esc_html_e( 'Action', 'woo-nalda-sync' ); ?></th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>

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
                <span class="wns-progress-text"><?php echo esc_html( $setup_progress ); ?>/5</span>
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

                    <!-- Step 4: Product Export -->
                    <div class="wns-checklist-item <?php echo $product_export_status ? 'completed' : ''; ?>">
                        <div class="wns-checklist-icon">
                            <?php if ( $product_export_status ) : ?>
                                <span class="dashicons dashicons-yes-alt"></span>
                            <?php else : ?>
                                <span class="wns-step-num">4</span>
                            <?php endif; ?>
                        </div>
                        <div class="wns-checklist-content">
                            <div class="wns-checklist-title"><?php esc_html_e( 'Enable Product Export', 'woo-nalda-sync' ); ?></div>
                            <div class="wns-checklist-desc"><?php esc_html_e( 'Auto-export products to Nalda.', 'woo-nalda-sync' ); ?></div>
                        </div>
                        <?php if ( ! $product_export_status ) : ?>
                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=woo-nalda-sync-settings' ) ); ?>" class="wns-checklist-action">
                                <?php esc_html_e( 'Enable', 'woo-nalda-sync' ); ?>
                            </a>
                        <?php endif; ?>
                    </div>

                    <!-- Step 5: Order Import -->
                    <div class="wns-checklist-item <?php echo $order_import_status ? 'completed' : ''; ?>">
                        <div class="wns-checklist-icon">
                            <?php if ( $order_import_status ) : ?>
                                <span class="dashicons dashicons-yes-alt"></span>
                            <?php else : ?>
                                <span class="wns-step-num">5</span>
                            <?php endif; ?>
                        </div>
                        <div class="wns-checklist-content">
                            <div class="wns-checklist-title"><?php esc_html_e( 'Enable Order Import', 'woo-nalda-sync' ); ?></div>
                            <div class="wns-checklist-desc"><?php esc_html_e( 'Auto-import orders from Nalda.', 'woo-nalda-sync' ); ?></div>
                        </div>
                        <?php if ( ! $order_import_status ) : ?>
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
