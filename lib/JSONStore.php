<?php
/**
 * Simple MT-safe JSON-based key-value storage.
 * 
 * With the restriction that the JSON object in the database must be an array
 * (vector or map) since this interface is built upon key-value pairs. Keys
 * must be strings, integers or floats. Values can be of any type.
 */
class JSONStore extends FileDB implements ArrayAccess, Countable {
	public $autocommit = true;
	public $pretty_output = true;
	
	/** For keeping track of modifications done or not */
	protected $originalData = null;
	
	function __construct($file='/dev/null', $skeleton_file=null, $createmode=0660, $autocommit=true, $pretty_output=true) {
		parent::__construct($file, $skeleton_file, $createmode);
		$this->autocommit = $autocommit;
		$this->pretty_output = $pretty_output;
	}
	
	function loadString($s) {
		$this->autocommit = false;
		$this->data = $s;
		$this->parseData();
	}
	
	function throwJSONError($errno=false, $compatmsg='JSON error') {
		if (function_exists('json_last_error')) {
			if ($errno === false)
				$errno = json_last_error();
			switch ($errno) {
				case JSON_ERROR_DEPTH:
					throw new OverflowException('json data too deep');
				case JSON_ERROR_CTRL_CHAR:
					throw new UnexpectedValueException(
						'json data contains invalid or unparsable control character');
				case JSON_ERROR_SYNTAX:
					throw new LogicException('json syntax error');
			}
		}
		else {
			throw new LogicException($compatmsg);
		}
	}
	
	function parseData() {
		$this->originalData = $this->data;
		if ($this->data === '')
			$this->data = array();
		elseif ( ($this->data = json_decode($this->data, true)) === null )
			$this->throwJSONError(false, 'Failed to parse '.gb_relpath(gb::$site_dir, $this->file));
	}
	
	function encodeData() {
		$this->data = $this->pretty_output ? json::pretty($this->data)."\n" : json_encode($this->data);
		if ($this->data === null)
			$this->throwJSONError(false, 'Failed to encode $data ('.gettype($this->data).') as JSON');
	}
	
	protected function txReadData() {
		parent::txReadData();
		$this->parseData();
	}
	
	protected function txWriteData() {
		$this->encodeData();
		if ($this->data !== $this->originalData)
			return parent::txWriteData();
		return false;
	}
	
	function set($key, $value=null) {
		$return_value = true;
		$temptx = $this->txFp === false && $this->autocommit;
		if ($temptx)
			$this->begin();
		if ($this->data === null)
			$this->txReadData();
		if (is_array($key)) {
			$this->data = $key;
		}
		elseif ($value === null) {
			if (($return_value = isset($this->data[$key]))) {
				$return_value = $this->data[$key];
				unset($this->data[$key]);
			}
		}
		else {
			$this->data[$key] = $value;
		}
		if ($temptx)
			$this->commit();
		return $return_value;
	}
	
	function get($key=null, $default=null) {
		$temptx = $this->txFp === false && $this->autocommit && $this->data === null;
		if ($temptx)
			$this->begin();
		if ($this->data === null)
			$this->txReadData();
		$v = $key !== null ? (isset($this->data[$key]) ? $this->data[$key] : $default) : $this->data;
		if ($temptx)
			$this->txEnd();
		return $v;
	}
	
	function offsetGet($k) {
		return $this->get($k);
	}
	
	function offsetSet($k, $v) {
		$this->set($k, $v);
	}
	
	function offsetExists($k) {
		$temptx = $this->txFp === false && $this->autocommit;
		if ($temptx)
			$this->begin();
		if ($this->data === null)
			$this->txReadData();
		$v = isset($this->data[$k]);
		if ($temptx)
			$this->txEnd();
		return $v;
	}
	
	function offsetUnset($k) {
		$this->set($k, null);
	}
	
	function count() {
		return count($this->get());
	}
	
	function __toString() {
		return r($this->get());
	}
}

/*# Test
error_reporting(E_ALL);
$fdb = new JSONStore('/Users/rasmus/Desktop/db.json');
$fdb->begin();
try {
	assert($fdb->get('mykey') === null);
	$fdb->set('mykey', 'myvalue');
	assert($fdb->get('mykey') === 'myvalue');
	assert($fdb->mykey === 'myvalue');
	assert(isset($fdb->mykey) === true);
	unset($fdb->mykey);
	assert(isset($fdb->mykey) === false);
	$fdb->commit();
}
catch(Exception $e) {
	$fdb->rollback();
	throw $e;
}
assert(is_array($fdb->get()));
$v = array('mos' => 'grek', 'hej' => 'bar');
$fdb->set($v);
assert($fdb->get() == $v);*/
?>