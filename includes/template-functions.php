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
 * Enqueue compiled block-support CSS when rendering Sikshya templates on block (FSE) themes.
 * Mirrors Yatra's {@see yatra_block_support_styles()} so layout primitives stay styled.
 */
if (!function_exists('sikshya_block_support_styles')) {
    function sikshya_block_support_styles(): void
    {
        if (!function_exists('wp_style_engine_get_stylesheet_from_context')) {
            return;
        }

        $core_styles_keys = ['block-supports'];
        $compiled = '';
        foreach ($core_styles_keys as $style_key) {
            $compiled .= wp_style_engine_get_stylesheet_from_context($style_key, []);
        }
        if ($compiled === '') {
            return;
        }

        wp_register_style('sikshya-block-supports', false);
        wp_enqueue_style('sikshya-block-supports');
        wp_add_inline_style('sikshya-block-supports', $compiled);
    }
}

/**
 * Load the theme header for Sikshya frontend templates.
 *
 * On block (full-site editing) themes, {@see get_header()} often omits a usable document shell;
 * this mirrors Yatra's {@see yatra_get_header()} by outputting a minimal HTML scaffold, title, and
 * {@see block_header_area()}. On classic themes it delegates to {@see get_header()}.
 *
 * @param string|null $header_name Optional template name passed to {@see get_header()}.
 */
if (!function_exists('sikshya_get_header')) {
    function sikshya_get_header(?string $header_name = null): void
    {
        global $wp_version;
        if (
            version_compare((string) $wp_version, '5.9', '>=')
            && function_exists('wp_is_block_theme')
            && wp_is_block_theme()
        ) {
            /*
             * FSE themes may rely on the template canvas for title tags; ensure a <title> exists
             * and avoid duplicate core title hooks (same rationale as Yatra).
             */
            remove_action('wp_head', '_wp_render_title_tag', 1);
            remove_action('wp_head', '_block_template_render_title_tag', 1);
            ?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <title><?php echo esc_html(wp_get_document_title()); ?></title>
    <?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
    <div class="wp-site-blocks">
        <header class="wp-block-template-part site-header">
            <?php
            if (function_exists('block_header_area')) {
                block_header_area();
            }
            ?>
        </header>
            <?php
        } elseif ($header_name !== null && $header_name !== '') {
            get_header($header_name);
        } else {
            get_header();
        }
    }
}

/**
 * Load the theme footer for Sikshya frontend templates.
 *
 * Closes the FSE wrapper opened in {@see sikshya_get_header()} when applicable; otherwise {@see get_footer()}.
 *
 * @param string|null $footer_name Optional template name passed to {@see get_footer()}.
 */
if (!function_exists('sikshya_get_footer')) {
    function sikshya_get_footer(?string $footer_name = null): void
    {
        global $wp_version;
        if (
            version_compare((string) $wp_version, '5.9', '>=')
            && function_exists('wp_is_block_theme')
            && wp_is_block_theme()
        ) {
            ?>
        <footer class="wp-block-template-part site-footer">
            <?php
            if (function_exists('block_footer_area')) {
                block_footer_area();
            }
            ?>
        </footer>
    </div>
            <?php
            sikshya_block_support_styles();
            wp_footer();
            ?>
</body>
</html>
            <?php
        } elseif ($footer_name !== null && $footer_name !== '') {
            get_footer($footer_name);
        } else {
            get_footer();
        }
    }
}

/**
 * Resolved brand profile for the current request context.
 *
 * This is intentionally an array (not a class) so the free plugin stays decoupled
 * from Pro addon implementation details. Pro injects values via filters.
 *
 * @return array<string,mixed>
 */
function sikshya_brand_profile(string $context = 'frontend'): array
{
    $base = [
        'brandName' => __('Sikshya LMS', 'sikshya'),
        'brandShortName' => __('Sikshya', 'sikshya'),
        'frontendAccent' => '',
        'loginAccent' => '',
        'loginLogoUrl' => '',
        'hidePlatformFooter' => false,
        'adminFooterHtml' => '',
        'terminology' => [],
        'links' => [
            'documentationUrl' => 'https://docs.sikshya.com',
            'supportUrl' => 'https://support.sikshya.com',
            'upgradeUrl' => 'https://sikshya.com/pricing/',
        ],
        'surfaces' => [
            'admin' => true,
            'login' => true,
            'frontend' => true,
            'email' => true,
        ],
    ];

    /**
     * Filter the effective brand profile.
     *
     * @param array<string,mixed> $base
     * @param string $context One of: admin, login, frontend, email.
     */
    $profile = (array) apply_filters('sikshya_brand_profile', $base, $context);

    return array_merge($base, is_array($profile) ? $profile : []);
}

function sikshya_brand_name(string $context = 'frontend'): string
{
    $p = sikshya_brand_profile($context);
    $name = isset($p['brandName']) ? sanitize_text_field((string) $p['brandName']) : '';
    return $name !== '' ? $name : __('Sikshya LMS', 'sikshya');
}

/**
 * @return array<string,string>
 */
function sikshya_brand_links(): array
{
    $base = [
        'documentationUrl' => 'https://docs.sikshya.com',
        'supportUrl' => 'https://support.sikshya.com',
        'upgradeUrl' => 'https://sikshya.com/pricing/',
    ];
    $links = apply_filters('sikshya_brand_links', $base);
    $links = is_array($links) ? $links : [];
    return array_merge($base, array_map('strval', $links));
}

/**
 * Terminology relabeling for common LMS nouns.
 */
function sikshya_label(string $key, string $default, string $context = 'frontend'): string
{
    $key = sanitize_key($key);
    if ($key === '') {
        return $default;
    }

    /**
     * Filter a single label (terminology relabeling).
     *
     * @param string $defaultLabel
     * @param string $key E.g. course, lesson, quiz, assignment, chapter, student, instructor, enrollment.
     * @param string $context E.g. admin, frontend, email.
     */
    $filtered = apply_filters('sikshya_label', $default, $key, $context);
    $out = is_string($filtered) ? sanitize_text_field($filtered) : $default;
    return $out !== '' ? $out : $default;
}

function sikshya_label_plural(string $singularKey, string $pluralKey, string $defaultPlural, string $context = 'frontend'): string
{
    $plural = sikshya_label($pluralKey, '', $context);
    if ($plural !== '') {
        return $plural;
    }
    // Fallback: if singular was customized but plural wasn't, keep default plural (safer than naive "add s").
    unset($singularKey);
    return $defaultPlural;
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
 * Country choices for checkout/billing fields.
 *
 * Prefer WooCommerce's country list when available; otherwise fall back to a full
 * ISO 3166-1 alpha-2 list. Add-ons/themes can override via filter.
 *
 * @return array<string, string> Map ISO code => Country name
 */
function sikshya_get_country_choices(): array
{
    $countries = [];

    // Prefer WooCommerce if present.
    if (class_exists('\WC_Countries')) {
        try {
            $wc = new \WC_Countries();
            $countries = is_array($wc->get_countries()) ? $wc->get_countries() : [];
        } catch (\Throwable $e) {
            $countries = [];
        }
    }

    if (empty($countries)) {
        // Full fallback list (ISO 3166-1 alpha-2). Can be overridden by filter.
        $countries = [
            'AF' => 'Afghanistan',
            'AX' => 'Åland Islands',
            'AL' => 'Albania',
            'DZ' => 'Algeria',
            'AS' => 'American Samoa',
            'AD' => 'Andorra',
            'AO' => 'Angola',
            'AI' => 'Anguilla',
            'AQ' => 'Antarctica',
            'AG' => 'Antigua and Barbuda',
            'AR' => 'Argentina',
            'AM' => 'Armenia',
            'AW' => 'Aruba',
            'AU' => 'Australia',
            'AT' => 'Austria',
            'AZ' => 'Azerbaijan',
            'BS' => 'Bahamas',
            'BH' => 'Bahrain',
            'BD' => 'Bangladesh',
            'BB' => 'Barbados',
            'BY' => 'Belarus',
            'BE' => 'Belgium',
            'BZ' => 'Belize',
            'BJ' => 'Benin',
            'BM' => 'Bermuda',
            'BT' => 'Bhutan',
            'BO' => 'Bolivia',
            'BQ' => 'Bonaire, Sint Eustatius and Saba',
            'BA' => 'Bosnia and Herzegovina',
            'BW' => 'Botswana',
            'BV' => 'Bouvet Island',
            'BR' => 'Brazil',
            'IO' => 'British Indian Ocean Territory',
            'BN' => 'Brunei Darussalam',
            'BG' => 'Bulgaria',
            'BF' => 'Burkina Faso',
            'BI' => 'Burundi',
            'KH' => 'Cambodia',
            'CM' => 'Cameroon',
            'CA' => 'Canada',
            'CV' => 'Cape Verde',
            'KY' => 'Cayman Islands',
            'CF' => 'Central African Republic',
            'TD' => 'Chad',
            'CL' => 'Chile',
            'CN' => 'China',
            'CX' => 'Christmas Island',
            'CC' => 'Cocos (Keeling) Islands',
            'CO' => 'Colombia',
            'KM' => 'Comoros',
            'CG' => 'Congo',
            'CD' => 'Congo (Democratic Republic of the)',
            'CK' => 'Cook Islands',
            'CR' => 'Costa Rica',
            'CI' => "Côte d'Ivoire",
            'HR' => 'Croatia',
            'CU' => 'Cuba',
            'CW' => 'Curaçao',
            'CY' => 'Cyprus',
            'CZ' => 'Czechia',
            'DK' => 'Denmark',
            'DJ' => 'Djibouti',
            'DM' => 'Dominica',
            'DO' => 'Dominican Republic',
            'EC' => 'Ecuador',
            'EG' => 'Egypt',
            'SV' => 'El Salvador',
            'GQ' => 'Equatorial Guinea',
            'ER' => 'Eritrea',
            'EE' => 'Estonia',
            'SZ' => 'Eswatini',
            'ET' => 'Ethiopia',
            'FK' => 'Falkland Islands (Malvinas)',
            'FO' => 'Faroe Islands',
            'FJ' => 'Fiji',
            'FI' => 'Finland',
            'FR' => 'France',
            'GF' => 'French Guiana',
            'PF' => 'French Polynesia',
            'TF' => 'French Southern Territories',
            'GA' => 'Gabon',
            'GM' => 'Gambia',
            'GE' => 'Georgia',
            'DE' => 'Germany',
            'GH' => 'Ghana',
            'GI' => 'Gibraltar',
            'GR' => 'Greece',
            'GL' => 'Greenland',
            'GD' => 'Grenada',
            'GP' => 'Guadeloupe',
            'GU' => 'Guam',
            'GT' => 'Guatemala',
            'GG' => 'Guernsey',
            'GN' => 'Guinea',
            'GW' => 'Guinea-Bissau',
            'GY' => 'Guyana',
            'HT' => 'Haiti',
            'HM' => 'Heard Island and McDonald Islands',
            'VA' => 'Holy See',
            'HN' => 'Honduras',
            'HK' => 'Hong Kong',
            'HU' => 'Hungary',
            'IS' => 'Iceland',
            'IN' => 'India',
            'ID' => 'Indonesia',
            'IR' => 'Iran',
            'IQ' => 'Iraq',
            'IE' => 'Ireland',
            'IM' => 'Isle of Man',
            'IL' => 'Israel',
            'IT' => 'Italy',
            'JM' => 'Jamaica',
            'JP' => 'Japan',
            'JE' => 'Jersey',
            'JO' => 'Jordan',
            'KZ' => 'Kazakhstan',
            'KE' => 'Kenya',
            'KI' => 'Kiribati',
            'KP' => "Korea (Democratic People's Republic of)",
            'KR' => 'Korea (Republic of)',
            'KW' => 'Kuwait',
            'KG' => 'Kyrgyzstan',
            'LA' => "Lao People's Democratic Republic",
            'LV' => 'Latvia',
            'LB' => 'Lebanon',
            'LS' => 'Lesotho',
            'LR' => 'Liberia',
            'LY' => 'Libya',
            'LI' => 'Liechtenstein',
            'LT' => 'Lithuania',
            'LU' => 'Luxembourg',
            'MO' => 'Macao',
            'MG' => 'Madagascar',
            'MW' => 'Malawi',
            'MY' => 'Malaysia',
            'MV' => 'Maldives',
            'ML' => 'Mali',
            'MT' => 'Malta',
            'MH' => 'Marshall Islands',
            'MQ' => 'Martinique',
            'MR' => 'Mauritania',
            'MU' => 'Mauritius',
            'YT' => 'Mayotte',
            'MX' => 'Mexico',
            'FM' => 'Micronesia',
            'MD' => 'Moldova',
            'MC' => 'Monaco',
            'MN' => 'Mongolia',
            'ME' => 'Montenegro',
            'MS' => 'Montserrat',
            'MA' => 'Morocco',
            'MZ' => 'Mozambique',
            'MM' => 'Myanmar',
            'NA' => 'Namibia',
            'NR' => 'Nauru',
            'NP' => 'Nepal',
            'NL' => 'Netherlands',
            'NC' => 'New Caledonia',
            'NZ' => 'New Zealand',
            'NI' => 'Nicaragua',
            'NE' => 'Niger',
            'NG' => 'Nigeria',
            'NU' => 'Niue',
            'NF' => 'Norfolk Island',
            'MK' => 'North Macedonia',
            'MP' => 'Northern Mariana Islands',
            'NO' => 'Norway',
            'OM' => 'Oman',
            'PK' => 'Pakistan',
            'PW' => 'Palau',
            'PS' => 'Palestine, State of',
            'PA' => 'Panama',
            'PG' => 'Papua New Guinea',
            'PY' => 'Paraguay',
            'PE' => 'Peru',
            'PH' => 'Philippines',
            'PN' => 'Pitcairn',
            'PL' => 'Poland',
            'PT' => 'Portugal',
            'PR' => 'Puerto Rico',
            'QA' => 'Qatar',
            'RE' => 'Réunion',
            'RO' => 'Romania',
            'RU' => 'Russian Federation',
            'RW' => 'Rwanda',
            'BL' => 'Saint Barthélemy',
            'SH' => 'Saint Helena, Ascension and Tristan da Cunha',
            'KN' => 'Saint Kitts and Nevis',
            'LC' => 'Saint Lucia',
            'MF' => 'Saint Martin (French part)',
            'PM' => 'Saint Pierre and Miquelon',
            'VC' => 'Saint Vincent and the Grenadines',
            'WS' => 'Samoa',
            'SM' => 'San Marino',
            'ST' => 'Sao Tome and Principe',
            'SA' => 'Saudi Arabia',
            'SN' => 'Senegal',
            'RS' => 'Serbia',
            'SC' => 'Seychelles',
            'SL' => 'Sierra Leone',
            'SG' => 'Singapore',
            'SX' => 'Sint Maarten (Dutch part)',
            'SK' => 'Slovakia',
            'SI' => 'Slovenia',
            'SB' => 'Solomon Islands',
            'SO' => 'Somalia',
            'ZA' => 'South Africa',
            'GS' => 'South Georgia and the South Sandwich Islands',
            'SS' => 'South Sudan',
            'ES' => 'Spain',
            'LK' => 'Sri Lanka',
            'SD' => 'Sudan',
            'SR' => 'Suriname',
            'SJ' => 'Svalbard and Jan Mayen',
            'SE' => 'Sweden',
            'CH' => 'Switzerland',
            'SY' => 'Syrian Arab Republic',
            'TW' => 'Taiwan',
            'TJ' => 'Tajikistan',
            'TZ' => 'Tanzania, United Republic of',
            'TH' => 'Thailand',
            'TL' => 'Timor-Leste',
            'TG' => 'Togo',
            'TK' => 'Tokelau',
            'TO' => 'Tonga',
            'TT' => 'Trinidad and Tobago',
            'TN' => 'Tunisia',
            'TR' => 'Turkey',
            'TM' => 'Turkmenistan',
            'TC' => 'Turks and Caicos Islands',
            'TV' => 'Tuvalu',
            'UG' => 'Uganda',
            'UA' => 'Ukraine',
            'AE' => 'United Arab Emirates',
            'GB' => 'United Kingdom',
            'US' => 'United States',
            'UM' => 'United States Minor Outlying Islands',
            'UY' => 'Uruguay',
            'UZ' => 'Uzbekistan',
            'VU' => 'Vanuatu',
            'VE' => 'Venezuela',
            'VN' => 'Viet Nam',
            'VG' => 'Virgin Islands (British)',
            'VI' => 'Virgin Islands (U.S.)',
            'WF' => 'Wallis and Futuna',
            'EH' => 'Western Sahara',
            'YE' => 'Yemen',
            'ZM' => 'Zambia',
            'ZW' => 'Zimbabwe',
        ];
    }

    /**
     * Filter country choices used by Sikshya checkout field rendering.
     *
     * @param array<string, string> $countries
     */
    $countries = apply_filters('sikshya_country_choices', $countries);

    // Normalize to strings + sort by name for UI.
    $countries = array_map('strval', $countries);
    asort($countries, SORT_NATURAL | SORT_FLAG_CASE);

    return $countries;
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
 * Parse a float from course price meta values (legacy migrations may include symbols).
 *
 * @param mixed $raw Meta value.
 * @return float|null Null when empty/unparseable.
 */
function sikshya_parse_price_meta($raw): ?float
{
    if ($raw === '' || $raw === null || $raw === false) {
        return null;
    }
    if (is_int($raw) || is_float($raw) || (is_string($raw) && is_numeric($raw))) {
        return (float) $raw;
    }
    if (!is_string($raw)) {
        return null;
    }

    $s = trim($raw);
    if ($s === '') {
        return null;
    }

    // Keep digits and separators only; tolerate "USD 99.99", "₹1,200", etc.
    $clean = preg_replace('/[^0-9,.\-]/', '', $s);
    $clean = is_string($clean) ? trim($clean) : '';
    if ($clean === '' || $clean === '-' || $clean === '.' || $clean === ',') {
        return null;
    }

    // Remove thousand separators; keep last decimal separator when plausible.
    if (str_contains($clean, ',') && str_contains($clean, '.')) {
        $clean = str_replace(',', '', $clean);
    } else {
        if (substr_count($clean, ',') === 1 && preg_match('/,\d{1,2}$/', $clean)) {
            $clean = str_replace(',', '.', $clean);
        } else {
            $clean = str_replace(',', '', $clean);
        }
    }

    return is_numeric($clean) ? (float) $clean : null;
}

/**
 * Resolved course pricing from all known meta key variants (builder, legacy, CPT box).
 *
 * @param int $course_id Course post ID.
 * @return array{price:?float,sale_price:?float,currency:string,effective:?float,on_sale:bool}
 */
function sikshya_get_course_pricing(int $course_id): array
{
    $course_type_raw = function_exists('sikshya_first_nonempty_post_meta')
        ? sikshya_first_nonempty_post_meta($course_id, ['_sikshya_course_type', 'course_type', 'sikshya_course_type'])
        : get_post_meta($course_id, '_sikshya_course_type', true);
    $course_type = sanitize_key((string) $course_type_raw);

    $price_raw = sikshya_first_nonempty_post_meta(
        $course_id,
        ['_sikshya_price', '_sikshya_course_price', 'sikshya_course_price']
    );
    $sale_raw = sikshya_first_nonempty_post_meta(
        $course_id,
        ['_sikshya_sale_price', '_sikshya_course_sale_price', 'sikshya_course_sale_price']
    );

    $currency = sikshya_get_store_currency_code();

    $price = sikshya_parse_price_meta($price_raw);
    $sale = sikshya_parse_price_meta($sale_raw);

    // Course explicitly marked free should always render as free in listings.
    if ($course_type === 'free') {
        $price = null;
        $sale = null;
    }

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
 * Course duration label for catalog cards (short, human friendly).
 *
 * Supports:
 * - minutes as int/string: "90" => "1h 30m"
 * - hh:mm: "1:30" => "1h 30m"
 * - strings that already contain h/m/hour/min: kept (lightly normalized)
 *
 * @param mixed $raw
 */
function sikshya_format_course_duration_display($raw): string
{
    if ($raw === '' || $raw === null || $raw === false) {
        return '';
    }

    if (is_int($raw) || is_float($raw) || (is_string($raw) && is_numeric(trim($raw)))) {
        $mins = (int) round((float) $raw);
        if ($mins <= 0) {
            return '';
        }
        $h = intdiv($mins, 60);
        $m = $mins % 60;
        if ($h > 0 && $m > 0) {
            return sprintf('%dh %dm', $h, $m);
        }
        if ($h > 0) {
            return sprintf('%dh', $h);
        }
        return sprintf('%dm', $m);
    }

    if (!is_string($raw)) {
        return '';
    }

    $s = trim(wp_strip_all_tags($raw));
    if ($s === '') {
        return '';
    }

    if (preg_match('/^(\d{1,3})\s*:\s*(\d{1,2})$/', $s, $m)) {
        $h = (int) $m[1];
        $mm = (int) $m[2];
        if ($h <= 0 && $mm <= 0) {
            return '';
        }
        if ($h > 0 && $mm > 0) {
            return sprintf('%dh %dm', $h, $mm);
        }
        if ($h > 0) {
            return sprintf('%dh', $h);
        }
        return sprintf('%dm', $mm);
    }

    $norm = strtolower($s);
    $norm = preg_replace('/\bhours?\b|\bhrs?\b/', 'h', $norm);
    $norm = preg_replace('/\bminutes?\b|\bmins?\b/', 'm', $norm);
    $norm = preg_replace('/\s+/', ' ', (string) $norm);
    $norm = trim((string) $norm);

    $norm = preg_replace('/(\d)\s*h\s*(\d)/', '$1h $2', (string) $norm);

    return $norm !== '' ? $norm : $s;
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

    $course_duration = function_exists('sikshya_get_course_catalog_duration_display')
        ? sikshya_get_course_catalog_duration_display($course_id)
        : sikshya_format_course_duration_display(
            sikshya_first_nonempty_post_meta($course_id, ['_sikshya_duration', '_sikshya_course_duration', 'sikshya_course_duration'])
        );
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
 * Whether the user may access the course as a learner (enrollment row or add-on access such as an active subscription).
 */
function sikshya_is_user_enrolled_in_course(int $course_id, int $user_id = 0): bool
{
    $user_id = $user_id ?: get_current_user_id();
    if ($user_id <= 0 || $course_id <= 0) {
        return false;
    }
    $courses = new \Sikshya\Services\CourseService();

    return $courses->isUserEnrolled($user_id, $course_id);
}

/**
 * Whether the user has completed the course (enrollment status).
 *
 * Cached per request to avoid repeated queries on course listings.
 */
function sikshya_is_user_completed_course(int $course_id, int $user_id = 0): bool
{
    $user_id = $user_id ?: get_current_user_id();
    $course_id = (int) $course_id;
    if ($user_id <= 0 || $course_id <= 0) {
        return false;
    }

    static $cache = [];
    $k = $user_id . ':' . $course_id;
    if (array_key_exists($k, $cache)) {
        return (bool) $cache[$k];
    }

    $repo = new \Sikshya\Database\Repositories\EnrollmentRepository();
    $row = $repo->findByUserAndCourse($user_id, $course_id);
    $cache[$k] = $row && isset($row->status) && (string) $row->status === 'completed';

    return (bool) $cache[$k];
}

/**
 * Certificate download URL for a user + course, if issued.
 *
 * Cached per request to keep course listings fast.
 */
function sikshya_get_user_course_certificate_download_url(int $course_id, int $user_id = 0): string
{
    $user_id = $user_id ?: get_current_user_id();
    $course_id = (int) $course_id;
    if ($user_id <= 0 || $course_id <= 0) {
        return '';
    }

    static $cache = [];
    $k = $user_id . ':' . $course_id;
    if (array_key_exists($k, $cache)) {
        return (string) $cache[$k];
    }

    // Respect global setting used by LearnerCertificateService.
    if (class_exists(\Sikshya\Services\Settings::class) && !\Sikshya\Services\Settings::isTruthy(\Sikshya\Services\Settings::get('students_can_download_certificates', '1'))) {
        $cache[$k] = '';
        return '';
    }

    $repo = new \Sikshya\Database\Repositories\CertificateRepository();
    $row = $repo->findByUserAndCourse($user_id, $course_id);
    $cache[$k] = $row ? (string) ($row->download_url ?? '') : '';

    return (string) $cache[$k];
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
            return sikshya_label('lesson', __('Lesson', 'sikshya'), 'frontend');
        case 'sik_quiz':
            return sikshya_label('quiz', __('Quiz', 'sikshya'), 'frontend');
        case 'sik_assignment':
            return sikshya_label('assignment', __('Assignment', 'sikshya'), 'frontend');
        default:
            return __('Content', 'sikshya');
    }
}

/**
 * Map Sikshya content post type to outline row key (`lesson`|`quiz`|`assignment`|`content`).
 */
function sikshya_outline_type_key_from_post_type(string $post_type): string
{
    switch ($post_type) {
        case 'sik_lesson':
            return 'lesson';
        case 'sik_quiz':
            return 'quiz';
        case 'sik_assignment':
            return 'assignment';
        default:
            return 'content';
    }
}

/**
 * Shared SVG open tag for curriculum type icons — matches Learn sidebar drawing box.
 *
 * @param string $variant `learn`: 18px row icon. `course`: 20px accordion row on single course LP.
 *
 * @return string Attributes snippet (starts with space for concatenation).
 */
function sikshya_curriculum_type_icon_svg_open_attrs(string $variant): string
{
    $variant = sanitize_key($variant);

    if ($variant === 'course') {
        return ' class="sikshya-course-lp__type-svg sikshya-curriculum-type-svg" width="20" height="20" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false"';
    }

    return ' class="sikshya-curriculum-type-svg" viewBox="0 0 24 24" width="18" height="18" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false"';
}

/**
 * Curriculum row type icon (same glyphs as Learn sidebar outline {@see templates/partials/learn-curriculum-outline.php}).
 *
 * @param string $outline_type_key `lesson`|`quiz`|`assignment`|`content`.
 * @param string $lesson_type_key  Sanitized `_sikshya_lesson_type` when type is lesson.
 * @param string $variant           `learn` or `course` (sizes / classes for landing vs shell).
 *
 * @return string SVG markup; safe fixed paths only.
 */
function sikshya_curriculum_outline_row_type_icon_html(
    string $outline_type_key,
    string $lesson_type_key = '',
    string $variant = 'learn'
): string {
    $outline_type_key = sanitize_key($outline_type_key);
    $lesson_type_key = sanitize_key($lesson_type_key);
    $variant = sanitize_key($variant) === 'course' ? 'course' : 'learn';

    $a = sikshya_curriculum_type_icon_svg_open_attrs($variant);
    $body = '';

    switch ($outline_type_key) {
        case 'lesson':
            switch ($lesson_type_key) {
                case 'video':
                    // Learn shell "play-video" glyph.
                    $body = '<rect x="4" y="5" width="14" height="14" rx="2.5" fill="none" stroke="currentColor" stroke-width="2"/><path d="M11 10.5v5l3.5-2.5L11 10.5z" fill="currentColor"/>';

                    break;
                case 'audio':
                    $body = '<path d="M11 5L6 9H3v6h3l5 4V5z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/><path d="M15.5 8.5a4 4 0 010 7" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M18.5 6a7 7 0 010 12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>';

                    break;
                case 'live':
                    $body = '<rect x="3" y="5" width="18" height="16" rx="2" fill="none" stroke="currentColor" stroke-width="2"/><path d="M8 3v4M16 3v4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M7 11h10M7 15h6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>';

                    break;
                case 'scorm':
                    $body = '<path d="M12 2l9 5-9 5-9-5 9-5z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/><path d="M3 12l9 5 9-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/><path d="M3 17l9 5 9-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>';

                    break;
                case 'h5p':
                    $body = '<path d="M8.5 4a2.5 2.5 0 1 1 5 0v1h2a2 2 0 0 1 2 2v2h-1a2.5 2.5 0 1 0 0 5h1v2a2 2 0 0 1-2 2h-2v-1a2.5 2.5 0 1 0-5 0v1h-2a2 2 0 0 1-2-2v-2h1a2.5 2.5 0 1 0 0-5H4.5V7a2 2 0 0 1 2-2h2V4z" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>';

                    break;
                case 'lesson':
                case 'text':
                default:
                    // Learn shell "doc" glyph for text/read lessons.
                    $body = '<path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>';

                    break;
            }

            break;
        case 'quiz':
            $body = '<path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>';

            break;
        case 'assignment':
            $body = '<path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>';

            break;
        case 'content':
        default:
            $body = '<path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>';

            break;
    }

    return '<svg' . $a . '>' . $body . '</svg>';
}

/**
 * Lock SVG shared by Learn sidebar outline and single course curriculum (same glyph).
 *
 * @return string Markup safe for templates (fixed paths).
 */
function sikshya_curriculum_lock_icon_html(): string
{
    return '<svg class="sikshya-curriculum-lock-svg" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false">'
        . '<path d="M7 11V8a5 5 0 0110 0v3" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>'
        . '<path d="M6.5 11h11A2.5 2.5 0 0120 13.5v6A2.5 2.5 0 0117.5 22h-11A2.5 2.5 0 014 19.5v-6A2.5 2.5 0 016.5 11z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>'
        . '</svg>';
}

/**
 * Inline SVG icon for curriculum line items — delegates to unified Learn/course glyphs.
 *
 * Uses default lesson subtype (document-style); prefer {@see sikshya_curriculum_outline_row_type_icon_html()} with lesson subtype on the course landing page.
 *
 * @param string $post_type Post type slug (e.g. sik_lesson).
 *
 * @return string SVG element HTML.
 */
function sikshya_public_content_type_icon_html(string $post_type): string
{
    return sikshya_curriculum_outline_row_type_icon_html(
        sikshya_outline_type_key_from_post_type($post_type),
        '',
        'course'
    );
}

/**
 * Lock icon SVG for curriculum rows (aliases {@see sikshya_curriculum_lock_icon_html()}).
 *
 * @return string Markup safe to escape-print in templates (fixed paths).
 */
function sikshya_learner_lock_icon_svg_html(): string
{
    return sikshya_curriculum_lock_icon_html();
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
