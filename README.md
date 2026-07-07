# GriffNode for WooCommerce

Accept Bitcoin, Litecoin, Dogecoin and Dash payments in your WooCommerce store. Customers pick their preferred coin at checkout and are redirected to a hosted payment page — funds go directly to your wallet, GriffNode never holds them.

**Requirements:** WordPress 6.0+, WooCommerce 7.0+, PHP 7.4+

---

## Installation

### 1 — Download

Download the latest `cryptogate-woocommerce.zip` from the [Releases](https://github.com/CryptoGateHQ/woocommerce-plugin/releases) page.

### 2 — Install

1. In your WordPress admin go to **Plugins → Add New → Upload Plugin**
2. Choose the downloaded ZIP and click **Install Now**
3. Click **Activate Plugin**

### 3 — Configure

Go to **WooCommerce → Settings → Payments**, find **GriffNode** and click **Manage**.

| Field | Where to find it |
|-------|-----------------|
| **Publishable Key** | Dashboard → API Integration → `pk_live_...` — safe to expose in the browser |
| **Secret Key** | Dashboard → API Integration → `sk_live_...` — keep this private |
| **Webhook Secret** | Dashboard → Webhooks → your endpoint's signing secret |

### 4 — Register your webhook

In your [GriffNode dashboard](https://griffnode.com/dashboard) go to **Webhooks** and add:

```
https://yourstore.com/?wc-api=cryptogate
```

Subscribe to: `payment.completed`, `payment.partial`, `payment.expired`

Copy the signing secret into the **Webhook Secret** field in the plugin settings.

### 5 — Add wallets

The checkout dropdown only shows cryptos you have wallets configured for. Go to **Dashboard → Wallet Management** and add an xpub for each coin you want to accept.

---

## How it works

1. Customer selects a cryptocurrency at checkout and clicks Place Order
2. Plugin calls the GriffNode API and redirects the customer to a hosted payment page
3. Customer sends the exact crypto amount within the 60-minute window
4. GriffNode fires a webhook — order is automatically marked as **Processing** in WooCommerce

---

## Test mode

Replace your live keys with `pk_test_...` and `sk_test_...` in the plugin settings. No real funds move in test mode.

---

## Support

- [Documentation](https://docs.griffnode.com#woocommerce)
- [GriffNode Dashboard](https://griffnode.com/dashboard) — open a support ticket
- [GitHub Issues](https://github.com/CryptoGateHQ/woocommerce-plugin/issues)
