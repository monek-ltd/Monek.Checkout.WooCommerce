<?php

namespace Monek\Checkout\Infrastructure\WordPress\Blocks;

use Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry;

class BlockPaymentRegistrar
{
    public function register(PaymentMethodRegistry $registry): void
    {
        if (! class_exists(MonekBlocksIntegration::class)) {
            require_once MONEK_PLUGIN_DIR . 'Blocks/Monek_Blocks.php';
        }

        if (! class_exists(MonekBlocksIntegration::class)) {
            return;
        }

        $registry->register(new MonekBlocksIntegration());
    }
}
