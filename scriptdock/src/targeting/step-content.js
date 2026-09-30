/**
 * Step 2: which pages and posts the snippet runs on.
 *
 * One tab per content type the site has, plus terms, the pages that are not
 * posts at all, and URL rules. Everything picked lands in the rail's tray.
 */
import { useEffect, useRef, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import {
	Button,
	Checkbox,
	Icon,
	IconButton,
	SearchField,
	SegmentedControl,
	Select,
	SkeletonCard,
	Status,
	SwitchField,
	TabPanel,
	Tabs,
	TextField,
	cx,
} from '../components';
import * as api from './api';
import { CardGrid, SelectCard } from './cards';

const SCOPES = [
	{
		value: 'all',
		title: __( 'Entire site', 'scriptdock' ),
		note: __( 'Every page, now and later.', 'scriptdock' ),
	},
	{
		value: 'only',
		title: __( 'Only on selected content', 'scriptdock' ),
		note: __( 'Pick the pages and posts yourself.', 'scriptdock' ),
	},
	{
		value: 'except',
		title: __( 'Everywhere except', 'scriptdock' ),
		note: __( 'All pages but the ones you pick.', 'scriptdock' ),
	},
];

/**
 * @param {Object}   props         Props.
 * @param {Object}   props.draft   Draft.
 * @param {Function} props.patch   Changes the draft.
 * @param {Object}   props.options Catalogue and value lists.
 * @param {Function} props.learn   Remembers titles for the tray.
 * @return {Element} The step.
 */
export default function ContentStep( { draft, patch, options, learn } ) {
	const types = options?.lists?.post_types || [];
	const [ tab, setTab ] = useState( types[ 0 ]?.value || 'page' );
	const { content } = draft;
	const setContent = ( change ) =>
		patch( { content: { ...content, ...change } } );

	const tabs = [
		...types.map( ( type ) => ( {
			id: type.value,
			label: type.plural || type.label,
			count: countFor( content, type.value ),
		} ) ),
		{
			id: 'terms',
			label: __( 'Categories & tags', 'scriptdock' ),
			count: content.terms.length || undefined,
		},
		{
			id: 'special',
			label: __( 'Special pages', 'scriptdock' ),
			count: content.special.length || undefined,
		},
		{
			id: 'urls',
			label: __( 'URL rules', 'scriptdock' ),
			count:
				content.urls.filter( ( row ) => row.value ).length || undefined,
		},
	];

	return (
		<div className="sd-step sd-step--content">
			<CardGrid
				label={ __( 'Where it runs', 'scriptdock' ) }
				className="sd-scopes"
			>
				{ SCOPES.map( ( scope ) => (
					<SelectCard
						key={ scope.value }
						title={ scope.title }
						note={ scope.note }
						art={ <ScopeArt kind={ scope.value } /> }
						selected={ draft.scope === scope.value }
						tabIndex={ draft.scope === scope.value ? 0 : -1 }
						onSelect={ () => patch( { scope: scope.value } ) }
					/>
				) ) }
			</CardGrid>

			{ draft.scope === 'all' ? (
				<p className="sd-step__hint">
					{ __(
						'The snippet runs on every page of the site. Pick one of the other two to narrow it down.',
						'scriptdock'
					) }
				</p>
			) : (
				<>
					<Tabs
						label={ __( 'What to pick from', 'scriptdock' ) }
						tabs={ tabs }
						selected={ tab }
						onSelect={ setTab }
						idPrefix="sd-content-tabs"
					/>
					<TabPanel
						idPrefix="sd-content-tabs"
						id={ tab }
						className="sd-step__panel"
					>
						{ tab === 'terms' && (
							<TermPicker
								content={ content }
								setContent={ setContent }
								options={ options }
								learn={ learn }
							/>
						) }
						{ tab === 'special' && (
							<SpecialPages
								content={ content }
								setContent={ setContent }
								options={ options }
							/>
						) }
						{ tab === 'urls' && (
							<UrlRules
								content={ content }
								setContent={ setContent }
								options={ options }
							/>
						) }
						{ ! [ 'terms', 'special', 'urls' ].includes( tab ) && (
							<ContentPicker
								key={ tab }
								type={ types.find(
									( entry ) => entry.value === tab
								) }
								content={ content }
								setContent={ setContent }
								options={ options }
								learn={ learn }
							/>
						) }
					</TabPanel>
				</>
			) }
		</div>
	);
}

/**
 * How many things are picked in one content type's tab.
 *
 * @param {Object} content Selection.
 * @param {string} type    Post type.
 * @return {number|undefined} The count, or nothing to hide the badge.
 */
function countFor( content, type ) {
	const whole = content.types.includes( type ) ? 1 : 0;
	// Posts are counted per tab once their titles are known; the tray holds
	// the full list either way.
	return whole ? whole : undefined;
}

/**
 * The grid or list of content to pick from, with its search and filters.
 *
 * @param {Object}   props            Props.
 * @param {Object}   props.type       Post type entry.
 * @param {Object}   props.content    Selection.
 * @param {Function} props.setContent Changes the selection.
 * @param {Function} props.learn      Remembers titles for the tray.
 * @return {Element} The picker.
 */
function ContentPicker( { type, content, setContent, learn } ) {
	const [ items, setItems ] = useState( [] );
	const [ total, setTotal ] = useState( 0 );
	const [ pages, setPages ] = useState( 1 );
	const [ page, setPage ] = useState( 1 );
	const [ loading, setLoading ] = useState( true );
	const [ search, setSearch ] = useState( '' );
	const [ status, setStatus ] = useState( '' );
	const [ view, setView ] = useState( 'grid' );
	const [ authors, setAuthors ] = useState( [] );
	const [ author, setAuthor ] = useState( 0 );
	const key = type?.value || 'page';
	const timer = useRef();

	useEffect( () => {
		api.getUsers( {} ).then( ( result ) => setAuthors( result.items ) );
	}, [] );

	useEffect( () => {
		setPage( 1 );
	}, [ search, status, author, key ] );

	useEffect( () => {
		let live = true;
		setLoading( true );
		window.clearTimeout( timer.current );
		timer.current = window.setTimeout(
			() => {
				api.getContent( {
					type: key,
					search,
					status,
					author,
					page,
					per_page: 24,
				} ).then( ( result ) => {
					if ( ! live ) {
						return;
					}
					setItems( ( old ) =>
						page > 1 ? [ ...old, ...result.items ] : result.items
					);
					setTotal( result.total );
					setPages( result.pages );
					setLoading( false );
					const found = {};
					result.items.forEach( ( item ) => {
						found[ `post:${ item.id }` ] = item.title;
					} );
					learn( found );
				} );
			},
			search ? 250 : 0
		);
		return () => {
			live = false;
			window.clearTimeout( timer.current );
		};
	}, [ key, search, status, author, page, learn ] );

	const all = content.types.includes( key );
	const toggle = ( id ) =>
		setContent( {
			posts: content.posts.includes( id )
				? content.posts.filter( ( value ) => value !== id )
				: [ ...content.posts, id ],
			children: content.children.filter( ( value ) => value !== id ),
		} );

	return (
		<div className="sd-picker">
			<div className="sd-picker__all">
				<SwitchField
					checked={ all }
					label={ sprintf(
						/* translators: 1: content type, 2: how many there are. */
						__( 'Select all %1$s (%2$d)', 'scriptdock' ),
						( type?.plural || key ).toLowerCase(),
						total
					) }
					onChange={ ( on ) =>
						setContent( {
							types: on
								? [ ...content.types, key ]
								: content.types.filter(
										( value ) => value !== key
									),
						} )
					}
				/>
				<p className="sd-picker__allnote">
					{ all
						? __(
								'Includes the ones you add later. You can still pick single pages to exclude on the "Everywhere except" scope.',
								'scriptdock'
							)
						: __( 'Includes pages you add later.', 'scriptdock' ) }
				</p>
			</div>

			<div className="sd-picker__tools">
				<SearchField
					label={ __( 'Search', 'scriptdock' ) }
					value={ search }
					onChange={ setSearch }
					placeholder={ __( 'Search by title…', 'scriptdock' ) }
				/>
				<Select
					label={ __( 'Status', 'scriptdock' ) }
					value={ status }
					onChange={ setStatus }
					options={ [
						{ value: '', label: __( 'Any status', 'scriptdock' ) },
						{
							value: 'publish',
							label: __( 'Published', 'scriptdock' ),
						},
						{ value: 'draft', label: __( 'Draft', 'scriptdock' ) },
						{
							value: 'pending',
							label: __( 'Pending', 'scriptdock' ),
						},
						{
							value: 'private',
							label: __( 'Private', 'scriptdock' ),
						},
						{
							value: 'future',
							label: __( 'Scheduled', 'scriptdock' ),
						},
					] }
				/>
				<Select
					label={ __( 'Author', 'scriptdock' ) }
					value={ String( author ) }
					onChange={ ( value ) => setAuthor( Number( value ) ) }
					options={ [
						{ value: '0', label: __( 'Any author', 'scriptdock' ) },
						...authors.map( ( user ) => ( {
							value: String( user.id ),
							label: user.name,
						} ) ),
					] }
				/>
				<SegmentedControl
					label={ __( 'View', 'scriptdock' ) }
					value={ view }
					onChange={ setView }
					options={ [
						{ value: 'grid', label: __( 'Grid', 'scriptdock' ) },
						{ value: 'list', label: __( 'List', 'scriptdock' ) },
					] }
				/>
			</div>

			{ loading && page === 1 ? (
				<div className="sd-picker__grid">
					{ [ 0, 1, 2, 3, 4, 5, 6, 7 ].map( ( n ) => (
						<SkeletonCard key={ n } />
					) ) }
				</div>
			) : (
				<>
					{ items.length === 0 && (
						<p className="sd-picker__empty">
							{ __(
								'Nothing matches that search.',
								'scriptdock'
							) }
						</p>
					) }
					<div
						className={ cx(
							view === 'grid'
								? 'sd-picker__grid'
								: 'sd-picker__list'
						) }
					>
						{ items.map( ( item ) =>
							view === 'grid' ? (
								<ContentCard
									key={ item.id }
									item={ item }
									content={ content }
									setContent={ setContent }
									onToggle={ () => toggle( item.id ) }
								/>
							) : (
								<ContentRow
									key={ item.id }
									item={ item }
									selected={ content.posts.includes(
										item.id
									) }
									onToggle={ () => toggle( item.id ) }
								/>
							)
						) }
					</div>
					{ page < pages && (
						<div className="sd-picker__more">
							<span>
								{ sprintf(
									/* translators: 1: how many are shown, 2: how many there are. */
									__( 'Showing %1$d of %2$d', 'scriptdock' ),
									items.length,
									total
								) }
							</span>
							<Button
								variant="secondary"
								loading={ loading }
								loadingLabel={ __( 'Loading…', 'scriptdock' ) }
								onClick={ () => setPage( page + 1 ) }
							>
								{ __( 'Load more', 'scriptdock' ) }
							</Button>
						</div>
					) }
				</>
			) }
		</div>
	);
}

/**
 * One piece of content as a card.
 *
 * @param {Object}   props            Props.
 * @param {Object}   props.item       Content item.
 * @param {Object}   props.content    Selection.
 * @param {Function} props.setContent Changes the selection.
 * @param {Function} props.onToggle   Picks or unpicks it.
 * @return {Element} The card.
 */
function ContentCard( { item, content, setContent, onToggle } ) {
	const selected = content.posts.includes( item.id );
	const withChildren = content.children.includes( item.id );

	return (
		<div className={ cx( 'sd-content-card', selected && 'is-selected' ) }>
			<button
				type="button"
				className="sd-content-card__main"
				aria-pressed={ selected }
				onClick={ onToggle }
			>
				<span
					className={ cx(
						'sd-content-card__thumb',
						! item.thumbnail && `is-${ item.type }`
					) }
					aria-hidden="true"
					style={
						item.thumbnail
							? { backgroundImage: `url(${ item.thumbnail })` }
							: undefined
					}
				>
					{ ! item.thumbnail && (
						<Icon name="page" size={ 28 } stroke={ 1.4 } />
					) }
				</span>
				<span className="sd-content-card__text">
					{ item.parent && (
						<span className="sd-content-card__parent">
							{ item.parent.title } ›
						</span>
					) }
					<span className="sd-content-card__title">
						{ item.title }
					</span>
					<span className="sd-content-card__path">{ item.path }</span>
					<span className="sd-content-card__meta">
						{ item.status !== 'publish' && (
							<Status tone={ statusTone( item.status ) }>
								{ statusLabel( item.status ) }
							</Status>
						) }
						{ item.date_label }
					</span>
				</span>
				<span className="sd-content-card__check" aria-hidden="true">
					{ selected && (
						<Icon name="check" size={ 12 } stroke={ 3 } />
					) }
				</span>
			</button>
			{ selected && item.children > 0 && (
				<div className="sd-content-card__children">
					<SwitchField
						size="sm"
						checked={ withChildren }
						label={ sprintf(
							/* translators: %d: how many child pages. */
							_n(
								'Include %d child page',
								'Include %d child pages',
								item.children,
								'scriptdock'
							),
							item.children
						) }
						onChange={ ( on ) =>
							setContent( {
								children: on
									? [ ...content.children, item.id ]
									: content.children.filter(
											( value ) => value !== item.id
										),
							} )
						}
					/>
				</div>
			) }
		</div>
	);
}

/**
 * One piece of content as a row, for sites with a lot of pages.
 *
 * @param {Object}   props          Props.
 * @param {Object}   props.item     Content item.
 * @param {boolean}  props.selected Whether it is picked.
 * @param {Function} props.onToggle Picks or unpicks it.
 * @return {Element} The row.
 */
function ContentRow( { item, selected, onToggle } ) {
	return (
		<div className={ cx( 'sd-content-row', selected && 'is-selected' ) }>
			<Checkbox
				className="sd-content-row__title"
				checked={ selected }
				onChange={ onToggle }
				label={ item.title }
			/>
			<span className="sd-content-row__path">{ item.path }</span>
			<span className="sd-content-row__meta">
				{ item.status !== 'publish' && statusLabel( item.status ) }
				{ item.date_label }
			</span>
		</div>
	);
}

/**
 * Categories, tags and any other terms, as a tree.
 *
 * @param {Object}   props            Props.
 * @param {Object}   props.content    Selection.
 * @param {Function} props.setContent Changes the selection.
 * @param {Object}   props.options    Catalogue and lists.
 * @param {Function} props.learn      Remembers names for the tray.
 * @return {Element} The picker.
 */
function TermPicker( { content, setContent, options, learn } ) {
	const taxonomies = options?.lists?.taxonomies || [];
	const [ taxonomy, setTaxonomy ] = useState(
		taxonomies[ 0 ]?.value || 'category'
	);
	const [ terms, setTerms ] = useState( [] );
	const [ search, setSearch ] = useState( '' );
	const [ loading, setLoading ] = useState( true );

	useEffect( () => {
		let live = true;
		setLoading( true );
		api.getTerms( { taxonomy, search } ).then( ( result ) => {
			if ( ! live ) {
				return;
			}
			setTerms( result.items );
			setLoading( false );
			const found = {};
			result.items.forEach( ( term ) => {
				found[ `term:${ term.id }` ] = term.name;
			} );
			learn( found );
		} );
		return () => {
			live = false;
		};
	}, [ taxonomy, search, learn ] );

	const toggle = ( id ) =>
		setContent( {
			terms: content.terms.includes( id )
				? content.terms.filter( ( value ) => value !== id )
				: [ ...content.terms, id ],
		} );

	const tree = ( parent = 0, depth = 0 ) =>
		terms
			.filter(
				( term ) => term.parent === parent || ( search && depth === 0 )
			)
			.map( ( term ) => (
				<li key={ term.id } className="sd-termtree__item">
					<div className="sd-termtree__row">
						<Checkbox
							className="sd-termtree__name"
							checked={ content.terms.includes( term.id ) }
							onChange={ () => toggle( term.id ) }
							label={ term.name }
						/>
						<span className="sd-termtree__count">
							{ term.count }
						</span>
					</div>
					{ ! search && (
						<ul className="sd-termtree__children">
							{ tree( term.id, depth + 1 ) }
						</ul>
					) }
				</li>
			) );

	return (
		<div className="sd-terms">
			<div className="sd-picker__tools">
				<SegmentedControl
					label={ __( 'Taxonomy', 'scriptdock' ) }
					value={ taxonomy }
					onChange={ setTaxonomy }
					options={ taxonomies.map( ( entry ) => ( {
						value: entry.value,
						label: entry.label,
					} ) ) }
				/>
				<SearchField
					label={ __( 'Search terms', 'scriptdock' ) }
					value={ search }
					onChange={ setSearch }
					placeholder={ __( 'Search…', 'scriptdock' ) }
				/>
			</div>

			{ loading ? (
				<p className="sd-picker__empty">
					{ __( 'Loading…', 'scriptdock' ) }
				</p>
			) : (
				<ul className="sd-termtree">{ tree() }</ul>
			) }

			<fieldset className="sd-terms__apply">
				<legend>{ __( 'Apply to', 'scriptdock' ) }</legend>
				<SegmentedControl
					label={ __( 'Apply to', 'scriptdock' ) }
					value={ content.termsApply }
					onChange={ ( value ) =>
						setContent( { termsApply: value } )
					}
					options={ [
						{
							value: 'posts',
							label: __( 'Posts in these terms', 'scriptdock' ),
						},
						{
							value: 'archives',
							label: __( 'Archive pages', 'scriptdock' ),
						},
						{ value: 'both', label: __( 'Both', 'scriptdock' ) },
					] }
				/>
			</fieldset>
		</div>
	);
}

/**
 * The pages that are not posts: the front page, search, 404 and the rest.
 *
 * @param {Object}   props            Props.
 * @param {Object}   props.content    Selection.
 * @param {Function} props.setContent Changes the selection.
 * @param {Object}   props.options    Catalogue and lists.
 * @return {Element} The cards.
 */
function SpecialPages( { content, setContent, options } ) {
	const list = options?.lists?.page_type || [];
	const toggle = ( value ) =>
		setContent( {
			special: content.special.includes( value )
				? content.special.filter( ( entry ) => entry !== value )
				: [ ...content.special, value ],
		} );

	return (
		<CardGrid
			multiple
			label={ __( 'Special pages', 'scriptdock' ) }
			className="sd-specials"
		>
			{ list.map( ( entry, position ) => {
				const selected = content.special.includes( entry.value );
				return (
					<SelectCard
						key={ entry.value }
						multiple
						title={ entry.label }
						selected={ selected }
						tabIndex={ position === 0 || selected ? 0 : -1 }
						onSelect={ () => toggle( entry.value ) }
					/>
				);
			} ) }
		</CardGrid>
	);
}

/**
 * URL rules, with a box that says whether a URL would match.
 *
 * @param {Object}   props            Props.
 * @param {Object}   props.content    Selection.
 * @param {Function} props.setContent Changes the selection.
 * @param {Object}   props.options    Catalogue and lists.
 * @return {Element} The rules.
 */
function UrlRules( { content, setContent, options } ) {
	const [ url, setUrl ] = useState( '' );
	const [ result, setResult ] = useState( null );
	const operators = [
		'contains',
		'starts_with',
		'is',
		'ends_with',
		'wildcard',
		'regex',
	];

	const setRow = ( index, change ) =>
		setContent( {
			urls: content.urls.map( ( row, position ) =>
				position === index ? { ...row, ...change } : row
			),
		} );

	useEffect( () => {
		if ( ! url.trim() ) {
			setResult( null );
			return undefined;
		}
		const timer = window.setTimeout( () => {
			api.testUrl(
				url,
				content.urls
					.filter( ( row ) => row.value.trim() )
					.map( ( row ) => ( {
						operator: row.operator,
						value: [ row.value ],
					} ) )
			).then( setResult );
		}, 300 );
		return () => window.clearTimeout( timer );
	}, [ url, content.urls ] );

	return (
		<div className="sd-urlrules">
			<ul className="sd-urlrules__list">
				{ content.urls.map( ( row, index ) => (
					<li key={ index } className="sd-urlrules__row">
						<Select
							label={ __( 'Match', 'scriptdock' ) }
							hideLabel
							value={ row.operator }
							onChange={ ( value ) =>
								setRow( index, { operator: value } )
							}
							options={ operators.map( ( value ) => ( {
								value,
								label: options?.operators?.[ value ] || value,
							} ) ) }
						/>
						<TextField
							label={ __( 'URL or path', 'scriptdock' ) }
							hideLabel
							value={ row.value }
							placeholder="/shop/"
							onChange={ ( value ) => setRow( index, { value } ) }
						/>
						<IconButton
							icon="trash"
							variant="ghost"
							size="sm"
							label={ __( 'Remove this rule', 'scriptdock' ) }
							onClick={ () =>
								setContent( {
									urls: content.urls.filter(
										( entry, position ) =>
											position !== index
									),
								} )
							}
						/>
					</li>
				) ) }
			</ul>
			<Button
				variant="secondary"
				icon="plus"
				onClick={ () =>
					setContent( {
						urls: [
							...content.urls,
							{ operator: 'contains', value: '' },
						],
					} )
				}
			>
				{ __( 'Add rule', 'scriptdock' ) }
			</Button>

			<div className="sd-urlrules__test">
				<TextField
					label={ __( 'Test a URL', 'scriptdock' ) }
					value={ url }
					placeholder="https://example.com/shop/kettles/"
					onChange={ setUrl }
				/>
				{ result && (
					<p
						className={ cx(
							'sd-urlrules__result',
							result.matches ? 'is-match' : 'is-miss'
						) }
						aria-live="polite"
					>
						<Icon
							name={ result.matches ? 'check' : 'close' }
							size={ 14 }
							stroke={ 2.4 }
						/>
						{ result.matches
							? __( 'Matches', 'scriptdock' )
							: __( 'No match', 'scriptdock' ) }
					</p>
				) }
			</div>
		</div>
	);
}

/**
 * How a status reads.
 *
 * @param {string} status Post status.
 * @return {string} Label.
 */
function statusLabel( status ) {
	return (
		{
			draft: __( 'Draft', 'scriptdock' ),
			pending: __( 'Pending', 'scriptdock' ),
			private: __( 'Private', 'scriptdock' ),
			future: __( 'Scheduled', 'scriptdock' ),
		}[ status ] || status
	);
}

/**
 * The colour a status wears.
 *
 * @param {string} status Post status.
 * @return {string} Tone.
 */
function statusTone( status ) {
	return status === 'future' ? 'info' : 'neutral';
}

/**
 * The little drawing on a scope card.
 *
 * @param {Object} props      Props.
 * @param {string} props.kind all, only or except.
 * @return {Element} The drawing.
 */
function ScopeArt( { kind } ) {
	return (
		<span className={ cx( 'sd-scopeart', `sd-scopeart--${ kind }` ) }>
			<span />
			<span />
			<span />
			<span />
		</span>
	);
}
