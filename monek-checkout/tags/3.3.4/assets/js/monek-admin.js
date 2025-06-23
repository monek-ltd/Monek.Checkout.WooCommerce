jQuery(document).ready(function ($) {
    function toggleMerchantIDField() {
        var isConsignmentModeChecked = $('#woocommerce_monek-checkout_consignment_mode').is(':checked');
        
        var merchantIDInput = document.getElementById('woocommerce_monek-checkout_merchant_id');

        if(isConsignmentModeChecked) {
            merchantIDInput.value = '';
            merchantIDInput.disabled = true;
        }
        else {
            merchantIDInput.disabled = false;
        }
    }

    toggleMerchantIDField();

    $('#woocommerce_monek-checkout_consignment_mode').on('change', function () {
        toggleMerchantIDField();
    });
});
