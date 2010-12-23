<?php
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
	
	/** Load rebuilders from gb::$dir/rebuilders */
	static function loadRebuilders() {
		self::$rebuilders = array();
		foreach (glob(gb::$dir.'/rebuilders/*.php') as $path) {
			$n = basename($path);
			if (preg_match('/^[a-z_][0-9a-z_]*\.php$/i', $n)) {
				$libname = substr($n, 0, -4);
				gb::log(LOG_INFO, 'loading rebuilder library "%s" from %s', 
					$libname, substr($path, strlen(gb::$dir)+1));
				include_once $path;
				$initname = 'init_rebuilder_'.$libname;
				$initname(self::$rebuilders);
			}
		}
	}
	
	/**
	 * Rebuild caches, indexes, etc.
	 */
	static function rebuild($forceFullRebuild=false) {
		gb::log(LOG_NOTICE, 'rebuilding cache'.($forceFullRebuild ? ' (forcing full rebuild)':''));
		$time_started = microtime(1);
		$failures = array();
		
		# Load rebuild plugins
		gb::load_plugins('rebuild');
		
		# Load rebuilders if needed
		if (empty(self::$rebuilders))
			self::loadRebuilders();
		
		# Create rebuilder instances
		$rebuilders = array();
		foreach (self::$rebuilders as $cls)
			$rebuilders[] = new $cls($forceFullRebuild);
		
		# Load rebuild plugins (2nd offer)
		gb::load_plugins('rebuild');
		
		# Query ls-tree
		$ls = rtrim(git::exec('ls-files --stage'));
		
		if ($ls) {
			# Iterate objects
			$ls = explode("\n", $ls);
			foreach ($ls as $line) {
				try {
					# <mode> SP <object> SP <stage no> TAB <name>
					if (!$line)
						continue;
					$line = explode(' ', $line, 3);
					$id = $line[1];
					$name = gb_normalize_git_name(substr($line[2], strpos($line[2], "\t")+1));
			
					foreach ($rebuilders as $rebuilder)
						$rebuilder->onObject($name, $id);
				}
				catch (RuntimeException $e) {
					gb::log(LOG_ERR, 'failed to rebuild object %s %s: %s',
						var_export($name,1), $e->getMessage(), $e->getTraceAsString());
					$failures[] = array($rebuilder, $name);
				}
			}
		}
		
		# Let rebuilders finalize
		foreach ($rebuilders as $rebuilder) {
			try {
				$rebuilder->finalize();
			}
			catch (RuntimeException $e) {
				gb::log(LOG_ERR, 'rebuilder %s (0x%x) failed to finalize: %s',
					get_class($rebuilder), spl_object_hash($rebuilder),
					GBException::format($e, true, false, null, 0));
				$failures[] = array($rebuilder, null);
			}
		}
		
		gb::log(LOG_NOTICE, 'cache updated -- time spent: %s', 
			gb_format_duration(microtime(1)-$time_started));
		
		return $failures;
	}
}
?>