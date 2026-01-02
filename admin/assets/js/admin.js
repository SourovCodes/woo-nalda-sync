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

            const icons = {
                success: 'yes',
                error: 'warning',
                warning: 'flag',
                info: 'info'
            };

            const toast = $('<div class="wns-toast wns-toast-' + type + '">' +
                '<span class="dashicons dashicons-' + (icons[type] || 'info') + '"></span>' +
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
            }, 3500);
        },

        success: function (message) {
            this.show(message, 'success');
        },

        error: function (message) {
            this.show(message, 'error');
        },

        warning: function (message) {
            this.show(message, 'warning');
        },

        info: function (message) {
            this.show(message, 'info');
        }
    };

    /**
     * Helper function to set button loading state
     */
    function setButtonLoading($button, loading, text) {
        if (loading) {
            $button.data('original-text', $button.html());
            $button.prop('disabled', true).html(
                '<span class="wns-spinner"></span> ' + (text || '')
            );
        } else {
            $button.prop('disabled', false).html($button.data('original-text'));
        }
    }

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

            const $button = $('#wns-activate-license');
            const licenseKey = $('#wns-license-key').val().trim();

            if (!licenseKey) {
                Toast.error(wooNaldaSync.strings.error);
                return;
            }

            setButtonLoading($button, true, wooNaldaSync.strings.activating);

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
                        setButtonLoading($button, false);
                    }
                },
                error: function () {
                    Toast.error(wooNaldaSync.strings.error);
                    setButtonLoading($button, false);
                }
            });
        },

        handleDeactivate: function (e) {
            e.preventDefault();

            if (!confirm(wooNaldaSync.strings.confirmDeactivate)) {
                return;
            }

            const $button = $(e.currentTarget);

            setButtonLoading($button, true, wooNaldaSync.strings.deactivating);

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
                        setButtonLoading($button, false);
                    }
                },
                error: function () {
                    Toast.error(wooNaldaSync.strings.error);
                    setButtonLoading($button, false);
                }
            });
        },

        handleValidate: function (e) {
            e.preventDefault();

            const $button = $(e.currentTarget);

            setButtonLoading($button, true, wooNaldaSync.strings.validating);

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
                        setButtonLoading($button, false);
                    } else {
                        Toast.error(response.data.message);
                        // Reload page if license status changed
                        if (response.data && response.data.status_changed) {
                            setTimeout(function () {
                                window.location.reload();
                            }, 1500);
                        } else {
                            setButtonLoading($button, false);
                        }
                    }
                },
                error: function () {
                    Toast.error(wooNaldaSync.strings.error);
                    setButtonLoading($button, false);
                }
            });
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

            // Test SFTP connection
            $(document).on('click', '#wns-test-sftp', this.handleTestSftp.bind(this));

            // Test Nalda API connection
            $(document).on('click', '#wns-test-nalda-api', this.handleTestNaldaApi.bind(this));
        },

        handleSave: function (e) {
            e.preventDefault();

            const $form = $(e.currentTarget);
            const $button = $form.find('button[type="submit"]');
            const formData = $form.serialize();

            setButtonLoading($button, true, wooNaldaSync.strings.saving);

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
                    setButtonLoading($button, false);
                },
                error: function () {
                    Toast.error(wooNaldaSync.strings.error);
                    setButtonLoading($button, false);
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

        handleTestSftp: function (e) {
            e.preventDefault();

            const $button = $(e.currentTarget);

            setButtonLoading($button, true, wooNaldaSync.strings.testing);

            $.ajax({
                url: wooNaldaSync.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'woo_nalda_sync_validate_sftp',
                    nonce: wooNaldaSync.nonce,
                    sftp_host: $('#sftp_host').val(),
                    sftp_port: $('#sftp_port').val(),
                    sftp_username: $('#sftp_username').val(),
                    sftp_password: $('#sftp_password').val()
                },
                success: function (response) {
                    if (response.success) {
                        Toast.success(response.data.message);
                    } else {
                        Toast.error(response.data.message);
                    }
                    setButtonLoading($button, false);
                },
                error: function () {
                    Toast.error(wooNaldaSync.strings.error);
                    setButtonLoading($button, false);
                }
            });
        },

        handleTestNaldaApi: function (e) {
            e.preventDefault();

            const $button = $(e.currentTarget);

            setButtonLoading($button, true, wooNaldaSync.strings.testing);

            $.ajax({
                url: wooNaldaSync.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'woo_nalda_sync_validate_nalda_api',
                    nonce: wooNaldaSync.nonce,
                    nalda_api_key: $('#nalda_api_key').val(),
                    nalda_api_url: $('#nalda_api_url').val()
                },
                success: function (response) {
                    if (response.success) {
                        Toast.success(response.data.message);
                    } else {
                        Toast.error(response.data.message);
                    }
                    setButtonLoading($button, false);
                },
                error: function () {
                    Toast.error(wooNaldaSync.strings.error);
                    setButtonLoading($button, false);
                }
            });
        },

        restoreActiveTab: function () {
            const activeTab = localStorage.getItem('wns-active-tab');
            if (activeTab && $('.wns-tab[data-tab="' + activeTab + '"]').length) {
                $('.wns-tab[data-tab="' + activeTab + '"]').trigger('click');
            }
        }
    };

    /**
     * Sync Manager
     */
    const SyncManager = {
        init: function () {
            this.bindEvents();
        },

        bindEvents: function () {
            // Run product sync
            $(document).on('click', '#wns-run-product-sync, #wns-run-first-sync', this.handleProductSync.bind(this));

            // Run order sync
            $(document).on('click', '#wns-run-order-sync', this.handleOrderSync.bind(this));
        },

        handleProductSync: function (e) {
            e.preventDefault();

            const $button = $(e.currentTarget);

            setButtonLoading($button, true, wooNaldaSync.strings.syncing);

            $.ajax({
                url: wooNaldaSync.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'woo_nalda_sync_run_product_sync',
                    nonce: wooNaldaSync.nonce
                },
                success: function (response) {
                    if (response.success) {
                        Toast.success(response.data.message);
                        // Reload page to update stats
                        setTimeout(function () {
                            window.location.reload();
                        }, 2000);
                    } else {
                        Toast.error(response.data.message);
                        setButtonLoading($button, false);
                    }
                },
                error: function () {
                    Toast.error(wooNaldaSync.strings.error);
                    setButtonLoading($button, false);
                }
            });
        },

        handleOrderSync: function (e) {
            e.preventDefault();

            const $button = $(e.currentTarget);

            setButtonLoading($button, true, wooNaldaSync.strings.syncing);

            $.ajax({
                url: wooNaldaSync.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'woo_nalda_sync_run_order_sync',
                    nonce: wooNaldaSync.nonce,
                    range: 'today'
                },
                success: function (response) {
                    if (response.success) {
                        Toast.success(response.data.message);
                        // Reload page to update stats
                        setTimeout(function () {
                            window.location.reload();
                        }, 2000);
                    } else {
                        Toast.error(response.data.message);
                        setButtonLoading($button, false);
                    }
                },
                error: function () {
                    Toast.error(wooNaldaSync.strings.error);
                    setButtonLoading($button, false);
                }
            });
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
        SyncManager.init();
        ToggleSwitches.init();

        // Restore active tab
        SettingsManager.restoreActiveTab();

        // Initialize any select2 or other enhanced inputs
        if ($.fn.select2) {
            $('.wns-select2').select2({
                width: '100%'
            });
        }

        // Password toggle visibility
        $(document).on('click', '.wns-password-toggle', function () {
            const $input = $(this).siblings('input');
            const type = $input.attr('type') === 'password' ? 'text' : 'password';
            $input.attr('type', type);
            $(this).find('.dashicons')
                .toggleClass('dashicons-visibility')
                .toggleClass('dashicons-hidden');
        });
    });

    // Expose Toast for external use
    window.WNSToast = Toast;

})(jQuery);
