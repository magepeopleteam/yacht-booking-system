import { createRoot } from '@wordpress/element';
import App from './App';
import './style.css';

const mount = document.getElementById( 'ybs-admin-root' );

if ( mount ) {
	createRoot( mount ).render( <App /> );
}
