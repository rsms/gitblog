<?php
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
 * 
 * To enable this plugin, register it in both the "rebuild" and the "request"
 * plugin context. If you are planning to only use "php-eval: rebuild" you do
 * not need to register the for the "request" plugin context.
 * 
 * The meta header "php-eval" can be set to explicitly control PHP evaluation.
 * Values can be one of the following:
 * 
 *  - "request" to evaluate the content when requested.
 *  - "rebuild" to evaluate and save the content when post is rebuilt.
 *  - Boolean: if true, "request" is assumed. If false PHP evaluation 
 *    is explicitly disabled.
 * 
 * If no php-eval meta header exists, this plugin will guess if there are any
 * PHP content, and if so assume "request" evaluation.
 */
class php_content_plugin {
	static function init($context) {
		if ($context === 'rebuild') {
			gb::observe('did-parse-object-meta', array(__CLASS__, 'check_content'));
			gb_cfilter::add('body.html', array(__CLASS__, 'escape_php'), 0);
			gb_cfilter::add('body.html', array(__CLASS__, 'unescape_php'), 9000);
			
			return true;
		}
		elseif ($context === 'request') {
			gb::add_filter('post-body', array(__CLASS__, 'eval_body'));
			return true;
		}
	}
	
	static function escape_php($text) {
		return preg_replace('/<\?.+\?>/Umse', '\'???-\'.base64_encode(\'$0\').\'-???\'', $text);
	}
	
	static function unescape_php($text) {
		return preg_replace('/\?\?\?-([a-zA-Z0-9\/+=]+)-\?\?\?/Umse', 'base64_decode(\'$1\')', $text);
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
			if (strpos($obj->body, '<?') !== false && strpos($obj->body, '?>') !== false)
				$eval = 'request';
		}
		else {
			# normalize custom meta value
			if (is_string($eval))
				$eval = strtolower($eval);
			if ($eval !== 'request' && $eval !== 'rebuild') {
				if (gb_strbool($eval, true) === true) {
					$eval = 'request';
				}
				else {
					unset($obj->meta['php-eval']);
					$obj->body = strtr($obj->body, array('<?'=>'&lt;?','?>'=>'&gt;?'));
					$eval = null;
				}
			}
		}
		# eval now, at rebuild?
		if ($eval === 'rebuild') {
			ob_start();
			eval('?>'.$obj->body.'<?');
			$obj->body = ob_get_clean();
		}
	}
	
	static function eval_body($body) {
		if (strpos($body, '<?') !== false) {
			ob_start();
			eval('?>'.$body.'<?');
			$body = ob_get_clean();
		}
		return $body;
	}
}
?>
