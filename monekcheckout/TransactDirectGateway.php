<?php

class TransactDirectGateway extends WC_Payment_Gateway
{ 
    private const GATEWAY_ID = 'transactdirect';
    private const TEXT_DOMAIN = 'monek-woo-commerce';

    private $test_mode;

    public function __construct() {
        $this->setup_properties();
        $this->init_form_fields();
        $this->init_settings();
        $this->get_settings();

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) ); 
        add_action('woocommerce_api_'.self::GATEWAY_ID, array(&$this, 'handle_callback'));
    }

    private function convert_decimal_to_flat($decimal_number) {
        $flat_number = (int) str_replace('.', '', $decimal_number);
    
        return $flat_number;
    }

    //TODO: Find better way than listing all
    private function get_iso4217_currency_code() {
        $currency_code = get_woocommerce_currency();

        $iso4217_mapping = array(
            'USD' => '840',  // US Dollar
            'EUR' => '978',  // Euro
            'GBP' => '826',  // British Pound
            'CAD' => '124',  // Canadian Dollar
            'AUD' => '036',  // Australian Dollar
            'CZK' => '203',  // Czech Koruna
            'DKK' => '208',  // Danish Krone
            'HKD' => '344',  // Hong Kong Dollar
            'ISK' => '352',  // Icelandic Krona
            'JPY' => '392',  // Japanese Yen
            'NOK' => '578',  // Norwegian Krone
            'SGD' => '702',  // Singapore Dollar
            'SEK' => '752',  // Swedish Krona
            'CHF' => '756',  // Swiss Franc
        );

        return isset($iso4217_mapping[$currency_code]) ? $iso4217_mapping[$currency_code] : '';
    }

    //TODO: Find better way than listing all
    private function get_iso4217_numeric_country_code() {
        $iso4217_country_code = WC()->countries->get_base_country();
    
        $iso4217_numeric_mapping = array(
            'US' => '840',  // United States
            'CA' => '124',  // Canada
            'AU' => '036',  // Australia
            'CZ' => '203',  // Czech Republic
            'DK' => '208',  // Denmark
            'HK' => '344',  // Hong Kong
            'IS' => '352',  // Iceland
            'JP' => '392',  // Japan
            'NO' => '578',  // Norway
            'SG' => '702',  // Singapore
            'SE' => '752',  // Sweden
            'CH' => '756',  // Switzerland
            'GB' => '826',  // United Kingdom
            'DE' => '276',  // Germany
            'FR' => '250',  // France
            'IT' => '380',  // Italy
            'ES' => '724',  // Spain
            'NL' => '528',  // Netherlands
            'BE' => '056',  // Belgium
            'AT' => '040',  // Austria
            'PT' => '620',  // Portugal
        );
    
        return isset($iso4217_numeric_mapping[$iso4217_country_code]) ? $iso4217_numeric_mapping[$iso4217_country_code] : '';
    }

    private function get_settings() {
        $this->title = __('Credit/Debit Card', self::TEXT_DOMAIN);
		$this->description = __('Pay securely with Monek.', self::TEXT_DOMAIN);
        $this->merchant_id = $this->get_option( 'merchant_id' );
        $this->echo_check_code = $this->get_option('echo_check_code');
        $this->test_mode = isset($this->settings['test_mode']) && $this->settings['test_mode'] == 'yes';
    }
    
    private function get_url()
    {
        $testUrl = 'https://staging.monek.com/Secure/bootstrap/iPay.aspx';
        $liveUrl = 'https://elite.monek.com/Secure/bootstrap/iPay.aspx';

        return $this->test_mode ? $testUrl : $liveUrl;
    }

    public function handle_callback(){
        $json_echo = file_get_contents('php://input');
        $transaction_response_echo_data = json_decode($json_echo, true);

        if(isset($transaction_response_echo_data)){
            $this->process_transaction_response_echo($transaction_response_echo_data);
        }
        else {
            $this->process_payment_callback();
        }
    }

    public function init_form_fields() {
        $this->form_fields = array(

            'enabled' => array(
                'title' => __( 'Enabled', self::TEXT_DOMAIN),
                'type' => 'checkbox',
                'label' => __('Enable this payment gateway', self::TEXT_DOMAIN),
                'default' => 'no'
            ),
            'merchant_id' => array(
                'title'=> __('Monek ID', self::TEXT_DOMAIN),
                'type' => 'number',
                'description'=> __("Your Monek ID, a unique code that connects your business with Monek. This ID helps streamline transactions and communication between your account and Monek's systems.", self::TEXT_DOMAIN),
                'default'=> '',
                'desc_tip'=> true
            ),
            'echo_check_code' => array(
                'title'=> __('Echo Check Code', self::TEXT_DOMAIN),
                'type'=> 'text',
                'description'=> __("Configure the Response Echo Code to directly confirm all transactions. If you plan to use this feature, ensure it's set up with Monek to avoid any disruptions in order completion.", self::TEXT_DOMAIN),
                'default'=> '',
                'desc_tip'=> true
            ),
            'test_mode' => array(
                'title' => __('Trial Features', self::TEXT_DOMAIN),
                'type' => 'checkbox',
                'default' => 'no',
                'label' => __('Enable trial features', self::TEXT_DOMAIN),
                'description' => __('Enable this option to access trial features. Trial features provide early access to new functionalities and enhancements that are currently in testing.', self::TEXT_DOMAIN),
                'desc_tip' => true
            )
        );
    }

     public function process_payment($order_id) {
        $order = wc_get_order($order_id);
        $amount = $order->get_total();
        $return_plugin_url = (new WooCommerce)->api_request_url(self::GATEWAY_ID);

        $parsed_url = parse_url($return_plugin_url);

        if (isset($parsed_url['port'])) {
            $note = 'Invalid Return URL : Port Detected';
            wc_add_notice( $note,'error');

            $order->add_order_note(__($note, 'woocommerce'));
            $order->update_status('failed');

            wp_redirect(wc_get_cart_url());
            exit;

        } else {
            $billing_name = $_POST['billing_first_name'].' '.$_POST['billing_last_name'];
            $billing_address = $_POST['billing_address_1'].' '.$_POST['billing_address_2'].' '.$_POST['billing_city'].' '.$_POST['billing_state'].' '.$_POST['billing_country'];

            $body_data = array(
                'MerchantID' => $this->get_option('merchant_id'),
                'MessageType' => 'ESALE_KEYED',
                'Amount' => $this->convert_decimal_to_flat($amount),
                'CurrencyCode' => $this->get_iso4217_currency_code(),
                'CountryCode' => $this->get_iso4217_numeric_country_code(),
                'Dispatch' => 'NOW',
                'ResponseAction' => 'REDIRECT',
                'RetOKAddress' => $return_plugin_url,
                'RetNotOKAddress' => $return_plugin_url,
                'PaymentReference' => $order_id,
                'QAName' => $billing_name,
                'QAAddress' => $billing_address,
                'QAPostcode' => isset($_POST['billing_postcode']) ? $_POST['billing_postcode'] : '',
                'QAEmailAddress' => isset($_POST['billing_email']) ? $_POST['billing_email'] : '',
                'QAPhoneNumber' => isset($_POST['billing_phone']) ? $_POST['billing_phone'] : '',
                'ShowPayPal' => 'YES',
                'ThreeDSAction' => 'ACSDIRECT',
                'IdempotencyToken' => uniqid(null, true),
            );

            $body_data_query_string = http_build_query($body_data);
            $prepared_payment_url = 'https://staging.monek.com/Secure/iPayPrepare.ashx';

            $response = wp_remote_post($prepared_payment_url, array(
                'body' => $body_data_query_string,
            ));

            if (is_wp_error($response) || wp_remote_retrieve_response_code($response) >= 300) {
                $error_message = is_wp_error($response) ? $response->get_error_message() : wp_remote_retrieve_response_message($response);
                echo 'Error: ' . esc_html($error_message);
            } else {
                $body = wp_remote_retrieve_body($response);
            }

            $url = $this->get_url().'?PreparedPayment='.$body;

        }
        
        return array(
            'result' => 'success',
            'redirect' => $url
        );
    }

    private function process_payment_callback(){
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
            $order->add_order_note(__($note, 'woocommerce'));
            $order->update_status('failed');
            wp_redirect(wc_get_cart_url());
            exit;
        }

        $echo_check_code = $this->get_option('echo_check_code');
        if(!isset($echo_check_code) || $echo_check_code == ''){
            $order->payment_complete();
        }
        WC()->cart->empty_cart();

        $thankyou = WC_Payment_Gateway::get_return_url($order);
        wp_redirect($thankyou);
    }

    private function process_transaction_response_echo($transaction_response_echo_data){
        if($_SERVER['REQUEST_METHOD'] === 'POST') {

            $order = wc_get_order($transaction_response_echo_data['paymentreference']);
            if(!$order){
                header('HTTP/1.1 400 Bad Request');
                echo json_encode(array('error' => 'Bad Request'));
                return;
            }

            $saved_echo_check_code = $this->get_option('echo_check_code');
            $transmited_echo_check_code = $transaction_response_echo_data['echocheckcode'];
            if(!isset($saved_echo_check_code) || $saved_echo_check_code == '' || !isset($transmited_echo_check_code)){
                header('HTTP/1.1 400 Bad Request');
                echo json_encode(array('error' => 'Bad Request'));
                return;
            }

            if($saved_echo_check_code == $transmited_echo_check_code){
                $order->payment_complete();
            }
            else {
                header('HTTP/1.1 500 Internal Server Error');
                echo json_encode(array('error' => 'Internal Server Error'));
            }
        }
        else {
            header('HTTP/1.1 405 Method Not Allowed');
            header('Allow: POST');
            echo json_encode(array('error' => 'Method Not Allowed'));
        }
    }
    
    protected function setup_properties() {
        $this->id = self::GATEWAY_ID;
        $this->icon = plugins_url('Monek-Logo100x12.png', __FILE__);
        $this->has_fields = false;
        $this->method_title = __('Monek', self::TEXT_DOMAIN);
        $this->method_description = __('Pay securely with Monek using your credit/debit card.', self::TEXT_DOMAIN);
    }
}
