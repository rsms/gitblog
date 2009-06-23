<?
error_reporting(E_ALL);
include 'mime_type.php';
define('GITBLOG_DIR', dirname(realpath(__FILE__)));
$conf = json_decode(file_get_contents(GITBLOG_DIR.'/config.json'), true);

$_ENV['PATH'] .= ':/opt/local/bin'; # xxx local


function proc_open2($cmd, $cwd=null, $env=null) {
	$fds = array(array("pipe", "r"), array("pipe", "w"), array("pipe", "w"));
	$ps = proc_open($cmd, $fds, $pipes, $cwd, $env);
	if (!is_resource($ps)) {
		trigger_error('proc_open2('.var_export($cmd,1).') failed in '.__FILE__.':'.__LINE__);
		return null;
	}
	return array('handle'=>$ps, 'pipes'=>$pipes);
}


function fileext($path) {
	$p = strpos($path, '.', strrpos($path, '/'));
	return $p > 0 ? substr($path, $p+1) : '';
}

# path w/o extension
function filenoext($path) {
	$p = strpos($path, '.', strrpos($path, '/'));
	return $p > 0 ? substr($path, 0, $p) : $path;
}

/** Like readline, but acts on a byte array. Keeps state with $p */
function sreadline(&$p, &$str, $sep="\n") {
	if ($p === null)
		$p = 0;
	$i = strpos($str, $sep, $p);
	if ($i === false)
		return null;
	#echo "p=$p i=$i i-p=".($i-$p)."\n";
	$line = substr($str, $p, $i-$p);
	$p = $i + 1;
	return $line;
}


function git_is_objid($str) {
	return strlen($str) == 40 && preg_match('/^[a-f0-9]{40}$/', $str);
}


function gb_parse_headers($raw) {
	$headers = array();
	$lines = explode("\n", $raw);
	$k = null;
	
	foreach ($lines as $line) {
		if (!$line)
			continue;
		if ($line{0} === ' ' || $line{0} === "\t") {
			# continuation
			if ($k !== null)
				$headers[$k] .= ltrim($line);
			continue;
		}
		$line = explode(':', $line, 2);
		if (isset($line[1])) {
			$k = $line[0];
			$headers[$k] = ltrim($line[1]);
		}
	}
	
	return $headers;
}

function gb_atomic_write($filename, &$data) {
	$tempnam = tempnam(dirname($filename), basename($filename));
	$f = fopen($tempnam, 'w');
	fwrite($f, $data);
	fclose($f);
	if (!rename($tempnam, $filename)) {
		unlink($tempnam);
		return false;
	}
	return true;
}


class GitError extends Exception {}
class GitUninitializedRepoError extends GitError {}


class GitObject {
	const TAG_CACHED = 'H';
	const TAG_UNMERGED = 'M';
	const TAG_REMOVED = 'R';
	const TAG_MODIFIED = 'C';
	const TAG_KILLED = 'K';
	const TAG_OTHER = '?';
	
	public $repo;
	public $id;
	public $name;
	public $mode;
	public $stage;
	public $tag;
	public $size;
	public $_data;
	public $_mime_type;
	public $_commit;
	
	function __construct($repo, $id, $name=null, $size=-1, $mode=0, $stage=0, 
	                     $tag='?', $data=null, $mime_type=null, $date=null, 
	                     $commit=null, $cache=true)
	{
		$this->repo = $repo;
		$this->id = $id;
		$this->name = $name;
		$this->size = $size;
		$this->mode = $mode;
		$this->stage = $stage;
		#switch ($tag) {
		#	case 'H': $this->tag = self::TAG_CACHED; break;
		#	case 'M': $this->tag = self::TAG_UNMERGED; break;
		#	case 'R': $this->tag = self::TAG_REMOVED; break;
		#	case 'C': $this->tag = self::TAG_MODIFIED; break;
		#	case 'K': $this->tag = self::TAG_KILLED; break;
		#	default: $this->tag = self::TAG_OTHER;
		#}
		$this->tag = $tag;
		$this->_data = $data;
		$this->_mime_type = $mime_type;
		$this->_date = $date;
		$this->_commit = $commit;
		if ($cache)
			$repo->cacheObject($this);
	}
	
	function __get($k) {
		if ($k == 'filename') {
			return array_pop(explode('/', $this->name));
		}
		elseif ($k == 'fileext') {
			return fileext($this->name);
		}
		elseif ($k == 'commit') {
			if ($this->_commit === null)
				$this->repo->loadCommitsForObjects(array($this));
			return $this->_commit;
		}
		elseif ($k == 'date') {
			/*if ($this->_date === null) {
				# .git/objects/fd/61acc11234ba29e15c41bbae91ae701467d704
				$this->_date = filemtime($this->repo->gitdir.'/objects/'.
					substr($this->id, 0, 2).'/'.substr($this->id, 2));
			}
			return $this->_date;*/
			return $this->commit->comitterDate;
		}
		return null;
	}
	
	static function fromLsTreeLine($repo, $line) {
		# <mode> SP <type> SP <object> SP <object size> TAB <file>
		$id = substr($line, 12, 40);
		if (!($obj = $repo->getCachedObjectById($id))) {
			$cls = (substr($line, 7, 4) == 'blob') ? 'GitBlob' : 'GitTree';
			$tp = strpos($line, "\t", 50);
			$obj = new $cls(
				$repo,
				$id,
				substr($line, $tp+1), # name
				intval(ltrim(substr($line, 53, $tp-53))), # size
				intval(substr($line, 0, 6)), # mode
				intval(substr($line, 51, 1)), # stage
				'?');
		}
		return $obj;
	}
	
	static function fromLsFilesLine($repo, $line) {
		# [<tag> SP] mode SP object SP stage TAB path
		$tag = '?';
		if ($line{1} == ' ') {
			$tag = $line{0};
			$line = substr($line, 2);
		}
		$id = substr($line, 7, 40);
		if (!($obj = $repo->getCachedObjectById($id))) {
			$obj = new self(
				$repo,
				$id,
				substr($line, 50), # name
				-1, # size
				intval(substr($line, 0, 6)), # mode
				intval(substr($line, 48, 1)), # stage
				$tag);
		}
		return $obj;
	}
	
	static function fromObjectTypeSizeLine($repo, $line, $name=null) {
		if (substr($line, -9) == " missing\n")
			return null;
		$id = substr($line, 0, 40);
		if (!($obj = $repo->getCachedObjectById($id))) {
			$cls = substr($line, 41, 4);
			$cls = ($cls == 'blob') ? 'GitBlob' : (($cls == 'tree') ? 'GitTree' : 'GitObject');
			$obj = new $cls(
				$repo,
				$id,
				$name,
				intval(substr($line, 47))
			);
		}
		return $obj;
	}
}


class GitBlob extends GitObject {
	#static public $defaultMimeType = 'application/octet-stream';
	static public $defaultMimeType = 'text/plain';
	
	function __set($name, $value) {
		if ($name == 'data')
			$this->_data = $value;
	}
	
	function __get($k) {
		$v = parent::__get($k);
		if ($v !== null)
			return $v;
		
		if ($k == 'data') {
			if ($this->_data === null)
				$this->_data = $this->repo->exec("cat-file blob {$this->id}");
			return $this->_data;
		}
		elseif ($k == 'mimeType') {
			if ($this->_mime_type === null) {
				if ($this->name)
					$this->_mime_type = mime_type($this->name);
				if (!$this->_mime_type)
					$this->_mime_type = self::$defaultMimeType;
			}
			return $this->_mime_type;
		}
		return null;
	}
}


class GitTree extends GitObject {
}


# xxx todo should be a subclass of GitObject but GitObject is 
#          bloated -- should move most members to subclasses.
class GitCommit {
	public $id;
	public $tree;
	public $authorEmail;
	public $authorName;
	public $authorDate; # UTC timestamp
	public $comitterEmail;
	public $comitterName;
	public $comitterDate; # UTC timestamp
	public $message;
	public $files; # array example: array(GitPatch::CREATE => array('file1', 'file3'), GitPatch::DELETE => array('file2'))
	public $previousFiles; # available for GitPatch::RENAME and COPY. array('file1', 'file2', ..)
	
	static public $fields = array(
		'id','tree',
		'authorEmail','authorName','authorDate',
		'comitterEmail','comitterName', 'comitterDate',
		'message');
	
	static public $logFormat = '%H%n%T%n%ae%n%an%n%ai%n%ce%n%cn%n%ci%n%s%x00';
	
	static function find($repo, $kwargs=null) {
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
		$out = $repo->exec($cmd);
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
					$c->$field = strtotime(substr($out, $a, $b-$a));
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
				$t = $line[0]{0};
				$name = $line[1];
				$previousName = null;
				
				# R|C have two names wherether the last is the new name
				if ($t === GitPatch::RENAME or $t === GitPatch::COPY) {
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
				if (isset($repo->objectCacheByName[$name])) {
					$obj = $repo->objectCacheByName[$name];
					if ($obj->_commit === null)
						$obj->_commit = $c;
				}
				
				# update existing
				# todo: make it work with $rcron -- currently relies on a cronol. sequence. Idea: post-process (slow but robust)
				if (!$rcron) {
					if ($t === GitPatch::CREATE or $t === GitPatch::COPY)
						$existing[$name] = $c;
					elseif ($t === GitPatch::DELETE and isset($existing[$name]))
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
  				  if ($ntoc !== null and isset($ntoc[$previousName])) {
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


class GitRepository {
	public $gitdir;
	public $gitQueryCount;
	public $objectCacheById;
	public $objectCacheByName;
	
	function __construct($gitdir) {
		$this->gitdir = $gitdir;
		$this->gitQueryCount = 0;
		$this->objectCacheById = array();
		$this->objectCacheByName = array();
	}
	
	function exec($cmd, $input=null) {
		# build cmd
		$cmd = "GIT_DIR='{$this->gitdir}' git $cmd";
		#var_dump($cmd);
		# start process
		$ps = proc_open2($cmd, null, $_ENV);
		$this->gitQueryCount++;
		if (!$ps)
			return null;
		# stdin
		if ($input)
			fwrite($ps['pipes'][0], $input);
		fclose($ps['pipes'][0]);
		# stdout
		$output = stream_get_contents($ps['pipes'][1]);
		fclose($ps['pipes'][1]);
		# stderr
		$errors = stream_get_contents($ps['pipes'][2]);
		fclose($ps['pipes'][2]);
		# wait
		$status = proc_close($ps['handle']);
		# check for errors
		if ($status != 0) {
			if (strpos($errors, 'Not a git repository') !== false)
				throw new GitUninitializedRepoError($errors);
			else
				throw new GitError($errors);
		}
		return $output;
	}
	
	function getCachedObjectById($id) {
		if (isset($this->objectCacheById[$id]))
			return $this->objectCacheById[$id];
		return null;
	}
	
	function getCachedObjectByName($name) {
		if (isset($this->objectCacheByName[$name]))
			return $this->objectCacheByName[$name];
		return null;
	}
	
	function cacheObject($obj) {
		if ($obj->id !== null)
			$this->objectCacheById[$obj->id] = $obj;
		if ($obj->name !== null)
			$this->objectCacheByName[$obj->name] = $obj;
	}
	
	function getObject($object, $name=null) {
		if (git_is_objid($object) && ($obj = $this->getCachedObjectById($object)))
			return $obj;
		$out = $this->exec("cat-file --batch-check", "$object");
		return GitObject::fromObjectTypeSizeLine($this, $out, $name);
	}
	
	/** @param objects array( [ array("object"[, "name"]) .. ] ) */
	function getObjects($objects) {
		$ids = array();
		$names = array();
		$objs = array();
		
		foreach ($objects as $v) {
			if (git_is_objid($v[0]) && ($obj = $this->getCachedObjectById($v[0])) ) {
				$objs[$obj->name] = $obj;
			}
			elseif ($v[1] !== null && isset($this->objectCacheByName[$v[1]])) {
				$obj = $this->objectCacheByName[$v[1]];
				$objs[$obj->name] = $obj;
			}
			else {
				$ids[] = $v[0];
				$names[] = isset($v[1]) ? $v[1] : null;
			}
		}
		
		if (empty($ids))
			return $objs;
		
		$out = $this->exec("cat-file --batch-check", implode("\n", $ids));
		foreach (explode("\n", $out) as $i => $line) {
			if ($line) {
				$obj = GitObject::fromObjectTypeSizeLine($this, $line, $names[$i]);
				$objs[$obj->name] = $obj;
			}
		}
		return $objs;
	}
	
	function getObjectByName($name, $treeish='HEAD') {
		if (isset($this->objectCacheByName[$name]))
			return $this->objectCacheByName[$name];
		return $this->getObject("$treeish:$name", $name);
	}
	
	function getObjectsByName($names, $treeish='HEAD') {
		$objects = array();
		foreach ($names as $name)
			$objects[] = array("$treeish:$name", $name);
		return $this->getObjects($objects);
	}
	
	function loadCommitsForObjects($objects=null, $kwargs=null) {
		$objs = array();
		$names = array();
		
		foreach ($objects as $obj)
			if ($obj->_commit === null)
				$names[] = $obj->name;
		
		if (!$names)
			return array();
		
		if (!$kwargs)
			$kwargs = array();
		$kwargs['names'] = $names;
		$kwargs['limit'] = count($names)*100;
		
		return GitCommit::find($this, $kwargs);
	}
	
	function loadBlobs($blobobjs) {
		$ids = array();
		$objs = array();
		foreach ($blobobjs as $obj) {
			$ids[] = $obj->id;
			$objs[$obj->id] = $obj;
		}
		
		$out = $this->exec("cat-file --batch", implode("\n", $ids));
		$p = 0;
		
		foreach ($objs as $obj) {
			# <sha1> SP <type> SP <size> LF
			# <contents> LF
			$hend = strpos($out, "\n", $p);
			$h = explode(' ', substr($out, $p, $hend-$p));
			#var_dump($h);
			
			$missing = ($h[1] == 'missing');
			$size = 0;
			$data = null;
			$dstart = $hend + 1;
			
			if (!$missing) {
				$size = intval($h[2]);
				$obj->_data = substr($out, $dstart, $size);
				if ($obj->size == -1)
					$obj->size = $size;
			}
			else {
				$obj->_data = '[git error: missing '.$obj->id.' '.var_export($obj->name,1).']';
			}
			
			$p = $dstart + $size + 1;
		}
	}
	
	function loadCommits() {
		return $this->loadCommitsForObjects($this->objectCacheById);
	}
	
	# loads any lazy data and info
	function batchLoadPending() {
		if (!$this->objectCacheById)
			return;
		
		$load_blobs = array();
		
		foreach ($this->objectCacheById as $obj) {
			if ( ($obj instanceof GitBlob) and ($obj->_data === null) ) {
				$load_blobs[] = $obj;
			}
		}
		
		$this->loadBlobs($load_blobs);
		
		# We could preload all commits here, but as it's expensive and not always 
		# needed, we currently don't.
		#$this->loadCommits();
	}
	
	/** Return array of GitObjects keyed by object name */
	function ls($names=null, $recursive=true, $treeish='HEAD') {
		$r = $recursive ? '-r' : '';
		$names = $names ? implode(' ', array_map('escapeshellarg', $names)) : '';
		$out = $this->exec("ls-tree -l -t $r '$treeish' $names");
		$out = explode("\n", $out);
		#var_dump($out);
		$objects = array();
		foreach ($out as $line) {
			if (!$line)
				continue;
			$obj = GitObject::fromLsTreeLine($this, $line);
			$objects[$obj->name] = $obj;
		}
		return $objects;
	}
	
	/*
	ls commits
	git rev-list --max-count=10 --pretty='format:%ci %H %T %ae %d' HEAD
	*/
	
	/**
	 * @param extra Extra arguments. See man git-ls-file
	 */
	function lsFile($paths='', $treeish='HEAD', $extra='') {
		$out = $this->exec("ls-files --stage --full-name -t --with-tree='$treeish' $extra $paths");
		$out = explode("\n", $out);
		$objects = array();
		foreach ($out as $line) {
			if (!$line)
				continue;
			$objects[] = GitObject::fromLsFilesLine($this, $line);
		}
		return $objects;
	}
	
	function init($shared=true, $bare=false) {
		$mkdirmode = 0755;
		if ($shared) {
			$shared = 'true';
			$mkdirmode = 0775;
		}
		else
			$shared = 'false';
		$cmd = "init --quiet --shared=$shared";
		if ($bare)
			$cmd .= " --bare";
		if (!is_dir($this->gitdir) && !mkdir($this->gitdir, $mkdirmode, true))
			return false;
		$this->exec($cmd);
		
		if (!$bare) {
			$dirname = dirname($this->gitdir);
			mkdir("$dirname/content/posts", $mkdirmode, true);
			mkdir("$dirname/content/pages", $mkdirmode);
		}
		
		$skeleton = dirname(realpath(__FILE__)).'/skeleton';
		copy("$skeleton/hooks/post-commit", "{$this->gitdir}/hooks/post-commit");
		
		return true;
	}
}


##########################################

# Fore details of format, see section "GENERATING PATCHES WITH -P" in man git-diff

class GitPatch {
	const EDIT_IN_PLACE = 'M';
	const COPY = 'C';
	const RENAME = 'R';
	const CREATE = 'A';
	const DELETE = 'D';
	
	public $action;
	public $lines;
	public $prevobj;
	public $prevname;
	public $currobj;
	public $currname;
	public $delta;
	public $meta;
	public $mode;
	
	function __construct($action) {
		$this->action = $action;
		$this->lines = array();
		$this->meta = array();
	}

	static function parse(&$udiff) {
		$patches = array();
		$currpatch = null;
		$passed_delta = false;
		
		while ( ($line = sreadline($p, $udiff)) !== null) {
			#var_dump($line);
			if (!$line)
				continue;
		
			$start3 = substr($line, 0, 3);
		
			# line 1 -- new set
			if ($start3 === 'diff') {
				# flush previous
				if ($currpatch !== null)
					$patches[] = $currpatch;
				# new
				$currpatch = new self(self::EDIT_IN_PLACE);
				$passed_delta = false;
			}
		
			# content
			elseif($passed_delta) {
				$currpatch->lines[] = $line;
			}
		
			# prev/curr name
			elseif ($start3 === '---' or $start3 === '+++') {
				$s = rtrim(substr($line, 4));
				if ($s === '/dev/null')
					$s = null;
				else
					$s = substr($s, 2); # remove trailing "a/"
				if ($start3 === '---')
					$currpatch->prevname = $s;
				else
					$currpatch->currname = $s;
			}
		
			# curr name
			elseif ($start3 === '@@ ') {
				$currpatch->delta = substr($line, 3, -3);
				$passed_delta = true;
			}
		
			# header lines
			# old  old mode <mode>
			# new  new mode <mode>
			#      new file mode <mode>
			# del  deleted file mode <mode>
			# cop  copy from <path>
			#      copy to <path>
			# ren  rename from <path>
			#      rename to <path>
			# sim  similarity index <number>
			# dis  dissimilarity index <number>
			# ind  index <hash>..<hash> <mode>
		
			# old mode <mode>
			elseif ($start3 === 'old') {
				$currpatch->meta['old_mode'] = intval(substr($line, 9));
			}
		
			# new mode <mode>
			# new file mode <mode>
			elseif ($start3 === 'new') {
				if (substr($line, 4, 4) === 'file') {
					$currpatch->mode = intval(substr($line, 14));
					$currpatch->meta['new_file_mode'] = $currpatch->mode;
					$currpatch->action = self::CREATE;
				}
				else {
					$currpatch->mode = intval(substr($line, 9));
					$currpatch->meta['new_mode'] = $currpatch->mode;
				}
			}
		
			# deleted file mode <mode>
			elseif ($start3 === 'del') {
				$currpatch->meta['deleted_file_mode'] = intval(substr($line, 18));
				$currpatch->action = self::DELETE;
			}
		
			# copy from <path>
			# copy to <path>
			elseif ($start3 === 'cop') {
				$x = strpos($line, ' ', 5);
				$currpatch->meta['copy_'.substr($line, 5, $x-5)] = rtrim(substr($line, $x+1));
				$currpatch->action = self::COPY;
			}
		
			# rename from <path>
			# rename to <path>
			elseif ($start3 === 'ren') {
				$x = strpos($line, ' ', 7);
				$currpatch->meta['rename_'.substr($line, 7, $x-7)] = rtrim(substr($line, $x+1));
				$currpatch->action = self::RENAME;
			}
		
			# similarity index <number>
			elseif ($start3 === 'sim') {
				$currpatch->meta['similarity_index'] = intval(substr($line, 17));
			}
		
			# dissimilarity index <number>
			elseif ($start3 === 'dis') {
				$currpatch->meta['dissimilarity_index'] = intval(substr($line, 20));
			}
		
			# index <hash>..<hash> <mode>
			elseif ($start3 === 'ind') {
				$v = explode(' ', substr($line, 6));
				$objs = explode('..', $v[0]);
				$currpatch->prevobj = $objs[0] === '0000000000000000000000000000000000000000' ? null : $objs[0];
				$currpatch->currobj = $objs[1] === '0000000000000000000000000000000000000000' ? null : $objs[1];
				if (isset($v[1]))
					$currpatch->mode = intval($v[1]);
			}
		
		}
		# flush
		if ($currpatch !== null)
			$patches[] = $currpatch;
	
		return $patches;
	}
}


##########################################


class GitContent {
	static public $filters = array();
	
	static function applyFilters($type, &$data) {
		if (!isset(self::$filters[$type]))
			return;
		
		$filters =& self::$filters[$type];
		
		foreach ($filters as $filter)
			if (!$filter($data))
				break;
	}
	
	static function parseData(&$data, $load_body=true) {
		$meta = array(
			'title' => null,
			'tags' => array(),
			'published' => 0
		);
		$body = '';
		
		$p = strpos($data, "\n\n");
		if ($p === false) {
			# no header
			$body = $load_body ? $data : 0;
		}
		else {
			$nmeta = gb_parse_headers(substr($data, 0, $p));
			
			# tags
			if (isset($nmeta['tags'])) {
				$nmeta['tags'] = preg_split('/[, ]+/', $nmeta['tags']);
			}
			
			# published
			# todo: translate these rules to english:
			#- Om headern saknas defaultar den till true.
      #- Om published värde inte kan parsas av strtotime (typ om man anger 
      #  2102-01-01 (out of bounds) eller "mosmaster") tolkas det som false.
      #- Om headern tolkas som true (el. implicit saknas) används datumet från
      #  den commit som skapade filen (genom create eller copy).
			if (isset($nmeta['published'])) {
				$lc = strtolower($nmeta['published']);
				if ($lc) {
					if ($lc{0} === 't' || $lc{0} === 'y')
						$nmeta['published'] = 0;
					elseif ($lc{0} === 'f' || $lc{0} === 'n')
						$nmeta['published'] = 2147483647; # MAX (Jan 19, 2038)
					else {
						$ts = strtotime($nmeta['published']);
						if ($ts === false or $ts === -1) # false in PHP >=5.1.0, -1 in PHP <5.1.0
						  $ts = 2147483647; # MAX (Jan 19, 2038)
						$nmeta['published'] = $ts;
					}
				}
			}
			
			$meta = array_merge($meta, $nmeta);
			$body = $load_body ? substr($data, $p+2) : $p+2;
		}
		
		if (is_string($body))
			self::applyFilters('body', $body);
		
		return array($meta, $body);
	}
	
	/** todo: comment */
	static function contentForName($repo, $name) {
		$path = "{$repo->gitdir}/info/gitblog/stage/$name";
		$data = @file_get_contents($path);
		if ($data === false)
			if (GitObjectIndex::assureIntegrity($repo))
				$data = file_get_contents($path);
		if ($data === false)
			return null;
		return unserialize($data);
	}
	
	/** todo: comment */
	static function postForSlug($repo, $slug, $type='html') {
		return self::contentForName($repo, "content/posts/$slug.$type");
	}
	
	static function publishedPosts($repo, $limit=25) {
		$records = GitObjectIndex::tail($repo, 'stage-published-posts.rchlog', $limit);
		$posts = array();
		
		foreach ($records as $rec) {
		  $name =& $rec['name'];
			$post = self::contentForName($repo, "content/posts/$name");
			$post->slug = filenoext($name);
			$posts[] = $post;
		}
		
		return $posts;
	}
}


function gb_html_filter(&$s) {
	$s = str_replace("\n", "<br/>\n", trim($s));
	return true;
}


GitContent::$filters['body'] = array('gb_html_filter');


class GitBlogPost {
	public $slug;
	public $object;
	public $_meta;
	public $_body_offset;
	
	function __construct($slug, $object) {
		$this->slug = $slug;
		$this->object = $object;
		$this->_meta = null;
	}
	
	function _parseMeta() {
		$data = $this->object->data;
		$v = GitContent::parseData($data, false);
		$this->_meta = $v[0];
		$this->_body_offset = $v[1];
	}
	
	function __get($k) {
		if ($this->_meta === null)
			$this->_parseMeta();
		
		if ($k === 'meta') {
			return $this->_meta;
		}
		elseif ($k === 'id') {
			return $this->object->id;
		}
		elseif ($k == 'date') {
			$c = $this->object->commit;
			return $c ? $c->comitterDate : null;
		}
		elseif ($k == 'title' or $k == 'tags') {
			return $this->_meta[$k];
		}
		elseif ($k == 'datePublished') {
			return $this->_datePublished;
		}
		elseif ($k == 'dateModified') {
			if ($this->_dateModified === null) {
				$this->_dateModified = filemtime( $this->object->repo->gitdir.'/objects/'
					.substr($this->object->id, 0, 2).'/'.substr($this->object->id, 2) );
			}
			return $this->_dateModified;
		}
		elseif ($k == 'body') {
			$s = substr($this->object->data, $this->_body_offset); # copy
			self::applyContentFilters($s);
			return $s;
		}
		elseif (in_array($k, GitCommit::$fields)) {
			$c = $this->object->commit;
			return $c ? $c->$k : null;
		}
		return null;
	}
	
	static function objNameToSlug($name) {
		return substr($name, 6, -strlen(fileext($name))-1);
	}
	
	static function findBySlug($repo, $slug) {
		$slug = trim($slug, '/');
		$name = 'posts/'.$slug.'.html';
		$object = $repo->getObjectByName($name);
		
		if (!$object)
			return null;
		
		return new self($slug, $object);
	}
	
	static function findByINT($repo, $ids_names_times) {
		$posts = array();
		$objects = $repo->getObjects($ids_names_times);
		$i = 0;
		
		foreach ($objects as $object) {
			$int =& $ids_names_times[$i];
			$post = new self(self::objNameToSlug($int[1]), $object);
			$post->_datePublished = $int[2];
			$posts[] = $post;
			$i++;
		}
		
		return $posts;
	}
	
	static function findPublished($repo, $limit=25) {
		$records = GitObjectIndex::tail($repo, 'published-posts', $limit);
		$ids_names_times = array();
		foreach ($records as $rec)
			$ids_names_times[] = array($rec['object'], $rec['name'], $rec['time']);
		return self::findByINT($repo, $ids_names_times);
	}
}

##########################################


class GitObjectIndex {
	const HEAD_SIZE = 109; # must not be changed
	const NAME_SIZE = 200; # might be changed but will break compat.
	const STAGE_DIRMODE = 0775;
	
	static function encodeRec($timestamp, $commit, $object, $name='') {
		# size in bytes = HEAD_SIZE + NAME_SIZE
		# <timestamp 25> SP <commit 40> SP <object 40> SP <name null-padded NAME_SIZE> LF
		$namelen = strlen($name);
		if ($namelen < self::NAME_SIZE)
			$name .= str_repeat("\0", self::NAME_SIZE-$namelen);
		if ($namelen > self::NAME_SIZE)
			$name = substr($name, 0, self::NAME_SIZE);
		return date('c', $timestamp)." $commit $object $name\n";
	}
	
	static function decodeRec($line) {
		$rec = explode(' ', $line, 4);
		$rec = array(
			'time' => strtotime($rec[0]),
			'commit' => $rec[1],
			'object' => $rec[2],
			'name' => rtrim($rec[3], "\0"),
		);
		return $rec;
	}
	
	# fast: append patches. Should be an array of GitPatch objects.
	static function appendPatches($repo, $indexname, &$patches) {
		$obj_created_log = fopen("{$repo->gitdir}/info/gitblog/index/{$indexname}", 'a');
		foreach ($patches as $patch) {
			# action: created
			if ($patch->action === GitPatch::CREATE) {
				# append to created log
				# log record is fixed at 108 bytes, including linebreak.
				fwrite($obj_created_log, 
					self::encodeRec($commit_timestamp, $commit, $patch->currobj, $patch->currname));
			}
		}
		fclose($obj_created_log);
	}
	
	/** write named index in an atomic manner */
	static function write($repo, $indexname, &$data, $mode) {
		$filename = "{$repo->gitdir}/info/gitblog/index/{$indexname}";
		if (gb_atomic_write($filename, $data)) {
			chmod($filename, $mode);
			return true;
		}
		return false;
	}
	
	/** Returns the number of bytes written, or FALSE on failure. */
	static function writeContentObjectToStage($stagedir, $object, $commits, &$meta, &$body) {
		$ccommit = null;
		$acommits = array();
		
		foreach ($commits as $i => $c) {
			$co = (object)array(
				'id' => $c->id,
				'author' => (object)array(
					'email' => $c->authorEmail,
					'name' => $c->authorName,
					'date' => $c->authorDate
				),
				'comitter' => (object)array(
					'email' => $c->comitterEmail,
					'name' => $c->comitterName,
					'date' => $c->comitterDate
				),
				'message' => $c->message
			);
			if ($ccommit === null) {
			  if (
			    (isset($c->files[GitPatch::CREATE]) and in_array($object->name, $c->files[GitPatch::CREATE], true))
			    or
			    (isset($c->files[GitPatch::RENAME]) and in_array($object->name[1], $c->files[GitPatch::RENAME], true))
			  ) {
				  $ccommit = $co;
			  }
			}
			$acommits[$c->comitterDate] = $co;
		}
		
		krsort($acommits, SORT_NUMERIC);
		$acommits = array_values($acommits);
		
		if ($ccommit === null and $acommits)
		  $ccommit = $acommits[0];
		
		$data = (object)array(
			'meta' => (object)$meta,
			'body' => $body,
			'id' => $object->id,
			'mimeType' => $object->mimeType,
			'commits' => array_values($acommits),
			'ccommit' => $ccommit
		);
		#var_export($data);
		$data = serialize($data);
		$dirname = dirname($object->name);
		if ($dirname !== '.') {
			$dirpath = "{$stagedir}/{$dirname}";
			if (!is_dir($dirpath))
				mkdir($dirpath, self::STAGE_DIRMODE, true);
		}
		
		$filename = "{$stagedir}/{$object->name}";
		$bw = file_put_contents($filename, $data, LOCK_EX);
		chmod($filename, 0664);
	}

	static function rebuild($repo) {
		$v = GitCommit::find($repo, array('sortrcron' => false, 'mapnamestoc' => true));
		$commits =& $v[0];
		$existing =& $v[1];
		$name_to_commits =& $v[2];
		
		# index buffers
		$stage = array(); # objects on stage, keyed by object id
		$stage_rcron = ''; # reverse-chronologically ordered list of published objects
		
		/*
		'099c2a87674fe302e9e74aef55059be4b62b5534' => 
			GitCommit(
				'id' => '099c2a87674fe302e9e74aef55059be4b62b5534',
				'tree' => '5bf5c90a97dcce70a88b3ca4b13ce34790671138',
				'authorEmail' => 'rasmus@notion.se',
				'authorName' => 'Rasmus Andersson',
				'authorDate' => 1245608462,
				'comitterEmail' => 'rasmus@notion.se',
				'comitterName' => 'Rasmus Andersson',
				'comitterDate' => 1245608462,
				'message' => 'added title',
				'files' => array (
					'M' => array (
						0 => 'posts/handle space.html',
					),
				),
		), ..
		*/
		
		# get file objects
		$files = $repo->ls();
		
		# load all file data. op needed for building gbstage
		$repo->loadBlobs($files);
		
		# gitblog stage directory
		$newstagedir = $stagedir = "{$repo->gitdir}/info/gitblog/stage.new";
		mkdir($newstagedir, self::STAGE_DIRMODE);
		
		# build map of name => array(commit, .. )
		/*$name_to_commits = array();
		foreach ($commits as $c) {
			foreach ($c->files as $t => $cfiles) {
				foreach ($cfiles as $name) {
				  if ($t === GitPatch::RENAME or $t === GitPatch::COPY) {
				    foreach ($name as $n) {
    					if (!isset($name_to_commits[$n]))
    						$name_to_commits[$n] = array($c);
    					else
    						$name_to_commits[$n][] = $c;
  					}
					}
					else {
  					if (!isset($name_to_commits[$name]))
  						$name_to_commits[$name] = array($c);
  					else
  						$name_to_commits[$name][] = $c;
					}
				}
			}
		}*/
		
		# build indexes
		foreach ($existing as $name => $c) {
			assert(isset($files[$name]));
			$file =& $files[$name];
			$published = $c->comitterDate;
			
			# stage-rcron
			$stage_rcron .= self::encodeRec($c->comitterDate, $c->id, $file->id, $name);
			
			# content
			if (substr($name, 0, 8) === 'content/') {
				$data = $file->data;
				$mb = GitContent::parseData($data);
				$meta =& $mb[0];
				$body =& $mb[1];
				$published = $meta['published'];
				if ($published === 0) {
					$published = $c->comitterDate;
					$meta['published'] = $published;
				}
				
				self::writeContentObjectToStage($newstagedir, $file, $name_to_commits[$name], $meta, $body);
			}
			
			# stage
			$stage[$file->id] = array($published, $c->id, $file->id, $name);
		}
		
		# activate gbstage
		if (!self::activateStage($repo, $newstagedir))
			exec("rm -rf ".escapeshellarg($newstagedir));
		
		# write indexes
		$stagedat = serialize($stage);
		self::write($repo, 'stage.phpser', $stagedat, 0664);
		self::write($repo, 'stage.rchlog', $stage_rcron, 0664);
		
		self::buildPublishedPostsRCHLog($repo, $stage);
		
		return $commits;
	}
	
	static function buildPublishedPostsRCHLog($repo, &$stage) {
		# build published posts rchlog
		# reverse-chronologically ordered list of published content/posts/**
		$data = '';
		$now = time()+300; # 5 minutes granularity
		$sorted = array();
		
		$i = 0;
		foreach ($stage as $o)
			if ( (substr($o[3], 0, 14) === 'content/posts/') and ($o[0] <= $now) )
				$sorted[strval($o[0]).strval($i++)] = $o;
		
		ksort($sorted, SORT_NUMERIC);
		
		foreach ($sorted as $o)
			$data .= self::encodeRec($o[0], $o[1], $o[2], substr($o[3], 14));
		
		self::write($repo, 'stage-published-posts.rchlog', $data, 0664);
	}
	
	static function activateStage($repo, $newstagedir) {
		$stagedir = "{$repo->gitdir}/info/gitblog/stage";
		$intermediatestagedir = "{$stagedir}.old";
		
		if (!file_exists($stagedir))
			return rename($newstagedir, $stagedir);
		
		if (rename($stagedir, $intermediatestagedir)) {
			if (rename($newstagedir, $stagedir)) {
				exec("rm -rf ".escapeshellarg($intermediatestagedir));
				return true;
			}
			else {
				if (rename($intermediatestagedir, $stagedir))
					trigger_error("failed to rename $newstagedir => $stagedir -- old stage still active");
				else
					trigger_error("failed to rename $newstagedir => $stagedir -- CRITICAL: no active stage!");
			}
		}
		else {
			trigger_error("failed to rename $stagedir => $intermediatestagedir -- old stage still active");
		}
		return false;
	}
	
	static function tail($repo, $indexname, $limit=25) {
		$records = array();
		$recsize = self::NAME_SIZE + self::HEAD_SIZE;
		$path = "{$repo->gitdir}/info/gitblog/index/{$indexname}";
		$readsize = $recsize * ($limit+1);
		
		# read
		$f = fopen($path, 'r');
		fseek($f, -$readsize, SEEK_END);
		$data = fread($f, $readsize);
		fclose($f);
	
		# special case: no records
		if (strlen($data) < $recsize)
			return $records;
	
		# align (protection against concurrency issues)
		if (strlen($data) > $recsize) {
			$p = strpos($data, "\n");
			if ($p === false) {
				trigger_error("$indexname log is corrupt");
				return $records;
			}
			if ($p < ($recsize-1))
				$data = substr($data, $p+1);
		}
	
		# parse records
		$p = 0;
		while ( ($line = sreadline($p, $data)) !== null)
			$records[] = self::decodeRec($line);
	
		rsort($records);
		return $records;
	}
	
	# returns true if the integrity was compromised and has been repaired.
	static function assureIntegrity($repo) {
		$path = "{$repo->gitdir}/info/gitblog";
		if (is_dir($path))
			return false;
		# no gb meta dir!
		# do we even have a repo?
		$repo_exists = is_dir($repo->gitdir);
		if (!$repo_exists)
			if (!$repo->init())
				return false;
		self::rebuild($repo);
	}
}


##########################################

$debug_time_started = microtime(true);
$repo = new GitRepository(dirname(GITBLOG_DIR)."/db/.git");

?>
