<?
class GBFilter {
	static public $filters = array();
	
	/**
	 * Add a filter
	 * 
	 * Lower number for $priority means earlier execution of $func.
	 * 
	 * If $func returns boolean FALSE the filter chain is broken, not applying
	 * any more filter after the one returning FALSE. Returning anything else
	 * have no effect.
	 */
	static function add($tag, $func, $priority=100) {
		if (!isset(self::$filters[$tag]))
			self::$filters[$tag] = array($priority => array($func));
		elseif (!isset(self::$filters[$tag][$priority]))
			self::$filters[$tag][$priority] = array($func);
		else
			self::$filters[$tag][$priority][] = $func;
	}
	
	/** Apply filters for $tag on $value */
	static function apply($tag, $value/*, [arg ..] */) {
		$vargs = func_get_args();
		$tag = array_shift($vargs);
		$a = @self::$filters[$tag];
		if ($a === null)
			return $value;
		ksort($a, SORT_NUMERIC);
		foreach ($a as $funcs) {
			foreach ($funcs as $func) {
				$value = call_user_func_array($func, $vargs);
				$vargs[0] = $value;
			}
		}
		return $vargs[0];
	}
}

# -----------------------------------------------------------------------------
# General filters

# Convert short-hands to nice unicode characters.
# Shamelessly borrowed from my worst nightmare Wordpress.
function gb_texturize_html($text) {
	$next = true;
	$has_pre_parent = false;
	$output = '';
	$curl = '';
	$textarr = preg_split('/(<.*>|\[.*\])/Us', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
	$stop = count($textarr);
	
	static $static_characters = array(
		'---', ' -- ', '--', "xn\xe2\x80\x93", '...', '``', '\'s', '\'\'', ' (tm)',
		# cockney:
		"'tain't","'twere","'twas","'tis","'twill","'til","'bout",
		"'nuff","'round","'cause");
	static $static_replacements = array("\xe2\x80\x94"," \xe2\x80\x94 ",
		"\xe2\x80\x93","xn--","\xe2\x80\xa6","\xe2\x80\x9c","\xe2\x80\x99s",
		"\xe2\x80\x9d"," \xe2\x84\xa2",
		# cockney
		"\xe2\x80\x99tain\xe2\x80\x99t","\xe2\x80\x99twere",
		"\xe2\x80\x99twas","\xe2\x80\x99tis","\xe2\x80\x99twill","\xe2\x80\x99til",
		"\xe2\x80\x99bout","\xe2\x80\x99nuff","\xe2\x80\x99round","\xe2\x80\x99cause");
	
	static $dynamic_characters = array('/\'(\d\d(?:&#8217;|\')?s)/', '/(\s|\A|")\'/', '/(\d+)"/', '/(\d+)\'/',
	 	'/(\S)\'([^\'\s])/', '/(\s|\A)"(?!\s)/', '/"(\s|\S|\Z)/', '/\'([\s.]|\Z)/', '/(\d+)x(\d+)/');
	static $dynamic_replacements = array("\xe2\x80\x99\$1","\$1\xe2\x80\x98","\$1\xe2\x80\xb3","\$1\xe2\x80\xb2",
		"\$1\xe2\x80\x99$2","\$1\xe2\x80\x9c\$2","\xe2\x80\x9d\$1","\xe2\x80\x99\$1","\$1\xc3\x97\$2");
	
	for ( $i = 0; $i < $stop; $i++ ) {
		$curl = $textarr[$i];
		
		if (isset($curl{0}) && '<' != $curl{0} && '[' != $curl{0} && $next && !$has_pre_parent)
		{ # If it's not a tag
			# static strings
			$curl = str_replace($static_characters, $static_replacements, $curl);
			# regular expressions
			$curl = preg_replace($dynamic_characters, $dynamic_replacements, $curl);
		} elseif (strpos($curl, '<code') !== false || strpos($curl, '<kbd') !== false
			|| strpos($curl, '<style') !== false || strpos($curl, '<script') !== false)
		{
			$next = false;
		} elseif (strpos($curl, '<pre') !== false) {
			$has_pre_parent = true;
		} elseif (strpos($curl, '</pre>') !== false) {
			$has_pre_parent = false;
		} else {
			$next = true;
		}
		
		$curl = preg_replace('/&([^#])(?![a-zA-Z1-4]{1,8};)/', '&#038;$1', $curl);
		$output .= $curl;
	}
	
	return $output;
}

function gb_convert_html_chars($content) {
	# Translation of invalid Unicode references range to valid range,
	# often added by Windows programs after a copy-paste.
	static $map = array(
	'&#128;' => '&#8364;', # the Euro sign
	'&#129;' => '',
	'&#130;' => '&#8218;', # these are Windows CP1252 specific characters
	'&#131;' => '&#402;',  # they would look weird on non-Windows browsers
	'&#132;' => '&#8222;',
	'&#133;' => '&#8230;',
	'&#134;' => '&#8224;',
	'&#135;' => '&#8225;',
	'&#136;' => '&#710;',
	'&#137;' => '&#8240;',
	'&#138;' => '&#352;',
	'&#139;' => '&#8249;',
	'&#140;' => '&#338;',
	'&#141;' => '',
	'&#142;' => '&#382;',
	'&#143;' => '',
	'&#144;' => '',
	'&#145;' => '&#8216;',
	'&#146;' => '&#8217;',
	'&#147;' => '&#8220;',
	'&#148;' => '&#8221;',
	'&#149;' => '&#8226;',
	'&#150;' => '&#8211;',
	'&#151;' => '&#8212;',
	'&#152;' => '&#732;',
	'&#153;' => '&#8482;',
	'&#154;' => '&#353;',
	'&#155;' => '&#8250;',
	'&#156;' => '&#339;',
	'&#157;' => '',
	'&#158;' => '',
	'&#159;' => '&#376;'
	);
	
	# Converts lone & characters into &#38; (a.k.a. &amp;)
	$content = preg_replace('/&([^#])(?![a-z1-4]{1,8};)/i', '&#038;$1', $content);
	
	# Fix Microsoft Word pastes
	$content = strtr($content, $map);
	
	return $content;
}

# HTML -> XHTML
function gb_html_to_xhtml($content) {
	return str_replace(array('<br>','<hr>'), array('<br />','<hr />'), $content);
}

function gb_normalize_html_structure_clean_pre($matches) {
	if ( is_array($matches) )
		$text = $matches[1] . $matches[2] . "</pre>";
	else
		$text = $matches;
	$text = str_replace('<br />', '', $text);
	$text = str_replace('<p>', "\n", $text);
	$text = str_replace('</p>', '', $text);
	return $text;
}


# LF => <br />, etc
function gb_normalize_html_structure($s, $br = 1) {
	$s = $s . "\n"; # just to make things a little easier, pad the end
	$s = preg_replace('|<br />\s*<br />|', "\n\n", $s);
	# Space things out a little
	$allblocks = '(?:table|thead|tfoot|caption|colgroup|tbody|tr|td|th|div|dl|dd|dt|ul|ol|li|'
		.'pre|select|form|map|area|blockquote|address|math|style|input|p|h[1-6]|hr)';
	$s = preg_replace('!(<' . $allblocks . '[^>]*>)!', "\n$1", $s);
	$s = preg_replace('!(</' . $allblocks . '>)!', "$1\n\n", $s);
	$s = str_replace(array("\r\n", "\r"), "\n", $s); # cross-platform newlines
	if ( strpos($s, '<object') !== false ) {
		$s = preg_replace('|\s*<param([^>]*)>\s*|', "<param$1>", $s); # no pee inside object/embed
		$s = preg_replace('|\s*</embed>\s*|', '</embed>', $s);
	}
	$s = preg_replace("/\n\n+/", "\n\n", $s); # take care of duplicates
	$s = preg_replace('/\n?(.+?)(?:\n\s*\n|\z)/s', "<p>$1</p>\n", $s); # make paragraphs, including one at the end
	$s = preg_replace('|<p>\s*?</p>|', '', $s); # under certain strange conditions it could create a P of entirely whitespace
	$s = preg_replace('!<p>([^<]+)\s*?(</(?:div|address|form)[^>]*>)!', "<p>$1</p>$2", $s);
	$s = preg_replace( '|<p>|', "$1<p>", $s );
	$s = preg_replace('!<p>\s*(</?' . $allblocks . '[^>]*>)\s*</p>!', "$1", $s); # don't pee all over a tag
	$s = preg_replace("|<p>(<li.+?)</p>|", "$1", $s); # problem with nested lists
	$s = preg_replace('|<p><blockquote([^>]*)>|i', "<blockquote$1><p>", $s);
	$s = str_replace('</blockquote></p>', '</p></blockquote>', $s);
	$s = preg_replace('!<p>\s*(</?' . $allblocks . '[^>]*>)!', "$1", $s);
	$s = preg_replace('!(</?' . $allblocks . '[^>]*>)\s*</p>!', "$1", $s);
	if ($br) {
		$s = preg_replace_callback('/<(script|style).*?<\/\\1>/s', 
			create_function('$matches', 'return str_replace("\n", "<WPPreserveNewline />", $matches[0]);'), $s);
		$s = preg_replace('|(?<!<br />)\s*\n|', "<br />\n", $s); # optionally make line breaks
		$s = str_replace('<WPPreserveNewline />', "\n", $s);
	}
	$s = preg_replace('!(</?' . $allblocks . '[^>]*>)\s*<br />!', "$1", $s);
	$s = preg_replace('!<br />(\s*</?(?:p|li|div|dl|dd|dt|th|pre|td|ul|ol)[^>]*>)!', '$1', $s);
	if (strpos($s, '<pre') !== false)
		$s = preg_replace_callback('!(<pre.*?>)(.*?)</pre>!is', 'gb_normalize_html_structure_clean_pre', $s );
	$s = preg_replace( "|\n</p>$|", '</p>', $s );
	#$s = preg_replace('/<p>\s*?(' . get_shortcode_regex() . ')\s*<\/p>/s', '$1', $s);
	# ^ don't auto-p wrap shortcodes that stand alone
	return $s;
}

function gb_remove_accents($s) {
	if (!preg_match('/[\x80-\xff]/', $s))
		return $s;
	static $map = array(
		# Latin-1 Supplement
		"\xc3\x80"=>'A',"\xc3\x81"=>'A',"\xc3\x82"=>'A',"\xc3\x83"=>'A',
		"\xc3\x84"=>'A',"\xc3\x85"=>'A',"\xc3\x87"=>'C',"\xc3\x88"=>'E',
		"\xc3\x89"=>'E',"\xc3\x8a"=>'E',"\xc3\x8b"=>'E',"\xc3\x8c"=>'I',
		"\xc3\x8d"=>'I',"\xc3\x8e"=>'I',"\xc3\x8f"=>'I',"\xc3\x91"=>'N',
		"\xc3\x92"=>'O',"\xc3\x93"=>'O',"\xc3\x94"=>'O',"\xc3\x95"=>'O',
		"\xc3\x96"=>'O',"\xc3\x99"=>'U',"\xc3\x9a"=>'U',"\xc3\x9b"=>'U',
		"\xc3\x9c"=>'U',"\xc3\x9d"=>'Y',"\xc3\x9f"=>'s',"\xc3\xa0"=>'a',
		"\xc3\xa1"=>'a',"\xc3\xa2"=>'a',"\xc3\xa3"=>'a',"\xc3\xa4"=>'a',
		"\xc3\xa5"=>'a',"\xc3\xa7"=>'c',"\xc3\xa8"=>'e',"\xc3\xa9"=>'e',
		"\xc3\xaa"=>'e',"\xc3\xab"=>'e',"\xc3\xac"=>'i',"\xc3\xad"=>'i',
		"\xc3\xae"=>'i',"\xc3\xaf"=>'i',"\xc3\xb1"=>'n',"\xc3\xb2"=>'o',
		"\xc3\xb3"=>'o',"\xc3\xb4"=>'o',"\xc3\xb5"=>'o',"\xc3\xb6"=>'o',
		"\xc3\xb9"=>'u',"\xc3\xba"=>'u',"\xc3\xbb"=>'u',"\xc3\xbc"=>'u',
		"\xc3\xbd"=>'y',"\xc3\xbf"=>'y',
		# Latin Extended-A
		"\xc4\x80"=>'A',"\xc4\x81"=>'a',
		"\xc4\x82"=>'A',"\xc4\x83"=>'a',"\xc4\x84"=>'A',"\xc4\x85"=>'a',
		"\xc4\x86"=>'C',"\xc4\x87"=>'c',"\xc4\x88"=>'C',"\xc4\x89"=>'c',
		"\xc4\x8a"=>'C',"\xc4\x8b"=>'c',"\xc4\x8c"=>'C',"\xc4\x8d"=>'c',
		"\xc4\x8e"=>'D',"\xc4\x8f"=>'d',"\xc4\x90"=>'D',"\xc4\x91"=>'d',
		"\xc4\x92"=>'E',"\xc4\x93"=>'e',"\xc4\x94"=>'E',"\xc4\x95"=>'e',
		"\xc4\x96"=>'E',"\xc4\x97"=>'e',"\xc4\x98"=>'E',"\xc4\x99"=>'e',
		"\xc4\x9a"=>'E',"\xc4\x9b"=>'e',"\xc4\x9c"=>'G',"\xc4\x9d"=>'g',
		"\xc4\x9e"=>'G',"\xc4\x9f"=>'g',"\xc4\xa0"=>'G',"\xc4\xa1"=>'g',
		"\xc4\xa2"=>'G',"\xc4\xa3"=>'g',"\xc4\xa4"=>'H',"\xc4\xa5"=>'h',
		"\xc4\xa6"=>'H',"\xc4\xa7"=>'h',"\xc4\xa8"=>'I',"\xc4\xa9"=>'i',
		"\xc4\xaa"=>'I',"\xc4\xab"=>'i',"\xc4\xac"=>'I',"\xc4\xad"=>'i',
		"\xc4\xae"=>'I',"\xc4\xaf"=>'i',"\xc4\xb0"=>'I',"\xc4\xb1"=>'i',
		"\xc4\xb2"=>'IJ',"\xc4\xb3"=>'ij',"\xc4\xb4"=>'J',"\xc4\xb5"=>'j',
		"\xc4\xb6"=>'K',"\xc4\xb7"=>'k',"\xc4\xb8"=>'k',"\xc4\xb9"=>'L',
		"\xc4\xba"=>'l',"\xc4\xbb"=>'L',"\xc4\xbc"=>'l',"\xc4\xbd"=>'L',
		"\xc4\xbe"=>'l',"\xc4\xbf"=>'L',"\xc5\x80"=>'l',"\xc5\x81"=>'L',
		"\xc5\x82"=>'l',"\xc5\x83"=>'N',"\xc5\x84"=>'n',"\xc5\x85"=>'N',
		"\xc5\x86"=>'n',"\xc5\x87"=>'N',"\xc5\x88"=>'n',"\xc5\x89"=>'N',
		"\xc5\x8a"=>'n',"\xc5\x8b"=>'N',"\xc5\x8c"=>'O',"\xc5\x8d"=>'o',
		"\xc5\x8e"=>'O',"\xc5\x8f"=>'o',"\xc5\x90"=>'O',"\xc5\x91"=>'o',
		"\xc5\x92"=>'OE',"\xc5\x93"=>'oe',"\xc5\x94"=>'R',"\xc5\x95"=>'r',
		"\xc5\x96"=>'R',"\xc5\x97"=>'r',"\xc5\x98"=>'R',"\xc5\x99"=>'r',
		"\xc5\x9a"=>'S',"\xc5\x9b"=>'s',"\xc5\x9c"=>'S',"\xc5\x9d"=>'s',
		"\xc5\x9e"=>'S',"\xc5\x9f"=>'s',"\xc5\xa0"=>'S',"\xc5\xa1"=>'s',
		"\xc5\xa2"=>'T',"\xc5\xa3"=>'t',"\xc5\xa4"=>'T',"\xc5\xa5"=>'t',
		"\xc5\xa6"=>'T',"\xc5\xa7"=>'t',"\xc5\xa8"=>'U',"\xc5\xa9"=>'u',
		"\xc5\xaa"=>'U',"\xc5\xab"=>'u',"\xc5\xac"=>'U',"\xc5\xad"=>'u',
		"\xc5\xae"=>'U',"\xc5\xaf"=>'u',"\xc5\xb0"=>'U',"\xc5\xb1"=>'u',
		"\xc5\xb2"=>'U',"\xc5\xb3"=>'u',"\xc5\xb4"=>'W',"\xc5\xb5"=>'w',
		"\xc5\xb6"=>'Y',"\xc5\xb7"=>'y',"\xc5\xb8"=>'Y',"\xc5\xb9"=>'Z',
		"\xc5\xba"=>'z',"\xc5\xbb"=>'Z',"\xc5\xbc"=>'z',"\xc5\xbd"=>'Z',
		"\xc5\xbe"=>'z',"\xc5\xbf"=>'s',
		# Euro
		"\xe2\x82\xac"=>'E',
		# GBP
		"\xc2\xa3"=>'L');
	return strtr($s, $map);
}

function gb_sanitize_title($s, $default='') {
	$s = strip_tags(trim($s));
	$s = preg_replace('|%([a-fA-F0-9][a-fA-F0-9])|', '', $s);
	$s = str_replace('%', '-', $s);
	$s = gb_remove_accents($s);
	$s = function_exists('mb_strtolower') ? mb_strtolower($s, 'UTF-8') : strtolower($s);
	$s = preg_replace('/&.+?;/', '', $s); # entities
	$s = str_replace('.', '-', $s);
	$s = preg_replace('/[^%a-z0-9 _-]/', '', $s);
	$s = preg_replace('/\s+/', '-', $s);
	$s = preg_replace('|-+|', '-', $s);
	$s = trim($s, '-');
	if (!$s)
		return $default;
	return $s;
}


# Used to GBExposedContent->slug = filter(GBExposedContent->title)
GBFilter::add('sanitize-title', 'gb_sanitize_title');

# Applied to GBExposedContent->body
GBFilter::add('body.html', 'gb_texturize_html');
GBFilter::add('body.html', 'gb_convert_html_chars');
GBFilter::add('body.html', 'gb_html_to_xhtml');
GBFilter::add('body.html', 'gb_normalize_html_structure');

# Applied to GBExposedContent->excerpt
GBFilter::add('excerpt.html', 'gb_texturize_html');
GBFilter::add('excerpt.html', 'gb_convert_html_chars');
GBFilter::add('excerpt.html', 'gb_html_to_xhtml');
GBFilter::add('excerpt.html', 'gb_normalize_html_structure');


# -----------------------------------------------------------------------------
# GBExposedContent filters

/** trim(c->body) */
function gb_filter_post_reload_content(GBExposedContent $c) {
	if ($c->body)
		$c->body = trim($c->body);
	return $c;
}

/** Converts LF to <br/>LF and extracts excerpt for GBPost objects */
function gb_filter_post_reload_content_html(GBExposedContent $c) {
	if ($c->body) {
		# create excerpt for GBPosts if not already set
		if ($c instanceof GBPost && !$c->excerpt) {
			$p = strpos($c->body, '<!--more-->');
			if ($p !== false) {
				$c->excerpt = substr($c->body, 0, $p);
				$c->body = $c->excerpt
					.'<div id="'.$c->domID().'-more" class="post-more-anchor"></div>'
					.substr($c->body, $p+strlen('<!--more-->'));
			}
		}
		$c->body = GBFilter::apply('body.html', $c->body);
	}
	if ($c instanceof GBPost && $c->excerpt)
		$c->excerpt = GBFilter::apply('excerpt.html', $c->excerpt);
	return $c;
}

GBFilter::add('post-reload-GBExposedContent', 'gb_filter_post_reload_content');
GBFilter::add('post-reload-GBExposedContent.html', 'gb_filter_post_reload_content_html');

?>