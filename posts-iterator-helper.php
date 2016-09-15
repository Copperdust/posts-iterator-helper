<?php
/**
 * @link              https://github.com/Copperdust/
 * @since             1.0.0
 * @package           Posts_Iterator_Helper
 *
 * @wordpress-plugin
 * Plugin Name:       Copperdust's Posts Iterator Helper
 * Plugin URI:        https://github.com/Copperdust/posts-iterator-helper
 * Version:           1.0.0
 * Author:            Fabio Pampin
 * Author URI:        https://github.com/Copperdust/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 */

// Example Commands:
// 
// wp zg iterate-posts --query_posts_per_page=10 --query_paged=2 --query_post_status="array('ready-to-post')"

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

include_once( 'inc/config.php' );

Class Posts_Iterator_Helper {
  /**
   * Singleton START (Normally this'd be a trait but we're using PHP5.3 in UbuntuSrv)
   */
  protected static $instance;
  final public static function getInstance() {
      return isset(static::$instance)
          ? static::$instance
          : static::$instance = new static;
  }
  final private function __construct() {
      $this->init();
  }
  final private function __wakeup() {}
  final private function __clone() {}    
  /**
   * Singleton END (Normally this'd be a trait but we're using PHP5.3 in UbuntuSrv)
   */

  // Default arguments for WP_Query
  public static $default_args = array(
    "paged"          => 1,
    "posts_per_page" => 100,
    "post_type"      => "post",
    "post_status"    => "draft",
  );

  // Unnamed arguments array. Should be only one, the function to iterate posts with
  protected $args = array();

  // Named arguments. Currently only WP_Query args, prefixed with --query_
  protected $args_assoc = array();

  /**
   * Init class
   */
  protected function init() {
  }

  /**
   * Get a human readable time, hopefully with microseconds
   * @return string
   */
  public static function now_with_micro() {
    $t = microtime(true);
    $micro = sprintf("%06d",($t - floor($t)) * 1000000);
    $d = new DateTime( "now", new DateTimeZone( 'America/Argentina/Buenos_Aires' ) );
    $d->setTimestamp( $t );
    return $d->format("Y-m-d H:i:s.u"); // note at point on "u"
  }

  /**
   * Log all arguments sent to this function to the logs directory
   */
  public static function log(){
    $text = str_pad( " ".self::now_with_micro()." ", 80, '*', STR_PAD_BOTH ).PHP_EOL;
    for ($i=0; $i < $arg_count = count( $args = func_get_args() ); $i++) {
      $text .= print_r( is_bool( $args[$i] ) ? $args[$i] ? "(bool) true" : "(bool) false" : $args[$i], true);
      if ( $i != $arg_count - 1 ) {
        $text .= print_r(PHP_EOL.'  -  -  -  -  -  -  -  -  -  -  -  -  -  -  -  -  -  -  -  -  -  -  -  -  -  -  '.PHP_EOL, true);
      } else {
        $text .= print_r(PHP_EOL.PHP_EOL, true);
      }
    }
    if ( PIH_METHOD == "WP_CLI" ) {
      WP_CLI::log( $text );
    } else {
      error_log( $text . "\n", 3, self::$logfile );
    }
  }

  /**
   * Default callback function. Will cause a post update and print out the
   * updated posts' ID
   */
  // TODO Use WordPress's POST instead of a foreach on the results maybe?
  public static function default_callback( $post ) {
    // Trigger post update (same data)
    wp_update_post( $post );
    // Print updated ID
    self::log( $post->ID." updated" );
  }

  /**
   * If a function body was passed, then assign it to $this->callback
   *
   * I know it's fugly, I'm sorry
   */
  private function create_callback( $callback ) {
    // TODO Use this here instead of the ugly eval http://wp-cli.org/docs/internal-api/wp-cli-utils-launch-editor-for-input/
    $func = "\$callback = function( \$post ) { ".PHP_EOL.$callback.PHP_EOL." };";
    eval ($func);
    return $callback;
  }

  /**
   * Get all --query_ arguments and put them on $args
   *
   * We are eval'ing the values passed in order to allow arrays and objects to be passed
   */
  private function parse_query_args( $assoc_args ) {
    $args = array();
    foreach ($assoc_args as $k => $v) {
      // For now we only know what to do with --query_ arguments
      if ( strpos($k, 'query_') !== false ) {
        eval( "\$val = ".$v.";" );
        $args[ str_replace( 'query_', '', $k ) ] = $val;
      }
    }
    return wp_parse_args( $args, self::$default_args );
  }

  /**
   * This is where we start our execution if called through WP_CLI
   *
   * wp zg iterate posts [args] [--assoc_args=assoc_args]
   */
  public function wp_cli_entry( $args = array(), $assoc_args = array() ) {
    // Create default callback if passed, otherwise use default
    $cb = array( __CLASS__, 'default_callback' );
    if ( isset( $args[0] ) ) {
      $cb = $this->create_callback( $args[0] );
    }
    // Parse query args if any were passed
    $args = $this->parse_query_args( $assoc_args );
    // Do query
    $query = new WP_Query( $args );
    // Iterate through callback
    if ( count( $query->posts ) ) {
      array_map( $cb, $query->posts );
    } else {
      // Print query posts for debugging purposes
      self::log( "Posts: ", $query->posts );
      WP_CLI::error( "No posts found." );
    }
  }

}

if ( PIH_METHOD == "WP_CLI" && class_exists( 'WP_CLI' ) ) {
  $helper = Posts_Iterator_Helper::getInstance();
  WP_CLI::add_command( 'iterate-posts', array( $helper, 'wp_cli_entry' ) );
}
