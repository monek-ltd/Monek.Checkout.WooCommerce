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
            'basketDescription' => $this->createBasketDetails($order),
            'paymentReference' => $paymentReference,
            'originId' => $this->storeContext->buildOriginId(),
            'sourceIpAddress' => $_SERVER['REMOTE_ADDR'] ?? null,
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

    private function createBasketDetails(WC_Order $order): string
    {
        $items = [];

        foreach ( $order->get_items() as $item_id => $item ) {

            $product = $item->get_product();
            if ( ! $product ) {
                continue;
            }

            $quantity     = $item->get_quantity();
            $line_total   = (float) $item->get_total();
            $line_tax     = (float) $item->get_total_tax();

            // Avoid division by zero
            $unit_price = $quantity > 0 ? $line_total / $quantity : 0;

            // Tax rate calculation (approx)
            $tax_rate = 0;
            if ( $line_total > 0 && $line_tax > 0 ) {
                $tax_rate = round( ( $line_tax / $line_total ) * 100, 2 );
            }

            $items[] = [
                'sku'            => $product->get_sku() ?: (string) $product->get_id(),
                'commodityCode'  => '',
                'description'    => $item->get_name(),
                'quantity'       => (int) $quantity,
                'unitPrice'      => round( $unit_price, 2 ),
                'unitOfMeasure'  => '',
                'total'          => round( $line_total, 2 ),
                'taxRate'        => $tax_rate,
                'taxAmount'      => round( $line_tax, 2 ),
            ];
        }

        $basket = [
            'taxType'   => 0,
            'items'     => $items,
            'discounts' => [],
            'taxes'     => [],
        ];

        $basketJson = wp_json_encode($basket);

        return base64_encode($basketJson);
    }
}
