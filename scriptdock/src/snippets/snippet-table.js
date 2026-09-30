/**
 * The snippet table: status, snippet, type, where it runs, badges,
 * priority, updated and the row menu, as the design lays them out.
 */
import { __, _n, sprintf } from '@wordpress/i18n';
import {
	Badge,
	Button,
	DropdownMenu,
	Icon,
	Switch,
	Table,
	TYPE_LABELS,
	Tooltip,
	TypeChip,
	cx,
} from '../components';
import { badgeProps, subline, switchLabel, whereLines } from './messages';

/**
 * Column widths: fixed columns in pixels, the rest shared 2.2 : 1.5 : 1.1
 * like the design's grid.
 */
const FIXED = {
	status: 62,
	type: 116,
	priority: 68,
	updated: 92,
	menu: 44,
};
const FLEX = { snippet: 2.2, where: 1.5, badges: 1.1 };
const SELECT = 56;

/**
 * Width for each visible column.
 *
 * @param {string[]} keys Visible column keys.
 * @return {Object} Key => CSS width.
 */
function widths( keys ) {
	const fixed = keys.reduce(
		( sum, key ) => sum + ( FIXED[ key ] || 0 ),
		SELECT
	);
	const share = keys.reduce( ( sum, key ) => sum + ( FLEX[ key ] || 0 ), 0 );
	const out = {};
	keys.forEach( ( key ) => {
		if ( FIXED[ key ] ) {
			out[ key ] = `${ FIXED[ key ] }px`;
		} else if ( FLEX[ key ] ) {
			out[ key ] = `calc((100% - ${ fixed }px) * ${ (
				FLEX[ key ] / share
			).toFixed( 4 ) })`;
		}
	} );
	return out;
}

/**
 * @param {Object}   props             Props.
 * @param {Array}    props.items       Snippet rows.
 * @param {boolean}  props.loading     Show skeleton rows.
 * @param {number}   props.skeletons   How many skeleton rows.
 * @param {string}   props.density     comfortable or compact.
 * @param {string[]} props.hidden      Columns hidden in Screen Options.
 * @param {string[]} props.folded      Columns folded into the snippet cell
 *                                     on narrow screens.
 * @param {Object}   props.selection   Table selection.
 * @param {Object}   props.query       List query (for sorting).
 * @param {Function} props.onSort      Called with orderby and order.
 * @param {Object}   props.busy        Snippet IDs whose switch is saving.
 * @param {Function} props.onToggle    Called with a row and the new state.
 * @param {Function} props.onBlocked   Called with a row whose switch is
 *                                     paused or locked.
 * @param {Function} props.onQuickView Called with a row.
 * @param {Function} props.menuItems   Row => row menu items.
 * @param {boolean}  props.reviewing   The needs-review view is open.
 * @return {Element} The table.
 */
export default function SnippetTable( {
	items,
	loading,
	skeletons,
	density,
	hidden,
	folded,
	selection,
	query,
	onSort,
	busy,
	onToggle,
	onBlocked,
	onQuickView,
	menuItems,
	reviewing,
} ) {
	const hides = ( key ) => hidden.includes( key ) || folded.includes( key );
	const keys = [
		'status',
		'snippet',
		'type',
		'where',
		'badges',
		'priority',
		'updated',
		'menu',
	].filter( ( key ) => ! hides( key ) );
	const width = widths( keys );

	const columns = [
		{
			key: 'status',
			label: __( 'Status', 'scriptdock' ),
			render: ( item ) => (
				<Switch
					checked={ item.active }
					label={ switchLabel( item ) }
					busy={ !! busy[ item.id ] }
					paused={ item.paused }
					locked={
						! item.paused && ! item.active && ! item.can_activate
					}
					onChange={ ( on ) => onToggle( item, on ) }
					onBlockedClick={ () => onBlocked( item ) }
				/>
			),
		},
		{
			key: 'snippet',
			label: __( 'Snippet', 'scriptdock' ),
			sortable: true,
			render: ( item ) => (
				<SnippetCell
					item={ item }
					busy={ !! busy[ item.id ] }
					folded={ folded }
					detailed={ reviewing }
				/>
			),
		},
		{
			key: 'type',
			label: __( 'Type', 'scriptdock' ),
			sortable: true,
			screenOption: true,
			render: ( item ) => (
				<TypeButton item={ item } onQuickView={ onQuickView } />
			),
		},
		{
			key: 'where',
			label: __( 'Where it runs', 'scriptdock' ),
			screenOption: true,
			render: ( item ) => <WhereCell item={ item } />,
		},
		{
			key: 'badges',
			label: __( 'Badges', 'scriptdock' ),
			screenOption: true,
			render: ( item ) => (
				<BadgesCell
					item={ item }
					reviewing={ reviewing }
					onCompare={ () => onBlocked( item ) }
				/>
			),
		},
		{
			key: 'priority',
			label: __( 'Priority', 'scriptdock' ),
			sortable: true,
			screenOption: true,
			render: ( item ) => (
				<span className="sd-snippets__priority">{ item.priority }</span>
			),
		},
		{
			key: 'updated',
			label: __( 'Updated', 'scriptdock' ),
			sortable: true,
			screenOption: true,
			render: ( item ) => <UpdatedCell item={ item } />,
		},
		{
			key: 'menu',
			label: __( 'Actions', 'scriptdock' ),
			hideLabel: true,
			render: ( item ) => (
				<DropdownMenu
					label={ sprintf(
						/* translators: %s: snippet title. */
						__( 'Actions for %s', 'scriptdock' ),
						item.title
					) }
					align="end"
					items={ menuItems( item ) }
				/>
			),
		},
	].map( ( column ) => ( {
		...column,
		hidden: hides( column.key ),
		width: width[ column.key ],
	} ) );

	const sortKey = query.orderby === 'modified' ? 'updated' : query.orderby;
	const defaultOrder = [ 'modified', 'created' ].includes( query.orderby )
		? 'desc'
		: 'asc';

	return (
		<Table
			caption={ __( 'Snippets', 'scriptdock' ) }
			columns={ columns }
			rows={ items }
			selection={ selection }
			sort={ {
				key: sortKey === 'title' ? 'snippet' : sortKey,
				direction: query.order || defaultOrder,
				onChange: ( key, direction ) => {
					const orderby =
						{ updated: 'modified', snippet: 'title' }[ key ] || key;
					onSort( orderby, direction );
				},
			} }
			density={ density }
			loading={ loading }
			skeletonRows={ skeletons }
			onRowClick={ ( item ) => window.location.assign( item.edit_url ) }
			rowClassName={ ( item ) =>
				cx(
					item.error && ! item.active && 'is-danger',
					item.paused && 'is-paused'
				)
			}
		/>
	);
}

/**
 * Title, then the most useful line about the snippet. On narrow screens the
 * badges fold into that line as words, and the tags make room for them.
 *
 * @param {Object}   props          Props.
 * @param {Object}   props.item     Snippet row.
 * @param {boolean}  props.busy     Switch saving.
 * @param {string[]} props.folded   Folded columns.
 * @param {boolean}  props.detailed Say what changed (needs-review view).
 * @return {Element} The cell.
 */
function SnippetCell( { item, busy, folded, detailed } ) {
	const line = subline( item, busy, detailed );
	const folds = folded.includes( 'badges' );
	// An error or a change already leads the line; its badge would repeat it.
	const extra =
		folds && ! [ 'error', 'review' ].includes( line.kind )
			? item.badges.map( ( badge ) => badge.label ).join( ' · ' )
			: '';
	const showTags = line.kind === 'notes' && item.tags.length > 0 && ! folds;
	return (
		<div className="sd-table__title">
			<a className="sd-table__primary" href={ item.edit_url } dir="auto">
				{ item.title }
			</a>
			<span className={ cx( 'sd-snippets__sub', `is-${ line.kind }` ) }>
				{ line.kind === 'error' && (
					<Icon name="alert" size={ 13 } stroke={ 2 } />
				) }
				{ line.kind === 'locked' && (
					<Icon name="lock" size={ 12 } stroke={ 2.2 } />
				) }
				{ line.text && (
					<span
						className="sd-snippets__sub-text"
						dir={ line.kind === 'shortcode' ? 'ltr' : undefined }
					>
						{ line.text }
					</span>
				) }
				{ extra && (
					<span className="sd-snippets__sub-extra">
						{ line.text ? `· ${ extra }` : extra }
					</span>
				) }
				{ showTags && <Tags tags={ item.tags } /> }
			</span>
		</div>
	);
}

/**
 * Up to two tag chips, then a count.
 *
 * @param {Object} props      Props.
 * @param {Array}  props.tags Tags.
 * @return {Element} The chips.
 */
function Tags( { tags } ) {
	const shown = tags.slice( 0, 2 );
	const more = tags.length - shown.length;
	return (
		<>
			{ shown.map( ( tag ) => (
				<span key={ tag.id } className="sd-snippets__tag">
					{ tag.name }
				</span>
			) ) }
			{ more > 0 && (
				<span className="sd-snippets__tag">
					{ sprintf(
						/* translators: %d: number of other tags. */
						__( '+%d', 'scriptdock' ),
						more
					) }
				</span>
			) }
		</>
	);
}

/**
 * The type chip as a button that opens the quick view.
 *
 * @param {Object}   props             Props.
 * @param {Object}   props.item        Snippet row.
 * @param {Function} props.onQuickView Opens the quick view.
 * @return {Element} The button.
 */
function TypeButton( { item, onQuickView } ) {
	return (
		<button
			type="button"
			className="sd-snippets__type"
			onClick={ () => onQuickView( item ) }
			aria-label={ sprintf(
				/* translators: 1: code type, 2: snippet title. */
				__( '%1$s. Quick view of %2$s', 'scriptdock' ),
				TYPE_LABELS[ item.type ],
				item.title
			) }
		>
			<TypeChip type={ item.type } size="row" />
		</button>
	);
}

/**
 * Placement, then where it applies.
 *
 * @param {Object} props      Props.
 * @param {Object} props.item Snippet row.
 * @return {Element} The cell.
 */
function WhereCell( { item } ) {
	const [ place, reach ] = whereLines( item );
	return (
		<div className="sd-snippets__where">
			<span className="sd-snippets__place">{ place }</span>
			<span className="sd-snippets__reach">{ reach }</span>
		</div>
	);
}

/**
 * The most important badge, with the rest in a count.
 *
 * @param {Object} props        Props.
 * @param {Array}  props.badges Badges.
 * @return {Element} The badge.
 */
function FirstBadge( { badges } ) {
	const [ first, ...rest ] = badges;
	const props = badgeProps( first );
	return (
		<span className="sd-snippets__badges">
			<Badge
				size="sm"
				tone={ props.tone }
				icon={ props.icon }
				title={ first.detail }
			>
				{ first.label }
			</Badge>
			{ rest.length > 0 && (
				<Tooltip
					text={ rest.map( ( badge ) => badge.label ).join( ' · ' ) }
				>
					<span className="sd-snippets__more-badges">
						{ sprintf(
							/* translators: %d: number of other badges. */
							__( '+%d', 'scriptdock' ),
							rest.length
						) }
						<span className="sd-visually-hidden">
							{ ': ' +
								rest
									.map( ( badge ) => badge.label )
									.join( ', ' ) }
						</span>
					</span>
				</Tooltip>
			) }
		</span>
	);
}

/**
 * Badges, and in the review view a Compare button.
 *
 * @param {Object}   props           Props.
 * @param {Object}   props.item      Snippet row.
 * @param {boolean}  props.reviewing Review view.
 * @param {Function} props.onCompare Opens the comparison.
 * @return {Element} The cell.
 */
function BadgesCell( { item, reviewing, onCompare } ) {
	if ( ! item.badges.length ) {
		return <span className="sd-snippets__none">—</span>;
	}
	return (
		<span className="sd-snippets__badge-cell">
			<FirstBadge badges={ item.badges } />
			{ reviewing && ! item.trusted && (
				<Button variant="primary" size="sm" onClick={ onCompare }>
					{ __( 'Compare', 'scriptdock' ) }
				</Button>
			) }
		</span>
	);
}

/**
 * When it changed; the date and who changed it on hover and for screen
 * readers.
 *
 * @param {Object} props      Props.
 * @param {Object} props.item Snippet row.
 * @return {Element} The cell.
 */
function UpdatedCell( { item } ) {
	const detail = item.modified.author
		? sprintf(
				/* translators: 1: date and time, 2: person's name. */
				__( '%1$s by %2$s', 'scriptdock' ),
				item.modified.label,
				item.modified.author
			)
		: item.modified.label;
	return (
		<Tooltip text={ detail }>
			<span className="sd-snippets__updated">
				<time dateTime={ item.modified.gmt }>
					{ item.modified.human }
				</time>
				<span className="sd-visually-hidden">{ `, ${ detail }` }</span>
			</span>
		</Tooltip>
	);
}

/**
 * How many snippets a count of rows is, for the table's status line.
 *
 * @param {number} count Count.
 * @return {string} Text.
 */
export function snippetCount( count ) {
	return sprintf(
		/* translators: %d: number of snippets. */
		_n( '%d snippet', '%d snippets', count, 'scriptdock' ),
		count
	);
}
