/**
 * The code type: five pills with their type colour, and a line about the
 * chosen one. Types the viewer may not use stay listed, locked, with the
 * reason.
 */
import { useRef } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Icon, Tooltip, cx } from '../components';

/**
 * What each type does, in the editor's words.
 *
 * @return {Object} Type => sentence.
 */
function hints() {
	return {
		php: __(
			'PHP runs on the server. ScriptDock tests it once before it goes live, and switches it off if it crashes.',
			'scriptdock'
		),
		html: __(
			'HTML goes into the page as it is, including <script> and <style> tags. Ideal for tracking codes.',
			'scriptdock'
		),
		css: __(
			'CSS styles your site. It is wrapped in a <style> tag, or loaded as a cached file.',
			'scriptdock'
		),
		js: __(
			'JavaScript runs in the browser. It is wrapped in a <script> tag, or loaded as a cached file.',
			'scriptdock'
		),
		universal: __(
			'HTML with <?php ?> tags in it, like a theme template. The PHP runs on the server.',
			'scriptdock'
		),
	};
}

/**
 * @param {Object}   props          Props.
 * @param {Array}    props.types    { value, label, allowed }.
 * @param {string}   props.value    Chosen type.
 * @param {Function} props.onChange Called with the new type.
 * @param {boolean}  props.readOnly Nothing can change.
 * @param {string}   props.reason   Why PHP types are locked.
 * @return {Element} The picker.
 */
export default function TypePicker( {
	types,
	value,
	onChange,
	readOnly,
	reason,
} ) {
	const group = useRef();
	const usable = types.filter( ( type ) => type.allowed && ! readOnly );

	// Arrow keys move and choose, as in any radio group.
	const onKeyDown = ( event ) => {
		const keys = {
			ArrowRight: 1,
			ArrowDown: 1,
			ArrowLeft: -1,
			ArrowUp: -1,
		};
		if ( ! ( event.key in keys ) || ! usable.length ) {
			return;
		}
		event.preventDefault();
		const rtl =
			window.getComputedStyle( group.current ).direction === 'rtl';
		const sideways =
			event.key === 'ArrowLeft' || event.key === 'ArrowRight';
		const step = rtl && sideways ? -keys[ event.key ] : keys[ event.key ];
		const index = usable.findIndex( ( type ) => type.value === value );
		const next = usable[ ( index + step + usable.length ) % usable.length ];
		onChange( next.value );
		group.current.querySelector( `[data-type="${ next.value }"]` )?.focus();
	};

	return (
		<section
			className="sd-card sd-typepicker"
			aria-labelledby="sd-type-label"
		>
			<h2 id="sd-type-label" className="sd-overline">
				{ __( 'Code type', 'scriptdock' ) }
			</h2>
			<div
				ref={ group }
				className="sd-typepicker__options"
				role="radiogroup"
				aria-labelledby="sd-type-label"
				aria-describedby="sd-type-hint"
			>
				{ types.map( ( type ) => {
					const checked = type.value === value;
					const locked = ! type.allowed;
					const option = (
						<button
							key={ type.value }
							type="button"
							role="radio"
							data-type={ type.value }
							aria-checked={ checked }
							aria-disabled={ locked || readOnly || undefined }
							tabIndex={ checked ? 0 : -1 }
							className={ cx(
								'sd-typepicker__option',
								`sd-typepicker__option--${ type.value }`,
								checked && 'is-checked',
								locked && 'is-locked'
							) }
							onClick={ () =>
								! locked && ! readOnly && onChange( type.value )
							}
							onKeyDown={ onKeyDown }
						>
							<Icon
								name={
									locked ? 'lock' : `type-${ type.value }`
								}
								size={ 15 }
								stroke={ 1.9 }
							/>
							{ type.label }
						</button>
					);
					return locked ? (
						<Tooltip key={ type.value } text={ reason }>
							{ option }
						</Tooltip>
					) : (
						option
					);
				} ) }
			</div>
			<p id="sd-type-hint" className="sd-typepicker__hint">
				{ hints()[ value ] }
			</p>
		</section>
	);
}
