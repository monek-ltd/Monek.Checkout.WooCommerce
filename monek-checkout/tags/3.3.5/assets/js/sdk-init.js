document.addEventListener('DOMContentLoaded', function () {
    console.log('[SDK] Initializing...');

    if (typeof MySDK === 'undefined') {
        console.error('[SDK] MySDK is not available');
        return;
    }

    MySDK.mount('#sdk-container');
    
    jQuery(document.body).on('updated_checkout', function() {
        console.log('[MySDK] Checkout updated, remounting iframe...');
        MySDK.mount('#sdk-container');
    });

    const form = document.querySelector('form.checkout');

    form.setAttribute('data-processing-sdk', 'true');

    if (!form) return;

    form.addEventListener('submit', function (e) {
        e.preventDefault();

        console.log('[SDK] Intercepted form submit');

        MySDK.call('pay').then(function (response) {
            if (response.success) {
                console.log('[SDK] Payment successful');
                document.getElementById('transactionToken').value = response.transactionId;
                form.submit(); // Continue Woo checkout
            } else {
                alert('Payment failed: ' + response.message);
            }
        }).catch(function (err) {
            console.error('[SDK] Payment error:', err);
            alert('Payment failed. Try again.');
        });
    });
});