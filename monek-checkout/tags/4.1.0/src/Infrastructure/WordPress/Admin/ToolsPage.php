<?php

namespace Monek\Checkout\Infrastructure\WordPress\Admin;

use WP_Error;

class ToolsPage
{
    private const PAGE_SLUG = 'monek-tools';

    public function register(): void
    {
        if (! function_exists('add_management_page')) {
            return;
        }

        add_management_page(
            __('Monek diagnostics', 'monek-checkout'),
            __('Monek', 'monek-checkout'),
            'manage_woocommerce',
            self::PAGE_SLUG,
            [$this, 'render']
        );
    }

    public function render(): void
    {
        if (! current_user_can('manage_woocommerce')) {
            wp_die(__('You do not have permission to access this page.', 'monek-checkout'));
        }

        $webhookStatus    = $this->evaluateWebhook();
        $credentialStatus = $this->evaluateCredentials();

        // Build “today” links to Woo’s log viewer (site timezone)
        $today   = function_exists('current_time') ? current_time('Y-m-d') : gmdate('Y-m-d');
        $links   = $this->buildTodayLogLinks($today);

        $webhookEndpoint = function_exists('rest_url') ? rest_url('monek/v1/webhook') : '';
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Monek diagnostics', 'monek-checkout'); ?></h1>
            <p><?php esc_html_e('Use this page to review recent activity, verify webhook connectivity and confirm your credentials are set correctly.', 'monek-checkout'); ?></p>

            <h2><?php esc_html_e('Webhook heartbeat', 'monek-checkout'); ?></h2>
            <?php $this->renderNotice($webhookStatus); ?>
            <?php if ($webhookEndpoint !== '') : ?>
                <p><strong><?php esc_html_e('Endpoint URL:', 'monek-checkout'); ?></strong> <code><?php echo esc_html($webhookEndpoint); ?></code></p>
            <?php endif; ?>
            <?php if ($webhookStatus['details'] !== '') : ?>
                <p><code><?php echo esc_html($webhookStatus['details']); ?></code></p>
            <?php endif; ?>

            <h2><?php esc_html_e('Credential validation', 'monek-checkout'); ?></h2>
            <?php $this->renderNotice($credentialStatus); ?>
            <?php if ($credentialStatus['details'] !== '') : ?>
                <p><code><?php echo esc_html($credentialStatus['details']); ?></code></p>
            <?php endif; ?>

            <h2><?php esc_html_e('Recent logs (today’s quick links)', 'monek-checkout'); ?></h2>
            <p class="description">
                <?php
                printf(
                    /* translators: %s: date like 2025-11-05 */
                    esc_html__('These open the WooCommerce log viewer for %s. If a file does not exist yet, the Woo page will show "No logs found" until something is written.', 'monek-checkout'),
                    esc_html($today)
                );
                ?>
            </p>
            <ul>
                <li>
                    <a class="button" href="<?php echo esc_url($links['monek']); ?>">
                        <?php echo esc_html(sprintf(__('Open %s', 'monek-checkout'), "monek-$today")); ?>
                    </a>
                </li>
                <li>
                    <a class="button" href="<?php echo esc_url($links['monek-webhook']); ?>">
                        <?php echo esc_html(sprintf(__('Open %s', 'monek-checkout'), "monek-webhook-$today")); ?>
                    </a>
                </li>
            </ul>

            <p class="description" style="margin-top:8px;">
                <?php esc_html_e('Tip: If the Woo page opens but shows no entries, it just means nothing has been logged yet for today with that source.', 'monek-checkout'); ?>
            </p>
        </div>
        <?php
    }

    private function renderNotice(array $result): void
    {
        $status  = $result['status']  ?? 'warning';
        $message = $result['message'] ?? '';

        $class = 'notice';
        switch ($status) {
            case 'success':
                $class .= ' notice-success';
                break;
            case 'error':
                $class .= ' notice-error';
                break;
            default:
                $class .= ' notice-warning';
        }
        ?>
        <div class="<?php echo esc_attr($class); ?>"><p><?php echo esc_html($message); ?></p></div>
        <?php
    }

    /**
     * Build a WooCommerce Status → Logs URL pointing at a specific file key (without ".log").
     * We include both `file_id` and `file` params for broad WC version compatibility.
     *
     * @param string $date like "2025-11-05"
     * @return array{monek:string,monek-webhook:string}
     */
    private function buildTodayLogLinks(string $date): array
    {
        $base = function_exists('admin_url') ? admin_url('admin.php') : '/wp-admin/admin.php';

        $make = static function (string $key) use ($base): string {
            // Example key: monek-webhook-2025-11-05 (Woo adds -<hash>-log internally)
            $args = [
                'page' => 'wc-status',
                'tab'  => 'logs',
                // WC 7.x / 8.x commonly accept either of these:
                'view'    => 'single_file',
                'file_id' => $key,
                'file'    => $key,
            ];
            return add_query_arg($args, $base);
        };

        return [
            'monek'         => $make("monek-$date"),
            'monek-webhook' => $make("monek-webhook-$date"),
        ];
    }

    /**
     * @return array{status:string,message:string,details:string}
     */
    private function evaluateWebhook(): array
    {
        if (! function_exists('wp_remote_post') || ! function_exists('rest_url')) {
            return [
                'status' => 'warning',
                'message' => __('WordPress REST API functions are unavailable.', 'monek-checkout'),
                'details' => '',
            ];
        }

        $endpoint = rest_url('monek/v1/webhook');
        $response = wp_remote_post(
            $endpoint,
            [
                'timeout' => 10,
                'headers' => ['Content-Type' => 'application/json'],
                'body'    => wp_json_encode(['diagnostics' => true]),
            ]
        );

        if ($response instanceof WP_Error) {
            return [
                'status'  => 'error',
                'message' => __('Failed to contact the webhook endpoint.', 'monek-checkout'),
                'details' => $this->truncateDetails($response->get_error_message()),
            ];
        }

        $statusCode = (int) wp_remote_retrieve_response_code($response);
        $body       = (string) wp_remote_retrieve_body($response);

        if ($statusCode === 401) {
            return [
                'status'  => 'success',
                'message' => __('The webhook endpoint responded as expected (signature required).', 'monek-checkout'),
                'details' => sprintf(__('Response: HTTP %d', 'monek-checkout'), $statusCode),
            ];
        }

        if ($statusCode >= 200 && $statusCode < 300) {
            return [
                'status'  => 'success',
                'message' => __('The webhook endpoint is reachable.', 'monek-checkout'),
                'details' => sprintf(__('Response: HTTP %1$d %2$s', 'monek-checkout'), $statusCode, $this->truncateDetails($body)),
            ];
        }

        return [
            'status'  => 'warning',
            'message' => __('The webhook endpoint responded with an unexpected status code.', 'monek-checkout'),
            'details' => sprintf(__('Response: HTTP %1$d %2$s', 'monek-checkout'), $statusCode, $this->truncateDetails($body)),
        ];
    }

    /**
     * @return array{status:string,message:string,details:string}
     */
    private function evaluateCredentials(): array
    {
        if (! function_exists('get_option')) {
            return [
                'status'  => 'warning',
                'message' => __('Unable to read gateway settings from the database.', 'monek-checkout'),
                'details' => '',
            ];
        }

        $settings       = get_option('woocommerce_monek-checkout_settings', []);
        $publishableKey = '';
        $secretKey      = '';

        if (is_array($settings)) {
            $publishableKey = isset($settings['publishable_key']) ? trim((string) $settings['publishable_key']) : '';
            $secretKey      = isset($settings['secret_key']) ? trim((string) $settings['secret_key']) : '';
        }

        if ($publishableKey === '' || $secretKey === '') {
            return [
                'status'  => 'warning',
                'message' => __('Add your publishable and secret keys on the payment settings screen to run the validation test.', 'monek-checkout'),
                'details' => '',
            ];
        }

        if (! function_exists('wp_remote_post')) {
            return [
                'status'  => 'warning',
                'message' => __('WordPress HTTP functions are unavailable.', 'monek-checkout'),
                'details' => '',
            ];
        }

        $response = wp_remote_post(
            'https://api.monek.com/embedded-checkout/payment',
            [
                'timeout' => 10,
                'headers' => [
                    'Content-Type' => 'application/json',
                    'X-Api-Key'    => $publishableKey,
                    'X-Secret-Key' => $secretKey,
                ],
                'body'    => wp_json_encode(['diagnostics' => true]),
            ]
        );

        if ($response instanceof WP_Error) {
            return [
                'status'  => 'error',
                'message' => __('Failed to reach the Monek API.', 'monek-checkout'),
                'details' => $this->truncateDetails($response->get_error_message()),
            ];
        }

        $statusCode = (int) wp_remote_retrieve_response_code($response);
        $body       = (string) wp_remote_retrieve_body($response);
        $details    = sprintf(__('Response: HTTP %1$d %2$s', 'monek-checkout'), $statusCode, $this->truncateDetails($body));

        if ($statusCode === 401 || $statusCode === 403) {
            return [
                'status'  => 'error',
                'message' => __('The Monek API rejected the provided credentials.', 'monek-checkout'),
                'details' => $details,
            ];
        }

        if ($statusCode >= 200 && $statusCode < 500) {
            return [
                'status'  => 'success',
                'message' => __('The Monek API is reachable with the configured credentials.', 'monek-checkout'),
                'details' => $details,
            ];
        }

        return [
            'status'  => 'warning',
            'message' => __('Received an unexpected response from the Monek API.', 'monek-checkout'),
            'details' => $details,
        ];
    }

    private function truncateDetails(string $value): string
    {
        $trimmed = trim(function_exists('wp_strip_all_tags') ? wp_strip_all_tags($value) : $value);
        if ($trimmed === '') {
            return '';
        }

        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            return mb_strlen($trimmed) > 400 ? mb_substr($trimmed, 0, 400) . '…' : $trimmed;
        }

        return strlen($trimmed) > 400 ? substr($trimmed, 0, 400) . '…' : $trimmed;
    }
}
