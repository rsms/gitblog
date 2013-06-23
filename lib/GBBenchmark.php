<?php
class GBBenchmark {
	static public $t;
	static function iterations($iterations=100000) {
		return new GBBenchmarkIterator($iterations);
	}
}

class GBBenchmarkIterator implements Iterator {
	public $t;
	public $iterations;
	public $current;
	
	public $buf;
	public $utime = 0.0;
	public $stime = 0.0;
	public $rtime = 0.0;
	
	function _rus($end=false) {
		$this->buff = getrusage();
		if($end) {
			$this->rtime = microtime(true) - $this->rtime;
			$this->utime = floatval($this->buff["ru_utime.tv_sec"].$this->buff["ru_utime.tv_usec"] - $this->utime)/1000000.0;
			$this->stime = floatval($this->buff["ru_stime.tv_sec"].$this->buff["ru_stime.tv_usec"] - $this->stime)/1000000.0;
		}
		else {
			$this->rtime = microtime(true);
			$this->utime = $this->buff["ru_utime.tv_sec"].$this->buff["ru_utime.tv_usec"];
			$this->stime = $this->buff["ru_stime.tv_sec"].$this->buff["ru_stime.tv_usec"];
		}
	}
	
	function __construct($iterations) {
		$this->iterations = $iterations;
		$this->_rus();
	}
	
	function rewind() {
		$this->current = 0;
	}

	function current() {
		return $this->current;
	}

	function key() {
		return $this->current;
	}

	function next() {
		$this->current++;
	}

	function valid() {
		if ($this->current >= $this->iterations) {
			$this->_rus(true);
			$this->t = microtime(1)-$this->t;
			printf("---------\n"
				."real: %.6f s (%.6f ms mean per iteration)\n"
				."user: %.6f s (%.6f ms mean per iteration)\n"
				."sys:  %.6f s (%.6f ms mean per iteration)\n",
				$this->rtime, ($this->rtime/$this->iterations)*1000.0,
				$this->utime, ($this->utime/$this->iterations)*1000.0,
				$this->stime, ($this->stime/$this->iterations)*1000.0
				);
			return false;
		}
		return true;
	}
}

#foreach (GBBenchmark::iterations() as $iteration)
#	$m = strtotime('1993-01-14 19:36:11 +0400');
?>