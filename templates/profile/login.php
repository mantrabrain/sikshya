<div class="sikshya-user-login-form">
    <?php
    do_action('sikshya_before_login_form');
    ?>
    <form method="post">
        <input type="hidden" value="sikshya_login_user" name="sikshya_action"/>
        <input type="hidden" value="sikshya_user_login" name="sikshya_login_notice"/>
        <?php
        $redirect_to = isset($_GET['redirect_to']) ? esc_url($_GET['redirect_to']) : '';
        if (!empty($redirect_to)) {
            ?>
            <input type="hidden" value="<?php echo esc_url($redirect_to) ?>" name="sikshya_redirect_to"/>
        <?php } ?>
        <input type="hidden" value="<?php echo wp_create_nonce('wp_sikshya_login_user_nonce') ?>"
               name="sikshya_nonce"/>
        <div class="sik-row">
            <div class="sik-col-md-12">
                <div class="form-group">
                    <label>
                        <?php _e('Username', 'sikshya'); ?>
                    </label>

                    <input type="text" name="user_login"
                           value="<?php echo sikshya()->helper->input('user_login'); ?>"
                           placeholder="<?php _e('Username or Email address', 'sikshya'); ?>">
                </div>
            </div>

        </div>
        <div class="sik-row">
            <div class="sik-col-md-12">
                <div class="form-group">
                    <label>
                        <?php _e('Password', 'sikshya'); ?>
                    </label>

                    <input type="password" name="password" value="<?php echo sikshya()->helper->input('password'); ?>"
                           placeholder="<?php _e('Password', 'sikshya'); ?>">
                </div>
            </div>

        </div>
        <div class="sik-row">
            <div class="sik-col-md-6">
                <div class="form-group">
                    <label>
                        <?php _e('Remember Me', 'sikshya'); ?>
                    </label>

                    <input type="checkbox" name="rememberme"
                           value="1" <?php echo sikshya()->helper->input('rememberme') == "1" ? 'checked="checked"' : ''; ?>
                           placeholder="<?php _e('Remember Me', 'sikshya'); ?>">
                </div>
            </div>
            <div class="sik-col-md-6">
                <div class="form-group">
                    <?php $forgot_password_link = home_url('wp-login.php?action=lostpassword'); ?>
                    <label>
                        <a href="<?php echo esc_url($forgot_password_link) ?>"><?php _e('Forgot password', 'sikshya'); ?></a>
                    </label>

                </div>
            </div>


        </div>
        <div class="sik-row">
            <div class="sik-col-md-12">
                <div class="form-group sikshya-login-form-btn-wrap">
                    <button type="submit" name="sikshya_login_btn" value="Login"
                            class="sikshya-button"><?php _e('Login', 'sikshya'); ?></button>
                </div>
            </div>
            <?php
            $registration_page_link = sikshya_get_user_registration_page(true);
            if (!empty($registration_page_link)) {
                ?>
                <div class="sik-col-md-12">
                    <div class="form-group">

                        <a href="<?php echo esc_url($registration_page_link) ?>"><?php _e('Create new account', 'sikshya'); ?></a>
                    </div>
                </div>
            <?php } ?>
        </div>

    </form>
    <?php
    do_action('sikshya_after_login_form');
    ?>
</div>