import { __ } from '@wordpress/i18n';
import { useEffect, useRef, useState } from '@wordpress/element';
import ClassicEditor from './ClassicEditor';

function makeUid() {
	return 'faq_' + Math.random().toString(36).slice(2, 10);
}

/**
 * The answer as one line of plain text, for the collapsed row's preview.
 */
function answerPreview(html) {
	const div = document.createElement('div');
	div.innerHTML = html || '';
	return (div.textContent || '').replace(/\s+/g, ' ').trim();
}

/**
 * The yacht's FAQ, laid out like the accordion guests see on the yacht page:
 * each question is a collapsed row (number, question, a one-line preview of
 * the answer), and clicking a row opens it for editing - the question field
 * and the answer's classic editor. One question is open at a time, which
 * also keeps it to a single TinyMCE instance rather than one per question.
 *
 * One question gets one classic (TinyMCE) editor for its answer, so each row
 * needs an id that survives reordering/removal - `_uid` is assigned once,
 * up front, and carried through every subsequent edit via object spread.
 * Older saved FAQ data predating this field falls back to an index-based id
 * until the one-time normalize effect below backfills a real `_uid`.
 */
export default function FaqEditor({ items, onChange }) {
	const normalized = useRef(false);
	const [openUid, setOpenUid] = useState(null);
	const focusQuestion = useRef(null);

	useEffect(() => {
		if (normalized.current) {
			return;
		}

		normalized.current = true;

		if (items.some((item) => !item._uid)) {
			onChange(items.map((item) => (item._uid ? item : { ...item, _uid: makeUid() })));
		}
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, []);

	// A freshly added (or opened) question puts the cursor in its field.
	useEffect(() => {
		if (focusQuestion.current) {
			focusQuestion.current.focus();
			focusQuestion.current = null;
		}
	}, [openUid]);

	const idFor = (item, index) => item._uid || `idx_${index}`;

	const update = (uid, key, value) => {
		onChange(items.map((item, index) => (idFor(item, index) === uid ? { ...item, [key]: value } : item)));
	};

	const remove = (uid) => {
		if (!window.confirm(__('Delete this question?', 'magepeople-yacht-booking-system'))) {
			return;
		}

		if (openUid === uid) {
			setOpenUid(null);
		}

		onChange(items.filter((item, index) => idFor(item, index) !== uid));
	};

	const add = () => {
		const uid = makeUid();
		onChange([...items, { _uid: uid, question: '', answer: '' }]);
		setOpenUid(uid);
	};

	const toggle = (uid) => setOpenUid(openUid === uid ? null : uid);

	return (
		<div className="ybs-faq-editor">
			{items.length === 0 && (
				<p className="ybs-faq-editor__empty">
					{__('No questions yet. Add the things guests usually ask about this yacht.', 'magepeople-yacht-booking-system')}
				</p>
			)}

			{items.map((item, index) => {
				const uid = idFor(item, index);
				const isOpen = openUid === uid;
				const panelId = `ybs-faq-panel-${uid}`;
				const preview = answerPreview(item.answer);

				return (
					<div className={'ybs-faq-item' + (isOpen ? ' is-open' : '')} key={uid}>
						<div className="ybs-faq-item__row">
							<button
								type="button"
								className="ybs-faq-item__toggle"
								aria-expanded={isOpen}
								aria-controls={panelId}
								onClick={() => toggle(uid)}
							>
								<span className="ybs-faq-item__badge">{index + 1}</span>
								<span className="ybs-faq-item__text">
									<span className={'ybs-faq-item__title' + (item.question ? '' : ' is-empty')}>
										{item.question || __('Untitled question', 'magepeople-yacht-booking-system')}
									</span>
									{!isOpen && (
										<span className="ybs-faq-item__preview">
											{preview || __('No answer yet', 'magepeople-yacht-booking-system')}
										</span>
									)}
								</span>
								<span className="ybs-faq-item__edit-hint" aria-hidden="true">
									<span className="dashicons dashicons-edit" />
								</span>
								<span className="ybs-faq-item__chevron" aria-hidden="true" />
							</button>
							<button
								type="button"
								className="ybs-faq-item__remove"
								onClick={() => remove(uid)}
								aria-label={__('Delete this question', 'magepeople-yacht-booking-system')}
							>
								<span className="dashicons dashicons-trash" />
							</button>
						</div>

						{isOpen && (
							<div className="ybs-faq-item__panel" id={panelId}>
								<label className="ybs-faq-item__label" htmlFor={`${panelId}-q`}>
									{__('Question', 'magepeople-yacht-booking-system')}
								</label>
								<input
									id={`${panelId}-q`}
									type="text"
									className="ybs-faq-item__question"
									placeholder={__('e.g. "Can we bring our own drinks?"', 'magepeople-yacht-booking-system')}
									value={item.question || ''}
									onChange={(e) => update(uid, 'question', e.target.value)}
									ref={(el) => {
										if (el && !item.question && !focusQuestion.current) {
											focusQuestion.current = el;
										}
									}}
								/>

								<span className="ybs-faq-item__label">{__('Answer', 'magepeople-yacht-booking-system')}</span>
								<ClassicEditor
									id={`mageyabo_faq_answer_${uid}`}
									value={item.answer || ''}
									onChange={(html) => update(uid, 'answer', html)}
									compact
								/>

								<div className="ybs-faq-item__actions">
									<button type="button" className="ybs-btn is-primary" onClick={() => setOpenUid(null)}>
										{__('Done', 'magepeople-yacht-booking-system')}
									</button>
								</div>
							</div>
						)}
					</div>
				);
			})}

			<button type="button" className="ybs-btn" onClick={add}>
				{__('+ Add FAQ', 'magepeople-yacht-booking-system')}
			</button>
		</div>
	);
}
