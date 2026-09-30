/**
 * S01: the Overview. What is running, what needs attention, and the quickest
 * way to the next thing.
 *
 * The screen arrives with its data inlined, so it draws at once; switching a
 * snippet here talks to the same routes the list does.
 */
import { useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import {
	Banner,
	Button,
	EmptyState,
	Icon,
	Illustration,
	ProgressRing,
	StatTile,
	Status,
	Switch,
	TemplateCard,
	TypeChip,
	cx,
	useToast,
} from '../components';
import { act, errorMessage } from '../snippets/api';

/**
 * @param {Object} props      Props.
 * @param {Object} props.boot The screen's data.
 * @return {Element} The screen.
 */
export default function App( { boot } ) {
	const toast = useToast();
	const [ data, setData ] = useState( boot );
	const [ busy, setBusy ] = useState( {} );
	const {
		greeting,
		health,
		stats,
		recent,
		problems,
		checklist,
		tip,
		library,
		urls,
	} = data;
	const fresh = ! recent.length;

	const flip = ( item, on ) => {
		setBusy( ( old ) => ( { ...old, [ item.id ]: true } ) );
		act( item.id, on ? 'activate' : 'deactivate' )
			.then( ( result ) => {
				setData( ( old ) => ( {
					...old,
					recent: old.recent.map( ( row ) =>
						row.id === item.id ? result.item : row
					),
				} ) );
				toast.show( {
					message: on
						? sprintf(
								/* translators: %s: snippet title. */
								__( '“%s” is running.', 'scriptdock' ),
								item.title
							)
						: sprintf(
								/* translators: %s: snippet title. */
								__( '“%s” is switched off.', 'scriptdock' ),
								item.title
							),
				} );
			} )
			.catch( ( problem ) =>
				toast.show( {
					tone: 'error',
					message: errorMessage( problem ),
				} )
			)
			.finally( () =>
				setBusy( ( old ) => ( { ...old, [ item.id ]: false } ) )
			);
	};

	return (
		<div className="sd-overview">
			<section className="sd-hero">
				<div className="sd-hero__text">
					<p className="sd-hero__greeting">{ greeting.text }</p>
					<h1 className="sd-hero__title">
						{ __( 'Your site’s code,', 'scriptdock' ) }{ ' ' }
						<span className="sd-hero__accent">
							{ __( 'in control', 'scriptdock' ) }
						</span>
						.
					</h1>
					<p
						className={ cx(
							'sd-hero__health',
							! health.ok && 'is-attention'
						) }
					>
						{ ! health.ok && (
							<Icon name="alert" size={ 15 } stroke={ 2 } />
						) }
						{ health.text }
					</p>
				</div>
				<span className="sd-hero__glow" aria-hidden="true" />
			</section>

			{ health.cards.length > 0 && (
				<section
					className="sd-overview__health"
					aria-label={ __( 'Needs attention', 'scriptdock' ) }
				>
					{ health.cards.map( ( card ) => (
						<Banner
							key={ card.id }
							tone={ card.tone }
							title={ card.title }
							className="sd-banner--side-actions"
							actions={
								<>
									<Button
										variant={
											card.tone === 'danger'
												? 'danger-solid'
												: 'primary'
										}
										size="compact"
										href={ card.action.url }
									>
										{ card.action.label }
									</Button>
									{ card.dismiss && (
										<Button
											variant="ghost"
											size="compact"
											href={ card.dismiss }
										>
											{ __( 'Dismiss', 'scriptdock' ) }
										</Button>
									) }
								</>
							}
						>
							{ card.body }
						</Banner>
					) ) }
				</section>
			) }

			<section
				className="sd-overview__stats"
				aria-label={ __( 'Snippets at a glance', 'scriptdock' ) }
			>
				{ stats.map( ( stat ) => (
					<StatTile
						key={ stat.key }
						label={ stat.label }
						value={ stat.count }
						caption={ stat.note }
						running={ stat.key === 'running' && stat.count > 0 }
						tone={
							stat.key === 'errors' && stat.count > 0
								? 'danger'
								: 'default'
						}
						href={ stat.url }
					/>
				) ) }
			</section>

			{ fresh ? (
				<section className="sd-card sd-overview__welcome">
					<EmptyState
						illustration={ <Illustration name="welcome" /> }
						title={ __( 'Nothing here yet', 'scriptdock' ) }
						text={ __(
							'Add your first snippet, or start from one of the ready-made ones. Nothing is downloaded: the library ships with the plugin.',
							'scriptdock'
						) }
						actions={
							<>
								<Button variant="primary" href={ urls.new }>
									{ __( 'New snippet', 'scriptdock' ) }
								</Button>
								<Button href={ urls.library }>
									{ __( 'Browse library', 'scriptdock' ) }
								</Button>
							</>
						}
					/>
				</section>
			) : (
				<div className="sd-overview__columns">
					<div className="sd-overview__main">
						<Card
							title={ __( 'Recently edited', 'scriptdock' ) }
							link={ {
								label: __( 'All snippets', 'scriptdock' ),
								url: urls.list,
							} }
						>
							<ul className="sd-recent">
								{ recent.map( ( item ) => (
									<RecentRow
										key={ item.id }
										item={ item }
										busy={ !! busy[ item.id ] }
										onFlip={ flip }
									/>
								) ) }
							</ul>
						</Card>

						{ problems.length > 0 && (
							<Card
								title={ __( 'Recent problems', 'scriptdock' ) }
							>
								<ul className="sd-problems">
									{ problems.map( ( item ) => (
										<ProblemRow
											key={ item.id }
											item={ item }
										/>
									) ) }
								</ul>
							</Card>
						) }
					</div>

					<div className="sd-overview__side">
						<Card title={ __( 'Quick actions', 'scriptdock' ) }>
							<div className="sd-quick">
								<Button
									variant="primary"
									href={ urls.new }
									dot="plus"
								>
									{ __( 'New snippet', 'scriptdock' ) }
								</Button>
								<Button href={ urls.library }>
									{ __( 'Browse library', 'scriptdock' ) }
								</Button>
								<Button href={ urls.global }>
									{ __( 'Header & Footer', 'scriptdock' ) }
								</Button>
								<Button href={ urls.import }>
									{ __( 'Import', 'scriptdock' ) }
								</Button>
							</div>
						</Card>

						{ checklist.done < checklist.total && (
							<Card
								title={ __( 'Getting started', 'scriptdock' ) }
							>
								<div className="sd-checklist">
									<ProgressRing
										value={ checklist.done }
										max={ checklist.total }
										label={ sprintf(
											/* translators: 1: steps done, 2: steps in all. */
											__(
												'%1$d of %2$d done',
												'scriptdock'
											),
											checklist.done,
											checklist.total
										) }
									/>
									<div className="sd-checklist__body">
										<p className="sd-checklist__left">
											{ left( checklist ) }
										</p>
										<ul className="sd-checklist__items">
											{ checklist.items.map( ( item ) => (
												<li
													key={ item.key }
													className={ cx(
														'sd-checklist__item',
														item.done && 'is-done'
													) }
												>
													<span
														className="sd-checklist__mark"
														aria-hidden="true"
													>
														{ item.done && (
															<Icon
																name="check"
																size={ 11 }
																stroke={ 3 }
															/>
														) }
													</span>
													{ item.done ? (
														<span>
															{ item.label }
															<span className="sd-visually-hidden">
																{ __(
																	'(done)',
																	'scriptdock'
																) }
															</span>
														</span>
													) : (
														<a href={ item.url }>
															{ item.label }
														</a>
													) }
												</li>
											) ) }
										</ul>
									</div>
								</div>
							</Card>
						) }

						<Card title={ __( 'Speed tip', 'scriptdock' ) }>
							<p className="sd-tip__text">{ tip.text }</p>
							<Button
								variant="tertiary"
								size="compact"
								href={ tip.action.url }
							>
								{ tip.action.label }
							</Button>
						</Card>
					</div>
				</div>
			) }

			<section className="sd-overview__library">
				<header className="sd-overview__libhead">
					<div>
						<h2 className="sd-card__title">
							{ __( 'From the library', 'scriptdock' ) }
						</h2>
						<p className="sd-overview__libnote">
							{ sprintf(
								/* translators: %d: number of templates. */
								_n(
									'%d ready-made snippet ships with the plugin. Nothing is downloaded.',
									'%d ready-made snippets ship with the plugin. Nothing is downloaded.',
									library.total,
									'scriptdock'
								),
								library.total
							) }
						</p>
					</div>
					<Button size="compact" href={ library.url }>
						{ __( 'Browse all', 'scriptdock' ) }
					</Button>
				</header>
				<div className="sd-overview__libcards">
					{ library.cards.map( ( card ) => (
						<TemplateCard
							key={ card.id }
							title={ card.title }
							description={ card.description }
							state={ card.added ? 'added' : 'default' }
							openHref={ card.url }
						>
							<Button
								variant={
									card.added ? 'tertiary' : 'secondary'
								}
								size="compact"
								href={ card.url }
								disabled={ card.added }
							>
								{ card.added
									? __( 'Added', 'scriptdock' )
									: __( 'Add', 'scriptdock' ) }
								<span className="sd-visually-hidden">
									{ ' ' + card.title }
								</span>
							</Button>
						</TemplateCard>
					) ) }
				</div>
			</section>
		</div>
	);
}

/**
 * A card with a title and an optional link in its corner.
 *
 * @param {Object}  props          Props.
 * @param {string}  props.title    Card title.
 * @param {Object}  props.link     { label, url }.
 * @param {Element} props.children Contents.
 * @return {Element} The card.
 */
function Card( { title, link, children } ) {
	return (
		<section className="sd-card sd-overview__card">
			<header className="sd-overview__cardhead">
				<h2 className="sd-card__title">{ title }</h2>
				{ link && (
					<a className="sd-overview__cardlink" href={ link.url }>
						{ link.label }
					</a>
				) }
			</header>
			{ children }
		</section>
	);
}

/**
 * One recently edited snippet: what it is, where it runs, and its switch.
 *
 * @param {Object}   props        Props.
 * @param {Object}   props.item   Snippet.
 * @param {boolean}  props.busy   Whether its switch is waiting.
 * @param {Function} props.onFlip Switches it on or off.
 * @return {Element} The row.
 */
function RecentRow( { item, busy, onFlip } ) {
	// Without notes, the line under the title carries the targeting the
	// "where" line has no room for, rather than repeating its first part.
	const note = item.notes || item.targeting.summary.slice( 1 ).join( ' · ' );
	return (
		<li className="sd-recent__row">
			<TypeChip type={ item.type } />
			<span className="sd-recent__text">
				<a
					className="sd-recent__title"
					href={ item.edit_url }
					dir="auto"
				>
					{ item.title }
				</a>
				{ ( item.paused || note ) && (
					<span className="sd-recent__note">
						{ item.paused ? (
							<Status tone="danger">
								{ __( 'Needs review', 'scriptdock' ) }
							</Status>
						) : null }
						<span className="sd-recent__clip" dir="auto">
							{ note }
						</span>
					</span>
				) }
				<span className="sd-recent__where">
					<span className="sd-recent__clip">
						{ [ item.placement.short, item.targeting.summary[ 0 ] ]
							.filter( Boolean )
							.join( ' · ' ) }
					</span>
				</span>
			</span>
			<span className="sd-recent__when">{ item.modified.human }</span>
			<Switch
				checked={ item.active }
				busy={ busy }
				locked={ ! item.can_activate && ! item.active }
				paused={ item.paused }
				label={ sprintf(
					/* translators: %s: snippet title. */
					__( 'Switch “%s” on or off', 'scriptdock' ),
					item.title
				) }
				onChange={ ( on ) => onFlip( item, on ) }
			/>
		</li>
	);
}

/**
 * One snippet that needs attention, with the way to deal with it.
 *
 * @param {Object} props      Props.
 * @param {Object} props.item Snippet.
 * @return {Element} The row.
 */
function ProblemRow( { item } ) {
	const error = !! item.error;
	return (
		<li className={ cx( 'sd-problems__row', error && 'is-error' ) }>
			<span className="sd-problems__mark" aria-hidden="true">
				<Icon
					name={ error ? 'alert' : 'shield-alert' }
					size={ 15 }
					stroke={ 2 }
				/>
			</span>
			<span className="sd-problems__text">
				<span className="sd-problems__title" dir="auto">
					{ item.title }
				</span>
				<span className="sd-problems__note">
					{ error
						? failure( item.error )
						: sprintf(
								/* translators: %s: how long ago, for example "1 hour ago". */
								__(
									'Code changed outside ScriptDock · %s',
									'scriptdock'
								),
								item.modified.human
							) }
				</span>
			</span>
			<Button size="compact" href={ item.edit_url }>
				{ error
					? __( 'Fix', 'scriptdock' )
					: __( 'Review', 'scriptdock' ) }
				<span className="sd-visually-hidden"> { item.title }</span>
			</Button>
		</li>
	);
}

/**
 * "Fatal error on line 12 · switched off 25 mins ago".
 *
 * @param {Object} error Error from the API.
 * @return {string} The line.
 */
function failure( error ) {
	const what = error.line
		? sprintf(
				/* translators: %d: line number. */
				__( 'Fatal error on line %d', 'scriptdock' ),
				error.line
			)
		: __( 'Fatal error', 'scriptdock' );
	return sprintf(
		/* translators: 1: what went wrong, 2: how long ago. */
		__( '%1$s · switched off %2$s', 'scriptdock' ),
		what,
		error.human
	);
}

/**
 * "One step left." under the progress ring.
 *
 * @param {Object} checklist Checklist.
 * @return {string} The line.
 */
function left( checklist ) {
	const remaining = checklist.total - checklist.done;
	return sprintf(
		/* translators: %d: how many steps are left. */
		_n( '%d step left.', '%d steps left.', remaining, 'scriptdock' ),
		remaining
	);
}
