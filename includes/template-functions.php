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
 * Symbol / prefix for common currencies.
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
 * Render a reusable template partial from the plugin.
 *
 * @param string $relative Relative path inside plugin root (e.g. 'templates/partials/course-card.php').
 * @param array  $vars     Variables to extract into template scope.
 */
function sikshya_render_template_partial(string $relative, array $vars = []): void
{
    $path = dirname(__DIR__) . '/' . ltrim($relative, '/');
    if (!is_readable($path)) {
        return;
    }
    if ($vars !== []) {
        extract($vars, EXTR_SKIP);
    }
    include $path;
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

    sikshya_render_template_partial('templates/partials/course-card.php', [
        'course' => $course,
        'type' => $type,
        'pricing' => $p,
        'course_duration' => $course_duration,
        'course_difficulty' => $course_difficulty,
        'course_instructor' => $course_instructor,
        'course_thumbnail' => $course_thumbnail,
        'course_categories' => $course_categories,
        'curriculum_counts' => sikshya_get_course_curriculum_counts($course_id),
    ]);
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
 * When > 0, checkout should use the Pro bundle price if the cart still matches that bundle’s courses.
 */
function sikshya_cart_get_bundle_id(): int
{
    return \Sikshya\Frontend\Public\CartStorage::getBundleId();
}

/**
 * @param array<int, int> $course_ids Course IDs included in the bundle (same as admin-defined bundle).
 */
function sikshya_cart_set_bundle(array $course_ids, int $bundle_id): void
{
    \Sikshya\Frontend\Public\CartStorage::setBundleCart($course_ids, $bundle_id);
}

/**
 * Permalink for a Sikshya frontend page (cart, checkout, …).
 */
function sikshya_frontend_page_url(string $key): string
{
    return \Sikshya\Frontend\Public\PublicPageUrls::url($key);
}

/**
 * Learn / player entry URL for a course (catalog cards, enrolled CTA).
 *
 * @param int $course_id Course post ID.
 */
function sikshya_course_learn_entry_url(int $course_id): string
{
    $course_id = (int) $course_id;
    if ($course_id <= 0) {
        return home_url('/');
    }
    if (class_exists(\Sikshya\Frontend\Public\PublicPageUrls::class)) {
        return \Sikshya\Frontend\Public\PublicPageUrls::learnForCourse($course_id);
    }

    $p = get_permalink($course_id);

    return is_string($p) && $p !== '' ? $p : home_url('/');
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
 * Curriculum counts for catalog cards (cached).
 *
 * @return array{lessons:int,quizzes:int,assignments:int,total:int}
 */
function sikshya_get_course_curriculum_counts(int $course_id): array
{
    $course_id = (int) $course_id;
    if ($course_id <= 0) {
        return ['lessons' => 0, 'quizzes' => 0, 'assignments' => 0, 'total' => 0];
    }

    $cache_key = 'sikshya_course_counts_' . $course_id . '_' . (string) get_post_modified_time('U', true, $course_id);
    $cached = get_transient($cache_key);
    if (is_array($cached)) {
        return array_merge(['lessons' => 0, 'quizzes' => 0, 'assignments' => 0, 'total' => 0], $cached);
    }

    $counts = ['lessons' => 0, 'quizzes' => 0, 'assignments' => 0, 'total' => 0];
    $blocks = sikshya_get_course_curriculum_public($course_id);
    foreach ($blocks as $b) {
        foreach ((array) ($b['contents'] ?? []) as $content_post) {
            if (!$content_post instanceof \WP_Post) {
                continue;
            }
            $pt = (string) $content_post->post_type;
            if ($pt === 'sik_lesson') {
                $counts['lessons']++;
            } elseif ($pt === 'sik_quiz') {
                $counts['quizzes']++;
            } elseif ($pt === 'sik_assignment') {
                $counts['assignments']++;
            }
            $counts['total']++;
        }
    }

    set_transient($cache_key, $counts, 6 * HOUR_IN_SECONDS);

    return $counts;
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

/**
 * Whether the site allows privileged users to enroll in paid courses without checkout.
 *
 * Requires option "allow_admin_enroll_without_purchase" and a user with {@see manage_options} or {@see manage_sikshya}.
 */
function sikshya_current_user_can_admin_enroll_without_purchase(): bool
{
    if (!is_user_logged_in()) {
        return false;
    }
    if (!class_exists('\Sikshya\Services\Settings')) {
        return false;
    }
    $on = \Sikshya\Services\Settings::get('allow_admin_enroll_without_purchase', '');
    if (!\Sikshya\Services\Settings::isTruthy($on)) {
        return false;
    }
    $can = current_user_can('manage_options') || current_user_can('manage_sikshya');

    /**
     * Filters whether the current user may use admin enrollment without purchase.
     *
     * @param bool $can Whether the user passes capability + setting checks.
     * @param int  $user_id Current user ID.
     */
    return (bool) apply_filters('sikshya_user_can_admin_enroll_without_purchase', $can, get_current_user_id());
}

/**
 * Enroll the current user in a paid course without payment (admin bypass). Does not run for free courses.
 *
 * @return int Enrollment ID on success, 0 on failure.
 */
function sikshya_enroll_paid_course_as_admin(int $course_id): int
{
    if ($course_id <= 0 || !is_user_logged_in()) {
        return 0;
    }
    if (!function_exists('sikshya_get_course_pricing') || !function_exists('sikshya_current_user_can_admin_enroll_without_purchase')) {
        return 0;
    }
    if (!sikshya_current_user_can_admin_enroll_without_purchase()) {
        return 0;
    }
    $p = sikshya_get_course_pricing($course_id);
    $paid = null !== $p['effective'] && (float) $p['effective'] > 0.00001;
    if (!$paid) {
        return 0;
    }
    if (!class_exists('\Sikshya\Core\Plugin')) {
        return 0;
    }
    $plugin = \Sikshya\Core\Plugin::getInstance();
    $courseService = $plugin->getService('course');
    if (!$courseService instanceof \Sikshya\Services\CourseService) {
        return 0;
    }
    $uid = get_current_user_id();
    try {
        $eid = (int) $courseService->enrollUser($uid, $course_id, [
            'payment_method' => 'admin_bypass',
            'amount' => 0,
            'transaction_id' => 'admin:' . $uid . ':' . time(),
            'notes' => sprintf(
                /* translators: %d: WordPress user ID */
                __('Administrator enrollment without purchase (user %d).', 'sikshya'),
                $uid
            ),
        ]);

        return $eid > 0 ? $eid : 0;
    } catch (\Exception $e) {
        return 0;
    }
}
