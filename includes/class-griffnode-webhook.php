<?php
defined( 'ABSPATH' ) || exit;

class GriffNode_Webhook {

    /**
     * Entry point — hooked to woocommerce_api_griffnode.
     * URL: https://yoursite.com/?wc-api=griffnode
     */
    public static function handle() {
        if ( ! is_ssl() ) {
            self::respond( 400, 'HTTPS required.' );
        }

        $raw_body = file_get_contents( 'php://input' );

        // Verify HMAC-SHA256 signature.
        $gateway = new GriffNode_Gateway();
        $secret  = $gateway->get_option( 'webhook_secret' );

        if ( empty( $secret ) ) {
            self::respond( 400, 'Webhook secret not configured.' );
        }

        $sig_header = isset( $_SERVER['HTTP_X_GRIFFNODE_SIGNATURE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_GRIFFNODE_SIGNATURE'] ) ) : '';
        // Header format: "sha256=<hex>"
        $expected = 'sha256=' . hash_hmac( 'sha256', $raw_body, $secret );

        if ( ! hash_equals( $expected, $sig_header ) ) {
            self::respond( 401, 'Invalid signature.' );
        }

        $event = json_decode( $raw_body, true );
        if ( ! $event || empty( $event['event'] ) ) {
            self::respond( 400, 'Invalid payload.' );
        }

        switch ( $event['event'] ) {
            case 'payment.completed':
            // The backend treats an overpayment as a completed, fundable payment
            // (hephaestus/internal/handlers/inthandlers/transactions.go gates fulfillment
            // on "completed" OR "overpaid") — handle it the same way here, otherwise a
            // customer who paid *more* than required leaves the order stuck Pending forever.
            case 'payment.overpaid':
                self::on_completed( $event );
                break;

            case 'payment.partial':
                self::on_partial( $event );
                break;

            case 'payment.expired':
                self::on_expired( $event );
                break;

            case 'payment.failed':
                self::on_failed( $event );
                break;

            case 'payment.paid_late':
            case 'payment.needs_review':
                self::on_exception( $event, substr( $event['event'], 8 ) );
                break;

            default:
                // payment.created / payment.pending and any future event types: nothing
                // to do (the order is already "pending" from process_payment()), but log
                // it so an unrecognized/unhandled type is visible instead of silently
                // dropped, the way payment.overpaid/payment.failed were before this fix.
                if ( function_exists( 'wc_get_logger' ) ) {
                    wc_get_logger()->info(
                        sprintf( 'Received webhook event with no dedicated handler: %s', $event['event'] ),
                        [ 'source' => 'griffnode' ]
                    );
                }
                break;
        }

        self::respond( 200, 'OK' );
    }

    // ── Event handlers ────────────────────────────────────────────────────────

    private static function on_completed( array $event ) {
        $order = self::find_order( $event );
        if ( ! $order ) return;

        self::mark_done( $order );
        // Avoid double-processing.
        if ( $order->has_status( [ 'processing', 'completed' ] ) ) return;

        // payment_complete() also revives an order WooCommerce auto-cancelled (Hold stock
        // timer) before the coins confirmed. By then the stock went back on sale, so if it
        // sold out, completing would push it below zero and sell one item twice. Hold the
        // order instead and let the merchant restock or refund.
        if ( self::out_of_stock( $order ) ) {
            if ( $order->get_meta( '_griffnode_stock_hold' ) ) return; // already told the merchant
            $order->update_meta_data( '_griffnode_stock_hold', 1 );
            self::hold( $order, sprintf(
                /* translators: %s: GriffNode transaction ID. */
                __( 'GriffNode payment confirmed after WooCommerce had released the stock. TX: %s. Restock and set this order to Processing, or refund the customer from your wallet.', 'griffnode-for-woocommerce' ),
                $event['transaction_id'] ?? '—'
            ) );
            return;
        }

        $order->payment_complete( $event['transaction_id'] ?? '' );
        $order->add_order_note( sprintf(
            /* translators: 1: GriffNode transaction ID, 2: crypto currency code, 3: crypto amount, 4: crypto currency code. */
            __( 'GriffNode payment confirmed. TX: %1$s | Crypto: %2$s | Amount: %3$s %4$s', 'griffnode-for-woocommerce' ),
            $event['transaction_id'] ?? '—',
            $event['currency_crypto'] ?? '—',
            $event['amount_crypto'] ?? '—',
            $event['currency_crypto'] ?? ''
        ) );
    }

    private static function on_failed( array $event ) {
        $order = self::find_order( $event );
        if ( ! $order ) return;
        self::mark_done( $order );

        if ( $order->has_status( 'pending' ) ) {
            $order->update_status( 'failed', sprintf(
                /* translators: %s: GriffNode transaction ID. */
                __( 'GriffNode payment failed. TX: %s', 'griffnode-for-woocommerce' ),
                $event['transaction_id'] ?? '—'
            ) );
        }
    }

    private static function on_partial( array $event ) {
        $order = self::find_order( $event );
        if ( ! $order || $order->has_status( [ 'processing', 'completed' ] ) ) return;
        if ( $order->has_status( 'on-hold' ) && $order->get_meta( '_griffnode_partial' ) ) return; // already noted
        $order->update_meta_data( '_griffnode_partial', 1 );

        self::hold( $order, sprintf(
            /* translators: %s: GriffNode transaction ID. */
            __( 'GriffNode partial payment received. TX: %s - customer paid less than required. Awaiting resolution.', 'griffnode-for-woocommerce' ),
            $event['transaction_id'] ?? '—'
        ) );
    }

    private static function on_expired( array $event ) {
        $order = self::find_order( $event );
        if ( ! $order ) return;
        self::mark_done( $order );

        if ( $order->has_status( 'pending' ) ) {
            $order->update_status( 'cancelled', sprintf(
                /* translators: %s: GriffNode transaction ID. */
                __( 'GriffNode payment window expired. TX: %s', 'griffnode-for-woocommerce' ),
                $event['transaction_id'] ?? '—'
            ) );
        }
    }

    /**
     * Coins arrived after the payment window (paid_late), or the amount could not be read
     * (needs_review). Money may be in the merchant's wallet, so never cancel: hold the
     * order and say why. Not final: the merchant can confirm it in the GriffNode
     * dashboard, which then completes the order on the next check.
     */
    private static function on_exception( array $event, string $kind ) {
        $order = self::find_order( $event );
        if ( ! $order || $order->has_status( [ 'processing', 'completed' ] ) ) return;
        if ( $order->get_meta( '_griffnode_exception' ) === $kind ) return; // already noted

        $order->update_meta_data( '_griffnode_exception', $kind );
        $order->save();
        $note = 'paid_late' === $kind
            /* translators: %s: GriffNode transaction ID. */
            ? __( 'GriffNode: coins arrived after the payment window. TX: %s. Check your wallet, then confirm or refund it in your GriffNode dashboard; a confirmation completes this order automatically.', 'griffnode-for-woocommerce' )
            /* translators: %s: GriffNode transaction ID. */
            : __( 'GriffNode: this payment needs your review. TX: %s. Check your wallet and decide in your GriffNode dashboard; a confirmation completes this order automatically.', 'griffnode-for-woocommerce' );
        self::hold( $order, sprintf( $note, $event['transaction_id'] ?? '—' ) );
    }

    /**
     * Put an order on hold. WooCommerce takes stock when an order goes on-hold; if that
     * stock is gone (the order was cancelled and the item sold since), skip taking it and
     * say so, rather than going below zero. Setting the order to Processing later takes it.
     */
    private static function hold( \WC_Order $order, string $note ): void {
        $short = self::out_of_stock( $order );
        if ( ! $short ) {
            $order->update_status( 'on-hold', $note );
            return;
        }
        $id   = $order->get_id();
        $skip = static function ( $reduce, $order_id ) use ( $id ) {
            return (int) $order_id === $id ? false : $reduce;
        };
        add_filter( 'woocommerce_payment_complete_reduce_order_stock', $skip, 10, 2 );
        $order->update_status( 'on-hold', $note . ' ' . sprintf(
            /* translators: %s: comma-separated product names. */
            __( 'Out of stock, not reserved: %s.', 'griffnode-for-woocommerce' ),
            implode( ', ', $short )
        ) );
        remove_filter( 'woocommerce_payment_complete_reduce_order_stock', $skip, 10 );
    }

    /**
     * Names of products in the order that taking stock now would push below zero. Empty when
     * this order already holds its stock or the store does not track stock.
     * ponytail: compares each line on its own; the same product on two lines is not summed.
     */
    private static function out_of_stock( \WC_Order $order ): array {
        if ( 'yes' !== get_option( 'woocommerce_manage_stock' ) || $order->get_data_store()->get_stock_reduced( $order->get_id() ) ) {
            return [];
        }
        $short = [];
        foreach ( $order->get_items() as $item ) {
            $product = $item->get_product();
            if ( $product && $product->managing_stock() && ! $product->backorders_allowed()
                && (float) $product->get_stock_quantity() < (float) $item->get_quantity() ) {
                $short[] = $product->get_name();
            }
        }
        return $short;
    }

    /** The transaction reached a final state: stop checking this order. */
    private static function mark_done( \WC_Order $order ): void {
        $order->update_meta_data( '_griffnode_done', 1 );
        $order->save();
    }

    // ── API status check (see GriffNode_Poller) ───────────────────────────────

    /**
     * Apply a transaction as GET /transactions returns it, through the same handlers a
     * webhook uses. Safe to call repeatedly: every handler is idempotent.
     */
    public static function apply_status( array $tx ): void {
        $event = [
            'transaction_id'  => $tx['transaction_id'] ?? '',
            'metadata'        => $tx['metadata'] ?? [],
            'currency_crypto' => $tx['crypto'] ?? '',
            'amount_crypto'   => $tx['amount_paid'] ?? ( $tx['amount_crypto'] ?? '' ),
        ];
        $exception = $tx['exception'] ?? '';
        switch ( $tx['status'] ?? '' ) {
            case 'completed':
            case 'overpaid':
                self::on_completed( $event );
                break;
            case 'partial':
                self::on_partial( $event );
                break;
            case 'expired':
                'paid_late' === $exception ? self::on_exception( $event, 'paid_late' ) : self::on_expired( $event );
                break;
            case 'failed':
                self::on_failed( $event );
                break;
            default: // pending / awaiting_confirmation
                if ( 'needs_review' === $exception ) {
                    self::on_exception( $event, 'needs_review' );
                }
        }
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Resolve the WooCommerce order from the webhook event.
     *
     * The API's basic /transactions/create endpoint (what this gateway uses) has no
     * top-level order_id field, so the backend never has one to echo back — top-level
     * event['order_id'] is effectively always absent for transactions this plugin
     * creates. The value that *does* survive the round trip is the metadata object we
     * sent at creation time (metadata.wc_order_id), so that's checked first. order_id is
     * still checked too, in case a future API version or a manually-created transaction
     * sets it directly.
     */
    private static function find_order( array $event ): ?\WC_Order {
        $order_id = $event['metadata']['wc_order_id'] ?? ( $event['order_id'] ?? null );

        if ( $order_id ) {
            $order = wc_get_order( (int) $order_id );
            if ( $order && self::txid_matches( $order, $event ) ) {
                return $order;
            }
        }

        // Fallback: search by the stored GriffNode txid.
        $txid = $event['transaction_id'] ?? '';
        if ( ! $txid ) return null;

        // Bounded fallback lookup by the stored GriffNode txid (single row).
        $orders = wc_get_orders( [
            'meta_key'   => '_griffnode_txid', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
            'meta_value' => $txid, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
            'limit'      => 1,
        ] );

        return $orders[0] ?? null;
    }

    /**
     * Defense in depth: once an order has a stored GriffNode transaction id, any webhook
     * claiming to be about that order must carry the same id. Guards against acting on
     * the wrong order if a lookup by wc_order_id/order_id is ever fed a mismatched event
     * (e.g. a backend bug, or metadata reused across transactions).
     */
    private static function txid_matches( \WC_Order $order, array $event ): bool {
        $stored = $order->get_meta( '_griffnode_txid' );
        $incoming = $event['transaction_id'] ?? null;
        if ( $stored && $incoming && $stored !== $incoming ) {
            return false;
        }
        return true;
    }

    private static function respond( int $status, string $message ): void {
        http_response_code( $status );
        echo esc_html( $message );
        exit;
    }
}
