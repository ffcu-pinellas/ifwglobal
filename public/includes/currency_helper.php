<?php
// includes/currency_helper.php - Global Multi-Currency Conversion Engine

if (!function_exists('get_available_currencies')) {
    function get_available_currencies() {
        return [
            'USD' => ['code' => 'USD', 'symbol' => '$',   'name' => 'US Dollar',          'flag' => '🇺🇸', 'rate' => 1.0,      'decimals' => 2],
            'EUR' => ['code' => 'EUR', 'symbol' => '€',   'name' => 'Euro',               'flag' => '🇪🇺', 'rate' => 0.92,     'decimals' => 2],
            'GBP' => ['code' => 'GBP', 'symbol' => '£',   'name' => 'British Pound',      'flag' => '🇬🇧', 'rate' => 0.79,     'decimals' => 2],
            'AUD' => ['code' => 'AUD', 'symbol' => 'A$',  'name' => 'Australian Dollar',  'flag' => '🇦🇺', 'rate' => 1.52,     'decimals' => 2],
            'CAD' => ['code' => 'CAD', 'symbol' => 'C$',  'name' => 'Canadian Dollar',    'flag' => '🇨🇦', 'rate' => 1.36,     'decimals' => 2],
            'CHF' => ['code' => 'CHF', 'symbol' => 'CHF', 'name' => 'Swiss Franc',        'flag' => '🇨🇭', 'rate' => 0.89,     'decimals' => 2],
            'JPY' => ['code' => 'JPY', 'symbol' => '¥',   'name' => 'Japanese Yen',       'flag' => '🇯🇵', 'rate' => 155.0,    'decimals' => 0],
            'SGD' => ['code' => 'SGD', 'symbol' => 'S$',  'name' => 'Singapore Dollar',   'flag' => '🇸🇬', 'rate' => 1.35,     'decimals' => 2],
            'HKD' => ['code' => 'HKD', 'symbol' => 'HK$', 'name' => 'Hong Kong Dollar',   'flag' => '🇭🇰', 'rate' => 7.82,     'decimals' => 2],
            'NZD' => ['code' => 'NZD', 'symbol' => 'NZ$', 'name' => 'New Zealand Dollar', 'flag' => '🇳🇿', 'rate' => 1.64,     'decimals' => 2],
            'CNY' => ['code' => 'CNY', 'symbol' => '¥',   'name' => 'Chinese Yuan',       'flag' => '🇨🇳', 'rate' => 7.23,     'decimals' => 2],
            'INR' => ['code' => 'INR', 'symbol' => '₹',   'name' => 'Indian Rupee',       'flag' => '🇮🇳', 'rate' => 83.5,     'decimals' => 2],
            'BRL' => ['code' => 'BRL', 'symbol' => 'R$',  'name' => 'Brazilian Real',     'flag' => '🇧🇷', 'rate' => 5.40,     'decimals' => 2],
            'MXN' => ['code' => 'MXN', 'symbol' => 'Mex$','name' => 'Mexican Peso',       'flag' => '🇲🇽', 'rate' => 17.2,     'decimals' => 2],
            'AED' => ['code' => 'AED', 'symbol' => 'AED', 'name' => 'UAE Dirham',         'flag' => '🇦🇪', 'rate' => 3.67,     'decimals' => 2],
            'SAR' => ['code' => 'SAR', 'symbol' => 'SAR', 'name' => 'Saudi Riyal',        'flag' => '🇸🇦', 'rate' => 3.75,     'decimals' => 2],
            'ZAR' => ['code' => 'ZAR', 'symbol' => 'R',   'name' => 'South African Rand', 'flag' => '🇿🇦', 'rate' => 18.2,     'decimals' => 2],
            'BTC' => ['code' => 'BTC', 'symbol' => '₿',   'name' => 'Bitcoin',            'flag' => '🪙', 'rate' => 0.000015, 'decimals' => 6],
            'ETH' => ['code' => 'ETH', 'symbol' => 'Ξ',   'name' => 'Ethereum',           'flag' => '🪙', 'rate' => 0.00038,  'decimals' => 5],
            'USDT'=> ['code' => 'USDT','symbol' => '₮',   'name' => 'Tether USD',         'flag' => '💵', 'rate' => 1.0,      'decimals' => 2],
        ];
    }
}

if (!function_exists('get_client_currency')) {
    function get_client_currency($pdo = null, $client_id = null) {
        if (!empty($_SESSION['preferred_currency'])) {
            return strtoupper(trim($_SESSION['preferred_currency']));
        }
        if (!empty($_COOKIE['client_currency'])) {
            $c = strtoupper(trim($_COOKIE['client_currency']));
            $_SESSION['preferred_currency'] = $c;
            return $c;
        }
        if ($pdo && $client_id) {
            try {
                $stmt = $pdo->prepare("SELECT preferred_currency FROM IFW_clients WHERE id = ?");
                $stmt->execute([$client_id]);
                $val = $stmt->fetchColumn();
                if ($val) {
                    $_SESSION['preferred_currency'] = strtoupper(trim($val));
                    return $_SESSION['preferred_currency'];
                }
            } catch (Exception $e) {}
        }
        return 'USD'; // Default currency is USD
    }
}

if (!function_exists('convert_currency')) {
    function convert_currency($amount, $from_currency = 'USD', $to_currency = 'USD') {
        $amount = floatval($amount);
        $from = strtoupper(trim($from_currency ?: 'USD'));
        $to = strtoupper(trim($to_currency ?: 'USD'));
        
        if ($from === $to || $amount == 0) {
            return $amount;
        }
        
        $currencies = get_available_currencies();
        $from_rate = isset($currencies[$from]) ? $currencies[$from]['rate'] : 1.0;
        $to_rate = isset($currencies[$to]) ? $currencies[$to]['rate'] : 1.0;
        
        // Convert to USD base first, then to target currency
        $usd_amount = $amount / ($from_rate > 0 ? $from_rate : 1.0);
        $converted = $usd_amount * $to_rate;
        
        return $converted;
    }
}

if (!function_exists('format_currency')) {
    function format_currency($amount, $currency_code = 'USD', $show_symbol = true, $custom_decimals = null) {
        $amount = floatval($amount);
        $code = strtoupper(trim($currency_code ?: 'USD'));
        $currencies = get_available_currencies();
        $meta = $currencies[$code] ?? ['symbol' => '$', 'decimals' => 2];
        
        $decimals = $custom_decimals !== null ? $custom_decimals : ($meta['decimals'] ?? 2);
        $formatted = number_format($amount, $decimals);
        
        if ($show_symbol) {
            return ($meta['symbol'] ?? '') . $formatted . ' ' . $code;
        }
        return $formatted;
    }
}

if (!function_exists('get_currency_disclaimer')) {
    function get_currency_disclaimer($invoiced_currency = 'USD', $preferred_currency = 'USD') {
        $inv = strtoupper($invoiced_currency ?: 'USD');
        $pref = strtoupper($preferred_currency ?: 'USD');
        if ($inv === $pref) {
            return "All amounts are billed and settled directly in {$inv}.";
        }
        return "Amounts shown in {$pref} are approximate conversions based on current benchmark rates. Official billing and settlement is in {$inv}.";
    }
}
