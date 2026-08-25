import './style.css';
import { initBookingForms } from './booking-form';
import { initSearch } from './search';
import { initMaps, initNewsletter } from './map';
import { initAvailabilityCalendars } from './availability-calendar';
import { initGalleries } from './gallery';

function boot() {
	initBookingForms();
	initSearch();
	initMaps();
	initNewsletter();
	initAvailabilityCalendars();
	initGalleries();
}

if ( 'loading' === document.readyState ) {
	document.addEventListener( 'DOMContentLoaded', boot );
} else {
	boot();
}
