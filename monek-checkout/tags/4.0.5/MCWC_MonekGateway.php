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
    public const ELITE_URL = 'https://elite.monek.com/Secure/';
    private const GATEWAY_ID = 'monek-checkout';
    public const STAGING_URL = 'https://staging.monek.com/Secure/';

    public string $basket_summary;
    public bool $is_consignment_mode_active;
    public string $country_dropdown;
    private bool $is_test_mode_active;
    public string $merchant_id;
    private MCWC_PreparedPaymentManager $prepared_payment_manager;
    private bool $show_google_pay;
    private bool $disable_basket;

    public function __construct()
    {
        $this->mcwc_setup_properties();
        $this->mcwc_init_form_fields();
        $this->init_settings();
        $this->mcwc_get_settings();

        add_action("woocommerce_update_options_payment_gateways_{$this->id}", [$this, 'process_admin_options']);

        $this->prepared_payment_manager = new MCWC_PreparedPaymentManager($this->is_test_mode_active, $this->show_google_pay, $this->disable_basket);

        $callback_controller = new MCWC_CallbackController($this->is_test_mode_active);
        $callback_controller->mcwc_register_routes();

        if ($this->is_consignment_mode_active) {
            MCWC_ProductConsignmentInitializer::init();
        }
    }

    /**
     * Validate that all basket items have the same Monek ID and return the product Monek ID
     * 
     * @param WC_Order $order
     * @return string
     * @throws Exception
     */
    private function mcwc_get_consignment_merchant_id(WC_Order $order): string
    {
        if(MCWC_ConsignmentCart::mcwc_check_order_for_matching_merchants($order->get_id()) !== 1) {
            wc_add_notice('Invalid Monek ID: Order items have different merchant IDs.', 'error');
            $order->add_order_note(__('Invalid Monek ID: Order items have different merchant IDs.', 'monek-checkout'));
            throw new Exception('Order items have different merchant IDs.');
        }
        else {
            $order_items = $order->get_items();
            return MCWC_ConsignmentCart::mcwc_get_merchant_id_by_product_tags_from_pairs(reset($order_items)->get_product()->get_id())[0];
        }
    }

    /**
     * Get the URL for the iPay checkout endpoint 
     *
     * @return string
     */
    private function mcwc_get_ipay_url(): string
    {
        $ipay_extension = 'checkout.aspx';
        return ($this->is_test_mode_active ? self::STAGING_URL : self::ELITE_URL) . $ipay_extension;
    }

    /**
     * Get the merchant ID for the Monek payment gateway 
     *
     * @param WC_Order $order
     * @return string
     */
    private function mcwc_get_merchant_id(WC_Order $order): string
    {
        if ($this->is_consignment_mode_active) {
            return $this->mcwc_get_consignment_merchant_id($order);
        } else {
            return $this->get_option('merchant_id');
        }
    }

    /**
     * Get the settings for the Monek payment gateway 
     *
     * @return void
     */
    private function mcwc_get_settings(): void
    {
        $this->title = __('Credit/Debit Card', 'monek-checkout');
        $this->description = __('Pay securely with Monek.', 'monek-checkout');
        $this->merchant_id = $this->get_option('merchant_id');
        $this->is_test_mode_active = isset($this->settings['test_mode']) && $this->settings['test_mode'] == 'yes';
        $this->show_google_pay = isset($this->settings['google_pay']) && $this->settings['google_pay'] == 'yes';
        $this->is_consignment_mode_active = isset($this->settings['consignment_mode']) && $this->settings['consignment_mode'] == 'yes';
        $this->country_dropdown = $this->get_option('country_dropdown');
        $this->basket_summary = $this->get_option('basket_summary');
        $this->disable_basket = isset($this->settings['basket_disable']) && $this->settings['basket_disable'] == 'yes';
        $this->publishable_key = $this->get_option('publishable_key');
        $this->secret_key = $this->get_option('secret_key');
        $this->svix_signing_secret = $this->get_option('svix_signing_secret');
    }

   /**
     * Initialise the form fields for the Monek payment gateway 
     *
     * @return void
     */
    public function mcwc_init_form_fields(): void
    {
        $country_codes = include 'Model/MCWC_CountryCodes.php';
        $consignment_mode = $this->get_option('consignment_mode');

        $this->form_fields = [
            'enabled' => [
                'title' => __('Enabled', 'monek-checkout'),
                'type' => 'checkbox',
                'label' => __('Enable this payment gateway', 'monek-checkout'),
                'default' => 'no'
            ],

            'end_of_section_1' => ['type' => 'title'],

            // Monek ID Settings Section
            'monek_id_section_title' => [
                'title' => __('Monek ID Settings', 'monek-checkout'),
                'type' => 'title',
                'description' => __('Configure the Monek ID.', 'monek-checkout'),
            ],
            'merchant_id' => [
                'title' => __('Monek ID', 'monek-checkout'),
                'type' => 'number',
                'description' => __("Your Monek ID, a unique code that connects your business with Monek. This ID helps streamline transactions and communication between your account and Monek's systems.", 'monek-checkout'),
                'default' => '',
                'desc_tip' => true
            ],
            'consignment_mode' => [
                'title' => __('Enable Consignment Sales', 'monek-checkout'),
                'type' => 'checkbox',
                'label' => isset($consignment_mode) && $consignment_mode == 'yes'
                    ? sprintf(
                        __('Monek ID per product. <a href="%s">Configure Consignment IDs</a>.', 'monek-checkout'),
                        admin_url('admin.php?page=wc-settings&tab=products&section=monek_consigment_ids')
                    )
                    : __('Monek ID per product.', 'monek-checkout'),
                'default' => 'no',
                'description' => __('If enabled, the Monek ID field will be hidden and the Monek ID will need to be configured per product.', 'monek-checkout'),
                'desc_tip' => true
            ],
           
            'embedded_checkout_section' => [
                'title' => __('Monek Embedded Checkout Settings', 'monek-checkout'),
                'type' => 'title',
                'description' => __(
                    'Configure the settings for the Monek embedded checkout. Go to <a href="https://portal.monek.com/authentication/login" target="_blank">Monek Portal</a>.<br/> <b>These settings will be required in the upcoming release</b>. If you do not have the necessary credentials, please contact Monek Support.',
                    'monek-checkout'
                ),
            ],
            'publishable_key' => [
                'title' => __('Access key', 'monek-checkout'),
                'type' => 'text',
                'description' => __('Your public key used to initialise the embedded checkout.', 'monek-checkout'),
                'default' => '',
                'desc_tip' => true,
            ],
            'secret_key' => [
                'title' => __('Secret key', 'monek-checkout'),
                'type' => 'password',
                'description' => __('Server key used for completing payments from your server.', 'monek-checkout'),
                'default' => '',
                'desc_tip' => true,
            ],
            'svix_signing_secret' => [
                'title' => __('Webhook URL key', 'monek-checkout'),
                'type' => 'text',
                'description' => __('Paste the signing secret for your webhook endpoint.', 'monek-checkout'),
                'default' => '',
                'desc_tip' => true,
            ],
            'download_apple_file' => [
                'title'       => __('Apple Pay registration file', 'monek-checkout'),
                'type'        => 'download_button',
                'description' => __('Domain registration file.', 'monek-checkout'),
            ],

            'end_of_section_2' => ['type' => 'title'],

            // General Settings Section
            'general_section_title' => [
                'title' => __('General Settings', 'monek-checkout'),
                'type' => 'title',
                'description' => __('Configure the basic settings for the Monek payment gateway. ', 'monek-checkout'),
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
                'label' => __('Merchants must adhere to the <a href="https://payments.developers.google.com/terms/aup" target="_blank">Google Pay APIs Acceptable Use Policy</a> and accept the terms defined in the <a href="https://payments.developers.google.com/terms/sellertos" target="_blank">Google Pay API Terms of Service</a>.', 'monek-checkout'),
                'description' => __('Enable this option to provide access to GooglePay as a payment option. ', 'monek-checkout'),
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
            'basket_summary' => [
                'title' => __('Basket Summary', 'monek-checkout'),
                'type' => 'text',
                'description' => __('This section allows you to customise the basket summary that is required as a purchase summary by PayPal.', 'monek-checkout'),
                'default' => 'Goods',
                'desc_tip' => true
            ],
            'basket_disable' => [
                'title' => __('Disable Basket Breakdown', 'monek-checkout'),
                'type' => 'checkbox',
                'default' => 'no',
                'description' => __('Enable this option if you use custom plugins that are incompatible with the hosted checkout page basket', 'monek-checkout'),
                'desc_tip' => true
            ],
        ];
    }

    /**
     * Process the payment for the Monek payment gateway 
     *
     * @param int $order_id
     * @return array
     */
    public function process_payment($order_id): array
    {
        $order = wc_get_order($order_id);
        $return_plugin_url = (new WooCommerce)->api_request_url(self::GATEWAY_ID);
        $this->mcwc_validate_return_url($order, $return_plugin_url);

        $response = $this->prepared_payment_manager->mcwc_create_prepared_payment(
            $order,
            $this->mcwc_get_merchant_id($order),
            $this->get_option('country_dropdown'),
            $return_plugin_url,
            $this->get_option('basket_summary'),

        );

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) >= 300) {
            $error_message = is_wp_error($response) ? $response->get_error_message() : wp_remote_retrieve_response_message($response);
            echo 'Error: ' . esc_html($error_message);
            return [];
        } else {
            $body = wp_remote_retrieve_body($response);
        }
        
        $order->add_order_note(__('Order Created: Redirecting to checkout page', 'monek-checkout'));

        return [
            'result' => 'success',
            'redirect' => $this->mcwc_get_ipay_url() . '?PreparedPayment=' . $body
        ];
    }

    public function generate_download_button_html($key, $data)
    {
        $nonce = wp_create_nonce('monek_download_apple_file');

        ob_start();
        ?>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <label><?php echo esc_html($data['title']); ?></label>
            </th>
            <td class="forminp">
                <button
                    type="button"
                    class="button button-secondary"
                    id="monek-download-apple-file"
                    onclick="monekInstallApplePayFile(this)"
                    data-nonce="<?php echo esc_attr($nonce); ?>">
                    <?php esc_html_e('Download file', 'monek-checkout'); ?>
                </button>

                <p class="apple-pay-description">
                    <?php echo esc_html($data['description']); ?>
                </p>
            </td>
        </tr>
        <?php
        return ob_get_clean();
    }

    /**
     * Setup the properties for the Monek payment gateway 
     *
     * @return void
     */
    protected function mcwc_setup_properties(): void
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
    private function mcwc_validate_return_url(WC_Order $order, string $return_plugin_url): void
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