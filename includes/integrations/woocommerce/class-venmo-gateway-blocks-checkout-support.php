<?php
/**
 * Make the Venmo payment gateway available to the WooCommerce Blocks checkout.
 *
 * Registers the gateway script and exposes settings data to the frontend.
 *
 * One is registered for each instance of the gateway, e.g. the original and duplicates created with
 * Duplicate Payment Gateways, each under its gateway's id.
 *
 * @package brianhenryie/juiced-venmo-gateway-pro
 */

namespace JuicedPlugins\Venmo_Gateway_Pro\Integrations\WooCommerce;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;
use Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry;

/**
 * Blocks checkout support for the Venmo payment gateway.
 */
class Venmo_Gateway_Blocks_Checkout_Support extends AbstractPaymentMethodType {

	/**
	 * The gateway instance.
	 *
	 * @var Venmo_Gateway
	 */
	protected $gateway;

	/**
	 * Payment method name. Must match WC_Payment_Gateway::$id.
	 *
	 * @see Venmo_Gateway_Blocks_Checkout_Support::__construct()
	 *
	 * @var string
	 */
	protected $name = 'venmo';

	/**
	 * Constructor.
	 *
	 * @param Venmo_Gateway $gateway The Venmo gateway instance, e.g. the original or a duplicate.
	 */
	public function __construct( Venmo_Gateway $gateway ) {
		$this->gateway = $gateway;
		$this->name    = $gateway->id;
	}

	/**
	 * Initializes the payment method type.
	 *
	 * @used-by IntegrationRegistry::initialize()
	 */
	public function initialize(): void {
		$this->settings = $this->gateway->settings;
	}

	/**
	 * Returns if this payment method should be active.
	 *
	 * @used-by PaymentMethodRegistry::get_all_active_registered()
	 */
	public function is_active(): bool {
		return $this->gateway->is_available();
	}

	/**
	 * Returns an array of script handles to be registered for this payment method.
	 *
	 * @used-by PaymentMethodRegistry::get_all_active_payment_method_script_dependencies()
	 *
	 * @return array<string>
	 */
	public function get_payment_method_script_handles(): array {

		$handle = 'juiced-venmo-gateway-pro-blocks-checkout';

		$script_asset_path = WP_PLUGIN_DIR . '/juiced-venmo-gateway-pro/build/checkout/index.asset.php';
		$script_asset      = file_exists( $script_asset_path )
			? require $script_asset_path
			: array(
				'dependencies' => array( 'wc-blocks-registry', 'wc-settings', 'wp-element', 'wp-html-entities', 'wp-i18n' ),
				'version'      => '1.0.0',
			);

		/** @var non-empty-string $script_url */
		$script_url = plugins_url( 'build/checkout/index.js', WP_PLUGIN_DIR . '/juiced-venmo-gateway-pro/juiced-venmo-gateway-pro.php' );

		wp_register_script(
			$handle,
			$script_url,
			$script_asset['dependencies'],
			$script_asset['version'],
			true
		);

		wp_set_script_translations( $handle, 'juiced-venmo-gateway-pro' );

		return array( $handle );
	}

	/**
	 * Returns data made available to the payment methods script via `getSetting( 'paymentMethodData' )[ name ]`.
	 *
	 * `is_venmo_gateway` identifies each instance of the gateway, since there may be more than one.
	 *
	 * @used-by PaymentMethodRegistry::get_all_registered_script_data()
	 *
	 * @return array{title:string, description:string, supports:array<string>, venmo_icon_url:string, saved_venmo_username:string, customer_venmo_username_field_name:string, is_venmo_gateway:true}
	 */
	public function get_payment_method_data(): array {
		// Get saved Venmo username for auto-fill.
		$customer_id          = get_current_user_id();
		$saved_venmo_username = $this->gateway->get_saved_venmo_username( $customer_id );

		return array(
			'title'                              => $this->get_setting( 'title' ),
			'description'                        => $this->get_setting( 'description' ),
			'supports'                           => array_filter( $this->gateway->supports, array( $this->gateway, 'supports' ) ),
			'venmo_icon_url'                     => $this->gateway->icon,
			'saved_venmo_username'               => $saved_venmo_username,
			// The payment data key, which `Venmo_Gateway::validate_fields()` reads from `$_POST`.
			'customer_venmo_username_field_name' => $this->gateway->get_customer_venmo_username_field_name(),
			'is_venmo_gateway'                   => true,
		);
	}
}
