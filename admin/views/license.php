<?php
/**
 * License Page View
 *
 * @package Woo_Nalda_Sync
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Determine license status class.
$status_class = 'is-inactive';
if ( $is_licensed ) {
    $status_class = 'is-active';
} elseif ( isset( $license_data['status'] ) && $license_data['status'] === 'expired' ) {
    $status_class = 'is-expired';
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
                <h1><?php esc_html_e( 'License', 'woo-nalda-sync' ); ?></h1>
                <p><?php esc_html_e( 'Manage your WooCommerce Nalda Sync license.', 'woo-nalda-sync' ); ?></p>
            </div>
        </div>
        <div class="wns-header-right">
            <span class="wns-version"><?php echo esc_html( 'v' . WOO_NALDA_SYNC_VERSION ); ?></span>
        </div>
    </div>

    <!-- License Status Card -->
    <div class="wns-card">
        <div class="wns-card-body">
            <div class="wns-license-status <?php echo esc_attr( $status_class ); ?>">
                <div class="wns-license-icon">
                    <?php if ( $is_licensed ) : ?>
                        <span class="dashicons dashicons-yes-alt"></span>
                    <?php elseif ( isset( $license_data['status'] ) && $license_data['status'] === 'expired' ) : ?>
                        <span class="dashicons dashicons-dismiss"></span>
                    <?php else : ?>
                        <span class="dashicons dashicons-warning"></span>
                    <?php endif; ?>
                </div>
                <div class="wns-license-info">
                    <?php if ( $is_licensed ) : ?>
                        <h3><?php esc_html_e( 'License Active', 'woo-nalda-sync' ); ?></h3>
                        <p><?php esc_html_e( 'Your license is active and all features are unlocked.', 'woo-nalda-sync' ); ?></p>
                    <?php elseif ( isset( $license_data['status'] ) && $license_data['status'] === 'expired' ) : ?>
                        <h3><?php esc_html_e( 'License Expired', 'woo-nalda-sync' ); ?></h3>
                        <p><?php esc_html_e( 'Your license has expired. Please renew to continue receiving updates.', 'woo-nalda-sync' ); ?></p>
                    <?php else : ?>
                        <h3><?php esc_html_e( 'No Active License', 'woo-nalda-sync' ); ?></h3>
                        <p><?php esc_html_e( 'Enter your license key below to activate.', 'woo-nalda-sync' ); ?></p>
                    <?php endif; ?>
                </div>
                <?php if ( $is_licensed ) : ?>
                    <div>
                        <button type="button" id="wns-validate-license" class="wns-btn wns-btn-secondary wns-btn-sm">
                            <span class="dashicons dashicons-update"></span>
                            <?php esc_html_e( 'Refresh', 'woo-nalda-sync' ); ?>
                        </button>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ( $is_licensed || ! empty( $license_key ) ) : ?>
                <!-- License Details -->
                <div class="wns-license-details">
                    <div class="wns-license-detail">
                        <span class="wns-license-detail-label"><?php esc_html_e( 'License Key', 'woo-nalda-sync' ); ?></span>
                        <span class="wns-license-detail-value"><?php echo esc_html( $masked_key ); ?></span>
                    </div>
                    <div class="wns-license-detail">
                        <span class="wns-license-detail-label"><?php esc_html_e( 'Status', 'woo-nalda-sync' ); ?></span>
                        <span class="wns-license-detail-value">
                            <?php if ( $is_licensed ) : ?>
                                <span class="wns-badge wns-badge-success"><?php esc_html_e( 'Active', 'woo-nalda-sync' ); ?></span>
                            <?php elseif ( isset( $license_data['status'] ) && $license_data['status'] === 'expired' ) : ?>
                                <span class="wns-badge wns-badge-danger"><?php esc_html_e( 'Expired', 'woo-nalda-sync' ); ?></span>
                            <?php else : ?>
                                <span class="wns-badge wns-badge-warning"><?php esc_html_e( 'Inactive', 'woo-nalda-sync' ); ?></span>
                            <?php endif; ?>
                        </span>
                    </div>
                    <div class="wns-license-detail">
                        <span class="wns-license-detail-label"><?php esc_html_e( 'Domain', 'woo-nalda-sync' ); ?></span>
                        <span class="wns-license-detail-value"><?php echo esc_html( $domain ); ?></span>
                    </div>
                    <?php if ( $expiration_date ) : ?>
                        <div class="wns-license-detail">
                            <span class="wns-license-detail-label"><?php esc_html_e( 'Expires', 'woo-nalda-sync' ); ?></span>
                            <span class="wns-license-detail-value">
                                <?php
                                $expiry_timestamp = strtotime( $expiration_date );
                                echo esc_html( date_i18n( get_option( 'date_format' ), $expiry_timestamp ) );
                                if ( $days_remaining !== null && $days_remaining > 0 ) {
                                    echo ' <span class="wns-badge wns-badge-info">' . sprintf(
                                        /* translators: %d: Number of days remaining */
                                        esc_html__( '%d days left', 'woo-nalda-sync' ),
                                        $days_remaining
                                    ) . '</span>';
                                }
                                ?>
                            </span>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if ( ! $is_licensed ) : ?>
        <!-- Activate License Card -->
        <div class="wns-card">
            <div class="wns-card-header">
                <h2>
                    <span class="dashicons dashicons-admin-network"></span>
                    <?php esc_html_e( 'Activate License', 'woo-nalda-sync' ); ?>
                </h2>
            </div>
            <div class="wns-card-body">
                <form id="wns-license-form" method="post">
                    <div class="wns-form-group">
                        <label for="wns-license-key" class="wns-form-label">
                            <?php esc_html_e( 'License Key', 'woo-nalda-sync' ); ?>
                            <span class="required">*</span>
                        </label>
                        <div class="wns-input-group">
                            <input type="text" id="wns-license-key" name="license_key" class="wns-form-input" placeholder="<?php esc_attr_e( 'XXXX-XXXX-XXXX-XXXX', 'woo-nalda-sync' ); ?>" value="<?php echo esc_attr( $license_key ); ?>">
                            <button type="submit" id="wns-activate-license" class="wns-btn wns-btn-primary">
                                <span class="dashicons dashicons-yes"></span>
                                <?php esc_html_e( 'Activate', 'woo-nalda-sync' ); ?>
                            </button>
                        </div>
                        <span class="wns-form-hint">
                            <?php esc_html_e( 'Enter the license key you received after purchase.', 'woo-nalda-sync' ); ?>
                        </span>
                    </div>
                </form>
            </div>
        </div>
    <?php else : ?>
        <!-- Deactivate License Card -->
        <div class="wns-card">
            <div class="wns-card-header">
                <h2>
                    <span class="dashicons dashicons-migrate"></span>
                    <?php esc_html_e( 'Deactivate License', 'woo-nalda-sync' ); ?>
                </h2>
            </div>
            <div class="wns-card-body">
                <p style="margin-bottom: 16px; color: var(--wns-gray-600);">
                    <?php esc_html_e( 'Deactivate your license to use it on another domain. You can reactivate it at any time.', 'woo-nalda-sync' ); ?>
                </p>
                <button type="button" id="wns-deactivate-license" class="wns-btn wns-btn-danger">
                    <span class="dashicons dashicons-dismiss"></span>
                    <?php esc_html_e( 'Deactivate License', 'woo-nalda-sync' ); ?>
                </button>
            </div>
        </div>
    <?php endif; ?>

    <!-- FAQ Card -->
    <div class="wns-card">
        <div class="wns-card-header">
            <h2>
                <span class="dashicons dashicons-editor-help"></span>
                <?php esc_html_e( 'Frequently Asked Questions', 'woo-nalda-sync' ); ?>
            </h2>
        </div>
        <div class="wns-card-body">
            <div class="wns-settings-section">
                <div class="wns-settings-row" style="border-bottom: 1px solid var(--wns-gray-100);">
                    <div class="wns-settings-row-info">
                        <div class="wns-settings-row-label"><?php esc_html_e( 'Where can I find my license key?', 'woo-nalda-sync' ); ?></div>
                        <p class="wns-settings-row-desc"><?php esc_html_e( 'Your license key was sent to your email after purchase. You can also find it in your account on our website.', 'woo-nalda-sync' ); ?></p>
                    </div>
                </div>
                <div class="wns-settings-row" style="border-bottom: 1px solid var(--wns-gray-100);">
                    <div class="wns-settings-row-info">
                        <div class="wns-settings-row-label"><?php esc_html_e( 'Can I use my license on multiple sites?', 'woo-nalda-sync' ); ?></div>
                        <p class="wns-settings-row-desc"><?php esc_html_e( 'Each license key can only be active on one domain at a time. You can deactivate and reactivate on different domains.', 'woo-nalda-sync' ); ?></p>
                    </div>
                </div>
                <div class="wns-settings-row" style="border-bottom: 1px solid var(--wns-gray-100);">
                    <div class="wns-settings-row-info">
                        <div class="wns-settings-row-label"><?php esc_html_e( 'What happens when my license expires?', 'woo-nalda-sync' ); ?></div>
                        <p class="wns-settings-row-desc"><?php esc_html_e( 'The plugin will continue to work, but you will not receive updates or support. We recommend renewing to stay secure.', 'woo-nalda-sync' ); ?></p>
                    </div>
                </div>
                <div class="wns-settings-row">
                    <div class="wns-settings-row-info">
                        <div class="wns-settings-row-label"><?php esc_html_e( 'How do I transfer my license?', 'woo-nalda-sync' ); ?></div>
                        <p class="wns-settings-row-desc"><?php esc_html_e( 'First deactivate the license on your current domain, then activate it on the new domain. Note: There are limits on domain changes.', 'woo-nalda-sync' ); ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Support Card -->
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
                <a href="https://jonakyds.com/account" target="_blank" class="wns-quick-action">
                    <span class="dashicons dashicons-admin-users"></span>
                    <span><?php esc_html_e( 'My Account', 'woo-nalda-sync' ); ?></span>
                </a>
            </div>
        </div>
    </div>
</div>
