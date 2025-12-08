<?php

namespace Monek\Checkout\Infrastructure\WordPress\Status;

class OrderStatusRegistrar
{
    private const STATUS_KEY = 'wc-payment-confirmed';

    public function register(): void
    {
        register_post_status(self::STATUS_KEY, [
            'label' => _x('Payment Confirmed', 'Order status', 'monek-checkout'),
            'public' => true,
            'exclude_from_search' => false,
            'show_in_admin_all_list' => true,
            'show_in_admin_status_list' => true,
            'label_count' => _n_noop('Payment Confirmed <span class="count">(%s)</span>', 'Payment Confirmed <span class="count">(%s)</span>'),
        ]);
    }

    public function injectIntoStatuses(array $statuses): array
    {
        $orderedStatuses = [];
        foreach ($statuses as $key => $label) {
            $orderedStatuses[$key] = $label;
            if ($key === 'wc-processing') {
                $orderedStatuses[self::STATUS_KEY] = _x('Payment Confirmed', 'Order status', 'monek-checkout');
            }
        }

        return $orderedStatuses;
    }
}
