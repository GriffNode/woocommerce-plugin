=== GriffNode Crypto Payments for WooCommerce ===
Contributors: griffnode
Tags: woocommerce, cryptocurrency, bitcoin, crypto payments, payment gateway
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Accept Bitcoin, Ethereum, Litecoin, Dogecoin and stablecoins in WooCommerce. Non-custodial, no chargebacks - funds go to your own wallet.

== Description ==

**GriffNode is a non-custodial crypto payment gateway for WooCommerce.** Your customers pay in crypto and the money settles **straight to your own wallet** - GriffNode never holds, touches, or can freeze your funds. There are no chargebacks and no account holds.

You connect your wallet's extended public key (xPub) once, and GriffNode derives a fresh receiving address for every order, so payments go customer to merchant directly. Learn how it works at [griffnode.com](https://griffnode.com/) or read the full [WooCommerce setup guide](https://learn.griffnode.com/how-to-accept-crypto-payments-woocommerce).

**Why merchants switch to crypto checkout**

* **Non-custodial** - funds settle directly to your wallet, not ours. We literally cannot spend or freeze them.
* **No chargebacks** - on-chain settlement is final. A customer cannot keep your product and claw back the money.
* **Live in minutes** - sign up, paste your xPub, enable the gateway.
* **No frozen payouts** - no processor sits between you and your revenue.
* **Flat monthly pricing** - no percentage per transaction. See [pricing](https://griffnode.com/) (free Starter tier, then flat plans).

**Coins supported**

Bitcoin (BTC), Ethereum (ETH), Litecoin (LTC), Dogecoin (DOGE), Dash (DASH), plus the ERC-20 stablecoins **USDT, USDC and DAI** for price-stable payments.

**Features**

* A "Pay with Cryptocurrency" gateway at WooCommerce checkout with a live coin selector.
* Hosted, mobile-friendly payment page with a BIP-21 QR code.
* Real-time on-chain confirmation via signed webhooks (HMAC-SHA256).
* Automatic order completion the moment a payment confirms, partial-payment and expiry handling.
* HPOS (High-Performance Order Storage) compatible.
* Full REST API and SDKs (JavaScript, Python, PHP) at [docs.griffnode.com](https://docs.griffnode.com/).

This plugin connects to the GriffNode API (griffnode.com) to create hosted payment sessions and receive payment webhooks; a free GriffNode account and API key are required.

== External services ==

This plugin relies on the GriffNode API (`api.griffnode.com`), operated by GriffNode, to create
crypto payment sessions and to notify your store when a payment confirms. It is not optional:
the plugin cannot function without it.

**What is sent, and when:**

* When a customer places an order and selects a cryptocurrency, the plugin sends the order total,
  currency, the selected cryptocurrency, your store's return/cancel URLs, and the order's WooCommerce
  ID/order key and billing email to `https://api.griffnode.com/transactions/create`, authenticated
  with your Secret Key.
* On the checkout page, your store's browser fetches the list of cryptocurrencies you've
  configured wallets for from `https://api.griffnode.com/merchant/cryptos`, authenticated with your
  Publishable Key (safe to expose, it grants no write access).
* GriffNode sends signed payment-status webhooks (HMAC-SHA256) back to your store's
  `?wc-api=griffnode` endpoint as the customer's payment is detected/confirmed/expires.

See GriffNode's [Terms of Service](https://info.griffnode.com/terms-of-service) and
[Privacy Policy](https://info.griffnode.com/privacy-policy).

== Installation ==

1. Create a free account at [griffnode.com](https://griffnode.com/) and connect your wallet's xPub.
2. In your GriffNode dashboard, copy your **Publishable key**, **Secret key** and **Webhook secret**.
3. Install this plugin: Plugins → Add New → Upload, choose the zip, and Activate. (WooCommerce must be active.)
4. Go to WooCommerce → Settings → Payments → **GriffNode** and paste your keys.
5. Business plan and up: in your GriffNode dashboard, set the webhook URL to `https://your-store.com/?wc-api=griffnode` so orders are marked paid automatically. On the free Starter plan there is no webhook: GriffNode emails you each payment and you set the order to Processing yourself.
6. Place a small test order to confirm end to end.

== Frequently Asked Questions ==

= Are crypto payments reversible? Can I get a chargeback? =
No. On-chain settlement is final, so there are no chargebacks. This is a major advantage for digital goods and high-risk categories.

= Where does the money go? =
Directly to your own wallet, every time, via the xPub you connect. GriffNode cannot custody, spend, or freeze it.

= Which cryptocurrencies can customers pay with? =
Bitcoin, Ethereum, Litecoin, Dogecoin and Dash, plus the USDT, USDC and DAI stablecoins.

= Does it work with High-Performance Order Storage (HPOS)? =
Yes. The plugin declares HPOS compatibility.

= What does it cost? =
GriffNode uses flat monthly pricing with no percentage per transaction, including a free Starter tier. See [griffnode.com](https://griffnode.com/) for current plans.

= Where are the docs? =
Full API and integration docs are at [docs.griffnode.com](https://docs.griffnode.com/).

== Screenshots ==

1. The "Pay with Cryptocurrency" option at WooCommerce checkout with the live coin selector.
2. GriffNode payment settings in WooCommerce (API keys and options).
3. The hosted payment page with a BIP-21 QR code and live confirmation status.

== Changelog ==

= 1.0.3 =
* Automatic order updates use a GriffNode webhook (Business plan and up). Without a webhook, each order gets a note saying it must be set to Processing by hand after GriffNode emails you the payment.
* Default checkout description now lists Ethereum and stablecoins.

= 1.0.2 =
* Works in the block-based Checkout (the WooCommerce default for new stores) as well as the classic checkout.
* Display name is now "GriffNode Crypto Payments for WooCommerce".
* Checkout script is now enqueued (wp_enqueue_script) instead of printed inline, and re-fills the selector after WooCommerce refreshes the checkout.
* Text domain matches the plugin slug (griffnode-for-woocommerce); all user-facing strings translatable.
* Fixed the Terms of Service and Privacy Policy links in External services.

= 1.0.1 =
* Fix: transaction creation sent the fiat amount under the wrong field name, which the API
  silently interpreted as USD regardless of your store's actual currency, so non-USD stores were
  billing the wrong crypto amount on every order. Fixed.
* Fix: orders never automatically left "Pending" on payment, because the plugin looked for the
  order under a field the API doesn't return for this integration. Order matching is now done via
  the checkout metadata that's actually echoed back, with the transaction id used as a
  cross-check.
* Fix: two of the API's payment-status events (overpaid, failed) were not handled at all;
  overpaid orders in particular could stay stuck "Pending" despite being paid. Both are now
  handled.
* Fix: the crypto selector on checkout built its dropdown via raw HTML string concatenation;
  it's now built through the DOM API instead.
* Improvement: resubmitting checkout (double-click, back-button) now reuses an order's existing
  payment session for up to 55 minutes instead of creating a new one each time.
* Improvement: customer email is now sent as its own field so the hosted payment page can
  pre-fill it, instead of only being nested in metadata.
* Improvement: the webhook endpoint now requires HTTPS.
* Docs: added the required External Services disclosure.

= 1.0.0 =
* Initial release: non-custodial crypto payment gateway for WooCommerce (BTC, ETH, LTC, DOGE, DASH + USDT/USDC/DAI), hosted checkout, signed webhooks, HPOS support.

== Upgrade Notice ==

= 1.0.1 =
Important correctness fixes: wrong-currency billing on non-USD stores and orders not
auto-completing are both fixed. Upgrade is strongly recommended for all existing installs.

= 1.0.0 =
Initial release.
