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
	public $data = null;
	
	function __construct($file, $createmode=0660) {
		$this->file = $file;
		$this->createmode = $createmode;
	}
	
	protected $txExclusive = true;
	protected $txFp = false;
	
	function begin($exclusive=true) {
		if ($this->txFp !== false)
			throw new LogicException('a transaction is already active');
		$this->txExclusive = $exclusive;
		$this->txFp = @fopen($this->file, 'r+');
		if ( ($this->txFp === false) && (($this->txFp = fopen($this->file, 'x+')) !== false) ) {
			if ($this->createmode !== false)
				chmod($this->file, $this->createmode);
		}
		if ($this->txFp === false)
			throw new RuntimeException('fopen('.var_export($this->file,1).', "x+") failed');
		if ($this->txExclusive && !flock($this->txFp, LOCK_EX)) {
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
		$ex = null;
		try {
			$this->txWriteData();
		}
		catch (Exception $e) {
			$ex = $e;
		}
		$this->txEnd($ex);
		if ($ex)
			throw $ex;
	}
	
	function rollback() {
		if ($this->txFp === false)
			throw new LogicException('transaction is not active');
		return $this->txEnd();
	}
	
	protected function txEnd(Exception $previousex=null) {
		if ($this->txExclusive && !flock($this->txFp, LOCK_UN))
			throw new RuntimeException('flock(<txFp>, LOCK_UN) failed', 0, $previousex);
		if (!fclose($this->txFp))
			throw new RuntimeException('fclose(<txFp>) failed', 0, $previousex);
		$this->txFp = false;
		$this->data = null;
	}
}
?>