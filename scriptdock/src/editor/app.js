/**
 * The snippet editor (S04). Changes collect in a draft and save together:
 * the switch, the code, the notes and the loading options alike. ⌘S or
 * Ctrl+S saves from anywhere, and leaving with unsaved changes asks first.
 */
import {
	useCallback,
	useEffect,
	useMemo,
	useRef,
	useState,
} from '@wordpress/element';
import { useMediaQuery } from '@wordpress/compose';
import { __, sprintf } from '@wordpress/i18n';
import {
	Button,
	CodeEditor,
	ConfirmDialog,
	EmptyState,
	Illustration,
	TabPanel,
	Tabs,
	copyText,
	cx,
	useToast,
} from '../components';
import * as api from './api';
import { changedFields, changedPhrases, fileName, toDraft } from './draft';
import { TargetingWizard } from '../targeting';
import HistoryDrawer from './history-drawer';
import EditorBar, { PhoneBar, PhoneSaveBar } from './editor-bar';
import {
	ErrorBanner,
	ReadOnlyBanner,
	SafeModeBanner,
	TamperedBanner,
	TrashBanner,
} from './banners';
import TypePicker from './type-picker';
import WhereCard from './where-card';
import LoadingCard from './loading-card';
import {
	DetailsCard,
	HistoryCard,
	OptionsCard,
	RunCard,
	SafetyCard,
} from './sidebar';
import RunConsole from './run-console';
import useLeaveGuard from './use-leave-guard';

const boot = window.sdEditor || {};

// A message for the next page, such as after Save as copy.
const FLASH_KEY = 'sd-editor-flash';

/**
 * An example for an empty editor, per type.
 *
 * @return {Object} Type => code.
 */
function placeholders() {
	// Only the words are translated; the code stays as it is.
	return {
		html: [
			`<!-- ${ __( 'Paste your HTML here. For example:', 'scriptdock' ) } -->`,
			'<meta name="google-site-verification" content="…">',
			`<!-- ${ __( 'Smart tags work here too:', 'scriptdock' ) } {{page_title}} -->`,
		].join( '\n' ),
		css: [
			`/* ${ __( 'Paste your CSS here. For example:', 'scriptdock' ) } */`,
			'.site-header {',
			'\tbackground: #111;',
			'}',
		].join( '\n' ),
		js: [
			`// ${ __( 'Runs in a <script> tag, so no wrapper is needed.', 'scriptdock' ) }`,
			"console.log( 'Hello from ScriptDock' );",
		].join( '\n' ),
		php: [
			`// ${ __( 'Example: shorter excerpts.', 'scriptdock' ) }`,
			"add_filter( 'excerpt_length', function () {",
			'\treturn 30;',
			'} );',
		].join( '\n' ),
		universal:
			'<p>Hello, <?php echo esc_html( wp_get_current_user()->display_name ); ?>.</p>',
	};
}

/**
 * What the leave dialog says was changed.
 *
 * @param {string[]} fields Changed fields.
 * @return {string} Sentence.
 */
function leaveText( fields ) {
	const phrases = changedPhrases( fields );
	if ( phrases.length === 1 ) {
		return sprintf(
			/* translators: %s: what changed, for example "the code". */
			__( 'You changed %s. That change will be lost.', 'scriptdock' ),
			phrases[ 0 ]
		);
	}
	if ( phrases.length === 2 ) {
		return sprintf(
			/* translators: 1: what changed, for example "the code", 2: what else changed, for example "the notes". */
			__(
				'You changed %1$s and %2$s. Those changes will be lost.',
				'scriptdock'
			),
			phrases[ 0 ],
			phrases[ 1 ]
		);
	}
	return sprintf(
		/* translators: 1: what changed, for example "the code", 2: what else changed, for example "the notes". */
		__(
			'You changed %1$s, %2$s and more. Those changes will be lost.',
			'scriptdock'
		),
		phrases[ 0 ],
		phrases[ 1 ]
	);
}

/**
 * A title for a copy.
 *
 * @param {string} title Original title.
 * @return {string} Title.
 */
function copyTitle( title ) {
	return sprintf(
		/* translators: %s: snippet title. */
		__( '%s (copy)', 'scriptdock' ),
		title || __( 'Untitled snippet', 'scriptdock' )
	);
}

/**
 * The editor, or a note when the snippet does not exist.
 *
 * @return {Element} The screen.
 */
export default function App() {
	if ( ! boot.snippet ) {
		return (
			<div className="sd-editor-screen__missing">
				<EmptyState
					level={ 1 }
					illustration={
						<Illustration name="empty-snippets" width={ 160 } />
					}
					title={ __( 'This snippet does not exist', 'scriptdock' ) }
					text={ __(
						'It may have been deleted permanently, or the link is wrong.',
						'scriptdock'
					) }
					actions={
						<Button variant="primary" href={ boot.urls.list }>
							{ __( 'Back to snippets', 'scriptdock' ) }
						</Button>
					}
				/>
			</div>
		);
	}
	return <Editor />;
}

/**
 * @return {Element} The editor.
 */
function Editor() {
	const toast = useToast();
	const phone = useMediaQuery( '(max-width: 782px)' );
	const tablet = useMediaQuery( '(max-width: 1024px)' );
	const code = useRef();

	const [ saved, setSaved ] = useState( boot.snippet );
	const base = useMemo( () => toDraft( saved ), [ saved ] );
	const [ draft, setDraft ] = useState( base );
	const changed = useMemo(
		() => changedFields( draft, base ),
		[ draft, base ]
	);
	const dirty = changed.length > 0;
	const isNew = ! saved.id;
	const canEdit = saved.can_edit && saved.status !== 'trash';

	const [ saving, setSaving ] = useState( false );
	const [ targeting, setTargeting ] = useState( saved.targeting );
	const [ describing, setDescribing ] = useState( false );
	const [ theme, setTheme ] = useState( boot.theme );
	const [ running, setRunning ] = useState( false );
	const [ result, setResult ] = useState( null );
	const [ confirm, setConfirm ] = useState( null );
	const [ approving, setApproving ] = useState( false );
	const [ untrashing, setUntrashing ] = useState( false );
	const [ tab, setTab ] = useState( 'details' );
	const [ wizard, setWizard ] = useState( false );
	const [ history, setHistory ] = useState(
		boot.history ? { revision: boot.history } : null
	);

	const guard = useLeaveGuard( dirty, ( url ) =>
		setConfirm( { kind: 'leave', url } )
	);

	const update = ( fields ) =>
		setDraft( ( current ) => ( { ...current, ...fields } ) );
	const updateOptions = ( fields ) =>
		setDraft( ( current ) => ( {
			...current,
			options: { ...current.options, ...fields },
		} ) );

	// A message left by the page before (Save as copy).
	useEffect( () => {
		try {
			const message = window.sessionStorage.getItem( FLASH_KEY );
			if ( message ) {
				window.sessionStorage.removeItem( FLASH_KEY );
				toast.show( { message } );
			}
		} catch {}
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [] );

	// Changing the type keeps the placement when the new type can use it.
	const changeType = ( type ) =>
		setDraft( ( current ) => {
			const place = boot.locations.find(
				( item ) => item.value === current.location
			);
			const info = boot.types.find( ( item ) => item.value === type );
			return {
				...current,
				type,
				location:
					place && place.types.includes( type )
						? current.location
						: info.defaultLocation,
			};
		} );

	// The sentence follows the draft once placement or rules differ from
	// what is saved.
	const where = JSON.stringify( [
		draft.type,
		draft.location,
		draft.location_args,
		draft.conditions,
		draft.schedule,
	] );
	const savedWhere = JSON.stringify( [
		base.type,
		base.location,
		base.location_args,
		base.conditions,
		base.schedule,
	] );
	useEffect( () => {
		if ( where === savedWhere ) {
			setTargeting( saved.targeting );
			return;
		}
		let current = true;
		setDescribing( true );
		api.describe( draft )
			.then( ( found ) => current && setTargeting( found ) )
			.catch( () => {} )
			.finally( () => current && setDescribing( false ) );
		return () => {
			current = false;
		};
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ where, savedWhere ] );

	/**
	 * Takes in a saved snippet. The draft follows it unless it changed
	 * while the save was on its way.
	 *
	 * @param {Object}  item        Saved snippet.
	 * @param {Object}  sent        The draft that was sent.
	 * @param {boolean} switchedOff The save left it switched off.
	 */
	const applySaved = ( item, sent, switchedOff = false ) => {
		setSaved( item );
		setDraft( ( current ) => {
			if ( ! changedFields( current, sent ).length ) {
				return toDraft( item );
			}
			return switchedOff ? { ...current, active: false } : current;
		} );
		if ( ! saved.id && item.id ) {
			window.history.replaceState( null, '', item.edit_url );
		}
	};

	const save = ( { active = draft.active, copy = false } = {} ) => {
		if ( saving || ! canEdit ) {
			return;
		}
		const sent = { ...draft, active };
		if ( ! copy && ! changedFields( sent, base ).length ) {
			return;
		}
		const payload = copy
			? { ...sent, title: copyTitle( draft.title ), active: false }
			: sent;
		setSaving( true );
		api.save( copy ? 0 : saved.id, payload )
			.then( ( response ) => {
				if ( copy ) {
					try {
						window.sessionStorage.setItem(
							FLASH_KEY,
							__(
								'Saved as a copy. The original is unchanged.',
								'scriptdock'
							)
						);
					} catch {}
					guard.allow();
					window.location.assign( response.item.edit_url );
					return;
				}
				applySaved( response.item, sent );
				if ( response.notice ) {
					toast.show( {
						tone: 'error',
						message: response.notice.message,
					} );
				} else {
					toast.show( {
						message: response.item.active
							? __( 'Snippet saved and active.', 'scriptdock' )
							: __(
									'Snippet saved. It is switched off.',
									'scriptdock'
								),
					} );
				}
			} )
			.catch( ( error ) => {
				if ( error && error.code === 'scriptdock_test_fatal' ) {
					// Saved and switched off, with the error recorded.
					const id =
						saved.id || ( error.data && error.data.snippet_id );
					toast.show( {
						tone: 'error',
						message: __(
							'Saved, but left switched off: it crashed when ScriptDock tested it.',
							'scriptdock'
						),
					} );
					if ( id ) {
						api.fetchSnippet( id )
							.then( ( item ) => applySaved( item, sent, true ) )
							.catch( () => {} );
					}
					return;
				}
				toast.show( {
					tone: 'error',
					message: api.errorMessage( error ),
					action: {
						label: __( 'Retry', 'scriptdock' ),
						onClick: () => saveRef.current( { active, copy } ),
					},
				} );
			} )
			.finally( () => setSaving( false ) );
	};
	const saveRef = useRef();
	saveRef.current = save;

	// ⌘S / Ctrl+S anywhere. CodeMirror handles its own and marks the event.
	useEffect( () => {
		const onKeyDown = ( event ) => {
			if (
				( event.metaKey || event.ctrlKey ) &&
				! event.altKey &&
				event.key.toLowerCase() === 's' &&
				! event.defaultPrevented
			) {
				event.preventDefault();
				saveRef.current();
			}
		};
		document.addEventListener( 'keydown', onKeyDown );
		return () => document.removeEventListener( 'keydown', onKeyDown );
	}, [] );

	/**
	 * Opens a URL, asking first when there are unsaved changes.
	 *
	 * @param {string} url URL.
	 */
	const go = ( url ) => {
		if ( dirty ) {
			setConfirm( { kind: 'leave', url } );
		} else {
			window.location.assign( url );
		}
	};

	const reload = () =>
		api.fetchSnippet( saved.id ).then( ( item ) => {
			setSaved( item );
			setDraft( ( current ) =>
				changedFields( current, base ).length
					? current
					: toDraft( item )
			);
			return item;
		} );

	const approve = () => {
		setApproving( true );
		api.act( saved.id, 'approve' )
			.then( reload )
			.then( ( item ) =>
				toast.show( {
					message: item.active
						? __(
								'Approved. The snippet runs again.',
								'scriptdock'
							)
						: __( 'Approved.', 'scriptdock' ),
				} )
			)
			.catch( ( error ) => {
				toast.show( {
					tone: 'error',
					message:
						error && error.code === 'scriptdock_test_fatal'
							? __(
									'It crashed when ScriptDock tried it, so it stays paused.',
									'scriptdock'
								)
							: api.errorMessage( error ),
				} );
				reload().catch( () => {} );
			} )
			.finally( () => setApproving( false ) );
	};

	const untrash = () => {
		setUntrashing( true );
		api.act( saved.id, 'untrash' )
			.then( reload )
			.then( () =>
				toast.show( {
					message: __(
						'Restored. It is switched off.',
						'scriptdock'
					),
				} )
			)
			.catch( ( error ) =>
				toast.show( {
					tone: 'error',
					message: api.errorMessage( error ),
				} )
			)
			.finally( () => setUntrashing( false ) );
	};

	const run = () => {
		setRunning( true );
		setResult( null );
		api.act( saved.id, 'run' )
			.then( ( response ) =>
				setResult( {
					ok: true,
					output: response.output,
					ms: response.ms,
				} )
			)
			.catch( ( error ) => {
				setResult( { ok: false, message: api.errorMessage( error ) } );
				// The error is recorded on the snippet; show its banner.
				reload().catch( () => {} );
			} )
			.finally( () => setRunning( false ) );
	};

	const trashIt = () => {
		setConfirm( null );
		api.trash( saved.id )
			.then( () => {
				guard.allow();
				window.location.assign( boot.urls.list );
			} )
			.catch( ( error ) =>
				toast.show( {
					tone: 'error',
					message: api.errorMessage( error ),
				} )
			);
	};

	const duplicate = () =>
		api
			.act( saved.id, 'duplicate' )
			.then( ( response ) => go( response.item.edit_url ) )
			.catch( ( error ) =>
				toast.show( {
					tone: 'error',
					message: api.errorMessage( error ),
				} )
			);

	const exportIt = () =>
		api
			.exportSnippets( [ saved.id ] )
			.then( ( response ) =>
				api.download( response.filename, response.data )
			)
			.catch( ( error ) =>
				toast.show( {
					tone: 'error',
					message: api.errorMessage( error ),
				} )
			);

	const shortcode = saved.id ? `[scriptdock id="${ saved.id }"]` : '';
	const copyShortcode = () =>
		copyText( shortcode )
			.then( () =>
				toast.show( {
					message: __( 'Shortcode copied.', 'scriptdock' ),
				} )
			)
			.catch( () =>
				toast.show( {
					tone: 'error',
					message: __(
						'Couldn’t copy. Select the shortcode and copy it yourself.',
						'scriptdock'
					),
				} )
			);

	const openHistory = ( revision = 0 ) => setHistory( { revision } );

	const menuItems = isNew
		? []
		: [
				{
					label: __( 'Duplicate', 'scriptdock' ),
					icon: 'copy',
					onClick: duplicate,
					disabled: ! saved.can_edit,
				},
				{
					label: __( 'Export', 'scriptdock' ),
					icon: 'download',
					onClick: exportIt,
				},
				{
					label: __( 'History', 'scriptdock' ),
					icon: 'history',
					onClick: () => openHistory(),
				},
				...( base.location === 'shortcode'
					? [
							{
								label: __( 'Copy shortcode', 'scriptdock' ),
								icon: 'shortcode',
								onClick: copyShortcode,
							},
						]
					: [] ),
				...( saved.status === 'trash'
					? []
					: [
							{ separator: true },
							{
								label: __( 'Move to trash', 'scriptdock' ),
								icon: 'trash',
								danger: true,
								onClick: () => setConfirm( { kind: 'trash' } ),
							},
						] ),
			];

	const lintPhp = useCallback(
		( value ) => api.lintPhp( value, draft.type ),
		[ draft.type ]
	);

	// The recorded error's line, while the code is still what failed.
	const sameCode = draft.code === base.code;
	const runtimeErrors = useMemo(
		() =>
			saved.error && saved.error.line && sameCode
				? [ { line: saved.error.line, message: saved.error.message } ]
				: [],
		[ saved.error, sameCode ]
	);

	const jump = ( line ) => {
		document
			.querySelector( '.sd-editor-screen .sd-editor' )
			?.scrollIntoView( { block: 'center' } );
		code.current?.jumpToLine( line );
	};

	const placement =
		(
			boot.locations.find( ( item ) => item.value === draft.location ) ||
			{}
		).short || '';

	// Taking code away is open to every snippet manager, so an admin who
	// cannot edit PHP may still switch a running snippet off here. It
	// applies at once, since they cannot save.
	const switchOffOnly = ! canEdit && saved.active && saved.can_deactivate;
	const deactivate = () => {
		api.act( saved.id, 'deactivate' )
			.then( reload )
			.then( () =>
				toast.show( {
					message: __( 'Switched off.', 'scriptdock' ),
				} )
			)
			.catch( ( error ) =>
				toast.show( {
					tone: 'error',
					message: api.errorMessage( error ),
				} )
			);
	};

	const switchProps = {
		active: draft.active,
		onChange: ( on ) =>
			switchOffOnly ? deactivate() : update( { active: on } ),
		locked: ! canEdit && ! switchOffOnly,
		paused: saved.paused,
		onBlockedClick: () =>
			document
				.querySelector(
					'.sd-editor-screen__banners .sd-button--danger-solid'
				)
				?.focus(),
	};

	const saveProps = {
		onSave: () => save(),
		onSaveOff: () => {
			update( { active: false } );
			save( { active: false } );
		},
		onSaveCopy: () => save( { copy: true } ),
		disabled: ! dirty || saving,
		saving,
		canSaveOff: saved.active || draft.active,
	};

	const onDemand = draft.type === 'php' && base.location === 'on_demand';

	let runBlocked = '';
	if ( isNew ) {
		runBlocked = __( 'Save the snippet first.', 'scriptdock' );
	} else if ( ! saved.trusted ) {
		runBlocked = __(
			'It changed outside ScriptDock. Approve the code before you run it.',
			'scriptdock'
		);
	}

	const loadingCard = (
		<LoadingCard
			draft={ draft }
			onOptions={ updateOptions }
			locations={ boot.locations }
			assetFiles={ boot.assetFiles }
			consent={ boot.consent }
			readOnly={ ! canEdit }
		/>
	);
	const detailsCard = (
		<DetailsCard
			draft={ draft }
			onChange={ update }
			tags={ boot.tags }
			readOnly={ ! canEdit }
		/>
	);
	const optionsCard = (
		<OptionsCard
			draft={ draft }
			onOption={ updateOptions }
			readOnly={ ! canEdit }
		/>
	);
	const safety = (
		<>
			<SafetyCard
				draft={ draft }
				item={ saved }
				onOption={ updateOptions }
				readOnly={ ! canEdit }
			/>
			{ onDemand && canEdit && (
				<RunCard
					dirty={ dirty }
					running={ running }
					blocked={ runBlocked }
					onRun={ run }
				/>
			) }
			{ ! isNew && (
				<HistoryCard item={ saved } onOpen={ () => openHistory() } />
			) }
		</>
	);
	const hasLoading = [ 'html', 'css', 'js' ].includes( draft.type );

	const heading = isNew
		? __( 'New snippet', 'scriptdock' )
		: sprintf(
				/* translators: %s: snippet title. */
				__( 'Edit snippet: %s', 'scriptdock' ),
				saved.title
			);

	// WordPress names the tab when the page loads, so saving a new snippet
	// would leave it reading "Add Snippet" for the rest of the visit. The
	// site's part of the title is kept.
	useEffect( () => {
		const tail = document.title.slice( document.title.indexOf( ' ‹ ' ) );
		document.title = tail ? heading + tail : heading;
	}, [ heading ] );

	return (
		<div className={ cx( 'sd-editor-screen__inner', phone && 'is-phone' ) }>
			<h1 className="sd-visually-hidden">{ heading }</h1>

			{ phone ? (
				<PhoneBar
					draft={ draft }
					placement={ placement }
					listUrl={ boot.urls.list }
					switchProps={ switchProps }
				/>
			) : (
				<EditorBar
					draft={ draft }
					onTitle={ ( title ) => update( { title } ) }
					isNew={ isNew }
					dirty={ dirty }
					canEdit={ canEdit }
					switchProps={ switchProps }
					save={ saveProps }
					menuItems={ menuItems }
					listUrl={ boot.urls.list }
					compact={ tablet }
				/>
			) }

			<div className="sd-editor-screen__banners">
				{ boot.safeMode.active && (
					<SafeModeBanner
						forced={ boot.safeMode.forced }
						exitUrl={ boot.safeMode.exitUrl }
					/>
				) }
				{ saved.status === 'trash' && (
					<TrashBanner busy={ untrashing } onUntrash={ untrash } />
				) }
				{ ! saved.can_edit && (
					<ReadOnlyBanner
						reason={ boot.phpReason }
						canSwitchOff={ switchOffOnly }
					/>
				) }
				{ ! saved.trusted && (
					<TamperedBanner
						item={ saved }
						onHistory={ () => openHistory() }
						approving={ approving }
						onApprove={ approve }
					/>
				) }
				{ saved.error && (
					<ErrorBanner item={ saved } onJump={ jump } />
				) }
			</div>

			<div className="sd-editor-screen__layout">
				<div className="sd-editor-screen__main">
					{ phone && (
						<div className="sd-editor-screen__phone-title">
							<label
								className="sd-field__label"
								htmlFor="sd-title-phone"
							>
								{ __( 'Title', 'scriptdock' ) }
							</label>
							<input
								id="sd-title-phone"
								className="sd-input"
								type="text"
								dir="auto"
								value={ draft.title }
								placeholder={ __(
									'Untitled snippet',
									'scriptdock'
								) }
								onChange={ ( event ) =>
									update( { title: event.target.value } )
								}
								readOnly={ ! canEdit }
								maxLength={ 200 }
							/>
						</div>
					) }
					<TypePicker
						types={ boot.types }
						value={ draft.type }
						onChange={ changeType }
						readOnly={ ! canEdit }
						reason={ boot.phpReason }
					/>
					<CodeEditor
						key={ draft.type }
						ref={ code }
						value={ draft.code }
						onChange={ ( value ) => update( { code: value } ) }
						type={ draft.type }
						settings={
							boot.codeEditors
								? boot.codeEditors[ draft.type ]
								: null
						}
						fileName={ fileName( draft.title, draft.type ) }
						title={ draft.title }
						theme={ theme }
						onThemeChange={ ( next ) => {
							setTheme( next );
							api.savePreferences( { editor_theme: next } ).catch(
								() => {}
							);
						} }
						readOnly={ ! canEdit }
						errors={ runtimeErrors }
						lintPhp={ canEdit ? lintPhp : undefined }
						onSave={ canEdit ? () => saveRef.current() : undefined }
						smartTags={
							[ 'html', 'js' ].includes( draft.type )
								? boot.smartTags
								: null
						}
						placeholder={
							canEdit ? placeholders()[ draft.type ] : ''
						}
						fullscreenActions={
							canEdit && (
								<Button
									variant="inverse"
									size="compact"
									dot="check"
									onClick={ () => saveRef.current() }
									disabled={ ! dirty || saving }
									loading={ saving }
									loadingLabel={ __(
										'Saving…',
										'scriptdock'
									) }
								>
									{ __( 'Save', 'scriptdock' ) }
								</Button>
							)
						}
					/>
					<WhereCard
						targeting={ targeting }
						pending={ describing }
						draft={ draft }
						item={ saved }
						canEdit={ canEdit }
						onEditTargeting={ () => setWizard( true ) }
					/>
					{ ! phone && loadingCard }
				</div>

				{ phone ? (
					<div className="sd-editor-screen__tabs">
						<Tabs
							label={ __( 'Snippet settings', 'scriptdock' ) }
							idPrefix="sd-editor-tab"
							selected={ tab }
							onSelect={ setTab }
							tabs={ [
								{
									id: 'details',
									label: __( 'Details', 'scriptdock' ),
								},
								...( hasLoading
									? [
											{
												id: 'loading',
												label: __(
													'Loading',
													'scriptdock'
												),
											},
										]
									: [] ),
								{
									id: 'safety',
									label: __( 'Safety', 'scriptdock' ),
								},
							] }
						/>
						<TabPanel
							idPrefix="sd-editor-tab"
							id="details"
							hidden={ tab !== 'details' }
						>
							{ detailsCard }
							{ optionsCard }
						</TabPanel>
						{ hasLoading && (
							<TabPanel
								idPrefix="sd-editor-tab"
								id="loading"
								hidden={ tab !== 'loading' }
							>
								{ loadingCard }
							</TabPanel>
						) }
						<TabPanel
							idPrefix="sd-editor-tab"
							id="safety"
							hidden={ tab !== 'safety' }
						>
							{ safety }
						</TabPanel>
					</div>
				) : (
					<aside
						className="sd-editor-screen__side"
						aria-label={ __( 'Snippet settings', 'scriptdock' ) }
					>
						{ detailsCard }
						{ safety }
						{ optionsCard }
					</aside>
				) }
			</div>

			{ phone && canEdit && (
				<PhoneSaveBar dirty={ dirty } save={ saveProps } />
			) }

			{ result && (
				<RunConsole
					result={ result }
					title={ saved.title }
					onClose={ () => setResult( null ) }
				/>
			) }

			<ConfirmDialog
				open={ !! confirm && confirm.kind === 'leave' }
				title={ __( 'Leave without saving?', 'scriptdock' ) }
				onCancel={ () => setConfirm( null ) }
				actions={
					<>
						<Button
							variant="primary"
							onClick={ () => setConfirm( null ) }
						>
							{ __( 'Keep editing', 'scriptdock' ) }
						</Button>
						<Button onClick={ () => guard.leave( confirm.url ) }>
							{ __( 'Discard changes', 'scriptdock' ) }
						</Button>
					</>
				}
			>
				{ dirty && leaveText( changed ) }
			</ConfirmDialog>

			<ConfirmDialog
				open={ !! confirm && confirm.kind === 'trash' }
				title={ __( 'Move this snippet to the trash?', 'scriptdock' ) }
				onCancel={ () => setConfirm( null ) }
				actions={
					<>
						<Button variant="danger-solid" onClick={ trashIt }>
							{ __( 'Move to trash', 'scriptdock' ) }
						</Button>
						<Button
							variant="tertiary"
							onClick={ () => setConfirm( null ) }
						>
							{ __( 'Cancel', 'scriptdock' ) }
						</Button>
					</>
				}
			>
				{ dirty
					? __(
							'It stops running now, and your unsaved changes are lost. You can restore it from the trash.',
							'scriptdock'
						)
					: __(
							'It stops running now. You can restore it from the trash.',
							'scriptdock'
						) }
			</ConfirmDialog>

			{ history && (
				<HistoryDrawer
					id={ saved.id }
					title={ draft.title || saved.title }
					type={ draft.type }
					trusted={ saved.trusted }
					start={ history.revision }
					approving={ approving }
					onClose={ () => setHistory( null ) }
					onApprove={ () => {
						setHistory( null );
						approve();
					} }
					onRestore={ ( restored ) => {
						setHistory( null );
						setSaved( restored.item );
						setDraft( toDraft( restored.item ) );
						toast.show( {
							tone:
								restored.notice &&
								restored.notice.code !== 'restored'
									? 'error'
									: 'success',
							message: restored.notice
								? restored.notice.message
								: __( 'Restored.', 'scriptdock' ),
						} );
					} }
				/>
			) }

			{ wizard && (
				<TargetingWizard
					snippet={ {
						title: draft.title,
						location: draft.location,
						location_args: draft.location_args,
						priority: draft.priority,
						conditions: draft.conditions,
						schedule: draft.schedule,
						targeting,
						consent: saved.consent,
					} }
					locations={ boot.locations }
					type={ draft.type }
					canPhp={ boot.canPhp }
					phpReason={ boot.phpReason }
					onSave={ ( next ) => {
						update( next );
						setWizard( false );
					} }
					onClose={ () => setWizard( false ) }
				/>
			) }
		</div>
	);
}
