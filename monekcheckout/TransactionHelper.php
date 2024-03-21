<?php

class TransactionHelper {
    
    public static function convert_decimal_to_flat($decimal_number) {
        $flat_number = (int) str_replace('.', '', $decimal_number);
        return $flat_number;
    }

    public static function get_iso4217_currency_code() {
        $country_codes = include('CurrencyCodes.php');
        $currency_code = get_woocommerce_currency();
        return isset($country_codes[$currency_code]) ? $country_codes[$currency_code] : '';
    }
}