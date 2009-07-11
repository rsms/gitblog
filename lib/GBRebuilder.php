<?
class GBRebuilder {
	public $forceFullRebuild;
	
	function __construct($forceFullRebuild=false) {
		$this->forceFullRebuild = $forceFullRebuild;
	}
	
	function onObject($name, $id) {
		return false;
	}
	
	function finalize() {
	}
	
	#----------------------------------------------------------------------------
	
	static public $rebuilders = array();
	
	/** Load rebuilders from GB_DIR/rebuilders */
	static function loadRebuilders() {
		self::$rebuilders = array();
		foreach (glob(GB_DIR.'/rebuilders/*.php') as $path) {
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
	static function rebuild($forceFullRebuild=false) {
		gb::log(LOG_NOTICE, 'rebuilding cache'.($forceFullRebuild ? ' (forcing full rebuild)':''));
		
		# Load rebuilders if needed
		if (empty(self::$rebuilders))
			self::loadRebuilders();
		
		# Create rebuilder instances
		$rebuilders = array();
		foreach (self::$rebuilders as $cls)
			$rebuilders[] = new $cls($forceFullRebuild);
		
		# Query ls-tree
		$ls = rtrim(GitBlog::exec('ls-files --stage'));
		
		if ($ls) {
			# Iterate objects
			$ls = explode("\n", $ls);
			foreach ($ls as $line) {
				# <mode> SP <object> SP <stage no> TAB <name>
				if (!$line)
					continue;
				$line = explode(' ', $line, 3);
				$id = $line[1];
				$name = gb_normalize_git_name(substr($line[2], strpos($line[2], "\t")+1));
			
				foreach ($rebuilders as $rebuilder)
					$rebuilder->onObject($name, $id);
			}
		}
		
		# Let rebuilders finalize
		foreach ($rebuilders as $rebuilder)
			$rebuilder->finalize();
	}
}
?>