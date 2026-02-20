<?php

class MCWC_ProductConsignmentInitializer
{
    public static function init()
    {
        require_once 'MerchantMapping/MCWC_AddProductSettingsSection.php';
        require_once 'MerchantMapping/Includes/MCWC_EnqueueScripts.php';
    }
}

