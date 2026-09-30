/**
 * The trash: each snippet with Restore and Delete permanently, and Empty
 * trash for all of them.
 */
import { __, _n, sprintf } from '@wordpress/i18n';
import { Button, Skeleton, TypeChip } from '../components';

/**
 * @param {Object}   props           Props.
 * @param {Array}    props.items     Trashed snippets.
 * @param {number}   props.total     All trashed snippets.
 * @param {boolean}  props.loading   Loading.
 * @param {number}   props.days      Days before WordPress deletes them.
 * @param {Object}   props.busy      Snippet IDs being changed.
 * @param {Function} props.onRestore Takes one out of the trash.
 * @param {Function} props.onDelete  Deletes one for good (asks first).
 * @param {Function} props.onEmpty   Empties the trash (asks first).
 * @return {Element} The trash.
 */
export default function TrashView( {
	items,
	total,
	loading,
	days,
	busy,
	onRestore,
	onDelete,
	onEmpty,
} ) {
	return (
		<section className="sd-trash" aria-labelledby="sd-trash-title">
			<div className="sd-trash__header">
				<h2 id="sd-trash-title" className="sd-trash__title">
					{ sprintf(
						/* translators: %d: number of snippets in the trash. */
						_n(
							'Trash · %d snippet',
							'Trash · %d snippets',
							total,
							'scriptdock'
						),
						total
					) }
				</h2>
				{ days > 0 && (
					<span className="sd-trash__note">
						{ sprintf(
							/* translators: %d: number of days. */
							_n(
								'Deleted snippets are removed after %d day.',
								'Deleted snippets are removed after %d days.',
								days,
								'scriptdock'
							),
							days
						) }
					</span>
				) }
				{ total > 0 && (
					<Button size="sm" variant="danger" onClick={ onEmpty }>
						{ __( 'Empty trash', 'scriptdock' ) }
					</Button>
				) }
			</div>
			{ loading && (
				<ul className="sd-trash__list" aria-hidden="true">
					{ [ 0, 1, 2 ].map( ( index ) => (
						<li key={ index } className="sd-trash__row">
							<Skeleton variant="pill" shimmer />
							<Skeleton width="40%" shimmer />
						</li>
					) ) }
				</ul>
			) }
			{ ! loading && items.length === 0 && (
				<p className="sd-trash__empty">
					{ __( 'The trash is empty.', 'scriptdock' ) }
				</p>
			) }
			{ ! loading && items.length > 0 && (
				<ul className="sd-trash__list">
					{ items.map( ( item ) => (
						<li key={ item.id } className="sd-trash__row">
							<TypeChip type={ item.type } size="sm" />
							<span className="sd-trash__name" dir="auto">
								{ item.title }
							</span>
							{ item.trashed && item.trashed.human && (
								<span className="sd-trash__when">
									{ sprintf(
										/* translators: %s: time since, for example "4 days ago". */
										__( 'Trashed %s', 'scriptdock' ),
										item.trashed.human
									) }
								</span>
							) }
							<span className="sd-trash__actions">
								<Button
									size="sm"
									loading={ busy[ item.id ] === 'untrash' }
									loadingLabel={ __(
										'Restoring…',
										'scriptdock'
									) }
									onClick={ () => onRestore( item ) }
									aria-label={ sprintf(
										/* translators: %s: snippet title. */
										__( 'Restore %s', 'scriptdock' ),
										item.title
									) }
								>
									{ __( 'Restore', 'scriptdock' ) }
								</Button>
								<Button
									size="sm"
									variant="tertiary"
									className="sd-button--danger-text"
									onClick={ () => onDelete( item ) }
									aria-label={ sprintf(
										/* translators: %s: snippet title. */
										__(
											'Delete %s permanently',
											'scriptdock'
										),
										item.title
									) }
								>
									{ __( 'Delete permanently', 'scriptdock' ) }
								</Button>
							</span>
						</li>
					) ) }
				</ul>
			) }
		</section>
	);
}
