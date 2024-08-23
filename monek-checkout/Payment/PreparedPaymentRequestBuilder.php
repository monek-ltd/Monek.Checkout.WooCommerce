<?php

/**
 * Builds the prepared payment request for the payment gateway
 *
 * @package Monek
 */
class PreparedPaymentRequestBuilder 
{    
    private const PARTIAL_ORIGIN_ID = 'a6c921f4-8e00-4b11-99f4-';

    /**
     * Build the prepared payment request for the payment gateway
     *
     * @param WC_Order $order
     * @param string $merchant_id
     * @param string $country_code
     * @param string $return_plugin_url
     * @param string $purchase_description
     * @return array
     */
	public function build_request(WC_Order $order, string $merchant_id, string $country_code, 
        string $return_plugin_url, string $purchase_description) : array
    {
        $billing_amount = $order->get_total();
        
        update_post_meta($order->get_id(), 'idempotency_token', uniqid($order->get_id(), true));
        update_post_meta($order->get_id(), 'integrity_secret', uniqid($order->get_id(), true));

        $prepared_payment_request = [
            'MerchantID' => $merchant_id,
            'MessageType' => 'ESALE_KEYED',
            'Amount' => TransactionHelper::convert_decimal_to_flat($billing_amount),
            'CurrencyCode' => TransactionHelper::get_iso4217_currency_code(),
            'CountryCode' => $country_code,
            'Dispatch' => 'NOW',
            'ResponseAction' => 'REDIRECT',
            'RedirectUrl' => $return_plugin_url,
            'WebhookUrl' => $return_plugin_url,
            'PaymentReference' => $order->get_id(),
            'ThreeDSAction' => 'ACSDIRECT',
            'IdempotencyToken' => get_post_meta($order->get_id(), 'idempotency_token', true),
            'OriginID' => self::PARTIAL_ORIGIN_ID . str_replace('.', '', get_monek_plugin_version()) . str_repeat('0', 14 - strlen(get_monek_plugin_version())),
            'PurchaseDescription' => $purchase_description,
            'IntegritySecret' => get_post_meta($order->get_id(), 'integrity_secret', true),
            'Basket' => $this->generate_basket_base64($order),
            'ShowDeliveryAddress' => 'YES',
            'WPNonce' => wp_create_nonce('complete-payment_' . $order->get_id())
        ];

        return $this->generate_cardholder_detail_information($prepared_payment_request);
    }

    /**
     * Generate the basket summary for the prepared payment request
     * 
     * @param WC_Order $order
     * @return string
     */
    private function generate_basket_base64(WC_Order $order) : string
    {
        $order_items = $this->get_item_details($order);
        $basket = [
            'items' => []
        ];

        foreach ($order_items as $item) {
            $item_description = $item['product_name'] ?? '';
            if (strlen($item_description) > 15) {
                $item_description = substr($item_description, 0, 15) . '...';
            }
            $basket['items'][] = [
                'sku' => $item['sku'] ?? '',
                'description' => $item_description,
                'quantity' => $item['quantity'] ?? '',
                'unitPrice' => $item['price'] ?? '',
                'total' => $item['total'] ?? ''
            ];
        }

        $order_discounts = $this->get_order_discounts($order);
        if (!empty($order_discounts)) {
            $basket['discounts'] = [];
            foreach ($order_discounts as $discount) {
                $discount_description = $discount['description'] ?? '';
                if (strlen($discount_description) > 15) {
                    $discount_description = substr($discount_description, 0, 15) . '...';
                }
                $basket['discounts'][] = [
                    'code' => $discount['code'] ?? '',
                    'description' => $discount_description,
                    'amount' => $discount['amount'] ?? ''
                ];
            }
        }

        $order_taxes = $this->get_order_taxes($order);
        if (!empty($order_taxes)) {
            $basket['taxes'] = [];
            foreach ($order_taxes as $tax) {
                $tax_description = $tax['description'] ?? '';
                if (strlen($tax_description) > 15) {
                    $tax_description = substr($tax_description, 0, 15) . '...';
                }
                $basket['taxes'][] = [
                    'code' => $tax['code'] ?? '',
                    'description' => $tax_description,
                    'rate' => $tax['rate'] ?? '',
                    'amount' => $tax['amount'] ?? ''
                ];
            }
        }

        $order_delivery = $this->get_order_delivery($order);
        if (!empty($order_delivery)) {
            $basket['delivery'] = [
                'carrier' => $order_delivery[0]['carrier'] ?? '',
                'amount' => $order_delivery[0]['amount'] ?? ''
            ];
        }

        $basket_json = wp_json_encode($basket);
        return base64_encode($basket_json);
    }

    /**
     * Generate the cardholder detail information for the prepared payment request
     *
     * @param array $prepared_payment_request
     * @return array
     */
    private function generate_cardholder_detail_information(array $prepared_payment_request) : array
    {
        $countries = WC()->countries->countries;
    
        $billing_address = new BillingAddress();
        $billing_country_name = $billing_address->billing_country ?? '';
        $billing_country_code = array_search($billing_country_name, $countries);
        
        $cardholder_detail_information = [
            'BillingName' => "{$billing_address->billing_first_name} {$billing_address->billing_last_name}",
            'BillingCompany' => $billing_address->billing_company ?? '',
            'BillingLine1' => $billing_address->billing_address_1 ?? '',
            'BillingLine2' => $billing_address->billing_address_2 ?? '',
            'BillingCity' => $billing_address->billing_city ?? '',
            'BillingCounty' => $billing_address->billing_state ?? '',
            'BillingCountry' => $billing_address->billing_country ?? '',
            'BillingCountryCode' => $billing_country_code,
            'BillingPostcode' => $billing_address->billing_postcode ?? '',
            'EmailAddress' => $billing_address->billing_email ?? '',
            'PhoneNumber' => $billing_address->billing_phone ?? '',
        ];
    
        if (isset($billing_address->ship_to_different_address) && $billing_address->ship_to_different_address == 1) {
            $shipping_address = new ShippingAddress();

            $delivery_country_name = $shipping_address->shipping_country ?? '';
            $delivery_country_code = array_search($delivery_country_name, $countries);    

            $cardholder_detail_information['DeliveryName'] = isset($shipping_address->shipping_first_name) && isset($shipping_address->shipping_last_name) ? "{$shipping_address->shipping_first_name} {$shipping_address->shipping_last_name}" : '';
            $cardholder_detail_information['DeliveryCompany'] = $shipping_address->shipping_company ?? '';
            $cardholder_detail_information['DeliveryLine1'] = $shipping_address->shipping_address_1 ?? '';
            $cardholder_detail_information['DeliveryLine2'] = $shipping_address->shipping_address_2 ?? '';
            $cardholder_detail_information['DeliveryCity'] = $shipping_address->shipping_city ?? '';
            $cardholder_detail_information['DeliveryCounty'] = $shipping_address->shipping_state ?? '';
            $cardholder_detail_information['DeliveryCountry'] = $shipping_address->shipping_country ?? '';
            $cardholder_detail_information['DeliveryCountryCode'] = $delivery_country_code;
            $cardholder_detail_information['DeliveryPostcode'] = $shipping_address->shipping_postcode ?? '';
        }
        else {
            $cardholder_detail_information['DeliveryIsBilling'] = "YES";
        }

        return array_merge($prepared_payment_request, $cardholder_detail_information);
    }

    /**
     * Get the details of the items in the order
     *
     * @param WC_Order $order
     * @return array
     */
    private function get_item_details(WC_Order $order) : array
    {
        $line_items = $order->get_items();
        $items_details = [];
    
        foreach ($line_items as $item) {
            $product = $item->get_product();
            $product_name = $product->get_name();
            $product_id = $product->get_id();
            $sku = $product->get_sku();
            $quantity = $item->get_quantity();
            $price = $product->get_price();
            $subtotal = $item->get_subtotal();
            $total = $item->get_total();
            $total_tax = $item->get_total_tax();
    
            $items_details[] = [
                'product_name' => $product_name,
                'product_id' => $product_id,
                'sku' => $sku,
                'quantity' => $quantity,
                'price' => $price,
                'subtotal' => $subtotal,
                'total' => $total,
                'total_tax' => $total_tax
            ];
        }
    
        return $items_details;
    }

    /**
     * Get the delivery details of the order
     *
     * @param WC_Order $order
     * @return array
     */
    private function get_order_delivery(WC_Order $order) : array
    {
        $shipping_methods = $order->get_shipping_methods();
        $delivery = [];
    
        if (!empty($shipping_methods)) {
            foreach ($shipping_methods as $shipping_method) {
                $delivery[] = [
                    'carrier' => $shipping_method->get_method_title(),
                    'amount' => $shipping_method->get_total()
                    //TrackingReference and TrackingUrl currently unavailable
                ];
            }
        }
    
        return $delivery;
    }

    /**
     * Get the discounts applied to the order
     *
     * @param WC_Order $order
     * @return array
     */
    private function get_order_discounts(WC_Order $order) : array
    {
        $wc_discounts = new WC_Discounts( WC()->cart );
        $discounts = [];
    
        $applied_coupons = $order->get_coupon_codes();
    
        foreach ($applied_coupons as $coupon_code) {
            $coupon = new WC_Coupon($coupon_code);
    
            if ($wc_discounts -> is_coupon_valid($coupon)) {
                $discounts[] = array(
                    'code' => $coupon_code,
                    'description' => $coupon->get_description(),
                    'amount' => $coupon->get_amount()
                );
            }
        }
    
        return $discounts;
    }

    /**
     * Get the taxes applied to the order
     *
     * @param WC_Order $order
     * @return array
     */
    private function get_order_taxes(WC_Order $order) : array
    {
        $taxes = [];
        $tax_lines = $order->get_taxes();
    
        foreach ($tax_lines as $tax) {
            $data = $tax->get_data();
            $taxes[] = [
                'code' => $data['rate_code'],
                'description' => $data['label'],
                'rate' => $data['rate_percent'],
                'amount' => $data['tax_total']
            ];
        }
    
        return $taxes;
    }    
}