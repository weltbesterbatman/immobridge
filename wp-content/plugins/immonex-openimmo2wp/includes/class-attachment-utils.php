<?php
namespace immonex\OpenImmo2Wp;

/**
 * Plugin-specific attachment helper methods and default values.
 */
class Attachment_Utils {

	const DEFAULT_VALID_IMAGE_FORMATS = [ 'JPG', 'JPEG', 'PNG', 'GIF', 'WEBP', 'AVIF' ];
	const DEFAULT_VALID_VIDEO_FORMATS = [ 'AVI', 'MOV', 'MP4', 'M4V', 'FLV', 'OGV', 'WEBM' ];
	const DEFAULT_VALID_MISC_FORMATS  = [ 'PDF' ];

	/**
	 * Return an array of valid property attachment image file formats (filter callback).
	 *
	 * @since 5.0.3-beta
	 *
	 * @param string[] $formats Original file formats (MIME subtypes).
	 *
	 * @return string[] File formats (MIME subtypes).
	 */
	public static function get_valid_image_file_formats( $formats ) {
		if ( is_array( $formats ) && ! empty( $formats ) ) {
			return array_unique(
				array_merge( self::DEFAULT_VALID_IMAGE_FORMATS, $formats )
			);
		}

		return self::DEFAULT_VALID_IMAGE_FORMATS;
	} // get_valid_image_file_formats

	/**
	 * Return an array of valid property attachment video file formats (filter callback).
	 *
	 * @since 5.0.3-beta
	 *
	 * @param string[] $formats Original file formats (MIME subtypes).
	 *
	 * @return string[] File formats (MIME subtypes).
	 */
	public static function get_valid_video_file_formats( $formats ) {
		if ( is_array( $formats ) && ! empty( $formats ) ) {
			return array_unique(
				array_merge( self::DEFAULT_VALID_VIDEO_FORMATS, $formats )
			);
		}

		return self::DEFAULT_VALID_VIDEO_FORMATS;
	} // get_valid_video_file_formats

	/**
	 * Return an array of valid property attachment miscellaneous file formats (filter callback).
	 *
	 * @since 5.0.3-beta
	 *
	 * @param string[] $formats Original file formats (MIME subtypes).
	 *
	 * @return string[] File formats (MIME subtypes).
	 */
	public static function get_valid_misc_file_formats( $formats ) {
		if ( is_array( $formats ) && ! empty( $formats ) ) {
			return array_unique(
				array_merge( self::DEFAULT_VALID_MISC_FORMATS, $formats )
			);
		}

		return self::DEFAULT_VALID_MISC_FORMATS;
	} // get_valid_misc_file_formats

	/**
	 * Return merged array of all valid attachment file formats (filter callback).
	 *
	 * since 5.0.3-beta
	 *
	 * @param string[] $formats Original array of merged valid file extensions.
	 *
	 * @return string[] Merged valid file extensions
	 */
	public static function get_valid_file_formats( $formats ) {
		$prefix = Registry::get( 'plugin_prefix' );

		return array_unique(
			array_merge(
				apply_filters( "{$prefix}image_file_formats", [] ),
				apply_filters( "{$prefix}video_file_formats", [] ),
				apply_filters( "{$prefix}misc_file_formats", [] )
			)
		);
	} // get_valid_file_formats

	/**
	 * Compare two arrays of property attachment filenames/URLs and check if
	 * the order is the same while ignoring deleted files.
	 *
	 * @since 4.3
	 *
	 * @param string[] $old Previous attachment file list.
	 * @param string[] $new New attachment file list.
	 *
	 * @return bool Order is still the same?
	 */
	public static function is_same_order( $old, $new ) {
		/**
		 * Only regard filenames/URLs of the previous list that also exist
		 * in the new attachment list.
		 */
		$old_intersect = array_intersect( $old, $new );
		$new_compare   = array_slice( $new, 0, count( $old ) );

		return $old_intersect === $new_compare;
	} // is_same_order

	/**
	 * Get a list of attachment filenames/URLs defined in the given property
	 * XML node.
	 *
	 * @since 4.3
	 *
	 * @param SimpleXMLElement $immobilie Property XML node.
	 *
	 * @return string[] Flat array of file names/URLs.
	 */
	public static function get_list( $immobilie ) {
		$files = [];

		if ( isset( $immobilie->anhaenge ) ) {
			foreach ( $immobilie->anhaenge->anhang as $anhang ) {
				$path_or_url = self::get_path_or_url( $anhang );
				if ( $path_or_url ) {
					$files[] = $path_or_url;
				}
			}
		}

		return $files;
	} // get_list

	/**
	 * Get the attachment path/filename/URL from the given XML node.
	 *
	 * @since 4.3
	 *
	 * @param SimpleXMLElement $anhang Property attachment XML node.
	 *
	 * @return string|bool Path/Filename/URL or false if not existent.
	 */
	public static function get_path_or_url( $anhang ) {
		$path_or_url = false;

		if (
			isset( $anhang->daten->pfad )
			&& trim( (string) $anhang->daten->pfad )
		) {
			$path_or_url = trim( (string) $anhang->daten->pfad );
		} elseif (
			isset( $anhang->anhangtitel )
			&& 'http' === substr( strtolower( trim( (string) $anhang->anhangtitel ) ), 0, 4 )
		) {
			$path_or_url = trim( (string) $anhang->anhangtitel );
		}

		return $path_or_url;
	} // get_path_or_url

	/**
	 * Get the property's main/title image set in its XML definition (if given).
	 *
	 * @since 4.3
	 *
	 * @param SimpleXMLElement $anhaenge Property's attachment XML elements.
	 *
	 * @return mixed[]|bool Associative array with filename and attachment XML element or
	 *     false if not found.
	 */
	public static function get_main_image_from_xml( $anhaenge ) {
		$prefix      = Registry::get( 'plugin_prefix' );
		$attachments = $anhaenge->xpath('anhang[@gruppe="TITELBILD"]');

		if ( ! is_array( $attachments ) || 0 === count( $attachments ) ) {
			return false;
		}

		$main_image = array_pop( $attachments );
		$format     = self::get_attachment_format_from_xml(
			$main_image,
			apply_filters( "{$prefix}image_file_formats", [] ),
			true
		);

		if ( $format ) {
			$path_or_url = self::get_path_or_url( $main_image );

			return [
				'filename'    => trim( self::maybe_add_suffix( basename( $path_or_url ), $format ) ),
				'path_or_url' => $path_or_url,
				'xml_element' => $main_image,
			];
		}

		return false;
	} // get_main_image_from_xml

	/**
	 * Get an attachment's file format via XML definition or filename.
	 *
	 * @since 4.3
	 *
	 * @param SimpleXMLElement $anhang        Attachment XML element.
	 * @param string[]         $valid_formats Valid file formats (optional).
	 * @param bool             $quiet         Omit log entries if false (optional).
	 *
	 * @return string File format in upper case if valid, empty string otherwise.
	 */
	public static function get_attachment_format_from_xml( $anhang, $valid_formats = false, $quiet = false ) {
		$format = strtoupper( trim( (string) $anhang->format ) );

		if ( empty( $valid_formats ) ) {
			$valid_formats = self::get_valid_file_formats( array() );
		}

		if ( false !== strpos( $format, '/' ) ) {
			// Split file format declaration (MIME type).
			$mime   = Registry::get( 'string_utils' )::get_mime_type_parts( $format );
			$format = $mime['subtype'];
		}

		if ( ! $format || ! in_array( $format, $valid_formats ) ) {
			$format = false;

			// No or invalid file format definition: Check filename instead.
			$path_or_url = self::get_path_or_url( $anhang );
			$file_info   = $path_or_url ? pathinfo( $path_or_url ) : false;

			if (
				$file_info
				&& isset( $file_info['extension'] )
				&& in_array( strtoupper( $file_info['extension'] ), $valid_formats )
			) {
				// Seems to be a valid file format, use file extension as format definition.
				$format = strtoupper( $file_info['extension'] );
				if ( ! $quiet ) {
					Registry::get( 'log' )->add(
						wp_sprintf(
							__( 'No or invalid attachment format definition, using file extension instead: %s', 'immonex-openimmo2wp' ),
							$format
						),
						'debug'
					);
				}
			}
		}

		return $format ? $format : '';
	} // get_attachment_format_from_xml

	/**
	 * Add a suffix to a filename or an URL if missing.
	 *
	 * @since 4.3
	 *
	 * @param string $file   Filename/Path or URL.
	 * @param string $format Format determined by attachment XML data or file info.
	 *
	 * @return string Original filename/URL, possibly extended by an appropriate suffix.
	 */
	public static function maybe_add_suffix( $file, $format ) {
		if ( ! $format ) {
			return $file;
		}

		$file_info = pathinfo( $file );

		if ( empty( $file_info['extension'] ) ) {
			// Add a lowercase suffix to filenames without one.
			return $file . '.' . str_replace( 'jpeg', 'jpg', strtolower( $format ) );
		}

		return $file;
	} // maybe_add_suffix

	/**
	 * Get or generate a base filename from an URL.
	 *
	 * @since 4.7.0
	 *
	 * @param string $url URL.
	 *
	 * @return string Base filename (path including query if given).
	 */
	public static function get_url_basename( $url ) {
		$url_elements = parse_url( $url );
		$basename     = ! empty( $url_elements['path'] ) ? basename( $url_elements['path'] ) : '';

		if ( ! empty( $url_elements['query'] ) ) {
			$path_parts = pathinfo( $basename );
			$query      = Registry::get( 'string_utils' )::slugify(
				str_replace( '=', '-', $url_elements['query'] )
			);

			$basename = ( ! empty( $path_parts['filename'] ) ? $path_parts['filename'] . '-' : '' ) . $query;
			if ( ! empty( $path_parts['extension'] ) ) {
				$basename .= '.' . $path_parts['extension'];
			}
		}

		return $basename;
	} // get_url_basename

	/**
	 * Check for (and possibly delete) already existing identical image attachments.
	 *
	 * @since 5.0.6-beta
	 *
	 * @param string|int $post_id Property post ID.
	 * @param string     $file    Image file to import (full path).
	 * @param string[]   $exclude IDs of attachments to exclude from check.
	 *
	 * @return bool true if attachment found/deleted.
	 */
	public static function check_existing_image_attachment( $post_id, $file, $exclude = [] ) {
		$args = [
			'post_type'   => 'attachment',
			'numberposts' => -1,
			'post_status' => 'any',
			'post_parent' => $post_id,
			'exclude'     => $exclude,
			'lang'        => ''
		];

		$post_image_attachments = get_posts( $args );

		if ( empty( $post_image_attachments ) ) {
			return false;
		}

		$filename = strtolower( basename( $file ) );

		if ( false !== strpos( $file, '://' ) ) {
			$filesize = (int) Registry::get( 'general_utils' )::get_remote_filesize( $file );
		} else {
			$filesize = filesize( $file );
		}

		foreach ( $post_image_attachments as $att ) {
			$att_file     = get_attached_file( $att->ID );
			$att_filename = strtolower( basename( $att_file ) );
			$att_size     = filesize( $att_file );

			if (
				(
					Registry::get( 'string_utils' )::get_plain_filename( $att_filename ) === $filename
					&& ( ! $att_size || $att_size === $filesize )
				)
				|| Registry::get( 'string_utils' )::get_plain_filename( $att_filename, 'counter+size' ) === $filename
			) {
				/**
				 * Image to be imported already exists (maybe script termination during scaling):
				 * Delete the existing version before importing the same file again.
				 */
				$result = wp_delete_attachment( $att->ID, true );

				if ( $result ) {
					return $att->ID;
				} else {
					Registry::get( 'log' )->add( wp_sprintf( __( 'Error on deleting an image attachment: Attachment ID %s', 'immonex-openimmo2wp' ), $att->ID ), 'error' );
				}
			}
		}

		return false;
	} // check_existing_image_attachment

} // class Attachment_Utils
