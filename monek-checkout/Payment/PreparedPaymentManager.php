<?php

class PreparedPaymentManager {
	
    private $is_test_mode_active;

    public function __construct($is_test_mode_active) {
        $this->is_test_mode_active = $is_test_mode_active;
    }    

    public function create_prepared_payment($order, $merchant_id, $country_code, $return_plugin_url, $purchase_description) {
        if (!$this->verify_nonce()) {
            return new WP_Error('invalid_nonce', __('Invalid nonce', 'monek-payment-gateway'));
        }
        $request_builder = new PreparedPaymentRequestBuilder();
        $prepared_payment_request = $request_builder->build_request($order, $merchant_id, $country_code, $return_plugin_url, $purchase_description);

        return $this->send_prepared_payment_request($prepared_payment_request);
    }
    
    private function get_ipay_prepare_url() {
        $ipay_prepare_extension = 'iPayPrepare.ashx';
        return ($this->is_test_mode_active ? MonekGateway::$staging_url : MonekGateway::$elite_url) . $ipay_prepare_extension;
    }

    private function send_prepared_payment_request($prepared_payment_request) {
        $prepared_payment_url = $this->get_ipay_prepare_url();

        return wp_remote_post($prepared_payment_url, array(
            'body' => http_build_query($prepared_payment_request),
        ));
    }

    private function verify_nonce() {
        $nonce = filter_input(INPUT_POST, "woocommerce-process-checkout-nonce", FILTER_SANITIZE_STRING);

        if (!$nonce) {
            return false;
        }

        return wp_verify_nonce($nonce, 'woocommerce-process_checkout');
    }
}