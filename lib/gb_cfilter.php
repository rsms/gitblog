<?php
/** Content filters used in admin and rebuild contexts */
class gb_cfilter {
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
		if (!isset(self::$filters[$tag]))
			return $value;
		$a = self::$filters[$tag];
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
		}
		elseif (strpos($curl, '<code') !== false || strpos($curl, '<kbd') !== false
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
	$content = preg_replace('/&([^#])(?![a-z1-4]{1,8};)/i', '&#38;$1', $content);
	
	# Fix Microsoft Word pastes
	$content = strtr($content, $map);
	
	return $content;
}

function gb_htmlents_to_xmlents($s) {
	static $map = array(
		'&quot;'=>'&#34;','&amp;'=>'&#38;','&frasl;'=>'&#47;','&lt;'=>'&#60;','&gt;'=>'&#62;',
		'&nbsp;'=>'&#160;','&iexcl;'=>'&#161;','&cent;'=>'&#162;','&pound;'=>'&#163;',
		'&curren;'=>'&#164;','&yen;'=>'&#165;','&brvbar;'=>'&#166;','&brkbar;'=>'&#166;',
		'&sect;'=>'&#167;','&uml;'=>'&#168;','&die;'=>'&#168;','&copy;'=>'&#169;','&ordf;'=>'&#170;',
		'&laquo;'=>'&#171;','&not;'=>'&#172;','&shy;'=>'&#173;','&reg;'=>'&#174;','&macr;'=>'&#175;',
		'&hibar;'=>'&#175;','&deg;'=>'&#176;','&plusmn;'=>'&#177;','&sup2;'=>'&#178;','&sup3;'=>'&#179;',
		'&acute;'=>'&#180;','&micro;'=>'&#181;','&para;'=>'&#182;','&middot;'=>'&#183;',
		'&cedil;'=>'&#184;','&sup1;'=>'&#185;','&ordm;'=>'&#186;','&raquo;'=>'&#187;','&frac14;'=>'&#188;',
		'&frac12;'=>'&#189;','&frac34;'=>'&#190;','&iquest;'=>'&#191;','&Agrave;'=>'&#192;',
		'&Aacute;'=>'&#193;','&Acirc;'=>'&#194;','&Atilde;'=>'&#195;','&Auml;'=>'&#196;','&Aring;'=>'&#197;',
		'&AElig;'=>'&#198;','&Ccedil;'=>'&#199;','&Egrave;'=>'&#200;','&Eacute;'=>'&#201;','&Ecirc;'=>'&#202;',
		'&Euml;'=>'&#203;','&Igrave;'=>'&#204;','&Iacute;'=>'&#205;','&Icirc;'=>'&#206;','&Iuml;'=>'&#207;',
		'&ETH;'=>'&#208;','&Ntilde;'=>'&#209;','&Ograve;'=>'&#210;','&Oacute;'=>'&#211;','&Ocirc;'=>'&#212;',
		'&Otilde;'=>'&#213;','&Ouml;'=>'&#214;','&times;'=>'&#215;','&Oslash;'=>'&#216;','&Ugrave;'=>'&#217;',
		'&Uacute;'=>'&#218;','&Ucirc;'=>'&#219;','&Uuml;'=>'&#220;','&Yacute;'=>'&#221;','&THORN;'=>'&#222;',
		'&szlig;'=>'&#223;','&agrave;'=>'&#224;','&aacute;'=>'&#225;','&acirc;'=>'&#226;','&atilde;'=>'&#227;',
		'&auml;'=>'&#228;','&aring;'=>'&#229;','&aelig;'=>'&#230;','&ccedil;'=>'&#231;','&egrave;'=>'&#232;',
		'&eacute;'=>'&#233;','&ecirc;'=>'&#234;','&euml;'=>'&#235;','&igrave;'=>'&#236;','&iacute;'=>'&#237;',
		'&icirc;'=>'&#238;','&iuml;'=>'&#239;','&eth;'=>'&#240;','&ntilde;'=>'&#241;','&ograve;'=>'&#242;',
		'&oacute;'=>'&#243;','&ocirc;'=>'&#244;','&otilde;'=>'&#245;','&ouml;'=>'&#246;','&divide;'=>'&#247;',
		'&oslash;'=>'&#248;','&ugrave;'=>'&#249;','&uacute;'=>'&#250;','&ucirc;'=>'&#251;','&uuml;'=>'&#252;',
		'&yacute;'=>'&#253;','&thorn;'=>'&#254;','&yuml;'=>'&#255;','&OElig;'=>'&#338;','&oelig;'=>'&#339;',
		'&Scaron;'=>'&#352;','&scaron;'=>'&#353;','&Yuml;'=>'&#376;','&fnof;'=>'&#402;','&circ;'=>'&#710;',
		'&tilde;'=>'&#732;','&Alpha;'=>'&#913;','&Beta;'=>'&#914;','&Gamma;'=>'&#915;','&Delta;'=>'&#916;',
		'&Epsilon;'=>'&#917;','&Zeta;'=>'&#918;','&Eta;'=>'&#919;','&Theta;'=>'&#920;','&Iota;'=>'&#921;',
		'&Kappa;'=>'&#922;','&Lambda;'=>'&#923;','&Mu;'=>'&#924;','&Nu;'=>'&#925;','&Xi;'=>'&#926;',
		'&Omicron;'=>'&#927;','&Pi;'=>'&#928;','&Rho;'=>'&#929;','&Sigma;'=>'&#931;','&Tau;'=>'&#932;',
		'&Upsilon;'=>'&#933;','&Phi;'=>'&#934;','&Chi;'=>'&#935;','&Psi;'=>'&#936;','&Omega;'=>'&#937;',
		'&alpha;'=>'&#945;','&beta;'=>'&#946;','&gamma;'=>'&#947;','&delta;'=>'&#948;','&epsilon;'=>'&#949;',
		'&zeta;'=>'&#950;','&eta;'=>'&#951;','&theta;'=>'&#952;','&iota;'=>'&#953;','&kappa;'=>'&#954;',
		'&lambda;'=>'&#955;','&mu;'=>'&#956;','&nu;'=>'&#957;','&xi;'=>'&#958;','&omicron;'=>'&#959;',
		'&pi;'=>'&#960;','&rho;'=>'&#961;','&sigmaf;'=>'&#962;','&sigma;'=>'&#963;','&tau;'=>'&#964;',
		'&upsilon;'=>'&#965;','&phi;'=>'&#966;','&chi;'=>'&#967;','&psi;'=>'&#968;','&omega;'=>'&#969;',
		'&thetasym;'=>'&#977;','&upsih;'=>'&#978;','&piv;'=>'&#982;','&ensp;'=>'&#8194;','&emsp;'=>'&#8195;',
		'&thinsp;'=>'&#8201;','&zwnj;'=>'&#8204;','&zwj;'=>'&#8205;','&lrm;'=>'&#8206;','&rlm;'=>'&#8207;',
		'&ndash;'=>'&#8211;','&mdash;'=>'&#8212;','&lsquo;'=>'&#8216;','&rsquo;'=>'&#8217;','&sbquo;'=>'&#8218;',
		'&ldquo;'=>'&#8220;','&rdquo;'=>'&#8221;','&bdquo;'=>'&#8222;','&dagger;'=>'&#8224;',
		'&Dagger;'=>'&#8225;','&bull;'=>'&#8226;','&hellip;'=>'&#8230;','&permil;'=>'&#8240;','&prime;'=>'&#8242;',
		'&Prime;'=>'&#8243;','&lsaquo;'=>'&#8249;','&rsaquo;'=>'&#8250;','&oline;'=>'&#8254;','&frasl;'=>'&#8260;',
		'&euro;'=>'&#8364;','&image;'=>'&#8465;','&weierp;'=>'&#8472;','&real;'=>'&#8476;','&trade;'=>'&#8482;',
		'&alefsym;'=>'&#8501;','&crarr;'=>'&#8629;','&lArr;'=>'&#8656;','&uArr;'=>'&#8657;','&rArr;'=>'&#8658;',
		'&dArr;'=>'&#8659;','&hArr;'=>'&#8660;','&forall;'=>'&#8704;','&part;'=>'&#8706;','&exist;'=>'&#8707;',
		'&empty;'=>'&#8709;','&nabla;'=>'&#8711;','&isin;'=>'&#8712;','&notin;'=>'&#8713;','&ni;'=>'&#8715;',
		'&prod;'=>'&#8719;','&sum;'=>'&#8721;','&minus;'=>'&#8722;','&lowast;'=>'&#8727;','&radic;'=>'&#8730;',
		'&prop;'=>'&#8733;','&infin;'=>'&#8734;','&ang;'=>'&#8736;','&and;'=>'&#8743;','&or;'=>'&#8744;',
		'&cap;'=>'&#8745;','&cup;'=>'&#8746;','&int;'=>'&#8747;','&there4;'=>'&#8756;','&sim;'=>'&#8764;',
		'&cong;'=>'&#8773;','&asymp;'=>'&#8776;','&ne;'=>'&#8800;','&equiv;'=>'&#8801;','&le;'=>'&#8804;',
		'&ge;'=>'&#8805;','&sub;'=>'&#8834;','&sup;'=>'&#8835;','&nsub;'=>'&#8836;','&sube;'=>'&#8838;',
		'&supe;'=>'&#8839;','&oplus;'=>'&#8853;','&otimes;'=>'&#8855;','&perp;'=>'&#8869;','&sdot;'=>'&#8901;',
		'&lceil;'=>'&#8968;','&rceil;'=>'&#8969;','&lfloor;'=>'&#8970;','&rfloor;'=>'&#8971;','&lang;'=>'&#9001;',
		'&rang;'=>'&#9002;','&larr;'=>'&#8592;','&uarr;'=>'&#8593;','&rarr;'=>'&#8594;','&darr;'=>'&#8595;',
		'&harr;'=>'&#8596;','&loz;'=>'&#9674;','&spades;'=>'&#9824;','&clubs;'=>'&#9827;','&hearts;'=>'&#9829;',
		'&diams;'=>'&#9830;'
	);
	return strtr($s, $map);
}

function _gb_xmlents_to_utf8_cb($matches) {
	$s = html_entity_decode($matches[0], ENT_QUOTES, 'UTF-8');
	if (strlen($s) === 1) {
		$n = ord($s);
		if (
			($n === 34 || $n === 39 || $n === 38 || $n === 60 || $n === 62)
			||
			(($n < 32) && ($n !== 9 && $n !== 10 && $n !== 13))
		)
			return $matches[0];
	}
	return $s;
}

function gb_xmlents_to_utf8($s) {
	return preg_replace_callback('/&(?:#(?:\d{2,}|x[0-9a-fA-F]{2,})|\w+);/',
		'_gb_xmlents_to_utf8_cb', $s);
}

# HTML -> XHTML
function gb_html_to_xhtml($s) {
	$s = preg_replace("/<(img|hr|meta|link|br|base|frame|input)([^>]*[^>\/]|)>/mi", "<$1$2 />", $s);
	return $s;
}

function gb_normalize_html_structure_clean_pre($matches) {
	static $map = array('<br />' => '', '<p>' => "\n", '</p>' => '');
	return strtr(is_string($matches) ? $matches : $matches[1] . $matches[2] . '</pre>', $map);
}


# LF => <br />, etc
function gb_normalize_html_structure($s, $conf=array()) {
	$convert_nl_to_br = $conf && isset($conf['auto_linebreaks']) ? $conf['auto_linebreaks'] : true;
	$convert_nlnl_to_p = $conf && isset($conf['auto_paragraphs']) ? $conf['auto_paragraphs'] : true;
	$s = $s . "\n"; # just to make things a little easier, pad the end
	$s = preg_replace('|<br />\s*<br />|', "\n\n", $s);
	# Space things out a little
	static $allblocks = '(?:table|thead|tfoot|caption|colgroup|tbody|tr|td|th|div|dl|dd|dt|ul|ol|li|pre|select|form|map|area|blockquote|address|math|style|input|p|h[1-6]|hr)';
	$s = preg_replace('!(<' . $allblocks . '[^>]*>)!', "\n$1", $s);
	$s = preg_replace('!(</' . $allblocks . '>)!', "$1\n\n", $s);
	$s = str_replace(array("\r\n", "\r"), "\n", $s); # cross-platform newlines
	if ( strpos($s, '<object') !== false ) {
		$s = preg_replace('|\s*<param([^>]*)>\s*|', "<param$1>", $s); # no pee inside object/embed
		$s = preg_replace('|\s*</embed>\s*|', '</embed>', $s);
	}
	$s = preg_replace("/\n\n+/", "\n\n", $s); # take care of duplicates
	if ($convert_nlnl_to_p)
		$s = preg_replace('/\n?(.+?)(?:\n\s*\n|\z)/s', "<p>$1</p>\n", $s); # make paragraphs, including one at the end
	$s = preg_replace('!<p>([^<]+)\s*?(</(?:div|address|form)[^>]*>)!', "<p>$1</p>$2", $s);
	$s = preg_replace( '|<p>|', "$1<p>", $s );
	$s = preg_replace('!<p>\s*(</?' . $allblocks . '[^>]*>)\s*</p>!', "$1", $s); # don't pee all over a tag
	$s = preg_replace("|<p>(<li.+?)</p>|", "$1", $s); # problem with nested lists
	$s = preg_replace('|<p><blockquote([^>]*)>|i', "<blockquote$1><p>", $s);
	$s = str_replace('</blockquote></p>', '</p></blockquote>', $s);
	$s = preg_replace('!<p>\s*(</?' . $allblocks . '[^>]*>)!', "$1", $s);
	$s = preg_replace('!(</?' . $allblocks . '[^>]*>)\s*</p>!', "$1", $s);
	
	if ($convert_nl_to_br) {
		$s = preg_replace_callback('/<(script|style).*?<\/\\1>/s', 
			create_function('$matches', 'return str_replace("\n", "<__preserve_newline__ />", $matches[0]);'), $s);
			$s = preg_replace('|(?<!<br />)\s*\n|', "<br />\n", $s); # optionally make line breaks
		$s = str_replace('<__preserve_newline__ />', "\n", $s);
	}
	
	$s = preg_replace('!(</?' . $allblocks . '[^>]*>)\s*<br />!', "$1", $s);
	$s = preg_replace('!<br />(\s*</?(?:p|li|div|dl|dd|dt|th|pre|td|ul|ol)[^>]*>)!', '$1', $s);
	if (strpos($s, '<pre') !== false)
		$s = preg_replace_callback('!(<pre.*?>)(.*?)</pre>!is', 'gb_normalize_html_structure_clean_pre', $s );
	$s = preg_replace( "|\n</p>$|", '</p>', $s );
	#$s = preg_replace('/<p>\s*?(' . get_shortcode_regex() . ')\s*<\/p>/s', '$1', $s);
	# ^ don't auto-p wrap shortcodes that stand alone
	$s = preg_replace('|<p>\s*?</p>|', '', $s);
	return rtrim($s);
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

function gb_sanitize_url($s, $default_scheme='http') {
	$u = @parse_url($s);
	if ($u === false || !isset($u['scheme'])) {
		if (($p = strpos($s, '://')) !== false)
			$s = substr($s, $p+3);
		$u = @parse_url($default_scheme.'://'.ltrim($s,'/'));
		if ($u === false || !isset($u['host']) || strpos($u['host'], '.') === false)
			return false;
	}
	if (!isset($u['path']))
		$u['path'] = '/';
	$s = $u['scheme'].'://';
	if (isset($u['user']) || isset($u['pass'])) {
		if (isset($u['user']))
			$s .= $u['user'];
		if (isset($u['pass']))
			$s .= ':'.$u['pass'];
		$s .= '@';
	}
	$s .= $u['host'] . $u['path'];
	if (isset($u['query']))
		$s .= '?'.$u['query'];
	if (isset($u['fragment']))
		$s .= '#'.$u['fragment'];
	return $s;
}

/**
 * Balances tags of string using a modified stack.
 *
 * @since 2.0.4
 *
 * @author Leonard Lin <leonard@acm.org>
 * @license GPL v2.0
 * @copyright November 4, 2001
 * @version 1.1
 * @todo Make better - change loop condition to $text in 1.2
 * @internal Modified by Scott Reilly (coffee2code) 02 Aug 2004
 *		1.1  Fixed handling of append/stack pop order of end text
 *			 Added Cleaning Hooks
 *		1.0  First Version
 *
 * @param string $text Text to be balanced.
 * @return string Balanced text.
 */
function gb_force_balance_tags($text) {
	$tagstack = array(); $stacksize = 0; $tagqueue = ''; $newtext = '';
	$single_tags = array('br', 'hr', 'img', 'input'); #Known single-entity/self-closing tags
	$nestable_tags = array('blockquote', 'div', 'span'); #Tags that can be immediately nested within themselves

	# WP bug fix for comments - in case you REALLY meant to type '< !--'
	$text = str_replace('< !--', '<    !--', $text);
	# WP bug fix for LOVE <3 (and other situations with '<' before a number)
	$text = preg_replace('#<([0-9]{1})#', '&lt;$1', $text);

	while (preg_match("/<(\/?\w*)\s*([^>]*)>/",$text,$regex)) {
		$newtext .= $tagqueue;

		$i = strpos($text,$regex[0]);
		$l = strlen($regex[0]);

		# clear the shifter
		$tagqueue = '';
		# Pop or Push
		if ( isset($regex[1][0]) && '/' == $regex[1][0] ) { # End Tag
			$tag = strtolower(substr($regex[1],1));
			
			# if too many closing tags
			if ($stacksize <= 0) {
				$tag = '';
				#or close to be safe $tag = '/' . $tag;
			}
			# if stacktop value = tag close value then pop
			else if ($tagstack[$stacksize - 1] == $tag) { # found closing tag
				$tag = '</' . $tag . '>'; # Close Tag
				# Pop
				array_pop ($tagstack);
				$stacksize--;
			} 
			else { # closing tag not at top, search for it
				for ($j=$stacksize-1;$j>=0;$j--) {
					if ($tagstack[$j] == $tag) {
					# add tag to tagqueue
						for ($k=$stacksize-1;$k>=$j;$k--){
							$tagqueue .= '</' . array_pop ($tagstack) . '>';
							$stacksize--;
						}
						break;
					}
				}
				$tag = '';
			}
		}
		else { # Begin Tag
			$tag = strtolower($regex[1]);

			# Tag Cleaning

			# If self-closing or '', don't do anything.
			if((substr($regex[2],-1) == '/') || ($tag == '')) {
			}
			# ElseIf it's a known single-entity tag but it doesn't close itself, do so
			elseif ( in_array($tag, $single_tags) ) {
				$regex[2] .= '/';
			} else {	# Push the tag onto the stack
				# If the top of the stack is the same as the tag we want to push, close previous tag
				if (($stacksize > 0) && !in_array($tag, $nestable_tags) && ($tagstack[$stacksize - 1] == $tag)) {
					$tagqueue = '</' . array_pop ($tagstack) . '>';
					$stacksize--;
				}
				$stacksize = array_push ($tagstack, $tag);
			}

			# Attributes
			$attributes = $regex[2];
			if($attributes) {
				$attributes = ' '.$attributes;
			}
			$tag = '<'.$tag.$attributes.'>';
			#If already queuing a close tag, then put this tag on, too
			if ($tagqueue) {
				$tagqueue .= $tag;
				$tag = '';
			}
		}
		$newtext .= substr($text,0,$i) . $tag;
		$text = substr($text,$i+$l);
	}

	# Clear Tag Queue
	$newtext .= $tagqueue;

	# Add Remaining text
	$newtext .= $text;

	# Empty Stack
	while($x = array_pop($tagstack)) {
		$newtext .= '</' . $x . '>'; # Add remaining tags to close
	}

	# WP fix for the bug with HTML comments
	$newtext = str_replace("< !--","<!--",$newtext);
	$newtext = str_replace("<    !--","< !--",$newtext);

	return $newtext;
}


# -----------------------------------------------------------------------------
# GBExposedContent filters

function gb_filter_br2sp($text) {
	return str_replace("\n", ' ', $text);
}

function gb_filter_br2brlf($text) {
	return preg_replace('/<(br[\r\n\s ]*\/?|\/?p)>/m', "$0\n", $text);
}

/** trim(c->body) */
function gb_filter_post_reload_content(GBExposedContent $c) {
	if ($c->body)
		$c->body = trim($c->body);
	if ($c->excerpt)
		$c->excerpt = trim($c->excerpt);
	return $c;
}

function gb_filter_post_reload_content_get_conf(GBExposedContent $c) {
	$conf = array(
		'auto_linebreaks' => true,
		'auto_paragraphs' => true
	);
	if (isset($c->meta['auto-linebreaks'])) {
		$conf['auto_linebreaks'] = gb_strbool($c->meta['auto-linebreaks']);
		unset($c->meta['auto-linebreaks']);
	}
	if (isset($c->meta['auto-paragraphs'])) {
		$conf['auto_paragraphs'] = gb_strbool($c->meta['auto-paragraphs']);
		unset($c->meta['auto-paragraphs']);
	}
	return $conf;
}

/** Converts LF to <br/>LF and extracts excerpt for GBPost objects */
function gb_filter_post_reload_content_html(GBExposedContent $c) {
	$conf = gb_filter_post_reload_content_get_conf($c);
	# have body?
	if ($c->body) {
		# create excerpt for GBPosts if not already set
		if (!$c->excerpt && preg_match('/<!--[ \s\t\r\n]*more[ \s\t\r\n]*--(>)/i', $c->body, $m, PREG_OFFSET_CAPTURE)) {
			$c->excerpt = substr($c->body, 0, $m[0][1]);
			$c->body = $c->excerpt
				.'<div id="read-more" class="post-more-anchor"></div>'
				.substr($c->body, $m[1][1]+1 /* pos of last ">" */ );
		}
		$c->body = gb_cfilter::apply('body.html', $c->body, $conf);
	}
	if ($c->excerpt) {
		$c->excerpt = gb_cfilter::apply('body.html', $c->excerpt, $conf);
	}
	return $c;
}


# -----------------------------------------------------------------------------
# GBComments filters

function gb_filter_post_reload_comments(GBComments $comments) {
	foreach ($comments as $comment)
		gb_cfilter::apply('post-reload-comment', $comment);
	return $comments;
}

function gb_filter_post_reload_comment(GBComment $comment) {
	$comment->body = gb_cfilter::apply('sanitize-comment', $comment->body);
	return $comment;
}


class gb_allowed_tags {
	public static $cb = array();
}

# generate map of tag => attr callback proxies
foreach (GBComment::$allowedTags as $t => $x) {
	gb_allowed_tags::$cb[$t] = create_function(
		'$matches','return _gb_filter_allowed_attrs_cb(\''.$t.'\', $matches);');
}

function _gb_filter_allowed_attrs_cb($tag, $matches) {
	$attr = strtolower($matches[1]);
	if (!in_array($attr, GBComment::$allowedTags[$tag]))
		return '';
	return $attr.'="'.trim($matches[2],'"\'').'"';
}

function _gb_filter_allowed_tags_cb($matches) {
	$tag = strtolower($matches[1]);
	if (($is_end = ($tag && $tag{0} === '/')))
		$tag = substr($tag, 1);
	if (!isset(GBComment::$allowedTags[$tag]))
		return '';
	if ($is_end)
		return '</'.$tag.'>';
	$attrs = false;
	if (GBComment::$allowedTags[$tag] && $matches[2]) {
		$attrs = trim(preg_replace_callback('/(\w+)=("[^"]*"|\'[^\']*\'|[^ \s\t]*)/',
			gb_allowed_tags::$cb[$tag],
			$matches[2]));
	}
	if ($attrs)
		return '<'.$tag.' '.$attrs.'>';
	return '<'.$tag.'>';
}

function gb_filter_allowed_tags($body) {
	$body = preg_replace_callback('/<(\/?\w*)\s*([^>]*)>/', '_gb_filter_allowed_tags_cb', $body);
	return $body;
}

function gb_filter_pre_comment(GBComment $comment) {
  if (!$comment) return $comment; # if it was classed as spam, for instance
	# replace CR[LF] with LF (damned be you, Windows users!)
	$comment->body = preg_replace('/\\r\\n?/', "\n", $comment->body);
	
	return $comment;
}

function gb_uri_to_html_link($text) {
	$tokens = gb_tokenize_html($text);
	$out = '';
	
	foreach ($tokens as $token) {
		if (isset($token{0}) && $token{0} !== '<' && $token{0} !== '[') {
			$words = preg_split('/([\\x00-\\x20\\x7f\\x60"\'<>\\\\\]\[\^]+)/', $token, -1,
				PREG_SPLIT_DELIM_CAPTURE|PREG_SPLIT_NO_EMPTY);
			$token = '';
			foreach ($words as $word) {
				$word = preg_replace('/^[a-zA-Z][a-zA-Z0-9\.+-]*:.+$/', '<a href="$0">$0</a>', $word);
				$token .= $word;
			}
		}
		$out .= $token;
	}
	
	return $out;
}

/** Expands abspath in HTML to URLs (based on gb::$site_url) */
function gb_html_abspaths_to_urls($html) {
	$html = preg_replace('/((?:^|[ \t\s])(?:src|href))="\/([^"]*)"/', 
		'$1="'.gb::$site_url.'$2"', $html);
	return $html;
}

/** Adds missing width and height attribute for img tags */
function gb_html_img_size($html) {
	$html = preg_replace_callback('/<img[^>]+src=[^>]+>/sm', 
		'_gb_html_img_size_cb', $html);
	return $html;
}

function _gb_html_img_size_cb($m) {
	$img = $m[0];
	if (preg_match('/(?:[^a-zA-Z0-9_]+|^)(?:width|height)=/', $img))
		return $img;
	
	if (!preg_match('/(?:[^a-zA-Z0-9_]+|^)src=("[^"]+"|\'[^\']+\')/sm', $img, $srcm))
		return $img;
	
	# lookup
	$src = trim($srcm[1], '"\'');
	if (!$src)
		return $img;
	
	if ($src{0} === '/')
		$src = gb::$site_dir.$src;
	elseif (strpos($src, '://') === false)
		$src = gb::$site_dir.'/'.$src;
	elseif (strpos($src, gb::$site_url) === 0)
		$src = gb::$site_dir.'/'.substr($src, strlen(gb::$site_url));
	
	if (!($v = @getimagesize($src)))
		return $img;
	
	if (substr($img, -2) === '/>')
		$img = trim(substr($img, 0, -2)).' '.$v[3].' />';
	else
		$img = trim(substr($img, 0, -1)).' '.$v[3].'>';
	
	return $img;
}

function gb_split_tags($text) {
	return preg_split('/[ \t]*,+[ \t]*/', $text, -1, PREG_SPLIT_NO_EMPTY);
}

function gb_unique_strings($strings) {
	# a trick for removing duplicates w/o reordering items.
	# Also faster than array_unique.
	return array_flip(array_flip($strings));
}

function gb_vstrtolower($strings) {
	return array_map('strtolower', $strings);
}

function gb_approve_ham_comment($comment) {
	if ($comment && $comment->spam === false)
		$comment->approved = true;
	return $comment;
}

# ----------------------------------------------------------------------------
# Default filters

# Used to GBExposedContent->slug = filter(GBExposedContent->title)
gb_cfilter::add('sanitize-title', 'gb_sanitize_title', 10);

# Applied to URLs from the outside world, for instance when adding comments
gb_cfilter::add('sanitize-url', 'gb_sanitize_url', 10);

# Called with GBExposedContent object when they are reloaded
gb_cfilter::add('post-reload-GBExposedContent', 'gb_filter_post_reload_content', 10);

# Called with GBExposedContent object of type ".html" when they are reloaded
gb_cfilter::add('post-reload-GBExposedContent.html', 'gb_filter_post_reload_content_html', 10);

# Applied to HTML content prior to writing cache
gb_cfilter::add('body.html', 'gb_texturize_html', 10);
gb_cfilter::add('body.html', 'gb_convert_html_chars', 20);
gb_cfilter::add('body.html', 'gb_html_to_xhtml', 30);
gb_cfilter::add('body.html', 'gb_normalize_html_structure', 40);
gb_cfilter::add('body.html', 'gb_htmlents_to_xmlents', 50);
gb_cfilter::add('body.html', 'gb_xmlents_to_utf8', 60);
gb_cfilter::add('body.html', 'gb_force_balance_tags', 70);
#gb_cfilter::add('body.html', 'gb_html_img_size', 80);
gb_cfilter::add('body.html', 'gb_html_abspaths_to_urls', 10000);

# Applied to GBComment after being posted, but before being saved
gb_cfilter::add('pre-comment', 'gb_filter_pre_comment', 10);
# before this one you might want to hook on some spam assessment plugin, like Akismet.
gb_cfilter::add('pre-comment', 'gb_approve_ham_comment', 10000);

# Applied to GBComments/GBComment after being reloaded but prior to writing cache.
gb_cfilter::add('post-reload-comments', 'gb_filter_post_reload_comments', 10);
gb_cfilter::add('post-reload-comment', 'gb_filter_post_reload_comment', 10);

# Applied to GBComment::$body prior to writing the comments' cache.
gb_cfilter::add('sanitize-comment', 'gb_texturize_html', 10);
gb_cfilter::add('sanitize-comment', 'gb_convert_html_chars', 20);
gb_cfilter::add('sanitize-comment', 'gb_html_to_xhtml', 30);
gb_cfilter::add('sanitize-comment', 'gb_force_balance_tags', 40);
gb_cfilter::add('sanitize-comment', 'gb_uri_to_html_link', 50);
gb_cfilter::add('sanitize-comment', 'gb_filter_allowed_tags', 60);
gb_cfilter::add('sanitize-comment', 'gb_normalize_html_structure', 70);
gb_cfilter::add('sanitize-comment', 'gb_htmlents_to_xmlents', 80);
gb_cfilter::add('sanitize-comment', 'gb_xmlents_to_utf8', 90);
gb_cfilter::add('sanitize-comment', 'gb_html_abspaths_to_urls', 10000);

# Applied to tag-type meta fields at reload and should at the end return a
# clean array of tokens. Note that gb_split_tags performs the initial splitting
# on comma and strips whitespace, thus any filter run after this one will get
# an array as the first argument. Any filter run prior to gb_split_tags will
# get a string as input and should return a string.
gb_cfilter::add('parse-tags', 'gb_split_tags', 10);
gb_cfilter::add('parse-tags', 'gb_vstrtolower', 20);
gb_cfilter::add('parse-tags', 'gb_unique_strings', 30);

?>
