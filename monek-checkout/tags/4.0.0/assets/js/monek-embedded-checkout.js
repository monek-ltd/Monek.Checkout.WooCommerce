/* global jQuery */
(function initializeMonekEmbeddedCheckout(windowObject, documentObject, jQueryInstance) {
  'use strict';

  const wcSettings = windowObject.wc?.wcSettings;
  const configurationFromBlocks = wcSettings?.getSetting?.('monek-checkout_data', {}) || {};
  const legacyConfiguration = windowObject.monekCheckoutConfig || {};
  const configuration = { ...legacyConfiguration, ...configurationFromBlocks };

  if (!configuration.publishableKey) {
    return;
  }

  const expressEnabled = normalizeBoolean(configuration.showExpress, true);

  const selectors = {
    wrapper: '#monek-checkout-wrapper',
    messages: '#monek-checkout-messages',
    express: '#monek-express-container',
    checkout: '#monek-checkout-container',
  };

  const state = {
    sdkPromise: null,
    checkoutComponent: null,
    expressComponent: null,
    mountingPromise: null,
    expressStyle: null,
    expressResult: null,
    clientPaymentReference: null,
    completionResolver: null,
  };

  const HEX_COLOR_REGEX = /^#(?:[0-9a-f]{3}|[0-9a-f]{6})$/i;

  function isPlainObject(value) {
    return !!value && typeof value === 'object' && !Array.isArray(value);
  }

  function normaliseHexColor(value, fallback) {
    if (typeof value !== 'string') {
      return fallback;
    }

    const trimmed = value.trim();
    if (HEX_COLOR_REGEX.test(trimmed)) {
      return trimmed;
    }

    return fallback;
  }

  function resolveThemeMode(configurationObject) {
    const mode = configurationObject?.themeMode || configurationObject?.theme || 'light';
    if (typeof mode !== 'string') {
      return 'light';
    }

    const lower = mode.toLowerCase();
    if (lower === 'dark' || lower === 'custom') {
      return lower;
    }

    return 'light';
  }

  function resolveExpressDefaults(configurationObject) {
    const themeMode = resolveThemeMode(configurationObject);
    if (themeMode === 'custom') {
      return { borderRadius: 12 };
    }

    return {};
  }

  function resolveStylingConfiguration(configurationObject) {
    if (isPlainObject(configurationObject?.styling)) {
      return configurationObject.styling;
    }

    const themeMode = resolveThemeMode(configurationObject);
    if (themeMode === 'custom' && isPlainObject(configurationObject?.customTheme)) {
      const customTheme = configurationObject.customTheme;
      const background = normaliseHexColor(customTheme.backgroundColor, '#ffffff');
      const text = normaliseHexColor(customTheme.textColor, '#1a1a1a');
      const inputBackground = normaliseHexColor(customTheme.inputBackgroundColor, '#ffffff');
      const accent = normaliseHexColor(customTheme.accentColor, '#1460f2');

      return {
        theme: 'light',
        core: {
          backgroundColor: background,
          textColor: text,
          borderRadius: 16,
        },
        inputs: {
          inputBackgroundColor: inputBackground,
          inputTextColor: text,
          inputBorderColor: accent,
          inputBorderRadius: 12,
        },
        cssVars: {
          '--monek-input-focus': accent,
        },
      };
    }

    if (themeMode === 'dark') {
      return { theme: 'dark' };
    }

    return { theme: 'light' };
  }

  const ISO_ALPHA2_TO_NUMERIC = {
    GB: '826', US: '840', IE: '372', NL: '528', DE: '276', FR: '250', ES: '724', IT: '380', PT: '620',
    BE: '056', SE: '752', NO: '578', DK: '208', FI: '246', IS: '352', AT: '040', CH: '756', PL: '616',
    CZ: '203', SK: '703', HU: '348', RO: '642', BG: '100', GR: '300', CY: '196', MT: '470', EE: '233',
    LV: '428', LT: '440', LU: '442', SI: '705', HR: '191', CA: '124', AU: '036', NZ: '554', JP: '392',
    KR: '410', CN: '156', HK: '344', SG: '702', MY: '458', TH: '764', VN: '704', PH: '608', ID: '360',
    IN: '356', BR: '076', AR: '032', CL: '152', MX: '484', CO: '170', PE: '604', ZA: '710', TR: '792',
    IL: '376', SA: '682', AE: '784', QA: '634', KW: '414', BH: '048', OM: '512',
  };

  function emitEvent(name, detail) {
    try {
      windowObject.dispatchEvent(new CustomEvent(name, { detail }));
    } catch (error) {
      if (windowObject.console?.warn) {
        windowObject.console.warn('[monek] Failed to emit event', name, error);
      }
    }
  }

  function resolveCompletion(detail) {
    if (typeof state.completionResolver === 'function') {
      state.completionResolver(detail);
      state.completionResolver = null;
    }
  }

  function normalizeBoolean(value, defaultValue) {
    if (value === undefined || value === null) {
      return defaultValue;
    }

    if (typeof value === 'boolean') {
      return value;
    }

    if (typeof value === 'number') {
      return value !== 0;
    }

    if (typeof value === 'string') {
      const normalized = value.trim().toLowerCase();

      if (normalized === 'true' || normalized === 'yes' || normalized === '1') {
        return true;
      }

      if (normalized === 'false' || normalized === 'no' || normalized === '0') {
        return false;
      }
    }

    return defaultValue;
  }

  function setExpressStyle(style) {
    state.expressStyle = style || null;
  }

  function waitForCompletionOnce() {
    return new Promise((resolve) => {
      state.completionResolver = resolve;
    });
  }

  function createClientPaymentReference() {
    try {
      if (windowObject.crypto?.randomUUID) {
        return `MNK-${windowObject.crypto.randomUUID()}`;
      }
    } catch (error) {
      if (windowObject.console?.warn) {
        windowObject.console.warn('[monek] Failed to generate UUID, falling back', error);
      }
    }

    return `MNK-${Date.now()}-${Math.random().toString(36).slice(2)}`;
  }

  function ensureClientPaymentReference() {
    if (!state.clientPaymentReference) {
      state.clientPaymentReference = createClientPaymentReference();
    }

    return state.clientPaymentReference;
  }

  function getClientPaymentReference() {
    return ensureClientPaymentReference();
  }

  function toIso3166Numeric(code) {
    if (!code) {
      return null;
    }

    const trimmedCode = String(code).trim();
    if (/^\d{3}$/.test(trimmedCode)) {
      return trimmedCode;
    }

    const upperCaseCode = trimmedCode.toUpperCase();
    if (upperCaseCode === 'UK') {
      return '826';
    }

    return ISO_ALPHA2_TO_NUMERIC[upperCaseCode] || null;
  }

  function displayError(message) {
    const container = documentObject.querySelector(selectors.messages);
    const fallbackMessage = configuration.strings?.token_error || 'There was a problem preparing your payment. Please try again.';
    const resolvedMessage = message || fallbackMessage;

    if (container) {
      container.textContent = resolvedMessage;
      container.style.display = '';
    }

    if (typeof jQueryInstance === 'function') {
      try {
        jQueryInstance(documentObject.body).trigger('checkout_error', [resolvedMessage]);
      } catch (error) {
        if (windowObject.console?.warn) {
          windowObject.console.warn('[monek] Failed to trigger checkout_error event', error);
        }
      }
    }
  }

  function clearError() {
    const container = documentObject.querySelector(selectors.messages);
    if (container) {
      container.textContent = '';
    }
  }

  function selectFromBlocks(storeNamespace) {
    return windowObject.wp?.data?.select?.(storeNamespace);
  }

  function getBlocksStores() {
    return {
      cart: selectFromBlocks('wc/store/cart'),
    };
  }

  function getOrderTotalMinor() {
    const { cart } = getBlocksStores();
    const totals = cart?.getCartTotals?.();
    return totals?.total_price || 0;
  }

  function readCustomerFromPropsContext() {
    const context = windowObject.monekBlocksCtx;
    if (!context) {
      return null;
    }

    const billing = typeof context.getBilling === 'function' ? context.getBilling() || {} : {};
    const email = typeof context.getEmail === 'function' ? context.getEmail() || '' : '';
    const phone = typeof context.getPhone === 'function' ? context.getPhone() || '' : '';

    return { billing, email, phone };
  }

  function buildCardholderDetails() {
    const propsCustomer = readCustomerFromPropsContext() || {};
    const billing = propsCustomer.billing || {};

    const firstName = billing.first_name ?? billing.firstName ?? '';
    const lastName = billing.last_name ?? billing.lastName ?? '';

    return {
      name: [firstName, lastName].filter(Boolean).join(' ').trim(),
      email: propsCustomer.email || '',
      HomePhone: propsCustomer.phone || billing.phone || '',
      billingAddress: {
        addressLine1: billing.address_1 ?? billing.address1 ?? '',
        addressLine2: billing.address_2 ?? billing.address2 ?? '',
        city: billing.city ?? '',
        postcode: billing.postcode ?? billing.postalCode ?? '',
        country: toIso3166Numeric(billing.country ?? billing.countryCode) || (configuration.countryNumeric || '826'),
        state: billing.state ?? billing.region ?? '',
      },
    };
  }

  function waitForSdk() {
    if (state.sdkPromise) {
      return state.sdkPromise;
    }

    state.sdkPromise = new Promise((resolve, reject) => {
      let attempts = 0;
      const interval = windowObject.setInterval(() => {
        attempts += 1;

        if (typeof windowObject.Monek === 'function') {
          windowObject.clearInterval(interval);

          try {
            Promise.resolve(windowObject.Monek(configuration.publishableKey))
              .then(resolve)
              .catch(reject);
          } catch (error) {
            reject(error);
          }

          return;
        }

        if (attempts >= 40) {
          windowObject.clearInterval(interval);
          reject(new Error('Monek Checkout SDK not available.'));
        }
      }, 250);
    });

    return state.sdkPromise;
  }

  function buildComponentOptions(isExpress) {
    const paymentReference = ensureClientPaymentReference();

    const callbacks = {
      getAmount: () => ({ minor: getOrderTotalMinor(), currency: configuration.currencyNumeric || '826' }),
      getDescription: () => configuration.orderDescription || 'Order',
      getCardholderDetails: buildCardholderDetails,
    };

    const baseStyling = resolveStylingConfiguration(configuration);

    const options = {
      callbacks,
      paymentReference,
      countryCode: toIso3166Numeric(configuration.countryNumeric || 'GB'),
      intent: 'Purchase',
      order: 'Checkout',
      settlementType: 'Auto',
      cardEntry: 'ECommerce',
      storeCardDetails: false,
      challenge: configuration.challenge || { display: 'popup', size: 'medium' },
      completion: {
        mode: 'none',
        onSuccess: (context) => {
          const token = state.checkoutComponent?.getCardTokenId?.() || state.expressComponent?.getCardTokenId?.() || null;
          const sessionId = state.checkoutComponent?.getSessionId?.() || state.expressComponent?.getSessionId?.() || null;
          const expiry = state.checkoutComponent?.getCardExpiry?.() || state.expressComponent?.getCardExpiry?.() || null;

          const detail = { status: 'success', ctx: context, token, sessionId, expiry };
          state.expressResult = detail;
          emitEvent('monek:express:success', detail);
          resolveCompletion(detail);
        },
        onError: (context, helpers) => {
          const detail = { status: 'error', ctx: context };
          state.expressResult = detail;
          emitEvent('monek:express:error', detail);
          helpers?.reenable?.();
          resolveCompletion(detail);
        },
        onCancel: (context, helpers) => {
          const detail = { status: 'cancel', ctx: context };
          state.expressResult = detail;
          emitEvent('monek:express:cancel', detail);
          helpers?.reenable?.();
          resolveCompletion(detail);
        },
      },
      debug: !!configuration.debug,
      styling: baseStyling,
    };

    if (isExpress) {
      const style = state.expressStyle || {};
      const expressDefaults = resolveExpressDefaults(configuration);
      const expressStyling = { ...expressDefaults };

      if (typeof style.height !== 'undefined') {
        expressStyling.height = style.height;
      }

      if (typeof style.borderRadius !== 'undefined') {
        expressStyling.borderRadius = style.borderRadius;
      }

      options.styling = {
        ...(options.styling || {}),
        express: expressStyling,
      };
      options.surface = 'express';
    }

    return options;
  }

  async function mountExpress(selector) {
    if (!expressEnabled) {
      if (windowObject.console?.log) {
        windowObject.console.log('[monek] express disabled; skipping mount');
      }
      return false;
    }

    if (windowObject.console?.log) {
      windowObject.console.log('[monek] mountExpress →', selector);
    }

    if (!selector) {
      return false;
    }

    const container = documentObject.querySelector(selector);
    if (!container) {
      if (windowObject.console?.warn) {
        windowObject.console.warn('[monek] express container not found:', selector);
      }
      return false;
    }

    if (state.expressComponent) {
      if (windowObject.console?.log) {
        windowObject.console.log('[monek] express already mounted');
      }
      return true;
    }

    try {
      const sdk = await waitForSdk();
      const express = sdk.createComponent('express', buildComponentOptions(true));
      await express.mount(selector);
      state.expressComponent = express;

      if (windowObject.console?.log) {
        windowObject.console.log('[monek] express mounted');
      }
      return true;
    } catch (error) {
      if (windowObject.console?.error) {
        windowObject.console.error('[monek] express mount failed:', error);
      }
      return false;
    }
  }

  async function mountComponents() {
    if (state.mountingPromise) {
      return state.mountingPromise;
    }

    const checkoutContainer = documentObject.querySelector(selectors.checkout);
    if (!checkoutContainer) {
      return false;
    }

    state.mountingPromise = waitForSdk()
      .then(async (sdk) => {
        if (!state.checkoutComponent) {
          const checkout = sdk.createComponent('checkout', buildComponentOptions(false));
          await checkout.mount(selectors.checkout);
          state.checkoutComponent = checkout;
        }

        return true;
      })
      .catch((error) => {
        displayError(error?.message || 'There was a problem preparing your payment. Please try again.');
        return false;
      })
      .finally(() => {
        state.mountingPromise = null;
      });

    return state.mountingPromise;
  }

  async function trigger() {
    if (!state.checkoutComponent) {
      throw new Error('Checkout component not ready.');
    }

    await state.checkoutComponent.triggerSubmission();
    const token = state.checkoutComponent.getCardTokenId?.() || state.checkoutComponent.getCardTokenId;
    const sessionId = state.checkoutComponent.getSessionId?.() || state.checkoutComponent.getSessionId;
    const expiry = state.checkoutComponent.getCardExpiry?.() || state.checkoutComponent.getCardExpiry;

    if (!token || !sessionId) {
      throw new Error('Card details not ready.');
    }

    if (configuration.debug && windowObject.console?.log) {
      windowObject.console.log('[monek] trigger() → token/session', { token, sessionId });
    }

    return { token, sessionId, expiry };
  }

  windowObject.monekCheckout = {
    mount: mountComponents,
    setExpressStyle,
    mountExpress,
    trigger,
    displayError,
    clearError,
    getClientPaymentRef: getClientPaymentReference,
    waitForCompletionOnce,
  };
})(window, document, window.jQuery);
