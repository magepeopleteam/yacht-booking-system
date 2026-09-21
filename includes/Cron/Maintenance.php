<?php
namespace MageYaBo\Cron;

use MageYaBo\Booking\GuestRepository;
use MageYaBo\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The one scheduled job the plugin needs: GDPR retention. Scheduled at activation
 * (Install\Migrator::activate) and cleared at deactivation - this class only
 * owns what runs when the hook fires.
 */
class Maintenance {

	public static function register() {
		add_action( 'mageyabo_daily_maintenance', array( __CLASS__, 'run' ) );
	}

	public static function run() {
		$months = (int) Settings::get( 'retention_months', 0 );

		if ( $months > 0 ) {
			GuestRepository::anonymize_expired( $months );
		}

		/**
		 * Anything else with housekeeping to do daily. Add-ons trim their own
		 * tables here - the Pro add-on's mail log grows with every email sent,
		 * and is pruned on this hook.
		 */
		do_action( 'mageyabo_daily_maintenance_tasks' );
	}
}
