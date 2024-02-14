<?php

class PaymentProcessor {
    private const MAXIMUM_ITEMS = 6; //We currently only support 6 items
    private const ORIGIN_ID = '0b26b731-856c-4925-a799-01c364c78fa6';

    private $is_test_mode_active;

    public function __construct($is_test_mode_active) {
        $this->is_test_mode_active = $is_test_mode_active;
    }

    public function create_prepared_payment($order, $merchant_id, $country_code, $return_plugin_url, $purchase_description) {
        $body_data = $this->prepare_body_data($order, $merchant_id, $country_code, $return_plugin_url, $purchase_description);

        return $this->send_payment_request($body_data);
    }

    private function generate_item_details_body($request_body, $order){
        $order_items = $this->get_item_details($order);
        $item_details_array = array();
    
        for ($item_index = 0; $item_index < self::MAXIMUM_ITEMS; $item_index++) {
            $item_number = $item_index + 1;
            $item_details_array["LIDItem{$item_number}Quantity"] = isset($order_items[$item_index]['quantity']) ? $order_items[$item_index]['quantity'] : '';
            $item_details_array["LIDItem{$item_number}Description"] = isset($order_items[$item_index]['product_name']) ? $order_items[$item_index]['product_name'] : '';
            $item_details_array["LIDItem{$item_number}GrossValue"] = isset($order_items[$item_index]['total']) ? TransactionHelper::convert_decimal_to_flat($order_items[$item_index]['total']) : '';
        }
    
        $merged_array = array_merge($request_body, $item_details_array);
    
        return $merged_array;
    }

    private function generate_qa_information($request_body){
        $qa_information = array(
            'QAName' => $_POST['billing_first_name'].' '.$_POST['billing_last_name'],
            'QAAddress' => $_POST['billing_address_1'].' '.$_POST['billing_address_2'].' '.$_POST['billing_city'].' '.$_POST['billing_state'].' '.$_POST['billing_country'],
            'QAPostcode' => isset($_POST['billing_postcode']) ? $_POST['billing_postcode'] : '',
            'QAEmailAddress' => isset($_POST['billing_email']) ? $_POST['billing_email'] : '',
            'QAPhoneNumber' => isset($_POST['billing_phone']) ? $_POST['billing_phone'] : '',
        );
        
        $merged_array = array_merge($request_body, $qa_information);
        
        return $merged_array;
    }
    
    private function get_ipay_prepare_url() {
        $testUrl = 'https://staging.monek.com/Secure/iPayPrepare.ashx';
        $liveUrl = 'https://elite.monek.com/Secure/iPayPrepare.ashx';
    
        return $this->is_test_mode_active ? $testUrl : $liveUrl;
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

    private function prepare_body_data($order, $merchant_id, $country_code, $return_plugin_url, $purchase_description) {
        $billing_amount = $order->get_total();

        $body_data = array(
            'MerchantID' => $merchant_id,
            'MessageType' => 'ESALE_KEYED',
            'Amount' => TransactionHelper::convert_decimal_to_flat($billing_amount),
            'CurrencyCode' => TransactionHelper::get_iso4217_currency_code(),
            'CountryCode' => $country_code,
            'Dispatch' => 'NOW',
            'ResponseAction' => 'REDIRECT',
            'RetOKAddress' => $return_plugin_url,
            'RetNotOKAddress' => $return_plugin_url,
            'PaymentReference' => $order->get_id(),
            'ShowPayPal' => 'YES',
            'ThreeDSAction' => 'ACSDIRECT',
            'IdempotencyToken' => uniqid(null, true),
            'OriginID' => self::ORIGIN_ID,
            'PurchaseDescription' => $purchase_description
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

