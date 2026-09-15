import { __ } from '@wordpress/i18n';
import { useEffect, useState } from '@wordpress/element';
import { api } from '../api/client';

/**
 * Tag-style multi-select for linking a yacht to a few others to recommend
 * alongside it (the "Related Yachts" carousel on its details page) - the
 * same pill + filtered-autocomplete pattern as TagSelect, but backed by the
 * yacht list instead of a taxonomy, and with no "add new" affordance since
 * you can't create a yacht from here.
 *
 * `selected` may hold either bare ids (a fresh pick this session) or the
 * `{id, title, thumbnail}` objects the REST payload hydrates it into on
 * load - both are normalized to ids for the actual selection state, with
 * the richer object (when present) used only for pill display.
 */
export default function RelatedYachtsPicker({ selected, onChange, excludeId }) {
	const [options, setOptions] = useState([]);
	const [loaded, setLoaded] = useState(false);
	const [query, setQuery] = useState('');
	const [focused, setFocused] = useState(false);

	useEffect(() => {
		api.get('/yachts', { per_page: 100 })
			.then((data) => setOptions(data.items || []))
			.catch(() => setOptions([]))
			.finally(() => setLoaded(true));
	}, []);

	const idOf = (item) => Number((item && 'object' === typeof item ? item.id : item) || 0);
	const selectedIds = selected.map(idOf);

	const selectedOptions = selected.map((item) => {
		const id = idOf(item);

		return (
			options.find((option) => Number(option.id) === id) ||
			(item && 'object' === typeof item ? item : { id, title: `#${id}`, thumbnail: '' })
		);
	});

	const suggestions = options.filter(
		(option) =>
			Number(option.id) !== Number(excludeId) &&
			!selectedIds.includes(Number(option.id)) &&
			option.title.toLowerCase().includes(query.toLowerCase())
	);

	const add = (id) => {
		onChange([...selectedIds, Number(id)]);
		setQuery('');
	};

	const remove = (id) => onChange(selectedIds.filter((existing) => existing !== Number(id)));

	return (
		<div className="ybs-related-picker">
			<div className="ybs-related-picker__pills">
				{selectedOptions.map((option) => (
					<span key={option.id} className="ybs-related-picker__pill">
						{option.thumbnail ? <img src={option.thumbnail} alt="" /> : null}
						{option.title}
						<button type="button" onClick={() => remove(option.id)} aria-label={__('Remove', 'magepeople-yacht-booking-system')}>
							×
						</button>
					</span>
				))}

				<div className="ybs-related-picker__input-wrap">
					<input
						type="text"
						value={query}
						placeholder={selectedOptions.length ? '' : __('Search yachts…', 'magepeople-yacht-booking-system')}
						onChange={(e) => setQuery(e.target.value)}
						onFocus={() => setFocused(true)}
						onBlur={() => setTimeout(() => setFocused(false), 150)}
					/>

					{focused && loaded && (suggestions.length > 0 || query) && (
						<div className="ybs-related-picker__suggestions">
							{suggestions.slice(0, 20).map((option) => (
								<button type="button" key={option.id} onClick={() => add(option.id)}>
									{option.thumbnail ? (
										<img src={option.thumbnail} alt="" />
									) : (
										<span className="ybs-related-picker__no-thumb dashicons dashicons-palmtree" />
									)}
									<span className="ybs-related-picker__option-title">
										{option.title}
										{option.classes && option.classes.length ? (
											<small>{' — ' + option.classes[0]}</small>
										) : null}
									</span>
								</button>
							))}
							{0 === suggestions.length && (
								<div className="ybs-related-picker__no-match">{__('No matching yachts.', 'magepeople-yacht-booking-system')}</div>
							)}
						</div>
					)}
				</div>
			</div>

			<p className="ybs-hint">
				{__(
					'Leave empty to automatically show other yachts that share this one\'s class instead.',
					'magepeople-yacht-booking-system'
				)}
			</p>
		</div>
	);
}
