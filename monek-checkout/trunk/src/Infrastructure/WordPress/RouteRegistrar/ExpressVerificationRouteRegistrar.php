<?php

namespace Monek\Checkout\Infrastructure\WordPress\RouteRegistrar;

use Monek\Checkout\Application\Checkout\ExpressPaymentVerifier;
use Monek\Checkout\Application\Checkout\ExpressVerificationStore;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Registers the REST endpoint that the SDK's onPaymentAuthorised callback calls
 * during an Apple Pay (express) authorisation.
 *
 * The endpoint verifies the signed token server-side and, on success, records a
 * single-use marker keyed by payment reference. The marker is later consumed by
 * the express checkout handler before the order is completed, so completing an
 * express order requires a genuine, signature-verified authorisation rather than
 * a browser-reported "success".
 */
class ExpressVerificationRouteRegistrar
{
    private ExpressPaymentVerifier $verifier;
    private ExpressVerificationStore $store;

    public function __construct(
        ?ExpressPaymentVerifier $verifier = null,
        ?ExpressVerificationStore $store = null
    ) {
        $this->verifier = $verifier ?? new ExpressPaymentVerifier();
        $this->store = $store ?? new ExpressVerificationStore();
    }

    public function register(): void
    {
        register_rest_route(
            'monek/v1',
            '/express/authorise',
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'handleAuthorise'],
                'permission_callback' => '__return_true',
            ]
        );
    }

    public function handleAuthorise(WP_REST_Request $request): WP_REST_Response
    {
        $body = $request->get_json_params();
        if (! is_array($body)) {
            return new WP_REST_Response(['verified' => false, 'error' => 'invalid_json'], 400);
        }

        $token = $this->readString($body, 'verification');
        $paymentReference = $this->readString($body, 'paymentReference');
        $sessionId = $this->readString($body, 'sessionId');

        if ($token === '' || $paymentReference === '') {
            return new WP_REST_Response(['verified' => false, 'error' => 'missing_fields'], 400);
        }

        $secret = $this->getSecretKey();
        if ($secret === '') {
            $this->log('express verify: no secret key configured', [], 'error');

            return new WP_REST_Response(['verified' => false, 'error' => 'not_configured'], 500);
        }

        $result = $this->verifier->verify($token, $secret, [
            'paymentReference' => $paymentReference,
            'sessionId' => $sessionId,
        ]);

        if (! $result['verified']) {
            $this->log('express verify: rejected', [
                'reason' => $result['reason'],
                'reference' => $paymentReference,
            ], 'warning');

            return new WP_REST_Response([
                'verified' => false,
                'error' => $result['reason'] ?: 'verification_failed',
            ], 401);
        }

        $this->store->remember($paymentReference, $result['claims']);

        $this->log('express verify: accepted', ['reference' => $paymentReference]);

        return new WP_REST_Response(['verified' => true], 200);
    }

    private function getSecretKey(): string
    {
        if (! function_exists('get_option')) {
            return '';
        }

        $settings = get_option('woocommerce_monek-checkout_settings', []);
        if (! is_array($settings) || ! isset($settings['secret_key'])) {
            return '';
        }

        return trim((string) $settings['secret_key']);
    }

    private function readString(array $body, string $key): string
    {
        if (! isset($body[$key]) || ! is_scalar($body[$key])) {
            return '';
        }

        return trim((string) $body[$key]);
    }

    private function log(string $message, array $context = [], string $level = 'info'): void
    {
        if (function_exists('wc_get_logger')) {
            wc_get_logger()->log($level, '[monek] ' . $message, ['source' => 'monek-express'] + $context);
            return;
        }

        error_log('[monek] ' . $message . ($context ? ' ' . wp_json_encode($context) : ''));
    }
}
