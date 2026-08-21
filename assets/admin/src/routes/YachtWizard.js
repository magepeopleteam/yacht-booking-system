import { __, sprintf } from '@wordpress/i18n';
import { useEffect, useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { api } from '../api/client';
import { navigate } from '../router';
import { toast } from '../components/Toast';
import MapPicker from '../components/MapPicker';
import RepeatableRows from '../components/RepeatableRows';
import ClassicEditor from '../components/ClassicEditor';
import FaqEditor from '../components/FaqEditor';
import MediaPicker from '../components/MediaPicker';
import GalleryPicker from '../components/GalleryPicker';
import TagSelect from '../components/TagSelect';
import PublishBox from '../components/PublishBox';
import PaymentSettingsCard from '../components/PaymentSettingsCard';

const STEPS = [
	{ key: 'basic', label: __( 'Basic Info', 'yacht-booking-system' ) },
	{ key: 'specs', label: __( 'Specs & Capacity', 'yacht-booking-system' ) },
	{ key: 'pricing', label: __( 'Pricing & Availability', 'yacht-booking-system' ) },
	{ key: 'review', label: __( 'Review & Publish', 'yacht-booking-system' ) },
];

const DEFAULT_FORM = {
	title: '',
	description: '',
	status: 'draft',
	featured_media: 0,
	thumbnail: '',
	gallery: [],
	location_name: '',
	location_lat: '',
	location_lng: '',
	build_year: '',
	yacht_class: [],
	yacht_occasion: [],
	faq: [],
	capacity: '',
	cabins: '',
	crew_size: '',
	length: '',
	booking_mode: 'full',
	included_items: [],
	base_price_hourly: '',
	base_price_daily: '',
	base_price_multiday: '',
	base_price_halfday: '',
	base_price_morning_slot: '',
	base_price_evening_slot: '',
	min_notice_hours: '',
	buffer_minutes: '',
	min_duration: '',
	max_duration: '',
	off_days: [],
	slug: '',
	permalink: '',
	daily_start_time: '08:00',
	daily_end_time: '20:00',
	halfday_start_time: '08:00',
	halfday_end_time: '12:00',
	morning_slot_start: '08:00',
	morning_slot_end: '13:00',
	evening_slot_start: '15:00',
	evening_slot_end: '20:00',
};

function Field( { label, hint, error, children } ) {
	return (
		<div className={ 'ybs-field' + ( error ? ' has-error' : '' ) }>
			<label>{ label }</label>
			{ children }
			{ error && <p className="ybs-field__error">{ error }</p> }
			{ ! error && hint && <p className="ybs-hint">{ hint }</p> }
		</div>
	);
}

function Card( { title, subtitle, children } ) {
	return (
		<div className="ybs-wcard">
			{ title && (
				<div className="ybs-wcard__head">
					<h3>{ title }</h3>
					{ subtitle && <p>{ subtitle }</p> }
				</div>
			) }
			<div className="ybs-wcard__body">{ children }</div>
		</div>
	);
}

/** Only step 1 gates forward progress - mirrors the eventpress wizard's `validateBasicStep()`. */
function validateBasicInfo( form ) {
	const errors = {};

	if ( ! form.title || ! form.title.trim() ) {
		errors.title = __( 'Please give this yacht a name.', 'yacht-booking-system' );
	}

	return errors;
}

export default function YachtWizard( { yachtId } ) {
	const [ step, setStep ] = useState( 1 );
	const [ form, setForm ] = useState( DEFAULT_FORM );
	const [ errors, setErrors ] = useState( {} );
	const [ taxonomies, setTaxonomies ] = useState( { classes: [], occasions: [] } );
	const [ loading, setLoading ] = useState( !! yachtId );
	const [ saving, setSaving ] = useState( false );

	const set = ( key, value ) => setForm( ( prev ) => ( { ...prev, [ key ]: value } ) );

	useEffect( () => {
		Promise.all( [
			apiFetchTerms( 'yacht_class' ),
			apiFetchTerms( 'yacht_occasion' ),
		] ).then( ( [ classes, occasions ] ) => setTaxonomies( { classes, occasions } ) );

		if ( yachtId ) {
			api.get( `/yachts/${ yachtId }` )
				.then( ( data ) => {
					setForm( ( prev ) => ( { ...prev, ...data } ) );
					setLoading( false );
				} )
				.catch( ( err ) => {
					toast( err.message, 'error' );
					setLoading( false );
				} );
		}
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ yachtId ] );

	const goNext = () => {
		if ( 1 === step ) {
			const stepErrors = validateBasicInfo( form );

			if ( Object.keys( stepErrors ).length ) {
				setErrors( stepErrors );
				toast( __( 'Please fix the highlighted field before continuing.', 'yacht-booking-system' ), 'error' );
				return;
			}
		}

		setErrors( {} );
		setStep( ( s ) => Math.min( STEPS.length, s + 1 ) );
	};

	const save = ( status, { silent } = {} ) => {
		setSaving( true );

		const payload = { ...form, status: status || form.status || 'draft' };
		const request = yachtId ? api.put( `/yachts/${ yachtId }`, payload ) : api.post( '/yachts', payload );

		return request
			.then( ( data ) => {
				setSaving( false );
				setForm( ( prev ) => ( { ...prev, ...data } ) );

				if ( ! silent ) {
					toast(
						'publish' === status
							? __( 'Yacht published.', 'yacht-booking-system' )
							: __( 'Draft saved.', 'yacht-booking-system' )
					);
				}

				if ( ! yachtId ) {
					navigate( `yachts/${ data.id }/edit` );
				}

				if ( 'publish' === status ) {
					navigate( 'yachts' );
				}

				return data;
			} )
			.catch( ( err ) => {
				setSaving( false );
				toast( err.message, 'error' );
				throw err;
			} );
	};

	if ( loading ) {
		return <div className="ybs-loading">{ __( 'Loading…', 'yacht-booking-system' ) }</div>;
	}

	return (
		<div className="ybs-wizard">
			<div className="ybs-page-header">
				<div>
					<h2>{ yachtId ? __( 'Edit Yacht', 'yacht-booking-system' ) : __( 'Add New Yacht', 'yacht-booking-system' ) }</h2>
				</div>
			</div>

			<nav className="ybs-wizard-steps" aria-label={ __( 'Yacht setup steps', 'yacht-booking-system' ) }>
				{ STEPS.map( ( item, index ) => (
					<div
						key={ item.key }
						data-step={ index + 1 }
						className={
							'ybs-wizard-steps__step' +
							( step === index + 1 ? ' is-active' : step > index + 1 ? ' is-done' : '' )
						}
					>
						{ item.label }
					</div>
				) ) }
			</nav>

			<div className="ybs-wizard-grid">
				<div className="ybs-wizard-grid__main">
					{ 1 === step && <StepBasicInfo form={ form } set={ set } errors={ errors } /> }
					{ 2 === step && <StepSpecs form={ form } set={ set } /> }
					{ 3 === step && <StepPricing form={ form } set={ set } /> }
					{ 4 === step && <StepReview form={ form } /> }
				</div>

				<aside className="ybs-wizard-sidebar">
					<PublishBox
						status={ form.status }
						slug={ form.slug }
						title={ form.title }
						permalink={ form.permalink }
						saving={ saving }
						onSlugChange={ ( value ) => set( 'slug', value ) }
						onSaveDraft={ () => save( 'draft' ) }
						onPublish={ () => save( 'publish' ) }
					/>

					<PaymentSettingsCard />

					{ 1 === step && (
						<Step1Sidebar form={ form } set={ set } taxonomies={ taxonomies } setTaxonomies={ setTaxonomies } />
					) }
				</aside>
			</div>

			<footer className="ybs-wizard-footer">
				<button className="ybs-btn" onClick={ () => setStep( ( s ) => Math.max( 1, s - 1 ) ) } disabled={ 1 === step }>
					{ __( '← Back', 'yacht-booking-system' ) }
				</button>

				<span className="ybs-wizard-footer__progress">
					{ sprintf(
						/* translators: 1: current step, 2: total steps */
						__( 'Step %1$d of %2$d', 'yacht-booking-system' ),
						step,
						STEPS.length
					) }
				</span>

				{ step < STEPS.length ? (
					<button className="ybs-btn is-primary" onClick={ goNext }>
						{ __( 'Next →', 'yacht-booking-system' ) }
					</button>
				) : (
					<span />
				) }
			</footer>
		</div>
	);
}

function apiFetchTerms( taxonomy ) {
	return apiFetch( { path: `/wp/v2/${ taxonomy }?per_page=100` } ).catch( () => [] );
}

function StepBasicInfo( { form, set, errors } ) {
	return (
		<>
			<Card
				title={ __( 'Basic Information', 'yacht-booking-system' ) }
				subtitle={ __( 'The name and story guests see first.', 'yacht-booking-system' ) }
			>
				<Field label={ __( 'Yacht Name', 'yacht-booking-system' ) } error={ errors.title }>
					<input type="text" value={ form.title } onChange={ ( e ) => set( 'title', e.target.value ) } />
				</Field>

				<Field label={ __( 'Description', 'yacht-booking-system' ) }>
					<ClassicEditor
						id="ybs_yacht_description"
						value={ form.description }
						onChange={ ( html ) => set( 'description', html ) }
					/>
				</Field>

				<div className="ybs-field-row">
					<Field label={ __( 'Build Year', 'yacht-booking-system' ) }>
						<input type="number" value={ form.build_year } onChange={ ( e ) => set( 'build_year', e.target.value ) } />
					</Field>
					<Field label={ __( 'Marina / Pier Name', 'yacht-booking-system' ) }>
						<input type="text" value={ form.location_name } onChange={ ( e ) => set( 'location_name', e.target.value ) } />
					</Field>
				</div>
			</Card>

			<Card
				title={ __( 'Departure Point', 'yacht-booking-system' ) }
				subtitle={ __( 'Search for the marina or pier, or drop the pin manually.', 'yacht-booking-system' ) }
			>
				<MapPicker
					lat={ form.location_lat }
					lng={ form.location_lng }
					onChange={ ( lat, lng, label ) => {
						set( 'location_lat', lat );
						set( 'location_lng', lng );

						if ( label ) {
							set( 'location_name', label );
						}
					} }
				/>
			</Card>

			<Card
				title={ __( 'Frequently Asked Questions', 'yacht-booking-system' ) }
				subtitle={ __( 'Shown on the yacht listing page.', 'yacht-booking-system' ) }
			>
				<FaqEditor items={ form.faq } onChange={ ( items ) => set( 'faq', items ) } />
			</Card>
		</>
	);
}

/** Media/taxonomy cards only make sense while editing Basic Info - shown in the sidebar only on step 1. */
function Step1Sidebar( { form, set, taxonomies, setTaxonomies } ) {
	return (
		<>
			<Card title={ __( 'Featured Image', 'yacht-booking-system' ) }>
				<MediaPicker
					id={ form.featured_media }
					url={ form.thumbnail }
					onChange={ ( id, url ) => {
						set( 'featured_media', id );
						set( 'thumbnail', url );
					} }
				/>
			</Card>

			<Card title={ __( 'Gallery', 'yacht-booking-system' ) } subtitle={ __( 'Shown on the yacht listing page.', 'yacht-booking-system' ) }>
				<GalleryPicker items={ form.gallery } onChange={ ( items ) => set( 'gallery', items ) } />
			</Card>

			<Card title={ __( 'Yacht Class', 'yacht-booking-system' ) }>
				<TagSelect
					label={ __( 'Class', 'yacht-booking-system' ) }
					taxonomy="yacht_class"
					terms={ taxonomies.classes }
					selected={ form.yacht_class }
					onChange={ ( ids ) => set( 'yacht_class', ids ) }
					onTermsChange={ ( classes ) => setTaxonomies( ( prev ) => ( { ...prev, classes } ) ) }
				/>
			</Card>

			<Card title={ __( 'Occasion Tags', 'yacht-booking-system' ) }>
				<TagSelect
					label={ __( 'Occasion', 'yacht-booking-system' ) }
					taxonomy="yacht_occasion"
					terms={ taxonomies.occasions }
					selected={ form.yacht_occasion }
					onChange={ ( ids ) => set( 'yacht_occasion', ids ) }
					onTermsChange={ ( occasions ) => setTaxonomies( ( prev ) => ( { ...prev, occasions } ) ) }
				/>
			</Card>
		</>
	);
}

function StepSpecs( { form, set } ) {
	return (
		<Card
			title={ __( 'Specs & Capacity', 'yacht-booking-system' ) }
			subtitle={ __( 'How many guests, and what comes with every charter.', 'yacht-booking-system' ) }
		>
			<div className="ybs-field-row">
				<Field label={ __( 'Capacity (guests)', 'yacht-booking-system' ) }>
					<input type="number" value={ form.capacity } onChange={ ( e ) => set( 'capacity', e.target.value ) } />
				</Field>
				<Field label={ __( 'Cabins', 'yacht-booking-system' ) }>
					<input type="number" value={ form.cabins } onChange={ ( e ) => set( 'cabins', e.target.value ) } />
				</Field>
				<Field label={ __( 'Crew Size', 'yacht-booking-system' ) }>
					<input type="number" value={ form.crew_size } onChange={ ( e ) => set( 'crew_size', e.target.value ) } />
				</Field>
				<Field label={ __( 'Length (ft)', 'yacht-booking-system' ) }>
					<input type="number" value={ form.length } onChange={ ( e ) => set( 'length', e.target.value ) } />
				</Field>
			</div>

			<Field label={ __( 'Booking Mode', 'yacht-booking-system' ) } hint={ __( 'Full charter, shared by seat, or both.', 'yacht-booking-system' ) }>
				<select value={ form.booking_mode } onChange={ ( e ) => set( 'booking_mode', e.target.value ) }>
					<option value="full">{ __( 'Full Charter', 'yacht-booking-system' ) }</option>
					<option value="shared">{ __( 'Shared', 'yacht-booking-system' ) }</option>
					<option value="both">{ __( 'Both', 'yacht-booking-system' ) }</option>
				</select>
			</Field>

			<Field label={ __( 'Included in Every Charter', 'yacht-booking-system' ) }>
				<RepeatableRows
					items={ form.included_items }
					onChange={ ( items ) => set( 'included_items', items ) }
					emptyItem={ { text: '' } }
					addLabel={ __( '+ Add Item', 'yacht-booking-system' ) }
					fields={ [ { key: 'text', label: __( 'e.g. Bottled water, life jackets…', 'yacht-booking-system' ) } ] }
				/>
			</Field>
		</Card>
	);
}

function StepPricing( { form, set } ) {
	return (
		<>
			<Card
				title={ __( 'Base Rates', 'yacht-booking-system' ) }
				subtitle={ __( 'Leave a rate blank to disable that booking type for this yacht.', 'yacht-booking-system' ) }
			>
				<div className="ybs-field-row">
					<Field label={ __( 'Hourly Rate', 'yacht-booking-system' ) }>
						<input type="number" value={ form.base_price_hourly } onChange={ ( e ) => set( 'base_price_hourly', e.target.value ) } />
					</Field>
					<Field label={ __( 'Half-Day Rate', 'yacht-booking-system' ) }>
						<input type="number" value={ form.base_price_halfday } onChange={ ( e ) => set( 'base_price_halfday', e.target.value ) } />
					</Field>
					<Field label={ __( 'Morning Slot Rate', 'yacht-booking-system' ) }>
						<input type="number" value={ form.base_price_morning_slot } onChange={ ( e ) => set( 'base_price_morning_slot', e.target.value ) } />
					</Field>
					<Field label={ __( 'Evening / Sunset Slot Rate', 'yacht-booking-system' ) }>
						<input type="number" value={ form.base_price_evening_slot } onChange={ ( e ) => set( 'base_price_evening_slot', e.target.value ) } />
					</Field>
					<Field label={ __( 'Daily Rate', 'yacht-booking-system' ) }>
						<input type="number" value={ form.base_price_daily } onChange={ ( e ) => set( 'base_price_daily', e.target.value ) } />
					</Field>
					<Field label={ __( 'Multi-Day Rate (per day)', 'yacht-booking-system' ) }>
						<input type="number" value={ form.base_price_multiday } onChange={ ( e ) => set( 'base_price_multiday', e.target.value ) } />
					</Field>
				</div>
			</Card>

			<Card
				title={ __( 'Time Configuration', 'yacht-booking-system' ) }
				subtitle={ __( 'The clock-time window each fixed-schedule booking type runs within.', 'yacht-booking-system' ) }
			>
				<div className="ybs-field-row">
					<Field label={ __( 'Daily Charter Start', 'yacht-booking-system' ) }>
						<input type="time" value={ form.daily_start_time } onChange={ ( e ) => set( 'daily_start_time', e.target.value ) } />
					</Field>
					<Field label={ __( 'Daily Charter End', 'yacht-booking-system' ) }>
						<input type="time" value={ form.daily_end_time } onChange={ ( e ) => set( 'daily_end_time', e.target.value ) } />
					</Field>
				</div>

				<div className="ybs-field-row">
					<Field label={ __( 'Half-Day Slot Start', 'yacht-booking-system' ) }>
						<input type="time" value={ form.halfday_start_time } onChange={ ( e ) => set( 'halfday_start_time', e.target.value ) } />
					</Field>
					<Field label={ __( 'Half-Day Slot End', 'yacht-booking-system' ) }>
						<input type="time" value={ form.halfday_end_time } onChange={ ( e ) => set( 'halfday_end_time', e.target.value ) } />
					</Field>
				</div>

				<div className="ybs-field-row">
					<Field label={ __( 'Morning Slot Start', 'yacht-booking-system' ) }>
						<input type="time" value={ form.morning_slot_start } onChange={ ( e ) => set( 'morning_slot_start', e.target.value ) } />
					</Field>
					<Field label={ __( 'Morning Slot End', 'yacht-booking-system' ) }>
						<input type="time" value={ form.morning_slot_end } onChange={ ( e ) => set( 'morning_slot_end', e.target.value ) } />
					</Field>
				</div>

				<div className="ybs-field-row">
					<Field label={ __( 'Evening / Sunset Slot Start', 'yacht-booking-system' ) }>
						<input type="time" value={ form.evening_slot_start } onChange={ ( e ) => set( 'evening_slot_start', e.target.value ) } />
					</Field>
					<Field label={ __( 'Evening / Sunset Slot End', 'yacht-booking-system' ) }>
						<input type="time" value={ form.evening_slot_end } onChange={ ( e ) => set( 'evening_slot_end', e.target.value ) } />
					</Field>
				</div>
			</Card>

			<Card
				title={ __( 'Booking Rules', 'yacht-booking-system' ) }
				subtitle={ __( 'Notice period, spacing between charters, and duration limits.', 'yacht-booking-system' ) }
			>
				<div className="ybs-field-row">
					<Field label={ __( 'Minimum Notice (hours)', 'yacht-booking-system' ) }>
						<input type="number" value={ form.min_notice_hours } onChange={ ( e ) => set( 'min_notice_hours', e.target.value ) } />
					</Field>
					<Field label={ __( 'Buffer Between Bookings (minutes)', 'yacht-booking-system' ) }>
						<input type="number" value={ form.buffer_minutes } onChange={ ( e ) => set( 'buffer_minutes', e.target.value ) } />
					</Field>
					<Field label={ __( 'Min Duration (minutes, hourly)', 'yacht-booking-system' ) }>
						<input type="number" value={ form.min_duration } onChange={ ( e ) => set( 'min_duration', e.target.value ) } />
					</Field>
					<Field label={ __( 'Max Duration (minutes, hourly)', 'yacht-booking-system' ) }>
						<input type="number" value={ form.max_duration } onChange={ ( e ) => set( 'max_duration', e.target.value ) } />
					</Field>
				</div>
			</Card>

			<Card title={ __( 'Off-Days', 'yacht-booking-system' ) } subtitle={ __( 'Dates this yacht cannot be booked.', 'yacht-booking-system' ) }>
				<RepeatableRows
					items={ form.off_days.map( ( date ) => ( { date } ) ) }
					onChange={ ( items ) => set( 'off_days', items.map( ( item ) => item.date ) ) }
					emptyItem={ { date: '' } }
					addLabel={ __( '+ Add Off-Day', 'yacht-booking-system' ) }
					fields={ [ { key: 'date', label: __( 'Off-Day date', 'yacht-booking-system' ), type: 'date' } ] }
				/>
			</Card>
		</>
	);
}

function StepReview( { form } ) {
	return (
		<Card
			title={ form.title || __( '(Untitled Yacht)', 'yacht-booking-system' ) }
			subtitle={ __( 'Review the details, then publish to make this yacht bookable on the frontend.', 'yacht-booking-system' ) }
		>
			<p>{ form.description }</p>
			<table className="ybs-table">
				<tbody>
					<tr><th>{ __( 'Capacity', 'yacht-booking-system' ) }</th><td>{ form.capacity }</td></tr>
					<tr><th>{ __( 'Cabins', 'yacht-booking-system' ) }</th><td>{ form.cabins }</td></tr>
					<tr><th>{ __( 'Booking Mode', 'yacht-booking-system' ) }</th><td>{ form.booking_mode }</td></tr>
					<tr><th>{ __( 'Hourly Rate', 'yacht-booking-system' ) }</th><td>{ form.base_price_hourly }</td></tr>
					<tr><th>{ __( 'Daily Rate', 'yacht-booking-system' ) }</th><td>{ form.base_price_daily }</td></tr>
					<tr><th>{ __( 'Location', 'yacht-booking-system' ) }</th><td>{ form.location_name }</td></tr>
				</tbody>
			</table>
		</Card>
	);
}
