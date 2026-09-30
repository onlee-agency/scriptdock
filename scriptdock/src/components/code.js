/**
 * Code: sd-CodeEditor, sd-LintStatus, sd-CodePreview and
 * sd-SmartTagPalette.
 *
 * The editor is WordPress's bundled CodeMirror, started through
 * wp.codeEditor so WordPress's linters (HTMLHint, CSSLint, JSHint) and its
 * keyboard escape (Escape, then Tab) keep working. PHP is linted on the
 * server through the `lintPhp` callback.
 */
import {
	createInterpolateElement,
	forwardRef,
	useEffect,
	useImperativeHandle,
	useMemo,
	useRef,
	useState,
} from '@wordpress/element';
import { useInstanceId } from '@wordpress/compose';
import { __, _n, sprintf } from '@wordpress/i18n';
import Icon from './icon';
import { SearchField } from './form';
import { TypeChip } from './display';
import { cx, isApple, useOutsidePointer } from './utils';

/**
 * No syntax errors · Line 3 · … · Checking… · Read-only.
 *
 * @param {Object}   props         Props.
 * @param {string}   props.state   ok, error, checking or readonly.
 * @param {string}   props.text    Label override (the error message).
 * @param {Function} props.onClick Makes the error jump to its line.
 * @param {string}   props.size    md (26) or lg (28).
 * @return {Element} The status.
 */
export function LintStatus( { state = 'ok', text, onClick, size = 'md' } ) {
	const label =
		text ||
		{
			ok: __( 'No syntax errors', 'scriptdock' ),
			checking: __( 'Checking…', 'scriptdock' ),
			readonly: __( 'Read-only', 'scriptdock' ),
			error: __( 'Syntax error', 'scriptdock' ),
		}[ state ];
	const Tag = onClick && state === 'error' ? 'button' : 'span';
	return (
		<Tag
			className={ cx(
				'sd-lint',
				state !== 'ok' && `sd-lint--${ state }`,
				size === 'lg' && 'sd-lint--lg'
			) }
			{ ...( Tag === 'button'
				? { type: 'button', onClick }
				: { role: 'status' } ) }
		>
			{ state === 'ok' && <Icon name="check" size={ 13 } stroke={ 3 } /> }
			{ state === 'error' && (
				<Icon name="alert" size={ 13 } stroke={ 2 } />
			) }
			{ state === 'readonly' && (
				<Icon name="lock" size={ 13 } stroke={ 2 } />
			) }
			{ state === 'checking' && (
				<span className="sd-spinner" aria-hidden="true" />
			) }
			<span className="sd-lint__text">{ label }</span>
		</Tag>
	);
}

/**
 * Where a smart tag lands decides its modifier: |js inside a JavaScript
 * string, |url in href and src values, |attr in other attribute values.
 *
 * @param {Object} cm CodeMirror instance.
 * @return {string} Modifier suffix, or ''.
 */
function modifierAtCursor( cm ) {
	const cursor = cm.getCursor();
	const token = cm.getTokenAt( cursor );
	const mode = cm.getModeAt( cursor ).name;
	const type = token.type || '';
	if ( mode === 'javascript' && type.includes( 'string' ) ) {
		return '|js';
	}
	if ( type.includes( 'string' ) && mode === 'xml' ) {
		const line = cm.getLine( cursor.line ).slice( 0, token.start );
		const attr = line.match( /([\w-]+)\s*=\s*$/ );
		if ( attr ) {
			return [ 'href', 'src', 'action' ].includes(
				attr[ 1 ].toLowerCase()
			)
				? '|url'
				: '|attr';
		}
	}
	return '';
}

// What a keyboard can reach, for the editor's Escape-then-Tab exit.
const TABBABLE =
	'a[href], button:not(:disabled), input:not(:disabled), select:not(:disabled), textarea:not(:disabled), [tabindex]:not([tabindex="-1"])';

const FILE_EXTENSION = {
	php: 'php',
	html: 'html',
	css: 'css',
	js: 'js',
	universal: 'php',
};

/**
 * The code editor: toolbar, CodeMirror and status bar.
 *
 * @param {Object}   props                   Props.
 * @param {string}   props.value             Code.
 * @param {Function} props.onChange          Called with the new code.
 * @param {string}   props.type              php, html, css, js or universal.
 * @param {Object}   props.settings          wp.codeEditor settings for the type
 *                                           (from wp_enqueue_code_editor()).
 * @param {string}   props.fileName          Name shown in the toolbar.
 * @param {string}   props.label             Accessible name of the editor.
 * @param {string}   props.theme             light or dark.
 * @param {Function} props.onThemeChange     Called with the other theme.
 * @param {boolean}  props.readOnly          Read-only.
 * @param {Array}    props.errors            Extra errors to mark: { line,
 *                                           message } (runtime or server lint).
 * @param {Function} props.lintPhp           code => Promise<{ line, message } |
 *                                           null>, for PHP.
 * @param {Function} props.onLint            Called with the current errors.
 * @param {Function} props.onSave            ⌘S / Ctrl+S.
 * @param {Element}  props.smartTags         SmartTagPalette groups, or null to
 *                                           hide the button.
 * @param {Element}  props.toolbarExtra      Extra toolbar content.
 * @param {string}   props.placeholder       Shown while there is no code.
 * @param {string}   props.title             Snippet title, for the full-screen
 *                                           bar.
 * @param {Element}  props.fullscreenActions Actions in the full-screen bar
 *                                           (Save).
 * @param {Object}   ref                     { focus(), jumpToLine( n ),
 *                                           insert( text ) }.
 * @return {Element} The editor.
 */
function CodeEditor(
	{
		value = '',
		onChange,
		type = 'html',
		settings,
		fileName,
		label = __( 'Code', 'scriptdock' ),
		theme = 'dark',
		onThemeChange,
		readOnly = false,
		errors = [],
		lintPhp,
		onLint,
		onSave,
		smartTags = null,
		toolbarExtra,
		placeholder,
		title,
		fullscreenActions,
	},
	ref
) {
	const textarea = useRef();
	const cmRef = useRef( null );
	const wrap = useRef();
	const paletteWrap = useRef();
	const paletteButton = useRef();
	const id = useInstanceId( CodeEditor, 'sd-editor' );
	const [ cursor, setCursor ] = useState( { line: 1, ch: 1 } );
	const [ lines, setLines ] = useState( 1 );
	const [ lintErrors, setLintErrors ] = useState( [] );
	const [ checking, setChecking ] = useState( false );
	const [ fullscreen, setFullscreen ] = useState( false );
	const [ paletteOpen, setPaletteOpen ] = useState( false );
	const exit = useRef( false );
	const fullButton = useRef();
	const callbacks = useRef( {} );
	callbacks.current = { onChange, onSave, onLint, fullscreen, setFullscreen };

	const allErrors = useMemo(
		() =>
			[ ...errors, ...lintErrors ]
				.filter( ( error ) => error && error.line )
				.sort( ( a, b ) => a.line - b.line ),
		[ errors, lintErrors ]
	);

	// WordPress's code editor lets Escape, then Tab, leave the code. It
	// needs somewhere to send focus, or the editor is a keyboard trap.
	const leave = ( forward ) => {
		const cm = cmRef.current;
		if ( ! cm ) {
			return;
		}
		const area = cm.getWrapperElement();
		// Everything a keyboard can reach, in document order, so the code
		// itself marks the spot to step out from.
		const all = Array.from( document.querySelectorAll( TABBABLE ) ).filter(
			( node ) =>
				area.contains( node ) || node.offsetWidth || node.offsetHeight
		);
		const inside = all.filter( ( node ) => area.contains( node ) );
		if ( ! inside.length ) {
			return;
		}
		const target = forward
			? all[ all.indexOf( inside[ inside.length - 1 ] ) + 1 ]
			: all[ all.indexOf( inside[ 0 ] ) - 1 ];
		if ( target && ! area.contains( target ) ) {
			target.focus();
		}
	};

	// Start CodeMirror once.
	useEffect( () => {
		const wp = window.wp;
		if ( ! wp || ! wp.codeEditor || ! textarea.current ) {
			return;
		}
		const base = settings || wp.codeEditor.defaultSettings || {};
		const editor = wp.codeEditor.initialize( textarea.current, {
			...base,
			onTabNext: () => leave( true ),
			onTabPrevious: () => leave( false ),
			codemirror: {
				...( base.codemirror || {} ),
				lineNumbers: true,
				lineWrapping: false,
				readOnly,
				// Lint underlines only; our gutter marks lines (no lint gutter).
				gutters: [ 'CodeMirror-linenumbers' ],
				lint:
					base.codemirror && base.codemirror.lint
						? {
								...( typeof base.codemirror.lint === 'object'
									? base.codemirror.lint
									: {} ),
								tooltips: false,
							}
						: false,
				extraKeys: {
					...( ( base.codemirror && base.codemirror.extraKeys ) ||
						{} ),
					'Cmd-S': () =>
						callbacks.current.onSave && callbacks.current.onSave(),
					'Ctrl-S': () =>
						callbacks.current.onSave && callbacks.current.onSave(),
					Esc: ( cm ) => {
						if ( callbacks.current.fullscreen ) {
							// Back to the Full screen button, as from anywhere else.
							exit.current = true;
							callbacks.current.setFullscreen( false );
							return undefined;
						}
						return cm.constructor.Pass;
					},
				},
			},
			onUpdateErrorNotice( annotations ) {
				const found = ( annotations || [] )
					.filter( ( item ) => item.severity === 'error' )
					.map( ( item ) => ( {
						line: item.from.line + 1,
						message: item.message,
					} ) );
				setLintErrors( ( previous ) =>
					JSON.stringify( previous ) === JSON.stringify( found )
						? previous
						: found
				);
			},
		} );
		const cm = editor.codemirror;
		cmRef.current = cm;
		const input = cm.getInputField();
		input.setAttribute( 'aria-label', label );
		// WordPress starts CodeMirror in contenteditable mode; give that
		// div the multi-line textbox role a textarea would have.
		if ( input.tagName !== 'TEXTAREA' ) {
			input.setAttribute( 'role', 'textbox' );
			input.setAttribute( 'aria-multiline', 'true' );
		}
		cm.on( 'change', () => {
			setLines( cm.lineCount() );
			if ( callbacks.current.onChange ) {
				callbacks.current.onChange( cm.getValue() );
			}
		} );
		cm.on( 'cursorActivity', () => {
			const pos = cm.getCursor();
			setCursor( { line: pos.line + 1, ch: pos.ch + 1 } );
		} );
		setLines( cm.lineCount() );
		// Redraw when the editor is resized by its corner.
		const observer =
			typeof window.ResizeObserver === 'function'
				? new window.ResizeObserver( () => cm.refresh() )
				: null;
		observer?.observe( cm.getWrapperElement() );
		return () => {
			observer?.disconnect();
			cm.toTextArea();
			cmRef.current = null;
		};
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [] );

	// Outside changes to the value (restore, template) replace the text.
	useEffect( () => {
		const cm = cmRef.current;
		if ( cm && cm.getValue() !== value ) {
			const scroll = cm.getScrollInfo();
			cm.setValue( value );
			cm.scrollTo( scroll.left, scroll.top );
		}
	}, [ value ] );

	useEffect( () => {
		const cm = cmRef.current;
		if ( cm ) {
			cm.setOption( 'readOnly', readOnly );
			const input = cm.getInputField();
			if ( readOnly ) {
				input.setAttribute( 'aria-readonly', 'true' );
			} else {
				input.removeAttribute( 'aria-readonly' );
			}
		}
	}, [ readOnly ] );

	// PHP: lint on the server 0.7s after typing stops.
	useEffect( () => {
		if (
			! lintPhp ||
			readOnly ||
			( type !== 'php' && type !== 'universal' )
		) {
			return;
		}
		setChecking( true );
		let current = true;
		const timer = setTimeout( () => {
			Promise.resolve( lintPhp( value ) )
				.then( ( error ) => {
					if ( current ) {
						setLintErrors( error ? [ error ] : [] );
					}
				} )
				.finally( () => current && setChecking( false ) );
		}, 700 );
		return () => {
			current = false;
			clearTimeout( timer );
		};
	}, [ value, lintPhp, type, readOnly ] );

	useEffect( () => {
		if ( onLint ) {
			onLint( allErrors );
		}
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ allErrors ] );

	// Mark error lines: wash, 2px edge, red gutter number with a dot.
	useEffect( () => {
		const cm = cmRef.current;
		if ( ! cm ) {
			return;
		}
		const marked = allErrors
			.map( ( error ) => error.line - 1 )
			.filter( ( line ) => line >= 0 && line < cm.lineCount() );
		marked.forEach( ( line ) => {
			cm.addLineClass( line, 'background', 'sd-cm-error-line' );
			cm.addLineClass( line, 'gutter', 'sd-cm-error-gutter' );
		} );
		return () =>
			marked.forEach( ( line ) => {
				if ( line < cm.lineCount() ) {
					cm.removeLineClass(
						line,
						'background',
						'sd-cm-error-line'
					);
					cm.removeLineClass( line, 'gutter', 'sd-cm-error-gutter' );
				}
			} );
	}, [ allErrors, lines ] );

	useEffect( () => {
		if ( cmRef.current ) {
			cmRef.current.refresh();
		}
		document.documentElement.classList.toggle(
			'sd-scroll-locked',
			fullscreen
		);
	}, [ fullscreen ] );

	// Full screen covers the screen, so it behaves like a dialog: Escape
	// closes it wherever focus is, and Tab keeps focus inside. CodeMirror
	// handles both itself while the code has focus, except Option+Tab:
	// Safari's way of moving focus, which CodeMirror leaves to the browser.
	useEffect( () => {
		if ( ! fullscreen ) {
			return undefined;
		}
		const onKeyDown = ( event ) => {
			const area = cmRef.current && cmRef.current.getWrapperElement();
			const moving = event.key === 'Tab' && event.altKey;
			if ( area && area.contains( event.target ) && ! moving ) {
				return;
			}
			if ( event.key === 'Escape' && ! paletteOpen ) {
				event.preventDefault();
				setFullscreen( false );
				exit.current = true;
				return;
			}
			if ( event.key !== 'Tab' || ! wrap.current ) {
				return;
			}
			const stops = Array.from(
				wrap.current.querySelectorAll( TABBABLE )
			).filter( ( node ) => node.offsetWidth || node.offsetHeight );
			if ( ! stops.length ) {
				return;
			}
			const first = stops[ 0 ];
			const last = stops[ stops.length - 1 ];
			if ( ! wrap.current.contains( event.target ) ) {
				event.preventDefault();
				first.focus();
			} else if ( event.shiftKey && event.target === first ) {
				event.preventDefault();
				last.focus();
			} else if ( ! event.shiftKey && event.target === last ) {
				event.preventDefault();
				first.focus();
			}
		};
		document.addEventListener( 'keydown', onKeyDown );
		return () => document.removeEventListener( 'keydown', onKeyDown );
	}, [ fullscreen, paletteOpen ] );

	// Leaving full screen, focus goes back to the button that opened it.
	useEffect( () => {
		if ( fullscreen || ! exit.current ) {
			return;
		}
		exit.current = false;
		fullButton.current?.focus();
	}, [ fullscreen ] );

	useOutsidePointer(
		[ paletteWrap ],
		() => setPaletteOpen( false ),
		paletteOpen
	);

	const jumpToLine = ( line ) => {
		const cm = cmRef.current;
		if ( ! cm ) {
			return;
		}
		cm.focus();
		cm.setCursor( { line: Math.max( 0, line - 1 ), ch: 0 } );
		cm.scrollIntoView( null, 120 );
	};

	const insert = ( text ) => {
		const cm = cmRef.current;
		if ( ! cm ) {
			return;
		}
		cm.replaceSelection( text );
		cm.focus();
	};

	useImperativeHandle( ref, () => ( {
		focus: () => cmRef.current && cmRef.current.focus(),
		jumpToLine,
		insert,
	} ) );

	const insertTag = ( tag ) => {
		const cm = cmRef.current;
		const modifier = cm ? modifierAtCursor( cm ) : '';
		insert( `{{${ tag }${ modifier }}}` );
		setPaletteOpen( false );
	};

	const first = allErrors[ 0 ];
	let lintState = 'ok';
	let lintText;
	if ( readOnly ) {
		lintState = 'readonly';
	} else if ( checking ) {
		lintState = 'checking';
	} else if ( first ) {
		lintState = 'error';
		lintText = sprintf(
			/* translators: 1: line number, 2: error message. */
			__( 'Line %1$d · %2$s', 'scriptdock' ),
			first.line,
			first.message
		);
	}

	const file = fileName || `untitled.${ FILE_EXTENSION[ type ] || 'txt' }`;
	const dark = theme === 'dark';

	return (
		<div
			ref={ wrap }
			className={ cx(
				'sd-editor',
				dark ? 'sd-editor--dark' : 'sd-editor--light',
				`sd-editor--${ type }`,
				fullscreen && 'is-fullscreen'
			) }
			role={ fullscreen ? 'dialog' : undefined }
			aria-modal={ fullscreen || undefined }
			aria-label={ fullscreen ? label : undefined }
		>
			<div className="sd-editor__toolbar">
				{ fullscreen && title !== undefined ? (
					<span className="sd-editor__title">
						<span
							className="sd-editor__title-dot"
							aria-hidden="true"
						/>
						<span className="sd-editor__title-text" dir="auto">
							{ title || __( 'Untitled snippet', 'scriptdock' ) }
						</span>
					</span>
				) : (
					<span className="sd-editor__file">
						<span
							className={ cx(
								'sd-editor__file-dot',
								`sd-editor__file-dot--${ type }`
							) }
							aria-hidden="true"
						/>
						{ file }
					</span>
				) }
				<LintStatus
					state={ lintState }
					text={ lintText }
					onClick={
						first ? () => jumpToLine( first.line ) : undefined
					}
				/>
				{ toolbarExtra }
				<div className="sd-editor__tools">
					{ smartTags && (
						<div className="sd-menu-anchor" ref={ paletteWrap }>
							<button
								ref={ paletteButton }
								type="button"
								className="sd-editor__tool"
								aria-expanded={ paletteOpen }
								aria-controls={ `${ id }-tags` }
								onClick={ () =>
									setPaletteOpen( ( open ) => ! open )
								}
							>
								{ __( 'Smart tags', 'scriptdock' ) }
							</button>
							{ paletteOpen && (
								<div
									id={ `${ id }-tags` }
									className="sd-editor__palette"
								>
									<SmartTagPalette
										groups={ smartTags }
										onInsert={ insertTag }
										onClose={ () => {
											// Escape: back to the button, not the top of the page.
											setPaletteOpen( false );
											paletteButton.current?.focus();
										} }
										focusOnMount
									/>
								</div>
							) }
						</div>
					) }
					{ onThemeChange && ! fullscreen && (
						<button
							type="button"
							className="sd-editor__tool sd-editor__tool--icon"
							aria-label={
								dark
									? __(
											'Switch to light theme',
											'scriptdock'
										)
									: __( 'Switch to dark theme', 'scriptdock' )
							}
							onClick={ () =>
								onThemeChange( dark ? 'light' : 'dark' )
							}
						>
							<Icon
								name={ dark ? 'sun' : 'moon' }
								size={ 15 }
								stroke={ 1.6 }
							/>
						</button>
					) }
					{ fullscreen && fullscreenActions }
					{ fullscreen ? (
						<button
							type="button"
							className="sd-editor__tool sd-editor__tool--exit"
							aria-label={ __(
								'Exit full screen',
								'scriptdock'
							) }
							onClick={ () => {
								exit.current = true;
								setFullscreen( false );
							} }
						>
							{ __( 'Exit', 'scriptdock' ) }
							<kbd aria-hidden="true">⎋</kbd>
						</button>
					) : (
						<button
							ref={ fullButton }
							type="button"
							className="sd-editor__tool sd-editor__tool--icon"
							aria-label={
								label
									? sprintf(
											/* translators: %s: the editor's name, for example "Header code". */
											__(
												'Full screen: %s',
												'scriptdock'
											),
											label
										)
									: __( 'Full screen', 'scriptdock' )
							}
							onClick={ () => setFullscreen( true ) }
						>
							<Icon
								name="fullscreen"
								size={ 15 }
								stroke={ 1.6 }
							/>
						</button>
					) }
				</div>
			</div>
			<div className="sd-editor__body">
				<textarea
					ref={ textarea }
					defaultValue={ value }
					aria-label={ label }
					readOnly={ readOnly }
					// Without CodeMirror (syntax highlighting turned off in the
					// profile) the plain textarea is the editor.
					onInput={ ( event ) =>
						! cmRef.current &&
						callbacks.current.onChange &&
						callbacks.current.onChange( event.target.value )
					}
				/>
				{ placeholder && ! value && (
					<pre className="sd-editor__placeholder" aria-hidden="true">
						{ placeholder }
					</pre>
				) }
			</div>
			<div className="sd-editor__status">
				<span>
					{ sprintf(
						/* translators: 1: line, 2: column. */
						__( 'Ln %1$d, Col %2$d', 'scriptdock' ),
						cursor.line,
						cursor.ch
					) }
				</span>
				<span>
					{ sprintf(
						/* translators: %d: number of lines. */
						_n( '%d line', '%d lines', lines, 'scriptdock' ),
						lines
					) }
				</span>
				<span>UTF-8</span>
				{ allErrors.length > 0 && (
					<span className="sd-editor__status-problems">
						{ sprintf(
							/* translators: %d: number of problems. */
							_n(
								'%d problem',
								'%d problems',
								allErrors.length,
								'scriptdock'
							),
							allErrors.length
						) }
					</span>
				) }
				{ ! readOnly && onSave && (
					<span className="sd-editor__status-end">
						{ fullscreen
							? sprintf(
									/* translators: %s: the save shortcut, ⌘S or Ctrl+S. */
									__(
										'%s to save · Esc to exit',
										'scriptdock'
									),
									isApple()
										? '⌘S'
										: __( 'Ctrl+S', 'scriptdock' )
								)
							: sprintf(
									/* translators: %s: the save shortcut, ⌘S or Ctrl+S. */
									__( '%s to save', 'scriptdock' ),
									isApple()
										? '⌘S'
										: __( 'Ctrl+S', 'scriptdock' )
								) }
					</span>
				) }
			</div>
		</div>
	);
}

export default forwardRef( CodeEditor );

/**
 * Read-only code with highlighted %%PLACEHOLDERS%%. Every line is its own
 * block in both the gutter and the code column.
 *
 * @param {Object}  props             Props.
 * @param {string}  props.code        Code.
 * @param {string}  props.type        Type chip in the header.
 * @param {string}  props.title       Header text ("Read-only preview").
 * @param {boolean} props.numbered    Show line numbers.
 * @param {boolean} props.locked      Show "Locked".
 * @param {string}  props.lockedLabel Label next to the lock ("Read-only").
 * @param {Element} props.note        Footer note.
 * @return {Element} The preview.
 */
export function CodePreview( {
	code = '',
	type,
	title = __( 'Read-only preview', 'scriptdock' ),
	numbered = false,
	locked = true,
	lockedLabel = __( 'Locked', 'scriptdock' ),
	note,
} ) {
	const lines = code.replace( /\n$/, '' ).split( '\n' );
	const highlight = ( line ) =>
		line.split( /(%%[A-Z0-9_]+%%)/ ).map( ( part, index ) =>
			/^%%[A-Z0-9_]+%%$/.test( part ) ? (
				<mark key={ index } className="sd-placeholder">
					{ part }
				</mark>
			) : (
				part
			)
		);
	return (
		<div className="sd-preview">
			<div className="sd-preview__header">
				{ type && <TypeChip type={ type } size="sm" /> }
				<span className="sd-preview__title">{ title }</span>
				{ locked && (
					<span className="sd-preview__locked">
						<Icon name="lock" size={ 12 } stroke={ 2 } />
						{ lockedLabel }
					</span>
				) }
			</div>
			{ /* Scrolls sideways, so it takes focus for keyboard users. */ }
			<div
				className={ cx(
					'sd-preview__code',
					numbered && 'sd-preview__code--numbered'
				) }
				tabIndex={ 0 }
				role="region"
				aria-label={ title }
			>
				{ numbered && (
					<div className="sd-preview__gutter" aria-hidden="true">
						{ lines.map( ( _, index ) => (
							<div key={ index }>{ index + 1 }</div>
						) ) }
					</div>
				) }
				<code className="sd-preview__lines">
					{ lines.map( ( line, index ) => (
						<span key={ index } className="sd-preview__line">
							{ highlight( line ) }
						</span>
					) ) }
				</code>
			</div>
			{ note && <div className="sd-preview__note">{ note }</div> }
		</div>
	);
}

/**
 * The smart tags palette: search, groups, insert, and the modifier help.
 *
 * @param {Object}   props              Props.
 * @param {Array}    props.groups       { label, note, available, tags: [ { tag,
 *                                      description } ] }.
 * @param {Function} props.onInsert     Called with the tag name.
 * @param {Function} props.onClose      Closes the palette (Escape).
 * @param {boolean}  props.focusOnMount Focus the search when shown (when
 *                                      the palette opens on request).
 * @return {Element} The palette.
 */
export function SmartTagPalette( {
	groups = [],
	onInsert,
	onClose,
	focusOnMount = false,
} ) {
	const [ query, setQuery ] = useState( '' );
	const list = useRef();
	const search = useRef();

	useEffect( () => {
		if ( focusOnMount && search.current ) {
			search.current.focus();
		}
	}, [ focusOnMount ] );
	const needle = query
		.trim()
		.toLowerCase()
		.replace( /^\{+|\}+$/g, '' );

	const visible = groups
		.map( ( group ) => ( {
			...group,
			tags: group.tags.filter(
				( item ) =>
					! needle ||
					item.tag.toLowerCase().includes( needle ) ||
					( item.description || '' ).toLowerCase().includes( needle )
			),
		} ) )
		.filter( ( group ) => group.tags.length );

	const move = ( event ) => {
		const items = Array.from(
			list.current.querySelectorAll( '.sd-tags__item' )
		);
		const index = items.indexOf( event.target.ownerDocument.activeElement );
		if ( event.key === 'ArrowDown' ) {
			event.preventDefault();
			( items[ index + 1 ] || items[ 0 ] )?.focus();
		} else if ( event.key === 'ArrowUp' ) {
			event.preventDefault();
			( items[ index - 1 ] || items[ items.length - 1 ] )?.focus();
		} else if ( event.key === 'Escape' && onClose ) {
			event.preventDefault();
			event.stopPropagation();
			onClose();
		}
	};

	return (
		<div className="sd-tags">
			<div className="sd-tags__head">
				<SearchField
					ref={ search }
					label={ __( 'Search smart tags', 'scriptdock' ) }
					placeholder={ __( 'Search smart tags…', 'scriptdock' ) }
					value={ query }
					onChange={ setQuery }
					size="sm"
					onKeyDown={ move }
				/>
			</div>
			<div className="sd-tags__list" ref={ list }>
				{ visible.map( ( group ) => (
					<div
						key={ group.label }
						role="group"
						aria-label={ group.label }
					>
						<div className="sd-tags__group" aria-hidden="true">
							{ group.label }
						</div>
						{ group.tags.map( ( item ) => (
							<button
								key={ item.tag }
								type="button"
								className={ cx(
									'sd-tags__item',
									group.available === false &&
										'is-unavailable'
								) }
								onClick={ () => onInsert( item.tag ) }
								onKeyDown={ move }
							>
								<code className="sd-tags__tag">{ `{{${ item.tag }}}` }</code>
								<span className="sd-tags__desc">
									{ item.description }
								</span>
								<span
									className="sd-tags__insert"
									aria-hidden="true"
								>
									{ __( 'Insert', 'scriptdock' ) }
								</span>
							</button>
						) ) }
						{ group.available === false && group.note && (
							<div className="sd-tags__note">
								<Icon name="info" size={ 16 } stroke={ 1.8 } />
								<span>{ group.note }</span>
							</div>
						) }
					</div>
				) ) }
				{ ! visible.length && (
					<div className="sd-tags__empty" role="status">
						<span className="sd-tags__empty-title">
							{ sprintf(
								/* translators: %s: what the user searched for. */
								__( 'No tag called “%s”', 'scriptdock' ),
								query.trim()
							) }
						</span>
						<span className="sd-tags__empty-text">
							{ __(
								'Smart tags are fixed — you cannot add your own. For custom values, use a PHP or Universal snippet.',
								'scriptdock'
							) }
						</span>
					</div>
				) }
			</div>
			<div className="sd-tags__foot">
				<p className="sd-tags__foot-text">
					{ createInterpolateElement(
						__(
							'Always add a modifier for where it lands: <js /> in JavaScript, <url /> in links, <json /> in data layers, <raw /> for no escaping.',
							'scriptdock'
						),
						{
							js: <code>|js</code>,
							url: <code>|url</code>,
							json: <code>|json</code>,
							raw: <code>|raw</code>,
						}
					) }
				</p>
				<code className="sd-tags__example">
					{
						"gtag('event', 'page_view', { title: '{{page_title|js}}' });"
					}
				</code>
			</div>
		</div>
	);
}
