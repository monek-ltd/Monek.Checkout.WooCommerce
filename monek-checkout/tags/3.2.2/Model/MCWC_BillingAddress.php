<?php

/**
 * Class BillingAddress - represents the billing address of the customer 
 *
 * @package Monek
 */
class MCWC_BillingAddress extends MCWC_Address
{
    public string $email;
    public string $phone;
    public bool $ship_to_different_address;

    public function __construct() 
    {
        $this->first_name = $this->mcwc_sanitize_name(INPUT_POST, 'billing_first_name');
        $this->last_name = $this->mcwc_sanitize_name(INPUT_POST, 'billing_last_name');
        $this->company = filter_input(INPUT_POST, 'billing_company', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? '';
        $this->address_1 = filter_input(INPUT_POST, 'billing_address_1', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? '';
        $this->address_2 = filter_input(INPUT_POST, 'billing_address_2', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? '';
        $this->city = filter_input(INPUT_POST, 'billing_city', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? '';
        $this->state = filter_input(INPUT_POST, 'billing_state', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? '';
        $this->country = filter_input(INPUT_POST, 'billing_country', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? '';
        $this->postcode = filter_input(INPUT_POST, 'billing_postcode', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? '';
        $this->email = filter_input(INPUT_POST, 'billing_email' , FILTER_VALIDATE_EMAIL) ?? '';
        $this->phone = $this->mcwc_sanitize_phone(INPUT_POST, 'billing_phone');
        $this->ship_to_different_address = filter_input(INPUT_POST, 'ship_to_different_address', FILTER_VALIDATE_BOOLEAN) ?? false;
        
        if ($this->phone != '' && !$this->mcwc_validate_phone($this->phone)) {
            throw new InvalidArgumentException("Invalid phone number format.");
        }

        if ($this->postcode != '' && $this->country != '' && !$this->mcwc_validate_postcode($this->postcode, $this->country)) {
            throw new InvalidArgumentException("Invalid postcode format for the given country.");
        }
    }

    /**
    * Sanitize the phone number input
    *
    * @param int $inputType
    * @param string $fieldName
    * @return string|null
	*/
    public function mcwc_sanitize_phone($inputType, $fieldName) : ?string
    {
        $phone = filter_input($inputType, $fieldName, FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? '';
        return preg_replace("/[^\d\+\-\s]/", "", $phone) ?: '';
    }

    /**
    * Validate the phone number format
	 *
	 * @param string|null $phone
	 * @return bool
	 */
    public function mcwc_validate_phone(?string $phone) : bool 
    {
        return preg_match('/^\+?[0-9\s\-]{7,15}$/', $phone) === 1;
    }
}
