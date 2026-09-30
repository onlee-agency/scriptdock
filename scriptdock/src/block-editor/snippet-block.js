/**
 * S16: the "ScriptDock Snippet" block.
 *
 * It places a snippet whose location is "Shortcode or block only" — the ones
 * a writer positions by hand. Four states: nothing to choose from, choosing,
 * chosen, and a snippet that has since gone.
 *
 * The canvas is its own document, so nothing here uses the design system.
 * The block brings its own small stylesheet (assets/css/block/snippet.css),
 * which WordPress loads into the canvas with the block.
 */
import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';
import metadata from '../../blocks/snippet/block.json';

/**
 * The dot ScriptDock is drawn with.
 *
 * @param {Object}  props       Props.
 * @param {boolean} props.dim   Gray, for the empty state.
 * @param {boolean} props.small Smaller, for the chosen state.
 * @return {Element} The dot.
 */
function Dot( { dim = false, small = false } ) {
	return (
		<span
			className={ [
				'sd-snippetblock__dot',
				dim ? 'is-dim' : '',
				small ? 'is-small' : '',
			]
				.filter( Boolean )
				.join( ' ' ) }
			aria-hidden="true"
		/>
	);
}

/**
 * The type of a snippet, as a small coloured chip.
 *
 * @param {Object} props         Props.
 * @param {Object} props.snippet Snippet.
 * @return {Element} The chip.
 */
function Chip( { snippet } ) {
	return (
		<span
			className={ `sd-snippetblock__chip sd-snippetblock__chip--${ snippet.type }` }
		>
			{ snippet.typeLabel }
		</span>
	);
}

/**
 * The block in the canvas.
 *
 * @param {Object}   props               Props.
 * @param {Object}   props.attributes    Block attributes.
 * @param {Function} props.setAttributes Changes them.
 * @return {Element} The block.
 */
function Edit( { attributes, setAttributes } ) {
	const boot = ( window.sdBlockEditor && window.sdBlockEditor.block ) || {
		snippets: [],
	};
	const blockProps = useBlockProps( { className: 'sd-snippetblock' } );
	const id = Number( attributes.snippetId ) || 0;
	const chosen = boot.snippets.find( ( snippet ) => snippet.id === id );

	if ( id && ! chosen ) {
		return (
			<div { ...blockProps }>
				<div className="sd-snippetblock__card sd-snippetblock__card--gone">
					<strong className="sd-snippetblock__gone">
						{ __(
							'This snippet was deleted or switched off',
							'scriptdock'
						) }
					</strong>
					<span className="sd-snippetblock__text">
						{ __(
							'Nothing will show on the published page.',
							'scriptdock'
						) }
					</span>
					<button
						type="button"
						className="sd-snippetblock__link"
						onClick={ () => setAttributes( { snippetId: 0 } ) }
					>
						{ __( 'Choose another', 'scriptdock' ) }
					</button>
				</div>
			</div>
		);
	}

	if ( chosen ) {
		return (
			<div { ...blockProps }>
				<div className="sd-snippetblock__card sd-snippetblock__card--set">
					<Dot small />
					<span className="sd-snippetblock__detail">
						<span className="sd-snippetblock__name" dir="auto">
							{ chosen.title }
						</span>
						<span className="sd-snippetblock__meta">
							<Chip snippet={ chosen } />
							<span className="sd-snippetblock__where">
								{ __(
									'Shown on the published page',
									'scriptdock'
								) }
							</span>
						</span>
					</span>
					{ chosen.editUrl && (
						<a
							className="sd-snippetblock__link sd-snippetblock__link--end"
							href={ chosen.editUrl }
							target="_blank"
							rel="noreferrer"
						>
							{ __( 'Edit snippet ↗', 'scriptdock' ) }
						</a>
					) }
				</div>
			</div>
		);
	}

	if ( ! boot.snippets.length ) {
		return (
			<div { ...blockProps }>
				<div className="sd-snippetblock__card sd-snippetblock__card--empty">
					<span className="sd-snippetblock__head">
						<Dot dim />
						<span className="sd-snippetblock__title is-dim">
							{ __( 'No snippets available yet', 'scriptdock' ) }
						</span>
					</span>
					<span className="sd-snippetblock__text">
						{ __(
							'Only snippets set to “Shortcode or block only” appear here.',
							'scriptdock'
						) }
					</span>
					{ boot.newUrl && (
						<a
							className="sd-snippetblock__link"
							href={ boot.newUrl }
							target="_blank"
							rel="noreferrer"
						>
							{ __( 'Create a snippet ↗', 'scriptdock' ) }
						</a>
					) }
				</div>
			</div>
		);
	}

	return (
		<div { ...blockProps }>
			<div className="sd-snippetblock__card sd-snippetblock__card--empty">
				<span className="sd-snippetblock__head">
					<Dot />
					<span className="sd-snippetblock__title">
						{ __( 'ScriptDock Snippet', 'scriptdock' ) }
					</span>
				</span>
				<span className="sd-snippetblock__text">
					{ __(
						'Pick a snippet set to “Shortcode or block only”.',
						'scriptdock'
					) }
				</span>
				<select
					className="sd-snippetblock__select"
					aria-label={ __( 'Snippet to show', 'scriptdock' ) }
					value={ id }
					onChange={ ( event ) =>
						setAttributes( {
							snippetId: Number( event.target.value ),
						} )
					}
				>
					<option value="0">
						{ __( 'Choose a snippet…', 'scriptdock' ) }
					</option>
					{ boot.snippets.map( ( snippet ) => (
						<option key={ snippet.id } value={ snippet.id }>
							{ `${ snippet.title } (${ snippet.typeLabel })` }
						</option>
					) ) }
				</select>
			</div>
		</div>
	);
}

/**
 * ScriptDock's dot, for the inserter and the block list.
 *
 * @return {Element} The icon.
 */
function BlockIcon() {
	return (
		<svg viewBox="0 0 24 24" width="24" height="24" aria-hidden="true">
			<defs>
				<linearGradient id="sd-block-dot" x1="4" y1="4" x2="20" y2="20">
					<stop offset="0" stopColor="#fb8e01" />
					<stop offset="1" stopColor="#c2410c" />
				</linearGradient>
			</defs>
			<circle cx="12" cy="12" r="9" fill="url(#sd-block-dot)" />
		</svg>
	);
}

/**
 * Registers the block with the editor.
 */
export default function registerSnippetBlock() {
	registerBlockType( metadata, {
		icon: <BlockIcon />,
		edit: Edit,
		save: () => null,
	} );
}
