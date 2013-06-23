<?php
class GBUser extends GBAuthor {
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
		return CHAP::shadow($email, $passphrase, $context);
	}
	
	static function findAdmin() {
		foreach (self::storage()->get() as $email => $user) {
			if ($user->admin === true)
				return $user;
		}
		return null;
	}
}
?>