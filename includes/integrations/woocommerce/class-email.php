<?php
/**
 * Add instructions to the customer on-hold email.
 *
 * Inline images do not display in gmail. Potential fix at:
 *
 * @see https://gist.github.com/thomasfw/5df1a041fd8f9c939ef9d88d887ce023
 * @see https://stackoverflow.com/questions/9110091/base64-encoded-images-in-email-signatures/9110164#9110164
 *
 * @package brianhenryie/juiced-venmo-gateway-pro
 */

declare(strict_types=1);

namespace JuicedPlugins\Venmo_Gateway_Pro\Integrations\WooCommerce;

use JuicedPlugins\Venmo_Gateway_Pro\Includes\Register_Hooks;
use JuicedPlugins\Venmo_Gateway_Pro\chillerlan\QRCode\Output\QROutputInterface;
use JuicedPlugins\Venmo_Gateway_Pro\QR\QR_Code;
use WC_Order;
use WC_Payment_Gateways;

/**
 * @see wp-content/plugins/woocommerce/templates/emails/email-order-details.php
 * @see wp-content/plugins/woocommerce/templates/emails/plain/email-order-details.php
 */
class Email {

	// TODO: Delay the email so it only sends if they do not pay immediately.

	/**
	 * Adds instructions to the order confirmation emails.
	 *
	 * This runs for every order, on every email (received, complete, etc.).
	 *
	 * @hooked woocommerce_email_before_order_table
	 * @see Register_Hooks::define_woocommerce_hooks()
	 *
	 * @param WC_Order $order The order the email is for.
	 * @param bool     $sent_to_admin Is the email being sent to the store admin (rather than the customer).
	 * @param bool     $plain_text Is the email plain text (rather than HTML).
	 */
	public function email_instructions( WC_Order $order, bool $sent_to_admin, bool $plain_text = false ): void {

		$payment_gateways = WC_Payment_Gateways::instance()->payment_gateways();

		if ( ! isset( $payment_gateways[ $order->get_payment_method() ] ) ) {
			return;
		}

		$payment_gateway_instance = $payment_gateways[ $order->get_payment_method() ];

		if ( ! ( $payment_gateway_instance instanceof Venmo_Gateway ) ) {
			return;
		}

		$store_venmo_username    = $order->get_meta( Venmo_Gateway::STORE_VENMO_USERNAME_META_KEY );
		$customer_venmo_username = $order->get_meta( Venmo_Gateway::CUSTOMER_VENMO_USERNAME_META_KEY );

		if ( empty( $store_venmo_username ) ) {
			return;
		}

		if ( $sent_to_admin || ! $order->has_status( 'on-hold' ) ) {
			return;
		}

		// Your order has been received.

		$payment_url_helper = new Venmo_Payment_Url( $order );
		$order_total        = $this->get_plain_text_order_total( $order );

		if ( $plain_text ) {
			$this->print_plain_text_instructions( $order, $payment_url_helper, $order_total, $store_venmo_username, $customer_venmo_username );
			return;
		}

		$this->print_html_instructions( $order, $payment_url_helper, $order_total, $store_venmo_username, $customer_venmo_username );
	}

	/**
	 * Print the payment instructions for plain text emails: no markup, no images, and the payment link as a bare URL.
	 *
	 * @param WC_Order          $order The order the email is for.
	 * @param Venmo_Payment_Url $payment_url_helper Provides the order's payment URL.
	 * @param string            $order_total The order total with currency symbol, without HTML.
	 * @param string            $store_venmo_username The Venmo username the customer should pay.
	 * @param string            $customer_venmo_username The customer's Venmo username, or empty if unknown.
	 */
	protected function print_plain_text_instructions(
		WC_Order $order,
		Venmo_Payment_Url $payment_url_helper,
		string $order_total,
		string $store_venmo_username,
		string $customer_venmo_username
	): void {

		// Show from/to usernames if customer username is available.
		if ( ! empty( $customer_venmo_username ) ) {
			printf(
				'Payment from @%s to @%s' . PHP_EOL . PHP_EOL,
				esc_html( wp_strip_all_tags( $customer_venmo_username ) ),
				esc_html( wp_strip_all_tags( $store_venmo_username ) )
			);
		}

		printf(
			'Please send payment of %s via Venmo to @%s' . PHP_EOL . PHP_EOL,
			esc_html( $order_total ),
			esc_html( wp_strip_all_tags( $store_venmo_username ) )
		);

		printf(
			'Please pay the precise amount – %s and include the order number – %d in the note.' . PHP_EOL . PHP_EOL,
			esc_html( $order_total ),
			absint( $order->get_id() )
		);

		printf(
			'Open Venmo: %s' . PHP_EOL . PHP_EOL,
			esc_url_raw( $payment_url_helper->get_browser_url() )
		);
	}

	/**
	 * Print the payment instructions for HTML emails, with the Venmo logo and payment QR code.
	 *
	 * @param WC_Order          $order The order the email is for.
	 * @param Venmo_Payment_Url $payment_url_helper Provides the order's payment URLs.
	 * @param string            $order_total The order total with currency symbol, without HTML.
	 * @param string            $store_venmo_username The Venmo username the customer should pay.
	 * @param string            $customer_venmo_username The customer's Venmo username, or empty if unknown.
	 */
	protected function print_html_instructions(
		WC_Order $order,
		Venmo_Payment_Url $payment_url_helper,
		string $order_total,
		string $store_venmo_username,
		string $customer_venmo_username
	): void {

		$venmo_payment_url    = $payment_url_helper->get_browser_url();
		$venmo_payment_qr_url = $payment_url_helper->get_qr_url();

		$venmo_image_url     = plugins_url( 'assets/woocommerce/images/venmo-logo-25.png', 'juiced-venmo-gateway-pro/juiced-venmo-gateway-pro.php' );
		$qr_code_data_base64 = ( new QR_Code() )->get_data_uri( $venmo_payment_qr_url, QROutputInterface::GDIMAGE_PNG );

		// Show from/to usernames if customer username is available.
		if ( ! empty( $customer_venmo_username ) ) {
			printf(
				'<p><strong>Payment from @%s to @%s</strong></p>' . PHP_EOL,
				esc_html( $customer_venmo_username ),
				esc_html( $store_venmo_username )
			);
		}

		printf(
			'<p>Please send payment of %s via Venmo to <a href="%s">@%s</a></p>' . PHP_EOL . PHP_EOL,
			esc_html( $order_total ),
			esc_url_raw( $venmo_payment_url ),
			esc_html( $store_venmo_username )
		);

		printf(
			'<p>Please pay the precise amount – <b>%s</b> and include the order number – <b>%d</b> in the note.</p>' . PHP_EOL . PHP_EOL,
			esc_html( $order_total ),
			absint( $order->get_id() )
		);

		// Venmo logo image.
		printf(
			'<p><a href="%s"><img src="%s" /></a></p>' . PHP_EOL . PHP_EOL,
			esc_url_raw( $venmo_payment_url ),
			esc_url_raw( $venmo_image_url )
		);

		// QR Code.
		printf(
			'<p><a href="%s"><img style="display:block; max-width: 90vw; max-height: 500px;" src="%s" alt="Payment QR code" /></a></p>' . PHP_EOL . PHP_EOL,
			esc_url_raw( $venmo_payment_qr_url, array( 'venmo' ) ),
			esc_url_raw( $qr_code_data_base64, array( 'data' ) )
		);

		printf(
			'<p><a href="%s">Open Venmo</a></p>' . PHP_EOL . PHP_EOL,
			esc_url_raw( $venmo_payment_url )
		);
	}

	/**
	 * Get the order total formatted with the currency symbol, without the HTML markup `wc_price()` adds.
	 *
	 * E.g. `$18.00` rather than `<span class="woocommerce-Price-amount amount"><bdi>...`.
	 *
	 * @param WC_Order $order The order whose total to format.
	 */
	protected function get_plain_text_order_total( WC_Order $order ): string {
		return html_entity_decode(
			wp_strip_all_tags(
				wc_price(
					(float) $order->get_total(),
					array( 'currency' => $order->get_currency() )
				)
			),
			ENT_QUOTES | ENT_HTML5,
			'UTF-8'
		);
	}
}
