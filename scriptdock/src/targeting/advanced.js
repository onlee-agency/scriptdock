/**
 * S05f: the rule builder. Groups are joined by OR, the rules inside a group
 * by AND, which is exactly how the snippet stores them.
 *
 * Anything written here is kept as it is. The steps cannot always draw it, so
 * the review card says "Custom rules" instead of a sentence.
 */
import { __ } from '@wordpress/i18n';
import {
	Banner,
	Button,
	IconButton,
	SegmentedControl,
	Select,
	TextArea,
	TextField,
	TimeRange,
} from '../components';
import * as api from './api';
import { ChipSet, SearchChips } from './controls';

/**
 * @param {Object}   props            Props.
 * @param {Object}   props.conditions The rule set as it stands.
 * @param {Object}   props.options    Catalogue and lists.
 * @param {Function} props.onChange   Called with the new rule set.
 * @param {Function} props.onLeave    Drops the custom rules and goes back to
 *                                    the steps.
 * @return {Element} The builder.
 */
export default function AdvancedRules( {
	conditions,
	options,
	onChange,
	onLeave,
} ) {
	const catalogue = options?.catalogue || [];
	const groups = conditions.groups.length ? conditions.groups : [ [] ];

	const setGroups = ( next ) =>
		onChange( {
			enabled: next.some( ( group ) => group.length > 0 ),
			action: conditions.action,
			groups: next.filter( ( group ) => group.length > 0 ),
		} );

	const setRule = ( groupIndex, ruleIndex, change ) =>
		setGroups(
			groups.map( ( group, position ) =>
				position !== groupIndex
					? group
					: group.map( ( rule, at ) =>
							at === ruleIndex ? { ...rule, ...change } : rule
						)
			)
		);

	const addRule = ( groupIndex ) => {
		const first = catalogue[ 0 ]?.rules?.[ 0 ];
		if ( ! first ) {
			return;
		}
		setGroups(
			groups.map( ( group, position ) =>
				position === groupIndex ? [ ...group, blank( first ) ] : group
			)
		);
	};

	return (
		<div className="sd-step sd-advanced">
			<div className="sd-advanced__head">
				<SegmentedControl
					label={ __( 'What the rules do', 'scriptdock' ) }
					value={ conditions.action }
					onChange={ ( action ) =>
						onChange( { ...conditions, action } )
					}
					options={ [
						{ value: 'show', label: __( 'Show', 'scriptdock' ) },
						{ value: 'hide', label: __( 'Hide', 'scriptdock' ) },
					] }
				/>
				<p className="sd-advanced__reads">
					{ conditions.action === 'show'
						? __(
								'Show this snippet when any group matches. Inside a group, every rule must match.',
								'scriptdock'
							)
						: __(
								'Hide this snippet when any group matches. Inside a group, every rule must match.',
								'scriptdock'
							) }
				</p>
			</div>

			{ groups.map( ( group, groupIndex ) => (
				<div key={ groupIndex } className="sd-advanced__group">
					{ groupIndex > 0 && (
						<span className="sd-advanced__or">
							{ __( 'OR', 'scriptdock' ) }
						</span>
					) }
					<div className="sd-advanced__card">
						{ group.length === 0 && (
							<p className="sd-advanced__empty">
								{ __(
									'No rules in this group yet.',
									'scriptdock'
								) }
							</p>
						) }
						{ group.map( ( rule, ruleIndex ) => (
							<div
								key={ ruleIndex }
								className="sd-advanced__rule"
							>
								{ ruleIndex > 0 && (
									<span className="sd-advanced__and">
										{ __( 'AND', 'scriptdock' ) }
									</span>
								) }
								<RuleRow
									rule={ rule }
									catalogue={ catalogue }
									options={ options }
									onChange={ ( change ) =>
										setRule( groupIndex, ruleIndex, change )
									}
									onRemove={ () =>
										setGroups(
											groups.map( ( entry, position ) =>
												position === groupIndex
													? entry.filter(
															( item, at ) =>
																at !== ruleIndex
														)
													: entry
											)
										)
									}
								/>
							</div>
						) ) }
						<Button
							variant="ghost"
							size="sm"
							icon="plus"
							onClick={ () => addRule( groupIndex ) }
						>
							{ __( 'Add rule (AND)', 'scriptdock' ) }
						</Button>
					</div>
				</div>
			) ) }

			<Button
				variant="secondary"
				icon="plus"
				onClick={ () => setGroups( [ ...groups, [] ] ) }
			>
				{ __( 'Add rule group', 'scriptdock' ) }
			</Button>

			<Banner tone="info" inline>
				{ __(
					'Rules set here show as "Custom rules" on the review card: they cannot always be drawn as a plain sentence.',
					'scriptdock'
				) }
				<Button variant="ghost" size="sm" onClick={ onLeave }>
					{ __( 'Clear them and use the steps', 'scriptdock' ) }
				</Button>
			</Banner>
		</div>
	);
}

/**
 * One rule: what to check, how to compare, and what to compare with.
 *
 * @param {Object}   props           Props.
 * @param {Object}   props.rule      Rule.
 * @param {Array}    props.catalogue Condition catalogue.
 * @param {Object}   props.options   Lists and operator labels.
 * @param {Function} props.onChange  Called with the change.
 * @param {Function} props.onRemove  Removes the rule.
 * @return {Element} The row.
 */
function RuleRow( { rule, catalogue, options, onChange, onRemove } ) {
	const entry = find( catalogue, rule );
	const operators = entry?.operators || [ 'is', 'is_not' ];
	const labels = options?.operators || {};

	return (
		<div className="sd-advanced__row">
			<Select
				label={ __( 'Rule', 'scriptdock' ) }
				hideLabel
				value={ entry?.id || rule.rule }
				onChange={ ( id ) => {
					const next = catalogue
						.flatMap( ( group ) => group.rules )
						.find( ( item ) => item.id === id );
					if ( next ) {
						onChange( blank( next ) );
					}
				} }
				groups={ catalogue.map( ( group ) => ( {
					label: group.label,
					options: group.rules.map( ( item ) => ( {
						value: item.id,
						label: item.locked
							? `${ item.label } — ${ __( 'not available', 'scriptdock' ) }`
							: item.label,
						disabled: !! item.locked,
					} ) ),
				} ) ) }
			/>
			<Select
				label={ __( 'Comparison', 'scriptdock' ) }
				hideLabel
				value={ rule.operator }
				onChange={ ( operator ) => onChange( { operator } ) }
				options={ operators.map( ( value ) => ( {
					value,
					label: labels[ value ] || value,
				} ) ) }
			/>
			<div className="sd-advanced__value">
				<Value
					rule={ rule }
					entry={ entry }
					options={ options }
					onChange={ onChange }
				/>
			</div>
			<IconButton
				icon="trash"
				variant="ghost"
				size="sm"
				label={ __( 'Remove this rule', 'scriptdock' ) }
				onClick={ onRemove }
			/>
		</div>
	);
}

/**
 * The control a rule's value needs.
 *
 * @param {Object}   props          Props.
 * @param {Object}   props.rule     Rule.
 * @param {Object}   props.entry    Catalogue entry.
 * @param {Object}   props.options  Lists.
 * @param {Function} props.onChange Called with the change.
 * @return {Element|null} The control.
 */
function Value( { rule, entry, options, onChange } ) {
	const lists = options?.lists || {};
	const control = entry?.control || 'text';
	const values = Array.isArray( rule.value ) ? rule.value : [];

	if (
		[ 'exists', 'not_exists' ].includes( rule.operator ) &&
		control === 'pair'
	) {
		return (
			<TextField
				label={ __( 'Name', 'scriptdock' ) }
				hideLabel
				value={ rule.value?.key || '' }
				placeholder={ __( 'Name', 'scriptdock' ) }
				onChange={ ( key ) =>
					onChange( { value: { key, value: '' } } )
				}
			/>
		);
	}

	switch ( control ) {
		case 'chips':
			return (
				<ChipSet
					label={ entry.label }
					options={ lists[ entry.source ] || [] }
					value={ values }
					onChange={ ( next ) => onChange( { value: next } ) }
				/>
			);

		case 'search':
			return (
				<SearchChips
					label={ entry.label }
					value={ values.map( Number ) }
					onChange={ ( next ) =>
						onChange( { value: next.map( String ) } )
					}
					search={ ( words ) => searchFor( entry, words ) }
					lookup={ ( ids ) => lookupFor( entry, ids ) }
					toOption={ ( item ) => ( {
						value: item.id,
						label: item.title || item.name,
					} ) }
				/>
			);

		case 'patterns':
			return (
				<TextArea
					label={ __( 'One per line', 'scriptdock' ) }
					hideLabel
					rows={ 3 }
					value={ values.join( '\n' ) }
					placeholder={ '/shop/\n/checkout/' }
					onChange={ ( text ) =>
						onChange( {
							value: text
								.split( '\n' )
								.map( ( line ) => line.trim() )
								.filter( Boolean ),
						} )
					}
				/>
			);

		case 'pair':
			return (
				<div className="sd-advanced__pair">
					<TextField
						label={ __( 'Name', 'scriptdock' ) }
						hideLabel
						value={ rule.value?.key || '' }
						placeholder={ __( 'Name', 'scriptdock' ) }
						onChange={ ( key ) =>
							onChange( {
								value: { key, value: rule.value?.value || '' },
							} )
						}
					/>
					<TextField
						label={ __( 'Value', 'scriptdock' ) }
						hideLabel
						value={ rule.value?.value || '' }
						placeholder={ __( 'Value', 'scriptdock' ) }
						onChange={ ( value ) =>
							onChange( {
								value: { key: rule.value?.key || '', value },
							} )
						}
					/>
				</div>
			);

		case 'number':
			return rule.operator === 'between' ? (
				<div className="sd-advanced__pair">
					<TextField
						label={ __( 'From', 'scriptdock' ) }
						hideLabel
						type="number"
						value={ rule.value?.from || '' }
						onChange={ ( from ) =>
							onChange( {
								value: { from, to: rule.value?.to || '' },
							} )
						}
					/>
					<TextField
						label={ __( 'To', 'scriptdock' ) }
						hideLabel
						type="number"
						value={ rule.value?.to || '' }
						onChange={ ( to ) =>
							onChange( {
								value: { from: rule.value?.from || '', to },
							} )
						}
					/>
				</div>
			) : (
				<TextField
					label={ __( 'Amount', 'scriptdock' ) }
					hideLabel
					type="number"
					value={ values[ 0 ] || '' }
					onChange={ ( amount ) => onChange( { value: [ amount ] } ) }
				/>
			);

		case 'time':
			return (
				<TimeRange
					label={ __( 'Between', 'scriptdock' ) }
					from={ rule.value?.from || '' }
					to={ rule.value?.to || '' }
					onChange={ ( range ) => onChange( { value: range } ) }
				/>
			);

		case 'date':
			return (
				<TextField
					label={ __( 'Date', 'scriptdock' ) }
					hideLabel
					type="datetime-local"
					value={ values[ 0 ] || '' }
					onChange={ ( date ) => onChange( { value: [ date ] } ) }
				/>
			);

		case 'boolean':
			return null;

		default:
			return (
				<TextField
					label={ entry?.label || __( 'Value', 'scriptdock' ) }
					hideLabel
					value={ values[ 0 ] || '' }
					onChange={ ( value ) => onChange( { value: [ value ] } ) }
				/>
			);
	}
}

/**
 * An empty rule of a kind.
 *
 * @param {Object} entry Catalogue entry.
 * @return {Object} Rule.
 */
function blank( entry ) {
	const value = { pair: { key: '', value: '' }, time: { from: '', to: '' } }[
		entry.control
	];
	return {
		rule: entry.rule,
		operator: entry.operators[ 0 ],
		value: value !== undefined ? value : [],
	};
}

/**
 * The catalogue entry a rule belongs to. The taxonomy entries share one rule,
 * so the values decide which one it is.
 *
 * @param {Array}  catalogue Catalogue.
 * @param {Object} rule      Rule.
 * @return {Object|undefined} The entry.
 */
function find( catalogue, rule ) {
	const all = catalogue.flatMap( ( group ) => group.rules );
	return (
		all.find( ( entry ) => entry.id === rule.rule ) ||
		all.find( ( entry ) => entry.rule === rule.rule )
	);
}

/**
 * Searches whatever a rule picks from.
 *
 * @param {Object} entry Catalogue entry.
 * @param {string} words Search words.
 * @return {Promise} Items.
 */
function searchFor( entry, words ) {
	switch ( entry.source ) {
		case 'terms':
			return api.getTerms( {
				taxonomy: entry.taxonomy || '',
				search: words,
			} );
		case 'product_terms':
			return api.getTerms( { taxonomy: 'product_cat', search: words } );
		case 'users':
			return api.getUsers( { search: words } );
		case 'products':
			return api.getContent( {
				type: 'product',
				search: words,
				per_page: 10,
			} );
		default:
			return api.getContent( { search: words, per_page: 10 } );
	}
}

/**
 * Looks up what a rule already holds, to name it.
 *
 * @param {Object} entry Catalogue entry.
 * @param {Array}  ids   IDs.
 * @return {Promise} Items.
 */
function lookupFor( entry, ids ) {
	switch ( entry.source ) {
		case 'terms':
		case 'product_terms':
			return api.getTerms( { include: ids } );
		case 'users':
			return api.getUsers( { include: ids } );
		default:
			return api.getContent( { include: ids } );
	}
}
