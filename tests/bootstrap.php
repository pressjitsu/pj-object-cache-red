<?php
ini_set( 'display_errors', 'on' );
error_reporting( E_ALL );

$_wp_tests_dir = getenv( 'WP_TESTS_DIR' ) ? : '/tmp/wordpress-tests-lib';
$_pj_ocr_tests_dir = __DIR__;

require_once $_pj_ocr_tests_dir . '/../object-cache.php';
require_once $_pj_ocr_tests_dir . '/redis-spy.php';

global $_wp_using_ext_object_cache;
$_wp_using_ext_object_cache = true;
global $wp_object_cache;
$wp_object_cache = new class {
	function __call( $_, $__ ) { return true; }
};

// Load test function so tests_add_filter() is available.
require_once $_wp_tests_dir . '/includes/functions.php';

// Load and install the plugins.
tests_add_filter( 'muplugins_loaded', function() {
	wp_cache_init();
} );

// Load the WP testing environment.
require_once $_wp_tests_dir . '/includes/bootstrap.php';
