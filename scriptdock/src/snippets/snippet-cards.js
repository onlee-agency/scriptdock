/**
 * Snippets as cards, for phones: title, the useful line and the switch on
 * top; type, where it runs and the first badge below.
 */
import { __, sprintf } from '@wordpress/i18n';
import {
	Badge,
	Button,
	DropdownMenu,
	Skeleton,
	Switch,
	TYPE_LABELS,
	TypeChip,
	cx,
} from '../components';
import { badgeProps, subline, switchLabel, whereLines } from './messages';

/**
 * @param {Object}   props             Props.
 * @param {Array}    props.items       Snippet rows.
 * @param {boolean}  props.loading     Loading.
 * @param {Object}   props.busy        Snippet IDs whose switch is saving.
 * @param {Function} props.onToggle    Called with a row and the new state.
 * @param {Function} props.onBlocked   Called with a row whose switch is
 *                                     paused or locked.
 * @param {Function} props.onQuickView Called with a row.
 * @param {Function} props.menuItems   Row => row menu items.
 * @return {Element} The cards.
 */
export default function SnippetCards( {
	items,
	loading,
	busy,
	onToggle,
	onBlocked,
	onQuickView,
	menuItems,
} ) {
	if ( loading ) {
		return (
			<ul className="sd-cards" aria-hidden="true">
				{ [ 0, 1, 2, 3 ].map( ( index ) => (
					<li key={ index } className="sd-cards__card">
						<Skeleton variant="title" width="60%" shimmer />
						<Skeleton width="80%" />
						<Skeleton variant="pill" />
					</li>
				) ) }
			</ul>
		);
	}
	return (
		<ul className="sd-cards" aria-label={ __( 'Snippets', 'scriptdock' ) }>
			{ items.map( ( item ) => {
				const line = subline( item, !! busy[ item.id ] );
				const [ place, reach ] = whereLines( item );
				const first = item.badges[ 0 ];
				const firstProps = first && badgeProps( first );
				return (
					<li
						key={ item.id }
						className={ cx(
							'sd-cards__card',
							item.error && ! item.active && 'is-danger',
							item.paused && 'is-paused'
						) }
					>
						<div className="sd-cards__top">
							<div className="sd-cards__text">
								<span className="sd-cards__title-row">
									<a
										className="sd-cards__title"
										href={ item.edit_url }
										dir="auto"
									>
										{ item.title }
									</a>
									{ ! item.trusted && (
										<Badge size="sm" tone="danger">
											{ __( 'Review', 'scriptdock' ) }
										</Badge>
									) }
								</span>
								{ line.text && (
									<span
										className={ cx(
											'sd-cards__sub',
											`is-${ line.kind }`
										) }
										dir={
											line.kind === 'shortcode'
												? 'ltr'
												: undefined
										}
									>
										{ line.text }
									</span>
								) }
							</div>
							<Switch
								checked={ item.active }
								label={ switchLabel( item ) }
								busy={ !! busy[ item.id ] }
								paused={ item.paused }
								locked={
									! item.paused &&
									! item.active &&
									! item.can_activate
								}
								onChange={ ( on ) => onToggle( item, on ) }
								onBlockedClick={ () => onBlocked( item ) }
							/>
						</div>
						<div className="sd-cards__meta">
							<button
								type="button"
								className="sd-snippets__type"
								onClick={ () => onQuickView( item ) }
								aria-label={ sprintf(
									/* translators: 1: code type, 2: snippet title. */
									__(
										'%1$s. Quick view of %2$s',
										'scriptdock'
									),
									TYPE_LABELS[ item.type ],
									item.title
								) }
							>
								<TypeChip type={ item.type } size="xs" />
							</button>
							<span className="sd-cards__where">
								{ reach === '—'
									? place
									: `${ place } · ${ reach }` }
							</span>
							{ first && item.trusted && (
								<Badge
									size="sm"
									tone={ firstProps.tone }
									icon={ firstProps.icon }
									title={ first.detail }
								>
									{ first.label }
								</Badge>
							) }
							<DropdownMenu
								className="sd-cards__menu"
								label={ sprintf(
									/* translators: %s: snippet title. */
									__( 'Actions for %s', 'scriptdock' ),
									item.title
								) }
								align="end"
								items={ menuItems( item ) }
							/>
						</div>
						{ item.error && ! item.active && item.can_edit && (
							<Button
								variant="primary"
								className="sd-cards__fix"
								href={ item.edit_url }
							>
								{ __( 'Fix it', 'scriptdock' ) }
							</Button>
						) }
					</li>
				);
			} ) }
		</ul>
	);
}
