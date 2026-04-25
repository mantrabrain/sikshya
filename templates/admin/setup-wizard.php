<?php
/**
 * One-time setup wizard (admin).
 *
 * Designed for first-time admins:
 * - One topic per page (low cognitive load)
 * - Plain-language titles (no jargon like "slugs" or "permalinks")
 * - Smart defaults pre-filled so "Next" always works
 * - Calm visual hierarchy; no noisy admin notices
 *
 * @var \Sikshya\Core\Plugin $plugin
 * @var string               $wizard_page_url
 * @var array<int, string>   $errors
 * @var array<string,string> $permalinks
 * @var bool                 $learn_use_public_id
 * @var int                  $initial_step
 * @var bool                 $show_done
 * @var array<string,mixed>  $sample_import   Optional. Result of optional sample-data import.
 *                                            Shape: ['success' => bool, 'message' => string,
 *                                            'counts' => array<string,int>].
 */

if (!defined('ABSPATH')) {
    exit;
}

use Sikshya\Admin\SetupWizardController;
use Sikshya\Services\PermalinkService;
use Sikshya\Services\Settings;

$wizard_page_url = isset($wizard_page_url) ? (string) $wizard_page_url : SetupWizardController::adminUrl(1);
$action_url = esc_url($wizard_page_url);
$initial_step = isset($initial_step) ? max(1, min(5, (int) $initial_step)) : 1;
$show_done = !empty($show_done);
$plain = PermalinkService::isPlainPermalinks();

$total_steps = 5;
$steps_meta = [
    1 => ['key' => 'welcome', 'label' => __('Welcome', 'sikshya')],
    2 => ['key' => 'pages', 'label' => __('Pages', 'sikshya')],
    3 => ['key' => 'currency', 'label' => __('Currency', 'sikshya')],
    4 => ['key' => 'lessons', 'label' => __('Lessons', 'sikshya')],
    5 => ['key' => 'finish', 'label' => __('Finish', 'sikshya')],
];

// Currency settings (used by `sikshya_format_price_plain()`).
$currency = (string) get_option('_sikshya_currency', 'USD');
$currency_position = (string) get_option('_sikshya_currency_position', 'left');
$currency_decimal_places = (string) get_option('_sikshya_currency_decimal_places', '2');
$currency_thousand_separator = (string) get_option('_sikshya_currency_thousand_separator', ',');
$currency_decimal_separator = (string) get_option('_sikshya_currency_decimal_separator', '.');

if ($show_done) {
    ?>
    <div class="sikshya-setup sikshya-setup--done" data-sikshya-setup data-initial-step="done">
        <div class="sikshya-setup__shell sikshya-setup__shell--celebrate">
            <header class="sikshya-setup__header">
                <div class="sikshya-setup__brand">
                    <div class="sikshya-setup__logo" aria-hidden="true">S</div>
                    <div>
                        <div class="sikshya-setup__title"><?php esc_html_e('Sikshya', 'sikshya'); ?></div>
                        <div class="sikshya-setup__subtitle"><?php esc_html_e('Setup complete', 'sikshya'); ?></div>
                    </div>
                </div>
                <div class="sikshya-setup__meta">
                    <span class="sikshya-setup__badge"><?php echo esc_html(sprintf(__('v%s', 'sikshya'), (string) $plugin->version)); ?></span>
                </div>
            </header>

            <div class="sikshya-setup__celebrate" role="status">
                <div class="sikshya-setup__celebrate-card">
                    <div class="sikshya-setup__confetti" aria-hidden="true">
                        <?php
                        $colors = [
                            'rgb(37 99 235)',
                            'rgb(14 165 233)',
                            'rgb(34 197 94)',
                            'rgb(245 158 11)',
                            'rgb(168 85 247)',
                            'rgb(239 68 68)',
                        ];
                        for ($i = 0; $i < 18; $i++) {
                            $c = $colors[$i % count($colors)];
                            $left = 5 + ($i * 5);
                            $dur = 1100 + ($i % 6) * 220;
                            $delay = ($i % 9) * 120;
                            $w = 8 + ($i % 4) * 2;
                            $h = 10 + ($i % 5) * 2;
                            echo '<i style="left:' . esc_attr((string) $left) . '%;background:' . esc_attr($c) . ';width:' . esc_attr((string) $w) . 'px;height:' . esc_attr((string) $h) . 'px;--dur:' . esc_attr((string) $dur) . 'ms;--delay:' . esc_attr((string) $delay) . 'ms"></i>';
                        }
                        ?>
                    </div>
                    <div class="sikshya-setup__celebrate-icon" aria-hidden="true">
                        <svg viewBox="0 0 80 80" fill="none" xmlns="http://www.w3.org/2000/svg" class="sikshya-setup__check-svg">
                            <circle cx="40" cy="40" r="38" stroke="currentColor" stroke-width="3" class="sikshya-setup__check-ring"/>
                            <path class="sikshya-setup__check-mark" d="M24 42l12 12 22-26" stroke="currentColor" stroke-width="4" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
                        </svg>
                    </div>

                    <h1 class="sikshya-setup__celebrate-title"><?php esc_html_e("You're all set!", 'sikshya'); ?></h1>
                    <p class="sikshya-setup__celebrate-lead">
                        <?php esc_html_e('Sikshya saved your choices. Next, create your first course and try a test purchase.', 'sikshya'); ?>
                    </p>

                    <?php
                    // Show feedback for the optional sample-data import (success or failure).
                    $sample_import = isset($sample_import) && is_array($sample_import) ? $sample_import : [];
                    $sample_ok = !empty($sample_import['success']);
                    $sample_counts = isset($sample_import['counts']) && is_array($sample_import['counts']) ? $sample_import['counts'] : [];
                    if ($sample_import !== []) :
                        if ($sample_ok) :
                            $courses_link = admin_url('admin.php?page=sikshya&view=courses');
                            $count_pairs = [
                                'courses' => __('courses', 'sikshya'),
                                'chapters' => __('chapters', 'sikshya'),
                                'lessons' => __('lessons', 'sikshya'),
                                'quizzes' => __('quizzes', 'sikshya'),
                                'questions' => __('questions', 'sikshya'),
                                'assignments' => __('assignments', 'sikshya'),
                            ];
                            ?>
                            <div class="sikshya-setup__sample-result sikshya-setup__sample-result--ok" role="status">
                                <span class="sikshya-setup__sample-result-icon" aria-hidden="true">
                                    <svg viewBox="0 0 16 16" width="14" height="14"><path d="M3.5 8.5l3 3 6-7" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                </span>
                                <span class="sikshya-setup__sample-result-body">
                                    <strong><?php esc_html_e('Sample course added.', 'sikshya'); ?></strong>
                                    <?php
                                    $bits = [];
                                    foreach ($count_pairs as $k => $label) {
                                        $n = isset($sample_counts[ $k ]) ? (int) $sample_counts[ $k ] : 0;
                                        if ($n > 0) {
                                            $bits[] = sprintf('%d %s', $n, $label);
                                        }
                                    }
                                    if ($bits !== []) {
                                        echo ' ' . esc_html(sprintf(
                                            /* translators: %s: comma-separated list of imported items, e.g. "1 courses, 3 chapters". */
                                            __('Created %s.', 'sikshya'),
                                            implode(', ', $bits)
                                        ));
                                    }
                                    ?>
                                    <a class="sikshya-setup__inline-link" href="<?php echo esc_url($courses_link); ?>"><?php esc_html_e('View courses', 'sikshya'); ?></a>
                                </span>
                            </div>
                        <?php else : ?>
                            <div class="sikshya-setup__sample-result sikshya-setup__sample-result--warn" role="status">
                                <span class="sikshya-setup__sample-result-icon" aria-hidden="true">
                                    <svg viewBox="0 0 16 16" width="14" height="14"><path d="M8 3.5v5M8 11.5v.5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                                </span>
                                <span class="sikshya-setup__sample-result-body">
                                    <strong><?php esc_html_e('Sample data was not added.', 'sikshya'); ?></strong>
                                    <?php
                                    $msg = isset($sample_import['message']) ? (string) $sample_import['message'] : '';
                                    if ($msg !== '') {
                                        echo ' ' . esc_html($msg);
                                    }
                                    ?>
                                </span>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>

                    <div class="sikshya-setup__celebrate-actions">
                        <a class="sikshya-setup__btn sikshya-setup__btn--primary sikshya-setup__cta" href="<?php echo esc_url(admin_url('admin.php?page=sikshya&view=courses')); ?>">
                            <?php esc_html_e('Create your first course', 'sikshya'); ?>
                        </a>
                        <a class="sikshya-setup__btn" href="<?php echo esc_url(admin_url('admin.php?page=sikshya')); ?>">
                            <?php esc_html_e('Open dashboard', 'sikshya'); ?>
                        </a>
                    </div>
                </div>

                <div class="sikshya-setup__celebrate-grid">
                    <div class="sikshya-setup__card">
                        <h3><?php esc_html_e('Try this next', 'sikshya'); ?></h3>
                        <ol class="sikshya-setup__list sikshya-setup__list--loose">
                            <li><?php esc_html_e('Create a course and add one lesson.', 'sikshya'); ?></li>
                            <li><?php esc_html_e('Open cart and checkout to confirm pages work.', 'sikshya'); ?></li>
                            <li><?php esc_html_e('Set payments, email and branding from Settings.', 'sikshya'); ?></li>
                        </ol>
                    </div>
                    <div class="sikshya-setup__card">
                        <h3><?php esc_html_e('Need to change anything?', 'sikshya'); ?></h3>
                        <p class="sikshya-setup__muted">
                            <?php esc_html_e('Open Settings anytime — you can always come back here from Sikshya → Tools.', 'sikshya'); ?>
                        </p>
                        <p class="sikshya-setup__celebrate-links">
                            <a class="sikshya-setup__inline-link" href="<?php echo esc_url(admin_url('admin.php?page=sikshya&view=settings')); ?>"><?php esc_html_e('Open Settings', 'sikshya'); ?></a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
    return;
}

$current_label = $steps_meta[$initial_step]['label'] ?? '';
$progress_pct = (int) round($initial_step / $total_steps * 100);
?>

<div class="sikshya-setup" data-sikshya-setup data-initial-step="<?php echo esc_attr((string) $initial_step); ?>">
    <div class="sikshya-setup__shell">
        <header class="sikshya-setup__header">
            <div class="sikshya-setup__brand">
                <div class="sikshya-setup__logo" aria-hidden="true">S</div>
                <div>
                    <div class="sikshya-setup__title"><?php esc_html_e('Sikshya setup', 'sikshya'); ?></div>
                    <div class="sikshya-setup__subtitle">
                        <?php esc_html_e('We save as you go. Use Back any time.', 'sikshya'); ?>
                    </div>
                </div>
            </div>
            <div class="sikshya-setup__meta">
                <span class="sikshya-setup__step-pill" aria-live="polite">
                    <?php
                    echo esc_html(sprintf(
                        /* translators: 1: current step number, 2: total step count, 3: current step label. */
                        __('Step %1$s of %2$s · %3$s', 'sikshya'),
                        (string) $initial_step,
                        (string) $total_steps,
                        $current_label
                    ));
                    ?>
                </span>
            </div>
        </header>

        <nav class="sikshya-setup__rail" aria-label="<?php esc_attr_e('Setup steps', 'sikshya'); ?>">
            <?php foreach ($steps_meta as $n => $meta) :
                $is_current = $n === $initial_step;
                $is_done = $n < $initial_step;
                $state_class = $is_done ? 'is-done' : ($is_current ? 'is-current' : 'is-upcoming');
                // Allow jumping back to completed/current step; future steps are not interactive.
                $url = esc_url(SetupWizardController::adminUrl((int) $n));
                $tag = ($is_done || $is_current) ? 'a' : 'span';
                ?>
                <<?php echo esc_attr($tag); ?>
                    class="sikshya-setup__rail-step <?php echo esc_attr($state_class); ?>"
                    <?php if ($tag === 'a') : ?>href="<?php echo $url; ?>"<?php endif; ?>
                    <?php if ($is_current) : ?>aria-current="step"<?php endif; ?>
                >
                    <span class="sikshya-setup__rail-dot" aria-hidden="true">
                        <?php if ($is_done) : ?>
                            <svg viewBox="0 0 16 16" width="12" height="12" aria-hidden="true">
                                <path d="M3.5 8.5l3 3 6-7" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        <?php else : ?>
                            <?php echo (int) $n; ?>
                        <?php endif; ?>
                    </span>
                    <span class="sikshya-setup__rail-label"><?php echo esc_html($meta['label']); ?></span>
                </<?php echo esc_attr($tag); ?>>
            <?php endforeach; ?>
        </nav>

        <div class="sikshya-setup__progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?php echo esc_attr((string) $progress_pct); ?>" aria-label="<?php esc_attr_e('Setup progress', 'sikshya'); ?>">
            <div class="sikshya-setup__progress-bar" data-setup-progress style="width: <?php echo esc_attr((string) $progress_pct); ?>%;"></div>
        </div>

        <noscript>
            <div class="sikshya-setup__notice sikshya-setup__notice--error" role="alert">
                <?php esc_html_e('JavaScript is needed for the guided buttons. Use the Finish button on the last step to save.', 'sikshya'); ?>
            </div>
        </noscript>

        <?php if ($errors !== []) : ?>
            <div class="sikshya-setup__notice sikshya-setup__notice--error" role="alert" data-setup-error>
                <strong><?php esc_html_e('Please fix the following:', 'sikshya'); ?></strong>
                <ul>
                    <?php foreach ($errors as $e) : ?>
                        <li><?php echo esc_html($e); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <p class="sikshya-setup__toast" data-setup-toast hidden role="status"></p>

        <form class="sikshya-setup__form" method="post" action="<?php echo $action_url; ?>" data-setup-form novalidate>
            <?php wp_nonce_field('sikshya_setup_wizard', 'sikshya_setup_nonce'); ?>
            <input type="hidden" name="return_step" value="<?php echo esc_attr((string) $initial_step); ?>" />

            <?php /* ----- Step 1: Welcome ----- */ ?>
            <section class="sikshya-setup__step" data-setup-step="1"<?php echo $initial_step === 1 ? '' : ' hidden'; ?>>
                <div class="sikshya-setup__hero">
                    <h2 class="sikshya-setup__h2"><?php esc_html_e('Welcome to Sikshya', 'sikshya'); ?></h2>
                    <p class="sikshya-setup__lead">
                        <?php esc_html_e('A quick guided setup to get your course site ready. It takes about a minute and you can change everything later.', 'sikshya'); ?>
                    </p>
                </div>

                <div class="sikshya-setup__feature-row">
                    <div class="sikshya-setup__feature">
                        <div class="sikshya-setup__feature-ico" aria-hidden="true">
                            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7h16M4 12h16M4 17h10"/></svg>
                        </div>
                        <div>
                            <div class="sikshya-setup__feature-title"><?php esc_html_e('Page addresses', 'sikshya'); ?></div>
                            <div class="sikshya-setup__feature-text"><?php esc_html_e('Cart, checkout, account, learn.', 'sikshya'); ?></div>
                        </div>
                    </div>
                    <div class="sikshya-setup__feature">
                        <div class="sikshya-setup__feature-ico" aria-hidden="true">
                            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v10M9 9.5h5a2 2 0 010 4H10a2 2 0 000 4h5"/></svg>
                        </div>
                        <div>
                            <div class="sikshya-setup__feature-title"><?php esc_html_e('Currency', 'sikshya'); ?></div>
                            <div class="sikshya-setup__feature-text"><?php esc_html_e('How prices appear to students.', 'sikshya'); ?></div>
                        </div>
                    </div>
                    <div class="sikshya-setup__feature">
                        <div class="sikshya-setup__feature-ico" aria-hidden="true">
                            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 6h16v12H4z"/><path d="M4 10h16"/></svg>
                        </div>
                        <div>
                            <div class="sikshya-setup__feature-title"><?php esc_html_e('Lesson links', 'sikshya'); ?></div>
                            <div class="sikshya-setup__feature-text"><?php esc_html_e('How lesson URLs are formatted.', 'sikshya'); ?></div>
                        </div>
                    </div>
                </div>

                <label class="sikshya-setup__consent">
                    <input
                        type="checkbox"
                        name="allow_usage_tracking"
                        value="1"
                        <?php
                        // Default ON for new installs; respect existing preference otherwise.
                        $cur = Settings::get('allow_usage_tracking', null);
                        $default_checked = ($cur === null) ? true : Settings::isTruthy($cur);
                        checked($default_checked, true);
                        ?>
                    />
                    <span class="sikshya-setup__consent-body">
                        <span class="sikshya-setup__consent-title"><?php esc_html_e('Help improve Sikshya by sharing anonymous usage data', 'sikshya'); ?></span>
                        <span class="sikshya-setup__consent-text"><?php esc_html_e('Only environment and feature counts — never student names, emails, or order details. Change anytime in Settings.', 'sikshya'); ?></span>
                    </span>
                </label>
            </section>

            <?php /* ----- Step 2: Pages ----- */ ?>
            <section class="sikshya-setup__step" data-setup-step="2"<?php echo $initial_step === 2 ? '' : ' hidden'; ?>>
                <h2 class="sikshya-setup__h2"><?php esc_html_e('Page addresses', 'sikshya'); ?></h2>
                <p class="sikshya-setup__lead">
                    <?php esc_html_e('These short words become parts of your store URLs. Defaults are fine for most sites.', 'sikshya'); ?>
                </p>

                <div class="sikshya-setup__example">
                    <span class="sikshya-setup__example-label"><?php esc_html_e('Example', 'sikshya'); ?></span>
                    <code class="sikshya-setup__example-code"><?php echo esc_html(PermalinkService::virtualPageUrl('cart')); ?></code>
                </div>

                <?php if ($plain) : ?>
                    <p class="sikshya-setup__hint sikshya-setup__hint--tight">
                        <?php esc_html_e('Your site uses “Plain” WordPress permalinks, so the address may look slightly different — that is normal.', 'sikshya'); ?>
                    </p>
                <?php endif; ?>

                <div class="sikshya-setup__fields">
                    <div class="sikshya-setup__field">
                        <label for="permalink_cart"><?php esc_html_e('Cart', 'sikshya'); ?></label>
                        <input id="permalink_cart" name="permalink_cart" type="text" value="<?php echo esc_attr($permalinks['permalink_cart'] ?? 'cart'); ?>" autocomplete="off" />
                    </div>
                    <div class="sikshya-setup__field">
                        <label for="permalink_checkout"><?php esc_html_e('Checkout', 'sikshya'); ?></label>
                        <input id="permalink_checkout" name="permalink_checkout" type="text" value="<?php echo esc_attr($permalinks['permalink_checkout'] ?? 'checkout'); ?>" autocomplete="off" />
                    </div>
                    <div class="sikshya-setup__field">
                        <label for="permalink_account"><?php esc_html_e('Student account', 'sikshya'); ?></label>
                        <input id="permalink_account" name="permalink_account" type="text" value="<?php echo esc_attr($permalinks['permalink_account'] ?? 'account'); ?>" autocomplete="off" />
                    </div>
                    <div class="sikshya-setup__field">
                        <label for="permalink_learn"><?php esc_html_e('Learning area', 'sikshya'); ?></label>
                        <input id="permalink_learn" name="permalink_learn" type="text" value="<?php echo esc_attr($permalinks['permalink_learn'] ?? 'learn'); ?>" autocomplete="off" />
                    </div>
                    <div class="sikshya-setup__field sikshya-setup__field--full">
                        <label for="permalink_order"><?php esc_html_e('Order receipt', 'sikshya'); ?></label>
                        <input id="permalink_order" name="permalink_order" type="text" value="<?php echo esc_attr($permalinks['permalink_order'] ?? 'order'); ?>" autocomplete="off" />
                    </div>
                </div>
            </section>

            <?php /* ----- Step 3: Currency ----- */ ?>
            <section class="sikshya-setup__step" data-setup-step="3"<?php echo $initial_step === 3 ? '' : ' hidden'; ?>>
                <h2 class="sikshya-setup__h2"><?php esc_html_e('Currency', 'sikshya'); ?></h2>
                <p class="sikshya-setup__lead">
                    <?php esc_html_e('Pick the currency students see. The example below updates after you save and re-open this step.', 'sikshya'); ?>
                </p>

                <div class="sikshya-setup__fields">
                    <div class="sikshya-setup__field">
                        <label for="currency"><?php esc_html_e('Currency', 'sikshya'); ?></label>
                        <select id="currency" name="currency">
                            <?php
                            $currency_choices = function_exists('sikshya_get_currency_choices')
                                ? sikshya_get_currency_choices()
                                : ['USD' => 'United States dollar ($)'];
                            $current_currency = strtoupper($currency);
                            foreach ($currency_choices as $code => $label) {
                                printf(
                                    '<option value="%s"%s>%s</option>',
                                    esc_attr($code),
                                    selected($current_currency, $code, false),
                                    esc_html($label)
                                );
                            }
                            ?>
                        </select>
                    </div>
                    <div class="sikshya-setup__field">
                        <label for="currency_position"><?php esc_html_e('Symbol position', 'sikshya'); ?></label>
                        <select id="currency_position" name="currency_position">
                            <option value="left" <?php selected($currency_position, 'left'); ?>><?php esc_html_e('Left ($99.99)', 'sikshya'); ?></option>
                            <option value="right" <?php selected($currency_position, 'right'); ?>><?php esc_html_e('Right (99.99$)', 'sikshya'); ?></option>
                            <option value="left_space" <?php selected($currency_position, 'left_space'); ?>><?php esc_html_e('Left with space ($ 99.99)', 'sikshya'); ?></option>
                            <option value="right_space" <?php selected($currency_position, 'right_space'); ?>><?php esc_html_e('Right with space (99.99 $)', 'sikshya'); ?></option>
                        </select>
                    </div>
                </div>

                <div class="sikshya-setup__example sikshya-setup__example--row">
                    <span class="sikshya-setup__example-label"><?php esc_html_e('Preview', 'sikshya'); ?></span>
                    <code class="sikshya-setup__example-code"><?php echo esc_html(function_exists('sikshya_format_price_plain') ? sikshya_format_price_plain(1234.5, $currency) : ''); ?></code>
                </div>

                <details class="sikshya-setup__details">
                    <summary><?php esc_html_e('Advanced number formatting', 'sikshya'); ?></summary>
                    <div class="sikshya-setup__fields sikshya-setup__fields--inset">
                        <div class="sikshya-setup__field">
                            <label for="currency_decimal_places"><?php esc_html_e('Decimals', 'sikshya'); ?></label>
                            <select id="currency_decimal_places" name="currency_decimal_places">
                                <option value="0" <?php selected((string) $currency_decimal_places, '0'); ?>><?php esc_html_e('0 (whole numbers)', 'sikshya'); ?></option>
                                <option value="2" <?php selected((string) $currency_decimal_places, '2'); ?>><?php esc_html_e('2 (e.g. 99.99)', 'sikshya'); ?></option>
                                <option value="3" <?php selected((string) $currency_decimal_places, '3'); ?>><?php esc_html_e('3 (e.g. 99.999)', 'sikshya'); ?></option>
                            </select>
                        </div>
                        <div class="sikshya-setup__field">
                            <label for="currency_thousand_separator"><?php esc_html_e('Thousands separator', 'sikshya'); ?></label>
                            <input id="currency_thousand_separator" name="currency_thousand_separator" type="text" value="<?php echo esc_attr($currency_thousand_separator); ?>" />
                        </div>
                        <div class="sikshya-setup__field">
                            <label for="currency_decimal_separator"><?php esc_html_e('Decimal separator', 'sikshya'); ?></label>
                            <input id="currency_decimal_separator" name="currency_decimal_separator" type="text" value="<?php echo esc_attr($currency_decimal_separator); ?>" />
                        </div>
                    </div>
                </details>
            </section>

            <?php /* ----- Step 4: Lesson links ----- */ ?>
            <section class="sikshya-setup__step" data-setup-step="4"<?php echo $initial_step === 4 ? '' : ' hidden'; ?>>
                <h2 class="sikshya-setup__h2"><?php esc_html_e('Lesson links', 'sikshya'); ?></h2>
                <p class="sikshya-setup__lead">
                    <?php esc_html_e('Choose how lesson URLs look in the learning area. The recommended option is safer if you ever rename a lesson.', 'sikshya'); ?>
                </p>

                <div class="sikshya-setup__choice">
                    <label class="sikshya-setup__choice-card">
                        <input type="radio" name="learn_permalink_use_public_id" value="1" <?php checked($learn_use_public_id, true); ?> />
                        <span class="sikshya-setup__choice-body">
                            <span class="sikshya-setup__choice-head">
                                <span class="sikshya-setup__choice-title"><?php esc_html_e('Stable (recommended)', 'sikshya'); ?></span>
                                <span class="sikshya-setup__choice-tag"><?php esc_html_e('Best for most sites', 'sikshya'); ?></span>
                            </span>
                            <span class="sikshya-setup__choice-text"><?php esc_html_e('Includes the lesson ID, so renaming is safe.', 'sikshya'); ?></span>
                            <span class="sikshya-setup__mono">/learn/lesson/12/my-lesson</span>
                        </span>
                    </label>
                    <label class="sikshya-setup__choice-card">
                        <input type="radio" name="learn_permalink_use_public_id" value="0" <?php checked($learn_use_public_id, false); ?> />
                        <span class="sikshya-setup__choice-body">
                            <span class="sikshya-setup__choice-head">
                                <span class="sikshya-setup__choice-title"><?php esc_html_e('Shorter URL', 'sikshya'); ?></span>
                            </span>
                            <span class="sikshya-setup__choice-text"><?php esc_html_e('Cleaner address, but be careful when renaming lessons.', 'sikshya'); ?></span>
                            <span class="sikshya-setup__mono">/learn/lesson/my-lesson</span>
                        </span>
                    </label>
                </div>
            </section>

            <?php /* ----- Step 5: Finish ----- */ ?>
            <section class="sikshya-setup__step" data-setup-step="5"<?php echo $initial_step === 5 ? '' : ' hidden'; ?>>
                <h2 class="sikshya-setup__h2"><?php esc_html_e('Ready to go', 'sikshya'); ?></h2>
                <p class="sikshya-setup__lead">
                    <?php esc_html_e('That’s it — press Finish to save the last step and open your dashboard.', 'sikshya'); ?>
                </p>

                <div class="sikshya-setup__summary">
                    <div class="sikshya-setup__summary-item">
                        <span class="sikshya-setup__summary-label"><?php esc_html_e('Currency', 'sikshya'); ?></span>
                        <span class="sikshya-setup__summary-value"><?php echo esc_html($currency); ?></span>
                    </div>
                    <div class="sikshya-setup__summary-item">
                        <span class="sikshya-setup__summary-label"><?php esc_html_e('Cart', 'sikshya'); ?></span>
                        <span class="sikshya-setup__summary-value">/<?php echo esc_html($permalinks['permalink_cart'] ?? 'cart'); ?></span>
                    </div>
                    <div class="sikshya-setup__summary-item">
                        <span class="sikshya-setup__summary-label"><?php esc_html_e('Checkout', 'sikshya'); ?></span>
                        <span class="sikshya-setup__summary-value">/<?php echo esc_html($permalinks['permalink_checkout'] ?? 'checkout'); ?></span>
                    </div>
                    <div class="sikshya-setup__summary-item">
                        <span class="sikshya-setup__summary-label"><?php esc_html_e('Lesson URL', 'sikshya'); ?></span>
                        <span class="sikshya-setup__summary-value"><?php echo $learn_use_public_id ? esc_html__('Stable (recommended)', 'sikshya') : esc_html__('Shorter URL', 'sikshya'); ?></span>
                    </div>
                </div>

                <div class="sikshya-setup__sample sikshya-setup__sample--action" data-setup-sample>
                    <div class="sikshya-setup__sample-body">
                        <div class="sikshya-setup__sample-head">
                            <span class="sikshya-setup__sample-title">
                                <?php esc_html_e('Add a sample course (optional)', 'sikshya'); ?>
                            </span>
                            <span class="sikshya-setup__sample-tag">
                                <?php esc_html_e('Great for first-time setup', 'sikshya'); ?>
                            </span>
                        </div>
                        <p class="sikshya-setup__sample-text">
                            <?php esc_html_e('Creates one demo course with chapters, lessons, a quiz, and an assignment so you can click around immediately. You can delete it anytime from Courses.', 'sikshya'); ?>
                        </p>
                        <div class="sikshya-setup__sample-row">
                            <button
                                type="button"
                                class="sikshya-setup__btn sikshya-setup__btn--secondary sikshya-setup__sample-btn"
                                data-setup-sample-import
                            >
                                <span class="sikshya-setup__sample-btn-icon" aria-hidden="true">
                                    <svg viewBox="0 0 16 16" width="14" height="14">
                                        <path d="M8 3v8m0 0 3-3m-3 3-3-3M3 13h10" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                </span>
                                <span data-setup-sample-label><?php esc_html_e('Add sample course', 'sikshya'); ?></span>
                            </button>
                            <span class="sikshya-setup__sample-helper" data-setup-sample-helper>
                                <?php esc_html_e('Takes a few seconds. You can keep clicking Finish setup either way.', 'sikshya'); ?>
                            </span>
                        </div>
                        <div class="sikshya-setup__sample-status" data-setup-sample-status hidden role="status" aria-live="polite"></div>
                    </div>
                </div>

                <p class="sikshya-setup__tip">
                    <?php esc_html_e('Tip: enable Offline payment first to test checkout without API keys.', 'sikshya'); ?>
                </p>
            </section>

            <footer class="sikshya-setup__actions">
                <a class="sikshya-setup__btn sikshya-setup__btn--ghost" data-setup-back href="<?php echo esc_url(SetupWizardController::adminUrl(max(1, $initial_step - 1))); ?>"<?php echo $initial_step <= 1 ? ' hidden' : ''; ?>>
                    <?php esc_html_e('Back', 'sikshya'); ?>
                </a>

                <?php /* Skip-all only on Welcome (skips entire wizard, marks setup complete). */ ?>
                <?php if ($initial_step === 1) : ?>
                    <button
                        type="submit"
                        class="sikshya-setup__btn sikshya-setup__btn--ghost sikshya-setup__skip"
                        name="wizard_action"
                        value="skip"
                        data-setup-skip-all
                    >
                        <?php esc_html_e('Skip setup', 'sikshya'); ?>
                    </button>
                <?php endif; ?>

                <?php /* Skip-this on Pages, Currency, Lessons (no skip on Welcome or Finish). */ ?>
                <?php if ($initial_step >= 2 && $initial_step <= 4) : ?>
                    <a
                        class="sikshya-setup__btn sikshya-setup__btn--ghost sikshya-setup__skip"
                        href="<?php echo esc_url(SetupWizardController::adminUrl($initial_step + 1)); ?>"
                        data-setup-skip-step
                    >
                        <?php esc_html_e('Skip this step', 'sikshya'); ?>
                    </a>
                <?php endif; ?>

                <span class="sikshya-setup__actions-spacer"></span>

                <button type="button" class="sikshya-setup__btn sikshya-setup__btn--primary" data-setup-next<?php echo $initial_step >= 5 ? ' hidden' : ''; ?>>
                    <?php esc_html_e('Continue', 'sikshya'); ?>
                </button>
                <button type="submit" class="sikshya-setup__btn sikshya-setup__btn--primary" name="wizard_action" value="save" data-setup-finish<?php echo $initial_step < 5 ? ' hidden' : ''; ?>>
                    <?php esc_html_e('Finish setup', 'sikshya'); ?>
                </button>
            </footer>
        </form>
    </div>
</div>
