/**
 * Loading (HTML, CSS and JavaScript): inline or a cached file, when it
 * loads, and whether it waits for cookie consent. PHP has no such card.
 */
import { __, sprintf } from '@wordpress/i18n';
import { CONSENT_CATEGORIES } from './consent';
import { Icon, SegmentedControl, Select, cx } from '../components';

/**
 * Whether the draft can load as a cached file, and why not.
 *
 * @param {Object}  draft      Draft.
 * @param {Array}   locations  Placements: { value, files }.
 * @param {boolean} assetFiles Files are on in Settings.
 * @return {Object} possible, reason.
 */
export function fileOutput( draft, locations, assetFiles ) {
	let reason = '';
	const place = locations.find( ( item ) => item.value === draft.location );
	if ( ! [ 'css', 'js' ].includes( draft.type ) ) {
		reason = __(
			'Only CSS and JavaScript can load as a file.',
			'scriptdock'
		);
	} else if ( ! place || ! place.files ) {
		reason = __(
			'This placement prints into the page, so it cannot use a file. Files work in the site header and footer, the admin, the login page and the block editor.',
			'scriptdock'
		);
	} else if ( ! assetFiles ) {
		reason = __(
			'Loading as files is turned off in Settings.',
			'scriptdock'
		);
	}
	return { possible: ! reason, reason };
}

/**
 * The load strategies, as cards.
 *
 * @return {Array} value, label, text, badge.
 */
function strategies() {
	return [
		{
			value: 'default',
			label: __( 'Normal', 'scriptdock' ),
			text: __( 'Loads in place, in order.', 'scriptdock' ),
		},
		{
			value: 'defer',
			label: __( 'Defer', 'scriptdock' ),
			text: __( 'Waits until the page is parsed.', 'scriptdock' ),
		},
		{
			value: 'async',
			label: __( 'Async', 'scriptdock' ),
			text: __( 'Loads alongside the page.', 'scriptdock' ),
		},
		{
			value: 'idle',
			label: __( 'When idle', 'scriptdock' ),
			text: __( 'After everything else is done.', 'scriptdock' ),
			badge: __( 'Faster', 'scriptdock' ),
		},
		{
			value: 'interaction',
			label: __( 'On interaction', 'scriptdock' ),
			text: __( 'After the first scroll or tap.', 'scriptdock' ),
			badge: __( 'Fastest', 'scriptdock' ),
		},
	];
}

/**
 * @param {Object}   props            Props.
 * @param {Object}   props.draft      Draft.
 * @param {Function} props.onOptions  Called with changed options.
 * @param {Array}    props.locations  Placements.
 * @param {boolean}  props.assetFiles Files are on in Settings.
 * @param {Object}   props.consent    { api, plugin }.
 * @param {boolean}  props.readOnly   Nothing can change.
 * @return {Element|null} The card.
 */
export default function LoadingCard( {
	draft,
	onOptions,
	locations,
	assetFiles,
	consent,
	readOnly,
} ) {
	const { type, options } = draft;
	if ( ! [ 'html', 'css', 'js' ].includes( type ) ) {
		return null;
	}
	const file = fileOutput( draft, locations, assetFiles );
	const output = file.possible ? options.output : 'inline';
	const delays = type === 'js' || type === 'html';
	const asyncReason =
		type === 'js' && output === 'file'
			? ''
			: __( 'Only for JavaScript loaded as a file.', 'scriptdock' );

	return (
		<section
			className="sd-card sd-loading"
			aria-labelledby="sd-loading-label"
		>
			<h2 id="sd-loading-label" className="sd-overline">
				{ __( 'Loading', 'scriptdock' ) }
			</h2>

			{ ( type === 'css' || type === 'js' ) && (
				<div className="sd-loading__row">
					<div className="sd-loading__text">
						<span
							className="sd-loading__label"
							id="sd-output-label"
						>
							{ __( 'Output', 'scriptdock' ) }
						</span>
						<span className="sd-loading__hint">
							{ file.possible
								? __(
										'A cached file can be reused between pages.',
										'scriptdock'
									)
								: file.reason }
						</span>
					</div>
					<SegmentedControl
						label={ __( 'Output', 'scriptdock' ) }
						value={ output }
						onChange={ ( value ) => onOptions( { output: value } ) }
						options={ [
							{
								value: 'inline',
								label: __( 'Inline in the page', 'scriptdock' ),
								disabled: readOnly,
							},
							{
								value: 'file',
								label: __( 'As a cached file', 'scriptdock' ),
								disabled: readOnly || ! file.possible,
								reason: file.reason,
							},
						] }
					/>
				</div>
			) }

			{ delays && (
				<fieldset className="sd-loading__strategies">
					<legend className="sd-loading__label">
						{ __( 'Load strategy', 'scriptdock' ) }
					</legend>
					<div className="sd-choices">
						{ strategies().map( ( strategy ) => {
							const blocked =
								strategy.value === 'async' ? asyncReason : '';
							const checked = options.strategy === strategy.value;
							return (
								// The title and description inside are the label's text.
								// eslint-disable-next-line jsx-a11y/label-has-associated-control
								<label
									key={ strategy.value }
									className={ cx(
										'sd-choice',
										checked && 'is-checked',
										( blocked || readOnly ) && 'is-disabled'
									) }
								>
									<input
										type="radio"
										className="sd-choice__input"
										name="sd-strategy"
										value={ strategy.value }
										checked={ checked }
										disabled={ !! blocked || readOnly }
										onChange={ () =>
											onOptions( {
												strategy: strategy.value,
											} )
										}
									/>
									<span
										className="sd-choice__mark"
										aria-hidden="true"
									/>
									<span className="sd-choice__text">
										<span className="sd-choice__title">
											{ strategy.label }
											{ strategy.badge && (
												<span className="sd-choice__badge">
													{ strategy.badge }
												</span>
											) }
										</span>
										<span className="sd-choice__desc">
											{ blocked ? (
												<>
													<Icon
														name="lock"
														size={ 12 }
														stroke={ 2 }
													/>
													{ blocked }
												</>
											) : (
												strategy.text
											) }
										</span>
									</span>
								</label>
							);
						} ) }
					</div>
				</fieldset>
			) }

			{ delays && (
				<div className="sd-loading__consent">
					<Select
						label={ __( 'Cookie consent', 'scriptdock' ) }
						value={ options.consent || '' }
						onChange={ ( value ) =>
							onOptions( { consent: value } )
						}
						disabled={ readOnly }
						options={ CONSENT_CATEGORIES }
					/>
					<ConsentNote consent={ consent } />
				</div>
			) }
		</section>
	);
}

/**
 * Whether a consent plugin will hold the code back.
 *
 * @param {Object} props         Props.
 * @param {Object} props.consent { api, plugin }.
 * @return {Element} The note.
 */
function ConsentNote( { consent } ) {
	if ( consent.api ) {
		return (
			<span className="sd-consent-note is-found">
				<Icon name="check" size={ 14 } stroke={ 2.6 } />
				{ consent.plugin
					? sprintf(
							/* translators: %s: consent plugin name, for example "Complianz". */
							__(
								'%s detected — categories map automatically',
								'scriptdock'
							),
							consent.plugin
						)
					: __(
							'Your consent plugin decides when it loads.',
							'scriptdock'
						) }
			</span>
		);
	}
	return (
		<span className="sd-consent-note">
			<Icon name="info" size={ 14 } stroke={ 2 } />
			{ __(
				'No consent plugin found, so code that waits for consent loads right away.',
				'scriptdock'
			) }
		</span>
	);
}
