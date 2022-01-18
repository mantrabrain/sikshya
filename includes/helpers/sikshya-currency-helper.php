<?php
defined('ABSPATH') || exit;

if (!function_exists('sikshya_get_currencies')) {
	function sikshya_get_currencies($currency_key = '')
	{
		$currencies = array_unique(
			apply_filters('sikshya_currencies',
				array(
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
					'BYR' => __('Belarusian ruble (old)', 'sikshya'),
					'BYN' => __('Belarusian ruble', 'sikshya'),
					'BZD' => __('Belize dollar', 'sikshya'),
					'CAD' => __('Canadian dollar', 'sikshya'),
					'CDF' => __('Congolese franc', 'sikshya'),
					'CHF' => __('Swiss franc', 'sikshya'),
					'CLP' => __('Chilean peso', 'sikshya'),
					'CNY' => __('Chinese yuan', 'sikshya'),
					'COP' => __('Colombian peso', 'sikshya'),
					'CRC' => __('Costa Rican col&oacute;n', 'sikshya'),
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
					'ISK' => __('Icelandic kr&oacute;na', 'sikshya'),
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
					'MNT' => __('Mongolian t&ouml;gr&ouml;g', 'sikshya'),
					'MOP' => __('Macanese pataca', 'sikshya'),
					'MRO' => __('Mauritanian ouguiya', 'sikshya'),
					'MUR' => __('Mauritian rupee', 'sikshya'),
					'MVR' => __('Maldivian rufiyaa', 'sikshya'),
					'MWK' => __('Malawian kwacha', 'sikshya'),
					'MXN' => __('Mexican peso', 'sikshya'),
					'MYR' => __('Malaysian ringgit', 'sikshya'),
					'MZN' => __('Mozambican metical', 'sikshya'),
					'NAD' => __('Namibian dollar', 'sikshya'),
					'NGN' => __('Nigerian naira', 'sikshya'),
					'NIO' => __('Nicaraguan c&oacute;rdoba', 'sikshya'),
					'NOK' => __('Norwegian krone', 'sikshya'),
					'NPR' => __('Nepalese rupee', 'sikshya'),
					'NZD' => __('New Zealand dollar', 'sikshya'),
					'OMR' => __('Omani rial', 'sikshya'),
					'PAB' => __('Panamanian balboa', 'sikshya'),
					'PEN' => __('Peruvian nuevo sol', 'sikshya'),
					'PGK' => __('Papua New Guinean kina', 'sikshya'),
					'PHP' => __('Philippine peso', 'sikshya'),
					'PKR' => __('Pakistani rupee', 'sikshya'),
					'PLN' => __('Polish z&#x142;oty', 'sikshya'),
					'PRB' => __('Transnistrian ruble', 'sikshya'),
					'PYG' => __('Paraguayan guaran&iacute;', 'sikshya'),
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
					'STD' => __('S&atilde;o Tom&eacute; and Pr&iacute;ncipe dobra', 'sikshya'),
					'SYP' => __('Syrian pound', 'sikshya'),
					'SZL' => __('Swazi lilangeni', 'sikshya'),
					'THB' => __('Thai baht', 'sikshya'),
					'TJS' => __('Tajikistani somoni', 'sikshya'),
					'TMT' => __('Turkmenistan manat', 'sikshya'),
					'TND' => __('Tunisian dinar', 'sikshya'),
					'TOP' => __('Tongan pa&#x2bb;anga', 'sikshya'),
					'TRY' => __('Turkish lira', 'sikshya'),
					'TTD' => __('Trinidad and Tobago dollar', 'sikshya'),
					'TWD' => __('New Taiwan dollar', 'sikshya'),
					'TZS' => __('Tanzanian shilling', 'sikshya'),
					'UAH' => __('Ukrainian hryvnia', 'sikshya'),
					'UGX' => __('Ugandan shilling', 'sikshya'),
					'USD' => __('United States (US) dollar', 'sikshya'),
					'UYU' => __('Uruguayan peso', 'sikshya'),
					'UZS' => __('Uzbekistani som', 'sikshya'),
					'VEF' => __('Venezuelan bol&iacute;var', 'sikshya'),
					'VND' => __('Vietnamese &#x111;&#x1ed3;ng', 'sikshya'),
					'VUV' => __('Vanuatu vatu', 'sikshya'),
					'WST' => __('Samoan t&#x101;l&#x101;', 'sikshya'),
					'XAF' => __('Central African CFA franc', 'sikshya'),
					'XCD' => __('East Caribbean dollar', 'sikshya'),
					'XOF' => __('West African CFA franc', 'sikshya'),
					'XPF' => __('CFP franc', 'sikshya'),
					'YER' => __('Yemeni rial', 'sikshya'),
					'ZAR' => __('South African rand', 'sikshya'),
					'ZMW' => __('Zambian kwacha', 'sikshya'),
				)
			)
		);

		if (!empty($currency_key) && isset($currencies[$currency_key])) {

			return $currencies[$currency_key];
		}
		return $currencies;
	}
}

if (!function_exists('sikshya_get_currency_symbols')) {
	function sikshya_get_currency_symbols($currency_key = '')
	{
		$symbols = apply_filters('sikshya_currency_symbols', array(
			'AED' => '&#x62f;.&#x625;',
			'AFN' => '&#x60b;',
			'ALL' => 'L',
			'AMD' => 'AMD',
			'ANG' => '&fnof;',
			'AOA' => 'Kz',
			'ARS' => '&#36;',
			'AUD' => '&#36;',
			'AWG' => 'Afl.',
			'AZN' => 'AZN',
			'BAM' => 'KM',
			'BBD' => '&#36;',
			'BDT' => '&#2547;&nbsp;',
			'BGN' => '&#1083;&#1074;.',
			'BHD' => '.&#x62f;.&#x628;',
			'BIF' => 'Fr',
			'BMD' => '&#36;',
			'BND' => '&#36;',
			'BOB' => 'Bs.',
			'BRL' => '&#82;&#36;',
			'BSD' => '&#36;',
			'BTC' => '&#3647;',
			'BTN' => 'Nu.',
			'BWP' => 'P',
			'BYR' => 'Br',
			'BYN' => 'Br',
			'BZD' => '&#36;',
			'CAD' => '&#36;',
			'CDF' => 'Fr',
			'CHF' => '&#67;&#72;&#70;',
			'CLP' => '&#36;',
			'CNY' => '&yen;',
			'COP' => '&#36;',
			'CRC' => '&#x20a1;',
			'CUC' => '&#36;',
			'CUP' => '&#36;',
			'CVE' => '&#36;',
			'CZK' => '&#75;&#269;',
			'DJF' => 'Fr',
			'DKK' => 'DKK',
			'DOP' => 'RD&#36;',
			'DZD' => '&#x62f;.&#x62c;',
			'EGP' => 'EGP',
			'ERN' => 'Nfk',
			'ETB' => 'Br',
			'EUR' => '&euro;',
			'FJD' => '&#36;',
			'FKP' => '&pound;',
			'GBP' => '&pound;',
			'GEL' => '&#x20be;',
			'GGP' => '&pound;',
			'GHS' => '&#x20b5;',
			'GIP' => '&pound;',
			'GMD' => 'D',
			'GNF' => 'Fr',
			'GTQ' => 'Q',
			'GYD' => '&#36;',
			'HKD' => '&#36;',
			'HNL' => 'L',
			'HRK' => 'kn',
			'HTG' => 'G',
			'HUF' => '&#70;&#116;',
			'IDR' => 'Rp',
			'ILS' => '&#8362;',
			'IMP' => '&pound;',
			'INR' => '&#8377;',
			'IQD' => '&#x639;.&#x62f;',
			'IRR' => '&#xfdfc;',
			'IRT' => '&#x062A;&#x0648;&#x0645;&#x0627;&#x0646;',
			'ISK' => 'kr.',
			'JEP' => '&pound;',
			'JMD' => '&#36;',
			'JOD' => '&#x62f;.&#x627;',
			'JPY' => '&yen;',
			'KES' => 'KSh',
			'KGS' => '&#x441;&#x43e;&#x43c;',
			'KHR' => '&#x17db;',
			'KMF' => 'Fr',
			'KPW' => '&#x20a9;',
			'KRW' => '&#8361;',
			'KWD' => '&#x62f;.&#x643;',
			'KYD' => '&#36;',
			'KZT' => 'KZT',
			'LAK' => '&#8365;',
			'LBP' => '&#x644;.&#x644;',
			'LKR' => '&#xdbb;&#xdd4;',
			'LRD' => '&#36;',
			'LSL' => 'L',
			'LYD' => '&#x644;.&#x62f;',
			'MAD' => '&#x62f;.&#x645;.',
			'MDL' => 'MDL',
			'MGA' => 'Ar',
			'MKD' => '&#x434;&#x435;&#x43d;',
			'MMK' => 'Ks',
			'MNT' => '&#x20ae;',
			'MOP' => 'P',
			'MRO' => 'UM',
			'MUR' => '&#x20a8;',
			'MVR' => '.&#x783;',
			'MWK' => 'MK',
			'MXN' => '&#36;',
			'MYR' => '&#82;&#77;',
			'MZN' => 'MT',
			'NAD' => '&#36;',
			'NGN' => '&#8358;',
			'NIO' => 'C&#36;',
			'NOK' => '&#107;&#114;',
			'NPR' => '&#8360;',
			'NZD' => '&#36;',
			'OMR' => '&#x631;.&#x639;.',
			'PAB' => 'B/.',
			'PEN' => 'S/.',
			'PGK' => 'K',
			'PHP' => '&#8369;',
			'PKR' => '&#8360;',
			'PLN' => '&#122;&#322;',
			'PRB' => '&#x440;.',
			'PYG' => '&#8370;',
			'QAR' => '&#x631;.&#x642;',
			'RMB' => '&yen;',
			'RON' => 'lei',
			'RSD' => '&#x434;&#x438;&#x43d;.',
			'RUB' => '&#8381;',
			'RWF' => 'Fr',
			'SAR' => '&#x631;.&#x633;',
			'SBD' => '&#36;',
			'SCR' => '&#x20a8;',
			'SDG' => '&#x62c;.&#x633;.',
			'SEK' => '&#107;&#114;',
			'SGD' => '&#36;',
			'SHP' => '&pound;',
			'SLL' => 'Le',
			'SOS' => 'Sh',
			'SRD' => '&#36;',
			'SSP' => '&pound;',
			'STD' => 'Db',
			'SYP' => '&#x644;.&#x633;',
			'SZL' => 'L',
			'THB' => '&#3647;',
			'TJS' => '&#x405;&#x41c;',
			'TMT' => 'm',
			'TND' => '&#x62f;.&#x62a;',
			'TOP' => 'T&#36;',
			'TRY' => '&#8378;',
			'TTD' => '&#36;',
			'TWD' => '&#78;&#84;&#36;',
			'TZS' => 'Sh',
			'UAH' => '&#8372;',
			'UGX' => 'UGX',
			'USD' => '&#36;',
			'UYU' => '&#36;',
			'UZS' => 'UZS',
			'VEF' => 'Bs F',
			'VND' => '&#8363;',
			'VUV' => 'Vt',
			'WST' => 'T',
			'XAF' => 'CFA',
			'XCD' => '&#36;',
			'XOF' => 'CFA',
			'XPF' => 'Fr',
			'YER' => '&#xfdfc;',
			'ZAR' => '&#82;',
			'ZMW' => 'ZK',
		));

		$currency_symbol = isset($symbols[$currency_key]) ? $symbols[$currency_key] : '';

		return apply_filters('sikshya_currency_symbol', $currency_symbol, $currency_key);
	}
}

if (!function_exists('sikshya_get_currency_with_symbol')) {

	function sikshya_get_currency_with_symbol($currency_position = 'right')
	{
		$currency = sikshya_get_currencies();

		$currency_with_symbol = array();

		foreach ($currency as $currency_key => $currency_value) {

			$symbol = sikshya_get_currency_symbols($currency_key);

			$value = !empty($symbol) ? ' (' . $symbol . ') ' : '';

			$value = $currency_position == "left" ? $value . $currency_value : $currency_value . $value;

			$currency_with_symbol[$currency_key] = $value;
		}

		return $currency_with_symbol;
	}
}

if (!function_exists('sikshya_get_active_currency_symbol')) {
	function sikshya_get_active_currency_symbol()
	{

		$currency_key = sikshya_get_active_currency(true);

		$currency_symbol = '' != $currency_key ? sikshya_get_currency_symbols($currency_key) : '';

		return $currency_symbol;
	}
}
if (!function_exists('sikshya_get_active_currency')) {

	function sikshya_get_active_currency($get_currency_key = false)
	{
		$currency_key = get_option('sikshya_currency');

		if ($get_currency_key) {

			return $currency_key;
		}

		$currency = '' != $currency_key ? sikshya_get_currencies($currency_key) : '';

		return $currency;

	}
}

if (!function_exists('sikshya_get_currency_symbol_type')) {
	function sikshya_get_currency_symbol_type()
	{
		return array(
			'code' => __('Currency Code', 'sikshya'),
			'symbol' => __('Currency Symbol', 'sikshya')
		);
	}
}
