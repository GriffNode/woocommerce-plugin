/* GriffNode for WooCommerce: payment method for the block-based Checkout.
 * Renders the same crypto selector as the classic checkout and sends the choice as
 * payment data (griffnode_crypto), which process_payment() reads. */
(function () {
    var registry = window.wc && window.wc.wcBlocksRegistry;
    var settings = window.wc && window.wc.wcSettings;
    if (!registry || !settings) return;

    var el = window.wp.element.createElement;
    var useState = window.wp.element.useState;
    var useEffect = window.wp.element.useEffect;
    var __ = window.wp.i18n.__;
    var decode = window.wp.htmlEntities.decodeEntities;
    var data = settings.getSetting('griffnode_data', {});
    var title = decode(data.title || 'Crypto (GriffNode)');

    function Content(props) {
        var state = useState({ list: null, error: '' });
        var choice = useState('');
        var s = state[0], setS = state[1], value = choice[0], setValue = choice[1];

        useEffect(function () {
            if (!data.pk) { setS({ list: [], error: __('No publishable key configured', 'griffnode-for-woocommerce') }); return; }
            fetch(data.api + '/merchant/cryptos', { headers: { 'Authorization': 'Bearer ' + data.pk } })
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    var list = (d && d.data && d.data.cryptocurrencies) || [];
                    setS({ list: list, error: list.length ? '' : __('No cryptos configured, check your GriffNode dashboard', 'griffnode-for-woocommerce') });
                    if (list.length) setValue(list[0].symbol);
                })
                .catch(function () { setS({ list: [], error: __('Failed to load, try refreshing', 'griffnode-for-woocommerce') }); });
        }, []);

        useEffect(function () {
            return props.eventRegistration.onPaymentSetup(function () {
                if (!value) {
                    return { type: props.emitResponse.responseTypes.ERROR, message: __('Please select a cryptocurrency.', 'griffnode-for-woocommerce') };
                }
                return { type: props.emitResponse.responseTypes.SUCCESS, meta: { paymentMethodData: { griffnode_crypto: value } } };
            });
        }, [value]);

        var options = (s.list || []).map(function (c) {
            return el('option', { key: c.symbol, value: c.symbol }, c.name + ' (' + c.symbol + ')');
        });
        if (s.list === null) options = [el('option', { key: 'l', value: '' }, __('Loading...', 'griffnode-for-woocommerce'))];

        return el('div', null,
            data.description ? el('p', null, decode(data.description)) : null,
            el('label', { htmlFor: 'griffnode_crypto_block' }, __('Select cryptocurrency', 'griffnode-for-woocommerce')),
            el('select', {
                id: 'griffnode_crypto_block', value: value, style: { width: '100%', marginTop: '4px' },
                onChange: function (e) { setValue(e.target.value); }
            }, options),
            s.error ? el('p', { role: 'alert' }, s.error) : null
        );
    }

    registry.registerPaymentMethod({
        name: 'griffnode',
        label: el('span', null, title),
        ariaLabel: title,
        content: el(Content),
        edit: el(Content),
        canMakePayment: function () { return true; },
        supports: { features: ['products'] }
    });
})();
