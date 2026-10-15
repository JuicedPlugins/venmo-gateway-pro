/**
 * More than one instance of the Venmo gateway in the blocks checkout, e.g. duplicates created with
 * Duplicate Payment Gateways.
 *
 * The development plugin adds a second instance, `venmo_second`, configured in initialize-internal.sh, which is only
 * available to browsers with the `venmo_second_instance` cookie.
 *
 * @see development-plugin/gateways/class-second-venmo-gateway.php
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';
import { BLOCKS_CHECKOUT_PATH } from '../helpers/development-plugin/rest/checkout';
import { setDefaultCustomerAddresses } from '../helpers/development-plugin/ajax/wc-cart';
import { addProductToCartByName } from '../helpers/general/ui/wc-cart';
import { testConfig } from '../test-config';

const SECOND_INSTANCE_ID = 'venmo_second';
const CUSTOMER_VENMO_USERNAME = 'brianhenryie';

test.describe( 'Venmo checkout (blocks), multiple gateway instances', () => {
	test.beforeEach( async ( { page, baseURL } ) => {
		await page.context().clearCookies();

		await page.context().addCookies( [
			{
				name: 'venmo_second_instance',
				value: '1',
				url: baseURL ?? 'http://localhost:8888',
			},
		] );

		await setDefaultCustomerAddresses( page );
		await addProductToCartByName( page, testConfig.products.simple.name );
	} );

	test( 'each instance is listed with its own title', async ( { page } ) => {
		await page.goto( BLOCKS_CHECKOUT_PATH );

		await expect(
			page.locator(
				'label[for="radio-control-wc-payment-method-options-venmo"]'
			)
		).toBeVisible();
		await expect(
			page.locator(
				`label[for="radio-control-wc-payment-method-options-${ SECOND_INSTANCE_ID }"]`
			)
		).toContainText( 'Venmo by phone' );
	} );

	test( 'an order can be placed with the second instance', async ( {
		page,
		requestUtils,
	} ) => {
		await page.goto( BLOCKS_CHECKOUT_PATH );

		await page.click(
			`label[for="radio-control-wc-payment-method-options-${ SECOND_INSTANCE_ID }"]`
		);

		// Its own description and username field.
		await expect( page.getByText( 'Second Venmo instance' ) ).toBeVisible();
		const usernameInput = page.locator(
			`#${ SECOND_INSTANCE_ID }-username-input`
		);
		await usernameInput.fill( CUSTOMER_VENMO_USERNAME );

		await page.click( '.wc-block-components-checkout-place-order-button' );

		await page.waitForURL( /order-received\/(\d+)/, { timeout: 30000 } );
		const orderId = page.url().match( /order-received\/(\d+)/ )?.[ 1 ];

		const order: {
			payment_method: string;
			status: string;
			meta_data: Array< { key: string; value: string } >;
		} = await requestUtils.rest( { path: `/wc/v3/orders/${ orderId }` } );

		expect( order.payment_method ).toBe( SECOND_INSTANCE_ID );
		expect( order.status ).toBe( 'on-hold' );

		const meta = Object.fromEntries(
			order.meta_data.map( ( { key, value } ) => [ key, value ] )
		);
		expect( meta._customer_venmo_username ).toBe( CUSTOMER_VENMO_USERNAME );
		// The second instance's own store Venmo username.
		expect( meta._destination_account_venmo_username ).toBe(
			'secondvendor'
		);
	} );
} );
