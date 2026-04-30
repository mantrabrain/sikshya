<?php
/**
 * Account: profile and security settings.
 *
 * @package Sikshya
 *
 * @var array<string, mixed>                           $acc
 * @var \Sikshya\Presentation\Models\AccountPageModel  $page_model
 */

$uid = $page_model->getUserId();
$user = $uid > 0 ? get_userdata($uid) : false;
$first_name = $user ? (string) $user->first_name : '';
$last_name = $user ? (string) $user->last_name : '';
$display_name = $page_model->getDisplayName();
$email = $page_model->getEmail();
$pending_email = $uid > 0 ? get_user_meta($uid, '_new_email', true) : [];
$pending_new_email = (is_array($pending_email) && isset($pending_email['newemail'])) ? sanitize_email((string) $pending_email['newemail']) : '';

$notice_code = isset($_GET['sik_acc_notice']) ? sanitize_key((string) wp_unslash($_GET['sik_acc_notice'])) : '';
$error_code = isset($_GET['sik_acc_err']) ? sanitize_key((string) wp_unslash($_GET['sik_acc_err'])) : '';

$notice_map = [
    'profile_saved' => __('Profile updated successfully.', 'sikshya'),
    'password_saved' => __('Password changed successfully.', 'sikshya'),
    'email_confirmation_sent' => __('Profile saved. Please check your new email address and click the confirmation link to complete the email change.', 'sikshya'),
];
$error_map = [
    'invalid_nonce' => __('Security check failed. Please try again.', 'sikshya'),
    'user_not_found' => __('User account could not be loaded.', 'sikshya'),
    'invalid_email' => __('Please enter a valid email address.', 'sikshya'),
    'email_in_use' => __('That email is already in use by another account.', 'sikshya'),
    'profile_update_failed' => __('Could not update your profile. Please try again.', 'sikshya'),
    'password_fields_required' => __('Fill all password fields to change your password.', 'sikshya'),
    'password_current_invalid' => __('Current password is incorrect.', 'sikshya'),
    'password_too_short' => __('New password must be at least 8 characters.', 'sikshya'),
    'password_mismatch' => __('New password and confirm password do not match.', 'sikshya'),
    'email_confirmation_failed' => __('Profile saved, but we could not send the email confirmation link. Please try again.', 'sikshya'),
];
?>
            <section class="sik-acc-panel" aria-label="<?php esc_attr_e('Profile details', 'sikshya'); ?>">
                <div class="sik-acc-panel__head">
                    <div class="sik-acc-panel__title-block">
                        <h2 class="sik-acc-panel__title"><?php esc_html_e('Profile details', 'sikshya'); ?></h2>
                        <p class="sik-acc-panel__sub"><?php esc_html_e('Update how your account appears across your courses and receipts.', 'sikshya'); ?></p>
                    </div>
                </div>

                <?php if ($notice_code !== '' && isset($notice_map[$notice_code])) : ?>
                    <div class="sik-acc-notice sik-acc-notice--ok" role="status" aria-live="polite" data-sik-acc-dismissible>
                        <span><?php echo esc_html($notice_map[$notice_code]); ?></span>
                        <button type="button" class="sik-acc-notice__close" data-sik-acc-dismiss aria-label="<?php esc_attr_e('Dismiss notice', 'sikshya'); ?>">×</button>
                    </div>
                <?php endif; ?>
                <?php if ($error_code !== '' && isset($error_map[$error_code])) : ?>
                    <div class="sik-acc-notice sik-acc-notice--err" role="alert" data-sik-acc-dismissible>
                        <span><?php echo esc_html($error_map[$error_code]); ?></span>
                        <button type="button" class="sik-acc-notice__close" data-sik-acc-dismiss aria-label="<?php esc_attr_e('Dismiss notice', 'sikshya'); ?>">×</button>
                    </div>
                <?php endif; ?>
                <?php if ($pending_new_email !== '') : ?>
                    <div class="sik-acc-notice sik-acc-notice--pending" role="status" data-sik-acc-dismissible>
                        <span>
                            <?php
                            echo esc_html(
                                sprintf(
                                    /* translators: %s: pending new email address */
                                    __('Pending email change: %s. Confirm from your inbox to apply it.', 'sikshya'),
                                    $pending_new_email
                                )
                            );
                            ?>
                        </span>
                        <button type="button" class="sik-acc-notice__close" data-sik-acc-dismiss aria-label="<?php esc_attr_e('Dismiss notice', 'sikshya'); ?>">×</button>
                    </div>
                <?php endif; ?>

                <form method="post" class="sik-acc-form-grid sik-acc-form-grid--profile">
                    <?php wp_nonce_field('sikshya_account_profile_update'); ?>
                    <div class="sik-acc-field">
                        <label class="sik-acc-field__label" for="sik-acc-first-name"><?php esc_html_e('First name', 'sikshya'); ?></label>
                        <input class="sik-acc-input" id="sik-acc-first-name" name="first_name" type="text" value="<?php echo esc_attr($first_name); ?>" autocomplete="given-name" placeholder="<?php esc_attr_e('e.g. John', 'sikshya'); ?>">
                        <p class="sik-acc-field__desc"><?php esc_html_e('Optional. Used for account profile and personalized emails.', 'sikshya'); ?></p>
                    </div>
                    <div class="sik-acc-field">
                        <label class="sik-acc-field__label" for="sik-acc-last-name"><?php esc_html_e('Last name', 'sikshya'); ?></label>
                        <input class="sik-acc-input" id="sik-acc-last-name" name="last_name" type="text" value="<?php echo esc_attr($last_name); ?>" autocomplete="family-name" placeholder="<?php esc_attr_e('e.g. Doe', 'sikshya'); ?>">
                        <p class="sik-acc-field__desc"><?php esc_html_e('Optional. Combined with first name when available.', 'sikshya'); ?></p>
                    </div>
                    <div class="sik-acc-field">
                        <label class="sik-acc-field__label" for="sik-acc-display-name"><?php esc_html_e('Display name', 'sikshya'); ?></label>
                        <input class="sik-acc-input" id="sik-acc-display-name" name="display_name" type="text" value="<?php echo esc_attr($display_name); ?>" required autocomplete="name" placeholder="<?php esc_attr_e('Name shown to learners', 'sikshya'); ?>">
                        <p class="sik-acc-field__desc"><?php esc_html_e('This name appears on course pages, certificates, and reviews.', 'sikshya'); ?></p>
                    </div>
                    <div class="sik-acc-field">
                        <label class="sik-acc-field__label" for="sik-acc-email"><?php esc_html_e('Email address', 'sikshya'); ?></label>
                        <input class="sik-acc-input" id="sik-acc-email" name="user_email" type="email" value="<?php echo esc_attr($email); ?>" required autocomplete="email" placeholder="<?php esc_attr_e('you@example.com', 'sikshya'); ?>">
                        <p class="sik-acc-field__desc"><?php esc_html_e('Used for login and transactional emails like receipts and access updates.', 'sikshya'); ?></p>
                    </div>
                    <div class="sik-acc-form-actions sik-acc-form-actions--full">
                        <button class="sik-acc-btn sik-acc-btn--primary" type="submit" name="sikshya_account_profile_submit" value="1"><?php esc_html_e('Save profile', 'sikshya'); ?></button>
                    </div>
                </form>
            </section>

            <section class="sik-acc-panel" aria-label="<?php esc_attr_e('Password', 'sikshya'); ?>">
                <div class="sik-acc-panel__head">
                    <div class="sik-acc-panel__title-block">
                        <h2 class="sik-acc-panel__title"><?php esc_html_e('Change password', 'sikshya'); ?></h2>
                        <p class="sik-acc-panel__sub"><?php esc_html_e('Use your current password to set a new one. Minimum 8 characters.', 'sikshya'); ?></p>
                    </div>
                </div>
                <form method="post" class="sik-acc-form-grid sik-acc-form-grid--full">
                    <?php wp_nonce_field('sikshya_account_profile_update'); ?>
                    <div class="sik-acc-field">
                        <label class="sik-acc-field__label" for="sik-acc-current-password"><?php esc_html_e('Current password', 'sikshya'); ?></label>
                        <input class="sik-acc-input" id="sik-acc-current-password" name="current_password" type="password" autocomplete="current-password" required placeholder="<?php esc_attr_e('Enter your current password', 'sikshya'); ?>">
                        <p class="sik-acc-field__desc"><?php esc_html_e('Required to verify this change for your account security.', 'sikshya'); ?></p>
                    </div>
                    <div class="sik-acc-field">
                        <label class="sik-acc-field__label" for="sik-acc-new-password"><?php esc_html_e('New password', 'sikshya'); ?></label>
                        <input class="sik-acc-input" id="sik-acc-new-password" name="new_password" type="password" autocomplete="new-password" minlength="8" required placeholder="<?php esc_attr_e('At least 8 characters', 'sikshya'); ?>">
                        <p class="sik-acc-field__desc"><?php esc_html_e('Use a strong password with letters, numbers, and symbols when possible.', 'sikshya'); ?></p>
                    </div>
                    <div class="sik-acc-field">
                        <label class="sik-acc-field__label" for="sik-acc-confirm-password"><?php esc_html_e('Confirm new password', 'sikshya'); ?></label>
                        <input class="sik-acc-input" id="sik-acc-confirm-password" name="confirm_password" type="password" autocomplete="new-password" minlength="8" required placeholder="<?php esc_attr_e('Re-enter new password', 'sikshya'); ?>">
                        <p class="sik-acc-field__desc"><?php esc_html_e('Must match the new password exactly.', 'sikshya'); ?></p>
                    </div>
                    <div class="sik-acc-form-actions sik-acc-form-actions--full">
                        <button class="sik-acc-btn sik-acc-btn--primary" type="submit" name="sikshya_account_password_submit" value="1"><?php esc_html_e('Change password', 'sikshya'); ?></button>
                    </div>
                </form>
            </section>

            <script>
                (function () {
                    var notices = document.querySelectorAll('[data-sik-acc-dismissible]');
                    if (!notices.length) return;
                    notices.forEach(function (n) {
                        var b = n.querySelector('[data-sik-acc-dismiss]');
                        if (!b) return;
                        b.addEventListener('click', function () {
                            n.style.display = 'none';
                        });
                    });
                })();
            </script>
