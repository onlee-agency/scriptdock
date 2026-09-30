/**
 * The plain-language sentence, as the REST layer sends it: plain pieces carry
 * `text`, the highlighted ones carry `chip` and the step they came from.
 */

/**
 * Turns the answer's parts into what TargetingSentence draws.
 *
 * @param {Array}    parts  Parts from the REST layer.
 * @param {Function} onChip Called with a chip's step when it is clicked; left
 *                          out, the chips are not buttons.
 * @return {Array} Sentence parts.
 */
export default function sentenceParts( parts = [], onChip ) {
	return parts.map( ( part, index ) => {
		if ( part.text !== undefined ) {
			return part.text;
		}
		return {
			key: `${ part.step }-${ index }`,
			label: part.chip,
			code: !! part.code,
			...( onChip && part.step
				? { onClick: () => onChip( part.step ) }
				: {} ),
		};
	} );
}
