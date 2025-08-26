/**
 * Reports Page JavaScript
 *
 * @package Sikshya
 * @since 1.0.0
 */

(function($) {
    'use strict';

    const SikshyaReports = {
        charts: {},
        initialized: false,
        initTimeout: null,
        creatingCharts: false,
        initRetryCount: 0,
        maxRetries: 3,
        
        init: function() {
            console.log('SikshyaReports.init() called, retry count:', this.initRetryCount);
            
            // Prevent infinite retries
            if (this.initRetryCount >= this.maxRetries) {
                console.error('Maximum retry count reached, stopping initialization');
                return;
            }
            
            // Clear any existing timeout
            if (this.initTimeout) {
                clearTimeout(this.initTimeout);
            }
            
            // Prevent multiple initializations
            if (this.initialized) {
                console.log('SikshyaReports already initialized, skipping');
                return;
            }
            
            this.initRetryCount++;
            console.log('Initializing SikshyaReports');
            this.bindEvents();
            
            // Use timeout to ensure DOM is ready and prevent rapid re-initialization
            this.initTimeout = setTimeout(() => {
                this.initCharts();
                this.initialized = true;
                console.log('SikshyaReports initialization complete');
            }, 100);
        },

        bindEvents: function() {
            $('#report-period').on('change', this.handlePeriodChange.bind(this));
            $('.sikshya-chart-controls button').on('click', this.handleChartTypeChange.bind(this));
            $('#export-report').on('click', this.handleExportReport.bind(this));
        },

        initCharts: function() {
            console.log('initCharts called');
            
            if (typeof Chart === 'undefined') {
                console.warn('Chart.js is not loaded. Charts will not be displayed.');
                return;
            }

            // Prevent concurrent chart creation
            if (this.creatingCharts) {
                console.log('Charts are already being created, skipping');
                return;
            }

            // Only initialize charts if they haven't been initialized yet
            if (this.charts.initialized) {
                console.log('Charts already initialized, skipping');
                return;
            }

            this.creatingCharts = true;
            console.log('Destroying existing charts and initializing new ones');
            
            // Destroy existing charts before creating new ones
            this.destroyCharts();

            this.initEnrollmentChart();
            this.initAgeChart();
            this.initGeoChart();
            
            // Mark charts as initialized
            this.charts.initialized = true;
            this.creatingCharts = false;
            console.log('Charts initialization complete');
        },

        destroyCharts: function() {
            // Destroy existing charts to prevent canvas reuse errors
            Object.keys(this.charts).forEach(key => {
                if (key !== 'initialized' && this.charts[key] && typeof this.charts[key].destroy === 'function') {
                    this.charts[key].destroy();
                }
            });
            this.charts = { initialized: false };
        },

        initEnrollmentChart: function() {
            const ctx = document.getElementById('enrollment-chart');
            if (!ctx) {
                console.log('Enrollment chart canvas not found');
                return;
            }

            // Check if chart is already initialized and not destroyed
            if (this.charts.enrollment && !this.charts.enrollment._destroyed) {
                console.log('Enrollment chart already exists and not destroyed, skipping');
                return;
            }

            // Check if there's already a Chart.js instance on this canvas
            const existingChart = Chart.getChart(ctx);
            if (existingChart) {
                console.log('Destroying existing Chart.js instance on enrollment-chart canvas');
                existingChart.destroy();
            }

            // Destroy our stored chart instance if it exists
            if (this.charts.enrollment && typeof this.charts.enrollment.destroy === 'function') {
                console.log('Destroying our stored enrollment chart instance');
                this.charts.enrollment.destroy();
            }

            // Set a fixed height on the canvas to prevent infinite growth
            ctx.style.height = '300px';
            ctx.style.maxHeight = '300px';

            console.log('Creating new enrollment chart');
            this.charts.enrollment = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                    datasets: [{
                        label: 'Enrollments',
                        data: [65, 78, 90, 85, 95, 110, 125, 140, 135, 150, 165, 180],
                        borderColor: '#3B82F6',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        tension: 0.4,
                        fill: true,
                        pointBackgroundColor: '#3B82F6',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        pointRadius: 4,
                        pointHoverRadius: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            backgroundColor: 'rgba(0, 0, 0, 0.8)',
                            titleColor: '#fff',
                            bodyColor: '#fff',
                            borderColor: '#3B82F6',
                            borderWidth: 1,
                            cornerRadius: 6,
                            displayColors: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: 'rgba(0, 0, 0, 0.1)',
                                drawBorder: false
                            },
                            ticks: {
                                color: '#6b7280',
                                font: {
                                    size: 12
                                }
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            },
                            ticks: {
                                color: '#6b7280',
                                font: {
                                    size: 12
                                }
                            }
                        }
                    },
                    interaction: {
                        intersect: false,
                        mode: 'index'
                    }
                }
            });
        },

        initAgeChart: function() {
            const ctx = document.getElementById('age-chart');
            if (!ctx) {
                console.log('Age chart canvas not found');
                return;
            }

            // Check if chart is already initialized and not destroyed
            if (this.charts.age && !this.charts.age._destroyed) {
                console.log('Age chart already exists and not destroyed, skipping');
                return;
            }

            // Check if there's already a Chart.js instance on this canvas
            const existingChart = Chart.getChart(ctx);
            if (existingChart) {
                console.log('Destroying existing Chart.js instance on age-chart canvas');
                existingChart.destroy();
            }

            // Destroy our stored chart instance if it exists
            if (this.charts.age && typeof this.charts.age.destroy === 'function') {
                console.log('Destroying our stored age chart instance');
                this.charts.age.destroy();
            }

            // Set a fixed height on the canvas to prevent infinite growth
            ctx.style.height = '300px';
            ctx.style.maxHeight = '300px';

            console.log('Creating new age chart');
            this.charts.age = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['18-24', '25-34', '35-44', '45-54', '55+'],
                    datasets: [{
                        data: [15, 35, 25, 15, 10],
                        backgroundColor: [
                            '#3B82F6',
                            '#10B981',
                            '#F59E0B',
                            '#EF4444',
                            '#8B5CF6'
                        ],
                        borderWidth: 0,
                        hoverBorderWidth: 2,
                        hoverBorderColor: '#fff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                padding: 20,
                                usePointStyle: true,
                                font: {
                                    size: 12
                                },
                                color: '#6b7280'
                            }
                        },
                        tooltip: {
                            backgroundColor: 'rgba(0, 0, 0, 0.8)',
                            titleColor: '#fff',
                            bodyColor: '#fff',
                            borderColor: '#3B82F6',
                            borderWidth: 1,
                            cornerRadius: 6
                        }
                    },
                    cutout: '60%'
                }
            });
        },

        initGeoChart: function() {
            const ctx = document.getElementById('geo-chart');
            if (!ctx) {
                console.log('Geo chart canvas not found');
                return;
            }

            // Check if chart is already initialized and not destroyed
            if (this.charts.geo && !this.charts.geo._destroyed) {
                console.log('Geo chart already exists and not destroyed, skipping');
                return;
            }

            // Check if there's already a Chart.js instance on this canvas
            const existingChart = Chart.getChart(ctx);
            if (existingChart) {
                console.log('Destroying existing Chart.js instance on geo-chart canvas');
                existingChart.destroy();
            }

            // Destroy our stored chart instance if it exists
            if (this.charts.geo && typeof this.charts.geo.destroy === 'function') {
                console.log('Destroying our stored geo chart instance');
                this.charts.geo.destroy();
            }

            // Set a fixed height on the canvas to prevent infinite growth
            ctx.style.height = '300px';
            ctx.style.maxHeight = '300px';

            console.log('Creating new geo chart');
            this.charts.geo = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['United States', 'United Kingdom', 'Canada', 'Australia', 'Others'],
                    datasets: [{
                        data: [45, 20, 15, 10, 10],
                        backgroundColor: [
                            '#3B82F6',
                            '#10B981',
                            '#F59E0B',
                            '#EF4444',
                            '#8B5CF6'
                        ],
                        borderWidth: 0,
                        hoverBorderWidth: 2,
                        hoverBorderColor: '#fff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                padding: 20,
                                usePointStyle: true,
                                font: {
                                    size: 12
                                },
                                color: '#6b7280'
                            }
                        },
                        tooltip: {
                            backgroundColor: 'rgba(0, 0, 0, 0.8)',
                            titleColor: '#fff',
                            bodyColor: '#fff',
                            borderColor: '#3B82F6',
                            borderWidth: 1,
                            cornerRadius: 6
                        }
                    },
                    cutout: '60%'
                }
            });
        },

        handlePeriodChange: function() {
            const period = $('#report-period').val();
            this.updateChartsForPeriod(period);
        },

        handleChartTypeChange: function(e) {
            const $button = $(e.target);
            const chartType = $button.data('chart-type');
            
            // Update active button
            $('.sikshya-chart-controls button').removeClass('active');
            $button.addClass('active');
            
            // Update chart data based on type
            this.updateEnrollmentChartData(chartType);
        },

        updateChartsForPeriod: function(period) {
            // Simulate loading new data based on period
            this.showLoadingState();
            
            setTimeout(() => {
                // Update chart data based on period
                const data = this.getDataForPeriod(period);
                this.updateEnrollmentChartData('enrollments', data);
                this.hideLoadingState();
            }, 1000);
        },

        updateEnrollmentChartData: function(type, data = null) {
            if (!this.charts.enrollment) return;

            let newData;
            let newLabel;

            switch (type) {
                case 'revenue':
                    newData = [1200, 1500, 1800, 1600, 2000, 2200, 2500, 2800, 2600, 3000, 3200, 3500];
                    newLabel = 'Revenue ($)';
                    break;
                case 'completions':
                    newData = [45, 52, 60, 58, 65, 72, 78, 85, 82, 90, 95, 100];
                    newLabel = 'Completions';
                    break;
                default:
                    newData = data || [65, 78, 90, 85, 95, 110, 125, 140, 135, 150, 165, 180];
                    newLabel = 'Enrollments';
            }

            this.charts.enrollment.data.datasets[0].data = newData;
            this.charts.enrollment.data.datasets[0].label = newLabel;
            this.charts.enrollment.update('active');
        },

        getDataForPeriod: function(period) {
            // Simulate different data for different periods
            const dataSets = {
                '7': [15, 18, 22, 20, 25, 28, 30],
                '30': [65, 78, 90, 85, 95, 110, 125, 140, 135, 150, 165, 180, 175, 190, 200, 210, 195, 205, 220, 230, 225, 240, 250, 245, 260, 270, 265, 280, 290, 285],
                '90': [65, 78, 90, 85, 95, 110, 125, 140, 135, 150, 165, 180, 175, 190, 200, 210, 195, 205, 220, 230, 225, 240, 250, 245, 260, 270, 265, 280, 290, 285, 300, 310, 305, 320, 330, 325, 340, 350, 345, 360, 370, 365, 380, 390, 385, 400, 410, 405, 420, 430, 425, 440, 450, 445, 460, 470, 465, 480, 490, 485, 500, 510, 505, 520, 530, 525, 540, 550, 545, 560, 570, 565, 580, 590, 585, 600, 610, 605, 620, 630, 625, 640, 650, 645, 660, 670, 665, 680, 690, 685],
                '365': [65, 78, 90, 85, 95, 110, 125, 140, 135, 150, 165, 180] // Simplified for demo
            };

            return dataSets[period] || dataSets['30'];
        },

        showLoadingState: function() {
            const $container = $('.sikshya-chart-container');
            $container.append(`
                <div class="sikshya-chart-loading">
                    <div class="sikshya-spinner"></div>
                    <span>Loading data...</span>
                </div>
            `);
        },

        hideLoadingState: function() {
            $('.sikshya-chart-loading').remove();
        },

        handleExportReport: function() {
            this.showNotification('Report export feature coming soon!', 'info');
        },

        showNotification: function(message, type = 'info') {
            const icon = this.getNotificationIcon(type);
            const className = `sikshya-notification-${type}`;
            
            const $notification = $(`
                <div class="sikshya-notification ${className}">
                    <div class="sikshya-notification-content">
                        <span class="sikshya-notification-icon">${icon}</span>
                        <span class="sikshya-notification-message">${message}</span>
                    </div>
                    <button class="sikshya-notification-close">&times;</button>
                </div>
            `);

            // Remove existing notifications
            $('.sikshya-notification').remove();
            
            // Add new notification
            $('body').append($notification);
            
            // Show notification with animation
            $notification.addClass('sikshya-notification-show');
            
            // Handle close button
            $notification.find('.sikshya-notification-close').on('click', function() {
                $notification.removeClass('sikshya-notification-show');
                setTimeout(() => {
                    $notification.remove();
                }, 300);
            });
            
            // Auto-remove after 5 seconds
            setTimeout(() => {
                if ($notification.length) {
                    $notification.removeClass('sikshya-notification-show');
                    setTimeout(() => {
                        $notification.remove();
                    }, 300);
                }
            }, 5000);
        },

        getNotificationIcon: function(type) {
            const icons = {
                info: 'ℹ',
                success: '✓',
                warning: '⚠',
                error: '✗'
            };
            return icons[type] || icons.info;
        },

        // Utility function to format numbers
        formatNumber: function(num) {
            return new Intl.NumberFormat().format(num);
        },

        // Utility function to format currency
        formatCurrency: function(amount) {
            return new Intl.NumberFormat('en-US', {
                style: 'currency',
                currency: 'USD'
            }).format(amount);
        }
    };

    // Add CSS for notifications and loading states
    const additionalCSS = `
        <style>
            .sikshya-notification {
                position: fixed;
                top: 20px;
                right: 20px;
                background: #fff;
                border: 1px solid #e5e7eb;
                border-radius: 8px;
                box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
                padding: 1rem;
                max-width: 400px;
                z-index: 9999;
                transform: translateX(100%);
                transition: transform 0.3s ease;
            }
            
            .sikshya-notification-show {
                transform: translateX(0);
            }
            
            .sikshya-notification-content {
                display: flex;
                align-items: center;
                gap: 0.75rem;
            }
            
            .sikshya-notification-icon {
                font-size: 1.25rem;
                font-weight: bold;
            }
            
            .sikshya-notification-message {
                flex: 1;
                font-size: 0.875rem;
                color: #374151;
            }
            
            .sikshya-notification-close {
                position: absolute;
                top: 0.5rem;
                right: 0.5rem;
                background: none;
                border: none;
                font-size: 1.25rem;
                color: #9ca3af;
                cursor: pointer;
                padding: 0;
                width: 20px;
                height: 20px;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            
            .sikshya-notification-close:hover {
                color: #6b7280;
            }
            
            .sikshya-notification-info {
                border-left: 4px solid #3b82f6;
            }
            
            .sikshya-notification-success {
                border-left: 4px solid #10b981;
            }
            
            .sikshya-notification-warning {
                border-left: 4px solid #f59e0b;
            }
            
            .sikshya-notification-error {
                border-left: 4px solid #ef4444;
            }
            
            .sikshya-chart-loading {
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(255, 255, 255, 0.9);
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                z-index: 10;
            }
            
            .sikshya-chart-loading .sikshya-spinner {
                margin-bottom: 0.5rem;
            }
            
            .sikshya-chart-loading span {
                color: #6b7280;
                font-size: 0.875rem;
            }
        </style>
    `;
    
    // Inject CSS
    $('head').append(additionalCSS);

    // Initialize when document is ready
    $(document).ready(function() {
        // Prevent multiple initializations
        if (window.sikshyaReportsInitialized) {
            return;
        }
        
        SikshyaReports.init();
        window.sikshyaReportsInitialized = true;
    });

    // Make it globally available for debugging
    window.SikshyaReports = SikshyaReports;

})(jQuery);
