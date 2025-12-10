<?php

if (!defined('ABSPATH')) exit;

/**
 * Handles adding a new merchant pair to the merchant mapping table.
 * 
 * @return void
 */
function mcwc_add_merchant_pair()
{
    check_ajax_referer(MCWC_ConsignmentSettings::MERCHANT_MAPPING_NONCE_SLUG, 'security');

    $merchant_id = sanitize_text_field($_POST['merchant_id']);
    $merchant_name = sanitize_text_field($_POST['merchant_name']);

    $merchant_pairs = get_option(MCWC_ConsignmentSettings::MERCHANT_MAPPING_OPTION_SLUG, []);

    if (!isset($merchant_pairs[$merchant_id])) {

        $tag = mcwc_create_consignment_tag($merchant_name);

        if (is_wp_error($tag)) {
            wp_send_json_error(['message' => __('Failed to create product tag.', 'monek-checkout')]);
        } else {
            $tag_id = $tag['term_id'];

            $merchant_pairs[$merchant_id] = [
                'name' => $merchant_name,
                'tag' => $tag_id,
            ];

            update_option(MCWC_ConsignmentSettings::MERCHANT_MAPPING_OPTION_SLUG, $merchant_pairs);
            wp_send_json_success(['tag_name' => "Mnk | $merchant_name"]);
        }
    } else {
        wp_send_json_error(['message' => __('Merchant ID already exists.', 'monek-checkout')]);
    }
}
add_action('wp_ajax_mcwc_add_merchant_pair', 'mcwc_add_merchant_pair');

/**
 * Handles deleting a merchant pair from the merchant mapping table.
 * 
 * @return void
 */
function mcwc_delete_merchant_pair()
{
    check_ajax_referer(MCWC_ConsignmentSettings::MERCHANT_MAPPING_NONCE_SLUG, 'security');

    $merchant_id = sanitize_text_field($_POST['merchant_id']);

    $merchant_pairs = get_option(MCWC_ConsignmentSettings::MERCHANT_MAPPING_OPTION_SLUG, []);

    if (isset($merchant_pairs[$merchant_id])) {
        $tag_id = $merchant_pairs[$merchant_id]['tag'];
        unset($merchant_pairs[$merchant_id]);
        update_option(MCWC_ConsignmentSettings::MERCHANT_MAPPING_OPTION_SLUG, $merchant_pairs);

        wp_delete_term($tag_id, 'product_tag');

        wp_send_json_success();
    } else {
        wp_send_json_error(['message' => __('Merchant ID not found.', 'monek-checkout')]);
    }
}
add_action('wp_ajax_mcwc_delete_merchant_pair', 'mcwc_delete_merchant_pair');

/**
 * Creates a new tag for a merchant pair.
 * 
 * @param string $merchant_id
 * @param string $merchant_name
 * @return string
 */
function mcwc_create_consignment_tag($merchant_name)
{
    $tag_name = "Mnk | $merchant_name";
    return wp_insert_term($tag_name, 'product_tag', ['description' => "Monek Checkout tag for $merchant_name"]);
}
