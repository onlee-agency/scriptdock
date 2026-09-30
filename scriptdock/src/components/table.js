/**
 * sd-Table, sd-BulkBar and sd-Pagination.
 *
 * A real <table>: sortable headers carry aria-sort, the select-all box goes
 * mixed when some rows are chosen, and a click anywhere on a row runs
 * `onRowClick` unless it lands on a control inside the row. Keyboard users
 * reach each row through its title link.
 */
import { __, _n, sprintf } from '@wordpress/i18n';
import { Checkbox } from './form';
import Icon from './icon';
import IconButton from './icon-button';
import { Skeleton } from './display';
import { cx, INTERACTIVE } from './utils';

/**
 * Classes for a column's cells. Columns that Screen Options can hide carry
 * WordPress's names (manage-column, column-{key}) so its toggles find them.
 *
 * @param {Object} column Column.
 * @return {string|undefined} Classes.
 */
function columnClass( column ) {
	return (
		cx(
			`sd-col-${ column.key.replace( /^__/, '' ) }`,
			column.screenOption && `manage-column column-${ column.key }`,
			column.hidden && 'hidden'
		) || undefined
	);
}

/**
 * A placeholder cell while rows load: a box for the checkbox, a pill for
 * the first column (the type chip), a shimmering line for the title and
 * plain lines after it.
 *
 * @param {Object} column   The column.
 * @param {number} position Its position among the data columns, from 0.
 * @return {Element} The placeholder.
 */
function skeletonCell( column, position ) {
	if ( column.key === '__select' ) {
		return <Skeleton variant="box" />;
	}
	if ( position === 0 ) {
		return <Skeleton variant="pill" shimmer />;
	}
	return <Skeleton shimmer={ position === 1 } />;
}

/**
 * @param {Object}   props              Props.
 * @param {string}   props.caption      Table caption (read by screen readers).
 * @param {Array}    props.columns      { key, label, width, sortable,
 *                                      hideLabel, hidden, screenOption,
 *                                      render( row ) }. Columns that
 *                                      Screen Options can hide set
 *                                      screenOption; hidden ones stay in the
 *                                      page with the hidden class, as its
 *                                      toggles expect.
 * @param {Array}    props.rows         Rows.
 * @param {Function} props.rowKey       row => unique key.
 * @param {Object}   props.selection    { selected: keys[], onChange( keys ),
 *                                      label( row ) } or null.
 * @param {Object}   props.sort         { key, direction, onChange( key,
 *                                      direction ) } or null.
 * @param {string}   props.density      comfortable (64px) or compact (44px).
 * @param {boolean}  props.loading      Show skeleton rows.
 * @param {number}   props.skeletonRows How many.
 * @param {Function} props.onRowClick   row => void.
 * @param {Function} props.rowClassName row => extra classes (is-danger).
 * @param {Element}  props.empty        Shown when there are no rows.
 * @param {Element}  props.footer       Under the table (pagination).
 * @return {Element} The table.
 */
export default function Table( {
	caption,
	columns = [],
	rows = [],
	rowKey = ( row ) => row.id,
	selection = null,
	sort = null,
	density = 'comfortable',
	loading = false,
	skeletonRows = 5,
	onRowClick,
	rowClassName,
	empty,
	footer,
} ) {
	const keys = rows.map( rowKey );
	const selected = selection ? selection.selected : [];
	const all =
		keys.length > 0 && keys.every( ( key ) => selected.includes( key ) );
	const some = ! all && keys.some( ( key ) => selected.includes( key ) );

	const toggleAll = ( on ) =>
		selection.onChange(
			on
				? Array.from( new Set( [ ...selected, ...keys ] ) )
				: selected.filter( ( key ) => ! keys.includes( key ) )
		);
	const toggleRow = ( key, on ) =>
		selection.onChange(
			on
				? [ ...selected, key ]
				: selected.filter( ( item ) => item !== key )
		);

	const onClickRow = ( event, row ) => {
		if ( ! onRowClick ) {
			return;
		}
		const control = event.target.closest( INTERACTIVE );
		if ( control && event.currentTarget.contains( control ) ) {
			return;
		}
		onRowClick( row );
	};

	const allColumns = selection
		? [ { key: '__select', width: '56px', label: '' }, ...columns ]
		: columns;
	const visible = allColumns.filter( ( column ) => ! column.hidden );

	return (
		<div className="sd-table-wrap">
			<table
				className={ cx(
					'sd-table',
					density === 'compact' && 'sd-table--compact'
				) }
			>
				{ caption && (
					<caption className="sd-visually-hidden">
						{ caption }
					</caption>
				) }
				<colgroup>
					{ visible.map( ( column ) => (
						<col
							key={ column.key }
							style={
								column.width
									? { inlineSize: column.width }
									: undefined
							}
						/>
					) ) }
				</colgroup>
				<thead>
					<tr>
						{ allColumns.map( ( column ) => {
							if ( column.key === '__select' ) {
								return (
									<th key={ column.key } scope="col">
										<Checkbox
											size="sm"
											hideLabel
											label={ __(
												'Select all',
												'scriptdock'
											) }
											checked={ all }
											indeterminate={ some }
											onChange={ toggleAll }
										/>
									</th>
								);
							}
							const sorted = sort && sort.key === column.key;
							let ariaSort;
							if ( sorted ) {
								ariaSort =
									sort.direction === 'asc'
										? 'ascending'
										: 'descending';
							}
							return (
								<th
									key={ column.key }
									id={
										column.screenOption
											? column.key
											: undefined
									}
									scope="col"
									aria-sort={ ariaSort }
									className={ columnClass( column ) }
								>
									{ column.sortable && sort ? (
										<button
											type="button"
											className="sd-table__sort"
											onClick={ () =>
												sort.onChange(
													column.key,
													sorted &&
														sort.direction ===
															'desc'
														? 'asc'
														: 'desc'
												)
											}
										>
											{ column.label }
											{ sorted && (
												<Icon
													name={
														sort.direction === 'asc'
															? 'chevron-up'
															: 'chevron-down'
													}
													size={ 11 }
													stroke={ 2.6 }
												/>
											) }
										</button>
									) : (
										<span
											className={
												column.hideLabel
													? 'sd-visually-hidden'
													: undefined
											}
										>
											{ column.label }
										</span>
									) }
								</th>
							);
						} ) }
					</tr>
				</thead>
				<tbody>
					{ loading &&
						Array.from( { length: skeletonRows } ).map(
							( _, index ) => (
								<tr
									key={ `skeleton-${ index }` }
									className="is-skeleton"
									aria-hidden="true"
								>
									{ allColumns.map(
										( column, columnIndex ) => (
											<td
												key={ column.key }
												className={ columnClass(
													column
												) }
											>
												{ skeletonCell(
													column,
													columnIndex -
														( selection ? 1 : 0 )
												) }
											</td>
										)
									) }
								</tr>
							)
						) }
					{ ! loading &&
						rows.map( ( row ) => {
							const key = rowKey( row );
							const isSelected = selected.includes( key );
							return (
								// Keyboard users open a row through its title link.

								<tr
									key={ key }
									className={ cx(
										onRowClick && 'is-clickable',
										isSelected && 'is-selected',
										rowClassName && rowClassName( row )
									) }
									onClick={ ( event ) =>
										onClickRow( event, row )
									}
								>
									{ allColumns.map( ( column ) =>
										column.key === '__select' ? (
											<td key={ column.key }>
												<Checkbox
													size="sm"
													hideLabel
													label={ selection.label(
														row
													) }
													checked={ isSelected }
													onChange={ ( on ) =>
														toggleRow( key, on )
													}
												/>
											</td>
										) : (
											<td
												key={ column.key }
												className={ columnClass(
													column
												) }
											>
												{ column.render
													? column.render( row )
													: row[ column.key ] }
											</td>
										)
									) }
								</tr>
							);
						} ) }
				</tbody>
			</table>
			{ ! loading && ! rows.length && empty }
			{ loading && (
				<span className="sd-visually-hidden" role="status">
					{ __( 'Loading…', 'scriptdock' ) }
				</span>
			) }
			{ footer }
		</div>
	);
}

/**
 * The floating bar of bulk actions while rows are selected.
 *
 * @param {Object}   props          Props.
 * @param {number}   props.count    How many are selected.
 * @param {Function} props.onClear  Clears the selection.
 * @param {Element}  props.children Action buttons (Button variant on-ink).
 * @return {Element} The bar.
 */
export function BulkBar( { count, onClear, children } ) {
	return (
		<div
			className="sd-bulkbar"
			role="region"
			aria-label={ __( 'Bulk actions', 'scriptdock' ) }
		>
			<span className="sd-bulkbar__count" aria-live="polite">
				{ sprintf(
					/* translators: %d: number of selected snippets. */
					_n( '%d selected', '%d selected', count, 'scriptdock' ),
					count
				) }
			</span>
			{ children }
			<IconButton
				icon="close"
				label={ __( 'Clear selection', 'scriptdock' ) }
				variant="on-ink"
				size="compact"
				iconSize={ 14 }
				stroke={ 2.2 }
				onClick={ onClear }
			/>
		</div>
	);
}

/**
 * "1–20 of 24" and previous/next.
 *
 * @param {Object}   props          Props.
 * @param {number}   props.page     Current page, from 1.
 * @param {number}   props.perPage  Items per page.
 * @param {number}   props.total    Total items.
 * @param {Function} props.onChange Called with the new page.
 * @param {Element}  props.children Extra controls (per page select).
 * @return {Element} The pagination.
 */
export function Pagination( { page, perPage, total, onChange, children } ) {
	const first = total ? ( page - 1 ) * perPage + 1 : 0;
	const last = Math.min( total, page * perPage );
	const pages = Math.max( 1, Math.ceil( total / perPage ) );
	return (
		<nav
			className="sd-pagination"
			aria-label={ __( 'Pages', 'scriptdock' ) }
		>
			<span className="sd-pagination__range">
				{ sprintf(
					/* translators: 1: first item shown, 2: last item shown, 3: total items. */
					__( '%1$d–%2$d of %3$d', 'scriptdock' ),
					first,
					last,
					total
				) }
			</span>
			<span className="sd-pagination__pages">
				{ children }
				<IconButton
					icon="chevron-left"
					label={ __( 'Previous page', 'scriptdock' ) }
					size="sm"
					className="sd-flip"
					disabled={ page <= 1 }
					onClick={ () => onChange( page - 1 ) }
				/>
				<IconButton
					icon="chevron-right"
					label={ __( 'Next page', 'scriptdock' ) }
					size="sm"
					className="sd-flip"
					disabled={ page >= pages }
					onClick={ () => onChange( page + 1 ) }
				/>
			</span>
		</nav>
	);
}
