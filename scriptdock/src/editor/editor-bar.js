/**
 * The editor bar, pinned under the top bar: breadcrumb and title on the
 * left; unsaved changes, the Active switch, Save and more on the right.
 * Phones get a slimmer bar at the top and Save in a bar at the bottom.
 */
import { useRef } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
	Button,
	DropdownMenu,
	Icon,
	IconButton,
	SplitButton,
	Switch,
	TYPE_LABELS,
	cx,
} from '../components';

/**
 * "Unsaved changes", with the orange dot.
 *
 * @param {Object}  props       Props.
 * @param {boolean} props.short "Unsaved" (phones).
 * @return {Element} The note.
 */
export function Unsaved( { short = false } ) {
	return (
		<span className="sd-editbar__unsaved" role="status">
			<span className="sd-editbar__dot" aria-hidden="true" />
			{ short
				? __( 'Unsaved', 'scriptdock' )
				: __( 'Unsaved changes', 'scriptdock' ) }
		</span>
	);
}

/**
 * The Active switch with its word.
 *
 * @param {Object}   props                Props.
 * @param {boolean}  props.active         On.
 * @param {Function} props.onChange       Called with the new state.
 * @param {boolean}  props.locked         Cannot be changed here.
 * @param {boolean}  props.paused         Paused for review.
 * @param {Function} props.onBlockedClick Called when locked or paused.
 * @param {boolean}  props.hideWord       Switch only (phones).
 * @return {Element} The switch.
 */
export function ActiveSwitch( {
	active,
	onChange,
	locked,
	paused,
	onBlockedClick,
	hideWord = false,
} ) {
	return (
		<span className="sd-editbar__active">
			<Switch
				checked={ active }
				onChange={ onChange }
				label={ __( 'Snippet active', 'scriptdock' ) }
				locked={ locked }
				paused={ paused }
				onBlockedClick={ onBlockedClick }
			/>
			{ ! hideWord && (
				<span className="sd-editbar__word" aria-hidden="true">
					{ active && ! paused
						? __( 'Active', 'scriptdock' )
						: __( 'Inactive', 'scriptdock' ) }
				</span>
			) }
		</span>
	);
}

/**
 * @param {Object}   props             Props.
 * @param {Object}   props.draft       Draft.
 * @param {Function} props.onTitle     Called with the new title.
 * @param {boolean}  props.isNew       Not saved yet.
 * @param {boolean}  props.dirty       Unsaved changes.
 * @param {boolean}  props.canEdit     May change it.
 * @param {Object}   props.switchProps ActiveSwitch props.
 * @param {Object}   props.save        { onSave, onSaveOff, onSaveCopy,
 *                                     disabled, saving, canSaveOff }.
 * @param {Array}    props.menuItems   The ⋯ menu.
 * @param {string}   props.listUrl     The snippets list.
 * @param {boolean}  props.compact     Tablet: fewer words.
 * @return {Element} The bar.
 */
export default function EditorBar( {
	draft,
	onTitle,
	isNew,
	dirty,
	canEdit,
	switchProps,
	save,
	menuItems,
	listUrl,
	compact,
} ) {
	const input = useRef();
	return (
		<div className="sd-editbar">
			<div className="sd-editbar__heading">
				<nav
					className="sd-editbar__crumbs"
					aria-label={ __( 'Breadcrumb', 'scriptdock' ) }
				>
					<a href={ listUrl }>{ __( 'Snippets', 'scriptdock' ) }</a>
					<span className="sd-editbar__slash" aria-hidden="true">
						/
					</span>
					<span aria-current="page">
						{ isNew
							? __( 'New', 'scriptdock' )
							: __( 'Edit', 'scriptdock' ) }
					</span>
				</nav>
				<div className="sd-editbar__title">
					<label className="sd-visually-hidden" htmlFor="sd-title">
						{ __( 'Snippet title', 'scriptdock' ) }
					</label>
					<input
						ref={ input }
						id="sd-title"
						className="sd-editbar__input"
						type="text"
						dir="auto"
						value={ draft.title }
						placeholder={ __( 'Untitled snippet', 'scriptdock' ) }
						onChange={ ( event ) => onTitle( event.target.value ) }
						readOnly={ ! canEdit }
						maxLength={ 200 }
						autoComplete="off"
						size={ Math.min(
							48,
							Math.max(
								6,
								(
									draft.title ||
									__( 'Untitled snippet', 'scriptdock' )
								).length + 1
							)
						) }
					/>
					{ canEdit && (
						<button
							type="button"
							className="sd-editbar__pencil"
							tabIndex={ -1 }
							aria-hidden="true"
							onClick={ () => input.current.focus() }
						>
							<Icon name="edit" size={ 16 } stroke={ 1.8 } />
						</button>
					) }
				</div>
			</div>
			<div className="sd-editbar__actions">
				{ dirty && ! compact && <Unsaved /> }
				<ActiveSwitch { ...switchProps } />
				{ canEdit && (
					<SplitButton
						label={ __( 'Save', 'scriptdock' ) }
						onClick={ save.onSave }
						loading={ save.saving }
						disabled={ save.disabled }
						menuDisabled={ save.saving || isNew }
						items={ [
							{
								label: __( 'Save & deactivate', 'scriptdock' ),
								onClick: save.onSaveOff,
								disabled: ! save.canSaveOff,
							},
							{
								label: __( 'Save as copy', 'scriptdock' ),
								onClick: save.onSaveCopy,
							},
						] }
					/>
				) }
				{ menuItems.length > 0 && (
					<DropdownMenu
						label={ __( 'More actions', 'scriptdock' ) }
						items={ menuItems }
						align="end"
						toggleVariant="outline"
						toggleSize="md"
					/>
				) }
			</div>
		</div>
	);
}

/**
 * The phone bar: back, title with type and placement, and the switch.
 *
 * @param {Object} props             Props.
 * @param {Object} props.draft       Draft.
 * @param {string} props.placement   Placement name.
 * @param {string} props.listUrl     The snippets list.
 * @param {Object} props.switchProps ActiveSwitch props.
 * @return {Element} The bar.
 */
export function PhoneBar( { draft, placement, listUrl, switchProps } ) {
	return (
		<div className="sd-editbar sd-editbar--phone">
			<IconButton
				icon="chevron-left"
				label={ __( 'Back to snippets', 'scriptdock' ) }
				href={ listUrl }
				size="compact"
				className="sd-flip"
			/>
			<div className="sd-editbar__phone-title">
				<span className="sd-editbar__phone-name" dir="auto">
					{ draft.title || __( 'Untitled snippet', 'scriptdock' ) }
				</span>
				<span className="sd-editbar__phone-meta">
					{ `${ TYPE_LABELS[ draft.type ] } · ${ placement }` }
				</span>
			</div>
			<ActiveSwitch { ...switchProps } hideWord />
		</div>
	);
}

/**
 * The phone's save bar, at the bottom of the screen.
 *
 * @param {Object}  props       Props.
 * @param {boolean} props.dirty Unsaved changes.
 * @param {Object}  props.save  Save props (see EditorBar).
 * @return {Element} The bar.
 */
export function PhoneSaveBar( { dirty, save } ) {
	return (
		<div className={ cx( 'sd-editor-savebar', dirty && 'is-dirty' ) }>
			{ dirty && <Unsaved short /> }
			<Button
				variant="primary"
				size="lg"
				dot="check"
				onClick={ save.onSave }
				disabled={ save.disabled }
				loading={ save.saving }
				loadingLabel={ __( 'Saving…', 'scriptdock' ) }
			>
				{ __( 'Save', 'scriptdock' ) }
			</Button>
		</div>
	);
}
