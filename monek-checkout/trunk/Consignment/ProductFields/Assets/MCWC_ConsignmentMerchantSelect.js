jQuery(document).ready(function ($) {
    $('#monek_consignment_merchant').on('change', function () {
        var merchantID = $('#monek_consignment_merchant').val();

        if (merchantID) {
            toggleMerchantSelect(true);

            $.ajax({
                url: ajaxurl,
                method: 'POST',
                data: {
                    action: 'mcwc_save_product_consignment_mid',
                    product_id: consignmentSelect.product_id,
                    merchant_id: merchantID,
                    security: consignmentSelect.nonce
                },
                success: function (response) {
                    console.error('Saved merchant ID: ', error);
                },
                error: function (xhr, status, error) {
                    toggleMerchantSelect(false);
                    console.error('Error saving merchant ID: ', error);
                }
            });
        }
    });

    $('#unlock-merchant').on('click', function (e) {
        e.preventDefault();
        toggleMerchantSelect(false);
    });

    var merchantID = $('#monek_consignment_merchant').val();
    if (merchantID) {
        toggleMerchantSelect(true);
    }

    function toggleMerchantSelect(disable) {
        document.getElementById('monek_consignment_merchant').disabled = disable;
    }
});
