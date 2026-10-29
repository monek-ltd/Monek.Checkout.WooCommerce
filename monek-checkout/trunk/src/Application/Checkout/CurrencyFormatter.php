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

    public function toMajorUnits(string $amount, string $currencyCode): string
    {
        $decimals = $this->getDecimals($currencyCode);

        if ($decimals === 0) {
            return $amount;
        }

        $amount = trim($amount);
        $negative = str_starts_with($amount, '-');
        $digits = ltrim($negative ? substr($amount, 1) : $amount, '0');
        $digits = $digits === '' ? '0' : $digits;

        $digits = str_pad($digits, $decimals + 1, '0', STR_PAD_LEFT);

        $major = substr($digits, 0, -$decimals);
        $fraction = substr($digits, -$decimals);

        return ($negative ? '-' : '') . $major . '.' . $fraction;
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
