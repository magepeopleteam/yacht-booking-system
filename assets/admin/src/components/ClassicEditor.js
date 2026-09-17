import { useEffect, useRef } from '@wordpress/element';

/**
 * Inserts text at the cursor into a mounted classic editor - works whether
 * the visual (TinyMCE) or text (quicktags) view is currently active, since
 * a compact/inline editor's toolbar-less text mode has no TinyMCE instance
 * to insert into. Returns the editor's full content after inserting, so
 * callers can push it straight into their own `onChange` state.
 */
export function insertIntoEditor( id, text ) {
	const editor = window.tinymce && window.tinymce.get( id );

	if ( editor && ! editor.isHidden() ) {
		editor.execCommand( 'mceInsertContent', false, text );
		editor.save();
		return editor.getContent();
	}

	const textarea = document.getElementById( id );

	if ( ! textarea ) {
		return null;
	}

	const start = textarea.selectionStart ?? textarea.value.length;
	const end = textarea.selectionEnd ?? textarea.value.length;
	const next = textarea.value.slice( 0, start ) + text + textarea.value.slice( end );

	textarea.value = next;
	textarea.focus();
	textarea.selectionStart = textarea.selectionEnd = start + text.length;

	return next;
}

/**
 * Mounts WordPress's classic (TinyMCE + Quicktags) editor, the same API
 * Gutenberg's own "Classic" block uses (`wp.editor.initialize`/
 * `wp.editor.remove`) - requires the host page to have called
 * `wp_enqueue_editor()` (see Admin\Menu::enqueue).
 *
 * React renders an empty container and nothing else; the `<textarea>` is
 * created imperatively inside it. That division matters. TinyMCE takes the
 * textarea over and moves it into its own wrapper markup, so a textarea
 * rendered by React is a node React believes it owns and TinyMCE has since
 * relocated - and unmounting it (switching email template, leaving the
 * screen) throws "Failed to execute 'removeChild' on 'Node': The node to be
 * removed is not a child of this node", taking the whole screen down with
 * it. Owning the subtree ourselves means React only ever adds and removes
 * the one container it really does own.
 *
 * `compact` trims the toolbar to the essentials and shortens the editor,
 * for repeated small instances (e.g. one per FAQ answer) rather than the
 * one full-size Description field.
 */
export default function ClassicEditor( { id, value, onChange, compact = false } ) {
	const containerRef = useRef( null );
	const onChangeRef = useRef( onChange );
	const valueRef = useRef( value );

	// Both kept current on every render, but only read when the effect
	// actually runs - so the editor is created with the latest content
	// without a re-render ever yanking it out from under someone typing.
	onChangeRef.current = onChange;
	valueRef.current = value;

	useEffect( () => {
		const container = containerRef.current;

		if ( ! container ) {
			return undefined;
		}

		const textarea = document.createElement( 'textarea' );
		textarea.id = id;
		textarea.rows = compact ? 5 : 10;
		textarea.value = valueRef.current || '';
		container.appendChild( textarea );

		const onTextareaInput = () => onChangeRef.current( textarea.value );
		textarea.addEventListener( 'input', onTextareaInput );

		// Without wp.editor the field still works - it is just a plain
		// textarea, which is better than an empty box.
		if ( window.wp && window.wp.editor ) {
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
		}

		return () => {
			textarea.removeEventListener( 'input', onTextareaInput );

			if ( window.wp && window.wp.editor ) {
				window.wp.editor.remove( id );
			}

			// Whatever state TinyMCE left the markup in, this subtree is ours
			// to clear - React never rendered any of it and will not try to
			// reconcile it.
			container.innerHTML = '';
		};
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ id ] );

	return <div ref={ containerRef } className="ybs-classic-editor" />;
}
