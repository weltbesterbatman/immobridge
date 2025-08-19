<?php
/**
 * Class Process_Resources
 *
 * @package immonex\OpenImmo2Wp
 */

namespace immonex\OpenImmo2Wp;

/**
 * Import process resource related methods.
 */
class Process_Resources {

	const DEFAULT_MAX_SCRIPT_EXEC_TIME      = 300;
	const MAX_SCRIPT_EXEC_TIME_MIN          = 30;
	const MAX_SCRIPT_EXEC_TIME_MAX          = 3600;
	const DEFAULT_MAX_SCRIPT_EXEC_TIME_AJAX = 30;
	const MAX_MEMORY_LIMIT                  = '1024M';
	const STALL_CHECK_TIME_MINUTES          = 4;
	const STALL_CHECK_TIME_MINUTES_MIN      = 2;
	const STALL_CHECK_TIME_MINUTES_MAX      = 60;

	/**
	 * Estimated max. processing time per property (seconds).
	 */
	const EST_MAX_PROPERTY_PROC_TIME = 15;

	/**
	 * Default max. number of properties to be imported per script run (0 = no limit).
	 */
	const DEFAULT_MAX_SCRIPT_RUN_PROPERTY_CNT = 0;

	/**
	 * Max. number of deleted properties per script run (full import only).
	 */
	const MAX_SCRIPT_RUN_DELETED_PROPERTIES_CNT = 128;

	/**
	 * Default max. number of processed attachments per script run (0 = no limit).
	 */
	const DEFAULT_MAX_SCRIPT_RUN_ATTACHMENT_CNT = 0;

	/**
	 * Max. number or import attempts for a single image/attachment (0 = no limit).
	 */
	const MAX_ATTACHMENT_IMPORT_ATTEMPTS = 3;

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
	 * Prefix for hook names etc.
	 *
	 * @var string
	 */
	private $prefix;

	/**
	 * Script Start Time
	 *
	 * @var float
	 */
	private $script_start_time = 0;

	/**
	 * Constructor
	 *
	 * @since 5.0.0
	 *
	 * @param mixed[] $bootstrap_data Plugin bootstrap data.
	 * @param object[] $utils Utility objects.
	 */
	public function __construct( $bootstrap_data, $utils ) {
		$this->data   = $bootstrap_data;
		$this->prefix = $bootstrap_data['plugin_prefix'];
		$this->utils  = $utils;

		add_filter( 'admin_memory_limit', [ $this, 'raise_memory_limit' ] );
		add_filter( 'cron_memory_limit', [ $this, 'raise_memory_limit' ] );
		add_filter( 'image_memory_limit', [ $this, 'raise_memory_limit' ] );

		add_filter( "{$this->prefix}estimated_max_property_processing_time", [ $this, 'get_est_max_property_proc_time' ] );
		add_filter( "{$this->prefix}max_attachment_import_attempts", [ $this, 'get_max_attachment_import_attempts' ] );
	} // __construct

	/**
	 * Save the current time in microseconds for runtime measuring purposes.
	 *
	 * @since 5.0.0
	 */
	public function start_timekeeping() {
		$this->script_start_time = microtime( true );
	} // start_timekeeping

	/**
	 * Check if the current process execution time is about to reach or has
	 * exceeded its limit.
	 *
	 * @since 5.0.0
	 *
	 * @return bool True if the execution time limit is (about to be) exceeded.
	 */
	public function exec_time_expired() {
		$max_script_exec_time = defined( 'DOING_AJAX' ) && DOING_AJAX ?
			apply_filters( "{$this->prefix}max_script_exec_time_ajax", self::DEFAULT_MAX_SCRIPT_EXEC_TIME_AJAX ) :
			Registry::get( 'max_script_exec_time' );

		if ( 0 === $max_script_exec_time ) {
			return false;
		}

		$est_max_property_proc_time = (int) apply_filters( "{$this->prefix}estimated_max_property_processing_time", 0 );

		return $this->get_exec_time() > $max_script_exec_time - $est_max_property_proc_time;
	} // exec_time_expired

	/**
	 * Return the estimated max. processing time per property (filter callback).
	 *
	 * @since 5.0.0
	 *
	 * @param int $seconds Default value (seconds).
	 *
	 * @return int Estimated max. processing time per property (seconds).
	 */
	public function get_est_max_property_proc_time( $seconds ) {
		return self::EST_MAX_PROPERTY_PROC_TIME;
	} // get_est_max_property_proc_time

	/**
	 * Return the max. number of attachment import attempts (filter callback).
	 *
	 * @since 5.0.0
	 *
	 * @param int $attempts Default value (attempts).
	 *
	 * @return int Max. number of attachment import attempts.
	 */
	public function get_max_attachment_import_attempts( $attempts ) {
		return self::MAX_ATTACHMENT_IMPORT_ATTEMPTS;
	} // get_max_attachment_import_attempts

	/**
	 * Get the current script execution time.
	 *
	 * @since 5.0.0
	 *
	 * @return int Current execution time in seconds (rounded up).
	 */
	public function get_exec_time() {
		return ceil( microtime( true ) - $this->script_start_time );
	} // get_exec_time

	/**
	 * Get the maximum number of properties to be deleted per script run.
	 *
	 * @since 5.0.0
	 *
	 * @return int Number of properties.
	 */
	public function get_num_of_properties_to_be_deleted() {
		$num = (int) Registry::get( 'max_script_run_deleted_properties_cnt' );

		if ( 0 === $num || $num > self::MAX_SCRIPT_RUN_DELETED_PROPERTIES_CNT ) {
			return self::MAX_SCRIPT_RUN_DELETED_PROPERTIES_CNT;
		}

		return $num;
	} // get_num_of_properties_to_be_deleted

	/**
	 * Check if the given count value is within the attachment import attempt limit.
	 *
	 * @since 5.0.0
	 *
	 * @param int $cnt Current number of import attempts.
	 *
	 * @return bool True if the given count value is within the limit.
	 */
	public function is_within_attachment_import_attempt_limit( $cnt ) {
		$max = (int) apply_filters( "{$this->prefix}max_attachment_import_attempts", 0 );

		return 0 === $max || $cnt <= $max;
	} // is_within_attachment_import_attempt_limit

	/**
	 * Return the strtotime parameter used to check if the current import process
	 * has stalled (maybe modified by a filter function).
	 *
	 * @since 5.0.0
	 *
	 * @param bool $minutes_only Return the minute value only (optional, false by default).
	 *
	 * @return string|int Time parameter.
	 */
	public function get_stall_check_time( $minutes_only = false ) {
		$stall_check_minutes = (int) Registry::get( 'stall_check_minutes' );
		if ( ! $stall_check_minutes ) {
			$stall_check_minutes = self::STALL_CHECK_TIME_MINUTES;
		}

		$stall_check_time_org = "-{$stall_check_minutes} minutes";
		$stall_check_time = apply_filters( "{$this->prefix}stall_check_time", $stall_check_time_org );

		if ( is_numeric( $stall_check_time ) && (int) $stall_check_time >= 2 ) {
			$stall_check_time = "-{$stall_check_time} minutes";
		} elseif ( ! preg_match( '/^-[0-9]{1,2} minutes$/', $stall_check_time ) ) {
			$stall_check_time = $stall_check_time_org;
		}

		return $minutes_only ? 0 - intval( $stall_check_time ) : $stall_check_time;
	} // get_stall_check_time

	/**
	 * Raise the PHP memory limit for import related tasks (filter callback).
	 *
	 * @since 5.0.16-beta
	 *
	 * @param int|string $limit Default memory limit.
	 *
	 * @return int|string New memory limit.
	 */
	public function raise_memory_limit( $limit ) {
		return self::MAX_MEMORY_LIMIT;
	} // raise_memory_limit

} // class Process_Resources
