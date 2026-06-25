<?php

namespace Monek\Checkout\Application\Checkout;

use Automattic\WooCommerce\Blocks\Payments\PaymentResult;
use Monek\Checkout\Domain\Checkout\CheckoutRequest;
use Monek\Checkout\Infrastructure\Logging\Logger;
use WC_Order;

class ExpressCheckoutHandler
{
    private Logger $logger;
    private ExpressVerificationStore $verificationStore;
    private ExpressPaymentVerifier $verifier;
    private CurrencyFormatter $currencyFormatter;

    public function __construct(
        Logger $logger,
        ExpressVerificationStore $verificationStore,
        ExpressPaymentVerifier $verifier,
        CurrencyFormatter $currencyFormatter
    ) {
        $this->logger = $logger;
        $this->verificationStore = $verificationStore;
        $this->verifier = $verifier;
        $this->currencyFormatter = $currencyFormatter;
    }

    public function handle(CheckoutRequest $request, WC_Order $order, PaymentResult $result): void
    {
        $paymentReference = $request->getPaymentReference();
        if ($paymentReference === '') {
            $this->logger->error('Express checkout missing payment reference');
            throw new \Exception(__('Missing payment reference.', 'monek-checkout'));
        }

        if ($this->isVerificationRequired()) {
            $this->assertVerified($paymentReference, $order);
        } else {
            $this->logger->warning('Express checkout verification disabled via filter', [
                'reference' => $paymentReference,
            ]);
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

    private function assertVerified(string $paymentReference, WC_Order $order): void
    {
        $claims = $this->verificationStore->consume($paymentReference);
        if ($claims === null) {
            $this->logger->error('Express checkout not verified', ['reference' => $paymentReference]);
            throw new \Exception(__('Payment could not be verified. Please try again.', 'monek-checkout'));
        }
    
        $this->assertAmountMatches($claims, $order, $paymentReference);

        $transactionId = $this->verifier->readTransactionId($claims);
        if ($transactionId !== '') {
            $order->update_meta_data('_monek_transaction_id', $transactionId);
            $order->add_order_note(sprintf('Express payment verified (txn: %s).', $transactionId));
        } else {
            $order->add_order_note('Express payment verified.');
        }
    }

    private function assertAmountMatches(array $claims, WC_Order $order, string $paymentReference): void
    {
        $expectedMinor = $this->verifier->readMinorAmount($claims);
        if ($expectedMinor === null) {
            return;
        }

        $currencyCode = $order->get_currency();
        $orderMinor = $this->currencyFormatter->toMinorUnits($order->get_total(), $currencyCode);

        if ($expectedMinor !== $orderMinor) {
            $this->logger->error('Express checkout amount mismatch', [
                'reference' => $paymentReference,
                'verified_minor' => $expectedMinor,
                'order_minor' => $orderMinor,
            ]);
            throw new \Exception(__('Payment amount did not match the order. Please try again.', 'monek-checkout'));
        }

        $expectedCurrency = $this->verifier->readCurrencyCode($claims);
        if ($expectedCurrency === '') {
            return;
        }

        $orderCurrency = $this->currencyFormatter->getNumericCurrencyCode($currencyCode);
        if (! hash_equals($orderCurrency, $expectedCurrency)) {
            $this->logger->error('Express checkout currency mismatch', [
                'reference' => $paymentReference,
                'verified_currency' => $expectedCurrency,
                'order_currency' => $orderCurrency,
            ]);
            throw new \Exception(__('Payment currency did not match the order. Please try again.', 'monek-checkout'));
        }
    }

    private function isVerificationRequired(): bool
    {
        $required = true;

        if (function_exists('apply_filters')) {
            $required = (bool) apply_filters('monek_express_require_verification', $required);
        }

        return $required;
    }
}
