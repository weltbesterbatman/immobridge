<?php
namespace immonex\OpenImmo2Wp;

/**
 * Internal methods for filtering/modifying OpenImmo data during import.
 */
class Import_Content_Filters {

	const
		DEC_POINT = ',',
		THOUSANDS_SEP = '.',
		DEFAULT_CURRENCY = '€',
		DEFAULT_DECIMALS = 2,
		SQM_TERM = 'm²',
		DATE_FORMAT = 'd.m.Y',
		DATE_TIME_FORMAT = 'd.m.Y H:i:s';

	/**
	 * Replace a (mainly boolean) value.
	 *
	 * @since 1.0
	 *
	 * @param mixed $value Boolean (or other kind of) value.
	 * @param object SimpleXMLElement $obj SimpleXML node.
	 * @param mixed $args Additional arguments.
	 *
	 * @return string "Yes" or "No".
	 */
	public static function filter_boolean( $value, $obj, $args = array() ) {
		if ( is_string( $value ) ) $value = trim( $value );
		elseif ( is_array( $value ) ) $value = count( $value );

		$translations = array(
			'yes' => __( 'Yes', 'immonex-openimmo2wp' ),
			'no' => __( 'No', 'immonex-openimmo2wp' )
		);

		if ( isset( $args['force_translation'] ) && $args['force_translation'] ) {
			return $value ? $translations['yes'] : $translations['no'];
		} else {
			// Translation will be performed during output.
			return $value ? 'Yes' : 'No';
		}
	} // filter_number_boolean

	/**
	 * Get an integer value.
	 *
	 * @since 1.0
	 *
	 * @param string|float $value Value as string or float.
	 * @param object SimpleXMLElement $obj SimpleXML node.
	 * @param mixed $args Additional arguments.
	 *
	 * @return string Formatted number.
	 */
	public static function filter_integer( $value, $obj, $args = array() ) {
		return intval( self::filter_float( $value, $obj, $args ) );
	} // filter_integer

	/**
	 * Get a float value.
	 *
	 * @since 2.7
	 *
	 * @param string|float $value Value as string or float.
	 * @param object SimpleXMLElement $obj SimpleXML node.
	 * @param mixed $args Additional arguments.
	 *
	 * @return string Number as float value.
	 */
	public static function filter_float( $value, $obj, $args = array() ) {
		global $immonex_openimmo2wp, $immonex_openimmo2wpcasa;

		if ( isset( $immonex_openimmo2wp ) && $immonex_openimmo2wp->string_utils ) {
			return $immonex_openimmo2wp->string_utils->get_float( $value );
		} elseif ( isset( $immonex_openimmo2wpcasa ) && $immonex_openimmo2wpcasa->string_utils ) {
			return $immonex_openimmo2wpcasa->string_utils->get_float( $value );
		}

		return $value;
	} // filter_float

	/**
	 * Format a number.
	 *
	 * @since 1.0
	 *
	 * @param float $value Float value.
	 * @param object SimpleXMLElement $obj SimpleXML node.
	 * @param mixed $args Additional arguments (optional).
	 * @param mixed $decimals Infos on decimal places (optional).
	 *
	 * @return string Formatted number.
	 */
	public static function filter_number_format( $value, $obj, $args = array(), $decimals = false ) {
		if ( ! $decimals ) $decimals = self::_get_decimals( $args );

		if ( is_numeric( $value ) ) $value = number_format( $value, $decimals['decimals'], self::DEC_POINT, self::THOUSANDS_SEP );
		if ( $decimals['cut'] && false !== strpos( $value, self::DEC_POINT ) ) {
			if ( $decimals['comma_cut_only'] ) {
				$value = preg_replace( '/' . ( '.' === self::DEC_POINT ? "\\" : '' ) . self::DEC_POINT . '0*$/', '$1', $value );
			} else {
				$value = preg_replace( '/(' . ( '.' === self::DEC_POINT ? "\\" : '' ) . self::DEC_POINT . '[1-9]*)0*$/', '$1', $value );
			}
		}
		if ( self::DEC_POINT === substr( $value, -1 ) ) $value = substr( $value, 0, -1 );

		return $value;
	} // filter_number_format

	/**
	 * Format number and add currency.
	 *
	 * @since 1.0
	 *
	 * @param float|string $value Float value.
	 * @param object SimpleXMLElement $obj SimpleXML node.
	 * @param mixed $args Additional arguments.
	 *
	 * @return string Formatted number including currency symbol.
	 */
	public static function filter_currency( $value, $obj, $args = array() ) {
		if ( ! trim( $value ) ) return false;

		$pre_check_value = trim(
			str_ireplace(
				array( ',', '€', 'EUR' ),
				array( '.', '', '' ),
				(string) $value
			)
		);

		if ( ! is_numeric( $pre_check_value ) ) return $value;

		$num_value = self::filter_float( $value, $obj, array() );
		if ( ! $num_value ) return $value;

		$currency = isset( $obj->preise->waehrung['iso_waehrung'] ) ? (string) $obj->preise->waehrung['iso_waehrung'] : self::DEFAULT_CURRENCY;
		if ( 'EUR' === $currency ) $currency = '€';

		$unit_params = array(
			$currency,
			isset( $args['mapping_parameters'][0] ) ? $args['mapping_parameters'][0] : 9
		);

		return self::filter_unit( $num_value, $obj, array( 'mapping_parameters' => $unit_params ) );
	} // filter_currency

	/**
	 * Format number and add sqm.
	 *
	 * @since 1.0
	 *
	 * @param float $value Float value.
	 * @param object SimpleXMLElement $obj SimpleXML node.
	 * @param mixed $args Additional arguments.
	 *
	 * @return string Formatted number including sqm term.
	 */
	public static function filter_sqm( $value, $obj, $args = array() ) {
		$num_value = self::filter_float( $value, $obj, array() );
		if ( ! $num_value ) return $value;

		$unit_params = array(
			self::SQM_TERM,
			isset( $args['mapping_parameters'][0] ) ? $args['mapping_parameters'][0] : 0
		);

		return self::filter_unit( $num_value, $obj, array( 'mapping_parameters' => $unit_params ) );
	} // filter_sqm

	/**
	 * Format date.
	 *
	 * @since 1.0
	 *
	 * @param string $value Date or date/time string.
	 * @param object SimpleXMLElement $obj SimpleXML node.
	 * @param mixed $args Additional arguments.
	 *
	 * @return string Formatted date.
	 */
	public static function filter_date( $value, $obj, $args = array() ) {
		$ts = strtotime( self::get_convertible_date( $value ) );

		if ( $ts && strlen( $value ) < 10 ) {
			// Use last day of month if no day is stated in source value.
			$full_date = date_i18n( 'Y-m-t', $ts );
			$ts = strtotime( $full_date );
		}

		return $ts ? date( self::DATE_FORMAT, $ts ) : $value;
	} // filter_date

	/**
	 * Format date/time.
	 *
	 * @since 1.0
	 *
	 * @param string $value Date/Time string.
	 * @param object SimpleXMLElement $obj SimpleXML node.
	 * @param mixed $args Additional arguments.
	 *
	 * @return string Formatted date and time.
	 */
	public static function filter_date_time( $value, $obj, $args = array() ) {
		$ts = strtotime( self::get_convertible_date( $value ) );
		return $ts ? date( self::DATE_TIME_FORMAT, $ts ) : $value;
	} // filter_date_time

	/**
	 * Return a lower case string with the first characer uppercase.
	 *
	 * @since 1.0
	 *
	 * @param string $value The String.
	 * @param object SimpleXMLElement $obj SimpleXML node.
	 * @param mixed $args Additional arguments.
	 *
	 * @return string Converted string.
	 */
	public static function filter_ucfirst( $value, $obj, $args = array() ) {
		return ucfirst( strtolower( $value ) );
	} // filter_ucfirst

	/**
	 * Parse/replace energy pass type.
	 *
	 * @since 1.0
	 *
	 * @param string $value Source energy pass type (OpenImmo standard).
	 * @param object SimpleXMLElement $obj SimpleXML node.
	 * @param mixed $args Additional arguments.
	 *
	 * @return string Parsed string.
	 */
	public static function filter_epass_type( $value, $obj, $args = array() ) {
		switch ( strtolower( $value ) ) {
			case 'bedarf':
				$type_translated = __( 'Demand', 'immonex-openimmo2wp' );
				$type = 'Demand';
				break;
			case 'verbrauch':
				$type_translated = __( 'Consumption', 'immonex-openimmo2wp' );
				$type = 'Consumption';
				break;
			default:
				$type = $value;
		}

		if (
			isset( $type_translated ) &&
			( isset( $args['force_translation'] ) && $args['force_translation'] )
		) {
			return $type_translated;
		} else {
			// Translation will be performed during output.
			return $type;
		}
	} // filter_epass_type

	/**
	 * Parse/replace energy pass year.
	 *
	 * @since 1.0
	 *
	 * @param string $value Energy pass year.
	 * @param object SimpleXMLElement $obj SimpleXML node.
	 * @param mixed $args Additional arguments.
	 *
	 * @return string Parsed string.
	 */
	public static function filter_epass_year( $value, $obj, $args = array() ) {
		switch ( $value ) {
			case '2008':
				$type_translated = __( 'before 2014', 'immonex-openimmo2wp' );
				$type = 'before 2014';
				break;
			case '2014':
				$type_translated = __( 'as from may 2014', 'immonex-openimmo2wp' );
				$type = 'as from may 2014';
				break;
			case 'ohne':
				$type_translated = __( 'not available', 'immonex-openimmo2wp' );
				$type = 'not available';
				break;
			case 'nicht_noetig':
				$type_translated = __( 'not required', 'immonex-openimmo2wp' );
				$type = 'not required';
				break;
			default:
				$type_translated = __( 'not specified', 'immonex-openimmo2wp' );
				$type = 'not specified';
		}

		if ( isset( $args['force_translation'] ) && $args['force_translation'] ) {
			return $type_translated;
		} else {
			// Translation will be performed during output.
			return $type;
		}
	} // filter_epass_year

	/**
	 * Parse/replace energy pass building type.
	 *
	 * @since 1.0
	 *
	 * @param string $value Energy pass building type.
	 * @param object SimpleXMLElement $obj SimpleXML node.
	 * @param mixed $args Additional arguments.
	 *
	 * @return string Parsed string.
	 */
	public static function filter_epass_building_type( $value, $obj, $args = array() ) {
		switch ( $value ) {
			case 'wohn':
				$type_translated = __( 'Residential Building', 'immonex-openimmo2wp' );
				$type = 'Residential Building';
				break;
			case 'nichtwohn':
				$type_translated = __( 'Non-residential Building', 'immonex-openimmo2wp' );
				$type = 'Non-residential Building';
				break;
			default:
				$type_translated = __( 'not specified', 'immonex-openimmo2wp' );
				$type = 'not specified';
		}

		if ( isset( $args['force_translation'] ) && $args['force_translation'] ) {
			return $type_translated;
		} else {
			// Translation will be performed during output.
			return $type;
		}
	} // filter_epass_building_type

	/**
	 * Add energy parameter unit.
	 *
	 * @since 1.0
	 *
	 * @param string $value Energy parameter.
	 * @param object SimpleXMLElement $obj SimpleXML node.
	 * @param mixed $args Additional arguments.
	 *
	 * @return string Parsed string.
	 */
	public static function filter_epass_unit( $value, $obj, $args = array() ) {
		if ( trim( $value ) ) {
			if ( is_numeric( $value ) && false !== strpos( $value, '.' ) ) {
				$value = self::filter_number_format( $value, $obj );
			}

			$value .= '&nbsp;kWh/(m²*a)';
		}

		return $value;
	} // filter_epass_unit

	/**
	 * Convert energy carrier types.
	 *
	 * @since 3.4.1 beta
	 *
	 * @param string $value Energy carrier types (one or more in arbitrary string).
	 * @param object SimpleXMLElement $obj SimpleXML node.
	 * @param mixed $args Additional arguments.
	 *
	 * @return string Parsed string.
	 */
	public static function filter_epass_energy_carriers( $value, $obj, $args = array() ) {
		$force_translation = isset( $args['force_translation'] ) && $args['force_translation'];
		$value = trim( $value, " \t," );

		if ( $value ) {
			$replace = array(
				'OEL' => $force_translation ? __( 'Oil', 'immonex-openimmo2wp' ) : 'Oil',
				'FLUESSIGGAS' => $force_translation ? __( 'Liquid Gas (Own Tank)', 'immonex-openimmo2wp' ) : 'Liquid Gas (Own Tank)',
				'GAS' => $force_translation ? __( 'Gas', 'immonex-openimmo2wp' ) : 'Gas',
				'WASSER-ELEKTRO' => $force_translation ? __( 'Water Electric Heating', 'immonex-openimmo2wp' ) : 'Water Electric Heating',
				'ELEKTRO' => $force_translation ? __( 'Electric', 'immonex-openimmo2wp' ) : 'Electric',
				'ALTERNATIV' => $force_translation ? __( 'Alternative', 'immonex-openimmo2wp' ) : 'Alternative',
				'SOLAR' => $force_translation ? __( 'Solar', 'immonex-openimmo2wp' ) : 'Solar',
				'ERDWAERME' => $force_translation ? __( 'Geothermal', 'immonex-openimmo2wp' ) : 'Geothermal',
				'LUFTWP' => $force_translation ? __( 'Air Heat Pump', 'immonex-openimmo2wp' ) : 'Air Heat Pump',
				'FERN' => $force_translation ? __( 'District Heat', 'immonex-openimmo2wp' ) : 'Long-distance Heat',
				'BLOCK' => $force_translation ? __( 'Thermal Power Station', 'immonex-openimmo2wp' ) : 'Thermal Power Station',
				'PELLET' => $force_translation ? __( 'Pellets', 'immonex-openimmo2wp' ) : 'Pellets',
				'KOHLE' => $force_translation ? __( 'Coal', 'immonex-openimmo2wp' ) : 'Coal',
				'HOLZ' => $force_translation ? __( 'Wood Chips', 'immonex-openimmo2wp' ) : 'Wood Chips',
				'ERDGAS_LEICHT' => $force_translation ? __( 'Natural Gas (light)', 'immonex-openimmo2wp' ) : 'Natural Gas (light)',
				'ERDGAS_SCHWER' => $force_translation ? __( 'Natural Gas (heavy)', 'immonex-openimmo2wp' ) : 'Natural Gas (heavy)',
				'FERNWAERME_DAMPF' => $force_translation ? __( 'District Heat (Steam)', 'immonex-openimmo2wp' ) : 'District Heat (Steam)',
				'NAHWAERME' => $force_translation ? __( 'Local Heat', 'immonex-openimmo2wp' ) : 'Local Heat',
				'WAERMELIEFERUNG' => $force_translation ? __( 'Heat Delivery', 'immonex-openimmo2wp' ) : 'Heat Delivery',
				'BIOENERGIE' => $force_translation ? __( 'Bio Energy', 'immonex-openimmo2wp' ) : 'Bio Energy',
				'WINDENERGIE' => $force_translation ? __( 'Wind Energy', 'immonex-openimmo2wp' ) : 'Wind Energy',
				'WASSERENERGIE' => $force_translation ? __( 'Water Energy', 'immonex-openimmo2wp' ) : 'Water Energy',
				'UMWELTWAERME' => $force_translation ? __( 'Environmental Heat', 'immonex-openimmo2wp' ) : 'Environmental Heat',
				'KWK_FOSSIL' => $force_translation ? __( 'Combined Heat and Power (fossil)', 'immonex-openimmo2wp' ) : 'Combined Heat and Power (fossil)',
				'KWK_ERNEUERBAR' => $force_translation ? __( 'Combined Heat and Power (renewable)', 'immonex-openimmo2wp' ) : 'Combined Heat and Power (renewable)',
				'KWK_REGENERATIV' => $force_translation ? __( 'Combined Heat and Power (regenerative)', 'immonex-openimmo2wp' ) : 'Combined Heat and Power (regenerative)',
				'KWK_BIO' => $force_translation ? __( 'Combined Heat and Power (Bio)', 'immonex-openimmo2wp' ) : 'Combined Heat and Power (Bio)'
			);

			$value = str_replace( array_keys( $replace ), array_values( $replace ), $value );
		}

		return $value;
	} // filter_epass_energy_carriers

	/**
	 * Add variable unit/suffix.
	 *
	 * @since 1.2.4
	 *
	 * @param string $value Element value.
	 * @param object SimpleXMLElement $obj SimpleXML node.
	 * @param mixed $args Additional arguments.
	 *
	 * @return string Parsed string.
	 */
	public static function filter_unit( $value, $obj, $args = array() ) {
		if ( ! trim( $value ) ) return false;

		if ( is_numeric( $value ) && isset( $args['mapping_parameters'][1] ) ) {
			$decimals = self::_get_decimals( $args, 1 );
			$value = self::filter_number_format( $value, $obj, array( 'mapping_parameters' => array( $decimals['decimals'] ) ), $decimals );
		}

		if ( '' !== trim( $value ) && isset( $args['mapping_parameters'][0] ) ) {
			$value .= '&nbsp;' . str_replace( ' ', '&nbsp;', $args['mapping_parameters'][0] );
		}

		return $value;
	} // filter_unit

	/**
	 * Transform incomplete dates into strtotime-convertible data.
	 *
	 * @since 1.0
	 * @access private
	 *
	 * @param string $date Source date.
	 *
	 * @return string Converted or source date.
	 */
	public static function get_convertible_date( $date ) {
		$date = trim( $date );

		// 2/2014, 08/2014 or 10/2014
		$matched = preg_match( '/^[0-9][0-9]?[-\/\.][1-2][0-9]{3}$/', $date, $matches );
		if ( $matched ) return substr( $matches[0], -4 ) . '-' . substr( $matches[0], 0, strpos( $date, $date[strlen($date) - 5] ) );

		// 2/14, 02/14 or 10/14
		$matched = preg_match( '/^\b([0-9][0-9]?)[-\/\.]([1-2][0-9])$/', $date, $matches );
		if ( $matched ) return '20' . $matches[2] . '-' . $matches[1];

		// 2014/2, 2014/08 or 2014/10
		$matched = preg_match( '/^\b[1-2][0-9]{3}[\/\.][0-9][0-9]?$/', $date, $matches );
		if ( $matched ) return str_replace( $date[4], '-', $date );

		return $date;
	} // get_convertible_date

	/**
	 * Get decimal places from mapping parameters.
	 *
	 * @since 2.5
	 * @access private
	 *
	 * @param mixed $args Associative array of mapping arguments.
	 * @param int $decimals_element_id Element ID of the number of decimals (optional).
	 *
	 * @return mixed Associative array containing number of decimals and "cut" parameter.
	 */
	private static function _get_decimals( $args, $decimals_element_id = 0 ) {
		$cut = false;
		$comma_cut_only = false;
		if ( isset( $args['mapping_parameters'][$decimals_element_id] ) ) {
			$decimals = (int) $args['mapping_parameters'][$decimals_element_id];
			if ( 0 === $decimals || 9 === $decimals ) {
				$cut = true;
				$comma_cut_only = 9 === $decimals;
				$decimals = 2;
			}
		} else {
			// Default number of decimal places.
			$decimals = self::DEFAULT_DECIMALS;
		}

		return array(
			'decimals' => $decimals,
			'cut' => $cut,
			'comma_cut_only' => $comma_cut_only
		);
	} // _get_decimals

} // class Import_Content_Filters
