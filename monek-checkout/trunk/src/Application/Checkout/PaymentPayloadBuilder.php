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
            'idempotencyToken' => $this->storeContext->resolveIdempotencyToken($order),
            'source' => 'EmbeddedCheckout',
            'url' => home_url(),
            'basketDescription' => $this->buildBasketFromOrder($order),
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

    public function buildBasketFromOrder(WC_Order $order): string
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

            $items[] = $this->buildBasketItem(
                $product->get_sku() ?: (string) $product->get_id(),
                $item->get_name(),
                (int) $quantity,
                $line_total,
                $line_tax
            );
        }

        return $this->assembleBasket($items);
    }

    /**
     * Builds the basket structure from the WooCommerce cart. This is used for express payment flow that is initiated before a WooCommerce order exists.
     */
    public function buildBasketFromCart(\WC_Cart $cart): string
    {
        $items = [];

        foreach ( $cart->get_cart() as $cartItem ) {
            $product = $cartItem['data'] ?? null;
            if ( ! $product ) {
                continue;
            }

            $quantity   = (int) ($cartItem['quantity'] ?? 0);
            $line_total = (float) ($cartItem['line_total'] ?? 0);
            $line_tax   = (float) ($cartItem['line_tax'] ?? 0);

            $items[] = $this->buildBasketItem(
                $product->get_sku() ?: (string) $product->get_id(),
                $product->get_name(),
                $quantity,
                $line_total,
                $line_tax
            );
        }

        return $this->assembleBasket($items);
    }

    private function buildBasketItem(string $sku, string $description, int $quantity, float $lineTotal, float $lineTax): array
    {
        // Avoid division by zero
        $unitPrice = $quantity > 0 ? $lineTotal / $quantity : 0;

        // Tax rate calculation (approx)
        $taxRate = 0;
        if ( $lineTotal > 0 && $lineTax > 0 ) {
            $taxRate = round( ( $lineTax / $lineTotal ) * 100, 2 );
        }

        return [
            'sku'            => $sku,
            'commodityCode'  => '',
            'description'    => $description,
            'quantity'       => $quantity,
            'unitPrice'      => round( $unitPrice, 2 ),
            'unitOfMeasure'  => '',
            'total'          => round( $lineTotal, 2 ),
            'taxRate'        => $taxRate,
            'taxAmount'      => round( $lineTax, 2 ),
        ];
    }

    private function assembleBasket(array $items): string
    {
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