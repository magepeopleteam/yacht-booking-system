/**
 * Extension registry for the `ybs_admin_react_routes` PHP filter. An add-on's
 * PHP side adds nav entries (id/label/icon) via that filter; its own enqueued
 * JS then calls `window.ybsAdmin.registerRoute( id, renderFn )` to supply the
 * component for that entry. This app only needs to know that a slot with
 * that id might get filled in.
 */
const routes = {};

export function registerRoute( id, render ) {
	routes[ id ] = render;
}

export function getRoute( id ) {
	return routes[ id ];
}

if ( typeof window !== 'undefined' ) {
	window.ybsAdmin = window.ybsAdmin || {};
	window.ybsAdmin.registerRoute = registerRoute;
}
