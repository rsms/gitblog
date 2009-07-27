<?
/**
 * Akismet spam helper.
 * 
 * Events:
 * 
 *  - "did-spam-comment", $comment
 *    Posted when a comment have been caught as spam.
 * 
 *  - "did-ham-comment", $comment
 *    Posted when a comment have been verified as ham.
 * 
 */
class akismet {
	static public $key;
	static public $host = 'rest.akismet.com';
	static public $port = 80;
	
	static function init() {
		# check settings
		if (gb::$settings['akismet'] === null)
			gb::$settings['akismet'] = array('api_key' => '');
		
		if (!self::$key)
			self::$key = gb::$settings['akismet']['api_key'];
	}

	static function verify_key($key, $ip=null) {
		$blog = urlencode(gb::$site_url);
		$response = self::http_post("key=$key&blog=$blog", '/1.1/verify-key', null, null, $ip);
		if ( !is_array($response) || !isset($response[1]) || $response[1] != 'valid' && $response[1] != 'invalid' )
			return 'failed';
		return $response[1];
	}
	
	// Check connectivity between the WordPress blog and Akismet's servers.
	// Returns an associative array of server IP addresses, where the key is the
	// IP address, and value is true (available) or false (unable to connect).
	static function check_server_connectivity() {
		$test_host = 'rest.akismet.com';

		// Some web hosts may disable one or both functions
		if ( !is_callable('fsockopen') || !is_callable('gethostbynamel') )
			return array();

		$ips = gethostbynamel($test_host);
		if ( !$ips || !is_array($ips) || !count($ips) )
			return array();

		$servers = array();
		foreach ( $ips as $ip ) {
			$response = self::verify_key(self::$key, $ip);
			// even if the key is invalid, at least we know we have connectivity
			if ( $response == 'valid' || $response == 'invalid' )
				$servers[$ip] = true;
			else
				$servers[$ip] = false;
		}

		return $servers;
	}
	
	// Check the server connectivity and store the results in an option.
	// Cached results will be used if not older than the specified timeout in
	// seconds; use $cache_timeout = 0 to force an update.
	// Returns the same associative array as akismet_check_server_connectivity()
	static function get_server_connectivity( $cache_timeout = 86400 ) {
		$servers = gb::$settings['akismet']['available_servers'];
		if ( (time() - gb::$settings['akismet']['connectivity_time'] < $cache_timeout) && $servers !== false )
			return $servers;

		// There's a race condition here but the effect is harmless.
		$servers = self::check_server_connectivity();
		gb::$settings['akismet']['available_servers'] = $servers;
		gb::$settings['akismet']['connectivity_time'] = time();
		return $servers;
	}

	// Returns true if server connectivity was OK at the last check, false if there was a problem that needs to be fixed.
	static function server_connectivity_ok() {
		$servers = self::get_server_connectivity();
		return !( empty($servers) || !count($servers) || count( array_filter($servers) ) < count($servers) );
	}
	
	static function get_host($host) {
		// if all servers are accessible, just return the host name.
		// if not, return an IP that was known to be accessible at the last check.
		if ( self::server_connectivity_ok() ) {
			return $host;
		} 
		else {
			$ips = self::get_server_connectivity();
			// a firewall may be blocking access to some Akismet IPs
			if ( count($ips) > 0 && count(array_filter($ips)) < count($ips) ) {
				// use DNS to get current IPs, but exclude any known to be unreachable
				$dns = (array)gethostbynamel( rtrim($host, '.') . '.' );
				$dns = array_filter($dns);
				foreach ( $dns as $ip ) {
					if ( array_key_exists( $ip, $ips ) && empty( $ips[$ip] ) )
						unset($dns[$ip]);
				}
				// return a random IP from those available
				if ( count($dns) )
					return $dns[ array_rand($dns) ];

			}
		}
		// if all else fails try the host name
		return $host;
	}
	
	static function http_post($body, $path, $host=null, $port=null, $ip=null) {
		$host = $host === null ? self::$host : $host;
		$port = $port === null ? self::$port : $port;
		
		$http_request  = "POST $path HTTP/1.0\r\n"
			. "Host: $host\r\n"
			. "Content-Type: application/x-www-form-urlencoded; charset=utf-8\r\n"
			. "Content-Length: " . strlen($body) . "\r\n"
			. "User-Agent: WordPress/2.8 | Akismet/2.0\r\n"
			. "\r\n"
			. $body;

		$http_host = $host;
		// use a specific IP if provided - needed by akismet_check_server_connectivity()
		if ( $ip && long2ip(ip2long($ip)) ) {
			$http_host = $ip;
		} else {
			$http_host = self::get_host($host);
		}

		$response = '';
		if( false != ( $fs = @fsockopen($http_host, $port, $errno, $errstr, 10) ) ) {
			fwrite($fs, $http_request);

			while ( !feof($fs) )
				$response .= fgets($fs, 1160); // One TCP-IP packet
			fclose($fs);
			$response = explode("\r\n\r\n", $response, 2);
		}
		return $response;
	}
	
	static function check_comment($comment) {
		# already approved?
		if ($comment->approved) {
			gb::log(LOG_INFO, 'aksimet: skipping check since comment is already approved');
			return $comment;
		}
		
		$params = array(
			# required
			'blog'         => gb::$site_url,
			'user_ip'      => $comment->ipAddress,
			'user_agent'   => $_SERVER['HTTP_USER_AGENT'],
			# optional
			'referrer'     => $_SERVER['HTTP_REFERER'],
			'blog_charset' => 'utf-8',
			'comment_type' => $comment->type === GBComment::TYPE_COMMENT ? 'comment' : 'pingback', # comment | trackback | pingback
			'comment_author' => $comment->name,
			'comment_author_email' => $comment->email,
			'comment_content' => $comment->body,
			#'blog_lang'    => 'en',
			#'permalink'    => $comment->url()
		);
		
		if ($comment->uri)
			$params['comment_author_url'] = $comment->uri;
		
		# add HTTP_* server vars (request headers)
		static $ignore = array('HTTP_COOKIE');
		foreach ($_SERVER as $key => $value)
			if (strpos($key, 'HTTP_') === 0 && !in_array($key, $ignore) && is_string($value))
				$params[$key] = $value;
		
		# POST
		gb::log(LOG_NOTICE, 'aksimet: checking comment');
		$reqbody = http_build_query($params);
		$response = self::http_post($reqbody, '/1.1/comment-check', self::$key.'.'.self::$host);
		
		# parse response
		if ($response[1] === 'true') {
			gb::log(LOG_NOTICE, 'aksimet: comment classed as spam');
			gb::$settings['akismet']['spam_count'] = intval(gb::$settings['akismet']['spam_count']) + 1;
			$comment->approved = false;
			gb::event('did-spam-comment', $comment);
		}
		elseif ($response[1] === 'false') {
			gb::log(LOG_NOTICE, 'aksimet: comment classed as ham');
			$comment->approved = true;
			gb::event('did-ham-comment', $comment);
		}
		else {
			gb::log(LOG_WARNING, 'akismet: unexpected response from /1.1/comment-check: '.$response[1]);
		}
		
		# forward
		return $comment;
	}
}

function akismet_init($context) {
	akismet::init();
	
	if (!akismet::$key) {
		gb::log(LOG_NOTICE, 
			'akismet not loaded since "akismet" => "api_key" is not set in settings.json');
		return false;
	}
	
	gb::log(LOG_DEBUG, 'akismet: loaded from '.__FILE__);
	
	if ($context === 'admin') {
		GBFilter::add('pre-comment', array('akismet','check_comment'));
		return true;
	}
}

?>