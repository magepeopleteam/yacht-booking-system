<?php
namespace Ybs\Booking;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * `wp_ybs_pricing_rules`. Free's own UI only creates `off_day` (block) and
 * basic weekday/weekend/date-range rules; the table and every other
 * `rule_type`/`adjustment_type` combination already work end-to-end so Pro's
 * seasonal/peak rule types (spec 4.7) need no schema change, just more UI.
 */
class PricingRuleRepository {

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'ybs_pricing_rules';
	}

	public static function list( $yacht_id = null ) {
		global $wpdb;

		if ( $yacht_id ) {
			return $wpdb->get_results(
				$wpdb->prepare(
					'SELECT * FROM ' . self::table() . ' WHERE (yacht_id = %d OR yacht_id IS NULL) AND active = 1 ORDER BY priority DESC',
					$yacht_id
				),
				ARRAY_A
			);
		}

		return $wpdb->get_results( 'SELECT * FROM ' . self::table() . ' ORDER BY priority DESC, id DESC', ARRAY_A ); // phpcs:ignore
	}

	public static function find( $id ) {
		global $wpdb;

		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE id = %d', $id ), ARRAY_A );
	}

	public static function create( array $data ) {
		global $wpdb;

		$now    = current_time( 'mysql' );
		$fields = self::sanitize( $data );

		$fields['created_at'] = $now;
		$fields['updated_at'] = $now;

		$wpdb->insert( self::table(), $fields );

		return (int) $wpdb->insert_id;
	}

	public static function update( $id, array $data ) {
		global $wpdb;

		$fields               = self::sanitize( $data );
		$fields['updated_at'] = current_time( 'mysql' );

		return false !== $wpdb->update( self::table(), $fields, array( 'id' => $id ) );
	}

	public static function delete( $id ) {
		global $wpdb;

		return false !== $wpdb->delete( self::table(), array( 'id' => $id ) );
	}

	/**
	 * The single best-matching rule for a yacht/date, ranked by specificity
	 * then priority. Shared by the pricing engine (to price the booking) and
	 * the availability service (to know a date is blocked before price is
	 * even computed) so the two never disagree about which rule applies.
	 */
	public static function best_match( $yacht_id, $datetime ) {
		$date        = substr( $datetime, 0, 10 );
		$day_of_week = (int) gmdate( 'w', strtotime( $datetime ) );
		$candidates  = self::list( $yacht_id );
		$matches     = array();

		foreach ( $candidates as $rule ) {
			if ( empty( $rule['active'] ) ) {
				continue;
			}

			if ( $rule['date_from'] && $date < $rule['date_from'] ) {
				continue;
			}

			if ( $rule['date_to'] && $date > $rule['date_to'] ) {
				continue;
			}

			if ( ! empty( $rule['days_of_week'] ) ) {
				$days = array_map( 'intval', explode( ',', $rule['days_of_week'] ) );

				if ( ! in_array( $day_of_week, $days, true ) ) {
					continue;
				}
			}

			$specificity  = ( $rule['yacht_id'] ? 2 : 0 ) + ( $rule['date_from'] ? 2 : 0 ) + ( ! empty( $rule['days_of_week'] ) ? 1 : 0 );
			$rule['_specificity'] = $specificity;
			$matches[]    = $rule;
		}

		if ( ! $matches ) {
			return null;
		}

		usort(
			$matches,
			static function ( $a, $b ) {
				if ( $a['_specificity'] !== $b['_specificity'] ) {
					return $b['_specificity'] <=> $a['_specificity'];
				}

				return $b['priority'] <=> $a['priority'];
			}
		);

		return $matches[0];
	}

	private static function sanitize( array $data ) {
		$fields = array();

		if ( array_key_exists( 'yacht_id', $data ) ) {
			$fields['yacht_id'] = $data['yacht_id'] ? (int) $data['yacht_id'] : null;
		}

		if ( isset( $data['rule_type'] ) ) {
			$fields['rule_type'] = sanitize_key( $data['rule_type'] );
		}

		if ( isset( $data['label'] ) ) {
			$fields['label'] = sanitize_text_field( $data['label'] );
		}

		if ( array_key_exists( 'date_from', $data ) ) {
			$fields['date_from'] = $data['date_from'] ? sanitize_text_field( $data['date_from'] ) : null;
		}

		if ( array_key_exists( 'date_to', $data ) ) {
			$fields['date_to'] = $data['date_to'] ? sanitize_text_field( $data['date_to'] ) : null;
		}

		if ( isset( $data['days_of_week'] ) ) {
			$fields['days_of_week'] = implode( ',', array_map( 'intval', (array) $data['days_of_week'] ) );
		}

		if ( isset( $data['adjustment_type'] ) ) {
			$fields['adjustment_type'] = sanitize_key( $data['adjustment_type'] );
		}

		if ( isset( $data['adjustment_value'] ) ) {
			$fields['adjustment_value'] = (float) $data['adjustment_value'];
		}

		if ( isset( $data['priority'] ) ) {
			$fields['priority'] = (int) $data['priority'];
		}

		if ( isset( $data['active'] ) ) {
			$fields['active'] = $data['active'] ? 1 : 0;
		}

		return $fields;
	}
}
