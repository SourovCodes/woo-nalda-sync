<?php
/**
 * Sync Logs Page Template
 *
 * @package Woo_Nalda_Sync
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<div class="wrap wns-wrap">
    <!-- Header -->
    <div class="wns-header">
        <div class="wns-header-left">
            <div class="wns-logo">
                <span class="dashicons dashicons-update"></span>
            </div>
            <div>
                <h1><?php esc_html_e( 'Sync Logs', 'woo-nalda-sync' ); ?></h1>
                <p><?php esc_html_e( 'View the history of all sync operations', 'woo-nalda-sync' ); ?></p>
            </div>
        </div>
        <div class="wns-header-right">
            <span class="wns-version"><?php echo esc_html( 'v' . WOO_NALDA_SYNC_VERSION ); ?></span>
        </div>
    </div>

    <!-- Logs Card -->
    <div class="wns-card">
        <div class="wns-card-header">
            <h2>
                <span class="dashicons dashicons-list-view"></span>
                <?php esc_html_e( 'Sync History', 'woo-nalda-sync' ); ?>
            </h2>
            <div class="wns-card-actions">
                <button type="button" class="wns-btn wns-btn-secondary wns-btn-sm" id="wns-refresh-logs">
                    <span class="dashicons dashicons-update"></span>
                    <?php esc_html_e( 'Refresh', 'woo-nalda-sync' ); ?>
                </button>
                <button type="button" class="wns-btn wns-btn-secondary wns-btn-sm" id="wns-clear-logs">
                    <span class="dashicons dashicons-trash"></span>
                    <?php esc_html_e( 'Clear Logs', 'woo-nalda-sync' ); ?>
                </button>
            </div>
        </div>
        <div class="wns-card-body">
            <div id="wns-logs-container">
                <div class="wns-logs-loading">
                    <span class="spinner is-active" style="float: none;"></span>
                    <?php esc_html_e( 'Loading logs...', 'woo-nalda-sync' ); ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Log Legend -->
    <div class="wns-card" style="margin-top: 24px;">
        <div class="wns-card-header">
            <h2>
                <span class="dashicons dashicons-info"></span>
                <?php esc_html_e( 'Legend', 'woo-nalda-sync' ); ?>
            </h2>
        </div>
        <div class="wns-card-body">
            <div class="wns-legend-grid">
                <div class="wns-legend-section">
                    <h4><?php esc_html_e( 'Sync Types', 'woo-nalda-sync' ); ?></h4>
                    <div class="wns-legend-items">
                        <div class="wns-legend-item">
                            <span class="wns-badge wns-badge-info"><?php esc_html_e( 'Product Export', 'woo-nalda-sync' ); ?></span>
                            <span class="wns-legend-desc"><?php esc_html_e( 'Products exported to Nalda', 'woo-nalda-sync' ); ?></span>
                        </div>
                        <div class="wns-legend-item">
                            <span class="wns-badge wns-badge-purple"><?php esc_html_e( 'Order Import', 'woo-nalda-sync' ); ?></span>
                            <span class="wns-legend-desc"><?php esc_html_e( 'Orders imported from Nalda', 'woo-nalda-sync' ); ?></span>
                        </div>
                    </div>
                </div>
                <div class="wns-legend-section">
                    <h4><?php esc_html_e( 'Trigger Types', 'woo-nalda-sync' ); ?></h4>
                    <div class="wns-legend-items">
                        <div class="wns-legend-item">
                            <span class="wns-badge wns-badge-secondary"><?php esc_html_e( 'Manual', 'woo-nalda-sync' ); ?></span>
                            <span class="wns-legend-desc"><?php esc_html_e( 'Triggered by clicking sync button', 'woo-nalda-sync' ); ?></span>
                        </div>
                        <div class="wns-legend-item">
                            <span class="wns-badge wns-badge-default"><?php esc_html_e( 'Automatic', 'woo-nalda-sync' ); ?></span>
                            <span class="wns-legend-desc"><?php esc_html_e( 'Triggered by scheduled cron job', 'woo-nalda-sync' ); ?></span>
                        </div>
                    </div>
                </div>
                <div class="wns-legend-section">
                    <h4><?php esc_html_e( 'Status Types', 'woo-nalda-sync' ); ?></h4>
                    <div class="wns-legend-items">
                        <div class="wns-legend-item">
                            <span class="wns-badge wns-badge-success"><?php esc_html_e( 'Success', 'woo-nalda-sync' ); ?></span>
                            <span class="wns-legend-desc"><?php esc_html_e( 'Sync completed successfully', 'woo-nalda-sync' ); ?></span>
                        </div>
                        <div class="wns-legend-item">
                            <span class="wns-badge wns-badge-error"><?php esc_html_e( 'Error', 'woo-nalda-sync' ); ?></span>
                            <span class="wns-legend-desc"><?php esc_html_e( 'Sync failed with an error', 'woo-nalda-sync' ); ?></span>
                        </div>
                        <div class="wns-legend-item">
                            <span class="wns-badge wns-badge-warning"><?php esc_html_e( 'Warning', 'woo-nalda-sync' ); ?></span>
                            <span class="wns-legend-desc"><?php esc_html_e( 'Sync completed with warnings', 'woo-nalda-sync' ); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
