/**
 * Navigation: sd-Tabs (underline and vertical), sd-Pills, sd-Stepper and
 * sd-Breadcrumb.
 */
import { useInstanceId } from '@wordpress/compose';
import { __, sprintf } from '@wordpress/i18n';
import Icon from './icon';
import { ProgressBar } from './display';
import { cx } from './utils';

/**
 * Tabs with arrow-key navigation. Render the panels yourself with
 * <TabPanel>, or pass `children` as a function of the selected id.
 *
 * @param {Object}   props           Props.
 * @param {string}   props.label     Accessible name of the tab list.
 * @param {Array}    props.tabs      { id, label, count, filled, dot,
 *                                   disabled }.
 * @param {string}   props.selected  Selected tab id.
 * @param {Function} props.onSelect  Called with a tab id.
 * @param {string}   props.variant   underline or vertical.
 * @param {string}   props.idPrefix  Prefix for tab and panel ids.
 * @param {string}   props.className Extra classes.
 * @return {Element} The tab list.
 */
export function Tabs( {
	label,
	tabs = [],
	selected,
	onSelect,
	variant = 'underline',
	idPrefix,
	className,
} ) {
	const instance = useInstanceId( Tabs, 'sd-tabs' );
	const prefix = idPrefix || instance;
	const vertical = variant === 'vertical';
	const enabled = tabs.filter( ( tab ) => ! tab.disabled );
	// The selected tab takes Tab focus; the first enabled one if none is.
	const focusable = enabled.some( ( tab ) => tab.id === selected )
		? selected
		: enabled[ 0 ]?.id;

	const onKeyDown = ( event, current ) => {
		const index = enabled.findIndex( ( tab ) => tab.id === current );
		const rtl =
			window.getComputedStyle( event.currentTarget ).direction === 'rtl';
		const nextKeys = vertical
			? [ 'ArrowDown' ]
			: [ rtl ? 'ArrowLeft' : 'ArrowRight' ];
		const prevKeys = vertical
			? [ 'ArrowUp' ]
			: [ rtl ? 'ArrowRight' : 'ArrowLeft' ];
		let next;
		if ( nextKeys.includes( event.key ) ) {
			next = enabled[ ( index + 1 ) % enabled.length ];
		} else if ( prevKeys.includes( event.key ) ) {
			next = enabled[ index <= 0 ? enabled.length - 1 : index - 1 ];
		} else if ( event.key === 'Home' ) {
			next = enabled[ 0 ];
		} else if ( event.key === 'End' ) {
			next = enabled[ enabled.length - 1 ];
		}
		if ( next ) {
			event.preventDefault();
			onSelect( next.id );
			const button = document.getElementById(
				`${ prefix }-tab-${ next.id }`
			);
			if ( button ) {
				button.focus();
			}
		}
	};

	return (
		<div
			role="tablist"
			aria-label={ label }
			aria-orientation={ vertical ? 'vertical' : undefined }
			className={ cx( vertical ? 'sd-vtabs' : 'sd-tabs', className ) }
		>
			{ tabs.map( ( tab ) => {
				const isSelected = tab.id === selected;
				return (
					<button
						key={ tab.id }
						id={ `${ prefix }-tab-${ tab.id }` }
						type="button"
						role="tab"
						aria-selected={ isSelected }
						aria-controls={ `${ prefix }-panel-${ tab.id }` }
						aria-disabled={ tab.disabled || undefined }
						tabIndex={ tab.id === focusable ? 0 : -1 }
						className={
							vertical ? 'sd-vtabs__tab' : 'sd-tabs__tab'
						}
						onClick={ () => ! tab.disabled && onSelect( tab.id ) }
						onKeyDown={ ( event ) => onKeyDown( event, tab.id ) }
					>
						{ tab.label }
						{ tab.count !== undefined && tab.count !== null && (
							<span
								className={ cx(
									'sd-tabs__count',
									tab.filled && 'is-filled'
								) }
							>
								{ tab.count }
							</span>
						) }
						{ tab.dot && (
							<span
								className={ cx(
									'sd-vtabs__dot',
									tab.dot === 'warning' &&
										'sd-vtabs__dot--warning'
								) }
							>
								<span className="sd-visually-hidden">
									{ tab.dot === 'warning'
										? __( '(needs a look)', 'scriptdock' )
										: __( '(has code)', 'scriptdock' ) }
								</span>
							</span>
						) }
					</button>
				);
			} ) }
		</div>
	);
}

/**
 * The panel for one tab.
 *
 * @param {Object}  props           Props.
 * @param {string}  props.idPrefix  The prefix passed to Tabs.
 * @param {string}  props.id        Tab id.
 * @param {boolean} props.hidden    Not the selected tab.
 * @param {string}  props.className Extra classes.
 * @param {Element} props.children  Content.
 * @return {Element} The panel.
 */
export function TabPanel( {
	idPrefix,
	id,
	hidden = false,
	className,
	children,
} ) {
	return (
		<div
			id={ `${ idPrefix }-panel-${ id }` }
			role="tabpanel"
			aria-labelledby={ `${ idPrefix }-tab-${ id }` }
			hidden={ hidden }
			tabIndex={ 0 }
			className={ className }
		>
			{ children }
		</div>
	);
}

/**
 * Status filter pills above a list. Links when items have `href`; with
 * `onSelect` too, a plain click switches in place and a modified click
 * (new tab, new window) still follows the link.
 *
 * @param {Object}   props          Props.
 * @param {string}   props.label    Accessible name.
 * @param {Array}    props.items    { id, label, count, tone, href }. Tone:
 *                                  danger (filled, with a dot) or error
 *                                  (outlined).
 * @param {string}   props.current  Current id.
 * @param {Function} props.onSelect Called with an id.
 * @return {Element} The pills.
 */
export function Pills( { label, items = [], current, onSelect } ) {
	const follow = ( event, id ) => {
		if (
			! onSelect ||
			event.button !== 0 ||
			event.metaKey ||
			event.ctrlKey ||
			event.shiftKey ||
			event.altKey
		) {
			return;
		}
		event.preventDefault();
		onSelect( id );
	};
	return (
		<nav aria-label={ label }>
			<ul className="sd-pills">
				{ items.map( ( item ) => {
					const isCurrent = item.id === current;
					const classes = cx(
						'sd-pills__pill',
						item.tone === 'danger' && 'sd-pills__pill--danger',
						item.tone === 'error' && 'sd-pills__pill--error',
						isCurrent && 'is-current'
					);
					const content = (
						<>
							{ item.label }
							{ item.count !== undefined && (
								<span className="sd-pills__count">
									{ item.count }
								</span>
							) }
						</>
					);
					return (
						<li key={ item.id }>
							{ item.href ? (
								<a
									className={ classes }
									href={ item.href }
									aria-current={
										isCurrent ? 'page' : undefined
									}
									onClick={ ( event ) =>
										follow( event, item.id )
									}
								>
									{ content }
								</a>
							) : (
								<button
									type="button"
									className={ classes }
									aria-pressed={ isCurrent }
									onClick={ () => onSelect( item.id ) }
								>
									{ content }
								</button>
							) }
						</li>
					);
				} ) }
			</ul>
		</nav>
	);
}

/**
 * What a step's circle shows: a check when done, "!" on error, else its
 * number.
 *
 * @param {Object} step  The step.
 * @param {number} index Its position, from 0.
 * @return {Element|string|number} The marker content.
 */
function stepMarker( step, index ) {
	if ( step.state === 'complete' ) {
		return <Icon name="check" size={ 15 } stroke={ 3 } />;
	}
	if ( step.state === 'error' ) {
		return '!';
	}
	return index + 1;
}

/**
 * Wizard steps: complete, current, upcoming and error.
 *
 * @param {Object}   props        Props.
 * @param {string}   props.label  Accessible name.
 * @param {Array}    props.steps  { id, label, note, state }. State:
 *                                complete, current, upcoming or error.
 * @param {Function} props.onStep Called with a step id; makes completed
 *                                steps buttons.
 * @return {Element} The stepper.
 */
export function Stepper( { label, steps = [], onStep } ) {
	return (
		<ol className="sd-stepper" aria-label={ label }>
			{ steps.map( ( step, index ) => {
				const clickable = onStep && step.state !== 'current';
				const Main = clickable ? 'button' : 'span';
				return [
					index > 0 && (
						<li
							key={ `connector-${ step.id }` }
							className={ cx(
								'sd-stepper__connector',
								steps[ index - 1 ].state === 'complete' &&
									'is-done'
							) }
							aria-hidden="true"
						/>
					),
					<li
						key={ step.id }
						className={ cx(
							'sd-stepper__step',
							`is-${ step.state }`
						) }
					>
						<Main
							className="sd-stepper__main"
							aria-current={
								step.state === 'current' ? 'step' : undefined
							}
							{ ...( clickable
								? {
										type: 'button',
										onClick: () => onStep( step.id ),
									}
								: {} ) }
						>
							<span
								className="sd-stepper__marker"
								aria-hidden="true"
							>
								{ stepMarker( step, index ) }
							</span>
							{ step.label }
							{ step.state === 'complete' && (
								<span className="sd-visually-hidden">
									{ __( '(done)', 'scriptdock' ) }
								</span>
							) }
						</Main>
						{ step.note && (
							<span className="sd-stepper__note">
								{ step.note }
							</span>
						) }
					</li>,
				];
			} ) }
		</ol>
	);
}

/**
 * The compact stepper on phones: "Step 2 of 5" and a bar.
 *
 * @param {Object} props       Props.
 * @param {number} props.index Current step, from 1.
 * @param {number} props.total Number of steps.
 * @param {string} props.name  Current step name.
 * @return {Element} The compact stepper.
 */
export function StepperCompact( { index, total, name } ) {
	const count = sprintf(
		/* translators: 1: current step, 2: number of steps. */
		__( 'Step %1$d of %2$d', 'scriptdock' ),
		index,
		total
	);
	return (
		<div className="sd-stepper-compact">
			<div className="sd-stepper-compact__head">
				<span className="sd-stepper-compact__count">{ count }</span>
				<span className="sd-stepper-compact__name">{ name }</span>
			</div>
			<ProgressBar
				value={ index }
				max={ total }
				tone="ink"
				count={ count }
			/>
		</div>
	);
}

/**
 * A breadcrumb: "Snippets / GA4 tag". Mirrors for RTL.
 *
 * @param {Object} props       Props.
 * @param {Array}  props.items { label, href }; the last is the current page.
 * @return {Element} The breadcrumb.
 */
export function Breadcrumb( { items = [] } ) {
	return (
		<nav aria-label={ __( 'Breadcrumb', 'scriptdock' ) }>
			<ol className="sd-breadcrumb">
				{ items.map( ( item, index ) => {
					const last = index === items.length - 1;
					return (
						<li className="sd-breadcrumb__item" key={ item.label }>
							{ last ? (
								<span
									className="sd-breadcrumb__current"
									aria-current="page"
								>
									{ item.label }
								</span>
							) : (
								<a href={ item.href }>{ item.label }</a>
							) }
						</li>
					);
				} ) }
			</ol>
		</nav>
	);
}
