<?php
/** Base exception with some additional functionality, like pretty formatting */
class GBException extends Exception {
	static public $is_php53 = false;
	protected $_previous;
	
	public function __construct($msg=null, $code=0, $previous=null, $file=null, $line=-1) {
		if ($msg instanceof Exception) {
			# copy properties from exception $msg
			foreach (get_class_vars($msg) as $k => $v)
				$this->$k = $v;
			$previous = self::getPreviousCompat($msg, $previous);
			$msg = $this->message;
			$code = $this->code;
		}
		else {
			if ($file != null)
				$this->file = $file;
			if ($line != -1)
				$this->line = $line;
		}
		if (self::$is_php53) {
			parent::__construct($msg, $code, $previous);
		}
		else {
			parent::__construct($msg, $code);
			$this->_previous = $previous;
		}
	}
	
	/** set $message */
	public function setMessage($msg) { $this->message = $msg; }
	
	/** set $file */
	public function setFile($msg) { $this->file = $file; }
	
	/** set $line */
	public function setLine($msg) { $this->line = intval($line); }
	
	/** Get message for formatting. Subclasses can add additional information to the message here. */
	public function formatMessage($html=null) {
		return $html ? nl2br(h($this->getMessage())) : $this->getMessage();
	}
	
	/**
	 * Convenience method equivalent to calling self::format with $html=true.
	 * 
	 * @param  Exception
	 * @param  bool
	 * @param  string[]
	 * @return string
	 * @see    format()
	 * @see    formatPlain()
	 */
	public static function formatHtml(Exception $e, $includingTrace=true, $skip=null, $context_lines=2) {
		return self::format($e, $includingTrace, true, $skip, $context_lines);
	}
	
	/**
	 * Convenience method equivalent to calling self::format with $html=false.
	 * 
	 * @param  Exception
	 * @param  bool
	 * @param  string[]
	 * @return string
	 * @see    format()
	 * @see    formatHtml()
	 */
	public static function formatPlain(Exception $e, $includingTrace=true, $skip=null, $context_lines=2) {
		return self::format($e, $includingTrace, false, $skip, $context_lines);
	}
	
	/**
	 * Render a full HTML description of an exception
	 *
	 * @param  Exception
	 * @param  bool       Include call trace in the output
	 * @param  bool       Return nicely formatted HTML instead of plain text
	 * @param  string[]   An array of function (or Class::method) names to remove from trace 
	 *                    prior to rendering it. Specify null to disable.
	 *                    See {@link formatTrace()} for more information.
	 * @return string
	 * @see    formatTrace()
	 */
	public static function format(Exception $e, $includingTrace=true, $html=null, $skip=null, $context_lines=2) {
		if ($html === null)
			$html = ini_get('html_errors') ? true : false;
		$tracestr = $includingTrace ? self::formatTrace($e, $html, $skip, $context_lines) : false;
		$code = $e->getCode();
		$message = ($e instanceof self) ? 
			$e->formatMessage($html) : ($html ? nl2br(h($e->getMessage())) : $e->getMessage());
		
		try {
			$context = '';
			if ($context_lines && $e->getFile() && $e->getLine()) {
				$context = self::formatSourceLines($e->getFile(), $e->getLine(), $html, $context_lines);
				if ($context)
					$context = ($html ? '<pre class="context">'.$context.'</pre>' : "\n\n".$context."\n\n");
				else
					$context = '';
			}
			
			if ($html) {
				$str = '<div class="exception"><h2>' .  get_class($e);
				if ($code)
					$str .= ' <span class="code">('.$code.')</span>';
				$abs = $e->getFile();
				$rel = gb_relpath(gb::$site_dir, $abs);
				$pathprefix = substr($abs, 0, -strlen($rel));
				$str .= '</h2>'
					. '<p class="message">'.$message.'</p> '
					. '<p class="location">'
						. 'in <span class="location"><span class="prefix">'.$pathprefix.'</span>'.$rel
						. ':'.$e->getLine().'</span>'
					. '</p>'
					. $context;
			}
			else {
				$str = get_class($e) . ($code ? ' ('.$code.'): ':': ')
					. $message
					. ' on line ' . $e->getLine() . ' in ' . $e->getFile()
					. $context;
			}
		
			if ($includingTrace)
				$str .= "\n" . $tracestr;
			
			# caused by...
			$previous = self::getPreviousCompat($e);
			if ($previous && $previous instanceof Exception) {
				# never include trace from caused php exception, because it is the same as it's parent.
				$inc_prev_trace = !($previous instanceof PHPException);
				$prev_fmt = self::format($previous, $inc_prev_trace, $html, $skip, $context_lines);
				if ($html)
					$str .= '<b>Caused by:</b><div style="margin-left:15px">'.$prev_fmt.'</div>';
				else
					$str .= "\nCaused by:\n  ".str_replace("\n", "\n  ", $prev_fmt)."\n";
			}
		
			if ($html)
				$str .= '</div>';
		
			return $str;
		}
		catch (Exception $ex) {
			$str = get_class($e) . ': ' . $message;
			if ($includingTrace)
				$str .= "\n" . $tracestr;
			return $html ? nl2br(h($str)) : $str;
		}
	}
	
	/**
	 * Render a nice output of a backtrace from an exception
	 * 
	 * <b>The skip parameter</b>
	 *   - To skip a plain function, simply specify the function name. i.e. "__errorhandler"
	 *   - To skip a class or instance method, specify "Class::methodName"
	 * 
	 * @param  Exception
	 * @param  bool       Include call trace in the output
	 * @param  bool       Return nicely formatted HTML instead of plain text
	 * @return string
	 * @see    format()
	 */
	public static function formatTrace(Exception $e, $html=null, $skip=null, $context_lines=2) {
		if ($html === null)
			$html = ini_get('html_errors') ? true : false;
		
		try {
			$trace = $e->getTrace();
			$traceLen = count($trace);
			$str = '';
			
			if ($traceLen > 0) {
				if($html)
					$str .= "<div class=\"trace\">";
				
				if ($e instanceof PHPException) {
					$skip = is_array($skip) ? array_merge($skip, array('PHPException::rethrow')) : array('PHPException::rethrow');
					foreach($trace as $i => $ti) {
						if (isset($ti['function']) && $ti['function'] === 'gb_throw_php_error') {
							array_splice($trace, 0, $i+1);
							break;
						}
					}
				}
				
				if ($skip) {
					$traceTmp = $trace;
					$trace = array();
					foreach($traceTmp as $i => $ti) {
						if ( in_array($ti['function'], $skip) || (isset($ti['type']) && in_array($ti['class'].'::'.$ti['function'], $skip)) )
							continue;
						$trace[] = $ti;
					}
				}
				
				foreach($trace as $i => $ti) {
					$str .= ($html ? '<div class="frame">' : '')
						. self::formatFrame($ti, $html, $context_lines)
						. ($html ? "</div><br />\n" : "\n");
				}
				$str .= $html ? "</div>\n" : "\n";
			}
			return trim($str,"\n")."\n";
		}
		catch (Exception $ex) {
			return $html ? nl2br(h($e->getTraceAsString())) : $e->getTraceAsString();
		}
	}
	
	public static function formatFrame($ti, $html=null, $context_lines=2) {
		if ($html === null)
			$html = ini_get('html_errors') ? true : false;
		
		$context = false;
		if ($context_lines && isset($ti['file']) && isset($ti['line']) && $ti['file'] && $ti['line'])
			$context = self::formatSourceLines($ti['file'], $ti['line'], $html, $context_lines);
		
		$args = '()';
		if(isset($ti['args'])) {
			$argsCnt = count($ti['args']);
			if($argsCnt > 0)
				$args = '('.$argsCnt.')';
		}
		$str = $html ? '<span class="function">' : '';
		$str .= (isset($ti['type']) ?  $ti['class'].$ti['type'] : '::') . $ti['function'].$args;
		if ($html)
			$str .= '</span>';
		$str .= ' called in ';
		$str .= $html ? '<span class="location">' : '';
		$str .= (isset($ti['file']) && $ti['file']) ? h(gb_relpath(gb::$site_dir, $ti['file'])) : '?';
		$str .= (isset($ti['line']) && $ti['line']) ? ':'.$ti['line'] : '';
		$str .= $html ? '</span>' : '';
		
		if ($context)
			$str .= ($html ? '<pre class="context">'.$context.'</pre>' : "\n\n".$context."\n\n");
		
		return $str;
	}
	
	public static function formatSourceLines($file, $line, $html=null, $lines=2) {
		if ($html === null)
			$html = ini_get('html_errors') ? true : false;
		$context = '';
		$context_lines = $lines;
		if (!($lines = @file_get_contents($file)))
			return false;
		$lines = explode("\n", $lines);
		$nlines = count($lines);
		$lineindex = $line-1;
		$before = array();
		for ($i=$lineindex-$context_lines; $i<$lineindex; $i++) {
			if (isset($lines[$i]))
				$before[] = $lines[$i];
		}
		if ($before) {
			if ($html)
				$context = '<span class="c before">' . h(str_replace("\t",'  ',implode("\n",$before))) . '</span>';
			else
				$context = str_replace("\t",'  ',implode("\n",$before));
		}
		if (isset($lines[$lineindex])) {
			if ($html) {
				$context .= ($context ? "\n" : '') 
					. '<span class="f">' . h(str_replace("\t",'  ',$lines[$lineindex])) . '</span>';
			}
			else {
				$context .= str_replace("\t",'  ',$lines[$lineindex]);
			}
			$end = array();
			for ($i=$lineindex+1; $i <= $lineindex+$context_lines; $i++) {
				if (isset($lines[$i]))
					$end[] = $lines[$i];
			}
			if ($end) {
				if ($html)
					$context .= "\n". '<span class="c after">' . h(str_replace("\t",'  ',implode("\n",$end))) . '</span>';
				else
					$context .= "\n". str_replace("\t",'  ',implode("\n",$end));
			}
		}
		return $context;
	}
	
	public static function getPreviousCompat($e, $default=null) {
		if (self::$is_php53)
			return $e->getPrevious();
		elseif ($e instanceof self)
			return $e->_previous;
		return $default;
	}
	
	/** @return string */
	public function toHTML() { return self::format($this); }
	
	/** @return string */
	public function __toString() { return self::format($this, false, false); }
	
	static public $css = '
		html,body { margin:0; padding:0; }
		body,div.exception { background-color:#f0ffff; color:#666; font-family:"helvetica neue",helvetica,sans-serif; font-size:16px; }
		h1 { padding:.8em 0 .5em 30px; font-weight:normal; color:#777; background:#333; margin:0; }
		h2 { background-color:#fcc; color:#721; margin:0; padding:.2em 30px; }
		h2 span.code { color:#c99; font-weight:normal; }
		p.message { background:#fff; color:#220; padding:1.5em 30px; margin:0; }
		p.message code { display:block; margin:1.5em 0 0 0; }
		p.location { padding:0.5em 30px; margin:0; background:#36a; color:#fff; font-family:monospace; font-size:18px; }
		p.location span.prefix { color:#9cf; font-size:9px; padding-right:4px; float:right; line-height:22px; }
		div.trace { padding:1em 30px; margin:0; font-size:13px; }
		div.frame { padding:0; font-family:monospace; }
		pre.context { font-size:10px; margin-left:55px; margin-bottom:0; 
			border-left:1px solid #ccc; padding:0 0 0 4px; }
		div.frame pre.context { margin-left:25px; }
		pre.context span { display:inline-block; }
		pre.context span.c { color:#8aa; }
		pre.context span.f { color:#000; padding:3px 0; }
		div.frame span.function { color:#06a; }
		div.frame span.location { color:#000; }';

	static public function formatHTMLBlock(Exception $e, $includingTrace=true, $skip=null, $context_lines=2, $inc_css=true) {
		$msg = self::format($e, $includingTrace, true, $skip, $context_lines);
		if ($inc_css)
			$msg = '<style type="text/css" media="screen">'.self::$css.'</style>'.$msg;
		return '<div class="exception">'.$msg.'</div>';
	}
	
	static public function formatHTMLDocument(Exception $e, $includingTrace=true, $skip=null, $context_lines=2) {
		$cls = get_class($e);
		$html = self::formatHTMLBlock($e, $includingTrace, $skip, $context_lines, false);
		$css = self::$css;
		return <<<HTML
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
		<title>Error: $cls</title>
		<style type="text/css" media="screen">
			$css
		</style>
	</head>
	<body>
		<h1>An unrecoverable error occured</h1>
		$html
	</body>
</html>
HTML;
	}
}

GBException::$is_php53 = version_compare(PHP_VERSION, '5.3.0', '>=');
?>