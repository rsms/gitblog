<?
# content rebuilders, for objects stored in the content directory.

class GBContentRebuilder extends GBRebuilder {
	function _onObject(&$obj, $cls, $name, $id, $slug) {
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
		GBContentFinalizer::$objects[] = $obj;
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
		$date = strtotime(str_replace(array('.','_','/'), '-', substr($name, 14, 10)).' UTC');
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
		
		# handle missing slug. content/posts/2009-01-22 => post
		if (!$slug)
			$slug = 'post';
		
		# date missing means malformed pathname
		if ($date === false) {
			trigger_error("malformed post '$name' missing date prefix -- skipping");
			return false;
		}
		
		# read, put into reload if needed, etc
		$obj = GBPost::getCached($date, $slug);
		$this->_onObject($obj, 'GBPost', $name, $id, $slug);
		if ($obj->published === false and $date !== false)
			$obj->published = $date;
		self::$posts[] = $obj;
		
		return true;
	}
}

/** Sort GBContent objects on published, descending */
function gb_sortfunc_cobj_date_published_r(GBContent $a, GBContent $b) {
	return $b->published - $a->published;
}


class GBPagesRebuilder extends GBContentRebuilder {
	/** Handle object */
	function onObject($name, $id) {
		if (substr($name, 0, 14) !== 'content/pages/')
			return false;
		
		$slug = gb_filenoext(substr($name, 14));
		
		# read, put into reload if needed, etc
		$obj = GBPage::getCached($slug);
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
	static public $objects; # [obj, ..]
	static public $dirtyObjects = array(); # [id => obj, ..]
	
	function __construct($forceFullRebuild=false) {
		parent::__construct($forceFullRebuild);
		self::$objects = array();
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
	
	function finalize() {
		# (re)load dirty objects
		if (self::$dirtyObjects) {
			$this->reloadObjects(self::$dirtyObjects);
			foreach (self::$dirtyObjects as $obj)
				$obj->writeCache();
		}
		
		# sort objects on published, desc. with a granularity of one second
		usort(GBPostsRebuilder::$posts, 'gb_sortfunc_cobj_date_published_r');
		
		# build posts pages
		$this->_finalizePagedPosts();
		
		# garbage collect stage cache
		$this->_gcStageCache();
	}
	
	function _gcStageCache() {
		# List cachenames
		$cachenames = array();
		foreach (self::$objects as $obj)
			$cachenames[$obj->cachename()] = 1;
		
		# remove unused objects from stage cache (todo: this can be very expensive with much content)
		$prefix_len = strlen(gb::$repo.'/.git/info/gitblog/');
		$existing_paths = glob(gb::$repo.
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
	
	function _finalizePagedPosts() {
		$published_posts = array();
		$time_now = time();
		foreach (GBPostsRebuilder::$posts as $post)
			if ($post->published <= $time_now)
				$published_posts[] = $post;
		$pages = array_chunk($published_posts, gb::$posts_pagesize);
		$numpages = count($pages);
		$dir = gb::$repo."/.git/info/gitblog/content-paged-posts";
		
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
			$need_rewrite = $is_empty or $this->forceFullRebuild or (!file_exists($path));
			
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
				$page = (object)array(
					'posts' => $page,
					'nextpage' => -1,
					'prevpage' => $pageno-1,
					'numpages' => $numpages
				);
				if ($pageno < $numpages-1)
					$page->nextpage = $pageno+1;
				gb_atomic_write($path, serialize($page), 0664);
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