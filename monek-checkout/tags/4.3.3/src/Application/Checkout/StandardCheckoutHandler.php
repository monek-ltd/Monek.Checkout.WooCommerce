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
        $redirectUrl = $this->capture($request, $order);

        $result->set_status('success');
        $result->set_redirect_url($redirectUrl);
    }

    /**
     * Legacy (non-Blocks) entry point. Runs the same capture + order-completion
     * core as {@see handle()} but returns a plain array for WooCommerce's
     * classic process_payment() flow instead of mutating a Blocks PaymentResult.
     *
     * @return array{success:bool,message:?string,redirect:?string}
     */
    public function process(CheckoutRequest $request, WC_Order $order): array
    {
        try {
            $redirectUrl = $this->capture($request, $order);

            return [
                'success' => true,
                'message' => null,
                'redirect' => $redirectUrl,
            ];
        } catch (\Throwable $exception) {
            return [
                'success' => false,
                'message' => $exception->getMessage(),
                'redirect' => null,
            ];
        }
    }

    /**
     * Shared capture + order-completion core. Single source of truth for order
     * meta ({@see _monek_*}) and payment_complete().
     *
     * @return string The order-received redirect URL.
     * @throws \Exception When required data is missing or the capture fails.
     */
    private function capture(CheckoutRequest $request, WC_Order $order): string
    {
        $this->assertRequiredDataPresent($request);

        $response = $this->paymentProcessor->process(
            $order,
            $request->getToken(),
            $request->getSessionIdentifier(),
            $request->getExpiry(),
            $request->getPaymentReference()
        );

        if ($response['message']) {
            $order->add_order_note('Monek transaction response message: ' . $response['message']);
        }

        if (! $response['success']) {
            $message = $response['message'] ?: __('Payment failed. Please try again.', 'monek-checkout');
            throw new \Exception(esc_html($message));
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

        return $redirectUrl;
    }

    private function assertRequiredDataPresent(CheckoutRequest $request): void
    {
        if (
            $request->getToken() === ''
            || $request->getSessionIdentifier() === ''
            || $request->getExpiry() === ''
            || $request->getPaymentReference() === ''
        ) {
            throw new \Exception(esc_html__('Missing payment data. Please try again.', 'monek-checkout'));
        }
    }
}
