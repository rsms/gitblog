<?
/*
 * Name:				Code blocks
 * Version:		 0.1
 * Plugin URI:	http://trac.hunch.se/rasmus/wiki/wp-plugins#SyntaxHighlight
 * Author:			Rasmus Andersson
 * Author URI:	http://hunch.se/
 * Description: Enables defining code blocks using Trac-style {{{...}}} form.
 *							Also highlights code using Pygments, if available.
 * 
 * CSS colors:
 *   You can find a bunch of stylesheets here: 
 */

function code_blocks_dummy_block($content) {
	return '<div><pre>'.h($content).'</pre></div>';
}

function code_blocks_highlight($content, $lang, $cache_ttl=30) {
	global $code_blocks_previous_failure;
	static $pat = 'pygmentize -l %s -f html -P cssclass=';
	
	if (isset($code_blocks_previous_failure) && $code_blocks_previous_failure === true)
		return code_blocks_dummy_block($content);
	
	$cmd = sprintf($pat, escapeshellarg($lang));
	$st = gb::shell($cmd, $content);
	if ($st === null || ($st[0] !== 0 && strpos($st[2], 'command not found') !== false)) {
		# probably no pygments installed -- remember failure in order to speed up multiple calls
		$code_blocks_previous_failure = true;
		gb::log(LOG_WARNING,
			'code-blocks plugin can not highlight code because it can not find pygmentize');
		return code_blocks_dummy_block($content);
	}
	# $st => array(int status, string out, string err)
	if ($st[0] !== 0) {
		gb::log(LOG_WARNING, 'code-blocks plugin failed to highlight code: '.$st[2]);
		return code_blocks_dummy_block($content);
	}
	return $st[1];
}

function code_blocks_wrap($content='') {
	$start = 0;
	while(1) {
		if( ($start = @strpos($content, ($start == 0 ? '{{{' : "\n{{{"), $start)) !== false) {
			if( ($end = strpos($content, "}}}", $start+5)) !== false) {
				$code = trim(substr($content, $start+4, $end-($start+4)));
				$lang = null;
				if( substr($code, 0, 2) == '#!' ) {
					$nl = strpos($code, "\n", 2);
					$lang = trim(substr($code, 2, $nl-2));
					$code = code_blocks_highlight(ltrim(substr($code, $nl+1), "\r"), $lang);
				}
				else {
					$code = code_blocks_dummy_block($code);
				}
				$code_wrapped = base64_encode($code);
				$code_wrapped = sprintf("{{{!%010d%s",strlen($code_wrapped), $code_wrapped);
				$content = substr($content, 0, $start)
					. '<p class="code-block">'.$code_wrapped.'</p>'
					. substr($content, $end+3);
				$start = $end;
			}
			else {
				break;
			}
		}
		else {
			break;
		}
	}
	return $content;
}

function code_blocks_unwrap($content='') {
	$start = 0;
	$content_len = strlen($content);
	while(1) {
		if( ($start = strpos($content, "{{{!", $start)) !== false) {
			$len = intval(substr($content, $start+4, 10));
			$content = substr($content, 0, $start)
				. base64_decode(substr($content, $start+14, $len))
				. substr($content, $start+14+$len);
			$start = $start + 14;
			if($start > $content_len)
				break;
		}
		else {
			break;
		}
	}
	return $content;
}


function code_blocks_init($context) {
	if ($context !== 'rebuild')
		return false;
	GBFilter::add('body.html', 'code_blocks_wrap', 0);
	GBFilter::add('body.html', 'code_blocks_unwrap', 9999);
	return true;
}

?>
