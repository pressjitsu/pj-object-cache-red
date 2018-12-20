<?php
class Test_Object_Cache extends WP_UnitTestCase {
	public function setUp() {
		global $wp_object_cache;
		$wp_object_cache = new WP_Object_Cache( $this->redis_spy = new Redis_Spy() );
		wp_cache_flush();
	}

	private function assertRedisCalls( $method, $count ) {
		$this->assertEquals( $count, $actual = count( $this->redis_spy->_get( $method ) ), "Redis::$method called $actual times" );
	}

	public function test_simple() {
		$this->assertFalse( wp_cache_get( 'miss' ) );
		$this->assertFalse( wp_cache_get( 'miss', 'group' ) );

		$this->assertTrue( wp_cache_set( 'miss', '1', 'group' ) );
		$this->assertFalse( wp_cache_get( 'miss' ) );
		$this->assertEquals( '1', wp_cache_get( 'miss', 'group' ) );
	}

	public function test_spaces_in_keys() {
		global $wp_object_cache;

		wp_cache_set( 'hello world', '1' );
		$this->assertEquals( '1', wp_cache_get( 'hello world' ) );

		$wp_object_cache->cache = array();

		$this->assertEquals( '1', wp_cache_get( 'hello world' ) );

		wp_cache_set( 'helloworld', '2' );
		$this->assertEquals( '1', wp_cache_get( 'hello world') );
		$this->assertEquals( '2', wp_cache_get( 'helloworld' ) );

		$wp_object_cache->cache = array();

		$this->assertEquals( '1', wp_cache_get( 'hello world') );
		$this->assertEquals( '2', wp_cache_get( 'helloworld' ) );
	}

	public function test_internal_cache_miss() {
		wp_cache_get( 'miss' );
		wp_cache_get( 'miss' );
		wp_cache_get( 'miss', 'default', true );
		$this->assertFalse( wp_cache_get( 'miss' ) );

		$this->assertRedisCalls( 'get', 2 );
	}

	public function test_internal_cache_hit() {
		wp_cache_set( 'hit', '1' );

		wp_cache_get( 'hit' );
		wp_cache_get( 'hit' );
		$this->assertEquals( '1', wp_cache_get( 'hit', 'default', true ) );
		$this->assertEquals( '1', wp_cache_get( 'hit' ) );

		$this->assertRedisCalls( 'get', 1 );
	}

	public function test_incr_decr() {
		wp_cache_incr( 'incr' );
		wp_cache_decr( 'decr' );

		$this->assertEquals( 1, wp_cache_get( 'incr' ) );
		$this->assertEquals( -1, wp_cache_get( 'decr' ) );

		$this->assertRedisCalls( 'incrBy', 2 );
		$this->assertRedisCalls( 'get', 0 );
	}

	public function test_multi_get() {
		wp_cache_set( 'hit', '1' );
		wp_cache_set( 'hit', '2', 'group2' );

		global $wp_object_cache;
		$wp_object_cache->cache = array();

		$result = wp_cache_get_multi( array(
			'group2' => array( 'hit' ),
			'default' => array( 'hit' ),
		) );

		$this->assertEquals( array(
			'group2' => array(
				'1:hit' => '2',
			),
			'default' => array(
				'1:hit' => '1',
			)
		), $result );

		wp_cache_get( 'hit' );
		wp_cache_get( 'hit', 'group2' );

		$this->assertRedisCalls( 'get', 0 );
		$this->assertRedisCalls( 'mget', 1 );
	}

	public function test_preload() {
		wp_cache_set( 'hit', '1' );
		wp_cache_set( 'hit', '2', 'group2' );

		$this->assertEquals( '1', wp_cache_get( 'hit' ) );
		$this->assertEquals( '2', wp_cache_get( 'hit', 'group2' ) );

		$this->redis_spy->_reset();

		global $wp_object_cache;

		$wp_object_cache->save_preloads( 'hash' );
		$wp_object_cache->cache = array();
		$wp_object_cache->preload( 'hash' );

		$this->assertRedisCalls( 'get', 1 );
		$this->assertRedisCalls( 'mget', 1 );

		$this->assertEquals( '1', wp_cache_get( 'hit' ) );
		$this->assertEquals( '2', wp_cache_get( 'hit', 'group2' ) );

		$result = wp_cache_get_multi( array(
			'group2' => array( 'hit' ),
			'default' => array( 'hit' ),
		) );

		$this->assertEquals( array(
			'group2' => array(
				'1:hit' => '2',
			),
			'default' => array(
				'1:hit' => '1',
			)
		), $result );

		$this->assertRedisCalls( 'mget', 1 );
		$this->assertRedisCalls( 'get', 1 );
	}

	public function test_request_preload() {
		global $wp_object_cache;

		/**
		 * Setup.
		 */
		$_SERVER['REQUEST_URI'] = '/home/';
		$request_hash = $wp_object_cache->maybe_preload();

		wp_cache_set( 'home', '1' );
		wp_cache_get( 'home' );

		$wp_object_cache->save_preloads( $request_hash );

		$wp_object_cache->cache = array();
		$wp_object_cache->to_preload = array();

		$_SERVER['REQUEST_URI'] = '/about/';
		$request_hash = $wp_object_cache->maybe_preload();

		wp_cache_set( 'about', '1' );
		wp_cache_get( 'about' );

		$wp_object_cache->save_preloads( $request_hash );

		$wp_object_cache->cache = array();
		$wp_object_cache->to_preload = array();

		/**
		 * Test.
		 */
		$_SERVER['REQUEST_URI'] = '/home/';
		$wp_object_cache->maybe_preload();

		$this->redis_spy->_reset();

		wp_cache_get( 'about' );
		$this->assertRedisCalls( 'get', 1 );
		wp_cache_get( 'home' );
		$this->assertRedisCalls( 'get', 1 );

		$wp_object_cache->cache = array();
		$wp_object_cache->to_preload = array();

		$_SERVER['REQUEST_URI'] = '/about/';
		$wp_object_cache->maybe_preload();

		$this->redis_spy->_reset();

		wp_cache_get( 'about' );
		$this->assertRedisCalls( 'get', 0 );
		wp_cache_get( 'home' );
		$this->assertRedisCalls( 'get', 1 );

		$_SERVER['REQUEST_URI'] = '';
	}

	public function test_preload_before_flush() {
		wp_cache_set( 'hit', '1' );

		global $wp_object_cache;

		$wp_object_cache->save_preloads( 'hash' );
		$wp_object_cache->cache = array();
		$wp_object_cache->preload( 'hash' );

		$this->redis_spy->_reset();

		wp_cache_flush();

		$this->assertFalse( wp_cache_get( 'hit' ) );
		$this->assertRedisCalls( 'get', 1 );
	}

	public function test_preload_before_set() {
		wp_cache_set( 'hit', '1' );

		global $wp_object_cache;

		$wp_object_cache->save_preloads( 'hash' );
		$wp_object_cache->cache = array();
		$wp_object_cache->preload( 'hash' );

		$this->redis_spy->_reset();

		wp_cache_set( 'hit', '2' );

		$this->assertEquals( '2', wp_cache_get( 'hit' ) );

		$this->assertRedisCalls( 'get', 0 );
	}

	public function test_close() {
		$this->assertTrue( wp_cache_close() );
	}

	public function test_delete() {
		$this->assertFalse( wp_cache_delete( 'miss' ) );

		$this->assertFalse( wp_cache_get( 'hit' ) );

		wp_cache_add( 'hit', '1' );

		$this->assertTrue( wp_cache_delete( 'hit' ) );
		$this->assertFalse( wp_cache_get( 'hit' ) );

		$this->assertRedisCalls( 'get', 2 );
	}

	public function test_flush() {
		wp_cache_add( 'hit', '1' );
		wp_cache_flush();
		$this->assertFalse( wp_cache_get( 'hit' ) );
		$this->assertFalse( wp_cache_get( 'hit' ) );

		$this->assertRedisCalls( 'get', 1 );
	}

	public function test_init() {
		$this->assertNull( wp_cache_init() );
	}

	public function test_replace() {
		wp_cache_replace( 'hit', '1' );
		$this->assertFalse( wp_cache_get( 'hit' ) );

		wp_cache_add( 'hit', '1' );
		wp_cache_replace( 'hit', '2' );

		$this->assertEquals( '2', wp_cache_get( 'hit' ) );

		$this->assertRedisCalls( 'exists', 2 );
		$this->assertRedisCalls( 'get', 1 );
	}

	public function test_suspend() {
		wp_suspend_cache_addition( true );

		wp_cache_add( 'hit', '1' );

		$this->assertRedisCalls( 'set', 0 );

		wp_suspend_cache_addition( false );
	}

	public function test_non_persistent() {
		wp_cache_add_non_persistent_groups( 'this' );

		wp_cache_add( 'hit', '1', 'this' );
		wp_cache_incr( 'incr', 1, 'this' );
		wp_cache_decr( 'decr', 1, 'this' );

		$this->assertEquals( '1', wp_cache_get( 'hit', 'this' ) );
		$this->assertEquals(  1, wp_cache_get( 'incr', 'this' ) );
		$this->assertEquals( -1, wp_cache_get( 'decr', 'this' ) );

		$this->assertRedisCalls( 'set', 0 );
		$this->assertRedisCalls( 'incrBy', 0 );
		$this->assertRedisCalls( 'get', 0 );

		global $wp_object_cache;
		$wp_object_cache->save_preloads( 'hash' );

		$this->assertEmpty( wp_cache_get( 'hash', 'pj-preload' ) );
	}

	public function test_multisite() {
		global $wp_object_cache;

		wp_cache_add_global_groups( 'global' );

		wp_cache_add( 'hit', 'global', 'global' );

		$site_1 = get_current_site()->blog_id;
		$site_2 = wpmu_create_blog( wp_generate_password( 12, false ), '/', 'Site 2', 1 );
		$site_3 = wpmu_create_blog( wp_generate_password( 12, false ), '/', 'Site 3', 1 );

		foreach ( array( $site_1, $site_2, $site_3 ) as $site ) {
			switch_to_blog( $site );

			$this->redis_spy->_reset();

			wp_cache_add( 'hit', "_$site" );
			wp_cache_add( 'hit', $site, 'this' );

			// Preheated
			$this->assertEquals( 'global', wp_cache_get( 'hit', 'global' ) );
			$this->assertEquals( "_$site", wp_cache_get( 'hit' ) );
			$this->assertEquals( $site, wp_cache_get( 'hit', 'this' ) );

			$this->assertRedisCalls( 'get', 0 );

			$wp_object_cache->cache = array();

			// Fetched
			$this->assertEquals( 'global', wp_cache_get( 'hit', 'global' ) );
			$this->assertEquals( "_$site", wp_cache_get( 'hit' ) );
			$this->assertEquals( $site, wp_cache_get( 'hit', 'this' ) );

			$this->assertRedisCalls( 'get', 3 );

			$wp_object_cache->cache = array();

			wp_cache_get( 'hit', 'global' );
		}

		switch_to_blog( $site_1 );
	}

	public function test_multisite_preloads() {
		global $wp_object_cache;

		wp_cache_add_global_groups( 'global' );

		wp_cache_add( 'hit', 'global', 'global' );

		$site_1 = get_current_site()->blog_id;
		$site_2 = wpmu_create_blog( wp_generate_password( 12, false ), '/', 'Site 2', 1 );

		switch_to_blog( $site_1 );

		wp_cache_add( 'hit', $site_1, 'this' );

		wp_cache_get( 'hit', 'this' );
		wp_cache_get( 'hit', 'global' );

		$wp_object_cache->save_preloads( $site_1 );

		$wp_object_cache->cache = array();
		$wp_object_cache->to_preload = array();

		switch_to_blog( $site_2 );

		$wp_object_cache->preload( $site_2 );
		$this->redis_spy->_reset();

		$this->assertEquals( 'global', wp_cache_get( 'hit', 'global' ) );
		$this->assertFalse( wp_cache_get( 'hit', 'this' ) );

		$this->assertRedisCalls( 'get', 2 );

		switch_to_blog( $site_1 );

		$wp_object_cache->cache = array();
		$wp_object_cache->to_preload = array();

		$wp_object_cache->preload( $site_1 );
		$this->redis_spy->_reset();

		$this->assertEquals( 'global', wp_cache_get( 'hit', 'global' ) );
		$this->assertEquals( $site_1, wp_cache_get( 'hit', 'this' ) );

		$this->assertRedisCalls( 'get', 0 );
	}
}
