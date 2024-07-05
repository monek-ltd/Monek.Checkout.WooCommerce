<?php

/*
 * Plugin Name: Monek Checkout Payment Gateway
 * Author: Monek Ltd
 * Author URI: http://www.monek.com
 * Description: Take credit/debit card payments with Monek.
 * Version: 3.0.0
 * text-domain: monek-payment-gateway
 * Requires Plugins: woocommerce
 * 
 * NOTE: This header comment is required for WordPress, see https://developer.wordpress.org/plugins/plugin-basics/header-requirements/#header-fields for details.
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
                'CountryCodes' => 'CountryCodes.php',
                'CurrencyCodes' => 'CurrencyCodes.php',
                'IntegrityCorroborator' => 'IntegrityCorroborator.php'
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