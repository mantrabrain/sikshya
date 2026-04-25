<?php
/**
 * One-time setup wizard (admin).
 *
 * @var \Sikshya\Core\Plugin $plugin
 * @var bool                $saved
 * @var array<int, string>  $errors
 * @var array<string,string> $permalinks
 * @var bool                $learn_use_public_id
 */

if (!defined('ABSPATH')) {
    exit;
}

$action_url = esc_url(admin_url('admin.php?page=sikshya-setup'));
$is_done = \Sikshya\Services\Settings::isTruthy(\Sikshya\Services\Settings::get('setup_completed', '0'));
?>

<div class="sikshya-setup">
    <div class="sikshya-setup__shell">
        <header class="sikshya-setup__header">
            <div class="sikshya-setup__brand">
                <div class="sikshya-setup__logo" aria-hidden="true">S</div>
                <div>
                    <div class="sikshya-setup__title"><?php esc_html_e('Welcome to Sikshya', 'sikshya'); ?></div>
                    <div class="sikshya-setup__subtitle"><?php esc_html_e('A quick setup to get you live in minutes.', 'sikshya'); ?></div>
                </div>
            </div>
            <div class="sikshya-setup__meta">
                <span class="sikshya-setup__badge"><?php echo esc_html(sprintf(__('v%s', 'sikshya'), (string) $plugin->version)); ?></span>
            </div>
        </header>

        <div class="sikshya-setup__progress" role="progressbar" aria-label="<?php esc_attr_e('Setup progress', 'sikshya'); ?>">
            <div class="sikshya-setup__progress-bar" data-setup-progress></div>
        </div>

        <?php if ($errors !== []) : ?>
            <div class="sikshya-setup__notice sikshya-setup__notice--error" role="alert">
                <strong><?php esc_html_e('Please fix the following:', 'sikshya'); ?></strong>
                <ul>
                    <?php foreach ($errors as $e) : ?>
                        <li><?php echo esc_html($e); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ($saved) : ?>
            <div class="sikshya-setup__notice sikshya-setup__notice--success" role="status">
                <?php esc_html_e('Setup saved. You are ready to start building courses.', 'sikshya'); ?>
            </div>
        <?php endif; ?>

        <?php if ($is_done) : ?>
            <div class="sikshya-setup__notice" role="status">
                <?php esc_html_e('Setup is already complete. You can review settings below and save again if needed.', 'sikshya'); ?>
            </div>
        <?php endif; ?>

        <form class="sikshya-setup__form" method="post" action="<?php echo $action_url; ?>">
            <?php wp_nonce_field('sikshya_setup_wizard', 'sikshya_setup_nonce'); ?>

            <section class="sikshya-setup__step" data-setup-step="1">
                <h2><?php esc_html_e('1. Basics', 'sikshya'); ?></h2>
                <p class="sikshya-setup__hint">
                    <?php esc_html_e('This wizard only changes Sikshya settings. Your theme stays untouched.', 'sikshya'); ?>
                </p>

                <div class="sikshya-setup__grid">
                    <div class="sikshya-setup__card">
                        <h3><?php esc_html_e('Recommended next steps', 'sikshya'); ?></h3>
                        <ol class="sikshya-setup__list">
                            <li><?php esc_html_e('Create your first course in Sikshya → Courses.', 'sikshya'); ?></li>
                            <li><?php esc_html_e('Open Sikshya → Settings to configure payments, emails, and branding.', 'sikshya'); ?></li>
                            <li><?php esc_html_e('Visit the site and test /cart → /checkout → /learn.', 'sikshya'); ?></li>
                        </ol>
                    </div>

                    <div class="sikshya-setup__card">
                        <h3><?php esc_html_e('Permalinks mode', 'sikshya'); ?></h3>
                        <p class="sikshya-setup__muted">
                            <?php esc_html_e('Sikshya uses virtual pages. If your site uses “Plain” permalinks, URLs will use query args.', 'sikshya'); ?>
                        </p>
                        <p class="sikshya-setup__muted">
                            <?php esc_html_e('You can change these slugs later in Settings.', 'sikshya'); ?>
                        </p>
                    </div>
                </div>
            </section>

            <section class="sikshya-setup__step" data-setup-step="2" hidden>
                <h2><?php esc_html_e('2. Frontend slugs', 'sikshya'); ?></h2>
                <p class="sikshya-setup__hint">
                    <?php esc_html_e('These control the URLs for cart, checkout, account, learn, and order pages.', 'sikshya'); ?>
                </p>

                <div class="sikshya-setup__fields">
                    <div class="sikshya-setup__field">
                        <label for="permalink_cart"><?php esc_html_e('Cart', 'sikshya'); ?></label>
                        <input id="permalink_cart" name="permalink_cart" type="text" value="<?php echo esc_attr($permalinks['permalink_cart'] ?? 'cart'); ?>" />
                    </div>
                    <div class="sikshya-setup__field">
                        <label for="permalink_checkout"><?php esc_html_e('Checkout', 'sikshya'); ?></label>
                        <input id="permalink_checkout" name="permalink_checkout" type="text" value="<?php echo esc_attr($permalinks['permalink_checkout'] ?? 'checkout'); ?>" />
                    </div>
                    <div class="sikshya-setup__field">
                        <label for="permalink_account"><?php esc_html_e('Account', 'sikshya'); ?></label>
                        <input id="permalink_account" name="permalink_account" type="text" value="<?php echo esc_attr($permalinks['permalink_account'] ?? 'account'); ?>" />
                    </div>
                    <div class="sikshya-setup__field">
                        <label for="permalink_learn"><?php esc_html_e('Learn', 'sikshya'); ?></label>
                        <input id="permalink_learn" name="permalink_learn" type="text" value="<?php echo esc_attr($permalinks['permalink_learn'] ?? 'learn'); ?>" />
                    </div>
                    <div class="sikshya-setup__field">
                        <label for="permalink_order"><?php esc_html_e('Order receipt', 'sikshya'); ?></label>
                        <input id="permalink_order" name="permalink_order" type="text" value="<?php echo esc_attr($permalinks['permalink_order'] ?? 'order'); ?>" />
                    </div>
                </div>
            </section>

            <section class="sikshya-setup__step" data-setup-step="3" hidden>
                <h2><?php esc_html_e('3. Learn URL style', 'sikshya'); ?></h2>
                <p class="sikshya-setup__hint">
                    <?php esc_html_e('Choose whether the Learn player URLs include a stable public id segment.', 'sikshya'); ?>
                </p>

                <div class="sikshya-setup__card">
                    <div class="sikshya-setup__radio">
                        <label>
                            <input type="radio" name="learn_permalink_use_public_id" value="1" <?php checked($learn_use_public_id, true); ?> />
                            <span>
                                <strong><?php esc_html_e('Recommended', 'sikshya'); ?></strong><br />
                                <span class="sikshya-setup__muted"><?php esc_html_e('Includes a stable public id: /learn/lesson/{id}/{slug}', 'sikshya'); ?></span>
                            </span>
                        </label>
                    </div>
                    <div class="sikshya-setup__radio">
                        <label>
                            <input type="radio" name="learn_permalink_use_public_id" value="0" <?php checked($learn_use_public_id, false); ?> />
                            <span>
                                <strong><?php esc_html_e('Simpler', 'sikshya'); ?></strong><br />
                                <span class="sikshya-setup__muted"><?php esc_html_e('Slug-only: /learn/lesson/{slug}', 'sikshya'); ?></span>
                            </span>
                        </label>
                    </div>
                </div>
            </section>

            <section class="sikshya-setup__step" data-setup-step="4" hidden>
                <h2><?php esc_html_e('4. Finish', 'sikshya'); ?></h2>
                <p class="sikshya-setup__hint">
                    <?php esc_html_e('Save these settings and go to the Sikshya dashboard.', 'sikshya'); ?>
                </p>

                <div class="sikshya-setup__card">
                    <p class="sikshya-setup__muted">
                        <?php esc_html_e('After saving, go to Sikshya → Courses to create your first course.', 'sikshya'); ?>
                    </p>
                </div>
            </section>

            <footer class="sikshya-setup__actions">
                <button type="button" class="button button-secondary" data-setup-prev>
                    <?php esc_html_e('Back', 'sikshya'); ?>
                </button>

                <div class="sikshya-setup__actions-right">
                    <button type="submit" class="button button-link-delete" name="wizard_action" value="skip">
                        <?php esc_html_e('Skip for now', 'sikshya'); ?>
                    </button>
                    <button type="button" class="button button-primary" data-setup-next>
                        <?php esc_html_e('Next', 'sikshya'); ?>
                    </button>
                    <button type="submit" class="button button-primary" name="wizard_action" value="save" data-setup-finish hidden>
                        <?php esc_html_e('Save and continue', 'sikshya'); ?>
                    </button>
                </div>
            </footer>
        </form>
    </div>
</div>

