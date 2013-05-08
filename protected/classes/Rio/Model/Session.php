<?php
namespace Rio\Model;

class Session implements \ArrayAccess
{
	const DEFAULT_INSTANCE_NAME = 'default';
	
	private $values;
	
	public function __construct(array &$data = null)
	{
		if ($data === null) {
			if (!session_id()) {
				session_cache_limiter(false);
				session_start();
			}

			$this->values = &$_SESSION;
		}
		else
			$this->values = &$data;
	}

	public function offsetExists($id) { return array_key_exists($id, $this->values); }
	public function offsetUnset($id) { unset($this->values[$id]); }
	public function offsetGet($id) { return $this->offsetExists($id) ? $this->values[$id] : null; }
	public function offsetSet($id, $value) { return $this->values[$id] = $value; }
	public function __isset($id) { return $this->offsetExists($id); }
	public function __unset($id) { return $this->offsetUnset($id); }
	public function __get($id) { return $this->offsetGet($id); }
	public function __set($id, $value) { return $this->offsetSet($id, $value); }	
}