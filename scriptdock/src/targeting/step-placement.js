/**
 * Step 1: where in the page the code goes.
 *
 * Each card draws a small page with an ember dot where the code lands, so the
 * choice can be read at a glance rather than from the hook name.
 */
import { __, _x, sprintf } from '@wordpress/i18n';
import { CopyChip, NumberStepper, TextField, cx } from '../components';
import { CardGrid, SelectCard } from './cards';

// Where the dot sits in the little page drawing, per placement.
const SPOTS = {
	site_header: 'head',
	admin_header: 'head',
	login_header: 'head',
	site_body_open: 'top',
	before_content: 'top',
	after_content: 'bottom',
	before_paragraph: 'middle',
	after_paragraph: 'middle',
	before_excerpt: 'middle',
	after_excerpt: 'middle',
	between_posts: 'list',
	site_footer: 'foot',
	admin_footer: 'foot',
	login_footer: 'foot',
	shortcode: 'free',
	custom_hook: 'free',
	block_editor: 'free',
	on_demand: 'free',
	php_everywhere: 'all',
	php_frontend: 'all',
	php_admin: 'all',
};

/**
 * @param {Object}   props           Props.
 * @param {Object}   props.draft     Draft.
 * @param {Function} props.patch     Changes the draft.
 * @param {Array}    props.locations Placements.
 * @param {string}   props.type      Code type.
 * @return {Element} The step.
 */
export default function PlacementStep( { draft, patch, locations, type } ) {
	const groups = [];
	locations.forEach( ( place ) => {
		const group = groups.find( ( entry ) => entry.key === place.groupKey );
		(
			group ||
			groups[
				groups.push( {
					key: place.groupKey,
					label: place.group,
					places: [],
				} ) - 1
			]
		).places.push( place );
	} );
	// The PHP places lead for a PHP snippet, and follow for the rest, where
	// they are all out of reach anyway.
	if ( type !== 'php' ) {
		const php = groups.findIndex( ( group ) => group.key === 'php' );
		if ( php > -1 ) {
			groups.push( groups.splice( php, 1 )[ 0 ] );
		}
	}

	return (
		<div className="sd-step sd-step--placement">
			{ groups.map( ( group ) => (
				<section key={ group.key } className="sd-step__section">
					<h3 className="sd-step__heading">{ group.label }</h3>
					<CardGrid label={ group.label }>
						{ group.places.map( ( place ) => {
							const selected = draft.location === place.value;
							const reason = place.types.includes( type )
								? ''
								: unavailable( type, place );
							return (
								<SelectCard
									key={ place.value }
									title={ cardTitle(
										place,
										selected,
										draft
									) }
									note={ place.description }
									art={
										<Wireframe
											spot={ SPOTS[ place.value ] }
										/>
									}
									selected={ selected }
									reason={ reason }
									tabIndex={ tabStop( draft, group, place ) }
									onSelect={ () =>
										patch( {
											location: place.value,
											locationArgs: {},
										} )
									}
								>
									<Extras
										place={ place }
										draft={ draft }
										patch={ patch }
									/>
								</SelectCard>
							);
						} ) }
					</CardGrid>
				</section>
			) ) }
		</div>
	);
}

/**
 * A placement's name. The two paragraph places carry a "#" that stands for
 * the paragraph number, the way the rest of the plugin fills it in.
 *
 * @param {Object}  place    Placement.
 * @param {boolean} selected Whether it is the chosen one.
 * @param {Object}  draft    Draft.
 * @return {string} The title.
 */
function cardTitle( place, selected, draft ) {
	if ( ! place.label.includes( '#' ) ) {
		return place.label;
	}
	const number = selected
		? String( Number( draft.locationArgs?.paragraph ) || 2 )
		: _x( 'N', 'stands for a paragraph number', 'scriptdock' );
	return place.label.replace( '#', number );
}

/**
 * Only one card in each group is a tab stop: arrow keys reach the rest.
 *
 * @param {Object} draft Draft.
 * @param {Object} group Group.
 * @param {Object} place Placement.
 * @return {number} 0 or -1.
 */
function tabStop( draft, group, place ) {
	const chosen = group.places.find(
		( entry ) => entry.value === draft.location
	);
	const first = chosen || group.places[ 0 ];
	return first.value === place.value ? 0 : -1;
}

/**
 * The settings that belong to one placement, shown inside its card once it is
 * chosen: the paragraph number, how often between posts, the hook name, the
 * shortcode to copy, and the priority every placement has.
 *
 * @param {Object}   props       Props.
 * @param {Object}   props.place Placement.
 * @param {Object}   props.draft Draft.
 * @param {Function} props.patch Changes the draft.
 * @return {Element} The extras.
 */
function Extras( { place, draft, patch } ) {
	const args = draft.locationArgs || {};
	const setArg = ( key, value ) =>
		patch( { locationArgs: { ...args, [ key ]: value } } );

	return (
		<>
			{ place.value === 'before_paragraph' ||
			place.value === 'after_paragraph' ? (
				<NumberStepper
					label={ __( 'Paragraph', 'scriptdock' ) }
					value={ Number( args.paragraph ) || 2 }
					min={ 1 }
					max={ 50 }
					onChange={ ( value ) => setArg( 'paragraph', value ) }
				/>
			) : null }

			{ place.value === 'between_posts' && (
				<NumberStepper
					label={ __( 'Every N posts', 'scriptdock' ) }
					value={ Number( args.every ) || 3 }
					min={ 1 }
					max={ 20 }
					onChange={ ( value ) => setArg( 'every', value ) }
				/>
			) }

			{ place.value === 'custom_hook' && (
				<TextField
					label={ __( 'Hook name', 'scriptdock' ) }
					value={ args.hook || '' }
					placeholder="woocommerce_after_cart"
					onChange={ ( value ) => setArg( 'hook', value ) }
				/>
			) }

			{ place.value === 'shortcode' && draft.id !== 0 && (
				<p className="sd-step__hint">
					{ __(
						'You place this one by hand. The shortcode appears once the snippet is saved.',
						'scriptdock'
					) }
				</p>
			) }

			{ draft.shortcode && place.value === 'shortcode' && (
				<CopyChip value={ draft.shortcode } />
			) }

			<NumberStepper
				label={ __( 'Priority', 'scriptdock' ) }
				help={ __( 'Lower runs first', 'scriptdock' ) }
				value={ draft.priority }
				min={ -9999 }
				max={ 9999 }
				onChange={ ( value ) => patch( { priority: value } ) }
			/>
		</>
	);
}

/**
 * Why a placement is not available for this code type.
 *
 * @param {string} type  Code type.
 * @param {Object} place Placement.
 * @return {string} The reason.
 */
function unavailable( type, place ) {
	const labels = {
		php: 'PHP',
		html: 'HTML',
		css: 'CSS',
		js: 'JavaScript',
		universal: __( 'Universal', 'scriptdock' ),
	};
	if ( place.value.startsWith( 'php_' ) || place.value === 'on_demand' ) {
		return __(
			'This place runs PHP. Change the code type to PHP to use it.',
			'scriptdock'
		);
	}
	if ( place.value === 'block_editor' ) {
		return __(
			'The block editor canvas only takes CSS and JavaScript.',
			'scriptdock'
		);
	}
	if ( type === 'css' ) {
		return __(
			'CSS is added as a stylesheet in the head, so it cannot sit inside a page.',
			'scriptdock'
		);
	}
	if ( type === 'js' ) {
		return __(
			'JavaScript is added as a script, so it cannot sit inside the text of a post.',
			'scriptdock'
		);
	}
	return sprintf(
		/* translators: %s: code type, for example "HTML". */
		__( '%s cannot be used here.', 'scriptdock' ),
		labels[ type ] || type
	);
}

/**
 * The little page with a dot where the code goes.
 *
 * @param {Object} props      Props.
 * @param {string} props.spot Which part of the page.
 * @return {Element} The drawing.
 */
function Wireframe( { spot = 'free' } ) {
	return (
		<span className={ cx( 'sd-wire', `sd-wire--${ spot }` ) }>
			<span className="sd-wire__bar" />
			<span className="sd-wire__body">
				<span className="sd-wire__line" />
				<span className="sd-wire__line" />
				<span className="sd-wire__line sd-wire__line--short" />
			</span>
			<span className="sd-wire__foot" />
			<span className="sd-wire__dot" />
		</span>
	);
}
