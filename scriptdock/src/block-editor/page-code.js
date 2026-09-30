/**
 * Reading and writing page code in the block editor.
 *
 * Page code lives in one post meta entry, so the editor treats it like any
 * other part of the post: an edit marks the post dirty, undo works, and it is
 * written when the writer updates the page. Nothing here saves on its own —
 * that is what "Saved when you update the page" means.
 */
import { useCallback, useMemo } from '@wordpress/element';
import { useDispatch, useSelect } from '@wordpress/data';
import { store as editorStore } from '@wordpress/editor';

/**
 * The seven code fields, in the order the panel lists them.
 */
export const FIELDS = [
	'head',
	'body',
	'footer',
	'before',
	'after',
	'css',
	'js',
];

/**
 * An empty page code entry, matching what PHP stores.
 *
 * @return {Object} Empty data.
 */
function empty() {
	const data = { disable: [], disable_all: false, sig: '' };
	FIELDS.forEach( ( field ) => {
		data[ field ] = '';
	} );
	return data;
}

/**
 * Stored meta in the shape the panel works with. Anything missing — a page
 * that has never had code — comes back empty rather than undefined.
 *
 * @param {Object} stored Meta value.
 * @return {Object} Page data.
 */
function read( stored ) {
	const data = empty();
	if ( ! stored || typeof stored !== 'object' ) {
		return data;
	}
	FIELDS.forEach( ( field ) => {
		data[ field ] =
			typeof stored[ field ] === 'string' ? stored[ field ] : '';
	} );
	data.disable = Array.isArray( stored.disable )
		? stored.disable.map( Number ).filter( Boolean )
		: [];
	data.disable_all = !! stored.disable_all;
	data.sig = typeof stored.sig === 'string' ? stored.sig : '';
	return data;
}

/**
 * How many lines a field holds. Empty code is nought lines, not one.
 *
 * @param {string} code Code.
 * @return {number} Lines.
 */
export function lineCount( code ) {
	return code && code.trim() ? code.split( '\n' ).length : 0;
}

/**
 * How much of its own ScriptDock is doing on this page: code slots in use
 * plus site-wide snippets switched off. This is what the ember badge on the
 * toolbar button counts, and nought means the page is plain.
 *
 * @param {Object} data  Page data.
 * @param {number} total How many site-wide snippets there are.
 * @return {number} Count.
 */
export function pageCount( data, total ) {
	const slots = FIELDS.filter(
		( field ) => data[ field ].trim() !== ''
	).length;
	return slots + ( data.disable_all ? total : data.disable.length );
}

/**
 * The post's page code, and a way to change it.
 *
 * @param {string} metaKey Meta key page code is stored in.
 * @return {Object} { data, update, title, saving }.
 */
export default function usePageCode( metaKey ) {
	const { meta, title, saving } = useSelect( ( select ) => {
		const editor = select( editorStore );
		return {
			meta: editor.getEditedPostAttribute( 'meta' ),
			title: editor.getEditedPostAttribute( 'title' ),
			saving: editor.isSavingPost(),
		};
	}, [] );
	const { editPost } = useDispatch( editorStore );
	const data = useMemo(
		() => read( meta ? meta[ metaKey ] : null ),
		[ meta, metaKey ]
	);

	const update = useCallback(
		( changes ) => {
			// The signature is worked out on the server after every save, so
			// sending a stale one back would only be noise.
			const next = { ...data, ...changes };
			delete next.sig;
			editPost( { meta: { [ metaKey ]: next } } );
		},
		[ data, editPost, metaKey ]
	);

	return { data, update, title, saving };
}
