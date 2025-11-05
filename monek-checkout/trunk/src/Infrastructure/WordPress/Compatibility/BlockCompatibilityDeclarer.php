<?php

namespace Monek\Checkout\Infrastructure\WordPress\Compatibility;

class BlockCompatibilityDeclarer
{
    public function declareCompatibility(): void
    {
        if (! class_exists('\\Automattic\\WooCommerce\\Utilities\\FeaturesUtil')) {
            return;
        }

        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
            'cart_checkout_blocks',
            MONEK_PLUGIN_FILE,
            true
        );
    }
}
