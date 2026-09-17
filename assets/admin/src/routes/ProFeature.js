import { __, sprintf } from '@wordpress/i18n';
import { proFeature } from '../proFeatures';

/**
 * Stands in for a Pro screen when the add-on is not installed.
 *
 * Reached through the rail's locked entries, or a locked control on a
 * built-in screen (`parent`): the moment the add-on registers the real thing
 * for an id, that wins and this is never rendered for it.
 */
export default function ProFeature( { id } ) {
	const feature = proFeature( id );

	if ( ! feature ) {
		return null;
	}

	return (
		<div>
			<div className="ybs-page-header">
				<div>
					<h2>
						{ feature.label }
						<span className="ybs-pro-badge">{ __( 'Pro', 'magepeople-yacht-booking-system' ) }</span>
					</h2>
					<p>{ __( 'Available in Yacht Booking System Pro.', 'magepeople-yacht-booking-system' ) }</p>
				</div>
			</div>

			{ feature.parent && (
				<p>
					<a className="ybs-btn" href={ '#/' + feature.parent }>
						{ __( '← Back', 'magepeople-yacht-booking-system' ) }
					</a>
				</p>
			) }

			<div className="ybs-card ybs-pro-card">
				<span className={ 'dashicons ' + feature.icon } />
				<h3>{ sprintf(
					/* translators: %s: name of the Pro feature, e.g. "Check-in". */
					__( '%s is a Pro feature', 'magepeople-yacht-booking-system' ),
					feature.label
				) }</h3>
				<p>{ feature.blurb }</p>
				<p className="ybs-hint">
					{ feature.parent
						? __( 'Install and activate the Yacht Booking System Pro add-on to turn this on. Nothing you have already set up changes.', 'magepeople-yacht-booking-system' )
						: __( 'Install and activate the Yacht Booking System Pro add-on to turn this screen on. Nothing you have already set up changes.', 'magepeople-yacht-booking-system' ) }
				</p>
			</div>
		</div>
	);
}
