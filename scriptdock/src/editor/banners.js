/**
 * Banners under the editor bar: changed outside ScriptDock, the last error,
 * read-only, safe mode and the trash. Each says what happened and offers
 * the next step.
 */
import { createInterpolateElement } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { Banner, Button } from '../components';

/**
 * The code changed outside ScriptDock.
 *
 * @param {Object}   props           Props.
 * @param {Object}   props.item      Saved snippet.
 * @param {Function} props.onHistory Opens the history drawer.
 * @param {boolean}  props.approving Approving now.
 * @param {Function} props.onApprove Approves the current code.
 * @return {Element} The banner.
 */
export function TamperedBanner( { item, onHistory, approving, onApprove } ) {
	const change = item.review && item.review.text;
	let text = item.paused
		? __(
				'The snippet is paused until you approve it. Compare the changes first.',
				'scriptdock'
			)
		: __(
				'It is switched off. Compare the changes before you approve them.',
				'scriptdock'
			);
	if ( change ) {
		text = sprintf(
			/* translators: 1: what changed, for example "1 line added, loading from cdn.example.com", 2: the next sentence. */
			__( '%1$s. %2$s', 'scriptdock' ),
			change,
			text
		);
	}
	return (
		<Banner
			tone="danger"
			icon="shield-alert"
			className="sd-banner--side-actions"
			title={ __( 'This code changed outside ScriptDock', 'scriptdock' ) }
			actions={
				<>
					<Button
						variant="outline"
						size="compact"
						onClick={ onHistory }
					>
						{ __( 'Compare and restore', 'scriptdock' ) }
					</Button>
					{ item.can_edit && (
						<Button
							variant="danger-solid"
							size="compact"
							onClick={ onApprove }
							loading={ approving }
							loadingLabel={ __( 'Approving…', 'scriptdock' ) }
						>
							{ __( 'Approve', 'scriptdock' ) }
						</Button>
					) }
				</>
			}
		>
			{ text }
		</Banner>
	);
}

/**
 * The headline for an error: what it meant for the site.
 *
 * @param {Object}  error The recorded error.
 * @param {boolean} off   The snippet is switched off.
 * @return {string} Title.
 */
function errorTitle( error, off ) {
	if ( error.fatal && off ) {
		return __(
			'This snippet was switched off to keep your site running',
			'scriptdock'
		);
	}
	if ( off ) {
		return __(
			'This snippet is switched off after an error',
			'scriptdock'
		);
	}
	return __( 'This snippet ran into an error', 'scriptdock' );
}

/**
 * The last error, with a jump to its line.
 *
 * @param {Object}   props        Props.
 * @param {Object}   props.item   Saved snippet.
 * @param {Function} props.onJump Jumps to the line.
 * @return {Element} The banner.
 */
export function ErrorBanner( { item, onJump } ) {
	const error = item.error;
	const off = ! item.active;
	// The message and URL go in as elements, never into the translated
	// string, so a message with "<" in it cannot break the sentence.
	let template;
	if ( error.line && error.url ) {
		template = sprintf(
			/* translators: %d: line number. <message /> is the error message and <url /> the page that was loading. */
			__(
				'<message /> on line %d, while loading <url />.',
				'scriptdock'
			),
			error.line
		);
	} else if ( error.line ) {
		template = sprintf(
			/* translators: %d: line number. <message /> is the error message. */
			__( '<message /> on line %d.', 'scriptdock' ),
			error.line
		);
	} else if ( error.url ) {
		/* translators: <message /> is the error message and <url /> the page that was loading. */
		template = __( '<message />, while loading <url />.', 'scriptdock' );
	} else {
		/* translators: <message /> is the error message. */
		template = __( '<message />.', 'scriptdock' );
	}
	const detail = createInterpolateElement( template, {
		message: <code>{ error.message }</code>,
		url: <code>{ error.url }</code>,
	} );
	const after = [];
	if ( error.human ) {
		after.push(
			sprintf(
				/* translators: %s: time since, for example "25 mins ago". */
				__( 'It happened %s.', 'scriptdock' ),
				error.human
			)
		);
	}
	if ( error.emailed ) {
		after.push(
			sprintf(
				/* translators: %s: email address. */
				__( 'We emailed %s.', 'scriptdock' ),
				error.emailed
			)
		);
	}
	return (
		<Banner
			tone="danger"
			className="sd-banner--side-actions"
			title={ errorTitle( error, off ) }
			actions={
				error.line > 0 && (
					<Button
						variant="outline"
						size="compact"
						onClick={ () => onJump( error.line ) }
					>
						{ sprintf(
							/* translators: %d: line number. */
							__( 'Jump to line %d', 'scriptdock' ),
							error.line
						) }
					</Button>
				)
			}
		>
			{ detail }
			{ after.length > 0 && ' ' + after.join( ' ' ) }
		</Banner>
	);
}

/**
 * The viewer may read the code but not change it.
 *
 * @param {Object}  props              Props.
 * @param {string}  props.reason       Why, as Capabilities explains it.
 * @param {boolean} props.canSwitchOff They may still switch it off.
 * @return {Element} The banner.
 */
export function ReadOnlyBanner( { reason, canSwitchOff } ) {
	return (
		<Banner
			tone="warning"
			icon="lock"
			title={ __(
				'You can read this code but not change it.',
				'scriptdock'
			) }
		>
			{ canSwitchOff
				? sprintf(
						/* translators: %s: why PHP cannot be edited. */
						__( '%s You can still switch it off.', 'scriptdock' ),
						reason
					)
				: reason }
		</Banner>
	);
}

/**
 * Safe mode is on in this browser.
 *
 * @param {Object}  props         Props.
 * @param {boolean} props.forced  Forced for everyone in wp-config.php.
 * @param {string}  props.exitUrl Exit link.
 * @return {Element} The banner.
 */
export function SafeModeBanner( { forced, exitUrl } ) {
	return (
		<Banner
			tone="warning"
			actions={
				! forced && (
					<Button variant="outline" size="compact" href={ exitUrl }>
						{ __( 'Exit safe mode', 'scriptdock' ) }
					</Button>
				)
			}
		>
			{ forced
				? createInterpolateElement(
						__(
							'<strong>Safe mode is on for everyone.</strong> SCRIPTDOCK_SAFE_MODE is set in wp-config.php, so no snippets run anywhere while you work.',
							'scriptdock'
						),
						{ strong: <strong /> }
					)
				: createInterpolateElement(
						__(
							'<strong>Safe mode is on.</strong> Snippets are paused in your browser, so you will not see this one on the front end while you work.',
							'scriptdock'
						),
						{ strong: <strong /> }
					) }
		</Banner>
	);
}

/**
 * The snippet is in the trash.
 *
 * @param {Object}   props           Props.
 * @param {boolean}  props.busy      Taking it out now.
 * @param {Function} props.onUntrash Takes it out of the trash.
 * @return {Element} The banner.
 */
export function TrashBanner( { busy, onUntrash } ) {
	return (
		<Banner
			tone="warning"
			icon="trash"
			actions={
				<Button
					variant="outline"
					size="compact"
					onClick={ onUntrash }
					loading={ busy }
					loadingLabel={ __( 'Restoring…', 'scriptdock' ) }
				>
					{ __( 'Restore', 'scriptdock' ) }
				</Button>
			}
		>
			{ __(
				'This snippet is in the trash. Restore it to edit it; it comes back switched off.',
				'scriptdock'
			) }
		</Banner>
	);
}
