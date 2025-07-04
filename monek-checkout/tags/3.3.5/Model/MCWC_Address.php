<?php

/**
 * Class Address - represents an address 
 *
 * @package Monek
 */
class MCWC_Address 
{
    public string $first_name;
    public string $last_name;
    public string $company;
    public string $address_1;
    public string $address_2;
    public string $city;
    public string $state;
    public string $country;
    public string $postcode;
    
    /**
    * Sanitize the name input
	 *
	 * @param int $inputType
	 * @param string $fieldName
	 * @return string
	 */
    public function mcwc_sanitize_name($inputType, $fieldName) : string
    {
        $name = filter_input($inputType, $fieldName, FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? '';
        return preg_replace("/[^a-zA-Z\s\-']/u", "", $name) ?: '';
    }

    /**
    * Validate the postcode format
    *
    * @param string $postcode
    * @param string $country
    * @return bool
	*/
    public function mcwc_validate_postcode(string $postcode, string $country) : bool 
    {
        $postcodePatterns = [
            'GB' => '/^([A-Z]{1,2}\d[A-Z\d]? ?\d[A-Z]{2})$/i',  // United Kingdom
            'AU' => '/^\d{4}$/',                               // Australia
            'AT' => '/^\d{4}$/',                               // Austria
            'BE' => '/^\d{4}$/',                               // Belgium
            'BR' => '/^\d{5}(-\d{3})?$/',                      // Brazil
            'CA' => '/^[A-Za-z]\d[A-Za-z][ -]?\d[A-Za-z]\d$/', // Canada
            'CN' => '/^\d{6}$/',                               // China
            'CZ' => '/^\d{3} ?\d{2}$/',                        // Czech Republic
            'DK' => '/^\d{4}$/',                               // Denmark
            'FR' => '/^\d{5}$/',                               // France
            'DE' => '/^\d{5}$/',                               // Germany
            'HK' => '/^\d{6}$/',                               // Hong Kong
            'IE' => '/^[A-Za-z]\d[A-Za-z]\d ?\d[A-Za-z]\d$/',  // Ireland (new Eircode format)
            'IT' => '/^\d{5}$/',                               // Italy
            'JP' => '/^\d{3}-\d{4}$/',                         // Japan
            'KR' => '/^\d{5}$/',                               // South Korea
            'MX' => '/^\d{5}$/',                               // Mexico
            'NL' => '/^\d{4}$/',                               // Netherlands
            'NO' => '/^\d{4}$/',                               // Norway
            'PT' => '/^\d{4}-\d{3}$/',                         // Portugal
            'RU' => '/^\d{6}$/',                               // Russia
            'SG' => '/^\d{6}$/',                               // Singapore
            'ES' => '/^\d{5}$/',                               // Spain
            'SE' => '/^\d{3} ?\d{2}$/',                        // Sweden
            'CH' => '/^\d{4}$/',                               // Switzerland
            'US' => '/^\d{5}(-\d{4})?$/',                      // United States
            'UY' => '/^\d{5}$/',                               // Uruguay
        ];

        if (array_key_exists($country, $postcodePatterns)) {
            return preg_match($postcodePatterns[$country], $postcode) === 1;
        }

        return true;
    }
}
