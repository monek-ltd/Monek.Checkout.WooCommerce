<?php

/**
 * Class ShippingAddress - represents the shipping address of the customer 
 *
 * @package Monek
 */
class ShippingAddress 
{
    public $shipping_first_name;
    public $shipping_last_name;
    public $shipping_company;
    public $shipping_address_1;
    public $shipping_address_2;
    public $shipping_city;
    public $shipping_state;
    public $shipping_country;
    public $shipping_postcode;
    public $shipping_email;
    public $shipping_phone;

    public function __construct() 
    {
        $this->shipping_first_name = filter_input(INPUT_POST, 'shipping_first_name', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $this->shipping_last_name = filter_input(INPUT_POST, 'shipping_last_name', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $this->shipping_company = filter_input(INPUT_POST, 'shipping_company', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $this->shipping_address_1 = filter_input(INPUT_POST, 'shipping_address_1', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $this->shipping_address_2 = filter_input(INPUT_POST, 'shipping_address_2', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $this->shipping_city = filter_input(INPUT_POST, 'shipping_city', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $this->shipping_state = filter_input(INPUT_POST, 'shipping_state', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $this->shipping_country = filter_input(INPUT_POST, 'shipping_country', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $this->shipping_postcode = filter_input(INPUT_POST, 'shipping_postcode', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $this->shipping_email = filter_input(INPUT_POST, 'shipping_email', FILTER_VALIDATE_EMAIL);
        $this->shipping_phone = filter_input(INPUT_POST, 'shipping_phone', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    }
}
