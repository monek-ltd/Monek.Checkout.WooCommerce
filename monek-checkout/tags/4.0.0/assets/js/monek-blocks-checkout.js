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
  const paymentMethodDescription = settings.description || 'Pay securely with Monek.';
  const paymentMethodLogo = settings.logoUrl || '';

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

  function createExpressContentElement(props) {
    const {
      buttonAttributes,
      onClose,
      onSubmit,
      setExpressPaymentError,
      isEditor,
      eventRegistration,
    } = props || {};

    const mountedRef = useRef(false);
    const listenersRegisteredRef = useRef(false);

    useEffect(() => {
      if (isEditor || mountedRef.current) return;
      mountedRef.current = true;
      try {
        windowObject.monekCheckout?.mountExpress?.('#monek-express-container');
      } catch (e) {
        windowObject.console?.warn?.('[monek] mountExpress failed:', e);
      }
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

    useEffect(() => {
      const register = eventRegistration?.onPaymentSetup;
      if (!register) return () => {};

      const unsubscribe = register(async () => {
        const paymentReference = windowObject.monekCheckout?.getClientPaymentRef?.();
        if (!paymentReference) {
          return { type: 'ERROR', message: 'Payment reference missing.' };
        }
        return {
          type: 'SUCCESS',
          meta: {
            paymentMethodData: {
              monek_reference: paymentReference,
              monek_mode: 'express',
            },
          },
        };
      });

      return () => {
        try {
          unsubscribe?.();
        } catch (error) {
          windowObject.console?.warn?.('[monek] Failed to remove express setup listener', error);
        }
      };
    }, [eventRegistration?.onPaymentSetup]);

    return createElement(
      'div',
      { key: props?._key || 'monek-express', className: 'monek-express-wrapper', 'data-monek-block': 'true' },
      createElement('div', { key: 'container', id: 'monek-express-container', className: 'monek-sdk-surface', 'aria-live': 'polite' }),
    );
  }

  function registerExpressSdkListeners(onSubmit, onClose, setExpressPaymentError) {
    function handleSuccess() {
      const ref = windowObject.monekCheckout?.getClientPaymentRef?.();
      if (!ref) return;

      const payload = {
        monek_reference: ref,
        monek_mode: 'express',
      };

      windowObject.__monekExpressPayload = payload;

      try {
        const dispatch = windowObject.wp?.data?.dispatch?.('wc/store/payment');

        if (dispatch?.__internalSetPaymentMethodData) {
          dispatch.__internalSetPaymentMethodData(payload);
        }

        console.log('[monek] payment data set:', payload);
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
