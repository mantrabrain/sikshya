<h2><?php echo __('Edit Profile') ?></h2>
<form class="sikshya-update-profile" method="post">
    <?php
    do_action('sikshya_before_update_profile_form');
    ?>
    <input type="hidden" value="<?php echo absint($user_id); ?>" name="current_user_id"/>
    <input type="hidden" value="sikshya_update_profile" name="sikshya_action"/>
    <input type="hidden" value="sikshya_update_profile" name="sikshya_notice"/>
    <input type="hidden" value="<?php echo wp_create_nonce('wp_sikshya_update_profile_nonce') ?>" name="sikshya_nonce"/>
    <div class="sik-row">
        <div class="sik-col-md-12">
            <div class="sik-avatar">
                <img class="sik-center-block" src="<?php echo esc_url($user_avatar_url) ?>"/>
            </div>
        </div>
    </div>
    <div class="sik-row">
        <div class="sikshya-update-profile">
            <div class="sik-col-md-12">
                <label for="first_name"><strong><?php echo esc_html('First name', 'sikshya') ?>:</strong></label>
            </div>

            <div class="sik-col-md-12">
                <input type="text" value="<?php echo esc_attr($user_first_name) ?>" name="first_name"/>
            </div>

            <div class="sik-col-md-12">
                <label><strong><?php echo esc_html('Last name', 'sikshya') ?></strong>:</label>
            </div>

            <div class="sik-col-md-12">
                <input type="text" value="<?php echo esc_attr($user_last_name) ?>" name="last_name"/>
            </div>
            <div class="sik-col-md-12">
                <label><strong><?php echo esc_html('Nice name', 'sikshya') ?></strong></label>
            </div>

            <div class="sik-col-md-12">
                <input type="text" value="<?php echo esc_attr($user_nicename) ?>" name="nicename"/>
            </div>
            <div class="sik-col-md-12">
                <label><strong><?php echo esc_html('Display name', 'sikshya') ?></strong></label>
            </div>
            <div class="sik-col-md-12">
                <span><?php echo !empty($ser_display_name) ? esc_html($ser_display_name) : 'N/A' ?></span>
            </div>
            <div class="sik-col-md-12">
                <label><strong><?php echo esc_html('Email', 'sikshya') ?></strong></label>
            </div>
            <div class="sik-col-md-12">
                <span><?php echo !empty($user_email) ? esc_html($user_email) : 'N/A' ?></span>
            </div>
            <div class="sik-col-md-12">
                <label><strong><?php echo esc_html('Website', 'sikshya') ?></strong></label>
            </div>
            <div class="sik-col-md-12">
                <input type="text" value="<?php echo esc_attr($user_website) ?>" name="website"/>
            </div>
        </div>
    </div>
    <div class="sik-row">
        <div class="sikshya-change-password">
            <div class="sik-col-md-12">
                <label><input type="checkbox" name="sikshya_change_password" value="1"/>
                    <strong><?php echo esc_html('Change Password', 'sikshya') ?></strong>:</label>
            </div>

            <div class="sik-col-md-12">
                <label><strong><?php echo esc_html('Old Password', 'sikshya') ?></strong>:</label>
            </div>

            <div class="sik-col-md-12">
                <input type="password" value="" name="old_password" disabled="disabled" class="sikshya-password-field"/>
            </div>
            <div class="sik-col-md-12">
                <label><strong><?php echo esc_html('New Password', 'sikshya') ?></strong></label>
            </div>

            <div class="sik-col-md-12">
                <input type="password" value="" name="new_password" disabled="disabled" class="sikshya-password-field"/>
            </div>
            <div class="sik-col-md-12">
                <label><strong><?php echo esc_html('Confirm Password', 'sikshya') ?></strong></label>
            </div>
            <div class="sik-col-md-12">
                <input type="password" value="" name="confirm_password" disabled="disabled"
                       class="sikshya-password-field"/>
            </div>
        </div>

    </div>
    <div class="sik-row">
        <div class="sik-col-md-12">
            <button type="submit" name="submit"><?php echo __('Save changes', 'sikshya') ?></button>
        </div>
    </div>
    <?php
    do_action('sikshya_after_update_profile_form');
    ?>
    <div class="clearfix"></div>
</form>
