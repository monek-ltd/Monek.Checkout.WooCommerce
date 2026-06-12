<?php

namespace Monek\Checkout\Application\Checkout;

use Automattic\WooCommerce\Blocks\Payments\PaymentResult;
use Monek\Checkout\Domain\Checkout\CheckoutRequest;
use Monek\Checkout\Infrastructure\Logging\Logger;
use WC_Order;

class ExpressCheckoutHandler
{
    private Logger $logger;

    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
    }

    public function handle(CheckoutRequest $request, WC_Order $order, PaymentResult $result): void
    {
        $paymentReference = $request->getPaymentReference();
        if ($paymentReference === '') {
            $this->logger->error('Express checkout missing payment reference');
            throw new \Exception(__('Missing payment reference.', 'monek-checkout'));
        }

        $order->update_meta_data('_monek_session', $request->getSessionIdentifier());
        $order->update_meta_data('_monek_payment_reference', $paymentReference);
        $order->add_order_note(sprintf('Express payment reference set: %s', $paymentReference));
        $order->payment_complete();
        $order->save();

        $redirectUrl = $order->get_checkout_order_received_url();

        $this->logger->info('Express checkout successful', [
            'order_id' => $order->get_id(),
            'redirect' => $redirectUrl,
        ]);

        $result->set_status('success');
        $result->set_redirect_url($redirectUrl);
    }
}
