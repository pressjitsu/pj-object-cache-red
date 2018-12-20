<?php
class Redis_Spy {
	private $calls= array();

	private $redis = null;

	public function __construct() {
		$this->redis = new Redis();
	}

	public function _get( $method ) {
		return isset( $this->calls[ $method ] ) ? $this->calls[ $method ] : array();
	}

	public function _reset() {
		$this->calls = array();
	}

	public function __call( $method, $arguments ) {
		if ( ! isset( $this->calls[ $method ] ) ) {
			$this->calls[ $method ] = array();
		}

		$return = call_user_func_array( array( $this->redis, $method ), $arguments );

		$this->calls[ $method ][] = array(
			'return' => $return,
			'arguments' => $arguments,
		);

		return $return;
	}
}
