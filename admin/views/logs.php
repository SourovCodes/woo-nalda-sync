<?php
/**
 * Sync Logs Page View
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
            <?php if ( isset( $is_licensed ) && $is_licensed ) : ?>
                <span class="wns-badge wns-badge-success">
                    <span class="dashicons dashicons-yes-alt"></span>
                    <?php esc_html_e( 'Licensed', 'woo-nalda-sync' ); ?>
                </span>
            <?php endif; ?>
        </div>
    </div>

    <!-- Logs Card -->
    <div class="wns-card wns-logs-card">
        <div class="wns-card-header">
            <h2>
                <span class="dashicons dashicons-list-view"></span>
                <?php esc_html_e( 'Sync History', 'woo-nalda-sync' ); ?>
            </h2>
            <div class="wns-card-actions">
                <div class="wns-logs-filter">
                    <select id="wns-logs-filter-type" class="wns-form-select wns-form-select-sm">
                        <option value=""><?php esc_html_e( 'All Types', 'woo-nalda-sync' ); ?></option>
                        <option value="product_export"><?php esc_html_e( 'Product Export', 'woo-nalda-sync' ); ?></option>
                        <option value="order_import"><?php esc_html_e( 'Order Import', 'woo-nalda-sync' ); ?></option>
                        <option value="order_status_export"><?php esc_html_e( 'Order Status Export', 'woo-nalda-sync' ); ?></option>
                    </select>
                    <select id="wns-logs-filter-status" class="wns-form-select wns-form-select-sm">
                        <option value=""><?php esc_html_e( 'All Status', 'woo-nalda-sync' ); ?></option>
                        <option value="success"><?php esc_html_e( 'Success', 'woo-nalda-sync' ); ?></option>
                        <option value="warning"><?php esc_html_e( 'Warning', 'woo-nalda-sync' ); ?></option>
                        <option value="error"><?php esc_html_e( 'Error', 'woo-nalda-sync' ); ?></option>
                    </select>
                </div>
                <button type="button" class="wns-btn wns-btn-secondary wns-btn-sm" id="wns-refresh-logs">
                    <span class="dashicons dashicons-update"></span>
                    <?php esc_html_e( 'Refresh', 'woo-nalda-sync' ); ?>
                </button>
                <button type="button" class="wns-btn wns-btn-danger wns-btn-sm" id="wns-clear-logs">
                    <span class="dashicons dashicons-trash"></span>
                    <?php esc_html_e( 'Clear All', 'woo-nalda-sync' ); ?>
                </button>
            </div>
        </div>
        <div class="wns-card-body wns-card-body-flush">
            <div id="wns-logs-container">
                <!-- Loading State -->
                <div class="wns-logs-loading" id="wns-logs-loading">
                    <span class="wns-spinner"></span>
                    <span><?php esc_html_e( 'Loading logs...', 'woo-nalda-sync' ); ?></span>
                </div>
                
                <!-- Empty State -->
                <div class="wns-logs-empty" id="wns-logs-empty" style="display: none;">
                    <div class="wns-logs-empty-icon">
                        <span class="dashicons dashicons-clipboard"></span>
                    </div>
                    <h3><?php esc_html_e( 'No Sync Logs Yet', 'woo-nalda-sync' ); ?></h3>
                    <p><?php esc_html_e( 'Sync logs will appear here after you run a sync. Start by exporting products or importing orders to see activity here.', 'woo-nalda-sync' ); ?></p>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=woo-nalda-sync' ) ); ?>" class="wns-btn wns-btn-primary">
                        <span class="dashicons dashicons-controls-play"></span>
                        <?php esc_html_e( 'Go to Dashboard', 'woo-nalda-sync' ); ?>
                    </a>
                </div>
                
                <!-- Logs List -->
                <div class="wns-logs-list" id="wns-logs-list" style="display: none;">
                    <!-- Logs will be rendered here dynamically -->
                </div>
            </div>
        </div>
        <div class="wns-card-footer wns-logs-footer" id="wns-logs-pagination" style="display: none;">
            <div class="wns-pagination">
                <button type="button" class="wns-btn wns-btn-secondary wns-btn-sm wns-logs-prev" disabled>
                    <span class="dashicons dashicons-arrow-left-alt2"></span>
                    <?php esc_html_e( 'Previous', 'woo-nalda-sync' ); ?>
                </button>
                <span class="wns-pagination-info"></span>
                <button type="button" class="wns-btn wns-btn-secondary wns-btn-sm wns-logs-next" disabled>
                    <?php esc_html_e( 'Next', 'woo-nalda-sync' ); ?>
                    <span class="dashicons dashicons-arrow-right-alt2"></span>
                </button>
            </div>
        </div>
    </div>

    <!-- Legend Card -->
    <div class="wns-card wns-legend-card">
        <div class="wns-card-header wns-card-header-collapsible" id="wns-legend-toggle">
            <h2>
                <span class="dashicons dashicons-info"></span>
                <?php esc_html_e( 'Status Legend', 'woo-nalda-sync' ); ?>
            </h2>
            <span class="dashicons dashicons-arrow-down-alt2 wns-collapse-icon"></span>
        </div>
        <div class="wns-card-body wns-legend-body">
            <div class="wns-legend-sections">
                <div class="wns-legend-section">
                    <h4><?php esc_html_e( 'Sync Types', 'woo-nalda-sync' ); ?></h4>
                    <div class="wns-legend-items">
                        <div class="wns-legend-item">
                            <span class="wns-badge wns-badge-info">
                                <span class="dashicons dashicons-upload"></span>
                                <?php esc_html_e( 'Product Export', 'woo-nalda-sync' ); ?>
                            </span>
                            <span class="wns-legend-desc"><?php esc_html_e( 'Products exported to Nalda platform.', 'woo-nalda-sync' ); ?></span>
                        </div>
                        <div class="wns-legend-item">
                            <span class="wns-badge wns-badge-purple">
                                <span class="dashicons dashicons-download"></span>
                                <?php esc_html_e( 'Order Import', 'woo-nalda-sync' ); ?>
                            </span>
                            <span class="wns-legend-desc"><?php esc_html_e( 'Orders imported from Nalda platform.', 'woo-nalda-sync' ); ?></span>
                        </div>
                        <div class="wns-legend-item">
                            <span class="wns-badge wns-badge-warning">
                                <span class="dashicons dashicons-update"></span>
                                <?php esc_html_e( 'Order Status Export', 'woo-nalda-sync' ); ?>
                            </span>
                            <span class="wns-legend-desc"><?php esc_html_e( 'Order statuses exported to Nalda platform.', 'woo-nalda-sync' ); ?></span>
                        </div>
                    </div>
                </div>
                <div class="wns-legend-section">
                    <h4><?php esc_html_e( 'Status Types', 'woo-nalda-sync' ); ?></h4>
                    <div class="wns-legend-items">
                        <div class="wns-legend-item">
                            <span class="wns-badge wns-badge-success">
                                <span class="dashicons dashicons-yes"></span>
                                <?php esc_html_e( 'Success', 'woo-nalda-sync' ); ?>
                            </span>
                            <span class="wns-legend-desc"><?php esc_html_e( 'Sync completed successfully without errors.', 'woo-nalda-sync' ); ?></span>
                        </div>
                        <div class="wns-legend-item">
                            <span class="wns-badge wns-badge-error">
                                <span class="dashicons dashicons-dismiss"></span>
                                <?php esc_html_e( 'Error', 'woo-nalda-sync' ); ?>
                            </span>
                            <span class="wns-legend-desc"><?php esc_html_e( 'Sync failed due to an error.', 'woo-nalda-sync' ); ?></span>
                        </div>
                    </div>
                </div>
                <div class="wns-legend-section">
                    <h4><?php esc_html_e( 'Trigger Types', 'woo-nalda-sync' ); ?></h4>
                    <div class="wns-legend-items">
                        <div class="wns-legend-item">
                            <span class="wns-badge wns-badge-secondary">
                                <span class="dashicons dashicons-admin-users"></span>
                                <?php esc_html_e( 'Manual', 'woo-nalda-sync' ); ?>
                            </span>
                            <span class="wns-legend-desc"><?php esc_html_e( 'Sync triggered manually by clicking the sync button.', 'woo-nalda-sync' ); ?></span>
                        </div>
                        <div class="wns-legend-item">
                            <span class="wns-badge wns-badge-default">
                                <span class="dashicons dashicons-clock"></span>
                                <?php esc_html_e( 'Automatic', 'woo-nalda-sync' ); ?>
                            </span>
                            <span class="wns-legend-desc"><?php esc_html_e( 'Sync triggered by scheduled cron job.', 'woo-nalda-sync' ); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
