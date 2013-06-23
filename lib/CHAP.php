<?php
/**
 * Challenge-handshake authentication protocol.
 * 
 * Schema:
 * 
 *   response = HMAC( a, nonce )
 *   a        = HMAC( shadow, opaque )
 *   shadow   = SHA1( username ":" context ":" password )
 *   opaque   = H( gb::$secret )
 *   nonce    = H( timeslice [context] client-addr )
 *   H        = HMAC( $data, gb::$secret )
 *   HMAC     = SHA-1-HMAC
 */
class CHAP {
	public $ttl = 604800; # 1 week
	public $cookie_name = 'gb-chap'; # set to false to disable cookies
	public $refresh_cookie = true;
	public $users = null;
	public $context = '';
	public $preshadowed = true;
	public $allow_plain = true;
	
	const BAD_USER = 0;
	const BAD_RESPONSE = false;
	const UNKNOWN = null;
	
	function __construct($users, $context='', $preshadowed=true, $cookie_name='gb-chap') {
		$this->users = $users;
		$this->context = $context;
		$this->preshadowed = $preshadowed;
		$this->cookie_name = $cookie_name;
	}
	
	static function h($s) {
		return hash_hmac('sha1', $s, gb::$secret);
	}
	
	static function shadow($username, $password, $context='') {
		return sha1($username.':'.$context.':'.$password);
	}
	
	static function opaque() {
		return self::h(gb::$secret);
	}
	
	function nonce() {
		$s = strval((int)ceil(time() / $this->ttl)) . $this->context . $_SERVER['REMOTE_ADDR'];
		return self::h($s);
	}
	
	function set_cookie($username, $response, $shadow=false, $cookie=false) {
		if (!$this->cookie_name)
			return;
		if ( $cookie !== false && !$this->refresh_cookie
			&& ($response && isset($cookie['r'])) || ($shadow && isset($cookie['p'])) ) {
			# no need to refresh
			return;
		}
		if (headers_sent())
			return;
		$cookie = array('u' => $username);
		if ($response)
			$cookie['r'] = $response;
		else
			$cookie['s'] = $shadow;
		$cookie = base64_encode(serialize($cookie));
		$url = new GBURL(gb::$site_url);
		setcookie($this->cookie_name, $cookie, time() + $this->ttl, $url->path, $url->host, $url->secure);
	}
	
	function get_cookie() {
		$s = $_COOKIE[$this->cookie_name];
		return unserialize(base64_decode(get_magic_quotes_gpc() ? stripslashes($s) : $s));
	}
	
	function auth_handshake($username, $response, $cookie=false) {
		if (!isset($this->users[$username]))
			return self::BAD_USER;
		$shadow = $this->preshadowed ? 
			$this->users[$username] : 
			self::shadow($username, $this->users[$username], $this->context);
		$a = hash_hmac('sha1', $shadow, self::opaque());
		$expected_response = hash_hmac('sha1', $a, $this->nonce());
		if ($response !== $expected_response)
			return self::BAD_RESPONSE;
		$this->set_cookie($username, $response, null, $cookie);
		return $username;
	}
	
	function auth_plain($username, $password, $shadow=false, $cookie=false) {
		if (!$this->allow_plain)
			return self::UNKNOWN;
		if (!isset($this->users[$username]))
			return self::BAD_USER;
		$expected_shadow = $this->preshadowed ? 
			$this->users[$username] : 
			self::shadow($username, $this->users[$username], $this->context);
		if ( ($password && $this->users[$username] !== 
					($this->preshadowed ? self::shadow($username, $password, $this->context) : $password) )
				|| ($shadow && $expected_shadow !== $shadow) )
			return self::BAD_RESPONSE;
		$this->set_cookie($username, null, $expected_shadow, $cookie);
		return $username;
	}
	
	function authenticate() {
		$authed = self::UNKNOWN;
		$cookie = false;
		
		if (isset($_POST['chap-username'])) {
			$username = $_POST['chap-username'];
			if (get_magic_quotes_gpc())
			 	$username = stripslashes($username);
			if (isset($_POST['chap-response']) && $_POST['chap-response'])
				$authed = $this->auth_handshake($username, $_POST['chap-response']);
			elseif (isset($_POST['chap-shadow']) && $_POST['chap-shadow'])
				$authed = $this->auth_plain($username, false, $_POST['chap-shadow']);
			elseif (isset($_POST['chap-password']) && $_POST['chap-password']) {
				$passwd = get_magic_quotes_gpc() ? 
					stripslashes($_POST['chap-password']) : $_POST['chap-password'];
				$authed = $this->auth_plain($username, $passwd);
			}
		}
		elseif (isset($_COOKIE[$this->cookie_name]) && ($cookie = $this->get_cookie())) {
			if (isset($cookie['r']))
				$authed = $this->auth_handshake($cookie['u'], $cookie['r'], $cookie);
			elseif (isset($cookie['s']))
				$authed = $this->auth_plain($cookie['u'], false, $cookie['s'], $cookie);
		}
		
		return $authed;
	}
	
	function deauthorize() {
		if (isset($_COOKIE[$this->cookie_name])) {
			$url = new GBURL(gb::$site_url);
			setcookie($this->cookie_name, '', time()-1000, $url->path, $url->host, $url->secure);
			unset($_COOKIE[$this->cookie_name]);
		}
	}
}
?>