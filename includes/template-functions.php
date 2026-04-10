<?php
/**
 * Theme-facing template helpers for Sikshya LMS (catalog, cards, pricing).
 *
 * @package Sikshya
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * First non-empty post meta value from a list of keys (legacy + builder keys).
 *
 * @param int   $post_id Post ID.
 * @param array $keys    Meta keys to try in order.
 * @return mixed|string
 */
function sikshya_first_nonempty_post_meta(int $post_id, array $keys)
{
    foreach ($keys as $key) {
        $v = get_post_meta($post_id, $key, true);
        if ($v !== '' && $v !== null && false !== $v) {
            return $v;
        }
    }

    return '';
}

/**
 * Normalize ISO currency code for display.
 *
 * @param string $code Raw code.
 * @return string
 */
function sikshya_normalize_currency_code(string $code): string
{
    $code = strtoupper(sanitize_text_field($code));
    if ($code === 'OTHER') {
        return 'OTHER';
    }
    $code = substr($code, 0, 3);

    return strlen($code) === 3 ? $code : 'USD';
}

/**
 * Symbol / prefix for common currencies (fallback when WooCommerce is not used).
 *
 * @param string $code ISO 4217 code.
 * @return string
 */
function sikshya_get_currency_symbol(string $code): string
{
    $map = [
        'USD' => '$',
        'EUR' => '€',
        'GBP' => '£',
        'INR' => '₹',
        'AUD' => 'A$',
        'CAD' => 'C$',
        'JPY' => '¥',
        'BRL' => 'R$',
        'MXN' => 'MX$',
        'SGD' => 'S$',
        'NPR' => 'रू',
        'OTHER' => '',
    ];

    return isset($map[$code]) ? $map[$code] : $code . ' ';
}

/**
 * Store currency code from Sikshya settings (Payment tab — stored as `_sikshya_currency`).
 *
 * @return string ISO code.
 */
function sikshya_get_store_currency_code(): string
{
    $raw = get_option('_sikshya_currency', 'USD');

    return sikshya_normalize_currency_code((string) $raw);
}

/**
 * Format amount with Sikshya currency settings (separators, position, decimals).
 *
 * @param float  $amount   Amount.
 * @param string $currency ISO code (for symbol).
 * @return string HTML-safe fragment (caller may wp_kses_post).
 */
function sikshya_format_price_plain(float $amount, string $currency = 'USD'): string
{
    $currency = sikshya_normalize_currency_code($currency);
    $decimals = (int) get_option('_sikshya_currency_decimal_places', 2);
    if ($decimals < 0) {
        $decimals = 2;
    }

    $thousand = (string) get_option('_sikshya_currency_thousand_separator', ',');
    $decimal = (string) get_option('_sikshya_currency_decimal_separator', '.');
    $formatted = number_format($amount, $decimals, $decimal, $thousand);
    $symbol = sikshya_get_currency_symbol($currency);
    $position = (string) get_option('_sikshya_currency_position', 'left');

    if ($currency === 'OTHER' || $symbol === '') {
        return trim($currency . ' ' . $formatted);
    }

    switch ($position) {
        case 'right':
            return $formatted . $symbol;
        case 'left_space':
            return $symbol . ' ' . $formatted;
        case 'right_space':
            return $formatted . ' ' . $symbol;
        case 'left':
        default:
            return $symbol . $formatted;
    }
}

/**
 * Format a price for display. Currency always comes from global Sikshya settings unless $currency_code is passed explicitly.
 *
 * @param float|string      $amount         Raw amount.
 * @param string|null       $currency_code  ISO code override, or null for store default.
 * @param int|null          $course_id      Unused; kept for backward compatibility.
 * @return string           HTML (may include WooCommerce price HTML).
 */
function sikshya_format_price($amount, ?string $currency_code = null, ?int $course_id = null): string
{
    unset($course_id);
    $amount = is_numeric($amount) ? (float) $amount : 0.0;

    $code = $currency_code
        ? sikshya_normalize_currency_code($currency_code)
        : sikshya_get_store_currency_code();

    $wc_currency = function_exists('get_woocommerce_currency') ? strtoupper((string) get_woocommerce_currency()) : '';

    if (function_exists('wc_price') && ($wc_currency === '' || $code === $wc_currency)) {
        return wp_kses_post(wc_price($amount));
    }

    return wp_kses_post(sikshya_format_price_plain($amount, $code));
}

/**
 * Resolved course pricing from all known meta key variants (builder, legacy, CPT box).
 *
 * @param int $course_id Course post ID.
 * @return array{price:?float,sale_price:?float,currency:string,effective:?float,on_sale:bool}
 */
function sikshya_get_course_pricing(int $course_id): array
{
    $price_raw = sikshya_first_nonempty_post_meta(
        $course_id,
        ['_sikshya_price', '_sikshya_course_price', 'sikshya_course_price']
    );
    $sale_raw = sikshya_first_nonempty_post_meta(
        $course_id,
        ['_sikshya_sale_price', '_sikshya_course_sale_price', 'sikshya_course_sale_price']
    );

    $currency = sikshya_get_store_currency_code();

    $price = is_numeric($price_raw) ? (float) $price_raw : null;
    $sale = is_numeric($sale_raw) ? (float) $sale_raw : null;

    $on_sale = null !== $price && null !== $sale && $sale < $price && $sale >= 0;
    $effective = $on_sale ? $sale : $price;

    return [
        'price' => $price,
        'sale_price' => $sale,
        'currency' => $currency,
        'effective' => $effective,
        'on_sale' => $on_sale,
    ];
}

/**
 * Echo a course card (used on catalog, featured, and popular sections).
 *
 * @param \WP_Post $course Course post object.
 * @param string   $type   Visual variant: default, featured, popular.
 * @return void
 */
function sikshya_render_course_card(\WP_Post $course, string $type = 'default'): void
{
    $course_id = (int) $course->ID;
    $p = sikshya_get_course_pricing($course_id);

    $course_duration = sikshya_first_nonempty_post_meta($course_id, ['_sikshya_duration', '_sikshya_course_duration', 'sikshya_course_duration']);
    $course_difficulty = sikshya_first_nonempty_post_meta($course_id, ['_sikshya_difficulty', '_sikshya_course_difficulty', 'sikshya_course_level']);
    $course_instructor = get_userdata((int) $course->post_author);
    $course_thumbnail = get_the_post_thumbnail_url($course_id, 'medium');
    $course_categories = get_the_terms($course_id, \Sikshya\Constants\Taxonomies::COURSE_CATEGORY);

    $price_num = $p['price'];
    $sale_num = $p['sale_price'];
    $on_sale = $p['on_sale'];
    $currency = $p['currency'];

    $card_label = sprintf(
        /* translators: %s: course title */
        __('Course: %s', 'sikshya'),
        $course->post_title
    );
    ?>
    <article class="sikshya-course-card sikshya-course-card--<?php echo esc_attr($type); ?>" aria-label="<?php echo esc_attr($card_label); ?>">
        <div class="sikshya-course-card-image">
            <?php if ($course_thumbnail) : ?>
                <a href="<?php echo esc_url(get_permalink($course_id)); ?>" class="sikshya-course-card-image-link" tabindex="-1" aria-hidden="true">
                    <img src="<?php echo esc_url($course_thumbnail); ?>" alt="" class="sikshya-course-thumbnail" loading="lazy" width="400" height="225">
                </a>
            <?php else : ?>
                <div class="sikshya-course-placeholder" aria-hidden="true">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" focusable="false">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                        <polyline points="14,2 14,8 20,8"></polyline>
                    </svg>
                </div>
            <?php endif; ?>

            <?php if ($on_sale) : ?>
                <div class="sikshya-course-badge sikshya-course-badge--sale">
                    <?php esc_html_e('Sale', 'sikshya'); ?>
                </div>
            <?php endif; ?>

            <?php if (get_post_meta($course_id, '_sikshya_featured', true)) : ?>
                <div class="sikshya-course-badge sikshya-course-badge--featured">
                    <?php esc_html_e('Featured', 'sikshya'); ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="sikshya-course-card-content">
            <?php if ($course_categories && !is_wp_error($course_categories)) : ?>
                <div class="sikshya-course-categories">
                    <?php foreach (array_slice($course_categories, 0, 2) as $category) : ?>
                        <span class="sikshya-course-category"><?php echo esc_html($category->name); ?></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <h3 class="sikshya-course-title">
                <a href="<?php echo esc_url(get_permalink($course_id)); ?>">
                    <?php echo esc_html($course->post_title); ?>
                </a>
            </h3>

            <p class="sikshya-course-excerpt">
                <?php echo esc_html(wp_trim_words($course->post_excerpt ?: $course->post_content, 15)); ?>
            </p>

            <div class="sikshya-course-meta">
                <?php if ($course_instructor) : ?>
                    <div class="sikshya-course-instructor">
                        <span class="sikshya-course-instructor-label"><?php esc_html_e('By', 'sikshya'); ?></span>
                        <span class="sikshya-course-instructor-name"><?php echo esc_html($course_instructor->display_name); ?></span>
                    </div>
                <?php endif; ?>

                <?php if ($course_duration) : ?>
                    <div class="sikshya-course-duration">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false">
                            <circle cx="12" cy="12" r="10"></circle>
                            <polyline points="12,6 12,12 16,14"></polyline>
                        </svg>
                        <span><?php echo esc_html($course_duration); ?></span>
                    </div>
                <?php endif; ?>

                <?php if ($course_difficulty) : ?>
                    <div class="sikshya-course-difficulty">
                        <span class="sikshya-difficulty-badge sikshya-difficulty-badge--<?php echo esc_attr($course_difficulty); ?>">
                            <?php echo esc_html(ucfirst((string) $course_difficulty)); ?>
                        </span>
                    </div>
                <?php endif; ?>
            </div>

            <div class="sikshya-course-footer">
                <div class="sikshya-course-price">
                    <?php if ($on_sale && null !== $price_num && null !== $sale_num) : ?>
                        <span class="sikshya-course-price-original" aria-hidden="true"><?php echo wp_kses_post(sikshya_format_price($price_num, $currency)); ?></span>
                        <span class="sikshya-course-price-current"><?php echo wp_kses_post(sikshya_format_price($sale_num, $currency)); ?></span>
                    <?php elseif (null !== $price_num && $price_num > 0) : ?>
                        <span class="sikshya-course-price-current"><?php echo wp_kses_post(sikshya_format_price($price_num, $currency)); ?></span>
                    <?php else : ?>
                        <span class="sikshya-course-price-free"><?php esc_html_e('Free', 'sikshya'); ?></span>
                    <?php endif; ?>
                </div>

                <a href="<?php echo esc_url(get_permalink($course_id)); ?>" class="sikshya-button sikshya-button--small">
                    <?php esc_html_e('View course', 'sikshya'); ?>
                </a>
            </div>
        </div>
    </article>
    <?php
}

/**
 * Cart helpers delegate to {@see \Sikshya\Frontend\Public\CartStorage} (logic not duplicated in templates).
 */
function sikshya_cart_cookie_name(): string
{
    return \Sikshya\Frontend\Public\CartStorage::cookieName();
}

/**
 * @return array<int, int> Unique course IDs in cart.
 */
function sikshya_cart_get_course_ids(): array
{
    return \Sikshya\Frontend\Public\CartStorage::getCourseIds();
}

/**
 * @param array<int, int> $ids
 */
function sikshya_cart_set_guest_ids(array $ids): void
{
    \Sikshya\Frontend\Public\CartStorage::setGuestIds($ids);
}

/**
 * @param array<int, int> $ids
 */
function sikshya_cart_set_user_ids(array $ids): void
{
    \Sikshya\Frontend\Public\CartStorage::setIds($ids);
}

/**
 * @return bool True if cart changed.
 */
function sikshya_cart_add_course(int $course_id): bool
{
    return \Sikshya\Frontend\Public\CartStorage::addCourse($course_id);
}

/**
 * @return bool True if cart changed.
 */
function sikshya_cart_remove_course(int $course_id): bool
{
    return \Sikshya\Frontend\Public\CartStorage::removeCourse($course_id);
}

function sikshya_cart_clear(): void
{
    \Sikshya\Frontend\Public\CartStorage::clear();
}

/**
 * Permalink for a Sikshya frontend page (cart, checkout, …).
 */
function sikshya_frontend_page_url(string $key): string
{
    return \Sikshya\Frontend\Public\PublicPageUrls::url($key);
}

/**
 * Whether the current user is enrolled (DB-backed).
 */
function sikshya_is_user_enrolled_in_course(int $course_id, int $user_id = 0): bool
{
    $user_id = $user_id ?: get_current_user_id();
    if ($user_id <= 0 || $course_id <= 0) {
        return false;
    }
    $repo = new \Sikshya\Database\Repositories\EnrollmentRepository();

    return $repo->findByUserAndCourse($user_id, $course_id) !== null;
}

/**
 * Chapters and linked content for the learn view.
 *
 * @return array<int, array{chapter: \WP_Post, contents: array<int, \WP_Post>}>
 */
function sikshya_get_course_curriculum_public(int $course_id): array
{
    return \Sikshya\Services\PublicCurriculumService::getCourseCurriculum($course_id);
}

/**
 * Learner-facing label for curriculum line items (lessons, quizzes, assignments).
 */
function sikshya_public_content_type_label(string $post_type): string
{
    switch ($post_type) {
        case 'sik_lesson':
            return __('Lesson', 'sikshya');
        case 'sik_quiz':
            return __('Quiz', 'sikshya');
        case 'sik_assignment':
            return __('Assignment', 'sikshya');
        default:
            return __('Content', 'sikshya');
    }
}

/**
 * Inline SVG icon for curriculum line items (lesson, quiz, assignment, other).
 *
 * Markup is fixed paths only; safe to print in templates.
 *
 * @param string $post_type Post type slug (e.g. sik_lesson).
 * @return string SVG element HTML.
 */
function sikshya_public_content_type_icon_html(string $post_type): string
{
    $attrs = 'class="sikshya-course-lp__type-svg" width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false"';

    switch ($post_type) {
        case 'sik_lesson':
            return '<svg ' . $attrs . '><path d="M8 5v14l11-7L8 5z" fill="currentColor"/></svg>';

        case 'sik_quiz':
            return '<svg ' . $attrs . ' stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="5" y="3" width="14" height="18" rx="2" fill="none"/><path d="M8 9h8M8 13h6M8 17h4" fill="none"/></svg>';

        case 'sik_assignment':
            return '<svg ' . $attrs . ' stroke="currentColor" stroke-width="2" stroke-linejoin="round" fill="none"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8l-6-6z"/><path d="M14 2v6h6"/></svg>';

        default:
            return '<svg ' . $attrs . ' stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9" fill="none"/><circle cx="12" cy="12" r="2.5" fill="currentColor" stroke="none"/></svg>';
    }
}
