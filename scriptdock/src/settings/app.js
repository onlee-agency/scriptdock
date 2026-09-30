/**
 * S13: Settings. Section nav on the left, cards on the right, one save bar
 * for everything that can be changed.
 *
 * The cards that explain safety read the site's own state; they are not
 * settings and have no switches.
 */
import { useEffect, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import {
	Badge,
	Banner,
	Button,
	Card,
	Checkbox,
	ConfirmDialog,
	CopyChip,
	NumberStepper,
	SaveBar,
	SegmentedControl,
	SettingsRow,
	SwitchField,
	cx,
	useToast,
} from '../components';
import * as api from './api';

const SECTIONS = [
	{ key: 'general', label: __( 'General', 'scriptdock' ) },
	{ key: 'errors', label: __( 'Error protection', 'scriptdock' ) },
	{ key: 'pages', label: __( 'Page scripts', 'scriptdock' ) },
	{ key: 'performance', label: __( 'Performance', 'scriptdock' ) },
	{ key: 'editor', label: __( 'Editor', 'scriptdock' ) },
	{ key: 'safety', label: __( 'Safety & security', 'scriptdock' ) },
	{ key: 'uninstall', label: __( 'Uninstall', 'scriptdock' ) },
	{ key: 'about', label: __( 'About', 'scriptdock' ) },
];

/**
 * @param {Object} props      Props.
 * @param {Object} props.boot The settings and what they mean here.
 * @return {Element} The screen.
 */
export default function App( { boot } ) {
	const toast = useToast();
	const [ item, setItem ] = useState( boot.item );
	const [ draft, setDraft ] = useState( boot.item.values );
	const [ saving, setSaving ] = useState( false );
	const [ confirm, setConfirm ] = useState( null );
	const [ busy, setBusy ] = useState( '' );
	const dirty = JSON.stringify( draft ) !== JSON.stringify( item.values );

	useEffect( () => {
		const onKeyDown = ( event ) => {
			const meta = event.metaKey || event.ctrlKey;
			if ( ! meta || event.key !== 's' || event.defaultPrevented ) {
				return;
			}
			event.preventDefault();
			if ( dirty ) {
				save();
			}
		};
		document.addEventListener( 'keydown', onKeyDown );
		return () => document.removeEventListener( 'keydown', onKeyDown );
	} );

	const set = ( change ) => setDraft( ( old ) => ( { ...old, ...change } ) );

	const fail = ( error ) =>
		toast.show( { tone: 'error', message: api.errorMessage( error ) } );

	const save = () => {
		setSaving( true );
		api.saveSettings( draft )
			.then( ( result ) => {
				setItem( result.item );
				setDraft( result.item.values );
				toast.show( { message: result.notice.message } );
			} )
			.catch( fail )
			.finally( () => setSaving( false ) );
	};

	const newLink = () => {
		setConfirm( null );
		setBusy( 'link' );
		api.newSafeLink()
			.then( ( result ) => {
				setItem( result.item );
				toast.show( { message: result.notice.message } );
			} )
			.catch( fail )
			.finally( () => setBusy( '' ) );
	};

	return (
		<div className="sd-settings">
			<div className="sd-settings__head">
				<h1 className="sd-screen__title">
					{ __( 'Settings', 'scriptdock' ) }
				</h1>
			</div>

			<div className="sd-settings__layout">
				<nav
					className="sd-settings__nav"
					aria-label={ __( 'Settings sections', 'scriptdock' ) }
				>
					<ul>
						{ SECTIONS.map( ( section ) => (
							<li key={ section.key }>
								<a href={ `#sd-${ section.key }` }>
									{ section.label }
									{ section.key === 'safety' &&
										item.tamper.review > 0 && (
											<span className="sd-settings__count">
												{ item.tamper.review }
											</span>
										) }
								</a>
							</li>
						) ) }
					</ul>
				</nav>

				<div className="sd-settings__cards">
					<Card
						anchor="sd-general"
						title={ __( 'General', 'scriptdock' ) }
					>
						<SettingsRow
							title={ __( 'Admin bar inspector', 'scriptdock' ) }
							description={ __(
								'Shows which snippets ran on the page you are looking at, for admins only.',
								'scriptdock'
							) }
						>
							<SwitchField
								label={ __(
									'Show the inspector',
									'scriptdock'
								) }
								checked={ draft.admin_bar }
								onChange={ ( on ) => set( { admin_bar: on } ) }
							/>
						</SettingsRow>
					</Card>

					<Card
						anchor="sd-errors"
						title={ __( 'Error protection', 'scriptdock' ) }
					>
						<SettingsRow
							title={ __(
								'Switch off code that crashes',
								'scriptdock'
							) }
							description={ __(
								'A PHP snippet that causes a fatal error is switched off, so the site keeps working.',
								'scriptdock'
							) }
						>
							<SwitchField
								label={ __( 'Switch it off', 'scriptdock' ) }
								checked={ draft.auto_disable }
								onChange={ ( on ) =>
									set( { auto_disable: on } )
								}
							/>
						</SettingsRow>
						<SettingsRow
							title={ __(
								'Email me when that happens',
								'scriptdock'
							) }
							description={ sprintf(
								/* translators: %s: email address. */
								__( 'Sent to %s.', 'scriptdock' ),
								item.email
							) }
						>
							<SwitchField
								label={ __( 'Send the email', 'scriptdock' ) }
								checked={ draft.error_email }
								onChange={ ( on ) =>
									set( { error_email: on } )
								}
							/>
						</SettingsRow>
					</Card>

					<Card
						anchor="sd-pages"
						title={ __( 'Page scripts', 'scriptdock' ) }
						subtitle={ __(
							'Which content types get their own code box in the editor.',
							'scriptdock'
						) }
					>
						<div className="sd-settings__checks">
							{ item.types.map( ( type ) => (
								<Checkbox
									key={ type.value }
									label={ type.label }
									checked={ draft.page_post_types.includes(
										type.value
									) }
									onChange={ ( on ) =>
										set( {
											page_post_types: on
												? [
														...draft.page_post_types,
														type.value,
													]
												: draft.page_post_types.filter(
														( entry ) =>
															entry !== type.value
													),
										} )
									}
								/>
							) ) }
						</div>
					</Card>

					<Card
						anchor="sd-performance"
						title={ __( 'Performance', 'scriptdock' ) }
					>
						<SettingsRow
							title={ __(
								'Serve CSS and JS as files',
								'scriptdock'
							) }
							description={ sprintf(
								/* translators: %s: folder the files are written to. */
								__(
									'Cached files load faster than inline code and can be cached by the browser. Written to %s.',
									'scriptdock'
								),
								item.files.path
							) }
						>
							<SwitchField
								label={ __( 'Use files', 'scriptdock' ) }
								checked={ draft.asset_files }
								onChange={ ( on ) =>
									set( { asset_files: on } )
								}
							/>
						</SettingsRow>
						<SettingsRow
							title={ __( 'Minify CSS', 'scriptdock' ) }
							description={ __(
								'Strips comments and spacing from CSS snippets as they are served.',
								'scriptdock'
							) }
						>
							<SwitchField
								label={ __( 'Minify', 'scriptdock' ) }
								checked={ draft.minify_css }
								onChange={ ( on ) => set( { minify_css: on } ) }
							/>
						</SettingsRow>
					</Card>

					<Card
						anchor="sd-editor"
						title={ __( 'Editor', 'scriptdock' ) }
					>
						<SettingsRow
							title={ __( 'Theme', 'scriptdock' ) }
							description={ __(
								'The default for everyone; each person can switch it in the editor.',
								'scriptdock'
							) }
						>
							<SegmentedControl
								label={ __( 'Editor theme', 'scriptdock' ) }
								value={ draft.editor_theme }
								onChange={ ( theme ) =>
									set( { editor_theme: theme } )
								}
								options={ [
									{
										value: 'default',
										label: __( 'Light', 'scriptdock' ),
									},
									{
										value: 'dark',
										label: __( 'Dark', 'scriptdock' ),
									},
								] }
							/>
						</SettingsRow>
						<SettingsRow
							title={ __( 'Versions to keep', 'scriptdock' ) }
							description={ __(
								'Older versions stay in a snippet’s history so you can put them back.',
								'scriptdock'
							) }
						>
							<NumberStepper
								label={ __( 'Versions', 'scriptdock' ) }
								value={ draft.revisions }
								min={ 0 }
								max={ 200 }
								onChange={ ( revisions ) =>
									set( { revisions } )
								}
							/>
						</SettingsRow>
					</Card>

					<Card
						anchor="sd-safety"
						title={ __( 'Safe mode', 'scriptdock' ) }
						subtitle={ __(
							'A private link that stops every snippet running for your browser, so a broken site can still be fixed.',
							'scriptdock'
						) }
					>
						<CopyChip
							value={ item.safe.url }
							onCopy={ api.safeLinkKept }
						/>
						<p className="sd-settings__hint">
							{ __(
								'Keep this somewhere safe, outside the site. Anyone with the link can turn snippets off for themselves.',
								'scriptdock'
							) }
						</p>
						<div className="sd-settings__row">
							<Button
								size="compact"
								loading={ busy === 'link' }
								loadingLabel={ __( 'Making…', 'scriptdock' ) }
								onClick={ () => setConfirm( 'link' ) }
							>
								{ __( 'Make a new link', 'scriptdock' ) }
							</Button>
							<code className="sd-settings__code">
								{ item.safe.constant }
							</code>
						</div>
						{ item.safe.forced && (
							<Banner tone="warning" inline>
								{ __(
									'Safe mode is forced on for everyone by that constant in wp-config.php.',
									'scriptdock'
								) }
							</Banner>
						) }
					</Card>

					<Card title={ __( 'Tamper protection', 'scriptdock' ) }>
						<p className="sd-settings__state">
							<Badge
								tone={ item.tamper.on ? 'success' : 'warning' }
								size="sm"
							>
								{ item.tamper.on
									? __( 'On', 'scriptdock' )
									: __( 'Off', 'scriptdock' ) }
							</Badge>
							{ item.tamper.on
								? __(
										'Snippets are signed, so code changed straight in the database does not run.',
										'scriptdock'
									)
								: item.tamper.off_note }
						</p>
						{ item.tamper.on && (
							<p className="sd-settings__hint">
								{ item.tamper.source }
							</p>
						) }
						{ item.tamper.review > 0 && (
							<Banner
								tone="danger"
								compact
								className="sd-banner--side-actions"
								actions={
									<>
										<Button
											size="compact"
											variant="outline"
											href={ item.tamper.review_url }
										>
											{ __( 'Review', 'scriptdock' ) }
										</Button>
										<Button
											size="compact"
											variant="danger"
											onClick={ () =>
												setConfirm( 'approve' )
											}
										>
											{ __(
												'Approve all',
												'scriptdock'
											) }
										</Button>
									</>
								}
							>
								{ sprintf(
									/* translators: %d: number of snippets. */
									_n(
										'%d snippet is waiting for review.',
										'%d snippets are waiting for review.',
										item.tamper.review,
										'scriptdock'
									),
									item.tamper.review
								) }
							</Banner>
						) }
					</Card>

					<Card title={ __( 'PHP snippets', 'scriptdock' ) }>
						<p className="sd-settings__state">
							<Badge
								tone={
									item.php.available ? 'success' : 'warning'
								}
								size="sm"
							>
								{ item.php.available
									? __( 'Available', 'scriptdock' )
									: __( 'Unavailable', 'scriptdock' ) }
							</Badge>
							{ item.php.available
								? __(
										'You can write and run PHP snippets on this site.',
										'scriptdock'
									)
								: item.php.reason }
						</p>
						<ul className="sd-settings__constants">
							{ item.php.constants.map( ( line ) => (
								<li key={ line }>
									<code>{ line }</code>
								</li>
							) ) }
						</ul>
					</Card>

					<Card
						anchor="sd-uninstall"
						title={ __( 'Uninstall', 'scriptdock' ) }
						className={ cx(
							'sd-settings__danger',
							draft.delete_on_uninstall && 'is-armed'
						) }
					>
						<SettingsRow
							title={ __(
								'Delete everything when the plugin is deleted',
								'scriptdock'
							) }
							description={ __(
								'Snippets, the code on your pages, settings and files all go with it. Deactivating the plugin never deletes anything.',
								'scriptdock'
							) }
						>
							<SwitchField
								label={ __( 'Delete my data', 'scriptdock' ) }
								checked={ draft.delete_on_uninstall }
								onChange={ ( on ) =>
									set( { delete_on_uninstall: on } )
								}
							/>
						</SettingsRow>
					</Card>

					<Card
						anchor="sd-about"
						title={ __( 'About', 'scriptdock' ) }
					>
						<p className="sd-settings__state">
							{ sprintf(
								/* translators: %s: version number. */
								__( 'ScriptDock %s', 'scriptdock' ),
								item.about.version
							) }
						</p>
						<div className="sd-settings__badges">
							{ item.about.badges.map( ( badge ) => (
								<Badge key={ badge } tone="muted" size="sm">
									{ badge }
								</Badge>
							) ) }
						</div>
					</Card>
				</div>
			</div>

			{ dirty && (
				<SaveBar
					saving={ saving }
					onDiscard={ () => setDraft( item.values ) }
					onSave={ save }
				/>
			) }

			<ConfirmDialog
				open={ confirm === 'link' }
				title={ __( 'Make a new safe mode link?', 'scriptdock' ) }
				onCancel={ () => setConfirm( null ) }
				actions={
					<>
						<Button variant="primary" onClick={ newLink }>
							{ __( 'Make a new link', 'scriptdock' ) }
						</Button>
						<Button
							variant="tertiary"
							onClick={ () => setConfirm( null ) }
						>
							{ __( 'Keep the one I have', 'scriptdock' ) }
						</Button>
					</>
				}
			>
				{ __(
					'The link you saved stops working, so replace it wherever you keep it.',
					'scriptdock'
				) }
			</ConfirmDialog>

			<ConfirmDialog
				open={ confirm === 'approve' }
				title={ __( 'Approve every waiting snippet?', 'scriptdock' ) }
				onCancel={ () => setConfirm( null ) }
				actions={
					<>
						<Button
							variant="danger"
							href={ item.tamper.review_url }
						>
							{ __( 'Review them first', 'scriptdock' ) }
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
				{ __(
					'Approving marks the code as it stands now as trusted. Only do that if you changed your security keys or moved the site yourself — otherwise look at the changes first.',
					'scriptdock'
				) }
			</ConfirmDialog>
		</div>
	);
}
