import Shell from './components/Shell';
import ErrorBoundary from './components/ErrorBoundary';
import { useHashRoute } from './router';
import { getRoute, getSlot } from './registry';
import { proFeature } from './proFeatures';
import Dashboard from './routes/Dashboard';
import YachtsList from './routes/YachtsList';
import YachtWizard from './routes/YachtWizard';
import Bookings from './routes/Bookings';
import Calendar from './routes/Calendar';
import Guests from './routes/Guests';
import Addons from './routes/Addons';
import SettingsScreen from './routes/Settings';
import ProFeature from './routes/ProFeature';
import UserGuide from './routes/UserGuide';

export default function App() {
	const segments = useHashRoute();
	const [ requested, ...rest ] = segments;

	// A locked control's stand-in page belongs to a built-in screen, which stays
	// lit in the rail. Once the add-on fills that slot the address is simply
	// that screen again - a bookmark or a Back press must not land on an
	// upsell for a feature that is installed.
	const partOf = proFeature( requested )?.parent;
	const section = partOf && getSlot( requested ) ? partOf : requested;

	let screen;

	switch ( section ) {
		case 'yachts':
			if ( rest[ 0 ] === 'new' ) {
				screen = <YachtWizard />;
			} else if ( rest[ 0 ] && rest[ 1 ] === 'edit' ) {
				screen = <YachtWizard yachtId={ Number( rest[ 0 ] ) } />;
			} else {
				screen = <YachtsList />;
			}
			break;
		case 'bookings':
			screen = <Bookings />;
			break;
		case 'calendar':
			screen = <Calendar />;
			break;
		case 'guests':
			screen = <Guests />;
			break;
		case 'addons':
			screen = <Addons />;
			break;
		case 'settings':
			screen = <SettingsScreen />;
			break;
		case 'user-guide':
			screen = <UserGuide />;
			break;
		case 'dashboard':
			screen = <Dashboard />;
			break;
		default: {
			// An add-on's own screen, if one claimed this id...
			const ExtraRoute = getRoute( section );

			if ( ExtraRoute ) {
				screen = <ExtraRoute />;
				break;
			}

			// ...otherwise the locked stand-in, for an id the Pro add-on would
			// have claimed had it been installed.
			screen = proFeature( section ) ? <ProFeature id={ section } /> : <Dashboard />;
		}
	}

	return (
		<ErrorBoundary resetKey={ segments.join( '/' ) }>
			<Shell active={ partOf || section || 'dashboard' }>
				{ screen }
			</Shell>
		</ErrorBoundary>
	);
}
