import { __, sprintf } from '@wordpress/i18n';
import { useEffect, useState } from '@wordpress/element';
import { api } from '../api/client';
import { navigate } from '../router';
import PaymentMethodFields from '../components/PaymentMethodFields';
import TestEmailModal from '../components/TestEmailModal';
import DateField from '../components/DateField';

function Field({ label, hint, children }) {
	return (
		<div className="ybs-field">
			<label>{label}</label>
			{children}
			{hint && <p className="ybs-hint">{hint}</p>}
		</div>
	);
}

// Sunday-first, matching PHP's gmdate('w') - the values are what the
// pricing rule stores and what best_match() compares against.
const WEEKDAYS = [
	{ value: 0, label: __('Sun', 'magepeople-yacht-booking-system') },
	{ value: 1, label: __('Mon', 'magepeople-yacht-booking-system') },
	{ value: 2, label: __('Tue', 'magepeople-yacht-booking-system') },
	{ value: 3, label: __('Wed', 'magepeople-yacht-booking-system') },
	{ value: 4, label: __('Thu', 'magepeople-yacht-booking-system') },
	{ value: 5, label: __('Fri', 'magepeople-yacht-booking-system') },
	{ value: 6, label: __('Sat', 'magepeople-yacht-booking-system') },
];

const RULE_TYPE_LABELS = {
	off_day: __('Off-day', 'magepeople-yacht-booking-system'),
	weekday: __('Weekday / weekend', 'magepeople-yacht-booking-system'),
	seasonal: __('Seasonal', 'magepeople-yacht-booking-system'),
};

const BLANK_RULE = { rule_type: 'off_day', label: '', yacht_id: 0, days_of_week: [], date_from: '', date_to: '', adjustment_type: 'block', adjustment_value: 0 };

function PricingRules() {
	const [rules, setRules] = useState(null);
	const [yachts, setYachts] = useState([]);
	const [newRule, setNewRule] = useState(BLANK_RULE);

	const load = () => api.get('/pricing-rules').then(setRules);

	useEffect(() => {
		load();
		api.get('/yachts', { per_page: 100 })
			.then((res) => setYachts(res.items || []))
			.catch(() => setYachts([]));
	}, []);

	const add = () => {
		api.post('/pricing-rules', newRule).then(() => {
			setNewRule(BLANK_RULE);
			load();
		});
	};

	const remove = (id) => api.del(`/pricing-rules/${id}`).then(load);

	const toggleDay = (day) => {
		const days = newRule.days_of_week || [];

		setNewRule({
			...newRule,
			days_of_week: days.includes(day) ? days.filter((d) => d !== day) : [...days, day],
		});
	};

	const yachtName = (id) => {
		const match = yachts.find((y) => Number(y.id) === Number(id));
		return match ? match.title : __('All yachts', 'magepeople-yacht-booking-system');
	};

	const dayNames = (raw) => {
		const days = String(raw || '').split(',').filter((d) => '' !== d);

		if (!days.length) {
			return __('Every day', 'magepeople-yacht-booking-system');
		}

		return days.map((d) => (WEEKDAYS[Number(d)] || {}).label).filter(Boolean).join(', ');
	};

	// A plain-English read of the rule being built. The semantics here are
	// subtle - specificity beats priority, an empty weekday set means "every
	// day" - so it is worth saying out loud before the rule is saved.
	const draftSummary = () => {
		const scope = newRule.yacht_id
			? yachtName(newRule.yacht_id)
			: __('every yacht', 'magepeople-yacht-booking-system');

		const days = (newRule.days_of_week || []).length
			? newRule.days_of_week.slice().sort().map((d) => WEEKDAYS[d].label).join(', ')
			: __('every day', 'magepeople-yacht-booking-system');

		const effect = 'off_day' === newRule.rule_type || 'block' === newRule.adjustment_type
			? __('Blocks booking', 'magepeople-yacht-booking-system')
			: 'percent' === newRule.adjustment_type
				? sprintf(
					/* translators: %s: signed percentage, e.g. "+15%". */
					__('Adjusts the price by %s', 'magepeople-yacht-booking-system'),
					`${Number(newRule.adjustment_value) > 0 ? '+' : ''}${Number(newRule.adjustment_value) || 0}%`
				)
				: sprintf(
					/* translators: %s: signed amount, e.g. "+120". */
					__('Adjusts the price by %s', 'magepeople-yacht-booking-system'),
					`${Number(newRule.adjustment_value) > 0 ? '+' : ''}${Number(newRule.adjustment_value) || 0}`
				);

		const range = newRule.date_from || newRule.date_to
			? sprintf(
				/* translators: 1: start date or "any", 2: end date or "any". */
				__('between %1$s and %2$s', 'magepeople-yacht-booking-system'),
				newRule.date_from || __('any date', 'magepeople-yacht-booking-system'),
				newRule.date_to || __('any date', 'magepeople-yacht-booking-system')
			)
			: __('on any date', 'magepeople-yacht-booking-system');

		return sprintf(
			/* translators: 1: effect, 2: yacht scope, 3: weekdays, 4: date range. */
			__('%1$s on %2$s, %3$s, %4$s.', 'magepeople-yacht-booking-system'),
			effect,
			scope,
			days,
			range
		);
	};

	const effectBadge = (rule) => {
		if ('block' === rule.adjustment_type || 'off_day' === rule.rule_type) {
			return <span className="ybs-badge status-cancelled">{__('Blocked', 'magepeople-yacht-booking-system')}</span>;
		}

		const sign = Number(rule.adjustment_value) > 0 ? '+' : '';
		const amount = 'percent' === rule.adjustment_type
			? `${sign}${Number(rule.adjustment_value)}%`
			: `${sign}${Number(rule.adjustment_value)}`;

		return <span className="ybs-badge status-processing">{amount}</span>;
	};

	const isBlocking = 'off_day' === newRule.rule_type;

	return (
		<>
			<div className="ybs-card">
				<div className="ybs-card__head-row">
					<div>
						<h3>{__('Pricing rules', 'magepeople-yacht-booking-system')}</h3>
						<p className="ybs-hint" style={{ marginTop: 4 }}>
							{__('Block dates, or raise and lower prices for a season, a weekend, or one yacht. When two rules match the same date the more specific one wins, then the higher priority.', 'magepeople-yacht-booking-system')}
						</p>
					</div>
					{rules && rules.length > 0 && (
						<span className="ybs-count-pill">
							{sprintf(
								/* translators: %d: number of rules. */
								__('%d active', 'magepeople-yacht-booking-system'),
								rules.length
							)}
						</span>
					)}
				</div>

				{!rules && <div className="ybs-loading">{__('Loading…', 'magepeople-yacht-booking-system')}</div>}

				{rules && rules.length === 0 && (
					<div className="ybs-empty-state">
						{__('No rules yet. Every yacht is bookable on every date at its normal rate.', 'magepeople-yacht-booking-system')}
					</div>
				)}

				{rules && rules.length > 0 && (
					<table className="ybs-table">
						<thead>
							<tr>
								<th>{__('Rule', 'magepeople-yacht-booking-system')}</th>
								<th>{__('Applies to', 'magepeople-yacht-booking-system')}</th>
								<th>{__('Days', 'magepeople-yacht-booking-system')}</th>
								<th>{__('Dates', 'magepeople-yacht-booking-system')}</th>
								<th>{__('Effect', 'magepeople-yacht-booking-system')}</th>
								<th aria-label={__('Actions', 'magepeople-yacht-booking-system')}></th>
							</tr>
						</thead>
						<tbody>
							{rules.map((rule) => (
								<tr key={rule.id}>
									<td>
										<strong>{rule.label || __('(untitled)', 'magepeople-yacht-booking-system')}</strong>
										<br />
										<small className="ybs-muted">{RULE_TYPE_LABELS[rule.rule_type] || rule.rule_type}</small>
									</td>
									<td>{yachtName(rule.yacht_id)}</td>
									<td>{dayNames(rule.days_of_week)}</td>
									<td>
										{rule.date_from || rule.date_to
											? `${rule.date_from || '…'} → ${rule.date_to || '…'}`
											: __('Any date', 'magepeople-yacht-booking-system')}
									</td>
									<td>{effectBadge(rule)}</td>
									<td>
										<button
											type="button"
											className="ybs-icon-btn is-danger"
											title={__('Remove rule', 'magepeople-yacht-booking-system')}
											aria-label={sprintf(
												/* translators: %s: rule label. */
												__('Remove rule %s', 'magepeople-yacht-booking-system'),
												rule.label || __('(untitled)', 'magepeople-yacht-booking-system')
											)}
											onClick={() => remove(rule.id)}
										>
											<span className="dashicons dashicons-trash" aria-hidden="true" />
										</button>
									</td>
								</tr>
							))}
						</tbody>
					</table>
				)}
			</div>

			<div className="ybs-card">
				<h3>{__('Add a rule', 'magepeople-yacht-booking-system')}</h3>

				<div className="ybs-rule-grid">
					<Field label={__('Label', 'magepeople-yacht-booking-system')} hint={__('Just for you - e.g. "Christmas week".', 'magepeople-yacht-booking-system')}>
						<input
							type="text"
							value={newRule.label}
							placeholder={__('Summer weekend surcharge', 'magepeople-yacht-booking-system')}
							onChange={(e) => setNewRule({ ...newRule, label: e.target.value })}
						/>
					</Field>

					<Field label={__('Applies to', 'magepeople-yacht-booking-system')} hint={__('A rule for one yacht beats a fleet-wide rule for the same date.', 'magepeople-yacht-booking-system')}>
						<select value={newRule.yacht_id} onChange={(e) => setNewRule({ ...newRule, yacht_id: Number(e.target.value) })}>
							<option value={0}>{__('All yachts', 'magepeople-yacht-booking-system')}</option>
							{yachts.map((yacht) => (
								<option key={yacht.id} value={yacht.id}>{yacht.title}</option>
							))}
						</select>
					</Field>

					<Field label={__('Rule type', 'magepeople-yacht-booking-system')} hint={__('Off-days block booking; the others move the price.', 'magepeople-yacht-booking-system')}>
						<select
							value={newRule.rule_type}
							onChange={(e) => setNewRule({ ...newRule, rule_type: e.target.value, adjustment_type: 'off_day' === e.target.value ? 'block' : newRule.adjustment_type })}
						>
							<option value="off_day">{__('Off-day (block booking)', 'magepeople-yacht-booking-system')}</option>
							<option value="weekday">{__('Weekday / weekend', 'magepeople-yacht-booking-system')}</option>
							<option value="seasonal">{__('Seasonal', 'magepeople-yacht-booking-system')}</option>
						</select>
					</Field>
				</div>

				<div className="ybs-rule-grid">
					<Field label={__('From date', 'magepeople-yacht-booking-system')} hint={__('Leave blank for no start limit.', 'magepeople-yacht-booking-system')}>
						<DateField value={newRule.date_from} onChange={(v) => setNewRule({ ...newRule, date_from: v })} />
					</Field>

					<Field label={__('To date', 'magepeople-yacht-booking-system')} hint={__('Leave blank for no end limit.', 'magepeople-yacht-booking-system')}>
						<DateField value={newRule.date_to} onChange={(v) => setNewRule({ ...newRule, date_to: v })} />
					</Field>

					{!isBlocking && (
						<Field label={__('Adjustment', 'magepeople-yacht-booking-system')}>
							<div className="ybs-rule-adjust">
								<select value={newRule.adjustment_type} onChange={(e) => setNewRule({ ...newRule, adjustment_type: e.target.value })}>
									<option value="percent">{__('Percent', 'magepeople-yacht-booking-system')}</option>
									<option value="fixed">{__('Fixed amount', 'magepeople-yacht-booking-system')}</option>
									<option value="block">{__('Block booking', 'magepeople-yacht-booking-system')}</option>
								</select>
								{'block' !== newRule.adjustment_type && (
									<input
										type="number"
										step="0.01"
										aria-label={__('Adjustment value', 'magepeople-yacht-booking-system')}
										value={newRule.adjustment_value}
										onChange={(e) => setNewRule({ ...newRule, adjustment_value: e.target.value })}
									/>
								)}
							</div>
							<p className="ybs-hint">{__('Negative values discount, positive values surcharge.', 'magepeople-yacht-booking-system')}</p>
						</Field>
					)}
				</div>

				<div className="ybs-field">
					<label>{__('Days of week', 'magepeople-yacht-booking-system')}</label>
					<div className="ybs-weekday-picker">
						{WEEKDAYS.map((day) => (
							<label key={day.value} className={(newRule.days_of_week || []).includes(day.value) ? 'is-on' : ''}>
								<input
									type="checkbox"
									checked={(newRule.days_of_week || []).includes(day.value)}
									onChange={() => toggleDay(day.value)}
								/>
								<span>{day.label}</span>
							</label>
						))}
					</div>
					<p className="ybs-hint">{__('Leave all unticked to apply on every day in the date range.', 'magepeople-yacht-booking-system')}</p>
				</div>

				<div className="ybs-rule-foot">
					<button className="ybs-btn is-primary" onClick={add}>
						{__('+ Add rule', 'magepeople-yacht-booking-system')}
					</button>
					<p className="ybs-rule-summary">{draftSummary()}</p>
				</div>
			</div>
		</>
	);
}

function GeneralTab({ settings, set }) {
	return (
		<>
			<div className="ybs-card">
				<h3>{__('Currency & Tax', 'magepeople-yacht-booking-system')}</h3>
				<div className="ybs-field-row">
					<Field label={__('Currency Code', 'magepeople-yacht-booking-system')}>
						<input type="text" value={settings.currency_code} onChange={(e) => set('currency_code', e.target.value)} />
					</Field>
					<Field label={__('Currency Symbol', 'magepeople-yacht-booking-system')}>
						<input type="text" value={settings.currency_symbol} onChange={(e) => set('currency_symbol', e.target.value)} />
					</Field>
					<Field label={__('Tax Rate (%)', 'magepeople-yacht-booking-system')}>
						<input type="number" value={settings.tax_rate} onChange={(e) => set('tax_rate', e.target.value)} />
					</Field>
				</div>
			</div>

			<div className="ybs-card">
				<h3>{__('Details Page - "Ready When You Are" CTA', 'magepeople-yacht-booking-system')}</h3>
				<p className="ybs-hint" style={{ marginTop: 0 }}>
					{__('The booking call-to-action shown near the bottom of every yacht details page. Use {yacht_name} in the heading to insert the yacht\'s name - a yacht can override the heading/text from its own Review step.', 'magepeople-yacht-booking-system')}
				</p>
				<Field label={__('Eyebrow', 'magepeople-yacht-booking-system')}>
					<input type="text" value={settings.cta_eyebrow} onChange={(e) => set('cta_eyebrow', e.target.value)} />
				</Field>
				<Field label={__('Heading', 'magepeople-yacht-booking-system')}>
					<input type="text" value={settings.cta_heading} onChange={(e) => set('cta_heading', e.target.value)} />
				</Field>
				<Field label={__('Text', 'magepeople-yacht-booking-system')}>
					<input type="text" value={settings.cta_text} onChange={(e) => set('cta_text', e.target.value)} />
				</Field>
				<div className="ybs-field-row">
					<Field label={__('Primary Button Label', 'magepeople-yacht-booking-system')}>
						<input type="text" value={settings.cta_button_label} onChange={(e) => set('cta_button_label', e.target.value)} />
					</Field>
					<Field label={__('Secondary Button Label', 'magepeople-yacht-booking-system')}>
						<input type="text" value={settings.cta_button2_label} onChange={(e) => set('cta_button2_label', e.target.value)} />
					</Field>
				</div>
			</div>
		</>
	);
}

function PaymentsTab({ settings, setSettings }) {
	return (
		<div className="ybs-card">
			<h3>{__('Payment Methods', 'magepeople-yacht-booking-system')}</h3>
			<PaymentMethodFields settings={settings} onSettingsChange={setSettings} />
		</div>
	);
}

function PrivacyTab({ settings, set }) {
	return (
		<div className="ybs-card">
			<h3>{__('Data & Privacy', 'magepeople-yacht-booking-system')}</h3>
			<Field label={__('Anonymize guest data after (months)', 'magepeople-yacht-booking-system')} hint={__('0 disables automatic anonymization.', 'magepeople-yacht-booking-system')}>
				<input type="number" value={settings.retention_months} onChange={(e) => set('retention_months', e.target.value)} />
			</Field>
			<label style={{ display: 'block' }}>
				<input type="checkbox" checked={!!settings.remove_data_on_uninstall} onChange={() => set('remove_data_on_uninstall', !settings.remove_data_on_uninstall)} />{' '}
				{__('Remove all plugin data when uninstalled', 'magepeople-yacht-booking-system')}
			</label>
		</div>
	);
}

/**
 * Deposits, and the master switches for add-ons and coupons. The per-yacht
 * deposit override lives in the yacht wizard - this is the fleet default it
 * falls back to.
 */
function DepositsTab({ settings, set }) {
	return (
		<>
			<div className="ybs-card">
				<h3>{__('Deposits', 'magepeople-yacht-booking-system')}</h3>
				<p className="ybs-hint" style={{ marginTop: 0 }}>
					{__('Take part of the charter now and settle the balance later. The payment method is only asked for the deposit; the booking still records the full total, and the guest sees the balance on their confirmation.', 'magepeople-yacht-booking-system')}
				</p>

				<label style={{ display: 'block', marginBottom: 12 }}>
					<input type="checkbox" checked={!!settings.deposit_enabled} onChange={() => set('deposit_enabled', !settings.deposit_enabled)} />{' '}
					{__('Take a deposit instead of the full amount', 'magepeople-yacht-booking-system')}
				</label>

				{settings.deposit_enabled && (
					<div className="ybs-field-row">
						<Field label={__('Deposit type', 'magepeople-yacht-booking-system')}>
							<select value={settings.deposit_type} onChange={(e) => set('deposit_type', e.target.value)}>
								<option value="percent">{__('Percentage of the total', 'magepeople-yacht-booking-system')}</option>
								<option value="fixed">{__('Fixed amount', 'magepeople-yacht-booking-system')}</option>
							</select>
						</Field>
						<Field
							label={'percent' === settings.deposit_type ? __('Percent', 'magepeople-yacht-booking-system') : __('Amount', 'magepeople-yacht-booking-system')}
							hint={__('A deposit at or above the total is treated as paying in full.', 'magepeople-yacht-booking-system')}
						>
							<input type="number" min="0" step="0.01" value={settings.deposit_value} onChange={(e) => set('deposit_value', e.target.value)} />
						</Field>
					</div>
				)}
			</div>

			<div className="ybs-card">
				<h3>{__('Extras', 'magepeople-yacht-booking-system')}</h3>
				<label style={{ display: 'block' }}>
					<input type="checkbox" checked={!!settings.addons_enabled} onChange={() => set('addons_enabled', !settings.addons_enabled)} />{' '}
					{__('Offer add-ons on the booking form', 'magepeople-yacht-booking-system')}
				</label>
				<p className="ybs-hint">
					{__('Turning this off hides extras from the front end immediately. Bookings already taken keep the extras they were charged for.', 'magepeople-yacht-booking-system')}
				</p>
			</div>
		</>
	);
}

const TRIGGER_STATUSES = [
	{ value: 'pending', label: __('Pending Payment', 'magepeople-yacht-booking-system') },
	{ value: 'processing', label: __('Processing', 'magepeople-yacht-booking-system') },
	{ value: 'on-hold', label: __('On Hold', 'magepeople-yacht-booking-system') },
	{ value: 'completed', label: __('Completed', 'magepeople-yacht-booking-system') },
];

function EmailTab({ settings, set }) {
	const [showTestModal, setShowTestModal] = useState(false);
	const triggers = settings.email_trigger_statuses || [];

	const toggleTrigger = (value) => {
		const next = triggers.includes(value)
			? triggers.filter((s) => s !== value)
			: [...triggers, value];
		set('email_trigger_statuses', next);
	};

	return (
		<>
			<div className="ybs-settings__grid">
				<div className="ybs-settings__grid-main">
					<div className="ybs-card">
						<h3>{__('New Booking Alerts', 'magepeople-yacht-booking-system')}</h3>
						<p className="ybs-hint" style={{ marginTop: 0 }}>
							{__('Tells you when a booking comes in. Separate from the guest confirmation, so turning guest emails off does not also silence this. Edit the wording under Emails.', 'magepeople-yacht-booking-system')}
						</p>
						<label style={{ display: 'block', marginBottom: 12 }}>
							<input type="checkbox" checked={!!settings.admin_email_enabled} onChange={() => set('admin_email_enabled', !settings.admin_email_enabled)} />{' '}
							{__('Email me when a new booking is made', 'magepeople-yacht-booking-system')}
						</label>
						<Field
							label={__('Send alerts to', 'magepeople-yacht-booking-system')}
							hint={__('Comma-separated. Leave blank to use the site admin email.', 'magepeople-yacht-booking-system')}
						>
							<input
								type="text"
								value={settings.admin_email_recipients}
								placeholder={(window.mageyaboAdminConfig || {}).adminEmail || ''}
								onChange={(e) => set('admin_email_recipients', e.target.value)}
							/>
						</Field>
					</div>

					<div className="ybs-card">
						<h3>{__('Sender Identity', 'magepeople-yacht-booking-system')}</h3>
						<div className="ybs-field-row">
							<Field label={__('From Name', 'magepeople-yacht-booking-system')} hint={__('Defaults to your site name if left blank.', 'magepeople-yacht-booking-system')}>
								<input type="text" value={settings.email_from_name} onChange={(e) => set('email_from_name', e.target.value)} />
							</Field>
							<Field label={__('From Email', 'magepeople-yacht-booking-system')} hint={__('Defaults to the site admin email if left blank.', 'magepeople-yacht-booking-system')}>
								<input type="email" value={settings.email_from_address} onChange={(e) => set('email_from_address', e.target.value)} />
							</Field>
						</div>
					</div>

					<div className="ybs-card">
						<div className="ybs-card__head-row">
							<h3>{__('Guest Emails', 'magepeople-yacht-booking-system')}</h3>
							<button type="button" className="ybs-btn" onClick={() => setShowTestModal(true)}>
								{__('Send Test Email', 'magepeople-yacht-booking-system')}
							</button>
						</div>
						<label style={{ display: 'block', marginBottom: 12 }}>
							<input type="checkbox" checked={!!settings.email_enabled} onChange={() => set('email_enabled', !settings.email_enabled)} />{' '}
							{__('Send booking emails to guests', 'magepeople-yacht-booking-system')}
						</label>
						<p className="ybs-hint" style={{ marginTop: 0 }}>
							{__('What each email says is edited under Yacht Booking → Emails, alongside the payment-received and cancellation emails. A yacht can override its own confirmation wording in its edit screen (Step 4).', 'magepeople-yacht-booking-system')}
						</p>
						<button type="button" className="ybs-btn" onClick={() => navigate('emails')}>
							{__('Edit email templates', 'magepeople-yacht-booking-system')}
						</button>
					</div>
				</div>

				<div className="ybs-settings__grid-side">
					<div className="ybs-card">
						<h3>{__('Send Confirmation On', 'magepeople-yacht-booking-system')}</h3>
						<p className="ybs-hint" style={{ marginTop: 0 }}>
							{__('The booking status(es) that trigger the confirmation email.', 'magepeople-yacht-booking-system')}
						</p>
						<div className="ybs-checkbox-list">
							{TRIGGER_STATUSES.map((status) => (
								<label className="ybs-checkbox-list__item" key={status.value}>
									<input
										type="checkbox"
										checked={triggers.includes(status.value)}
										onChange={() => toggleTrigger(status.value)}
									/>
									{status.label}
								</label>
							))}
						</div>
					</div>

				</div>
			</div>

			{showTestModal && (
				<TestEmailModal
					fromName={settings.email_from_name}
					fromEmail={settings.email_from_address}
					onRequestClose={() => setShowTestModal(false)}
				/>
			)}
		</>
	);
}

const TABS = [
	{ id: 'general', label: __('General', 'magepeople-yacht-booking-system'), subtitle: __('Currency and tax', 'magepeople-yacht-booking-system'), icon: 'dashicons-admin-generic' },
	{ id: 'payments', label: __('Payments', 'magepeople-yacht-booking-system'), subtitle: __('Accepted payment methods', 'magepeople-yacht-booking-system'), icon: 'dashicons-cart' },
	{ id: 'pricing', label: __('Pricing Rules', 'magepeople-yacht-booking-system'), subtitle: __('Off-days and seasonal adjustments', 'magepeople-yacht-booking-system'), icon: 'dashicons-calendar-alt' },
	{ id: 'deposits', label: __('Deposits & Extras', 'magepeople-yacht-booking-system'), subtitle: __('Part payment and add-ons', 'magepeople-yacht-booking-system'), icon: 'dashicons-money-alt' },
	{ id: 'email', label: __('Email', 'magepeople-yacht-booking-system'), subtitle: __('Booking confirmation emails', 'magepeople-yacht-booking-system'), icon: 'dashicons-email-alt' },
	{ id: 'privacy', label: __('Data & Privacy', 'magepeople-yacht-booking-system'), subtitle: __('Retention and uninstall behavior', 'magepeople-yacht-booking-system'), icon: 'dashicons-privacy' },
];

export default function Settings() {
	const [settings, setSettings] = useState(null);
	const [tab, setTab] = useState('general');
	const [saved, setSaved] = useState(false);
	const [error, setError] = useState('');

	useEffect(() => {
		api.get('/settings').then(setSettings).catch((err) => setError(err.message));
	}, []);

	const set = (key, value) => setSettings({ ...settings, [key]: value });

	const save = () => {
		setError('');
		api.put('/settings', settings)
			.then((data) => {
				setSettings(data);
				setSaved(true);
				setTimeout(() => setSaved(false), 2000);
			})
			.catch((err) => setError(err.message));
	};

	if (!settings) {
		return <div className="ybs-loading">{__('Loading…', 'magepeople-yacht-booking-system')}</div>;
	}

	const activeTab = TABS.find((t) => t.id === tab) || TABS[0];

	return (
		<div className="ybs-settings">
			<aside className="ybs-settings__sidebar">
				<div className="ybs-settings__sb-header">
					<span className="ybs-settings__sb-eyebrow">{__('Yacht Booking System', 'magepeople-yacht-booking-system')}</span>
					<span className="ybs-settings__sb-title">
						<span className="ybs-settings__sb-dot" />
						{__('Settings', 'magepeople-yacht-booking-system')}
					</span>
				</div>
				<nav className="ybs-settings__nav">
					{TABS.map((t) => (
						<button
							type="button"
							key={t.id}
							className={'ybs-settings__nav-item' + (tab === t.id ? ' is-active' : '')}
							onClick={() => setTab(t.id)}
						>
							<span className={'dashicons ' + t.icon} />
							{t.label}
						</button>
					))}
				</nav>
			</aside>

			<div className="ybs-settings__main">
				<div className="ybs-settings__topbar">
					<span className="ybs-settings__topbar-title">{activeTab.label}</span>
					<span className="ybs-settings__topbar-sep">&rsaquo;</span>
					<span className="ybs-settings__topbar-sub">{activeTab.subtitle}</span>
					<button className="ybs-btn is-primary ybs-settings__save" onClick={save}>
						{__('Save Changes', 'magepeople-yacht-booking-system')}
					</button>
				</div>

				<div className="ybs-settings__content">
					{saved && <div className="ybs-notice is-success">{__('Settings saved.', 'magepeople-yacht-booking-system')}</div>}
					{error && <div className="ybs-notice is-error">{error}</div>}

					{'general' === tab && <GeneralTab settings={settings} set={set} />}
					{'payments' === tab && <PaymentsTab settings={settings} setSettings={setSettings} />}
					{'pricing' === tab && <PricingRules />}
					{'deposits' === tab && <DepositsTab settings={settings} set={set} />}
					{'email' === tab && <EmailTab settings={settings} set={set} />}
					{'privacy' === tab && <PrivacyTab settings={settings} set={set} />}
				</div>
			</div>
		</div>
	);
}
