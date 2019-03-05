<?php
ini_set( 'display_errors', 'on' );
error_reporting( E_ALL );

// Always be Multisiting.
define( 'MULTISITE', true );

$_wp_tests_dir     = getenv( 'WP_TESTS_DIR' ) ?: '/tmp/wordpress-tests-lib';
$_pj_ocr_tests_dir = __DIR__;

require_once $_pj_ocr_tests_dir . '/../object-cache.php';
require_once $_pj_ocr_tests_dir . '/redis-spy.php';

global $wp_object_cache;
$wp_object_cache = new Test_WP_Object_Cache_Stub();

/**
 * Class Test_WP_Object_Cache_Stub
 */
class Test_WP_Object_Cache_Stub {
	/**
	 * A minimal stub cache implementation to launch Multisite.
	 */

	/**
	 * Switch to blog.
	 *
	 * @param int $b Blog id.
	 */
	public function switch_to_blog( $b ) {
		$this->b = $b;
	}

	/**
	 * Add global groups.
	 */
	public function add_global_groups() {
	}

	/**
	 * Add non-persistent groups.
	 */
	public function add_non_persistent_groups() {
	}

	/**
	 * Add to cache.
	 *
	 * @param string $k Key.
	 * @param mixed  $v Value.
	 * @param string $g Group.
	 */
	public function add( $k, $v, $g = '' ) {
		isset( $this->c["{$this->b}:$k:$g"] ) || $this->set( $k, $v );
	}

	/**
	 * Set cache.
	 *
	 * @param string $k Key.
	 * @param mixed  $v Value.
	 * @param string $g Group.
	 */
	public function set( $k, $v, $g = '' ) {
		$this->c["{$this->b}:$k:$g"] = $v;
	}

	/**
	 * Get cache.
	 *
	 * @param string $k Key.
	 * @param string $g Group.
	 *
	 * @return bool
	 */
	public function get( $k, $g = '' ) {
		return isset( $this->c["{$this->b}:$k:$g"] ) ? $this->c["{$this->b}:$k:$g"] : false;
	}
};

global $_wp_using_ext_object_cache;
$_wp_using_ext_object_cache = true;

// Load test function so tests_add_filter() is available.
require_once $_wp_tests_dir . '/includes/functions.php';

// Load and install the plugins.
tests_add_filter(
	'muplugins_loaded',
	function () use ( $_pj_ocr_tests_dir ) {
		wp_cache_init();
	}
);

register_shutdown_function(
	function () {
		global $wpdb;
		$wpdb->query( "SET foreign_key_checks = 0" );
		if ( is_multisite() ) {
			foreach ( get_sites() as $site ) {
				switch_to_blog( $site->blog_id );
				foreach ( $wpdb->tables() as $table => $prefixed_table ) {
					$wpdb->query( "DROP TABLE IF EXISTS $prefixed_table" );
				}
			}
		} else {
			foreach ( $wpdb->tables() as $table => $prefixed_table ) {
				$wpdb->query( "DROP TABLE IF EXISTS $prefixed_table" );
			}
		}
	}
);

// Load the WP testing environment.
require_once $_wp_tests_dir . '/includes/bootstrap.php';
