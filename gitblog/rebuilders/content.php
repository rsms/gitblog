<?
# content rebuilders, for objects stored in the content directory.

class GBContentRebuilder extends GBRebuilder {
	/** Batch-reload objects */
	function reloadObjects(&$objects) {
		$names = array();
		$ids = array();
		
		# Demux
		foreach ($objects as $id => $obj) {
			$names[] =& $obj->name;
			$ids[] = $id;
		}
		
		# Load commits
		$commits = GitCommit::find($this->gb, array(
			'names' => $names,
			'mapnamestoc' => true));
		$commitsbyname =& $commits[2];
		
		# Load blobs
		$out = $this->gb->exec("cat-file --batch", implode("\n", $ids));
		$p = 0;
		$numobjects = count($objects);
		
		# Parse object blobs
		for ($i=0; $i<$numobjects; $i++) {
			# <id> SP <type> SP <size> LF
			# <contents> LF
			$hend = strpos($out, "\n", $p);
			$h = explode(' ', substr($out, $p, $hend-$p));
			#var_dump($h);
			
			$missing = ($h[1] === 'missing');
			$size = 0;
			$data = null;
			$dstart = $hend + 1;
			
			if (!$missing) {
				$obj = $objects[$h[0]];
				$size = intval($h[2]);
				$data = substr($out, $dstart, $size);
				$obj->reload($data, isset($commitsbyname[$obj->name]) ? $commitsbyname[$obj->name] : array());
			}
			else {
				trigger_error('missing blob '.$obj->id.' '.var_export($obj->name,1).' in repo stage');
			}
			
			$p = $dstart + $size + 1;
		}
	}
	
	function _onObject(&$obj, $cls, &$name, &$id, &$slug) {
		# check for missing or outdated cache
		if ( $this->forceFullRebuild
			or ($obj === false) 
			or (!($obj instanceof $cls)) 
			or ($obj->id != $id) )
		{
			# defer loading of uncached blobs until call to finalize()
			$obj = new $cls($name, $id, $slug);
			GBContentFinalizer::$dirtyObjects[$id] = $obj;
		}
		
		# append to maps
		GBContentFinalizer::$objects[] =& $obj;
		
		#echo "$name > ".$obj->cachename()."\n";
	}
}


class GBPostsRebuilder extends GBContentRebuilder {
	static public $posts = array();
	
	/**
	 * content/posts/2008-08-29-reading-a-book.html
	 *  date: 2008-08-29 (timestamp)
	 *  slug: "reading-a-book"
	 *  fnext: "html"
	 */
	function parsePostName(&$name, &$date, &$slug, &$fnext) {
		$date = strtotime(str_replace(array('.','_','/'), '-', substr($name, 14, 10)));
		$lastdot = strrpos($name, '.', strrpos($name, '/'));
		if ($lastdot > 25) {
			$slug = substr($name, 25, $lastdot-25);
			$fnext = substr($name, $lastdot+1);
		}
		else {
			$slug = substr($name, 25);
			$fnext = null;
		}
	}
		
	/** Handle object */
	function onObject(&$name, &$id) {
		if (substr($name, 0, 14) !== 'content/posts/')
			return false;
		
		$this->parsePostName($name, $date, $slug, $fnext);
		
		# handle missing slug. content/posts/2009-01-22 => post
		if (!$slug)
			$slug = 'post';
		
		# date missing means malformed pathname
		if ($date === false) {
			trigger_error("malformed post '$name' missing date prefix -- skipping");
			return false;
		}
		
		# read, put into reload if needed, etc
		$obj = GBPost::getCached($this->cachebase, $date, $slug);
		$this->_onObject($obj, 'GBPost', $name, $id, $slug);
		if ($obj->published === false and $date !== false)
			$obj->published = $date;
		self::$posts[] =& $obj;
		
		return true;
	}
}


class GBPagesRebuilder extends GBContentRebuilder {
	/** Handle object */
	function onObject(&$name, &$id) {
		if (substr($name, 0, 14) !== 'content/pages/')
			return false;
		
		$slug = gb_filenoext(substr($name, 14));
		
		# read, put into reload if needed, etc
		$obj = GBPage::getCached($this->cachebase, $slug);
		$this->_onObject($obj, 'GBPage', $name, $id, $slug);
	}
}


/**
 * This rebuilder is a compound of all content objects.
 * 
 * Reloading all objects and commits in one batch is much faster than repeating
 * those actions for every content class. Also, generating common indexes of
 * content objects is simpler this way.
 */
class GBContentFinalizer extends GBContentRebuilder {
	static public $objects;
	static public $dirtyObjects = array(); # [id => obj, ..]
	
	function __construct($gb, $forceFullRebuild=false) {
		parent::__construct($gb, $forceFullRebuild);
		self::$objects = array();
	}
	
	function finalize() {
		# (re)load queued objects
		if (self::$dirtyObjects) {
			$this->reloadObjects(self::$dirtyObjects);
			foreach (self::$dirtyObjects as $obj)
				$obj->writeCache($this->cachebase);
		}
		
		var_export(self::$objects); # xxx
		
		$this->_finalizePagedPosts();
	}
	
	# build posts pages
	function _finalizePagedPosts() {
		$pagesize = 5; # todo move to config
		$pages = array_chunk(array_reverse(GBPostsRebuilder::$posts), $pagesize);
		$numpages = count($pages);
		$dir = "{$this->cachebase}/content-paged-posts";
		
		if (!is_dir($dir))
			mkdir($dir, 0775, true);
		
		foreach ($pages as $pageno => $page) {
			$path = $dir.'/'.sprintf('%010d', $pageno);
			$need_rewrite = $this->forceFullRebuild or (!file_exists($path));
			
			# check if any objects on this page are dirty
			if (!$need_rewrite and GBContentFinalizer::$dirtyObjects) {
				foreach ($page as $post) {
					if (in_array($post, GBContentFinalizer::$dirtyObjects)) {
						$need_rewrite = true;
						break;
					}
				}
			}
			
			if ($need_rewrite) {
				$page = array('posts' => $page);
				if ($pageno < $numpages-1)
					$page['nextpage'] = $pageno+1;
				$data = serialize($page);
				gb_atomic_write($path, $data);
				#var_dump("wrote $path"); # xxx
			}
		}
	}
}


function init_rebuilder_content(&$rebuilders) {
	$rebuilders[] = 'GBPostsRebuilder';
	$rebuilders[] = 'GBPagesRebuilder';
	# this must be added after the other ones above
	$rebuilders[] = 'GBContentFinalizer';
}
?>