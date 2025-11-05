<?php

namespace Monek\Checkout\Infrastructure\WordPress\Blocks;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

if (! defined('ABSPATH')) {
    exit;
}

final class MonekBlocksIntegration extends AbstractPaymentMethodType
{
    protected $name = 'monek-checkout';

    public function initialize()
    {
        $this->settings = get_option('woocommerce_' . $this->name . '_settings', []);
    }

    public function is_active(): bool
    {
        if (empty($this->settings)) {
            return false;
        }

        if (! isset($this->settings['enabled']) || 'yes' !== $this->settings['enabled']) {
            return false;
        }

        if (empty($this->settings['publishable_key'])) {
            return false;
        }

        return true;
    }

    public function get_payment_method_script_handles(): array
    {
        $sdkHandle = 'monek-checkout-sdk';
        if (! wp_script_is($sdkHandle, 'registered')) {
            wp_register_script($sdkHandle, 'https://checkout-js.monek.com/monek-checkout.iife.js', [], null, true);
        }

        $checkoutHandle = 'monek-embedded-checkout';
        if (! wp_script_is($checkoutHandle, 'registered')) {
            $checkoutPath = MONEK_PLUGIN_DIR . 'assets/js/monek-embedded-checkout.js';
            $checkoutUrl = MONEK_PLUGIN_URL . 'assets/js/monek-embedded-checkout.js';
            $checkoutVersion = file_exists($checkoutPath) ? filemtime($checkoutPath) : monek_get_plugin_version();

            wp_register_script(
                $checkoutHandle,
                $checkoutUrl,
                ['jquery', $sdkHandle],
                $checkoutVersion,
                true
            );
        }

        $styleHandle = 'monek-embedded-checkout';
        if (! wp_style_is($styleHandle, 'registered')) {
            $stylePath = MONEK_PLUGIN_DIR . 'assets/css/monek-checkout.css';
            $styleUrl = MONEK_PLUGIN_URL . 'assets/css/monek-checkout.css';
            $styleVersion = file_exists($stylePath) ? filemtime($stylePath) : monek_get_plugin_version();

            wp_register_style(
                $styleHandle,
                $styleUrl,
                [],
                $styleVersion
            );
        }

        if (! wp_script_is('monek-blocks-checkout', 'registered')) {
            $blocksPath = MONEK_PLUGIN_DIR . 'assets/js/monek-blocks-checkout.js';
            $blocksUrl = MONEK_PLUGIN_URL . 'assets/js/monek-blocks-checkout.js';
            $blocksVersion = file_exists($blocksPath) ? filemtime($blocksPath) : monek_get_plugin_version();

            wp_register_script(
                'monek-blocks-checkout',
                $blocksUrl,
                ['wc-blocks-registry', 'wc-settings', 'wp-element', $checkoutHandle],
                $blocksVersion,
                true
            );
        }

        return [$checkoutHandle, 'monek-blocks-checkout'];
    }

    public function get_payment_method_script_handles_for_admin(): array
    {
        return $this->get_payment_method_script_handles();
    }

    public function get_payment_method_data(): array
    {
        $gateways = WC() && WC()->payment_gateways() ? WC()->payment_gateways()->payment_gateways() : [];
        $gateway = isset($gateways[$this->name]) ? $gateways[$this->name] : null;

        $publishable = $gateway ? $gateway->get_option('publishable_key') : '';
        $showExpress = $gateway ? ($gateway->get_option('show_express', 'yes') === 'yes') : true;
        $debug = $gateway ? ($gateway->get_option('debug', 'no') === 'yes') : false;
        $stylingConfiguration = $gateway && method_exists($gateway, 'getStylingConfiguration')
            ? $gateway->getStylingConfiguration()
            : $this->get_default_styling_configuration();

        return [
            'title' => $gateway ? $gateway->get_title() : __('Monek Checkout', 'monek-checkout'),
            'description' => $gateway ? $gateway->get_description() : __('Secure payment powered by Monek.', 'monek-checkout'),
            'logoUrl' => MONEK_PLUGIN_URL . 'img/Monek-Logo100x12.png',
            'supports' => ['products'],
            'errorMessage' => __('We were unable to prepare your payment. Please try again.', 'monek-checkout'),
            'gatewayId' => $this->name,
            'publishableKey' => $publishable ?: '',
            'showExpress' => $showExpress,
            'currency' => get_woocommerce_currency(),
            'currencyNumeric' => '826',
            'currencyDecimals' => wc_get_price_decimals(),
            'countryNumeric' => '826',
            'orderDescription' => get_bloginfo('name'),
            'initialAmountMinor' => 0,
            'debug' => $debug,
            'strings' => [
                'token_error' => __('There was a problem preparing your payment. Please try again.', 'monek-checkout'),
            ],
            'themeMode' => $stylingConfiguration['themeMode'],
            'theme' => $stylingConfiguration['theme'],
            'styling' => $stylingConfiguration['styling'] ?? null,
            'customTheme' => $stylingConfiguration['customTheme'] ?? null,
        ];
    }

    private function get_default_styling_configuration(): array
    {
        return [
            'themeMode' => 'light',
            'theme' => 'light',
        ];
    }
}
