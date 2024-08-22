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
 * Requires PHP: 7.0
 * Stable tag: 3.0.1
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

    function get_monek_plugin_version() {
        $plugin_data = get_file_data(__FILE__, array('Version' => 'Version'), false);
        return $plugin_data['Version'];
    }

    function initialise_monek_payment_gateway(){
        
        if (!defined('ABSPATH')) {
            exit;
        }

        if( !class_exists( 'WC_Payment_Gateway' ) ) {
            return;
        }
        function monekcheckout_autoloader($class_name) {
            $class_map = array(
                'MonekGateway' => 'MonekGateway.php',
                'TransactionHelper' => 'TransactionHelper.php',
                'PaymentProcessor' => 'PaymentProcessor.php',
                'CountryCodes' => 'Model/CountryCodes.php',
                'CurrencyCodes' => 'Model/CurrencyCodes.php',
                'IntegrityCorroborator' => 'Callback/IntegrityCorroborator.php',
                'CallbackController' => 'Callback/CallbackController.php',
                'Callback' => 'Model/Callback.php',
                'WebhookPayload' => 'Model/WebhookPayload.php',
            );
        
            if (array_key_exists($class_name, $class_map)) {
                require_once $class_map[$class_name];
            }
        }
        
        spl_autoload_register('monekcheckout_autoloader');

        function add_monek_gateway($gateways) {
            $gateways[] = 'MonekGateway';
            return $gateways;
        }
        
        add_filter('woocommerce_payment_gateways', 'add_monek_gateway');
        
        function add_monek_settings_link($links)
        {
            $settings_url = admin_url('admin.php?page=wc-settings&tab=checkout&section=monekgateway');

            $plugin_links = [
                '<a href="' . $settings_url . '">' . __('Settings', 'monekgateway') . '</a>'
            ];

            $links = array_merge($plugin_links, $links);

            return $links;
        }

        add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'add_monek_settings_link');
    }

    add_action('plugins_loaded', 'initialise_monek_payment_gateway', 0);
}