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
      AND post_type = '".$this->post_type."'
    ";
    // DEV ONLY
    $this->sql .= " LIMIT 1";

    // Loop and execute as needed
    if ( PIH_METHOD == "WP_CLI" ) {
      WP_CLI::add_hook( 'after_wp_load', array( $this, 'do_callback' ) );
    } else {
      add_action( 'wp', array( $this, 'do_callback' ) );
    }
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
  public static function callback( $id ) {
    // Get the post
    $post = get_post( $id );
    // Trigger post update (same data)
    wp_update_post( $post );
    // Clean cache entirely, since we don't need to keep any information about
    // the post we're iterating in memory
    wp_cache_flush();
    // Print updated ID
    self::log( $id." updated" );
  }

  /**
   * Loop through each product and execute callback based on it. Then die.
   */
  public function do_callback() {
    if ( !isset( $this->ids ) ) {
      $this->set_post_ids();
    }
    // Loop through the array of ids
    foreach ( $this->ids as $id ) {
      // Do the callback based on this post's ID
      self::callback( $id );
    }
    // Die
    exit;
  }

  /**
   * Returns an array of all IDs that we want to run the callback on
   * @return array
   */
  public function set_post_ids() {
    // Only run this function once, if we have ids set, bail
    if ( isset( $this->ids ) ) return;
    global $wpdb;
    $this->ids = $wpdb->get_col( $this->sql );
  }

  // This is where we start our execution if called through WP_CLI
  public function wp_cli_entry( $args = null ) {
    // TODO: Parse arguments and such
    $this->do_callback();
  }

}

if ( PIH_METHOD == "WP_CLI" && class_exists( 'WP_CLI' ) ) {
  // Init?
  $helper = Posts_Iterator_Helper::getInstance();
  WP_CLI::add_command( 'zg iterate-posts', array( $helper, 'wp_cli_entry' ) );
}

// Override default SQL
$helper->sql = "
  SELECT
    ID
  FROM $wpdb->posts
  WHERE 1 = 1
  AND post_type = 'post'
  AND post_status IN ('ready-to-post')
  ORDER BY ID ASC
";


// Example Commands:
// 
// wp zg iterate-posts
// wp zg iterate-posts [post-type]
// wp zg iterate-posts [post-type] [code]


// // Get any existing copy of our transient data
// if ( false === ( $iterator_settings = get_transient( 'iterator_settings' ) ) ) {
//   // It wasn't there, so regenerate the data and save the transient
//   $iterator_settings = array();
//   $iterator_settings['total'] = $helper->get_total_ids();
//   set_transient( 'iterator_settings', $iterator_settings, 1 * DAY_IN_SECONDS );
// }

// $total = 
// $amount =
// $offest =


