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

    window.monekInstallApplePayFile = function (buttonEl) {
        var $button = jQuery(buttonEl);
        var nonce = $button.data('nonce');

        $button.prop('disabled', true).text('Installing…');

        jQuery.post(ajaxurl, {
            action: 'monek_download_apple_file',
            _wpnonce: nonce
        })
        .done(function () {
            console.log('[monek] Apple Pay domain file installed successfully');
           
            jQuery('.apple-pay-description')
                .text('Apple Pay domain file installed successfully.')
                .addClass('monek-checkout-messages');
        })
        .fail(function (jqXHR, textStatus, errorThrown) {
            console.error('[monek] Error when installing Apple Pay domain file: ' + textStatus, errorThrown);

            jQuery('.apple-pay-description')
                .text('Failed to install Apple Pay domain file. Please try again or install the file manually. Check the documentation for more details.')
                .addClass('monek-checkout-messages');
        })
        .always(function () {
            $button.prop('disabled', false).text('Download file');
        });
    }

    // Listen for dismiss click on the Apple Pay notice
    $('.monek-apple-pay-notice button.notice-dismiss').on('click', function () {
        createDismissApplePayNoticeAction();
    });

    function createDismissApplePayNoticeAction() {
        $.post(ajaxurl, {
            action: 'monek_dismiss_apple_pay_notice'
        }).done(function (response) {
            console.log('[monek] Apple Pay notice dismissed', response);
        }).fail(function (jqXHR, textStatus, errorThrown) {
            console.error('[monek] Apple Pay notice dismissing error', textStatus, errorThrown);
        });
    }
});
