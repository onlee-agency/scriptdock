/**
 * sd-DropdownMenu: a menu of actions (row menus, the editor's ⋯ menu, save
 * options) or of choices (filters, sort). A WAI-ARIA menu: arrow keys move,
 * Home and End jump, Escape closes and returns to the toggle, Tab closes.
 *
 * Items are actions, checkboxes (menuitemcheckbox: the menu stays open so
 * several can be ticked) or radios (menuitemradio: picking one closes the
 * menu), and can be gathered in labelled groups.
 */

const ITEM_ROLES =
	'[role="menuitem"], [role="menuitemcheckbox"], [role="menuitemradio"]';
import {
	useEffect,
	useLayoutEffect,
	useRef,
	useState,
} from '@wordpress/element';
import { useInstanceId } from '@wordpress/compose';
import Icon from './icon';
import IconButton from './icon-button';
import { cx, useOutsidePointer } from './utils';

/**
 * @param {Object}   props               Props.
 * @param {string}   props.label         The menu's accessible name, and the
 *                                       toggle's when it is an icon.
 * @param {Array}    props.items         Items: { label, icon, shortcut,
 *                                       onClick, href, danger, disabled,
 *                                       hint }, { type: 'checkbox', label,
 *                                       checked, onChange }, { type: 'radio',
 *                                       label, checked, onClick },
 *                                       { group: label, items: [...] } or
 *                                       { separator: true }.
 * @param {string}   props.align         start or end: which edge lines up.
 * @param {Function} props.renderToggle  Renders a custom toggle; receives the
 *                                       props (ref, aria and handlers) to
 *                                       spread on it.
 * @param {string}   props.toggleIcon    Icon for the default toggle.
 * @param {string}   props.toggleVariant IconButton variant for the default
 *                                       toggle.
 * @param {string}   props.toggleSize    IconButton size for the default
 *                                       toggle.
 * @param {string}   props.className     Extra classes.
 * @param {string}   props.menuClassName Extra classes on the menu.
 * @return {Element} The menu and its toggle.
 */
export default function DropdownMenu( {
	label,
	items = [],
	align = 'start',
	renderToggle,
	toggleIcon = 'more',
	toggleVariant = 'ghost',
	toggleSize = 'sm',
	className,
	menuClassName,
} ) {
	const [ open, setOpen ] = useState( false );
	const [ upward, setUpward ] = useState( false );
	const focusOnOpen = useRef( 'first' );
	const toggleRef = useRef();
	const menuRef = useRef();
	const id = useInstanceId( DropdownMenu, 'sd-menu' );

	const enabledItems = () =>
		menuRef.current
			? Array.from(
					menuRef.current.querySelectorAll( ITEM_ROLES )
				).filter(
					( item ) => item.getAttribute( 'aria-disabled' ) !== 'true'
				)
			: [];

	const openMenu = ( focus = 'first' ) => {
		focusOnOpen.current = focus;
		setOpen( true );
	};

	const close = ( returnFocus = true ) => {
		setOpen( false );
		if ( returnFocus && toggleRef.current ) {
			toggleRef.current.focus();
		}
	};

	useOutsidePointer( [ toggleRef, menuRef ], () => setOpen( false ), open );

	// Open upward when there is no room below, then move focus in.
	useLayoutEffect( () => {
		if ( ! open || ! menuRef.current || ! toggleRef.current ) {
			return;
		}
		const toggle = toggleRef.current.getBoundingClientRect();
		const height = menuRef.current.offsetHeight;
		setUpward(
			toggle.bottom + height + 16 > window.innerHeight &&
				toggle.top > height + 16
		);
		const list = enabledItems();
		const target =
			focusOnOpen.current === 'last'
				? list[ list.length - 1 ]
				: list[ 0 ];
		if ( target ) {
			target.focus();
		}
	}, [ open ] );

	// Close when focus leaves the menu (Tab, or a click elsewhere).
	useEffect( () => {
		if ( ! open || ! menuRef.current ) {
			return;
		}
		const menu = menuRef.current;
		const onFocusOut = ( event ) => {
			const next = event.relatedTarget;
			if (
				next &&
				! menu.contains( next ) &&
				! toggleRef.current.contains( next )
			) {
				setOpen( false );
			}
		};
		menu.addEventListener( 'focusout', onFocusOut );
		return () => menu.removeEventListener( 'focusout', onFocusOut );
	}, [ open ] );

	const onToggleKeyDown = ( event ) => {
		if ( [ 'ArrowDown', 'Enter', ' ' ].includes( event.key ) ) {
			event.preventDefault();
			openMenu( 'first' );
		} else if ( event.key === 'ArrowUp' ) {
			event.preventDefault();
			openMenu( 'last' );
		}
	};

	const onMenuKeyDown = ( event ) => {
		const list = enabledItems();
		const index = list.indexOf( event.target.ownerDocument.activeElement );
		const move = ( to ) => {
			event.preventDefault();
			list[ ( to + list.length ) % list.length ].focus();
		};
		switch ( event.key ) {
			case 'ArrowDown':
				move( index + 1 );
				break;
			case 'ArrowUp':
				move( index - 1 );
				break;
			case 'Home':
				move( 0 );
				break;
			case 'End':
				move( list.length - 1 );
				break;
			case 'Escape':
				event.preventDefault();
				event.stopPropagation();
				close();
				break;
			case 'Tab':
				setOpen( false );
				break;
		}
	};

	const toggleProps = {
		ref: toggleRef,
		'aria-haspopup': 'menu',
		'aria-expanded': open,
		'aria-controls': id,
		onClick: () => ( open ? close( false ) : openMenu( 'first' ) ),
		onKeyDown: onToggleKeyDown,
	};

	return (
		<div className={ cx( 'sd-menu-anchor', className ) }>
			{ renderToggle ? (
				renderToggle( toggleProps )
			) : (
				<IconButton
					icon={ toggleIcon }
					label={ label }
					variant={ toggleVariant }
					size={ toggleSize }
					{ ...toggleProps }
				/>
			) }
			<ul
				id={ id }
				ref={ menuRef }
				role="menu"
				aria-label={ label }
				className={ cx(
					'sd-menu',
					align === 'end' && 'sd-menu--end',
					upward && 'sd-menu--up',
					menuClassName
				) }
				hidden={ ! open }
				onKeyDown={ onMenuKeyDown }
				onFocus={ ( event ) =>
					// Roving tabindex: the item with focus is the one a
					// keyboard can reach, so a long menu that scrolls still
					// holds a focusable element.
					enabledItems().forEach( ( item ) => {
						item.tabIndex = item === event.target ? 0 : -1;
					} )
				}
			>
				{ renderItems( items, () => close() ) }
			</ul>
		</div>
	);
}

/**
 * Menu rows: items, separators and labelled groups.
 *
 * @param {Array}    items  Items.
 * @param {Function} onDone Closes the menu.
 * @return {Element[]} Rows.
 */
function renderItems( items, onDone ) {
	return items.map( ( item, index ) => {
		if ( item.separator ) {
			return (
				<li
					key={ `separator-${ index }` }
					role="separator"
					className="sd-menu__separator"
				/>
			);
		}
		if ( item.group ) {
			return (
				<li
					key={ `group-${ item.group }` }
					role="none"
					className="sd-menu__group"
				>
					<span className="sd-menu__group-label" aria-hidden="true">
						{ item.group }
					</span>
					<ul role="group" aria-label={ item.group }>
						{ renderItems( item.items || [], onDone ) }
					</ul>
				</li>
			);
		}
		return (
			<li key={ item.key || item.label } role="none">
				<MenuItem item={ item } onDone={ onDone } />
			</li>
		);
	} );
}

/**
 * One menu item: a link or a button.
 *
 * @param {Object}   props        Props.
 * @param {Object}   props.item   The item.
 * @param {Function} props.onDone Closes the menu.
 * @return {Element} The item.
 */
function MenuItem( { item, onDone } ) {
	const choice = item.type === 'checkbox' || item.type === 'radio';
	const classes = cx(
		'sd-menu__item',
		item.danger && 'sd-menu__item--danger',
		choice && 'sd-menu__item--choice'
	);
	const content = (
		<>
			{ choice && (
				<span
					className={ cx(
						'sd-menu__mark',
						item.type === 'radio' && 'sd-menu__mark--radio'
					) }
					aria-hidden="true"
				>
					{ item.checked && (
						<Icon name="check" size={ 12 } stroke={ 3 } />
					) }
				</span>
			) }
			{ ! choice && item.icon && (
				<Icon name={ item.icon } size={ 15 } stroke={ 1.6 } />
			) }
			<span className="sd-menu__label">{ item.label }</span>
			{ item.hint !== undefined && (
				<span className="sd-menu__hint">{ item.hint }</span>
			) }
			{ item.shortcut && (
				<kbd className="sd-menu__shortcut" aria-hidden="true">
					{ item.shortcut }
				</kbd>
			) }
		</>
	);
	if ( choice ) {
		return (
			<button
				type="button"
				role={
					item.type === 'checkbox'
						? 'menuitemcheckbox'
						: 'menuitemradio'
				}
				aria-checked={ !! item.checked }
				tabIndex={ -1 }
				className={ classes }
				aria-disabled={ item.disabled || undefined }
				onClick={ () => {
					if ( item.disabled ) {
						return;
					}
					if ( item.type === 'checkbox' ) {
						// Ticking a box keeps the menu open for the next one.
						item.onChange( ! item.checked );
						return;
					}
					onDone();
					item.onClick();
				} }
			>
				{ content }
			</button>
		);
	}
	if ( item.href && ! item.disabled ) {
		return (
			<a
				role="menuitem"
				tabIndex={ -1 }
				className={ classes }
				href={ item.href }
				onClick={ onDone }
			>
				{ content }
			</a>
		);
	}
	return (
		<button
			type="button"
			role="menuitem"
			tabIndex={ -1 }
			className={ classes }
			aria-disabled={ item.disabled || undefined }
			title={ item.disabled ? item.reason : undefined }
			onClick={ () => {
				if ( item.disabled ) {
					return;
				}
				onDone();
				if ( item.onClick ) {
					item.onClick();
				}
			} }
		>
			{ content }
		</button>
	);
}
