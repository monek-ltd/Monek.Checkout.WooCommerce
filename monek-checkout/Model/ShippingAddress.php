<?php

/**
 * Class ShippingAddress - represents the shipping address of the customer 
 *
 * @package Monek
 */
class ShippingAddress extends Address
{
    public function __construct() 
    {
        $this->first_name = $this->sanitize_name(INPUT_POST, 'shipping_first_name');
        $this->last_name = $this->sanitize_name(INPUT_POST, 'shipping_last_name');
        $this->company = filter_input(INPUT_POST, 'shipping_company', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $this->address_1 = filter_input(INPUT_POST, 'shipping_address_1', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $this->address_2 = filter_input(INPUT_POST, 'shipping_address_2', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $this->city = filter_input(INPUT_POST, 'shipping_city', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $this->state = filter_input(INPUT_POST, 'shipping_state', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $this->country = filter_input(INPUT_POST, 'shipping_country', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $this->postcode = filter_input(INPUT_POST, 'shipping_postcode', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $this->email = filter_input(INPUT_POST, 'shipping_email', FILTER_VALIDATE_EMAIL);
        $this->phone = $this->sanitize_phone(INPUT_POST, 'shipping_phone');
        
        if (!$this->validate_phone($this->phone)) {
            throw new InvalidArgumentException("Invalid phone number format.");
        }

        if (!$this->validate_postcode($this->billing_postcode, $this->country)) {
            throw new InvalidArgumentException("Invalid postcode format for the given country.");
        }
    }
}
