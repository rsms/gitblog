<?
/**
 * Simple MT-safe JSON database.
 * 
 * With the restriction that the JSON object in the database must be an array
 * (vector or map) since this interface is built upon key-value pairs. Keys
 * must be strings, integers or floats. Values can be of any type.
 */
class JSONDB extends FileDB {
	public $autocommit = true;
	
	/** For keeping track of modifications done or not */
	protected $originalData = null;
	
	function __construct($file='/dev/null', $createmode=0660, $autocommit=true) {
		parent::__construct($file, $createmode);
		$this->autocommit = $autocommit;
	}
	
	function loadString($s) {
		$this->autocommit = false;
		$this->data = $s;
		$this->parseData();
	}
	
	function throwJsonEncoderError($errno=false) {
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
			throw new LogicException('json error');
		}
	}
	
	function parseData() {
		$this->originalData = $this->data;
		if ($this->data === '')
			$this->data = array();
		elseif ( ($this->data = json_decode($this->data, true)) === null )
			$this->throwJsonEncoderError();
	}
	
	protected function txReadData() {
		parent::txReadData();
		$this->parseData();
	}
	
	protected function txWriteData() {
		$this->data = json_encode($this->data);
		if ($this->data === null)
			$this->throwJsonEncoderError();
		elseif ($this->data != $this->originalData)
			parent::txWriteData();
	}
	
	function set($key, $value=null) {
		$temptx = $this->txFp === false && $this->autocommit;
		if ($temptx)
			$this->begin();
		if ($this->data === null)
			$this->txReadData();
		if (is_array($key))
			$this->data = $key;
		elseif ($value === null)
			unset($this->data[$key]);
		else
			$this->data[$key] = $value;
		if ($temptx)
			$this->commit();
	}
	
	function get($key=null) {
		$temptx = $this->txFp === false && $this->autocommit;
		if ($temptx)
			$this->begin();
		if ($this->data === null)
			$this->txReadData();
		$v = $key !== null ? (isset($this->data[$key]) ? $this->data[$key] : null) : $this->data;
		if ($temptx)
			$this->txEnd();
		return $v;
	}
	
	function __get($key) {
		return $this->get($key);
	}
	
	function __set($key, $value) {
		return $this->set($key, $value);
	}
	
	function __isset($key) {
		return $this->get($key) !== null;
	}
	
	function __unset($key) {
		return $this->set($key, null);
	}
}

/*# Test
error_reporting(E_ALL);
$fdb = new JSONDB('/Users/rasmus/Desktop/db.json');
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