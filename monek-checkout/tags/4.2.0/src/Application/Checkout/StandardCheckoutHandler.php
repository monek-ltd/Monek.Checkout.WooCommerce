<?php

namespace Monek\Checkout\Application\Checkout;

use Automattic\WooCommerce\Blocks\Payments\PaymentResult;
use Monek\Checkout\Domain\Checkout\CheckoutRequest;
use Monek\Checkout\Infrastructure\Logging\Logger;
use WC_Order;

class StandardCheckoutHandler
{
    private PaymentProcessor $paymentProcessor;
    private Logger $logger;

    public function __construct(PaymentProcessor $paymentProcessor, Logger $logger)
    {
        $this->paymentProcessor = $paymentProcessor;
        $this->logger = $logger;
    }

    public function handle(CheckoutRequest $request, WC_Order $order, PaymentResult $result): void
    {
        $this->assertRequiredDataPresent($request);

        $response = $this->paymentProcessor->process(
            $order,
            $request->getToken(),
            $request->getSessionIdentifier(),
            $request->getExpiry(),
            $request->getPaymentReference()
        );

        if (! $response['success']) {
            $message = $response['message'] ?: __('Payment failed. Please try again.', 'monek-checkout');
            throw new \Exception($message);
        }

        if ($response['auth_code']) {
            $order->add_order_note('Monek auth code: ' . $response['auth_code']);
        }

        $order->update_meta_data('_monek_token', $request->getToken());
        $order->update_meta_data('_monek_session', $request->getSessionIdentifier());
        $order->update_meta_data('_monek_result', $response['raw'] ? wp_json_encode($response['raw']) : '');
        $order->update_meta_data('_monek_payment_reference', $request->getPaymentReference());
        $order->payment_complete();
        $order->save();

        $redirectUrl = $order->get_checkout_order_received_url();

        $this->logger->info('Standard checkout successful', [
            'order_id' => $order->get_id(),
            'redirect' => $redirectUrl,
        ]);

        $result->set_status('success');
        $result->set_redirect_url($redirectUrl);
    }

    private function assertRequiredDataPresent(CheckoutRequest $request): void
    {
        if (
            $request->getToken() === ''
            || $request->getSessionIdentifier() === ''
            || $request->getExpiry() === ''
            || $request->getPaymentReference() === ''
        ) {
            throw new \Exception(__('Missing payment data. Please try again.', 'monek-checkout'));
        }
    }
}
