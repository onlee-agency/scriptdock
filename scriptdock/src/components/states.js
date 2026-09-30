/**
 * S21: what a screen shows when things are not normal.
 *
 * Three of them, and the rule behind all three is the same: say what
 * happened, say whether the site is affected, and give the one thing worth
 * doing next. A screen failing to load is our problem, not the reader's, and
 * their snippets are still running — so the copy says that rather than
 * apologising in the abstract.
 */
import { Component } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import Button, { CopyChip } from './button';
import Illustration from './illustrations';
import { Banner } from './feedback';
import { EmptyState, Skeleton } from './display';

/**
 * The screen would not load. Shown by ErrorBoundary, and usable on its own
 * when a first fetch fails.
 *
 * @param {Object} props         Props.
 * @param {string} props.details What went wrong, for the copy button.
 * @param {string} props.title   Overrides the heading.
 * @param {string} props.text    Overrides the explanation.
 * @return {Element} The state.
 */
export function LoadFailed( { details, title, text } ) {
	return (
		<EmptyState
			level={ 2 }
			illustration={ <Illustration name="load-failed" width={ 160 } /> }
			title={ title || __( 'This screen would not load', 'scriptdock' ) }
			text={
				text ||
				__(
					'Something went wrong at our end, not yours. Your snippets are unaffected and still running.',
					'scriptdock'
				)
			}
			actions={
				<>
					<Button
						variant="primary"
						onClick={ () => window.location.reload() }
					>
						{ __( 'Reload', 'scriptdock' ) }
					</Button>
					{ details && (
						<CopyChip
							value={ details }
							label={ __( 'Copy error details', 'scriptdock' ) }
						/>
					) }
				</>
			}
		/>
	);
}

/**
 * ScriptDock is for administrators, and this reader is not one.
 *
 * @param {Object} props       Props.
 * @param {string} props.admin Who to ask, when we know a name.
 * @param {string} props.back  Where "Back to Dashboard" goes.
 * @return {Element} The state.
 */
export function NoAccess( { admin, back } ) {
	return (
		<EmptyState
			level={ 2 }
			illustration={ <Illustration name="no-access" width={ 160 } /> }
			title={ __( 'You do not have access to this', 'scriptdock' ) }
			text={
				admin
					? sprintf(
							/* translators: %s: the name of someone who can help. */
							__(
								'ScriptDock is limited to administrators on this site. Ask %s to give you access, or to make the change for you.',
								'scriptdock'
							),
							admin
						)
					: __(
							'ScriptDock is limited to administrators on this site. Ask whoever looks after it to give you access, or to make the change for you.',
							'scriptdock'
						)
			}
			actions={
				<Button href={ back || 'index.php' }>
					{ __( 'Back to Dashboard', 'scriptdock' ) }
				</Button>
			}
		/>
	);
}

/**
 * A save that did not land. Worth its own banner rather than a toast,
 * because the reader needs to know their work is still here.
 *
 * @param {Object}   props         Props.
 * @param {boolean}  props.offline The browser says there is no connection.
 * @param {string}   props.message What the server said, when it said
 *                                 anything.
 * @param {Function} props.onRetry Tries again.
 * @param {boolean}  props.busy    A retry is running.
 * @return {Element} The banner.
 */
export function SaveFailed( { offline, message, onRetry, busy = false } ) {
	return (
		<Banner
			tone="danger"
			icon={ offline ? 'offline' : 'alert' }
			actions={
				onRetry && (
					<Button
						size="compact"
						loading={ busy }
						loadingLabel={ __( 'Retrying…', 'scriptdock' ) }
						onClick={ onRetry }
					>
						{ __( 'Retry now', 'scriptdock' ) }
					</Button>
				)
			}
		>
			<strong>
				{ offline
					? __(
							'Could not save — you appear to be offline.',
							'scriptdock'
						)
					: __( 'Could not save.', 'scriptdock' ) }
			</strong>{ ' ' }
			{ message ||
				__(
					'Your changes are kept in this tab. Try again in a moment.',
					'scriptdock'
				) }
		</Banner>
	);
}

/**
 * One loading row in a list.
 *
 * @param {Object}  props        Props.
 * @param {boolean} props.select Leave room for the checkbox.
 * @return {Element} The row.
 */
export function SkeletonRow( { select = true } ) {
	return (
		<div className="sd-skeleton-row" aria-hidden="true">
			{ select && <Skeleton variant="box" /> }
			<Skeleton variant="pill" shimmer />
			<Skeleton variant="line" width="100%" shimmer />
			<Skeleton variant="line" width="80px" />
		</div>
	);
}

/**
 * A loading code editor: the gutter and a few lines.
 *
 * @param {Object} props       Props.
 * @param {string} props.label What is loading, for screen readers.
 * @param {number} props.lines How many lines to draw.
 * @return {Element} The editor.
 */
export function SkeletonEditor( {
	label = __( 'Loading the editor…', 'scriptdock' ),
	lines = 4,
} ) {
	const widths = [ '80%', '62%', '71%', '40%', '55%', '66%' ];
	return (
		<div className="sd-skeleton-editor" role="status">
			<span className="sd-visually-hidden">{ label }</span>
			<div className="sd-skeleton-editor__gutter" aria-hidden="true" />
			<div className="sd-skeleton-editor__code" aria-hidden="true">
				{ Array.from( { length: lines } ).map( ( ignore, index ) => (
					<Skeleton
						key={ index }
						variant="line"
						width={ widths[ index % widths.length ] }
						shimmer={ index === 0 }
					/>
				) ) }
			</div>
		</div>
	);
}

/**
 * Catches a crash in a screen so the whole admin page does not go blank.
 *
 * React gives no hook for this, so it stays a class.
 */
export class ErrorBoundary extends Component {
	/**
	 * @param {Object} props Props: children, and an optional `label` naming
	 *                       the screen for the console.
	 */
	constructor( props ) {
		super( props );
		this.state = { error: null };
	}

	/**
	 * Switches to the failed state.
	 *
	 * @param {Error} error The error.
	 * @return {Object} New state.
	 */
	static getDerivedStateFromError( error ) {
		return { error };
	}

	/**
	 * Puts the stack in the console, where a developer would look for it.
	 *
	 * @param {Error}  error The error.
	 * @param {Object} info  React's component stack.
	 */
	componentDidCatch( error, info ) {
		// eslint-disable-next-line no-console
		console.error( this.props.label || 'ScriptDock', error, info );
	}

	/**
	 * @return {Element} The children, or the failed state.
	 */
	render() {
		const { error } = this.state;
		if ( ! error ) {
			return this.props.children;
		}
		const details = [
			this.props.label || 'ScriptDock',
			`${ error.name }: ${ error.message }`,
			error.stack || '',
			window.location.href,
		]
			.filter( Boolean )
			.join( '\n' );
		return <LoadFailed details={ details } />;
	}
}
