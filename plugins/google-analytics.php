<?
class google_analytics {
	static public $conf;
	
	static function init($context) {
		self::$conf = gb::data('google-analytics');
		if (!self::$conf['property_id']) {
			gb::log(LOG_WARNING, 'missing property_id in google-analytics configuration');
		}
		else {
			gb::observe('on-html-footer', array(__CLASS__, 'echo_tracking_code'));
			return true;
		}
		return false;
	}
	
	static function echo_tracking_code() {
		static $prefix = '<script type="text/javascript">//<![CDATA[
		var gaJsHost = (("https:" == document.location.protocol) ? "https://ssl." : "http://www.");
		document.write(unescape("%3Cscript src=\'" + gaJsHost + "google-analytics.com/ga.js\' type=\'text/javascript\'%3E%3C/script%3E"));
		//]]></script><script type="text/javascript">//<![CDATA[
		try {var pageTracker = _gat._getTracker("';
		echo $prefix
			. self::$conf['property_id']
			. '");pageTracker._trackPageview();} catch(err) {} //]]></script>';
	}
}

function google_analytics_init($context) {
	return google_analytics::init($context);
}

?>