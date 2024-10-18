<?php

if (!defined('ABSPATH')) exit;

add_filter('woocommerce_add_to_cart_validation', 'validate_product_merchant', 10, 3);

/**
 * Validates that the passed product is from the same merchant as the cart items. 
 *
 * @param mixed $passed
 * @param mixed $product_id
 * @param mixed $quantity
 * @return bool
 */
function validate_product_merchant($passed, $product_id, $quantity) : bool {

    $cart = WC()->cart->get_cart();

    $merchant_id = get_post_meta($product_id, MCWC_ConsignmentSettings::CONSIGNMENT_MERCHANT_PRODUCT_META_KEY, true);
    if (empty($merchant_id)) {
        // This is a consignment product, but it doesn't have a merchant ID set.
        /*    
        wc_add_notice('Invalid Monek ID: Monek ID is required for consignment sales', 'error');
        return false;
        */
        $merchant_id = '';
    }

    $same_merchant_id = true;

    foreach ($cart as $cart_item_key => $cart_item) {
        $product_id = $cart_item['product_id'];
        $current_merchant_id = get_post_meta($product_id, MCWC_ConsignmentSettings::CONSIGNMENT_MERCHANT_PRODUCT_META_KEY, true);

        if ($current_merchant_id !== $merchant_id) {
            $same_merchant_id = false;
            break;
        }
    }

    if (!$same_merchant_id) {
        wc_add_notice('This store operates on a consignment basis, allowing purchases on behalf of multiple merchants. Please complete your current purchase before adding this item, or clear your basket and try again.', 'error');
        return false;
    }

    return $passed;
}
