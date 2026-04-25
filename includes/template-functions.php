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
 * Full ISO 4217 currency map shared across Sikshya (settings + setup wizard).
 *
 * Returns ISO code => human-readable name. Filterable for add-ons to extend.
 *
 * @return array<string, string>
 */
function sikshya_get_currencies(): array
{
    static $currencies = null;
    if ($currencies !== null) {
        return $currencies;
    }

    $currencies = [
        'AED' => __('United Arab Emirates dirham', 'sikshya'),
        'AFN' => __('Afghan afghani', 'sikshya'),
        'ALL' => __('Albanian lek', 'sikshya'),
        'AMD' => __('Armenian dram', 'sikshya'),
        'ANG' => __('Netherlands Antillean guilder', 'sikshya'),
        'AOA' => __('Angolan kwanza', 'sikshya'),
        'ARS' => __('Argentine peso', 'sikshya'),
        'AUD' => __('Australian dollar', 'sikshya'),
        'AWG' => __('Aruban florin', 'sikshya'),
        'AZN' => __('Azerbaijani manat', 'sikshya'),
        'BAM' => __('Bosnia and Herzegovina convertible mark', 'sikshya'),
        'BBD' => __('Barbadian dollar', 'sikshya'),
        'BDT' => __('Bangladeshi taka', 'sikshya'),
        'BGN' => __('Bulgarian lev', 'sikshya'),
        'BHD' => __('Bahraini dinar', 'sikshya'),
        'BIF' => __('Burundian franc', 'sikshya'),
        'BMD' => __('Bermudian dollar', 'sikshya'),
        'BND' => __('Brunei dollar', 'sikshya'),
        'BOB' => __('Bolivian boliviano', 'sikshya'),
        'BRL' => __('Brazilian real', 'sikshya'),
        'BSD' => __('Bahamian dollar', 'sikshya'),
        'BTC' => __('Bitcoin', 'sikshya'),
        'BTN' => __('Bhutanese ngultrum', 'sikshya'),
        'BWP' => __('Botswana pula', 'sikshya'),
        'BYN' => __('Belarusian ruble', 'sikshya'),
        'BZD' => __('Belize dollar', 'sikshya'),
        'CAD' => __('Canadian dollar', 'sikshya'),
        'CDF' => __('Congolese franc', 'sikshya'),
        'CHF' => __('Swiss franc', 'sikshya'),
        'CLP' => __('Chilean peso', 'sikshya'),
        'CNY' => __('Chinese yuan', 'sikshya'),
        'COP' => __('Colombian peso', 'sikshya'),
        'CRC' => __('Costa Rican colón', 'sikshya'),
        'CUC' => __('Cuban convertible peso', 'sikshya'),
        'CUP' => __('Cuban peso', 'sikshya'),
        'CVE' => __('Cape Verdean escudo', 'sikshya'),
        'CZK' => __('Czech koruna', 'sikshya'),
        'DJF' => __('Djiboutian franc', 'sikshya'),
        'DKK' => __('Danish krone', 'sikshya'),
        'DOP' => __('Dominican peso', 'sikshya'),
        'DZD' => __('Algerian dinar', 'sikshya'),
        'EGP' => __('Egyptian pound', 'sikshya'),
        'ERN' => __('Eritrean nakfa', 'sikshya'),
        'ETB' => __('Ethiopian birr', 'sikshya'),
        'EUR' => __('Euro', 'sikshya'),
        'FJD' => __('Fijian dollar', 'sikshya'),
        'FKP' => __('Falkland Islands pound', 'sikshya'),
        'GBP' => __('Pound sterling', 'sikshya'),
        'GEL' => __('Georgian lari', 'sikshya'),
        'GGP' => __('Guernsey pound', 'sikshya'),
        'GHS' => __('Ghana cedi', 'sikshya'),
        'GIP' => __('Gibraltar pound', 'sikshya'),
        'GMD' => __('Gambian dalasi', 'sikshya'),
        'GNF' => __('Guinean franc', 'sikshya'),
        'GTQ' => __('Guatemalan quetzal', 'sikshya'),
        'GYD' => __('Guyanese dollar', 'sikshya'),
        'HKD' => __('Hong Kong dollar', 'sikshya'),
        'HNL' => __('Honduran lempira', 'sikshya'),
        'HRK' => __('Croatian kuna', 'sikshya'),
        'HTG' => __('Haitian gourde', 'sikshya'),
        'HUF' => __('Hungarian forint', 'sikshya'),
        'IDR' => __('Indonesian rupiah', 'sikshya'),
        'ILS' => __('Israeli new shekel', 'sikshya'),
        'IMP' => __('Manx pound', 'sikshya'),
        'INR' => __('Indian rupee', 'sikshya'),
        'IQD' => __('Iraqi dinar', 'sikshya'),
        'IRR' => __('Iranian rial', 'sikshya'),
        'IRT' => __('Iranian toman', 'sikshya'),
        'ISK' => __('Icelandic króna', 'sikshya'),
        'JEP' => __('Jersey pound', 'sikshya'),
        'JMD' => __('Jamaican dollar', 'sikshya'),
        'JOD' => __('Jordanian dinar', 'sikshya'),
        'JPY' => __('Japanese yen', 'sikshya'),
        'KES' => __('Kenyan shilling', 'sikshya'),
        'KGS' => __('Kyrgyzstani som', 'sikshya'),
        'KHR' => __('Cambodian riel', 'sikshya'),
        'KMF' => __('Comorian franc', 'sikshya'),
        'KPW' => __('North Korean won', 'sikshya'),
        'KRW' => __('South Korean won', 'sikshya'),
        'KWD' => __('Kuwaiti dinar', 'sikshya'),
        'KYD' => __('Cayman Islands dollar', 'sikshya'),
        'KZT' => __('Kazakhstani tenge', 'sikshya'),
        'LAK' => __('Lao kip', 'sikshya'),
        'LBP' => __('Lebanese pound', 'sikshya'),
        'LKR' => __('Sri Lankan rupee', 'sikshya'),
        'LRD' => __('Liberian dollar', 'sikshya'),
        'LSL' => __('Lesotho loti', 'sikshya'),
        'LYD' => __('Libyan dinar', 'sikshya'),
        'MAD' => __('Moroccan dirham', 'sikshya'),
        'MDL' => __('Moldovan leu', 'sikshya'),
        'MGA' => __('Malagasy ariary', 'sikshya'),
        'MKD' => __('Macedonian denar', 'sikshya'),
        'MMK' => __('Burmese kyat', 'sikshya'),
        'MNT' => __('Mongolian tögrög', 'sikshya'),
        'MOP' => __('Macanese pataca', 'sikshya'),
        'MRU' => __('Mauritanian ouguiya', 'sikshya'),
        'MUR' => __('Mauritian rupee', 'sikshya'),
        'MVR' => __('Maldivian rufiyaa', 'sikshya'),
        'MWK' => __('Malawian kwacha', 'sikshya'),
        'MXN' => __('Mexican peso', 'sikshya'),
        'MYR' => __('Malaysian ringgit', 'sikshya'),
        'MZN' => __('Mozambican metical', 'sikshya'),
        'NAD' => __('Namibian dollar', 'sikshya'),
        'NGN' => __('Nigerian naira', 'sikshya'),
        'NIO' => __('Nicaraguan córdoba', 'sikshya'),
        'NOK' => __('Norwegian krone', 'sikshya'),
        'NPR' => __('Nepalese rupee', 'sikshya'),
        'NZD' => __('New Zealand dollar', 'sikshya'),
        'OMR' => __('Omani rial', 'sikshya'),
        'PAB' => __('Panamanian balboa', 'sikshya'),
        'PEN' => __('Sol', 'sikshya'),
        'PGK' => __('Papua New Guinean kina', 'sikshya'),
        'PHP' => __('Philippine peso', 'sikshya'),
        'PKR' => __('Pakistani rupee', 'sikshya'),
        'PLN' => __('Polish złoty', 'sikshya'),
        'PRB' => __('Transnistrian ruble', 'sikshya'),
        'PYG' => __('Paraguayan guaraní', 'sikshya'),
        'QAR' => __('Qatari riyal', 'sikshya'),
        'RON' => __('Romanian leu', 'sikshya'),
        'RSD' => __('Serbian dinar', 'sikshya'),
        'RUB' => __('Russian ruble', 'sikshya'),
        'RWF' => __('Rwandan franc', 'sikshya'),
        'SAR' => __('Saudi riyal', 'sikshya'),
        'SBD' => __('Solomon Islands dollar', 'sikshya'),
        'SCR' => __('Seychellois rupee', 'sikshya'),
        'SDG' => __('Sudanese pound', 'sikshya'),
        'SEK' => __('Swedish krona', 'sikshya'),
        'SGD' => __('Singapore dollar', 'sikshya'),
        'SHP' => __('Saint Helena pound', 'sikshya'),
        'SLL' => __('Sierra Leonean leone', 'sikshya'),
        'SOS' => __('Somali shilling', 'sikshya'),
        'SRD' => __('Surinamese dollar', 'sikshya'),
        'SSP' => __('South Sudanese pound', 'sikshya'),
        'STN' => __('São Tomé and Príncipe dobra', 'sikshya'),
        'SYP' => __('Syrian pound', 'sikshya'),
        'SZL' => __('Swazi lilangeni', 'sikshya'),
        'THB' => __('Thai baht', 'sikshya'),
        'TJS' => __('Tajikistani somoni', 'sikshya'),
        'TMT' => __('Turkmenistan manat', 'sikshya'),
        'TND' => __('Tunisian dinar', 'sikshya'),
        'TOP' => __('Tongan paʻanga', 'sikshya'),
        'TRY' => __('Turkish lira', 'sikshya'),
        'TTD' => __('Trinidad and Tobago dollar', 'sikshya'),
        'TWD' => __('New Taiwan dollar', 'sikshya'),
        'TZS' => __('Tanzanian shilling', 'sikshya'),
        'UAH' => __('Ukrainian hryvnia', 'sikshya'),
        'UGX' => __('Ugandan shilling', 'sikshya'),
        'USD' => __('United States dollar', 'sikshya'),
        'UYU' => __('Uruguayan peso', 'sikshya'),
        'UZS' => __('Uzbekistani som', 'sikshya'),
        'VEF' => __('Venezuelan bolívar', 'sikshya'),
        'VES' => __('Bolívar soberano', 'sikshya'),
        'VND' => __('Vietnamese đồng', 'sikshya'),
        'VUV' => __('Vanuatu vatu', 'sikshya'),
        'WST' => __('Samoan tālā', 'sikshya'),
        'XAF' => __('Central African CFA franc', 'sikshya'),
        'XCD' => __('East Caribbean dollar', 'sikshya'),
        'XOF' => __('West African CFA franc', 'sikshya'),
        'XPF' => __('CFP franc', 'sikshya'),
        'YER' => __('Yemeni rial', 'sikshya'),
        'ZAR' => __('South African rand', 'sikshya'),
        'ZMW' => __('Zambian kwacha', 'sikshya'),
    ];

    /**
     * Filter the available Sikshya currencies (ISO code => display name).
     *
     * @param array<string,string> $currencies
     */
    return apply_filters('sikshya_currencies', $currencies);
}

/**
 * Symbol / prefix for common currencies.
 *
 * Falls back to `<CODE> ` (e.g. "AED 99.99") for currencies without a known glyph.
 *
 * @param string $code ISO 4217 code.
 * @return string
 */
function sikshya_get_currency_symbol(string $code): string
{
    $code = strtoupper($code);
    $map = [
        'AED' => 'د.إ',
        'AFN' => '؋',
        'ALL' => 'L',
        'AMD' => 'AMD',
        'ANG' => 'ƒ',
        'AOA' => 'Kz',
        'ARS' => '$',
        'AUD' => 'A$',
        'AWG' => 'Afl.',
        'AZN' => 'AZN',
        'BAM' => 'KM',
        'BBD' => '$',
        'BDT' => '৳',
        'BGN' => 'лв.',
        'BHD' => '.د.ب',
        'BIF' => 'Fr',
        'BMD' => '$',
        'BND' => '$',
        'BOB' => 'Bs.',
        'BRL' => 'R$',
        'BSD' => '$',
        'BTC' => '฿',
        'BTN' => 'Nu.',
        'BWP' => 'P',
        'BYN' => 'Br',
        'BZD' => '$',
        'CAD' => 'C$',
        'CDF' => 'Fr',
        'CHF' => 'CHF',
        'CLP' => '$',
        'CNY' => '¥',
        'COP' => '$',
        'CRC' => '₡',
        'CUC' => '$',
        'CUP' => '$',
        'CVE' => '$',
        'CZK' => 'Kč',
        'DJF' => 'Fr',
        'DKK' => 'kr.',
        'DOP' => 'RD$',
        'DZD' => 'د.ج',
        'EGP' => 'EGP',
        'ERN' => 'Nfk',
        'ETB' => 'Br',
        'EUR' => '€',
        'FJD' => '$',
        'FKP' => '£',
        'GBP' => '£',
        'GEL' => '₾',
        'GGP' => '£',
        'GHS' => '₵',
        'GIP' => '£',
        'GMD' => 'D',
        'GNF' => 'Fr',
        'GTQ' => 'Q',
        'GYD' => '$',
        'HKD' => 'HK$',
        'HNL' => 'L',
        'HRK' => 'kn',
        'HTG' => 'G',
        'HUF' => 'Ft',
        'IDR' => 'Rp',
        'ILS' => '₪',
        'IMP' => '£',
        'INR' => '₹',
        'IQD' => 'ع.د',
        'IRR' => '﷼',
        'IRT' => 'تومان',
        'ISK' => 'kr.',
        'JEP' => '£',
        'JMD' => '$',
        'JOD' => 'د.أ',
        'JPY' => '¥',
        'KES' => 'KSh',
        'KGS' => 'сом',
        'KHR' => '៛',
        'KMF' => 'Fr',
        'KPW' => '₩',
        'KRW' => '₩',
        'KWD' => 'د.ك',
        'KYD' => '$',
        'KZT' => '₸',
        'LAK' => '₭',
        'LBP' => 'ل.ل',
        'LKR' => 'රු',
        'LRD' => '$',
        'LSL' => 'L',
        'LYD' => 'ل.د',
        'MAD' => 'د.م.',
        'MDL' => 'MDL',
        'MGA' => 'Ar',
        'MKD' => 'ден',
        'MMK' => 'Ks',
        'MNT' => '₮',
        'MOP' => 'P',
        'MRU' => 'UM',
        'MUR' => '₨',
        'MVR' => '.ރ',
        'MWK' => 'MK',
        'MXN' => 'MX$',
        'MYR' => 'RM',
        'MZN' => 'MT',
        'NAD' => 'N$',
        'NGN' => '₦',
        'NIO' => 'C$',
        'NOK' => 'kr',
        'NPR' => 'रू',
        'NZD' => 'NZ$',
        'OMR' => 'ر.ع.',
        'PAB' => 'B/.',
        'PEN' => 'S/',
        'PGK' => 'K',
        'PHP' => '₱',
        'PKR' => '₨',
        'PLN' => 'zł',
        'PRB' => 'р.',
        'PYG' => '₲',
        'QAR' => 'ر.ق',
        'RON' => 'lei',
        'RSD' => 'дин.',
        'RUB' => '₽',
        'RWF' => 'Fr',
        'SAR' => 'ر.س',
        'SBD' => '$',
        'SCR' => '₨',
        'SDG' => 'ج.س.',
        'SEK' => 'kr',
        'SGD' => 'S$',
        'SHP' => '£',
        'SLL' => 'Le',
        'SOS' => 'Sh',
        'SRD' => '$',
        'SSP' => '£',
        'STN' => 'Db',
        'SYP' => 'ل.س',
        'SZL' => 'L',
        'THB' => '฿',
        'TJS' => 'SM',
        'TMT' => 'm',
        'TND' => 'د.ت',
        'TOP' => 'T$',
        'TRY' => '₺',
        'TTD' => '$',
        'TWD' => 'NT$',
        'TZS' => 'Sh',
        'UAH' => '₴',
        'UGX' => 'UGX',
        'USD' => '$',
        'UYU' => '$',
        'UZS' => 'UZS',
        'VEF' => 'Bs F',
        'VES' => 'Bs.S',
        'VND' => '₫',
        'VUV' => 'Vt',
        'WST' => 'T',
        'XAF' => 'CFA',
        'XCD' => '$',
        'XOF' => 'CFA',
        'XPF' => 'Fr',
        'YER' => '﷼',
        'ZAR' => 'R',
        'ZMW' => 'ZK',
        'OTHER' => '',
    ];

    /**
     * Filter the symbol/prefix for a currency.
     *
     * @param string $symbol The resolved symbol (or fallback "<code> ").
     * @param string $code   ISO code (uppercased).
     */
    $symbol = isset($map[$code]) ? $map[$code] : $code . ' ';

    return (string) apply_filters('sikshya_currency_symbol', $symbol, $code);
}

/**
 * Display label for a currency suitable for select dropdowns ("Name (Symbol)").
 *
 * @param string $code ISO 4217 code.
 * @return string
 */
function sikshya_get_currency_label(string $code): string
{
    $code = strtoupper($code);
    $currencies = sikshya_get_currencies();
    $name = isset($currencies[$code]) ? $currencies[$code] : $code;
    $symbol = sikshya_get_currency_symbol($code);

    if ($symbol === '' || trim($symbol) === $code) {
        return sprintf('%s (%s)', $name, $code);
    }

    return sprintf('%s (%s)', $name, $symbol);
}

/**
 * Map of code => "Name (Symbol)" for `<select>` dropdowns.
 *
 * @return array<string,string>
 */
function sikshya_get_currency_choices(): array
{
    $out = [];
    foreach (sikshya_get_currencies() as $code => $_name) {
        $out[$code] = sikshya_get_currency_label($code);
    }

    return $out;
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
