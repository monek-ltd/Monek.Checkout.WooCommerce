<?php

/**
 * Handles the creation of prepared payment requests and sending them to the payment gateway
 *
 * @package Monek
 */
class MCWC_PreparedPaymentManager 
{
    private bool $is_test_mode_active;
    private bool $show_google_pay;
    private bool $disable_basket;

    /**
     * @param bool $is_test_mode_active
     */
    public function __construct(bool $is_test_mode_active, bool $show_google_pay, bool $disable_basket) 
    { 
        $this->is_test_mode_active = $is_test_mode_active;
        $this->show_google_pay = $show_google_pay;
        $this->disable_basket = $disable_basket;
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
        $request_builder = new MCWC_PreparedPaymentRequestBuilder($this->show_google_pay, $this->disable_basket);
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
        return esc_url(($this->is_test_mode_active ? MCWC_MonekGateway::STAGING_URL : MCWC_MonekGateway::ELITE_URL) . $ipay_prepare_extension);
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
     * Accept nonce from Classic checkout OR Checkout Blocks (Store API).
     *
     * @return bool
     */
    private function mcwc_verify_nonce(): bool {
        // 1) Classic checkout (AJAX): hidden field "woocommerce-process-checkout-nonce"
        if ( isset( $_POST['woocommerce-process-checkout-nonce'] ) ) {
            $nonce = sanitize_text_field( wp_unslash( $_POST['woocommerce-process-checkout-nonce'] ) );
            if ( wp_verify_nonce( $nonce, 'woocommerce-process_checkout' ) ) {
                return true;
            }
        }

        // 2) Blocks (Store API): header "X-WC-Store-API-Nonce" with action "wc_store_api"
        $store_api_nonce = $_SERVER['HTTP_X_WC_STORE_API_NONCE'] ?? '';
        if ( ! empty( $store_api_nonce ) && wp_verify_nonce( $store_api_nonce, 'wc_store_api' ) ) {
            return true;
            }

        // 3) Some environments also provide "X-WP-Nonce" for REST with action "wp_rest"
        $wp_rest_nonce = $_SERVER['HTTP_X_WP_NONCE'] ?? '';
        if ( ! empty( $wp_rest_nonce ) && wp_verify_nonce( $wp_rest_nonce, 'wp_rest' ) ) {
            return true;
        }

        return false;
    }
}