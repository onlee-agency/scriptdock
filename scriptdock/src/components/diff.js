/**
 * Diff: changed lines with a few lines of context, inline or side by side.
 *
 * Colour is never the only signal: every changed line carries a + or − in the
 * gutter, a 3px edge, and a word screen readers read out.
 */
import { __, _n, sprintf } from '@wordpress/i18n';
import { cx } from './utils';

const MARKERS = { add: '+', remove: '−', same: ' ' };

/**
 * @param {Object} props           Props.
 * @param {Array}  props.lines     Lines: { type, text, old, new } and skip lines
 *                                 with a count.
 * @param {string} props.mode      inline (one column) or split (before and after).
 * @param {string} props.label     Accessible name of the region.
 * @param {string} props.before    Column heading for the older code.
 * @param {string} props.after     Column heading for the newer code.
 * @param {string} props.className Extra class.
 * @return {Element} The diff.
 */
export default function Diff( {
	lines = [],
	mode = 'inline',
	label = __( 'Changed lines', 'scriptdock' ),
	before = __( 'Before', 'scriptdock' ),
	after = __( 'After', 'scriptdock' ),
	className,
} ) {
	if ( mode === 'split' ) {
		const rows = pairs( lines );
		// Each column scrolls on its own, so each is its own region a
		// keyboard can reach.
		return (
			<div
				className={ cx( 'sd-diff', 'sd-diff--split', className ) }
				role="group"
				aria-label={ label }
			>
				<div
					className="sd-diff__col"
					role="region"
					aria-label={ before }
					tabIndex={ 0 }
				>
					<div className="sd-diff__head">{ before }</div>
					{ rows.map( ( row, index ) => (
						<Side key={ index } line={ row.left } side="old" />
					) ) }
				</div>
				<div
					className="sd-diff__col"
					role="region"
					aria-label={ after }
					tabIndex={ 0 }
				>
					<div className="sd-diff__head">{ after }</div>
					{ rows.map( ( row, index ) => (
						<Side key={ index } line={ row.right } side="new" />
					) ) }
				</div>
			</div>
		);
	}

	return (
		<div
			className={ cx( 'sd-diff', className ) }
			role="region"
			aria-label={ label }
			tabIndex={ 0 }
		>
			{ lines.map( ( line, index ) =>
				line.type === 'skip' ? (
					<Skip key={ index } count={ line.count } />
				) : (
					<div
						key={ index }
						className={ cx( 'sd-diff__line', `is-${ line.type }` ) }
					>
						<span className="sd-diff__gutter" aria-hidden="true">
							<span className="sd-diff__number">
								{ line.new || line.old }
							</span>
							<span className="sd-diff__marker">
								{ MARKERS[ line.type ] }
							</span>
						</span>
						<code className="sd-diff__code" dir="ltr">
							<Says type={ line.type } />
							{ line.text || ' ' }
						</code>
					</div>
				)
			) }
		</div>
	);
}

/**
 * One line in a column of the side-by-side view. A blank keeps the two
 * columns level with each other.
 *
 * @param {Object} props      Props.
 * @param {Object} props.line Line, or null for a blank.
 * @param {string} props.side old or new, which line number to show.
 * @return {Element} The line.
 */
function Side( { line, side } ) {
	if ( ! line ) {
		return <div className="sd-diff__line is-blank" aria-hidden="true" />;
	}
	if ( line.type === 'skip' ) {
		return <Skip count={ line.count } />;
	}
	return (
		<div className={ cx( 'sd-diff__line', `is-${ line.type }` ) }>
			<span className="sd-diff__gutter" aria-hidden="true">
				<span className="sd-diff__number">
					{ side === 'old' ? line.old : line.new }
				</span>
				<span className="sd-diff__marker">
					{ MARKERS[ line.type ] }
				</span>
			</span>
			<code className="sd-diff__code" dir="ltr">
				<Says type={ line.type } />
				{ line.text || ' ' }
			</code>
		</div>
	);
}

/**
 * "3 unchanged lines" between two changes.
 *
 * @param {Object} props       Props.
 * @param {number} props.count How many lines were left out.
 * @return {Element} The row.
 */
function Skip( { count } ) {
	return (
		<div className="sd-diff__skip">
			{ sprintf(
				/* translators: %d: number of unchanged lines. */
				_n(
					'%d unchanged line',
					'%d unchanged lines',
					count,
					'scriptdock'
				),
				count
			) }
		</div>
	);
}

/**
 * What a screen reader hears before a changed line.
 *
 * @param {Object} props      Props.
 * @param {string} props.type add, remove or same.
 * @return {Element|null} The words.
 */
function Says( { type } ) {
	const words = {
		add: __( 'Added:', 'scriptdock' ),
		remove: __( 'Removed:', 'scriptdock' ),
	};
	if ( ! words[ type ] ) {
		return null;
	}
	return <span className="sd-visually-hidden">{ words[ type ] }</span>;
}

/**
 * Pairs the lines into rows for the side-by-side view: a run of removed lines
 * sits beside the added ones that replaced it, and the shorter side is padded
 * with blanks.
 *
 * @param {Array} lines Diff lines.
 * @return {Array} Rows of { left, right }.
 */
function pairs( lines ) {
	const rows = [];
	let removed = [];
	let added = [];

	const flush = () => {
		const height = Math.max( removed.length, added.length );
		for ( let at = 0; at < height; at++ ) {
			rows.push( {
				left: removed[ at ] || null,
				right: added[ at ] || null,
			} );
		}
		removed = [];
		added = [];
	};

	lines.forEach( ( line ) => {
		if ( line.type === 'remove' ) {
			removed.push( line );
			return;
		}
		if ( line.type === 'add' ) {
			added.push( line );
			return;
		}
		flush();
		rows.push( { left: line, right: line } );
	} );
	flush();

	return rows;
}
