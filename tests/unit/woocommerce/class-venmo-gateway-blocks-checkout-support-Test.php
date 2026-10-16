<?php
/**
 * Tests for Venmo Gateway Blocks Checkout Support.
 *
 * @package brianhenryie/juiced-venmo-gateway-pro
 */

namespace JuicedPlugins\Venmo_Gateway_Pro\Integrations\WooCommerce;

use JuicedPlugins\Venmo_Gateway_Pro\Unit_Testcase;
use WP_Mock;

/**
 * @coversDefaultClass \JuicedPlugins\Venmo_Gateway_Pro\Integrations\WooCommerce\Venmo_Gateway_Blocks_Checkout_Support
 */
class Venmo_Gateway_Blocks_Checkout_Support_Test extends Unit_TestCase {

	/**
	 * Test that get_payment_method_data includes saved_venmo_username key.
	 *
	 * @covers ::get_payment_method_data
	 */
	public function test_get_payment_method_data_includes_saved_username_key(): void {
		$gateway           = $this->createMock( Venmo_Gateway::class );
		$gateway->supports = array( 'products' );
		$gateway->icon     = 'test-icon-url';
		$gateway->method( 'supports' )->willReturn( true );
		$gateway->method( 'get_saved_venmo_username' )
			->with( 42 )
			->willReturn( 'saved-username' );

		WP_Mock::userFunction( 'get_current_user_id' )->andReturn( 42 );

		$gateway->settings = array(
			'title'       => 'Venmo',
			'description' => 'Pay with Venmo',
		);

		$support = new Venmo_Gateway_Blocks_Checkout_Support( $gateway );

		$data = $support->get_payment_method_data();

		$this->assertArrayHasKey( 'saved_venmo_username', $data );
		$this->assertEquals( 'saved-username', $data['saved_venmo_username'] );
		$this->assertEquals( 'test-icon-url', $data['venmo_icon_url'] );
	}

	/**
	 * Each gateway instance, e.g. a duplicate created with Duplicate Payment Gateways, is registered under its own id.
	 *
	 * @covers ::__construct
	 * @covers ::get_name
	 */
	public function test_name_is_gateway_id(): void {
		$gateway     = $this->createMock( Venmo_Gateway::class );
		$gateway->id = 'venmo_2';

		$support = new Venmo_Gateway_Blocks_Checkout_Support( $gateway );

		$this->assertEquals( 'venmo_2', $support->get_name() );
	}

	/**
	 * The checkout script registers each entry with `is_venmo_gateway` in `paymentMethodData`.
	 *
	 * @covers ::get_payment_method_data
	 */
	public function test_get_payment_method_data_identifies_venmo_gateway(): void {
		$gateway           = $this->createMock( Venmo_Gateway::class );
		$gateway->supports = array( 'products' );
		$gateway->settings = array();
		$gateway->method( 'supports' )->willReturn( true );
		$gateway->method( 'get_saved_venmo_username' )->willReturn( '' );

		WP_Mock::userFunction( 'get_current_user_id' )->andReturn( 0 );

		$support = new Venmo_Gateway_Blocks_Checkout_Support( $gateway );

		$data = $support->get_payment_method_data();

		$this->assertArrayHasKey( 'is_venmo_gateway', $data );
	}
}
