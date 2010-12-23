<?php
class GBHTTPDigestAuth {
	public $realm = 'auth';
	public $domain = null;
	public $ttl = 300;
	
	function __construct($realm='auth', $users=null, $ttl=300, $domain=null) {
		$this->realm = $realm;
		$this->users = $users;
		$this->ttl = $ttl;
		$this->domain = $domain;
	}
	
	function authenticate($users=null) {
		if (!isset($_SERVER['PHP_AUTH_DIGEST']) || empty($_SERVER['PHP_AUTH_DIGEST']))
			return false;
		
		# users
		if ($users === null)
			$users = $this->users ? $this->users : array();
		
		# analyze
		if (!($data = self::parse($_SERVER['PHP_AUTH_DIGEST']))) {
			gb::log('GBHTTPDigestAuth: failed to parse '.var_export($_SERVER['PHP_AUTH_DIGEST'],1));
			return false;
		}
		elseif (!isset($users[$data['username']])) {
			gb::log('GBHTTPDigestAuth: unknown username '.var_export($data['username'],1));
			return false;
		}
		
		# check input
		if ($this->ttl > 0 && $data['nonce'] !== $this->nonce())
			return false;
		
		# generate the valid response
		$A1 = $users[$data['username']]; # MD5(username:realm:password)
		$A2 = md5($_SERVER['REQUEST_METHOD'].':'.$data['uri']); # MD5(method:digestURI)
		$valid_response = md5($A1.':'.$data['nonce'].':'.$data['nc'].':'.$data['cnonce'].':'.$data['qop'].':'.$A2);
		if ($data['response'] != $valid_response) {
			gb::log('GBHTTPDigestAuth: unexpected response '.var_export($data['response'],1));
			return false;
		}
		
		return $data['username'];
	}
	
	function nonce() {
		return gb_nonce_make('digest-auth-'.$this->realm, $this->ttl);
	}
	
	function sendHeaders($status='401 Unauthorized') {
		if ($status)
			header('HTTP/1.1 '.$status);
		header('WWW-Authenticate: Digest '.
			'realm="'.$this->realm.'",'.
			($this->domain ? 'domain="'.$this->domain.'",' : '').
			'qop="auth",'.
			'algorithm="MD5",'.
			'nonce="'.$this->nonce().'",'.
			'opaque="'.md5($this->realm).'"'
		);
	}
	
	static function parse($txt) {
		# protect against missing data
		$needed_parts = array('nonce'=>1, 'nc'=>1, 'cnonce'=>1, 'qop'=>1, 'username'=>1, 'uri'=>1, 'response'=>1);
		$data = array();
		$keys = implode('|', array_keys($needed_parts));
		preg_match_all('@(' . $keys . ')=(?:([\'"])([^\2]+?)\2|([^\s,]+))@', $txt, $matches, PREG_SET_ORDER);
		foreach ($matches as $m) {
			$data[$m[1]] = $m[3] ? $m[3] : $m[4];
			unset($needed_parts[$m[1]]);
		}
		return $needed_parts ? false : $data;
	}
}

/*
$realm = 'hell';
$users = array(
	'rasmus' => md5('rasmus:'.$realm.':password')
);
$d = new GBHTTPDigestAuth($realm);
if (!($username = $d->authenticate($users))) {
	$d->sendHeaders();
	exit(0);
}
echo 'authenticated as '.$username;
*/
?>