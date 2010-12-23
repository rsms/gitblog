<?php
class GitCommit {
	public $id;
	public $tree;
	public $authorEmail;
	public $authorName;
	public $authorDate; # GBDateTime
	public $comitterEmail;
	public $comitterName;
	public $comitterDate; # GBDateTime
	public $message;
	public $files; # array example: array(GitPatch::CREATE => array('file1', 'file3'), GitPatch::DELETE => array('file2'))
	public $previousFiles; # available for GitPatch::RENAME and COPY. array('file1', 'file2', ..)
	
	/** Tries to resolve author as a GBUser */
	function authorUser($default=null) {
		$user = $this->authorEmail ? GBUser::find($this->authorEmail) : null;
		if ($user === null)
			return $default;
		return $user;
	}
	
	/** Tries to resolve committer as a GBUser */
	function committerUser() {
		$user = $this->committerEmail ? GBUser::find($this->committerEmail) : null;
		if ($user === null)
			return $default;
	}
	
	function rawPatch($paths=null) {
		$paths = git::escargs($paths);
		$cmd = 'log -p --full-index --pretty="format:" --encoding=UTF-8 --date=iso --dense '
			.escapeshellarg($this->id)
			.' -1';
		if ($paths)
			$cmd .= ' -- '.$paths;
		$s = git::exec($cmd);
		return $s ? substr($s, 1, -1) : '';
	}
	
	/** Load a set of GitPatch objects for this commit, optionally restricting to patches affecting $paths */
	function loadPatches($paths=null) {
		if (($x = $this->rawPatch($paths)))
			return GitPatch::parse($x);
		return array();
	}
	
	static public $fields = array(
		'id','tree',
		'authorEmail','authorName','authorDate',
		'comitterEmail','comitterName', 'comitterDate',
		'message');
	
	static public $logFormat = '%H%n%T%n%ae%n%an%n%ai%n%ce%n%cn%n%ci%n%s%x00';
	
	/** Returns array($commits, $existing, $ntoc) */
	static function find($kwargs=null) {
		static $defaultkwargs = array(
			'names' => null,
			'treeish' => 'HEAD',
			'limit' => -1,
			'sortrcron' => true,
			'detectRC' => true,
			'mapnamestoc' => false # if true, returns a 3rd arg: map[name] => commit
		);
		$kwargs = $kwargs ? array_merge($defaultkwargs, $kwargs) : $defaultkwargs;
		$commits = array();
		$existing = array(); # tracks existing files
		$ntoc = $kwargs['mapnamestoc'] ? array() : null;
		
		$cmd = "log --name-status --pretty='format:".self::$logFormat."' "
			."--encoding=UTF-8 --date=iso --dense";
		
		# do not sort reverse chronological
		$rcron = $kwargs['sortrcron'];
		if (!$rcron)
			$cmd .= ' --reverse';
		
		# detect renames and copies
		if ($kwargs['detectRC'])
			$cmd .= ' -C';
		else
			$cmd .= ' --no-renames';
		
		# limit
		if ($kwargs['limit'] > 0)
			$cmd .= " --max-count=".$kwargs['limit'];
		
		# treeish
		$cmd .= " ".$kwargs['treeish']." -- ";
		
		# filter object names
		if ($kwargs['names'])
			$cmd .= implode(' ', array_map('escapeshellarg', $kwargs['names']));
		
		#var_dump($cmd);
		$out = git::exec($cmd);
		#var_dump($out);
		
		$a = 0;
		$len = strlen($out);
		
		while ($a !== false && $a <= $len) {
			$c = new self();
			
			foreach (self::$fields as $field) {
				if ($field == 'message')
					$b = strpos($out, "\0", $a);
				else
					$b = strpos($out, "\n", $a);
				
				if ($b === false)
					break;
				
				if (substr($field, -4) == 'Date')
					$c->$field = new GBDateTime(substr($out, $a, $b-$a));
				else
					$c->$field = substr($out, $a, $b-$a);
				
				$a = $b + 1;
			}
			
			if ($b === false)
				break;
			
			$b = strpos($out, "\n\n", $a);
			$files = ($b === false) ? substr($out, $a) : substr($out, $a, $b-$a);
			$files = explode("\n", trim($files));
			$c->files = array();
			
			foreach ($files as $line) {
				$line = explode("\t", $line);
				if (count($line) < 2)
					continue;
				$t = $line[0]{0};
				$name = gb_normalize_git_name($line[1]);
				$previousName = null;
				
				# R|C have two names wherether the last is the new name
				if ($t === GitPatch::RENAME || $t === GitPatch::COPY) {
					$previousName = $name;
					$name = $line[2];
					if ($c->previousFiles === null)
						$c->previousFiles = array($previousName);
					else
						$c->previousFiles[] = $previousName;
				}
				
				# add to files[tag] => [name, ..]
				if (isset($c->files[$t]))
					$c->files[$t][] = $name;
				else
					$c->files[$t] = array($name);
				
				# if kwarg mapnamestoc == true
				if ($ntoc !== null) {
					if (!isset($ntoc[$name]))
						$ntoc[$name] = array($c);
					else
						$ntoc[$name][] = $c;
				}
				
				# update cached objects
				#if (isset($repo->objectCacheByName[$name])) {
				#	$obj = $repo->objectCacheByName[$name];
				#	if ($obj->_commit === null)
				#		$obj->_commit = $c;
				#}
				
				# update existing
				if (!$rcron) {
					if ($t === GitPatch::CREATE || $t === GitPatch::COPY)
						$existing[$name] = $c;
					elseif ($t === GitPatch::DELETE && isset($existing[$name]))
						unset($existing[$name]);
					elseif ($t === GitPatch::RENAME) {
						if (isset($existing[$previousName])) {
							# move original CREATE
							$existing[$name] = $existing[$previousName];
							unset($existing[$previousName]);
						}
						else {
							$existing[$name] = $c;
						}
						# move commits from previous file if kwarg mapnamestoc == true
						if ($ntoc !== null && isset($ntoc[$previousName])) {
							$ntoc[$name] = array_merge($ntoc[$previousName], $ntoc[$name]);
							unset($ntoc[$previousName]);
						}
					}
				}
			}
			
			$commits[$c->id] = $c;
			
			if ($b === false)
				break;
			$a = $b + 2;
		}
		
		return array($commits, $existing, $ntoc);
	}
}
?>