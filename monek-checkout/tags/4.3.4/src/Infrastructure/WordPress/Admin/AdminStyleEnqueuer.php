<?php

namespace Monek\Checkout\Infrastructure\WordPress\Admin;

class AdminStyleEnqueuer
{
    public function outputStatusStyles(): void
    {
        echo '<style>';
        echo '.order-status.status-payment-confirmed {';
        echo 'background: #c6e1c6;';
        echo 'color: #5b841b;';
        echo '}';
        echo '.order-status.status-payment-confirmed:before {';
        echo 'color: #5b841b;';
        echo '}';
        echo '</style>';
    }
}
