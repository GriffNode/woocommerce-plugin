# WordPress.org listing assets — still needed

`readme.txt`'s `== Screenshots ==` section currently promises 3 numbered screenshots, but no
image files exist anywhere in this repository yet, and there's no icon/banner. WordPress.org
expects these as `screenshot-1.png` (etc.), `icon-256x256.png`, and `banner-1544x500.png` in the
listing's assets (typically the plugin's SVN `assets/` folder, separate from trunk).

This directory is a placeholder so the gap is tracked instead of silently shipping a listing with
broken screenshot placeholders. It needs real captures from a working install before submission:

- [ ] `screenshot-1.png` — the "Pay with Cryptocurrency" option at WooCommerce checkout with the live coin selector.
- [ ] `screenshot-2.png` — GriffNode payment settings in WooCommerce (API keys and options).
- [ ] `screenshot-3.png` — the hosted payment page with a BIP-21 QR code and live confirmation status.
- [ ] `icon-128x128.png` / `icon-256x256.png` — plugin icon.
- [ ] `banner-772x250.png` / `banner-1544x500.png` — listing banner.

See `docs/audits/woocommerce_plugin_audit_v2.md` (finding #6) for context.
