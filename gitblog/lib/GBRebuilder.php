<?
class GBRebuilder {
	public $gb;
	public $cachebase;
	public $forceFullRebuild;
	
	function __construct($gb, $forceFullRebuild=false) {
		$this->gb = $gb;
		$this->forceFullRebuild = $forceFullRebuild;
		$this->cachebase = "{$this->gb->gitdir}/info/gitblog";
	}
	
	function onObject(&$name, &$id) {
		return false;
	}
	
	function finalize() {
	}
	
	#----------------------------------------------------------------------------
	
	static public $rebuilders = array();
	
	/** Load rebuilders from GITBLOG_DIR/rebuilders */
	static function loadRebuilders() {
		self::$rebuilders = array();
		foreach (glob(GITBLOG_DIR.'/rebuilders/*.php') as $path) {
			$n = basename($path);
			if (preg_match('/^[a-z_][0-9a-z_]*\.php$/i', $n)) {
				include_once $path;
				$initname = 'init_rebuilder_'.substr($n, 0, -4);
				$initname(self::$rebuilders);
			}
		}
	}
	
	/**
	 * Rebuild caches, indexes, etc.
	 */
	static function rebuild(GitBlog $gb, $forceFullRebuild=false) {
		# Load rebuilders if needed
		if (empty(self::$rebuilders))
			self::loadRebuilders();
		
		# Create rebuilder instances
		$rebuilders = array();
		foreach (self::$rebuilders as $cls)
			$rebuilders[] = new $cls($gb, $forceFullRebuild);
		
		# List stage
		$ls = explode("\n", rtrim($gb->exec('ls-files --stage')));
		
		# Iterate objects
		foreach ($ls as $line) {
			# <mode> SP <object> SP <stage no> TAB <name>
			$line = explode(' ', $line, 3);
			$id =& $line[1];
			$name = substr($line[2], strpos($line[2], "\t")+1);
			
			foreach ($rebuilders as $rebuilder)
				$rebuilder->onObject($name, $id);
		}
		
		# Let rebuilders finalize
		foreach ($rebuilders as $rebuilder)
			$rebuilder->finalize();
	}
}
?>