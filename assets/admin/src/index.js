import { createRoot } from '@wordpress/element';
import App from './App';
import './style.css';

/**
 * Mounted once the document has finished parsing, not the moment this script
 * runs. An add-on's script loads after this one and fills its routes and slots
 * (`window.mageyaboAdmin.registerRoute/registerSlot`) as it executes; React
 * schedules the first render on a later task, which can run while the
 * browser is still fetching that script - and the screen would then render
 * without it, showing a locked Pro teaser for a feature that is installed.
 * DOMContentLoaded waits for every footer script.
 */
function mountApp() {
	const mount = document.getElementById( 'ybs-admin-root' );

	if ( mount ) {
		createRoot( mount ).render( <App /> );
	}
}

if ( 'loading' === document.readyState ) {
	document.addEventListener( 'DOMContentLoaded', mountApp );
} else {
	mountApp();
}
