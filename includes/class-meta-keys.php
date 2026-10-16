<?php
/**
 * The order and donation meta keys, shared by the WooCommerce and GiveWP integrations.
 *
 * @package brianhenryie/juiced-venmo-gateway-pro
 */

declare(strict_types=1);

namespace JuicedPlugins\Venmo_Gateway_Pro;

/**
 * Kept apart from the gateway classes, which extend WooCommerce's and GiveWP's own classes: reading a constant
 * autoloads its class, which is fatal when the parent plugin is inactive.
 *
 * @see Integrations\WooCommerce\Venmo_Gateway
 * @see Integrations\GiveWP\Venmo_Gateway
 */
class Meta_Keys {

	/**
	 * The customer's Venmo username, without a leading "@".
	 */
	const CUSTOMER_VENMO_USERNAME_META_KEY = '_customer_venmo_username';

	/**
	 * The store's Venmo username at the time of the order, i.e. where the customer was told to pay.
	 */
	const STORE_VENMO_USERNAME_META_KEY = '_destination_account_venmo_username';

	/**
	 * The customer's full name as shown on their public Venmo profile, fetched in the background after checkout.
	 * Venmo's payment emails name the payer, not their username, so this is what a payment email can be matched against.
	 */
	const CUSTOMER_VENMO_DISPLAY_NAME_META_KEY = '_customer_venmo_display_name';

	/**
	 * The Venmo transaction id, recorded when the payment is marked received.
	 */
	const VENMO_TRANSACTION_ID_META_KEY = '_venmo_transaction_id';

	/**
	 * The date of the Venmo payment, recorded when the payment is marked received.
	 */
	const VENMO_PAYMENT_DATE_META_KEY = '_venmo_payment_date';
}
