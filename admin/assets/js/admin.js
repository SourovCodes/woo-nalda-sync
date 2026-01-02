/**
 * WooCommerce Nalda Sync - Admin JavaScript
 *
 * Handles admin interactions and AJAX requests.
 */

(function ($) {
    'use strict';

    /**
     * Toast notification helper
     */
    const Toast = {
        show: function (message, type = 'success') {
            // Remove existing toasts
            $('.wns-toast').remove();

            const toast = $('<div class="wns-toast wns-toast-' + type + '">' +
                '<span class="dashicons dashicons-' + (type === 'success' ? 'yes' : 'warning') + '"></span>' +
                '<span>' + message + '</span>' +
                '</div>');

            $('body').append(toast);

            // Trigger animation
            setTimeout(function () {
                toast.addClass('show');
            }, 10);

            // Auto hide after 3 seconds
            setTimeout(function () {
                toast.removeClass('show');
                setTimeout(function () {
                    toast.remove();
                }, 300);
            }, 3000);
        },

        success: function (message) {
            this.show(message, 'success');
        },

        error: function (message) {
            this.show(message, 'error');
        },

        warning: function (message) {
            this.show(message, 'warning');
        }
    };

    /**
     * License Manager
     */
    const LicenseManager = {
        init: function () {
            this.bindEvents();
        },

        bindEvents: function () {
            // Activate license
            $(document).on('submit', '#wns-license-form', this.handleActivate.bind(this));
            $(document).on('click', '#wns-activate-license', this.handleActivate.bind(this));

            // Deactivate license
            $(document).on('click', '#wns-deactivate-license', this.handleDeactivate.bind(this));

            // Validate license
            $(document).on('click', '#wns-validate-license', this.handleValidate.bind(this));
        },

        handleActivate: function (e) {
            e.preventDefault();

            const $form = $('#wns-license-form');
            const $button = $('#wns-activate-license');
            const licenseKey = $('#wns-license-key').val().trim();

            if (!licenseKey) {
                Toast.error(wooNaldaSync.strings.error);
                return;
            }

            this.setLoading($button, true, wooNaldaSync.strings.activating);

            $.ajax({
                url: wooNaldaSync.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'woo_nalda_sync_activate_license',
                    nonce: wooNaldaSync.nonce,
                    license_key: licenseKey
                },
                success: function (response) {
                    if (response.success) {
                        Toast.success(response.data.message);
                        // Reload page to show updated status
                        setTimeout(function () {
                            window.location.reload();
                        }, 1000);
                    } else {
                        Toast.error(response.data.message);
                        LicenseManager.setLoading($button, false);
                    }
                },
                error: function () {
                    Toast.error(wooNaldaSync.strings.error);
                    LicenseManager.setLoading($button, false);
                }
            });
        },

        handleDeactivate: function (e) {
            e.preventDefault();

            if (!confirm(wooNaldaSync.strings.confirmDeactivate)) {
                return;
            }

            const $button = $(e.currentTarget);

            this.setLoading($button, true, wooNaldaSync.strings.deactivating);

            $.ajax({
                url: wooNaldaSync.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'woo_nalda_sync_deactivate_license',
                    nonce: wooNaldaSync.nonce
                },
                success: function (response) {
                    if (response.success) {
                        Toast.success(response.data.message);
                        // Reload page to show updated status
                        setTimeout(function () {
                            window.location.reload();
                        }, 1000);
                    } else {
                        Toast.error(response.data.message);
                        LicenseManager.setLoading($button, false);
                    }
                },
                error: function () {
                    Toast.error(wooNaldaSync.strings.error);
                    LicenseManager.setLoading($button, false);
                }
            });
        },

        handleValidate: function (e) {
            e.preventDefault();

            const $button = $(e.currentTarget);

            this.setLoading($button, true, wooNaldaSync.strings.validating);

            $.ajax({
                url: wooNaldaSync.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'woo_nalda_sync_validate_license',
                    nonce: wooNaldaSync.nonce
                },
                success: function (response) {
                    if (response.success) {
                        Toast.success(response.data.message);
                    } else {
                        Toast.error(response.data.message);
                    }
                    LicenseManager.setLoading($button, false);
                },
                error: function () {
                    Toast.error(wooNaldaSync.strings.error);
                    LicenseManager.setLoading($button, false);
                }
            });
        },

        setLoading: function ($button, loading, text) {
            if (loading) {
                $button.data('original-text', $button.html());
                $button.prop('disabled', true).html(
                    '<span class="wns-spinner"></span> ' + (text || '')
                );
            } else {
                $button.prop('disabled', false).html($button.data('original-text'));
            }
        }
    };

    /**
     * Settings Manager
     */
    const SettingsManager = {
        init: function () {
            this.bindEvents();
        },

        bindEvents: function () {
            // Save settings via AJAX
            $(document).on('submit', '#wns-settings-form', this.handleSave.bind(this));

            // Tab navigation
            $(document).on('click', '.wns-tab', this.handleTabClick.bind(this));
        },

        handleSave: function (e) {
            e.preventDefault();

            const $form = $(e.currentTarget);
            const $button = $form.find('button[type="submit"]');
            const formData = $form.serialize();

            this.setLoading($button, true, wooNaldaSync.strings.saving);

            $.ajax({
                url: wooNaldaSync.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'woo_nalda_sync_save_settings',
                    nonce: wooNaldaSync.nonce,
                    form_data: formData
                },
                success: function (response) {
                    if (response.success) {
                        Toast.success(response.data.message);
                    } else {
                        Toast.error(response.data.message);
                    }
                    SettingsManager.setLoading($button, false);
                },
                error: function () {
                    Toast.error(wooNaldaSync.strings.error);
                    SettingsManager.setLoading($button, false);
                }
            });
        },

        handleTabClick: function (e) {
            e.preventDefault();

            const $tab = $(e.currentTarget);
            const target = $tab.data('tab');

            // Update tabs
            $('.wns-tab').removeClass('active');
            $tab.addClass('active');

            // Update content
            $('.wns-tab-content').removeClass('active');
            $('#' + target).addClass('active');

            // Save to localStorage
            localStorage.setItem('wns-active-tab', target);
        },

        setLoading: function ($button, loading, text) {
            if (loading) {
                $button.data('original-text', $button.html());
                $button.prop('disabled', true).html(
                    '<span class="wns-spinner"></span> ' + (text || '')
                );
            } else {
                $button.prop('disabled', false).html($button.data('original-text'));
            }
        },

        restoreActiveTab: function () {
            const activeTab = localStorage.getItem('wns-active-tab');
            if (activeTab && $('.wns-tab[data-tab="' + activeTab + '"]').length) {
                $('.wns-tab[data-tab="' + activeTab + '"]').trigger('click');
            }
        }
    };

    /**
     * Toggle Switches
     */
    const ToggleSwitches = {
        init: function () {
            this.bindEvents();
        },

        bindEvents: function () {
            // Handle toggle changes
            $(document).on('change', '.wns-toggle input', function () {
                const $toggle = $(this);
                const isChecked = $toggle.is(':checked');

                // Visual feedback
                if (isChecked) {
                    $toggle.closest('.wns-toggle').addClass('is-active');
                } else {
                    $toggle.closest('.wns-toggle').removeClass('is-active');
                }
            });
        }
    };

    /**
     * Initialize on document ready
     */
    $(document).ready(function () {
        LicenseManager.init();
        SettingsManager.init();
        ToggleSwitches.init();

        // Restore active tab
        SettingsManager.restoreActiveTab();

        // Initialize any select2 or other enhanced inputs
        if ($.fn.select2) {
            $('.wns-select2').select2({
                width: '100%'
            });
        }
    });

    // Expose Toast for external use
    window.WNSToast = Toast;

})(jQuery);
