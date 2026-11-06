<?php
/**
 * Plugin Name: Monek Checkout
 * Description: Embedded checkout experience for WooCommerce powered by Monek.
 * Author: Monek Ltd
 * Author URI: https://www.monek.com
 * Version: 4.4.0
 * Text Domain: monek-checkout
 * Requires Plugins: woocommerce
 * Requires at least: 6.0
 * Tested up to: 7.1
 * Requires PHP: 8.3     
 * License: GPLv3 or later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 */

use Monek\Checkout\Autoloader;
use Monek\Checkout\Infrastructure\WordPress\Admin\AdminStyleEnqueuer;
use Monek\Checkout\Infrastructure\WordPress\Admin\PluginActionLinkProvider;
use Monek\Checkout\Infrastructure\WordPress\Admin\SettingsNoticePresenter;
use Monek\Checkout\Infrastructure\WordPress\Admin\ToolsPage;
use Monek\Checkout\Infrastructure\WordPress\Blocks\BlockPaymentRegistrar;
use Monek\Checkout\Infrastructure\WordPress\Compatibility\BlockCompatibilityDeclarer;
use Monek\Checkout\Infrastructure\WordPress\RouteRegistrar\ExpressVerificationRouteRegistrar;
use Monek\Checkout\Infrastructure\WordPress\Gateway\GatewayBootstrapper;
use Monek\Checkout\Infrastructure\WordPress\Status\OrderStatusRegistrar;
use Monek\Checkout\Infrastructure\WordPress\Webhook\WebhookRouteRegistrar;
use Monek\Checkout\Plugin;
use Monek\Checkout\PluginMetadata;
use Monek\Checkout\Infrastructure\Logging\Logger;

if (! defined('ABSPATH')) {
    exit;
}

if (! defined('MONEK_PLUGIN_FILE')) {
    define('MONEK_PLUGIN_FILE', __FILE__);
}

if (! defined('MONEK_PLUGIN_DIR')) {
    define('MONEK_PLUGIN_DIR', plugin_dir_path(__FILE__));
}

if (! defined('MONEK_PLUGIN_URL')) {
    define('MONEK_PLUGIN_URL', plugin_dir_url(__FILE__));
}

if (! defined('MONEK_CHECKOUT_SDK_URL')) {
    define('MONEK_CHECKOUT_SDK_URL', 'https://checkout-js.monek.com/monek-checkout.iife.js');
}

require_once MONEK_PLUGIN_DIR . 'src/Autoloader.php';
Autoloader::register();

if (! function_exists('monek_get_plugin_version')) {
    function monek_get_plugin_version(): string
    {
        $metadata = new PluginMetadata(MONEK_PLUGIN_FILE);
        return $metadata->getVersion();
    }
}

register_activation_hook( __FILE__, 'monek_plugin_activate' );

function monek_plugin_activate(): void
{
    update_option( 'monek_apple_pay_needs_domain_file', true );
}

add_action( 'init', 'monek_install_apple_pay_domain_file' );

function monek_install_apple_pay_domain_file(): void
{
    if ( ! get_option( 'monek_apple_pay_needs_domain_file' ) ) {
        return;
    }

    $logger = new Logger();

    // Prevent repeat attempts
    delete_option( 'monek_apple_pay_needs_domain_file' );

    $installer = new \Monek\Checkout\Application\Checkout\ApplePayFileInstaller($logger);
    $success = $installer->downloadFile();  
    if ( ! $success ) {
        update_option( 'monek_apple_pay_install_failed', true );
    }
}

add_action( 'admin_notices', function () {
    if ( ! get_option( 'monek_apple_pay_install_failed' ) ) {
        return;
    }

    $screen = get_current_screen();
    if ( ! $screen || ! in_array( $screen->id, [ 'woocommerce_page_wc-settings', 'plugins' ], true ) ) {
        return;
    }
    
    ?>
    <div class="notice notice-error is-dismissible monek-apple-pay-notice">
        <p>
            <strong>Monek Checkout – Apple Pay setup failed.</strong><br>
            We couldn’t install the Apple Pay domain verification file automatically.
            Please check file permissions or install it manually.
        </p>
    </div>
    <?php
});

add_action('wp_ajax_monek_dismiss_apple_pay_notice', function () {
    check_ajax_referer('monek_dismiss_apple_pay_notice', '_wpnonce');
    delete_option('monek_apple_pay_install_failed');
    wp_send_json_success();
});

add_action('wp_ajax_monek_log_express_checkout', 'monek_log_express_checkout');
add_action('wp_ajax_nopriv_monek_log_express_checkout', 'monek_log_express_checkout');

function monek_log_express_checkout(): void
{
    check_ajax_referer('monek_log_express_checkout', 'nonce');

    $flow = isset($_POST['flow']) && 'blocks' === $_POST['flow'] ? 'blocks' : 'legacy';
    $status = isset($_POST['status']) ? absint($_POST['status']) : 0;
    $responseJson = isset($_POST['response']) ? wp_unslash($_POST['response']) : '';

    if (! is_string($responseJson) || strlen($responseJson) > 20000) {
        wp_send_json_error(null, 400);
    }

    $response = json_decode($responseJson, true);
    if (! is_array($response)) {
        $response = [];
    }

    $text = static fn ($value): string => is_scalar($value)
        ? substr(wp_strip_all_tags((string) $value), 0, 500)
        : '';

    // Log only whitelisted fields, the response body can contain customer data
    if ('blocks' === $flow) {
        $paymentResult = is_array($response['payment_result'] ?? null) ? $response['payment_result'] : [];
        $outcome = $text($paymentResult['payment_status'] ?? '');
        $summary = [
            'payment_status' => $outcome,
            'redirect_url' => $text($paymentResult['redirect_url'] ?? ''),
            'error_code' => $text($response['code'] ?? ''),
            'error_message' => $text($response['message'] ?? ''),
        ];
        $level = 'success' === $outcome ? 'info' : ($status >= 400 || 'failure' === $outcome ? 'error' : 'warning');
    } else {
        $outcome = $text($response['result'] ?? '');
        $summary = [
            'result' => $outcome,
            'redirect' => $text($response['redirect'] ?? ''),
            'messages' => $text($response['messages'] ?? ''),
        ];
        $level = match ($outcome) {
            'success' => 'info',
            'failure' => 'error',
            default => 'warning',
        };
    }

    $paymentReference = isset($_POST['reference']) && is_string($_POST['reference'])
        ? sanitize_text_field(wp_unslash($_POST['reference']))
        : '';

    wc_get_logger()->log($level, sprintf('Express checkout order creation response (%s)', $flow), [
        'source' => 'monek-express',
        'flow' => $flow,
        'payment_reference' => $paymentReference,
        'http_status' => $status,
        'checkout_response' => $summary,
    ]);

    // The wallet payment is already authorised, so tell the shopper the order failed.
    if ('legacy' === $flow && ($status >= 400 || 'success' !== $outcome)) {
        $message = $paymentReference !== ''
            ? sprintf(
                /* translators: %s: payment reference */
                __('Your payment was authorised but we could not complete your order. Please contact the store quoting reference %s.', 'monek-checkout'),
                $paymentReference
            )
            : __('Your payment was authorised but we could not complete your order. Please contact the store.', 'monek-checkout');

        ob_start();
        wc_print_notice($message, 'error');
        wp_send_json_success(['notice' => ob_get_clean()]);
    }

    wp_send_json_success();
}

if (! function_exists('monek_bootstrap_plugin')) {
    function monek_bootstrap_plugin(): void
    {
        $plugin = new Plugin(
            new GatewayBootstrapper(),
            new PluginActionLinkProvider(),
            new SettingsNoticePresenter(),
            new ToolsPage(),
            new OrderStatusRegistrar(),
            new AdminStyleEnqueuer(),
            new WebhookRouteRegistrar(),
            new ExpressVerificationRouteRegistrar(),
            new BlockCompatibilityDeclarer(),
            new BlockPaymentRegistrar()
        );

        $plugin->initialise();
    }
}

monek_bootstrap_plugin();
