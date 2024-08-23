<?php

/**
 * Class TransactionHelper - provides helper functions for transactions
 *
 * @package Monek
 */
class TransactionHelper {
    
    /**
     * Convert the amount to minor currency unit
     *
     * @param float $decimal_number
     * @return int
     */
    public static function convert_decimal_to_flat($decimal_number) : int
    {
        return (int) str_replace('.', '', $decimal_number);
    }

    /**
     * Get the currency code in ISO 4217 format
     * 
     * @return string
     */
    public static function get_iso4217_currency_code()  : string
    {
        $country_codes = include 'Includes/CurrencyCodes.php';
        $currency_code = get_woocommerce_currency();
        return $country_codes[$currency_code] ?? '';
    }
}