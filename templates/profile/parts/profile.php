<div class="sik-row">
    <div class="sik-col-md-12">
        <div class="sik-avatar">
            <img class="sik-center-block" src="<?php echo esc_url($user_avatar_url) ?>"/>
        </div>
    </div>
</div>
<div class="sik-row">
    <div class="sik-col-md-3">
        <label><strong><?php echo esc_html('First name', 'sikshya') ?></strong></label>
    </div>

    <div class="sik-col-md-3">
    <span><?php echo !empty($user_first_name) ? esc_html($user_first_name): 'N/A' ?></span>
    </div>

    <div class="sik-col-md-3">
    <label><strong><?php echo esc_html('Last name', 'sikshya') ?></strong></label>
    </div>

    <div class="sik-col-md-3">
    <span><?php echo !empty($user_last_name) ? esc_html($user_last_name): 'N/A' ?></span>
    </div>
</div>

<div class="sik-row">
    <div class="sik-col-md-3">
        <label><strong><?php echo esc_html('Nick name', 'sikshya') ?></strong></label>
    </div>

    <div class="sik-col-md-3">
    <span><?php echo !empty($user_nick_name) ? esc_html($user_nick_name): 'N/A' ?></span>
    </div>

    <div class="sik-col-md-3">
    <label><strong><?php echo esc_html('Display name', 'sikshya') ?></strong></label>
    </div>

    <div class="sik-col-md-3">
    <span><?php echo !empty($user_display_name) ? esc_html($user_display_name): 'N/A' ?></span>
    </div>
</div>
<div class="sik-row">
    <div class="sik-col-md-3">
    <label><strong><?php echo esc_html('Email', 'sikshya') ?></strong></label>
    </div>

    <div class="sik-col-md-3">
    <span><?php echo !empty($user_email) ? esc_html($user_email): 'N/A' ?></span>
    </div>

    <div class="sik-col-md-3">
    <label><strong><?php echo esc_html('Website', 'sikshya') ?></strong></label>
    </div>

    <div class="sik-col-md-3">
    <span><?php echo !empty($user_website) ? esc_html($user_website): 'N/A' ?></span>
    </div>
</div>