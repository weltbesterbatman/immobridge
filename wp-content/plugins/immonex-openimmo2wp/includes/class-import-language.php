<?php
/**
 * Class Import_Language
 *
 * @package immonex\OpenImmo2Wp
 */

namespace immonex\OpenImmo2Wp;

/**
 * Import language and related hooks.
 */
class Import_Language {

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
	 * Current import language code (ISO-639-1)
	 *
	 * @var string
	 */
	private $current_lang = '';

	/**
	 * Determined multilingual plugin availability states
	 *
	 * @var mixed[]
	 */
	private $ml_plugin_availability = [];

	/**
	 * Constructor
	 *
	 * @since 5.3.12-beta
	 *
	 * @param mixed[]  $bootstrap_data Plugin bootstrap data.
	 * @param object[] $utils          Utility objects.
	 */
	public function __construct( $bootstrap_data, $utils ) {
		$this->data  = $bootstrap_data;
		$this->utils = $utils;

		add_action( 'immonex_oi2wp_set_current_import_language', [ $this, 'set_current_import_language' ] );

		add_filter( 'immonex_oi2wp_enable_multilang', '__return_true' );
		add_filter( 'immonex_oi2wp_force_slug_language_tags', '__return_false' );

		add_filter( 'immonex_oi2wp_current_import_language', [ $this, 'get_current_import_language' ] );
		add_filter( 'immonex_oi2wp_wpml_available', [ $this, 'is_wpml_available' ] );
		add_filter( 'immonex_oi2wp_polylang_available', [ $this, 'is_polylang_available' ] );
	} // __construct

	/**
	 * Return the current import language code (filter callback).
	 *
	 * @since 5.3.12-beta
	 *
	 * @param string $lang Default language code or empty string.
	 *
	 * @return string Current import language code (2-letter, ISO-639-1).
	 */
	public function get_current_import_language( $lang ) {
		if ( ! $this->current_lang ) {
			$this->set_current_import_language( substr( get_locale(), 0, 2 ) );
		}

		return $this->current_lang;
	} // get_current_import_language

	/**
	 * Set the current import language code via OpenImmo2WP Multilang add-on
	 * (action callback).
	 *
	 * @since 5.3.12-beta
	 *
	 * @param string $lang Language code (2-letter, ISO-639-1).
	 */
	public function set_current_import_language( $lang ) {
		if ( strlen( $lang ) > 2 ) {
			$lang = substr( $lang, 0, 2 );
		}

		$this->current_lang = apply_filters(
			'immonex_oi2wp_multilang_set_current_import_language',
			strtolower( $lang )
		);
	} // set_current_import_language

	/**
	 * Check if WPML is available (filter callback).
	 *
	 * @since 5.3.12-beta
	 *
	 * @param bool $available Default value.
	 *
	 * @return bool True if the WPML plugin is active and languages have been set up.
	 */
	public function is_wpml_available( $available ) {
		if ( isset( $this->ml_plugin_availability['wpml'] ) ) {
			return $this->ml_plugin_availability['wpml'];
		}

		$this->ml_plugin_availability['wpml'] = is_plugin_active( 'sitepress-multilingual-cms/sitepress.php' ) &&
			! empty( apply_filters( 'wpml_active_languages', [] ) );

		return $this->ml_plugin_availability['wpml'];
	} // is_wpml_available

	/**
	 * Check if Polylang is available (filter callback).
	 *
	 * @since 5.3.12-beta
	 *
	 * @param bool $available Default value.
	 *
	 * @return bool True if the Polylang plugin is active and languages have been set up.
	 */
	public function is_polylang_available( $available ) {
		if ( isset( $this->ml_plugin_availability['polylang'] ) ) {
			return $this->ml_plugin_availability['polylang'];
		}

		$this->ml_plugin_availability['polylang'] = is_plugin_active( 'polylang/polylang.php' ) &&
			function_exists( 'pll_languages_list' ) &&
			! empty( pll_languages_list() );

		return $this->ml_plugin_availability['polylang'];
	} // is_polylang_available

} // class Import_Language
