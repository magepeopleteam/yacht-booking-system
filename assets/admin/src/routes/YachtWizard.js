import { __, sprintf } from '@wordpress/i18n';
import { useEffect, useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { api } from '../api/client';
import { navigate } from '../router';
import { toast } from '../components/Toast';
import MapPicker from '../components/MapPicker';
import RepeatableRows from '../components/RepeatableRows';
import ClassicEditor, { insertIntoEditor } from '../components/ClassicEditor';
import EmailVariables from '../components/EmailVariables';
import TestEmailModal from '../components/TestEmailModal';
import FaqEditor from '../components/FaqEditor';
import MediaPicker from '../components/MediaPicker';
import GalleryPicker from '../components/GalleryPicker';
import RelatedYachtsPicker from '../components/RelatedYachtsPicker';
import TagSelect from '../components/TagSelect';
import PublishBox from '../components/PublishBox';
import PaymentSettingsCard from '../components/PaymentSettingsCard';
import TimeField from '../components/TimeField';

const STEPS = [
	{ key: 'basic', label: __('Basic Info', 'magepeople-yacht-booking-system') },
	{ key: 'specs', label: __('Specs & Capacity', 'magepeople-yacht-booking-system') },
	{ key: 'pricing', label: __('Pricing & Availability', 'magepeople-yacht-booking-system') },
	{ key: 'review', label: __('Review & Publish', 'magepeople-yacht-booking-system') },
];

const DEFAULT_FORM = {
	title: '',
	description: '',
	status: 'draft',
	featured_media: 0,
	thumbnail: '',
	gallery: [],
	related_yachts: [],
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
	base_price_shared_hourly: '',
	base_price_shared_daily: '',
	base_price_shared_multiday: '',
	base_price_shared_halfday: '',
	base_price_shared_morning_slot: '',
	base_price_shared_evening_slot: '',
	deposit_mode: '',
	deposit_value: '',
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
	confirmation_email_subject: '',
	confirmation_email_body: '',
	cta_heading: '',
	cta_text: '',
	cta_disabled: false,
};

function Field({ label, hint, error, children }) {
	return (
		<div className={'ybs-field' + (error ? ' has-error' : '')}>
			<label>{label}</label>
			{children}
			{error && <p className="ybs-field__error">{error}</p>}
			{!error && hint && <p className="ybs-hint">{hint}</p>}
		</div>
	);
}

function Card({ title, subtitle, children }) {
	return (
		<div className="ybs-wcard">
			{title && (
				<div className="ybs-wcard__head">
					<h3>{title}</h3>
					{subtitle && <p>{subtitle}</p>}
				</div>
			)}
			<div className="ybs-wcard__body">{children}</div>
		</div>
	);
}

/** Only step 1 gates forward progress - mirrors the eventpress wizard's `validateBasicStep()`. */
function validateBasicInfo(form) {
	const errors = {};

	if (!form.title || !form.title.trim()) {
		errors.title = __('Please give this yacht a name.', 'magepeople-yacht-booking-system');
	}

	return errors;
}

export default function YachtWizard({ yachtId }) {
	const [step, setStep] = useState(1);
	const [form, setForm] = useState(DEFAULT_FORM);
	const [errors, setErrors] = useState({});
	const [taxonomies, setTaxonomies] = useState({ classes: [], occasions: [] });
	const [loading, setLoading] = useState(!!yachtId);
	const [saving, setSaving] = useState(false);

	const set = (key, value) => setForm((prev) => ({ ...prev, [key]: value }));

	useEffect(() => {
		Promise.all([
			apiFetchTerms('mageyabo_yacht_class'),
			apiFetchTerms('mageyabo_yacht_occasion'),
		]).then(([classes, occasions]) => setTaxonomies({ classes, occasions }));

		if (yachtId) {
			api.get(`/yachts/${yachtId}/edit`)
				.then((data) => {
					setForm((prev) => ({ ...prev, ...data }));
					setLoading(false);
				})
				.catch((err) => {
					toast(err.message, 'error');
					setLoading(false);
				});
		}
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [yachtId]);

	/** Jumps to any step directly - clicking the nav, not just "Next", can call this. */
	const goToStep = (target) => {
		if (step === target) {
			return;
		}

		// Basic Info is the only step with required fields - leaving it
		// forward (via Next or by clicking straight to a later step) still
		// has to clear that gate; going back to it, or between any of the
		// other steps, is always free.
		if (1 === step && target > step) {
			const stepErrors = validateBasicInfo(form);

			if (Object.keys(stepErrors).length) {
				setErrors(stepErrors);
				toast(__('Please fix the highlighted field before continuing.', 'magepeople-yacht-booking-system'), 'error');
				return;
			}
		}

		setErrors({});
		setStep(Math.max(1, Math.min(STEPS.length, target)));
	};

	const goNext = () => goToStep(step + 1);

	const save = (status, { silent } = {}) => {
		setSaving(true);

		const payload = { ...form, status: status || form.status || 'draft' };
		const request = yachtId ? api.put(`/yachts/${yachtId}`, payload) : api.post('/yachts', payload);

		return request
			.then((data) => {
				setSaving(false);
				setForm((prev) => ({ ...prev, ...data }));

				if (!silent) {
					toast(
						'publish' === status
							? __('Yacht published.', 'magepeople-yacht-booking-system')
							: __('Draft saved.', 'magepeople-yacht-booking-system')
					);
				}

				if (!yachtId) {
					navigate(`yachts/${data.id}/edit`);
				}

				if ('publish' === status) {
					navigate('yachts');
				}

				return data;
			})
			.catch((err) => {
				setSaving(false);
				toast(err.message, 'error');
				throw err;
			});
	};

	if (loading) {
		return <div className="ybs-loading">{__('Loading…', 'magepeople-yacht-booking-system')}</div>;
	}

	return (
		<div className="ybs-wizard">
			<div className="ybs-page-header">
				<div>
					<h2>{yachtId ? __('Edit Yacht', 'magepeople-yacht-booking-system') : __('Add New Yacht', 'magepeople-yacht-booking-system')}</h2>
				</div>
			</div>

			<nav className="ybs-wizard-steps" aria-label={__('Yacht setup steps', 'magepeople-yacht-booking-system')}>
				{STEPS.map((item, index) => (
					<button
						type="button"
						key={item.key}
						data-step={index + 1}
						className={
							'ybs-wizard-steps__step' +
							(step === index + 1 ? ' is-active' : step > index + 1 ? ' is-done' : '')
						}
						aria-current={step === index + 1 ? 'step' : undefined}
						onClick={() => goToStep(index + 1)}
					>
						{item.label}
					</button>
				))}
			</nav>

			<div className="ybs-wizard-grid">
				<div className="ybs-wizard-grid__main">
					{1 === step && <StepBasicInfo form={form} set={set} errors={errors} />}
					{2 === step && <StepSpecs form={form} set={set} />}
					{3 === step && <StepPricing form={form} set={set} yachtId={yachtId || form.id} />}
					{4 === step && <StepReview form={form} set={set} />}
				</div>

				<aside className="ybs-wizard-sidebar">
					<PublishBox
						status={form.status}
						slug={form.slug}
						title={form.title}
						permalink={form.permalink}
						saving={saving}
						onSlugChange={(value) => set('slug', value)}
						onSaveDraft={() => save('draft')}
						onPublish={() => save('publish')}
					/>

					{1 === step && (
						<Card title={__('Featured Image', 'magepeople-yacht-booking-system')}>
							<MediaPicker
								id={form.featured_media}
								url={form.thumbnail}
								onChange={(id, url) => {
									set('featured_media', id);
									set('thumbnail', url);
								}}
							/>
						</Card>
					)}

					<PaymentSettingsCard />

					{1 === step && (
						<Step1Sidebar form={form} set={set} taxonomies={taxonomies} setTaxonomies={setTaxonomies} yachtId={yachtId} />
					)}
				</aside>
			</div>

			<footer className="ybs-wizard-footer">
				<button className="ybs-btn" onClick={() => setStep((s) => Math.max(1, s - 1))} disabled={1 === step}>
					{__('← Back', 'magepeople-yacht-booking-system')}
				</button>

				<span className="ybs-wizard-footer__progress">
					{sprintf(
						/* translators: 1: current step, 2: total steps */
						__('Step %1$d of %2$d', 'magepeople-yacht-booking-system'),
						step,
						STEPS.length
					)}
				</span>

				{step < STEPS.length ? (
					<button className="ybs-btn is-primary" onClick={goNext}>
						{__('Next →', 'magepeople-yacht-booking-system')}
					</button>
				) : (
					<span />
				)}
			</footer>
		</div>
	);
}

function apiFetchTerms(taxonomy) {
	return apiFetch({ path: `/wp/v2/${taxonomy}?per_page=100` }).catch(() => []);
}

function StepBasicInfo({ form, set, errors }) {
	return (
		<>
			<Card
				title={__('Basic Information', 'magepeople-yacht-booking-system')}
				subtitle={__('The name and story guests see first.', 'magepeople-yacht-booking-system')}
			>
				<Field label={__('Yacht Name', 'magepeople-yacht-booking-system')} error={errors.title}>
					<input type="text" value={form.title} onChange={(e) => set('title', e.target.value)} />
				</Field>

				<Field label={__('Description', 'magepeople-yacht-booking-system')}>
					<ClassicEditor
						id="mageyabo_yacht_description"
						value={form.description}
						onChange={(html) => set('description', html)}
					/>
				</Field>

				<div className="ybs-field-row">
					<Field label={__('Build Year', 'magepeople-yacht-booking-system')}>
						<input type="number" value={form.build_year} onChange={(e) => set('build_year', e.target.value)} />
					</Field>
					<Field label={__('Marina / Pier Name', 'magepeople-yacht-booking-system')}>
						<input type="text" value={form.location_name} onChange={(e) => set('location_name', e.target.value)} />
					</Field>
				</div>
			</Card>

			<Card
				title={__('Departure Point', 'magepeople-yacht-booking-system')}
				subtitle={__('Search for the marina or pier, or drop the pin manually.', 'magepeople-yacht-booking-system')}
			>
				<MapPicker
					lat={form.location_lat}
					lng={form.location_lng}
					onChange={(lat, lng, label) => {
						set('location_lat', lat);
						set('location_lng', lng);

						if (label) {
							set('location_name', label);
						}
					}}
				/>
			</Card>

			<Card
				title={__('Frequently Asked Questions', 'magepeople-yacht-booking-system')}
				subtitle={__('Shown on the yacht listing page.', 'magepeople-yacht-booking-system')}
			>
				<FaqEditor items={form.faq} onChange={(items) => set('faq', items)} />
			</Card>
		</>
	);
}

/** Media/taxonomy cards only make sense while editing Basic Info - shown in the sidebar only on step 1. Featured Image sits right under the Publish box instead, higher in the sidebar - see the wizard's aside. */
function Step1Sidebar({ form, set, taxonomies, setTaxonomies, yachtId }) {
	return (
		<>
			<Card title={__('Gallery', 'magepeople-yacht-booking-system')} subtitle={__('Shown on the yacht listing page.', 'magepeople-yacht-booking-system')}>
				<GalleryPicker items={form.gallery} onChange={(items) => set('gallery', items)} />
			</Card>

			<Card
				title={__('Related Yachts', 'magepeople-yacht-booking-system')}
				subtitle={__('Shown as a carousel on this yacht\'s details page.', 'magepeople-yacht-booking-system')}
			>
				<RelatedYachtsPicker
					selected={form.related_yachts}
					onChange={(ids) => set('related_yachts', ids)}
					excludeId={yachtId}
				/>
			</Card>

			<Card title={__('Yacht Class', 'magepeople-yacht-booking-system')}>
				<TagSelect
					label={__('Class', 'magepeople-yacht-booking-system')}
					taxonomy="yacht_class"
					terms={taxonomies.classes}
					selected={form.yacht_class}
					onChange={(ids) => set('mageyabo_yacht_class', ids)}
					onTermsChange={(classes) => setTaxonomies((prev) => ({ ...prev, classes }))}
				/>
			</Card>

			<Card title={__('Occasion Tags', 'magepeople-yacht-booking-system')}>
				<TagSelect
					label={__('Occasion', 'magepeople-yacht-booking-system')}
					taxonomy="yacht_occasion"
					terms={taxonomies.occasions}
					selected={form.yacht_occasion}
					onChange={(ids) => set('mageyabo_yacht_occasion', ids)}
					onTermsChange={(occasions) => setTaxonomies((prev) => ({ ...prev, occasions }))}
				/>
			</Card>
		</>
	);
}

function StepSpecs({ form, set }) {
	return (
		<Card
			title={__('Specs & Capacity', 'magepeople-yacht-booking-system')}
			subtitle={__('How many guests, and what comes with every charter.', 'magepeople-yacht-booking-system')}
		>
			<div className="ybs-field-row">
				<Field label={__('Capacity (guests)', 'magepeople-yacht-booking-system')}>
					<input type="number" value={form.capacity} onChange={(e) => set('capacity', e.target.value)} />
				</Field>
				<Field label={__('Cabins', 'magepeople-yacht-booking-system')}>
					<input type="number" value={form.cabins} onChange={(e) => set('cabins', e.target.value)} />
				</Field>
				<Field label={__('Crew Size', 'magepeople-yacht-booking-system')}>
					<input type="number" value={form.crew_size} onChange={(e) => set('crew_size', e.target.value)} />
				</Field>
				<Field label={__('Length (ft)', 'magepeople-yacht-booking-system')}>
					<input type="number" value={form.length} onChange={(e) => set('length', e.target.value)} />
				</Field>
			</div>

			<Field label={__('Included in Every Charter', 'magepeople-yacht-booking-system')}>
				<RepeatableRows
					items={form.included_items}
					onChange={(items) => set('included_items', items)}
					emptyItem={{ text: '' }}
					addLabel={__('+ Add Item', 'magepeople-yacht-booking-system')}
					fields={[{ key: 'text', label: __('e.g. Bottled water, life jackets…', 'magepeople-yacht-booking-system') }]}
				/>
			</Field>
		</Card>
	);
}

const RATE_FIELDS = [
	{ key: 'hourly', label: __('Hourly Rate', 'magepeople-yacht-booking-system') },
	{ key: 'halfday', label: __('Half-Day Rate', 'magepeople-yacht-booking-system') },
	{ key: 'morning_slot', label: __('Morning Slot Rate', 'magepeople-yacht-booking-system') },
	{ key: 'evening_slot', label: __('Evening / Sunset Slot Rate', 'magepeople-yacht-booking-system') },
	{ key: 'daily', label: __('Daily Rate', 'magepeople-yacht-booking-system') },
	{ key: 'multiday', label: __('Multi-Day Rate (per day)', 'magepeople-yacht-booking-system') },
];

function RateGrid({ prefix, form, set }) {
	return (
		<div className="ybs-field-row">
			{RATE_FIELDS.map((field) => (
				<Field key={field.key} label={field.label}>
					<input
						type="number"
						value={form[prefix + field.key] ?? ''}
						onChange={(e) => set(prefix + field.key, e.target.value)}
					/>
				</Field>
			))}
		</div>
	);
}

/**
 * Which catalogue add-ons this yacht offers. Saved through its own route
 * rather than with the rest of the wizard: the assignment is a join table,
 * not postmeta, and the catalogue itself (names, prices) is owned by the
 * Add-ons screen so one price change does not mean editing every yacht.
 */
function AddonAssignment({ yachtId }) {
	const [addons, setAddons] = useState(null);
	const [assigned, setAssigned] = useState([]);
	const [saving, setSaving] = useState(false);

	useEffect(() => {
		if (!yachtId) {
			return;
		}

		Promise.all([
			api.get('/addons'),
			api.get(`/yachts/${yachtId}/addons`, { all: 1 }),
		])
			.then(([catalogue, yachtAddons]) => {
				setAddons(catalogue);
				setAssigned(yachtAddons.assigned || []);
			})
			.catch(() => setAddons([]));
	}, [yachtId]);

	const toggle = (id) =>
		setAssigned((prev) => (prev.includes(id) ? prev.filter((a) => a !== id) : [...prev, id]));

	const save = () => {
		setSaving(true);

		api.post(`/yachts/${yachtId}/addons`, { addon_ids: assigned })
			.then(() => toast(__('Add-ons updated.', 'magepeople-yacht-booking-system'), 'success'))
			.catch((err) => toast(err.message, 'error'))
			.finally(() => setSaving(false));
	};

	if (!yachtId) {
		return (
			<p className="ybs-hint">
				{__('Save this yacht as a draft first, then come back to pick which add-ons it offers.', 'magepeople-yacht-booking-system')}
			</p>
		);
	}

	if (!addons) {
		return <div className="ybs-loading">{__('Loading…', 'magepeople-yacht-booking-system')}</div>;
	}

	if (!addons.length) {
		return (
			<p className="ybs-hint">
				{__('No add-ons exist yet. Create them under Yacht Booking → Add-ons, then assign them here.', 'magepeople-yacht-booking-system')}
			</p>
		);
	}

	return (
		<>
			<div className="ybs-checkbox-grid">
				{addons.map((addon) => (
					<label key={addon.id}>
						<input type="checkbox" checked={assigned.includes(addon.id)} onChange={() => toggle(addon.id)} />{' '}
						{addon.name}
						{!addon.active && ` (${__('inactive', 'magepeople-yacht-booking-system')})`}
					</label>
				))}
			</div>
			<button type="button" className="ybs-btn is-primary" disabled={saving} onClick={save}>
				{__('Save add-on selection', 'magepeople-yacht-booking-system')}
			</button>
		</>
	);
}

function StepPricing({ form, set, yachtId }) {
	const mode = form.booking_mode || 'full';
	const showShared = 'both' === mode;

	return (
		<>
			<Card
				title={__('Base Rates', 'magepeople-yacht-booking-system')}
				subtitle={
					showShared
						? __('This yacht runs both full and shared charters - set a separate price grid for each.', 'magepeople-yacht-booking-system')
						: __('Leave a rate blank to disable that booking type for this yacht.', 'magepeople-yacht-booking-system')
				}
			>
				<Field
					label={__('Booking Mode', 'magepeople-yacht-booking-system')}
					hint={
						'both' === mode
							? __('Two price grids are shown below - one for full charters, one for shared per-seat bookings.', 'magepeople-yacht-booking-system')
							: __('Full charter, shared by seat, or both.', 'magepeople-yacht-booking-system')
					}
				>
					<select value={mode} onChange={(e) => set('booking_mode', e.target.value)}>
						<option value="full">{__('Full Charter', 'magepeople-yacht-booking-system')}</option>
						<option value="shared">{__('Shared', 'magepeople-yacht-booking-system')}</option>
						<option value="both">{__('Both', 'magepeople-yacht-booking-system')}</option>
					</select>
				</Field>

				<div className="ybs-field">
					<label>{showShared ? __('Full Charter Prices', 'magepeople-yacht-booking-system') : __('Prices', 'magepeople-yacht-booking-system')}</label>
					<RateGrid prefix="base_price_" form={form} set={set} />
				</div>

				{showShared && (
					<div className="ybs-field" style={{ marginTop: 16 }}>
						<label>{__('Shared Prices (per seat)', 'magepeople-yacht-booking-system')}</label>
						<RateGrid prefix="base_price_shared_" form={form} set={set} />
					</div>
				)}
			</Card>

			<Card
				title={__('Time Configuration', 'magepeople-yacht-booking-system')}
				subtitle={__('The clock-time window each fixed-schedule booking type runs within.', 'magepeople-yacht-booking-system')}
			>
				<div className="ybs-field-row">
					<Field label={__('Daily Charter Start', 'magepeople-yacht-booking-system')}>
						<TimeField value={form.daily_start_time} onChange={(v) => set('daily_start_time', v)} />
					</Field>
					<Field label={__('Daily Charter End', 'magepeople-yacht-booking-system')}>
						<TimeField value={form.daily_end_time} onChange={(v) => set('daily_end_time', v)} />
					</Field>
				</div>

				<div className="ybs-field-row">
					<Field label={__('Half-Day Slot Start', 'magepeople-yacht-booking-system')}>
						<TimeField value={form.halfday_start_time} onChange={(v) => set('halfday_start_time', v)} />
					</Field>
					<Field label={__('Half-Day Slot End', 'magepeople-yacht-booking-system')}>
						<TimeField value={form.halfday_end_time} onChange={(v) => set('halfday_end_time', v)} />
					</Field>
				</div>

				<div className="ybs-field-row">
					<Field label={__('Morning Slot Start', 'magepeople-yacht-booking-system')}>
						<TimeField value={form.morning_slot_start} onChange={(v) => set('morning_slot_start', v)} />
					</Field>
					<Field label={__('Morning Slot End', 'magepeople-yacht-booking-system')}>
						<TimeField value={form.morning_slot_end} onChange={(v) => set('morning_slot_end', v)} />
					</Field>
				</div>

				<div className="ybs-field-row">
					<Field label={__('Evening / Sunset Slot Start', 'magepeople-yacht-booking-system')}>
						<TimeField value={form.evening_slot_start} onChange={(v) => set('evening_slot_start', v)} />
					</Field>
					<Field label={__('Evening / Sunset Slot End', 'magepeople-yacht-booking-system')}>
						<TimeField value={form.evening_slot_end} onChange={(v) => set('evening_slot_end', v)} />
					</Field>
				</div>
			</Card>

			<Card
				title={__('Booking Rules', 'magepeople-yacht-booking-system')}
				subtitle={__('Notice period, spacing between charters, and duration limits.', 'magepeople-yacht-booking-system')}
			>
				<div className="ybs-field-row">
					<Field label={__('Minimum Notice (hours)', 'magepeople-yacht-booking-system')}>
						<input type="number" value={form.min_notice_hours} onChange={(e) => set('min_notice_hours', e.target.value)} />
					</Field>
					<Field label={__('Buffer Between Bookings (minutes)', 'magepeople-yacht-booking-system')}>
						<input type="number" value={form.buffer_minutes} onChange={(e) => set('buffer_minutes', e.target.value)} />
					</Field>
					<Field label={__('Min Duration (minutes, hourly)', 'magepeople-yacht-booking-system')}>
						<input type="number" value={form.min_duration} onChange={(e) => set('min_duration', e.target.value)} />
					</Field>
					<Field label={__('Max Duration (minutes, hourly)', 'magepeople-yacht-booking-system')}>
						<input type="number" value={form.max_duration} onChange={(e) => set('max_duration', e.target.value)} />
					</Field>
				</div>
			</Card>

			<Card
				title={__('Deposit', 'magepeople-yacht-booking-system')}
				subtitle={__('What this yacht asks for up front. Leave it inheriting to follow the fleet-wide setting under Settings → Deposits & Extras.', 'magepeople-yacht-booking-system')}
			>
				<div className="ybs-field-row">
					<Field label={__('Deposit', 'magepeople-yacht-booking-system')}>
						<select value={form.deposit_mode || ''} onChange={(e) => set('deposit_mode', e.target.value)}>
							<option value="">{__('Use the global setting', 'magepeople-yacht-booking-system')}</option>
							<option value="off">{__('No deposit - pay in full', 'magepeople-yacht-booking-system')}</option>
							<option value="percent">{__('Percentage of the total', 'magepeople-yacht-booking-system')}</option>
							<option value="fixed">{__('Fixed amount', 'magepeople-yacht-booking-system')}</option>
						</select>
					</Field>
					{('percent' === form.deposit_mode || 'fixed' === form.deposit_mode) && (
						<Field label={'percent' === form.deposit_mode ? __('Percent', 'magepeople-yacht-booking-system') : __('Amount', 'magepeople-yacht-booking-system')}>
							<input type="number" min="0" step="0.01" value={form.deposit_value ?? ''} onChange={(e) => set('deposit_value', e.target.value)} />
						</Field>
					)}
				</div>
			</Card>

			<Card
				title={__('Add-ons', 'magepeople-yacht-booking-system')}
				subtitle={__('The optional extras guests can add when booking this yacht.', 'magepeople-yacht-booking-system')}
			>
				<AddonAssignment yachtId={yachtId} />
			</Card>

			<Card title={__('Off-Days', 'magepeople-yacht-booking-system')} subtitle={__('Dates this yacht cannot be booked.', 'magepeople-yacht-booking-system')}>
				<RepeatableRows
					items={form.off_days.map((date) => ({ date }))}
					onChange={(items) => set('off_days', items.map((item) => item.date))}
					emptyItem={{ date: '' }}
					addLabel={__('+ Add Off-Day', 'magepeople-yacht-booking-system')}
					fields={[{ key: 'date', label: __('Off-Day date', 'magepeople-yacht-booking-system'), type: 'date' }]}
				/>
			</Card>
		</>
	);
}

const YACHT_EMAIL_EDITOR_ID = 'mageyabo_yacht_confirmation_email_body';

function StepReview({ form, set }) {
	const [showTestModal, setShowTestModal] = useState(false);
	const mode = form.booking_mode || 'full';
	const rateRows = (prefix, suffix) =>
		RATE_FIELDS.map((field) => {
			const value = form[prefix + field.key];

			return value ? (
				<tr key={field.key}>
					<th>{field.label}{suffix}</th>
					<td>{value}</td>
				</tr>
			) : null;
		});

	const insertTag = (tag) => {
		const next = insertIntoEditor(YACHT_EMAIL_EDITOR_ID, tag);

		if (null !== next) {
			set('confirmation_email_body', next);
		}
	};

	return (
		<>
			<Card
				title={form.title || __('(Untitled Yacht)', 'magepeople-yacht-booking-system')}
				subtitle={__('Review the details, then publish to make this yacht bookable on the frontend.', 'magepeople-yacht-booking-system')}
			>
				<p>{form.description}</p>
				<table className="ybs-table">
					<tbody>
						<tr><th>{__('Capacity', 'magepeople-yacht-booking-system')}</th><td>{form.capacity}</td></tr>
						<tr><th>{__('Cabins', 'magepeople-yacht-booking-system')}</th><td>{form.cabins}</td></tr>
						<tr>
							<th>{__('Booking Mode', 'magepeople-yacht-booking-system')}</th>
							<td>{'full' === mode ? __('Full Charter', 'magepeople-yacht-booking-system') : 'shared' === mode ? __('Shared', 'magepeople-yacht-booking-system') : __('Both', 'magepeople-yacht-booking-system')}</td>
						</tr>
						{'shared' !== mode && rateRows('base_price_', '')}
						{'both' === mode && rateRows('base_price_shared_', __(' (shared)', 'magepeople-yacht-booking-system'))}
						<tr><th>{__('Location', 'magepeople-yacht-booking-system')}</th><td>{form.location_name}</td></tr>
						<tr>
							<th>{__('Related Yachts', 'magepeople-yacht-booking-system')}</th>
							<td>
								{form.related_yachts && form.related_yachts.length ? (
									form.related_yachts
										.map((item) => (item && 'object' === typeof item ? item.title : `#${item}`))
										.join(', ')
								) : (
									<em>{__('None picked — shown automatically by class instead.', 'magepeople-yacht-booking-system')}</em>
								)}
							</td>
						</tr>
					</tbody>
				</table>
			</Card>

			<Card
				title={__('Confirmation Email', 'magepeople-yacht-booking-system')}
				subtitle={__('Overrides the global confirmation email (Settings → Email) for this yacht only. Leave the body blank to use the global email instead.', 'magepeople-yacht-booking-system')}
			>
				<div style={{ display: 'flex', justifyContent: 'flex-end', marginBottom: 12 }}>
					<button type="button" className="ybs-btn" onClick={() => setShowTestModal(true)}>
						{__('Send Test Email', 'magepeople-yacht-booking-system')}
					</button>
				</div>
				<Field label={__('Email Subject', 'magepeople-yacht-booking-system')} hint={__('Leave blank to use the global subject when a yacht-specific body is set.', 'magepeople-yacht-booking-system')}>
					<input
						type="text"
						value={form.confirmation_email_subject}
						onChange={(e) => set('confirmation_email_subject', e.target.value)}
					/>
				</Field>
				<Field label={__('Email Body', 'magepeople-yacht-booking-system')}>
					<ClassicEditor
						id={YACHT_EMAIL_EDITOR_ID}
						value={form.confirmation_email_body}
						onChange={(html) => set('confirmation_email_body', html)}
					/>
				</Field>
				<EmailVariables onInsert={insertTag} />
			</Card>

			<Card
				title={__('Booking CTA', 'magepeople-yacht-booking-system')}
				subtitle={__('The "Ready When You Are" section on this yacht\'s details page. Text overrides the global version (Settings → General) - leave blank to use it.', 'magepeople-yacht-booking-system')}
			>
				<label style={{ display: 'flex', alignItems: 'center', gap: 8, marginBottom: 16 }}>
					<input
						type="checkbox"
						checked={!form.cta_disabled}
						onChange={(e) => set('cta_disabled', !e.target.checked)}
					/>
					{__('Show this section on the yacht\'s details page', 'magepeople-yacht-booking-system')}
				</label>
				<Field label={__('Heading', 'magepeople-yacht-booking-system')} hint={__('Use {yacht_name} to insert the yacht\'s name.', 'magepeople-yacht-booking-system')}>
					<input
						type="text"
						value={form.cta_heading}
						onChange={(e) => set('cta_heading', e.target.value)}
						placeholder={__('Book the {yacht_name} today', 'magepeople-yacht-booking-system')}
						disabled={form.cta_disabled}
					/>
				</Field>
				<Field label={__('Text', 'magepeople-yacht-booking-system')}>
					<input
						type="text"
						value={form.cta_text}
						onChange={(e) => set('cta_text', e.target.value)}
						placeholder={__('Dates fill fast - lock in your preferred slot with an instant booking request.', 'magepeople-yacht-booking-system')}
						disabled={form.cta_disabled}
					/>
				</Field>
			</Card>

			{showTestModal && (
				<TestEmailModal
					subject={form.confirmation_email_subject}
					body={form.confirmation_email_body}
					fromName=""
					fromEmail=""
					onRequestClose={() => setShowTestModal(false)}
				/>
			)}
		</>
	);
}
