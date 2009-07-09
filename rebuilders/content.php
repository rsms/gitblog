<?
# content rebuilders, for objects stored in the content directory.

class GBContentRebuilder extends GBRebuilder {
	function deferReloadIfNeeded(&$obj, $cls, $name, $id, $arg3=null) {
		# check for missing or outdated cache
		if ( $this->forceFullRebuild
			or ($obj === false) 
			or (!($obj instanceof $cls)) 
			or ($obj->id != $id) )
		{
			# defer loading of uncached blobs until call to finalize()
			$obj = ($arg3 !== null) ? new $cls($name, $id, $arg3) : new $cls($name, $id);
			return true;
		}
		return false;
	}
	
	function _onObject($obj, $cls, $name, $id, $arg3=null) {
		if ($this->deferReloadIfNeeded($obj, $cls, $name, $id, $arg3))
			GBContentFinalizer::$dirtyObjects[$id] = $obj;
		GBContentFinalizer::$objects[] = $obj;
		return $obj;
	}
	
	function _onComment($name, $id, $cachenamePrefix) {
		$obj = GBComments::getCached($cachenamePrefix);
		if ($this->deferReloadIfNeeded($obj, 'GBComments', $name, $id, $cachenamePrefix))
			GBContentFinalizer::$dirtyComments[$id] = $obj;
		GBContentFinalizer::$comments[substr($name, 0, -9)] = $obj;
		return $obj;
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
	function parsePostName($name, &$date, &$slug, &$fnext) {
		$date = new GBDateTime(str_replace(array('.','_','/'), '-', substr($name, 14, 10)).'T00:00:00Z');
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
	function onObject($name, $id) {
		if (substr($name, 0, 14) !== 'content/posts/')
			return false;
		
		$this->parsePostName($name, $date, $slug, $fnext);
		
		# date missing means malformed pathname
		if ($date === false) {
			throw new UnexpectedValueException(
				'malformed post "'.$name.'" missing date prefix -- skipping');
			return false;
		}
		
		# handle missing slug. content/posts/2009-01-22 => post
		if (!$slug)
			$slug = 'post';
		
		# comment or post?
		if ($fnext === 'comments') {
			$obj = $this->_onComment($name, $id, GBPost::mkCachename($date, $slug));
		}
		else {
			$obj = $this->_onObject(GBPost::getCached($date, $slug), 'GBPost', $name, $id, $slug);
			self::$posts[] = $obj;
		}
		
		if ($obj->published === null)
			$obj->published = $date;
		
		return true;
	}
}

/** Sort GBContent objects on published, descending */
function gb_sortfunc_cobj_date_published_r(GBContent $a, GBContent $b) {
	return $b->published->time - $a->published->time;
}


class GBPagesRebuilder extends GBContentRebuilder {
	/** Handle object */
	function onObject($name, $id) {
		if (substr($name, 0, 14) !== 'content/pages/')
			return false;
		
		$slug = gb_fnsplit(substr($name, 14));
		$fnext = $slug[1];
		$slug = $slug[0];
		
		# comment or page?
		if ($fnext === 'comments')
			$obj = $this->_onComment($name, $id, GBPage::mkCachename($slug));
		else
			$obj = $this->_onObject(GBPage::getCached($slug), 'GBPage', $name, $id, $slug);
		
		return true;
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
	static public $objects;       # [GBContent, ..]
	static public $dirtyObjects;  # [id   => GBContent, ..]
	static public $comments;      # [name => GBComments, ..]
	static public $dirtyComments; # [id   => GBComments, ..]
	
	function __construct($forceFullRebuild=false) {
		parent::__construct($forceFullRebuild);
		self::$objects = array();
		self::$dirtyObjects = array();
		self::$comments = array();
		self::$dirtyComments = array();
	}
	
	/** Batch-reload objects */
	function reloadObjects($objects) {
		$names = array();
		$ids = array();
		
		# Demux
		foreach ($objects as $id => $obj) {
			$names[] = $obj->name;
			$ids[] = $id;
		}
		
		# Load commits
		$commits = GitCommit::find(array('names' => $names, 'mapnamestoc' => true));
		$commitsbyname = $commits[2];
		
		# Load blobs
		$out = GitBlog::exec("cat-file --batch", implode("\n", $ids));
		$p = 0;
		$numobjects = count($objects);
		
		# Parse object blobs
		for ($i=0; $i<$numobjects; $i++) {
			# <id> SP <type> SP <size> LF
			# <contents> LF
			$hend = strpos($out, "\n", $p);
			$h = explode(' ', substr($out, $p, $hend-$p));
			
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
	
	function reloadAndWriteCache($objects) {
		if ($objects) {
			$this->reloadObjects($objects);
			foreach ($objects as $obj) {
				gb::log(LOG_INFO, 'wrote %s', $obj->cachename());
				$obj->writeCache();
			}
		}
	}
	
	function finalize() {
		# check comments dependencies, marking rdeps dirty if needed
		$this->_checkCommentDeps();
		
		# (re)load dirty comments
		$this->reloadAndWriteCache(self::$dirtyComments);
		
		# (re)load dirty objects
		$this->reloadAndWriteCache(self::$dirtyObjects);
		
		# sort objects on published, desc. with a granularity of one second
		usort(GBPostsRebuilder::$posts, 'gb_sortfunc_cobj_date_published_r');
		
		# build indexes
		$this->_buildIndexes();
		
		# build posts pages
		$this->_buildPagedPosts();
		
		# garbage collect stage cache
		$this->_gcStageCache();
	}
	
	function _buildIndexes() {
		# build comments index
		
	}
	
	function _checkCommentDeps() {
		# map comments to dirty objects (this way, together with the loop below,
		# we map all dirty comments and dirty objects, leaving maping between clean ones)
		foreach (self::$comments as $id => $cobj) {
			$parentName = substr($cobj->name, 0, -9); # .comments
			$parentNameLen = strlen($parentName);
			foreach (self::$dirtyObjects as $parentObj)
				if (substr($parentObj->name, 0, $parentNameLen) === $parentName)
					$parentObj->comments = $cobj;
		}
		# assure GBExposedContent objects with dirty comments are added to dirtyObjects
		foreach (self::$dirtyComments as $id => $cobj) {
			$parentName = substr($cobj->name, 0, -9); # .comments
			$parentNameLen = strlen($parentName);
			foreach (self::$objects as $parentObj) {
				if (substr($parentObj->name, 0, $parentNameLen) === $parentName) {
					if (!in_array($parentObj, self::$dirtyObjects, true))
						self::$dirtyObjects[$parentObj->id] = $parentObj;
					$parentObj->comments = $cobj;
				}
			}
		}
	}
	
	function _buildPagedPosts() {
		$published_posts = array();
		$time_now = time();
		foreach (GBPostsRebuilder::$posts as $post)
			if ($post->draft === false && $post->published->time <= $time_now)
				$published_posts[] = $post->condensedVersion();
		$pages = array_chunk($published_posts, gb::$posts_pagesize);
		$numpages = count($pages);
		$dir = GB_SITE_DIR.'/.git/info/gitblog/content-paged-posts';
		$dirPrefixLen = strlen(GB_SITE_DIR.'/.git/info/gitblog/');
		
		if (!is_dir($dir)) {
			mkdir($dir, 0775, true);
			chmod($dir, 0775);
			chmod(dirname($dir), 0775);
		}
		
		# no content at all? -- create empty page
		$is_empty = !$pages;
		if ($is_empty)
			$pages = array(array());
		
		foreach ($pages as $pageno => $page) {
			$path = $dir.'/'.sprintf('%011d', $pageno);
			$need_rewrite = $is_empty || $this->forceFullRebuild || (!file_exists($path));
			
			# check if any objects on this page are dirty
			if (!$need_rewrite && GBContentFinalizer::$dirtyObjects) {
				foreach ($page as $post) {
					if (isset(GBContentFinalizer::$dirtyObjects[$post->id])) {
						$need_rewrite = true;
						break;
					}
				}
			}
			
			if ($need_rewrite) {
				$page = (object)array(
					'posts' => $page,
					'nextpage' => -1,
					'prevpage' => $pageno-1,
					'numpages' => $numpages
				);
				if ($pageno < $numpages-1)
					$page->nextpage = $pageno+1;
				gb_atomic_write($path, serialize($page), 0664);
				gb::log(LOG_INFO, 'wrote %s', substr($path, $dirPrefixLen));
			}
		}
	}
	
	function _gcStageCache() {
		# Build cachenames
		$cachenames = array();
		foreach (self::$objects as $obj)
			$cachenames[$obj->cachename()] = 1;
		foreach (self::$comments as $obj)
			$cachenames[$obj->cachename()] = 1;
		
		# remove unused objects from stage cache (todo: this can be very expensive with much content)
		$prefix_len = strlen(GB_SITE_DIR.'/.git/info/gitblog/');
		$existing_paths = glob(GB_SITE_DIR.
			'/.git/info/gitblog/content/{posts/*/*,pages/{*,*/*,*/*/*,*/*/*/*,*/*/*/*/*,*/*/*/*/*/*}}',
			GLOB_BRACE|GLOB_NOSORT|GLOB_MARK);
		foreach ($existing_paths as $path) {
			if (substr($path, -1) === '/')
				continue;
			$cachename = substr($path, $prefix_len);
			if (!isset($cachenames[$cachename]))
				unlink($path);
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