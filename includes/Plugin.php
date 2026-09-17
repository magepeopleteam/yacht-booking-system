<?php
namespace MageYaBo;

use MageYaBo\Admin\Menu;
use MageYaBo\Booking\AvailabilityService;
use MageYaBo\Cron\Maintenance;
use MageYaBo\Frontend\Block;
use MageYaBo\Frontend\BookingConfirmation;
use MageYaBo\Frontend\Newsletter;
use MageYaBo\Frontend\Shortcode;
use MageYaBo\Frontend\Templates;
use MageYaBo\Install\Migrator;
use MageYaBo\Notifications\BookingEmailer;
use MageYaBo\Payments\Gateways;
use MageYaBo\PostTypes\Yacht;
use MageYaBo\Rest\AddonsController;
use MageYaBo\Rest\BookingsController;
use MageYaBo\Rest\GuestsController;
use MageYaBo\Rest\PricingRulesController;
use MageYaBo\Rest\ReportsController;
use MageYaBo\Rest\SettingsController;
use MageYaBo\Rest\YachtsController;
use MageYaBo\Taxonomies\YachtClass;
use MageYaBo\Taxonomies\YachtOccasion;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Central bootstrap. Every subsystem owns its own `register()` that wires its
 * own WordPress hooks - this class only decides *when in the request
 * lifecycle* each subsystem gets a chance to do that, it never contains
 * feature logic itself.
 */
final class Plugin {

	private static ?Plugin $instance = null;

	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	private function __construct() {}

	public function boot(): void {
		add_action( 'init', array( Yacht::class, 'register' ) );
		add_action( 'init', array( YachtClass::class, 'register' ) );
		add_action( 'init', array( YachtOccasion::class, 'register' ), 5 );
		add_action( 'init', array( YachtClass::class, 'maybe_seed_default_terms' ), 20 );
		add_action( 'init', array( YachtOccasion::class, 'maybe_seed_default_terms' ), 20 );
		add_action( 'init', array( Shortcode::class, 'register' ) );
		add_action( 'init', array( Templates::class, 'register' ) );
		add_action( 'init', array( Block::class, 'register' ) );
		add_action( 'init', array( Newsletter::class, 'register' ) );
		add_action( 'init', array( BookingConfirmation::class, 'register' ) );

		add_action( 'admin_menu', array( Menu::class, 'register' ) );
		add_action( 'admin_init', array( Migrator::class, 'maybe_upgrade' ), 5 );
		add_action( 'admin_init', array( $this, 'maybe_flush_rewrite_rules' ), 20 );

		add_action( 'rest_api_init', array( YachtsController::class, 'register_routes' ) );
		add_action( 'rest_api_init', array( BookingsController::class, 'register_routes' ) );
		add_action( 'rest_api_init', array( GuestsController::class, 'register_routes' ) );
		add_action( 'rest_api_init', array( SettingsController::class, 'register_routes' ) );
		add_action( 'rest_api_init', array( PricingRulesController::class, 'register_routes' ) );
		add_action( 'rest_api_init', array( ReportsController::class, 'register_routes' ) );
		add_action( 'rest_api_init', array( AddonsController::class, 'register_routes' ) );

		/**
		 * Add-ons and site code can register additional `mageyabo/v1` routes here -
		 * e.g. `/guests/{id}` edit/delete, `/tickets/{id}/pdf`.
		 */
		add_action( 'rest_api_init', function () {
			do_action( 'mageyabo_rest_namespace_routes' );
		}, 20 );

		AvailabilityService::register();
		Gateways::register();
		Maintenance::register();
		BookingEmailer::register();

		// No load_plugin_textdomain() call: WordPress has loaded translations
		// for a wordpress.org-hosted plugin's own text domain automatically
		// since 4.6, and calling it manually is now discouraged.
	}

	public function maybe_flush_rewrite_rules(): void {
		if ( get_option( 'mageyabo_flush_rewrite_rules' ) ) {
			flush_rewrite_rules();
			delete_option( 'mageyabo_flush_rewrite_rules' );
		}
	}
}
