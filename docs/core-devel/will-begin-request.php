static public $events = array();
	static public $lazy_triggers = array(
		'will-begin-response' => array('ob_start', array(array('gb','_will_begin_response_ev_obf'), 1))
	);
	
	/** Register $callable for receiving $event s */
	static function observe($event, $callable) {
		if(isset(self::$events[$event]))
			self::$events[$event][] = $callable;
		else {
			self::$events[$event] = array($callable);
			
			if (isset(self::$lazy_triggers[$event])) {
				$v = self::$lazy_triggers[$event];
				call_user_func_array($v[0], isset($v[1]) ? $v[1] : array());
			}
		}
	}
	
	static function $response_begun = false;
	
	static function _will_begin_response_ev_obf($chunk) {
		if (self::$response_begun === false) {
			self::$response_begun = true;
			$content_type = null;
			# try find content type in headers
			foreach (headers_list() as $h) {
				if (strpos(strtolower($h), 'content-type:') === 0) {
					$content_type = substr($h, 13);
					if (($p = strpos($content_type, ';')))
						$content_type = substr($content_type, 0, $p);
					$content_type = trim($content_type);
					break;
				}
			}
			# post event
			self::event('will-begin-response', $content_type, $chunk);
		}
	
		return $chunk;
	}