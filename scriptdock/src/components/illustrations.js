/**
 * sd-Illustration: the five product illustrations, drawn as one set.
 *
 * Thin black line work with a single ember dot as the focal point; the dot
 * always means "your code", and where it sits tells the story. All five
 * share one construction so they never drift apart: a 200 × 150 frame,
 * 1.2 px lines, two lighter line strengths (soft and faint), and the same
 * dot (radius 10) with the same glow (radius 20).
 *
 * No access is the one exception: its centre is gray rather than ember,
 * because the point of that drawing is that the code is not yours.
 */
import { useInstanceId } from '@wordpress/compose';
import { cx } from './utils';

/**
 * The focal dot and its glow.
 *
 * @param {Object} props    Props.
 * @param {number} props.cx Centre x.
 * @param {number} props.cy Centre y.
 * @param {string} props.id Gradient id.
 * @return {Element} The dot.
 */
function Dot( { cx: x, cy: y, id } ) {
	return (
		<>
			<circle
				className="sd-illustration__glow"
				cx={ x }
				cy={ y }
				r="20"
			/>
			<circle cx={ x } cy={ y } r="10" fill={ `url(#${ id })` } />
		</>
	);
}

/**
 * Welcome: orbits converging on your code.
 *
 * @param {Object} props    Props.
 * @param {string} props.id Gradient id.
 * @return {Element} The drawing.
 */
function Welcome( { id } ) {
	return (
		<>
			<g className="sd-illustration__line">
				<ellipse cx="100" cy="75" rx="62" ry="24" />
				<ellipse
					cx="100"
					cy="75"
					rx="62"
					ry="24"
					transform="rotate(60 100 75)"
				/>
				<ellipse
					cx="100"
					cy="75"
					rx="62"
					ry="24"
					transform="rotate(-60 100 75)"
				/>
				<circle
					className="sd-illustration__soft"
					cx="100"
					cy="75"
					r="40"
					strokeDasharray="3 6"
				/>
			</g>
			<Dot cx={ 100 } cy={ 75 } id={ id } />
			<circle className="sd-illustration__ink" cx="162" cy="75" r="3" />
			<circle className="sd-illustration__ink" cx="38" cy="75" r="3" />
		</>
	);
}

/**
 * Empty snippets: an empty stack waiting for its first card.
 *
 * @param {Object} props    Props.
 * @param {string} props.id Gradient id.
 * @return {Element} The drawing.
 */
function EmptySnippets( { id } ) {
	return (
		<>
			<g className="sd-illustration__line">
				<rect
					className="sd-illustration__faint"
					x="40"
					y="96"
					width="120"
					height="26"
					rx="6"
				/>
				<rect
					className="sd-illustration__soft"
					x="48"
					y="70"
					width="104"
					height="26"
					rx="6"
				/>
				<rect x="56" y="42" width="88" height="28" rx="6" />
				<path d="M68 56h24" />
				<path className="sd-illustration__soft" d="M68 62h14" />
				<g className="sd-illustration__faint">
					<path d="M100 18v10" />
					<path d="M78 24l5 8" />
					<path d="M122 24l-5 8" />
				</g>
			</g>
			<Dot cx={ 126 } cy={ 56 } id={ id } />
		</>
	);
}

/**
 * Crash recovered: a broken orbit, the dot intact and checked.
 *
 * @param {Object} props    Props.
 * @param {string} props.id Gradient id.
 * @return {Element} The drawing.
 */
function CrashRecovered( { id } ) {
	return (
		<>
			<g className="sd-illustration__line">
				<circle cx="100" cy="75" r="46" strokeDasharray="66 26" />
				<circle
					className="sd-illustration__soft"
					cx="100"
					cy="75"
					r="26"
				/>
				<path d="M54 75h-12" />
				<path d="M146 75h12" />
				<path className="sd-illustration__soft" d="M136 41l9-9" />
				<path className="sd-illustration__soft" d="M64 109l-9 9" />
				<g className="sd-illustration__soft">
					<path d="M158 46l6-6" />
					<path d="M163 52l7-3" />
					<path d="M152 39l3-7" />
				</g>
			</g>
			<Dot cx={ 100 } cy={ 75 } id={ id } />
			<path
				className="sd-illustration__check"
				d="M95 75l3.6 3.6L106 71"
			/>
		</>
	);
}

/**
 * Load failed: the orbit broken and crossed through, the dot still lit —
 * the screen went, your code did not.
 *
 * @param {Object} props    Props.
 * @param {string} props.id Gradient id.
 * @return {Element} The drawing.
 */
function LoadFailed( { id } ) {
	return (
		<>
			<g className="sd-illustration__line">
				<circle cx="100" cy="75" r="46" strokeDasharray="58 22" />
				<path className="sd-illustration__soft" d="M77 56l46 38" />
				<path className="sd-illustration__soft" d="M123 56l-46 38" />
				<g className="sd-illustration__faint">
					<path d="M36 75h-10" />
					<path d="M174 75h10" />
				</g>
			</g>
			<Dot cx={ 100 } cy={ 75 } id={ id } />
		</>
	);
}

/**
 * No access: a closed padlock with a gray centre — the one drawing where
 * the dot is not lit, because this code is not yours to see.
 *
 * The same drawing is in No_Access::PADLOCK, for the PHP page shown to
 * someone who cannot load a screen at all. Change both together.
 *
 * @return {Element} The drawing.
 */
function NoAccess() {
	return (
		<>
			<g className="sd-illustration__line">
				<rect x="60" y="66" width="80" height="54" rx="8" />
				<path d="M77 66V51a23 23 0 0 1 46 0v15" />
				<g className="sd-illustration__faint">
					<path d="M44 96h-10" />
					<path d="M166 96h10" />
				</g>
			</g>
			<circle className="sd-illustration__glow" cx="100" cy="93" r="18" />
			<circle
				className="sd-illustration__locked"
				cx="100"
				cy="93"
				r="8.5"
			/>
		</>
	);
}

const DRAWINGS = {
	welcome: Welcome,
	'empty-snippets': EmptySnippets,
	'crash-recovered': CrashRecovered,
	'load-failed': LoadFailed,
	'no-access': NoAccess,
};

/**
 * One of the product illustrations. Decorative: the text next to it says
 * what it means.
 *
 * @param {Object} props           Props.
 * @param {string} props.name      welcome, empty-snippets, crash-recovered,
 *                                 load-failed or no-access.
 * @param {number} props.width     Width in pixels (height follows 4:3).
 * @param {string} props.className Extra classes.
 * @return {Element} The illustration.
 */
export default function Illustration( { name, width = 200, className } ) {
	const id = useInstanceId( Illustration, 'sd-illustration-dot' );
	const Drawing = DRAWINGS[ name ];
	if ( ! Drawing ) {
		return null;
	}
	return (
		<svg
			className={ cx( 'sd-illustration', className ) }
			viewBox="0 0 200 150"
			width={ width }
			height={ Math.round( ( width * 150 ) / 200 ) }
			fill="none"
			aria-hidden="true"
			focusable="false"
		>
			<defs>
				<radialGradient id={ id } cx=".35" cy=".3" r=".8">
					<stop className="sd-illustration__stop-1" offset="0" />
					<stop className="sd-illustration__stop-2" offset=".55" />
					<stop className="sd-illustration__stop-3" offset="1" />
				</radialGradient>
			</defs>
			<Drawing id={ id } />
		</svg>
	);
}
