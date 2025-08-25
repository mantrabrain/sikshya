/**
 * Sikshya Admin List Table JavaScript
 * 
 * Provides interactive functionality for the reusable list table component
 */

(function($) {
    'use strict';

    // Sikshya List Table namespace
    window.SikshyaListTable = window.SikshyaListTable || {};

    /**
     * Initialize list table functionality
     */
    SikshyaListTable.init = function() {
        this.bindEvents();
        this.initFilters();
        this.initSearch();
        this.initBulkActions();
        this.initSorting();
        this.initResponsive();
    };

    /**
     * Bind event handlers
     */
    SikshyaListTable.bindEvents = function() {
        // Filter change events
        $(document).on('change', '.sikshya-filter-select, .sikshya-filter-input', function() {
            SikshyaListTable.handleFilterChange();
        });

        // Search input events
        $(document).on('input', '#sikshya-search', function() {
            SikshyaListTable.handleSearch();
        });

        // Bulk action events
        $(document).on('change', '#bulk-action-selector-top, #bulk-action-selector-bottom', function() {
            SikshyaListTable.handleBulkActionChange();
        });

        // Select all checkbox
        $(document).on('change', '#cb-select-all-1, #cb-select-all-2', function() {
            SikshyaListTable.handleSelectAll($(this));
        });

        // Row checkbox events
        $(document).on('change', '.check-column input[type="checkbox"]', function() {
            SikshyaListTable.handleRowSelection();
        });

        // Row action events
        $(document).on('click', '.row-actions a', function(e) {
            SikshyaListTable.handleRowAction(e, $(this));
        });

        // Table row hover events
        $(document).on('mouseenter', '.wp-list-table tbody tr', function() {
            $(this).find('.row-actions').addClass('visible');
        }).on('mouseleave', '.wp-list-table tbody tr', function() {
            $(this).find('.row-actions').removeClass('visible');
        });

        // Keyboard navigation
        $(document).on('keydown', '.wp-list-table tbody tr', function(e) {
            SikshyaListTable.handleKeyboardNavigation(e, $(this));
        });
    };

    /**
     * Initialize filters
     */
    SikshyaListTable.initFilters = function() {
        // Auto-submit filters after a delay
        let filterTimeout;
        
        $(document).on('change', '.sikshya-filter-select, .sikshya-filter-input', function() {
            clearTimeout(filterTimeout);
            filterTimeout = setTimeout(function() {
                SikshyaListTable.submitFilters();
            }, 500);
        });
    };

    /**
     * Initialize search functionality
     */
    SikshyaListTable.initSearch = function() {
        let searchTimeout;
        
        $(document).on('input', '#sikshya-search', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(function() {
                SikshyaListTable.submitSearch();
            }, 300);
        });
    };

    /**
     * Initialize bulk actions
     */
    SikshyaListTable.initBulkActions = function() {
        // Enable/disable bulk action buttons based on selection
        $(document).on('change', '.check-column input[type="checkbox"]', function() {
            SikshyaListTable.updateBulkActionButtons();
        });
    };

    /**
     * Initialize sorting
     */
    SikshyaListTable.initSorting = function() {
        // Add sort indicators to sortable columns
        $('.wp-list-table th.sortable').each(function() {
            const $th = $(this);
            const currentOrder = $th.data('order') || 'asc';
            const sortIcon = currentOrder === 'asc' ? '↑' : '↓';
            
            if ($th.hasClass('sorted')) {
                $th.find('.sorting-indicator').text(sortIcon);
            }
        });
    };

    /**
     * Initialize responsive behavior
     */
    SikshyaListTable.initResponsive = function() {
        // Handle mobile view
        if (window.innerWidth <= 782) {
            SikshyaListTable.enableMobileView();
        }

        // Handle window resize
        $(window).on('resize', function() {
            if (window.innerWidth <= 782) {
                SikshyaListTable.enableMobileView();
            } else {
                SikshyaListTable.disableMobileView();
            }
        });
    };

    /**
     * Handle filter changes
     */
    SikshyaListTable.handleFilterChange = function() {
        const filters = {};
        
        $('.sikshya-filter-select, .sikshya-filter-input').each(function() {
            const $input = $(this);
            const name = $input.attr('name');
            const value = $input.val();
            
            if (value && value !== '') {
                filters[name] = value;
            }
        });

        SikshyaListTable.updateURL(filters);
    };

    /**
     * Handle search
     */
    SikshyaListTable.handleSearch = function() {
        const searchTerm = $('#sikshya-search').val();
        
        if (searchTerm.length >= 2 || searchTerm.length === 0) {
            SikshyaListTable.updateURL({ s: searchTerm });
        }
    };

    /**
     * Handle bulk action changes
     */
    SikshyaListTable.handleBulkActionChange = function() {
        const action = $('#bulk-action-selector-top').val() || $('#bulk-action-selector-bottom').val();
        
        if (action && action !== '-1') {
            $('.bulkactions .button').prop('disabled', false);
        } else {
            $('.bulkactions .button').prop('disabled', true);
        }
    };

    /**
     * Handle select all checkbox
     */
    SikshyaListTable.handleSelectAll = function($checkbox) {
        const isChecked = $checkbox.is(':checked');
        const tableId = $checkbox.closest('.wp-list-table').attr('id');
        
        $(`#${tableId} tbody .check-column input[type="checkbox"]`).prop('checked', isChecked);
        SikshyaListTable.updateBulkActionButtons();
    };

    /**
     * Handle row selection
     */
    SikshyaListTable.handleRowSelection = function() {
        SikshyaListTable.updateBulkActionButtons();
        SikshyaListTable.updateSelectAllCheckbox();
    };

    /**
     * Handle row actions
     */
    SikshyaListTable.handleRowAction = function(e, $link) {
        const action = $link.data('action');
        const itemId = $link.closest('tr').data('id');
        
        if (action === 'delete') {
            e.preventDefault();
            SikshyaListTable.confirmDelete(itemId);
        }
    };

    /**
     * Handle keyboard navigation
     */
    SikshyaListTable.handleKeyboardNavigation = function(e, $row) {
        switch (e.keyCode) {
            case 13: // Enter
                e.preventDefault();
                $row.find('.row-title').first().click();
                break;
            case 32: // Space
                e.preventDefault();
                $row.find('.check-column input[type="checkbox"]').click();
                break;
        }
    };

    /**
     * Submit filters
     */
    SikshyaListTable.submitFilters = function() {
        const formData = new FormData();
        
        $('.sikshya-filter-select, .sikshya-filter-input').each(function() {
            const $input = $(this);
            const name = $input.attr('name');
            const value = $input.val();
            
            if (value && value !== '') {
                formData.append(name, value);
            }
        });

        // Add current page parameters
        const urlParams = new URLSearchParams(window.location.search);
        for (let [key, value] of urlParams) {
            if (!['status', 'instructor', 'price_type'].includes(key)) {
                formData.append(key, value);
            }
        }

        SikshyaListTable.reloadTable(formData);
    };

    /**
     * Submit search
     */
    SikshyaListTable.submitSearch = function() {
        const searchTerm = $('#sikshya-search').val();
        const formData = new FormData();
        
        if (searchTerm) {
            formData.append('s', searchTerm);
        }

        // Reset to first page for search
        formData.append('paged', '1');

        SikshyaListTable.reloadTable(formData);
    };

    /**
     * Update URL with parameters
     */
    SikshyaListTable.updateURL = function(params) {
        const url = new URL(window.location);
        
        // Remove empty parameters
        Object.keys(params).forEach(key => {
            if (params[key] && params[key] !== '') {
                url.searchParams.set(key, params[key]);
            } else {
                url.searchParams.delete(key);
            }
        });

        // Reset to first page when filtering
        url.searchParams.set('paged', '1');

        window.location.href = url.toString();
    };

    /**
     * Reload table with new data
     */
    SikshyaListTable.reloadTable = function(formData) {
        const $table = $('.wp-list-table').closest('.wrap');
        
        $table.addClass('loading');
        
        $.ajax({
            url: window.location.href,
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                // Extract table content from response
                const $newTable = $(response).find('.wp-list-table').closest('.wrap');
                $table.html($newTable.html());
                
                // Reinitialize
                SikshyaListTable.init();
                
                // Update URL without reloading
                const url = new URL(window.location);
                for (let [key, value] of formData.entries()) {
                    url.searchParams.set(key, value);
                }
                window.history.pushState({}, '', url.toString());
            },
            error: function() {
                // Fallback to page reload
                window.location.reload();
            },
            complete: function() {
                $table.removeClass('loading');
            }
        });
    };

    /**
     * Update bulk action buttons
     */
    SikshyaListTable.updateBulkActionButtons = function() {
        const selectedCount = $('.check-column input[type="checkbox"]:checked').length;
        const action = $('#bulk-action-selector-top').val() || $('#bulk-action-selector-bottom').val();
        
        if (selectedCount > 0 && action && action !== '-1') {
            $('.bulkactions .button').prop('disabled', false);
        } else {
            $('.bulkactions .button').prop('disabled', true);
        }
    };

    /**
     * Update select all checkbox
     */
    SikshyaListTable.updateSelectAllCheckbox = function() {
        const totalCheckboxes = $('.check-column input[type="checkbox"]').length;
        const checkedCheckboxes = $('.check-column input[type="checkbox"]:checked').length;
        
        if (checkedCheckboxes === 0) {
            $('#cb-select-all-1, #cb-select-all-2').prop('indeterminate', false).prop('checked', false);
        } else if (checkedCheckboxes === totalCheckboxes) {
            $('#cb-select-all-1, #cb-select-all-2').prop('indeterminate', false).prop('checked', true);
        } else {
            $('#cb-select-all-1, #cb-select-all-2').prop('indeterminate', true);
        }
    };

    /**
     * Confirm delete action
     */
    SikshyaListTable.confirmDelete = function(itemId) {
        if (confirm(sikshya_list_table.confirm_delete_message)) {
            SikshyaListTable.deleteItem(itemId);
        }
    };

    /**
     * Delete item
     */
    SikshyaListTable.deleteItem = function(itemId) {
        const $row = $(`tr[data-id="${itemId}"]`);
        
        $row.addClass('deleting');
        
        $.ajax({
            url: sikshya_list_table.ajax_url,
            method: 'POST',
            data: {
                action: 'sikshya_delete_course',
                course_id: itemId,
                nonce: sikshya_list_table.nonce
            },
            success: function(response) {
                if (response.success) {
                    $row.fadeOut(300, function() {
                        $(this).remove();
                        SikshyaListTable.showNotice(response.data.message, 'success');
                    });
                } else {
                    SikshyaListTable.showNotice(response.data.message, 'error');
                }
            },
            error: function() {
                SikshyaListTable.showNotice(sikshya_list_table.error_message, 'error');
            },
            complete: function() {
                $row.removeClass('deleting');
            }
        });
    };

    /**
     * Show admin notice
     */
    SikshyaListTable.showNotice = function(message, type) {
        const noticeClass = `notice notice-${type}`;
        const $notice = $(`<div class="${noticeClass}"><p>${message}</p></div>`);
        
        $('.wrap h1').after($notice);
        
        // Auto-remove after 5 seconds
        setTimeout(function() {
            $notice.fadeOut(300, function() {
                $(this).remove();
            });
        }, 5000);
    };

    /**
     * Enable mobile view
     */
    SikshyaListTable.enableMobileView = function() {
        $('.wp-list-table').addClass('mobile-view');
        
        // Stack filters vertically
        $('.sikshya-filters').addClass('mobile-filters');
    };

    /**
     * Disable mobile view
     */
    SikshyaListTable.disableMobileView = function() {
        $('.wp-list-table').removeClass('mobile-view');
        $('.sikshya-filters').removeClass('mobile-filters');
    };

    /**
     * Export table data
     */
    SikshyaListTable.exportData = function(format) {
        const $table = $('.wp-list-table');
        const data = [];
        
        // Get headers
        const headers = [];
        $table.find('thead th').each(function() {
            headers.push($(this).text().trim());
        });
        data.push(headers);
        
        // Get rows
        $table.find('tbody tr').each(function() {
            const row = [];
            $(this).find('td').each(function() {
                row.push($(this).text().trim());
            });
            data.push(row);
        });
        
        if (format === 'csv') {
            SikshyaListTable.downloadCSV(data);
        } else if (format === 'excel') {
            SikshyaListTable.downloadExcel(data);
        }
    };

    /**
     * Download CSV
     */
    SikshyaListTable.downloadCSV = function(data) {
        let csvContent = '';
        
        data.forEach(row => {
            const csvRow = row.map(cell => `"${cell.replace(/"/g, '""')}"`).join(',');
            csvContent += csvRow + '\n';
        });
        
        const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = 'courses-export.csv';
        link.click();
    };

    /**
     * Download Excel
     */
    SikshyaListTable.downloadExcel = function(data) {
        // This would require a library like SheetJS
        // For now, fallback to CSV
        SikshyaListTable.downloadCSV(data);
    };

    // Initialize when document is ready
    $(document).ready(function() {
        SikshyaListTable.init();
    });

})(jQuery);
