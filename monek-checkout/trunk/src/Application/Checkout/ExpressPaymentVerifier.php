<?php

namespace Monek\Checkout\Application\Checkout;

/**
 * Verifies the signed verification token issued by the Monek backend when an
 * Apple Pay (express) payment is authorised.
 *
 * The token is a JWT (HS256) signed with the merchant secret key. Because the
 * signature is produced server-side by Monek and verified server-side here, the
 * browser cannot forge an "approved" payment. This is what allows the express
 * checkout handler to safely complete a WooCommerce order without trusting the
 * client-reported result.
 *
 * Expected claims (aliases accepted for resilience to the backend contract):
 *   - result            ("Success") | status
 *   - paymentReference  | ref
 *   - sessionId         | sub
 *   - minorAmount (int) | amount
 *   - currencyCode      | currency        (ISO 4217 numeric, e.g. "826")
 *   - transactionId     | txn             (optional)
 *   - exp (unix seconds)                  (required)
 *   - jti | nonce                         (optional, one-time use marker)
 */
class ExpressPaymentVerifier
{
    private const ALLOWED_ALGORITHM = 'HS256';
    private const EXPIRY_LEEWAY_SECONDS = 60;

    /**
     * @param array{paymentReference?:string,sessionId?:string} $expectations
     *
     * @return array{verified:bool,reason:?string,claims:array}
     */
    public function verify(string $token, string $secret, array $expectations = []): array
    {
        if ($secret === '') {
            return $this->failure('no_secret');
        }

        $token = trim($token);
        if ($token === '') {
            return $this->failure('missing_token');
        }

        $segments = explode('.', $token);
        if (count($segments) !== 3) {
            return $this->failure('malformed_token');
        }

        [$headerSegment, $payloadSegment, $signatureSegment] = $segments;

        $header = $this->decodeJson($this->base64UrlDecode($headerSegment));
        if (! is_array($header)) {
            return $this->failure('invalid_header');
        }

        $algorithm = isset($header['alg']) ? (string) $header['alg'] : '';
        if (strtoupper($algorithm) !== self::ALLOWED_ALGORITHM) {
            return $this->failure('unsupported_algorithm');
        }

        $signingInput = $headerSegment . '.' . $payloadSegment;
        $expectedSignature = $this->base64UrlEncode(
            hash_hmac('sha256', $signingInput, $secret, true)
        );

        // NEED TO CHECK THIS
        
        $providedSignature = trim($signatureSegment);
        if (! hash_equals($expectedSignature, $providedSignature)) {
            return $this->failure('signature_mismatch');
        }

        $claims = $this->decodeJson($this->base64UrlDecode($payloadSegment));
        if (! is_array($claims)) {
            return $this->failure('invalid_payload');
        }

        if (! $this->isResultSuccessful($claims)) {
            return $this->failure('not_approved', $claims);
        }

        if (! $this->isUnexpired($claims)) {
            return $this->failure('expired', $claims);
        }

        $expectedReference = isset($expectations['paymentReference']) ? (string) $expectations['paymentReference'] : '';
        if ($expectedReference !== '' && ! $this->matchesClaim($claims, ['paymentReference', 'ref'], $expectedReference)) {
            return $this->failure('reference_mismatch', $claims);
        }

        $expectedSession = isset($expectations['sessionId']) ? (string) $expectations['sessionId'] : '';
        if ($expectedSession !== '' && ! $this->matchesClaim($claims, ['sessionId', 'sub'], $expectedSession)) {
            return $this->failure('session_mismatch', $claims);
        }

        return [
            'verified' => true,
            'reason' => 'verified',
            'claims' => $claims,
        ];
    }

    public function readPaymentReference(array $claims): string
    {
        return $this->readClaim($claims, ['paymentReference', 'ref']);
    }

    public function readSessionId(array $claims): string
    {
        return $this->readClaim($claims, ['sessionId', 'sub']);
    }

    public function readTransactionId(array $claims): string
    {
        return $this->readClaim($claims, ['transactionId', 'txn']);
    }

    public function readMinorAmount(array $claims): ?int
    {
        foreach (['minorAmount', 'amount'] as $key) {
            if (isset($claims[$key]) && is_numeric($claims[$key])) {
                return (int) $claims[$key];
            }
        }

        return null;
    }

    public function readCurrencyCode(array $claims): string
    {
        return $this->readClaim($claims, ['currencyCode', 'currency']);
    }

    private function isResultSuccessful(array $claims): bool
    {
        foreach (['result', 'status'] as $key) {
            if (isset($claims[$key]) && strtoupper((string) $claims[$key]) === 'SUCCESS') {
                return true;
            }
        }

        return false;
    }

    private function isUnexpired(array $claims): bool
    {
        if (! isset($claims['exp']) || ! is_numeric($claims['exp'])) {
            return false;
        }

        return (int) $claims['exp'] + self::EXPIRY_LEEWAY_SECONDS >= time();
    }

    private function matchesClaim(array $claims, array $keys, string $expected): bool
    {
        return hash_equals($expected, $this->readClaim($claims, $keys));
    }

    private function readClaim(array $claims, array $keys): string
    {
        foreach ($keys as $key) {
            if (isset($claims[$key]) && is_scalar($claims[$key])) {
                return (string) $claims[$key];
            }
        }

        return '';
    }

    private function decodeJson(string $json)
    {
        if ($json === '') {
            return null;
        }

        return json_decode($json, true);
    }

    private function base64UrlDecode(string $data): string
    {
        $remainder = strlen($data) % 4;
        if ($remainder !== 0) {
            $data .= str_repeat('=', 4 - $remainder);
        }

        $decoded = base64_decode(strtr($data, '-_', '+/'), true);

        return $decoded === false ? '' : $decoded;
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * @return array{verified:bool,reason:?string,claims:array}
     */
    private function failure(string $reason, array $claims = []): array
    {
        return [
            'verified' => false,
            'reason' => $reason,
            'claims' => $claims,
        ];
    }
}
