<?php

namespace Monek\Checkout\Infrastructure\WordPress\Webhook;

use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

class WebhookRouteRegistrar
{
    private const SIGNATURE_TOLERANCE_SECONDS = 300;

    public function register(): void
    {
        $this->log('rest_api_init fired');

        register_rest_route(
            'monek/v1',
            '/webhook',
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'handleWebhook'],
                'permission_callback' => '__return_true',
            ]
        );
    }

    public function handleWebhook(WP_REST_Request $request): WP_REST_Response
    {
        $signatureReason = null;
        if (! $this->isSignatureValid($request, $signatureReason)) {
            $this->log('webhook: signature verification failed', ['reason' => $signatureReason], 'warning');

            return new WP_REST_Response(['ok' => false, 'error' => 'invalid_signature'], 401);
        }

        if ($signatureReason === 'no_secret') {
            $this->log('webhook: signature verification skipped (no secret configured)', [], 'debug');
        }

        $body = $request->get_json_params();
        if (! is_array($body)) {
            return new WP_REST_Response(['ok' => false, 'error' => 'invalid_json'], 400);
        }

        $paymentReference = $this->extractPaymentReference($body);
        if ($paymentReference === '') {
            $this->log('webhook: missing paymentReference', ['body' => $body], 'warning');
            return new WP_REST_Response(['ok' => false, 'error' => 'missing_reference'], 400);
        }

        $orderId = $this->locateOrderByPaymentReference($paymentReference);
        if (! $orderId) {
            $this->log('webhook: order not found', ['reference' => $paymentReference], 'warning');
            return new WP_REST_Response([
                'ok' => true,
                'error' => 'order_not_found',
                'reference' => $paymentReference,
            ], 200);
        }

        $order = wc_get_order($orderId);
        if (! $order) {
            return new WP_REST_Response([
                'ok' => false,
                'error' => 'order_load_failed',
                'order_id' => $orderId,
            ], 500);
        }

        $order->update_meta_data('_monek_last_webhook', wp_json_encode($body));
        $targetStatus = 'payment-confirmed';

        if ($order->get_status() !== $targetStatus) {
            $order->update_status(
                $targetStatus,
                sprintf('Webhook set status to %s (ref: %s).', $targetStatus, $paymentReference)
            );
        } else {
            $order->add_order_note(sprintf('Webhook ping received (already %s). Ref: %s', $targetStatus, $paymentReference));
        }

        $order->save();

        $this->log('webhook: status updated', [
            'reference' => $paymentReference,
            'order_id' => $orderId,
            'status' => $order->get_status(),
        ]);

        return new WP_REST_Response([
            'ok' => true,
            'reference' => $paymentReference,
            'order_id' => $orderId,
            'status' => $order->get_status(),
        ], 200);
    }

    private function extractPaymentReference(array $body): string
    {
        if (isset($body['paymentReference'])) {
            return (string) $body['paymentReference'];
        }

        if (isset($body['Data']['PaymentReference'])) {
            return (string) $body['Data']['PaymentReference'];
        }

        if (isset($body['reference'])) {
            return (string) $body['reference'];
        }

        return '';
    }

    private function locateOrderByPaymentReference(string $paymentReference): ?int
    {
        if (! function_exists('wc_get_orders')) {
            return null;
        }

        $orders = wc_get_orders([
            'limit' => 1,
            'return' => 'ids',
            'meta_key' => '_monek_payment_reference',
            'meta_value' => $paymentReference,
            'orderby' => 'date',
            'order' => 'DESC',
            'type' => 'shop_order',
            'status' => array_keys(wc_get_order_statuses()),
        ]);

        if (! is_array($orders) || $orders === []) {
            return null;
        }

        return (int) $orders[0];
    }

    private function isSignatureValid(WP_REST_Request $request, ?string &$reason = null): bool
    {
        $secret = $this->getSigningSecret();
        if ($secret === '') {
            $reason = 'no_secret';
            return true;
        }

        $preparedSecret = $this->prepareSigningSecret($secret);
        if ($preparedSecret === '') {
            $reason = 'secret_invalid';
            return false;
        }

        $svixId = $this->getHeaderValue($request, 'svix-id');
        $svixTimestamp = $this->getHeaderValue($request, 'svix-timestamp');
        $svixSignature = $this->getHeaderValue($request, 'svix-signature');

        if ($svixId === '' || $svixTimestamp === '' || $svixSignature === '') {
            $reason = 'missing_headers';
            return false;
        }

        if (! $this->isTimestampFresh($svixTimestamp)) {
            $reason = 'timestamp_out_of_range';
            return false;
        }

        $payload = (string) $request->get_body();
        $payloadToSign = $svixId . '.' . $svixTimestamp . '.' . $payload;
        $expectedSignature = base64_encode(hash_hmac('sha256', $payloadToSign, $preparedSecret, true));

        $signatures = $this->extractSignatures($svixSignature);
        if (isset($signatures['v1'])) {
            foreach ($signatures['v1'] as $candidate) {
                if (hash_equals($expectedSignature, $candidate)) {
                    $reason = 'verified';
                    return true;
                }
            }
        }

        $reason = 'signature_mismatch';

        return false;
    }

    private function getSigningSecret(): string
    {
        if (! function_exists('get_option')) {
            return '';
        }

        $settings = get_option('woocommerce_monek-checkout_settings', []);
        if (! is_array($settings)) {
            return '';
        }

        if (! isset($settings['svix_signing_secret'])) {
            return '';
        }

        return trim((string) $settings['svix_signing_secret']);
    }

    private function prepareSigningSecret(string $secret): string
    {
        $trimmed = trim($secret);
        if ($trimmed === '') {
            return '';
        }

        if (strpos($trimmed, 'whsec_') === 0) {
            $trimmed = substr($trimmed, 6) ?: '';
        }

        if ($trimmed === '') {
            return '';
        }

        $decoded = base64_decode($trimmed, true);
        if ($decoded === false) {
            return $trimmed;
        }

        if ($decoded === '') {
            return '';
        }

        return $decoded;
    }

    private function getHeaderValue(WP_REST_Request $request, string $headerName): string
    {
        $value = $request->get_header($headerName);

        if (is_array($value)) {
            $value = reset($value);
        }

        return is_string($value) ? trim($value) : '';
    }

    private function isTimestampFresh(string $timestamp): bool
    {
        if (! ctype_digit($timestamp)) {
            return false;
        }

        $timestampInt = (int) $timestamp;
        if ($timestampInt <= 0) {
            return false;
        }

        return abs(time() - $timestampInt) <= self::SIGNATURE_TOLERANCE_SECONDS;
    }

    private function extractSignatures(string $header): array
    {
        $signatures = [];
        if ($header === '') {
            return $signatures;
        }

        foreach (preg_split('/\s+/', trim($header)) as $entry) {
            if ($entry === '') {
                continue;
            }

            $parts = explode(',', $entry, 2);
            if (count($parts) !== 2) {
                continue;
            }

            $version   = trim($parts[0]); 
            $signature = trim($parts[1]);

            if ($version === '' || $signature === '') {
                continue;
            }

            $signatures[$version][] = $signature;
        }

        return $signatures;
    }

    private function log(string $message, array $context = [], string $level = 'info'): void
    {
        if (function_exists('wc_get_logger')) {
            wc_get_logger()->log($level, '[monek] ' . $message, ['source' => 'monek-webhook'] + $context);
            return;
        }

        error_log('[monek] ' . $message . ($context ? ' ' . wp_json_encode($context) : ''));
    }
}
