/**
 * S20: the command palette.
 *
 * One box for the two things people want from a keyboard: reach a snippet by
 * name, or do a thing without hunting for the screen it lives on. Snippets
 * are searched on the server as you type; the actions are a fixed list,
 * matched here.
 */
import {
	useCallback,
	useEffect,
	useMemo,
	useRef,
	useState,
} from '@wordpress/element';
import { __, _x } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { addQueryArgs } from '@wordpress/url';
import { Icon, Status, TypeChip, cx, isApple } from '../components';

/**
 * How long to wait after a keystroke before asking the server.
 */
const TYPING = 200;

/**
 * How many snippets to offer.
 */
const LIMIT = 6;

/**
 * @param {Object}   props         Props.
 * @param {Object}   props.boot    What the palette can do and where it goes.
 * @param {Function} props.onClose Closes the palette.
 * @return {Element} The palette.
 */
export default function App( { boot, onClose } ) {
	const [ words, setWords ] = useState( '' );
	const [ snippets, setSnippets ] = useState( [] );
	const [ active, setActive ] = useState( 0 );
	const [ searching, setSearching ] = useState( false );
	const input = useRef();
	const list = useRef();

	const commands = useMemo( () => buildCommands( boot ), [ boot ] );
	const matched = useMemo(
		() => commands.filter( ( item ) => hits( item, words ) ),
		[ commands, words ]
	);

	// Snippets first: they are what a name most likely refers to.
	const results = useMemo(
		() => [
			...snippets.map( ( snippet ) => ( {
				id: `snippet-${ snippet.id }`,
				group: __( 'Snippets', 'scriptdock' ),
				snippet,
				href: snippet.edit_url,
			} ) ),
			...matched,
		],
		[ snippets, matched ]
	);

	useEffect( () => {
		if ( input.current ) {
			input.current.focus();
		}
	}, [] );

	useEffect( () => {
		setActive( 0 );
	}, [ words ] );

	useEffect( () => {
		const controller = new window.AbortController();
		const timer = setTimeout(
			() => {
				setSearching( true );
				apiFetch( {
					path: addQueryArgs( '/scriptdock/v1/snippets', {
						view: 'all',
						per_page: LIMIT,
						search: words,
					} ),
					signal: controller.signal,
				} )
					.then( ( found ) =>
						setSnippets(
							found && Array.isArray( found.items )
								? found.items
								: []
						)
					)
					.catch( () => {} )
					.finally( () => setSearching( false ) );
			},
			words ? TYPING : 0
		);

		return () => {
			clearTimeout( timer );
			controller.abort();
		};
	}, [ words ] );

	const run = useCallback(
		( item ) => {
			if ( ! item ) {
				return;
			}
			onClose();
			window.location.href = item.href;
		},
		[ onClose ]
	);

	const onKeyDown = ( event ) => {
		if ( event.key === 'ArrowDown' || event.key === 'ArrowUp' ) {
			event.preventDefault();
			if ( ! results.length ) {
				return;
			}
			const step = event.key === 'ArrowDown' ? 1 : -1;
			setActive(
				( index ) => ( index + step + results.length ) % results.length
			);
		} else if ( event.key === 'Enter' ) {
			event.preventDefault();
			run( results[ active ] );
		} else if ( event.key === 'Home' || event.key === 'End' ) {
			event.preventDefault();
			setActive( event.key === 'Home' ? 0 : results.length - 1 );
		}
	};

	// Keep the highlighted row in view: when arrowing past the fold, and
	// when snippets arrive and push the list down under it.
	useEffect( () => {
		const node =
			list.current &&
			list.current.querySelector( '[aria-selected="true"]' );
		if ( node && node.scrollIntoView ) {
			node.scrollIntoView( { block: 'nearest' } );
		}
	}, [ active, results.length ] );

	let group = '';

	return (
		<div className="sd-palette">
			<div className="sd-palette__head">
				<Icon name="search" size={ 19 } />
				<input
					ref={ input }
					type="text"
					className="sd-palette__input"
					role="combobox"
					aria-expanded="true"
					aria-controls="sd-palette-results"
					aria-activedescendant={
						results[ active ]
							? `sd-palette-${ results[ active ].id }`
							: undefined
					}
					aria-autocomplete="list"
					aria-label={ __(
						'Search snippets and commands',
						'scriptdock'
					) }
					placeholder={ __(
						'Search snippets, or type a command…',
						'scriptdock'
					) }
					value={ words }
					onChange={ ( event ) => setWords( event.target.value ) }
					onKeyDown={ onKeyDown }
				/>
				<button
					type="button"
					className="sd-palette__close"
					aria-label={ __( 'Close', 'scriptdock' ) }
					onClick={ onClose }
				>
					<Icon name="close" size={ 13 } stroke={ 2.2 } />
				</button>
			</div>

			<div
				className="sd-palette__results"
				id="sd-palette-results"
				role="listbox"
				aria-label={ __( 'Results', 'scriptdock' ) }
				ref={ list }
			>
				{ results.length === 0 && (
					<p className="sd-palette__none" role="status">
						{ searching
							? __( 'Searching…', 'scriptdock' )
							: __(
									'Nothing matches that. Try a snippet name, or “library”, “export”, “safe mode”.',
									'scriptdock'
								) }
					</p>
				) }

				{ results.map( ( item, index ) => {
					const heading = item.group !== group ? item.group : '';
					group = item.group;
					return (
						<div key={ item.id }>
							{ heading && (
								<div
									className="sd-palette__group"
									aria-hidden="true"
								>
									{ heading }
								</div>
							) }
							{ /* An option in a combobox listbox is never focused
							     itself: the input keeps focus and drives the
							     list with aria-activedescendant, so the key
							     handling lives there. */ }
							{ /* eslint-disable-next-line jsx-a11y/click-events-have-key-events */ }
							<div
								id={ `sd-palette-${ item.id }` }
								role="option"
								aria-selected={ index === active }
								tabIndex={ -1 }
								className={ cx(
									'sd-palette__row',
									index === active && 'is-active'
								) }
								onClick={ () => run( item ) }
								onMouseMove={ () => setActive( index ) }
							>
								{ item.snippet ? (
									<Snippet snippet={ item.snippet } />
								) : (
									<>
										<Icon
											name={ item.icon }
											size={ 17 }
											stroke={ 1.7 }
										/>
										<span className="sd-palette__label">
											{ item.label }
										</span>
										{ item.note && (
											<span className="sd-palette__note">
												{ item.note }
											</span>
										) }
									</>
								) }
							</div>
						</div>
					);
				} ) }
			</div>

			<div className="sd-palette__foot">
				<span>
					<kbd className="sd-kbd">
						{ _x( '↑↓', 'keyboard keys', 'scriptdock' ) }
					</kbd>{ ' ' }
					{ __( 'navigate', 'scriptdock' ) }
				</span>
				<span>
					<kbd className="sd-kbd">
						{ _x( '↵', 'the enter key', 'scriptdock' ) }
					</kbd>{ ' ' }
					{ __( 'open', 'scriptdock' ) }
				</span>
				<span className="sd-palette__foot-end">
					<kbd className="sd-kbd">
						{ _x( 'esc', 'the escape key', 'scriptdock' ) }
					</kbd>{ ' ' }
					{ __( 'close', 'scriptdock' ) }
				</span>
			</div>
		</div>
	);
}

/**
 * A snippet result: what it is, what it is called, and whether it runs.
 *
 * @param {Object} props         Props.
 * @param {Object} props.snippet Snippet.
 * @return {Element} The row.
 */
function Snippet( { snippet } ) {
	return (
		<>
			<TypeChip type={ snippet.type } size="sm" />
			<span className="sd-palette__label" dir="auto">
				{ snippet.title }
			</span>
			<span className="sd-palette__state">
				<Status state={ state( snippet ) }>
					{ stateLabel( snippet ) }
				</Status>
			</span>
		</>
	);
}

/**
 * Whether a snippet is running, off, or off because it broke.
 *
 * @param {Object} snippet Snippet.
 * @return {string} running, error or inactive.
 */
function state( snippet ) {
	if ( snippet.error ) {
		return 'error';
	}
	return snippet.active ? 'running' : 'inactive';
}

/**
 * The word beside the status dot, so colour is never the only signal.
 *
 * @param {Object} snippet Snippet.
 * @return {string} Label.
 */
function stateLabel( snippet ) {
	if ( snippet.error ) {
		return __( 'Error', 'scriptdock' );
	}
	return snippet.active
		? __( 'Active', 'scriptdock' )
		: __( 'Inactive', 'scriptdock' );
}

/**
 * Everything the palette can do, in the order it offers them.
 *
 * @param {Object} boot Bootstrap data.
 * @return {Array} Commands.
 */
function buildCommands( boot ) {
	const actions = __( 'Actions', 'scriptdock' );
	const settings = __( 'Settings', 'scriptdock' );
	const urls = boot.urls;

	const items = [
		{
			id: 'new',
			group: actions,
			icon: 'plus',
			label: __( 'New snippet', 'scriptdock' ),
			href: urls.new,
			keywords: 'add create',
		},
		{
			id: 'library',
			group: actions,
			icon: 'library',
			label: __( 'Open library', 'scriptdock' ),
			href: urls.library,
			keywords: 'templates ready made',
		},
		{
			id: 'global',
			group: actions,
			icon: 'head',
			label: __( 'Header & Footer', 'scriptdock' ),
			href: urls.global,
			keywords: 'head body footer site wide',
		},
		{
			id: 'files',
			group: actions,
			icon: 'file',
			label: __( 'Site files', 'scriptdock' ),
			href: urls.files,
			keywords: 'ads.txt robots.txt llms.txt security.txt',
		},
		{
			id: 'export',
			group: actions,
			icon: 'download',
			label: __( 'Export all snippets', 'scriptdock' ),
			href: urls.tools,
			keywords: 'backup json download import',
		},
	];

	if ( boot.safeMode.active ) {
		if ( ! boot.safeMode.forced ) {
			items.push( {
				id: 'safe-off',
				group: actions,
				icon: 'shield-check',
				label: __( 'Exit safe mode', 'scriptdock' ),
				href: boot.safeMode.exitUrl,
				keywords: 'turn off resume snippets',
			} );
		}
	} else {
		items.push( {
			id: 'safe-on',
			group: actions,
			icon: 'shield',
			label: __( 'Enter safe mode', 'scriptdock' ),
			href: boot.safeMode.enterUrl,
			note: __( 'this browser', 'scriptdock' ),
			keywords: 'pause stop everything recovery',
		} );
	}

	items.push(
		{
			id: 'settings',
			group: settings,
			icon: 'settings',
			label: __( 'All settings', 'scriptdock' ),
			href: urls.settings,
			keywords: 'options preferences',
		},
		{
			id: 'theme',
			group: settings,
			icon: 'moon',
			label: __( 'Editor theme', 'scriptdock' ),
			href: urls.editorTheme,
			keywords: 'dark light code colours',
		},
		{
			id: 'safe-link',
			group: settings,
			icon: 'shield-alert',
			label: __( 'Safe mode link', 'scriptdock' ),
			href: urls.safeLink,
			keywords: 'recovery locked out secret',
		}
	);

	return items;
}

/**
 * Whether a command matches what has been typed. Every word has to appear
 * somewhere, so "exp all" finds "Export all snippets".
 *
 * @param {Object} item  Command.
 * @param {string} words What was typed.
 * @return {boolean} True when it matches.
 */
function hits( item, words ) {
	const query = words.trim().toLowerCase();
	if ( ! query ) {
		return true;
	}
	const haystack = `${ item.label } ${ item.note || '' } ${
		item.keywords || ''
	}`.toLowerCase();
	return query.split( /\s+/ ).every( ( word ) => haystack.includes( word ) );
}

/**
 * The shortcut label for this platform.
 *
 * @return {string} ⌘K or Ctrl K.
 */
export function shortcutLabel() {
	return isApple()
		? _x( '⌘K', 'keyboard shortcut on macOS', 'scriptdock' )
		: _x(
				'Ctrl K',
				'keyboard shortcut on Windows and Linux',
				'scriptdock'
			);
}
