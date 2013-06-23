<?php
/**
 * @name    Google analytics
 * @version 0.1
 * @author  Rasmus Andersson
 * @uri     http://gitblog.se/
 * 
 * Google analytics tracking.
 * 
 * This plugin is only effective in the "request" plugin context.
 */
class google_analytics_plugin {
	static public $conf;
	
	static function init($context) {
		self::$conf = gb::data('plugins/google-analytics',array('property_id'=>''));
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
		var script = document.createElement("script");
		script.setAttribute("src", gaJsHost + "google-analytics.com/ga.js");
		script.setAttribute("type", "text/javascript");
		document.getElementsByTagName("head").item(0).appendChild(script);
		//]]></script><script type="text/javascript">//<![CDATA[
		try {var pageTracker = _gat._getTracker("';
		echo $prefix
			. self::$conf['property_id']
			. '");pageTracker._trackPageview();} catch(err) {} //]]></script>';
	}
}
?>