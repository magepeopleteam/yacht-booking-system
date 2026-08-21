import { useEffect, useRef } from '@wordpress/element';

/**
 * Mounts WordPress's classic (TinyMCE + Quicktags) editor onto a plain
 * textarea, the same API Gutenberg's own "Classic" block uses
 * (`wp.editor.initialize`/`wp.editor.remove`) - requires the host page to
 * have called `wp_enqueue_editor()` (see Admin\Menu::enqueue).
 *
 * `compact` trims the toolbar to the essentials and shortens the editor,
 * for repeated small instances (e.g. one per FAQ answer) rather than the
 * one full-size Description field.
 */
export default function ClassicEditor( { id, value, onChange, compact = false } ) {
	const initialValue = useRef( value );
	const onChangeRef = useRef( onChange );
	onChangeRef.current = onChange;

	useEffect( () => {
		if ( ! window.wp || ! window.wp.editor ) {
			return;
		}

		window.wp.editor.initialize( id, {
			tinymce: {
				wpautop: true,
				plugins: compact
					? 'lists,paste,tabfocus,wordpress,wplink'
					: 'charmap,colorpicker,hr,lists,media,paste,tabfocus,textcolor,fullscreen,wordpress,wplink,wpview',
				toolbar1: compact
					? 'bold,italic,bullist,numlist,link,unlink,removeformat'
					: 'formatselect,bold,italic,bullist,numlist,blockquote,alignleft,aligncenter,alignright,link,unlink,wp_adv',
				toolbar2: compact
					? ''
					: 'strikethrough,hr,forecolor,pastetext,removeformat,charmap,outdent,indent,undo,redo,wp_help',
				setup( editor ) {
					const sync = () => {
						editor.save();
						onChangeRef.current( editor.getContent() );
					};

					editor.on( 'change keyup undo redo', sync );
				},
			},
			quicktags: ! compact,
			mediaButtons: ! compact,
		} );

		const textarea = document.getElementById( id );
		const onTextareaInput = () => onChangeRef.current( textarea.value );
		textarea?.addEventListener( 'input', onTextareaInput );

		return () => {
			textarea?.removeEventListener( 'input', onTextareaInput );

			if ( window.wp?.editor ) {
				window.wp.editor.remove( id );
			}
		};
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ id ] );

	return <textarea id={ id } defaultValue={ initialValue.current } rows={ compact ? 5 : 10 } />;
}
