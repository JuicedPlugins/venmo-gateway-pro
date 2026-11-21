<?php
/**
 * Generate URLs/HREFs releveant to the order's payment username.
 *
 * @package brianhenryie/juiced-venmo-gateway-pro
 */

declare(strict_types=1);

namespace JuicedPlugins\Venmo_Gateway_Pro\Integrations\WooCommerce;

use JuicedPlugins\Venmo_Gateway_Pro\Venmo_Username;
use WC_Order;

/**
 * Mostly just some `sprintf()` functions
 */
class Venmo_Payment_Url {

	/**
	 * The payment address for the order.
	 *
	 * @var string
	 */
	protected string $store_venmo_username;

	/**
	 * Given an order, provide functions for URLs relevant to its payments.
	 *
	 * @param WC_Order $order The order, presuming a STORE_VENMO_USERNAME_META_KEY.
	 */
	public function __construct(
		protected WC_Order $order
	) {
		$this->store_venmo_username = Venmo_Username::sanitize( $order->get_meta( Venmo_Gateway::STORE_VENMO_USERNAME_META_KEY ) );
	}

	/**
	 * Get the QR code URL – starts with `venmo://` for iOS/Android.
	 *
	 * Template: `venmo://paycharge?txn=pay&recipients=~MYUSERNAME~&note=~PRE-FILLEDCOMMENT~&amount=~PREFILLEDAMOUNT~`.
	 */
	public function get_qr_url(): string {

		$venmo_payment_url = sprintf(
			'venmo://paycharge?txn=pay&recipients=%s&note=%s&amount=%s',
			$this->store_venmo_username,
			rawurlencode( 'order ' . $this->order->get_id() ),
			rawurlencode( $this->get_amount() ),
		);

		return $venmo_payment_url;
	}

	/**
	 * Get the browser payment URL.
	 *
	 * Template: `https://venmo.com/{username}?txn=pay&amount={amount}&note={note}`.
	 */
	public function get_browser_url(): string {

		$venmo_payment_url = sprintf(
			'https://venmo.com/%s?txn=pay&amount=%s&note=%s',
			rawurlencode( $this->store_venmo_username ),
			rawurlencode( $this->get_amount() ),
			rawurlencode( 'order ' . $this->order->get_id() )
		);

		return $venmo_payment_url;
	}

	/**
	 * Get the browser payment URL for printing. Returns a string split with `<span>` and zero-width spaces (`&#8203;`) for line-breaks.
	 *
	 * Uses the numeric entity because `wp_kses()` escapes unknown named entities like `&ZeroWidthSpace;`.
	 */
	public function get_html(): string {

		$venmo_payment_url_display = sprintf(
			'<span>venmo.com/</span>&#8203;<span>%s?</span>&#8203;<span>txn=pay&amp;</span>&#8203;<span>amount=%s&amp;</span>&#8203;<span>note=%s</span>',
			rawurlencode( $this->store_venmo_username ),
			rawurlencode( $this->get_amount() ),
			rawurlencode( 'order ' . $this->order->get_id() )
		);

		return $venmo_payment_url_display;
	}

	/**
	 * Get the order total as a string with the store's number of decimal places, e.g. `18.00`.
	 *
	 * `WC_Order::get_total()` can return an int, float or string.
	 */
	protected function get_amount(): string {
		return wc_format_decimal( $this->order->get_total(), wc_get_price_decimals() );
	}
}
