<?php
/**
 * Plugin Name: GriffNode Crypto Payments for WooCommerce
 * Plugin URI:  https://docs.griffnode.com/
 * Description: Accept Bitcoin, Ethereum, Litecoin, Dogecoin, Dash and USDT/USDC/DAI stablecoins in WooCommerce. Non-custodial - funds settle straight to your own wallet. No chargebacks.
 * Version:     1.0.3
 * Author:      GriffNode
 * Author URI:  https://griffnode.com
 * License:     GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: griffnode-for-woocommerce
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * WC requires at least: 7.0
 * WC tested up to: 8.9
 */

defined( 'ABSPATH' ) || exit;

define( 'GRIFFNODE_VERSION',    '1.0.3' );
define( 'GRIFFNODE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'GRIFFNODE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'GRIFFNODE_API_BASE',   'https://api.griffnode.com' );

// Declare HPOS compatibility.
add_action( 'before_woocommerce_init', function () {
    if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
    }
} );

add_action( 'plugins_loaded', function () {
    if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
        add_action( 'admin_notices', function () {
            echo '<div class="error"><p><strong>GriffNode:</strong> ' . esc_html__( 'WooCommerce must be active.', 'griffnode-for-woocommerce' ) . '</p></div>';
        } );
        return;
    }

    require_once GRIFFNODE_PLUGIN_DIR . 'includes/class-griffnode-gateway.php';
    require_once GRIFFNODE_PLUGIN_DIR . 'includes/class-griffnode-webhook.php';

    // Register the payment gateway.
    add_filter( 'woocommerce_payment_gateways', function ( $gateways ) {
        $gateways[] = 'GriffNode_Gateway';
        return $gateways;
    } );

    // Block-based Checkout support (the default checkout for new WooCommerce stores).
    add_action( 'woocommerce_blocks_payment_method_type_registration', function ( $registry ) {
        require_once GRIFFNODE_PLUGIN_DIR . 'includes/class-griffnode-blocks.php';
        $registry->register( new GriffNode_Blocks() );
    } );

    // Register the webhook listener on wc-api=griffnode.
    add_action( 'woocommerce_api_griffnode', [ 'GriffNode_Webhook', 'handle' ] );

    // No webhook (e.g. Starter plan): check the API when the customer lands back on the
    // thank-you page, and every 5 minutes for recent pending orders.
    add_action( 'woocommerce_thankyou', function ( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( $order && 'griffnode' === $order->get_payment_method() ) {
            GriffNode_Webhook::sync_order( $order );
        }
    } );
    add_action( 'griffnode_sync_pending', [ 'GriffNode_Webhook', 'sync_pending' ] );
    if ( ! wp_next_scheduled( 'griffnode_sync_pending' ) ) {
        wp_schedule_event( time() + 300, 'griffnode_five_minutes', 'griffnode_sync_pending' );
    }
} );

add_filter( 'cron_schedules', function ( $schedules ) {
    $schedules['griffnode_five_minutes'] = [ 'interval' => 300, 'display' => 'Every 5 minutes (GriffNode)' ];
    return $schedules;
} );

register_deactivation_hook( __FILE__, function () {
    wp_clear_scheduled_hook( 'griffnode_sync_pending' );
} );
