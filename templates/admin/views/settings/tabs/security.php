<?php
/**
 * Security Settings Tab Template
 * 
 * @package Sikshya
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="sikshya-settings-tab-content">
    <!-- Security Overview Card -->
    <div class="sikshya-security-overview">
        <div class="sikshya-security-stats">
            <div class="sikshya-stat-card">
                <div class="stat-icon">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <div class="stat-content">
                    <h4><?php _e('Security Level', 'sikshya'); ?></h4>
                    <p><?php _e('High Protection', 'sikshya'); ?></p>
                </div>
            </div>
            <div class="sikshya-stat-card">
                <div class="stat-icon">
                    <i class="fas fa-eye"></i>
                </div>
                <div class="stat-content">
                    <h4><?php _e('Privacy', 'sikshya'); ?></h4>
                    <p><?php _e('GDPR Compliant', 'sikshya'); ?></p>
                </div>
            </div>
            <div class="sikshya-stat-card">
                <div class="stat-icon">
                    <i class="fas fa-lock"></i>
                </div>
                <div class="stat-content">
                    <h4><?php _e('Encryption', 'sikshya'); ?></h4>
                    <p><?php _e('SSL Enabled', 'sikshya'); ?></p>
                </div>
            </div>
        </div>
    </div>

    <div class="sikshya-settings-section">
        <h3 class="sikshya-settings-section-title">
            <i class="fas fa-user-shield"></i>
            <?php _e('Authentication & Access Control', 'sikshya'); ?>
        </h3>
        
        <div class="sikshya-settings-grid">
            <div class="sikshya-settings-field">
                <label for="session_timeout"><?php _e('Session Timeout (minutes)', 'sikshya'); ?></label>
                <input type="number" id="session_timeout" name="session_timeout" 
                       value="<?php echo esc_attr(get_option('sikshya_session_timeout', 120)); ?>" 
                       min="15" max="1440">
                <p class="description"><?php _e('How long before users are automatically logged out (15-1440 minutes)', 'sikshya'); ?></p>
            </div>
            
            <div class="sikshya-settings-field">
                <label class="sikshya-checkbox-label">
                    <input type="checkbox" name="force_ssl" value="1" 
                           <?php checked(get_option('sikshya_force_ssl', false)); ?>>
                    <span class="checkmark"></span>
                    <?php _e('Force SSL/HTTPS', 'sikshya'); ?>
                </label>
                <p class="description"><?php _e('Redirect all traffic to HTTPS for enhanced security', 'sikshya'); ?></p>
            </div>
            
            <div class="sikshya-settings-field">
                <label for="max_login_attempts"><?php _e('Max Login Attempts', 'sikshya'); ?></label>
                <input type="number" id="max_login_attempts" name="max_login_attempts" 
                       value="<?php echo esc_attr(get_option('sikshya_max_login_attempts', 5)); ?>" 
                       min="3" max="20">
                <p class="description"><?php _e('Maximum failed login attempts before temporary lockout', 'sikshya'); ?></p>
            </div>
            
            <div class="sikshya-settings-field">
                <label for="lockout_duration"><?php _e('Lockout Duration (minutes)', 'sikshya'); ?></label>
                <input type="number" id="lockout_duration" name="lockout_duration" 
                       value="<?php echo esc_attr(get_option('sikshya_lockout_duration', 30)); ?>" 
                       min="5" max="1440">
                <p class="description"><?php _e('How long to lock out accounts after max login attempts', 'sikshya'); ?></p>
            </div>
        </div>
    </div>

    <div class="sikshya-settings-section">
        <h3 class="sikshya-settings-section-title">
            <i class="fas fa-shield-alt"></i>
            <?php _e('Content Security', 'sikshya'); ?>
        </h3>
        
        <div class="sikshya-settings-grid">
            <div class="sikshya-settings-field">
                <label class="sikshya-checkbox-label">
                    <input type="checkbox" name="prevent_content_copy" value="1" 
                           <?php checked(get_option('sikshya_prevent_content_copy', false)); ?>>
                    <span class="checkmark"></span>
                    <?php _e('Prevent Content Copying', 'sikshya'); ?>
                </label>
                <p class="description"><?php _e('Disable right-click and copy/paste on course content', 'sikshya'); ?></p>
            </div>
            
            <div class="sikshya-settings-field">
                <label class="sikshya-checkbox-label">
                    <input type="checkbox" name="watermark_content" value="1" 
                           <?php checked(get_option('sikshya_watermark_content', false)); ?>>
                    <span class="checkmark"></span>
                    <?php _e('Add Watermarks to Content', 'sikshya'); ?>
                </label>
                <p class="description"><?php _e('Add user-specific watermarks to downloadable content', 'sikshya'); ?></p>
            </div>
            
            <div class="sikshya-settings-field">
                <label for="content_expiry_days"><?php _e('Content Expiry (days)', 'sikshya'); ?></label>
                <input type="number" id="content_expiry_days" name="content_expiry_days" 
                       value="<?php echo esc_attr(get_option('sikshya_content_expiry_days', 0)); ?>" 
                       min="0" max="365">
                <p class="description"><?php _e('Days after which downloaded content expires (0 = no expiry)', 'sikshya'); ?></p>
            </div>
            
            <div class="sikshya-settings-field">
                <label class="sikshya-checkbox-label">
                    <input type="checkbox" name="disable_print" value="1" 
                           <?php checked(get_option('sikshya_disable_print', false)); ?>>
                    <span class="checkmark"></span>
                    <?php _e('Disable Print Functionality', 'sikshya'); ?>
                </label>
                <p class="description"><?php _e('Prevent users from printing course content', 'sikshya'); ?></p>
            </div>
        </div>
    </div>

    <div class="sikshya-settings-section">
        <h3 class="sikshya-settings-section-title">
            <i class="fas fa-eye"></i>
            <?php _e('Privacy & Data Protection', 'sikshya'); ?>
        </h3>
        
        <div class="sikshya-settings-grid">
            <div class="sikshya-settings-field">
                <label class="sikshya-checkbox-label">
                    <input type="checkbox" name="anonymize_data" value="1" 
                           <?php checked(get_option('sikshya_anonymize_data', false)); ?>>
                    <span class="checkmark"></span>
                    <?php _e('Anonymize User Data', 'sikshya'); ?>
                </label>
                <p class="description"><?php _e('Anonymize user data in analytics and reports', 'sikshya'); ?></p>
            </div>
            
            <div class="sikshya-settings-field">
                <label for="data_retention_days"><?php _e('Data Retention (days)', 'sikshya'); ?></label>
                <input type="number" id="data_retention_days" name="data_retention_days" 
                       value="<?php echo esc_attr(get_option('sikshya_data_retention_days', 2555)); ?>" 
                       min="30" max="10950">
                <p class="description"><?php _e('How long to keep user data (30-10950 days, default 7 years)', 'sikshya'); ?></p>
            </div>
            
            <div class="sikshya-settings-field">
                <label class="sikshya-checkbox-label">
                    <input type="checkbox" name="gdpr_compliance" value="1" 
                           <?php checked(get_option('sikshya_gdpr_compliance', true)); ?>>
                    <span class="checkmark"></span>
                    <?php _e('GDPR Compliance', 'sikshya'); ?>
                </label>
                <p class="description"><?php _e('Enable GDPR compliance features (data export, deletion)', 'sikshya'); ?></p>
            </div>
            
            <div class="sikshya-settings-field">
                <label class="sikshya-checkbox-label">
                    <input type="checkbox" name="cookie_consent" value="1" 
                           <?php checked(get_option('sikshya_cookie_consent', true)); ?>>
                    <span class="checkmark"></span>
                    <?php _e('Cookie Consent Banner', 'sikshya'); ?>
                </label>
                <p class="description"><?php _e('Show cookie consent banner to comply with privacy laws', 'sikshya'); ?></p>
            </div>
        </div>
    </div>

    <div class="sikshya-settings-section">
        <h3 class="sikshya-settings-section-title">
            <i class="fas fa-lock"></i>
            <?php _e('File Upload Security', 'sikshya'); ?>
        </h3>
        
        <div class="sikshya-settings-grid">
            <div class="sikshya-settings-field">
                <label for="max_file_size"><?php _e('Max File Size (MB)', 'sikshya'); ?></label>
                <input type="number" id="max_file_size" name="max_file_size" 
                       value="<?php echo esc_attr(get_option('sikshya_max_file_size', 10)); ?>" 
                       min="1" max="100">
                <p class="description"><?php _e('Maximum file size for uploads (1-100 MB)', 'sikshya'); ?></p>
            </div>
            
            <div class="sikshya-settings-field">
                <label for="allowed_file_types"><?php _e('Allowed File Types', 'sikshya'); ?></label>
                <input type="text" id="allowed_file_types" name="allowed_file_types" 
                       value="<?php echo esc_attr(get_option('sikshya_allowed_file_types', 'pdf,doc,docx,ppt,pptx,xls,xlsx,jpg,jpeg,png,gif,mp4,mov,avi,zip,rar')); ?>" 
                       placeholder="pdf,doc,docx,jpg,png,mp4">
                <p class="description"><?php _e('Comma-separated list of allowed file extensions', 'sikshya'); ?></p>
            </div>
            
            <div class="sikshya-settings-field">
                <label class="sikshya-checkbox-label">
                    <input type="checkbox" name="scan_uploads" value="1" 
                           <?php checked(get_option('sikshya_scan_uploads', true)); ?>>
                    <span class="checkmark"></span>
                    <?php _e('Scan Uploaded Files', 'sikshya'); ?>
                </label>
                <p class="description"><?php _e('Scan uploaded files for malware and security threats', 'sikshya'); ?></p>
            </div>
            
            <div class="sikshya-settings-field">
                <label class="sikshya-checkbox-label">
                    <input type="checkbox" name="rename_uploads" value="1" 
                           <?php checked(get_option('sikshya_rename_uploads', true)); ?>>
                    <span class="checkmark"></span>
                    <?php _e('Rename Uploaded Files', 'sikshya'); ?>
                </label>
                <p class="description"><?php _e('Rename uploaded files to prevent path traversal attacks', 'sikshya'); ?></p>
            </div>
        </div>
    </div>

    <div class="sikshya-settings-section">
        <h3 class="sikshya-settings-section-title">
            <i class="fas fa-network-wired"></i>
            <?php _e('API & Integration Security', 'sikshya'); ?>
        </h3>
        
        <div class="sikshya-settings-grid">
            <div class="sikshya-settings-field">
                <label class="sikshya-checkbox-label">
                    <input type="checkbox" name="rate_limit_api" value="1" 
                           <?php checked(get_option('sikshya_rate_limit_api', true)); ?>>
                    <span class="checkmark"></span>
                    <?php _e('Rate Limit API Requests', 'sikshya'); ?>
                </label>
                <p class="description"><?php _e('Limit API requests to prevent abuse and DDoS attacks', 'sikshya'); ?></p>
            </div>
            
            <div class="sikshya-settings-field">
                <label for="api_rate_limit"><?php _e('API Rate Limit (requests/min)', 'sikshya'); ?></label>
                <input type="number" id="api_rate_limit" name="api_rate_limit" 
                       value="<?php echo esc_attr(get_option('sikshya_api_rate_limit', 60)); ?>" 
                       min="10" max="1000">
                <p class="description"><?php _e('Maximum API requests per minute per user', 'sikshya'); ?></p>
            </div>
            
            <div class="sikshya-settings-field">
                <label class="sikshya-checkbox-label">
                    <input type="checkbox" name="require_api_key" value="1" 
                           <?php checked(get_option('sikshya_require_api_key', true)); ?>>
                    <span class="checkmark"></span>
                    <?php _e('Require API Key Authentication', 'sikshya'); ?>
                </label>
                <p class="description"><?php _e('Require API key for external integrations', 'sikshya'); ?></p>
            </div>
            
            <div class="sikshya-settings-field">
                <label for="api_key_expiry_days"><?php _e('API Key Expiry (days)', 'sikshya'); ?></label>
                <input type="number" id="api_key_expiry_days" name="api_key_expiry_days" 
                       value="<?php echo esc_attr(get_option('sikshya_api_key_expiry_days', 365)); ?>" 
                       min="30" max="1095">
                <p class="description"><?php _e('Days after which API keys expire (30-1095 days)', 'sikshya'); ?></p>
            </div>
        </div>
    </div>

    <div class="sikshya-settings-section">
        <h3 class="sikshya-settings-section-title">
            <i class="fas fa-exclamation-triangle"></i>
            <?php _e('Security Monitoring', 'sikshya'); ?>
        </h3>
        
        <div class="sikshya-settings-grid">
            <div class="sikshya-settings-field">
                <label class="sikshya-checkbox-label">
                    <input type="checkbox" name="security_logging" value="1" 
                           <?php checked(get_option('sikshya_security_logging', true)); ?>>
                    <span class="checkmark"></span>
                    <?php _e('Security Event Logging', 'sikshya'); ?>
                </label>
                <p class="description"><?php _e('Log security events for monitoring and auditing', 'sikshya'); ?></p>
            </div>
            
            <div class="sikshya-settings-field">
                <label class="sikshya-checkbox-label">
                    <input type="checkbox" name="email_security_alerts" value="1" 
                           <?php checked(get_option('sikshya_email_security_alerts', true)); ?>>
                    <span class="checkmark"></span>
                    <?php _e('Email Security Alerts', 'sikshya'); ?>
                </label>
                <p class="description"><?php _e('Send email notifications for security events', 'sikshya'); ?></p>
            </div>
            
            <div class="sikshya-settings-field">
                <label for="security_alert_email"><?php _e('Security Alert Email', 'sikshya'); ?></label>
                <input type="email" id="security_alert_email" name="security_alert_email" 
                       value="<?php echo esc_attr(get_option('sikshya_security_alert_email', get_option('admin_email'))); ?>" 
                       placeholder="security@example.com">
                <p class="description"><?php _e('Email address for security alerts and notifications', 'sikshya'); ?></p>
            </div>
            
            <div class="sikshya-settings-field">
                <label for="failed_login_threshold"><?php _e('Failed Login Alert Threshold', 'sikshya'); ?></label>
                <input type="number" id="failed_login_threshold" name="failed_login_threshold" 
                       value="<?php echo esc_attr(get_option('sikshya_failed_login_threshold', 10)); ?>" 
                       min="5" max="50">
                <p class="description"><?php _e('Number of failed logins before sending alert', 'sikshya'); ?></p>
            </div>
        </div>
    </div>

    <div class="sikshya-settings-section">
        <h3 class="sikshya-settings-section-title">
            <i class="fas fa-cogs"></i>
            <?php _e('Advanced Security', 'sikshya'); ?>
        </h3>
        
        <div class="sikshya-settings-grid">
            <div class="sikshya-settings-field">
                <label class="sikshya-checkbox-label">
                    <input type="checkbox" name="two_factor_auth" value="1" 
                           <?php checked(get_option('sikshya_two_factor_auth', false)); ?>>
                    <span class="checkmark"></span>
                    <?php _e('Two-Factor Authentication', 'sikshya'); ?>
                </label>
                <p class="description"><?php _e('Enable two-factor authentication for enhanced security', 'sikshya'); ?></p>
            </div>
            
            <div class="sikshya-settings-field">
                <label class="sikshya-checkbox-label">
                    <input type="checkbox" name="ip_whitelist" value="1" 
                           <?php checked(get_option('sikshya_ip_whitelist', false)); ?>>
                    <span class="checkmark"></span>
                    <?php _e('IP Address Whitelist', 'sikshya'); ?>
                </label>
                <p class="description"><?php _e('Restrict admin access to specific IP addresses', 'sikshya'); ?></p>
            </div>
            
            <div class="sikshya-settings-field">
                <label for="allowed_ip_addresses"><?php _e('Allowed IP Addresses', 'sikshya'); ?></label>
                <textarea id="allowed_ip_addresses" name="allowed_ip_addresses" rows="3" 
                          placeholder="192.168.1.1&#10;10.0.0.0/8&#10;172.16.0.0/12"><?php echo esc_textarea(get_option('sikshya_allowed_ip_addresses', '')); ?></textarea>
                <p class="description"><?php _e('One IP address or CIDR range per line (leave empty to disable)', 'sikshya'); ?></p>
            </div>
            
            <div class="sikshya-settings-field">
                <label class="sikshya-checkbox-label">
                    <input type="checkbox" name="disable_xmlrpc" value="1" 
                           <?php checked(get_option('sikshya_disable_xmlrpc', true)); ?>>
                    <span class="checkmark"></span>
                    <?php _e('Disable XML-RPC', 'sikshya'); ?>
                </label>
                <p class="description"><?php _e('Disable XML-RPC to prevent brute force attacks', 'sikshya'); ?></p>
            </div>
        </div>
    </div>
</div> 