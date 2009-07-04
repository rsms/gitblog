<?
/**
 * MT-safe file database baseclass.
 * 
 * Transaction-based operations which guarantees atomicity. Subclasses should
 * implement txReadData and/or txWriteData to make any sense.
 */
class FileDB {
	public $file;
	public $createmode;
	
	function __construct($file, $createmode=0660) {
		$this->file = $file;
		$this->createmode = $createmode;
	}
	
	protected $txExclusive = true;
	protected $txFp = false;
	protected $data = null;
	
	function begin($exclusive=true) {
		if ($this->txFp !== false)
			throw new LogicException('a transaction is already active');
		$this->txExclusive = $exclusive;
		$this->txFp = @fopen($this->file, 'r+');
		if ( ($this->txFp === false) and (($this->txFp = fopen($this->file, 'x+')) !== false) ) {
			if ($this->createmode !== false)
				chmod($this->file, $this->createmode);
		}
		if ($this->txFp === false)
			throw new RuntimeException('fopen('.var_export($this->file,1).', "x+") failed');
		if ($this->txExclusive and !flock($this->txFp, LOCK_EX)) {
			fclose($this->txFp);
			throw new RuntimeException('flock(<txFp>, LOCK_EX) failed');
		}
	}
	
	protected function txReadData() {
		if ($this->txFp === false)
			throw new LogicException('transaction is not active');
		$fz = @fstat($this->txFp);
		$fz = $fz ? $fz['size'] : 0;
		$this->data = $fz ? fread($this->txFp, $fz) : '';
	}
	
	protected function txWriteData() {
		if ($this->txFp === false)
			throw new LogicException('transaction is not active');
		if ($this->data !== null) {
			if (rewind($this->txFp) === false)
				throw new RuntimeException('rewind(<txFp>) failed');
			if (ftruncate($this->txFp, strlen($this->data)) === false)
				throw new RuntimeException('ftruncate(<txFp>, strlen(<data>)) failed');
			if (fwrite($this->txFp, $this->data) === false)
				throw new RuntimeException('fwrite(<txFp>, <data>) failed');
		}
	}
	
	function commit() {
		if ($this->txFp === false)
			throw new LogicException('transaction is not active');
		$e = null;
		try {
			$this->txWriteData();
		} catch (Exception $e) {}
		$this->txEnd($e);
		if ($e)
			throw $e;
	}
	
	function rollback() {
		if ($this->txFp === false)
			throw new LogicException('transaction is not active');
		return $this->txEnd();
	}
	
	protected function txEnd(Exception $previousex=null) {
		if ($this->txExclusive and !flock($this->txFp, LOCK_UN))
			throw new RuntimeException('flock(<txFp>, LOCK_UN) failed', 0, $previousex);
		if (!fclose($this->txFp))
			throw new RuntimeException('fclose(<txFp>) failed', 0, $previousex);
		$this->txFp = false;
		$this->data = null;
	}
}
?>