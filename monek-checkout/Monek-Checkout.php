<?php

/*
 * Plugin Name: Monek Checkout
 * Author: Monek Ltd
 * Author URI: http://www.monek.com
 * Description: Take credit/debit card payments with Monek.
 * Version: 3.0.3
 * text-domain: monek-payment-gateway
 * Requires Plugins: woocommerce
 * License: GPLv3 or later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Tags: payment, gateway, credit card, debit card, woocommerce
 * Contributors: Monek Ltd
 * Requires at least: 5.0
 * Tested up to: 6.0
 * Requires PHP: 8.0
 * Stable tag: 3.0.3
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

if( !in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins') ) ) ) {
    return;
}

if (!function_exists('initialise_monek_payment_gateway')) {

    /**
     * Get the version of the plugin 
     *
     * @return string
     */
    function get_monek_plugin_version() : string
    {
        $plugin_data = get_file_data(__FILE__, array('Version' => 'Version'), false);
        return $plugin_data['Version'];
    }

    /**
     * Initialise the Monek payment gateway
     * 
     * @return void
     */
    function initialise_monek_payment_gateway() : void
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
        function monekcheckout_autoloader($class_name) : void
        {
            $class_map = [
                'MonekGateway'                  => 'MonekGateway.php',
                'CallbackController'            => 'Callback/CallbackController.php',
                'IntegrityCorroborator'         => 'Callback/IntegrityCorroborator.php',
                'Callback'                      => 'Model/Callback.php',
                'CountryCodes'                  => 'Model/CountryCodes.php',
                'BillingAddress'                => 'Model/BillingAddress.php',
                'ShippingAddress'               => 'Model/ShippingAddress.php',
                'WebhookPayload'                => 'Model/WebhookPayload.php',
                'CurrencyCodes'                 => 'Payment/Includes/CurrencyCodes.php',
                'TransactionHelper'             => 'Payment/TransactionHelper.php',
                'PreparedPaymentRequestBuilder' => 'Payment/PreparedPaymentRequestBuilder.php',
                'PreparedPaymentManager'        => 'Payment/PreparedPaymentManager.php',
            ];
        
            if (array_key_exists($class_name, $class_map)) {
                require_once $class_map[$class_name];
            }
        }
        
        spl_autoload_register('monekcheckout_autoloader');

        /**
         * Add the Monek gateway to the list of available payment gateways
         *
         * @param array $gateways
         * @return array
         */
        function add_monek_gateway($gateways) : array
        {
            $gateways[] = 'MonekGateway';
            return $gateways;
        }
        
        add_filter('woocommerce_payment_gateways', 'add_monek_gateway');
        
        /**
         * Add a link to the settings page on the plugins page 
         *
         * @param array $links
         * @return array
         */
        function add_monek_settings_link(array $links) : array
        {
            $settings_url = admin_url('admin.php?page=wc-settings&tab=checkout&section=monekgateway');

            $plugin_links = [
                '<a href="' . $settings_url . '">' . __('Settings', 'monekgateway') . '</a>'
            ];

            return array_merge($plugin_links, $links);
        }

        add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'add_monek_settings_link');
    }

    add_action('plugins_loaded', 'initialise_monek_payment_gateway', 0);
}