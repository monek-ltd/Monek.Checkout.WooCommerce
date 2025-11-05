<?php

namespace Monek\Checkout;

use Monek\Checkout\Infrastructure\WordPress\Admin\AdminStyleEnqueuer;
use Monek\Checkout\Infrastructure\WordPress\Admin\PluginActionLinkProvider;
use Monek\Checkout\Infrastructure\WordPress\Admin\SettingsNoticePresenter;
use Monek\Checkout\Infrastructure\WordPress\Admin\ToolsPage;
use Monek\Checkout\Infrastructure\WordPress\Blocks\BlockPaymentRegistrar;
use Monek\Checkout\Infrastructure\WordPress\Compatibility\BlockCompatibilityDeclarer;
use Monek\Checkout\Infrastructure\WordPress\Gateway\GatewayBootstrapper;
use Monek\Checkout\Infrastructure\WordPress\Status\OrderStatusRegistrar;
use Monek\Checkout\Infrastructure\WordPress\Webhook\WebhookRouteRegistrar;

class Plugin
{
    private GatewayBootstrapper $gatewayBootstrapper;
    private PluginActionLinkProvider $actionLinkProvider;
    private SettingsNoticePresenter $settingsNoticePresenter;
    private ToolsPage $toolsPage;
    private OrderStatusRegistrar $orderStatusRegistrar;
    private AdminStyleEnqueuer $adminStyleEnqueuer;
    private WebhookRouteRegistrar $webhookRouteRegistrar;
    private BlockCompatibilityDeclarer $blockCompatibilityDeclarer;
    private BlockPaymentRegistrar $blockPaymentRegistrar;

    public function __construct(
        GatewayBootstrapper $gatewayBootstrapper,
        PluginActionLinkProvider $actionLinkProvider,
        SettingsNoticePresenter $settingsNoticePresenter,
        ToolsPage $toolsPage,
        OrderStatusRegistrar $orderStatusRegistrar,
        AdminStyleEnqueuer $adminStyleEnqueuer,
        WebhookRouteRegistrar $webhookRouteRegistrar,
        BlockCompatibilityDeclarer $blockCompatibilityDeclarer,
        BlockPaymentRegistrar $blockPaymentRegistrar
    ) {
        $this->gatewayBootstrapper = $gatewayBootstrapper;
        $this->actionLinkProvider = $actionLinkProvider;
        $this->settingsNoticePresenter = $settingsNoticePresenter;
        $this->toolsPage = $toolsPage;
        $this->orderStatusRegistrar = $orderStatusRegistrar;
        $this->adminStyleEnqueuer = $adminStyleEnqueuer;
        $this->webhookRouteRegistrar = $webhookRouteRegistrar;
        $this->blockCompatibilityDeclarer = $blockCompatibilityDeclarer;
        $this->blockPaymentRegistrar = $blockPaymentRegistrar;
    }

    public function initialise(): void
    {
        add_action('plugins_loaded', [$this->gatewayBootstrapper, 'bootstrap'], 11);
        add_filter('plugin_action_links_' . plugin_basename(MONEK_PLUGIN_FILE), [$this->actionLinkProvider, 'appendSettingsLink']);
        add_action('init', [$this->orderStatusRegistrar, 'register']);
        add_filter('wc_order_statuses', [$this->orderStatusRegistrar, 'injectIntoStatuses']);
        add_action('admin_head', [$this->adminStyleEnqueuer, 'outputStatusStyles']);
        add_action('admin_notices', [$this->settingsNoticePresenter, 'maybeDisplayNotice']);
        add_action('admin_menu', [$this->toolsPage, 'register']);
        add_action('rest_api_init', [$this->webhookRouteRegistrar, 'register']);
        add_action('before_woocommerce_init', [$this->blockCompatibilityDeclarer, 'declareCompatibility']);
        add_action('woocommerce_blocks_payment_method_type_registration', [$this->blockPaymentRegistrar, 'register']);
    }
}
