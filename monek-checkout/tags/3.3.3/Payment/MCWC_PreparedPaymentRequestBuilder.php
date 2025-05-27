<?php

/**
 * Builds the prepared payment request for the payment gateway
 *
 * @package Monek
 */
class MCWC_PreparedPaymentRequestBuilder 
{    
    private const PARTIAL_ORIGIN_ID = 'a6c921f4-8e00-4b11-99f4-';

    private bool $show_google_pay;

    public function __construct($show_google_pay) 
    {
        $this->show_google_pay = $show_google_pay;
    }

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
	public function mcwc_build_request(WC_Order $order, string $merchant_id, string $country_code, 
        string $return_plugin_url, string $purchase_description) : array
    {
        $billing_amount = $order->get_total();
        
        update_post_meta($order->get_id(), 'idempotency_token', uniqid($order->get_id(), true));
        update_post_meta($order->get_id(), 'integrity_secret', uniqid($order->get_id(), true));

        $prepared_payment_request = [
            'MerchantID' => $merchant_id,
            'MessageType' => 'ESALE_KEYED',
            'Amount' => MCWC_TransactionHelper::mcwc_convert_decimal_to_flat($billing_amount),
            'CurrencyCode' => MCWC_TransactionHelper::mcwc_get_iso4217_currency_code(),
            'CountryCode' => $country_code,
            'Dispatch' => 'NOW',
            'ResponseAction' => 'REDIRECT',
            'RedirectUrl' => $return_plugin_url,
            'WebhookUrl' => $return_plugin_url,
            'PaymentReference' => $order->get_id(),
            'ThreeDSAction' => 'ACSDIRECT',
            'IdempotencyToken' => get_post_meta($order->get_id(), 'idempotency_token', true),
            'OriginID' => self::PARTIAL_ORIGIN_ID . str_replace('.', '', mcwc_get_monek_plugin_version()) . str_repeat('0', 14 - strlen(mcwc_get_monek_plugin_version())),
            'PurchaseDescription' => $purchase_description,
            'IntegritySecret' => get_post_meta($order->get_id(), 'integrity_secret', true),
            'Basket' => $this->mcwc_generate_basket_base64($order),
            'ShowDeliveryAddress' => 'YES',
            'ShowGooglePay' => $this->show_google_pay ? 'YES' : 'NO',
            'WPNonce' => wp_create_nonce('complete-payment_' . $order->get_id()),
            'Callback' => 'true',
        ];

        return $this->mcwc_generate_cardholder_detail_information($prepared_payment_request);
    }

    /**
     * Generate the basket summary for the prepared payment request
     * 
     * @param WC_Order $order
     * @return string
     */
    private function mcwc_generate_basket_base64(WC_Order $order) : string
    {
        $tax_display_cart = get_option('woocommerce_tax_display_cart');
        $basket = [
            'taxType' => $tax_display_cart === 'incl' ? 'inclusive' : 'exclusive',
            'items' => $this->mcwc_get_item_details($order, $tax_display_cart),
            'discounts' => $this->mcwc_get_order_discounts($order, $tax_display_cart),
            'delivery' => $this->mcwc_get_order_delivery($order, $tax_display_cart)
        ];
        
        $basket = array_filter($basket, function($value) {
            return !empty($value);
        });

        $basket_json = wp_json_encode($basket);
        return base64_encode($basket_json);
    }

    /**
     * Generate the cardholder detail information for the prepared payment request
     *
     * @param array $prepared_payment_request
     * @return array
     */
    private function mcwc_generate_cardholder_detail_information(array $prepared_payment_request) : array
    {
        $countries = WC()->countries->countries;
    
        $billing_address = new MCWC_BillingAddress();
        $billing_country_name = $billing_address->country ?? '';
        $billing_country_code = array_search($billing_country_name, $countries);
        
        $cardholder_detail_information = [
            'BillingName' => "{$billing_address->first_name} {$billing_address->last_name}",
            'BillingCompany' => $billing_address->company ?? '',
            'BillingLine1' => $billing_address->address_1 ?? '',
            'BillingLine2' => $billing_address->address_2 ?? '',
            'BillingCity' => $billing_address->city ?? '',
            'BillingCounty' => $billing_address->state ?? '',
            'BillingCountry' => $billing_address->country ?? '',
            'BillingCountryCode' => $billing_country_code,
            'BillingPostcode' => $billing_address->postcode ?? '',
            'EmailAddress' => $billing_address->email ?? '',
            'PhoneNumber' => $billing_address->phone ?? '',
        ];
    
        if (isset($billing_address->ship_to_different_address) && $billing_address->ship_to_different_address == 1) {
            $shipping_address = new MCWC_ShippingAddress();

            $delivery_country_name = $shipping_address->country ?? '';
            $delivery_country_code = array_search($delivery_country_name, $countries);    

            $cardholder_detail_information['DeliveryName'] = "{$shipping_address->first_name} {$shipping_address->last_name}";
            $cardholder_detail_information['DeliveryCompany'] = $shipping_address->company ?? '';
            $cardholder_detail_information['DeliveryLine1'] = $shipping_address->address_1 ?? '';
            $cardholder_detail_information['DeliveryLine2'] = $shipping_address->address_2 ?? '';
            $cardholder_detail_information['DeliveryCity'] = $shipping_address->city ?? '';
            $cardholder_detail_information['DeliveryCounty'] = $shipping_address->state ?? '';
            $cardholder_detail_information['DeliveryCountry'] = $shipping_address->country ?? '';
            $cardholder_detail_information['DeliveryCountryCode'] = $delivery_country_code;
            $cardholder_detail_information['DeliveryPostcode'] = $shipping_address->postcode ?? '';
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
    private function mcwc_get_item_details(WC_Order $order, string $tax_display): array
    {
        $line_items = $order->get_items();
        $items_details = [];

        foreach ($line_items as $item) {
            $product = $item->get_product();
            $quantity = $item->get_quantity();
            $unit_price = $tax_display === 'incl' ? wc_get_price_including_tax($product) : wc_get_price_excluding_tax($product);
            $line_total = $item->get_total();
            $line_tax = $item->get_total_tax();
            $tax_rate = $this->get_item_tax_rate($item);

            $items_details[] = [
                'sku' => $product->get_sku(),
                'commodityCode' => $product->get_attribute('pa_commodity_code') ?? '',
                'description' => MCWC_TransactionHelper::mcwc_trim_description($product->get_name()),
                'quantity' => $quantity,
                'unitPrice' => round($unit_price, 2),
                'unitOfMeasure' => $product->get_attribute('pa_unit_of_measure') ?? '',
                'total' => $tax_display === 'incl' ? round($line_total + $line_tax, 2) : round($line_total, 2),
                'taxRate' => $tax_rate,
                'taxAmount' => round($line_tax, 2),
            ];
        }

        return $items_details;
    }

    /**
     * Get the tax rate for an item
     *
     * @param WC_Order_Item_Product $item
     * @return float
     */
    private function get_item_tax_rate($item): float
    {
        $taxes = $item->get_taxes();
        if (!empty($taxes['total'])) {
            $rate_id = array_key_first($taxes['total']);
            if ($rate_id && isset($taxes['total'][$rate_id])) {
                return (float) WC_Tax::get_rate_percent($rate_id);
            }
        }
        return 0.0;
    }



    /**
     * Get the delivery details of the order
     *
     * @param WC_Order $order
     * @return array
     */
    private function mcwc_get_order_delivery(WC_Order $order, string $tax_display): array
    {
        $delivery = [];
        foreach ($order->get_shipping_methods() as $shipping_method) {
            $amount = $shipping_method->get_total();
            
            if($amount <= 0) {
                continue; // Skip if shipping amount is zero or negative
            }

            $tax = $shipping_method->get_total_tax();
            $delivery = [
                'carrier' => $shipping_method->get_method_title(),
                'amount' => $tax_display === 'incl' ? round($amount + $tax, 2) : round($amount, 2),
                'taxRate' =>  $this->get_shipping_tax_rate($shipping_method),
                'taxAmount' => round($tax, 2) 
            ];
        }
        return $delivery;
    }

    /**
     * Get the tax rate for a shipping method
     *
     * @param WC_Order_Item_Shipping $shipping_method
     * @return float
     */
    private function get_shipping_tax_rate(WC_Order_Item_Shipping $shipping_method): float
    {
        $taxes = $shipping_method->get_taxes();
        if (!empty($taxes['total'])) {
            $rate_id = array_key_first($taxes['total']);
            if ($rate_id && isset($taxes['total'][$rate_id])) {
                return (float) WC_Tax::get_rate_percent($rate_id);
            }
        }
        return 0.0;
    }

    /**
     * Get the discounts applied to the order
     *
     * @param WC_Order $order
     * @return array
     */
    private function mcwc_get_order_discounts(WC_Order $order, string $tax_display): array
    {
        $discounts = [];

        foreach ($order->get_coupon_codes() as $code) {
            $coupon = new WC_Coupon($code);
            $amount = $order->get_discount_total();
            $tax = $order->get_discount_tax();

            $discounts[] = [
                'code' => $code,
                'description' => MCWC_TransactionHelper::mcwc_trim_description($coupon->get_description()),
                'amount' => $tax_display === 'incl' ? round($amount + $tax, 2) : round($amount, 2),
                'taxRate' => $this->get_discount_tax_rate($order),
                'taxAmount' => round($tax, 2),
            ];
        }

        return $discounts;
    }

    /**
     * Summary of get_discount_tax_rate
     * 
     * @param WC_Order $order
     * @return float
     */
    private function get_discount_tax_rate(WC_Order $order): float
    {
        $discount_total = $order->get_discount_total();
        $discount_tax = $order->get_discount_tax();

        if ($discount_total > 0) {
            return round(($discount_tax / $discount_total) * 100, 2);
        }

        return 0.0;
    }

    /**
     * Get the taxes applied to the order
     *
     * @param WC_Order $order
     * @return array
     */
    private function mcwc_get_order_taxes(WC_Order $order) : array
    {
        $taxes = [];
        $tax_lines = $order->get_taxes();
    
        foreach ($tax_lines as $tax) {
            $data = $tax->get_data();
            $taxes[] = [
                'code' => $data['rate_code'] ?? '',
                'description' => MCWC_TransactionHelper::mcwc_trim_description($data['label']),
                'rate' => $data['rate_percent'] ?? '',
                'amount' => ($data['tax_total'] ?? 0) == 0 ? ($data['shipping_tax_total'] ?? '') : $data['tax_total']
            ];
        }
    
        return $taxes;
    }    
}