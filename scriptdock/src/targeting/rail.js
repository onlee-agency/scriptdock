/**
 * The wizard's summary rail: the live sentence, what has been picked, the
 * match estimate and anything worth knowing before saving.
 */
import { useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { Banner, Icon, IconButton, TargetingSentence, cx } from '../components';

/**
 * @param {Object}   props          Props.
 * @param {Array}    props.parts    Sentence parts from the REST layer.
 * @param {Array}    props.groups   Tray groups: { key, label, items[] }.
 * @param {Object}   props.estimate { count, total, visitor, exact }.
 * @param {Array}    props.warnings Strings worth knowing.
 * @param {string}   props.tip      A tip about the current step.
 * @param {Function} props.onRemove ( groupKey, itemId ) => void.
 * @param {Function} props.onClear  Clears everything picked.
 * @param {boolean}  props.busy     Whether the estimate is being worked out.
 * @return {Element} The rail.
 */
export default function Rail( {
	parts = [],
	groups = [],
	estimate,
	warnings = [],
	tip = '',
	onRemove,
	onClear,
	busy = false,
} ) {
	const picked = groups.reduce(
		( total, group ) => total + group.items.length,
		0
	);

	return (
		<aside
			className="sd-wizard__rail"
			aria-label={ __( 'Summary', 'scriptdock' ) }
		>
			<section className="sd-rail__card">
				<h3 className="sd-rail__title">
					{ __( 'In plain language', 'scriptdock' ) }
				</h3>
				<div aria-live="polite">
					<TargetingSentence parts={ parts } />
				</div>
			</section>

			<section className="sd-rail__card">
				<h3 className="sd-rail__title">
					{ __( 'Selected', 'scriptdock' ) }
					{ picked > 0 && (
						<button
							type="button"
							className="sd-rail__clear"
							onClick={ onClear }
						>
							{ __( 'Clear all', 'scriptdock' ) }
						</button>
					) }
				</h3>
				{ picked === 0 ? (
					<p className="sd-rail__empty">
						{ __(
							'Nothing narrowed yet — steps 2 to 4 are optional.',
							'scriptdock'
						) }
					</p>
				) : (
					<ul className="sd-tray">
						{ groups
							.filter( ( group ) => group.items.length )
							.map( ( group ) => (
								<TrayGroup
									key={ group.key }
									group={ group }
									onRemove={ onRemove }
								/>
							) ) }
					</ul>
				) }
			</section>

			{ estimate && (
				<section
					className={ cx( 'sd-rail__estimate', busy && 'is-busy' ) }
					aria-live="polite"
				>
					<strong className="sd-rail__count">
						{ estimate.exact ? '' : '≈ ' }
						{ estimate.count }
					</strong>
					<span className="sd-rail__countnote">
						{ estimate.exact
							? sprintf(
									/* translators: %d: how much content the site has. */
									_n(
										'page of %d will run this snippet',
										'pages of %d will run this snippet',
										estimate.count,
										'scriptdock'
									),
									estimate.total
								)
							: __( 'pages match right now', 'scriptdock' ) }
						{ estimate.visitor && (
							<span className="sd-rail__countmore">
								{ __(
									'Some rules are checked as each page loads, so the real number varies.',
									'scriptdock'
								) }
							</span>
						) }
					</span>
				</section>
			) }

			{ warnings.map( ( warning ) => (
				<Banner key={ warning } tone="warning" inline>
					{ warning }
				</Banner>
			) ) }

			{ tip && (
				<p className="sd-rail__tip">
					<Icon name="info" size={ 14 } stroke={ 1.8 } />
					{ tip }
				</p>
			) }
		</aside>
	);
}

/**
 * One group of picked things, which opens to show what is in it.
 *
 * @param {Object}   props          Props.
 * @param {Object}   props.group    { key, label, items }.
 * @param {Function} props.onRemove ( groupKey, itemId ) => void.
 * @return {Element} The group.
 */
function TrayGroup( { group, onRemove } ) {
	const [ open, setOpen ] = useState( true );
	return (
		<li className="sd-tray__group">
			<button
				type="button"
				className="sd-tray__head"
				aria-expanded={ open }
				onClick={ () => setOpen( ! open ) }
			>
				<Icon
					name={ open ? 'chevron-down' : 'chevron-right' }
					size={ 14 }
					stroke={ 2 }
				/>
				{ group.label }
				<span className="sd-tray__count">{ group.items.length }</span>
			</button>
			{ open && (
				<ul className="sd-tray__items">
					{ group.items.map( ( item ) => (
						<li key={ item.id } className="sd-tray__item">
							<span className="sd-tray__label">
								{ item.label }
								{ item.note && (
									<span className="sd-tray__note">
										{ item.note }
									</span>
								) }
							</span>
							<IconButton
								icon="close"
								size="xs"
								variant="ghost"
								label={ sprintf(
									/* translators: %s: what was picked. */
									__( 'Remove %s', 'scriptdock' ),
									item.label
								) }
								onClick={ () => onRemove( group.key, item.id ) }
							/>
						</li>
					) ) }
				</ul>
			) }
		</li>
	);
}
