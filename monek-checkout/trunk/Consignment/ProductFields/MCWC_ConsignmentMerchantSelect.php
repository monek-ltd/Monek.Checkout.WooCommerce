<?php

/* DOES NOT WORK WITH NEW PRODUCT PAGE CURRENTLY IN BETA, PLUGIN SUPPORT HAS NOT BEEN ADDED */
class MCWC_ConsignmentMerchantSelect
{
    /**
     * Initializes the Consignment Merchant Select. 
     *
     * @return void
     */
    public static function init()
    {
        add_action('woocommerce_product_options_general_product_data', [__CLASS__, 'build_consignment_merchant_select']);
        add_action('woocommerce_process_product_meta', [__CLASS__, 'save_consignment_merchant']);
    }

    /**
     * Generates the Consignment Merchant Select to be displayed on the product page.
     * 
     * @param mixed $loop
     * @param mixed $variation
     * @return void
     */
    public static function build_consignment_merchant_select($loop = 0, $variation = null)
    {
        echo '<div class="options_group">';

        woocommerce_wp_select([
            'id' => 'consignment_merchant',
            'label' => __('Consignment Merchant', 'monek-checkout'),
            'options' => self::get_merchants(),
            'description' => __('Select the merchant for this product.', 'monek-checkout'),
        ]);

        echo '</div>';
    }

    /**
     * Get the merchants stored in the database.
     * 
     * @return array The merchants.
     */
    public static function get_merchants() : array
    {
        $merchant_pairs = get_option(MCWC_ConsignmentSettings::MERCHANT_MAPPING_OPTION_SLUG, []);
        
        $options[''] = __('Select a Merchant', 'monek-checkout');

        if (!empty($merchant_pairs)) {
            foreach ($merchant_pairs as $id => $name) {
                $options[$id] = $name;
            }
        }

        return $options;
    }

    /**
     * Saves the selected consignment merchant to the product.
     * 
     * @param int $post_id The ID of the product.
     * @return void
     */
    public static function save_consignment_merchant($post_id)
    {
        if (isset($_POST[MCWC_ConsignmentSettings::CONSIGNMENT_MERCHANT_PRODUCT_META_KEY])) {   
            $selected_pair = sanitize_text_field($_POST[MCWC_ConsignmentSettings::CONSIGNMENT_MERCHANT_PRODUCT_META_KEY]);     
            
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
                update_post_meta($post_id, MCWC_ConsignmentSettings::CONSIGNMENT_MERCHANT_PRODUCT_META_KEY, $selected_pair);
            }
        }
    }
}

