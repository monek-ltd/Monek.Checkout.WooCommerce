<?php

class BillingAddress {
    public $billing_first_name;
    public $billing_last_name;
    public $billing_company;
    public $billing_address_1;
    public $billing_address_2;
    public $billing_city;
    public $billing_state;
    public $billing_country;
    public $billing_postcode;
    public $billing_email;
    public $billing_phone;
    public $ship_to_different_address;

    public function __construct() {
        $this->billing_first_name = filter_input(INPUT_POST, 'billing_first_name', FILTER_SANITIZE_STRING);
        $this->billing_last_name = filter_input(INPUT_POST, 'billing_last_name', FILTER_SANITIZE_STRING);
        $this->billing_company = filter_input(INPUT_POST, 'billing_company', FILTER_SANITIZE_STRING);
        $this->billing_address_1 = filter_input(INPUT_POST, 'billing_address_1', FILTER_SANITIZE_STRING);
        $this->billing_address_2 = filter_input(INPUT_POST, 'billing_address_2', FILTER_SANITIZE_STRING);
        $this->billing_city = filter_input(INPUT_POST, 'billing_city', FILTER_SANITIZE_STRING);
        $this->billing_state = filter_input(INPUT_POST, 'billing_state', FILTER_SANITIZE_STRING);
        $this->billing_country = filter_input(INPUT_POST, 'billing_country', FILTER_SANITIZE_STRING);
        $this->billing_postcode = filter_input(INPUT_POST, 'billing_postcode', FILTER_SANITIZE_STRING);
        $this->billing_email = filter_input(INPUT_POST, 'billing_email', FILTER_SANITIZE_STRING);
        $this->billing_phone = filter_input(INPUT_POST, 'billing_phone', FILTER_SANITIZE_STRING);
        $this->ship_to_different_address = filter_input(INPUT_POST, 'ship_to_different_address', FILTER_SANITIZE_STRING);
    }
}
