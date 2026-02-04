<?php
/**
 * Plugin Name: Monek Checkout
 * Description: Embedded checkout experience for WooCommerce powered by Monek.
 * Author: Monek Ltd
 * Author URI: https://www.monek.com
 * Version: 4.1.0
 * Text Domain: monek-checkout
 * Requires Plugins: woocommerce
 * Requires at least: 6.0
 * Tested up to: 6.8.2
 * Requires PHP: 7.4
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
    delete_option('monek_apple_pay_install_failed');
    wp_send_json_success();
});



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
            new BlockCompatibilityDeclarer(),
            new BlockPaymentRegistrar()
        );

        $plugin->initialise();
    }
}

monek_bootstrap_plugin();
