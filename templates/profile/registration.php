<div class="sikshya-user-registration-form">
    <?php
    do_action('sikshya_before_registration_form');
    ?>
    <form method="post" enctype="multipart/form-data">

        <input type="hidden" value="sikshya_register_user" name="sikshya_action"/>
        <input type="hidden" value="sikshya_user_registration" name="sikshya_registration_notice"/>
        <input type="hidden" value="<?php echo wp_create_nonce('wp_sikshya_register_user_nonce') ?>"
               name="sikshya_nonce"/>
        <div class="sik-row">
            <div class="sik-col-md-6">
                <div class="form-group">
                    <label>
                        <?php _e('First Name', 'sikshya'); ?>
                    </label>

                    <input type="text" name="first_name"
                           value="<?php echo sikshya()->helper->input('first_name'); ?>"
                           placeholder="<?php _e('First Name', 'sikshya'); ?>">
                </div>
            </div>

            <div class="sik-col-md-6">
                <div class="form-group">
                    <label>
                        <?php _e('Last Name', 'sikshya'); ?>
                    </label>

                    <input type="text" name="last_name" value="<?php echo sikshya()->helper->input('last_name'); ?>"
                           placeholder="<?php _e('Last Name', 'sikshya'); ?>">
                </div>
            </div>

        </div>

        <div class="sik-row">
            <div class="sik-col-md-6">
                <div class="form-group">
                    <label>
                        <?php _e('User Name', 'sikshya'); ?>
                    </label>

                    <input type="text" name="user_login" class="sikshya_user_name"
                           value="<?php echo sikshya()->helper->input('user_login'); ?>"
                           placeholder="<?php _e('User Name', 'sikshya'); ?>">
                </div>
            </div>

            <div class="sik-col-md-6">
                <div class="form-group">
                    <label>
                        <?php _e('E-Mail', 'sikshya'); ?>
                    </label>

                    <input type="text" name="email" value="<?php echo sikshya()->helper->input('email'); ?>"
                           placeholder="<?php _e('E-Mail', 'sikshya'); ?>">
                </div>
            </div>

        </div>

        <div class="sik-row">
            <div class="sik-col-md-6">
                <div class="form-group">
                    <label>
                        <?php _e('Password', 'sikshya'); ?>
                    </label>

                    <input type="password" name="password"
                           value="<?php echo sikshya()->helper->input('password'); ?>"
                           placeholder="<?php _e('Password', 'sikshya'); ?>">
                </div>
            </div>

            <div class="sik-col-md-6">
                <div class="form-group">
                    <label>
                        <?php _e('Password confirmation', 'sikshya'); ?>
                    </label>

                    <input type="password" name="password_confirmation"
                           value="<?php echo sikshya()->helper->input('password_confirmation'); ?>"
                           placeholder="<?php _e('Password Confirmation', 'sikshya'); ?>">
                </div>
            </div>
        </div>
        <div class="sik-row">
            <div class="sik-col-md-12">
                <div class="form-group sikshya-reg-form-btn-wrap">
                    <button type="submit" name="sikshya_register_student_btn" value="register"
                            class="sikshya-button"><?php _e('Register', 'sikshya'); ?></button>
                </div>
            </div>
        </div>

    </form>
    <?php
    do_action('sikshya_after_registration_form');
    ?>
</div>