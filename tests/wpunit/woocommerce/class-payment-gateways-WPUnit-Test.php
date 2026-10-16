<?php
/**
 * @package brianhenryie/juiced-venmo-gateway-pro
 */

namespace JuicedPlugins\Venmo_Gateway_Pro\Integrations\WooCommerce;

use Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry;
use JuicedPlugins\Venmo_Gateway_Pro\WPUnit_Testcase;
use WC_Payment_Gateways;

/**
 * @coversDefaultClass \JuicedPlugins\Venmo_Gateway_Pro\Integrations\WooCommerce\Payment_Gateways
 * @requires function WC
 */
class Payment_Gateways_WPUnit_Test extends WPUnit_Testcase {

	/**
	 * A second instance of the gateway, as Duplicate Payment Gateways creates, gets its own blocks checkout integration.
	 *
	 * @covers ::register_blocks_checkout_support
	 */
	public function test_register_blocks_checkout_support_for_each_instance(): void {

		$second_instance = new class() extends Venmo_Gateway {
			/**
			 * The duplicate's id.
			 *
			 * @var string
			 */
			public $id = 'venmo_2';
		};

		add_filter(
			'woocommerce_payment_gateways',
			function ( array $gateways ) use ( $second_instance ): array {
				$gateways[] = Venmo_Gateway::class;
				$gateways[] = $second_instance;
				return $gateways;
			}
		);

		WC_Payment_Gateways::instance()->payment_gateways = array();
		WC_Payment_Gateways::instance()->init();

		$registry = new PaymentMethodRegistry();

		$sut = new Payment_Gateways();
		$sut->register_blocks_checkout_support( $registry );

		$this->assertTrue( $registry->is_registered( 'venmo' ) );
		$this->assertTrue( $registry->is_registered( 'venmo_2' ) );
		$registered = $registry->get_registered( 'venmo_2' );
		$this->assertNotNull( $registered );
		$this->assertEquals( 'venmo_2', $registered->get_name() );
	}

	/**
	 * E.g. Duplicate Payment Gateways may have registered it already.
	 *
	 * @covers ::register_blocks_checkout_support
	 */
	public function test_register_blocks_checkout_support_skips_registered(): void {

		WC_Payment_Gateways::instance()->payment_gateways = array();
		WC_Payment_Gateways::instance()->init();

		$registry = new PaymentMethodRegistry();
		$existing = new Venmo_Gateway_Blocks_Checkout_Support( new Venmo_Gateway() );
		$registry->register( $existing );

		$sut = new Payment_Gateways();
		$sut->register_blocks_checkout_support( $registry );

		$this->assertSame( $existing, $registry->get_registered( 'venmo' ) );
	}
}
