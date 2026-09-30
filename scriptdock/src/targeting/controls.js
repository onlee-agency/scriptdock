/**
 * The value controls a condition needs: chips to toggle, searched chips for
 * things a site has thousands of, and rows of key-and-value pairs.
 */
import { useEffect, useRef, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import {
	Icon,
	IconButton,
	SearchField,
	Select,
	TextField,
	cx,
} from '../components';

/**
 * A set of chips, any number of which can be on.
 *
 * @param {Object}   props          Props.
 * @param {string}   props.label    Group name.
 * @param {Array}    props.options  { value, label }.
 * @param {Array}    props.value    Chosen values.
 * @param {Function} props.onChange Called with the new values.
 * @param {boolean}  props.single   Whether choosing one clears the rest.
 * @return {Element} The chips.
 */
export function ChipSet( {
	label,
	options = [],
	value = [],
	onChange,
	single = false,
} ) {
	const toggle = ( entry ) => {
		if ( single ) {
			onChange( value.includes( entry ) ? [] : [ entry ] );
			return;
		}
		onChange(
			value.includes( entry )
				? value.filter( ( item ) => item !== entry )
				: [ ...value, entry ]
		);
	};

	return (
		<div className="sd-chipset" role="group" aria-label={ label }>
			{ options.map( ( option ) => {
				const on = value.includes( option.value );
				return (
					<button
						key={ option.value }
						type="button"
						className={ cx( 'sd-chipset__chip', on && 'is-on' ) }
						aria-pressed={ on }
						onClick={ () => toggle( option.value ) }
					>
						{ on && <Icon name="check" size={ 11 } stroke={ 3 } /> }
						{ option.label }
					</button>
				);
			} ) }
		</div>
	);
}

/**
 * Chips for things there are too many of to list: search, pick, and the ones
 * picked stay as chips.
 *
 * @param {Object}   props             Props.
 * @param {string}   props.label       Field name.
 * @param {Array}    props.value       Chosen IDs.
 * @param {Function} props.onChange    Called with the new IDs.
 * @param {Function} props.search      ( words ) => Promise<{ items }>.
 * @param {Function} props.lookup      ( ids ) => Promise<{ items }>.
 * @param {Function} props.toOption    ( item ) => { value, label }.
 * @param {string}   props.placeholder Placeholder for the search box.
 * @return {Element} The control.
 */
export function SearchChips( {
	label,
	value = [],
	onChange,
	search,
	lookup,
	toOption,
	placeholder,
} ) {
	const [ words, setWords ] = useState( '' );
	const [ found, setFound ] = useState( [] );
	const [ names, setNames ] = useState( {} );
	const [ open, setOpen ] = useState( false );
	const timer = useRef();
	// The caller builds these inline, so keep the latest without making the
	// search run again on every render.
	const calls = useRef( {} );
	calls.current = { search, lookup, toOption };

	// Names for what is already chosen.
	useEffect( () => {
		const missing = value.filter( ( id ) => ! names[ id ] );
		if ( ! missing.length ) {
			return;
		}
		calls.current.lookup( missing ).then( ( result ) => {
			const more = {};
			result.items.forEach( ( item ) => {
				const option = calls.current.toOption( item );
				more[ option.value ] = option.label;
			} );
			setNames( ( old ) => ( { ...old, ...more } ) );
		} );
		// Names are a cache; refetching when it fills would loop.
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ value ] );

	useEffect( () => {
		window.clearTimeout( timer.current );
		if ( ! words.trim() ) {
			setFound( [] );
			return undefined;
		}
		timer.current = window.setTimeout( () => {
			calls.current.search( words ).then( ( result ) => {
				setFound( result.items.map( calls.current.toOption ) );
				setOpen( true );
			} );
		}, 250 );
		return () => window.clearTimeout( timer.current );
	}, [ words ] );

	const add = ( option ) => {
		setNames( ( old ) => ( { ...old, [ option.value ]: option.label } ) );
		if ( ! value.includes( option.value ) ) {
			onChange( [ ...value, option.value ] );
		}
		setWords( '' );
		setOpen( false );
	};

	return (
		<div className="sd-searchchips">
			<SearchField
				label={ label }
				value={ words }
				onChange={ setWords }
				placeholder={ placeholder || __( 'Search…', 'scriptdock' ) }
			/>
			{ open && found.length > 0 && (
				<ul className="sd-searchchips__results">
					{ found.map( ( option ) => (
						<li key={ option.value }>
							<button
								type="button"
								onClick={ () => add( option ) }
							>
								{ option.label }
							</button>
						</li>
					) ) }
				</ul>
			) }
			{ value.length > 0 && (
				<ul className="sd-searchchips__chosen">
					{ value.map( ( id ) => (
						<li key={ id } className="sd-searchchips__chip">
							{ names[ id ] || `#${ id }` }
							<IconButton
								icon="close"
								size="xs"
								variant="ghost"
								label={ sprintf(
									/* translators: %s: what was picked. */
									__( 'Remove %s', 'scriptdock' ),
									names[ id ] || `#${ id }`
								) }
								onClick={ () =>
									onChange(
										value.filter(
											( entry ) => entry !== id
										)
									)
								}
							/>
						</li>
					) ) }
				</ul>
			) }
		</div>
	);
}

/**
 * Rows of key, comparison and value: custom fields, cookies, URL parameters.
 *
 * @param {Object}   props           Props.
 * @param {string}   props.label     Group name.
 * @param {Array}    props.rows      { key, value, operator }.
 * @param {Function} props.onChange  Called with the new rows.
 * @param {Array}    props.operators Operator keys allowed.
 * @param {Object}   props.labels    Operator key => label.
 * @param {string}   props.keyLabel  What the key is called.
 * @param {string}   props.addLabel  Label of the add button.
 * @return {Element} The rows.
 */
export function PairRows( {
	label,
	rows = [],
	onChange,
	operators = [ 'equals', 'not_equals', 'contains', 'exists', 'not_exists' ],
	labels = {},
	keyLabel = __( 'Name', 'scriptdock' ),
	addLabel = __( 'Add', 'scriptdock' ),
} ) {
	const set = ( index, change ) =>
		onChange(
			rows.map( ( row, position ) =>
				position === index ? { ...row, ...change } : row
			)
		);

	return (
		<div className="sd-pairs" role="group" aria-label={ label }>
			{ rows.map( ( row, index ) => (
				<div key={ index } className="sd-pairs__row">
					<TextField
						label={ keyLabel }
						hideLabel
						value={ row.key }
						placeholder={ keyLabel }
						onChange={ ( value ) => set( index, { key: value } ) }
					/>
					<Select
						label={ __( 'Comparison', 'scriptdock' ) }
						hideLabel
						value={ row.operator }
						onChange={ ( value ) =>
							set( index, { operator: value } )
						}
						options={ operators.map( ( value ) => ( {
							value,
							label: labels[ value ] || value,
						} ) ) }
					/>
					{ ! [ 'exists', 'not_exists' ].includes( row.operator ) && (
						<TextField
							label={ __( 'Value', 'scriptdock' ) }
							hideLabel
							value={ row.value }
							placeholder={ __( 'Value', 'scriptdock' ) }
							onChange={ ( value ) => set( index, { value } ) }
						/>
					) }
					<IconButton
						icon="trash"
						variant="ghost"
						size="sm"
						label={ __( 'Remove this rule', 'scriptdock' ) }
						onClick={ () =>
							onChange(
								rows.filter(
									( entry, position ) => position !== index
								)
							)
						}
					/>
				</div>
			) ) }
			<button
				type="button"
				className="sd-pairs__add"
				onClick={ () =>
					onChange( [
						...rows,
						{ key: '', value: '', operator: operators[ 0 ] },
					] )
				}
			>
				<Icon name="plus" size={ 13 } stroke={ 2 } />
				{ addLabel }
			</button>
		</div>
	);
}
