<?php
namespace immonex\OpenImmo2Wp;

/**
 * Plugin-specific XML reader methods (pseudo wrapper for \XMLReader).
 */
class XML_Reader {

	private
		$reader,
		$log,
		$at_start = true,
		$xml_file = false,
		$xml_source_code = false,
		$xml_loaded = false;

	public function __construct( $source, $log = false ) {
		$is_file = '<' !== $source[0];
		$this->reader = new \XMLReader();
		if ( $log ) $this->log = $log;

		try {
			if ( $source ) {
				if ( $is_file ) {
					$this->xml_file = $source;
					$this->xml_loaded = $this->reader->open( $this->xml_file );
				} else {
					$this->xml_source_code = $source;
					$this->xml_loaded = $this->reader->XML( $this->xml_source_code );
				}
			}
		} catch ( \Exception $e ) {
			$this->xml_loaded = false;
			if ( $this->log ) $this->log->add( wp_sprintf( __( 'XML Error: %s', 'immonex-openimmo2wp' ) . ' (1)', $e->getMessage() ), 'error' );
		}
	} // __construct

	public function __get( $var_name ) {
		switch ( $var_name ) {
			case 'xml_loaded' :
				return $this->xml_loaded;
				break;
			case 'current_element_name' :
				return $this->reader->name;
				break;
			case 'current_element_node_type' :
				return $this->reader->nodeType;
				break;
		}
	} // __get

	public function close() {
		return $this->reader->close();
	} // close

	public function count_elements( $element_name ) {
		if ( ! $this->xml_loaded ) return false;
		if ( ! $this->at_start ) $this->rewind();

		$cnt = 0;

		while ( $this->reader->read() ) {
			if (
				$this->reader->nodeType === \XMLReader::ELEMENT &&
				$this->reader->name === $element_name
			) {
				$cnt++;
			}
		}

		$this->rewind();

		return $cnt;
	} // count_elements

	public function get_current_element() {
		if ( ! $this->xml_loaded ) return false;

		try {
			$element = new \SimpleXMLElement( $this->reader->readOuterXML() );
		} catch ( \Exception $e ) {
			if ( $this->log ) $this->log->add( wp_sprintf( __( 'XML Error: %s', 'immonex-openimmo2wp' ) . ' (2)', $e->getMessage() ), 'error' );
			return -1;
		}

		return $element;
	} // get_next_element

	public function get_next_element( $element_name, $stop_at_element = false, $rewind = false ) {
		if ( ! $this->xml_loaded ) return false;

		$next = $this->goto_next_element( $element_name, $stop_at_element );

		try {
			$element = $next ? new \SimpleXMLElement( $this->reader->readOuterXML() ) : false;
		} catch ( \Exception $e ) {
			if ( $this->log ) $this->log->add( wp_sprintf( __( 'XML Error: %s', 'immonex-openimmo2wp' ) . ' (3)', $e->getMessage() ), 'error' );
			return -1;
		}

		if ( $rewind ) $this->rewind();

		return $element;
	} // get_next_element

	public function get_obids( $action = 'ADD,CHANGE' ) {
		if ( ! $this->xml_loaded ) return false;
		if ( ! $this->at_start ) $this->rewind();

		$action = array_map( 'trim', explode( ',', $action ) );

		$obids = array();
		$current_obid = false;
		$current_action = false;

		while ( $this->reader->read() ) {
			if ( $this->reader->nodeType === \XMLReader::ELEMENT && 'immobilie' === $this->reader->name ) {
				if ( $current_obid && in_array( $current_action, $action ) ) {
					$obids[] = trim( $current_obid );
				}
				$current_obid = false;
				$current_action = false;
			}

			if ( $this->reader->nodeType === \XMLReader::ELEMENT && 'openimmo_obid' === $this->reader->name ) {
				$current_obid = trim( $this->reader->readString() );
			}

			if ( $this->reader->nodeType == \XMLReader::ELEMENT && 'aktion' === $this->reader->name ) {
				$current_action = $this->reader->getAttribute( 'aktionart' );
				if ( NULL === $current_action ) $current_action = 'ADD';
				$current_action = strtoupper( $current_action );
			}
		}

		if ( trim( $current_obid ) && in_array( $current_action, $action ) ) {
			$obids[] = trim( $current_obid );
		}

		$this->rewind();

		return array_unique( $obids );
	} // get_obids

	public function goto_next_element( $element_name, $stop_at_element = false, $rewind = false ) {
		if ( ! $this->xml_loaded ) return false;

		$element_name = array_map( 'trim', explode( ',', $element_name ) );
		$stop_at_element = array_map( 'trim', explode( ',', $stop_at_element ) );

		$this->at_start = false;

		while (	$this->reader->read() ) {
			if ( $this->reader->nodeType === \XMLReader::ELEMENT ) {
				if ( $stop_at_element && in_array( $this->reader->name, $stop_at_element ) ) {
					if ( $rewind ) $this->rewind();
					return false;
				} elseif ( in_array( $this->reader->name, $element_name ) ) {
					if ( $rewind ) $this->rewind();
					return true;
				}
			}
		}

		if ( $rewind ) $this->rewind();

		return false;
	} // get_next_element

	public function goto_nth_element( $element_name, $n ) {
		if ( ! $this->xml_loaded ) return false;

		$this->rewind();

		$cnt = 0;
		while (
			$cnt < $n &&
			$this->goto_next_element( $element_name )
		) {
			$cnt++;
		}

		return $cnt === $n;
	} // goto_nth_element

	public function has_element( $element_name ) {
		if ( ! $this->xml_loaded ) return false;
		if ( ! $this->at_start ) $this->rewind();

		while ( $this->reader->read() ) {
			if (
				$this->reader->nodeType === \XMLReader::ELEMENT &&
				$this->reader->name === $element_name
			) {
				$this->rewind();
				return true;
			}
		}

		$this->rewind();

		return false;
	} // has_element

	public function is_valid() {
		$this->reader->setParserProperty( \XMLReader::VALIDATE, true );

		return $this->reader->isValid();
	} // is_valid

	public function next() {
		$this->at_start = false;

		return $this->reader->next();
	} // next

	public function read() {
		$this->at_start = false;

		return $this->reader->read();
	} // read

	public function rewind() {
		if ( ! $this->xml_loaded ) return false;

		$this->reader->close();

		if ( $this->xml_file ) {
			$this->xml_loaded = $this->reader->open( $this->xml_file );
		} else {
			$this->xml_loaded = $this->reader->XML( $this->xml_source_code );
		}

		$this->at_start = true;

		return true;
	} // rewind

} // class XML_Reader
