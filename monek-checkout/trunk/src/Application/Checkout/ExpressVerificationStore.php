<?php

namespace Monek\Checkout\Application\Checkout;

/**
 * Persists the outcome of a server-side express payment verification so it can
 * be consumed when the WooCommerce order is subsequently placed.
 *
 * Flow: the SDK's onPaymentAuthorised callback POSTs the signed token to the
 *      verification REST route, which verifies it and calls 'remember()'. 
 *      And, the browser then places the WooCommerce order, and the express checkout
 *      handler calls 'consume()' to retrieve (and invalidate) the marker
 *      before completing payment.
 */
class ExpressVerificationStore
{
    private const TRANSIENT_PREFIX = 'monek_express_verified_';
    private const DEFAULT_TTL_SECONDS = 900;

    public function remember(string $paymentReference, array $claims, int $ttlSeconds = self::DEFAULT_TTL_SECONDS): bool
    {
        $key = $this->buildKey($paymentReference);
        if ($key === '') {
            return false;
        }

        if (! function_exists('set_transient')) {
            return false;
        }

        return (bool) set_transient($key, $claims, max(60, $ttlSeconds));
    }

    /**
     * @return array|null The stored claims, or null when no valid marker exists.
     */
    public function consume(string $paymentReference): ?array
    {
        $key = $this->buildKey($paymentReference);
        if ($key === '') {
            return null;
        }

        if (! function_exists('get_transient')) {
            return null;
        }

        $stored = get_transient($key);

        if (function_exists('delete_transient')) {
            delete_transient($key);
        }

        return is_array($stored) ? $stored : null;
    }

    private function buildKey(string $paymentReference): string
    {
        $paymentReference = trim($paymentReference);
        
        if ($paymentReference === '') return '';

        return self::TRANSIENT_PREFIX . md5($paymentReference);
    }
}
