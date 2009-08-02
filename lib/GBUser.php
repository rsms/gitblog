<?
class GBUser {
	public $name;
	public $email;
	public $passhash;
	public $admin;
	
	function __construct($name=null, $email=null, $passhash=null, $admin=false) {
		$this->name = $name;
		$this->email = $email;
		$this->passhash = $passhash;
		$this->admin = $admin;
	}
	
	static function __set_state($state) {
		$o = new self;
		foreach ($state as $k => $v)
			$o->$k = $v;
		return $o;
	}
	
	function save() {
		return self::storage()->set(strtolower($this->email), $this);
	}
	
	function delete() {
		return self::storage()->set(strtolower($this->email), null);
	}
	
	static public $_storage = null;
	
	static function storage() {
		if (self::$_storage === null) {
			self::$_storage = new GBObjectStore(gb::$site_dir.'/data/users.json', __CLASS__);
			self::$_storage->autocommitToRepo = true;
		}
		return self::$_storage;
	}
	
	static function find($email=null) {
		if ($email !== null)
			$email = strtolower($email);
		return self::storage()->get($email);
	}
	
	static function passhash($email, $passphrase, $context='gb-admin') {
		return chap::shadow($email, $passphrase, $context);
	}
	
	static function findAdmin() {
		foreach (self::storage()->get() as $email -> $user) {
			if ($user->admin === true)
				return $user;
		}
		return null;
	}
	
	static function formatGitAuthor($account, $fallback=null) {
		if (!$account)
			throw new InvalidArgumentException('first argument is empty');
		$s = '';
		if ($account->name)
			$s = $account->name . ' ';
		if ($account->email)
			$s .= '<'.$account->email.'>';
		if (!$s) {
			if ($fallback === null)
				throw new InvalidArgumentException('neither name nor email is set');
			$s = $fallback;
		}
		return $s;
	}
	
	function gitAuthor() {
		return self::formatGitAuthor($this);
	}
	
	function __toString() {
		return $this->gitAuthor();
	}
}
?>