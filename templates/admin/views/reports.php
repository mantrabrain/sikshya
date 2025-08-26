<?php
/**
 * Reports Page Template
 *
 * @package Sikshya
 * @since 1.0.0
 */

defined('ABSPATH') || exit;

$page_title = $this->data['page_title'] ?? __('Reports', 'sikshya');
$page_description = $this->data['page_description'] ?? __('Analytics and insights for your LMS', 'sikshya');
?>

<div class="sikshya-dashboard">
    <!-- Header -->
    <div class="sikshya-header">
        <div class="sikshya-header-title">
            <h1>
                <i class="fas fa-chart-bar"></i>
                <?php echo esc_html($page_title); ?>
            </h1>
            <span class="sikshya-version">v1.0.0</span>
        </div>
        <div class="sikshya-header-actions">
            <div class="sikshya-date-filter">
                <select id="report-period" class="sikshya-select">
                    <option value="7"><?php esc_html_e('Last 7 days', 'sikshya'); ?></option>
                    <option value="30" selected><?php esc_html_e('Last 30 days', 'sikshya'); ?></option>
                    <option value="90"><?php esc_html_e('Last 90 days', 'sikshya'); ?></option>
                    <option value="365"><?php esc_html_e('Last year', 'sikshya'); ?></option>
                </select>
            </div>
            <button type="button" class="sikshya-btn sikshya-btn-secondary" id="export-report">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                <?php esc_html_e('Export Report', 'sikshya'); ?>
            </button>
        </div>
    </div>

    <div class="sikshya-main-content">
        <!-- Overview Stats Cards -->
        <div class="sikshya-stats-grid">
            <div class="sikshya-stat-card">
                <div class="sikshya-stat-icon sikshya-stat-primary">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                    </svg>
                </div>
                <div class="sikshya-stat-content">
                    <h3 class="sikshya-stat-value">1,247</h3>
                    <p class="sikshya-stat-label"><?php esc_html_e('Total Enrollments', 'sikshya'); ?></p>
                    <span class="sikshya-stat-change sikshya-stat-positive">+12.5%</span>
                </div>
            </div>

            <div class="sikshya-stat-card">
                <div class="sikshya-stat-icon sikshya-stat-success">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                    </svg>
                </div>
                <div class="sikshya-stat-content">
                    <h3 class="sikshya-stat-value">$45,230</h3>
                    <p class="sikshya-stat-label"><?php esc_html_e('Total Revenue', 'sikshya'); ?></p>
                    <span class="sikshya-stat-change sikshya-stat-positive">+8.3%</span>
                </div>
            </div>

            <div class="sikshya-stat-card">
                <div class="sikshya-stat-icon sikshya-stat-warning">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                    </svg>
                </div>
                <div class="sikshya-stat-content">
                    <h3 class="sikshya-stat-value">892</h3>
                    <p class="sikshya-stat-label"><?php esc_html_e('Active Students', 'sikshya'); ?></p>
                    <span class="sikshya-stat-change sikshya-stat-positive">+5.7%</span>
                </div>
            </div>

            <div class="sikshya-stat-card">
                <div class="sikshya-stat-icon sikshya-stat-info">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                    </svg>
                </div>
                <div class="sikshya-stat-content">
                    <h3 class="sikshya-stat-value">78.5%</h3>
                    <p class="sikshya-stat-label"><?php esc_html_e('Completion Rate', 'sikshya'); ?></p>
                    <span class="sikshya-stat-change sikshya-stat-positive">+2.1%</span>
                </div>
            </div>
        </div>

        <!-- Charts Section -->
        <div class="sikshya-content-card">
            <div class="sikshya-content-card-header">
                <div class="sikshya-content-card-header-left">
                    <h3 class="sikshya-content-card-title">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                        </svg>
                        <?php esc_html_e('Enrollment Trends', 'sikshya'); ?>
                    </h3>
                    <p class="sikshya-content-card-subtitle"><?php esc_html_e('Track enrollment growth over time', 'sikshya'); ?></p>
                </div>
                <div class="sikshya-content-card-header-right">
                    <div class="sikshya-chart-controls">
                        <button type="button" class="sikshya-btn sikshya-btn-sm sikshya-btn-secondary active" data-chart-type="enrollments">
                            <?php esc_html_e('Enrollments', 'sikshya'); ?>
                        </button>
                        <button type="button" class="sikshya-btn sikshya-btn-sm sikshya-btn-secondary" data-chart-type="revenue">
                            <?php esc_html_e('Revenue', 'sikshya'); ?>
                        </button>
                        <button type="button" class="sikshya-btn sikshya-btn-sm sikshya-btn-secondary" data-chart-type="completions">
                            <?php esc_html_e('Completions', 'sikshya'); ?>
                        </button>
                    </div>
                </div>
            </div>
            <div class="sikshya-content-card-body">
                <div class="sikshya-chart-container">
                    <canvas id="enrollment-chart" width="800" height="400"></canvas>
                </div>
            </div>
        </div>

        <!-- Top Performing Content -->
        <div class="sikshya-content-card">
            <div class="sikshya-content-card-header">
                <div class="sikshya-content-card-header-left">
                    <h3 class="sikshya-content-card-title">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                        </svg>
                        <?php esc_html_e('Top Performing Courses', 'sikshya'); ?>
                    </h3>
                    <p class="sikshya-content-card-subtitle"><?php esc_html_e('Your best performing courses by enrollment', 'sikshya'); ?></p>
                </div>
            </div>
            <div class="sikshya-content-card-body">
                <div class="sikshya-top-courses">
                    <div class="sikshya-course-item">
                        <div class="sikshya-course-rank">1</div>
                        <div class="sikshya-course-info">
                            <h4><?php esc_html_e('Web Development Fundamentals', 'sikshya'); ?></h4>
                            <p><?php esc_html_e('Learn the basics of web development with HTML, CSS, and JavaScript', 'sikshya'); ?></p>
                        </div>
                        <div class="sikshya-course-stats">
                            <div class="sikshya-course-stat">
                                <span class="sikshya-stat-number">342</span>
                                <span class="sikshya-stat-label"><?php esc_html_e('Enrollments', 'sikshya'); ?></span>
                            </div>
                            <div class="sikshya-course-stat">
                                <span class="sikshya-stat-number">$12,450</span>
                                <span class="sikshya-stat-label"><?php esc_html_e('Revenue', 'sikshya'); ?></span>
                            </div>
                            <div class="sikshya-course-stat">
                                <span class="sikshya-stat-number">89%</span>
                                <span class="sikshya-stat-label"><?php esc_html_e('Completion', 'sikshya'); ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="sikshya-course-item">
                        <div class="sikshya-course-rank">2</div>
                        <div class="sikshya-course-info">
                            <h4><?php esc_html_e('Digital Marketing Mastery', 'sikshya'); ?></h4>
                            <p><?php esc_html_e('Master digital marketing strategies and techniques', 'sikshya'); ?></p>
                        </div>
                        <div class="sikshya-course-stats">
                            <div class="sikshya-course-stat">
                                <span class="sikshya-stat-number">298</span>
                                <span class="sikshya-stat-label"><?php esc_html_e('Enrollments', 'sikshya'); ?></span>
                            </div>
                            <div class="sikshya-course-stat">
                                <span class="sikshya-stat-number">$9,870</span>
                                <span class="sikshya-stat-label"><?php esc_html_e('Revenue', 'sikshya'); ?></span>
                            </div>
                            <div class="sikshya-course-stat">
                                <span class="sikshya-stat-number">76%</span>
                                <span class="sikshya-stat-label"><?php esc_html_e('Completion', 'sikshya'); ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="sikshya-course-item">
                        <div class="sikshya-course-rank">3</div>
                        <div class="sikshya-course-info">
                            <h4><?php esc_html_e('Data Science Essentials', 'sikshya'); ?></h4>
                            <p><?php esc_html_e('Introduction to data science and machine learning', 'sikshya'); ?></p>
                        </div>
                        <div class="sikshya-course-stats">
                            <div class="sikshya-course-stat">
                                <span class="sikshya-stat-number">245</span>
                                <span class="sikshya-stat-label"><?php esc_html_e('Enrollments', 'sikshya'); ?></span>
                            </div>
                            <div class="sikshya-course-stat">
                                <span class="sikshya-stat-number">$8,230</span>
                                <span class="sikshya-stat-label"><?php esc_html_e('Revenue', 'sikshya'); ?></span>
                            </div>
                            <div class="sikshya-course-stat">
                                <span class="sikshya-stat-number">82%</span>
                                <span class="sikshya-stat-label"><?php esc_html_e('Completion', 'sikshya'); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Student Demographics -->
        <div class="sikshya-content-card">
            <div class="sikshya-content-card-header">
                <div class="sikshya-content-card-header-left">
                    <h3 class="sikshya-content-card-title">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                        </svg>
                        <?php esc_html_e('Student Demographics', 'sikshya'); ?>
                    </h3>
                    <p class="sikshya-content-card-subtitle"><?php esc_html_e('Understand your student base', 'sikshya'); ?></p>
                </div>
            </div>
            <div class="sikshya-content-card-body">
                <div class="sikshya-demographics-grid">
                    <div class="sikshya-demographic-chart">
                        <h4><?php esc_html_e('Age Distribution', 'sikshya'); ?></h4>
                        <canvas id="age-chart" width="300" height="300"></canvas>
                    </div>
                    <div class="sikshya-demographic-chart">
                        <h4><?php esc_html_e('Geographic Distribution', 'sikshya'); ?></h4>
                        <canvas id="geo-chart" width="300" height="300"></canvas>
                    </div>
                    <div class="sikshya-demographic-stats">
                        <h4><?php esc_html_e('Key Insights', 'sikshya'); ?></h4>
                        <div class="sikshya-insight-item">
                            <div class="sikshya-insight-icon">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                                </svg>
                            </div>
                            <div class="sikshya-insight-content">
                                <h5><?php esc_html_e('Most Active Age Group', 'sikshya'); ?></h5>
                                <p><?php esc_html_e('Students aged 25-34 are the most engaged', 'sikshya'); ?></p>
                            </div>
                        </div>
                        <div class="sikshya-insight-item">
                            <div class="sikshya-insight-icon">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064"></path>
                                </svg>
                            </div>
                            <div class="sikshya-insight-content">
                                <h5><?php esc_html_e('Top Location', 'sikshya'); ?></h5>
                                <p><?php esc_html_e('United States leads with 45% of enrollments', 'sikshya'); ?></p>
                            </div>
                        </div>
                        <div class="sikshya-insight-item">
                            <div class="sikshya-insight-icon">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            <div class="sikshya-insight-content">
                                <h5><?php esc_html_e('Peak Learning Time', 'sikshya'); ?></h5>
                                <p><?php esc_html_e('Most students study between 7-9 PM', 'sikshya'); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Chart.js for visualizations
    if (typeof Chart !== 'undefined') {
        // Enrollment Chart
        const enrollmentCtx = document.getElementById('enrollment-chart').getContext('2d');
        const enrollmentChart = new Chart(enrollmentCtx, {
            type: 'line',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                datasets: [{
                    label: '<?php esc_html_e('Enrollments', 'sikshya'); ?>',
                    data: [65, 78, 90, 85, 95, 110, 125, 140, 135, 150, 165, 180],
                    borderColor: '#3B82F6',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.1)'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });

        // Age Distribution Chart
        const ageCtx = document.getElementById('age-chart').getContext('2d');
        const ageChart = new Chart(ageCtx, {
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
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        // Geographic Distribution Chart
        const geoCtx = document.getElementById('geo-chart').getContext('2d');
        const geoChart = new Chart(geoCtx, {
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
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    }

    // Period filter
    $('#report-period').on('change', function() {
        const period = $(this).val();
        // TODO: Update charts and stats based on selected period
        console.log('Period changed to:', period);
    });

    // Chart type controls
    $('.sikshya-chart-controls button').on('click', function() {
        $('.sikshya-chart-controls button').removeClass('active');
        $(this).addClass('active');
        
        const chartType = $(this).data('chart-type');
        // TODO: Switch chart data based on type
        console.log('Chart type changed to:', chartType);
    });

    // Export report
    $('#export-report').on('click', function() {
        // TODO: Implement report export functionality
        alert('<?php esc_html_e('Report export feature coming soon!', 'sikshya'); ?>');
    });
});
</script>
