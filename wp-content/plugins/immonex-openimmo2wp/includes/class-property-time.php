<?php
/**
 * Class Property_Time
 *
 * @package immonex\OpenImmo2Wp
 */

namespace immonex\OpenImmo2Wp;

/**
 * Property creation/update time related methods.
 */
class Property_Time {

	/**
	 * Array of bootstrap data
	 *
	 * @var mixed[]
	 */
	private $data;

	/**
	 * Utility objects
	 *
	 * @var object[]
	 */
	private $utils;

	/**
	 * Prefix for hook names etc.
	 *
	 * @var string
	 */
	private $prefix;

	/**
	 * Constructor
	 *
	 * @since 5.0.0
	 *
	 * @param mixed[] $bootstrap_data Plugin bootstrap data.
     * @param object[] $utils          Utility objects.
     */
    public function __construct( $bootstrap_data, $utils ) {
        $this->data   = $bootstrap_data;
        $this->prefix = $bootstrap_data['plugin_prefix'];
        $this->utils  = $utils;

		add_filter( "{$this->prefix}property_last_update_ts", array( $this, 'get_last_update_ts' ), 10, 2 );
	} // __construct

	/**
	 * Compare two property dates/times.
	 *
	 * @since 5.0.0
	 *
	 * @param string|int $new_property_date_time      Date/Time string or timestamp of the new property.
	 * @param string|int $existing_property_date_time Date/Time string or timestamp of the existing property.
	 *
	 * @return mixed[] Comparison result including compared date/times.
	 */
	public function compare_update_times( $new_property_date_time, $existing_property_date_time ) {
		$new_property_ts = is_numeric( trim( $new_property_date_time ) ) && (int) $new_property_date_time ?
			(int) $new_property_date_time :
			strtotime( $new_property_date_time );

		if ( ! $new_property_ts ) {
			return false;
		}

		$existing_property_ts = is_numeric( trim( $existing_property_date_time ) ) && (int) $existing_property_date_time ?
			(int) $existing_property_date_time :
			strtotime( $existing_property_date_time );

		if ( ! $existing_property_ts ) {
			return false;
		}

		$cmp_new      = date_i18n( 'Y-m-d H:i:s', $new_property_ts );
		$cmp_existing = date_i18n( 'Y-m-d H:i:s', $existing_property_ts );

		/**
		 * Compare the dates only if no time of day is given for the one of the properties.
		 */
		$date_only_cmp = false;

		if ( '00:00:00' === date_i18n( 'H:i:s', $new_property_ts ) ) {
			$existing_property_ts = strtotime( date_i18n( 'Y-m-d', $existing_property_ts ) );
			$date_only_cmp      = true;
		} elseif ( '00:00:00' === date_i18n( 'H:i:s', $existing_property_ts ) ) {
			$new_property_ts = strtotime( date_i18n( 'Y-m-d', $new_property_ts ) );
			$date_only_cmp = true;
		}

		if ( $date_only_cmp ) {
			$cmp_new      = date_i18n( 'Y-m-d', $new_property_ts );
			$cmp_existing = date_i18n( 'Y-m-d', $existing_property_ts );
		}

		return [
			'is_newer'        => $new_property_ts > $existing_property_ts,
			'date_only_cmp'   => $date_only_cmp,
			'cmp_new_ts'      => $new_property_ts,
			'cmp_new'         => $cmp_new,
			'cmp_existing_ts' => $existing_property_ts,
			'cmp_existing'    => $cmp_existing,
		];
	} // compare_update_times

	/**
	 * Return the timestamp of a property's last modification time based on
	 * its XML data set (filter callback).
	 *
	 * @since 5.0.0
	 *
	 * @param int|bool               $ts        Current timestamp or false.
	 * @param \SimpleXMLElement|bool $immobilie Optional property object or false (default).
	 *
	 * @return int|bool Timestamp or false if undeterminable.
	 */
	public function get_last_update_ts( $ts, $immobilie = false ) {
		if ( ! $ts && ! $immobilie instanceof \SimpleXMLElement ) {
			return false;
		}

		if ( ! empty( $immobilie->xpath( '//verwaltung_techn/user_defined_simplefield[@feldname="stand_vom"]' ) ) ) {
			return strtotime( (string) $immobilie->xpath( '//verwaltung_techn/user_defined_simplefield[@feldname="stand_vom"]' )[0] );
		}

		if ( ! $ts && isset( $immobilie->verwaltung_techn->stand_vom ) ) {
			return strtotime( (string) $immobilie->verwaltung_techn->stand_vom );
		}

		return false;
	} // get_last_update_ts

} // class Property_Time
