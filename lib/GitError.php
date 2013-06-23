<?php
class GitError extends GBException {
	public $command;
	
	function __construct($msg=null, $exit_status=0, $command=null, $previous=null, $file=null, $line=-1) {
		parent::__construct($msg, $exit_status, $previous, $file, $line);
		$this->command = $command;
	}
	
	public function formatMessage($html=null) {
		if ($html === null)
			$html = ini_get('html_errors') ? true : false;
		$message = trim(parent::formatMessage($html));
		if ($this->command) {
			$message .= ($message ? '.' : '')
				. ($html ? '<code class="command">' : "\n\ncommand: ")
				. h($this->command)
				. ($html ? '</code>' : '');
		}
		return $message;
	}
}
?>