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

            // Run order status export
            $(document).on('click', '#wns-run-order-status-export', this.handleOrderStatusExport.bind(this));
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
                    nonce: wooNaldaSync.nonce
                    // Range will be taken from plugin settings
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

        handleOrderStatusExport: function (e) {
            e.preventDefault();

            const $button = $(e.currentTarget);

            setButtonLoading($button, true, wooNaldaSync.strings.exportingOrderStatus || wooNaldaSync.strings.syncing);

            $.ajax({
                url: wooNaldaSync.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'woo_nalda_sync_run_order_status_export',
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
     * Upload History Manager
     */
    const UploadHistoryManager = {
        currentPage: 1,
        perPage: 5,

        init: function () {
            this.bindEvents();
            // Load history on init if container exists
            if ($('#wns-upload-history-table').length) {
                this.loadHistory();
            }
        },

        bindEvents: function () {
            // Refresh history
            $(document).on('click', '#wns-refresh-upload-history', this.handleRefresh.bind(this));

            // Pagination
            $(document).on('click', '.wns-upload-history-pagination .wns-pagination-btn', this.handlePagination.bind(this));
        },

        loadHistory: function (page) {
            const self = this;
            const $container = $('#wns-upload-history-container');
            const $table = $('#wns-upload-history-table');
            const $loading = $('#wns-upload-history-loading');
            const $empty = $('#wns-upload-history-empty');
            const $error = $('#wns-upload-history-error');

            if (!$container.length) {
                return;
            }

            page = page || this.currentPage;

            // Show loading state
            $loading.show();
            $table.hide();
            $empty.hide();
            $error.hide();

            $.ajax({
                url: wooNaldaSync.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'woo_nalda_sync_get_upload_history',
                    nonce: wooNaldaSync.nonce,
                    per_page: this.perPage,
                    page: page
                },
                success: function (response) {
                    $loading.hide();

                    if (response.success) {
                        const uploads = response.data.data || [];
                        // Support both old 'meta' format and new 'pagination' format from API v2.
                        const pagination = response.data.pagination || response.data.meta || {};

                        if (uploads.length === 0) {
                            $empty.show();
                            self.updatePagination(pagination);
                        } else {
                            self.renderTable(uploads);
                            self.updatePagination(pagination);
                            $table.show();
                        }
                        self.currentPage = page;
                    } else {
                        $error.find('.wns-error-message').text(response.data.message || wooNaldaSync.strings.error);
                        $error.show();
                    }
                },
                error: function () {
                    $loading.hide();
                    $error.find('.wns-error-message').text(wooNaldaSync.strings.error);
                    $error.show();
                }
            });
        },

        renderTable: function (uploads) {
            const self = this;
            const $tbody = $('#wns-upload-history-table tbody');
            $tbody.empty();

            uploads.forEach(function (upload) {
                const statusClass = 'wns-badge-' + self.getStatusClass(upload.status);
                const statusLabel = self.getStatusLabel(upload.status);
                const createdAt = self.formatDate(upload.created_at);
                const processedAt = upload.processed_at ? self.formatDate(upload.processed_at) : '—';

                // Build error/action cell content.
                // API v2 returns csv_file_url for downloading the CSV file.
                let actionCell = '';
                if (upload.error_message) {
                    actionCell = self.renderErrorMessage(upload.error_message);
                } else if (upload.csv_file_url) {
                    // Show download link if CSV URL is available.
                    const fileName = upload.csv_file_name || 'products.csv';
                    actionCell = '<a href="' + self.escapeHtml(upload.csv_file_url) + '" ' +
                        'class="wns-btn wns-btn-secondary wns-btn-xs" ' +
                        'target="_blank" rel="noopener noreferrer" ' +
                        'title="' + self.escapeHtml(fileName) + '">' +
                        '<span class="dashicons dashicons-download"></span> ' +
                        (wooNaldaSync.strings.download || 'Download') +
                        '</a>';
                } else if (upload.csv_file_key && upload.status === 'pending') {
                    actionCell = '<span class="wns-text-muted">' +
                        '<span class="dashicons dashicons-clock"></span> ' +
                        (wooNaldaSync.strings.queued || 'Queued') +
                        '</span>';
                } else if (upload.csv_file_key && upload.status === 'processing') {
                    actionCell = '<span class="wns-text-info">' +
                        '<span class="dashicons dashicons-update wns-spin"></span> ' +
                        (wooNaldaSync.strings.processing || 'Processing') +
                        '</span>';
                } else {
                    actionCell = '—';
                }

                const $row = $('<tr>' +
                    '<td data-label="ID">' + self.escapeHtml(upload.id) + '</td>' +
                    '<td data-label="Status"><span class="wns-badge wns-badge-sm ' + statusClass + '">' + statusLabel + '</span></td>' +
                    '<td data-label="Domain" class="wns-hide-mobile">' + self.escapeHtml(upload.domain) + '</td>' +
                    '<td data-label="Created">' + createdAt + '</td>' +
                    '<td data-label="Processed" class="wns-hide-mobile">' + processedAt + '</td>' +
                    '<td data-label="Action" class="wns-action-cell">' + actionCell + '</td>' +
                    '</tr>');

                $tbody.append($row);
            });

            // Bind show more click events
            $tbody.find('.wns-error-show-more').on('click', function (e) {
                e.preventDefault();
                const $container = $(this).closest('.wns-error-container');
                $container.find('.wns-error-truncated').hide();
                $container.find('.wns-error-full').show();
            });

            $tbody.find('.wns-error-show-less').on('click', function (e) {
                e.preventDefault();
                const $container = $(this).closest('.wns-error-container');
                $container.find('.wns-error-full').hide();
                $container.find('.wns-error-truncated').show();
            });
        },

        renderErrorMessage: function (message) {
            const maxLength = 50;
            const escaped = this.escapeHtml(message);

            if (message.length <= maxLength) {
                return '<span class="wns-text-error">' + escaped + '</span>';
            }

            const truncated = this.escapeHtml(message.substring(0, maxLength)) + '...';

            return '<div class="wns-error-container">' +
                '<div class="wns-error-truncated">' +
                '<span class="wns-text-error">' + truncated + '</span> ' +
                '<button type="button" class="wns-error-show-more wns-link-btn">' + (wooNaldaSync.strings.showMore || 'Show more') + '</button>' +
                '</div>' +
                '<div class="wns-error-full" style="display: none;">' +
                '<span class="wns-text-error">' + escaped + '</span> ' +
                '<button type="button" class="wns-error-show-less wns-link-btn">' + (wooNaldaSync.strings.showLess || 'Show less') + '</button>' +
                '</div>' +
                '</div>';
        },

        updatePagination: function (pagination) {
            const $pagination = $('.wns-upload-history-pagination');

            // Support both old API format (total, current_page, last_page) 
            // and new v2 format (total, page, total_pages).
            const total = pagination.total || 0;
            const currentPage = pagination.page || pagination.current_page || 1;
            const lastPage = pagination.total_pages || pagination.last_page || 1;

            if (!total || total <= this.perPage) {
                $pagination.hide();
                return;
            }

            $pagination.find('.wns-pagination-info').text(
                wooNaldaSync.strings.pageInfo
                    .replace('{current}', currentPage)
                    .replace('{total}', lastPage)
            );

            $pagination.find('.wns-pagination-prev')
                .prop('disabled', currentPage <= 1)
                .data('page', currentPage - 1);

            $pagination.find('.wns-pagination-next')
                .prop('disabled', currentPage >= lastPage)
                .data('page', currentPage + 1);

            $pagination.show();
        },

        handleRefresh: function (e) {
            e.preventDefault();
            const $button = $(e.currentTarget);
            const $icon = $button.find('.dashicons');
            const self = this;

            $icon.addClass('wns-spin');

            this.loadHistory(1);

            setTimeout(function () {
                $icon.removeClass('wns-spin');
            }, 1000);
        },

        handlePagination: function (e) {
            e.preventDefault();
            const $button = $(e.currentTarget);
            const page = $button.data('page');

            if (page && !$button.prop('disabled')) {
                this.loadHistory(page);
            }
        },

        getStatusClass: function (status) {
            const classes = {
                'pending': 'warning',
                'processing': 'info',
                'processed': 'success',
                'failed': 'error'
            };
            return classes[status] || 'neutral';
        },

        getStatusLabel: function (status) {
            const labels = {
                'pending': wooNaldaSync.strings.statusPending || 'Pending',
                'processing': wooNaldaSync.strings.statusProcessing || 'Processing',
                'processed': wooNaldaSync.strings.statusProcessed || 'Processed',
                'failed': wooNaldaSync.strings.statusFailed || 'Failed'
            };
            return labels[status] || status;
        },

        formatDate: function (dateString) {
            if (!dateString) return '—';
            const date = new Date(dateString);
            return date.toLocaleDateString() + ' ' + date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        },

        escapeHtml: function (text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    };

    /**
     * Sync Logs Manager
     */
    const SyncLogsManager = {
        init: function () {
            this.bindEvents();
            this.loadLogs();
        },

        bindEvents: function () {
            $(document).on('click', '#wns-refresh-logs', this.loadLogs.bind(this));
            $(document).on('click', '#wns-clear-logs', this.clearLogs.bind(this));
        },

        loadLogs: function () {
            const $container = $('#wns-logs-container');

            if (!$container.length) {
                return;
            }

            $container.html(
                '<div class="wns-logs-loading">' +
                '<span class="spinner is-active" style="float: none;"></span> ' +
                (wooNaldaSync.strings.loadingLogs || 'Loading logs...') +
                '</div>'
            );

            $.ajax({
                url: wooNaldaSync.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'woo_nalda_sync_get_sync_logs',
                    nonce: wooNaldaSync.nonce,
                    limit: 20
                },
                success: function (response) {
                    if (response.success) {
                        SyncLogsManager.renderLogs(response.data.logs);
                    } else {
                        $container.html(
                            '<div class="wns-alert wns-alert-error">' +
                            (response.data.message || 'Failed to load logs.') +
                            '</div>'
                        );
                    }
                },
                error: function () {
                    $container.html(
                        '<div class="wns-alert wns-alert-error">' +
                        'Connection error. Please try again.' +
                        '</div>'
                    );
                }
            });
        },

        clearLogs: function () {
            if (!confirm(wooNaldaSync.strings.confirmClearLogs || 'Are you sure you want to clear all sync logs?')) {
                return;
            }

            const $button = $('#wns-clear-logs');
            setButtonLoading($button, true);

            $.ajax({
                url: wooNaldaSync.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'woo_nalda_sync_clear_sync_logs',
                    nonce: wooNaldaSync.nonce
                },
                success: function (response) {
                    setButtonLoading($button, false);

                    if (response.success) {
                        Toast.success(response.data.message || 'Logs cleared successfully.');
                        SyncLogsManager.loadLogs();
                    } else {
                        Toast.error(response.data.message || 'Failed to clear logs.');
                    }
                },
                error: function () {
                    setButtonLoading($button, false);
                    Toast.error('Connection error. Please try again.');
                }
            });
        },

        renderLogs: function (logs) {
            const $container = $('#wns-logs-container');

            if (!logs || logs.length === 0) {
                $container.html(
                    '<div class="wns-empty-state" style="text-align: center; padding: 40px 20px; color: #666;">' +
                    '<span class="dashicons dashicons-list-view" style="font-size: 48px; width: 48px; height: 48px; margin-bottom: 16px;"></span>' +
                    '<p>' + (wooNaldaSync.strings.noLogs || 'No sync logs yet. Run a sync to see activity here.') + '</p>' +
                    '</div>'
                );
                return;
            }

            let html = '<table class="wns-logs-table widefat striped">' +
                '<thead>' +
                '<tr>' +
                '<th style="width: 160px;">' + (wooNaldaSync.strings.logTime || 'Time') + '</th>' +
                '<th style="width: 130px;">' + (wooNaldaSync.strings.logType || 'Type') + '</th>' +
                '<th style="width: 100px;">' + (wooNaldaSync.strings.logTrigger || 'Trigger') + '</th>' +
                '<th style="width: 80px;">' + (wooNaldaSync.strings.logStatus || 'Status') + '</th>' +
                '<th>' + (wooNaldaSync.strings.logSummary || 'Summary') + '</th>' +
                '</tr>' +
                '</thead>' +
                '<tbody>';

            logs.forEach(function (log) {
                const typeClass = log.type === 'product_export' ? 'wns-badge-info' : 'wns-badge-purple';
                const typeLabel = log.type === 'product_export'
                    ? (wooNaldaSync.strings.productExport || 'Product Export')
                    : (wooNaldaSync.strings.orderImport || 'Order Import');

                const triggerClass = log.trigger === 'manual' ? 'wns-badge-secondary' : 'wns-badge-default';
                const triggerLabel = log.trigger === 'manual'
                    ? (wooNaldaSync.strings.triggerManual || 'Manual')
                    : (wooNaldaSync.strings.triggerAutomatic || 'Automatic');

                let statusClass = 'wns-badge-default';
                if (log.status === 'success') statusClass = 'wns-badge-success';
                else if (log.status === 'error') statusClass = 'wns-badge-error';
                else if (log.status === 'warning') statusClass = 'wns-badge-warning';

                const statusLabel = log.status.charAt(0).toUpperCase() + log.status.slice(1);

                const summary = log.summary ? String(log.summary).replace(/</g, '&lt;').replace(/>/g, '&gt;') : '';

                html += '<tr>' +
                    '<td>' + SyncLogsManager.formatDate(log.timestamp) + '</td>' +
                    '<td><span class="wns-badge ' + typeClass + '">' + typeLabel + '</span></td>' +
                    '<td><span class="wns-badge ' + triggerClass + '">' + triggerLabel + '</span></td>' +
                    '<td><span class="wns-badge ' + statusClass + '">' + statusLabel + '</span></td>' +
                    '<td>' + summary + '</td>' +
                    '</tr>';
            });

            html += '</tbody></table>';
            $container.html(html);
        },

        formatDate: function (timestamp) {
            if (!timestamp) return '—';
            const date = new Date(timestamp * 1000);
            return date.toLocaleDateString() + ' ' + date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
        }
    };

    /**
     * Plugin Update Manager
     */
    const UpdateManager = {
        init: function () {
            this.bindEvents();
        },

        bindEvents: function () {
            $(document).on('click', '#wns-check-update', this.checkUpdate.bind(this));
            $(document).on('click', '#wns-run-update', this.runUpdate.bind(this));
        },

        checkUpdate: function (e) {
            e.preventDefault();

            const $button = $('#wns-check-update');
            setButtonLoading($button, true, wooNaldaSync.strings.checkingUpdate || 'Checking...');

            $.ajax({
                url: wooNaldaSync.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'woo_nalda_sync_check_update',
                    nonce: wooNaldaSync.nonce
                },
                success: function (response) {
                    setButtonLoading($button, false);

                    if (response.success) {
                        UpdateManager.renderUpdateStatus(response.data);

                        if (response.data.update_available) {
                            Toast.info(response.data.message || (wooNaldaSync.strings.updateAvailable || 'Update available!'));
                        } else {
                            Toast.success(response.data.message || (wooNaldaSync.strings.noUpdateAvailable || 'You are running the latest version.'));
                        }
                    } else {
                        Toast.error(response.data.message || 'Failed to check for updates.');
                    }
                },
                error: function () {
                    setButtonLoading($button, false);
                    Toast.error('Connection error. Please try again.');
                }
            });
        },

        runUpdate: function (e) {
            e.preventDefault();

            const $button = $('#wns-run-update');

            if (!confirm('Are you sure you want to update the plugin? Make sure you have a backup.')) {
                return;
            }

            setButtonLoading($button, true, wooNaldaSync.strings.updating || 'Updating...');

            $.ajax({
                url: wooNaldaSync.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'woo_nalda_sync_run_update',
                    nonce: wooNaldaSync.nonce
                },
                success: function (response) {
                    if (response.success) {
                        Toast.success(wooNaldaSync.strings.updateSuccess || 'Plugin updated successfully! Reloading...');

                        // Reload the page after a short delay.
                        setTimeout(function () {
                            window.location.reload();
                        }, 1500);
                    } else {
                        setButtonLoading($button, false);
                        Toast.error(response.data.message || (wooNaldaSync.strings.updateError || 'Update failed.'));
                    }
                },
                error: function () {
                    setButtonLoading($button, false);
                    Toast.error(wooNaldaSync.strings.updateError || 'Update failed. Please try again or update manually.');
                }
            });
        },

        renderUpdateStatus: function (data) {
            const $container = $('#wns-update-status');

            if (!$container.length) {
                return;
            }

            let html = '';

            // Current version row.
            html += '<div class="wns-settings-row" style="border-bottom: none;">' +
                '<div class="wns-settings-row-info">' +
                '<div class="wns-settings-row-label">' + (wooNaldaSync.strings.currentVersion || 'Current Version') + '</div>' +
                '<p class="wns-settings-row-desc">The version of the plugin currently installed.</p>' +
                '</div>' +
                '<div class="wns-settings-row-control">' +
                '<span class="wns-version-badge wns-badge wns-badge-info">v' + data.current_version + '</span>' +
                '</div>' +
                '</div>';

            if (data.update_available) {
                // Update available notice.
                html += '<div class="wns-alert wns-alert-info" style="margin: 0 0 20px 0;">' +
                    '<span class="wns-alert-icon dashicons dashicons-info"></span>' +
                    '<div class="wns-alert-content">' +
                    '<div class="wns-alert-title">Version ' + data.latest_version + ' is available!</div>' +
                    '<p class="wns-alert-message">A new version of WooCommerce Nalda Sync is available. Update now to get the latest features and improvements.</p>' +
                    '</div>' +
                    '</div>';

                // Latest version row.
                let releaseDateText = '';
                if (data.published_at) {
                    const releaseDate = new Date(data.published_at);
                    releaseDateText = 'Released on ' + releaseDate.toLocaleDateString();
                }

                html += '<div class="wns-settings-row" style="border-bottom: none;">' +
                    '<div class="wns-settings-row-info">' +
                    '<div class="wns-settings-row-label">' + (wooNaldaSync.strings.latestVersion || 'Latest Version') + '</div>' +
                    '<p class="wns-settings-row-desc">' + releaseDateText + '</p>' +
                    '</div>' +
                    '<div class="wns-settings-row-control">' +
                    '<span class="wns-version-badge wns-badge wns-badge-success">v' + data.latest_version + '</span>' +
                    '</div>' +
                    '</div>';

                // Release notes.
                if (data.release_notes) {
                    const escapedNotes = $('<div>').text(data.release_notes).html().replace(/\n/g, '<br>');
                    html += '<div class="wns-release-notes" style="margin-top: 15px; padding: 15px; background: #f8f9fa; border-radius: 6px;">' +
                        '<h4 style="margin: 0 0 10px 0; font-size: 13px;">' +
                        '<span class="dashicons dashicons-editor-ul" style="font-size: 16px; vertical-align: middle;"></span> ' +
                        (wooNaldaSync.strings.releaseNotes || 'Release Notes') +
                        '</h4>' +
                        '<div class="wns-release-notes-content" style="font-size: 13px; color: #50575e; max-height: 150px; overflow-y: auto;">' +
                        escapedNotes +
                        '</div>' +
                        '</div>';
                }

                // Update actions.
                html += '<div class="wns-update-actions" style="margin-top: 20px; display: flex; gap: 10px;">' +
                    '<button type="button" class="wns-btn wns-btn-primary" id="wns-run-update">' +
                    '<span class="dashicons dashicons-update"></span> ' +
                    (wooNaldaSync.strings.updateNow || 'Update Now') +
                    '</button>';

                if (data.release_url) {
                    html += '<a href="' + data.release_url + '" class="wns-btn wns-btn-secondary" target="_blank">' +
                        '<span class="dashicons dashicons-external"></span> View on GitHub' +
                        '</a>';
                }

                html += '</div>';
            } else {
                // No update available.
                html += '<div class="wns-settings-row" style="border-bottom: none;">' +
                    '<div class="wns-settings-row-info">' +
                    '<div class="wns-settings-row-label">Status</div>' +
                    '</div>' +
                    '<div class="wns-settings-row-control">' +
                    '<span class="wns-badge wns-badge-success">' +
                    '<span class="dashicons dashicons-yes" style="font-size: 14px; width: 14px; height: 14px; vertical-align: text-bottom;"></span> ' +
                    'Up to date' +
                    '</span>' +
                    '</div>' +
                    '</div>';

                html += '<p class="wns-info-note" style="margin-top: 10px;">' +
                    '<span class="dashicons dashicons-info"></span> ' +
                    (wooNaldaSync.strings.noUpdateAvailable || 'You are running the latest version of WooCommerce Nalda Sync.') +
                    '</p>';
            }

            $container.html(html);
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
        UploadHistoryManager.init();
        SyncLogsManager.init();
        UpdateManager.init();

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
