<?php
/**
 * A second instance of the Venmo gateway, as Duplicate Payment Gateways creates, for testing multiple instances.
 *
 * Configured in `tests/_wp-env/initialize-internal.sh`, but only available to browsers with the
 * `venmo_second_instance` cookie, so it does not appear in other (parallel) tests.
 *
 * @see https://github.com/BrianHenryIE/bh-wc-duplicate-payment-gateways
 *
 * @package brianhenryie/juiced-venmo-gateway-pro
 */

declare(strict_types=1);

namespace JuicedPlugins\Venmo_Gateway_Pro\Development_Plugin\Gateways;

use JuicedPlugins\Venmo_Gateway_Pro\Integrations\WooCommerce\Venmo_Gateway;

/**
 * The Venmo gateway with another id, so it has its own settings.
 */
class Second_Venmo_Gateway extends Venmo_Gateway {

	const ID = 'venmo_second';

	const COOKIE_NAME = 'venmo_second_instance';

	/**
	 * The gateway id.
	 *
	 * @var string
	 */
	public $id = self::ID;

	/**
	 * Only when the test cookie is set.
	 *
	 * @see \WC_Payment_Gateways::get_available_payment_gateways()
	 */
	public function is_available(): bool {
		return isset( $_COOKIE[ self::COOKIE_NAME ] ) && parent::is_available();
	}
}
