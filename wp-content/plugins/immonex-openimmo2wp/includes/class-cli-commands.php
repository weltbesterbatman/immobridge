<?php
namespace immonex\OpenImmo2Wp;

/**
 * Commands for WP CLI.
 *
 * @since 4.10.0
 */
class Cli_Commands {

  /**
   * Constructor: Register WP CLI commands.
   */
  public function __construct( $instance ) {
    \WP_CLI::add_command( 'immonex-openimmo2wp delete-all', new Cli_Command_Delete_All( $instance ) );
    \WP_CLI::add_command( 'immonex-openimmo2wp import'    , new Cli_Command_import( $instance ) );
    \WP_CLI::add_command( 'immonex-openimmo2wp reset'     , new Cli_Command_Reset( $instance ) );
  } // __construct

} // class Cli_Commands

/**
 *
 */
abstract class Abstract_Cli_Command {

  protected $instance;

  /**
   * Function to be executed.
   */
  abstract public function __invoke( $args, $assoc_args );

  /**
   * Constructor: Register WP CLI commands.
   */
  public function __construct( $instance ) {
    $this->instance = $instance;
  } // __construct

} // abstract class Abstract_Cli_Command

/**
 * Command: delete-all
 */
class Cli_Command_Delete_All extends Abstract_Cli_Command {

  /**
   * Execute delete all post_type `inx_property`.
   *
   * @example `wp immonex-openimmo2wp delete-all`
   */
  public function __invoke( $args, $assoc_args ) {
    $posts = get_posts( array(
      'post_type'   => 'inx_property',
      'numberposts' => -1
    ) );
    foreach ( $posts as $post ) {
      \WP_CLI::debug( sprintf( 'Delete post %d', $post->ID ) );
      wp_delete_post( $post->ID, true );
    }
  } // __invoke

} // class Cli_Command_Delete_All

/**
 * Command: import
 */
class Cli_Command_Import extends Abstract_Cli_Command {

  /**
   * Execute import.
   *
   * @example `wp immonex-openimmo2wp import`
   */
  public function __invoke( $args, $assoc_args ) {
    $this->instance->process( 'import' );
  } // __invoke

} // class Cli_Command_Import

/**
 * Command: reset
 *
 * @example `wp immonex-openimmo2wp reset --user=inveris`
 */
class Cli_Command_Reset extends Abstract_Cli_Command {

  /**
   * Reset a running or broken import.
   */
  public function __invoke( $args, $assoc_args ) {
    // HACK in addition to the CLI parameter `--user=<admin_user>` set WP_ADMIN, because `is_admin()` will be checked
    // during import and for this the user specification alone is not sufficient
    define( 'WP_ADMIN', true );

    $this->instance->process( 'reset' );
  } // __invoke

} // class Cli_Command_Reset
