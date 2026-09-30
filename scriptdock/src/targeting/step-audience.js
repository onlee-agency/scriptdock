/**
 * Step 3: who sees the snippet. Every section starts at "everyone", so a
 * snippet only narrows when something here is set.
 */
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Icon, SegmentedControl, Select, TextField, cx } from '../components';
import * as api from './api';
import { CardGrid, SelectCard } from './cards';
import { ChipSet, PairRows, SearchChips } from './controls';

/**
 * @param {Object}   props           Props.
 * @param {Object}   props.draft     Draft.
 * @param {Function} props.patch     Changes the draft.
 * @param {Object}   props.options   Catalogue and lists.
 * @param {boolean}  props.canPhp    Whether PHP conditions are allowed.
 * @param {string}   props.phpReason Why not, when they are not.
 * @return {Element} The step.
 */
export default function AudienceStep( {
	draft,
	patch,
	options,
	canPhp,
	phpReason,
} ) {
	const [ moreDevices, setMoreDevices ] = useState( false );
	const [ moreAdvanced, setMoreAdvanced ] = useState( false );
	const lists = options?.lists || {};
	const operators = options?.operators || {};
	const { audience } = draft;
	const set = ( change ) => patch( { audience: { ...audience, ...change } } );
	const setWc = ( change ) => set( { wc: { ...audience.wc, ...change } } );
	const languages = lists.languages || [];

	return (
		<div className="sd-step sd-step--audience">
			<Section title={ __( 'Visitors', 'scriptdock' ) }>
				<SegmentedControl
					label={ __( 'Visitors', 'scriptdock' ) }
					value={ audience.loggedIn || 'any' }
					onChange={ ( value ) =>
						set( {
							loggedIn: value === 'any' ? '' : value,
							roles: value === 'logged_in' ? audience.roles : [],
						} )
					}
					options={ [
						{ value: 'any', label: __( 'Everyone', 'scriptdock' ) },
						{
							value: 'logged_out',
							label: __( 'Logged out', 'scriptdock' ),
						},
						{
							value: 'logged_in',
							label: __( 'Logged in', 'scriptdock' ),
						},
					] }
				/>
				{ audience.loggedIn === 'logged_in' && (
					<>
						<ChipSet
							label={ __( 'User roles', 'scriptdock' ) }
							options={ lists.roles || [] }
							value={ audience.roles }
							onChange={ ( roles ) => set( { roles } ) }
						/>
						<SearchChips
							label={ __( 'Specific users', 'scriptdock' ) }
							value={ audience.users }
							onChange={ ( users ) => set( { users } ) }
							search={ ( words ) =>
								api.getUsers( { search: words } )
							}
							lookup={ ( ids ) =>
								api.getUsers( { include: ids } )
							}
							toOption={ ( user ) => ( {
								value: user.id,
								label: user.name,
							} ) }
							placeholder={ __( 'Search people…', 'scriptdock' ) }
						/>
					</>
				) }
				<CacheNote when={ !! audience.loggedIn } />
			</Section>

			<Section title={ __( 'Devices', 'scriptdock' ) }>
				<CardGrid
					label={ __( 'Devices', 'scriptdock' ) }
					className="sd-devices"
				>
					{ ( lists.device || [] ).map( ( entry, position ) => {
						const on = audience.devices.includes( entry.value );
						return (
							<SelectCard
								key={ entry.value }
								multiple
								title={ entry.label }
								art={ <DeviceArt kind={ entry.value } /> }
								selected={ on }
								tabIndex={ position === 0 || on ? 0 : -1 }
								onSelect={ () =>
									set( {
										devices: on ? [] : [ entry.value ],
									} )
								}
							/>
						);
					} ) }
				</CardGrid>
				<CacheNote when={ !! audience.devices.length } />
				<button
					type="button"
					className="sd-step__disclosure"
					aria-expanded={ moreDevices }
					onClick={ () => setMoreDevices( ! moreDevices ) }
				>
					<Icon
						name={ moreDevices ? 'chevron-down' : 'chevron-right' }
						size={ 14 }
						stroke={ 2 }
					/>
					{ __( 'More: browsers, operating systems', 'scriptdock' ) }
				</button>
				{ moreDevices && (
					<div className="sd-step__more">
						<ChipSet
							label={ __( 'Browsers', 'scriptdock' ) }
							options={ lists.browser || [] }
							value={ audience.browsers }
							onChange={ ( browsers ) => set( { browsers } ) }
						/>
						<ChipSet
							label={ __( 'Operating systems', 'scriptdock' ) }
							options={ lists.os || [] }
							value={ audience.os }
							onChange={ ( list ) => set( { os: list } ) }
						/>
					</div>
				) }
			</Section>

			{ languages.length > 1 && (
				<Section title={ __( 'Language', 'scriptdock' ) }>
					<ChipSet
						label={ __( 'Languages', 'scriptdock' ) }
						options={ languages }
						value={ audience.languages }
						onChange={ ( list ) => set( { languages: list } ) }
					/>
				</Section>
			) }

			<Section title={ __( 'Traffic source', 'scriptdock' ) }>
				<div className="sd-step__row">
					<Select
						label={ __( 'Referrer', 'scriptdock' ) }
						value={ audience.referrer.operator }
						onChange={ ( operator ) =>
							set( {
								referrer: { ...audience.referrer, operator },
							} )
						}
						options={ [
							'contains',
							'is',
							'starts_with',
							'regex',
						].map( ( value ) => ( {
							value,
							label: operators[ value ] || value,
						} ) ) }
					/>
					<TextField
						label={ __( 'Came from', 'scriptdock' ) }
						value={ audience.referrer.values.join( ', ' ) }
						placeholder="google.com, bing.com"
						help={ __(
							'Separate several with commas. Any of them matching is enough.',
							'scriptdock'
						) }
						onChange={ ( value ) =>
							set( {
								referrer: {
									...audience.referrer,
									values: value
										.split( ',' )
										.map( ( entry ) => entry.trim() )
										.filter( Boolean ),
								},
							} )
						}
					/>
				</div>
				<PairRows
					label={ __( 'URL parameters', 'scriptdock' ) }
					rows={ audience.params }
					labels={ operators }
					keyLabel={ __( 'Parameter', 'scriptdock' ) }
					addLabel={ __( 'Add a URL parameter', 'scriptdock' ) }
					onChange={ ( params ) => set( { params } ) }
				/>
				<PairRows
					label={ __( 'Cookies', 'scriptdock' ) }
					rows={ audience.cookies }
					labels={ operators }
					keyLabel={ __( 'Cookie', 'scriptdock' ) }
					addLabel={ __( 'Add a cookie', 'scriptdock' ) }
					onChange={ ( cookies ) => set( { cookies } ) }
				/>
				<CacheNote
					when={
						!! audience.referrer.values.length ||
						!! audience.params.length ||
						!! audience.cookies.length
					}
				/>
			</Section>

			{ options?.woocommerce && (
				<Section title="WooCommerce">
					<div className="sd-step__row">
						<Select
							label={ __( 'Cart total', 'scriptdock' ) }
							value={ audience.wc.total.operator }
							onChange={ ( operator ) =>
								setWc( {
									total: { ...audience.wc.total, operator },
								} )
							}
							options={ [ 'between', 'gte', 'lte' ].map(
								( value ) => ( {
									value,
									label: operators[ value ] || value,
								} )
							) }
						/>
						{ audience.wc.total.operator === 'between' ? (
							<div className="sd-step__pair">
								<TextField
									label={ __( 'From', 'scriptdock' ) }
									type="number"
									value={ audience.wc.total.from }
									onChange={ ( from ) =>
										setWc( {
											total: {
												...audience.wc.total,
												from,
											},
										} )
									}
								/>
								<TextField
									label={ __( 'To', 'scriptdock' ) }
									type="number"
									value={ audience.wc.total.to }
									onChange={ ( to ) =>
										setWc( {
											total: { ...audience.wc.total, to },
										} )
									}
								/>
							</div>
						) : (
							<TextField
								label={ __( 'Amount', 'scriptdock' ) }
								type="number"
								value={ audience.wc.total.amount }
								onChange={ ( amount ) =>
									setWc( {
										total: { ...audience.wc.total, amount },
									} )
								}
							/>
						) }
					</div>
					<SearchChips
						label={ __( 'Cart contains', 'scriptdock' ) }
						value={ audience.wc.products }
						onChange={ ( products ) => setWc( { products } ) }
						search={ ( words ) =>
							api.getContent( {
								type: 'product',
								search: words,
								per_page: 10,
							} )
						}
						lookup={ ( ids ) => api.getContent( { include: ids } ) }
						toOption={ ( item ) => ( {
							value: item.id,
							label: item.title,
						} ) }
						placeholder={ __( 'Search products…', 'scriptdock' ) }
					/>
					<SearchChips
						label={ __(
							'Cart contains a product from',
							'scriptdock'
						) }
						value={ audience.wc.categories }
						onChange={ ( categories ) => setWc( { categories } ) }
						search={ ( words ) =>
							api.getTerms( {
								taxonomy: 'product_cat',
								search: words,
							} )
						}
						lookup={ ( ids ) => api.getTerms( { include: ids } ) }
						toOption={ ( term ) => ( {
							value: term.id,
							label: term.name,
						} ) }
						placeholder={ __(
							'Search product categories…',
							'scriptdock'
						) }
					/>
					<SegmentedControl
						label={ __(
							'Customer has ordered before',
							'scriptdock'
						) }
						value={ audience.wc.ordered || 'any' }
						onChange={ ( value ) =>
							setWc( { ordered: value === 'any' ? '' : value } )
						}
						options={ [
							{
								value: 'any',
								label: __( 'Either way', 'scriptdock' ),
							},
							{
								value: 'is_true',
								label: __( 'Has ordered', 'scriptdock' ),
							},
							{
								value: 'is_false',
								label: __( 'Has not ordered', 'scriptdock' ),
							},
						] }
					/>
					<CacheNote when />
				</Section>
			) }

			<Section title={ __( 'Advanced', 'scriptdock' ) }>
				<button
					type="button"
					className="sd-step__disclosure"
					aria-expanded={ moreAdvanced }
					onClick={ () => setMoreAdvanced( ! moreAdvanced ) }
				>
					<Icon
						name={ moreAdvanced ? 'chevron-down' : 'chevron-right' }
						size={ 14 }
						stroke={ 2 }
					/>
					{ __(
						'Custom fields, user fields and a PHP check',
						'scriptdock'
					) }
				</button>
				{ moreAdvanced && (
					<div className="sd-step__more">
						<PairRows
							label={ __(
								'Custom field on the page',
								'scriptdock'
							) }
							rows={ audience.postMeta }
							labels={ operators }
							keyLabel={ __( 'Field name', 'scriptdock' ) }
							addLabel={ __(
								'Add a custom field',
								'scriptdock'
							) }
							onChange={ ( postMeta ) => set( { postMeta } ) }
						/>
						<PairRows
							label={ __( 'Field on the user', 'scriptdock' ) }
							rows={ audience.userMeta }
							labels={ operators }
							keyLabel={ __( 'Field name', 'scriptdock' ) }
							addLabel={ __( 'Add a user field', 'scriptdock' ) }
							onChange={ ( userMeta ) => set( { userMeta } ) }
						/>
						<div
							className={ cx(
								'sd-step__php',
								! canPhp && 'is-locked'
							) }
						>
							<TextField
								label={ __(
									'PHP function returns true',
									'scriptdock'
								) }
								value={ audience.php }
								disabled={ ! canPhp }
								placeholder="my_membership_check"
								help={
									canPhp
										? __(
												'The name of a function that takes no arguments.',
												'scriptdock'
											)
										: phpReason
								}
								onChange={ ( php ) => set( { php } ) }
							/>
							{ ! canPhp && (
								<Icon name="lock" size={ 14 } stroke={ 1.8 } />
							) }
						</div>
					</div>
				) }
			</Section>
		</div>
	);
}

/**
 * A section of the step.
 *
 * @param {Object}  props          Props.
 * @param {string}  props.title    Section name.
 * @param {Element} props.children Contents.
 * @return {Element} The section.
 */
function Section( { title, children } ) {
	return (
		<section className="sd-step__section sd-step__card">
			<h3 className="sd-step__heading">{ title }</h3>
			{ children }
		</section>
	);
}

/**
 * The note that per-visitor rules and full-page caching do not mix.
 *
 * @param {Object}  props      Props.
 * @param {boolean} props.when Whether to show it.
 * @return {Element|null} The note.
 */
function CacheNote( { when } ) {
	if ( ! when ) {
		return null;
	}
	return (
		<p className="sd-step__cache">
			<Icon name="info" size={ 13 } stroke={ 1.8 } />
			{ __( 'May not work with full-page caching', 'scriptdock' ) }
		</p>
	);
}

/**
 * The drawing on a device card.
 *
 * @param {Object} props      Props.
 * @param {string} props.kind desktop or mobile.
 * @return {Element} The drawing.
 */
function DeviceArt( { kind } ) {
	return (
		<span className={ cx( 'sd-deviceart', `sd-deviceart--${ kind }` ) }>
			<span />
			<span />
		</span>
	);
}
