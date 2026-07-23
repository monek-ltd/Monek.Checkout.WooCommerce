/* global jQuery */
(function initializeMonekLegacyCheckout(windowObject, documentObject, jQueryInstance) {
  'use strict';

  const GATEWAY_ID = 'monek-checkout';
  const configuration = windowObject.monekCheckoutConfig || {};

  const api = windowObject.monekCheckout;
  if (!api || typeof api.setContextProvider !== 'function') {
    // Shared driver did not initialise (e.g. missing publishable key); nothing to do.
    return;
  }

  const selectors = {
    wrapper: '#monek-checkout-wrapper',
    container: '#monek-checkout-container',
    paymentMethodInput: 'input[name="payment_method"]:checked',
    checkoutForm: 'form.woocommerce-checkout',
    payForm: 'form#order_review',
    hidden: {
      token: 'monek_token',
      session: 'monek_session',
      expiry: 'monek_expiry',
      reference: 'monek_reference',
    },
  };

  // Guard so our re-submit of the classic checkout form is allowed straight through.
  let tokensReady = false;

  function log(...args) {
    if (configuration.debug && windowObject.console?.log) {
      windowObject.console.log('[monek][legacy]', ...args);
    }
  }

  function fieldValue(id) {
    const element = documentObject.getElementById(id);
    return element ? element.value || '' : null;
  }

  function setHiddenValue(id, value) {
    const element = documentObject.getElementById(id);
    if (element) {
      element.value = value || '';
    }
  }

  function getAmountMinor() {
    const configured = configuration.amountMinor;
    if (configured !== undefined && configured !== null && configured !== '') {
      const parsed = Number(configured);
      if (Number.isFinite(parsed)) {
        return parsed;
      }
    }

    const wrapper = documentObject.querySelector(selectors.wrapper)
      || documentObject.querySelector('.monek-checkout-wrapper');
    const attribute = wrapper?.getAttribute('data-monek-amount-minor');
    const fromAttribute = Number(attribute);

    return Number.isFinite(fromAttribute) ? fromAttribute : 0;
  }

  function toNumericCountry(country) {
    const converter = api.toIso3166Numeric;
    if (typeof converter === 'function') {
      const numeric = converter(country);
      if (numeric) {
        return numeric;
      }
    }

    return configuration.countryNumeric || '826';
  }

  function getCardholder() {
    // On the classic checkout the billing fields live in the DOM. On the Order Pay
    // page they are absent, so we fall back to the server-localised billing snapshot.
    let firstName = fieldValue('billing_first_name');
    const domPresent = firstName !== null;

    const snapshot = configuration.billing || {};

    let lastName;
    let email;
    let phone;
    let address1;
    let address2;
    let city;
    let postcode;
    let country;
    let state;

    if (domPresent) {
      lastName = fieldValue('billing_last_name');
      email = fieldValue('billing_email');
      phone = fieldValue('billing_phone');
      address1 = fieldValue('billing_address_1');
      address2 = fieldValue('billing_address_2');
      city = fieldValue('billing_city');
      postcode = fieldValue('billing_postcode');
      country = fieldValue('billing_country');
      state = fieldValue('billing_state');
    } else {
      firstName = snapshot.first_name || '';
      lastName = snapshot.last_name || '';
      email = snapshot.email || '';
      phone = snapshot.phone || '';
      address1 = snapshot.address_1 || '';
      address2 = snapshot.address_2 || '';
      city = snapshot.city || '';
      postcode = snapshot.postcode || '';
      country = snapshot.country || '';
      state = snapshot.state || '';
    }

    return {
      name: [firstName, lastName].filter(Boolean).join(' ').trim(),
      email: email || '',
      HomePhone: phone || '',
      billingAddress: {
        addressLine1: address1 || '',
        addressLine2: address2 || '',
        city: city || '',
        postcode: postcode || '',
        country: toNumericCountry(country),
        state: state || '',
      },
    };
  }

  api.setContextProvider({
    getAmountMinor,
    getCardholder,
  });

  function isMonekSelected() {
    const checked = documentObject.querySelector(selectors.paymentMethodInput);
    return !!checked && checked.value === GATEWAY_ID;
  }

  function refreshMount() {
    if (!documentObject.querySelector(selectors.container)) {
      return;
    }

    if (isMonekSelected()) {
      log('mounting checkout component');
      Promise.resolve(api.mount()).catch((error) => {
        windowObject.console?.warn?.('[monek][legacy] mount failed', error);
      });
    } else {
      api.unmount();
    }
  }

  async function prepareTokens() {
    if (typeof api.trigger !== 'function') {
      throw new Error(configuration.strings?.token_error || 'Payment is not ready. Please try again.');
    }

    await api.mount();

    const { token, sessionId, expiry } = await api.trigger();
    const reference = api.getClientPaymentRef?.();

    if (!token || !sessionId || !expiry) {
      throw new Error(configuration.strings?.token_error || 'Payment is not ready. Please try again.');
    }

    setHiddenValue(selectors.hidden.token, token);
    setHiddenValue(selectors.hidden.session, sessionId);
    setHiddenValue(selectors.hidden.expiry, expiry);
    setHiddenValue(selectors.hidden.reference, reference);

    log('tokens written to hidden inputs');
    return true;
  }

  function bindClassicCheckout($form) {
    // WooCommerce fires `checkout_place_order` via `$form.triggerHandler(...)` on the
    // checkout form itself. `triggerHandler` does NOT bubble the DOM, so the handler
    // must be bound to the form element (not document.body) or it never runs and the
    // still-empty hidden inputs get posted. A false return aborts the submit; we then
    // fetch tokens asynchronously and re-submit.
    $form.on('checkout_place_order', function onPlaceOrder() {
      if (!isMonekSelected()) {
        return true;
      }

      if (tokensReady) {
        tokensReady = false;
        return true;
      }

      api.clearError();

      prepareTokens()
        .then(() => {
          tokensReady = true;
          $form.trigger('submit');
        })
        .catch((error) => {
          tokensReady = false;
          api.displayError(error?.message);
        });

      return false;
    });
  }

  function bindPayPage(payForm) {
    // The Order Pay page performs a standard (non-AJAX) POST submit.
    payForm.addEventListener('submit', function onPaySubmit(event) {
      if (!isMonekSelected()) {
        return;
      }

      if (tokensReady) {
        tokensReady = false;
        return;
      }

      event.preventDefault();
      api.clearError();

      prepareTokens()
        .then(() => {
          tokensReady = true;
          // Native submit() bypasses this listener, posting the hidden inputs directly.
          payForm.submit();
        })
        .catch((error) => {
          api.displayError(error?.message);
        });
    });
  }

  function bindSubmitInterception() {
    const $checkoutForm = jQueryInstance(selectors.checkoutForm);
    if ($checkoutForm.length) {
      bindClassicCheckout($checkoutForm);
      return;
    }

    const payForm = documentObject.querySelector(selectors.payForm);
    if (payForm) {
      bindPayPage(payForm);
    }
  }

  jQueryInstance(function onReady() {
    bindSubmitInterception();
    refreshMount();

    jQueryInstance(documentObject.body).on('updated_checkout payment_method_selected', refreshMount);
    jQueryInstance(documentObject).on('change', 'input[name="payment_method"]', refreshMount);
  });
})(window, document, window.jQuery);
