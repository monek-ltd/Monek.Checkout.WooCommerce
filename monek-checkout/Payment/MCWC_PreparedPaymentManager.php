<?php

/**
 * Handles the creation of prepared payment requests and sending them to the payment gateway
 *
 * @package Monek
 */
class MCWC_PreparedPaymentManager 
{
    private bool $is_test_mode_active;

    /**
     * @param bool $is_test_mode_active
     */
    public function __construct(bool $is_test_mode_active) 
    { 
        $this->is_test_mode_active = $is_test_mode_active;
    }    

    /**
     * Create a prepared payment request and send it to the payment gateway
     *
     * @param WC_Order $order
     * @param string $merchant_id
     * @param string $country_code
     * @param string $return_plugin_url
     * @param string $purchase_description
     * @return array|WP_Error
     */
    public function mcwc_create_prepared_payment(WC_Order $order, string $merchant_id,  string $country_code,  
        string $return_plugin_url, string $purchase_description)
    {
        if (!$this->mcwc_verify_nonce()) {
            return new WP_Error('invalid_nonce', __('Invalid nonce', 'monek-checkout'));
        }
        $request_builder = new MCWC_PreparedPaymentRequestBuilder();
        $prepared_payment_request = $request_builder->mcwc_build_request($order, $merchant_id, $country_code, $return_plugin_url, $purchase_description);

        return $this->mcwc_send_prepared_payment_request($prepared_payment_request);
    }
    
    /**
     * Get the URL for the iPay prepare endpoint 
     *
     * @return string
     */
    private function mcwc_get_ipay_prepare_url() : string
    {
        $ipay_prepare_extension = 'iPayPrepare.ashx';
        return esc_url(($this->is_test_mode_active ? MCWC_MonekGateway::$staging_url : MCWC_MonekGateway::$elite_url) . $ipay_prepare_extension);
    }

    /**
     * Send the prepared payment request to the payment gateway
     *
     * @param array $prepared_payment_request
     * @return array|WP_Error
     */
    private function mcwc_send_prepared_payment_request(array $prepared_payment_request)
    {
        $prepared_payment_url = $this->mcwc_get_ipay_prepare_url();

        return wp_remote_post($prepared_payment_url, [
            'body' => http_build_query($prepared_payment_request),
        ]);
    }

    /**
     * Verify the nonce for the checkout process
     *
     * @return bool
     */
    private function mcwc_verify_nonce() : bool
    {
        $nonce = filter_input(INPUT_POST, "woocommerce-process-checkout-nonce", FILTER_SANITIZE_FULL_SPECIAL_CHARS);

        if (!$nonce) {
            return false;
        }

        return wp_verify_nonce($nonce, 'woocommerce-process_checkout');
    }
}