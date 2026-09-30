/**
 * The screen's smaller parts: the header, the status pills, the needs-review
 * banner, and the empty and no-results states.
 */
import { __, sprintf } from '@wordpress/i18n';
import {
	Banner,
	Button,
	EmptyState,
	Illustration,
	Icon,
	Pills,
} from '../components';
import { queryUrl } from './query';

/**
 * Title with the snippet count, the line under it, Import and New snippet.
 *
 * @param {Object}  props       Props.
 * @param {number}  props.count All snippets outside the trash.
 * @param {Object}  props.urls  Screen URLs.
 * @param {boolean} props.small Narrow screen: no subtitle or Import.
 * @return {Element} The header.
 */
export function Header( { count, urls, small } ) {
	return (
		<div className="sd-snippets__header">
			<div className="sd-snippets__heading">
				<div className="sd-snippets__title-row">
					<h1 className="sd-snippets__title">
						{ __( 'Snippets', 'scriptdock' ) }
					</h1>
					<span className="sd-count-chip">
						{ count }
						<span className="sd-visually-hidden">
							{ ' ' + __( 'snippets', 'scriptdock' ) }
						</span>
					</span>
				</div>
				{ ! small && (
					<p className="sd-snippets__subtitle">
						{ __(
							'Every piece of code on your site, in one place.',
							'scriptdock'
						) }
					</p>
				) }
			</div>
			<div className="sd-snippets__actions">
				{ ! small && (
					<Button href={ urls.import }>
						{ __( 'Import', 'scriptdock' ) }
					</Button>
				) }
				<Button variant="primary" dot="plus" href={ urls.new }>
					{ __( 'New snippet', 'scriptdock' ) }
				</Button>
			</div>
		</div>
	);
}

/**
 * The status views with their counts. Needs review and Errors only appear
 * while there is something in them, or while they are open.
 *
 * @param {Object}   props          Props.
 * @param {Object}   props.counts   Counts per view.
 * @param {Object}   props.query    Query.
 * @param {string}   props.base     Screen URL.
 * @param {boolean}  props.short    Short labels (phones).
 * @param {Function} props.onSelect Called with a view.
 * @return {Element} The pills.
 */
export function StatusPills( { counts, query, base, short, onSelect } ) {
	const views = [
		{ id: 'all', label: __( 'All', 'scriptdock' ) },
		{ id: 'active', label: __( 'Active', 'scriptdock' ) },
		{ id: 'inactive', label: __( 'Inactive', 'scriptdock' ) },
		{
			id: 'review',
			label: short
				? __( 'Review', 'scriptdock' )
				: __( 'Needs review', 'scriptdock' ),
			tone: 'danger',
			optional: true,
		},
		{
			id: 'error',
			label: __( 'Errors', 'scriptdock' ),
			tone: 'error',
			optional: true,
		},
		{ id: 'trash', label: __( 'Trash', 'scriptdock' ) },
	];
	const items = views
		.filter(
			( view ) =>
				! view.optional ||
				counts[ view.id ] > 0 ||
				query.view === view.id
		)
		.map( ( view ) => ( {
			id: view.id,
			label: view.label,
			count: counts[ view.id ],
			tone: view.tone,
			href: queryUrl( base, { ...query, view: view.id, page: 1 } ),
		} ) );
	return (
		<Pills
			label={ __( 'Snippet status', 'scriptdock' ) }
			items={ items }
			current={ query.view }
			onSelect={ onSelect }
		/>
	);
}

/**
 * The banner over the needs-review view.
 *
 * @param {Object}   props            Props.
 * @param {number}   props.count      Snippets to review.
 * @param {boolean}  props.canApprove Whether the user may approve them all.
 * @param {Function} props.onApprove  Approves them all.
 * @return {Element} The banner.
 */
export function ReviewBanner( { count, canApprove, onApprove } ) {
	return (
		<Banner
			tone="danger"
			icon="shield-alert"
			className="sd-banner--side-actions"
			title={ __(
				'These snippets changed outside ScriptDock',
				'scriptdock'
			) }
			actions={
				count > 0 &&
				canApprove && (
					<Button variant="danger" size="sm" onClick={ onApprove }>
						{ __( 'Approve all', 'scriptdock' ) }
					</Button>
				)
			}
		>
			{ __(
				'Possibly by malware or a migration. They are paused until you review them. Compare the changes before approving.',
				'scriptdock'
			) }
		</Banner>
	);
}

/**
 * No snippets at all yet.
 *
 * @param {Object} props      Props.
 * @param {Object} props.urls Screen URLs.
 * @return {Element} The empty state.
 */
export function EmptyList( { urls } ) {
	return (
		<EmptyState
			level={ 2 }
			illustration={
				<Illustration name="empty-snippets" width={ 180 } />
			}
			title={ __( 'Add code to your site in seconds', 'scriptdock' ) }
			text={ __(
				'Tracking pixels, custom CSS, PHP hooks. ScriptDock tests every snippet before it goes live, and switches it off if it breaks anything.',
				'scriptdock'
			) }
			actions={
				<>
					<Button variant="primary" dot="plus" href={ urls.new }>
						{ __( 'New snippet', 'scriptdock' ) }
					</Button>
					<Button href={ urls.library }>
						{ __( 'Browse the library', 'scriptdock' ) }
					</Button>
					<Button variant="tertiary" href={ urls.import }>
						{ __( 'Import from another plugin', 'scriptdock' ) }
					</Button>
				</>
			}
		/>
	);
}

/**
 * Nothing matches the search or filters.
 *
 * @param {Object}   props         Props.
 * @param {string}   props.search  The search.
 * @param {string}   props.hint    Which filter to clear first.
 * @param {Function} props.onClear Clears the search and filters.
 * @return {Element} The card.
 */
export function NoResults( { search, hint, onClear } ) {
	return (
		<div className="sd-snippets__state">
			<Icon name="search" size={ 40 } stroke={ 1.2 } />
			<h2 className="sd-snippets__state-title">
				{ search
					? sprintf(
							/* translators: %s: the search. */
							__( 'No snippets match “%s”', 'scriptdock' ),
							search
						)
					: __( 'No snippets match these filters', 'scriptdock' ) }
			</h2>
			<p className="sd-snippets__state-text">{ hint }</p>
			<Button size="sm" onClick={ onClear }>
				{ __( 'Clear filters', 'scriptdock' ) }
			</Button>
		</div>
	);
}

/**
 * A view with nothing in it (no errors, nothing to review).
 *
 * @param {Object} props      Props.
 * @param {string} props.view The view.
 * @return {Element} The card.
 */
export function EmptyView( { view } ) {
	const text = {
		active: __( 'No snippets are switched on.', 'scriptdock' ),
		inactive: __( 'Every snippet is switched on.', 'scriptdock' ),
		review: __(
			'Nothing to review. Every snippet matches the code ScriptDock saved.',
			'scriptdock'
		),
		error: __(
			'No errors recorded. Every snippet ran cleanly.',
			'scriptdock'
		),
	}[ view ];
	return (
		<div className="sd-snippets__state">
			<Icon name="check" size={ 32 } stroke={ 1.6 } />
			<p className="sd-snippets__state-text">{ text }</p>
		</div>
	);
}

/**
 * The list could not load.
 *
 * @param {Object}   props         Props.
 * @param {string}   props.message Error message.
 * @param {Function} props.onRetry Tries again.
 * @return {Element} The banner.
 */
export function LoadError( { message, onRetry } ) {
	return (
		<Banner
			tone="danger"
			title={ __( 'The snippets could not load', 'scriptdock' ) }
			actions={
				<Button size="sm" onClick={ onRetry }>
					{ __( 'Try again', 'scriptdock' ) }
				</Button>
			}
		>
			{ message }
		</Banner>
	);
}
