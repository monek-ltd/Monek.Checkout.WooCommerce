<?php

namespace Monek\Checkout\Application\Checkout;

class CurrencyFormatter
{
    private const DEFAULT_NUMERIC_CODE = '826';

    public function toMinorUnits($amount, string $currencyCode): int
    {
        $decimals = $this->getDecimals($currencyCode);
        $amountFloat = (float) $amount;

        return (int) round($amountFloat * pow(10, $decimals));
    }

    public function getNumericCurrencyCode(string $currencyCode): string
    {
        $defaultCodes = [
            'GBP' => '826',
            'USD' => '840',
            'EUR' => '978',
            'AUD' => '036',
            'CAD' => '124',
            'NZD' => '554',
            'SEK' => '752',
            'NOK' => '578',
            'DKK' => '208',
            'CHF' => '756',
        ];

        $codes = apply_filters('monek_currency_numeric_codes', $defaultCodes, $currencyCode);

        return $codes[$currencyCode] ?? self::DEFAULT_NUMERIC_CODE;
    }

    private function getDecimals(string $currencyCode): int
    {
        $woocommerceDecimals = function_exists('wc_get_price_decimals')
            ? (int) wc_get_price_decimals()
            : 2;

        return (int) apply_filters('monek_currency_decimals', $woocommerceDecimals, $currencyCode);
    }
}
