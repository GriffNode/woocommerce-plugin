<?php
/**
 * Plugin Name: GriffNode for WooCommerce
 * Plugin URI:  https://griffnode.com
 * Description: Accept Bitcoin, Ethereum, Litecoin, Dogecoin, Dash and USDT/USDC/DAI stablecoins in WooCommerce. Non-custodial - funds settle straight to your own wallet. No KYC, no chargebacks.
 * Version:     1.0.0
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

define( 'GRIFFNODE_VERSION',    '1.0.0' );
define( 'GRIFFNODE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'GRIFFNODE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'GRIFFNODE_API_BASE',   'https://api.griffnode.com' );

// Declare HPOS compatibility.
add_action( 'before_woocommerce_init', function () {
    if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
    }
} );

add_action( 'plugins_loaded', function () {
    if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
        add_action( 'admin_notices', function () {
            echo '<div class="error"><p><strong>GriffNode:</strong> WooCommerce must be active.</p></div>';
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

    // Register the webhook listener on wc-api=griffnode.
    add_action( 'woocommerce_api_griffnode', [ 'GriffNode_Webhook', 'handle' ] );
} );
