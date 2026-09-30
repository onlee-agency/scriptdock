/**
 * Icon: the thin-line set, drawn in currentColor.
 *
 * The shapes come from assets/icons/icons.json, the same file the PHP side
 * reads, so every icon matches the designs on both sides.
 */
import icons from '../../assets/icons/icons.json';
import { cx } from './utils';

/**
 * @param {Object} props           Props.
 * @param {string} props.name      Icon name, as in icons.json.
 * @param {number} props.size      Width and height in pixels.
 * @param {number} props.stroke    Stroke width on the 24px grid.
 * @param {string} props.className Extra classes.
 * @return {Element|null} The icon, hidden from assistive technology.
 */
export default function Icon( { name, size = 16, stroke = 1.8, className } ) {
	const body = icons[ name ];
	if ( ! body ) {
		return null;
	}
	return (
		<svg
			className={ cx( 'sd-icon', `sd-icon--${ name }`, className ) }
			width={ size }
			height={ size }
			viewBox="0 0 24 24"
			fill="none"
			stroke="currentColor"
			strokeWidth={ stroke }
			strokeLinecap="round"
			strokeLinejoin="round"
			aria-hidden="true"
			focusable="false"
			// The markup is static and ships with the plugin.
			dangerouslySetInnerHTML={ { __html: body } }
		/>
	);
}
