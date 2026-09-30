/**
 * The consent categories the WP Consent API defines, in the order the editor
 * offers them.
 */
import { __ } from '@wordpress/i18n';

export const CONSENT_CATEGORIES = [
	{ value: '', label: __( 'Not required', 'scriptdock' ) },
	{ value: 'statistics', label: __( 'Statistics', 'scriptdock' ) },
	{
		value: 'statistics-anonymous',
		label: __( 'Anonymous statistics', 'scriptdock' ),
	},
	{ value: 'marketing', label: __( 'Marketing', 'scriptdock' ) },
	{ value: 'preferences', label: __( 'Preferences', 'scriptdock' ) },
	{ value: 'functional', label: __( 'Functional', 'scriptdock' ) },
];

/**
 * What a category is called.
 *
 * @param {string} value Category.
 * @return {string} Label, or the category itself when it is not one of ours.
 */
export function consentLabel( value ) {
	return (
		CONSENT_CATEGORIES.find( ( entry ) => entry.value === value )?.label ||
		value
	);
}
