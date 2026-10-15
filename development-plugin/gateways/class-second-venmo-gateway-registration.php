<?php
/**
 * Add a second instance of the Venmo gateway to WooCommerce.
 *
 * @package brianhenryie/juiced-venmo-gateway-pro
 */

declare(strict_types=1);

namespace JuicedPlugins\Venmo_Gateway_Pro\Development_Plugin\Gateways;

/**
 * Hooks `woocommerce_payment_gateways`.
 */
class Second_Venmo_Gateway_Registration {

	/**
	 * Add the filter.
	 */
	public function register_hooks(): void {
		add_filter( 'woocommerce_payment_gateways', array( $this, 'add_gateway' ) );
	}

	/**
	 * Add the second Venmo gateway instance.
	 *
	 * @hooked woocommerce_payment_gateways
	 * @see \WC_Payment_Gateways::init()
	 *
	 * @param array<string|\WC_Payment_Gateway> $gateways Gateway class names or instances.
	 * @return array<string|\WC_Payment_Gateway>
	 */
	public function add_gateway( array $gateways ): array {
		$gateways[] = Second_Venmo_Gateway::class;
		return $gateways;
	}
}
