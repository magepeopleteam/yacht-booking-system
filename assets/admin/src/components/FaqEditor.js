import { __ } from '@wordpress/i18n';
import { useEffect, useRef } from '@wordpress/element';
import ClassicEditor from './ClassicEditor';

function makeUid() {
	return 'faq_' + Math.random().toString( 36 ).slice( 2, 10 );
}

/**
 * One question gets one classic (TinyMCE) editor for its answer, so each row
 * needs an id that survives reordering/removal - `_uid` is assigned once,
 * up front, and carried through every subsequent edit via object spread.
 * Older saved FAQ data predating this field falls back to an index-based id
 * until the one-time normalize effect below backfills a real `_uid`.
 */
export default function FaqEditor( { items, onChange } ) {
	const normalized = useRef( false );

	useEffect( () => {
		if ( normalized.current ) {
			return;
		}

		normalized.current = true;

		if ( items.some( ( item ) => ! item._uid ) ) {
			onChange( items.map( ( item ) => ( item._uid ? item : { ...item, _uid: makeUid() } ) ) );
		}
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [] );

	const idFor = ( item, index ) => item._uid || `idx_${ index }`;

	const update = ( uid, key, value ) => {
		onChange( items.map( ( item, index ) => ( idFor( item, index ) === uid ? { ...item, [ key ]: value } : item ) ) );
	};

	const remove = ( uid ) => {
		onChange( items.filter( ( item, index ) => idFor( item, index ) !== uid ) );
	};

	const add = () => {
		onChange( [ ...items, { _uid: makeUid(), question: '', answer: '' } ] );
	};

	return (
		<div className="ybs-faq-editor">
			{ items.map( ( item, index ) => {
				const uid = idFor( item, index );

				return (
					<div className="ybs-faq-item" key={ uid }>
						<div className="ybs-faq-item__head">
							<span className="ybs-faq-item__badge">{ index + 1 }</span>
							<input
								type="text"
								className="ybs-faq-item__question"
								placeholder={ __( 'Question, e.g. "Can we bring our own drinks?"', 'magepeople-yacht-booking-system' ) }
								value={ item.question || '' }
								onChange={ ( e ) => update( uid, 'question', e.target.value ) }
							/>
							<button
								type="button"
								className="ybs-faq-item__remove"
								onClick={ () => remove( uid ) }
								aria-label={ __( 'Remove this FAQ', 'magepeople-yacht-booking-system' ) }
							>
								<span className="dashicons dashicons-trash" />
							</button>
						</div>
						<div className="ybs-faq-item__body">
							<ClassicEditor
								id={ `ybs_faq_answer_${ uid }` }
								value={ item.answer || '' }
								onChange={ ( html ) => update( uid, 'answer', html ) }
								compact
							/>
						</div>
					</div>
				);
			} ) }

			<button type="button" className="ybs-btn" onClick={ add }>
				{ __( '+ Add FAQ', 'magepeople-yacht-booking-system' ) }
			</button>
		</div>
	);
}
