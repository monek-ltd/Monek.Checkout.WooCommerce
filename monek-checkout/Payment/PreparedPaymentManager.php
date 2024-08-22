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
        $prepared_payment_request = new PreparedPaymentRequestBuilder()->build_request($order, $merchant_id, $country_code, $return_plugin_url, $purchase_description);

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
        if (!isset($_POST["woocommerce-process-checkout-nonce"])) {
            return false;
        }

        $nonce = $_POST["woocommerce-process-checkout-nonce"];
        return wp_verify_nonce($nonce, 'woocommerce-process_checkout');
    }
}