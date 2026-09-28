import { __ } from '@wordpress/i18n';
import { navFeatures } from '../proFeatures';

/**
 * One details page listing every Pro screen not yet unlocked, reached from
 * the rail's single "Pro Features" entry - in place of a separate locked row
 * per feature (see components/Shell.js).
 */
export default function ProFeatures() {
	const features = navFeatures();

	return (
		<div>
			<div className="ybs-page-header">
				<div>
					<h2>
						{ __( 'Pro Features', 'magepeople-yacht-booking-system' ) }
						<span className="ybs-pro-badge">{ __( 'Pro', 'magepeople-yacht-booking-system' ) }</span>
					</h2>
					<p>{ __( 'What the Yacht Booking System Pro add-on unlocks.', 'magepeople-yacht-booking-system' ) }</p>
				</div>
			</div>

			<div className="ybs-pro-feature-grid">
				{ features.map( ( feature ) => (
					<div className="ybs-card ybs-pro-feature-card" key={ feature.id }>
						<span className={ 'dashicons ' + feature.icon } />
						<h3>{ feature.label }</h3>
						<p>{ feature.blurb }</p>
					</div>
				) ) }
			</div>

			<p className="ybs-hint">
				{ __( 'Install and activate the Yacht Booking System Pro add-on to turn these on. Nothing you have already set up changes.', 'magepeople-yacht-booking-system' ) }
			</p>
		</div>
	);
}
