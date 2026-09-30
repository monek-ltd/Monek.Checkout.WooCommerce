<?php

namespace Monek\Checkout\Infrastructure\WordPress\Admin;

use function __;
use function admin_url;
use function current_user_can;
use function esc_html;
use function esc_html__;
use function esc_url;
use function get_option;
use function is_admin;
use function wp_kses_post;

class SettingsNoticePresenter
{
    private const SETTINGS_OPTION_KEY = 'woocommerce_monek-checkout_settings';

    public function maybeDisplayNotice(): void
    {
        if (! is_admin()) {
            return;
        }

        if (! current_user_can('manage_woocommerce')) {
            return;
        }

        $settings = get_option(self::SETTINGS_OPTION_KEY, []);
        if (! is_array($settings)) {
            $settings = [];
        }

        if (($settings['enabled'] ?? 'no') !== 'yes') {
            return;
        }

        $missingFields = $this->determineMissingFields($settings);
        if (empty($missingFields)) {
            return;
        }

        $settingsUrl = admin_url('admin.php?page=wc-settings&tab=checkout&section=monek-checkout');
        $message = sprintf(
            /* translators: 1: list of missing settings, 2: opening anchor tag, 3: closing anchor tag */
            __('Monek Checkout is almost ready. Please add your %1$s in the %2$sMonek Checkout settings%3$s.', 'monek-checkout'),
            $this->formatFieldList($missingFields),
            '<a href="' . esc_url($settingsUrl) . '">',
            '</a>'
        );

        printf('<div class="notice notice-warning"><p>%s</p></div>', wp_kses_post($message));
    }

    private function determineMissingFields(array $settings): array
    {
        $fieldLabels = [
            'publishable_key' => __('publishable key', 'monek-checkout'),
            'secret_key' => __('secret key', 'monek-checkout'),
            'svix_signing_secret' => __('webhook signing secret', 'monek-checkout'),
        ];

        $missing = [];
        foreach ($fieldLabels as $field => $label) {
            if (empty($settings[$field])) {
                $missing[] = $label;
            }
        }

        return $missing;
    }

    private function formatFieldList(array $fields): string
    {
        $total = count($fields);

        if ($total <= 1) {
            $field = array_shift($fields);
            return $field === null ? '' : esc_html($field);
        }

        $escaped = array_map(static fn ($field) => esc_html($field), $fields);
        $lastField = array_pop($escaped);

        return implode(', ', $escaped) . ' ' . esc_html__('and', 'monek-checkout') . ' ' . $lastField;
    }
}
