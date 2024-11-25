<?php if (!defined('ABSPATH')) exit; ?>

<h3><?php _e('Add New Merchant', 'monek-checkout'); ?></h3>
<form id="add-merchant-pair-form">
    <input type="text" id="new-merchant-id" placeholder="<?php _e('Monek ID', 'monek-checkout'); ?>" required />
    <input type="text" id="new-merchant-name" placeholder="<?php _e('Merchant Name', 'monek-checkout'); ?>" required />
    <button type="button" class="button button-primary" id="add-merchant-pair-button">
        <?php _e('Add Merchant', 'monek-checkout'); ?>
    </button>
</form>

<h2><?php _e('Manage Monek IDs for Product Consignment', 'monek-checkout'); ?></h2>
<table class="wp-list-table widefat fixed striped">
    <thead>
        <tr>
            <th><?php _e('Monek ID', 'monek-checkout'); ?></th>
            <th><?php _e('Merchant Name', 'monek-checkout'); ?></th>
            <th><?php _e('Product Tag', 'monek-checkout'); ?></th>
            <th><?php _e('Actions', 'monek-checkout'); ?></th>
        </tr>
    </thead>
    <tbody id="merchant-pairs-table">
        <?php if (!empty($merchant_pairs)) : ?>
            <?php foreach ($merchant_pairs as $id => $data) : ?>
                <tr data-id="<?php echo esc_attr($id); ?>">
                    <td><?php echo esc_html($id); ?></td>
                    <td><?php echo esc_html($data['name']); ?></td>
                    <td>
                        <?php
                        $tag = get_term($data['tag'], 'product_tag');
                        echo $tag ? esc_html($tag->name) : __('No tag found', 'monek-checkout');
                        ?>
                    </td>
                    <td>
                        <button class="button delete-merchant-pair" data-id="<?php echo esc_attr($id); ?>">
                            <?php _e(MCWC_ConsignmentSettings::DELETE_TEXT, 'monek-checkout'); ?>
                        </button>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php else : ?>
            <tr><td colspan="4"><?php _e('No mappings found.', 'monek-checkout'); ?></td></tr>
        <?php endif; ?>
    </tbody>
</table>
