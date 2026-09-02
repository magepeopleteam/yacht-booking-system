import { __ } from '@wordpress/i18n';

export default function RepeatableRows({ items, fields, onChange, addLabel, emptyItem }) {
	const rows = items || [];

	const update = (index, key, value) => {
		const next = rows.map((row, i) => (i === index ? { ...row, [key]: value } : row));
		onChange(next);
	};

	const remove = (index) => {
		onChange(rows.filter((_, i) => i !== index));
	};

	const add = () => {
		onChange([...rows, { ...emptyItem }]);
	};

	return (
		<div className="ybs-repeatable">
			{rows.map((row, index) => (
				<div className="ybs-repeatable-row" key={index}>
					{fields.map((field) =>
						'textarea' === field.type ? (
							<textarea
								key={field.key}
								placeholder={field.label}
								value={row[field.key] || ''}
								onChange={(e) => update(index, field.key, e.target.value)}
								rows={2}
							/>
						) : (
							<input
								key={field.key}
								type={field.type || 'text'}
								placeholder={field.label}
								value={row[field.key] || ''}
								onChange={(e) => update(index, field.key, e.target.value)}
							/>
						)
					)}
					<button type="button" className="ybs-btn is-danger" onClick={() => remove(index)} style={{ flex: '0 0 auto' }}>
						{__('Remove', 'magepeople-yacht-booking-system')}
					</button>
				</div>
			))}
			<button type="button" className="ybs-btn" onClick={add}>
				{addLabel || __('+ Add Row', 'magepeople-yacht-booking-system')}
			</button>
		</div>
	);
}
