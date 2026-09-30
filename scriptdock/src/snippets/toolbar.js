/**
 * The toolbar above the list: search, filters, the chips of active filters,
 * sort and row density. On narrow screens the filters and sort move into
 * one "Filters" button that opens a bottom sheet.
 */
import { useEffect, useRef, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import {
	BottomSheet,
	Button,
	Checkbox,
	DropdownMenu,
	Icon,
	IconButton,
	Radio,
	SearchField,
	cx,
} from '../components';
import { filterCount } from './query';

const SORTS = [
	{ value: 'modified', label: __( 'Last modified', 'scriptdock' ) },
	{ value: 'created', label: __( 'Date created', 'scriptdock' ) },
	{ value: 'title', label: __( 'Title', 'scriptdock' ) },
	{ value: 'priority', label: __( 'Priority', 'scriptdock' ) },
	{ value: 'type', label: __( 'Type', 'scriptdock' ) },
];

const TARGETING = [
	{ value: 'everywhere', label: __( 'Everywhere', 'scriptdock' ) },
	{ value: 'conditional', label: __( 'Conditional', 'scriptdock' ) },
	{ value: 'scheduled', label: __( 'Scheduled', 'scriptdock' ) },
	{ value: 'shortcode', label: __( 'Shortcode', 'scriptdock' ) },
];

/**
 * The filter facets: each with its options, as the menus and the sheet
 * show them.
 *
 * @param {Object} boot   Bootstrap data.
 * @param {Array}  tags   Tags with counts.
 * @param {Array}  months Months that have snippets.
 * @return {Array} Facets: key, label, options, groups, single.
 */
export function facets( boot, tags, months ) {
	const groups = [];
	boot.locations.forEach( ( place ) => {
		let group = groups.find( ( item ) => item.label === place.group );
		if ( ! group ) {
			group = { label: place.group, options: [] };
			groups.push( group );
		}
		group.options.push( { value: place.value, label: place.label } );
	} );
	return [
		{
			key: 'type',
			label: __( 'Type', 'scriptdock' ),
			options: Object.entries( boot.types ).map(
				( [ value, label ] ) => ( {
					value,
					label,
				} )
			),
		},
		{
			key: 'location',
			label: __( 'Location', 'scriptdock' ),
			groups,
			options: groups.flatMap( ( group ) => group.options ),
		},
		{
			key: 'tag',
			label: __( 'Tags', 'scriptdock' ),
			options: tags.map( ( tag ) => ( {
				value: tag.id,
				label: tag.name,
				hint: tag.count,
			} ) ),
		},
		{
			key: 'targeting',
			label: __( 'Targeting', 'scriptdock' ),
			options: TARGETING,
		},
		{
			key: 'month',
			label: __( 'Date', 'scriptdock' ),
			single: true,
			options: months,
		},
	];
}

/**
 * @param {Object}   props              Props.
 * @param {Object}   props.query        List query.
 * @param {Function} props.onQuery      Called with changes to the query.
 * @param {Array}    props.facets       Filter facets.
 * @param {boolean}  props.compact      Filters in a sheet (narrow screens).
 * @param {string}   props.density      comfortable or compact.
 * @param {Function} props.onDensity    Called with the new density.
 * @param {Function} props.onManageTags Opens Manage tags.
 * @param {number}   props.total        Snippets matching, for the sheet.
 * @return {Element} The toolbar.
 */
export default function Toolbar( {
	query,
	onQuery,
	facets: all,
	compact,
	density,
	onDensity,
	onManageTags,
	total,
} ) {
	const [ sheet, setSheet ] = useState( false );
	const count = filterCount( query );

	return (
		<div className="sd-toolbar">
			<Search
				value={ query.search }
				onChange={ ( search ) => onQuery( { search } ) }
			/>
			{ ! compact &&
				all.map( ( facet ) => (
					<FacetMenu
						key={ facet.key }
						facet={ facet }
						query={ query }
						onQuery={ onQuery }
						onManageTags={ onManageTags }
					/>
				) ) }
			{ compact && (
				<button
					type="button"
					className={ cx(
						'sd-filter-button',
						count > 0 && 'is-active'
					) }
					onClick={ () => setSheet( true ) }
				>
					<Icon name="filter" size={ 15 } stroke={ 1.8 } />
					{ __( 'Filters', 'scriptdock' ) }
					{ count > 0 && (
						<span className="sd-filter-button__count">
							{ count }
						</span>
					) }
				</button>
			) }
			{ ! compact && (
				<ActiveChips
					facets={ all }
					query={ query }
					onQuery={ onQuery }
				/>
			) }
			{ ! compact && (
				<div className="sd-toolbar__end">
					<SortMenu query={ query } onQuery={ onQuery } />
					<Density density={ density } onDensity={ onDensity } />
				</div>
			) }
			{ compact && (
				<FilterSheet
					open={ sheet }
					onClose={ () => setSheet( false ) }
					facets={ all }
					query={ query }
					onQuery={ onQuery }
					total={ total }
				/>
			) }
		</div>
	);
}

/**
 * The search field. Typing waits a moment before it searches.
 *
 * @param {Object}   props          Props.
 * @param {string}   props.value    Current search.
 * @param {Function} props.onChange Called with the new search.
 * @return {Element} The field.
 */
function Search( { value, onChange } ) {
	const [ text, setText ] = useState( value );
	const latest = useRef( value );

	// Follow outside changes (Clear filters).
	useEffect( () => {
		if ( value !== latest.current ) {
			latest.current = value;
			setText( value );
		}
	}, [ value ] );

	useEffect( () => {
		if ( text === latest.current ) {
			return;
		}
		const timer = window.setTimeout( () => {
			latest.current = text;
			onChange( text );
		}, 300 );
		return () => window.clearTimeout( timer );
	}, [ text, onChange ] );

	return (
		<div className="sd-toolbar__search" role="search">
			<SearchField
				label={ __( 'Search snippets', 'scriptdock' ) }
				placeholder={ __(
					'Search snippets, code or tags…',
					'scriptdock'
				) }
				value={ text }
				onChange={ setText }
			/>
		</div>
	);
}

/**
 * An option's count, as text for a checkbox hint.
 *
 * @param {Object} option Option.
 * @return {string|undefined} Hint.
 */
function hintText( option ) {
	return option.hint === undefined ? undefined : String( option.hint );
}

/**
 * The query change that sets a facet's value.
 *
 * @param {Object} facet Facet.
 * @param {*}      value New value: a list, or one value for single facets.
 * @return {Object} Query change.
 */
function setFacet( facet, value ) {
	return { [ facet.key ]: value };
}

/**
 * Menu items for a facet: checkboxes, or radios for single-choice facets.
 *
 * @param {Object}   facet   Facet.
 * @param {Object}   query   Query.
 * @param {Function} onQuery Changes the query.
 * @return {Array} Items.
 */
function facetItems( facet, query, onQuery ) {
	const item = ( option ) => {
		if ( facet.single ) {
			return {
				type: 'radio',
				label: option.label,
				checked: query[ facet.key ] === option.value,
				onClick: () => onQuery( setFacet( facet, option.value ) ),
			};
		}
		const selected = query[ facet.key ];
		return {
			type: 'checkbox',
			key: String( option.value ),
			label: option.label,
			hint: option.hint,
			checked: selected.includes( option.value ),
			onChange: ( on ) =>
				onQuery(
					setFacet(
						facet,
						on
							? [ ...selected, option.value ]
							: selected.filter(
									( value ) => value !== option.value
								)
					)
				),
		};
	};
	if ( facet.groups ) {
		return facet.groups.map( ( group ) => ( {
			group: group.label,
			items: group.options.map( item ),
		} ) );
	}
	const items = facet.options.map( item );
	if ( facet.single ) {
		items.unshift( {
			type: 'radio',
			label: __( 'Any date', 'scriptdock' ),
			checked: ! query[ facet.key ],
			onClick: () => onQuery( setFacet( facet, '' ) ),
		} );
	}
	return items;
}

/**
 * One filter's button and menu.
 *
 * @param {Object}   props              Props.
 * @param {Object}   props.facet        Facet.
 * @param {Object}   props.query        Query.
 * @param {Function} props.onQuery      Changes the query.
 * @param {Function} props.onManageTags Opens Manage tags.
 * @return {Element} The menu.
 */
function FacetMenu( { facet, query, onQuery, onManageTags } ) {
	const value = query[ facet.key ];
	let count = value.length;
	if ( facet.single ) {
		count = value ? 1 : 0;
	}
	let items = facetItems( facet, query, onQuery );
	if ( facet.key === 'tag' ) {
		if ( ! items.length ) {
			items = [
				{
					label: __( 'No tags yet', 'scriptdock' ),
					disabled: true,
				},
			];
		}
		items = [
			...items,
			{ separator: true },
			{
				label: __( 'Manage tags…', 'scriptdock' ),
				icon: 'settings',
				onClick: onManageTags,
			},
		];
	} else if ( facet.single && ! facet.options.length ) {
		items = [
			{ label: __( 'No dates yet', 'scriptdock' ), disabled: true },
		];
	}
	if ( count > 0 ) {
		items = [
			...items,
			{ separator: true },
			{
				label: __( 'Clear', 'scriptdock' ),
				icon: 'close',
				onClick: () =>
					onQuery( setFacet( facet, facet.single ? '' : [] ) ),
			},
		];
	}
	return (
		<DropdownMenu
			label={ facet.label }
			items={ items }
			menuClassName="sd-filter-menu"
			renderToggle={ ( props ) => (
				<button
					type="button"
					className={ cx(
						'sd-filter-button',
						count > 0 && 'is-active'
					) }
					data-facet={ facet.key }
					{ ...props }
				>
					{ facet.label }
					{ count > 0 && (
						<span className="sd-filter-button__count">
							{ count }
							<span className="sd-visually-hidden">
								{ ' ' + __( 'selected', 'scriptdock' ) }
							</span>
						</span>
					) }
					<Icon name="chevron-down" size={ 13 } stroke={ 2.4 } />
				</button>
			) }
		/>
	);
}

/**
 * A removable chip for each active filter.
 *
 * @param {Object}   props         Props.
 * @param {Array}    props.facets  Facets.
 * @param {Object}   props.query   Query.
 * @param {Function} props.onQuery Changes the query.
 * @return {Element|null} The chips.
 */
function ActiveChips( { facets: all, query, onQuery } ) {
	const chips = [];
	all.forEach( ( facet ) => {
		const values = facet.single
			? ( query[ facet.key ] && [ query[ facet.key ] ] ) || []
			: query[ facet.key ];
		values.forEach( ( value ) => {
			const option = facet.options.find(
				( item ) => item.value === value
			);
			const label = option ? option.label : String( value );
			chips.push( {
				key: `${ facet.key }-${ value }`,
				label,
				remove: () =>
					onQuery(
						setFacet(
							facet,
							facet.single
								? ''
								: query[ facet.key ].filter(
										( item ) => item !== value
									)
						)
					),
			} );
		} );
	} );
	if ( ! chips.length ) {
		return null;
	}
	return (
		<ul
			className="sd-chips"
			aria-label={ __( 'Active filters', 'scriptdock' ) }
		>
			{ chips.map( ( chip ) => (
				<li key={ chip.key } className="sd-chip">
					{ chip.label }
					<button
						type="button"
						className="sd-chip__remove"
						onClick={ chip.remove }
						aria-label={ sprintf(
							/* translators: %s: filter value, for example "Site header". */
							__( 'Remove %s filter', 'scriptdock' ),
							chip.label
						) }
					>
						<Icon name="close" size={ 9 } stroke={ 3.2 } />
					</button>
				</li>
			) ) }
		</ul>
	);
}

/**
 * The sort menu: what to sort by, then which way.
 *
 * @param {Object}   props         Props.
 * @param {Object}   props.query   Query.
 * @param {Function} props.onQuery Changes the query.
 * @return {Element} The menu.
 */
function SortMenu( { query, onQuery } ) {
	const current =
		SORTS.find( ( sort ) => sort.value === query.orderby ) || SORTS[ 0 ];
	const dates = [ 'modified', 'created' ].includes( query.orderby );
	const order = query.order || ( dates ? 'desc' : 'asc' );
	return (
		<DropdownMenu
			label={ __( 'Sort by', 'scriptdock' ) }
			align="end"
			renderToggle={ ( props ) => (
				<button type="button" className="sd-filter-button" { ...props }>
					<span className="sd-visually-hidden">
						{ __( 'Sort by', 'scriptdock' ) + ' ' }
					</span>
					{ current.label }
					<Icon name="chevron-down" size={ 13 } stroke={ 2.4 } />
				</button>
			) }
			items={ [
				...SORTS.map( ( sort ) => ( {
					type: 'radio',
					label: sort.label,
					checked: sort.value === query.orderby,
					onClick: () =>
						onQuery( { orderby: sort.value, order: '' } ),
				} ) ),
				{ separator: true },
				{
					type: 'radio',
					label: dates
						? __( 'Newest first', 'scriptdock' )
						: __( 'Ascending', 'scriptdock' ),
					checked: order === ( dates ? 'desc' : 'asc' ),
					onClick: () => onQuery( { order: dates ? 'desc' : 'asc' } ),
				},
				{
					type: 'radio',
					label: dates
						? __( 'Oldest first', 'scriptdock' )
						: __( 'Descending', 'scriptdock' ),
					checked: order === ( dates ? 'asc' : 'desc' ),
					onClick: () => onQuery( { order: dates ? 'asc' : 'desc' } ),
				},
			] }
		/>
	);
}

/**
 * Comfortable or compact rows.
 *
 * @param {Object}   props           Props.
 * @param {string}   props.density   Current density.
 * @param {Function} props.onDensity Called with the new density.
 * @return {Element} The toggle.
 */
function Density( { density, onDensity } ) {
	return (
		<div
			className="sd-density"
			role="group"
			aria-label={ __( 'Row density', 'scriptdock' ) }
		>
			<IconButton
				icon="menu"
				label={ __( 'Comfortable rows', 'scriptdock' ) }
				variant={ density === 'comfortable' ? 'ink' : 'ghost' }
				size="sm"
				aria-pressed={ density === 'comfortable' }
				onClick={ () => onDensity( 'comfortable' ) }
			/>
			<IconButton
				icon="density-compact"
				label={ __( 'Compact rows', 'scriptdock' ) }
				variant={ density === 'compact' ? 'ink' : 'ghost' }
				size="sm"
				aria-pressed={ density === 'compact' }
				onClick={ () => onDensity( 'compact' ) }
			/>
		</div>
	);
}

/**
 * Every filter and the sort, in a bottom sheet for narrow screens. Changes
 * apply as they are made.
 *
 * @param {Object}   props         Props.
 * @param {boolean}  props.open    Open.
 * @param {Function} props.onClose Closes the sheet.
 * @param {Array}    props.facets  Facets.
 * @param {Object}   props.query   Query.
 * @param {Function} props.onQuery Changes the query.
 * @param {number}   props.total   Snippets matching.
 * @return {Element} The sheet.
 */
function FilterSheet( { open, onClose, facets: all, query, onQuery, total } ) {
	const clearable = filterCount( query ) > 0;
	return (
		<BottomSheet
			open={ open }
			onClose={ onClose }
			title={ __( 'Filters', 'scriptdock' ) }
		>
			<div className="sd-filter-sheet">
				{ all.map( ( facet ) => (
					<fieldset
						key={ facet.key }
						className="sd-filter-sheet__group"
					>
						<legend>{ facet.label }</legend>
						{ facet.single && (
							<Radio
								name={ `sd-sheet-${ facet.key }` }
								label={ __( 'Any date', 'scriptdock' ) }
								checked={ ! query[ facet.key ] }
								onChange={ () =>
									onQuery( setFacet( facet, '' ) )
								}
							/>
						) }
						{ facet.options.map( ( option ) =>
							facet.single ? (
								<Radio
									key={ option.value }
									name={ `sd-sheet-${ facet.key }` }
									label={ option.label }
									checked={
										query[ facet.key ] === option.value
									}
									onChange={ () =>
										onQuery(
											setFacet( facet, option.value )
										)
									}
								/>
							) : (
								<Checkbox
									key={ option.value }
									label={ option.label }
									hint={ hintText( option ) }
									checked={ query[ facet.key ].includes(
										option.value
									) }
									onChange={ ( on ) =>
										onQuery(
											setFacet(
												facet,
												on
													? [
															...query[
																facet.key
															],
															option.value,
														]
													: query[ facet.key ].filter(
															( value ) =>
																value !==
																option.value
														)
											)
										)
									}
								/>
							)
						) }
					</fieldset>
				) ) }
				<fieldset className="sd-filter-sheet__group">
					<legend>{ __( 'Sort by', 'scriptdock' ) }</legend>
					{ SORTS.map( ( sort ) => (
						<Radio
							key={ sort.value }
							name="sd-sheet-sort"
							label={ sort.label }
							checked={ query.orderby === sort.value }
							onChange={ () =>
								onQuery( { orderby: sort.value, order: '' } )
							}
						/>
					) ) }
				</fieldset>
			</div>
			<div className="sd-filter-sheet__footer">
				{ clearable && (
					<Button
						onClick={ () =>
							onQuery( {
								type: [],
								location: [],
								tag: [],
								targeting: [],
								month: '',
							} )
						}
					>
						{ __( 'Clear filters', 'scriptdock' ) }
					</Button>
				) }
				<Button variant="primary" onClick={ onClose }>
					{ sprintf(
						/* translators: %d: number of snippets. */
						_n(
							'Show %d snippet',
							'Show %d snippets',
							total,
							'scriptdock'
						),
						total
					) }
				</Button>
			</div>
		</BottomSheet>
	);
}
