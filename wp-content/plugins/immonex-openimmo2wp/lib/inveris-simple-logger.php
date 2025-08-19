<?php
class inveris_Simple_Logger {

	const
		DEFAULT_LOG_LEVEL = 'info',
		DEFAULT_LOCAL_TIMEZONE = 'Europe/Berlin';

	public
		$is_available = true;

	private
		$raw_log_filename,
		$raw_log_file,
		$message_count = array(),
		$last_message = array(),
		$timezone = false;

	public function __construct( $raw_log_filename = false ) {
		$this->set_timezone( self::DEFAULT_LOCAL_TIMEZONE );

		if ( $raw_log_filename ) {
			// Filename for raw log messages given: Open it to append new entries.
			try {
				$this->raw_log_file = @fopen( $raw_log_filename, 'c+b' );
			} catch ( Exception $e ) {
				$this->is_available = false;
				throw new Exception( sprintf( 'Unable to open/create raw logfile %s: %s' . $raw_log_filename, $e->getMessage() ) );
			}

			if ( is_resource( $this->raw_log_file ) ) {
				$this->raw_log_filename = $raw_log_filename;

				// Update current log level count.
				while ( ! feof( $this->raw_log_file ) ) {
					$message = @unserialize( fgets( $this->raw_log_file ) );
					if ( $message ) {
						$this->last_message = $message;
						if ( isset( $message['log_level'] ) ) $this->_increase_message_count( $message['log_level'] );
					}
				}
			} else {
				$this->is_available = false;
				throw new Exception( 'Unable to open/create raw logfile: ' . $raw_log_filename );
			}
		} elseif ( ! $this->raw_log_file = tmpfile() )  {
			$this->is_available = false;
			throw new Exception( 'Unable to create temporary raw logfile.' );
		}
	} // __construct

	public function __destruct() {
		if ( ! $this->is_available ) return;

		if ( is_resource( $this->raw_log_file ) ) fclose( $this->raw_log_file );
	} // __destruct

	public function reset() {
		if ( ! $this->is_available ) return;

		if ( is_resource( $this->raw_log_file ) ) fclose( $this->raw_log_file );

		if ( $this->raw_log_filename ) {
			// Filename for raw log messages given: Recreate an empty file.
			$this->raw_log_file = fopen( $this->raw_log_filename, 'w+' );
			if ( ! is_resource( $this->raw_log_file ) ) {
				throw new Exception( 'Unable to open/create raw logfile: ' . $this->raw_log_filename );
			}
		}

		$this->message_count = array();
	} // reset

	public function destroy() {
		if ( ! $this->is_available ) return;

		if ( is_resource( $this->raw_log_file ) ) fclose( $this->raw_log_file );

		if ( $this->raw_log_filename && file_exists( $this->raw_log_filename ) ) {
			// Delete logfile for raw messsage data.
			if ( ! @unlink( $this->raw_log_filename ) ) {
				throw new Exception( 'Unable to delete raw logfile: ' . $this->raw_log_filename );
			}
		}

		$this->message_count = array();
	} // destroy

	public function set_timezone( $timezone ) {
		try {
			$this->timezone = new DateTimeZone( $timezone );
		} catch ( Exception $e ) {
			$this->timezone = new DateTimeZone( self::DEFAULT_LOCAL_TIMEZONE );
		}
	} // set_utc_offset

	// DEPRECATED
	public function set_utc_offset( $offset ) {
		$tz = timezone_name_from_abbr( null, $offset * 3600, date( 'I' ) );
		if ( false === $tz ) $tz = timezone_name_from_abbr( null, $offset * 3600, false );
		$this->timezone = new DateTimeZone( $tz );
	} // set_utc_offset

	public function add( $message, $log_level = null ) {
		if ( ! $this->is_available ) return;

		$message = str_replace( PHP_EOL, ' ', trim( $message ) );
		if ( ! isset( $log_level ) || ! $log_level ) $log_level = self::DEFAULT_LOG_LEVEL;

		if ( is_resource( $this->raw_log_file ) && isset( $message ) && $message ) {
			$t = new DateTime( 'now', $this->timezone );

			$message_save = array(
				'time' => $t->format( 'Y-m-d H:i:s.v' ),
				'message' => $message,
				'log_level' => $log_level,
				'repetitions' => 0
			);

			if (
				isset( $this->last_message['message'] ) &&
				isset( $this->last_message['log_level'] ) &&
				$message === $this->last_message['message'] &&
				$log_level === $this->last_message['log_level']
			) {
				// New message equals last message: increase repetition
				// count and overwrite last entry.
				$message_save['repetitions'] = $this->last_message['repetitions'] + 1;
				$this->_rewind_to_current_line_start();
			}

			fwrite( $this->raw_log_file, serialize( $message_save ) . PHP_EOL );

			$this->_increase_message_count( $log_level );
			$this->last_message = $message_save;
		}
	} // add

	public function get_message_count() {
		return $this->message_count;
	} // get_message_count

	public function get_log( $log_levels = '*', $save_time = true, $save_log_level = true ) {
		if ( ! $this->is_available || ! is_resource( $this->raw_log_file ) ) return;

		$contents = '';
		fseek( $this->raw_log_file, 0 );
		while ( ! feof( $this->raw_log_file ) ) {
			$message = @unserialize( fgets( $this->raw_log_file ) );

			if (
				$message &&
				isset( $message['log_level'] ) &&
				(
					$log_levels === '*' ||
					( is_string( $log_levels ) && $message['log_level'] == $log_levels ) ||
					( is_array( $log_levels ) && in_array( $message['log_level'], $log_levels ) ) ||
					$message['log_level'] == '*'
				)
			) {
				if ( $message['message'] != '--' ) {
					if ( $save_time ) $contents .= $message['time'] . ' '; // date( 'Y-m-d H:i:s,v', $message['timestamp'] ) . ' ';
					if ( $save_log_level ) $contents .= '[' . strtoupper( $message['log_level'] ) . '] ';
					$contents .= $message['message'];
					if ( $message['repetitions'] > 0 ) $contents .= ' (' . ( $message['repetitions'] + 1 ) . ' x)';
				}
				$contents .= PHP_EOL;
			}
		}

		return $contents;
	} // get_log

	public function save( $filename, $log_levels = '*', $save_time = true, $save_log_level = true ) {
		if ( ! $this->is_available ) return;

		$f = fopen( $filename, 'wb' );
		if ( $f ) {
			fwrite( $f, $this->get_log( $log_levels, $save_time, $save_log_level ) );
			fclose( $f );
		}
	} // save

	private function _increase_message_count( $log_level ) {
		if ( ! $this->is_available ) return;

		if ( isset( $this->message_count[$log_level] ) ) {
			$this->message_count[$log_level]++;
		} else {
			$this->message_count[$log_level] = 1;
		}
	} // _increase_message_count

	private function _rewind_to_current_line_start() {
		if ( ! $this->is_available ) return;

		fseek( $this->raw_log_file, -2, SEEK_END );
		$offset = ftell( $this->raw_log_file );

		while ( ! in_array( $ch = fgetc( $this->raw_log_file ), array( "\n", "\r" ), true ) && $offset > 0 ) {
			fseek( $this->raw_log_file, $offset-- );
		}
	} // _rewind_to_current_line_start

} // class inveris_Simple_Logger
