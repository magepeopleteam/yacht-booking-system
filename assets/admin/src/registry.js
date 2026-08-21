/**
 * Extension registry for the `ybs_admin_react_routes` PHP filter. Pro's PHP
 * side adds nav entries (id/label/icon) via that filter; Pro's own enqueued
 * JS then calls `window.ybsAdmin.registerRoute( id, renderFn )` to supply the
 * component for that entry. Free never needs to know Pro's component code -
 * only that a slot with this id might get filled in.
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
