<?php

if (!defined('ABSPATH')) exit;

/**
 * Adds a new section to the WooCommerce Products tab.
 *
 * @param array $sections The existing WooCommerce product sections.
 * @return array The modified sections with the new custom section.
 */
function mcwc_add_monek_id_mappings_section($sections): array
{
    $sections[MCWC_ConsignmentSettings::MERCHANT_MAPPING_SECTION_SLUG] = __('Consignment Monek IDs', 'monek-checkout');
    return $sections;
}
add_filter('woocommerce_get_sections_products', 'mcwc_add_monek_id_mappings_section');

/**
 * Displays the custom HTML table for the new section.
 *
 * @param mixed $settings The existing settings.
 * @param mixed $current_section The current section being viewed.
 * @return mixed The modified settings with the HTML table.
 */
function mcwc_display_monek_id_mappings_section($settings, $current_section)
{
    if ($current_section === MCWC_ConsignmentSettings::MERCHANT_MAPPING_SECTION_SLUG) {
        $merchant_pairs = get_option(MCWC_ConsignmentSettings::MERCHANT_MAPPING_OPTION_SLUG, []);
        
        require_once plugin_dir_path(__FILE__) . 'Includes/MCWC_PageTemplate.php';
    }
    return $settings;
}
add_filter('woocommerce_get_settings_products', 'mcwc_display_monek_id_mappings_section', 10, 2);
