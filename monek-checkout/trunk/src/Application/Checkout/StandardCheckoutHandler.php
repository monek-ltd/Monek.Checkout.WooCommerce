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
    private StoreContext $storeContext;
    private CurrencyFormatter $currencyFormatter;

    public function __construct(
        PaymentProcessor $paymentProcessor,
        Logger $logger,
        StoreContext $storeContext,
        ?CurrencyFormatter $currencyFormatter = null
    ) {
        $this->paymentProcessor = $paymentProcessor;
        $this->logger = $logger;
        $this->storeContext = $storeContext;
        $this->currencyFormatter = $currencyFormatter ?? new CurrencyFormatter();
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
        $this->assertAuthenticatedAmountMatchesOrder($request, $order);

        // Generate a WooCommerce style payment ref using the order ID, or fall back to the payment reference provided.
        $wooCommerceOrderReference = $order->get_id() ? 'WOO-ORDER-' . $order->get_id() : $request->getPaymentReference();

        $response = $this->paymentProcessor->process(
            $order,
            $request->getToken(),
            $request->getSessionIdentifier(),
            $request->getExpiry(),
            $wooCommerceOrderReference
        );

        if ($response['message']) {
            $order->add_order_note('Monek transaction response message: ' . $response['message']);
        }

        $order->update_meta_data('_monek_payment_reference', $wooCommerceOrderReference);
        $order->update_meta_data('_monek_transaction_id', $response['transactionId'] ?? '');

        $order->add_order_note(sprintf('Payment Reference: %s', $wooCommerceOrderReference));

        if (! $response['success']) {
            $this->storeContext->clearIdempotencyToken($order);
            $message = $response['message'] ?: __('Payment failed. Please try again.', 'monek-checkout');
            throw new \Exception(esc_html($message));
        }

        $order->update_meta_data('_monek_token', $request->getToken());
        $order->update_meta_data('_monek_session', $request->getSessionIdentifier());
        $order->update_meta_data('_monek_result', $response['raw'] ? wp_json_encode($response['raw']) : '');
        $order->payment_complete();
        $order->save();

        $redirectUrl = $order->get_checkout_order_received_url();

        $this->logger->info('Standard checkout successful', [
            'order_id' => $order->get_id(),
            'redirect' => $redirectUrl,
        ]);

        return $redirectUrl;
    }

    /**
     * Monek charges the amount the browser authenticated with 3DS, so that amount must equal
     * the order total. Refuse the payment when they differ (for example when tax or shipping
     * changed after the card was authenticated) rather than charge the wrong amount.
     *
     * @throws \Exception When the authenticated amount differs from the order total.
     */
    private function assertAuthenticatedAmountMatchesOrder(CheckoutRequest $request, WC_Order $order): void
    {
        $currencyCode = (string) $order->get_currency();
        $orderMinor = $this->currencyFormatter->toMinorUnits($order->get_total(), $currencyCode);
        $authenticatedMinor = $request->getAuthenticatedAmountMinor();

        $context = [
            'order_id' => $order->get_id(),
            'order_minor' => $orderMinor,
            'order_tax_minor' => $this->currencyFormatter->toMinorUnits($order->get_total_tax(), $currencyCode),
            'authenticated_minor' => $authenticatedMinor,
            'currency' => $currencyCode,
        ];

        if ($authenticatedMinor === null) {
            $this->logger->warning('Standard checkout: browser did not report the authenticated amount', $context);
            return;
        }

        if ($authenticatedMinor !== $orderMinor) {
            $this->logger->error('Standard checkout amount mismatch; payment not attempted', $context);
            $order->add_order_note(sprintf(
                'Monek payment not attempted: the amount authenticated in the browser (%s) differs from the order total (%s).',
                $this->currencyFormatter->toMajorUnits((string) $authenticatedMinor, $currencyCode),
                $this->currencyFormatter->toMajorUnits((string) $orderMinor, $currencyCode)
            ));
            throw new \Exception(esc_html__('Your order total changed. Please review it and try again.', 'monek-checkout'));
        }

        $this->logger->info('Standard checkout amounts match', $context);
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
