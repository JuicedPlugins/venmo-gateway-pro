/**
 * WooCommerce Blocks Checkout integration for the Venmo payment gateway.
 *
 * Registers each instance of the Venmo payment method (the original, and any duplicates, e.g. created with
 * Duplicate Payment Gateways) with the WooCommerce Blocks checkout, including a text input for the customer's
 * Venmo username.
 *
 * @see Venmo_Gateway_Blocks_Checkout_Support::get_payment_method_data()
 *
 * @package
 */

/**
 * External dependencies
 */
import { registerPaymentMethod } from '@woocommerce/blocks-registry';
import { getSetting } from '@woocommerce/settings';
import { decodeEntities } from '@wordpress/html-entities';
import { __ } from '@wordpress/i18n';
import { useState, useCallback, useEffect, useRef } from '@wordpress/element';

interface VenmoGatewaySettings {
	is_venmo_gateway?: boolean;
	title?: string;
	description?: string;
	supports?: string[];
	venmo_icon_url?: string;
	saved_venmo_username?: string;
}

interface PaymentMethodLabelProps {
	components: {
		PaymentMethodLabel: React.FC< { text: string } >;
		PaymentMethodIcons: React.FC< {
			icons: Array< { id: string; src: string; alt: string } >;
		} >;
	};
}

interface EmitResponseType {
	responseTypes: {
		SUCCESS: string;
		ERROR: string;
		FAIL: string;
	};
	noticeContexts: {
		PAYMENTS: string;
	};
}

interface PaymentMethodContentProps {
	eventRegistration: {
		onPaymentSetup: (
			callback: () => {
				type: string;
				meta?: { paymentMethodData: Record< string, string > };
				message?: string;
			}
		) => () => void;
	};
	emitResponse: EmitResponseType;
}

interface VenmoInstanceProps {
	/**
	 * The gateway id, e.g. `venmo`.
	 */
	name: string;
	settings: VenmoGatewaySettings;
}

/**
 * The title is hidden when it is "Venmo", since the logo is displayed.
 *
 * @param settings The gateway instance's settings.
 */
const getLabel = ( settings: VenmoGatewaySettings ): string => {
	const settingsTitle: string = decodeEntities( settings.title || '' );
	if ( settingsTitle !== 'Venmo' && settingsTitle !== 'venmo' ) {
		return settingsTitle;
	}
	return '';
};

/**
 * Content component — shows description and Venmo username input.
 * @param root0                   Props.
 * @param root0.eventRegistration Checkout events, to send the username with the payment.
 * @param root0.emitResponse      Response types for the checkout events.
 * @param root0.name              The gateway id, e.g. `venmo`.
 * @param root0.settings          The gateway instance's settings.
 */
const VenmoContent: React.FC<
	PaymentMethodContentProps & VenmoInstanceProps
> = ( { eventRegistration, emitResponse, name, settings } ) => {
	// Initialize with saved username from settings
	const [ venmoUsername, setVenmoUsername ] = useState(
		settings.saved_venmo_username || ''
	);
	const { onPaymentSetup } = eventRegistration;
	const inputRef = useRef< HTMLInputElement >( null );

	// Auto-focus the input when component mounts (Venmo is selected)
	useEffect( () => {
		const timer = setTimeout( () => {
			if ( inputRef.current ) {
				inputRef.current.focus();
			}
		}, 100 );
		return () => clearTimeout( timer );
	}, [] );

	const onUsernameChange = useCallback(
		( e: React.ChangeEvent< HTMLInputElement > ) => {
			setVenmoUsername( e.target.value );
		},
		[]
	);

	useEffect( () => {
		const unsubscribe = onPaymentSetup( () => {
			if ( ! venmoUsername.trim() ) {
				// Focus the field on validation error
				setTimeout( () => {
					if ( inputRef.current ) {
						inputRef.current.focus();
					}
				}, 100 );
				return {
					type: emitResponse.responseTypes.ERROR,
					message: __(
						'Please enter your Venmo username.',
						'juiced-venmo-gateway-pro'
					),
				};
			}
			return {
				type: emitResponse.responseTypes.SUCCESS,
				meta: {
					paymentMethodData: {
						_customer_venmo_username: venmoUsername.trim(),
					},
				},
			};
		} );
		return unsubscribe;
	}, [ venmoUsername, onPaymentSetup, emitResponse.responseTypes ] );

	return (
		<div className="juiced-venmo-gateway-pro-blocks-checkout">
			{ settings.description && (
				<p className="wc-block-components-checkout-step__description">
					{ decodeEntities( settings.description ) }
				</p>
			) }
			<div className="juiced-venmo-gateway-pro-username-field">
				<label htmlFor={ `${ name }-username-input` }>
					{ __(
						'Enter your Venmo username:',
						'juiced-venmo-gateway-pro'
					) }
					<abbr
						className="required"
						title="This is required to reconcile payments."
					>
						*
					</abbr>
				</label>
				<input
					id={ `${ name }-username-input` }
					type="text"
					value={ venmoUsername }
					onChange={ onUsernameChange }
					placeholder={ __(
						'Venmo username',
						'juiced-venmo-gateway-pro'
					) }
					maxLength={ 255 }
					required
					ref={ inputRef }
					className="wc-block-components-text-input"
					aria-label={ __(
						'Venmo username',
						'juiced-venmo-gateway-pro'
					) }
				/>
			</div>
		</div>
	);
};

/**
 * Label component with optional icon.
 * @param root0            Props.
 * @param root0.components The checkout's components.
 * @param root0.settings   The gateway instance's settings.
 */
const VenmoLabel: React.FC< PaymentMethodLabelProps & VenmoInstanceProps > = ( {
	components,
	settings,
} ) => {
	const { PaymentMethodLabel } = components;
	const label = getLabel( settings );
	return (
		<div style={ { display: 'flex', alignItems: 'center', gap: '8px' } }>
			{ settings.venmo_icon_url && (
				<img
					src={ settings.venmo_icon_url }
					alt={ label }
					style={ { height: '24px' } }
				/>
			) }
			<PaymentMethodLabel text={ label } />
		</div>
	);
};

/**
 * Edit component for the block editor — static preview.
 * @param root0          Props.
 * @param root0.name     The gateway id, e.g. `venmo`.
 * @param root0.settings The gateway instance's settings.
 */
const VenmoEdit: React.FC< VenmoInstanceProps > = ( { name, settings } ) => {
	return (
		<div className="juiced-venmo-gateway-pro-blocks-checkout">
			{ settings.description && (
				<p className="wc-block-components-checkout-step__description">
					{ decodeEntities( settings.description || '' ) }
				</p>
			) }
			<div className="juiced-venmo-gateway-pro-username-field">
				<label htmlFor={ `${ name }-username-input-preview` }>
					{ __(
						'Enter your Venmo username:',
						'juiced-venmo-gateway-pro'
					) }
					<abbr
						className="required"
						title="This is required to reconcile payments."
					>
						*
					</abbr>
				</label>
				<input
					id={ `${ name }-username-input-preview` }
					type="text"
					placeholder={ __(
						'Venmo username',
						'juiced-venmo-gateway-pro'
					) }
					disabled
					className="wc-block-components-text-input"
				/>
			</div>
		</div>
	);
};

/**
 * Register one Venmo gateway instance.
 *
 * @param name     The gateway id, e.g. `venmo`.
 * @param settings The gateway instance's settings.
 */
const registerVenmoPaymentMethod = (
	name: string,
	settings: VenmoGatewaySettings
): void => {
	const label = getLabel( settings );

	registerPaymentMethod( {
		name,
		label: (
			<VenmoLabel
				name={ name }
				settings={ settings }
				components={ {
					PaymentMethodLabel: ( { text }: { text: string } ) => (
						<>{ text }</>
					),
					PaymentMethodIcons: () => null,
				} }
			/>
		),
		content: (
			<VenmoContent
				name={ name }
				settings={ settings }
				eventRegistration={ { onPaymentSetup: () => () => {} } }
				emitResponse={ {
					responseTypes: {
						SUCCESS: 'success',
						ERROR: 'error',
						FAIL: 'fail',
					},
					noticeContexts: { PAYMENTS: 'wc/checkout/payments' },
				} }
			/>
		),
		edit: <VenmoEdit name={ name } settings={ settings } />,
		canMakePayment: (): boolean => true,
		ariaLabel: label,
		supports: {
			features: settings.supports || [],
		},
	} );
};

const paymentMethodData: Record< string, VenmoGatewaySettings > = getSetting(
	'paymentMethodData',
	{}
);

// One for each Venmo gateway instance, e.g. `venmo` and its duplicates.
Object.keys( paymentMethodData )
	.filter(
		( name: string ) => true === paymentMethodData[ name ]?.is_venmo_gateway
	)
	.forEach( ( name: string ) =>
		registerVenmoPaymentMethod( name, paymentMethodData[ name ] )
	);
