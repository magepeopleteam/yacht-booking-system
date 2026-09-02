import { __, sprintf } from '@wordpress/i18n';
import { useState } from '@wordpress/element';
import { Modal, TextControl, Button } from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';
import { toast } from './Toast';

/**
 * A tag-style multi-select (pills + filtered autocomplete) with an inline
 * "Add New" modal that creates the term on the fly via the core `wp/v2`
 * terms endpoint - avoids pulling in jQuery/Select2 for a look this small.
 */
export default function TagSelect({ label, taxonomy, terms, selected, onChange, onTermsChange }) {
	const [query, setQuery] = useState('');
	const [focused, setFocused] = useState(false);
	const [modalOpen, setModalOpen] = useState(false);
	const [newName, setNewName] = useState('');
	const [creating, setCreating] = useState(false);

	// Normalized to numbers on both sides - `selected` can arrive as numeric
	// strings depending on where it was last serialized, and a strict
	// `["3"].includes(3)` mismatch would otherwise make a real selection
	// silently fail to render as a pill.
	const selectedIds = selected.map(Number);
	const selectedTerms = terms.filter((term) => selectedIds.includes(Number(term.id)));
	const suggestions = terms.filter(
		(term) => !selectedIds.includes(Number(term.id)) && term.name.toLowerCase().includes(query.toLowerCase())
	);

	const add = (id) => {
		onChange([...selectedIds, Number(id)]);
		setQuery('');
	};

	const remove = (id) => onChange(selectedIds.filter((existing) => existing !== Number(id)));

	const createTerm = () => {
		if (!newName.trim()) {
			return;
		}

		setCreating(true);

		apiFetch({ path: `/wp/v2/${taxonomy}`, method: 'POST', data: { name: newName.trim() } })
			.then((term) => {
				onTermsChange([...terms, term]);
				onChange([...selectedIds, Number(term.id)]);
				setNewName('');
				setModalOpen(false);
				setCreating(false);
				// translators: %s: name of the term that was added.
				toast(sprintf(__('"%s" added.', 'magepeople-yacht-booking-system'), term.name));
			})
			.catch((err) => {
				setCreating(false);
				toast(err.message, 'error');
			});
	};

	return (
		<div className="ybs-tagselect">
			<div className="ybs-tagselect__pills">
				{selectedTerms.map((term) => (
					<span key={term.id} className="ybs-tagselect__pill">
						{term.name}
						<button type="button" onClick={() => remove(term.id)} aria-label={__('Remove', 'magepeople-yacht-booking-system')}>
							×
						</button>
					</span>
				))}

				<div className="ybs-tagselect__input-wrap">
					<input
						type="text"
						value={query}
						placeholder={selectedTerms.length ? '' : label}
						onChange={(e) => setQuery(e.target.value)}
						onFocus={() => setFocused(true)}
						onBlur={() => setTimeout(() => setFocused(false), 150)}
					/>

					{focused && (suggestions.length > 0 || query) && (
						<div className="ybs-tagselect__suggestions">
							{suggestions.map((term) => (
								<button type="button" key={term.id} onClick={() => add(term.id)}>
									{term.name}
								</button>
							))}
							{0 === suggestions.length && (
								<div className="ybs-tagselect__no-match">{__('No matches.', 'magepeople-yacht-booking-system')}</div>
							)}
						</div>
					)}
				</div>
			</div>

			<button type="button" className="ybs-tagselect__add" onClick={() => setModalOpen(true)}>
				{ /* translators: %s: taxonomy label, e.g. "Class". */ sprintf(__('+ Add New %s', 'magepeople-yacht-booking-system'), label)}
			</button>

			{modalOpen && (
				<Modal
					title={ /* translators: %s: taxonomy label, e.g. "Class". */ sprintf(__('Add New %s', 'magepeople-yacht-booking-system'), label)}
					onRequestClose={() => setModalOpen(false)}
				>
					<TextControl
						label={label}
						value={newName}
						onChange={setNewName}
						onKeyDown={(e) => 'Enter' === e.key && createTerm()}
					/>
					<div style={{ display: 'flex', justifyContent: 'flex-end', gap: 8, marginTop: 16 }}>
						<Button variant="tertiary" onClick={() => setModalOpen(false)}>
							{__('Cancel', 'magepeople-yacht-booking-system')}
						</Button>
						<Button variant="primary" onClick={createTerm} isBusy={creating} disabled={creating}>
							{__('Save', 'magepeople-yacht-booking-system')}
						</Button>
					</div>
				</Modal>
			)}
		</div>
	);
}
