<?php
class GBObjectStore extends JSONStore {
	function __construct($file, $classname, $skeleton_file=null, $createmode=0660, $autocommit=true, $pretty_output=true) {
		parent::__construct($file, $skeleton_file, $createmode, $autocommit, $pretty_output);
		$this->classname = $classname;
	}
	
	function parseData() {
		parent::parseData();
		$f = array($this->classname, '__set_state');
		foreach ($this->data as $k => $v)
			$this->data[$k] = call_user_func($f, $v);
	}
	
	/*function encodeData() {
		parent::encodeData();
	}*/
}
?>