<?php

/**
 * Class MonekGateway - provides the main functionality of the Monek payment gateway 
 *
 * #[AllowDynamicProperties] - allows dynamic properties to be set on the class as we are not allowed to define the following variables
 * @property string $title
 * @property string $description
 * 
 * @package Monek
 */

#[AllowDynamicProperties]
class MCWC_MonekGateway extends WC_Payment_Gateway
{ 
    private const GATEWAY_ID = 'monek-checkout';
    
    public string $basket_summary;
    public string $country_dropdown;    
    public static string $elite_url = 'https://elite.monek.com/Secure/';
    private bool $is_test_mode_active;
    public string $merchant_id;
    private MCWC_PreparedPaymentManager $prepared_payment_manager;
    private bool $show_google_pay;
    public static string $staging_url = 'https://staging.monek.com/Secure/';

    public function __construct() 
    {
        $this->mcwc_setup_properties();
        $this->mcwc_init_form_fields();
        $this->init_settings();
        $this->mcwc_get_settings();

        add_action("woocommerce_update_options_payment_gateways_{$this->id}", [$this, 'process_admin_options'] ); 

        $this->prepared_payment_manager = new MCWC_PreparedPaymentManager($this->is_test_mode_active, $this->show_google_pay);

        $callback_controller = new MCWC_CallbackController($this->is_test_mode_active);
        $callback_controller->mcwc_register_routes();
    }

    /**
     * Get the URL for the iPay checkout endpoint 
     *
     * @return string
     */
    private function mcwc_get_ipay_url() : string
    {
        $ipay_extension = 'checkout.aspx';
        return ($this->is_test_mode_active ? self::$staging_url : self::$elite_url) . $ipay_extension;
    }

    /**
     * Get the settings for the Monek payment gateway 
     *
     * @return void
     */
    private function mcwc_get_settings() : void
    {
        $this->title = __('Credit/Debit Card', 'monek-checkout');
		$this->description = __('Pay securely with Monek.', 'monek-checkout');
        $this->merchant_id = $this->get_option( 'merchant_id' );
        $this->is_test_mode_active = isset($this->settings['test_mode']) && $this->settings['test_mode'] == 'yes';
        $this->show_google_pay = isset($this->settings['google_pay']) && $this->settings['google_pay'] == 'yes';
        $this->country_dropdown = $this->get_option('country_dropdown');
        $this->basket_summary = $this->get_option('basket_summary');
    }

    /**
     * Initialise the form fields for the Monek payment gateway 
     *
     * @return void
     */
    public function mcwc_init_form_fields() : void
    {
        $country_codes = include 'Model/MCWC_CountryCodes.php';

        $this->form_fields = [
            'enabled' => [
                'title' => __('Enabled', 'monek-checkout'),
                'type' => 'checkbox',
                'label' => __('Enable this payment gateway', 'monek-checkout'),
                'default' => 'no'
            ],
            'merchant_id' => [
                'title' => __('Monek ID', 'monek-checkout'),
                'type' => 'number',
                'description' => __("Your Monek ID, a unique code that connects your business with Monek. This ID helps streamline transactions and communication between your account and Monek's systems.", 'monek-checkout'),
                'default' => '',
                'desc_tip' => true
            ],
            'country_dropdown' => [
                'title' => __('Country', 'monek-checkout'),
                'type' => 'select',
                'options' => $country_codes,
                'default' => '826', // Set default to United Kingdom
                'description' => __('Set your location', 'monek-checkout'),
                'id' => 'country_dropdown_field',
                'desc_tip' => true
            ],
            'test_mode' => [
                'title' => __('Trial Features', 'monek-checkout'),
                'type' => 'checkbox',
                'default' => 'no',
                'label' => __('Enable trial features', 'monek-checkout'),
                'description' => __('Enable this option to access trial features. Trial features provide early access to new functionalities and enhancements that are currently in testing.', 'monek-checkout'),
                'desc_tip' => true
            ],
            'google_pay' => [
                'title' => __('Enable GooglePay', 'monek-checkout'),
                'type' => 'checkbox',
                'default' => 'no',
                'label' => __('Enable this option to provide access to GooglePay as a payment option.', 'monek-checkout'),
                'description' => __('Merchants must adhere to the <a href="https://payments.developers.google.com/terms/aup" target="_blank">Google Pay APIs Acceptable Use Policy</a> and accept the terms defined in the <a href="https://payments.developers.google.com/terms/sellertos" target="_blank">Google Pay API Terms of Service</a>.', 'monek-checkout'),
                'desc_tip' => true
            ],
            'basket_summary' => [
                'title' => __('Basket Summary', 'monek-checkout'),
                'type' => 'text',
                'description' => __('This section allows you to customise the basket summary that is required as a purchase summary by PayPal.', 'monek-checkout'),
                'default' => 'Goods',
                'desc_tip' => true
            ]
        ];
    }
        
    /**
     * Process the payment for the Monek payment gateway 
     *
     * @param int $order_id
     * @return array
     */
    public function process_payment($order_id) : array
    {
        $order = wc_get_order($order_id);
        $return_plugin_url = (new WooCommerce)->api_request_url(self::GATEWAY_ID);
        $this->mcwc_validate_return_url($order, $return_plugin_url);

        $response = $this->prepared_payment_manager->mcwc_create_prepared_payment($order, $this->get_option('merchant_id'), 
            $this->get_option('country_dropdown'), $return_plugin_url, $this->get_option('basket_summary'));

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) >= 300) {
            $error_message = is_wp_error($response) ? $response->get_error_message() : wp_remote_retrieve_response_message($response);
            echo 'Error: ' . esc_html($error_message);
            return []; 
        } else {
            $body = wp_remote_retrieve_body($response);
        }
        
        return [
            'result' => 'success',
            'redirect' => $this->mcwc_get_ipay_url() . '?PreparedPayment=' . $body
        ];
    }
    
    /**
     * Setup the properties for the Monek payment gateway 
     *
     * @return void
     */
    protected function mcwc_setup_properties() : void
    {
        $this->id = self::GATEWAY_ID;
        $this->icon = plugins_url('img/Monek-Logo100x12.png', __FILE__);
        $this->has_fields = false;
        $this->method_title = __('Monek', 'monek-checkout');
        $this->method_description = __('Pay securely with Monek using your credit/debit card.', 'monek-checkout');
    }

    /**
     * Validate the return URL for the Monek payment gateway 
     *
     * @param WC_Order $order
     * @param string $return_plugin_url
     * @return void
     */
    private function mcwc_validate_return_url(WC_Order $order, string $return_plugin_url) : void
    {
        $parsed_url = wp_parse_url($return_plugin_url);
    
        if ($parsed_url === false) {
            wc_add_notice('Invalid Return URL: Malformed URL', 'error');
            $order->add_order_note(__('Invalid Return URL: Malformed URL', 'monek-checkout'));
            exit;
        }
    
        if (isset($parsed_url['port'])) {
            wc_add_notice('Invalid Return URL: Port Detected', 'error');
            $order->add_order_note(__('Invalid Return URL: Port Detected', 'monek-checkout'));
            exit;
        }
    
        $current_permalink_structure = get_option('permalink_structure');
        if ($current_permalink_structure === '/index.php/%postname%/' || $current_permalink_structure === '') {
            wc_add_notice('Invalid Return URL: Permalink setting "Plain" is not supported', 'error');
            $order->add_order_note(__('Invalid Return URL: Permalink setting "Plain" is not supported', 'monek-checkout'));
            exit;
        }
    }
}