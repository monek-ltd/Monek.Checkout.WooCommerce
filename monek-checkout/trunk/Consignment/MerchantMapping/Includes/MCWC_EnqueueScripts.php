<?php

if (!defined('ABSPATH')) exit;

/**
 * Enqueues the JavaScript file for the custom merchant mapping table.
 *
 * @param mixed $hook The current admin page hook.
 * @return void
 */
function enqueue_monek_merchant_mapping_table_script($hook) {
    if (strpos($hook, 'woocommerce_page_wc-settings') !== false) {
        if (isset($_GET['section']) && $_GET['section'] === MCWC_ConsignmentSettings::MERCHANT_MAPPING_SECTION_SLUG) {
            wp_enqueue_script(
                'monek-merchant-mapping-table',
                plugin_dir_url(__FILE__) . '../Assets/MCWC_MappingTable.js',
                ['jquery'],
                '1.0.0',
                true
            );

            // Localize script to pass dynamic data
            wp_localize_script('monek-merchant-mapping-table', 'mappingTable', [
                'ajaxurl'     => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce(MCWC_ConsignmentSettings::MERCHANT_MAPPING_NONCE_SLUG),
                'deleteText' => __(MCWC_ConsignmentSettings::DELETE_TEXT, 'monek-checkout')
            ]);
        }
    }
}
add_action('admin_enqueue_scripts', 'enqueue_monek_merchant_mapping_table_script');
