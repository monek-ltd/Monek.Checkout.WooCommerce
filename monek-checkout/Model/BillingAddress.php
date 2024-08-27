<?php

/**
 * Class BillingAddress - represents the billing address of the customer 
 *
 * @package Monek
 */
class BillingAddress extends Address
{
    public bool $ship_to_different_address;

    public function __construct() 
    {
        $this->first_name = $this->sanitize_name(INPUT_POST, 'billing_first_name');
        $this->last_name = $this->sanitize_name(INPUT_POST, 'billing_last_name');
        $this->company = filter_input(INPUT_POST, 'billing_company', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $this->address_1 = filter_input(INPUT_POST, 'billing_address_1', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $this->address_2 = filter_input(INPUT_POST, 'billing_address_2', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $this->city = filter_input(INPUT_POST, 'billing_city', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $this->state = filter_input(INPUT_POST, 'billing_state', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $this->country = filter_input(INPUT_POST, 'billing_country', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $this->postcode = filter_input(INPUT_POST, 'billing_postcode', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $this->email = filter_input(INPUT_POST, 'billing_email' , FILTER_VALIDATE_EMAIL);
        $this->phone = $this->sanitize_phone(INPUT_POST, 'billing_phone');
        $this->ship_to_different_address = filter_input(INPUT_POST, 'ship_to_different_address', FILTER_VALIDATE_BOOLEAN);
        
        if (!$this->validate_phone($this->phone)) {
            throw new InvalidArgumentException("Invalid phone number format.");
        }

        if (!$this->validate_postcode($this->billing_postcode, $this->country)) {
            throw new InvalidArgumentException("Invalid postcode format for the given country.");
        }
    }
}
