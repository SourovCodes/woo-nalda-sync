<?php
/**
 * Uninstall script for Woo Nalda Sync
 *
 * This file is executed when the plugin is deleted.
 * It cleans up all plugin data from the database.
 *
 * @package Woo_Nalda_Sync
 */

// Exit if accessed directly or not uninstalling
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Delete options
delete_option('wns_license_key');
delete_option('wns_license_status');
delete_option('wns_license_data');
delete_option('wns_local_key');

// Delete transients
delete_transient('wns_update_check');
delete_transient('wns_license_valid');

// Clear scheduled events
wp_clear_scheduled_hook('wns_daily_license_check');
wp_clear_scheduled_hook('wns_update_check');
