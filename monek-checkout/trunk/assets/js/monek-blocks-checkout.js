(function initializeMonekBlocksIntegration() {
  'use strict';

  const windowObject = window;
  const registry = windowObject.wc?.wcBlocksRegistry;
  const getSetting = windowObject.wc?.wcSettings?.getSetting;
  const element = windowObject.wp?.element;

  if (!registry || !getSetting || !element) {
    return;
  }

  const { registerPaymentMethod, registerExpressPaymentMethod } = registry;
  const { createElement, useEffect, useRef } = element;

  const settings = getSetting('monek-checkout_data', {}) || {};
  const supportedFeatures = resolveSupportedFeatures(settings);
  const shouldShowExpress = normalizeBoolean(settings.showExpress, true);
  const paymentMethodLabel = settings.title || 'Monek Checkout';
  const paymentMethodDescription = settings.description;
  const paymentMethodLogo = settings.logoUrl || '';

  // Logs the outcome of an express order by observing the checkout data store; this
  // deliberately does not wrap fetch so it cannot interfere with order placement.
  function watchExpressCheckoutOutcome(paymentReference) {
    const wpData = windowObject.wp?.data;
    if (!wpData?.subscribe || !wpData?.select || !settings.logUrl || !settings.logNonce) {
      return;
    }

    let sawProcessing = false;
    let unsubscribe = null;
    const timeout = windowObject.setTimeout(() => stop(), 120000);

    function stop() {
      windowObject.clearTimeout(timeout);
      if (unsubscribe) unsubscribe();
    }

    function sendLog(hasError, checkoutStore) {
      const redirectUrl = checkoutStore.getRedirectUrl?.() || '';
      const body = new URLSearchParams({
        action: 'monek_log_express_checkout',
        nonce: settings.logNonce,
        flow: 'blocks',
        status: '0',
        reference: paymentReference || '',
        response: JSON.stringify({
          order_id: checkoutStore.getOrderId?.() || 0,
          payment_result: {
            payment_status: hasError ? 'failure' : 'success',
            redirect_url: redirectUrl,
          },
        }),
      });
      windowObject.fetch(settings.logUrl, { method: 'POST', body }).catch(() => {});
    }

    unsubscribe = wpData.subscribe(() => {
      const checkoutStore = wpData.select('wc/store/checkout');
      if (!checkoutStore) return;

      if (checkoutStore.isProcessing?.()) {
        sawProcessing = true;
        return;
      }

      if (!sawProcessing) return;

      const hasError = !!checkoutStore.hasError?.();
      if (hasError || checkoutStore.isComplete?.()) {
        stop();
        sendLog(hasError, checkoutStore);
      }
    });
  }

  function normalizeBoolean(value, defaultValue) {
    if (value === undefined || value === null) return defaultValue;
    if (typeof value === 'boolean') return value;
    if (typeof value === 'number') return value !== 0;
    if (typeof value === 'string') {
      const normalized = value.trim().toLowerCase();
      if (normalized === 'true' || normalized === 'yes' || normalized === '1') return true;
      if (normalized === 'false' || normalized === 'no' || normalized === '0') return false;
    }
    return defaultValue;
  }

  function resolveSupportedFeatures(configuration) {
    if (Array.isArray(configuration.supports)) return configuration.supports;
    if (Array.isArray(configuration.supports?.features)) return configuration.supports.features;
    return ['products'];
  }

  function updateBlocksContext(context) {
    windowObject.monekBlocksCtx = context;
  }

  function extractBillingFromProps(properties) {
    const billingFromBlocks = properties?.billing?.billingAddress;
    if (billingFromBlocks) return billingFromBlocks;
    return properties?.billingAddress || {};
  }

  function extractContactFromProps(properties) {
    return properties?.billing?.contact || {};
  }

  function createContentElement(props) {
    const { eventRegistration, emitResponse, isEditor } = props || {};
    const registerSetup =
      eventRegistration?.onPaymentSetup;

    const latestPropsRef = useRef(props);

    useEffect(() => {
      latestPropsRef.current = props;
      const latestBilling = extractBillingFromProps(latestPropsRef.current);
      const latestContact = extractContactFromProps(latestPropsRef.current);

      updateBlocksContext({
        getBilling: () => latestBilling,
        getEmail: () => (latestContact.email ?? latestBilling.email ?? '').trim(),
        getPhone: () => (latestContact.phone ?? latestBilling.phone ?? '').trim(),
      });
    }, [props]);

    useEffect(() => {
      if (isEditor) return;
      try {
        windowObject.monekCheckout?.mount?.();
      } catch (e) {
        windowObject.console?.warn?.('[monek] mount failed (standard):', e);
      }

      return () => {
        try {
          windowObject.monekCheckout?.unmount?.();
        } catch (e) {
          windowObject.console?.warn?.('[monek] unmount failed (standard):', e);
        }
      };
    }, [isEditor]);

    useEffect(() => {
      if (!registerSetup) return () => {};
      const responseTypes = emitResponse?.responseTypes || { SUCCESS: 'SUCCESS', ERROR: 'ERROR' };
      const unsubscribe = registerSetup(async () => {
        const expressPaymentPayload = windowObject.__monekExpressPayload;
        if (expressPaymentPayload?.monek_reference) {
          try { delete windowObject.__monekExpressPayload; } catch (_) {}
          return {
            type: responseTypes.SUCCESS,
            meta: {
              paymentMethodData: {
                monek_reference: expressPaymentPayload.monek_reference,
                monek_mode: 'express',
              },
            },
          };
        }

        try {
          if (!windowObject.monekCheckout?.trigger) {
            throw new Error('Payment initialisation not ready. Please try again.');
          }

          const { token, sessionId, expiry } = await windowObject.monekCheckout.trigger();
          const paymentReference = windowObject.monekCheckout?.getClientPaymentRef?.();

          if (!token || !sessionId || !expiry) {
            throw new Error('Payment initialisation data not ready! Please try again.');
          }
  
          windowObject.console?.log?.('[monek] mode -> standard');

          return {
            type: responseTypes.SUCCESS,
            meta: {
              paymentMethodData: {
                monek_token: token,
                monek_session: sessionId,
                monek_expiry: expiry,
                monek_reference: paymentReference,
                monek_mode: 'standard',
              },
            },
          };
        } catch (error) {
          return {
            type: responseTypes.ERROR,
            message: error?.message || 'There was a problem preparing your payment. Please try again.',
          };
        }
      });

      return () => {
        try {
          unsubscribe?.();
        } catch (error) {
          windowObject.console?.warn?.('[monek] Failed to remove payment setup listener', error);
        }
      };
    }, [registerSetup, emitResponse?.responseTypes]);

    useEffect(() => {
      const registerFail = eventRegistration?.onCheckoutFail;
      if (!registerFail) return () => {};

      const unsubscribe = registerFail(({ processingResponse } = {}) => {
        const message = processingResponse?.paymentDetails?.errorMessage;
        if (message) {
          try {
            windowObject.monekCheckout?.displayError?.(message);
          } catch (error) {
            windowObject.console?.warn?.('[monek] Failed to display checkout failure', error);
          }
        }
        return true;
      });

      return () => {
        try {
          unsubscribe?.();
        } catch (error) {
          windowObject.console?.warn?.('[monek] Failed to remove checkout fail listener', error);
        }
      };
    }, [eventRegistration?.onCheckoutFail]);

    return createElement(
      'div',
      { key: props?._key || 'monek-standard', className: 'monek-checkout-wrapper', 'data-monek-block': 'true' },
      createElement('div', { key: 'container', id: 'monek-checkout-container', className: 'monek-sdk-surface', 'aria-live': 'polite' }),
      createElement('div', { key: 'messages', id: 'monek-checkout-messages', className: 'monek-checkout-messages', role: 'alert', 'aria-live': 'polite' }),
      paymentMethodLogo
        ? createElement('img', {
            key: 'logo',
            src: paymentMethodLogo,
            alt: `${paymentMethodLabel} logo`,
            className: 'monek-checkout-logo',
            loading: 'lazy',
          })
        : null,
      paymentMethodDescription
        ? createElement('span', { key: 'desc', className: 'monek-checkout-description' }, paymentMethodDescription)
        : null,
    );
  }

  // The wallet payment has already been taken by the time we get here, so the checkout
  // submit that follows must not be blocked by state left over from the untouched form.
  //
  // 1. setBillingAddress/setShippingAddress only update the local store; the push to the
  //    server is debounced. Submitting straight away sends the checkout request with the
  //    totals for the old address, which the Store API rejects
  //    (woocommerce_rest_checkout_total_mismatch). Push the addresses now and wait for the
  //    recalculated cart.
  // 2. The Checkout block registers hidden validation errors for address fields the shopper
  //    has not touched. They clear when a field's value changes, so an optional field the
  //    wallet leaves empty (typically the UK county) keeps its error and blocks the submit.
  //    Once the server has accepted the addresses, drop those never-shown errors; the server
  //    validates the address again when the order is placed.
  async function syncWalletAddressesBeforeSubmit(cartDispatch, billingAddress, shippingAddress) {
    if (typeof cartDispatch?.updateCustomerData !== 'function') {
      windowObject.console?.warn?.('[monek] updateCustomerData is unavailable; wallet addresses were not pushed to the store before submit.');
      await clearUntouchedAddressValidationErrors(billingAddress, shippingAddress);
      return;
    }

    try {
      await cartDispatch.updateCustomerData(
        { billing_address: billingAddress, shipping_address: shippingAddress },
        false,
      );
    } catch (error) {
      // The server refused the address. Leave the validation errors in place so the
      // shopper is shown what needs correcting.
      windowObject.console?.warn?.('[monek] wallet address was not accepted by the store', error);
      return;
    }

    await clearUntouchedAddressValidationErrors(billingAddress, shippingAddress);
  }

  const OPTIONAL_ADDRESS_ERROR_IDS = /^(billing|shipping)[_-](state|address_2|company)$/;

  function countryHasStateList(countryCode) {
    const countryData = getSetting('countryData', {}) || {};
    const states = countryData[String(countryCode || '').toUpperCase()]?.states;
    return !!states && typeof states === 'object' && Object.keys(states).length > 0;
  }

  function clearOptionalAddressErrorsOnce(billingAddress, shippingAddress) {
    try {
      const validationSelect = windowObject.wp?.data?.select?.('wc/store/validation');
      const validationDispatch = windowObject.wp?.data?.dispatch?.('wc/store/validation');
      const errors = validationSelect?.getValidationErrors?.();

      if (!errors || typeof validationDispatch?.clearValidationErrors !== 'function') {
        return;
      }

      // Only optional-by-nature fields; genuinely required fields keep their errors. A state
      // error is also dropped when it is already visible, but only for countries without a
      // state list (e.g. GB), where the wallet legitimately has nothing to send.
      const staleIds = Object.keys(errors).filter((id) => {
        if (!OPTIONAL_ADDRESS_ERROR_IDS.test(id)) {
          return false;
        }
        if (errors[id]?.hidden === true) {
          return true;
        }
        const address = id.startsWith('shipping') ? shippingAddress : billingAddress;
        return /state$/.test(id) && !address?.state && !countryHasStateList(address?.country);
      });

      if (staleIds.length) {
        validationDispatch.clearValidationErrors(staleIds);
      }
    } catch (error) {
      windowObject.console?.warn?.('[monek] failed to clear untouched address validation errors', error);
    }
  }

  // The address fields re-run their validation after the store updates and can re-register
  // the error a moment later, so clear again once the form has settled.
  async function clearUntouchedAddressValidationErrors(billingAddress, shippingAddress) {
    clearOptionalAddressErrorsOnce(billingAddress, shippingAddress);
    await new Promise((resolve) => windowObject.setTimeout(resolve, 250));
    clearOptionalAddressErrorsOnce(billingAddress, shippingAddress);
  }

  function createExpressContentElement(props) {
    const {
      buttonAttributes,
      onClose,
      onSubmit,
      setExpressPaymentError,
      isEditor,
      eventRegistration,
      emitResponse,
    } = props || {};

    const mountedRef = useRef(false);
    const listenersRegisteredRef = useRef(false);

    useEffect(() => {
      if (isEditor) return () => {};
      mountedRef.current = true;
      try {
        windowObject.monekCheckout?.mountExpress?.('#monek-express-container');
      } catch (e) {
        windowObject.console?.warn?.('[monek] mountExpress failed:', e);
      }

      return () => {
        mountedRef.current = false;
        try {
          windowObject.monekCheckout?.unmountExpress?.();
        } catch (e) {
          windowObject.console?.warn?.('[monek] unmountExpress failed:', e);
        }
      };
    }, [isEditor]);

    useEffect(() => {
      try {
        windowObject.monekCheckout?.setExpressStyle?.(buttonAttributes);
      } catch (e) {
        windowObject.console?.warn?.('[monek] setExpressStyle failed:', e);
      }
    }, [buttonAttributes]);

    useEffect(() => {
      if (listenersRegisteredRef.current) return () => {};
      listenersRegisteredRef.current = true;

      const removeListeners = registerExpressSdkListeners(onSubmit, onClose, setExpressPaymentError);
      return () => {
        try { removeListeners(); } finally { listenersRegisteredRef.current = false; }
      };
    }, [onSubmit, onClose, setExpressPaymentError]);

    return createElement(
      'div',
      { key: props?._key || 'monek-express', className: 'monek-express-wrapper', 'data-monek-block': 'true' },
      createElement('div', { key: 'container', id: 'monek-express-container', className: 'monek-sdk-surface', 'aria-live': 'polite' }),
    );
  }

  function normaliseStateText(value) {
    return String(value || '')
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '')
      .replace(/[^a-z0-9]/gi, '')
      .toLowerCase();
  }

  // Blocks rejects a state that isn't one of WooCommerce's codes for that country, even when
  // the field is optional. Map Apple Pay's free-text value to a code, or send nothing.
  function resolveStateCode(countryCode, administrativeArea) {
    const area = String(administrativeArea || '').trim();
    const country = String(countryCode || '').toUpperCase();
    if (!area || !country) {
      return '';
    }

    const countryData = getSetting('countryData', {}) || {};
    const states = countryData[country]?.states;
    if (!states || typeof states !== 'object' || !Object.keys(states).length) {
      return '';
    }

    const wanted = normaliseStateText(area);
    const entries = Object.entries(states);
    const match = entries.find(([code]) => normaliseStateText(code) === wanted)
      || entries.find(([, name]) => normaliseStateText(name) === wanted)
      || entries.find(([, name]) => normaliseStateText(name).includes(wanted) || wanted.includes(normaliseStateText(name)));

    return match ? match[0] : '';
  }

  // Minimum fields WooCommerce Blocks needs to consider a billing/shipping address valid.
  const REQUIRED_ADDRESS_FIELDS = ['address_1', 'city', 'postcode', 'country'];

  function hasMinimumAddressFields(address) {
    return REQUIRED_ADDRESS_FIELDS.every((field) => !!address?.[field]);
  }

  function registerExpressSdkListeners(onSubmit, onClose, setExpressPaymentError) {
    async function handleSuccess() {
      const ref = windowObject.monekCheckout?.getClientPaymentRef?.();
      if (!ref) return;

      watchExpressCheckoutOutcome(ref);

      const expressPaymentPayload = {
        monek_reference: ref,
        monek_mode: 'express',
      };

      windowObject.__monekExpressPayload = expressPaymentPayload;

      const paymentResponseData = await windowObject.monekCheckout.waitForCompletionOnce();
      const ctx = paymentResponseData?.ctx?.applePay || {};

      // The SDK resolves the billing contact (falling back to the shipping contact, and
      // declining the payment before any backend call if neither has a usable address)
      // before completion, so ctx.billingContact should always be populated here.
      if (!ctx.billingContact) {
        windowObject.console?.warn?.('[monek] Apple Pay did not return a usable billing address; order placement may fail Blocks validation.');
      }

      const billingAddressFromApplePay = {
        first_name: ctx.billingContact?.givenName ?? "",
        last_name: ctx.billingContact?.familyName ?? "",
        email: ctx.payerEmail ?? "",
        address_1: ctx.billingContact?.addressLines?.join(' ') ?? "",
        city: ctx.billingContact?.locality ?? "",
        state: resolveStateCode(ctx.billingContact?.countryCode, ctx.billingContact?.administrativeArea),
        postcode: ctx.billingContact?.postalCode ?? "",
        country: ctx.billingContact?.countryCode ?? "",
        phone: ctx.payerPhone ?? "",
      };

      const shippingAddressFromApplePay = {
        first_name: ctx.shippingContact?.givenName ?? "",
        last_name: ctx.shippingContact?.familyName ?? "",
        address_1: ctx.shippingContact?.addressLines?.join(' ') ?? "",
        city: ctx.shippingContact?.locality ?? "",
        state: resolveStateCode(ctx.shippingContact?.countryCode, ctx.shippingContact?.administrativeArea),
        postcode: ctx.shippingContact?.postalCode ?? "",
        country: ctx.shippingContact?.countryCode ?? "",
        phone: ctx.shippingContact?.phoneNumber ?? "",
      };

      // If the wallet didn't return a separate shipping address, use the (already resolved)
      // billing address so Blocks has a complete shipping address too.
      const billingAddress = billingAddressFromApplePay;
      const shippingAddress = hasMinimumAddressFields(shippingAddressFromApplePay)
        ? shippingAddressFromApplePay
        : billingAddress;

      if (!hasMinimumAddressFields(billingAddress)) {
        windowObject.console?.warn?.('[monek] Billing address is incomplete; order placement may fail Blocks validation even though the Apple Pay payment has already been authorised.', billingAddress);
      }

      try {
        const cartDispatch = windowObject.wp?.data?.dispatch?.('wc/store/cart');

        if (cartDispatch?.setBillingAddress) {
          await cartDispatch.setBillingAddress(billingAddress);
        }

        if (cartDispatch?.setShippingAddress) {
          await cartDispatch.setShippingAddress(shippingAddress);
        }

        await syncWalletAddressesBeforeSubmit(cartDispatch, billingAddress, shippingAddress);

        const paymentDispatch = windowObject.wp?.data?.dispatch?.('wc/store/payment');

        if (paymentDispatch?.__internalSetPaymentMethodData) {
          paymentDispatch.__internalSetPaymentMethodData(expressPaymentPayload);
        }

        windowObject.console?.log?.('[monek] payment data set:', expressPaymentPayload);
      } catch (err) {
        windowObject.console?.warn?.('[monek] failed to set payment method data', err);
      }

      if (typeof onSubmit === 'function') onSubmit();
    }

    function handleCancel() {
      if (typeof setExpressPaymentError === 'function') {
        setExpressPaymentError('Payment cancelled.');
      }
      if (typeof onClose === 'function') onClose();
    }

    function handleError() {
      if (typeof setExpressPaymentError === 'function') {
        setExpressPaymentError('Payment failed. Please try another method.');
      }
      if (typeof onClose === 'function') onClose();
    }

    windowObject.addEventListener('monek:express:success', handleSuccess);
    windowObject.addEventListener('monek:express:cancel', handleCancel);
    windowObject.addEventListener('monek:express:error', handleError);

    return () => {
      windowObject.removeEventListener('monek:express:success', handleSuccess);
      windowObject.removeEventListener('monek:express:cancel', handleCancel);
      windowObject.removeEventListener('monek:express:error', handleError);
    };
  }

  if (windowObject.console?.log) {
    windowObject.console.log('[monek] registerPaymentMethod', { settings, features: supportedFeatures });
  }

  registerPaymentMethod({
    name: 'monek-checkout',
    paymentMethodId: 'monek-checkout',
    label: paymentMethodLabel,
    ariaLabel: paymentMethodLabel,
    content: createElement(createContentElement, { _key: 'monek-standard-content' }),
    edit: createElement(createContentElement, { isEditor: true, _key: 'monek-standard-edit' }),
    canMakePayment: () => true,
    supports: { features: supportedFeatures },
  });

  if (shouldShowExpress) {
    registerExpressPaymentMethod({
      name: 'monek-checkout-express',
      paymentMethodId: 'monek-checkout-express',
      label: 'Monek Express',
      content: createElement(createExpressContentElement, { _key: 'monek-express-content' }),
      edit: createElement(createExpressContentElement, { isEditor: true, _key: 'monek-express-edit' }),
      canMakePayment: () => true,
      supports: { features: ['products'], style: ['height', 'borderRadius'] },
    });
  } else if (windowObject.console?.log) {
    windowObject.console.log('[monek] express checkout disabled via settings');
  }

  (function setMonekAsDefault() {
    const w = windowObject;
    const data = w.wp?.data;
    if (!data) return;

    const methodId = 'monek-checkout';
    const storesToTry = ['wc/store/checkout', 'wc/store/payment'];

    function getSelectors(store) {
      try { return data.select(store); } catch (_) { return null; }
    }
    function getDispatch(store) {
      try { return data.dispatch(store); } catch (_) { return null; }
    }

    function selectMethod() {
      for (const store of storesToTry) {
        const d = getDispatch(store);
        if (!d) continue;
        if (typeof d.setSelectedPaymentMethod === 'function') { d.setSelectedPaymentMethod(methodId); return true; }
        if (typeof d.selectPaymentMethod === 'function')       { d.selectPaymentMethod(methodId);       return true; }
        if (typeof d.setPaymentMethod === 'function')          { d.setPaymentMethod(methodId);          return true; }
        if (typeof d.__experimentalSelectPaymentMethod === 'function') { d.__experimentalSelectPaymentMethod(methodId); return true; }
      }
      return false;
    }

    let debounceTimer = null;
    function debouncedSelect() {
      if (debounceTimer) clearTimeout(debounceTimer);
      debounceTimer = setTimeout(() => {
        try { selectMethod(); } catch (_) {}
      }, 0);
    }

    debouncedSelect();

    w.addEventListener('load', debouncedSelect);

    const unsubscribers = [];
    for (const store of storesToTry) {
      const subscribe = getDispatch(store)?.subscribe || data?.subscribe;
      const selectors = getSelectors(store);
      if (!subscribe || !selectors) continue;

      let lastAvailable = null;
      const un = subscribe(() => {
        try {
          const getPaymentMethods = selectors.getPaymentMethods || selectors.getAvailablePaymentMethods || selectors.__experimentalGetPaymentMethods;
          const methods = typeof getPaymentMethods === 'function' ? getPaymentMethods() : null;
          const available = methods ? Object.keys(methods).length : null;

          if (available !== lastAvailable) {
            lastAvailable = available;
            debouncedSelect();
          }
        } catch (_) {
          debouncedSelect();
        }
      });

      if (typeof un === 'function') {
        unsubscribers.push(un);
      }
    }

    w.addEventListener('beforeunload', () => {
      try { unsubscribers.forEach((u) => u && u()); } catch (_) {}
    });
  })();
})();
