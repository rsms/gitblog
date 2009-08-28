<?
/**
 * @name    PHP content
 * @version 0.1
 * @author  Rasmus Andersson
 * @uri     http://gitblog.se/
 * 
 * Enables inline PHP code in pages and posts.
 * 
 * If any PHP code is found in HTML content, that code is evaluated at
 * runtime (like using include).
 */
class php_content_plugin {
	static public $conf;
	
	static function init($context) {
		if ($context === 'rebuild') {
			gb::observe('did-parse-object-meta', array(__CLASS__, 'check_content'));
			return true;
		}
		elseif ($context === 'request') {
			gb::add_filter('post-body', array(__CLASS__, 'eval_body'));
			return true;
		}
	}
	
	static function check_content(GBExposedContent $obj) {
		if (!$obj)
			return;
		# already have meta values?
		$eval = null;
		foreach ($obj->meta as $k => $v) {
			if ($k === 'php-eval') {
				$eval = $v;
				break;
			}
		}
		if ($eval === null) {
			# no php-eval meta, so let's check the body for PHP tags
			if (strpos($obj->body, '<?') !== false && strpos($obj->body, '?>') !== false) {
				$obj->meta['php-eval'] = 'request';
				gb::log('post %s eval is %s', $obj, 'request');
			}
			gb::log('post %s eval is %s', $obj, '?');
		}
		else {
			# normalize custom meta value
			if (is_string($eval))
				$eval = strtolower($eval);
			if ($eval !== 'request' && $eval !== 'rebuild') {
				if ($eval === gb_strbool($eval, true)) {
					$obj->meta['php-eval'] = 'request';
				}
				else {
					unset($obj->meta['php-eval']);
					$obj->body = strtr($obj->body, array('<?'=>'&lt;?','?>'=>'&gt;?'));
				}
			}
		}
		# eval now, at rebuild?
		if ($obj->meta['php-eval'] === 'rebuild') {
			ob_start();
			eval('?>'.$obj->body.'<?');
			$obj->body = ob_get_clean();
		}
	}
	
	static function eval_body($body) {
		if (strpos($body, '<?') !== false) {
			gb::log('evaluating PHP for body %s', $body);
			ob_start();
			eval('?>'.$body.'<?');
			$body = ob_get_clean();
		}
		return $body;
	}
}
?>
