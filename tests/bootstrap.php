<?php
ini_set( 'display_errors', 'on' );
error_reporting( E_ALL );

$_wp_tests_dir = getenv( 'WP_TESTS_DIR' ) ? : '/tmp/wordpress-tests-lib';
$_pj_ocr_tests_dir = __DIR__;

// Load test function so tests_add_filter() is available.
require_once $_wp_tests_dir . '/includes/functions.php';

// Load and install the filter
require_once $_pj_ocr_tests_dir . '/../object-cache.php';

global $_wp_using_ext_object_cache;
$_wp_using_ext_object_cache = true;
wp_cache_init();

// Load the WP testing environment.
require_once $_wp_tests_dir . '/includes/bootstrap.php';
require_once $_wp_tests_dir . '/tests/cache.php';
