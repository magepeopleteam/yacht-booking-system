import './style.css';
import { initBookingForms } from './booking-form';
import { initSearch } from './search';
import { initMaps, initNewsletter } from './map';
import { initAvailabilityCalendars } from './availability-calendar';

function boot() {
	initBookingForms();
	initSearch();
	initMaps();
	initNewsletter();
	initAvailabilityCalendars();
}

if ( 'loading' === document.readyState ) {
	document.addEventListener( 'DOMContentLoaded', boot );
} else {
	boot();
}
