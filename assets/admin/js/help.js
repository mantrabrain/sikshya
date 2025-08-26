/**
 * Help & Support Page JavaScript
 *
 * @package Sikshya
 * @since 1.0.0
 */

(function($) {
    'use strict';

    const SikshyaHelp = {
        init: function() {
            this.bindEvents();
            this.initTooltips();
        },

        bindEvents: function() {
            $('#start-chat').on('click', this.handleChatClick.bind(this));
            $('.sikshya-link').on('click', this.handleExternalLink.bind(this));
            $('.sikshya-help-item, .sikshya-doc-item, .sikshya-support-item').on('click', this.handleItemClick.bind(this));
        },

        handleChatClick: function(e) {
            e.preventDefault();
            this.showComingSoon('Live chat feature coming soon!');
        },

        handleExternalLink: function(e) {
            e.preventDefault();
            const href = $(this).attr('href');
            
            if (href && href !== '#') {
                // Open external links in new tab
                window.open(href, '_blank');
            } else {
                this.showComingSoon('This feature is coming soon!');
            }
        },

        handleItemClick: function(e) {
            // Only handle clicks on the item itself, not on buttons or links inside
            if ($(e.target).closest('a, button').length === 0) {
                const $item = $(this);
                const type = this.getItemType($item);
                
                // Add a subtle click effect
                $item.addClass('sikshya-item-clicked');
                setTimeout(() => {
                    $item.removeClass('sikshya-item-clicked');
                }, 200);
                
                // Log interaction for analytics (if needed)
                this.logInteraction(type, $item.find('h4').text());
            }
        },

        getItemType: function($item) {
            if ($item.hasClass('sikshya-help-item')) return 'help';
            if ($item.hasClass('sikshya-doc-item')) return 'documentation';
            if ($item.hasClass('sikshya-support-item')) return 'support';
            return 'unknown';
        },

        logInteraction: function(type, title) {
            // This could be used for analytics tracking
            console.log(`User interacted with ${type}: ${title}`);
        },

        showComingSoon: function(message) {
            this.showNotification(message, 'info');
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

        initTooltips: function() {
            // Add tooltips to interactive elements
            $('.sikshya-help-item, .sikshya-doc-item, .sikshya-support-item').each(function() {
                const $item = $(this);
                const title = $item.find('h4').text();
                const description = $item.find('p').text();
                
                $item.attr('title', `${title}\n\n${description}`);
            });
        },

        // Utility function to check if a feature is available
        isFeatureAvailable: function(feature) {
            const availableFeatures = [
                'documentation',
                'email-support',
                'system-status'
            ];
            
            return availableFeatures.includes(feature);
        },

        // Handle system status updates
        updateSystemStatus: function() {
            // This could be used to fetch real-time system status
            const statuses = ['operational', 'degraded', 'outage'];
            const randomStatus = statuses[Math.floor(Math.random() * statuses.length)];
            
            // Update status indicator
            const $indicator = $('.sikshya-status-indicator');
            const $dot = $('.sikshya-status-dot');
            
            $indicator.removeClass('sikshya-status-operational sikshya-status-degraded sikshya-status-outage');
            $indicator.addClass(`sikshya-status-${randomStatus}`);
            
            const statusText = {
                operational: 'All Systems Operational',
                degraded: 'Some Systems Degraded',
                outage: 'System Outage'
            };
            
            $indicator.find('span:last').text(statusText[randomStatus]);
        }
    };

    // Add CSS for notifications and interactions
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
            
            .sikshya-item-clicked {
                transform: scale(0.98);
                transition: transform 0.2s ease;
            }
            
            .sikshya-status-degraded {
                background: #fef3c7;
                color: #d97706;
                border: 1px solid #fbbf24;
            }
            
            .sikshya-status-outage {
                background: #fef2f2;
                color: #dc2626;
                border: 1px solid #fecaca;
            }
            
            .sikshya-status-degraded .sikshya-status-dot {
                background: #f59e0b;
            }
            
            .sikshya-status-outage .sikshya-status-dot {
                background: #ef4444;
            }
        </style>
    `;
    
    // Inject CSS
    $('head').append(additionalCSS);

    // Initialize when document is ready
    $(document).ready(function() {
        SikshyaHelp.init();
    });

    // Make it globally available for debugging
    window.SikshyaHelp = SikshyaHelp;

})(jQuery);
