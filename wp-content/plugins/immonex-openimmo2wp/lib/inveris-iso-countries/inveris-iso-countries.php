<?php
if ( ! class_exists( 'inveris_Iso_Countries' ) ) :

class inveris_Iso_Countries {

	const
		COUNTRY_DATA_FILE = 'iso_3166_2_countries.csv',
		LANGUAGE_DATA_FILE = 'language-codes-full.csv';

	public static function get_country( $iso_code = '' ) {
		$iso_code = trim( strtoupper( $iso_code ) );
		$countries = array();

		$filename = dirname( __FILE__ ) . '/' . self::COUNTRY_DATA_FILE;
		$f = fopen($filename , 'r' );

		if ( is_resource( $f ) ) {
			$cnt = 0;
			while ( $country_data = fgetcsv( $f ) ) {
				$cnt++;
				if ( 1 === $cnt ) {
					$field_names = $country_data;
					continue;
				}

				foreach ( $field_names as $i => $key ) {
					$named_country_data[$key] = $country_data[$i];
				}

				if (
					$iso_code && (
						$named_country_data['ISO 3166-1 2 Letter Code'] === $iso_code ||
						$named_country_data['ISO 3166-1 3 Letter Code'] === $iso_code
					)
				) {
					fclose($f);
					return $named_country_data;
				} else {
					$countries[ $named_country_data['ISO 3166-1 3 Letter Code'] ] = $named_country_data;
				}
			}

			fclose($f);

			if ( ! $iso_code ) {
				return $countries;
			}
		} else {
			throw new Exception( 'Unable to open country data file: ' . $filename );
		}

		return false;
	} // get_country

	public static function get_language( $iso_code ) {
		$iso_code = trim( strtolower( $iso_code ) );

		$filename = dirname( __FILE__ ) . '/' . self::LANGUAGE_DATA_FILE;
		$f = fopen($filename , 'r' );

		if ( is_resource( $f ) ) {
			$cnt = 0;
			while ( $language_data = fgetcsv( $f ) ) {
				$cnt++;
				if ( $cnt == 1 ) {
					$field_names = $language_data;
					continue;
				}

				foreach ( $field_names as $i => $key ) {
					$named_language_data[$key] = $language_data[$i];
				}

				if (
					$named_language_data['alpha3-b'] === $iso_code ||
					$named_language_data['alpha3-t'] === $iso_code ||
					$named_language_data['alpha2'] === $iso_code
				) {
					fclose($f);
					return $named_language_data;
				}
			}

			fclose($f);
		} else {
			throw new Exception( 'Unable to open language data file: ' . $filename );
		}

		return false;
	} // get_language

} // class inveris_Iso_Countries

endif;
?>