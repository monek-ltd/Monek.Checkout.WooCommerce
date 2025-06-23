<?php

/*
 * Plugin Name: Monek Checkout
 * Author: Monek Ltd
 * Author URI: http://www.monek.com
 * Description: Take credit/debit card payments with Monek.
 * Version: 3.3.4
 * text-domain: monek-checkout
 * Requires Plugins: woocommerce
 * License: GPLv3 or later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Tags: payment, gateway, credit card, debit card, woocommerce
 * Contributors: Monek Ltd
 * Requires at least: 5.0
 * Tested up to: 6.8.1
 * Requires PHP: 7.4
 * Stable tag: 3.3.4
 */

 /*
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

 if ( ! defined( 'ABSPATH' ) ) exit;

if( !in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins') ) ) ) {
    return;
}

if (!function_exists('mcwc_initialise_monek_payment_gateway')) {

    /**
     * Get the version of the plugin 
     *
     * @return string
     */
    function mcwc_get_monek_plugin_version() : string
    {
        $plugin_data = get_file_data(__FILE__, array('Version' => 'Version'), false);
        return $plugin_data['Version'];
    }

    /**
     * Initialise the Monek payment gateway
     * 
     * @return void
     */
    function mcwc_initialise_monek_payment_gateway() : void
    {
        
        if (!defined('ABSPATH')) {
            exit;
        }

        if( !class_exists( 'WC_Payment_Gateway' ) ) {
            return;
        }

        /**
         * Autoload classes 
         *
         * @param string $class_name
         * @return void
         */
        function mcwc_monekcheckout_autoloader($class_name) : void
        {
            $class_map = [
                'MCWC_MonekGateway'                  => 'MCWC_MonekGateway.php',
                'MCWC_CallbackController'            => 'Callback/MCWC_CallbackController.php',
                'MCWC_IntegrityCorroborator'         => 'Callback/MCWC_IntegrityCorroborator.php',
                'MCWC_Callback'                      => 'Model/MCWC_Callback.php',
                'MCWC_CountryCodes'                  => 'Model/MCWC_CountryCodes.php',
                'MCWC_BillingAddress'                => 'Model/MCWC_BillingAddress.php',
                'MCWC_ShippingAddress'               => 'Model/MCWC_ShippingAddress.php',
                'MCWC_WebhookPayload'                => 'Model/MCWC_WebhookPayload.php',
                'MCWC_CurrencyCodes'                 => 'Payment/Includes/MCWC_CurrencyCodes.php',
                'MCWC_TransactionHelper'             => 'Payment/MCWC_TransactionHelper.php',
                'MCWC_PreparedPaymentRequestBuilder' => 'Payment/MCWC_PreparedPaymentRequestBuilder.php',
                'MCWC_PreparedPaymentManager'        => 'Payment/MCWC_PreparedPaymentManager.php',
                'MCWC_Address'                       => 'Model/MCWC_Address.php',
                'MCWC_ConsignmentSettings'           => 'Consignment/Model/MCWC_ConsignmentSettings.php',
                'MCWC_ProductConsignmentInitializer' => 'Consignment/MCWC_ProductConsignmentInitializer.php',
                'MCWC_ConsignmentCart'       => 'Consignment/Cart/MCWC_ConsignmentCart.php',
            ];
        
            if (array_key_exists($class_name, $class_map)) {
                require_once $class_map[$class_name];
            }
        }
        
        spl_autoload_register('mcwc_monekcheckout_autoloader');

        /**
         * Add the Monek gateway to the list of available payment gateways
         *
         * @param array $gateways
         * @return array
         */
        function mcwc_add_monek_gateway($gateways) : array
        {
            $gateways[] = 'MCWC_MonekGateway';
            return $gateways;
        }
        
        add_filter('woocommerce_payment_gateways', 'mcwc_add_monek_gateway');
        
        /**
         * Add a link to the settings page on the plugins page 
         *
         * @param array $links
         * @return array
         */
        function mcwc_add_monek_settings_link(array $links) : array
        {
            $settings_url = admin_url('admin.php?page=wc-settings&tab=checkout&section=monek-checkout');

            $plugin_links = [
                '<a href="' . $settings_url . '">' . __('Settings', 'monek-checkout') . '</a>'
            ];

            return array_merge($plugin_links, $links);
        }

        add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'mcwc_add_monek_settings_link');
    }

    /**
     * Enqueue the JavaScript file for the settings page
     * 
     * @return void
     */
    function mcwc_enqueue_monek_admin_scripts() {
        $current_screen = get_current_screen();
        
        if ($current_screen && $current_screen->id === 'woocommerce_page_wc-settings') {
            $current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : '';
            $current_section = isset($_GET['section']) ? sanitize_text_field($_GET['section']) : '';

            if ($current_tab === 'checkout' && $current_section === 'monek-checkout') {
                wp_enqueue_script(
                    'monek-custom-admin-js',
                    plugin_dir_url(__FILE__) . 'assets/js/monek-admin.js',
                    ['jquery'],
                    '1.0.0',
                    true
                );
            }
        }
    }

    /**
     * Conditionally disable the payment gateway if no monek id is set
     * 
     * @param mixed $available_gateways
     * @return mixed
     */
    function mcwc_conditionally_disable_gateway( $available_gateways ) {
        
        if(is_admin()) {
            return $available_gateways;
        }

        $is_merchant_id_set = false;

        $gateway_instance = new MCWC_MonekGateway();
        $merchant_id = $gateway_instance->get_option('merchant_id');

        if(!empty( $merchant_id ) ) {
            $is_merchant_id_set = true;
        } 
        else if (isset($gateway_instance->settings['consignment_mode']) 
            && $gateway_instance->settings['consignment_mode'] == 'yes'
            && isset(WC()->cart)) 
        {
            $is_merchant_id_set = MCWC_ConsignmentCart::mcwc_check_cart_for_matching_merchants() === 1;
        }

        if ( !$is_merchant_id_set ) {
            unset( $available_gateways['monek-checkout'] );
        }
        
        return $available_gateways;
    }
    
    add_action('admin_enqueue_scripts', 'mcwc_enqueue_monek_admin_scripts');
    add_action('plugins_loaded', 'mcwc_initialise_monek_payment_gateway', 0);
    add_filter( 'woocommerce_available_payment_gateways', 'mcwc_conditionally_disable_gateway' );

    require_once 'Consignment/MerchantMapping/Includes/MCWC_AjaxHandlers.php';
}