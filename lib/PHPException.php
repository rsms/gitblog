<?php
class PHPException extends RuntimeException {
	function __construct($msg=null, $errno=0, $file=null, $line=-1) {
		if ($msg instanceof Exception) {
			if (is_string($errno) && $file == null && $line == -1) {
				$msg = $errno;
				$errno = 0;
			}
			else {
				$line = $msg->getLine();
				$file = $msg->getFile();
				$errno = $msg->getCode();
				$msg = $msg->getMessage();
				if (isset($msg->errorInfo))
					$this->errorInfo = $msg->errorInfo;
			}
		}
		parent::__construct($msg, $errno);
		if ($file != null)  $this->file = $file;
		if ($line != -1)    $this->line = $line;
	}
}
?>