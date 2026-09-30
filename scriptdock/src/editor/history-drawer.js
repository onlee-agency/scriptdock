/**
 * S06: the history drawer. Every saved version on the left, what changed on
 * the right, and a way back to any of them.
 *
 * It replaces WordPress's revisions screen for snippets, so it has to carry
 * the same facts: who saved what, when, and what the difference is.
 */
import { useEffect, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { speak } from '@wordpress/a11y';
import {
	Avatar,
	Banner,
	Button,
	CodePreview,
	ConfirmDialog,
	Diff,
	Drawer,
	Icon,
	SegmentedControl,
	Skeleton,
	copyText,
	cx,
} from '../components';
import * as api from './api';

/**
 * @param {Object}   props           Props.
 * @param {number}   props.id        Snippet ID.
 * @param {string}   props.title     Snippet title.
 * @param {string}   props.type      Code type, for highlighting.
 * @param {boolean}  props.trusted   Whether the code is the version ScriptDock
 *                                   signed.
 * @param {number}   props.start     Revision to open on, from a link.
 * @param {Function} props.onClose   Closes the drawer.
 * @param {Function} props.onRestore Called with the restored snippet.
 * @param {Function} props.onApprove Approves the current code instead.
 * @param {boolean}  props.approving Whether approving is under way.
 * @return {Element} The drawer.
 */
export default function HistoryDrawer( {
	id,
	title,
	type = 'html',
	trusted = true,
	start = 0,
	onClose,
	onRestore,
	onApprove,
	approving = false,
} ) {
	const [ history, setHistory ] = useState( null );
	const [ picked, setPicked ] = useState( start );
	const [ view, setView ] = useState( null );
	const [ mode, setMode ] = useState( 'split' );
	const [ busy, setBusy ] = useState( false );
	const [ confirming, setConfirming ] = useState( false );
	const [ copied, setCopied ] = useState( false );
	const [ error, setError ] = useState( '' );

	useEffect( () => {
		let live = true;
		api.fetchRevisions( id )
			.then( ( result ) => {
				if ( ! live ) {
					return;
				}
				setHistory( result );
				if ( ! picked && result.items.length ) {
					setPicked( result.items[ 0 ].id );
				}
			} )
			.catch(
				( problem ) => live && setError( api.errorMessage( problem ) )
			);
		return () => {
			live = false;
		};
		// Loaded once when the drawer opens.
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ id ] );

	useEffect( () => {
		if ( ! picked ) {
			return undefined;
		}
		let live = true;
		setView( null );
		api.fetchRevision( id, picked )
			.then( ( result ) => live && setView( result ) )
			.catch(
				( problem ) => live && setError( api.errorMessage( problem ) )
			);
		return () => {
			live = false;
		};
	}, [ id, picked ] );

	const entry = history?.items.find( ( item ) => item.id === picked );
	const restore = () => {
		setBusy( true );
		setConfirming( false );
		api.restoreRevision( id, picked )
			.then( ( result ) => {
				setBusy( false );
				onRestore( result );
			} )
			.catch( ( problem ) => {
				setBusy( false );
				setError( api.errorMessage( problem ) );
			} );
	};

	const copy = () => {
		copyText( view?.code || '' )
			.then( () => {
				setCopied( true );
				speak( __( 'Code copied.', 'scriptdock' ) );
				window.setTimeout( () => setCopied( false ), 2000 );
			} )
			.catch( () => {} );
	};

	return (
		<>
			<Drawer
				open
				wide
				title={ __( 'History', 'scriptdock' ) }
				subtitle={ <span dir="auto">{ title }</span> }
				onClose={ onClose }
				headerExtra={
					<SegmentedControl
						label={ __( 'How to compare', 'scriptdock' ) }
						size="sm"
						value={ mode }
						onChange={ setMode }
						options={ [
							{
								value: 'split',
								label: __( 'Split', 'scriptdock' ),
							},
							{
								value: 'inline',
								label: __( 'Inline', 'scriptdock' ),
							},
						] }
					/>
				}
				footer={
					<div className="sd-history__actions">
						{ history && ! history.can_restore && (
							<p className="sd-history__reason">
								<Icon name="lock" size={ 14 } stroke={ 1.8 } />
								{ history.reason }
							</p>
						) }
						{ history?.can_restore && (
							<Button
								variant={ trusted ? 'secondary' : 'primary' }
								loading={ busy }
								loadingLabel={ __(
									'Restoring…',
									'scriptdock'
								) }
								disabled={ ! picked || ! view }
								onClick={ () => setConfirming( true ) }
							>
								{ __( 'Restore this version', 'scriptdock' ) }
							</Button>
						) }
						{ ! trusted && onApprove && (
							<Button
								variant="danger"
								loading={ approving }
								loadingLabel={ __(
									'Approving…',
									'scriptdock'
								) }
								onClick={ onApprove }
							>
								{ __(
									'Approve the current code',
									'scriptdock'
								) }
							</Button>
						) }
						<Button
							variant="tertiary"
							disabled={ ! view }
							onClick={ copy }
						>
							{ copied
								? __( 'Copied', 'scriptdock' )
								: __( 'Copy this code', 'scriptdock' ) }
						</Button>
					</div>
				}
			>
				<div className="sd-history">
					{ error && (
						<Banner tone="danger" inline>
							{ error }
						</Banner>
					) }
					{ ! trusted && (
						<Banner
							tone="danger"
							compact
							title={ __(
								'This code changed outside ScriptDock',
								'scriptdock'
							) }
						>
							{ __(
								'Putting back the last version you saved is the safe way out. Approve the current code only if you made the change yourself.',
								'scriptdock'
							) }
						</Banner>
					) }

					<div className="sd-history__panes">
						<Timeline
							history={ history }
							picked={ picked }
							onPick={ setPicked }
						/>
						<div className="sd-history__diff">
							{ entry && (
								<p className="sd-history__what">
									{ view
										? summary( view.diff )
										: __(
												'Working out what changed…',
												'scriptdock'
											) }
								</p>
							) }
							{ ! view && (
								<div className="sd-history__loading">
									<Skeleton variant="line" width="80%" />
									<Skeleton variant="line" width="60%" />
									<Skeleton variant="line" width="70%" />
								</div>
							) }
							{ view && ! changed( view.diff ) && (
								<CodePreview
									code={ view.code }
									type={ type }
									numbered
									locked={ false }
									title={ __( 'This version', 'scriptdock' ) }
								/>
							) }
							{ view && changed( view.diff ) && (
								<Diff
									lines={ view.diff.lines }
									mode={ mode }
									before={ __(
										'This version',
										'scriptdock'
									) }
									after={ __( 'Code now', 'scriptdock' ) }
									label={ __(
										'What changed between this version and the code now',
										'scriptdock'
									) }
								/>
							) }
						</div>
					</div>
				</div>
			</Drawer>

			<ConfirmDialog
				open={ confirming }
				title={ __( 'Restore this version?', 'scriptdock' ) }
				onCancel={ () => setConfirming( false ) }
				actions={
					<>
						<Button variant="primary" onClick={ restore }>
							{ __( 'Restore', 'scriptdock' ) }
						</Button>
						<Button
							variant="tertiary"
							onClick={ () => setConfirming( false ) }
						>
							{ __( 'Keep the current code', 'scriptdock' ) }
						</Button>
					</>
				}
			>
				{ sprintf(
					/* translators: %s: when the version was saved, for example "2 days ago". */
					__(
						'The code from %s replaces the code the snippet has now. The current code stays in the history, so this can be undone.',
						'scriptdock'
					),
					entry ? entry.label : ''
				) }
			</ConfirmDialog>
		</>
	);
}

/**
 * Whether a version differs from the code the snippet has now.
 *
 * @param {Object} diff Diff from the REST layer.
 * @return {boolean} True when something changed.
 */
function changed( diff ) {
	return !! ( diff.added || diff.removed );
}

/**
 * Every version, newest first, with the code as it stands at the top.
 *
 * @param {Object}   props         Props.
 * @param {Object}   props.history What the REST layer answered.
 * @param {number}   props.picked  The version on show.
 * @param {Function} props.onPick  Picks another.
 * @return {Element} The timeline.
 */
function Timeline( { history, picked, onPick } ) {
	if ( ! history ) {
		return (
			<div className="sd-history__timeline">
				<Skeleton variant="line" width="70%" />
				<Skeleton variant="line" width="80%" />
				<Skeleton variant="line" width="60%" />
			</div>
		);
	}

	return (
		<ol
			className="sd-history__timeline"
			aria-label={ __( 'Saved versions', 'scriptdock' ) }
		>
			<li>
				<div className="sd-history__now">
					<Entry item={ history.current } />
					<span className="sd-history__tags">
						<span className="sd-history__tag is-current">
							{ __( 'Current', 'scriptdock' ) }
						</span>
					</span>
				</div>
			</li>
			{ history.items.map( ( item ) => (
				<li key={ item.id }>
					<button
						type="button"
						className={ cx(
							'sd-history__item',
							item.id === picked && 'is-picked'
						) }
						aria-current={ item.id === picked ? 'true' : undefined }
						onClick={ () => onPick( item.id ) }
					>
						<Entry item={ item } />
						{ ( item.approved || item.same ) && (
							<span className="sd-history__tags">
								{ item.approved && (
									<span className="sd-history__tag">
										{ __(
											'Approved after review',
											'scriptdock'
										) }
									</span>
								) }
								{ item.same && (
									<span className="sd-history__tag">
										{ __( 'Same as now', 'scriptdock' ) }
									</span>
								) }
							</span>
						) }
					</button>
				</li>
			) ) }
			{ ! history.items.length && (
				<li className="sd-history__empty">
					{ __(
						'No earlier versions yet. Saving the snippet keeps one.',
						'scriptdock'
					) }
				</li>
			) }
		</ol>
	);
}

/**
 * Who saved a version and when.
 *
 * @param {Object} props      Props.
 * @param {Object} props.item Version.
 * @return {Element} The line.
 */
function Entry( { item } ) {
	return (
		<span className="sd-history__entry">
			{ /* The name is right beside it, so the picture adds nothing. */ }
			<span aria-hidden="true">
				<Avatar name={ item.author } size="sm" />
			</span>
			<span className="sd-history__who">
				<span className="sd-history__author">{ item.author }</span>
				<span className="sd-history__when" title={ item.stamp }>
					{ item.label }
					{ ' · ' }
					{ sprintf(
						/* translators: %d: number of lines of code. */
						_n( '%d line', '%d lines', item.lines, 'scriptdock' ),
						item.lines
					) }
				</span>
			</span>
		</span>
	);
}

/**
 * "2 lines added, 1 removed" for the version on show.
 *
 * @param {Object} diff Diff from the REST layer.
 * @return {string} The summary.
 */
function summary( diff ) {
	if ( ! diff.added && ! diff.removed ) {
		return __(
			'Nothing changed between this version and the code now.',
			'scriptdock'
		);
	}
	const added = sprintf(
		/* translators: %d: number of lines. */
		_n( '%d line added', '%d lines added', diff.added, 'scriptdock' ),
		diff.added
	);
	const removed = sprintf(
		/* translators: %d: number of lines. */
		_n( '%d line removed', '%d lines removed', diff.removed, 'scriptdock' ),
		diff.removed
	);
	if ( diff.added && diff.removed ) {
		return sprintf(
			/* translators: 1: for example "2 lines added", 2: for example "1 line removed". */
			__( 'Since this version: %1$s and %2$s.', 'scriptdock' ),
			added,
			removed
		);
	}
	return sprintf(
		/* translators: %s: for example "2 lines added". */
		__( 'Since this version: %s.', 'scriptdock' ),
		diff.added ? added : removed
	);
}
