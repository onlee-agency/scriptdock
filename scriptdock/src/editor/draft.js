/**
 * The editor's draft: the fields a person can change, taken from a snippet
 * as the REST API describes it, and how to tell what changed.
 */
import { __ } from '@wordpress/i18n';

/**
 * Fields the editor saves, in the order the leave dialog names them.
 */
export const FIELDS = [
	'code',
	'title',
	'notes',
	'tags',
	'priority',
	'type',
	'active',
	'options',
	'location',
	'location_args',
	'conditions',
	'schedule',
];

const EXTENSIONS = {
	php: 'php',
	universal: 'php',
	html: 'html',
	css: 'css',
	js: 'js',
};

/**
 * PHP sends empty associative arrays as [], so read them as objects.
 *
 * @param {*} value Value.
 * @return {Object} Object.
 */
const asObject = ( value ) =>
	value && ! Array.isArray( value ) && typeof value === 'object' ? value : {};

/**
 * The editable fields of a snippet.
 *
 * @param {Object} item Snippet from the REST API (the full shape).
 * @return {Object} Draft.
 */
export function toDraft( item ) {
	return {
		title: item.title || '',
		code: item.code || '',
		notes: item.notes || '',
		type: item.type,
		location: item.placement.key,
		location_args: asObject( item.location_args ),
		priority: item.priority,
		conditions: asObject( item.conditions ),
		schedule: {
			start: item.schedule.start || '',
			end: item.schedule.end || '',
		},
		options: asObject( item.options ),
		tags: item.tags.map( ( tag ) => tag.name ),
		active: !! item.active,
	};
}

/**
 * The same value, with object keys in a fixed order.
 *
 * @param {*} value Value.
 * @return {string} JSON.
 */
function stable( value ) {
	return JSON.stringify( value, ( key, inner ) =>
		inner && typeof inner === 'object' && ! Array.isArray( inner )
			? Object.keys( inner )
					.sort()
					.reduce( ( out, name ) => {
						out[ name ] = inner[ name ];
						return out;
					}, {} )
			: inner
	);
}

/**
 * Fields that differ between two drafts.
 *
 * @param {Object} draft Draft.
 * @param {Object} base  Draft to compare with (the saved one).
 * @return {string[]} Field names.
 */
export function changedFields( draft, base ) {
	return FIELDS.filter(
		( field ) => stable( draft[ field ] ) !== stable( base[ field ] )
	);
}

/**
 * What changed, in words, for the leave dialog.
 *
 * @param {string[]} fields Changed fields.
 * @return {string[]} Phrases, such as "the code".
 */
export function changedPhrases( fields ) {
	const phrases = {
		code: __( 'the code', 'scriptdock' ),
		title: __( 'the title', 'scriptdock' ),
		notes: __( 'the notes', 'scriptdock' ),
		tags: __( 'the tags', 'scriptdock' ),
		priority: __( 'the priority', 'scriptdock' ),
		type: __( 'the code type', 'scriptdock' ),
		active: __( 'whether it runs', 'scriptdock' ),
		options: __( 'how it loads', 'scriptdock' ),
	};
	const seen = new Set();
	return fields
		.map(
			( field ) => phrases[ field ] || __( 'where it runs', 'scriptdock' )
		)
		.filter( ( phrase ) => ! seen.has( phrase ) && seen.add( phrase ) );
}

/**
 * A file name for the code card: the title as a slug, and the extension.
 *
 * @param {string} title Title.
 * @param {string} type  Type.
 * @return {string} File name.
 */
export function fileName( title, type ) {
	const slug =
		( title || '' )
			.toLowerCase()
			.normalize( 'NFKD' )
			.replace( /[\u0300-\u036f]/g, '' )
			.replace( /[^a-z0-9]+/g, '-' )
			.replace( /^-+|-+$/g, '' )
			.slice( 0, 40 ) || 'untitled';
	return `${ slug }.${ EXTENSIONS[ type ] || 'txt' }`;
}
