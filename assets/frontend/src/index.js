import './style.css';
import { initBookingForms } from './booking-form';
import { initSearch } from './search';
import { initMaps, initNewsletter } from './map';
import { initAvailabilityCalendars } from './availability-calendar';
import { initGalleries } from './gallery';
import { initYachtList } from './yacht-list';
import { initCarousels } from './carousel';
import { initCustomSelects } from './custom-select';

function boot() {
	initBookingForms();
	initSearch();
	initMaps();
	initNewsletter();
	initAvailabilityCalendars();
	initGalleries();
	initYachtList();
	initCarousels();
	initCustomSelects();
}

if ( 'loading' === document.readyState ) {
	document.addEventListener( 'DOMContentLoaded', boot );
} else {
	boot();
}
