=== GriffNode for WooCommerce - Accept Crypto Payments (No KYC, No Chargebacks) ===
Contributors: griffnode
Tags: woocommerce, cryptocurrency, bitcoin, crypto payments, payment gateway
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Accept Bitcoin, Ethereum, Litecoin, Dogecoin and stablecoins in WooCommerce. Non-custodial, no KYC, no chargebacks - funds go to your own wallet.

== Description ==

**GriffNode is a non-custodial crypto payment gateway for WooCommerce.** Your customers pay in crypto and the money settles **straight to your own wallet** - GriffNode never holds, touches, or can freeze your funds. There are no chargebacks, no KYC, and no "high-risk" account holds.

You connect your wallet's extended public key (xPub) once, and GriffNode derives a fresh receiving address for every order, so payments go customer to merchant directly. Learn how it works at [griffnode.com](https://griffnode.com/) or read the full [WooCommerce setup guide](https://learn.griffnode.com/how-to-accept-crypto-payments-woocommerce).

**Why merchants switch to crypto checkout**

* **Non-custodial** - funds settle directly to your wallet, not ours. We literally cannot spend or freeze them.
* **No chargebacks** - on-chain settlement is final. A customer cannot keep your product and claw back the money.
* **No KYC** - because we never custody funds, signing up is just an email and password.
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

== Installation ==

1. Create a free account at [griffnode.com](https://griffnode.com/) and connect your wallet's xPub.
2. In your GriffNode dashboard, copy your **Publishable key**, **Secret key** and **Webhook secret**.
3. Install this plugin: Plugins → Add New → Upload, choose the zip, and Activate. (WooCommerce must be active.)
4. Go to WooCommerce → Settings → Payments → **GriffNode** and paste your keys.
5. In your GriffNode dashboard, set the webhook URL to `https://your-store.com/?wc-api=griffnode`.
6. Place a small test order to confirm end to end.

== Frequently Asked Questions ==

= Do I need KYC or identity verification? =
No. Because GriffNode never holds your funds, there is no custodial obligation to verify identity. You sign up with an email and password.

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

= 1.0.0 =
* Initial release: non-custodial crypto payment gateway for WooCommerce (BTC, ETH, LTC, DOGE, DASH + USDT/USDC/DAI), hosted checkout, signed webhooks, HPOS support.

== Upgrade Notice ==

= 1.0.0 =
Initial release.
