/* GriffNode for WooCommerce: fills the crypto selector on the classic checkout.
 * WooCommerce re-renders the payment box over AJAX (updated_checkout), so this runs
 * on load and after every re-render. Options are built via the DOM API: symbol/name
 * come from the API response and must never be parsed as markup. */
(function ($) {
    var cfg = window.griffnodeCheckout || {};

    function note(sel, text) {
        sel.innerHTML = '';
        var opt = document.createElement('option');
        opt.value = '';
        opt.textContent = text;
        sel.appendChild(opt);
    }

    function fill() {
        var sel = document.getElementById('griffnode_crypto');
        if (!sel || sel.getAttribute('data-filled')) return;
        sel.setAttribute('data-filled', '1');
        if (!cfg.pk) { note(sel, cfg.i18n.noKey); return; }
        fetch(cfg.api + '/merchant/cryptos', { headers: { 'Authorization': 'Bearer ' + cfg.pk } })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                var cryptos = (data && data.data && data.data.cryptocurrencies) || [];
                if (!cryptos.length) { note(sel, cfg.i18n.none); return; }
                sel.innerHTML = '';
                cryptos.forEach(function (c) {
                    var opt = document.createElement('option');
                    opt.value = c.symbol;
                    opt.textContent = c.name + ' (' + c.symbol + ')';
                    sel.appendChild(opt);
                });
            })
            .catch(function () { note(sel, cfg.i18n.failed); });
    }

    $(fill);
    $(document.body).on('updated_checkout', fill);
})(jQuery);
