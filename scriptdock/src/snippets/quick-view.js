/**
 * The quick view (S03b): a 560px drawer with the snippet's code, where it
 * runs, whether its code is signed and unchanged, its history, and the
 * everyday actions. For a snippet changed outside ScriptDock it also shows
 * what changed, with Approve.
 */
import { useEffect, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import {
	Avatar,
	Banner,
	Button,
	CodePreview,
	Diff,
	Drawer,
	Icon,
	Skeleton,
	Status,
	TypeChip,
	cx,
} from '../components';
import { errorMessage, fetchChanges, fetchSnippet } from './api';
import { status } from './messages';

const EXTENSIONS = {
	php: 'php',
	universal: 'php',
	html: 'html',
	css: 'css',
	js: 'js',
};

const PLACEMENT_ICONS = {
	site_header: 'head',
	site_footer: 'footer',
	before_paragraph: 'paragraph',
	after_paragraph: 'paragraph',
	custom_hook: 'hook',
	shortcode: 'shortcode',
	on_demand: 'run',
};

/**
 * A file name for the code card: the title as a slug, and the extension.
 *
 * @param {Object} item Snippet.
 * @return {string} File name.
 */
function fileName( item ) {
	const slug =
		item.title
			.toLowerCase()
			.normalize( 'NFKD' )
			.replace( /[\u0300-\u036f]/g, '' )
			.replace( /[^a-z0-9]+/g, '-' )
			.replace( /^-+|-+$/g, '' )
			.slice( 0, 40 ) || 'snippet';
	return `${ slug }.${ EXTENSIONS[ item.type ] || 'txt' }`;
}

/**
 * @param {Object}   props             Props.
 * @param {Object}   props.item        The row the drawer opened from.
 * @param {boolean}  props.showChanges Open on the comparison.
 * @param {Function} props.onClose     Closes the drawer.
 * @param {Function} props.onApprove   Approves the change.
 * @param {Function} props.onDuplicate Duplicates it.
 * @param {Function} props.onExport    Exports it.
 * @param {Function} props.onTrash     Moves it to the trash.
 * @return {Element} The drawer.
 */
export default function QuickView( {
	item,
	showChanges,
	onClose,
	onApprove,
	onDuplicate,
	onExport,
	onTrash,
} ) {
	const [ full, setFull ] = useState( null );
	const [ changes, setChanges ] = useState( null );
	const [ error, setError ] = useState( '' );

	// Load the code and history; a snippet to review also loads its changes.
	useEffect( () => {
		let current = true;
		setFull( null );
		setChanges( null );
		setError( '' );
		fetchSnippet( item.id )
			.then( ( data ) => current && setFull( data ) )
			.catch(
				( failure ) => current && setError( errorMessage( failure ) )
			);
		if ( ! item.trusted ) {
			fetchChanges( item.id )
				.then( ( data ) => current && setChanges( data ) )
				.catch( () => {} );
		}
		return () => {
			current = false;
		};
	}, [ item.id, item.trusted ] );

	const shown = full || item;
	const state = status( shown );

	return (
		<Drawer
			open
			onClose={ onClose }
			title={ <span dir="auto">{ shown.title }</span> }
			closeLabel={ __( 'Close quick view', 'scriptdock' ) }
			subtitle={
				<span className="sd-quick__meta">
					<TypeChip type={ shown.type } size="sm" />
					<Status state={ statusTone( state.state ) }>
						{ state.label }
					</Status>
					<span className="sd-quick__priority">
						{ sprintf(
							/* translators: %d: priority number. */
							__( 'Priority %d', 'scriptdock' ),
							shown.priority
						) }
					</span>
				</span>
			}
			footer={
				<>
					<Button
						variant="primary"
						dot="edit"
						href={ shown.edit_url }
					>
						{ __( 'Open editor', 'scriptdock' ) }
					</Button>
					{ shown.status !== 'trash' && (
						<>
							<Button
								onClick={ () => onDuplicate( shown ) }
								disabled={ ! shown.can_edit }
							>
								{ __( 'Duplicate', 'scriptdock' ) }
							</Button>
							<Button onClick={ () => onExport( shown ) }>
								{ __( 'Export', 'scriptdock' ) }
							</Button>
							<Button
								variant="tertiary"
								className="sd-button--danger-text sd-quick__trash"
								onClick={ () => onTrash( shown ) }
							>
								{ __( 'Trash', 'scriptdock' ) }
							</Button>
						</>
					) }
				</>
			}
		>
			<div className="sd-quick">
				{ error && (
					<Banner
						tone="danger"
						inline
						title={ __( 'The code could not load', 'scriptdock' ) }
					>
						{ error }
					</Banner>
				) }
				{ ! shown.trusted && (
					<ChangesPanel
						item={ shown }
						changes={ changes }
						open={ showChanges }
						onApprove={ () => onApprove( shown ) }
					/>
				) }
				{ full ? (
					<CodePreview
						code={ full.code }
						title={ fileName( full ) }
						lockedLabel={ __( 'Read-only', 'scriptdock' ) }
					/>
				) : (
					! error && (
						<div className="sd-quick__loading" aria-hidden="true">
							<Skeleton width="60%" shimmer />
							<Skeleton width="90%" shimmer />
							<Skeleton width="75%" shimmer />
						</div>
					)
				) }
				<WhereItRuns item={ shown } full={ full } />
				<Signature item={ shown } />
				{ full && <History item={ full } /> }
			</div>
		</Drawer>
	);
}

/**
 * Status tone for the status component.
 *
 * @param {string} state State from status().
 * @return {string} running, inactive or error.
 */
function statusTone( state ) {
	if ( state === 'running' ) {
		return 'running';
	}
	return state === 'error' || state === 'paused' ? 'error' : 'inactive';
}

/**
 * Where it runs: placement, pages, visitors and time, and consent.
 *
 * @param {Object}      props      Props.
 * @param {Object}      props.item Snippet row.
 * @param {Object|null} props.full Full snippet, once loaded.
 * @return {Element} The list.
 */
function WhereItRuns( { item, full } ) {
	const facts = Object.fromEntries(
		item.targeting.facts.map( ( fact ) => [ fact.key, fact ] )
	);
	const hook = item.placement.action || item.placement.hook;
	const rows = [
		{
			icon: PLACEMENT_ICONS[ item.placement.key ] || 'window',
			// The short name ("Site header"): the hook shows underneath.
			title:
				item.placement.key === 'custom_hook'
					? item.placement.label
					: item.placement.short,
			detail: hook,
			code: !! hook,
		},
	];
	if ( item.targeting.custom ) {
		rows.push( {
			icon: 'filter',
			title: __( 'Custom rules', 'scriptdock' ),
			detail: item.targeting.text,
		} );
	} else if ( facts.content || facts.audience ) {
		const plain =
			( ! facts.content || facts.content.default ) &&
			( ! facts.audience || facts.audience.default );
		rows.push( {
			icon: 'page',
			title: facts.content ? facts.content.value : facts.audience.value,
			detail: plain
				? __( 'No page or audience conditions', 'scriptdock' )
				: item.targeting.text,
		} );
	}
	if ( facts.time && ! facts.time.default ) {
		rows.push( { icon: 'clock', title: facts.time.value, detail: '' } );
	}
	const consent = item.badges.find( ( badge ) => badge.key === 'consent' );
	if ( consent ) {
		rows.push( {
			icon: 'consent',
			title: sprintf(
				/* translators: %s: consent category, for example "Statistics". */
				__( 'Waits for %s consent', 'scriptdock' ),
				consent.label
			),
			detail:
				full && full.consent && full.consent.api
					? __(
							'Your consent plugin decides when it loads.',
							'scriptdock'
						)
					: __(
							'No consent plugin found, so it loads right away.',
							'scriptdock'
						),
		} );
	}
	return (
		<section className="sd-quick__section" aria-labelledby="sd-quick-where">
			<h3 id="sd-quick-where" className="sd-quick__label">
				{ __( 'Where it runs', 'scriptdock' ) }
			</h3>
			<ul className="sd-quick__facts">
				{ rows.map( ( row, index ) => (
					<li key={ index } className="sd-quick__fact">
						<Icon name={ row.icon } size={ 18 } stroke={ 1.5 } />
						<span className="sd-quick__fact-text">
							<span className="sd-quick__fact-title">
								{ row.title }
							</span>
							{ row.detail && (
								<span
									className={ cx(
										'sd-quick__fact-detail',
										row.code && 'is-code'
									) }
									dir={ row.code ? 'ltr' : undefined }
								>
									{ row.detail }
								</span>
							) }
						</span>
					</li>
				) ) }
			</ul>
		</section>
	);
}

/**
 * Whether the code is signed and unchanged, and any recorded error.
 *
 * @param {Object} props      Props.
 * @param {Object} props.item Snippet row.
 * @return {Element} The line.
 */
function Signature( { item } ) {
	if ( ! item.trusted ) {
		return null;
	}
	if ( item.error ) {
		return (
			<div className="sd-quick__signature is-error">
				<Icon name="alert" size={ 18 } stroke={ 2 } />
				<span>
					{ item.error.line
						? sprintf(
								/* translators: 1: line number, 2: error message, 3: when. */
								__(
									'Error on line %1$d: %2$s (%3$s)',
									'scriptdock'
								),
								item.error.line,
								item.error.message,
								item.error.label
							)
						: sprintf(
								/* translators: 1: error message, 2: when. */
								__( 'Error: %1$s (%2$s)', 'scriptdock' ),
								item.error.message,
								item.error.label
							) }
				</span>
			</div>
		);
	}
	return (
		<div className="sd-quick__signature">
			<Icon name="shield-check" size={ 18 } stroke={ 2 } />
			<span>
				{ __(
					'Signed and unchanged · no errors recorded',
					'scriptdock'
				) }
			</span>
		</div>
	);
}

/**
 * Revisions and who saved last, with a link to the history.
 *
 * @param {Object} props      Props.
 * @param {Object} props.item Full snippet.
 * @return {Element} The line.
 */
function History( { item } ) {
	const latest = item.revisions.latest[ 0 ];
	const count = item.revisions.count;
	return (
		<div className="sd-quick__history">
			{ item.modified.author && (
				<Avatar name={ item.modified.author } size="sm" />
			) }
			<span className="sd-quick__history-text">
				{ count
					? sprintf(
							/* translators: 1: number of revisions, 2: time since, 3: person's name. */
							_n(
								'%1$d revision · last saved %2$s by %3$s',
								'%1$d revisions · last saved %2$s by %3$s',
								count,
								'scriptdock'
							),
							count,
							item.modified.human,
							item.modified.author
						)
					: sprintf(
							/* translators: 1: time since, 2: person's name. */
							__( 'Last saved %1$s by %2$s', 'scriptdock' ),
							item.modified.human,
							item.modified.author
						) }
			</span>
			{ latest && (
				<a className="sd-quick__history-link" href={ latest.url }>
					{ __( 'History', 'scriptdock' ) }
				</a>
			) }
		</div>
	);
}

/**
 * What changed since ScriptDock last signed the code, with Approve.
 *
 * @param {Object}      props           Props.
 * @param {Object}      props.item      Snippet.
 * @param {Object|null} props.changes   Changes, once loaded.
 * @param {boolean}     props.open      Show the lines straight away.
 * @param {Function}    props.onApprove Approves the change.
 * @return {Element} The panel.
 */
function ChangesPanel( { item, changes, open, onApprove } ) {
	const [ expanded, setExpanded ] = useState( open );
	return (
		<section
			className="sd-quick__changes"
			aria-labelledby="sd-quick-changes"
		>
			<div className="sd-quick__changes-head">
				<Icon name="shield-alert" size={ 18 } stroke={ 1.8 } />
				<div className="sd-quick__changes-text">
					<h3
						id="sd-quick-changes"
						className="sd-quick__changes-title"
					>
						{ item.paused
							? __(
									'Changed outside ScriptDock · paused',
									'scriptdock'
								)
							: __( 'Changed outside ScriptDock', 'scriptdock' ) }
					</h3>
					<span className="sd-quick__changes-summary">
						{ changes
							? changes.text
							: __( 'Comparing…', 'scriptdock' ) }
					</span>
				</div>
			</div>
			{ changes && changes.base && (
				<p className="sd-quick__changes-base">
					{ changes.base.trusted
						? sprintf(
								/* translators: 1: time since, 2: person's name. */
								__(
									'Compared with the version ScriptDock saved %1$s by %2$s.',
									'scriptdock'
								),
								changes.base.human,
								changes.base.author
							)
						: sprintf(
								/* translators: 1: time since, 2: person's name. */
								__(
									'Compared with the newest revision, saved %1$s by %2$s. No revision matches the signed code.',
									'scriptdock'
								),
								changes.base.human,
								changes.base.author
							) }
				</p>
			) }
			{ changes && changes.lines && changes.lines.length > 0 && (
				<>
					<Button
						size="sm"
						variant="tertiary"
						aria-expanded={ expanded }
						onClick={ () => setExpanded( ! expanded ) }
					>
						{ expanded
							? __( 'Hide the changed lines', 'scriptdock' )
							: __( 'Show the changed lines', 'scriptdock' ) }
					</Button>
					{ expanded && <Diff lines={ changes.lines } /> }
				</>
			) }
			<div className="sd-quick__changes-actions">
				{ item.can_edit ? (
					<Button variant="danger" size="sm" onClick={ onApprove }>
						{ __( 'Approve this code', 'scriptdock' ) }
					</Button>
				) : (
					<span className="sd-quick__changes-locked">
						<Icon name="lock" size={ 13 } stroke={ 2 } />
						{ __(
							'Only an administrator who can edit PHP can approve it.',
							'scriptdock'
						) }
					</span>
				) }
			</div>
		</section>
	);
}
