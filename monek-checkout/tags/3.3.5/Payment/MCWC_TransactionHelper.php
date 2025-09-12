<?php

/**
 * Class TransactionHelper - provides helper functions for transactions
 *
 * @package Monek
 */
class MCWC_TransactionHelper {
    
    /**
     * Convert the amount to minor currency unit
     *
     * @param float $decimal_number
     * @return int
     */
    public static function mcwc_convert_decimal_to_flat($decimal_number) : int
    {
        return (int) str_replace('.', '', $decimal_number);
    }

    /**
     * Get the currency code in ISO 4217 format
     * 
     * @return string
     */
    public static function mcwc_get_iso4217_currency_code()  : string
    {
        $country_codes = include 'Includes/MCWC_CurrencyCodes.php';
        $currency_code = get_woocommerce_currency();
        return $country_codes[$currency_code] ?? '';
    }

    /**
    * Trim the description to 15 chars and add elipses
    *
    * @return string
    */
    public static function mcwc_trim_description(string $description) : string
    {
        if (strlen($description) > 15) {
                $description = substr($description, 0, 15) . '...';
        }
        return $description;
    }
    
    /**
     * Strip apostrophes from all values.
     *
     * @param array $details
     * @return array
     */
    public static function mcwc_strip_apostrophes(array $details): array
    {
        foreach ($details as $key => $value) {
            $details[$key] = str_replace("&#039;", '', $value);
        }
        return $details;
    }
}