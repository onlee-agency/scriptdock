/**
 * The sidebar: Details, Safety, Run now (on-demand PHP), History and
 * Options. On phones the same cards sit in tabs.
 */
import { __, _n, sprintf } from '@wordpress/i18n';
import {
	Avatar,
	Button,
	Icon,
	NumberStepper,
	SettingsRow,
	Switch,
	TextArea,
	TokenInput,
	cx,
} from '../components';

/**
 * Notes, tags and priority.
 *
 * @param {Object}   props          Props.
 * @param {Object}   props.draft    Draft.
 * @param {Function} props.onChange Called with changed fields.
 * @param {Array}    props.tags     Existing tag names, for suggestions.
 * @param {boolean}  props.readOnly Nothing can change.
 * @return {Element} The card.
 */
export function DetailsCard( { draft, onChange, tags, readOnly } ) {
	return (
		<section
			className="sd-card sd-side-card"
			aria-labelledby="sd-details-label"
		>
			<h2 id="sd-details-label" className="sd-overline">
				{ __( 'Details', 'scriptdock' ) }
			</h2>
			<TextArea
				label={ __( 'Notes', 'scriptdock' ) }
				value={ draft.notes }
				onChange={ ( notes ) => onChange( { notes } ) }
				placeholder={ __( 'What does it do, and why?', 'scriptdock' ) }
				autoGrow
				readOnly={ readOnly }
			/>
			<TokenInput
				label={ __( 'Tags', 'scriptdock' ) }
				value={ draft.tags.map( ( name ) => ( {
					value: name,
					label: name,
				} ) ) }
				onChange={ ( items ) =>
					onChange( { tags: items.map( ( item ) => item.label ) } )
				}
				suggestions={ tags.map( ( name ) => ( {
					value: name,
					label: name,
				} ) ) }
				allowCreate
				neutral
				placeholder={ __( 'Add a tag…', 'scriptdock' ) }
				disabled={ readOnly }
			/>
			<NumberStepper
				className="sd-side-card__priority"
				label={ __( 'Priority', 'scriptdock' ) }
				help={ __( 'Lower runs first', 'scriptdock' ) }
				value={ draft.priority }
				onChange={ ( priority ) => onChange( { priority } ) }
				min={ -9999 }
				max={ 9999 }
				disabled={ readOnly }
			/>
		</section>
	);
}

/**
 * The signature line: signed and unchanged, changed, or not signed yet.
 *
 * @param {Object} props      Props.
 * @param {Object} props.item Saved snippet.
 * @return {Element} The line.
 */
function Signature( { item } ) {
	const states = {
		valid: {
			tone: 'success',
			icon: 'shield-check',
			text: __( 'Signed · unchanged', 'scriptdock' ),
		},
		changed: {
			tone: 'danger',
			icon: 'shield-alert',
			text: __(
				'Needs review · changed outside ScriptDock',
				'scriptdock'
			),
		},
		new: {
			tone: 'neutral',
			icon: 'shield',
			text: __( 'Signed when you save', 'scriptdock' ),
		},
		off: {
			tone: 'neutral',
			icon: 'shield',
			text: __( 'Tamper protection is off', 'scriptdock' ),
		},
	};
	const state = states[ item.signature ] || states.off;
	return (
		<p className={ cx( 'sd-signature', `is-${ state.tone }` ) }>
			<Icon name={ state.icon } size={ 18 } stroke={ 1.8 } />
			{ state.text }
		</p>
	);
}

/**
 * Test mode, the signature and the last error.
 *
 * @param {Object}   props          Props.
 * @param {Object}   props.draft    Draft.
 * @param {Object}   props.item     Saved snippet.
 * @param {Function} props.onOption Called with changed options.
 * @param {boolean}  props.readOnly Nothing can change.
 * @return {Element} The card.
 */
export function SafetyCard( { draft, item, onOption, readOnly } ) {
	const error = item.error;
	return (
		<section
			className="sd-card sd-side-card"
			aria-labelledby="sd-safety-label"
		>
			<h2 id="sd-safety-label" className="sd-overline">
				{ __( 'Safety', 'scriptdock' ) }
			</h2>
			<SettingsRow
				id="sd-test-mode"
				title={ __( 'Test mode', 'scriptdock' ) }
				description={ __( 'Only admins see it', 'scriptdock' ) }
				control={
					<Switch
						checked={ !! draft.options.test_mode }
						onChange={ ( on ) => onOption( { test_mode: on } ) }
						aria-labelledby="sd-test-mode"
						locked={ readOnly }
					/>
				}
			/>
			<Signature item={ item } />
			{ error && (
				<div className="sd-last-error">
					<span className="sd-last-error__title">
						{ error.human
							? sprintf(
									/* translators: %s: time since, for example "25 mins ago". */
									__( 'Last error · %s', 'scriptdock' ),
									error.human
								)
							: __( 'Last error', 'scriptdock' ) }
					</span>
					<span className="sd-last-error__detail" dir="ltr">
						{ [
							error.message,
							error.line &&
								sprintf(
									/* translators: %d: line number. */
									__( 'line %d', 'scriptdock' ),
									error.line
								),
							error.url,
						]
							.filter( Boolean )
							.join( ' · ' ) }
					</span>
				</div>
			) }
		</section>
	);
}

/**
 * Run now, for on-demand PHP.
 *
 * @param {Object}   props         Props.
 * @param {boolean}  props.dirty   Unsaved changes (Run now uses the saved
 *                                 code).
 * @param {boolean}  props.running Running now.
 * @param {boolean}  props.blocked Why it cannot run, if it cannot.
 * @param {Function} props.onRun   Runs it.
 * @return {Element} The card.
 */
export function RunCard( { dirty, running, blocked, onRun } ) {
	let note = __(
		'Runs the saved code once and shows what it prints.',
		'scriptdock'
	);
	if ( blocked ) {
		note = blocked;
	} else if ( dirty ) {
		note = __(
			'Save your changes first: Run now uses the saved code.',
			'scriptdock'
		);
	}
	return (
		<section
			className="sd-card sd-side-card"
			aria-labelledby="sd-run-label"
		>
			<h2 id="sd-run-label" className="sd-overline">
				{ __( 'Run now', 'scriptdock' ) }
			</h2>
			<p className="sd-side-card__text">{ note }</p>
			<Button
				variant="primary"
				dot="run"
				onClick={ onRun }
				disabled={ dirty || !! blocked }
				loading={ running }
				loadingLabel={ __( 'Running…', 'scriptdock' ) }
			>
				{ __( 'Run now', 'scriptdock' ) }
			</Button>
		</section>
	);
}

/**
 * Revisions and who saved last.
 *
 * @param {Object}   props        Props.
 * @param {Object}   props.item   Saved snippet.
 * @param {Function} props.onOpen Opens the history drawer.
 * @return {Element} The card.
 */
export function HistoryCard( { item, onOpen } ) {
	const count = item.revisions.count;
	return (
		<section
			className="sd-card sd-side-card"
			aria-labelledby="sd-history-label"
		>
			<h2 id="sd-history-label" className="sd-overline">
				{ __( 'History', 'scriptdock' ) }
			</h2>
			<div className="sd-history-line">
				{ item.modified.author && (
					<Avatar name={ item.modified.author } size="md" />
				) }
				<span className="sd-history-line__text">
					<span className="sd-history-line__count">
						{ sprintf(
							/* translators: %d: number of revisions. */
							_n(
								'%d revision',
								'%d revisions',
								count,
								'scriptdock'
							),
							count
						) }
					</span>
					{ item.modified.human && (
						<span className="sd-history-line__when">
							{ item.modified.author
								? sprintf(
										/* translators: 1: time since, 2: person's name. */
										__(
											'Last saved %1$s by %2$s',
											'scriptdock'
										),
										item.modified.human,
										item.modified.author
									)
								: sprintf(
										/* translators: %s: time since. */
										__( 'Last saved %s', 'scriptdock' ),
										item.modified.human
									) }
						</span>
					) }
				</span>
			</div>
			<Button size="compact" onClick={ onOpen }>
				{ __( 'View history', 'scriptdock' ) }
			</Button>
		</section>
	);
}

/**
 * Smart tags and shortcodes, for HTML and JavaScript.
 *
 * @param {Object}   props          Props.
 * @param {Object}   props.draft    Draft.
 * @param {Function} props.onOption Called with changed options.
 * @param {boolean}  props.readOnly Nothing can change.
 * @return {Element|null} The card.
 */
export function OptionsCard( { draft, onOption, readOnly } ) {
	if ( draft.type !== 'html' && draft.type !== 'js' ) {
		return null;
	}
	return (
		<section
			className="sd-card sd-side-card"
			aria-labelledby="sd-options-label"
		>
			<h2 id="sd-options-label" className="sd-overline">
				{ __( 'Options', 'scriptdock' ) }
			</h2>
			<SettingsRow
				id="sd-smart-tags"
				title={ __( 'Replace smart tags', 'scriptdock' ) }
				description={ __(
					'Swap {{page_title}} and other tags for their real values',
					'scriptdock'
				) }
				control={
					<Switch
						checked={ !! draft.options.smart_tags }
						onChange={ ( on ) => onOption( { smart_tags: on } ) }
						aria-labelledby="sd-smart-tags"
						locked={ readOnly }
					/>
				}
			/>
			{ draft.type === 'html' && (
				<SettingsRow
					id="sd-shortcodes"
					title={ __( 'Run shortcodes in this HTML', 'scriptdock' ) }
					description={ __(
						'Other plugins’ shortcodes will work',
						'scriptdock'
					) }
					control={
						<Switch
							checked={ !! draft.options.shortcodes }
							onChange={ ( on ) =>
								onOption( { shortcodes: on } )
							}
							aria-labelledby="sd-shortcodes"
							locked={ readOnly }
						/>
					}
				/>
			) }
		</section>
	);
}
