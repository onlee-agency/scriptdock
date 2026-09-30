/**
 * Form controls: sd-Field, sd-TextField, sd-TextArea, sd-Select,
 * sd-SearchField, sd-NumberStepper, sd-Switch, sd-Checkbox, sd-Radio,
 * sd-SegmentedControl, sd-WeekdayChips, sd-KeyValueField, sd-TimeRange and
 * sd-FileDropzone.
 */
import {
	forwardRef,
	useEffect,
	useLayoutEffect,
	useRef,
	useState,
} from '@wordpress/element';
import { useInstanceId } from '@wordpress/compose';
import { __, sprintf } from '@wordpress/i18n';
import Icon from './icon';
import IconButton from './icon-button';
import { cx, isApple } from './utils';

/**
 * Label, control, help and error. Gives the control the ids it needs for
 * aria-describedby.
 *
 * @param {Object}   props           Props.
 * @param {string}   props.label     Visible label.
 * @param {boolean}  props.hideLabel Keep the label for screen readers only.
 * @param {string}   props.help      Help text under the control.
 * @param {string}   props.error     Error text; replaces the help.
 * @param {string}   props.counter   Counter text at the end ("41 / 300").
 * @param {boolean}  props.required  Mark as required.
 * @param {boolean}  props.disabled  Disabled.
 * @param {string}   props.id        The control's id.
 * @param {string}   props.className Extra classes.
 * @param {Function} props.children  Render function receiving
 *                                   { id, describedBy, invalid }.
 * @return {Element} The field.
 */
export function Field( {
	label,
	hideLabel = false,
	help,
	error,
	counter,
	required = false,
	disabled = false,
	id,
	className,
	children,
} ) {
	const instance = useInstanceId( Field, 'sd-field' );
	const controlId = id || instance;
	const helpId = help || error ? `${ controlId }-help` : undefined;
	return (
		<div
			className={ cx( 'sd-field', disabled && 'is-disabled', className ) }
		>
			{ label && (
				<label
					className={ cx(
						'sd-field__label',
						hideLabel && 'sd-visually-hidden'
					) }
					htmlFor={ controlId }
				>
					{ label }
					{ required && (
						<span className="sd-field__required" aria-hidden="true">
							{ ' *' }
						</span>
					) }
				</label>
			) }
			{ children( {
				id: controlId,
				describedBy: helpId,
				invalid: !! error,
			} ) }
			{ ( help || error || counter ) && (
				<div className="sd-field__footer">
					{ error ? (
						<span id={ helpId } className="sd-field__error">
							{ error }
						</span>
					) : (
						help && (
							<span id={ helpId } className="sd-field__help">
								{ help }
							</span>
						)
					) }
					{ counter && (
						<span className="sd-field__counter">{ counter }</span>
					) }
				</div>
			) }
		</div>
	);
}

/**
 * A single-line text input.
 *
 * @param {Object}   props           Props.
 * @param {string}   props.label     Label.
 * @param {string}   props.value     Value.
 * @param {Function} props.onChange  Called with the new value.
 * @param {string}   props.help      Help text.
 * @param {string}   props.error     Error text (also marks it invalid).
 * @param {boolean}  props.mono      Monospace (IDs, hooks, paths).
 * @param {boolean}  props.required  Required.
 * @param {boolean}  props.disabled  Disabled.
 * @param {boolean}  props.hideLabel Visually hide the label.
 * @param {string}   props.className Extra classes on the field.
 * @param {string}   props.type      Input type (text, url, email).
 * @param {Object}   ref             Forwarded to the input.
 * @return {Element} The field.
 */
function TextFieldBase(
	{
		label,
		value,
		onChange,
		help,
		error,
		mono = false,
		required = false,
		disabled = false,
		hideLabel = false,
		className,
		type = 'text',
		...rest
	},
	ref
) {
	return (
		<Field
			label={ label }
			hideLabel={ hideLabel }
			help={ help }
			error={ error }
			required={ required }
			disabled={ disabled }
			className={ className }
		>
			{ ( { id, describedBy, invalid } ) => (
				<span
					className={ cx(
						'sd-input-wrap',
						invalid && 'sd-input-wrap--end sd-input-wrap--error'
					) }
				>
					<input
						ref={ ref }
						id={ id }
						type={ type }
						className={ cx( 'sd-input', mono && 'sd-input--mono' ) }
						value={ value }
						onChange={ ( event ) => onChange( event.target.value ) }
						aria-describedby={ describedBy }
						aria-invalid={ invalid || undefined }
						required={ required }
						disabled={ disabled }
						{ ...rest }
					/>
					{ invalid && (
						<span className="sd-input-wrap__end" aria-hidden="true">
							<Icon name="alert" size={ 18 } />
						</span>
					) }
				</span>
			) }
		</Field>
	);
}

export const TextField = forwardRef( TextFieldBase );

/**
 * A multi-line text input that grows with its content.
 *
 * @param {Object}   props           Props.
 * @param {string}   props.label     Label.
 * @param {string}   props.value     Value.
 * @param {boolean}  props.hideLabel Whether the label is for screen readers only.
 * @param {Function} props.onChange  Called with the new value.
 * @param {string}   props.help      Help text.
 * @param {string}   props.error     Error text.
 * @param {number}   props.maxLength Character limit; shows a counter.
 * @param {boolean}  props.autoGrow  Grow to fit the text.
 * @param {boolean}  props.mono      Monospace.
 * @param {string}   props.className Extra classes on the field.
 * @return {Element} The field.
 */
export function TextArea( {
	label,
	value,
	onChange,
	hideLabel = false,
	help,
	error,
	maxLength,
	autoGrow = true,
	mono = false,
	className,
	...rest
} ) {
	const ref = useRef();
	useLayoutEffect( () => {
		const node = ref.current;
		if ( ! autoGrow || ! node ) {
			return;
		}
		node.style.blockSize = 'auto';
		node.style.blockSize = `${ node.scrollHeight + 2 }px`;
	}, [ value, autoGrow ] );

	const counter = maxLength
		? sprintf(
				/* translators: 1: characters used, 2: character limit. */
				__( '%1$d / %2$d', 'scriptdock' ),
				( value || '' ).length,
				maxLength
			)
		: undefined;

	return (
		<Field
			label={ label }
			hideLabel={ hideLabel }
			help={ help }
			error={ error }
			counter={ counter }
			className={ className }
		>
			{ ( { id, describedBy, invalid } ) => (
				<textarea
					ref={ ref }
					id={ id }
					className={ cx(
						'sd-textarea',
						mono && 'sd-textarea--mono'
					) }
					value={ value }
					onChange={ ( event ) => onChange( event.target.value ) }
					maxLength={ maxLength }
					aria-describedby={ describedBy }
					aria-invalid={ invalid || undefined }
					{ ...rest }
				/>
			) }
		</Field>
	);
}

/**
 * A native select with our chevron.
 *
 * @param {Object}   props           Props.
 * @param {string}   props.label     Label.
 * @param {string}   props.value     Selected value.
 * @param {Function} props.onChange  Called with the new value.
 * @param {Array}    props.options   { value, label, disabled } items.
 * @param {Array}    props.groups    { label, options } groups, instead of options.
 * @param {string}   props.help      Help text.
 * @param {boolean}  props.hideLabel Visually hide the label.
 * @param {string}   props.className Extra classes on the field.
 * @return {Element} The field.
 */
export function Select( {
	label,
	value,
	onChange,
	groups,
	options = [],
	help,
	hideLabel = false,
	className,
	...rest
} ) {
	return (
		<Field
			label={ label }
			hideLabel={ hideLabel }
			help={ help }
			className={ className }
		>
			{ ( { id, describedBy } ) => (
				<span className="sd-select">
					<select
						id={ id }
						className="sd-select__control"
						value={ value }
						onChange={ ( event ) => onChange( event.target.value ) }
						aria-describedby={ describedBy }
						{ ...rest }
					>
						{ groups
							? groups.map( ( group ) => (
									<optgroup
										key={ group.label }
										label={ group.label }
									>
										{ group.options.map( ( option ) => (
											<option
												key={ option.value }
												value={ option.value }
												disabled={ option.disabled }
											>
												{ option.label }
											</option>
										) ) }
									</optgroup>
								) )
							: options.map( ( option ) => (
									<option
										key={ option.value }
										value={ option.value }
										disabled={ option.disabled }
									>
										{ option.label }
									</option>
								) ) }
					</select>
					<span className="sd-select__chevron" aria-hidden="true">
						<Icon name="chevron-down" size={ 16 } stroke={ 2 } />
					</span>
				</span>
			) }
		</Field>
	);
}

/**
 * The search pill, with an optional keyboard hint.
 *
 * @param {Object}   props             Props.
 * @param {string}   props.label       Accessible name (not shown).
 * @param {string}   props.value       Value.
 * @param {Function} props.onChange    Called with the new value.
 * @param {string}   props.placeholder Placeholder.
 * @param {boolean}  props.shortcut    Show the ⌘K / Ctrl K hint.
 * @param {string}   props.size        md or sm (36px).
 * @param {string}   props.className   Extra classes.
 * @param {Object}   ref               Forwarded to the input.
 * @return {Element} The search field.
 */
function SearchFieldBase(
	{
		label,
		value,
		onChange,
		placeholder,
		shortcut = false,
		size = 'md',
		className,
		...rest
	},
	ref
) {
	const id = useInstanceId( SearchFieldBase, 'sd-search' );
	return (
		<span
			className={ cx(
				'sd-search',
				shortcut && 'sd-search--kbd',
				size === 'sm' && 'sd-search--sm',
				className
			) }
		>
			<label htmlFor={ id } className="sd-visually-hidden">
				{ label }
			</label>
			<span className="sd-search__icon" aria-hidden="true">
				<Icon name="search" size={ 17 } />
			</span>
			<input
				ref={ ref }
				id={ id }
				type="search"
				className="sd-input"
				value={ value }
				placeholder={ placeholder }
				onChange={ ( event ) => onChange( event.target.value ) }
				{ ...rest }
			/>
			{ shortcut && (
				<kbd className="sd-kbd" aria-hidden="true">
					{ isApple() ? '⌘K' : __( 'Ctrl K', 'scriptdock' ) }
				</kbd>
			) }
		</span>
	);
}

export const SearchField = forwardRef( SearchFieldBase );

/**
 * − value + in one field.
 *
 * @param {Object}   props           Props.
 * @param {string}   props.label     Label.
 * @param {number}   props.value     Value.
 * @param {Function} props.onChange  Called with the new number.
 * @param {number}   props.min       Minimum.
 * @param {number}   props.max       Maximum.
 * @param {number}   props.step      Step.
 * @param {string}   props.help      Help text.
 * @param {boolean}  props.disabled  Cannot be changed.
 * @param {string}   props.className Extra classes on the field.
 * @param {string}   props.context   What the field belongs to, for screen
 *                                   readers when several share a label
 *                                   ("Header" for one of three Priority
 *                                   fields). Not shown.
 * @return {Element} The field.
 */
export function NumberStepper( {
	label,
	value,
	onChange,
	min = -Infinity,
	max = Infinity,
	step = 1,
	help,
	disabled = false,
	className,
	context,
} ) {
	const clamp = ( number ) => Math.min( max, Math.max( min, number ) );
	const spoken = context
		? sprintf(
				/* translators: 1: field name, such as "Priority", 2: what it belongs to, such as "Header". */
				__( '%1$s (%2$s)', 'scriptdock' ),
				label,
				context
			)
		: label;
	return (
		<Field
			label={
				context ? (
					<>
						{ label }
						<span className="sd-visually-hidden">
							{ ` (${ context })` }
						</span>
					</>
				) : (
					label
				)
			}
			help={ help }
			className={ className }
		>
			{ ( { id, describedBy } ) => (
				<span className="sd-number">
					<button
						type="button"
						className="sd-number__step"
						aria-label={
							/* translators: %s: field name, such as "Priority". */
							sprintf( __( 'Decrease %s', 'scriptdock' ), spoken )
						}
						disabled={ disabled || value <= min }
						onClick={ () => onChange( clamp( value - step ) ) }
					>
						−
					</button>
					<input
						id={ id }
						className="sd-number__value"
						type="number"
						inputMode="numeric"
						value={ value }
						min={ Number.isFinite( min ) ? min : undefined }
						max={ Number.isFinite( max ) ? max : undefined }
						step={ step }
						aria-describedby={ describedBy }
						disabled={ disabled }
						onChange={ ( event ) => {
							const number = parseInt( event.target.value, 10 );
							if ( ! Number.isNaN( number ) ) {
								onChange( clamp( number ) );
							}
						} }
					/>
					<button
						type="button"
						className="sd-number__step"
						aria-label={
							/* translators: %s: field name, such as "Priority". */
							sprintf( __( 'Increase %s', 'scriptdock' ), spoken )
						}
						disabled={ disabled || value >= max }
						onClick={ () => onChange( clamp( value + step ) ) }
					>
						+
					</button>
				</span>
			) }
		</Field>
	);
}

/**
 * The signature switch. On is an ink track with the gradient knob and a
 * check inside it. Busy shows a spinner. Locked (no rights) and paused
 * (tamper protection) cannot be toggled; clicking them calls
 * `onBlockedClick` instead, so the screen can explain or open History.
 *
 * @param {Object}   props                Props.
 * @param {boolean}  props.checked        On.
 * @param {Function} props.onChange       Called with the new state.
 * @param {string}   props.label          Accessible name ("GA4 tag active").
 * @param {string}   props.size           md (44 x 24) or sm (34 x 18).
 * @param {boolean}  props.busy           Saving.
 * @param {boolean}  props.locked         Cannot be changed here.
 * @param {boolean}  props.paused         Paused by tamper protection.
 * @param {Function} props.onBlockedClick Called when locked or paused.
 * @param {string}   props.className      Extra classes.
 * @param {Object}   ref                  Forwarded ref.
 * @return {Element} The switch.
 */
function SwitchBase(
	{
		checked = false,
		onChange,
		label,
		size = 'md',
		busy = false,
		locked = false,
		paused = false,
		onBlockedClick,
		className,
		...rest
	},
	ref
) {
	const blocked = locked || paused;
	return (
		<button
			ref={ ref }
			type="button"
			role="switch"
			aria-checked={ paused ? false : checked }
			aria-label={ label }
			aria-busy={ busy || undefined }
			aria-disabled={ blocked || undefined }
			className={ cx(
				'sd-switch',
				size === 'sm' && 'sd-switch--sm',
				locked && 'is-locked',
				paused && 'is-paused',
				className
			) }
			onClick={ () => {
				if ( busy ) {
					return;
				}
				if ( blocked ) {
					if ( onBlockedClick ) {
						onBlockedClick();
					}
					return;
				}
				onChange( ! checked );
			} }
			{ ...rest }
		>
			<span
				className="sd-spinner sd-switch__spinner"
				aria-hidden="true"
			/>
			<span className="sd-switch__knob" aria-hidden="true">
				<Icon
					name="check"
					size={ 11 }
					stroke={ 3 }
					className="sd-switch__check"
				/>
				<Icon
					name="lock"
					size={ 10 }
					stroke={ 2.2 }
					className="sd-switch__lock"
				/>
			</span>
		</button>
	);
}

export const Switch = forwardRef( SwitchBase );

/**
 * A switch with a visible label ("Keep active").
 *
 * @param {Object} props           Props: Switch props plus `label`.
 * @param {string} props.label     Visible label, also the accessible name.
 * @param {string} props.labelSize sm (12px) or md (14px).
 * @param {string} props.className Extra classes.
 * @return {Element} The labelled switch.
 */
export function SwitchField( {
	label,
	labelSize = 'sm',
	className,
	...props
} ) {
	const id = useInstanceId( SwitchField, 'sd-switch' );
	return (
		<span
			className={ cx(
				'sd-switch-label',
				labelSize === 'md' && 'sd-switch-label--md',
				className
			) }
		>
			<Switch id={ id } { ...props } />
			<label htmlFor={ id }>{ label }</label>
		</span>
	);
}

/**
 * A checkbox, or a radio with `type="radio"`.
 *
 * @param {Object}   props               Props.
 * @param {string}   props.label         Label.
 * @param {boolean}  props.checked       Checked.
 * @param {boolean}  props.indeterminate Mixed.
 * @param {Function} props.onChange      Called with the new state.
 * @param {string}   props.hint          Small text after the label.
 * @param {string}   props.size          md (20px) or sm (18px, tables).
 * @param {boolean}  props.hideLabel     Visually hide the label.
 * @param {string}   props.type          checkbox or radio.
 * @param {string}   props.className     Extra classes.
 * @return {Element} The control.
 */
export function Checkbox( {
	label,
	checked = false,
	indeterminate = false,
	onChange,
	hint,
	size = 'md',
	hideLabel = false,
	type = 'checkbox',
	className,
	...rest
} ) {
	const ref = useRef();
	const id = useInstanceId( Checkbox, 'sd-check', rest.id );
	useEffect( () => {
		if ( ref.current ) {
			ref.current.indeterminate = indeterminate;
		}
	}, [ indeterminate ] );
	return (
		<label
			htmlFor={ id }
			className={ cx(
				'sd-check',
				type === 'radio' && 'sd-check--radio',
				size === 'sm' && 'sd-check--sm',
				className
			) }
		>
			<input
				ref={ ref }
				id={ id }
				type={ type }
				className="sd-check__input"
				checked={ checked }
				onChange={ ( event ) => onChange( event.target.checked ) }
				{ ...rest }
			/>
			<span className="sd-check__box" aria-hidden="true">
				<Icon name="check" size={ 13 } stroke={ 3 } />
			</span>
			<span className={ hideLabel ? 'sd-visually-hidden' : undefined }>
				{ label }
			</span>
			{ hint && <span className="sd-check__hint">{ hint }</span> }
		</label>
	);
}

/**
 * A radio button.
 *
 * @param {Object} props Checkbox props, with name and value.
 * @return {Element} The radio.
 */
export function Radio( props ) {
	return <Checkbox type="radio" { ...props } />;
}

/**
 * Roving focus for a group of options: arrow keys move and select.
 *
 * @param {Array}    options  { value, disabled } items.
 * @param {Function} onChange Called with the new value.
 * @return {Function} keydown handler for each option: ( event, value ).
 */
function useRovingKeys( options, onChange ) {
	return ( event, current ) => {
		const group = event.currentTarget.closest( '[role="radiogroup"]' );
		const enabled = options.filter( ( option ) => ! option.disabled );
		const index = enabled.findIndex(
			( option ) => option.value === current
		);
		const rtl = window.getComputedStyle( group ).direction === 'rtl';
		const forward = [ 'ArrowDown', rtl ? 'ArrowLeft' : 'ArrowRight' ];
		const back = [ 'ArrowUp', rtl ? 'ArrowRight' : 'ArrowLeft' ];
		let next = null;
		if ( forward.includes( event.key ) ) {
			next = enabled[ ( index + 1 ) % enabled.length ];
		} else if ( back.includes( event.key ) ) {
			next = enabled[ index <= 0 ? enabled.length - 1 : index - 1 ];
		} else if ( event.key === 'Home' ) {
			next = enabled[ 0 ];
		} else if ( event.key === 'End' ) {
			next = enabled[ enabled.length - 1 ];
		}
		if ( next ) {
			event.preventDefault();
			onChange( next.value );
			const button = group.querySelector(
				`[data-value="${ window.CSS.escape( String( next.value ) ) }"]`
			);
			if ( button ) {
				button.focus();
			}
		}
	};
}

/**
 * The option that takes Tab focus: the selected one, or the first enabled
 * option when nothing usable is selected, so the group is always reachable.
 *
 * @param {Array}  options  { value, disabled } items.
 * @param {string} selected Selected value.
 * @return {*} The value of the option with tabindex 0.
 */
function tabStop( options, selected ) {
	const enabled = options.filter( ( option ) => ! option.disabled );
	const match = enabled.find( ( option ) => option.value === selected );
	if ( match ) {
		return match.value;
	}
	return enabled.length ? enabled[ 0 ].value : undefined;
}

/**
 * Two to four choices in one pill ("Inline in the page | As a cached
 * file"). Unavailable options keep their label, gray, with a padlock and
 * the reason as a tooltip.
 *
 * @param {Object}   props           Props.
 * @param {string}   props.label     Accessible name of the group.
 * @param {Array}    props.options   { value, label, disabled, reason, icon }.
 * @param {string}   props.value     Selected value.
 * @param {Function} props.onChange  Called with the new value.
 * @param {string}   props.size      md (34px) or sm (26px).
 * @param {boolean}  props.outline   White track with a border.
 * @param {string}   props.className Extra classes.
 * @return {Element} The control.
 */
export function SegmentedControl( {
	label,
	options = [],
	value,
	onChange,
	size = 'md',
	outline = false,
	className,
} ) {
	const onKeyDown = useRovingKeys( options, onChange );
	const focusable = tabStop( options, value );
	return (
		<div
			role="radiogroup"
			aria-label={ label }
			className={ cx(
				'sd-segmented',
				size === 'sm' && 'sd-segmented--sm',
				outline && 'sd-segmented--outline',
				className
			) }
		>
			{ options.map( ( option ) => {
				const selected = option.value === value;
				return (
					<button
						key={ option.value }
						type="button"
						role="radio"
						data-value={ option.value }
						aria-checked={ selected }
						aria-disabled={ option.disabled || undefined }
						title={ option.disabled ? option.reason : undefined }
						tabIndex={ option.value === focusable ? 0 : -1 }
						className="sd-segmented__option"
						onClick={ () =>
							! option.disabled && onChange( option.value )
						}
						onKeyDown={ ( event ) =>
							onKeyDown( event, option.value )
						}
					>
						{ option.disabled ? (
							<Icon name="lock" size={ 13 } stroke={ 2 } />
						) : (
							option.icon && (
								<Icon
									name={ option.icon }
									size={ 13 }
									stroke={ 2 }
								/>
							)
						) }
						{ option.label }
						{ option.disabled && option.reason && (
							<span className="sd-visually-hidden">
								{ ' ' + option.reason }
							</span>
						) }
					</button>
				);
			} ) }
		</div>
	);
}

/**
 * Day chips for a schedule, as toggle buttons.
 *
 * @param {Object}   props          Props.
 * @param {string}   props.label    Accessible name of the group.
 * @param {Array}    props.days     { value, label, name } in display order.
 * @param {Array}    props.value    Selected day values.
 * @param {Function} props.onChange Called with the new array.
 * @return {Element} The chips.
 */
export function WeekdayChips( { label, days = [], value = [], onChange } ) {
	return (
		<div role="group" aria-label={ label } className="sd-weekdays">
			{ days.map( ( day ) => {
				const on = value.includes( day.value );
				return (
					<button
						key={ day.value }
						type="button"
						className="sd-weekdays__day"
						aria-pressed={ on }
						aria-label={ day.name }
						onClick={ () =>
							onChange(
								on
									? value.filter(
											( item ) => item !== day.value
										)
									: [ ...value, day.value ]
							)
						}
					>
						{ day.label }
					</button>
				);
			} ) }
		</div>
	);
}

/**
 * A key and value pair, with a remove button ("Custom field").
 *
 * @param {Object}   props             Props.
 * @param {string}   props.keyLabel    Accessible name of the key input.
 * @param {string}   props.valueLabel  Accessible name of the value input.
 * @param {string}   props.keyValue    Key.
 * @param {string}   props.value       Value.
 * @param {Function} props.onChange    Called with { key, value }.
 * @param {Function} props.onRemove    Removes the pair; omit to hide.
 * @param {string}   props.removeLabel Accessible name of the remove button.
 * @param {Element}  props.operator    Optional control between the two.
 * @param {boolean}  props.hideValue   No value input (exists, not exists).
 * @return {Element} The pair.
 */
export function KeyValueField( {
	keyLabel,
	valueLabel,
	keyValue = '',
	value = '',
	onChange,
	onRemove,
	removeLabel = __( 'Remove rule', 'scriptdock' ),
	operator,
	hideValue = false,
} ) {
	return (
		<div className="sd-keyvalue">
			<input
				type="text"
				className="sd-input sd-input--mono sd-keyvalue__key"
				aria-label={ keyLabel }
				value={ keyValue }
				onChange={ ( event ) =>
					onChange( { key: event.target.value, value } )
				}
			/>
			{ operator && (
				<span className="sd-keyvalue__operator">{ operator }</span>
			) }
			{ ! hideValue && (
				<input
					type="text"
					className="sd-input sd-input--mono sd-keyvalue__value"
					aria-label={ valueLabel }
					value={ value }
					onChange={ ( event ) =>
						onChange( { key: keyValue, value: event.target.value } )
					}
				/>
			) }
			{ onRemove && (
				<button
					type="button"
					className="sd-keyvalue__remove"
					aria-label={ removeLabel }
					onClick={ onRemove }
				>
					<Icon name="close" size={ 15 } stroke={ 2 } />
				</button>
			) }
		</div>
	);
}

/**
 * From and to times (24-hour HH:MM), with a note such as the site timezone.
 *
 * @param {Object}   props          Props.
 * @param {string}   props.label    Label.
 * @param {string}   props.from     Start, HH:MM.
 * @param {string}   props.to       End, HH:MM.
 * @param {Function} props.onChange Called with { from, to }.
 * @param {string}   props.note     Note under the inputs.
 * @return {Element} The field.
 */
export function TimeRange( { label, from = '', to = '', onChange, note } ) {
	const id = useInstanceId( TimeRange, 'sd-time' );
	return (
		<div
			className="sd-field"
			role="group"
			aria-labelledby={ `${ id }-label` }
		>
			<span id={ `${ id }-label` } className="sd-field__label">
				{ label }
			</span>
			<div className="sd-timerange">
				<input
					type="text"
					inputMode="numeric"
					pattern="[0-2][0-9]:[0-5][0-9]"
					placeholder="09:00"
					className="sd-input"
					aria-label={ __( 'From', 'scriptdock' ) }
					value={ from }
					onChange={ ( event ) =>
						onChange( { from: event.target.value, to } )
					}
				/>
				<span className="sd-timerange__to">
					{ __( 'to', 'scriptdock' ) }
				</span>
				<input
					type="text"
					inputMode="numeric"
					pattern="[0-2][0-9]:[0-5][0-9]"
					placeholder="17:00"
					className="sd-input"
					aria-label={ __( 'To', 'scriptdock' ) }
					value={ to }
					onChange={ ( event ) =>
						onChange( { from, to: event.target.value } )
					}
				/>
			</div>
			{ note && <span className="sd-field__help">{ note }</span> }
		</div>
	);
}

/**
 * Drop a file here, or browse. Shows the chosen file with its size and a
 * summary line once read.
 *
 * @param {Object}   props           Props.
 * @param {string}   props.label     Accessible name of the file input.
 * @param {string}   props.accept    Accepted types (".json").
 * @param {string}   props.title     Idle title ("Drop a .json export here").
 * @param {string}   props.dropTitle Drag-over title ("Release to import").
 * @param {File}     props.file      The chosen file.
 * @param {string}   props.meta      Summary line for the file.
 * @param {Function} props.onFile    Called with the dropped or chosen File.
 * @param {Function} props.onRemove  Clears the file.
 * @return {Element} The dropzone or the chosen file.
 */
export function FileDropzone( {
	label,
	accept,
	title,
	dropTitle = __( 'Release to import', 'scriptdock' ),
	file,
	meta,
	onFile,
	onRemove,
} ) {
	const [ dragging, setDragging ] = useState( false );
	const input = useRef();

	if ( file ) {
		return (
			<div className="sd-file">
				<span className="sd-file__icon" aria-hidden="true">
					<Icon name="file" size={ 17 } stroke={ 1.6 } />
				</span>
				<span className="sd-file__text">
					<span className="sd-file__name">{ file.name }</span>
					{ meta && <span className="sd-file__meta">{ meta }</span> }
				</span>
				<IconButton
					icon="close"
					label={ __( 'Remove file', 'scriptdock' ) }
					variant="soft"
					size="sm"
					iconSize={ 14 }
					stroke={ 2 }
					onClick={ onRemove }
				/>
			</div>
		);
	}

	return (
		<div
			className={ cx( 'sd-dropzone', dragging && 'is-dragging' ) }
			onDragOver={ ( event ) => {
				event.preventDefault();
				setDragging( true );
			} }
			onDragLeave={ () => setDragging( false ) }
			onDrop={ ( event ) => {
				event.preventDefault();
				setDragging( false );
				const dropped =
					event.dataTransfer.files && event.dataTransfer.files[ 0 ];
				if ( dropped ) {
					onFile( dropped );
				}
			} }
		>
			<span className="sd-dropzone__title">
				{ dragging ? dropTitle : title }
			</span>
			{ ! dragging && (
				<span className="sd-dropzone__sub">
					{ __( 'or', 'scriptdock' ) }{ ' ' }
					<button
						type="button"
						className="sd-dropzone__browse"
						onClick={ () => input.current.click() }
					>
						{ __( 'browse your computer', 'scriptdock' ) }
					</button>
				</span>
			) }
			<input
				ref={ input }
				type="file"
				accept={ accept }
				aria-label={ label }
				className="sd-visually-hidden"
				tabIndex={ -1 }
				onChange={ ( event ) => {
					if ( event.target.files[ 0 ] ) {
						onFile( event.target.files[ 0 ] );
					}
				} }
			/>
		</div>
	);
}
