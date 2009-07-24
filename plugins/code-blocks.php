<?
/*
 * Name:        Code blocks
 * Version:     0.1
 * Author:      Rasmus Andersson
 * Author URI:  http://hunch.se/
 * Description: Enables syntax highlight of <code> blocks using Pygments, if
 *              available.
 */

function code_blocks_config() {
	return array(
		'classname' => '',
		'tabsize' => 2
	);
}

function code_blocks_dummy_block($content) {
	return '<div><pre>'.h($content).'</pre></div>';
}

function code_blocks_highlight($content, $lang, $conf, $input_encoding='utf-8') {
	global $code_blocks_previous_failure;
	
	if (isset($code_blocks_previous_failure) && $code_blocks_previous_failure === true)
		return code_blocks_dummy_block($content);
	
	$cmd = 'pygmentize '.($lang ? '-l '.escapeshellarg($lang) : '-g')
		.' -f html -O cssclass=,encoding='.$input_encoding
		.($conf['tabsize'] ? ',tabsize='.$conf['tabsize'] : '');
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
		if (strpos($st[2], 'guess_lexer') !== false)
			gb::log(LOG_NOTICE, 'code-blocks plugin failed to guess language (pygments guess_lexer failed)');
		else
			gb::log(LOG_WARNING, 'code-blocks plugin failed to highlight code: '.$st[2]);
		return code_blocks_dummy_block($content);
	}
	return $st[1];
}

function code_blocks_filter($text='') {
	$conf = code_blocks_config();
	$tokens = gb_tokenize_html($text);
	$out = '';
	$depth = 0;
	$block = '';
	$tag = '';

	foreach ($tokens as $token) {
		if (substr($token,0,5) === '<code') {
			$depth++;
			if ($depth === 1) {
				# code block just started
				$tag = $token;
				continue;
			}
		}
		elseif (substr($token,0,7) === '</code>') {
			$depth--;
			if ($depth < 0) {
				gb::log(LOG_WARNING, 'stray </code> messing up a code block');
				$depth = 0;
			}
			if ($depth === 0) {
				# code block ended
				if ($block) {
					$lang = '';
					# find lang, if any
					if (preg_match('/[\s\t ]+lang=("[^"]*"|\'[^\']*\')[\s\t ]*/', $tag, $m, PREG_OFFSET_CAPTURE)) {
						$lang = trim($m[1][0], '"\'');
						$end = substr($tag, $m[0][1]+strlen($m[0][0]));
						$tag = substr($tag, 0, $m[0][1]).($end === '>' ? '>' : ' '.$end);
					}
					# add CSS class name
					if ($conf['classname']) {
						if (($p = strpos($tag, 'class=')) !== false)
							$tag = substr($tag, 0, $p+7) . $conf['classname'].' ' . substr($tag, $p+7);
						else
							$tag = substr($tag, 0, -1) . ' class="'.$conf['classname'].'">';
					}
					# remove first and last line break if present
					if ($block{0} === "\n")
						$block = substr($block, 1);
					if ($block{strlen($block)-1} === "\n")
						$block = substr($block, 0, -1);
					# expand tabs
					if ($conf['tabsize'])
						$block = strtr($block, array("\t" => str_repeat(' ', $conf['tabsize'])));
					# append block to output
					$out .= $tag . code_blocks_highlight($block, $lang, $conf) . '</code>';
					# clear block
					$block = '';
				}
				continue;
			}
		}

		# in codeblock or not?
		if ($depth)
			$block .= $token;
		else
			$out .= $token;
	}
	
	return $out;
}


function code_blocks_init($context) {
	if ($context !== 'rebuild')
		return false;
	GBFilter::add('body.html', 'code_blocks_filter', 0);
	return true;
}

?>
