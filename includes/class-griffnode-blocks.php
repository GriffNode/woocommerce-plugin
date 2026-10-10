<?php
defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

/**
 * Makes GriffNode available in the block-based Checkout (the WooCommerce default for new
 * stores). The block sends the chosen coin as payment data, which WooCommerce hands to the
 * gateway's process_payment() exactly like the classic checkout's $_POST field.
 */
final class GriffNode_Blocks extends AbstractPaymentMethodType {

    protected $name = 'griffnode';

    public function initialize() {
        $this->settings = get_option( 'woocommerce_griffnode_settings', [] );
    }

    public function is_active() {
        return ! empty( $this->settings['enabled'] ) && 'yes' === $this->settings['enabled'];
    }

    public function get_payment_method_script_handles() {
        wp_register_script(
            'griffnode-blocks',
            GRIFFNODE_PLUGIN_URL . 'js/blocks.js',
            [ 'wc-blocks-registry', 'wc-settings', 'wp-element', 'wp-html-entities', 'wp-i18n' ],
            GRIFFNODE_VERSION,
            true
        );
        wp_set_script_translations( 'griffnode-blocks', 'griffnode-for-woocommerce' );
        return [ 'griffnode-blocks' ];
    }

    public function get_payment_method_data() {
        return [
            'title'       => $this->get_setting( 'title', 'Crypto (GriffNode)' ),
            'description' => $this->get_setting( 'description', '' ),
            'pk'          => $this->get_setting( 'publishable_key', '' ),
            'api'         => esc_url_raw( GRIFFNODE_API_BASE ),
        ];
    }
}
