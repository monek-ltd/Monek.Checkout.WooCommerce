<?php

namespace Monek\Checkout\Infrastructure\WordPress\Admin;

class PluginActionLinkProvider
{
    public function appendSettingsLink(array $links): array
    {
        $settingsUrl = admin_url('admin.php?page=wc-settings&tab=checkout&section=monek-checkout');
        $settingsLink = '<a href="' . esc_url($settingsUrl) . '">' . esc_html__('Settings', 'monek-checkout') . '</a>';
        array_unshift($links, $settingsLink);

        return $links;
    }
}
