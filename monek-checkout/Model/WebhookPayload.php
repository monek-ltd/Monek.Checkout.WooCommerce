<?php

class WebhookPayload {
    public $transaction_date_time;
    public $payment_reference;
    public $cross_reference;
    public $response_code;
    public $message;
    public $amount;
    public $currency_code;
    public $integrity_digest;

    public function __construct(array $data) {
        $this->$transaction_date_time = filter_var($data['transactionDateTime'] ?? '', FILTER_SANITIZE_STRING);
        $this->$payment_reference = filter_var($data['paymentReference'] ?? '', FILTER_SANITIZE_STRING);
        $this->$cross_reference = filter_var($data['crossReference'] ?? '', FILTER_SANITIZE_STRING);
        $this->$response_code = filter_var($data['responseCode'] ?? '', FILTER_SANITIZE_STRING);
        $this->message = filter_var($data['message'] ?? '', FILTER_SANITIZE_STRING);
        $this->amount = filter_var($data['amount'] ?? '', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
        $this->$currency_code = filter_var($data['currencyCode'] ?? '', FILTER_SANITIZE_STRING);
        $this->integrityDigest = filter_var($data['integrityDigest'] ?? '', FILTER_SANITIZE_STRING);
    }

    public function validate() : bool {
        return isset($this->$transaction_date_time)
            && isset($this->$payment_reference)
            && isset($this->$cross_reference)
            && isset($this->$response_code)
            && isset($this->message)
            && isset($this->amount)
            && isset($this->$currency_code)
            && isset($this->$integrity_digest)
            && is_numeric($this->amount)
            && preg_match('/^[0-9]{2}$/', $this->responseCode);
    }
}
