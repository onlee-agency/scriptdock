/**
 * Step 5: the whole thing in one sentence, with a card per step to go back to.
 */
import { useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { Button, TargetingSentence } from '../components';
import * as api from './api';
import sentenceParts from './sentence';
import { counts, toConditions } from './model';

const CARDS = [
	{ step: 'placement', label: __( 'Placement', 'scriptdock' ) },
	{ step: 'content', label: __( 'Pages', 'scriptdock' ) },
	{ step: 'audience', label: __( 'Audience', 'scriptdock' ) },
	{ step: 'schedule', label: __( 'Schedule', 'scriptdock' ) },
];

/**
 * @param {Object}   props           Props.
 * @param {Object}   props.draft     Draft.
 * @param {string}   props.type      Code type.
 * @param {Array}    props.locations Placements.
 * @param {Object}   props.options   Catalogue and lists.
 * @param {Function} props.onStep    Goes back to a step.
 * @return {Element} The step.
 */
export default function ReviewStep( {
	draft,
	type,
	locations = [],
	options,
	onStep,
} ) {
	const [ described, setDescribed ] = useState( null );

	useEffect( () => {
		api.describe( {
			type,
			location: draft.location,
			location_args: draft.locationArgs,
			conditions: toConditions( draft ),
			schedule: draft.schedule,
		} )
			.then( setDescribed )
			.catch( () => setDescribed( null ) );
	}, [ draft, type ] );

	const parts = sentenceParts( described?.targeting?.parts || [], ( step ) =>
		onStep( stepOf( step ) )
	);
	const totals = counts( draft );

	return (
		<div className="sd-step sd-step--review">
			<section className="sd-review__sentence">
				<TargetingSentence parts={ parts } size="lg" />
				<p className="sd-review__hint">
					{ __(
						'Every highlighted part jumps back to its step.',
						'scriptdock'
					) }
				</p>
			</section>

			<div className="sd-review__cards">
				{ CARDS.map( ( card ) => (
					<section key={ card.step } className="sd-review__card">
						<h3 className="sd-review__title">
							{ card.label }
							<Button
								variant="ghost"
								size="sm"
								onClick={ () => onStep( card.step ) }
							>
								{ __( 'Edit', 'scriptdock' ) }
							</Button>
						</h3>
						<p className="sd-review__line">
							{ line(
								card.step,
								described,
								draft,
								totals,
								locations
							) }
						</p>
						{ note( card.step, draft, locations, options ) && (
							<p className="sd-review__note">
								{ note( card.step, draft, locations, options ) }
							</p>
						) }
					</section>
				) ) }
			</div>
		</div>
	);
}

/**
 * Which step a sentence chip belongs to.
 *
 * @param {string} step Step named by the REST layer.
 * @return {string} Step key the wizard uses.
 */
function stepOf( step ) {
	return (
		{
			placement: 'placement',
			content: 'content',
			audience: 'audience',
			schedule: 'schedule',
		}[ step ] || 'placement'
	);
}

/**
 * The second line on a summary card: the detail the sentence leaves out.
 *
 * @param {string} step      Step key.
 * @param {Object} draft     Draft.
 * @param {Array}  locations Placements.
 * @param {Object} options   Catalogue and lists.
 * @return {string} The note, or an empty string.
 */
function note( step, draft, locations, options ) {
	if ( step === 'placement' ) {
		const hook = draft.locationArgs?.hook;
		return [
			sprintf(
				/* translators: %d: priority number. */
				__( 'Priority %d', 'scriptdock' ),
				draft.priority
			),
			hook,
		]
			.filter( Boolean )
			.join( ' · ' );
	}
	if ( step === 'schedule' ) {
		const zone = options?.timezone || '';
		const ends = draft.schedule.end
			? ''
			: __( 'no end date', 'scriptdock' );
		return [ zone, ends ].filter( Boolean ).join( ' · ' );
	}
	return '';
}

/**
 * What a summary card says.
 *
 * @param {string} step      Step key.
 * @param {Object} described Answer from the REST layer.
 * @param {Object} draft     Draft.
 * @param {Object} totals    Counts per step.
 * @param {Array}  locations Placements.
 * @return {string} The line.
 */
function line( step, described, draft, totals, locations = [] ) {
	if ( step === 'placement' ) {
		return (
			locations.find( ( entry ) => entry.value === draft.location )
				?.label || draft.location
		);
	}

	const chips = ( described?.targeting?.parts || [] )
		.filter( ( part ) => part.chip && part.step === step )
		.map( ( part ) => part.chip );

	if ( chips.length ) {
		return chips.join( ' · ' );
	}
	if ( step === 'content' ) {
		return totals.content
			? __( 'Some pages', 'scriptdock' )
			: __( 'Every page', 'scriptdock' );
	}
	if ( step === 'audience' ) {
		return __( 'Everyone', 'scriptdock' );
	}
	return __( 'Always', 'scriptdock' );
}
