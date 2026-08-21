import Shell from './components/Shell';
import ErrorBoundary from './components/ErrorBoundary';
import { useHashRoute } from './router';
import { getRoute } from './registry';
import Dashboard from './routes/Dashboard';
import YachtsList from './routes/YachtsList';
import YachtWizard from './routes/YachtWizard';
import Bookings from './routes/Bookings';
import Calendar from './routes/Calendar';
import Guests from './routes/Guests';
import SettingsScreen from './routes/Settings';

export default function App() {
	const segments = useHashRoute();
	const [ section, ...rest ] = segments;

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
		case 'settings':
			screen = <SettingsScreen />;
			break;
		case 'dashboard':
			screen = <Dashboard />;
			break;
		default: {
			const ExtraRoute = getRoute( section );
			screen = ExtraRoute ? <ExtraRoute /> : <Dashboard />;
		}
	}

	return (
		<ErrorBoundary resetKey={ segments.join( '/' ) }>
			<Shell active={ section || 'dashboard' }>
				{ screen }
			</Shell>
		</ErrorBoundary>
	);
}
