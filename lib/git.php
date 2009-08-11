<?
class git {
	static public $query_count = 0;
	
	/** Execute a git command */
	static function exec($cmd, $input=null, $gitdir=null, $worktree=null) {
		# build cmd
		if ($gitdir === null)
			$gitdir = gb::$site_dir.'/.git';
		if ($worktree === null)
			$worktree = gb::$site_dir;
		$cmd = 'git --git-dir='.escapeshellarg($gitdir)
			.' --work-tree='.escapeshellarg($worktree)
			.' '.$cmd;
		#var_dump($cmd);
		gb::log(LOG_DEBUG, 'exec$ '.$cmd);
		$r = gb::shell($cmd, $input, gb::$site_dir);
		git::$query_count++;
		# fail?
		if ($r === null)
			return null;
		# check for errors
		if ($r[0] != 0) {
			$msg = trim($r[1]."\n".$r[2]);
			if (strpos($r[2], 'Not a git repository') !== false)
				throw new GitUninitializedRepoError($msg, $r[0], $cmd);
			else
				throw new GitError($msg, $r[0], $cmd);
		}
		return $r[1];
	}
	
	static function escargs($args) {
		if ($args === null)
			return '';
		return is_string($args) ? escapeshellarg($args) : implode(' ', array_map('escapeshellarg', $args));
	}
	
	/** Each argument can be a string or and array of strings (or null to skip) */
	static function diff($commits=null, $paths=null, $args=null) {
		$commits = self::escargs($commits);
		$args = self::escargs($args);
		$paths = self::escargs($paths);
		$cmd = 'diff '.$args.' '.$commits;
		if ($paths)
			$cmd .= ' -- '.$paths;
		return git::exec($cmd);
	}
	
	static function cat_file($ids) {
		$id_to_data = array();
		if (is_string($ids)) {
			$ids = array($ids);
			$id_to_data = false;
		}
		
		# load
		$out = git::exec("cat-file --batch", implode("\n", $ids));
		$p = 0;
		$numobjects = count($ids);
		
		# parse
		for ($i=0; $i<$numobjects; $i++) {
			# <id> SP <type> SP <size> LF
			# <contents> LF
			$hend = strpos($out, "\n", $p);
			$hs = substr($out, $p, $hend-$p);
			$h = explode(' ', $hs);
			
			if ($h[1] === 'missing')
				throw new UnexpectedValueException('missing blob '.$hs);
			
			$dstart = $hend + 1;
			$size = intval($h[2]);
			$data = substr($out, $dstart, $size);
			if ($id_to_data === false) {
				# a single object was requested (string input)
				return $data;
			}
			$id_to_data[$h[0]] = $data;
			
			$p = $dstart + $size + 1;
		}
		
		return $id_to_data;
	}
	
	/** Retrieve ids for $pathspec (string or array of strings) at the current branch head */
	static function id_for_pathspec($pathspec) {
		if (is_string($pathspec))
			return trim(git::exec('rev-parse '.escapeshellarg(':'.$pathspec)));
		if (!is_array($pathspec))
			throw new InvalidArgumentException('$pathspec is '.gettype($pathspec));
		# array
		$pathspec_to_id = array();
		foreach ($pathspec as $k => $s)
			$pathspec[$k] = escapeshellarg(':'.$s);
		$lines = explode("\n", trim(git::exec('rev-parse '.implode(' ', $pathspec))));
		$i = 0;
		foreach ($pathspec as $s)
			$pathspec_to_id[$s] = $lines[$i++];
		return $pathspec_to_id;
	}
}
?>