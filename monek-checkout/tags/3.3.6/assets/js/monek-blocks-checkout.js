(function () {
    const registry = window.wc?.wcBlocksRegistry;
    const getSetting = window.wc?.wcSettings?.getSetting;
    const h = window.wp?.element?.createElement;

    if (!registry || !getSetting || !h) return;

    const s = getSetting('monek-checkout_data', {});
    const desc = s.description || 'Pay securely with Monek.';

    function Content(/* props */) {
        return h('div', null, desc);
    }

    registry.registerPaymentMethod({
        name: 'monek-checkout',
        label: s.title || 'Credit/Debit Card',
        ariaLabel: 'Monek',

        content: h(Content, {}),
        edit: h(Content, {}),

        canMakePayment: () => true,
        supports: { features: (s.supports && s.supports.features) || ['products'] },
    });
})();
