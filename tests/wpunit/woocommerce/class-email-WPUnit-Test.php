<?php
/**
 * The SVG image was not displaying in Gmail.
 *
 * @see https://www.codegenes.net/blog/is-there-a-trick-to-display-svg-images-in-gmail/
 *
 * @package brianhenryie/juiced-venmo-gateway-pro
 */

declare(strict_types=1);

namespace JuicedPlugins\Venmo_Gateway_Pro\Integrations\WooCommerce;

use JuicedPlugins\Venmo_Gateway_Pro\WPUnit_Testcase;
use WC_Order;
use WC_Payment_Gateways;

/**
 * @coversDefaultClass \JuicedPlugins\Venmo_Gateway_Pro\Integrations\WooCommerce\Email
 * @requires function WC
 */
class Email_WPUnit_Test extends WPUnit_Testcase {

	/**
	 * @covers ::email_instructions
	 */
	public function test_email_instructions(): void {

		$gateway = new Venmo_Gateway();
		WC_Payment_Gateways::instance()->payment_gateways[] = $gateway;

		$order = new WC_Order();
		$order->set_status( 'on-hold' );
		$order->set_payment_method( $gateway->id );
		$order->add_meta_data( Venmo_Gateway::STORE_VENMO_USERNAME_META_KEY, 'brianhenryie', true );
		$order->save();

		$sut = new Email();

		ob_start();

		$sut->email_instructions( $order, false );

		$result = ob_get_flush();
		$this->assertIsString( $result );

		$this->assertStringNotContainsString( 'data:image/svg+xml;base64', $result );
		$this->assertStringContainsString( 'data:image/png;base64', $result );
	}

	/**
	 * Generate the full customer on-hold email and check the order total is not rendered as escaped HTML.
	 *
	 * @covers ::email_instructions
	 */
	public function test_on_hold_email_total_is_not_escaped_html(): void {

		$gateway = new Venmo_Gateway();
		WC_Payment_Gateways::instance()->payment_gateways[] = $gateway;

		$order = new WC_Order();
		$order->set_status( 'on-hold' );
		$order->set_payment_method( $gateway->id );
		$order->set_total( '18.00' );
		$order->add_meta_data( Venmo_Gateway::STORE_VENMO_USERNAME_META_KEY, 'brianhenryie', true );
		$order->save();

		add_action( 'woocommerce_email_before_order_table', array( new Email(), 'email_instructions' ), 10, 2 );

		// WC_Emails is a singleton that adds this in its constructor, but hooks are reset between tests.
		$mailer = WC()->mailer();
		if ( ! has_action( 'woocommerce_email_order_details', array( $mailer, 'order_details' ) ) ) {
			add_action( 'woocommerce_email_order_details', array( $mailer, 'order_details' ), 10, 4 );
		}

		$emails = $mailer->get_emails();
		$email  = $emails['WC_Email_Customer_On_Hold_Order'];

		$email->object = $order;

		$result = $email->get_content_html();

		$this->assertStringNotContainsString( '&lt;span', $result );
		$this->assertStringContainsString( 'Please send payment of $18.00 via Venmo', $result );
		$this->assertStringContainsString( 'Please pay the precise amount – <b>$18.00</b>', $result );
	}
}
