/**
 * sd-Combobox and sd-TokenInput: text inputs with a listbox of suggestions,
 * following the WAI-ARIA 1.2 combobox pattern.
 *
 * Suggestions come from a list or from an async search (`onSearch`), which
 * is debounced and shows "Searching…" while it runs.
 */
import { useEffect, useMemo, useRef, useState } from '@wordpress/element';
import { useInstanceId } from '@wordpress/compose';
import { __, sprintf } from '@wordpress/i18n';
import Icon from './icon';
import { Field } from './form';
import { cx, useOutsidePointer } from './utils';

/**
 * Suggestion state shared by both components.
 *
 * @param {Object}   options             Options.
 * @param {string}   options.query       Current text.
 * @param {Array}    options.suggestions Static suggestions.
 * @param {Function} options.onSearch    Async search: query => Promise<Array>.
 * @param {Array}    options.exclude     Values to leave out.
 * @return {Object} { items, loading }.
 */
function useSuggestions( { query, suggestions, onSearch, exclude = [] } ) {
	const [ found, setFound ] = useState( [] );
	const [ loading, setLoading ] = useState( false );
	const request = useRef( 0 );

	useEffect( () => {
		if ( ! onSearch ) {
			return;
		}
		const id = ++request.current;
		setLoading( true );
		const timer = setTimeout( () => {
			Promise.resolve( onSearch( query ) )
				.then( ( results ) => {
					if ( id === request.current ) {
						setFound( results || [] );
					}
				} )
				.catch( () => {
					if ( id === request.current ) {
						setFound( [] );
					}
				} )
				.finally( () => {
					if ( id === request.current ) {
						setLoading( false );
					}
				} );
		}, 250 );
		return () => clearTimeout( timer );
	}, [ query, onSearch ] );

	const items = useMemo( () => {
		const source = onSearch ? found : suggestions || [];
		const needle = query.trim().toLowerCase();
		return source.filter(
			( item ) =>
				! exclude.includes( item.value ) &&
				( onSearch ||
					! needle ||
					String( item.label ).toLowerCase().includes( needle ) )
		);
	}, [ found, suggestions, query, onSearch, exclude ] );

	return { items, loading };
}

/**
 * Keyboard and pointer handling for the popup.
 *
 * @param {Object}   options                  Options.
 * @param {Array}    options.items            Rendered options, in order.
 * @param {Function} options.onChoose         Called with the chosen item.
 * @param {Function} options.onEmptyBackspace Backspace in an empty input.
 * @return {Object} State and handlers.
 */
function useListboxKeys( { items, onChoose, onEmptyBackspace } ) {
	const [ open, setOpen ] = useState( false );
	const [ active, setActive ] = useState( -1 );

	useEffect( () => {
		setActive( items.length ? 0 : -1 );
	}, [ items.length ] );

	const onKeyDown = ( event ) => {
		switch ( event.key ) {
			case 'ArrowDown':
				event.preventDefault();
				if ( ! open ) {
					setOpen( true );
					return;
				}
				setActive( ( index ) =>
					Math.min( items.length - 1, index + 1 )
				);
				break;
			case 'ArrowUp':
				event.preventDefault();
				setActive( ( index ) => Math.max( 0, index - 1 ) );
				break;
			case 'Enter':
				if ( open && items[ active ] ) {
					event.preventDefault();
					onChoose( items[ active ] );
				}
				break;
			case 'Escape':
				if ( open ) {
					event.preventDefault();
					event.stopPropagation();
					setOpen( false );
				}
				break;
			case 'Backspace':
				if ( ! event.currentTarget.value && onEmptyBackspace ) {
					onEmptyBackspace();
				}
				break;
			case 'Tab':
				setOpen( false );
				break;
		}
	};

	return { open, setOpen, active, setActive, onKeyDown };
}

/**
 * The popup list.
 *
 * @param {Object}   props          Props.
 * @param {string}   props.id       Listbox id.
 * @param {Array}    props.items    Items: { value, label, meta, count,
 *                                  create }.
 * @param {number}   props.active   Active index.
 * @param {Function} props.onChoose Called with the chosen item.
 * @param {Function} props.onHover  Called with an index.
 * @param {boolean}  props.loading  Searching.
 * @param {string}   props.status   Status line ("Searching 312 items…").
 * @param {string}   props.empty    Text when nothing matches.
 * @param {boolean}  props.neutral  Neutral highlight (tags).
 * @param {boolean}  props.hidden   Hidden.
 * @param {string}   props.label    Accessible name.
 * @return {Element} The listbox.
 */
function Listbox( {
	id,
	items,
	active,
	onChoose,
	onHover,
	loading,
	status,
	empty,
	neutral,
	hidden,
	label,
} ) {
	return (
		<div
			className={ cx( 'sd-listbox', neutral && 'sd-listbox--neutral' ) }
			hidden={ hidden }
		>
			<ul id={ id } role="listbox" aria-label={ label }>
				{ items.map( ( item, index ) => (
					// The input handles keys; options respond to the pointer.
					// eslint-disable-next-line jsx-a11y/click-events-have-key-events
					<li
						key={ item.create ? '__create' : item.value }
						id={ `${ id }-${ index }` }
						role="option"
						aria-selected={ index === active }
						className={ cx(
							'sd-listbox__option',
							index === active && 'is-active',
							item.create && 'sd-listbox__create'
						) }
						onPointerDown={ ( event ) => event.preventDefault() }
						onClick={ () => onChoose( item ) }
						onPointerEnter={ () => onHover( index ) }
					>
						<span>{ item.label }</span>
						{ item.meta && (
							<span className="sd-listbox__meta">
								{ item.meta }
							</span>
						) }
						{ item.count !== undefined && (
							<span className="sd-listbox__count">
								{ item.count }
							</span>
						) }
					</li>
				) ) }
			</ul>
			{ loading && (
				<div className="sd-listbox__status" role="status">
					<span className="sd-spinner" aria-hidden="true" />
					{ status || __( 'Searching…', 'scriptdock' ) }
				</div>
			) }
			{ ! loading && ! items.length && empty && (
				<div className="sd-listbox__status" role="status">
					{ empty }
				</div>
			) }
		</div>
	);
}

/**
 * Search and pick one item ("Specific post or page").
 *
 * @param {Object}   props             Props.
 * @param {string}   props.label       Label.
 * @param {boolean}  props.hideLabel   Visually hide the label.
 * @param {string}   props.placeholder Placeholder.
 * @param {Array}    props.suggestions Static items: { value, label, meta }.
 * @param {Function} props.onSearch    Async search: query => Promise<items>.
 * @param {Function} props.onSelect    Called with the chosen item.
 * @param {string}   props.status      Loading line.
 * @param {string}   props.help        Help text.
 * @return {Element} The combobox.
 */
export function Combobox( {
	label,
	hideLabel = false,
	placeholder,
	suggestions,
	onSearch,
	onSelect,
	status,
	help,
} ) {
	const [ query, setQuery ] = useState( '' );
	const wrap = useRef();
	const listId = useInstanceId( Combobox, 'sd-combobox-list' );
	const { items, loading } = useSuggestions( {
		query,
		suggestions,
		onSearch,
	} );
	const choose = ( item ) => {
		onSelect( item );
		setQuery( '' );
		keys.setOpen( false );
	};
	const keys = useListboxKeys( { items, onChoose: choose } );
	useOutsidePointer( [ wrap ], () => keys.setOpen( false ), keys.open );
	// Expanded only while the list is actually on screen.
	const shown = keys.open && ( items.length > 0 || loading || !! query );

	return (
		<Field label={ label } hideLabel={ hideLabel } help={ help }>
			{ ( { id, describedBy } ) => (
				<div className="sd-combobox" ref={ wrap }>
					<span className="sd-input-wrap sd-input-wrap--start">
						<span
							className="sd-input-wrap__start"
							aria-hidden="true"
						>
							<Icon name="search" size={ 17 } />
						</span>
						<input
							id={ id }
							type="text"
							role="combobox"
							className="sd-input"
							value={ query }
							placeholder={ placeholder }
							autoComplete="off"
							aria-autocomplete="list"
							aria-expanded={ shown }
							aria-controls={ listId }
							aria-describedby={ describedBy }
							aria-activedescendant={
								shown && keys.active >= 0
									? `${ listId }-${ keys.active }`
									: undefined
							}
							onChange={ ( event ) => {
								setQuery( event.target.value );
								keys.setOpen( true );
							} }
							onFocus={ () => keys.setOpen( true ) }
							onKeyDown={ keys.onKeyDown }
						/>
					</span>
					<Listbox
						id={ listId }
						label={ label }
						items={ items }
						active={ keys.active }
						onChoose={ choose }
						onHover={ keys.setActive }
						loading={ loading }
						status={ status }
						empty={
							query
								? sprintf(
										/* translators: %s: what the user typed. */
										__(
											'Nothing matches “%s”',
											'scriptdock'
										),
										query
									)
								: null
						}
						hidden={ ! shown }
					/>
				</div>
			) }
		</Field>
	);
}

/**
 * Chips plus an input: tags, roles, products. Can create new values.
 *
 * @param {Object}   props             Props.
 * @param {string}   props.label       Label.
 * @param {boolean}  props.hideLabel   Visually hide the label.
 * @param {Array}    props.value       Chosen items: { value, label }.
 * @param {Function} props.onChange    Called with the new array.
 * @param {Array}    props.suggestions Static items: { value, label, count }.
 * @param {Function} props.onSearch    Async search: query => Promise<items>.
 * @param {boolean}  props.allowCreate Offer "Create “…”".
 * @param {string}   props.placeholder Placeholder.
 * @param {string}   props.help        Help text.
 * @param {boolean}  props.neutral     Neutral highlight (tags).
 * @param {boolean}  props.disabled    Shows the tokens without changing them.
 * @return {Element} The token input.
 */
export function TokenInput( {
	label,
	hideLabel = false,
	value = [],
	onChange,
	suggestions,
	onSearch,
	allowCreate = false,
	placeholder,
	help,
	neutral = true,
	disabled = false,
} ) {
	const [ query, setQuery ] = useState( '' );
	const wrap = useRef();
	const input = useRef();
	const listId = useInstanceId( TokenInput, 'sd-tokens-list' );
	const chosen = value.map( ( item ) => item.value );
	const { items: found, loading } = useSuggestions( {
		query,
		suggestions,
		onSearch,
		exclude: chosen,
	} );

	const trimmed = query.trim();
	const exists = [ ...found, ...value ].some(
		( item ) => String( item.label ).toLowerCase() === trimmed.toLowerCase()
	);
	const items =
		allowCreate && trimmed && ! exists
			? [
					...found,
					{
						value: trimmed,
						label: sprintf(
							/* translators: %s: the new tag's name. */
							__( '+ Create “%s”', 'scriptdock' ),
							trimmed
						),
						create: true,
					},
				]
			: found;

	const add = ( item ) => {
		const token = item.create
			? { value: item.value, label: item.value }
			: { value: item.value, label: item.label };
		onChange( [ ...value, token ] );
		setQuery( '' );
		input.current.focus();
	};
	const remove = ( token ) =>
		onChange( value.filter( ( item ) => item.value !== token.value ) );

	const keys = useListboxKeys( {
		items,
		onChoose: add,
		onEmptyBackspace: () =>
			value.length && remove( value[ value.length - 1 ] ),
	} );
	useOutsidePointer( [ wrap ], () => keys.setOpen( false ), keys.open );
	// Expanded only while the list is actually on screen.
	const shown = keys.open && ( items.length > 0 || loading );

	return (
		<Field label={ label } hideLabel={ hideLabel } help={ help }>
			{ ( { id, describedBy } ) => (
				<div className="sd-tokens" ref={ wrap }>
					{ /* A click on the box's blank space focuses the input. */ }
					{ /* eslint-disable-next-line jsx-a11y/click-events-have-key-events, jsx-a11y/no-static-element-interactions */ }
					<div
						className="sd-tokens__box"
						onClick={ ( event ) => {
							if ( event.target === event.currentTarget ) {
								input.current.focus();
							}
						} }
					>
						{ value.map( ( token ) => (
							<span className="sd-token" key={ token.value }>
								<span className="sd-token__label">
									{ token.label }
								</span>
								{ ! disabled && (
									<button
										type="button"
										className="sd-token__remove"
										aria-label={ sprintf(
											/* translators: %s: tag, role or product name. */
											__( 'Remove %s', 'scriptdock' ),
											token.label
										) }
										onClick={ () => remove( token ) }
									>
										<Icon
											name="close"
											size={ 9 }
											stroke={ 3 }
										/>
									</button>
								) }
							</span>
						) ) }
						<input
							ref={ input }
							id={ id }
							type="text"
							role="combobox"
							className="sd-tokens__input"
							value={ query }
							disabled={ disabled }
							placeholder={
								value.length ? undefined : placeholder
							}
							autoComplete="off"
							aria-autocomplete="list"
							aria-expanded={ shown }
							aria-controls={ listId }
							aria-describedby={ describedBy }
							aria-activedescendant={
								shown && keys.active >= 0
									? `${ listId }-${ keys.active }`
									: undefined
							}
							onChange={ ( event ) => {
								setQuery( event.target.value );
								keys.setOpen( true );
							} }
							onFocus={ () => keys.setOpen( true ) }
							onKeyDown={ keys.onKeyDown }
						/>
					</div>
					<Listbox
						id={ listId }
						label={ label }
						items={ items }
						active={ keys.active }
						onChoose={ add }
						onHover={ keys.setActive }
						loading={ loading }
						neutral={ neutral }
						hidden={ ! shown }
					/>
				</div>
			) }
		</Field>
	);
}
