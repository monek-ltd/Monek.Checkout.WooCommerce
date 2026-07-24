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
    express: '#monek-express-container',
    expressForm: '#monek-express-form',
    paymentMethodInput: 'input[name="payment_method"]:checked',
    checkoutForm: 'form.woocommerce-checkout',
    payForm: 'form#order_review',
    hidden: {
      mode: 'monek_mode',
      token: 'monek_token',
      session: 'monek_session',
      expiry: 'monek_expiry',
      reference: 'monek_reference',
    },
  };

  // Guard so our re-submit of the classic checkout form is allowed straight through.
  let tokensReady = false;
  // Guards for the express (Apple Pay) wallet re-submit.
  let expressReady = false;
  let expressSubmitting = false;
  // Cached reference to the classic checkout form so the express flow can submit it.
  let classicForm = null;

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
    classicForm = $form;

    // WooCommerce fires checkout_place_order via $form.triggerHandler() on the checkout form itself. 
    // A false return aborts the submit, we then fetch tokens asynchronously and re-submit.
    $form.on('checkout_place_order', function onPlaceOrder() {
      // Express (Apple Pay) has already authorised and populated the hidden fields;
      // let the submit through without running the card tokenisation flow.
      if (expressReady) {
        expressReady = false;
        return true;
      }

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

  function joinAddressLines(lines) {
    if (Array.isArray(lines)) {
      return lines.filter(Boolean).join(' ').trim();
    }

    return lines ? String(lines) : '';
  }

  function setCheckoutFieldValue(id, value) {
    const element = documentObject.getElementById(id);
    if (!element) {
      return;
    }

    // Set the value only (no change event) so WooCommerce doesn't kick off an update_checkout AJAX refresh
    element.value = value == null ? '' : String(value);
  }

  function applyApplePayContactToCheckout(applePayContext) {
    const billing = applePayContext.billingContact || {};
    const shipping = applePayContext.shippingContact || {};

    const addressSource = (joinAddressLines(shipping.addressLines) || shipping.postalCode)
      ? shipping
      : billing;

    setCheckoutFieldValue('billing_first_name', billing.givenName || shipping.givenName || '');
    setCheckoutFieldValue('billing_last_name', billing.familyName || shipping.familyName || '');
    setCheckoutFieldValue('billing_email', applePayContext.payerEmail || shipping.emailAddress || '');
    setCheckoutFieldValue('billing_phone', applePayContext.payerPhone || shipping.phoneNumber || '');
    setCheckoutFieldValue('billing_address_1', joinAddressLines(addressSource.addressLines));
    setCheckoutFieldValue('billing_city', addressSource.locality || '');
    setCheckoutFieldValue('billing_state', addressSource.administrativeArea || '');
    setCheckoutFieldValue('billing_postcode', addressSource.postalCode || '');
    setCheckoutFieldValue('billing_country', addressSource.countryCode || '');

    const shipToDifferent = documentObject.getElementById('ship-to-different-address-checkbox');
    if (shipToDifferent) {
      shipToDifferent.checked = false;
    }
  }

  function selectMonekPaymentMethod() {
    const radio = documentObject.querySelector('input[name="payment_method"][value="' + GATEWAY_ID + '"]');
    if (radio && !radio.checked) {
      // Set the checked property directly (no change event) so we don't trigger an update_checkout refresh
      radio.checked = true;
    }
  }

  function onExpressSuccess(event) {
    if (expressSubmitting) {
      return;
    }

    const reference = api.getClientPaymentRef?.();
    if (!reference) {
      api.displayError(configuration.strings?.token_error);
      return;
    }

    if (!classicForm || !classicForm.length) {
      api.displayError(configuration.strings?.token_error);
      return;
    }

    const applePayContext = event?.detail?.ctx?.applePay || {};

    api.clearError();
    applyApplePayContactToCheckout(applePayContext);
    selectMonekPaymentMethod();

    setHiddenValue(selectors.hidden.mode, 'express');
    setHiddenValue(selectors.hidden.reference, reference);

    expressSubmitting = true;
    expressReady = true;

    log('submitting express order');
    classicForm.trigger('submit');
  }

  function onExpressCancelled() {
    expressSubmitting = false;
    expressReady = false;
  }

  function onExpressFailed() {
    expressSubmitting = false;
    expressReady = false;
    api.displayError(configuration.strings?.express_error
      || 'Payment failed. Please try another payment method.');
  }

  function setupExpressCheckout() {
    // The container is only rendered by the gateway when express is enabled, so its
    // absence means there is nothing to do on this page.
    const container = documentObject.querySelector(selectors.express);
    if (!container) {
      return;
    }

    const expressForm = documentObject.querySelector(selectors.expressForm);
    if (expressForm) {
      // The SDK requires its mount target inside a <form>, but this dedicated form must
      // never submit — the wallet drives the real checkout form via onExpressSuccess.
      expressForm.addEventListener('submit', (submitEvent) => submitEvent.preventDefault());
    }

    windowObject.addEventListener('monek:express:success', onExpressSuccess);
    windowObject.addEventListener('monek:express:cancel', onExpressCancelled);
    windowObject.addEventListener('monek:express:error', onExpressFailed);

    Promise.resolve(api.mountExpress(selectors.express)).catch((error) => {
      windowObject.console?.warn?.('[monek][legacy] express mount failed', error);
    });
  }

  jQueryInstance(function onReady() {
    bindSubmitInterception();
    refreshMount();
    setupExpressCheckout();

    jQueryInstance(documentObject.body).on('updated_checkout payment_method_selected', refreshMount);
    jQueryInstance(documentObject).on('change', 'input[name="payment_method"]', refreshMount);
  });
})(window, document, window.jQuery);
