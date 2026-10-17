<?php
defined( 'ABSPATH' ) || exit;

/**
 * Keeps orders in sync with GriffNode without a webhook (free Starter plan), and backs up
 * a webhook on paid plans.
 *
 * While any GriffNode transaction is open, the store checks all of them every 30 seconds
 * in ONE request (GET /transactions/list?ids=...). It does not depend on visitors: each run
 * calls the store itself (a loopback request), waits 30s and starts the next run. With
 * nothing open, the loop stops and nothing is polled.
 *
 * If the loop dies (host killed the request, server restart), it is restarted within ~90s
 * by the next order, thank-you page, page visit or WP-cron run (see kick()).
 *
 * "Open" = an order paid with GriffNode, created in the last 7 days, whose transaction has
 * not reached a final state (_griffnode_done). That covers slow confirmations, orders
 * WooCommerce auto-cancelled (Hold stock) before the coins confirmed, partial payments
 * topped up later, and late payments the merchant confirms in the GriffNode dashboard.
 */
class GriffNode_Poller {

    const INTERVAL = 30;                  // seconds between checks while something is open
    const LOCK     = 'griffnode_poll_lock';
    const BEAT     = 'griffnode_poll_beat';  // time of the last run
    const OPEN     = 'griffnode_poll_open';  // 1 while open transactions exist
    const KEY      = 'griffnode_poll_key';   // secret for the loopback URL
    const ERROR    = 'griffnode_api_error';  // last API failure, shown to the admin

    public static function init() {
        add_action( 'woocommerce_api_griffnode_poll', [ __CLASS__, 'handle_loop' ] );
        add_action( 'init', [ __CLASS__, 'kick' ] );
        add_action( 'griffnode_poll_kick', [ __CLASS__, 'kick' ] );
        add_action( 'admin_notices', [ __CLASS__, 'admin_notice' ] );

        // The customer came back: check that order right away, then make sure the loop runs.
        add_action( 'woocommerce_thankyou', function ( $order_id ) {
            $order = wc_get_order( $order_id );
            if ( $order && 'griffnode' === $order->get_payment_method() ) {
                self::poll( [ $order ] );
                self::kick();
            }
        } );

        // Real cron (DISABLE_WP_CRON + system cron) revives the loop even with zero traffic.
        if ( ! wp_next_scheduled( 'griffnode_poll_kick' ) ) {
            wp_schedule_event( time() + 60, 'griffnode_minute', 'griffnode_poll_kick' );
        }
    }

    /** A new GriffNode transaction exists: start polling. */
    public static function opened() {
        update_option( self::OPEN, 1, true );
        update_option( self::BEAT, 0, true ); // force a fresh start
        self::kick();
    }

    /** Start the loop if transactions are open and no run happened recently. */
    public static function kick() {
        if ( ! get_option( self::OPEN ) || get_transient( self::LOCK ) ) return;
        if ( time() - (int) get_option( self::BEAT, 0 ) < 3 * self::INTERVAL ) return;
        // A start is already on its way (loopback not running yet): don't start a second loop.
        if ( get_transient( 'griffnode_poll_spawned' ) ) return;
        set_transient( 'griffnode_poll_spawned', 1, 15 );
        self::spawn();
    }

    /** Loopback entry point: ?wc-api=griffnode_poll&k=<key>. */
    public static function handle_loop() {
        $k = isset( $_GET['k'] ) ? sanitize_text_field( wp_unslash( $_GET['k'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( ! hash_equals( self::key(), $k ) ) {
            status_header( 403 );
            exit;
        }
        ignore_user_abort( true );
        if ( get_transient( self::LOCK ) ) exit; // another run is active
        set_transient( self::LOCK, 1, 2 * self::INTERVAL );
        update_option( self::BEAT, time(), true );

        $open = self::poll();
        if ( $open ) {
            // ponytail: one PHP worker sleeps between checks while payments are open.
            // Fine at shop scale; a host with a hard request timeout under ~40s breaks
            // the chain, and kick() restarts it on the next visit or cron run.
            if ( function_exists( 'set_time_limit' ) ) {
                @set_time_limit( 3 * self::INTERVAL ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
            }
            sleep( self::INTERVAL );
            delete_transient( self::LOCK );
            self::spawn();
        } else {
            delete_transient( self::LOCK );
        }
        exit;
    }

    /**
     * Check open orders (or just $orders) in bulk. Returns how many are still open.
     *
     * @param WC_Order[]|null $orders
     */
    public static function poll( $orders = null ): int {
        if ( null === $orders ) {
            $orders = wc_get_orders( [
                'payment_method' => 'griffnode',
                'status'         => [ 'pending', 'on-hold', 'cancelled', 'failed' ],
                'date_created'   => '>' . ( time() - 7 * DAY_IN_SECONDS ),
                'meta_query'     => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
                    [ 'key' => '_griffnode_done', 'compare' => 'NOT EXISTS' ],
                    [ 'key' => '_griffnode_txid', 'compare' => 'EXISTS' ],
                ],
                'limit'          => 300,
            ] );
        }
        $ids = [];
        foreach ( $orders as $order ) {
            $txid = $order->get_meta( '_griffnode_txid' );
            if ( $txid && ! $order->get_meta( '_griffnode_done' ) ) {
                $ids[] = $txid;
            }
        }
        if ( ! $ids ) {
            update_option( self::OPEN, 0, true );
            return 0;
        }
        update_option( self::OPEN, 1, true );

        $gateway = new GriffNode_Gateway();
        foreach ( array_chunk( $ids, 100 ) as $chunk ) {
            $response = wp_remote_get(
                GRIFFNODE_API_BASE . '/transactions/list?ids=' . rawurlencode( implode( ',', $chunk ) ),
                [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $gateway->get_option( 'secret_key' ),
                        'Accept'        => 'application/json',
                    ],
                    'timeout' => 15,
                ]
            );
            $code = is_wp_error( $response ) ? 0 : wp_remote_retrieve_response_code( $response );
            $body = $code ? json_decode( wp_remote_retrieve_body( $response ), true ) : null;
            if ( 200 !== $code || ! isset( $body['data']['transactions'] ) ) {
                $msg = is_wp_error( $response ) ? $response->get_error_message() : ( $body['message'] ?? $body['error'] ?? 'HTTP ' . $code );
                update_option( self::ERROR, [ 'time' => time(), 'message' => (string) $msg ], false );
                continue;
            }
            delete_option( self::ERROR );
            foreach ( $body['data']['transactions'] as $tx ) {
                GriffNode_Webhook::apply_status( $tx );
            }
        }
        return count( $ids );
    }

    private static function spawn() {
        wp_remote_get( add_query_arg( [ 'wc-api' => 'griffnode_poll', 'k' => self::key() ], home_url( '/' ) ), [
            'blocking'  => false,
            'timeout'   => 0.01,
            'sslverify' => apply_filters( 'https_local_ssl_verify', false ), // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core filter
        ] );
    }

    private static function key(): string {
        $key = get_option( self::KEY );
        if ( ! $key ) {
            $key = wp_generate_password( 32, false );
            update_option( self::KEY, $key, true );
        }
        return $key;
    }

    /** API checks failing (wrong key, network): the merchant must know, orders are not updating. */
    public static function admin_notice() {
        $err = get_option( self::ERROR );
        if ( ! $err || time() - $err['time'] > DAY_IN_SECONDS || ! current_user_can( 'manage_woocommerce' ) ) return;
        printf(
            '<div class="notice notice-error"><p><strong>GriffNode:</strong> %s</p></div>',
            esc_html( sprintf(
                /* translators: %s: error message from the GriffNode API. */
                __( 'Payment status checks are failing, so orders are not updating: %s. Check the Secret Key under WooCommerce → Settings → Payments → GriffNode.', 'griffnode-for-woocommerce' ),
                $err['message']
            ) )
        );
    }
}
