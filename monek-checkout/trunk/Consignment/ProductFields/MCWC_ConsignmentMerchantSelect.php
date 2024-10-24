<?php

if (!defined('ABSPATH')) exit;

/**
    * Generates the Consignment Merchant Select to be displayed on the product page.
    * 
    * @param mixed $loop
    * @param mixed $variation
    * @return void
    */
function mcwc_build_consignment_merchant_select($loop = 0, $variation = null)
{
    $merchant_pairs = get_option(MCWC_ConsignmentSettings::MERCHANT_MAPPING_OPTION_SLUG, []);
        
    $options[''] = __('Select a Merchant', 'monek-checkout');
    
    if (!empty($merchant_pairs)) {
        foreach ($merchant_pairs as $id => $name) {
            $options[$id] = $name;
        }
    }

    echo '<div class="options_group">';

    woocommerce_wp_select([
        'id' => MCWC_ConsignmentSettings::CONSIGNMENT_MERCHANT_PRODUCT_META_KEY,
        'label' => __('Consignment Merchant', 'monek-checkout'),
        'options' => $options,
        'description' => __('Select the merchant for this product.', 'monek-checkout'),
    ]);

    echo '</div>';
}

/**
    * Saves the selected consignment merchant to the product.
	* 
	* @param int $post_id The ID of the product.
	* @return void
	*/
function mcwc_save_consignment_merchant($post_id){
    error_log( 'Saving Consignment Merchant for post ' . $post_id );
    if (isset($_POST[MCWC_ConsignmentSettings::CONSIGNMENT_MERCHANT_PRODUCT_META_KEY])) {   
            
        error_log( 'Form Data Set: ' . $_POST[MCWC_ConsignmentSettings::CONSIGNMENT_MERCHANT_PRODUCT_META_KEY]);

        $selected_pair = sanitize_text_field($_POST[MCWC_ConsignmentSettings::CONSIGNMENT_MERCHANT_PRODUCT_META_KEY]);     
            
        error_log( 'Select Pair: ' . $selected_pair );
        // Disables the ability to save the product if the selected merchant is empty
        /*
        if (empty($selected_pair)) {
            wp_die(
                __('Please select a valid consignment merchant.', 'monek-checkout'),
                __('Error: Missing Consignment Merchant', 'monek-checkout'),
                [
                    'response' => 403,
                    'back_link' => true
                ]
            );
        } else {
        */
        update_post_meta($post_id, MCWC_ConsignmentSettings::CONSIGNMENT_MERCHANT_PRODUCT_META_KEY, $selected_pair);
        /*
        }
        */
            
        error_log( 'Product Updated' );
    }
    else {
        error_log( 'Form Data not set: ' . $_POST[MCWC_ConsignmentSettings::CONSIGNMENT_MERCHANT_PRODUCT_META_KEY]);
    }

}
add_action('woocommerce_product_options_general_product_data', 'mcwc_build_consignment_merchant_select');
add_action('woocommerce_new_product', 'mcwc_save_consignment_merchant', 1);
add_action('woocommerce_update_product', 'mcwc_save_consignment_merchant', 1);

