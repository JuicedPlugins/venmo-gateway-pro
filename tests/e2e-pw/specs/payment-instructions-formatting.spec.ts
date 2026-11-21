/**
 * Playwright E2E tests that the Venmo payment instructions are not displayed as escaped HTML.
 *
 * - The customer on-hold email printed `wc_price()` HTML through `esc_html()`, so the customer saw
 *   `&lt;span class=&quot;woocommerce-Price-amount amount&quot;&gt;...` instead of the order total.
 * - The admin order "Venmo Payment" metabox printed the payment URL with `&ZeroWidthSpace;`, which `wp_kses()`
 *   escapes, so the admin saw `venmo.com/&ZeroWidthSpace;username?&ZeroWidthSpace;txn=pay&...`.
 *
 * Orders are arranged via the WooCommerce REST API. The email is read back from the WP Mail Debugger REST API.
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';
import { loginAsAdmin } from '../helpers/general/ui/login';
import { testConfig } from '../test-config';

const STORE_VENMO_USERNAME_META_KEY = '_destination_account_venmo_username';
const CUSTOMER_VENMO_USERNAME_META_KEY = '_customer_venmo_username';

const ZERO_WIDTH_SPACE = '​';

type RequestUtils = Parameters<
	Parameters< typeof test >[ 2 ]
>[ 0 ][ 'requestUtils' ];

async function createVenmoOrder(
	requestUtils: RequestUtils,
	billingEmail: string
) {
	const products = await requestUtils.rest( {
		path: '/wc/v3/products',
		params: { search: testConfig.products.simple.name, per_page: 1 },
	} );
	return requestUtils.rest( {
		method: 'POST',
		path: '/wc/v3/orders',
		data: {
			payment_method: 'venmo',
			payment_method_title: 'Venmo',
			status: 'on-hold',
			billing: {
				first_name: 'Formatting',
				last_name: 'Test',
				email: billingEmail,
			},
			line_items: [ { product_id: products[ 0 ].id, quantity: 1 } ],
			meta_data: [
				{ key: STORE_VENMO_USERNAME_META_KEY, value: 'teststore' },
				{
					key: CUSTOMER_VENMO_USERNAME_META_KEY,
					value: 'testcustomer',
				},
			],
		},
	} );
}

test.describe( 'Venmo payment instructions formatting', () => {
	test( 'customer on-hold email shows the order total as plain text', async ( {
		page,
		requestUtils,
	} ) => {
		const billingEmail = `formatting-${ Date.now() }@example.org`;
		const order = await createVenmoOrder( requestUtils, billingEmail );

		try {
			const emails = await requestUtils.rest( {
				path: '/wp-mail-debugger/v1/emails',
				params: { search: billingEmail, per_page: 10 },
			} );
			const email = emails.find( ( e: { to: unknown } ) =>
				JSON.stringify( e.to ).includes( billingEmail )
			);
			expect(
				email,
				`No email to ${ billingEmail } captured by WP Mail Debugger`
			).toBeTruthy();

			const html: string = email.message ?? email.html ?? email.body;
			const expectedTotal = `${ order.currency_symbol }${ order.total }`;

			expect( html ).not.toContain( '&lt;span' );
			expect( html ).toContain(
				`Please send payment of ${ expectedTotal } via Venmo`
			);
			expect( html ).toContain( `<b>${ expectedTotal }</b>` );

			// Screenshot of the instructions as the customer sees them, for the PR.
			await page.setContent( html );
			await page
				.getByText( 'Please send payment of' )
				.scrollIntoViewIfNeeded();
			await page.screenshot( {
				path: test.info().outputPath( 'on-hold-email.png' ),
				fullPage: true,
			} );
		} finally {
			await requestUtils.rest( {
				method: 'DELETE',
				path: `/wc/v3/orders/${ order.id }`,
				params: { force: true },
			} );
		}
	} );

	test( 'admin order metabox shows the payment link without escaped entities', async ( {
		page,
		requestUtils,
	} ) => {
		const order = await createVenmoOrder(
			requestUtils,
			`formatting-metabox-${ Date.now() }@example.org`
		);

		try {
			await loginAsAdmin( page );
			// Redirects to the HPOS edit screen when HPOS is enabled.
			await page.goto(
				`/wp-admin/post.php?post=${ order.id }&action=edit`,
				{ waitUntil: 'domcontentloaded' }
			);

			const metabox = page.locator( '#venmo_payment' );
			await expect( metabox ).toBeVisible();

			const text = ( ( await metabox.textContent() ) ?? '' ).replaceAll(
				ZERO_WIDTH_SPACE,
				''
			);
			expect( text ).not.toContain( 'ZeroWidthSpace' );
			expect( text ).not.toContain( '&amp;' );
			expect( text ).not.toContain( '&nbsp' );
			expect( text ).toContain(
				`venmo.com/teststore?txn=pay&amount=${ order.total }&note=order%20${ order.id }`
			);

			await metabox.screenshot( {
				path: test.info().outputPath( 'admin-order-metabox.png' ),
			} );
		} finally {
			await requestUtils.rest( {
				method: 'DELETE',
				path: `/wc/v3/orders/${ order.id }`,
				params: { force: true },
			} );
		}
	} );
} );
