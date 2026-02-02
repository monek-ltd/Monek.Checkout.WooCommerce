jQuery(document).ready(function ($) {

    $('#woocommerce_monek-checkout_show_express').on('change', function () {
       disableApplePayButton(this.checked);
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
        .fail(function () {
            console.error('[monek] Error when installing Apple Pay domain file');

            jQuery('.apple-pay-description')
                .text('[monek] Failed to install Apple Pay domain file.')
                .addClass('monek-checkout-messages');
        })
        .always(function () {
            $button.prop('disabled', false).text('Download file');
        });
    }

    function disableApplePayButton(checked) {
        $('#monek-download-apple-file').prop('disabled', !checked);
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