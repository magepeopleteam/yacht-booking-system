<?php
namespace Ybs;

use Ybs\Admin\Menu;
use Ybs\Booking\AvailabilityService;
use Ybs\Cron\Maintenance;
use Ybs\Frontend\Block;
use Ybs\Frontend\Newsletter;
use Ybs\Frontend\Shortcode;
use Ybs\Frontend\Templates;
use Ybs\Install\Migrator;
use Ybs\Notifications\BookingEmailer;
use Ybs\Payments\Gateways;
use Ybs\PostTypes\Yacht;
use Ybs\Rest\BookingsController;
use Ybs\Rest\GuestsController;
use Ybs\Rest\PricingRulesController;
use Ybs\Rest\ReportsController;
use Ybs\Rest\SettingsController;
use Ybs\Rest\YachtsController;
use Ybs\Taxonomies\YachtClass;
use Ybs\Taxonomies\YachtOccasion;

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

		add_action( 'admin_menu', array( Menu::class, 'register' ) );
		add_action( 'admin_init', array( Migrator::class, 'maybe_upgrade' ), 5 );
		add_action( 'admin_init', array( $this, 'maybe_flush_rewrite_rules' ), 20 );

		add_action( 'rest_api_init', array( YachtsController::class, 'register_routes' ) );
		add_action( 'rest_api_init', array( BookingsController::class, 'register_routes' ) );
		add_action( 'rest_api_init', array( GuestsController::class, 'register_routes' ) );
		add_action( 'rest_api_init', array( SettingsController::class, 'register_routes' ) );
		add_action( 'rest_api_init', array( PricingRulesController::class, 'register_routes' ) );
		add_action( 'rest_api_init', array( ReportsController::class, 'register_routes' ) );

		/**
		 * Pro registers additional `ybs/v1` routes here (spec section 7) -
		 * e.g. `/guests/{id}` edit/delete, `/tickets/{id}/pdf`.
		 */
		add_action( 'rest_api_init', function () {
			do_action( 'ybs_rest_namespace_routes' );
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
		if ( get_option( 'ybs_flush_rewrite_rules' ) ) {
			flush_rewrite_rules();
			delete_option( 'ybs_flush_rewrite_rules' );
		}
	}
}
