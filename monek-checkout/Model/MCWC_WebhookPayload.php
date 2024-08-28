<?php

/**
 * Class WebhookPayload - represents the payload of the webhook from the payment gateway
 *
 * @package Monek
 */
class MCWC_WebhookPayload 
{
    public string $transaction_date_time;
    public string $payment_reference;
    public string $cross_reference;
    public string $response_code;
    public string $message;
    public float $amount;
    public string $currency_code;
    public string $integrity_digest;

    /**
     * @param array $data
     */
    public function __construct(array $data) 
    {
        $this->transaction_date_time = $this->mcwc_validate_date_time($data['transactionDateTime'] ?? '');
        $this->payment_reference = $this->mcwc_validate_string($data['paymentReference'] ?? '', 'Payment Reference');
        $this->cross_reference = $this->mcwc_validate_string($data['crossReference'] ?? '', 'Cross Reference');
        $this->response_code = $this->mcwc_validate_response_code($data['responseCode'] ?? '');
        $this->message = sanitize_text_field($data['message'] ?? '');
        $this->amount = $this->mcwc_validate_amount($data['amount'] ?? '');
        $this->currency_code = $this->mcwc_validate_currency_code($data['currencyCode'] ?? '');
        $this->integrity_digest = $this->mcwc_validate_integrity_digest($data['integrityDigest'] ?? '');
    }

    /**
     * Validate the webhook payload data is available
     *
     * @return bool
     */
    public function mcwc_validate() : bool 
    {
        return !empty($this->transaction_date_time)
            && !empty($this->payment_reference)
            && !empty($this->cross_reference)
            && !empty($this->response_code)
            && !empty($this->message)
            && !empty($this->amount)
            && !empty($this->currency_code)
            && !empty($this->integrity_digest);
    }

    /**
     * Validate and sanitize a date-time string in RFC 3339 format
     *
     * @param string $dateTime
     * @return string
     * @throws InvalidArgumentException
     */
    private function mcwc_validate_date_time(string $dateTime): string 
    {
        if (empty($dateTime)) {
            throw new InvalidArgumentException('Transaction date and time is required.');
        }

        $dateTime = sanitize_text_field($dateTime);

        // RFC 3339 format validation
        $dateTimeRegex = '/^(\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:\d{2}))$/';
        if (!preg_match($dateTimeRegex, $dateTime)) {
            throw new InvalidArgumentException('Invalid transaction date and time format. Must be in RFC 3339 format.');
        }

        return $dateTime;
    }
    
    /**
     * Validate and sanitize a generic string field
     *
     * @param string $value
     * @param string $fieldName
     * @return string
     * @throws InvalidArgumentException
     */
    private function mcwc_validate_string(string $value, string $fieldName) : string 
    {
        $value = sanitize_text_field($value);

        if (empty($value)) {
            throw new InvalidArgumentException("$fieldName is required.");
        }

        return $value;
    }
    
    /**
     * Validate the response code (assuming it should be a two-digit numeric string)
     *
     * @param string $responseCode
     * @return string
     * @throws InvalidArgumentException
     */
    private function mcwc_validate_response_code(string $responseCode) : string 
    {
        $responseCode = sanitize_text_field($responseCode);

        if (!preg_match('/^\d{2}$/', $responseCode)) {
            throw new InvalidArgumentException('Invalid response code format.');
        }

        return $responseCode;
    }

    /**
     * Validate and sanitize the amount
     *
     * @param string $amount
     * @return float
     * @throws InvalidArgumentException
     */
    private function mcwc_validate_amount(string $amount) : float 
    {
        $amount = sanitize_text_field($amount);

        if (!is_numeric($amount)) {
            throw new InvalidArgumentException('Amount must be a valid number.');
        }

        return (float)$amount;
    }

    /**
     * Validate the currency code (assuming it should be a numeric ISO code)
     *
     * @param string $currencyCode
     * @return string
     * @throws InvalidArgumentException
     */
    private function mcwc_validate_currency_code(string $currencyCode) : string 
    {
        $currencyCode = sanitize_text_field($currencyCode);

        if (!preg_match('/^\d{3}$/', $currencyCode)) {
            throw new InvalidArgumentException('Invalid currency code format.');
        }

        return $currencyCode;
    }
    
    /**
     * Validate the integrity digest as an SHA-256 hash
     *
     * @param string $integrityDigest
     * @return string
     * @throws InvalidArgumentException
     */
    private function mcwc_validate_integrity_digest(string $integrityDigest) : string 
    {
        $integrityDigest = sanitize_text_field($integrityDigest);

        if (!preg_match('/^[a-f0-9]{64}$/i', $integrityDigest)) {
            throw new InvalidArgumentException('Invalid integrity digest format. Must be a 64-character hexadecimal string.');
        }

        return $integrityDigest;
    }
}
