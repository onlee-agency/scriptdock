/**
 * S05: the targeting wizard. Five steps decide where a snippet runs, and the
 * rail on the right says what that means in plain language as it changes.
 *
 * Nothing here saves: the wizard hands a finished draft back to the screen
 * that opened it, which saves it with the rest of the snippet.
 */
import {
	useCallback,
	useEffect,
	useMemo,
	useRef,
	useState,
} from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { speak } from '@wordpress/a11y';
import {
	Button,
	ConfirmDialog,
	Modal,
	Stepper,
	StepperCompact,
	SwitchField,
	cx,
} from '../components';
import { consentLabel } from '../editor/consent';
import * as api from './api';
import {
	counts,
	parseConditions,
	sameTargeting,
	toConditions,
	toDraft,
} from './model';
import Rail from './rail';
import sentenceParts from './sentence';
import PlacementStep from './step-placement';
import ContentStep from './step-content';
import AudienceStep from './step-audience';
import ScheduleStep from './step-schedule';
import ReviewStep from './step-review';
import AdvancedRules from './advanced';

const STEPS = [ 'placement', 'content', 'audience', 'schedule', 'review' ];

/**
 * @param {Object}   props           Props.
 * @param {Object}   props.snippet   Snippet being targeted.
 * @param {Array}    props.locations Placements from the editor's bootstrap.
 * @param {string}   props.type      Code type, which decides what is available.
 * @param {boolean}  props.canPhp    Whether this user may use PHP conditions.
 * @param {string}   props.phpReason Why not, when they may not.
 * @param {Function} props.onSave    Called with the finished targeting.
 * @param {Function} props.onClose   Closes the wizard.
 * @return {Element} The wizard.
 */
export default function TargetingWizard( {
	snippet,
	locations = [],
	type = 'html',
	canPhp = true,
	phpReason = '',
	onSave,
	onClose,
} ) {
	const start = useMemo( () => toDraft( snippet ), [ snippet ] );
	const [ draft, setDraft ] = useState( start );
	const [ step, setStep ] = useState( 'placement' );
	const [ advanced, setAdvanced ] = useState( !! start.custom );
	const [ options, setOptions ] = useState( null );
	const [ labels, setLabels ] = useState( {} );
	const [ parts, setParts ] = useState( snippet.targeting?.parts || [] );
	const [ estimate, setEstimate ] = useState( null );
	const [ working, setWorking ] = useState( false );
	const [ leaving, setLeaving ] = useState( false );
	const conditions = useMemo( () => toConditions( draft ), [ draft ] );
	const dirty = ! sameTargeting( draft, start );
	const index = STEPS.indexOf( step );
	const patch = ( change ) =>
		setDraft( ( old ) => ( { ...old, ...change } ) );

	// The catalogue and its value lists, once.
	useEffect( () => {
		let live = true;
		api.getOptions()
			.then( ( result ) => live && setOptions( result ) )
			.catch(
				() =>
					live &&
					setOptions( { catalogue: [], operators: {}, lists: {} } )
			);
		return () => {
			live = false;
		};
	}, [] );

	// The sentence and the estimate follow the draft, a moment behind.
	const timer = useRef();
	useEffect( () => {
		window.clearTimeout( timer.current );
		setWorking( true );
		timer.current = window.setTimeout( () => {
			const payload = {
				type,
				location: draft.location,
				location_args: draft.locationArgs,
				conditions,
				schedule: draft.schedule,
			};
			Promise.all( [
				api.describe( payload ).catch( () => null ),
				api.estimate( conditions ).catch( () => null ),
			] ).then( ( [ described, counted ] ) => {
				if ( described?.targeting ) {
					setParts( described.targeting.parts );
				}
				if ( counted ) {
					setEstimate( counted );
				}
				setWorking( false );
			} );
		}, 350 );
		return () => window.clearTimeout( timer.current );
	}, [
		conditions,
		draft.location,
		draft.locationArgs,
		draft.schedule,
		type,
	] );

	// Titles for whatever was already picked, so the tray can name it.
	useEffect( () => {
		const { posts, children, terms } = draft.content;
		const wanted = [ ...posts, ...children ];
		const missing = wanted.filter( ( id ) => ! labels[ `post:${ id }` ] );
		const missingTerms = terms.filter(
			( id ) => ! labels[ `term:${ id }` ]
		);
		if ( ! missing.length && ! missingTerms.length ) {
			return;
		}
		Promise.all( [
			missing.length
				? api.getContent( { include: missing, per_page: 100 } )
				: { items: [] },
			missingTerms.length
				? api.getTerms( { include: missingTerms } )
				: { items: [] },
		] ).then( ( [ content, termList ] ) => {
			const found = {};
			content.items.forEach( ( item ) => {
				found[ `post:${ item.id }` ] = item.title;
			} );
			termList.items.forEach( ( item ) => {
				found[ `term:${ item.id }` ] = item.name;
			} );
			setLabels( ( old ) => ( { ...old, ...found } ) );
		} );
		// Labels are a cache: refetching when it fills would loop.
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ draft.content.posts, draft.content.children, draft.content.terms ] );

	const learn = useCallback(
		( more ) => setLabels( ( old ) => ( { ...old, ...more } ) ),
		[]
	);

	const go = ( next ) => {
		setStep( next );
		speak(
			sprintf(
				/* translators: 1: step number, 2: step name. */
				__( 'Step %1$d of 5, %2$s', 'scriptdock' ),
				STEPS.indexOf( next ) + 1,
				stepLabels()[ next ]
			)
		);
	};

	const close = () => ( dirty ? setLeaving( true ) : onClose() );

	// Going back to the steps: the rules written by hand move into them when
	// they fit, and keep the builder open when they do not.
	const leaveAdvanced = () => {
		if ( ! draft.custom ) {
			return true;
		}
		const parsed = parseConditions( draft.custom );
		if ( ! parsed ) {
			return false;
		}
		patch( {
			custom: null,
			scope: parsed.scope,
			content: parsed.content,
			audience: parsed.audience,
			time: parsed.time,
		} );
		return true;
	};

	const save = () =>
		onSave( {
			location: draft.location,
			location_args: draft.locationArgs,
			priority: draft.priority,
			conditions,
			schedule: draft.schedule,
		} );

	const shared = {
		draft,
		patch,
		options,
		labels,
		learn,
		locations,
		type,
		canPhp,
		phpReason,
	};

	return (
		<>
			<Modal
				open
				size="full"
				className="sd-wizard"
				title={ title( snippet.title ) }
				onClose={ close }
				headerExtra={
					<div className="sd-wizard__head">
						<div className="sd-wizard__steps">
							<Stepper
								label={ __( 'Targeting steps', 'scriptdock' ) }
								steps={ stepper( draft, step ) }
								onStep={ go }
							/>
						</div>
						<StepperCompact
							index={ index + 1 }
							total={ STEPS.length }
							name={ stepLabels()[ step ] }
						/>
						<SwitchField
							label={ __( 'Advanced rules', 'scriptdock' ) }
							checked={ advanced }
							onChange={ ( on ) =>
								setAdvanced( on ? true : ! leaveAdvanced() )
							}
						/>
					</div>
				}
				footer={
					<div className="sd-wizard__bar">
						{ ! advanced && (
							<Button
								variant="ghost"
								disabled={ index === 0 }
								onClick={ () => go( STEPS[ index - 1 ] ) }
							>
								{ __( 'Back', 'scriptdock' ) }
							</Button>
						) }
						<span className="sd-wizard__count sd-wizard__spacer">
							{ advanced
								? __( 'Advanced rules', 'scriptdock' )
								: sprintf(
										/* translators: 1: current step, 2: how many steps. */
										__( 'Step %1$d of %2$d', 'scriptdock' ),
										index + 1,
										STEPS.length
									) }
						</span>
						{ ! advanced && step !== 'review' && (
							<Button
								variant="ghost"
								onClick={ () => go( 'review' ) }
							>
								{ __( 'Skip to review', 'scriptdock' ) }
							</Button>
						) }
						{ advanced || step === 'review' ? (
							<Button variant="primary" onClick={ save }>
								{ __( 'Save targeting', 'scriptdock' ) }
							</Button>
						) : (
							<Button
								variant="primary"
								onClick={ () => go( STEPS[ index + 1 ] ) }
							>
								{ __( 'Next', 'scriptdock' ) }
							</Button>
						) }
					</div>
				}
			>
				<div
					className={ cx(
						'sd-wizard__body',
						advanced && 'is-advanced'
					) }
				>
					<div className="sd-wizard__main">
						{ advanced ? (
							<AdvancedRules
								{ ...shared }
								conditions={ conditions }
								onChange={ ( custom ) => patch( { custom } ) }
								onLeave={ () => {
									patch( { custom: null } );
									setAdvanced( false );
								} }
							/>
						) : (
							<Step step={ step } shared={ shared } go={ go } />
						) }
					</div>
					<Rail
						parts={ sentenceParts( parts ) }
						groups={ tray( draft, labels, options ) }
						estimate={ estimate }
						warnings={ warnings( draft, snippet ) }
						tip={ advanced ? '' : tips()[ step ] }
						busy={ working }
						onRemove={ ( group, id ) =>
							patch( remove( draft, group, id ) )
						}
						onClear={ () => patch( cleared() ) }
					/>
				</div>
			</Modal>

			<ConfirmDialog
				open={ leaving }
				title={ __(
					'Leave without saving the targeting?',
					'scriptdock'
				) }
				onCancel={ () => setLeaving( false ) }
				actions={
					<>
						<Button
							variant="primary"
							onClick={ () => setLeaving( false ) }
						>
							{ __( 'Keep editing', 'scriptdock' ) }
						</Button>
						<Button variant="danger" onClick={ onClose }>
							{ __( 'Discard changes', 'scriptdock' ) }
						</Button>
					</>
				}
			>
				{ __(
					'The changes you made here are not saved yet.',
					'scriptdock'
				) }
			</ConfirmDialog>
		</>
	);
}

/**
 * The step on show.
 *
 * @param {Object}   props        Props.
 * @param {string}   props.step   Step key.
 * @param {Object}   props.shared Props every step takes.
 * @param {Function} props.go     Moves to another step.
 * @return {Element} The step.
 */
function Step( { step, shared, go } ) {
	switch ( step ) {
		case 'placement':
			return <PlacementStep { ...shared } />;
		case 'content':
			return <ContentStep { ...shared } />;
		case 'audience':
			return <AudienceStep { ...shared } />;
		case 'schedule':
			return <ScheduleStep { ...shared } />;
		default:
			return <ReviewStep { ...shared } onStep={ go } />;
	}
}

/**
 * The wizard's title, with the snippet named.
 *
 * @param {string} name Snippet title.
 * @return {string} Title.
 */
function title( name ) {
	return sprintf(
		/* translators: %s: snippet title. */
		__( 'Where should %s run?', 'scriptdock' ),
		name || __( 'this snippet', 'scriptdock' )
	);
}

/**
 * Step names.
 *
 * @return {Object} Step key => name.
 */
function stepLabels() {
	return {
		placement: __( 'Placement', 'scriptdock' ),
		content: __( 'Pages & content', 'scriptdock' ),
		audience: __( 'Audience', 'scriptdock' ),
		schedule: __( 'Schedule', 'scriptdock' ),
		review: __( 'Review', 'scriptdock' ),
	};
}

/**
 * A line about the step in hand.
 *
 * @return {Object} Step key => tip.
 */
function tips() {
	return {
		placement: __(
			'The footer is the fastest place for tracking scripts — the page is already visible when they load.',
			'scriptdock'
		),
		content: __(
			'Picking a whole post type covers the posts you add later; picking single pages does not.',
			'scriptdock'
		),
		audience: __(
			'Rules about the visitor are checked as the page is built, so they need caching to be off for those pages.',
			'scriptdock'
		),
		schedule: __(
			'Times follow the site timezone, not the visitor’s.',
			'scriptdock'
		),
		review: '',
	};
}

/**
 * The stepper's state, with a count of what each step narrows by.
 *
 * @param {Object} draft   Draft.
 * @param {string} current Current step.
 * @return {Array} Steps.
 */
function stepper( draft, current ) {
	const labels = stepLabels();
	const totals = counts( draft );
	const notes = {
		placement: '',
		content: totals.content
			? sprintf(
					/* translators: %d: how many things are picked. */
					__( '%d picked', 'scriptdock' ),
					totals.content
				)
			: '',
		audience: totals.audience
			? sprintf(
					/* translators: %d: how many rules. */
					_n( '%d rule', '%d rules', totals.audience, 'scriptdock' ),
					totals.audience
				)
			: '',
		schedule: totals.schedule ? __( 'set', 'scriptdock' ) : '',
		review: '',
	};
	const at = STEPS.indexOf( current );

	return STEPS.map( ( key, position ) => ( {
		id: key,
		label: labels[ key ],
		note: notes[ key ],
		state: stepState( position, at ),
	} ) );
}

/**
 * Where a step stands: the one on show, one already passed, or one to come.
 *
 * @param {number} position Step position.
 * @param {number} at       Current position.
 * @return {string} current, complete or todo.
 */
function stepState( position, at ) {
	if ( position === at ) {
		return 'current';
	}
	return position < at ? 'complete' : 'todo';
}

/**
 * What the tray lists, from the draft and the titles fetched so far.
 *
 * @param {Object} draft   Draft.
 * @param {Object} labels  id => title.
 * @param {Object} options Catalogue and lists.
 * @return {Array} Tray groups.
 */
function tray( draft, labels, options ) {
	const { content } = draft;
	const lists = options?.lists || {};
	const name = ( list, value ) =>
		( lists[ list ] || [] ).find( ( entry ) => entry.value === value )
			?.label || value;

	return [
		{
			key: 'posts',
			label: __( 'Pages & posts', 'scriptdock' ),
			items: content.posts.map( ( id ) => ( {
				id,
				label: labels[ `post:${ id }` ] || `#${ id }`,
				note: content.children.includes( id )
					? __( 'with children', 'scriptdock' )
					: '',
			} ) ),
		},
		{
			key: 'types',
			label: __( 'Whole post types', 'scriptdock' ),
			items: content.types.map( ( value ) => ( {
				id: value,
				label: name( 'post_types', value ),
			} ) ),
		},
		{
			key: 'terms',
			label: __( 'Categories & tags', 'scriptdock' ),
			items: content.terms.map( ( id ) => ( {
				id,
				label: labels[ `term:${ id }` ] || `#${ id }`,
			} ) ),
		},
		{
			key: 'special',
			label: __( 'Special pages', 'scriptdock' ),
			items: content.special.map( ( value ) => ( {
				id: value,
				label: name( 'page_type', value ),
			} ) ),
		},
		{
			key: 'urls',
			label: __( 'URL rules', 'scriptdock' ),
			items: content.urls
				.filter( ( row ) => row.value )
				.map( ( row, position ) => ( {
					id: position,
					label: row.value,
					note: options?.operators?.[ row.operator ] || row.operator,
				} ) ),
		},
	];
}

/**
 * Removes one thing from the tray.
 *
 * @param {Object} draft Draft.
 * @param {string} group Tray group key.
 * @param {*}      id    Item id.
 * @return {Object} Draft change.
 */
function remove( draft, group, id ) {
	const content = { ...draft.content };
	if ( group === 'posts' ) {
		content.posts = content.posts.filter( ( value ) => value !== id );
		content.children = content.children.filter( ( value ) => value !== id );
	} else if ( group === 'types' ) {
		content.types = content.types.filter( ( value ) => value !== id );
	} else if ( group === 'terms' ) {
		content.terms = content.terms.filter( ( value ) => value !== id );
	} else if ( group === 'special' ) {
		content.special = content.special.filter( ( value ) => value !== id );
	} else if ( group === 'urls' ) {
		content.urls = content.urls.filter(
			( row, position ) => position !== id
		);
	}
	return { content };
}

/**
 * An empty selection, back to the whole site.
 *
 * @return {Object} Draft change.
 */
function cleared() {
	return {
		scope: 'all',
		content: {
			posts: [],
			children: [],
			types: [],
			terms: [],
			termsApply: 'both',
			special: [],
			urls: [],
		},
	};
}

/**
 * What is worth knowing before saving.
 *
 * @param {Object} draft   Draft.
 * @param {Object} snippet Snippet.
 * @return {Array} Warnings.
 */
function warnings( draft, snippet ) {
	const list = [];
	const { audience } = draft;
	const perVisitor =
		audience.loggedIn ||
		audience.roles.length ||
		audience.users.length ||
		audience.devices.length ||
		audience.browsers.length ||
		audience.os.length ||
		audience.referrer.values.length ||
		audience.cookies.length ||
		audience.params.length;
	if ( perVisitor ) {
		list.push(
			__(
				'Rules about the visitor may not work with full-page caching. Leave these pages out of the cache, or check in JavaScript instead.',
				'scriptdock'
			)
		);
	}
	if ( draft.custom ) {
		list.push(
			__(
				'This snippet has rules written in Advanced rules, so the steps cannot show them.',
				'scriptdock'
			)
		);
	}
	if ( snippet.consent?.category ) {
		list.push(
			sprintf(
				/* translators: %s: consent category, for example "Statistics". */
				__(
					'It also waits for %s consent, which is set on the editor’s loading card.',
					'scriptdock'
				),
				consentLabel( snippet.consent.category )
			)
		);
	}
	return list;
}
