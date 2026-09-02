import { Component } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Without this, an uncaught render error anywhere in a routed screen makes
 * React 18 unmount the entire root - the app going instantly blank with
 * nothing in the DOM to explain why. This turns that into a visible,
 * recoverable message instead of a white screen.
 */
export default class ErrorBoundary extends Component {
	constructor( props ) {
		super( props );
		this.state = { error: null };
	}

	static getDerivedStateFromError( error ) {
		return { error };
	}

	componentDidCatch( error, info ) {
		// eslint-disable-next-line no-console
		console.error( 'Yacht Booking System admin error:', error, info );
	}

	componentDidUpdate( prevProps ) {
		if ( prevProps.resetKey !== this.props.resetKey && this.state.error ) {
			this.setState( { error: null } );
		}
	}

	render() {
		if ( this.state.error ) {
			return (
				<div className="ybs-notice is-error" style={ { margin: 24 } }>
					<strong>{ __( 'Something went wrong rendering this screen.', 'magepeople-yacht-booking-system' ) }</strong>
					<pre style={ { whiteSpace: 'pre-wrap', marginTop: 8 } }>
						{ String( this.state.error && this.state.error.message || this.state.error ) }
					</pre>
				</div>
			);
		}

		return this.props.children;
	}
}
