<?php

namespace Monek\Checkout\Application\Checkout;

use WC_Order;

class PaymentPayloadBuilder
{
    private CurrencyFormatter $currencyFormatter;
    private StoreContext $storeContext;

    public function __construct(CurrencyFormatter $currencyFormatter, StoreContext $storeContext)
    {
        $this->currencyFormatter = $currencyFormatter;
        $this->storeContext = $storeContext;
    }

    public function build(
        WC_Order $order,
        string $token,
        string $sessionIdentifier,
        string $expiry,
        string $paymentReference
    ): array {
        $currencyCode = $order->get_currency();
        $minorAmount = $this->currencyFormatter->toMinorUnits($order->get_total(), $currencyCode);
        $currencyNumericCode = $this->currencyFormatter->getNumericCurrencyCode($currencyCode);

        $billingFirstName = (string) $order->get_billing_first_name();
        $billingLastName = (string) $order->get_billing_last_name();
        $billingName = trim($billingFirstName . ' ' . $billingLastName);

        $billingPhone = $this->normalisePhoneNumber($order->get_billing_phone());

        $expiryMonth = substr($expiry, 0, 2);
        $expiryYear = substr($expiry, 3, 2);

        return [
            'sessionId' => $sessionIdentifier,
            'tokenId' => $token,
            'settlementType' => 'Auto',
            'cardEntry' => 'ECommerce',
            'intent' => 'Purchase',
            'order' => 'Checkout',
            'currencyCode' => $currencyNumericCode,
            'minorAmount' => $minorAmount,
            'countryCode' => $this->storeContext->getNumericCountryCode(),
            'card' => [
                'expiryMonth' => $expiryMonth,
                'expiryYear' => $expiryYear,
            ],
            'cardHolder' => array_filter([
                'name' => $billingName,
                'emailAddress' => $order->get_billing_email(),
                'phoneNumber' => $billingPhone,
                'billingStreet1' => $order->get_billing_address_1(),
                'billingStreet2' => $order->get_billing_address_2(),
                'billingCity' => $order->get_billing_city(),
                'billingPostcode' => $order->get_billing_postcode(),
            ], static function ($value) {
                return $value !== null && $value !== '';
            }),
            'storeCardDetails' => false,
            'idempotencyToken' => $this->storeContext->generateIdempotencyToken((int) $order->get_id()),
            'source' => 'EmbeddedCheckout',
            'url' => home_url(),
            'basketDescription' => sprintf(__('Order %s', 'monek-checkout'), $order->get_order_number()),
            'paymentReference' => $paymentReference,
        ];
    }

    private function normalisePhoneNumber(?string $phone): ?string
    {
        if (empty($phone)) {
            return $phone;
        }

        $digits = preg_replace('/\D+/', '', $phone);

        $digits = preg_replace('/^(?:44|0{2}44)/', '', $digits);

        if (!str_starts_with($digits, '0')) {
            $digits = "0$digits";
        }

        return $digits;
    }
}
