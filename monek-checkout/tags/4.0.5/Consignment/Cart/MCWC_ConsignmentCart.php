<?php

class MCWC_ConsignmentCart
{
    /**
     *  Check the cart for matching merchants.
     * 
     * @return int The number of matching merchants found in the cart.
     */
    public static function mcwc_check_cart_for_matching_merchants() : int  
    {
        $merchant_ids = [];
        $matching_merchants = true; 
        
        $cart_items = WC()->cart ? WC()->cart->get_cart() : [];
        
        if (empty($cart_items)) {
            return 0;
        }

        foreach ($cart_items as $cart_item) {
            $product_id = $cart_item['product_id'];
            $product_merchant_ids = MCWC_ConsignmentCart::mcwc_get_merchant_id_by_product_tags_from_pairs($product_id);

            if (empty($product_merchant_ids)) {
                $matching_merchants = false;
                continue;
            }

            if (!empty($merchant_ids)) {
                $common_merchants = array_intersect($merchant_ids, $product_merchant_ids);
                if (empty($common_merchants) || count($common_merchants) !== count($product_merchant_ids)) {
                    $matching_merchants = false;
                }
            }

            foreach ($product_merchant_ids as $merchant_id) {
                if (!in_array($merchant_id, $merchant_ids)) {
                    $merchant_ids[] = $merchant_id;
                }
            }
        }

        if ($matching_merchants) {
            return count($merchant_ids);
        } else {
            return 0;
        }
    }

    /**
     * Check the order for matching merchants.
     * 
     * @param int $order_id
     * @return int The number of matching merchants found in the order.
     */
    public static function mcwc_check_order_for_matching_merchants($order_id) : int
    {
        $order = wc_get_order($order_id);
        $items = $order->get_items();

        $merchant_ids = [];
        $matching_merchants = true;

        foreach ($items as $item) {
            $product_id = $item->get_product_id();
            $product_merchant_ids = MCWC_ConsignmentCart::mcwc_get_merchant_id_by_product_tags_from_pairs($product_id);

            if (empty($product_merchant_ids)) {
                $matching_merchants = false;
                continue;
            }

            if (!empty($merchant_ids)) {
                $common_merchants = array_intersect($merchant_ids, $product_merchant_ids);
                if (empty($common_merchants) || count($common_merchants) !== count($product_merchant_ids)) {
                    $matching_merchants = false;
                }
            }

            foreach ($product_merchant_ids as $merchant_id) {
                if (!in_array($merchant_id, $merchant_ids)) {
                    $merchant_ids[] = $merchant_id;
                }
            }
        }

        if ($matching_merchants) {
            return count($merchant_ids);
        } else {
            return 0;
        }
    }

    /**
     * Retrieve the merchant IDs based on matching tags added to the product.
     *
     * @param int $product_id The ID of the product.
     * @return array The merchant IDs that match the product's tags, or an empty array if no matches are found.
     */
    public static function mcwc_get_merchant_id_by_product_tags_from_pairs($product_id) : array
    {
        $merchant_pairs = get_option(MCWC_ConsignmentSettings::MERCHANT_MAPPING_OPTION_SLUG, []);

        $product_tags = wp_get_post_terms($product_id, 'product_tag', ['fields' => 'ids']);

        $matched_merchant_ids = [];

        foreach ($merchant_pairs as $id => $pair) {
            if (isset($pair['tag']) && is_int($pair['tag'])) {
                if (in_array($pair['tag'], $product_tags)) {
                    if (!in_array($id, $matched_merchant_ids)) {
                        $matched_merchant_ids[] = $id;
                    }
                }
            } else {
                error_log("Invalid or missing 'tag' property in merchant pair ID $id: " . print_r($pair, true));
            }
        }

        return $matched_merchant_ids;
    }
}