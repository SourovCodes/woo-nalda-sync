/**
 * Admin JavaScript for Woo Nalda Sync
 *
 * @package Woo_Nalda_Sync
 */

(function ($) {
    'use strict';

    var WNS_Admin = {

        /**
         * Initialize
         */
        init: function () {
            this.bindEvents();
        },

        /**
         * Bind events
         */
        bindEvents: function () {
            $(document).on('click', '#wns-activate-license', this.activateLicense.bind(this));
            $(document).on('click', '#wns-deactivate-license', this.deactivateLicense.bind(this));
            $(document).on('click', '#wns-check-updates', this.checkUpdates.bind(this));
            $(document).on('click', '#wns-refresh-license', this.refreshLicense.bind(this));

            // Enter key on license input
            $(document).on('keypress', '#wns-license-key', function (e) {
                if (e.which === 13) {
                    e.preventDefault();
                    $('#wns-activate-license').click();
                }
            });
        },

        /**
         * Activate license
         */
        activateLicense: function (e) {
            e.preventDefault();

            var $button = $('#wns-activate-license');
            var $input = $('#wns-license-key');
            var licenseKey = $input.val().trim();

            if (!licenseKey) {
                this.showMessage('#wns-license-message', wnsAdmin.strings.error, 'error');
                $input.focus();
                return;
            }

            this.setButtonLoading($button, wnsAdmin.strings.activating);

            $.ajax({
                url: wnsAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wns_activate_license',
                    nonce: wnsAdmin.nonce,
                    license_key: licenseKey
                },
                success: function (response) {
                    if (response.success) {
                        this.showMessage('#wns-license-message', response.data.message, 'success');
                        if (response.data.reload) {
                            setTimeout(function () {
                                location.reload();
                            }, 1500);
                        }
                    } else {
                        this.showMessage('#wns-license-message', response.data.message, 'error');
                        this.resetButton($button, wnsAdmin.strings.activating.replace('...', ''));
                    }
                }.bind(this),
                error: function () {
                    this.showMessage('#wns-license-message', wnsAdmin.strings.error, 'error');
                    this.resetButton($button, 'Activate License');
                }.bind(this)
            });
        },

        /**
         * Deactivate license
         */
        deactivateLicense: function (e) {
            e.preventDefault();

            if (!confirm(wnsAdmin.strings.confirmDeactivate)) {
                return;
            }

            var $button = $('#wns-deactivate-license');

            this.setButtonLoading($button, wnsAdmin.strings.deactivating);

            $.ajax({
                url: wnsAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wns_deactivate_license',
                    nonce: wnsAdmin.nonce
                },
                success: function (response) {
                    if (response.success) {
                        this.showMessage('#wns-license-message', response.data.message, 'success');
                        if (response.data.reload) {
                            setTimeout(function () {
                                location.reload();
                            }, 1500);
                        }
                    } else {
                        this.showMessage('#wns-license-message', response.data.message, 'error');
                        this.resetButton($button, 'Deactivate License');
                    }
                }.bind(this),
                error: function () {
                    this.showMessage('#wns-license-message', wnsAdmin.strings.error, 'error');
                    this.resetButton($button, 'Deactivate License');
                }.bind(this)
            });
        },

        /**
         * Check for updates
         */
        checkUpdates: function (e) {
            e.preventDefault();

            var $button = $('#wns-check-updates');

            this.setButtonLoading($button, wnsAdmin.strings.checking);

            $.ajax({
                url: wnsAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wns_check_updates',
                    nonce: wnsAdmin.nonce
                },
                success: function (response) {
                    if (response.success) {
                        var messageType = response.data.update_available ? 'info' : 'success';
                        this.showMessage('#wns-update-message', response.data.message, messageType);

                        // Update latest version display
                        var $latestVersion = $('#wns-latest-version');
                        var versionHtml = '<code>' + response.data.latest_version + '</code>';

                        if (response.data.update_available) {
                            versionHtml += ' <span class="wns-status wns-status-update">Update Available</span>';

                            // Show or add update button
                            if ($('#wns-update-now').length === 0) {
                                // Reload to show update button
                                setTimeout(function () {
                                    location.reload();
                                }, 2000);
                            }
                        }

                        $latestVersion.html(versionHtml);
                    } else {
                        this.showMessage('#wns-update-message', response.data.message, 'error');
                    }
                    this.resetButton($button, 'Check for Updates');
                }.bind(this),
                error: function () {
                    this.showMessage('#wns-update-message', wnsAdmin.strings.error, 'error');
                    this.resetButton($button, 'Check for Updates');
                }.bind(this)
            });
        },

        /**
         * Refresh license status
         */
        refreshLicense: function (e) {
            e.preventDefault();

            var $button = $('#wns-refresh-license');

            this.setButtonLoading($button, wnsAdmin.strings.refreshing);

            $.ajax({
                url: wnsAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wns_refresh_license',
                    nonce: wnsAdmin.nonce
                },
                success: function (response) {
                    if (response.success) {
                        this.showMessage('#wns-license-message', response.data.message, 'success');
                        if (response.data.reload) {
                            setTimeout(function () {
                                location.reload();
                            }, 1500);
                        }
                    } else {
                        this.showMessage('#wns-license-message', response.data.message, 'error');
                        this.resetButton($button, 'Refresh Status');
                    }
                }.bind(this),
                error: function () {
                    this.showMessage('#wns-license-message', wnsAdmin.strings.error, 'error');
                    this.resetButton($button, 'Refresh Status');
                }.bind(this)
            });
        },

        /**
         * Set button to loading state
         */
        setButtonLoading: function ($button, text) {
            $button.addClass('loading').prop('disabled', true).text(text);
        },

        /**
         * Reset button from loading state
         */
        resetButton: function ($button, text) {
            $button.removeClass('loading').prop('disabled', false).text(text);
        },

        /**
         * Show message
         */
        showMessage: function (selector, message, type) {
            var $message = $(selector);
            $message
                .removeClass('success error info warning')
                .addClass(type)
                .html(message)
                .slideDown(200);

            // Auto-hide success messages
            if (type === 'success') {
                setTimeout(function () {
                    $message.slideUp(200);
                }, 5000);
            }
        }
    };

    // Initialize on document ready
    $(document).ready(function () {
        WNS_Admin.init();
    });

})(jQuery);
