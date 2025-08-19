<?php
namespace immonex\OpenImmo2Wp;

/**
 * A simple registry for global data.
 */
abstract class Registry {

	public static $registry;

	/**
	 * Return data of given element.
	 *
	 * @since 4.3
	 *
	 * @param string $key Registry key.
	 *
	 * @return mixed Value or null if nonexistent.
	 */
	public static function get( $key ) {
		if ( isset( self::$registry[ $key ] ) ) {
			return self::$registry[ $key ];
		} else {
			return self::$registry['plugin']->{$key};
		}
	} // get

	/**
	 * Set/Update data of given element.
	 *
	 * @since 4.3
	 *
	 * @param string $key Registry Key.
	 * @param mixed $data Related data.
	 */
	public static function set( $key, $data ) {
		self::$registry[ $key ] = $data;
	} // set

} // class Registry
