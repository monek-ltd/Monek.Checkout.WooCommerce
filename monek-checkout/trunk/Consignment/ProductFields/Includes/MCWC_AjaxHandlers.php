<?php

if (!defined('ABSPATH')) exit;

function mcwc_save_product_consignment_mid() {
    if (!isset($_POST['security']) || !wp_verify_nonce($_POST['security'], MCWC_ConsignmentSettings::CONSIGNMENT_MERCHANT_SELECT_NONCE_SLUG)) {
        wp_send_json_error(['message' => __('Invalid nonce, request not authorized', 'monek-checkout')], 403);
    }

    if (empty($_POST['product_id']) || empty($_POST['merchant_id'])) {
        wp_send_json_error(['message' => __('Missing required parameters: product ID or merchant ID', 'monek-checkout')], 400);
    }

    $post_id = absint($_POST['product_id']);
    $merchant_id = sanitize_text_field($_POST['merchant_id']);

    update_post_meta($post_id, MCWC_ConsignmentSettings::CONSIGNMENT_MERCHANT_PRODUCT_META_KEY, $merchant_id);

    wp_send_json_success(['message' => __('Merchant ID saved successfully.', 'monek-checkout')]);
}

add_action('wp_ajax_mcwc_save_product_consignment_mid', 'mcwc_save_product_consignment_mid');