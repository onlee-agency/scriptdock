/**
 * Data display: sd-TypeChip, sd-Badge, sd-StatusDot, sd-StatTile,
 * sd-Avatar, sd-Tooltip, sd-KeyValueList, sd-TargetingSentence,
 * sd-EmptyState, sd-Skeleton, sd-ProgressBar and sd-ProgressRing.
 */
import { cloneElement, useEffect, useState } from '@wordpress/element';
import { useInstanceId } from '@wordpress/compose';
import { __, sprintf } from '@wordpress/i18n';
import Icon from './icon';
import { cx } from './utils';

export const TYPE_LABELS = {
	php: 'PHP',
	html: 'HTML',
	css: 'CSS',
	js: 'JavaScript',
	universal: __( 'Universal', 'scriptdock' ),
};

const TYPE_ICONS = {
	php: 'type-php',
	html: 'type-html',
	css: 'type-css',
	js: 'type-js',
	universal: 'type-universal',
};

/**
 * A code type, always with its label.
 *
 * @param {Object} props       Props.
 * @param {string} props.type  php, html, css, js or universal.
 * @param {string} props.size  md (28, with icon), row (26, with icon, for
 *                             table rows), sm (24) or xs (22).
 * @param {string} props.label Override ("JS" in tight spots).
 * @return {Element} The chip.
 */
export function TypeChip( { type, size = 'md', label } ) {
	return (
		<span
			className={ cx(
				'sd-type-chip',
				`sd-type-chip--${ type }`,
				size !== 'md' && `sd-type-chip--${ size }`
			) }
		>
			{ ( size === 'md' || size === 'row' ) && (
				<Icon
					name={ TYPE_ICONS[ type ] }
					size={ size === 'row' ? 13 : 15 }
					stroke={ size === 'row' ? 1.9 : 1.8 }
				/>
			) }
			{ label || TYPE_LABELS[ type ] }
		</span>
	);
}

/**
 * A badge. Status badges keep an icon.
 *
 * @param {Object}  props          Props.
 * @param {string}  props.tone     info, muted, danger, warning, success,
 *                                 ember, outline or outline-danger.
 * @param {string}  props.icon     Icon name.
 * @param {string}  props.size     md (26) or sm (24).
 * @param {string}  props.title    Tooltip text.
 * @param {Element} props.children Label.
 * @return {Element} The badge.
 */
export function Badge( { tone = 'info', icon, size = 'md', title, children } ) {
	return (
		<span
			className={ cx(
				'sd-badge',
				tone !== 'info' && `sd-badge--${ tone }`,
				size === 'sm' && 'sd-badge--sm'
			) }
			title={ title }
		>
			{ icon && <Icon name={ icon } size={ 13 } stroke={ 2 } /> }
			{ children }
		</span>
	);
}

/**
 * Running (pulsing dot), Inactive or Error, with its label.
 *
 * @param {Object}  props          Props.
 * @param {string}  props.state    running, inactive or error.
 * @param {Element} props.children Label.
 * @return {Element} The status.
 */
export function Status( { state = 'running', children } ) {
	return (
		<span className={ cx( 'sd-status', `sd-status--${ state }` ) }>
			<span className="sd-status__dot" aria-hidden="true">
				{ state === 'error' ? '!' : null }
			</span>
			{ children }
		</span>
	);
}

/**
 * A number with its label and caption. With `href` or `onClick` it filters
 * the list; the danger tone is the only one that changes colour.
 *
 * @param {Object}   props         Props.
 * @param {string}   props.label   Overline label.
 * @param {number}   props.value   The number.
 * @param {string}   props.caption Caption under it.
 * @param {string}   props.tone    default or danger.
 * @param {boolean}  props.running Pulsing dot before the label.
 * @param {string}   props.href    Link.
 * @param {Function} props.onClick Click handler.
 * @return {Element} The tile.
 */
export function StatTile( {
	label,
	value,
	caption,
	tone = 'default',
	running = false,
	href,
	onClick,
} ) {
	let Tag = 'div';
	let extra = {};
	if ( href ) {
		Tag = 'a';
		extra = { href };
	} else if ( onClick ) {
		Tag = 'button';
		extra = { type: 'button', onClick };
	}
	return (
		<Tag
			className={ cx(
				'sd-stat',
				tone === 'danger' && 'sd-stat--danger'
			) }
			{ ...extra }
		>
			<span className="sd-stat__label">
				{ running && (
					<span
						className="sd-status sd-status--running"
						aria-hidden="true"
					>
						<span className="sd-status__dot" />
					</span>
				) }
				{ tone === 'danger' && ! running && (
					<span className="sd-visually-hidden">
						{ __( 'Needs attention:', 'scriptdock' ) }
					</span>
				) }
				{ label }
			</span>
			<span className="sd-stat__value">{ value }</span>
			{ caption && <span className="sd-stat__caption">{ caption }</span> }
		</Tag>
	);
}

/**
 * Initials or a photo.
 *
 * @param {Object}  props       Props.
 * @param {string}  props.name  Person's name (initials and alt text).
 * @param {string}  props.src   Photo URL.
 * @param {string}  props.size  lg (32), md (28) or sm (24).
 * @param {boolean} props.muted Gray instead of ink.
 * @return {Element} The avatar.
 */
export function Avatar( { name = '', src, size = 'lg', muted = false } ) {
	const initials = name
		.split( /\s+/ )
		.filter( Boolean )
		.slice( 0, 2 )
		.map( ( part ) => part[ 0 ] )
		.join( '' );
	return (
		<span
			className={ cx(
				'sd-avatar',
				size !== 'lg' && `sd-avatar--${ size }`,
				muted && 'sd-avatar--muted'
			) }
			title={ name }
		>
			{ src ? (
				<img src={ src } alt="" />
			) : (
				<span aria-hidden="true">{ initials }</span>
			) }
			<span className="sd-visually-hidden">{ name }</span>
		</span>
	);
}

/**
 * A tooltip on hover and keyboard focus. The trigger must be focusable;
 * the tooltip text becomes its description. It stays while the pointer is
 * over the tooltip itself, and Escape dismisses it wherever focus is
 * (WCAG 1.4.13).
 *
 * @param {Object}  props           Props.
 * @param {string}  props.text      Tooltip text.
 * @param {string}  props.placement top or bottom.
 * @param {Element} props.children  The trigger element.
 * @return {Element} The trigger with its tooltip.
 */
export function Tooltip( { text, placement = 'top', children } ) {
	const [ visible, setVisible ] = useState( false );
	const id = useInstanceId( Tooltip, 'sd-tooltip' );
	const show = () => setVisible( true );
	const hide = () => setVisible( false );

	useEffect( () => {
		if ( ! visible ) {
			return;
		}
		const onKeyDown = ( event ) => {
			if ( event.key === 'Escape' ) {
				setVisible( false );
			}
		};
		document.addEventListener( 'keydown', onKeyDown );
		return () => document.removeEventListener( 'keydown', onKeyDown );
	}, [ visible ] );

	return (
		<span
			className="sd-tooltip-anchor"
			onPointerEnter={ show }
			onPointerLeave={ hide }
			onFocus={ show }
			onBlur={ hide }
		>
			{ cloneElement( children, { 'aria-describedby': id } ) }
			<span
				id={ id }
				role="tooltip"
				className={ cx(
					'sd-tooltip',
					placement === 'bottom' && 'sd-tooltip--below'
				) }
				hidden={ ! visible }
			>
				{ text }
			</span>
		</span>
	);
}

/**
 * "Where it runs": rows of icon, key and value.
 *
 * @param {Object}  props        Props.
 * @param {string}  props.title  Overline title.
 * @param {Array}   props.items  { icon, label, value, muted, key }.
 * @param {Element} props.action Link at the end of the title row.
 * @return {Element} The list.
 */
export function KeyValueList( { title, items = [], action } ) {
	return (
		<div className="sd-kv">
			{ ( title || action ) && (
				<div className="sd-card__header">
					{ title && <span className="sd-kv__title">{ title }</span> }
					{ action && (
						<span className="sd-kv__edit">{ action }</span>
					) }
				</div>
			) }
			<ul className="sd-kv__list">
				{ items.map( ( item ) => (
					<li className="sd-kv__row" key={ item.key || item.label }>
						{ item.icon && (
							<Icon
								name={ item.icon }
								size={ 19 }
								stroke={ 1.5 }
							/>
						) }
						<span className="sd-kv__text">
							<span className="sd-kv__key">{ item.label }</span>
							<span
								className={ cx(
									'sd-kv__value',
									item.muted && 'is-muted'
								) }
							>
								{ item.value }
							</span>
						</span>
					</li>
				) ) }
			</ul>
		</div>
	);
}

/**
 * A plain-language sentence with inline chips. A part is a string, or
 * { label, href, onClick, unset, code } for a chip. Chips never break
 * across lines; parts not chosen yet are neutral; code (a hook name) is
 * set in mono.
 *
 * @param {Object} props       Props.
 * @param {Array}  props.parts Sentence parts.
 * @param {string} props.size  md or lg.
 * @return {Element} The sentence.
 */
export function TargetingSentence( { parts = [], size = 'md' } ) {
	return (
		<p
			className={ cx(
				'sd-sentence',
				size === 'lg' && 'sd-sentence--lg'
			) }
		>
			{ parts.map( ( part, index ) => {
				if ( typeof part === 'string' ) {
					return part;
				}
				const classes = cx(
					'sd-sentence__chip',
					part.unset && 'is-unset',
					part.code && 'sd-sentence__chip--code'
				);
				const key = part.key || `${ part.label }-${ index }`;
				if ( part.href ) {
					return (
						<a key={ key } className={ classes } href={ part.href }>
							{ part.label }
						</a>
					);
				}
				if ( part.onClick ) {
					return (
						<button
							key={ key }
							type="button"
							className={ classes }
							onClick={ part.onClick }
						>
							{ part.label }
						</button>
					);
				}
				return (
					<span key={ key } className={ classes }>
						{ part.label }
					</span>
				);
			} ) }
		</p>
	);
}

/**
 * An empty state: glow, line illustration, headline, actions.
 *
 * @param {Object}  props              Props.
 * @param {string}  props.title        Headline.
 * @param {string}  props.text         One or two sentences.
 * @param {Element} props.illustration Line illustration.
 * @param {Element} props.actions      Buttons.
 * @param {boolean} props.compact      Small, centred, no border ("No
 *                                     results").
 * @param {number}  props.level        Heading level for the title (2–4).
 * @return {Element} The empty state.
 */
export function EmptyState( {
	title,
	text,
	illustration,
	actions,
	compact = false,
	level = 3,
} ) {
	const Heading = `h${ level }`;
	return (
		<div className={ cx( 'sd-empty', compact && 'sd-empty--compact' ) }>
			{ ! compact && (
				<div className="sd-empty__glow" aria-hidden="true" />
			) }
			{ illustration && (
				<div className="sd-empty__art" aria-hidden="true">
					{ illustration }
				</div>
			) }
			<div className="sd-empty__body">
				<Heading className="sd-empty__title">{ title }</Heading>
				{ text && <p className="sd-empty__text">{ text }</p> }
			</div>
			{ actions && <div className="sd-empty__actions">{ actions }</div> }
		</div>
	);
}

/**
 * A loading placeholder line.
 *
 * @param {Object}  props         Props.
 * @param {string}  props.variant line (10), text (12), title (14), pill or
 *                                box.
 * @param {string}  props.width   CSS width.
 * @param {boolean} props.shimmer Animate.
 * @return {Element} The placeholder.
 */
export function Skeleton( { variant = 'text', width, shimmer = false } ) {
	return (
		<span
			className={ cx(
				'sd-skeleton',
				variant !== 'text' && `sd-skeleton--${ variant }`,
				shimmer && 'sd-skeleton--shimmer'
			) }
			style={ width ? { inlineSize: width } : undefined }
			aria-hidden="true"
		/>
	);
}

/**
 * A loading card: a shimmering title and three lines.
 *
 * @param {Object} props       Props.
 * @param {string} props.label What is loading, for screen readers.
 * @return {Element} The card.
 */
export function SkeletonCard( { label = __( 'Loading…', 'scriptdock' ) } ) {
	return (
		<div className="sd-skeleton-card" role="status">
			<span className="sd-visually-hidden">{ label }</span>
			<Skeleton variant="title" width="40%" shimmer />
			<Skeleton variant="line" width="100%" />
			<Skeleton variant="line" width="82%" />
			<Skeleton variant="line" width="64%" />
		</div>
	);
}

/**
 * A progress bar with its label and count.
 *
 * @param {Object} props       Props.
 * @param {string} props.label Label ("Importing from WPCode").
 * @param {number} props.value Done.
 * @param {number} props.max   Total.
 * @param {string} props.count Count text ("9 of 12").
 * @param {string} props.tone  gradient or ink.
 * @return {Element} The bar.
 */
export function ProgressBar( {
	label,
	value,
	max = 100,
	count,
	tone = 'gradient',
} ) {
	const percent = max ? Math.round( ( value / max ) * 100 ) : 0;
	return (
		<div
			className={ cx(
				'sd-progress',
				tone === 'ink' && 'sd-progress--ink'
			) }
		>
			{ ( label || count ) && (
				<div className="sd-progress__head">
					<span>{ label }</span>
					{ count && (
						<span className="sd-progress__count">{ count }</span>
					) }
				</div>
			) }
			<div
				className="sd-progress__track"
				role="progressbar"
				aria-label={ label || count }
				aria-valuemin={ 0 }
				aria-valuemax={ max }
				aria-valuenow={ value }
				aria-valuetext={ count }
			>
				<span
					className="sd-progress__fill"
					style={ { inlineSize: `${ percent }%` } }
				/>
			</div>
		</div>
	);
}

/**
 * A progress ring with a count in the middle ("3/4").
 *
 * @param {Object} props       Props.
 * @param {number} props.value Done.
 * @param {number} props.max   Total.
 * @param {number} props.size  Diameter in pixels.
 * @param {string} props.label Accessible description.
 * @return {Element} The ring.
 */
export function ProgressRing( { value, max, size = 64, label } ) {
	const radius = 26;
	const circumference = 2 * Math.PI * radius;
	const offset = max ? circumference * ( 1 - value / max ) : circumference;
	const text = sprintf(
		/* translators: 1: steps done, 2: total steps. */
		__( '%1$d/%2$d', 'scriptdock' ),
		value,
		max
	);
	return (
		<svg
			className="sd-ring"
			width={ size }
			height={ size }
			viewBox="0 0 64 64"
			role="img"
			aria-label={ label || text }
		>
			<circle
				className="sd-ring__track"
				cx="32"
				cy="32"
				r={ radius }
				fill="none"
				strokeWidth="7"
			/>
			<circle
				className="sd-ring__fill"
				cx="32"
				cy="32"
				r={ radius }
				fill="none"
				strokeWidth="7"
				strokeLinecap="round"
				strokeDasharray={ circumference }
				strokeDashoffset={ offset }
				transform="rotate(-90 32 32)"
			/>
			<text className="sd-ring__label" x="32" y="37" textAnchor="middle">
				{ text }
			</text>
		</svg>
	);
}
