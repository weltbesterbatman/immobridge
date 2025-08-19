<?php
/**
 * Class AreaButler
 *
 * @package immonex\OpenImmo2Wp
 */

namespace immonex\OpenImmo2Wp\Integrations;

/**
 * AreaButler related utility methods.
 */
class AreaButler {

	/**
	 * Extract AreaButler URLs from XML simplefieds or attachment data and
	 * add them to the given array of unique custom fields.
	 *
	 * @since 5.3.5-beta
	 *
	 * @param SimpleXMLElement $immobilie     XML node of a property object.
	 * @param string           $plugin_prefix Plugin prefix.
	 * @param mixed[]          $unique_fields Data to be saved as unique custom fields.
	 */
	public static function add_ab_fields( $immobilie, $plugin_prefix, &$unique_fields ) {
  		$address_publishing_approved = apply_filters(
  			"{$plugin_prefix}approve_property_address_publishing",
  			! in_array( (string) $immobilie->verwaltung_objekt->objektadresse_freigeben, array( 'false', '0' ) )
  		);

		$ab_url_with_address = '';
		$ab_url_no_address   = '';
		$ab_url              = '';

		/**
		 * Extract URLs from onOffice style user_defined_simplefields.
		 */

		if ( ! empty( $immobilie->xpath( '//verwaltung_objekt/user_defined_simplefield[@feldname="MPAreaButlerUrlWithAddress"]' ) ) ) {
			$ab_url_with_address = (string) $immobilie->xpath( '//verwaltung_objekt/user_defined_simplefield[@feldname="MPAreaButlerUrlWithAddress"]' )[0]; // onOffice style
		}

		if ( ! empty( $immobilie->xpath( '//verwaltung_objekt/user_defined_simplefield[@feldname="MPAreaButlerUrlNoAddress"]' ) ) ) {
			$ab_url_no_address = (string) $immobilie->xpath( '//verwaltung_objekt/user_defined_simplefield[@feldname="MPAreaButlerUrlNoAddress"]' )[0]; // onOffice style
		}

		if (
			! $ab_url_with_address
			&& ! $ab_url_no_address
			&& ! empty( $immobilie->anhaenge->anhang )
		) {
			/**
			 * Extract URLs from attachment data.
			 */

			$attachment_urls = \immonex\OpenImmo2Wp\Attachment_Utils::get_list( $immobilie );
			foreach ( $attachment_urls as $url ) {
				if ( ! $ab_url_with_address && preg_match( '/https?:\/\/*app.areabutler.de\/embed\?.*isAddressShown=true/im', $url ) ) {
					$ab_url_with_address = $url;
				}
				if ( ! $ab_url_no_address && preg_match( '/https?:\/\/*app.areabutler.de\/embed\?.*isAddressShown=false/im', $url ) ) {
					$ab_url_no_address = $url;
				}

				if ( $ab_url_with_address && $ab_url_no_address ) {
					break;
				}
			}
		}

		$ab_url = $address_publishing_approved && $ab_url_with_address ?
			$ab_url_with_address : $ab_url_no_address;

		$unique_fields['_immonex_areabutler_url_with_address'] = $ab_url_with_address;
		$unique_fields['_immonex_areabutler_url_no_address']   = $ab_url_no_address;
		$unique_fields['_immonex_areabutler_url']              = $ab_url;
	} // add_ab_fields

} // class AreaButler
