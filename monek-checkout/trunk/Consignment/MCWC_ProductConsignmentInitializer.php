<?php

class MCWC_ProductConsignmentInitializer
{
    public static function init()
    {
        require_once 'MerchantMapping/MCWC_AddProductSettingsSection.php';
        require_once 'MerchantMapping/Includes/MCWC_EnqueueScripts.php';
        
        /* DOES NOT WORK WITH NEW PRODUCT PAGE CURRENTLY IN BETA, PLUGIN SUPPORT HAS NOT BEEN ADDED */
        require_once 'ProductFields/MCWC_ConsignmentMerchantSelect.php';
        $selctClass = new MCWC_ConsignmentMerchantSelect();
        $selctClass->init();

        require_once 'CartValidation/MCWC_EnforceSingleMerchant.php';
    }
}

