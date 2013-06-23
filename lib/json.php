<?php
/**
 * JSON pretty printer.
 * 
 * For compact output and for decoding json, use the built in json_encode and
 * json_decode, respectively.
 */
class json {
	/**
	 * @param $force_object Outputs an object rather than an array when a non-
	 *   associative array is used. Especially useful when the recipient of the
	 *   output is expecting an object and the array is empty.
	 */
	static function pretty($var, $compact=false, $force_object=false, $level=0) {
		if ($level > 100)
			throw new OverflowException('too deep or recursion');
	
		$type = gettype($var);
	
		switch ($type) {
			case 'boolean':
				return $var ? 'true' : 'false';

			case 'NULL':
				return 'null';

			case 'integer':
				return (int) $var;

			case 'double':
			case 'float':
				return (float) $var;

			case 'string':
				# STRINGS ARE EXPECTED TO BE IN ASCII OR UTF-8 FORMAT
				$ascii = '';
				$strlen_var = strlen($var);

			  # Iterate over every character in the string,
				# escaping with a slash or encoding to UTF-8 where necessary
				for ($c = 0; $c < $strlen_var; ++$c) {
					$ord_var_c = ord($var{$c});
					switch (true) {
						case $ord_var_c === 0x08:
							$ascii .= '\b';
							break;
						case $ord_var_c === 0x09:
							$ascii .= '\t';
							break;
						case $ord_var_c === 0x0A:
							$ascii .= '\n';
							break;
						case $ord_var_c === 0x0C:
							$ascii .= '\f';
							break;
						case $ord_var_c === 0x0D:
							$ascii .= '\r';
							break;

						case $ord_var_c === 0x22:
						#case $ord_var_c === 0x2F: # the spec is not 100% clear on "/"
						case $ord_var_c === 0x5C:
							# double quote, slash, slosh
							$ascii .= '\\'.$var{$c};
							break;

						case (($ord_var_c >= 0x20) && ($ord_var_c <= 0x7F)):
							# characters U-00000000 - U-0000007F (same as ASCII)
							$ascii .= $var{$c};
							break;

						case (($ord_var_c & 0xE0) === 0xC0):
							# characters U-00000080 - U-000007FF, mask 110XXXXX
							# see http://www.cl.cam.ac.uk/~mgk25/unicode.html#utf-8
							$char = pack('C*', $ord_var_c, ord($var{$c + 1}));
							$c += 1;
							$utf16 = gb_utf8_to_utf16($char);
							$ascii .= sprintf('\u%04s', bin2hex($utf16));
							break;

						case (($ord_var_c & 0xF0) === 0xE0):
							# characters U-00000800 - U-0000FFFF, mask 1110XXXX
							$char = pack('C*', $ord_var_c,
										 ord($var{$c + 1}),
										 ord($var{$c + 2}));
							$c += 2;
							$utf16 = gb_utf8_to_utf16($char);
							$ascii .= sprintf('\u%04s', bin2hex($utf16));
							break;

						case (($ord_var_c & 0xF8) === 0xF0):
							# characters U-00010000 - U-001FFFFF, mask 11110XXX
							$char = pack('C*', $ord_var_c,
										 ord($var{$c + 1}),
										 ord($var{$c + 2}),
										 ord($var{$c + 3}));
							$c += 3;
							$utf16 = gb_utf8_to_utf16($char);
							$ascii .= sprintf('\u%04s', bin2hex($utf16));
							break;

						case (($ord_var_c & 0xFC) === 0xF8):
							# characters U-00200000 - U-03FFFFFF, mask 111110XX
							$char = pack('C*', $ord_var_c,
										 ord($var{$c + 1}),
										 ord($var{$c + 2}),
										 ord($var{$c + 3}),
										 ord($var{$c + 4}));
							$c += 4;
							$utf16 = gb_utf8_to_utf16($char);
							$ascii .= sprintf('\u%04s', bin2hex($utf16));
							break;

						case (($ord_var_c & 0xFE) === 0xFC):
							# characters U-04000000 - U-7FFFFFFF, mask 1111110X
							$char = pack('C*', $ord_var_c,
										 ord($var{$c + 1}),
										 ord($var{$c + 2}),
										 ord($var{$c + 3}),
										 ord($var{$c + 4}),
										 ord($var{$c + 5}));
							$c += 5;
							$utf16 = gb_utf8_to_utf16($char);
							$ascii .= sprintf('\u%04s', bin2hex($utf16));
							break;
					}
				}

				return '"'.$ascii.'"';

			case 'array':
			case 'object':
				$indent = $level ? str_repeat("\t", $level) : '';
				
				if ($force_object && !$var)
					return '{}';
				
				if ($type === 'object' || ($var && array_keys($var) !== range(0, count($var)-1))) {
					if ($type === 'object') {
						if (method_exists($var, '__sleep'))
							$var = array_intersect_key(get_object_vars($var), array_flip($var->__sleep()));
						else
							$var = get_object_vars($var);
					}
					if (!$var)
						return '{}';
					$s = '';
					$count = count($var);
					$i = 0;
					foreach ($var as $k => $v) {
						$comma = ++$i === $count ? '':',';
						$s .= "\n\t".$indent . json_encode(strval($k)) . ': ' 
							. self::pretty($v, $compact, $force_object, $level+1) . $comma;
					}
					$s = '{' . $s . "\n".$indent.'}';
				}
				else {
					if (!$var)
						return '[]';
					
					$s = '[';
					$count = count($var);
					$i = 0;
					foreach ($var as $v) {
						$comma = ++$i === $count ? '':',';
					 	$s .= "\n\t".$indent . self::pretty($v, $compact, $force_object, $level+1) . $comma;
					}
					$s .= "\n".$indent.']';
				}
				static $m = array("\t"=>'', "\n"=>'', ','=>', ');
				return ($compact && strlen($s) < 100 && ($s2 = strtr($s, $m)) && (strlen($s2) + (strlen($indent)*8) < 80)) ? $s2 : $s;
		
			default:
				throw new UnexpectedValueException(gettype($var).' can not be encoded as JSON');
		}
	}
}

if (function_exists('mb_convert_encoding')) {
	function gb_utf8_to_utf16($s) { return mb_convert_encoding($s, 'UTF-16', 'UTF-8'); }
}
else {
	function gb_utf8_to_utf16($utf8) {
		switch(strlen($utf8)) {
			case 1:
				return $utf8;
			case 2:
				return chr(0x07 & (ord($utf8{0}) >> 2)) . chr((0xC0 & (ord($utf8{0}) << 6)) | (0x3F & ord($utf8{1})));
			case 3:
				return chr((0xF0 & (ord($utf8{0}) << 4)) | (0x0F & (ord($utf8{1}) >> 2)))
					 . chr((0xC0 & (ord($utf8{1}) << 6)) | (0x7F & ord($utf8{2})));
		}
		return '?';
	}
}

?>