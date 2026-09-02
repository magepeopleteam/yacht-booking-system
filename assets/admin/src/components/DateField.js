import { __ } from '@wordpress/i18n';
import { useEffect, useRef, useState } from '@wordpress/element';

const MONTH_NAMES = [
	__('January', 'magepeople-yacht-booking-system'), __('February', 'magepeople-yacht-booking-system'), __('March', 'magepeople-yacht-booking-system'),
	__('April', 'magepeople-yacht-booking-system'), __('May', 'magepeople-yacht-booking-system'), __('June', 'magepeople-yacht-booking-system'),
	__('July', 'magepeople-yacht-booking-system'), __('August', 'magepeople-yacht-booking-system'), __('September', 'magepeople-yacht-booking-system'),
	__('October', 'magepeople-yacht-booking-system'), __('November', 'magepeople-yacht-booking-system'), __('December', 'magepeople-yacht-booking-system'),
];
const DAY_NAMES = [
	__('Su', 'magepeople-yacht-booking-system'), __('Mo', 'magepeople-yacht-booking-system'), __('Tu', 'magepeople-yacht-booking-system'), __('We', 'magepeople-yacht-booking-system'),
	__('Th', 'magepeople-yacht-booking-system'), __('Fr', 'magepeople-yacht-booking-system'), __('Sa', 'magepeople-yacht-booking-system'),
];

function pad(n) {
	return String(n).padStart(2, '0');
}

function toIso(date) {
	return date.getFullYear() + '-' + pad(date.getMonth() + 1) + '-' + pad(date.getDate());
}

function parseIso(value) {
	const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(value || '');
	if (!m) {
		return null;
	}
	return new Date(parseInt(m[1], 10), parseInt(m[2], 10) - 1, parseInt(m[3], 10));
}

/**
 * A trigger input styled and behaving like mage-eventpress's admin date
 * field - a native `<input type="date">` (the browser still renders a
 * localized value) whose native picker is suppressed in favor of this
 * custom month-grid popover, ported to this plugin's own `ybs-` class
 * names and orange accent.
 */
export default function DateField({ value, onChange, placeholder }) {
	const [open, setOpen] = useState(false);
	const [viewMonth, setViewMonth] = useState(0);
	const [viewYear, setViewYear] = useState(2000);
	const wrapRef = useRef(null);

	useEffect(() => {
		if (!open) {
			return;
		}

		const onOutside = (e) => {
			if (wrapRef.current && !wrapRef.current.contains(e.target)) {
				setOpen(false);
			}
		};
		const onKey = (e) => {
			if (e.key === 'Escape') {
				setOpen(false);
			}
		};

		document.addEventListener('mousedown', onOutside);
		document.addEventListener('keydown', onKey);
		return () => {
			document.removeEventListener('mousedown', onOutside);
			document.removeEventListener('keydown', onKey);
		};
	}, [open]);

	const openPicker = () => {
		const parsed = parseIso(value) || new Date();
		setViewMonth(parsed.getMonth());
		setViewYear(parsed.getFullYear());
		setOpen(true);
	};

	const select = (iso) => {
		onChange(iso);
		setOpen(false);
	};

	const changeMonth = (delta) => {
		let m = viewMonth + delta;
		let y = viewYear;
		if (m < 0) {
			m = 11;
			y -= 1;
		} else if (m > 11) {
			m = 0;
			y += 1;
		}
		setViewMonth(m);
		setViewYear(y);
	};

	const selectedIso = value || '';
	const todayIso = toIso(new Date());
	const daysInMonth = new Date(viewYear, viewMonth + 1, 0).getDate();
	const firstDay = new Date(viewYear, viewMonth, 1).getDay();
	const cells = [];
	for (let i = 0; i < firstDay; i++) {
		cells.push(null);
	}
	for (let day = 1; day <= daysInMonth; day++) {
		cells.push(day);
	}

	return (
		<span className="ybs-date-input-wrap" ref={wrapRef}>
			<input
				type="date"
				className="ybs-date-input ybs-custom-date-enabled"
				readOnly
				autoComplete="off"
				inputMode="none"
				placeholder={placeholder}
				value={value || ''}
				onMouseDown={(e) => {
					e.preventDefault();
					if (!open) {
						openPicker();
					}
				}}
				onKeyDown={(e) => {
					if (e.key === 'Enter' || e.key === ' ' || e.key === 'ArrowDown') {
						e.preventDefault();
						openPicker();
					} else if (e.key === 'Escape') {
						setOpen(false);
					} else if (e.key.length === 1 || e.key === 'Backspace' || e.key === 'Delete') {
						e.preventDefault();
					}
				}}
				onChange={() => { }}
			/>

			{open && (
				<div className="ybs-custom-calendar is-open" role="dialog" aria-modal="false" aria-label={__('Choose date', 'magepeople-yacht-booking-system')}>
					<div className="ybs-custom-calendar__head">
						<button type="button" className="ybs-custom-calendar__nav" aria-label={__('Previous month', 'magepeople-yacht-booking-system')} onClick={() => changeMonth(-1)}>
							<span className="dashicons dashicons-arrow-left-alt2"></span>
						</button>
						<div className="ybs-custom-calendar__title">
							<select
								className="ybs-custom-calendar__month"
								aria-label={__('Month', 'magepeople-yacht-booking-system')}
								value={viewMonth}
								onChange={(e) => setViewMonth(parseInt(e.target.value, 10))}
							>
								{MONTH_NAMES.map((name, index) => <option key={name} value={index}>{name}</option>)}
							</select>
							<input
								className="ybs-custom-calendar__year"
								type="number"
								aria-label={__('Year', 'magepeople-yacht-booking-system')}
								min="1900"
								max="2100"
								value={viewYear}
								onChange={(e) => setViewYear(parseInt(e.target.value, 10) || viewYear)}
							/>
						</div>
						<button type="button" className="ybs-custom-calendar__nav" aria-label={__('Next month', 'magepeople-yacht-booking-system')} onClick={() => changeMonth(1)}>
							<span className="dashicons dashicons-arrow-right-alt2"></span>
						</button>
					</div>

					<div className="ybs-custom-calendar__week">
						{DAY_NAMES.map((name) => <span key={name}>{name}</span>)}
					</div>

					<div className="ybs-custom-calendar__days">
						{cells.map((day, index) => {
							if (day === null) {
								return <span key={'empty-' + index} className="ybs-custom-calendar__empty"></span>;
							}

							const iso = viewYear + '-' + pad(viewMonth + 1) + '-' + pad(day);
							const classes = ['ybs-custom-calendar__day'];
							if (iso === todayIso) {
								classes.push('is-today');
							}
							if (iso === selectedIso) {
								classes.push('is-selected');
							}

							return (
								<button
									key={iso}
									type="button"
									className={classes.join(' ')}
									onClick={() => select(iso)}
								>
									{day}
								</button>
							);
						})}
					</div>

					<div className="ybs-custom-calendar__foot">
						<button
							type="button"
							className="ybs-custom-calendar__today"
							onClick={() => {
								const now = new Date();
								setViewMonth(now.getMonth());
								setViewYear(now.getFullYear());
								select(toIso(now));
							}}
						>
							{__('Today', 'magepeople-yacht-booking-system')}
						</button>
						<button
							type="button"
							className="ybs-custom-calendar__clear"
							onClick={() => {
								onChange('');
								setOpen(false);
							}}
						>
							{__('Clear', 'magepeople-yacht-booking-system')}
						</button>
					</div>
				</div>
			)}
		</span>
	);
}
