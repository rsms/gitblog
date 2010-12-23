<?php
/**
 * @name    Feedburner
 * @version 0.1
 * @author  Rasmus Andersson
 * @uri     http://gitblog.se/
 * 
 * Replaces the feed URL with your Feddburner feed, but allows the Feedburner
 * bot to read the original feed.
 */
class feedburner_plugin {
	static public $conf;
	
	static function init($context) {
		self::$conf = gb::data('plugins/'.gb_filenoext(basename(__FILE__)), array(
			'url' => ''
		));
		if (!self::$conf['url']) {
			gb::log(LOG_WARNING, 'missing "url" in configuration');
			return false;
		}
		gb::observe('will-handle-request', array(__CLASS__, 'will_handle_req'));
		return true;
	}
	
	static function will_handle_req() {
		if (!gb::$is_feed)
			return;
		
		$allowed_ips = self::$conf['allowed_ips'];
		if (!$allowed_ips)
			$allowed_ips = array();
		elseif (!is_array($allowed_ips))
			$allowed_ips = array($allowed_ips);
		
		$isfb = isset($_SERVER['HTTP_USER_AGENT']) && strpos($_SERVER['HTTP_USER_AGENT'], 'FeedBurner/') !== false;
		
		if (($isfb === false)
			and (in_array($_SERVER['REMOTE_ADDR'], $allowed_ips) === false)
			and (!isset($_GET['original-feed'])) # manual override
			)
		{
			# we send an atom feed as response for clients which do not follow redirects
			$site_title = h(gb::$site_title);
			$curr_url = h(gb::url()->__toString());
			$site_url = h(gb::$site_url);
			$url = h(self::$conf['url']);
			$mdate = @filemtime(self::$conf->storage()->file);
			$mdate = date('c', $mdate ? $mdate : time());
			$s = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<feed xmlns="http://www.w3.org/2005/Atom" xml:lang="en" xml:base="{$site_url}">
	<id>$curr_url</id>
	<title>$site_title</title>
	<link rel="alternate" type="application/atom+xml" href="$url" />
	<link rel="alternate" type="text/html" href="$site_url" />
	<updated>$mdate</updated>
	<entry>
		<title type="html">This feed has moved</title>
		<link rel="alternate" type="application/atom+xml" href="$url" />
		<id>$url</id>
		<published>$mdate</published>
		<updated>$mdate</updated>
		<content type="html"><![CDATA[
			<p>This feed has moved to <a href="$url">$url</a>.</p>
			<p>You see this because your feed client does not support automatic redirection.</p>
		]]></content>
	</entry>
</feed>

XML;
			header('HTTP/1.1 301 Moved Permanently');
			header('Location: '.self::$conf['url']);
			header('Content-Length: '.strlen($s));
			header('Content-Type: application/atom+xml; charset=utf-8');
			exit($s);
		}
	}
}
?>