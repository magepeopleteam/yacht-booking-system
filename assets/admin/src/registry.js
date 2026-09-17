import { api } from './api/client';
import { toast } from './components/Toast';
import DateField from './components/DateField';
import TimeField from './components/TimeField';
import ClassicEditor, { insertIntoEditor } from './components/ClassicEditor';
import EmailVariables from './components/EmailVariables';
import TestEmailModal from './components/TestEmailModal';

/**
 * What this admin app offers an add-on.
 *
 * An add-on's PHP adds nav entries (id/label/icon) via the
 * `mageyabo_admin_react_routes` filter; its own enqueued JS then calls
 * `window.mageyaboAdmin.registerRoute( id, Component )` to supply the screen
 * for that entry. This app only needs to know a slot with that id might get
 * filled in.
 *
 * `registerSlot( name, Component )` is the same idea for a piece of a built-in
 * screen rather than a whole one - `booking-details`, the panel the Bookings
 * list opens. An empty slot is what the screen shows a locked Pro teaser for.
 *
 * The rest of this object is the shared furniture: the REST client, the toast
 * host, the date and time pickers, the classic editor wrapper. Exposed rather
 * than left for an add-on to reimplement, because a second copy of the date
 * field is a second thing to fix when the first one turns out to be wrong -
 * and it would look subtly different on screens sitting side by side in the
 * same rail.
 */
const routes = {};

export function registerRoute( id, render ) {
	routes[ id ] = render;
}

export function getRoute( id ) {
	return routes[ id ];
}

const slots = {};

export function registerSlot( name, render ) {
	slots[ name ] = render;
}

export function getSlot( name ) {
	return slots[ name ];
}

if ( typeof window !== 'undefined' ) {
	window.mageyaboAdmin = window.mageyaboAdmin || {};
	window.mageyaboAdmin.registerRoute = registerRoute;
	window.mageyaboAdmin.registerSlot = registerSlot;
	window.mageyaboAdmin.api = api;
	window.mageyaboAdmin.toast = toast;
	window.mageyaboAdmin.components = {
		DateField,
		TimeField,
		ClassicEditor,
		insertIntoEditor,
		EmailVariables,
		TestEmailModal,
	};
}
