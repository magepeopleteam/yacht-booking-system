<?php
namespace Ybs\Cron;

use Ybs\Booking\GuestRepository;
use Ybs\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The one scheduled job Free needs: GDPR retention. Scheduled at activation
 * (Install\Migrator::activate) and cleared at deactivation - this class only
 * owns what runs when the hook fires.
 */
class Maintenance {

	public static function register() {
		add_action( 'ybs_daily_maintenance', array( __CLASS__, 'run' ) );
	}

	public static function run() {
		$months = (int) Settings::get( 'retention_months', 0 );

		if ( $months > 0 ) {
			GuestRepository::anonymize_expired( $months );
		}
	}
}
