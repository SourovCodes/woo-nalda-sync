<?php
/**
 * Admin Class
 *
 * Handles all admin-related functionality including settings page,
 * AJAX handlers, and admin notices.
 *
 * @package Woo_Nalda_Sync
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * WNS_Admin class
 */
class WNS_Admin {

    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_notices', array($this, 'admin_notices'));

        // AJAX handlers
        add_action('wp_ajax_wns_activate_license', array($this, 'ajax_activate_license'));
        add_action('wp_ajax_wns_deactivate_license', array($this, 'ajax_deactivate_license'));
        add_action('wp_ajax_wns_check_updates', array($this, 'ajax_check_updates'));
        add_action('wp_ajax_wns_refresh_license', array($this, 'ajax_refresh_license'));
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'woocommerce',
            __('Nalda Sync', 'woo-nalda-sync'),
            __('Nalda Sync', 'woo-nalda-sync'),
            'manage_woocommerce',
            'woo-nalda-sync',
            array($this, 'render_settings_page')
        );
    }

    /**
     * Enqueue admin scripts and styles
     *
     * @param string $hook Current admin page hook
     */
    public function enqueue_scripts($hook) {
        if ($hook !== 'woocommerce_page_woo-nalda-sync') {
            return;
        }

        wp_enqueue_style(
            'wns-admin',
            WOO_NALDA_SYNC_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            WOO_NALDA_SYNC_VERSION
        );

        wp_enqueue_script(
            'wns-admin',
            WOO_NALDA_SYNC_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            WOO_NALDA_SYNC_VERSION,
            true
        );

        wp_localize_script('wns-admin', 'wnsAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('wns_admin_nonce'),
            'strings' => array(
                'activating'     => __('Activating...', 'woo-nalda-sync'),
                'deactivating'   => __('Deactivating...', 'woo-nalda-sync'),
                'checking'       => __('Checking...', 'woo-nalda-sync'),
                'refreshing'     => __('Refreshing...', 'woo-nalda-sync'),
                'error'          => __('An error occurred. Please try again.', 'woo-nalda-sync'),
                'confirmDeactivate' => __('Are you sure you want to deactivate your license?', 'woo-nalda-sync'),
            ),
        ));
    }

    /**
     * Render settings page
     */
    public function render_settings_page() {
        $license = woo_nalda_sync()->license;
        $updater = woo_nalda_sync()->updater;

        $license_key = $license->get_license_key();
        $license_status = get_option('wns_license_status', '');
        $license_data = $license->get_license_data();
        $current_version = $updater->get_current_version();
        $is_active = $license->is_active();
        ?>
        <div class="wrap wns-wrap">
            <h1><?php esc_html_e('Woo Nalda Sync', 'woo-nalda-sync'); ?></h1>

            <div class="wns-settings-container">
                <!-- License Section -->
                <div class="wns-card">
                    <div class="wns-card-header">
                        <h2><?php esc_html_e('License', 'woo-nalda-sync'); ?></h2>
                        <?php if ($is_active) : ?>
                            <span class="wns-status wns-status-active"><?php esc_html_e('Active', 'woo-nalda-sync'); ?></span>
                        <?php else : ?>
                            <span class="wns-status wns-status-inactive"><?php esc_html_e('Inactive', 'woo-nalda-sync'); ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="wns-card-body">
                        <?php if (!$is_active) : ?>
                            <div class="wns-license-form">
                                <p class="description"><?php esc_html_e('Enter your license key to activate the plugin and receive automatic updates.', 'woo-nalda-sync'); ?></p>
                                <div class="wns-form-row">
                                    <label for="wns-license-key"><?php esc_html_e('License Key', 'woo-nalda-sync'); ?></label>
                                    <input type="text" id="wns-license-key" class="regular-text" placeholder="XXXX-XXXX-XXXX-XXXX" value="">
                                </div>
                                <div class="wns-form-actions">
                                    <button type="button" id="wns-activate-license" class="button button-primary">
                                        <?php esc_html_e('Activate License', 'woo-nalda-sync'); ?>
                                    </button>
                                </div>
                            </div>
                        <?php else : ?>
                            <div class="wns-license-info">
                                <table class="wns-info-table">
                                    <tr>
                                        <th><?php esc_html_e('License Key', 'woo-nalda-sync'); ?></th>
                                        <td><code><?php echo esc_html($license->mask_license_key($license_key)); ?></code></td>
                                    </tr>
                                    <tr>
                                        <th><?php esc_html_e('Status', 'woo-nalda-sync'); ?></th>
                                        <td>
                                            <?php
                                            $status_label = ucfirst($license_data['status'] ?? 'unknown');
                                            $status_class = ($license_data['status'] ?? '') === 'active' ? 'wns-status-active' : 'wns-status-inactive';
                                            ?>
                                            <span class="wns-status <?php echo esc_attr($status_class); ?>"><?php echo esc_html($status_label); ?></span>
                                        </td>
                                    </tr>
                                    <?php if (!empty($license_data['product']['name'])) : ?>
                                    <tr>
                                        <th><?php esc_html_e('Product', 'woo-nalda-sync'); ?></th>
                                        <td><?php echo esc_html($license_data['product']['name']); ?></td>
                                    </tr>
                                    <?php endif; ?>
                                    <?php if (!empty($license_data['active_domain'])) : ?>
                                    <tr>
                                        <th><?php esc_html_e('Activated Domain', 'woo-nalda-sync'); ?></th>
                                        <td><?php echo esc_html($license_data['active_domain']); ?></td>
                                    </tr>
                                    <?php endif; ?>
                                    <?php if (!empty($license_data['activated_at'])) : ?>
                                    <tr>
                                        <th><?php esc_html_e('Activated On', 'woo-nalda-sync'); ?></th>
                                        <td><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($license_data['activated_at']))); ?></td>
                                    </tr>
                                    <?php endif; ?>
                                    <?php if (!empty($license_data['expires_at'])) : ?>
                                    <tr>
                                        <th><?php esc_html_e('Expires On', 'woo-nalda-sync'); ?></th>
                                        <td>
                                            <?php 
                                            echo esc_html(date_i18n(get_option('date_format'), strtotime($license_data['expires_at']))); 
                                            if (!empty($license_data['is_expired'])) {
                                                echo ' <span class="wns-status wns-status-expired">' . esc_html__('Expired', 'woo-nalda-sync') . '</span>';
                                            }
                                            ?>
                                        </td>
                                    </tr>
                                    <?php endif; ?>
                                    <?php if (isset($license_data['domain_changes_remaining'])) : ?>
                                    <tr>
                                        <th><?php esc_html_e('Domain Changes Remaining', 'woo-nalda-sync'); ?></th>
                                        <td><?php echo esc_html($license_data['domain_changes_remaining']); ?></td>
                                    </tr>
                                    <?php endif; ?>
                                </table>
                                <div class="wns-form-actions">
                                    <button type="button" id="wns-refresh-license" class="button">
                                        <?php esc_html_e('Refresh Status', 'woo-nalda-sync'); ?>
                                    </button>
                                    <button type="button" id="wns-deactivate-license" class="button button-link-delete">
                                        <?php esc_html_e('Deactivate License', 'woo-nalda-sync'); ?>
                                    </button>
                                </div>
                            </div>
                        <?php endif; ?>
                        <div id="wns-license-message" class="wns-message" style="display: none;"></div>
                    </div>
                </div>

                <!-- Updates Section -->
                <div class="wns-card">
                    <div class="wns-card-header">
                        <h2><?php esc_html_e('Updates', 'woo-nalda-sync'); ?></h2>
                    </div>
                    <div class="wns-card-body">
                        <table class="wns-info-table">
                            <tr>
                                <th><?php esc_html_e('Current Version', 'woo-nalda-sync'); ?></th>
                                <td><code><?php echo esc_html($current_version); ?></code></td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e('Latest Version', 'woo-nalda-sync'); ?></th>
                                <td id="wns-latest-version">
                                    <?php
                                    $update_data = get_transient('wns_update_check');
                                    if ($update_data && !empty($update_data['latest_version'])) {
                                        echo '<code>' . esc_html($update_data['latest_version']) . '</code>';
                                        if (!empty($update_data['update_available'])) {
                                            echo ' <span class="wns-status wns-status-update">' . esc_html__('Update Available', 'woo-nalda-sync') . '</span>';
                                        }
                                    } else {
                                        echo '<span class="wns-muted">' . esc_html__('Check for updates to see latest version', 'woo-nalda-sync') . '</span>';
                                    }
                                    ?>
                                </td>
                            </tr>
                        </table>
                        <div class="wns-form-actions">
                            <button type="button" id="wns-check-updates" class="button button-primary">
                                <?php esc_html_e('Check for Updates', 'woo-nalda-sync'); ?>
                            </button>
                            <?php
                            // Show update button if update is available
                            if ($update_data && !empty($update_data['update_available'])) :
                                $update_url = wp_nonce_url(
                                    self_admin_url('update.php?action=upgrade-plugin&plugin=' . WOO_NALDA_SYNC_PLUGIN_BASENAME),
                                    'upgrade-plugin_' . WOO_NALDA_SYNC_PLUGIN_BASENAME
                                );
                            ?>
                            <a href="<?php echo esc_url($update_url); ?>" id="wns-update-now" class="button button-primary">
                                <?php esc_html_e('Update Now', 'woo-nalda-sync'); ?>
                            </a>
                            <?php endif; ?>
                        </div>
                        <div id="wns-update-message" class="wns-message" style="display: none;"></div>
                    </div>
                </div>

                <!-- Plugin Info Section -->
                <div class="wns-card">
                    <div class="wns-card-header">
                        <h2><?php esc_html_e('Plugin Information', 'woo-nalda-sync'); ?></h2>
                    </div>
                    <div class="wns-card-body">
                        <table class="wns-info-table">
                            <tr>
                                <th><?php esc_html_e('Plugin Name', 'woo-nalda-sync'); ?></th>
                                <td><?php esc_html_e('Woo Nalda Sync', 'woo-nalda-sync'); ?></td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e('Product Slug', 'woo-nalda-sync'); ?></th>
                                <td><code><?php echo esc_html(WOO_NALDA_SYNC_PRODUCT_SLUG); ?></code></td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e('Site Domain', 'woo-nalda-sync'); ?></th>
                                <td><code><?php echo esc_html(Woo_Nalda_Sync::get_site_domain()); ?></code></td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e('Documentation', 'woo-nalda-sync'); ?></th>
                                <td><a href="https://jonakyds.com/docs/woo-nalda-sync" target="_blank"><?php esc_html_e('View Documentation', 'woo-nalda-sync'); ?></a></td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e('Support', 'woo-nalda-sync'); ?></th>
                                <td><a href="https://jonakyds.com/support" target="_blank"><?php esc_html_e('Get Support', 'woo-nalda-sync'); ?></a></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Admin notices
     */
    public function admin_notices() {
        // Only show on plugin pages
        $screen = get_current_screen();
        if (!$screen || strpos($screen->id, 'woo-nalda-sync') === false) {
            // Show license notice on all admin pages if not activated
            $license_status = get_option('wns_license_status', '');
            if ($license_status !== 'active') {
                ?>
                <div class="notice notice-warning is-dismissible">
                    <p>
                        <?php 
                        echo wp_kses_post(sprintf(
                            __('Woo Nalda Sync: Please <a href="%s">activate your license</a> to enable automatic updates and full functionality.', 'woo-nalda-sync'),
                            admin_url('admin.php?page=woo-nalda-sync')
                        ));
                        ?>
                    </p>
                </div>
                <?php
            }
        }
    }

    /**
     * AJAX: Activate license
     */
    public function ajax_activate_license() {
        check_ajax_referer('wns_admin_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'woo-nalda-sync')));
        }

        $license_key = isset($_POST['license_key']) ? sanitize_text_field(wp_unslash($_POST['license_key'])) : '';

        if (empty($license_key)) {
            wp_send_json_error(array('message' => __('Please enter a license key.', 'woo-nalda-sync')));
        }

        $result = woo_nalda_sync()->license->activate($license_key);

        if ($result['success']) {
            wp_send_json_success(array(
                'message' => $result['message'],
                'license' => $result['license'] ?? array(),
                'reload'  => true,
            ));
        } else {
            wp_send_json_error(array('message' => $result['message']));
        }
    }

    /**
     * AJAX: Deactivate license
     */
    public function ajax_deactivate_license() {
        check_ajax_referer('wns_admin_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'woo-nalda-sync')));
        }

        $result = woo_nalda_sync()->license->deactivate();

        if ($result['success']) {
            wp_send_json_success(array(
                'message' => $result['message'],
                'reload'  => true,
            ));
        } else {
            wp_send_json_error(array('message' => $result['message']));
        }
    }

    /**
     * AJAX: Check for updates
     */
    public function ajax_check_updates() {
        check_ajax_referer('wns_admin_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'woo-nalda-sync')));
        }

        $result = woo_nalda_sync()->updater->force_check();

        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error(array('message' => $result['message']));
        }
    }

    /**
     * AJAX: Refresh license status
     */
    public function ajax_refresh_license() {
        check_ajax_referer('wns_admin_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'woo-nalda-sync')));
        }

        $result = woo_nalda_sync()->license->get_status();

        if ($result['success']) {
            wp_send_json_success(array(
                'message' => __('License status refreshed.', 'woo-nalda-sync'),
                'license' => $result['license'],
                'reload'  => true,
            ));
        } else {
            wp_send_json_error(array('message' => $result['message']));
        }
    }
}
