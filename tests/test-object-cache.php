<?php
class Test_Object_Cache extends WP_UnitTestCase {
	public function setUp() {
		global $wp_object_cache;
		$wp_object_cache = new WP_Object_Cache( $this->redis_spy = new Redis_Spy() );
	}

	public function test_simple() {
		$this->assertFalse( wp_cache_get( 'miss' ) );
		$this->assertFalse( wp_cache_get( 'miss', 'group' ) );

		$this->assertTrue( wp_cache_set( 'miss', '1', 'group' ) );
		$this->assertFalse( wp_cache_get( 'miss' ) );
		$this->assertEquals( '1', wp_cache_get( 'miss', 'group' ) );
	}

	public function test_internal_cache_miss() {
		wp_cache_get( 'miss' );
		wp_cache_get( 'miss' );
		wp_cache_get( 'miss', 'default', true );
		$this->assertFalse( wp_cache_get( 'miss' ) );

		$this->assertCount( 2, $this->redis_spy->_get( 'get' ) );
	}

	public function test_internal_cache_hit() {
		wp_cache_set( 'hit', '1' );

		wp_cache_get( 'hit' );
		wp_cache_get( 'hit' );
		$this->assertEquals( '1', wp_cache_get( 'hit', 'default', true ) );
		$this->assertEquals( '1', wp_cache_get( 'hit' ) );

		$this->assertCount( 1, $this->redis_spy->_get( 'get' ) );
	}

	public function test_incr_decr() {
		wp_cache_incr( 'incr' );
		wp_cache_decr( 'decr' );

		$this->assertCount( 2, $this->redis_spy->_get( 'incrBy' ) );
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

		var_dump( $result );

		$this->assertCount( 0, $this->redis_spy->_get( 'get' ) );
		$this->assertCount( 1, $this->redis_spy->_get( 'mget' ) );
	}

	public function test_preload() {
		$this->assertTrue( wp_cache_set( 'hit', '1' ) );
		$this->assertTrue( wp_cache_set( 'hit', '2', 'group2' ) );

		$this->assertEquals( '1', wp_cache_get( 'hit' ) );
		$this->assertEquals( '2', wp_cache_get( 'hit', 'group2' ) );

		$this->assertCount( 2, $this->redis_spy->_get( 'get' ) );

		$this->redis_spy->_reset();

		// $wp_object_cache->save_preloads();
		// $wp_object_cache->flush_internal_cache();
		// $wp_object_cache->preload();

		$this->assertCount( 1, $this->redis_spy->_get( 'mget' ) );

		$this->assertEquals( '1', wp_cache_get( 'hit' ) );
		$this->assertEquals( '2', wp_cache_get( 'hit', 'group2' ) );

		$this->assertCount( 0, $this->redis_spy->_get( 'get' ) );
	}
}
