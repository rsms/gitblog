<?
class GBHTTPDigestAuth {
	public $realm = 'realm';
	public $url = '/';
	public $ttl = 300;
	
	function __construct($realm='auth', $ttl=300, $url=null) {
		$this->realm = $realm;
		$this->url = $url !== null ? $url : gb::$site_url;
		$this->ttl = $ttl;
	}
	
	function authenticate($users) {
		if (empty($_SERVER['PHP_AUTH_DIGEST']))
			return false;
		
		# analyze
		if (!($data = self::parse($_SERVER['PHP_AUTH_DIGEST'])) || !isset($users[$data['username']]))
			return false;
		
		# check input
		if ($this->ttl > 0 && $data['nonce'] !== $this->nonce())
			return false;
		
		# generate the valid response
		$A1 = $users[$data['username']];
		$A2 = md5($_SERVER['REQUEST_METHOD'].':'.$data['uri']);
		$valid_response = md5($A1.':'.$data['nonce'].':'.$data['nc'].':'.$data['cnonce'].':'.$data['qop'].':'.$A2);
		if ($data['response'] != $valid_response)
			return false;
		
		return $data['username'];
	}
	
	function nonce() {
		return gb_nonce_make('digest-auth-'.$this->realm, $this->ttl);
	}
	
	function send() {
		header('HTTP/1.0 401 Unauthorized');
		header('WWW-Authenticate: Digest '.
			'realm="'.$this->realm.'",'.
			#'domain="'.$this->url.'",'.
			'qop="auth",'.
			'algorithm="MD5",'.
			'nonce="'.$this->nonce().'",'.
			'opaque="'.md5($this->realm).'"'
		);
	}
	
	static function parse($txt) {
		# protect against missing data
		static $needed_parts = array('nonce'=>1, 'nc'=>1, 'cnonce'=>1, 'qop'=>1, 'username'=>1, 'uri'=>1, 'response'=>1);
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
	$d->send();
	exit(0);
}
echo 'authenticated as '.$username;
*/
?>