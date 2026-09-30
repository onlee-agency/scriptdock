/**
 * Surfaces: sd-Card, sd-SaveBar, sd-SettingsRow, sd-PluginSourceRow and
 * sd-TemplateCard.
 */
import { useInstanceId } from '@wordpress/compose';
import { __ } from '@wordpress/i18n';
import Button from './button';
import Icon from './icon';
import { cx } from './utils';

/**
 * A card with an optional title row.
 *
 * @param {Object}  props           Props.
 * @param {string}  props.anchor    Element id, for links to the section.
 * @param {string}  props.title     Title.
 * @param {string}  props.subtitle  Line after the title.
 * @param {Element} props.action    Link or button at the end of the title
 *                                  row.
 * @param {boolean} props.flush     No padding (tables, editors).
 * @param {string}  props.as        Element: section, div or article.
 * @param {string}  props.className Extra classes.
 * @param {Element} props.children  Content.
 * @return {Element} The card.
 */
export function Card( {
	anchor,
	title,
	subtitle,
	action,
	flush = false,
	as: Tag = 'section',
	className,
	children,
} ) {
	const id = useInstanceId( Card, 'sd-card' );
	return (
		<Tag
			id={ anchor }
			className={ cx( 'sd-card', flush && 'sd-card--flush', className ) }
			aria-labelledby={ title ? `${ id }-title` : undefined }
		>
			{ ( title || action ) && (
				<div className="sd-card__header">
					{ title && (
						<h2 id={ `${ id }-title` } className="sd-card__title">
							{ title }
						</h2>
					) }
					{ subtitle && (
						<span className="sd-card__subtitle">{ subtitle }</span>
					) }
					{ action && (
						<span className="sd-card__action">{ action }</span>
					) }
				</div>
			) }
			{ children }
		</Tag>
	);
}

/**
 * The sticky bar that appears when something changed.
 *
 * @param {Object}   props           Props.
 * @param {string}   props.label     "Unsaved changes in Header".
 * @param {Function} props.onDiscard Discards.
 * @param {Function} props.onSave    Saves.
 * @param {boolean}  props.saving    Saving.
 * @param {string}   props.saveLabel Save button label.
 * @return {Element} The bar.
 */
export function SaveBar( {
	label = __( 'Unsaved changes', 'scriptdock' ),
	onDiscard,
	onSave,
	saving = false,
	saveLabel = __( 'Save changes', 'scriptdock' ),
} ) {
	return (
		<div
			className="sd-savebar"
			role="region"
			aria-label={ __( 'Save', 'scriptdock' ) }
		>
			<span className="sd-savebar__label">{ label }</span>
			<div className="sd-savebar__actions">
				<Button
					variant="on-ink"
					onClick={ onDiscard }
					disabled={ saving }
				>
					{ __( 'Discard', 'scriptdock' ) }
				</Button>
				<Button
					variant="inverse"
					dot="check"
					onClick={ onSave }
					loading={ saving }
					loadingLabel={ __( 'Saving…', 'scriptdock' ) }
				>
					{ saveLabel }
				</Button>
			</div>
		</div>
	);
}

/**
 * A setting: title, what it does, and its control at the end.
 *
 * @param {Object}  props             Props.
 * @param {string}  props.title       Title.
 * @param {Element} props.description What happens.
 * @param {Element} props.control     Switch or other control.
 * @param {string}  props.id          Id for the title (labels the control).
 * @param {Element} props.children    The control, when it is not passed as
 *                                    `control`.
 * @return {Element} The row.
 */
export function SettingsRow( { title, description, control, children, id } ) {
	return (
		<div className="sd-setting">
			<div className="sd-setting__text">
				<span id={ id } className="sd-setting__title">
					{ title }
				</span>
				{ description && (
					<span className="sd-setting__desc">{ description }</span>
				) }
			</div>
			{ ( control || children ) && (
				<div className="sd-setting__control">
					{ control || children }
				</div>
			) }
		</div>
	);
}

/**
 * A plugin to import from: name, what was found, and actions.
 *
 * @param {Object}  props         Props.
 * @param {string}  props.icon    Icon name.
 * @param {string}  props.name    Plugin name.
 * @param {Element} props.badge   Badge after the name ("Still active").
 * @param {string}  props.meta    "12 snippets found · 9 would be active".
 * @param {Element} props.actions Keep-active switch and Import button.
 * @return {Element} The row.
 */
export function PluginSourceRow( {
	icon = 'download',
	name,
	badge,
	meta,
	actions,
} ) {
	return (
		<div className="sd-source">
			<span className="sd-source__icon" aria-hidden="true">
				<Icon name={ icon } size={ 20 } stroke={ 1.5 } />
			</span>
			<span className="sd-source__text">
				<span className="sd-source__name">
					{ name }
					{ badge }
				</span>
				{ meta && <span className="sd-source__meta">{ meta }</span> }
			</span>
			{ actions && <div className="sd-source__actions">{ actions }</div> }
		</div>
	);
}

/**
 * A library template: default, added, or blocked (needs a plugin).
 *
 * @param {Object}  props              Props.
 * @param {string}  props.title        Title.
 * @param {string}  props.description  One line.
 * @param {Element} props.art          Illustration (48px).
 * @param {Element} props.chips        Type and meta chips.
 * @param {string}  props.state        default, added or blocked.
 * @param {string}  props.blockedLabel "Requires WooCommerce".
 * @param {string}  props.openHref     "Open snippet" link when added.
 * @param {Element} props.children     Fields and the Add button.
 * @return {Element} The card.
 */
export function TemplateCard( {
	title,
	description,
	art,
	chips,
	state = 'default',
	blockedLabel,
	openHref,
	children,
} ) {
	return (
		<article
			className={ cx(
				'sd-template',
				state === 'added' && 'is-added',
				state === 'blocked' && 'is-blocked'
			) }
		>
			{ art && (
				<div className="sd-template__art" aria-hidden="true">
					{ art }
				</div>
			) }
			<div className="sd-template__text">
				<h3 className="sd-template__title">{ title }</h3>
				<p className="sd-template__desc">{ description }</p>
			</div>
			{ chips && state !== 'blocked' && (
				<div className="sd-template__meta">{ chips }</div>
			) }
			{ state === 'added' && (
				<div className="sd-template__footer">
					<span className="sd-template__added">
						<Icon name="check" size={ 13 } stroke={ 3 } />
						{ __( 'Added', 'scriptdock' ) }
					</span>
					{ openHref && (
						<a href={ openHref }>
							{ __( 'Open snippet', 'scriptdock' ) }
							<span className="sd-visually-hidden">
								{ ' ' + title }
							</span>
						</a>
					) }
				</div>
			) }
			{ state === 'blocked' && (
				<span className="sd-template__blocked">
					<Icon name="lock" size={ 13 } stroke={ 2 } />
					{ blockedLabel }
				</span>
			) }
			{ state === 'default' && children }
		</article>
	);
}
