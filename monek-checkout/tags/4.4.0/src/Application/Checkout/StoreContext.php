<?php

namespace Monek\Checkout\Application\Checkout;

class StoreContext
{
    private const DEFAULT_COUNTRY_CODE = '826';
    private const PARTIAL_ORIGIN_ID = 'a6c921f4-8e00-4b11-99f4-';
    private const IDEMPOTENCY_META_KEY = '_monek_idempotency_token';

    public function getNumericCountryCode(): string
    {
        $baseLocation = function_exists('wc_get_base_location') ? wc_get_base_location() : [];
        $countryCode = isset($baseLocation['country']) ? $baseLocation['country'] : 'GB';

        $defaultCodes = [
            'GB' => '826',
            'US' => '840',
            'IE' => '372',
            'AU' => '036',
            'NZ' => '554',
            'CA' => '124',
            'FR' => '250',
            'DE' => '276',
        ];

        $codes = apply_filters('monek_country_numeric_codes', $defaultCodes, $countryCode);

        return $codes[$countryCode] ?? self::DEFAULT_COUNTRY_CODE;
    }

    public function resolveIdempotencyToken(\WC_Order $order): string
    {
        $existing = (string) $order->get_meta(self::IDEMPOTENCY_META_KEY);
        if ($existing !== '') {
            return $existing;
        }

        $version = monek_get_plugin_version();
        $token   = 'wc-' . str_replace('.', '', $version) . '-' . $order->get_id() . '-' . wp_generate_uuid4();
        $order->update_meta_data(self::IDEMPOTENCY_META_KEY, $token);
        $order->save_meta_data();
        return $token;
    }

    public function clearIdempotencyToken(\WC_Order $order): void
    {
        $order->delete_meta_data(self::IDEMPOTENCY_META_KEY);
        $order->save_meta_data();
    }

    public function buildOriginId(): string
    {
        $version = monek_get_plugin_version();

        return self::PARTIAL_ORIGIN_ID 
            . str_replace('.', '', $version) 
            . str_repeat('0', 14 - strlen($version));
    }
}
