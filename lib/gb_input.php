<?php
class gb_input {
	static function process($filters, $source=INPUT_POST, $required_by_default=false, $strict=true) {
		# parse filters
		list($filters, $required, $defaults) = self::parse_filters($filters, $required_by_default);
		# apply
		$d = is_array($source) ? filter_var_array($source, $filters) : filter_input_array($source, $filters);
		if ($d === null)
			$d = array_fill_keys(array_keys($filters), null);
		# check required and set undefined to null (rather than false)
		foreach ($filters as $field => $filter) {
			$isa = is_array($filter);
			if ($d[$field] === null 
				|| ($d[$field] === false && ($isa ? $filter['filter'] : $filter) !== FILTER_VALIDATE_BOOLEAN))
			{
				if ($strict && isset($required[$field])) {
					throw new UnexpectedValueException($field.' is required');
				}
				elseif (isset($defaults[$field])) {
					if ($filter !== FILTER_DEFAULT) {
						if ($isa)
							$d[$field] = filter_var($defaults[$field], $filter['filter'],
								isset($filter['options']) ? $filter['options'] : null);
						else
							$d[$field] = filter_var($defaults[$field], $filter);
					}
					else
						$d[$field] = $defaults[$field];
				}
				else {
					$d[$field] = null;
				}
			}
		}
		return $d;
	}
	
	static function parse_filters($filters, $required_by_default) {
		static $stdsymbols = array(
			'str' => FILTER_DEFAULT,
			'bool' => FILTER_VALIDATE_BOOLEAN, # aliases: boolean
			#'float' => FILTER_VALIDATE_FLOAT,
			#'int' => FILTER_VALIDATE_INT,
			'email' => FILTER_VALIDATE_EMAIL,
			'ip' => FILTER_VALIDATE_IP,
			'url' => FILTER_VALIDATE_URL,
			'url+path' => array('filter'=>FILTER_VALIDATE_URL, 'flags'=>FILTER_FLAG_PATH_REQUIRED),
		);
		
		$required = array();
		$defaults = array();
		
		foreach ($filters as $field => $filter) {
			if (!$filter)
				$filter = FILTER_DEFAULT;
			$flags = $required_by_default ? FILTER_REQUIRE_SCALAR : 0;
			if (is_string($filter) && $filter) {
				if ($filter{0} === '!') {
					$filter = substr($filter, 1);
					$flags |= FILTER_REQUIRE_SCALAR;
				}
				elseif ($filter{0} === '?') {
					$flags &= ~FILTER_REQUIRE_SCALAR;
				}
				
				if ($filter && $filter{0} === '[') {
					$filter = trim($filter, '[]');
					if ($flags & FILTER_REQUIRE_SCALAR) {
						$flags &= ~FILTER_REQUIRE_SCALAR;
						$flags |= FILTER_REQUIRE_ARRAY;
					}
					$flags |= FILTER_FORCE_ARRAY;
				}
				
				if ($filter && ($p = strpos($filter, '(')) !== false) {
					$defaults[$field] = rtrim(substr($filter, $p+1),')');
					$filter = substr($filter, 0, $p);
				}
				
				if ($filter) {
					if (isset($stdsymbols[$filter])) {
						$filter = $stdsymbols[$filter];
					}
					elseif ($filter{0} === ':') {
						$filter = array('filter'=>FILTER_CALLBACK, 'options'=>substr($filter, 1));
					}
					elseif ($filter{0} === '@') {
						$filter = array('filter'=>FILTER_CALLBACK, 'options'=>create_function(
							'$x', 'return '.substr($filter, 1).'::__set_state($x);'
						));
					}
					elseif ($filter{0} === '/') {
						$filter = array('filter'=>FILTER_VALIDATE_REGEXP, 'options'=>array('regexp' => $filter));
					}
					else {
						$f = filter_id($filter);
						if ($f === false || $f === null)
							throw new InvalidArgumentException('Unknown filter "'.$filter.'"');
						$filter = $f;
					}
				}
				else {
					$filter = FILTER_DEFAULT;
				}
			}
			elseif (is_callable($filter)) {
				$filter = array('filter'=>FILTER_CALLBACK, 'options'=>$filter);
			}
			
			if ($flags) {
				if (is_int($filter)) {
					$filter = array('filter'=>$filter, 'flags'=>$flags);
				}
				elseif (isset($filter['flags'])) {
					$filter['flags'] |= $flags;
					$flags = $filter['flags'];
				}
				else {
					$filter['flags'] = $flags;
				}
			}
			else {
				$flags = is_array($filter) && isset($filter['flags']) ? $filter['flags'] : 0;
			}
			
			if ($flags & FILTER_REQUIRE_SCALAR || $flags & FILTER_REQUIRE_ARRAY)
				$required[$field] = $filter;
			
			$filters[$field] = $filter;
		}
		
		return array($filters, $required, $defaults);
	}
}
?>