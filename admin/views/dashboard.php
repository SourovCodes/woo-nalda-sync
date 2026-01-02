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
            <div class="wns-stat-value">0</div>
            <div class="wns-stat-label"><?php esc_html_e( 'Products Synced', 'woo-nalda-sync' ); ?></div>
        </div>
        <div class="wns-stat-card">
            <div class="wns-stat-icon success">
                <span class="dashicons dashicons-clipboard"></span>
            </div>
            <div class="wns-stat-value">0</div>
            <div class="wns-stat-label"><?php esc_html_e( 'Orders Synced', 'woo-nalda-sync' ); ?></div>
        </div>
        <div class="wns-stat-card">
            <div class="wns-stat-icon warning">
                <span class="dashicons dashicons-update"></span>
            </div>
            <div class="wns-stat-value">--</div>
            <div class="wns-stat-label"><?php esc_html_e( 'Last Sync', 'woo-nalda-sync' ); ?></div>
        </div>
        <div class="wns-stat-card">
            <div class="wns-stat-icon info">
                <span class="dashicons dashicons-yes-alt"></span>
            </div>
            <div class="wns-stat-value"><?php echo $is_licensed ? esc_html__( 'Active', 'woo-nalda-sync' ) : esc_html__( 'Inactive', 'woo-nalda-sync' ); ?></div>
            <div class="wns-stat-label"><?php esc_html_e( 'Sync Status', 'woo-nalda-sync' ); ?></div>
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
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=woo-nalda-sync-settings' ) ); ?>" class="wns-quick-action">
                        <span class="dashicons dashicons-admin-generic"></span>
                        <span><?php esc_html_e( 'Configure Settings', 'woo-nalda-sync' ); ?></span>
                    </a>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=woo-nalda-sync-license' ) ); ?>" class="wns-quick-action">
                        <span class="dashicons dashicons-admin-network"></span>
                        <span><?php esc_html_e( 'Manage License', 'woo-nalda-sync' ); ?></span>
                    </a>
                    <a href="#" class="wns-quick-action" onclick="return false;" style="opacity: 0.5; cursor: not-allowed;">
                        <span class="dashicons dashicons-update"></span>
                        <span><?php esc_html_e( 'Run Manual Sync', 'woo-nalda-sync' ); ?></span>
                    </a>
                    <a href="#" class="wns-quick-action" onclick="return false;" style="opacity: 0.5; cursor: not-allowed;">
                        <span class="dashicons dashicons-media-text"></span>
                        <span><?php esc_html_e( 'View Sync Logs', 'woo-nalda-sync' ); ?></span>
                    </a>
                </div>
            </div>
        </div>

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
                            <div class="wns-settings-row-label" style="display: flex; align-items: center; gap: 8px;">
                                <?php if ( $is_licensed ) : ?>
                                    <span class="dashicons dashicons-yes-alt" style="color: var(--wns-success);"></span>
                                <?php else : ?>
                                    <span class="dashicons dashicons-marker" style="color: var(--wns-gray-400);"></span>
                                <?php endif; ?>
                                <?php esc_html_e( '1. Activate your license', 'woo-nalda-sync' ); ?>
                            </div>
                            <p class="wns-settings-row-desc" style="margin-left: 28px;"><?php esc_html_e( 'Enter your license key to unlock all features.', 'woo-nalda-sync' ); ?></p>
                        </div>
                    </div>
                    <div class="wns-settings-row">
                        <div class="wns-settings-row-info" style="padding-right: 0;">
                            <div class="wns-settings-row-label" style="display: flex; align-items: center; gap: 8px;">
                                <span class="dashicons dashicons-marker" style="color: var(--wns-gray-400);"></span>
                                <?php esc_html_e( '2. Configure sync settings', 'woo-nalda-sync' ); ?>
                            </div>
                            <p class="wns-settings-row-desc" style="margin-left: 28px;"><?php esc_html_e( 'Choose what data to sync and how often.', 'woo-nalda-sync' ); ?></p>
                        </div>
                    </div>
                    <div class="wns-settings-row">
                        <div class="wns-settings-row-info" style="padding-right: 0;">
                            <div class="wns-settings-row-label" style="display: flex; align-items: center; gap: 8px;">
                                <span class="dashicons dashicons-marker" style="color: var(--wns-gray-400);"></span>
                                <?php esc_html_e( '3. Connect to Nalda', 'woo-nalda-sync' ); ?>
                            </div>
                            <p class="wns-settings-row-desc" style="margin-left: 28px;"><?php esc_html_e( 'Add your Nalda API credentials to establish connection.', 'woo-nalda-sync' ); ?></p>
                        </div>
                    </div>
                    <div class="wns-settings-row" style="border-bottom: none; padding-bottom: 0;">
                        <div class="wns-settings-row-info" style="padding-right: 0;">
                            <div class="wns-settings-row-label" style="display: flex; align-items: center; gap: 8px;">
                                <span class="dashicons dashicons-marker" style="color: var(--wns-gray-400);"></span>
                                <?php esc_html_e( '4. Start syncing', 'woo-nalda-sync' ); ?>
                            </div>
                            <p class="wns-settings-row-desc" style="margin-left: 28px;"><?php esc_html_e( 'Run your first sync to start keeping data in sync.', 'woo-nalda-sync' ); ?></p>
                        </div>
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
            <div class="wns-empty-state">
                <div class="wns-empty-state-icon">
                    <span class="dashicons dashicons-clock"></span>
                </div>
                <h3><?php esc_html_e( 'No activity yet', 'woo-nalda-sync' ); ?></h3>
                <p><?php esc_html_e( 'Your sync activity will appear here once you start syncing data.', 'woo-nalda-sync' ); ?></p>
                <?php if ( $is_licensed ) : ?>
                    <button class="wns-btn wns-btn-primary" disabled>
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
