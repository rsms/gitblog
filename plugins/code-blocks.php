<?php
/**
 * @name    Code blocks
 * @version 0.1
 * @author  Rasmus Andersson
 * @uri     http://gitblog.se/
 * 
 * Enables highlighting of code inside <code> blocks by using Pygments, if
 * available.
 * 
 * Learn more about Pygments: http://pygments.org/
 */
class code_blocks_plugin {
	static public $previous_failure = false;
	static public $conf;
	
	static function init($context) {
		if ($context !== 'rebuild')
			return false;
		self::$conf = gb::data('plugins/'.gb_filenoext(basename(__FILE__)), array(
			'classname' => 'codeblock',
			'tabsize' => 2,
			'pygmentize' => 'pygmentize'
		));
		gb_cfilter::add('body.html', array(__CLASS__, 'filter'), 0);
		return true;
	}
	
	static function dummy_block($content, $cssclass) {
		return '<div'
			.($cssclass ? ' class="'.$cssclass.'">' : '>')
			.'<pre>'.h($content).'</pre></div>';
	}
	
	static function highlight($content, $lang, $extra_cssclass=null, $input_encoding='utf-8') {
		$cssclass = self::$conf['classname'];
		if (!$cssclass)
			$cssclass = 'codeblock';
		if ($extra_cssclass)
			$cssclass .= ' ' . $extra_cssclass;
		
		if (self::$previous_failure === true || $lang === 'text' || $lang === 'txt')
			return self::dummy_block($content, $cssclass);
		
		$cmd = self::$conf['pygmentize'].' '.($lang ? '-l '.escapeshellarg($lang) : '-g')
			.' -f html -O cssclass='.escapeshellarg($cssclass)
			.',encoding='.$input_encoding
			.(self::$conf['tabsize'] ? ',tabsize='.self::$conf['tabsize'] : '');
		$st = gb::shell($cmd, $content);
		if ($st === null || ($st[0] !== 0 && strpos($st[2], 'command not found') !== false)) {
			# probably no pygments installed.
			# remember failure in order to speed up subsequent calls.
			self::$previous_failure = true;
			gb::log(LOG_WARNING,
				'unable to highlight code because %s can not be found',
				self::$conf['pygmentize']);
			return self::dummy_block($content, $cssclass);
		}
		# $st => array(int status, string out, string err)
		if ($st[0] !== 0) {
			if (strpos($st[2], 'guess_lexer') !== false)
				gb::log(LOG_NOTICE, 'pygments failed to guess language');
			else
				gb::log(LOG_WARNING, 'pygments failed to highlight code: '.$st[2]);
			return self::dummy_block($content, $cssclass);
		}
		return $st[1];
	}
	
	static function _escapeBlockContentCB($m) {
		return '<codeblock'.$m[1].'>'.base64_encode($m[2]).'</codeblock>';
	}
	
	static function filter($text='') {
		$text = preg_replace_callback('/<codeblock([^>]*)>(.*)<\/codeblock>/Usm',
			array(__CLASS__, '_escapeBlockContentCB'), $text);
		$tokens = gb_tokenize_html($text);
		$out = '';
		$depth = 0;
		$block = '';
		$tag = '';
		
		foreach ($tokens as $token) {
			if (substr($token,0,10) === '<codeblock') {
				$depth++;
				if ($depth === 1) {
					# code block just started
					$tag = $token;
					continue;
				}
			}
			elseif (substr($token,0,12) === '</codeblock>') {
				$depth--;
				if ($depth < 0) {
					gb::log(LOG_WARNING, 'stray </codeblock> messing up a code block');
					$depth = 0;
				}
				if ($depth === 0) {
					# code block ended
					if ($block) {
						$block = base64_decode($block);
						$lang = '';
						# find lang, if any
						if (preg_match('/[\s\t ]+lang=("[^"]*"|\'[^\']*\')[\s\t ]*/', $tag, $m, PREG_OFFSET_CAPTURE)) {
							$lang = trim($m[1][0], '"\'');
							$end = substr($tag, $m[0][1]+strlen($m[0][0]));
							$tag = substr($tag, 0, $m[0][1]).($end === '>' ? '>' : ' '.$end);
						}
						# add CSS class name
						$extra_cssclass = '';
						if (preg_match('/class="([^"]+)"/', $tag, $m))
							$extra_cssclass = $m[1];
						# remove first and last line break if present
						if ($block{0} === "\n")
							$block = substr($block, 1);
						if ($block{strlen($block)-1} === "\n")
							$block = substr($block, 0, -1);
						# expand tabs
						if (self::$conf['tabsize'])
							$block = strtr($block, array("\t" => str_repeat(' ', self::$conf['tabsize'])));
						# append block to output
						$out .= self::highlight($block, $lang, $extra_cssclass);
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
}
?>