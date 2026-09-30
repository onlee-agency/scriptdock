/**
 * The words the list shows about a snippet: the line under its title, the
 * switch's name, where it runs and its status.
 */
import { __, sprintf } from '@wordpress/i18n';

/**
 * The line under a snippet's title. The most useful fact wins: saving, an
 * error, a change to review, a lock, the shortcode, then the notes.
 *
 * @param {Object}  item     Snippet row.
 * @param {boolean} busy     Whether its switch is saving.
 * @param {boolean} detailed Say what changed, not just that it changed (the
 *                           needs-review view).
 * @return {Object} kind (saving, error, review, locked, shortcode, notes)
 *                  and text.
 */
export function subline( item, busy, detailed = false ) {
	if ( busy ) {
		return { kind: 'saving', text: __( 'Saving…', 'scriptdock' ) };
	}
	if ( item.error ) {
		const line = item.error.line;
		let text;
		if ( item.error.fatal && ! item.active ) {
			text = line
				? sprintf(
						/* translators: %d: line number. */
						__(
							'Switched off after a fatal error on line %d',
							'scriptdock'
						),
						line
					)
				: __( 'Switched off after a fatal error', 'scriptdock' );
		} else {
			text = line
				? sprintf(
						/* translators: %d: line number. */
						__( 'Threw an error on line %d', 'scriptdock' ),
						line
					)
				: __( 'Threw an error', 'scriptdock' );
		}
		return { kind: 'error', text };
	}
	if ( ! item.trusted ) {
		const change =
			detailed && item.review
				? item.review.text
				: __( 'Changed outside ScriptDock', 'scriptdock' );
		return {
			kind: 'review',
			text: item.paused
				? sprintf(
						/* translators: %s: what changed, for example "1 line added". */
						__( '%s · paused', 'scriptdock' ),
						change
					)
				: change,
		};
	}
	if ( ! item.can_activate && ! item.active ) {
		return {
			kind: 'locked',
			text: __(
				'Only an administrator who can edit PHP can switch it on',
				'scriptdock'
			),
		};
	}
	if ( item.shortcode ) {
		return { kind: 'shortcode', text: item.shortcode };
	}
	return { kind: 'notes', text: item.notes };
}

/**
 * The switch's accessible name. On and off come from the switch itself;
 * the name adds why it cannot be used, when it cannot.
 *
 * @param {Object} item Snippet row.
 * @return {string} Name.
 */
export function switchLabel( item ) {
	if ( item.paused ) {
		return sprintf(
			/* translators: %s: snippet title. */
			__( '%s, paused until you review the change', 'scriptdock' ),
			item.title
		);
	}
	if ( ! item.can_activate && ! item.active ) {
		return sprintf(
			/* translators: %s: snippet title. */
			__(
				'%s, locked: only an administrator who can edit PHP can switch it on',
				'scriptdock'
			),
			item.title
		);
	}
	return item.title;
}

/**
 * Where it runs, as the list's two lines: placement, then targeting.
 *
 * @param {Object} item Snippet row.
 * @return {string[]} The two lines.
 */
export function whereLines( item ) {
	const summary = item.targeting.summary;
	return [
		item.placement.short,
		summary.length ? summary.join( ' · ' ) : '—',
	];
}

/**
 * The snippet's state in a word or two, for the quick view.
 *
 * @param {Object} item Snippet row.
 * @return {Object} state (running, off, error, paused, scheduled, expired)
 *                  and label.
 */
export function status( item ) {
	if ( item.status === 'trash' ) {
		return { state: 'off', label: __( 'In the trash', 'scriptdock' ) };
	}
	if ( item.paused ) {
		return {
			state: 'paused',
			label: __( 'Paused for review', 'scriptdock' ),
		};
	}
	if ( item.running ) {
		return { state: 'running', label: __( 'Running', 'scriptdock' ) };
	}
	if ( item.active && item.schedule.state === 'scheduled' ) {
		return { state: 'scheduled', label: __( 'Scheduled', 'scriptdock' ) };
	}
	if ( item.active && item.schedule.state === 'expired' ) {
		return { state: 'expired', label: __( 'Expired', 'scriptdock' ) };
	}
	if ( item.active && item.placement.key === 'on_demand' ) {
		return {
			state: 'off',
			label: __( 'Runs when you run it', 'scriptdock' ),
		};
	}
	if ( item.error ) {
		return {
			state: 'error',
			label: __( 'Switched off after an error', 'scriptdock' ),
		};
	}
	return { state: 'off', label: __( 'Switched off', 'scriptdock' ) };
}

/**
 * Badge component props for a badge from the API.
 *
 * @param {Object} badge Badge: key, label, tone, detail.
 * @return {Object} Badge props: tone and icon.
 */
export function badgeProps( badge ) {
	if ( badge.key === 'review' ) {
		return { tone: 'danger', icon: 'shield-alert' };
	}
	if ( badge.key === 'error' ) {
		return { tone: 'outline-danger' };
	}
	const tones = {
		danger: 'danger',
		warning: 'warning',
		info: 'info',
		neutral: 'muted',
	};
	return { tone: tones[ badge.tone ] || 'muted' };
}
