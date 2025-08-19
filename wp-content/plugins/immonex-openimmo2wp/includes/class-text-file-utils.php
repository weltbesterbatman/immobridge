<?php
namespace immonex\OpenImmo2Wp;

/**
 * Plugin-specific text file helper methods.
 */
class Text_File_Utils {

	public static function create_processing_xml_files( $source_file, $chunked_write = true ) {
		$dir = dirname( $source_file );
		$source_filename = basename( $source_file );

		$dest_file_full = "{$dir}/proc_{$source_filename}";
		$dest_file_stripped = "{$dir}/proc_stripped_{$source_filename}";

		$after_properties_element = array( '<impressum', '<impressum_strukt',
			'<user_defined_simplefield', '<user_defined_anyfield', '<user_defined_extend', '</anbieter>' );
		$after_properties_elements_preg = str_replace(
			'/', '\/',
			implode( '|', array_map( 'preg_quote', $after_properties_element ) )
		);

		if (
			file_exists( $dest_file_full ) &&
			file_exists( $dest_file_stripped )
		) {
			// Use already existing processing files.
			return array(
				'result' => 'SUCCESS',
				'file_source' => $source_file,
				'file_proc_full' => $dest_file_full,
				'file_proc_stripped' => $dest_file_stripped
			);
		}

		// The source XML file.
		$source = fopen( $source_file, 'r' );
		if ( ! $source ) return array(
			'result' => 'ERROR',
			'message' => __( 'Unable to open the XML source file', 'immonex-openimmo2wp' ),
			'file' => $source_file
		);

		if ( $chunked_write ) {
			// Destination file with (possibly) slightly updated XML source for processing.
			$dest_full = fopen( $dest_file_full, 'w+' );
			if ( ! $dest_full ) return array(
				'result' => 'ERROR',
				'message' => __( 'Unable to create the XML destination file (full)', 'immonex-openimmo2wp' ),
				'file' => $dest_full
			);

			// Destination file with stripped property elements (<immobilie>).
			$dest_stripped = fopen( $dest_file_stripped, 'w+' );
			if ( ! $dest_stripped ) return array(
				'result' => 'ERROR',
				'message' => __( 'Unable to create the XML destination file (stripped)', 'immonex-openimmo2wp' ),
				'file' => $dest_stripped
			);
		}

		$full_contents = '';
		$stripped_contents = '';
		$max_buffer_size = 65536;
		$strip_ns = '';
		$matches = array();
		$property_element_cnt = 0;
		$exclude_property_elements = false;
		$eof = false;

		while ( ! $eof ) {
			$line = '';
			$next = false;

			while ( ! $eof && ! $next ) {
				// Read and concatenate tag by tag until a maximum length or the
				// end of the file is reached...
				$buffer = stream_get_line( $source, $max_buffer_size, '>' );

				if ( false === $buffer ) {
					// End of file reached.
					$eof = true;
				} else {
					if ( ! trim( $buffer ) ) continue;

					// Add current tag to "line".
					$line .= trim( $buffer ) . ( strlen( $buffer ) < $max_buffer_size ? '>' : '' );

					// Add line breaks between (most) tags.
					$line = preg_replace(
						array(
							'/\<openimmo_anid\>\<\/openimmo_anid\>/',
							'/\>\<([^!\/])/',
							'/(\<\/.*?\>)(\<\/.*?\>)/',
							'/(\<.*?\/\>)(\<\/)/'
						),
						array(
							'<openimmo_anid>' . uniqid() . '</openimmo_anid>',
							'>' . PHP_EOL . '<${1}',
							'${1}' . PHP_EOL . '${2}',
							'${1}' . PHP_EOL . '${2}'
						),
						$line
					);

					if ( strlen( $line ) > 4096 ) {
						// Current "Line" has reached the maximum length,
						// continue further processing.
						$next = true;
					}
				}
			}

			// Convert ImmoXML tags to OpenImmo in current line.
			$line = str_replace( array( '<immoxml', '</immoxml' ), array( '<openimmo', '</openimmo' ), $line );

			// Strip all namespace definitions and references in current line.
			$line = preg_replace( '/xmlns[^=]*="[^"]*"/i', '', $line );
			$line = preg_replace( '/[a-zA-Z]+:([a-zA-Z_\-\.\/]+[=> ])/', '$1', $line );

			$line = self::replace_special_html_entities( $line );

			if ( $chunked_write ) {
				// Add current line to full destination file.
				fwrite( $dest_full, $line );
			} else {
				$full_contents .= $line;
			}

			$line_parts = explode( PHP_EOL, $line );

			foreach ( $line_parts as $i => $line_part ) {
				if ( '<immobilie>' === trim( strtolower( $line_part ) ) ) {
					$property_element_cnt++;

					if ( $property_element_cnt > 1 ) {
						$exclude_property_elements = true;
					}
				}

				if (
					preg_match( "/^({$after_properties_elements_preg})/", $line_part ) && (
						( $i === 0 && '</immobilie>' === trim( $line_parts[0] ) ) ||
						( $i > 0 && '</immobilie>' === trim( $line_parts[ $i - 1 ] ) )
					)
				) {
					$property_element_cnt = 0;
					$exclude_property_elements = false;
				}

				if ( ! $exclude_property_elements ) {
					// Add only the first property element (<immobilie>...</immobilie>) PER AGENCY
					// to stripped XML file.
					if ( $chunked_write ) {
						fwrite( $dest_stripped, $line_part . PHP_EOL );
					} else {
						$stripped_contents .= $line_part . PHP_EOL;
					}
				}
			}
		}

		fclose( $source );

		if ( $chunked_write ) {
			fclose( $dest_full );
			fclose( $dest_stripped );
		} else {
			file_put_contents( $dest_file_full, $full_contents );
			file_put_contents( $dest_file_stripped, $stripped_contents );
		}

		return array(
			'result' => 'SUCCESS',
			'file_source' => $source_file,
			'file_proc_full' => $dest_file_full,
			'file_proc_stripped' => $dest_file_stripped
		);
	} // create_processing_xml_files

	public static function replace_special_html_entities( $text ) {
		$replace = array(
			'&auml;' => 'ä',
			'&Auml;' => 'Ä',
			'&uuml;' => 'ü',
			'&Uuml;' => 'Ü',
			'&ouml;' => 'ö',
			'&Ouml;' => 'Ö',
			'&szlig;' => 'ß',
			'&agrave;' => 'à',
			'&aacute;' => 'á',
			'&acirc;' => 'â',
			'&ccedil;' => 'ç',
			'&egrave;' => 'è',
			'&eacute;' => 'é',
			'&ecirc;' => 'ê',
			'&ograve;' => 'ò',
			'&oacute;' => 'ó',
			'&ocirc;' => 'ô',
			'&ugrave;' => 'ù',
			'&uacute;' => 'ú',
			'&ucirc;' => 'û'
		);

		$text = str_replace(
			array_keys( $replace ),
			array_values( $replace ),
			$text
		);

		return $text;
	} // replace_special_html_entities

} // class Text_File_Utils
