<div class="sikshya-content-protected-message">
    This content is protected, please
    <?php if (!get_current_user_id() > 0) { ?>
        <a
                href="<?php echo esc_url(sikshya_get_login_page(true, get_permalink())); ?>">login</a>
        and <?php } ?>
    enroll course to view this content!
</div>