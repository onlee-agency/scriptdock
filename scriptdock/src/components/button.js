/**
 * sd-Button, sd-IconButton, sd-SplitButton, sd-CopyChip and sd-Link.
 */
import { forwardRef, useEffect, useRef, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { speak } from '@wordpress/a11y';
import Icon from './icon';
import { copyText, cx } from './utils';
import DropdownMenu from './menu';

/**
 * A button, or a link styled as one when `href` is set.
 *
 * @param {Object}  props              Props.
 * @param {string}  props.variant      primary, secondary, tertiary, accent,
 *                                     danger, danger-solid, inverse, on-ink.
 * @param {string}  props.size         sm, compact, md or lg.
 * @param {string}  props.dot          Icon in the orange dot at the end
 *                                     (primary, secondary, inverse).
 * @param {string}  props.icon         Icon before the label.
 * @param {boolean} props.loading      Show a spinner and `loadingLabel`.
 * @param {string}  props.loadingLabel Label while loading ("Saving…").
 * @param {string}  props.href         Render a link.
 * @param {boolean} props.disabled     Disabled.
 * @param {string}  props.className    Extra classes.
 * @param {Element} props.children     Label.
 * @param {string}  props.type         Button type (button or submit).
 * @param {Object}  ref                Forwarded ref.
 * @return {Element} The button.
 */
function Button(
	{
		variant = 'secondary',
		size = 'md',
		dot,
		icon,
		loading = false,
		loadingLabel,
		href,
		disabled = false,
		className,
		children,
		type = 'button',
		...rest
	},
	ref
) {
	const classes = cx(
		'sd-button',
		`sd-button--${ variant }`,
		size !== 'md' && `sd-button--${ size }`,
		loading && 'is-loading',
		className
	);
	const onDark = [ 'primary', 'danger-solid', 'on-ink' ].includes( variant );
	const content = loading ? (
		<>
			<span
				className={ cx( 'sd-spinner', onDark && 'sd-spinner--on-ink' ) }
				aria-hidden="true"
			/>
			{ loadingLabel || children }
		</>
	) : (
		<>
			{ icon && <Icon name={ icon } size={ 16 } /> }
			{ children }
			{ dot && (
				<span className="sd-button__dot" aria-hidden="true">
					<Icon name={ dot } size={ 14 } stroke={ 2.4 } />
				</span>
			) }
		</>
	);

	if ( href && ! disabled ) {
		return (
			<a ref={ ref } className={ classes } href={ href } { ...rest }>
				{ content }
			</a>
		);
	}
	return (
		<button
			ref={ ref }

			type={ type }
			className={ classes }
			disabled={ disabled }
			aria-busy={ loading || undefined }
			{ ...rest }
		>
			{ content }
		</button>
	);
}

const ForwardedButton = forwardRef( Button );
export default ForwardedButton;

export { default as IconButton } from './icon-button';

/**
 * Save plus a menu of save options.
 *
 * @param {Object}   props              Props.
 * @param {string}   props.label        Main action label.
 * @param {Function} props.onClick      Main action.
 * @param {string}   props.menuLabel    Accessible name of the menu button.
 * @param {Array}    props.items        Menu items (see DropdownMenu).
 * @param {boolean}  props.disabled     Main action disabled.
 * @param {boolean}  props.menuDisabled Menu disabled; follows `disabled`
 *                                      unless set.
 * @param {boolean}  props.loading      Main action is running.
 * @param {string}   props.className    Extra classes.
 * @return {Element} The split button.
 */
export function SplitButton( {
	label,
	onClick,
	menuLabel = __( 'More save options', 'scriptdock' ),
	items = [],
	disabled = false,
	menuDisabled = disabled,
	loading = false,
	className,
} ) {
	return (
		<div className={ cx( 'sd-split-button', className ) }>
			<button
				type="button"
				className="sd-split-button__main"
				onClick={ onClick }
				disabled={ disabled || loading }
				aria-busy={ loading || undefined }
			>
				{ loading && (
					<span
						className="sd-spinner sd-spinner--on-ink"
						aria-hidden="true"
					/>
				) }
				{ label }
			</button>
			<span className="sd-split-button__divider" aria-hidden="true" />
			<DropdownMenu
				label={ menuLabel }
				items={ items }
				align="end"
				renderToggle={ ( toggleProps ) => (
					<button
						type="button"
						className="sd-split-button__menu"
						aria-label={ menuLabel }
						disabled={ menuDisabled || loading }
						{ ...toggleProps }
					>
						<Icon name="chevron-down" size={ 16 } stroke={ 2 } />
					</button>
				) }
			/>
		</div>
	);
}

/**
 * A value you can copy, such as a shortcode. Shows "Copied" for two
 * seconds and tells screen readers.
 *
 * @param {Object}   props           Props.
 * @param {string}   props.value     Text to copy.
 * @param {string}   props.text      What the chip reads, when showing the
 *                                   whole value would only repeat something
 *                                   already on the screen.
 * @param {string}   props.label     Accessible name.
 * @param {string}   props.className Extra classes.
 * @param {Function} props.onCopy    Called once the value is copied.
 * @return {Element} The chip.
 */
export function CopyChip( { value, text, label, className, onCopy } ) {
	const [ copied, setCopied ] = useState( false );
	const timer = useRef();
	useEffect( () => () => clearTimeout( timer.current ), [] );

	const copy = async () => {
		try {
			await copyText( value );
		} catch {
			return;
		}
		setCopied( true );
		speak( __( 'Copied to clipboard.', 'scriptdock' ) );
		if ( onCopy ) {
			onCopy();
		}
		clearTimeout( timer.current );
		timer.current = setTimeout( () => setCopied( false ), 2000 );
	};

	return (
		<button
			type="button"
			className={ cx( 'sd-copy-chip', copied && 'is-copied', className ) }
			onClick={ copy }
			aria-label={
				label ||
				/* translators: %s: the text that will be copied, such as a shortcode. */
				sprintf( __( 'Copy %s', 'scriptdock' ), value )
			}
		>
			<span className="sd-copy-chip__value">
				{ copied ? __( 'Copied ✓', 'scriptdock' ) : text || value }
			</span>
			{ ! copied && <Icon name="copy" size={ 14 } /> }
		</button>
	);
}

/**
 * A text link with a trailing icon ("View file ↗").
 *
 * @param {Object}  props          Props.
 * @param {string}  props.href     URL.
 * @param {string}  props.icon     Trailing icon.
 * @param {boolean} props.external Opens a new tab.
 * @param {Element} props.children Label.
 * @return {Element} The link.
 */
export function Link( {
	href,
	icon = 'external',
	external = false,
	children,
	...rest
} ) {
	return (
		<a
			className="sd-link"
			href={ href }
			{ ...( external
				? { target: '_blank', rel: 'noopener noreferrer' }
				: {} ) }
			{ ...rest }
		>
			{ children }
			{ icon && <Icon name={ icon } size={ 14 } stroke={ 2 } /> }
			{ external && (
				<span className="sd-visually-hidden">
					{ __( '(opens in a new tab)', 'scriptdock' ) }
				</span>
			) }
		</a>
	);
}
