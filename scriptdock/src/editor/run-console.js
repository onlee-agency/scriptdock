/**
 * What an on-demand snippet printed, in a dark console that rises from the
 * bottom of the screen.
 */
import { useEffect, useRef, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { speak } from '@wordpress/a11y';
import { Icon, IconButton, copyText, cx } from '../components';

/**
 * @param {Object}   props         Props.
 * @param {Object}   props.result  { ok, output, ms, message }.
 * @param {string}   props.title   Snippet title.
 * @param {Function} props.onClose Closes the console.
 * @return {Element} The console.
 */
export default function RunConsole( { result, title, onClose } ) {
	const panel = useRef();
	const [ copied, setCopied ] = useState( false );

	// Move focus in, so keyboard and screen reader users meet the output.
	useEffect( () => {
		panel.current?.focus();
	}, [ result ] );

	// Escape closes it while focus is inside.
	useEffect( () => {
		const onKeyDown = ( event ) => {
			if (
				event.key === 'Escape' &&
				panel.current?.contains(
					event.target.ownerDocument.activeElement
				)
			) {
				onClose();
			}
		};
		document.addEventListener( 'keydown', onKeyDown );
		return () => document.removeEventListener( 'keydown', onKeyDown );
	}, [ onClose ] );

	let ran = __( 'Ran', 'scriptdock' );
	if ( result.ms !== undefined ) {
		ran =
			result.ms < 1000
				? sprintf(
						/* translators: %d: milliseconds. */
						__( 'Ran in %d ms', 'scriptdock' ),
						result.ms
					)
				: sprintf(
						/* translators: %s: seconds, for example "1.4". */
						__( 'Ran in %ss', 'scriptdock' ),
						( result.ms / 1000 ).toFixed( 1 )
					);
	}
	const output = result.ok
		? result.output || __( '(no output)', 'scriptdock' )
		: result.message;

	return (
		<section
			ref={ panel }
			className={ cx( 'sd-console', ! result.ok && 'is-error' ) }
			aria-label={ __( 'Run output', 'scriptdock' ) }
			tabIndex={ -1 }
		>
			<div className="sd-console__head">
				<span className="sd-console__status">
					<Icon
						name={ result.ok ? 'check' : 'alert' }
						size={ 14 }
						stroke={ 2.4 }
					/>
					{ result.ok
						? ran
						: __( 'It stopped with an error', 'scriptdock' ) }
				</span>
				<span className="sd-console__title">
					{ sprintf(
						/* translators: %s: snippet title. */
						__( '%s · on demand', 'scriptdock' ),
						title || __( 'Untitled snippet', 'scriptdock' )
					) }
				</span>
				<span className="sd-console__tools">
					{ result.ok && result.output && (
						<button
							type="button"
							className="sd-console__copy"
							onClick={ () =>
								copyText( result.output )
									.then( () => {
										setCopied( true );
										speak(
											__( 'Output copied.', 'scriptdock' )
										);
										window.setTimeout(
											() => setCopied( false ),
											2000
										);
									} )
									.catch( () => {} )
							}
						>
							{ copied
								? __( 'Copied', 'scriptdock' )
								: __( 'Copy', 'scriptdock' ) }
						</button>
					) }
					<IconButton
						icon="close"
						label={ __( 'Close the output', 'scriptdock' ) }
						variant="on-ink"
						size="xs"
						onClick={ onClose }
					/>
				</span>
			</div>
			<pre className="sd-console__output" dir="ltr">
				{ output }
			</pre>
		</section>
	);
}
