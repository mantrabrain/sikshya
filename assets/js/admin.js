/**
 * Sikshya LMS Admin JavaScript
 *
 * @package Sikshya
 */

(function($) {
    'use strict';

    // Global Sikshya object
    window.sikshya = window.sikshya || {};

    // Utility functions
    sikshya.utils = {
        /**
         * Show notification
         */
        showNotification: function(message, type = 'info') {
            const notification = $(`
                <div class="sikshya-notification sikshya-notification-${type}">
                    <span class="sikshya-notification-message">${message}</span>
                    <button type="button" class="sikshya-notification-close">&times;</button>
                </div>
            `);

            // Add to page
            $('body').append(notification);

            // Auto remove after 5 seconds
            setTimeout(function() {
                notification.fadeOut(function() {
                    $(this).remove();
                });
            }, 5000);

            // Close button
            notification.find('.sikshya-notification-close').on('click', function() {
                notification.fadeOut(function() {
                    $(this).remove();
                });
            });
        },

        /**
         * Confirm action
         */
        confirm: function(message, callback) {
            if (confirm(message)) {
                callback();
            }
        },

        /**
         * Format currency
         */
        formatCurrency: function(amount, currency = 'USD') {
            return new Intl.NumberFormat('en-US', {
                style: 'currency',
                currency: currency
            }).format(amount);
        },

        /**
         * Format date
         */
        formatDate: function(date) {
            return new Intl.DateTimeFormat('en-US', {
                year: 'numeric',
                month: 'short',
                day: 'numeric'
            }).format(new Date(date));
        }
    };

    // DataTable functionality
    sikshya.dataTable = {
        /**
         * Initialize DataTable
         */
        init: function(tableId, config) {
            const table = $(`#${tableId}`);
            if (!table.length) return;

            // Search functionality
            if (config.search) {
                this.initSearch(table);
            }

            // Sorting functionality
            if (config.sortable) {
                this.initSorting(table);
            }

            // Bulk actions
            if (config.selectable) {
                this.initBulkActions(table);
            }

            // Pagination
            if (config.pagination) {
                this.initPagination(table);
            }
        },

        /**
         * Initialize search
         */
        initSearch: function(table) {
            const searchInput = $('#sikshya-search-input');
            if (!searchInput.length) return;

            searchInput.on('input', function() {
                const searchTerm = $(this).val().toLowerCase();
                table.find('tbody tr').each(function() {
                    const text = $(this).text().toLowerCase();
                    $(this).toggle(text.includes(searchTerm));
                });
            });
        },

        /**
         * Initialize sorting
         */
        initSorting: function(table) {
            table.find('th[data-sortable]').on('click', function() {
                const column = $(this).data('column');
                const currentOrder = $(this).data('order') || 'asc';
                const newOrder = currentOrder === 'asc' ? 'desc' : 'asc';

                // Update sort indicators
                table.find('th').removeClass('sikshya-sort-asc sikshya-sort-desc');
                $(this).addClass(`sikshya-sort-${newOrder}`).data('order', newOrder);

                // Sort table
                sikshya.dataTable.sortTable(table, column, newOrder);
            });
        },

        /**
         * Sort table
         */
        sortTable: function(table, column, order) {
            const tbody = table.find('tbody');
            const rows = tbody.find('tr').get();

            rows.sort(function(a, b) {
                const aVal = $(a).find(`td[data-column="${column}"]`).text();
                const bVal = $(b).find(`td[data-column="${column}"]`).text();

                if (order === 'asc') {
                    return aVal.localeCompare(bVal);
                } else {
                    return bVal.localeCompare(aVal);
                }
            });

            tbody.empty().append(rows);
        },

        /**
         * Initialize bulk actions
         */
        initBulkActions: function(table) {
            // Select all checkbox
            $('#sikshya-select-all').on('change', function() {
                $('.sikshya-row-select').prop('checked', $(this).is(':checked'));
            });

            // Bulk action apply
            $('#sikshya-bulk-apply').on('click', function() {
                const action = $('#sikshya-bulk-action').val();
                const selectedIds = $('.sikshya-row-select:checked').map(function() {
                    return $(this).val();
                }).get();

                if (!action || selectedIds.length === 0) {
                    sikshya.utils.showNotification('Please select an action and items', 'warning');
                    return;
                }

                sikshya.utils.confirm('Are you sure you want to perform this action?', function() {
                    $.post(sikshya.ajax_url, {
                        action: action,
                        ids: selectedIds,
                        nonce: sikshya.nonce
                    }, function(response) {
                        if (response.success) {
                            sikshya.utils.showNotification(response.data.message, 'success');
                            location.reload();
                        } else {
                            sikshya.utils.showNotification(response.data.message, 'error');
                        }
                    });
                });
            });
        },

        /**
         * Initialize pagination
         */
        initPagination: function(table) {
            // Previous page
            $('#sikshya-prev-page').on('click', function() {
                const currentPage = parseInt($('#sikshya-current-page').text());
                if (currentPage > 1) {
                    sikshya.dataTable.loadPage(currentPage - 1);
                }
            });

            // Next page
            $('#sikshya-next-page').on('click', function() {
                const currentPage = parseInt($('#sikshya-current-page').text());
                const totalPages = parseInt($('#sikshya-total-pages').text());
                if (currentPage < totalPages) {
                    sikshya.dataTable.loadPage(currentPage + 1);
                }
            });
        },

        /**
         * Load page
         */
        loadPage: function(page) {
            const tableId = $('.sikshya-table').attr('id');
            const searchTerm = $('#sikshya-search-input').val();
            const filters = {};

            // Get filter values
            $('#sikshya-filters-form select, #sikshya-filters-form input').each(function() {
                const name = $(this).attr('name');
                const value = $(this).val();
                if (name && value) {
                    filters[name] = value;
                }
            });

            $.post(sikshya.ajax_url, {
                action: 'sikshya_load_table_data',
                table_id: tableId,
                page: page,
                search: searchTerm,
                filters: filters,
                nonce: sikshya.nonce
            }, function(response) {
                if (response.success) {
                    // Update table content
                    $('.sikshya-table tbody').html(response.data.html);
                    
                    // Update pagination
                    $('#sikshya-current-page').text(response.data.current_page);
                    $('#sikshya-total-pages').text(response.data.total_pages);
                    $('#sikshya-pagination-from').text(response.data.from);
                    $('#sikshya-pagination-to').text(response.data.to);
                    $('#sikshya-pagination-total').text(response.data.total);
                    
                    // Update button states
                    $('#sikshya-prev-page').prop('disabled', response.data.current_page <= 1);
                    $('#sikshya-next-page').prop('disabled', response.data.current_page >= response.data.total_pages);
                }
            });
        }
    };

    // Form functionality
    sikshya.form = {
        /**
         * Initialize form
         */
        init: function(formId) {
            const form = $(`#${formId}`);
            if (!form.length) return;

            // Form submission
            form.on('submit', function(e) {
                e.preventDefault();
                sikshya.form.submit(form);
            });

            // File upload preview
            form.find('input[type="file"]').on('change', function() {
                sikshya.form.handleFileUpload($(this));
            });

            // Tags input
            form.find('.sikshya-tags-input input').on('keydown', function(e) {
                if (e.key === 'Enter' || e.key === ',') {
                    e.preventDefault();
                    sikshya.form.addTag($(this));
                }
            });
        },

        /**
         * Submit form
         */
        submit: function(form) {
            const submitBtn = form.find('button[type="submit"]');
            const originalText = submitBtn.text();
            
            // Disable submit button
            submitBtn.prop('disabled', true).text('Saving...');
            
            // Clear previous errors
            $('.sikshya-form-error').empty();
            
            // Submit form via AJAX
            $.post(form.attr('action'), form.serialize(), function(response) {
                if (response.success) {
                    sikshya.utils.showNotification(response.data.message, 'success');
                    
                    if (response.data.redirect) {
                        window.location.href = response.data.redirect;
                    } else {
                        location.reload();
                    }
                } else {
                    sikshya.utils.showNotification(response.data.message, 'error');
                    
                    if (response.data.errors) {
                        Object.keys(response.data.errors).forEach(function(field) {
                            $(`#${field}-error`).text(response.data.errors[field]);
                        });
                    }
                }
            }).fail(function() {
                sikshya.utils.showNotification('Network error occurred!', 'error');
            }).always(function() {
                submitBtn.prop('disabled', false).text(originalText);
            });
        },

        /**
         * Handle file upload
         */
        handleFileUpload: function(input) {
            const file = input[0].files[0];
            const preview = input.siblings('.sikshya-file-preview');
            
            if (file && preview.length) {
                if (file.type.startsWith('image/')) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        preview.html(`<img src="${e.target.result}" alt="Preview" style="max-width: 200px; max-height: 200px;">`);
                    };
                    reader.readAsDataURL(file);
                } else {
                    preview.html(`<p>${file.name}</p>`);
                }
            }
        },

        /**
         * Add tag
         */
        addTag: function(input) {
            const value = input.val().trim();
            if (!value) return;

            const container = input.closest('.sikshya-tags-input');
            const tag = $(`
                <span class="sikshya-tag">
                    ${value}
                    <span class="sikshya-tag-remove">&times;</span>
                </span>
            `);

            container.prepend(tag);
            input.val('');

            // Remove tag
            tag.find('.sikshya-tag-remove').on('click', function() {
                tag.remove();
            });
        }
    };

    // Dashboard functionality
    sikshya.dashboard = {
        /**
         * Initialize dashboard
         */
        init: function() {
            // Refresh dashboard data
            this.refreshData();
            
            // Auto refresh
            setInterval(() => {
                this.refreshData();
            }, 300000); // 5 minutes
        },

        /**
         * Refresh dashboard data
         */
        refreshData: function() {
            $.post(sikshya.ajax_url, {
                action: 'sikshya_refresh_dashboard',
                nonce: sikshya.nonce
            }, function(response) {
                if (response.success) {
                    // Update stats
                    Object.keys(response.data.stats).forEach(function(key) {
                        $(`.sikshya-stat-${key}`).text(response.data.stats[key]);
                    });
                }
            });
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        // Initialize DataTables
        $('.sikshya-table').each(function() {
            const tableId = $(this).attr('id');
            if (tableId) {
                sikshya.dataTable.init(tableId, {
                    search: true,
                    sortable: true,
                    selectable: true,
                    pagination: true
                });
            }
        });

        // Initialize forms
        $('.sikshya-form-wrapper').each(function() {
            const formId = $(this).find('form').attr('id');
            if (formId) {
                sikshya.form.init(formId);
            }
        });

        // Initialize dashboard
        if ($('.sikshya-widget').length) {
            sikshya.dashboard.init();
        }
    });

})(jQuery); 