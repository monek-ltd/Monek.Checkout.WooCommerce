<?php

class MonekGateway extends WC_Payment_Gateway
{ 
    private const GATEWAY_ID = 'monekgateway';
    
    public static $elite_url = 'https://elite.monek.com/Secure/';
    private $integrity_corroborator;
    private $is_test_mode_active;
    public static $staging_url = 'https://staging.monek.com/Secure/';
    private $payment_processor;

    public function __construct() {
        $this->setup_properties();
        $this->init_form_fields();
        $this->init_settings();
        $this->get_settings();

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) ); 
        add_action('woocommerce_api_'.self::GATEWAY_ID, array(&$this, 'handle_callback'));

        $this->payment_processor = new PaymentProcessor($this->is_test_mode_active);
        $this->integrity_corroborator = new IntegrityCorroborator($this->is_test_mode_active);
    }

    private function get_ipay_url()
    {
        $ipay_extension = 'checkout.aspx';
        return ($this->is_test_mode_active ? self::$staging_url : self::$elite_url) . $ipay_extension;
    }

    private function get_settings() {
        $this->title = __('Credit/Debit Card', 'monek-payment-gateway');
		$this->description = __('Pay securely with Monek.', 'monek-payment-gateway');
        $this->merchant_id = $this->get_option( 'merchant_id' );
        $this->is_test_mode_active = isset($this->settings['test_mode']) && $this->settings['test_mode'] == 'yes';
        $this->country_dropdown = $this->get_option('country_dropdown');
        $this->basket_summary = $this->get_option('basket_summary');
    }

    public function handle_callback(){
        $json_echo = file_get_contents('php://input');
        $transaction_webhook_payload_data = json_decode($json_echo, true);

        if(isset($transaction_webhook_payload_data)){
            $this->process_transaction_webhook_payload($transaction_webhook_payload_data);
        }
        else {
            $this->process_payment_callback();
        }
    }

    public function init_form_fields() {
        $country_codes= include('CountryCodes.php');

        $this->form_fields = array(
                'enabled' => array(
                    'title' => __( 'Enabled', 'monek-payment-gateway'),
                    'type' => 'checkbox',
                    'label' => __('Enable this payment gateway', 'monek-payment-gateway'),
                    'default' => 'no'
                ),
                'merchant_id' => array(
                    'title'=> __('Monek ID', 'monek-payment-gateway'),
                    'type' => 'number',
                    'description'=> __("Your Monek ID, a unique code that connects your business with Monek. This ID helps streamline transactions and communication between your account and Monek's systems.", 'monek-payment-gateway'),
                    'default'=> '',
                    'desc_tip'=> true
                ),
                'country_dropdown' => array(
                    'title'        => __('Country', 'monek-payment-gateway'),
                    'type'        => 'select',
                    'options'     => $country_codes,
                    'default'     => '826', // Set default to United Kingdom
                    'description' => __('Set your location', 'monek-payment-gateway'),
                    'id'          => 'country_dropdown_field',
                    'desc_tip'    => true
                ),
                'test_mode' => array(
                    'title' => __('Trial Features', 'monek-payment-gateway'),
                    'type' => 'checkbox',
                    'default' => 'no',
                    'label' => __('Enable trial features', 'monek-payment-gateway'),
                    'description' => __('Enable this option to access trial features. Trial features provide early access to new functionalities and enhancements that are currently in testing.', 'monek-payment-gateway'),
                    'desc_tip' => true
                ),
                'basket_summary' => array(
                    'title' => __('Basket Summary', 'monek-payment-gateway'),
                    'type' => 'text',
                    'description' => __('This section allows you to customise the basket summary that is required as a purchase summary by PayPal.', 'monek-payment-gateway'),
                    'default' => 'Goods',
                    'desc_tip' => true
                )
            );
        }
        
    public function process_payment($order_id) {
        $order = wc_get_order($order_id);
        $return_plugin_url = (new WooCommerce)->api_request_url(self::GATEWAY_ID);
        $this->validate_return_url($order, $return_plugin_url);

        $response = $this->payment_processor->create_prepared_payment($order, $this->get_option('merchant_id'), $this->get_option('country_dropdown'), $return_plugin_url, $this->get_option('basket_summary'));

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) >= 300) {
            $error_message = is_wp_error($response) ? $response->get_error_message() : wp_remote_retrieve_response_message($response);
            echo 'Error: ' . esc_html($error_message);
            return array(); 
        } else {
            $body = wp_remote_retrieve_body($response);
        }
        
        return array(
            'result' => 'success',
            'redirect' => $this->get_ipay_url().'?PreparedPayment='.$body
        );
    }

    private function process_payment_callback(){
        
        if (!wp_verify_nonce($_REQUEST['WPNonce'], 'complete-payment_'.$_REQUEST['paymentreference'])) {
            return new WP_Error('invalid_nonce', __('Invalid nonce', 'monek-payment-gateway'));
        }

        $responseCode = $_REQUEST['responsecode'];
        $order = wc_get_order($_REQUEST['paymentreference']);
        
        if(!$order){
            global $wp_query;
            $wp_query->set_404();
            status_header(404, 'Order Not Found');
            include( get_query_template( '404' ) );
            exit;
        }
        
        if(!isset($responseCode) || $responseCode != '00'){
            $note = 'Payment declined: ' . $_REQUEST['message'] ;
            wc_add_notice( $note,'error');
            $order->add_order_note(__('Payment declined', 'monek-payment-gateway'));
            $order->update_status('failed');
            wp_redirect(wc_get_cart_url());
            exit;
        }
   
        $order->add_order_note(__('Awaiting payment confirmation.', 'monek-payment-gateway'));
        WC()->cart->empty_cart();

        $thankyou = WC_Payment_Gateway::get_return_url($order);
        wp_redirect($thankyou);
    }

    private function process_transaction_webhook_payload($transaction_webhook_payload_data){
        if($_SERVER['REQUEST_METHOD'] === 'POST') {

            if(!$this->validate_webhook_payload($transaction_webhook_payload_data)){
                header('HTTP/1.1 400 Bad Request');
                echo wp_json_encode(array('error' => 'Bad Request'));
                return;
            }

            $order = wc_get_order($transaction_webhook_payload_data['paymentReference']);
            if(!$order){
                header('HTTP/1.1 400 Bad Request');
                echo wp_json_encode(array('error' => 'Bad Request'));
                return;
            }

            if($transaction_webhook_payload_data['responseCode'] == '00'){
                $saved_integrity_secret = get_post_meta($order->get_id(), 'integrity_secret', true);
                if(!isset($saved_integrity_secret) || $saved_integrity_secret == ''){
                    header('HTTP/1.1 500 Internal Server Error');
                    echo wp_json_encode(array('error' => 'Internal Server Error'));
                    return;
                }

                $response = $this->integrity_corroborator->confirm_integrity_digest($order, $transaction_webhook_payload_data);

                if (is_wp_error($response) || wp_remote_retrieve_response_code($response) >= 300) {
                    header('HTTP/1.1 400 Bad Request');
                    echo wp_json_encode(array('error' => 'Bad Request'));
                } else {
                    $order->add_order_note(__('Payment confirmed.', 'monek-payment-gateway'));
                    $order->payment_complete();
                }
            }
        }
        else {
            header('HTTP/1.1 405 Method Not Allowed');
            header('Allow: POST');
            echo wp_json_encode(array('error' => 'Method Not Allowed'));
        }
    }
    
    protected function setup_properties() {
        $this->id = self::GATEWAY_ID;
        $this->icon = plugins_url('Monek-Logo100x12.png', __FILE__);
        $this->has_fields = false;
        $this->method_title = __('Monek', 'monek-payment-gateway');
        $this->method_description = __('Pay securely with Monek using your credit/debit card.', 'monek-payment-gateway');
    }

    private function validate_return_url($order, $return_plugin_url){
        $parsed_url = wp_parse_url($return_plugin_url);
    
        if ($parsed_url === false) {
            wc_add_notice('Invalid Return URL: Malformed URL', 'error');
            $order->add_order_note(__('Invalid Return URL: Malformed URL', 'monek-payment-gateway'));
            exit;
        }
    
        if (isset($parsed_url['port']) && $parsed_url['port'] !== null) {
            wc_add_notice('Invalid Return URL: Port Detected', 'error');
            $order->add_order_note(__('Invalid Return URL: Port Detected', 'monek-payment-gateway'));
            exit;
        }
    
        $current_permalink_structure = get_option('permalink_structure');
        if ($current_permalink_structure === '/index.php/%postname%/' || $current_permalink_structure === '') {
            wc_add_notice('Invalid Return URL: Permalink setting "Plain" is not supported', 'error');
            $order->add_order_note(__('Invalid Return URL: Permalink setting "Plain" is not supported', 'monek-payment-gateway'));
            exit;
        }
    }

    private function validate_webhook_payload($transaction_webhook_payload_data){
        return isset($transaction_webhook_payload_data['transactionDateTime'])
        && isset($transaction_webhook_payload_data['paymentReference'])
        && isset($transaction_webhook_payload_data['crossReference'])
        && isset($transaction_webhook_payload_data['responseCode'])
        && isset($transaction_webhook_payload_data['message'])
        && isset($transaction_webhook_payload_data['amount'])
        && isset($transaction_webhook_payload_data['currencyCode'])
        && isset($transaction_webhook_payload_data['integrityDigest']);
    }
}