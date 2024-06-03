<?php

class PaymentProcessor {
    // Use version to complete ORIGIN_ID UID
    private const PARTIAL_ORIGIN_ID = 'a6c921f4-8e00-4b11-99f4-';

    private $is_test_mode_active;

    public function __construct($is_test_mode_active) {
        $this->is_test_mode_active = $is_test_mode_active;
    }    

    public function create_prepared_payment($order, $merchant_id, $country_code, $return_plugin_url, $purchase_description) {
        $body_data = $this->prepare_payment_request_body_data($order, $merchant_id, $country_code, $return_plugin_url, $purchase_description);

        return $this->send_payment_request($body_data);
    }

   private function generate_basket_base64($order){
        $order_items = $this->get_item_details($order);
        $basket = array(
            'items' => array()
        );

        foreach ($order_items as $item) {
            $basket['items'][] = array(
                'sku' => isset($item['sku']) ? $item['sku'] : '',
                'description' => isset($item['product_name']) ? $item['product_name'] : '',
                'quantity' => isset($item['quantity']) ? $item['quantity'] : '',
                'unitPrice' => isset($item['price']) ? $item['price'] : '',
                'total' => isset($item['total']) ? $item['total'] : ''
            );
        }

        $order_discounts = $this->get_order_discounts($order);
        if (!empty($order_discounts)) {
            $basket['discounts'] = array();
            foreach ($order_discounts as $discount) {
                $basket['discounts'][] = array(
                    'code' => isset($discount['code']) ? $discount['code'] : '',
                    'description' => isset($discount['description']) ? $discount['description'] : '',
                    'amount' => isset($discount['amount']) ? $discount['amount'] : ''
                );
            }
        }

        $order_taxes = $this->get_order_taxes($order);
        if (!empty($order_taxes)) {
            $basket['taxes'] = array();
            foreach ($order_taxes as $tax) {
                $basket['taxes'][] = array(
                    'code' => isset($tax['code']) ? $tax['code'] : '',
                    'description' => isset($tax['description']) ? $tax['description'] : '',
                    'rate' => isset($tax['rate']) ? $tax['rate'] : '',
                    'amount' => isset($tax['amount']) ? $tax['amount'] : ''
                );
            }
        }

        $order_delivery = $this->get_order_delivery($order);
        if (!empty($order_delivery)) {
            $basket['delivery'] = array(
                'carrier' => isset($order_delivery[0]['carrier']) ? $order_delivery[0]['carrier'] : '',
                'amount' => isset($order_delivery[0]['amount']) ? $order_delivery[0]['amount'] : ''
            );
        }

        $basket_json = json_encode($basket);
        $basket_base64 = base64_encode($basket_json);

        return $basket_base64;
    }

    private function generate_cardholder_detail_information($request_body){
        $countries = WC()->countries->countries;
    
        $billing_country_name = isset($_POST['billing_country']) ? $_POST['billing_country'] : '';
        $billing_country_code = array_search($billing_country_name, $countries);
        
        $cardholder_detail_information = array(
            'BillingName' => $_POST['billing_first_name'] . ' ' . $_POST['billing_last_name'],
            'BillingCompany' => isset($_POST['billing_company']) ? $_POST['billing_company'] : '',
            'BillingLine1' => isset($_POST['billing_address_1']) ? $_POST['billing_address_1'] : '',
            'BillingLine2' => isset($_POST['billing_address_2']) ? $_POST['billing_address_2'] : '',
            'BillingCity' => isset($_POST['billing_city']) ? $_POST['billing_city'] : '',
            'BillingCounty' => isset($_POST['billing_state']) ? $_POST['billing_state'] : '',
            'BillingCountry' => isset($_POST['billing_country']) ? $_POST['billing_country'] : '',
            'BillingCountryCode' => $billing_country_code,
            'BillingPostcode' => isset($_POST['billing_postcode']) ? $_POST['billing_postcode'] : '',
            'EmailAddress' => isset($_POST['billing_email']) ? $_POST['billing_email'] : '',
            'PhoneNumber' => isset($_POST['billing_phone']) ? $_POST['billing_phone'] : '',
        );
        
        $merged_array = array_merge($request_body, $qa_information);
        
        return $merged_array;
    }

    private function get_ipay_prepare_url() {
        $ipay_prepare_extension = 'iPayPrepare.ashx';
        return ($this->is_test_mode_active ? TransactDirectGateway::$staging_url : TransactDirectGateway::$elite_url) . $ipay_prepare_extension;
    }

    private function get_item_details($order) {
        $line_items = $order->get_items();
        $items_details = array();

        $count = 0;
        foreach ($line_items as $item_id => $item) {
            $count++;

            $product = $item->get_product();
            $product_name = $product->get_name();
            $quantity = $item->get_quantity();
            $total = $item->get_total();

            $items_details[] = array(
                'product_name' => $product_name,
                'quantity' => $quantity,
                'total' => $total
            );

            if ($count >= self::MAXIMUM_ITEMS) {
                break;
            }
        }

        return $items_details;
    }

    private function prepare_payment_request_body_data($order, $merchant_id, $country_code, $return_plugin_url, $purchase_description) {
        $billing_amount = $order->get_total();
        
        update_post_meta($order->get_id(), 'idempotency_token', uniqid($order->get_id(), true));
        update_post_meta($order->get_id(), 'integrity_secret', uniqid($order->get_id(), true));

        $body_data = array(
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
            'ShowPayPal' => 'YES',
            'ThreeDSAction' => 'ACSDIRECT',
            'IdempotencyToken' => get_post_meta($order->get_id(), 'idempotency_token', true),
            'OriginID' => self::PARTIAL_ORIGIN_ID . str_replace('.', '', get_monek_plugin_version()) . str_repeat('0', 14 - strlen(get_monek_plugin_version())),
            'PurchaseDescription' => $purchase_description,
            'IntegritySecret' => get_post_meta($order->get_id(), 'integrity_secret', true)
        );

        $body_data = $this->generate_qa_information($body_data);
        $body_data = $this->generate_item_details_body($body_data, $order);

        return $body_data;
    }

    private function send_payment_request($body_data) {
        $prepared_payment_url = $this->get_ipay_prepare_url();

        return wp_remote_post($prepared_payment_url, array(
            'body' => http_build_query($body_data),
        ));
    }
}

