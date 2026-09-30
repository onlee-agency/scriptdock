/**
 * S02: first run, in four steps.
 *
 * The order is deliberate. The safe mode link comes second, before anything
 * has been written, because it is the one thing that is useless to hand
 * someone after their site has already broken.
 *
 * Every step can be left: setup is a help, not a gate.
 */
import { useState } from '@wordpress/element';
import { __, _n, _x, sprintf } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { safeLinkKept } from '../settings/api';
import {
	Banner,
	Button,
	Checkbox,
	CopyChip,
	Icon,
	Illustration,
	Switch,
	SwitchField,
	isApple,
	useToast,
} from '../components';

const STEPS = 4;

/**
 * @param {Object} props      Props.
 * @param {Object} props.boot What setup found on this site.
 * @return {Element} The setup flow.
 */
export default function App( { boot } ) {
	const toast = useToast();
	const [ step, setStep ] = useState( 1 );
	const [ saved, setSaved ] = useState( false );
	const [ busy, setBusy ] = useState( '' );
	const [ imported, setImported ] = useState( null );
	const [ picked, setPicked ] = useState( () =>
		Object.fromEntries(
			boot.sources.map( ( source ) => [
				source.key,
				{ take: true, active: false },
			] )
		)
	);

	// Setup is over as soon as they leave it, whichever way they go, so it
	// never greets them a second time.
	const leave = ( href ) => {
		setBusy( 'leaving' );
		apiFetch( { path: '/scriptdock/v1/onboarding/done', method: 'POST' } )
			.catch( () => {} )
			.finally( () => {
				window.location.href = href;
			} );
	};

	const emailLink = () => {
		setBusy( 'email' );
		apiFetch( {
			path: '/scriptdock/v1/onboarding/email-link',
			method: 'POST',
		} )
			.then( ( result ) =>
				toast.show( { message: result.notice.message } )
			)
			.catch( ( error ) =>
				toast.show( {
					tone: 'error',
					message:
						error.message ||
						__( 'The email could not be sent.', 'scriptdock' ),
				} )
			)
			.finally( () => setBusy( '' ) );
	};

	const taking = boot.sources.filter(
		( source ) => picked[ source.key ].take
	);
	const total = taking.reduce( ( sum, source ) => sum + source.count, 0 );

	const runImport = () => {
		if ( ! taking.length ) {
			setStep( 4 );
			return;
		}
		setBusy( 'import' );
		Promise.all(
			taking.map( ( source ) =>
				apiFetch( {
					path: '/scriptdock/v1/tools/migrate',
					method: 'POST',
					data: {
						source: source.key,
						activate: picked[ source.key ].active,
					},
				} ).catch( ( error ) => ( { failed: source.label, error } ) )
			)
		)
			.then( ( results ) => {
				setImported( summarise( results ) );
				setStep( 4 );
			} )
			.finally( () => setBusy( '' ) );
	};

	return (
		<div className="sd-onboarding">
			<div className="sd-onboarding__card">
				{ step === 1 && <Welcome /> }
				{ step === 2 && (
					<SafeLink
						boot={ boot }
						saved={ saved }
						onSaved={ ( on ) => {
							setSaved( on );
							if ( on ) {
								safeLinkKept();
							}
						} }
						onEmail={ emailLink }
						emailing={ busy === 'email' }
					/>
				) }
				{ step === 3 && (
					<Bring
						boot={ boot }
						picked={ picked }
						onPick={ ( key, change ) =>
							setPicked( ( old ) => ( {
								...old,
								[ key ]: { ...old[ key ], ...change },
							} ) )
						}
					/>
				) }
				{ step === 4 && (
					<Ready boot={ boot } imported={ imported } onGo={ leave } />
				) }

				<div className="sd-onboarding__foot">
					{ step > 1 && step < 4 && (
						<Button onClick={ () => setStep( step - 1 ) }>
							{ __( 'Back', 'scriptdock' ) }
						</Button>
					) }

					<Dots step={ step } centred={ step > 1 && step < 4 } />

					{ step === 1 && (
						<>
							<Button
								variant="tertiary"
								onClick={ () => leave( boot.urls.overview ) }
							>
								{ __( 'Skip setup', 'scriptdock' ) }
							</Button>
							<Button
								variant="primary"
								size="lg"
								dot="chevron-right"
								onClick={ () => setStep( 2 ) }
							>
								{ __( 'Get started', 'scriptdock' ) }
							</Button>
						</>
					) }

					{ step === 2 && (
						<Button
							variant="primary"
							size="lg"
							dot="chevron-right"
							disabled={ ! saved }
							onClick={ () =>
								setStep( boot.sources.length ? 3 : 4 )
							}
						>
							{ __( 'Next', 'scriptdock' ) }
						</Button>
					) }

					{ step === 3 && (
						<>
							<Button
								variant="tertiary"
								onClick={ () => setStep( 4 ) }
							>
								{ __( 'Skip', 'scriptdock' ) }
							</Button>
							<Button
								variant="primary"
								size="lg"
								dot="download"
								loading={ busy === 'import' }
								loadingLabel={ __(
									'Importing…',
									'scriptdock'
								) }
								onClick={ runImport }
							>
								{ total
									? sprintf(
											/* translators: %d: how many snippets will be imported. */
											__( 'Import %d', 'scriptdock' ),
											total
										)
									: __( 'Continue', 'scriptdock' ) }
							</Button>
						</>
					) }

					{ step === 4 && (
						<Button
							variant="primary"
							size="lg"
							loading={ busy === 'leaving' }
							loadingLabel={ __( 'Finishing…', 'scriptdock' ) }
							onClick={ () => leave( boot.urls.overview ) }
						>
							{ __( 'Finish', 'scriptdock' ) }
						</Button>
					) }
				</div>
			</div>
		</div>
	);
}

/**
 * Step 1: what ScriptDock is for.
 *
 * @return {Element} The step.
 */
function Welcome() {
	const points = [
		{
			icon: 'shield-check',
			title: __( 'Crash-proof', 'scriptdock' ),
			text: __(
				'Broken code is switched off before it can take your site down.',
				'scriptdock'
			),
		},
		{
			icon: 'bolt',
			title: __( 'Fast', 'scriptdock' ),
			text: __(
				'Smart loading and cached files keep pages quick.',
				'scriptdock'
			),
		},
		{
			icon: 'plus-circle',
			title: __( 'Free', 'scriptdock' ),
			text: __(
				'Every feature. No upsells, no ads, no tracking.',
				'scriptdock'
			),
		},
	];

	return (
		<div className="sd-onboarding__body sd-onboarding__body--welcome">
			<div className="sd-onboarding__glow" aria-hidden="true" />
			<span className="sd-onboarding__brand">
				<span className="sd-brand-dot" aria-hidden="true" />
				<span className="sd-wordmark">scriptdock</span>
			</span>

			<div className="sd-onboarding__lead">
				<div className="sd-onboarding__words">
					<h1 className="sd-onboarding__h1">
						{ __( 'Add code anywhere.', 'scriptdock' ) }{ ' ' }
						<span className="sd-onboarding__accent">
							{ __( 'Safely.', 'scriptdock' ) }
						</span>
					</h1>
					<p className="sd-onboarding__text">
						{ __(
							'Tracking pixels, custom CSS, PHP hooks — anywhere on your site, without touching a theme file.',
							'scriptdock'
						) }
					</p>
				</div>
				<Illustration name="welcome" width={ 150 } />
			</div>

			<ul className="sd-onboarding__points">
				{ points.map( ( point ) => (
					<li key={ point.icon }>
						<Icon name={ point.icon } size={ 26 } stroke={ 1.5 } />
						<span className="sd-onboarding__point-title">
							{ point.title }
						</span>
						<span className="sd-onboarding__point-text">
							{ point.text }
						</span>
					</li>
				) ) }
			</ul>
		</div>
	);
}

/**
 * Step 2: the safe mode link, and a checkbox that makes you look at it.
 *
 * @param {Object}   props          Props.
 * @param {Object}   props.boot     Bootstrap data.
 * @param {boolean}  props.saved    Whether they have said they saved it.
 * @param {Function} props.onSaved  Records that.
 * @param {Function} props.onEmail  Emails the link.
 * @param {boolean}  props.emailing An email is on its way.
 * @return {Element} The step.
 */
function SafeLink( { boot, saved, onSaved, onEmail, emailing } ) {
	return (
		<div className="sd-onboarding__body">
			<Step number={ 2 } />
			<div className="sd-onboarding__words">
				<h1 className="sd-onboarding__h1 sd-onboarding__h1--sm">
					{ __( 'Save your safe mode link', 'scriptdock' ) }
				</h1>
				<p className="sd-onboarding__text">
					{ __(
						'If code ever locks you out of WordPress, opening this link pauses every snippet in your browser so you can fix things. Keep it somewhere outside WordPress — a password manager, a note on your phone.',
						'scriptdock'
					) }
				</p>
			</div>

			{ /* It scrolls sideways, so a keyboard has to be able to reach
			     it and read the whole link. */ }
			<code
				className="sd-onboarding__link"
				tabIndex={ 0 }
				role="group"
				aria-label={ __( 'Your safe mode link', 'scriptdock' ) }
			>
				{ boot.safeLink }
			</code>

			<div className="sd-onboarding__row">
				<CopyChip
					value={ boot.safeLink }
					text={ __( 'Copy link', 'scriptdock' ) }
					label={ __( 'Copy the safe mode link', 'scriptdock' ) }
					onCopy={ safeLinkKept }
				/>
				<Button
					loading={ emailing }
					loadingLabel={ __( 'Sending…', 'scriptdock' ) }
					onClick={ onEmail }
				>
					{ __( 'Email it to me', 'scriptdock' ) }
				</Button>
				<span className="sd-onboarding__to">
					{ sprintf(
						/* translators: %s: the current user's email address. */
						__( 'to %s', 'scriptdock' ),
						boot.email
					) }
				</span>
			</div>

			<Banner tone="info" inline>
				{ __(
					'Anyone with this link can pause your snippets, so treat it like a password. You can make a new one at any time in Settings.',
					'scriptdock'
				) }
			</Banner>

			<Checkbox
				className={
					saved
						? 'sd-onboarding__confirm is-checked'
						: 'sd-onboarding__confirm'
				}
				checked={ saved }
				onChange={ onSaved }
				label={ __( 'I saved it somewhere safe', 'scriptdock' ) }
			/>
		</div>
	);
}

/**
 * Step 3: snippets found in other plugins.
 *
 * @param {Object}   props        Props.
 * @param {Object}   props.boot   Bootstrap data.
 * @param {Object}   props.picked What is ticked.
 * @param {Function} props.onPick Changes a source.
 * @return {Element} The step.
 */
function Bring( { boot, picked, onPick } ) {
	return (
		<div className="sd-onboarding__body">
			<Step number={ 3 } />
			<div className="sd-onboarding__words">
				<h1 className="sd-onboarding__h1 sd-onboarding__h1--sm">
					{ __( 'Bring your snippets over', 'scriptdock' ) }
				</h1>
				<p className="sd-onboarding__text">
					{ __(
						'We found these on your site. Importing copies them across and leaves the originals alone.',
						'scriptdock'
					) }
				</p>
			</div>

			<ul className="sd-onboarding__sources">
				{ boot.sources.map( ( source ) => (
					<li
						key={ source.key }
						className={
							picked[ source.key ].take
								? 'sd-onboarding__source is-on'
								: 'sd-onboarding__source'
						}
					>
						<Switch
							checked={ picked[ source.key ].take }
							label={ sprintf(
								/* translators: %s: the other plugin's name. */
								__( 'Import from %s', 'scriptdock' ),
								source.label
							) }
							onChange={ ( take ) =>
								onPick( source.key, { take } )
							}
						/>
						<span className="sd-onboarding__source-text">
							<span className="sd-onboarding__source-name">
								{ source.label }
							</span>
							<span className="sd-onboarding__source-count">
								{ sprintf(
									/* translators: %d: how many snippets were found. */
									_n(
										'%d snippet',
										'%d snippets',
										source.count,
										'scriptdock'
									),
									source.count
								) }
							</span>
						</span>
						{ picked[ source.key ].take && (
							<SwitchField
								label={
									<>
										{ __( 'Keep active', 'scriptdock' ) }
										<span className="sd-visually-hidden">
											{ ` (${ source.label })` }
										</span>
									</>
								}
								checked={ picked[ source.key ].active }
								onChange={ ( active ) =>
									onPick( source.key, { active } )
								}
							/>
						) }
					</li>
				) ) }
			</ul>

			<Banner tone="warning" inline>
				{ __(
					'Deactivate the old plugins once you have checked everything works, or the same code runs twice.',
					'scriptdock'
				) }
			</Banner>
		</div>
	);
}

/**
 * Step 4: three ways on.
 *
 * @param {Object}   props          Props.
 * @param {Object}   props.boot     Bootstrap data.
 * @param {Object}   props.imported What the import did, when there was one.
 * @param {Function} props.onGo     Leaves setup for a screen.
 * @return {Element} The step.
 */
function Ready( { boot, imported, onGo } ) {
	const choices = [
		{
			id: 'new',
			icon: 'plus',
			title: __( 'Add a snippet', 'scriptdock' ),
			text: __( 'Start with a blank editor.', 'scriptdock' ),
			href: boot.urls.new,
		},
		{
			id: 'library',
			icon: 'library',
			title: __( 'Browse the library', 'scriptdock' ),
			text: sprintf(
				/* translators: %d: how many ready-made snippets there are. */
				_n(
					'%d ready-made snippet.',
					'%d ready-made snippets.',
					boot.library,
					'scriptdock'
				),
				boot.library
			),
			href: boot.urls.library,
			featured: true,
		},
		{
			id: 'overview',
			icon: 'overview',
			title: __( 'Go to Overview', 'scriptdock' ),
			text: __( 'See everything at a glance.', 'scriptdock' ),
			href: boot.urls.overview,
		},
	];

	return (
		<div className="sd-onboarding__body">
			<div
				className="sd-onboarding__glow sd-onboarding__glow--low"
				aria-hidden="true"
			/>
			<Step number={ 4 } />
			<div className="sd-onboarding__words">
				<h1 className="sd-onboarding__h1 sd-onboarding__h1--sm">
					{ __( 'You’re ready', 'scriptdock' ) }
				</h1>
				<p className="sd-onboarding__text">{ readyText( imported ) }</p>
			</div>

			{ imported && imported.warnings.length > 0 && (
				<Banner tone="warning" inline>
					{ imported.warnings.length === 1 ? (
						imported.warnings[ 0 ]
					) : (
						<ul className="sd-onboarding__warnings">
							{ imported.warnings.map( ( warning, index ) => (
								<li key={ index }>{ warning }</li>
							) ) }
						</ul>
					) }
				</Banner>
			) }

			<div className="sd-onboarding__choices">
				{ choices.map( ( choice ) => (
					<button
						key={ choice.id }
						type="button"
						className={
							choice.featured
								? 'sd-onboarding__choice is-featured'
								: 'sd-onboarding__choice'
						}
						onClick={ () => onGo( choice.href ) }
					>
						<Icon name={ choice.icon } size={ 26 } stroke={ 1.6 } />
						<span className="sd-onboarding__choice-title">
							{ choice.title }
						</span>
						<span className="sd-onboarding__choice-text">
							{ choice.text }
						</span>
					</button>
				) ) }
			</div>

			<p className="sd-onboarding__tip">
				{ __( 'Tip: press', 'scriptdock' ) }{ ' ' }
				<kbd className="sd-kbd">{ shortcut() }</kbd>{ ' ' }
				{ __(
					'anywhere to jump to a snippet or run an action.',
					'scriptdock'
				) }
			</p>
		</div>
	);
}

/**
 * "Step 2 of 4", above the heading.
 *
 * @param {Object} props        Props.
 * @param {number} props.number Which step.
 * @return {Element} The label.
 */
function Step( { number } ) {
	return (
		<span className="sd-onboarding__step">
			{ sprintf(
				/* translators: 1: this step, 2: how many steps there are. */
				__( 'Step %1$d of %2$d', 'scriptdock' ),
				number,
				STEPS
			) }
		</span>
	);
}

/**
 * The dot stepper. It is decoration: the heading already says where you are.
 *
 * @param {Object}  props         Props.
 * @param {number}  props.step    Which step.
 * @param {boolean} props.centred Sit in the middle of the footer.
 * @return {Element} The dots.
 */
function Dots( { step, centred } ) {
	return (
		<span
			className={
				centred
					? 'sd-onboarding__dots is-centred'
					: 'sd-onboarding__dots'
			}
			aria-hidden="true"
		>
			{ Array.from( { length: STEPS } ).map( ( ignore, index ) => (
				<span
					key={ index }
					className={
						index + 1 === step
							? 'sd-onboarding__dot is-here'
							: 'sd-onboarding__dot'
					}
				/>
			) ) }
		</span>
	);
}

/**
 * What the import came to.
 *
 * @param {Array} results One result per plugin.
 * @return {Object} { count, warnings }.
 */
function summarise( results ) {
	let count = 0;
	let active = 0;
	const warnings = [];
	results.forEach( ( result ) => {
		if ( ! result || result.failed ) {
			warnings.push(
				sprintf(
					/* translators: %s: the other plugin's name. */
					__( 'Nothing could be read from %s.', 'scriptdock' ),
					result ? result.failed : __( 'a plugin', 'scriptdock' )
				)
			);
			return;
		}
		count += result.imported || 0;
		active += result.active || 0;
		( result.warnings || [] ).forEach( ( warning ) =>
			warnings.push( warning )
		);
	} );
	return { count, active, warnings };
}

/**
 * What the last step says about an import: how many came in and whether
 * they run.
 *
 * @param {?Object} imported Summary from summarise(), or null.
 * @return {string} The sentence.
 */
function readyText( imported ) {
	if ( ! imported || ! imported.count ) {
		return __(
			'ScriptDock is set up. What would you like to do first?',
			'scriptdock'
		);
	}
	if ( ! imported.active ) {
		return sprintf(
			/* translators: %d: how many snippets were imported. */
			_n(
				'%d snippet imported and switched off, waiting for you to check it. What would you like to do first?',
				'%d snippets imported and switched off, waiting for you to check them. What would you like to do first?',
				imported.count,
				'scriptdock'
			),
			imported.count
		);
	}
	if ( imported.active === imported.count ) {
		return sprintf(
			/* translators: %d: how many snippets were imported. */
			_n(
				'%d snippet imported and running, as it was before. What would you like to do first?',
				'%d snippets imported and running, as they were before. What would you like to do first?',
				imported.count,
				'scriptdock'
			),
			imported.count
		);
	}
	return sprintf(
		/* translators: 1: how many snippets were imported, 2: how many of them run. */
		_n(
			'%1$d snippet imported, %2$d running. The rest are switched off, waiting for you to check them. What would you like to do first?',
			'%1$d snippets imported, %2$d running. The rest are switched off, waiting for you to check them. What would you like to do first?',
			imported.count,
			'scriptdock'
		),
		imported.count,
		imported.active
	);
}

/**
 * The palette's shortcut, as this platform writes it.
 *
 * @return {string} ⌘K or Ctrl K.
 */
function shortcut() {
	return isApple()
		? _x( '⌘K', 'keyboard shortcut on macOS', 'scriptdock' )
		: _x(
				'Ctrl K',
				'keyboard shortcut on Windows and Linux',
				'scriptdock'
			);
}
