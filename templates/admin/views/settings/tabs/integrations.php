<?php
/**
 * Integrations Settings Tab Template
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
    <div class="sikshya-settings-section">
        <h3 class="sikshya-settings-section-title">
            <i class="fas fa-plug"></i>
            <?php _e('Third-Party Integrations', 'sikshya'); ?>
        </h3>
        
        <div class="sikshya-settings-grid">
            <div class="sikshya-settings-field">
                <label class="sikshya-checkbox-label">
                    <input type="checkbox" name="enable_google_analytics" value="1" 
                           <?php checked(get_option('sikshya_enable_google_analytics', false)); ?>>
                    <span class="checkmark"></span>
                    <?php _e('Google Analytics', 'sikshya'); ?>
                </label>
                <p class="description"><?php _e('Enable Google Analytics tracking for LMS', 'sikshya'); ?></p>
            </div>
            
            <div class="sikshya-settings-field">
                <label for="google_analytics_id"><?php _e('Google Analytics ID', 'sikshya'); ?></label>
                <input type="text" id="google_analytics_id" name="google_analytics_id" 
                       value="<?php echo esc_attr(get_option('sikshya_google_analytics_id', '')); ?>" 
                       placeholder="G-XXXXXXXXXX">
                <p class="description"><?php _e('Your Google Analytics measurement ID', 'sikshya'); ?></p>
            </div>
            
            <div class="sikshya-settings-field">
                <label class="sikshya-checkbox-label">
                    <input type="checkbox" name="enable_facebook_pixel" value="1" 
                           <?php checked(get_option('sikshya_enable_facebook_pixel', false)); ?>>
                    <span class="checkmark"></span>
                    <?php _e('Facebook Pixel', 'sikshya'); ?>
                </label>
                <p class="description"><?php _e('Enable Facebook Pixel tracking', 'sikshya'); ?></p>
            </div>
            
            <div class="sikshya-settings-field">
                <label for="facebook_pixel_id"><?php _e('Facebook Pixel ID', 'sikshya'); ?></label>
                <input type="text" id="facebook_pixel_id" name="facebook_pixel_id" 
                       value="<?php echo esc_attr(get_option('sikshya_facebook_pixel_id', '')); ?>" 
                       placeholder="123456789012345">
                <p class="description"><?php _e('Your Facebook Pixel ID', 'sikshya'); ?></p>
            </div>
        </div>
    </div>

    <div class="sikshya-settings-section">
        <h3 class="sikshya-settings-section-title">
            <i class="fas fa-envelope-open"></i>
            <?php _e('Email Marketing', 'sikshya'); ?>
        </h3>
        
        <div class="sikshya-settings-grid">
            <div class="sikshya-settings-field">
                <label class="sikshya-checkbox-label">
                    <input type="checkbox" name="enable_mailchimp" value="1" 
                           <?php checked(get_option('sikshya_enable_mailchimp', false)); ?>>
                    <span class="checkmark"></span>
                    <?php _e('Mailchimp Integration', 'sikshya'); ?>
                </label>
                <p class="description"><?php _e('Integrate with Mailchimp for email marketing', 'sikshya'); ?></p>
            </div>
            
            <div class="sikshya-settings-field">
                <label for="mailchimp_api_key"><?php _e('Mailchimp API Key', 'sikshya'); ?></label>
                <input type="password" id="mailchimp_api_key" name="mailchimp_api_key" 
                       value="<?php echo esc_attr(get_option('sikshya_mailchimp_api_key', '')); ?>" 
                       placeholder="Your Mailchimp API Key">
                <p class="description"><?php _e('Your Mailchimp API key for integration', 'sikshya'); ?></p>
            </div>
            
            <div class="sikshya-settings-field">
                <label for="mailchimp_list_id"><?php _e('Mailchimp List ID', 'sikshya'); ?></label>
                <input type="text" id="mailchimp_list_id" name="mailchimp_list_id" 
                       value="<?php echo esc_attr(get_option('sikshya_mailchimp_list_id', '')); ?>" 
                       placeholder="abcdef123456">
                <p class="description"><?php _e('Mailchimp audience/list ID for subscribers', 'sikshya'); ?></p>
            </div>
            
            <div class="sikshya-settings-field">
                <label class="sikshya-checkbox-label">
                    <input type="checkbox" name="auto_subscribe_students" value="1" 
                           <?php checked(get_option('sikshya_auto_subscribe_students', false)); ?>>
                    <span class="checkmark"></span>
                    <?php _e('Auto-subscribe Students', 'sikshya'); ?>
                </label>
                <p class="description"><?php _e('Automatically add new students to email list', 'sikshya'); ?></p>
            </div>
        </div>
    </div>

    <div class="sikshya-settings-section">
        <h3 class="sikshya-settings-section-title">
            <i class="fas fa-comments"></i>
            <?php _e('Communication Tools', 'sikshya'); ?>
        </h3>
        
        <div class="sikshya-settings-grid">
            <div class="sikshya-settings-field">
                <label class="sikshya-checkbox-label">
                    <input type="checkbox" name="enable_slack" value="1" 
                           <?php checked(get_option('sikshya_enable_slack', false)); ?>>
                    <span class="checkmark"></span>
                    <?php _e('Slack Integration', 'sikshya'); ?>
                </label>
                <p class="description"><?php _e('Integrate with Slack for notifications', 'sikshya'); ?></p>
            </div>
            
            <div class="sikshya-settings-field">
                <label for="slack_webhook_url"><?php _e('Slack Webhook URL', 'sikshya'); ?></label>
                <input type="url" id="slack_webhook_url" name="slack_webhook_url" 
                       value="<?php echo esc_url(get_option('sikshya_slack_webhook_url', '')); ?>" 
                       placeholder="https://hooks.slack.com/services/...">
                <p class="description"><?php _e('Slack webhook URL for sending notifications', 'sikshya'); ?></p>
            </div>
            
            <div class="sikshya-settings-field">
                <label class="sikshya-checkbox-label">
                    <input type="checkbox" name="enable_discord" value="1" 
                           <?php checked(get_option('sikshya_enable_discord', false)); ?>>
                    <span class="checkmark"></span>
                    <?php _e('Discord Integration', 'sikshya'); ?>
                </label>
                <p class="description"><?php _e('Integrate with Discord for notifications', 'sikshya'); ?></p>
            </div>
            
            <div class="sikshya-settings-field">
                <label for="discord_webhook_url"><?php _e('Discord Webhook URL', 'sikshya'); ?></label>
                <input type="url" id="discord_webhook_url" name="discord_webhook_url" 
                       value="<?php echo esc_url(get_option('sikshya_discord_webhook_url', '')); ?>" 
                       placeholder="https://discord.com/api/webhooks/...">
                <p class="description"><?php _e('Discord webhook URL for sending notifications', 'sikshya'); ?></p>
            </div>
        </div>
    </div>

    <div class="sikshya-settings-section">
        <h3 class="sikshya-settings-section-title">
            <i class="fas fa-cloud"></i>
            <?php _e('Cloud Storage', 'sikshya'); ?>
        </h3>
        
        <div class="sikshya-settings-grid">
            <div class="sikshya-settings-field">
                <label for="cloud_storage_provider"><?php _e('Cloud Storage Provider', 'sikshya'); ?></label>
                <select id="cloud_storage_provider" name="cloud_storage_provider">
                    <option value="local" <?php selected(get_option('sikshya_cloud_storage_provider', 'local'), 'local'); ?>><?php _e('Local Storage', 'sikshya'); ?></option>
                    <option value="aws" <?php selected(get_option('sikshya_cloud_storage_provider', 'local'), 'aws'); ?>><?php _e('Amazon S3', 'sikshya'); ?></option>
                    <option value="google" <?php selected(get_option('sikshya_cloud_storage_provider', 'local'), 'google'); ?>><?php _e('Google Cloud Storage', 'sikshya'); ?></option>
                    <option value="azure" <?php selected(get_option('sikshya_cloud_storage_provider', 'local'), 'azure'); ?>><?php _e('Microsoft Azure', 'sikshya'); ?></option>
                    <option value="dropbox" <?php selected(get_option('sikshya_cloud_storage_provider', 'local'), 'dropbox'); ?>><?php _e('Dropbox', 'sikshya'); ?></option>
                </select>
                <p class="description"><?php _e('Cloud storage provider for course files', 'sikshya'); ?></p>
            </div>
            
            <div class="sikshya-settings-field">
                <label for="aws_access_key" id="aws_access_key_label" style="display: none;"><?php _e('AWS Access Key', 'sikshya'); ?></label>
                <input type="password" id="aws_access_key" name="aws_access_key" 
                       value="<?php echo esc_attr(get_option('sikshya_aws_access_key', '')); ?>" 
                       placeholder="Your AWS Access Key" style="display: none;">
                <p class="description" id="aws_access_key_desc" style="display: none;"><?php _e('AWS access key for S3 integration', 'sikshya'); ?></p>
            </div>
            
            <div class="sikshya-settings-field">
                <label for="aws_secret_key" id="aws_secret_key_label" style="display: none;"><?php _e('AWS Secret Key', 'sikshya'); ?></label>
                <input type="password" id="aws_secret_key" name="aws_secret_key" 
                       value="<?php echo esc_attr(get_option('sikshya_aws_secret_key', '')); ?>" 
                       placeholder="Your AWS Secret Key" style="display: none;">
                <p class="description" id="aws_secret_key_desc" style="display: none;"><?php _e('AWS secret key for S3 integration', 'sikshya'); ?></p>
            </div>
            
            <div class="sikshya-settings-field">
                <label for="aws_bucket" id="aws_bucket_label" style="display: none;"><?php _e('S3 Bucket Name', 'sikshya'); ?></label>
                <input type="text" id="aws_bucket" name="aws_bucket" 
                       value="<?php echo esc_attr(get_option('sikshya_aws_bucket', '')); ?>" 
                       placeholder="your-bucket-name" style="display: none;">
                <p class="description" id="aws_bucket_desc" style="display: none;"><?php _e('S3 bucket name for file storage', 'sikshya'); ?></p>
            </div>
        </div>
    </div>
</div> 