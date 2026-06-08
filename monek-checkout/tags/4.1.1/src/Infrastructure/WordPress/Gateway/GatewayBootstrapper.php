<?php

namespace Monek\Checkout\Infrastructure\WordPress\Gateway;

class GatewayBootstrapper
{
    public function bootstrap(): void
    {
        if (! class_exists('WC_Payment_Gateway')) {
            return;
        }

        add_filter('woocommerce_payment_gateways', [$this, 'registerGateway']);
    }

    public function registerGateway(array $gateways): array
    {
        if (! class_exists(MonekCheckoutGateway::class)) {
            require_once MONEK_PLUGIN_DIR . 'MonekCheckoutGateway.php';
        }

        if (! class_exists(MonekCheckoutGateway::class)) {
            return $gateways;
        }

        $gateways[] = MonekCheckoutGateway::class;

        return $gateways;
    }
}
