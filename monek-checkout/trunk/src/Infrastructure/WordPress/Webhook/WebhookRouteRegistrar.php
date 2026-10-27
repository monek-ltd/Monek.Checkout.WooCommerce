<?php

namespace Monek\Checkout\Infrastructure\WordPress\Webhook;

use Monek\Checkout\Application\Checkout\CurrencyFormatter;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

class WebhookRouteRegistrar
{
    private const SIGNATURE_TOLERANCE_SECONDS = 300;

    // Number of times to retry the order lookup if the webhook arrives before
    // WooCommerce has finished saving the payment reference on the order
    private const ORDER_LOOKUP_MAX_ATTEMPTS = 5;

    // Delay in ms between order lookup retries
    private const ORDER_LOOKUP_RETRY_DELAY_MICROSECONDS = 250000;

    private const PAYMENT_REFERENCE_META_KEY = '_monek_payment_reference';
    private const TRANSACTION_ID_META_KEY = '_monek_transaction_id';
    private const LAST_SVIX_ID_META_KEY = '_monek_last_svix_id';
    private const REFUND_AMOUNTS_META_KEY = '_monek_refund_amounts';

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

        $eventType = $this->extractEventType($body);
        if ($eventType === '') {
            $this->log('webhook: missing event type - event ignored', ['fields' => array_keys($body)], 'warning');
            return new WP_REST_Response(['ok' => true, 'status' => 'ignored', 'reason' => 'missing_event_type'], 200);
        }

        $orderId = $this->resolveOrderId($body, $eventType);
        if ($orderId instanceof WP_REST_Response) {
            return $orderId;
        }

        $order = wc_get_order($orderId);
        if (! $order) {
            return new WP_REST_Response([
                'ok' => false,
                'error' => 'order_load_failed',
                'order_id' => $orderId,
            ], 500);
        }

        // Svix redelivers on timeouts/non-2xx; skip events we have already processed
        $svixId = $this->getHeaderValue($request, 'svix-id');
        if ($svixId !== '' && $order->get_meta(self::LAST_SVIX_ID_META_KEY) === $svixId) {
            $this->log('webhook: duplicate delivery ignored', ['svix_id' => $svixId, 'order_id' => $orderId]);

            return new WP_REST_Response(['ok' => true, 'status' => 'duplicate', 'order_id' => $orderId], 200);
        }

        if ($svixId !== '') {
            $order->update_meta_data(self::LAST_SVIX_ID_META_KEY, $svixId);
        }
        $order->update_meta_data('_monek_last_webhook', wp_json_encode($body));

        return $this->handleSvixEventType($order, $body, $eventType);
    }

    // Reversals reference the original transaction id; everything else references the payment reference
    private function resolveOrderId(array $body, string $eventType)
    {
        // Event types look like "reversal.success"
        $isReversal = strpos($eventType, 'reversal') === 0;

        if ($isReversal) {
            $keys = ['ParentReference', 'parentReference', 'parent_reference'];
            $metaKey = self::TRANSACTION_ID_META_KEY;
            $missing = 'missing_parent_reference';
        } else {
            $keys = ['PaymentReference', 'paymentReference', 'payment_reference'];
            $metaKey = self::PAYMENT_REFERENCE_META_KEY;
            $missing = 'missing_payment_reference';
        }

        $reference = $this->extractPaymentValue($body, $keys);
        if ($reference === '') {
            $this->log('webhook: ' . $missing . ' - event ignored', ['event_type' => $eventType, 'fields' => array_keys($body)], 'warning');

            return new WP_REST_Response(['ok' => true, 'status' => 'ignored', 'reason' => $missing], 200);
        }

        // Only sales can race the checkout finalisation; a reversal's original transaction already exists
        $orderId = $this->locateOrderByMetaKeyWithRetry($reference, $metaKey, $isReversal ? 1 : self::ORDER_LOOKUP_MAX_ATTEMPTS);
        if (! $orderId) {
            $this->log('webhook: order not found', ['reference' => $reference, 'event_type' => $eventType], 'warning');

            // 404 so Webhook service retries later in case the order is saved after the webhook event
            return new WP_REST_Response([
                'ok' => false,
                'error' => 'order_not_found',
                'reference' => $reference,
            ], 404);
        }

        return $orderId;
    }

    // Maps a Svix event type ("operation.result") to a WooCommerce order status.
    private function handleSvixEventType($order, array $body, string $eventType): WP_REST_Response
    {
        $parts = explode('.', $eventType, 2);
        $operation = $parts[0];
        $result = $parts[1] ?? '';

        $targetStatus = null;
        // Statuses a failed/referred sale must never overwrite
        $protectedStatuses = ['payment-confirmed', 'processing', 'completed', 'refunded', 'cancelled'];

        $message = $this->extractMessage($body);
        $transactionId = $this->extractPaymentValue($body, ['CrossReference', 'crossReference', 'TxId', 'txId']);

        if ($operation === 'sale' && $result === 'success') {
            // Guard against late/out-of-order deliveries reviving a refunded or cancelled order
            $targetStatus = in_array($order->get_status(), ['refunded', 'cancelled'], true) ? null : 'payment-confirmed';
        } elseif ($operation === 'sale' && ($result === 'decline' || $result === 'error')) {
            $targetStatus = in_array($order->get_status(), $protectedStatuses, true) ? null : 'failed';
        } elseif ($operation === 'sale' && $result === 'refer') {
            $targetStatus = in_array($order->get_status(), $protectedStatuses, true) ? null : 'on-hold';
        } elseif ($operation === 'refund' && $result === 'success') {
            $targetStatus = $this->isFullRefund($order, $body, $transactionId) ? 'refunded' : null;
        } elseif ($operation === 'reversal' && $result === 'success') {
            $targetStatus = 'cancelled';
        }

        if ($targetStatus === null) {
            $order->add_order_note(sprintf(
                'Monek webhook %s received; order status left unchanged (%s).%s',
                $eventType,
                $order->get_status(),
                $message !== '' ? ' Message: ' . $message : ''
            ));
            $status = 'unchanged';
        } elseif ($order->get_status() === $targetStatus) {
            $order->add_order_note(sprintf('Monek webhook %s received (already %s).', $eventType, $targetStatus));
            $status = 'unchanged';
        } else {
            $order->update_status(
                $targetStatus,
                sprintf(
                    'Monek webhook %s set status to %s.%s',
                    $eventType,
                    $targetStatus,
                    $message !== '' ? ' Message: ' . $message : ''
                )
            );

            if ($transactionId !== '') {

                $labels = [
                    'cancelled' => 'Monek reversal Transaction ID',
                    'refunded'  => 'Monek refund Transaction ID',
                ];
                $label = isset($labels[$targetStatus]) ? $labels[$targetStatus] : 'Monek Transaction ID';

                $order->add_order_note(sprintf('%s: %s', $label, $transactionId));
            }
            $status = 'updated';
        }

        $order->save();

        $this->log('webhook: svix event handled', [
            'order_id' => $order->get_id(),
            'transaction_id' => $transactionId,
            'event_type' => $eventType,
            'result' => $status,
            'order_status' => $order->get_status(),
        ]);

        // Always 200 so svix does not retry non-confirming events
        return new WP_REST_Response([
            'ok' => true,
            'status' => $status,
            'event_type' => $eventType,
            'order_id' => $order->get_id(),
            'transaction_id' => $transactionId,
            'order_status' => $order->get_status(),
            'message' => $message
        ], 200);
    }

    private function extractEventType(array $body): string
    {
        foreach (['EventType', 'eventType'] as $key) {
            $value = $body[$key] ?? null;
            if (is_scalar($value) && trim((string) $value) !== '') {
                return strtolower(trim((string) $value));
            }
        }

        // No event type:  fall back to ResponseCode ("00" = approved, anything else = declined)
        $responseCode = $this->extractResponseCode($body);
        if ($responseCode === '') {
            return '';
        }

        return $this->inferOperation($body) . ($responseCode === '00' ? '.success' : '.decline');
    }

    // Infers sale/refund/reversal from MessageType
    private function inferOperation(array $body): string
    {
        $messageType = $body['Data']['MessageType'] ?? $body['messageType'] ?? '';
        $messageType = is_scalar($messageType) ? strtoupper((string) $messageType) : '';

        if (strpos($messageType, 'REFUND') !== false) {
            return 'refund';
        }

        if (strpos($messageType, 'REVERSAL') !== false || strpos($messageType, 'VOID') !== false) {
            return 'reversal';
        }

        return 'sale';
    }

    private function isFullRefund($order, array $body, string $transactionId): bool
    {
        $amount = $body['Data']['Amount'] ?? $body['amount'] ?? null;
        if (! is_scalar($amount) || ! is_numeric($amount)) {
            return false;
        }

        $orderMinor = (new CurrencyFormatter())->toMinorUnits($order->get_total(), (string) $order->get_currency());

        $totalRefunded = $this->saveRefundAmount($order, (int) $amount, $transactionId);

        $fullRefund = $totalRefunded >= $orderMinor;

        if (! $fullRefund) {
            $formatter = new CurrencyFormatter();
            $currency = (string) $order->get_currency();
            $eventMajor = $formatter->toMajorUnits((string) $amount, $currency);
            $totalMajor = $formatter->toMajorUnits((string) $totalRefunded, $currency);

            $order->add_order_note(sprintf(
                'Partial refund of %s (total refunded: %s); Monek refund Transaction ID: %s',
                $eventMajor,
                $totalMajor,
                $transactionId !== '' ? $transactionId : 'NONE'
            ));
        }

        return $fullRefund;
    }

    // Stores the refund amount (minor units) in order meta and returns the total refunded
    // Keyed by transaction ID so redelivered webhooks are not counted twice
    private function saveRefundAmount($order, int $amountMinor, string $transactionId): int
    {
        $refunds = $order->get_meta(self::REFUND_AMOUNTS_META_KEY, true);
        $refunds = is_array($refunds) ? $refunds : [];

        if ($transactionId !== '') {
            $refunds[$transactionId] = $amountMinor;
        } else {
            $refunds[] = $amountMinor;
        }

        $order->update_meta_data(self::REFUND_AMOUNTS_META_KEY, $refunds);

        return (int) array_sum(array_map('intval', $refunds));
    }

    private function extractResponseCode(array $body): string
    {
        $code = $body['Data']['ResponseCode'] ?? $body['responseCode'] ?? '';

        return is_scalar($code) ? trim((string) $code) : '';
    }

    private function extractMessage(array $body): string
    {
        $message = $body['Data']['Message'] ?? $body['message'] ?? '';

        return is_scalar($message) ? trim((string) $message) : '';
    }

    private function extractPaymentValue(array $body, array $keys): string
    {
        $data = isset($body['Data']) && is_array($body['Data']) ? $body['Data'] : [];

        foreach ([$body, $data] as $source) {
            foreach ($keys as $key) {
                if (isset($source[$key]) && is_scalar($source[$key]) && trim((string) $source[$key]) !== '') {
                    return trim((string) $source[$key]);
                }
            }
        }

        return '';
    }

    /**
     * Looks up the order by payment reference, retrying with a short delay in
     * case the webhook arrives before checkout finalisation has saved the
     * reference on the order (a race between WooCommerce order creation and
     * the async webhook delivery).
     */
    private function locateOrderByMetaKeyWithRetry(string $ref, string $metaKey, int $maxAttempts = self::ORDER_LOOKUP_MAX_ATTEMPTS): ?int
    {
        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {

            $orderId = $this->locateOrderByReference($ref, $metaKey);
            if ($orderId) {
                if ($attempt > 1) {
                    $this->log('webhook: order found after retry', [
                        'reference' => $ref,
                        'attempt' => $attempt,
                    ]);
                }

                return $orderId;
            }

            if ($attempt < $maxAttempts) {
                usleep(self::ORDER_LOOKUP_RETRY_DELAY_MICROSECONDS);
            }
        }

        return null;
    }

    private function locateOrderByReference(string $ref, string $metaKey): ?int
    {
        if (! function_exists('wc_get_orders')) {
            return null;
        }

        $orders = wc_get_orders([
            'limit' => 1,
            'return' => 'ids',
            'meta_key' => $metaKey,
            'meta_value' => $ref,
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
            return false;
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
