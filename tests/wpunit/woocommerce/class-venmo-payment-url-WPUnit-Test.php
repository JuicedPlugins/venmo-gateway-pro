<?php
/**
 * Tests for the URLs used to pay for an order with Venmo.
 *
 * @package brianhenryie/juiced-venmo-gateway-pro
 */

declare(strict_types=1);

namespace JuicedPlugins\Venmo_Gateway_Pro\Integrations\WooCommerce;

use JuicedPlugins\Venmo_Gateway_Pro\WPUnit_Testcase;
use Mockery;
use Mockery\MockInterface;
use WC_Order;

/**
 * @coversDefaultClass \JuicedPlugins\Venmo_Gateway_Pro\Integrations\WooCommerce\Venmo_Payment_Url
 * @requires function WC
 */
class Venmo_Payment_Url_WPUnit_Test extends WPUnit_Testcase {

	protected function tearDown(): void {
		Mockery::close();
		parent::tearDown();
	}

	/**
	 * Create a saved order with a total and the store's Venmo username.
	 *
	 * @param string $store_venmo_username The username as saved in the order meta.
	 * @param string $order_total The order total.
	 */
	protected function create_order( string $store_venmo_username = 'brianhenryie', string $order_total = '18.00' ): WC_Order {
		$order = new WC_Order();
		$order->set_total( $order_total );
		$order->add_meta_data( Venmo_Gateway::STORE_VENMO_USERNAME_META_KEY, $store_venmo_username, true );
		$order->save();

		return $order;
	}

	/**
	 * Mock order 123, to control the type `WC_Order::get_total()` returns.
	 *
	 * @param int|float|string $order_total What `WC_Order::get_total()` returns.
	 *
	 * @return WC_Order&MockInterface
	 */
	protected function mock_order( int|float|string $order_total ): WC_Order {
		/** @var WC_Order&MockInterface $order */
		$order = Mockery::mock( WC_Order::class );
		$order->shouldReceive( 'get_meta' )->with( Venmo_Gateway::STORE_VENMO_USERNAME_META_KEY )->andReturn( 'brianhenryie' );
		$order->shouldReceive( 'get_id' )->andReturn( 123 );
		$order->shouldReceive( 'get_total' )->andReturn( $order_total );
		return $order;
	}

	/**
	 * @covers ::__construct
	 * @covers ::get_browser_url
	 */
	public function test_get_browser_url(): void {

		$order = $this->create_order();

		$sut = new Venmo_Payment_Url( $order );

		$this->assertSame(
			sprintf( 'https://venmo.com/brianhenryie?txn=pay&amount=18.00&note=order%%20%d', $order->get_id() ),
			$sut->get_browser_url()
		);
	}

	/**
	 * @covers ::__construct
	 * @covers ::get_qr_url
	 */
	public function test_get_qr_url(): void {

		$order = $this->create_order();

		$sut = new Venmo_Payment_Url( $order );

		$this->assertSame(
			sprintf( 'venmo://paycharge?txn=pay&recipients=brianhenryie&note=order%%20%d&amount=18.00', $order->get_id() ),
			$sut->get_qr_url()
		);
	}

	/**
	 * The printable URL is split into spans separated by zero-width spaces so it can wrap, and has no scheme.
	 *
	 * @covers ::get_html
	 */
	public function test_get_html(): void {

		$order = $this->create_order();

		$sut = new Venmo_Payment_Url( $order );

		$this->assertSame(
			sprintf(
				'<span>venmo.com/</span>&#8203;<span>brianhenryie?</span>&#8203;<span>txn=pay&amp;</span>&#8203;<span>amount=18.00&amp;</span>&#8203;<span>note=order%%20%d</span>',
				$order->get_id()
			),
			$sut->get_html()
		);
	}

	/**
	 * With the markup and zero-width spaces removed, the printed URL should be the browser URL without its scheme.
	 *
	 * @covers ::get_html
	 * @covers ::get_browser_url
	 */
	public function test_get_html_text_matches_browser_url(): void {

		$order = $this->create_order();

		$sut = new Venmo_Payment_Url( $order );

		$printed_url = html_entity_decode(
			str_replace( '&#8203;', '', wp_strip_all_tags( $sut->get_html() ) ),
			ENT_QUOTES | ENT_HTML5,
			'UTF-8'
		);

		$this->assertSame( $sut->get_browser_url(), 'https://' . $printed_url );
	}

	/**
	 * The username is stored without the "@", but older orders or manual edits may include it.
	 *
	 * @covers ::__construct
	 *
	 * @dataProvider unsanitized_usernames
	 *
	 * @param string $saved_username The username as saved in the order meta.
	 */
	public function test_username_is_sanitized( string $saved_username ): void {

		$order = $this->create_order( $saved_username );

		$sut = new Venmo_Payment_Url( $order );

		$this->assertStringStartsWith( 'https://venmo.com/brianhenryie?', $sut->get_browser_url() );
		$this->assertStringContainsString( '&recipients=brianhenryie&', $sut->get_qr_url() );
		$this->assertStringContainsString( '<span>brianhenryie?</span>', $sut->get_html() );
	}

	/**
	 * @return array<string, array{0: string}>
	 */
	public function unsanitized_usernames(): array {
		return array(
			'leading @'              => array( '@brianhenryie' ),
			'surrounding whitespace' => array( ' brianhenryie ' ),
			'whitespace and @'       => array( ' @brianhenryie' ),
		);
	}

	/**
	 * @covers ::get_browser_url
	 * @covers ::get_html
	 */
	public function test_username_is_url_encoded(): void {

		$order = $this->create_order( 'brian henry/ie' );

		$sut = new Venmo_Payment_Url( $order );

		$this->assertStringStartsWith( 'https://venmo.com/brian%20henry%2Fie?', $sut->get_browser_url() );
		$this->assertStringContainsString( '<span>brian%20henry%2Fie?</span>', $sut->get_html() );
	}

	/**
	 * `WC_Order::get_total()` can return an int, float or string; the amount should always have the store's decimals.
	 *
	 * @covers ::get_amount
	 *
	 * @dataProvider order_totals
	 *
	 * @param int|float|string $order_total What `WC_Order::get_total()` returns.
	 * @param string           $expected_amount The amount expected in the URLs.
	 */
	public function test_amount_uses_store_decimals( int|float|string $order_total, string $expected_amount ): void {

		$order = $this->mock_order( $order_total );

		$sut = new Venmo_Payment_Url( $order );

		$this->assertSame(
			"https://venmo.com/brianhenryie?txn=pay&amount={$expected_amount}&note=order%20123",
			$sut->get_browser_url()
		);
		$this->assertSame(
			"venmo://paycharge?txn=pay&recipients=brianhenryie&note=order%20123&amount={$expected_amount}",
			$sut->get_qr_url()
		);
		$this->assertStringContainsString( "<span>amount={$expected_amount}&amp;</span>", $sut->get_html() );
	}

	/**
	 * @return array<string, array{0: int|float|string, 1: string}>
	 */
	public function order_totals(): array {
		return array(
			'int'                    => array( 18, '18.00' ),
			'float'                  => array( 18.5, '18.50' ),
			'string'                 => array( '18.00', '18.00' ),
			'string without decimal' => array( '18', '18.00' ),
			'rounded'                => array( 18.999, '19.00' ),
			'thousands'              => array( 1234.5, '1234.50' ),
			'zero'                   => array( 0, '0.00' ),
		);
	}

	/**
	 * @covers ::get_amount
	 */
	public function test_amount_respects_price_decimals_setting(): void {

		update_option( 'woocommerce_price_num_decimals', 0 );

		$order = $this->mock_order( '18.00' );

		$sut = new Venmo_Payment_Url( $order );

		$this->assertSame( 'https://venmo.com/brianhenryie?txn=pay&amount=18&note=order%20123', $sut->get_browser_url() );
	}
}
