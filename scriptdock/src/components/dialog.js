/**
 * sd-Modal, sd-ConfirmDialog, sd-Drawer and sd-BottomSheet.
 *
 * Every layer moves focus in when it opens, keeps Tab inside, returns focus
 * when it closes, closes on Escape, locks the page scroll and makes the
 * WordPress page behind it inert.
 */
import { useLayoutEffect } from '@wordpress/element';
import {
	useConstrainedTabbing,
	useFocusOnMount,
	useFocusReturn,
	useInstanceId,
	useMergeRefs,
} from '@wordpress/compose';
import { __ } from '@wordpress/i18n';
import IconButton from './icon-button';
import { cx, Portal } from './utils';

let openLayers = 0;

/**
 * Locks scrolling and hides the page from assistive technology while at
 * least one layer is open. Counts, so stacked layers (a confirm over a
 * drawer) release only when the last one closes.
 *
 * A layout effect, so the page is no longer inert by the time focus goes
 * back to the element that opened the layer.
 */
function useBackgroundLock() {
	useLayoutEffect( () => {
		openLayers++;
		const wrap = document.getElementById( 'wpwrap' );
		if ( openLayers === 1 ) {
			document.documentElement.classList.add( 'sd-scroll-locked' );
			if ( wrap ) {
				wrap.inert = true;
			}
		}
		return () => {
			openLayers--;
			if ( openLayers === 0 ) {
				document.documentElement.classList.remove( 'sd-scroll-locked' );
				if ( wrap ) {
					wrap.inert = false;
				}
			}
		};
	}, [] );
}

/**
 * The layer: backdrop plus a dialog panel. Exported so a surface with its
 * own chrome — the command palette — gets the same scrim, focus trap and
 * Escape handling as the dialogs.
 *
 * @param {Object}   props              Props.
 * @param {string}   props.kind         modal, drawer or sheet.
 * @param {string}   props.role         dialog or alertdialog.
 * @param {string}   props.labelledBy   Id of the title.
 * @param {string}   props.describedBy  Id of the description.
 * @param {Function} props.onClose      Closes the layer.
 * @param {boolean}  props.closeOnScrim Close when the backdrop is clicked.
 * @param {string}   props.className    Panel classes.
 * @param {string}   props.focus        What gets focus: firstElement or
 *                                      firstInputElement.
 * @param {Element}  props.children     Panel content.
 * @return {Element} The layer.
 */
export function Layer( {
	kind = 'modal',
	role = 'dialog',
	labelledBy,
	describedBy,
	onClose,
	closeOnScrim = true,
	className,
	focus = 'firstElement',
	children,
} ) {
	useBackgroundLock();
	const ref = useMergeRefs( [
		useConstrainedTabbing(),
		useFocusReturn(),
		useFocusOnMount( focus ),
	] );

	const onKeyDown = ( event ) => {
		if ( event.key === 'Escape' && ! event.defaultPrevented ) {
			event.preventDefault();
			event.stopPropagation();
			onClose();
		}
	};

	return (
		<Portal>
			<div
				className={ cx(
					'sd-app sd-layer',
					kind !== 'modal' && `sd-layer--${ kind }`
				) }
			>
				{ /* The backdrop is a pointer target only; Escape closes for keyboards. */ }
				{ /* eslint-disable-next-line jsx-a11y/click-events-have-key-events, jsx-a11y/no-static-element-interactions */ }
				<div
					className="sd-scrim"
					onClick={ closeOnScrim ? onClose : undefined }
				/>
				<div
					ref={ ref }
					role={ role }
					aria-modal="true"
					aria-labelledby={ labelledBy }
					aria-describedby={ describedBy }
					className={ className }
					tabIndex={ -1 }
					onKeyDown={ onKeyDown }
				>
					{ children }
				</div>
			</div>
		</Portal>
	);
}

/**
 * A modal: sm 480, md 640, lg 880, or full screen above the toolbar.
 *
 * @param {Object}   props             Props.
 * @param {boolean}  props.open        Shown.
 * @param {Function} props.onClose     Closes it.
 * @param {string}   props.title       Title.
 * @param {string}   props.subtitle    Line under the title.
 * @param {string}   props.size        sm, md, lg or full.
 * @param {boolean}  props.tinted      Gray body.
 * @param {Element}  props.footer      Footer content (Back · Step · Next).
 * @param {Element}  props.headerExtra Content after the title (toggles).
 * @param {string}   props.className   Extra panel classes.
 * @param {Element}  props.children    Body.
 * @return {Element|null} The modal.
 */
export function Modal( {
	open,
	onClose,
	title,
	subtitle,
	size = 'md',
	tinted = false,
	footer,
	headerExtra,
	className,
	children,
} ) {
	const id = useInstanceId( Modal, 'sd-modal' );
	if ( ! open ) {
		return null;
	}
	return (
		<Layer
			labelledBy={ `${ id }-title` }
			onClose={ onClose }
			className={ cx(
				'sd-modal',
				size !== 'md' && `sd-modal--${ size }`,
				className
			) }
		>
			<div className="sd-modal__header">
				<div className="sd-modal__heading">
					<h2 id={ `${ id }-title` } className="sd-modal__title">
						{ title }
					</h2>
					{ subtitle && (
						<span className="sd-modal__subtitle">{ subtitle }</span>
					) }
				</div>
				{ headerExtra }
				<IconButton
					className="sd-modal__close"
					icon="close"
					label={ __( 'Close', 'scriptdock' ) }
					size="compact"
					iconSize={ 15 }
					stroke={ 2 }
					onClick={ onClose }
				/>
			</div>
			<div
				className={ cx(
					'sd-modal__body',
					tinted && 'sd-modal__body--tinted'
				) }
			>
				{ children }
			</div>
			{ footer && <div className="sd-modal__footer">{ footer }</div> }
		</Layer>
	);
}

/**
 * A confirmation: title, consequence, actions. The safe action is the
 * easy one; Escape and Cancel both close it.
 *
 * @param {Object}   props          Props.
 * @param {boolean}  props.open     Shown.
 * @param {string}   props.title    Question.
 * @param {Element}  props.children Consequence.
 * @param {Element}  props.actions  Buttons, the first one focused.
 * @param {Element}  props.aside    Link at the end ("View history").
 * @param {Function} props.onCancel Closes it.
 * @return {Element|null} The dialog.
 */
export function ConfirmDialog( {
	open,
	title,
	children,
	actions,
	aside,
	onCancel,
} ) {
	const id = useInstanceId( ConfirmDialog, 'sd-confirm' );
	if ( ! open ) {
		return null;
	}
	return (
		<Layer
			role="alertdialog"
			labelledBy={ `${ id }-title` }
			describedBy={ `${ id }-text` }
			onClose={ onCancel }
			closeOnScrim={ false }
			className="sd-modal sd-modal--sm sd-confirm"
		>
			<h2 id={ `${ id }-title` } className="sd-confirm__title">
				{ title }
			</h2>
			<div id={ `${ id }-text` } className="sd-confirm__text">
				{ children }
			</div>
			<div className="sd-confirm__actions">
				{ actions }
				{ aside && (
					<span className="sd-confirm__aside">{ aside }</span>
				) }
			</div>
		</Layer>
	);
}

/**
 * A drawer from the end edge: 560 (quick view) or 720 wide (history).
 *
 * @param {Object}   props             Props.
 * @param {boolean}  props.open        Shown.
 * @param {Function} props.onClose     Closes it.
 * @param {string}   props.title       Title.
 * @param {Element}  props.subtitle    Line under the title.
 * @param {Element}  props.headerExtra Content before the close button.
 * @param {boolean}  props.wide        720px.
 * @param {Element}  props.footer      Footer actions.
 * @param {string}   props.closeLabel  Close button name.
 * @param {Element}  props.children    Body.
 * @return {Element|null} The drawer.
 */
export function Drawer( {
	open,
	onClose,
	title,
	subtitle,
	headerExtra,
	wide = false,
	footer,
	closeLabel = __( 'Close', 'scriptdock' ),
	children,
} ) {
	const id = useInstanceId( Drawer, 'sd-drawer' );
	if ( ! open ) {
		return null;
	}
	return (
		<Layer
			kind="drawer"
			labelledBy={ `${ id }-title` }
			onClose={ onClose }
			className={ cx( 'sd-drawer', wide && 'sd-drawer--wide' ) }
		>
			<div className="sd-drawer__header">
				<div className="sd-drawer__heading">
					<h2 id={ `${ id }-title` } className="sd-drawer__title">
						{ title }
					</h2>
					{ subtitle && (
						<span className="sd-drawer__subtitle">
							{ subtitle }
						</span>
					) }
				</div>
				{ headerExtra }
				<IconButton
					icon="close"
					label={ closeLabel }
					variant="soft"
					size="compact"
					iconSize={ 14 }
					stroke={ 2 }
					onClick={ onClose }
				/>
			</div>
			<div className="sd-drawer__body">{ children }</div>
			{ footer && <div className="sd-drawer__footer">{ footer }</div> }
		</Layer>
	);
}

/**
 * A bottom sheet on phones (filters, the targeting summary).
 *
 * @param {Object}   props          Props.
 * @param {boolean}  props.open     Shown.
 * @param {Function} props.onClose  Closes it.
 * @param {string}   props.title    Title.
 * @param {Element}  props.children Content.
 * @return {Element|null} The sheet.
 */
export function BottomSheet( { open, onClose, title, children } ) {
	const id = useInstanceId( BottomSheet, 'sd-sheet' );
	if ( ! open ) {
		return null;
	}
	return (
		<Layer
			kind="sheet"
			labelledBy={ `${ id }-title` }
			onClose={ onClose }
			className="sd-sheet"
		>
			<span className="sd-sheet__handle" aria-hidden="true" />
			<h2 id={ `${ id }-title` } className="sd-sheet__title">
				{ title }
			</h2>
			{ children }
		</Layer>
	);
}
