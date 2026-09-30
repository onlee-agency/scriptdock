/**
 * sd-Banner and sd-Toast.
 */
import {
	createContext,
	useCallback,
	useContext,
	useEffect,
	useMemo,
	useRef,
	useState,
} from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import Icon from './icon';
import IconButton from './icon-button';
import { cx, Portal } from './utils';

const TONE_ICON = {
	danger: 'alert',
	warning: 'warning',
	success: 'check',
	info: 'info',
};

/**
 * A banner in the notice zone or inside a screen. Keeps its icon, so
 * colour is never the only signal.
 *
 * @param {Object}   props           Props.
 * @param {string}   props.tone      danger, warning, success or info.
 * @param {string}   props.title     Bold first line (stacked banners).
 * @param {Element}  props.children  Text.
 * @param {Element}  props.actions   Buttons or links.
 * @param {string}   props.icon      Icon override.
 * @param {boolean}  props.inline    The narrow size, for cards.
 * @param {Function} props.onDismiss Adds a dismiss button.
 * @param {string}   props.className Extra classes.
 * @return {Element} The banner.
 */
export function Banner( {
	tone = 'info',
	title,
	children,
	actions,
	icon,
	inline = false,
	onDismiss,
	className,
} ) {
	const stacked = !! title;
	return (
		<div
			className={ cx(
				'sd-banner',
				`sd-banner--${ tone }`,
				stacked && 'sd-banner--stacked',
				inline && 'sd-banner--inline',
				className
			) }
			role={ tone === 'danger' ? 'alert' : undefined }
		>
			<span className="sd-banner__icon" aria-hidden="true">
				<Icon name={ icon || TONE_ICON[ tone ] } size={ 20 } />
			</span>
			<div className="sd-banner__body">
				{ title && <span className="sd-banner__title">{ title }</span> }
				{ children && (
					<div className="sd-banner__text">{ children }</div>
				) }
				{ stacked && actions && (
					<div className="sd-banner__actions">{ actions }</div>
				) }
			</div>
			{ ! stacked && actions && (
				<div className="sd-banner__actions">{ actions }</div>
			) }
			{ onDismiss && (
				<IconButton
					className="sd-banner__dismiss"
					icon="close"
					label={ __( 'Dismiss', 'scriptdock' ) }
					variant="ghost"
					size="sm"
					iconSize={ 14 }
					stroke={ 2 }
					onClick={ onDismiss }
				/>
			) }
		</div>
	);
}

const ToastContext = createContext( null );

/**
 * Hosts toasts for a screen. Wrap the screen in it and call useToast().
 *
 * @param {Object}  props          Props.
 * @param {Element} props.children The screen.
 * @return {Element} The provider.
 */
export function ToastProvider( { children } ) {
	const [ toasts, setToasts ] = useState( [] );
	const next = useRef( 1 );

	const dismiss = useCallback(
		( id ) =>
			setToasts( ( list ) =>
				list.filter( ( toast ) => toast.id !== id )
			),
		[]
	);
	const show = useCallback( ( toast ) => {
		const id = next.current++;
		setToasts( ( list ) => [
			...list.slice( -2 ),
			{ duration: 4000, ...toast, id },
		] );
		return id;
	}, [] );

	const value = useMemo( () => ( { show, dismiss } ), [ show, dismiss ] );

	return (
		<ToastContext.Provider value={ value }>
			{ children }
			<Portal>
				<div
					className="sd-app sd-toasts"
					role="status"
					aria-live="polite"
					aria-relevant="additions"
				>
					{ toasts.map( ( toast ) => (
						<Toast
							key={ toast.id }
							toast={ toast }
							onDone={ () => dismiss( toast.id ) }
						/>
					) ) }
				</div>
			</Portal>
		</ToastContext.Provider>
	);
}

/**
 * Shows toasts: show( { message, tone, action: { label, onClick } } ).
 *
 * @return {Object} { show, dismiss }.
 */
export function useToast() {
	return useContext( ToastContext );
}

/**
 * One toast. Leaves after its duration, paused while hovered or focused.
 *
 * @param {Object}   props        Props.
 * @param {Object}   props.toast  { message, tone, action, duration }.
 * @param {Function} props.onDone Removes it.
 * @return {Element} The toast.
 */
function Toast( { toast, onDone } ) {
	const [ paused, setPaused ] = useState( false );
	useEffect( () => {
		if ( paused ) {
			return;
		}
		const timer = setTimeout( onDone, toast.duration );
		return () => clearTimeout( timer );
	}, [ paused, toast.duration, onDone ] );

	// "danger" is the banner's word for the same thing.
	const error = toast.tone === 'error' || toast.tone === 'danger';
	return (
		<div
			className={ cx( 'sd-toast', error && 'sd-toast--error' ) }
			onPointerEnter={ () => setPaused( true ) }
			onPointerLeave={ () => setPaused( false ) }
			onFocus={ () => setPaused( true ) }
			onBlur={ () => setPaused( false ) }
		>
			<span className="sd-toast__icon" aria-hidden="true">
				{ error ? '!' : <Icon name="check" size={ 12 } stroke={ 3 } /> }
			</span>
			<span className="sd-toast__message">{ toast.message }</span>
			{ toast.action && (
				<button
					type="button"
					className="sd-toast__action"
					onClick={ () => {
						onDone();
						toast.action.onClick();
					} }
				>
					{ toast.action.label }
				</button>
			) }
		</div>
	);
}
