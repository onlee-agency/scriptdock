/**
 * Step 4: when the snippet runs. A start and end date belong to the snippet
 * itself; days and hours are rules, checked on every request.
 */
import { __, sprintf } from '@wordpress/i18n';
import { Icon, TextField, TimeRange, WeekdayChips } from '../components';
import { CardGrid, SelectCard } from './cards';

/**
 * @param {Object}   props         Props.
 * @param {Object}   props.draft   Draft.
 * @param {Function} props.patch   Changes the draft.
 * @param {Object}   props.options Catalogue and lists.
 * @return {Element} The step.
 */
export default function ScheduleStep( { draft, patch, options } ) {
	const { schedule, time } = draft;
	const custom =
		!! schedule.start ||
		!! schedule.end ||
		!! time.days.length ||
		!! time.from;
	const setTime = ( change ) => patch( { time: { ...time, ...change } } );
	const setSchedule = ( change ) =>
		patch( { schedule: { ...schedule, ...change } } );

	return (
		<div className="sd-step sd-step--schedule">
			<CardGrid
				label={ __( 'When it runs', 'scriptdock' ) }
				className="sd-scopes"
			>
				<SelectCard
					title={ __( 'Always', 'scriptdock' ) }
					note={ __( 'No start or end.', 'scriptdock' ) }
					selected={ ! custom }
					tabIndex={ custom ? -1 : 0 }
					onSelect={ () =>
						patch( {
							schedule: { start: '', end: '' },
							time: { days: [], from: '', to: '' },
						} )
					}
				/>
				<SelectCard
					title={ __( 'Custom schedule', 'scriptdock' ) }
					note={ __( 'Pick dates, days and hours.', 'scriptdock' ) }
					selected={ custom }
					tabIndex={ custom ? 0 : -1 }
					onSelect={ () =>
						setTime( { days: time.days.length ? time.days : [] } )
					}
				/>
			</CardGrid>

			<section className="sd-step__section sd-step__card">
				<h3 className="sd-step__heading">
					{ __( 'Start and end', 'scriptdock' ) }
				</h3>
				<div className="sd-step__pair">
					<TextField
						label={ __( 'Starts', 'scriptdock' ) }
						type="datetime-local"
						value={ schedule.start }
						onChange={ ( start ) => setSchedule( { start } ) }
					/>
					<TextField
						label={ __( 'Ends', 'scriptdock' ) }
						type="datetime-local"
						value={ schedule.end }
						onChange={ ( end ) => setSchedule( { end } ) }
					/>
				</div>
				<p className="sd-step__hint">
					{ __(
						'Leave a box empty for no start or no end.',
						'scriptdock'
					) }
				</p>
			</section>

			<section className="sd-step__section sd-step__card">
				<h3 className="sd-step__heading">
					{ __( 'Days', 'scriptdock' ) }
				</h3>
				<WeekdayChips
					label={ __( 'Days of the week', 'scriptdock' ) }
					days={ ( options?.lists?.day_of_week || [] ).map(
						( day ) => ( {
							value: day.value,
							name: day.label,
							label: day.short || day.label,
						} )
					) }
					value={ time.days }
					onChange={ ( days ) => setTime( { days } ) }
				/>
			</section>

			<section className="sd-step__section sd-step__card">
				<h3 className="sd-step__heading">
					{ __( 'Time of day', 'scriptdock' ) }
				</h3>
				<TimeRange
					label={ __( 'Between', 'scriptdock' ) }
					from={ time.from }
					to={ time.to }
					onChange={ ( range ) => setTime( range ) }
				/>
				<p className="sd-step__hint">
					<Icon name="info" size={ 13 } stroke={ 1.8 } />
					{ sprintf(
						/* translators: %s: the site's timezone, for example "Europe/Berlin". */
						__( 'Site timezone: %s', 'scriptdock' ),
						options?.timezone || ''
					) }
				</p>
			</section>
		</div>
	);
}
