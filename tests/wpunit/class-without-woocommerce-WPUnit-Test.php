<?php
/**
 * Code that runs outside WooCommerce's own hooks must not need WooCommerce: GiveWP-only sites, and WooCommerce
 * deactivated while a background task is still scheduled.
 *
 * Run by the `wpunit-no-woocommerce` suite; skipped when WooCommerce is loaded.
 *
 * @package brianhenryie/juiced-venmo-gateway-pro
 */

declare(strict_types=1);

namespace JuicedPlugins\Venmo_Gateway_Pro;

use BrianHenryIE\ColorLogger\ColorLogger;
use JuicedPlugins\Venmo_Gateway_Pro\API\API_Interface;
use JuicedPlugins\Venmo_Gateway_Pro\API\Settings;
use JuicedPlugins\Venmo_Gateway_Pro\Integrations\WooCommerce\Order;
use JuicedPlugins\Venmo_Gateway_Pro\Psr\Log\LoggerInterface;
use Mockery;

class Without_WooCommerce_WPUnit_Test extends WPUnit_Testcase {

	protected function setUp(): void {
		parent::setUp();

		if ( class_exists( 'WooCommerce' ) ) {
			$this->markTestSkipped( 'Run with the wpunit-no-woocommerce suite.' );
		}
	}

	protected function tearDown(): void {
		Mockery::close();
		parent::tearDown();
	}

	/**
	 * The reconcile library asks for the meta key when listing GiveWP donations, e.g. on the Unreconciled Orders page.
	 *
	 * @covers \JuicedPlugins\Venmo_Gateway_Pro\API\Settings::get_customer_payment_id_meta_key
	 */
	public function test_customer_payment_id_meta_key(): void {
		$this->assertSame( '_customer_venmo_username', ( new Settings() )->get_customer_payment_id_meta_key() );
	}

	/**
	 * @covers \JuicedPlugins\Venmo_Gateway_Pro\Integrations\WooCommerce\Order::fetch_customer_venmo_profile
	 */
	public function test_scheduled_customer_venmo_profile_fetch(): void {
		$api = Mockery::mock( API_Interface::class );
		$api->shouldNotReceive( 'get_venmo_profile' );

		/** @var ColorLogger&LoggerInterface $logger */
		$logger = $this->logger;

		/** @var API_Interface $api */
		$order = new Order( $api, new Settings(), $logger );

		$order->fetch_customer_venmo_profile( 123 );

		$this->assertTrue( $logger->hasWarningThatContains( 'WooCommerce is inactive' ) );
	}
}
