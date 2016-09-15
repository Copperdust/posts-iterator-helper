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

  public static $default_args = array(
    "paged"          => 1,
    "posts_per_page" => 100,
    "post_type"      => "post",
    "post_status"    => "draft",
  );

  public static $logfile = "";

  /**
   * Init class
   */
  protected function init() {
    // If we're trying to use WP_CLI and it doesn't exist, bail silently to
    // avoid problems to a site's FE
    if ( PIH_METHOD == "WP_CLI" && !class_exists( 'WP_CLI' ) ) return;

    // Define default variables

    // Log file
    self::$logfile = trailingslashit( __DIR__.'/logs' ) . "posts-iterator-helper.log";

    // Default post type
    $this->post_type = 'post';

    // Default SQL select
    global $wpdb;
    $this->sql = "
      SELECT
        ID
      FROM $wpdb->posts
      WHERE 1 = 1
    ";
    // FOR DEV ONLY
    $this->sql .= " LIMIT 1";
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
  public static function callback( $post ) {
    // Trigger post update (same data)
    wp_update_post( $post );
    // Print updated ID
    self::log( $post->ID." updated" );
  }

  /**
   * Loop through each post and execute callback based on it. Then die.
   */
  public function do_callback() {
    if ( !is_array( $this->posts ) ) {
      self::log( "Posts: ", $this->posts );
      WP_CLI::error( "No posts were initialized" );
    }
    // Loop through the array of posts
    foreach ( $this->posts as $post ) {
      // Do the callback based on this post's ID
      call_user_func_array( $this->callback, array( $post ) );
    }
    // Die
    exit;
  }

  private function parse_args() {
    if ( !$this->args ) {
      $this->callback = array( __CLASS__, 'callback' );
    } else {
      eval( "\$this->callback = function( $post ) {".$this->args."}" );
    }
  }

  private function parse_query() {
    $keys = array_keys( $this->assoc_args );
    $args = array();
    foreach ($keys as $k) {
      if ( strpos($k, 'query_') !== false ) {
        eval( "\$val = ".$this->assoc_args[ $k ].";" );
        $args[ str_replace( 'query_', '', $k ) ] = $val;
      }
    }
    $args = wp_parse_args( $args, self::$default_args );

    $this->query = new WP_Query( $args );
    $this->posts = $this->query->posts;
  }

  /**
   * This is where we start our execution if called through WP_CLI
   *
   * wp zg iterate posts [args] [--assoc_args=assoc_args]
   */
  public function wp_cli_entry( $args = false, $assoc_args = false ) {
    $this->args = $args;
    $this->assoc_args = $assoc_args;
    $this->parse_args();
    
    $this->parse_query();

    $this->do_callback();
  }

}

if ( PIH_METHOD == "WP_CLI" && class_exists( 'WP_CLI' ) ) {
  // Init?
  $helper = Posts_Iterator_Helper::getInstance();
  WP_CLI::add_command( 'zg iterate-posts', array( $helper, 'wp_cli_entry' ) );
}
