/**
 * sd-IconButton: a round, icon-only button. Its `label` is the accessible
 * name.
 */
import { forwardRef } from '@wordpress/element';
import Icon from './icon';
import { cx } from './utils';

const SIZE_CLASS = {
	lg: 'sd-icon-button--lg',
	compact: 'sd-icon-button--md',
	sm: 'sd-icon-button--sm',
	xs: 'sd-icon-button--xs',
};

const ICON_SIZE = { lg: 18, md: 18, compact: 15, sm: 16, xs: 15 };

/**
 * @param {Object} props           Props.
 * @param {string} props.icon      Icon name.
 * @param {string} props.label     Accessible name.
 * @param {string} props.variant   outline, soft, ghost, ink, on-ink or muted.
 * @param {string} props.size      lg (44), md (40), compact (36), sm (32) or
 *                                 xs (30).
 * @param {number} props.iconSize  Icon size override.
 * @param {number} props.stroke    Icon stroke override.
 * @param {string} props.href      Render a link.
 * @param {string} props.className Extra classes.
 * @param {Object} ref             Forwarded ref.
 * @return {Element} The button.
 */
function IconButton(
	{
		icon,
		label,
		variant = 'outline',
		size = 'md',
		iconSize,
		stroke = 1.8,
		href,
		className,
		...rest
	},
	ref
) {
	const classes = cx(
		'sd-icon-button',
		variant !== 'outline' && `sd-icon-button--${ variant }`,
		SIZE_CLASS[ size ],
		className
	);
	const glyph = (
		<Icon
			name={ icon }
			size={ iconSize || ICON_SIZE[ size ] || 18 }
			stroke={ stroke }
		/>
	);
	if ( href ) {
		return (
			<a
				ref={ ref }
				className={ classes }
				href={ href }
				aria-label={ label }
				{ ...rest }
			>
				{ glyph }
			</a>
		);
	}
	return (
		<button
			ref={ ref }
			type="button"
			className={ classes }
			aria-label={ label }
			{ ...rest }
		>
			{ glyph }
		</button>
	);
}

export default forwardRef( IconButton );
