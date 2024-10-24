<?php

if (!defined('ABSPATH')) exit;

/**
 * Enqueues the JavaScript file for the custom merchant select on the product page.
 *
 * @param mixed $hook The current admin page hook.
 * @return void
 */
function MCWC_enqueue_monek_product_consignment_select_script($hook) {
    if ($hook === 'post.php' || $hook === 'post-new.php') {
        global $post;

        wp_enqueue_script(
            'monek-product-consignment-select',
            plugin_dir_url(__FILE__) . '../Assets/MCWC_ConsignmentMerchantSelect.js',
            ['jquery'],
            '1.0.0',
            true
        );

        // Localize script to pass dynamic data
        wp_localize_script('monek-product-consignment-select', 'consignmentSelect', [
            'ajaxurl'     => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce(MCWC_ConsignmentSettings::CONSIGNMENT_MERCHANT_SELECT_NONCE_SLUG),
            'product_id' => $post->ID 
        ]);
    }
}
add_action('admin_enqueue_scripts', 'MCWC_enqueue_monek_product_consignment_select_script');
