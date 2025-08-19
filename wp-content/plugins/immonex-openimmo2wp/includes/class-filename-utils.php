<?php
namespace immonex\OpenImmo2Wp;

/**
 * Filename related helper methods.
 */
class Filename_Utils {

	/**
	 * Return a plain file basename without path, time prefixes and - optionally -
	 * import folder definitions.
	 *
	 * @since 5.1.5
	 *
	 * @param string $file Full path to file.
	 * @param bool $strip_import_folder Strip import folder definitions, too (optional/default).
	 *
	 * @return string Plain basename.
	 */
	public static function get_plain_basename( $file, $strip_import_folder = true ) {
		$basename = basename( $file );

		$date_time_patterns = array(
			'/^([0-9]{8})[-_]([0-9]{4})[-_]/',
			'/^([0-9]{4}[-_][0-9]{2}[-_][0-9]{2})_([0-9]{2})[-_]([0-9]{2})[-_]/',
			'/^([0-9]{8})[-_]/',
			'/^([0-9]{4}[-_][0-9]{2}[-_][0-9]{2})[-_]/'
		);

		do {
			foreach ( $date_time_patterns as $pattern ) {
				$basename = preg_replace( $pattern, '', $basename );
			}

			$time_prefix_exists = false;

			foreach ( $date_time_patterns as $pattern ) {
				if ( 1 === preg_match( $pattern, $basename ) ) {
					$time_prefix_exists = true;
					break;
				}
			}
		} while ( $time_prefix_exists );

		if (
			$strip_import_folder &&
			'_' === $basename[0] &&
			strpos( $basename, '__' )
		) {
			// Also strip an optional import folder name.
			$basename = substr( $basename, strpos( $basename, '__' ) + 2 );
		}

		return $basename;
	} // get_plain_basename

} // class Filename_Utils
