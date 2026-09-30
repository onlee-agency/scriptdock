/**
 * The cards the wizard picks things with, and the arrow-key handling that
 * makes a grid of them behave like one control.
 */
import { useRef } from '@wordpress/element';
import { Icon, Tooltip, cx } from '../components';

/**
 * A grid of cards that arrow keys move through. One choice behaves like radio
 * buttons; several choices behave like a group of toggles.
 *
 * @param {Object}  props           Props.
 * @param {Element} props.children  Cards.
 * @param {boolean} props.multiple  Whether several can be chosen.
 * @param {string}  props.label     Accessible name of the group.
 * @param {string}  props.className Extra class.
 * @return {Element} The grid.
 */
export function CardGrid( { children, multiple = false, label, className } ) {
	const grid = useRef();

	const onKeyDown = ( event ) => {
		const keys = [ 'ArrowRight', 'ArrowLeft', 'ArrowDown', 'ArrowUp' ];
		if ( ! keys.includes( event.key ) || ! grid.current ) {
			return;
		}
		const cards = Array.from(
			grid.current.querySelectorAll( '.sd-pickcard:not([disabled])' )
		);
		const at = cards.indexOf( event.target.closest( '.sd-pickcard' ) );
		if ( at < 0 ) {
			return;
		}
		// Rows are what the browser lays out, so step by the card's own row
		// for up and down.
		const top = cards[ at ].offsetTop;
		const perRow = Math.max(
			1,
			cards.filter( ( card ) => card.offsetTop === top ).length
		);
		const step = {
			ArrowRight: 1,
			ArrowLeft: -1,
			ArrowDown: perRow,
			ArrowUp: -perRow,
		}[ event.key ];
		const next =
			cards[ Math.min( cards.length - 1, Math.max( 0, at + step ) ) ];
		if ( next ) {
			event.preventDefault();
			next.focus();
		}
	};

	return (
		<div
			ref={ grid }
			className={ cx( 'sd-pickgrid', className ) }
			role={ multiple ? 'group' : 'radiogroup' }
			aria-label={ label }
			onKeyDown={ onKeyDown }
		>
			{ children }
		</div>
	);
}

/**
 * One card: a title, a line of description, an optional drawing, and extras
 * that appear inside it once it is chosen.
 *
 * @param {Object}   props           Props.
 * @param {string}   props.title     Title.
 * @param {string}   props.note      One-line description.
 * @param {Element}  props.art       Drawing above the title.
 * @param {boolean}  props.selected  Whether it is chosen.
 * @param {boolean}  props.multiple  Whether the group takes several.
 * @param {string}   props.reason    Why it cannot be chosen; dims the card.
 * @param {Function} props.onSelect  Called when chosen.
 * @param {Element}  props.children  Extras shown inside the chosen card.
 * @param {number}   props.tabIndex  Tab stop, for the roving group.
 * @param {string}   props.className Extra class.
 * @return {Element} The card.
 */
export function SelectCard( {
	title,
	note,
	art,
	selected = false,
	multiple = false,
	reason = '',
	onSelect,
	children,
	tabIndex,
	className,
} ) {
	const card = (
		<button
			type="button"
			className={ cx(
				'sd-pickcard',
				selected && 'is-selected',
				reason && 'is-unavailable',
				className
			) }
			role={ multiple ? undefined : 'radio' }
			aria-checked={ multiple ? undefined : selected }
			aria-pressed={ multiple ? selected : undefined }
			aria-disabled={ reason ? true : undefined }
			tabIndex={ tabIndex }
			onClick={ () => ( reason ? undefined : onSelect() ) }
		>
			{ art && (
				<span className="sd-pickcard__art" aria-hidden="true">
					{ art }
				</span>
			) }
			<span className="sd-pickcard__title">{ title }</span>
			{ note && <span className="sd-pickcard__note">{ note }</span> }
			{ selected && (
				<span className="sd-pickcard__check" aria-hidden="true">
					<Icon name="check" size={ 12 } stroke={ 3 } />
				</span>
			) }
		</button>
	);

	return (
		<div
			className={ cx(
				'sd-pickcard-wrap',
				selected && children && 'has-extras'
			) }
		>
			{ reason ? <Tooltip text={ reason }>{ card }</Tooltip> : card }
			{ reason && (
				<span className="sd-pickcard__reason">{ reason }</span>
			) }
			{ selected && children && (
				<div className="sd-pickcard__extras">{ children }</div>
			) }
		</div>
	);
}
