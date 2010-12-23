<?php
/**
 * MT-safe file database baseclass.
 * 
 * Transaction-based operations which guarantees atomicity. Subclasses should
 * implement txReadData and/or txWriteData to make any sense.
 */
class FileDB {
	public $file;
	public $skeleton_file;
	public $createmode;
	public $data = null;
	public $mkdirs = true;
	public $autocommitToRepo = false;
	public $autocommitToRepoAuthor = null;
	public $autocommitToRepoMessage = null;
	public $readOnly = false;
	
	function __construct($file, $skeleton_file=null, $createmode=0660) {
		$this->file = $file;
		$this->skeleton_file = $skeleton_file;
		$this->createmode = $createmode;
	}
	
	protected $txExclusive = true;
	protected $txFp = false;
	
	function transactionActive() {
		return $this->txFp !== false;
	}
	
	function begin($exclusive=true) {
		if ($this->txFp !== false)
			throw new LogicException('a transaction is already active');
		
		$this->txExclusive = $exclusive;
		
		if (($this->txFp = @fopen($this->file, 'r+')) === false) {
			if (($this->txFp = @fopen($this->file, 'r')) !== false)
				$this->readOnly = true;
		}
		
		if ($this->txFp === false) {
			if (file_exists($this->file)) {
				if (is_dir($this->file))
					throw new RuntimeException($this->file.' is a directory');
				throw new RuntimeException($this->file.' is not readable');
			}
			if ($this->skeleton_file) {
				copy($this->skeleton_file, $this->file);
				if ($this->createmode !== false)
					chmod($this->file, $this->createmode);
				if (($this->txFp = fopen($this->file, 'r+')) === false)
					throw new RuntimeException('fopen('.var_export($this->file,1).', "r+") failed');
			}
			else {
				if ($this->mkdirs) {
					$dir = dirname($this->file);
					if (!file_exists($dir)) {
						$mode = $this->createmode | 0111;
						mkdir($dir, $mode, true);
						chmod($dir, $mode);
					}
				}
				if (($this->txFp = fopen($this->file, 'x+')) === false)
					throw new RuntimeException('fopen('.var_export($this->file,1).', "x+") failed');
				elseif ($this->createmode !== false)
					chmod($this->file, $this->createmode);
			}
		}
		
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
			if ($this->readOnly)
				return false;
			if (rewind($this->txFp) === false)
				throw new RuntimeException('rewind(<txFp>) failed');
			if (ftruncate($this->txFp, strlen($this->data)) === false)
				throw new RuntimeException('ftruncate(<txFp>, strlen(<data>)) failed');
			if (fwrite($this->txFp, $this->data) === false)
				throw new RuntimeException('fwrite(<txFp>, <data>) failed');
		}
		return true;
	}
	
	function commit() {
		if ($this->txFp === false)
			throw new LogicException('transaction is not active');
		$ex = null;
		try {
			$did_write = $this->txWriteData();
		}
		catch (Exception $e) {
			$ex = $e;
		}
		$this->txEnd($ex);
		if ($ex)
			throw $ex;
		
		# commit to repo
		if ($did_write && $this->autocommitToRepo) {
			gb::log('committing changes to '.$this->file);
			if (!($author = $this->autocommitToRepoAuthor))
				$author = GBUser::findAdmin()->gitAuthor();
			$m = $this->autocommitToRepoMessage ? $this->autocommitToRepoMessage : 'autocommit:'.$this->file;
			git::add($this->file);
			git::commit($m, $author);
			# rollback() will handle gb::reset if needed
		}
		
		return $did_write;
	}
	
	function rollback($strict=true) {
		if ($this->txFp === false) {
			if ($strict)
				throw new LogicException('transaction is not active');
			return false;
		}
		if ($this->autocommitToRepo) {
			try { git::reset($this->file); } catch (Exception $y) {}
		}
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