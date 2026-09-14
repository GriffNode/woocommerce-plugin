<?php
defined( 'ABSPATH' ) || exit;

class GriffNode_Gateway extends WC_Payment_Gateway {

    public function __construct() {
        $this->id                 = 'griffnode';
        $this->method_title       = 'GriffNode';
        $this->method_description = 'Accept BTC, ETH, LTC, DOGE, DASH and USDT/USDC/DAI stablecoins via GriffNode. Funds settle directly to your own wallet - GriffNode never holds them. No KYC, no chargebacks.';
        $this->has_fields         = true; // we render a crypto selector on checkout

        $this->init_form_fields();
        $this->init_settings();

        $this->title       = $this->get_option( 'title' );
        $this->description = $this->get_option( 'description' );

        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, [ $this, 'process_admin_options' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
    }

    // ── Admin settings ────────────────────────────────────────────────────────

    public function init_form_fields() {
        $this->form_fields = [
            'enabled' => [
                'title'   => 'Enable / Disable',
                'type'    => 'checkbox',
                'label'   => 'Enable GriffNode payments',
                'default' => 'yes',
            ],
            'title' => [
                'title'   => 'Title',
                'type'    => 'text',
                'default' => 'Pay with Cryptocurrency',
                'desc_tip' => 'Label shown to the customer on the checkout page.',
            ],
            'description' => [
                'title'   => 'Description',
                'type'    => 'textarea',
                'default' => 'Pay securely with Bitcoin, Litecoin, Dogecoin or Dash. You will be redirected to a hosted payment page.',
            ],
            'publishable_key' => [
                'title'    => 'Publishable Key (pk_live_ / pk_test_)',
                'type'     => 'text',
                'desc_tip' => 'Used to fetch your supported cryptos on the checkout page. Safe to expose in the browser.',
            ],
            'secret_key' => [
                'title'    => 'Secret Key (sk_live_ / sk_test_)',
                'type'     => 'password',
                'desc_tip' => 'Used server-side to create transactions. Never exposed to the browser.',
            ],
            'webhook_secret' => [
                'title'    => 'Webhook Secret',
                'type'     => 'password',
                'desc_tip' => 'Found in your GriffNode dashboard under Webhooks. Used to verify incoming payment events.',
            ],
        ];
    }

    // ── Checkout field (crypto selector) ─────────────────────────────────────

    public function payment_fields() {
        if ( $this->description ) {
            echo '<p>' . esc_html( $this->description ) . '</p>';
        }

        $pk = esc_attr( $this->get_option( 'publishable_key' ) );
        ?>
        <div id="griffnode-crypto-selector">
            <p>
                <label for="griffnode_crypto"><?php esc_html_e( 'Select cryptocurrency', 'griffnode-woocommerce' ); ?></label>
                <select name="griffnode_crypto" id="griffnode_crypto" style="width:100%;margin-top:4px">
                    <option value=""><?php esc_html_e( 'Loading...', 'griffnode-woocommerce' ); ?></option>
                </select>
            </p>
        </div>
        <script>
        (function() {
            var pk  = <?php echo wp_json_encode( $pk ); ?>;
            var sel = document.getElementById('griffnode_crypto');
            if (!pk) {
                sel.innerHTML = '<option value="">No publishable key configured</option>';
                return;
            }
            fetch('<?php echo esc_url( GRIFFNODE_API_BASE ); ?>/merchant/cryptos', {
                headers: { 'Authorization': 'Bearer ' + pk }
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                var cryptos = (data && data.data && data.data.cryptocurrencies) ? data.data.cryptocurrencies : [];
                if (!cryptos.length) {
                    sel.innerHTML = '<option value="">No cryptos configured — check your dashboard</option>';
                    return;
                }
                // Build option elements via the DOM API rather than string-concatenated
                // innerHTML — c.symbol/c.name come straight from the API response, and
                // this way a future unexpected value can never execute as markup.
                sel.innerHTML = '';
                cryptos.forEach(function(c) {
                    var opt = document.createElement('option');
                    opt.value = c.symbol;
                    opt.textContent = c.name + ' (' + c.symbol + ')';
                    sel.appendChild(opt);
                });
            })
            .catch(function() {
                sel.innerHTML = '<option value="">Failed to load — try refreshing</option>';
            });
        })();
        </script>
        <?php
    }

    public function validate_fields() {
        // Nonce is verified by WooCommerce core during the checkout process before this runs.
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $crypto = isset( $_POST['griffnode_crypto'] ) ? sanitize_text_field( wp_unslash( $_POST['griffnode_crypto'] ) ) : '';
        if ( '' === $crypto ) {
            wc_add_notice( __( 'Please select a cryptocurrency.', 'griffnode-woocommerce' ), 'error' );
            return false;
        }
        return true;
    }

    // ── Process payment ───────────────────────────────────────────────────────

    public function process_payment( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            wc_add_notice( __( 'Payment error: order not found.', 'griffnode-woocommerce' ), 'error' );
            return [ 'result' => 'failure' ];
        }

        // Idempotency: a double-click on "Place order", a back-button resubmit, or a
        // WooCommerce checkout retry would otherwise call /transactions/create again and
        // overwrite the order's transaction id/payment URL, orphaning the first one. If
        // this order already has a live GriffNode transaction (created within the last
        // 55 minutes — transactions expire after 60), send the customer back to it
        // instead of minting a new deposit address.
        $existing_url     = $order->get_meta( '_griffnode_payment_url' );
        $existing_created = (int) $order->get_meta( '_griffnode_created_at' );
        if ( $existing_url && $order->has_status( 'pending' ) && $existing_created
            && ( time() - $existing_created ) < 55 * MINUTE_IN_SECONDS ) {
            return [
                'result'   => 'success',
                'redirect' => $existing_url,
            ];
        }

        // Nonce is verified by WooCommerce core during checkout before process_payment() runs.
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $crypto = isset( $_POST['griffnode_crypto'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_POST['griffnode_crypto'] ) ) ) : '';

        if ( ! $crypto ) {
            wc_add_notice( __( 'Please select a cryptocurrency.', 'griffnode-woocommerce' ), 'error' );
            return [ 'result' => 'failure' ];
        }

        $amount   = (float) $order->get_total();
        $currency = strtoupper( get_woocommerce_currency() );

        // Fall back to USD if the store currency is not supported by GriffNode.
        $supported_fiat = [ 'USD', 'PLN', 'EUR', 'GBP' ];
        if ( ! in_array( $currency, $supported_fiat, true ) ) {
            $currency = 'USD';
        }

        $secret_key = $this->get_option( 'secret_key' );

        $payload = [
            'crypto'         => $crypto,
            'amount'         => $amount,
            // NOTE: the API's request field is "currency_fiat", not "currency" — see
            // CreateTransactionRequest in hephaestus/internal/handlers/api/transaction.go.
            // Sending the wrong key doesn't error, it silently defaults server-side to
            // USD, which mis-bills every non-USD store. Keep this key exactly as-is.
            'currency_fiat'  => $currency,
            'customer_email' => $order->get_billing_email(),
            'success_url'    => $this->get_return_url( $order ),
            'cancel_url'     => wc_get_cart_url(),
            // The basic /transactions/create endpoint has no top-level order_id field
            // (only /transactions/create-detailed does) — metadata is the only channel
            // that survives the round trip into the webhook payload, so wc_order_id
            // belongs here, not as a top-level field.
            'metadata'       => [
                'wc_order_id'  => (string) $order_id,
                'wc_order_key' => $order->get_order_key(),
                'customer'     => $order->get_billing_email(),
            ],
        ];

        $response = wp_remote_post( GRIFFNODE_API_BASE . '/transactions/create', [
            'headers' => [
                'Authorization' => 'Bearer ' . $secret_key,
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
            ],
            'body'    => wp_json_encode( $payload ),
            'timeout' => 30,
        ] );

        if ( is_wp_error( $response ) ) {
            wc_add_notice( __( 'Payment error: could not reach GriffNode. Please try again.', 'griffnode-woocommerce' ), 'error' );
            return [ 'result' => 'failure' ];
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        $code = wp_remote_retrieve_response_code( $response );

        if ( $code !== 201 || empty( $body['data']['payment_url'] ) ) {
            $msg = $body['message'] ?? 'Unknown error';
            /* translators: %s: error message returned by the GriffNode API. */
            wc_add_notice( sprintf( __( 'Payment error: %s', 'griffnode-woocommerce' ), esc_html( $msg ) ), 'error' );
            return [ 'result' => 'failure' ];
        }

        // Store the GriffNode transaction ID on the order for webhook matching.
        // NOTE: the API's create response field is "transaction_id" (the legacy "txid"
        // key was retired when the backend moved to its canonical field names — see
        // docs/sdk/api-inconsistencies.md, item B1). Keep this key exactly as-is.
        $order->update_meta_data( '_griffnode_txid', $body['data']['transaction_id'] ?? '' );
        $order->update_meta_data( '_griffnode_payment_url', $body['data']['payment_url'] );
        $order->update_meta_data( '_griffnode_created_at', time() );
        $order->update_status( 'pending', __( 'Awaiting GriffNode payment.', 'griffnode-woocommerce' ) );
        $order->save();

        return [
            'result'   => 'success',
            'redirect' => $body['data']['payment_url'],
        ];
    }

    public function enqueue_scripts() {
        // Nothing extra needed — the inline script in payment_fields() handles everything.
    }
}
