<?php
/**
 * Redis_Spy class file.
 *
 * @package pj-object-cache-red
 */

/**
 * Class Redis_Spy
 */
class Redis_Spy {
	/**
	 * Calls to Redis.
	 *
	 * @var array
	 */
	private $calls = array();

	/**
	 * Redis instance.
	 *
	 * @var Redis
	 */
	private $redis = null;

	/**
	 * Redis_Spy constructor.
	 */
	public function __construct() {
		$this->redis = new Redis();
	}

	/**
	 * Get calls.
	 *
	 * @param string $method Cache method.
	 *
	 * @return array|mixed
	 */
	public function _get( $method ) {
		return isset( $this->calls[ $method ] ) ? $this->calls[ $method ] : array();
	}

	/**
	 * Reset calls.
	 */
	public function _reset() {
		$this->calls = array();
	}

	/**
	 * Spy call.
	 *
	 * @param string $method    Cache method.
	 * @param mixed  $arguments Arguments of the call.
	 *
	 * @return mixed
	 */
	public function __call( $method, $arguments ) {
		if ( ! isset( $this->calls[ $method ] ) ) {
			$this->calls[ $method ] = array();
		}

		$return = call_user_func_array( array( $this->redis, $method ), $arguments );

		$this->calls[ $method ][] = array(
			'return'    => $return,
			'arguments' => $arguments,
		);

		return $return;
	}
}
