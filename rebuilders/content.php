<?php
# content rebuilders, for objects stored in the content directory.

class GBContentRebuilder extends GBRebuilder {
	function deferReloadIfNeeded(&$obj, $cls, $name, $id, $arg3=null) {
		# check for missing or outdated cache
		if ( $this->forceFullRebuild
			|| ($obj === false) 
			|| (!($obj instanceof $cls)) 
			|| ($obj->id != $id) )
		{
			# defer loading of uncached blobs until call to finalize()
			$obj = ($arg3 !== null) ? new $cls($name, $id, $arg3) : new $cls($name, $id);
			GBContentFinalizer::$newfound[$id] = $obj;
			return true;
		}
		return false;
	}
	
	function _onObject($obj, $cls, $name, $id, $arg3=null) {
		if (isset(GBContentFinalizer::$objects[$id])) {
			GBContentFinalizer::$duplicates[] = $obj;
			gb::log(LOG_WARNING, 'skipping duplicate post '.$name);
			return null;
		}
		if ($this->deferReloadIfNeeded($obj, $cls, $name, $id, $arg3)) {
			GBContentFinalizer::$dirtyObjects[$id] = $obj;
		}
		GBContentFinalizer::$objects[$id] = $obj;
		GBContentFinalizer::$objectsByName[gb_filenoext($obj->name)] = $obj;
		return $obj;
	}
	
	function _onComment($name, $id, $cachenamePrefix) {
		$obj = GBComments::find($cachenamePrefix);
		if ($this->deferReloadIfNeeded($obj, 'GBComments', $name, $id, $cachenamePrefix))
			GBContentFinalizer::$dirtyComments[$id] = $obj;
		GBContentFinalizer::$comments[substr($name, 0, -9)] = $obj;
		return $obj;
	}
}


class GBPostsRebuilder extends GBContentRebuilder {
	static public $posts = array();
		
	/** Handle object */
	function onObject($name, $id) {
		if (substr($name, 0, 14) !== 'content/posts/')
			return false;
		
		GBPost::parsePathspec($name, $date, $slug, $fnext);
		
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
			$obj = $this->_onObject(GBPost::findByDateAndSlug($date, $slug), 'GBPost', $name, $id, $slug);
			if (!$obj)
				return false;
			self::$posts[] = $obj;
		}
		
		if ($obj->published === null)
			$obj->published = $date;
		return true;
	}
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
			$obj = $this->_onObject(GBPage::find($slug), 'GBPage', $name, $id, $slug);
		
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
	static public $objects;       # [id   => GBExposedContent, ..]
	static public $dirtyObjects;  # [id   => GBExposedContent, ..]
	static public $comments;      # [name => GBComments, ..]
	static public $dirtyComments; # [id   => GBComments, ..]
	
	static public $newfound;      # [id   => GBExposedContent, ..]
	static public $duplicates;    # [id   => GBExposedContent, ..]
	
	static public $objectsByName;
	
	static public $objectIndexRebuilders = array();
	static public $commentIndexRebuilders = array();
	
	function __construct($forceFullRebuild=false) {
		parent::__construct($forceFullRebuild);
		self::$objects = array();
		self::$dirtyObjects = array();
		self::$comments = array();
		self::$dirtyComments = array();
		self::$newfound = array();
		self::$duplicates = array();
		self::$objectsByName = array();
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
		$out = git::exec("cat-file --batch", implode("\n", $ids));
		$p = 0;
		$numobjects = count($objects);
		
		# Parse object blobs
		for ($i=0; $i<$numobjects; $i++) {
			# <id> SP <type> SP <size> LF
			# <contents> LF
			$hend = strpos($out, "\n", $p);
			$h = explode(' ', substr($out, $p, $hend-$p));
			
			$size = 0;
			$data = null;
			$dstart = $hend + 1;
			
			if ($h[1] === 'missing')
				throw new UnexpectedValueException(
					'missing blob '.$obj->id.' '.var_export($obj->name,1).' in repo stage');
			
			$obj = $objects[$h[0]];
			$size = intval($h[2]);
			$data = substr($out, $dstart, $size);
			$obj->reload($data, isset($commitsbyname[$obj->name]) ? $commitsbyname[$obj->name] : array());
			
			$p = $dstart + $size + 1;
		}
	}
	
	function reloadAndWriteCache($objects) {
		if ($objects) {
			$this->reloadObjects($objects);
			foreach ($objects as $obj) {
				$obj->writeCache();
				gb::log(LOG_NOTICE, 'wrote %s', $obj->cachename());
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
		
		# garbage collect stage cache
		$gc_count = $this->gcStageCache();
		
		# build posts pages
		$this->buildPagedPosts($gc_count);
		
		# build indexes (sub-rebuilders)
		usort(self::$objects, 'gb_sortfunc_cobj_date_published_r');
		gb::log('Running object index rebuilders...');
		$this->runIndexRebuilders(self::$objectIndexRebuilders, self::$objects);
		gb::log('Running comment index rebuilders...');
		$this->runIndexRebuilders(self::$commentIndexRebuilders, self::$comments);
	}
	
	function _checkCommentDeps() {
		# map comments to dirty objects (this way, together with the loop below,
		# we map all dirty comments and dirty objects, leaving maping between clean ones)
		foreach (self::$comments as $id => $cobj) {
			$parentName = substr($cobj->name, 0, -9); # .comments
			$parentNameLen = strlen($parentName);
			foreach (self::$dirtyObjects as $parentObj)
				if (gb_filenoext($parentObj->name) === $parentName)
					$parentObj->comments = $cobj;
		}
		# assure GBExposedContent objects with dirty comments are added to dirtyObjects
		foreach (self::$dirtyComments as $id => $cobj) {
			$parentName = substr($cobj->name, 0, -9); # .comments
			$parentNameLen = strlen($parentName);
			foreach (self::$objects as $parentObj) {
				if (gb_filenoext($parentObj->name) === $parentName) {
					if (!in_array($parentObj, self::$dirtyObjects, true))
						self::$dirtyObjects[$parentObj->id] = $parentObj;
					$parentObj->comments = $cobj;
				}
			}
		}
	}
	
	# only collects GBExposedContent stuff + comments
	function gcStageCache() {
		# Build cachenames
		$cachenames = array();
		foreach (self::$objects as $obj)
			$cachenames[$obj->cachename()] = 1;
		foreach (self::$comments as $obj)
			$cachenames[$obj->cachename()] = 1;
		
		# count of collected objects
		$count = 0;
		
		# remove unused objects from stage cache (todo: this can be very expensive with much content)
		$prefix_len = strlen(gb::$site_dir.'/.git/info/gitblog/');
		$existing_paths = glob(gb::$site_dir.
			'/.git/info/gitblog/content/{posts/*/*,pages/{*,*/*,*/*/*,*/*/*/*,*/*/*/*/*,*/*/*/*/*/*}}',
			GLOB_BRACE|GLOB_NOSORT|GLOB_MARK);
		foreach ($existing_paths as $path) {
			if (substr($path, -1) === '/')
				continue;
			$cachename = substr($path, $prefix_len);
			if (!isset($cachenames[$cachename])) {
				gb::log(LOG_NOTICE, 'removing unused cache .git/info/gitblog/%s', $cachename);
				unlink($path);
				$count++;
			}
		}
		
		return $count;
	}
	
	function buildPagedPosts($gc_count) {
		$published_posts = array();
		$time_now = time();
		
		foreach (GBPostsRebuilder::$posts as $post)
			if ($post->draft === false)
				$published_posts[] = $post->condensedVersion();
		
		$numtotal = count($published_posts);
		$pages = array_chunk($published_posts, gb::$posts_pagesize);
		$numpages = count($pages);
		$dir = gb::$site_dir.'/.git/info/gitblog/content-paged-posts';
		$dirPrefixLen = strlen(gb::$site_dir.'/.git/info/gitblog/');
		$force_rebuild = $this->forceFullRebuild || $gc_count > 0;
		$newfound_detected = false; # see below
		
		if (!is_dir($dir)) {
			mkdir($dir, 0775, true);
			chmod($dir, 0775);
			chmod(dirname($dir), 0775);
		}
		
		# no content at all? -- create empty page
		$is_empty = !$pages;
		if ($is_empty) {
			$force_rebuild = true;
			$pages = array(array());
		}
		
		foreach ($pages as $pageno => $page) {
			$path = $dir.'/'.sprintf('%011d', $pageno);
			$need_rewrite = $newfound_detected === true
				|| $force_rebuild === true 
				|| file_exists($path) === false;
			
			# see if any object in this page is newfound
			if ($need_rewrite === false) {
				foreach ($page as $obj) {
					if (isset(self::$newfound[$obj->id])) {
						gb::log(LOG_NOTICE, 'newfound object: '.$obj->cachename());
						$need_rewrite = true;
						# when this is set to true, all following pages will need to be rebuilt (and will).
						$newfound_detected = true;
						break;
					}
				}
			}
			
			# check if any objects on this page are dirty
			if (!$need_rewrite && GBContentFinalizer::$dirtyObjects) {
				foreach ($page as $post) {
					if (isset(GBContentFinalizer::$dirtyObjects[$post->id])) {
						$need_rewrite = true;
						gb::log(LOG_INFO, 'dirty object: '.$obj->cachename());
						break;
					}
				}
			}
			if ($need_rewrite) {
				$page = new GBPagedObjects($page, -1, $pageno-1, $numpages, $numtotal);
				if ($pageno < $numpages-1)
					$page->nextpage = $pageno+1;
				file_put_contents($path, serialize($page), LOCK_EX);
				chmod($path, 0664);
				gb::log(LOG_NOTICE, 'wrote paged posts page %d of %d to %s',
					$pageno+1, $numpages, substr($path, $dirPrefixLen));
			}
		}
	}
	
	function runIndexRebuilders($rebuilderClasses, $objects) {
		# setup indexes
		$rebuilders = array();
		foreach ($rebuilderClasses as $cls)
			$rebuilders[] = new $cls($this->forceFullRebuild);
		
		if (!$rebuilders)
			return;
		gb::log('Running %d index rebuilders on %d objects',count($rebuilders),count($objects));
		# iterate over all objects
		foreach ($objects as $obj)
			foreach ($rebuilders as $ir)
				$ir->onObject($obj);
		gb::log('Finalizing %d index rebuilders...',count($rebuilders));
		
		# let index rebuilders finalize
		foreach ($rebuilders as $ir)
			$ir->finalize();
		gb::log('Ran %d index rebuilders',count($rebuilders));
	}
}

class GBContentIndexRebuilder {
	public $forceFullRebuild;
	public $name;
	public $index;
	public $checksum;
	
	function __construct($name, $forceFullRebuild=false) {
		$this->forceFullRebuild = $forceFullRebuild;
		$this->name = $name;
		$data = @file_get_contents($this->path());
		$this->index = $data !== false ? @unserialize($data) : false;
		if ($this->index !== false)
			$this->checksum = sha1($data);
		$this->index = array();
	}
	
	function cachename() {
		return gb::index_cachename($this->name);
	}
	
	function path() {
		return gb::index_path($this->name);
	}
	
	function sync($force=false) {
		if ($this->index === null)
			return false;
		$data = $this->serialize();
		if ($this->checksum !== null && $this->checksum === sha1($data) && $force === false)
			return false; # no changes
		$bw = file_put_contents($this->path(), $data, LOCK_EX);
		chmod($this->path(), 0664);
		gb::log(LOG_NOTICE, 'wrote %s', $this->cachename());
		return $bw;
	}
	
	function serialize() {
		# subclasses can interfere to normalize or fix values prior to serialization
		return serialize($this->index);
	}
	
	function onObject($obj) {
		# subclasses should to override this
		$this->index[] = $obj;
	}
	
	function finalize() {
		$this->sync($this->forceFullRebuild);
	}
}

class GBCategoryToObjsIndexRebuilder extends GBContentIndexRebuilder {
	function __construct($forceFullRebuild=false) {
		parent::__construct('category-to-objs', $forceFullRebuild);
	}
	
	function onObject($obj) {
		if (!$obj->categories)
			return;
		foreach ($obj->categories as $cat) {
			$cat = strtolower($cat);
			if (!isset($this->index[$cat]))
				$this->index[$cat] = array($obj->cachename());
			else
				$this->index[$cat][] = $obj->cachename();
		}
	}
	
	function serialize() {
		# array_flip-array_flip is cheaper than array_unique and retains order
		foreach ($this->index as $k => $v)
			$this->index[$k] = array_flip(array_flip($v));
		return parent::serialize();
	}
}

class GBTagToObjsIndexRebuilder extends GBContentIndexRebuilder {
	function __construct($forceFullRebuild=false) {
		parent::__construct('tag-to-objs', $forceFullRebuild);
	}
	
	function onObject($obj) {
		if (!$obj->tags)
			return;
		foreach ($obj->tags as $tag) {
			$tag = strtolower($tag);
			if (!isset($this->index[$tag]))
				$this->index[$tag] = array($obj->cachename());
			else
				$this->index[$tag][] = $obj->cachename();
		}
	}
	
	function serialize() {
		# array_flip-array_flip is cheaper than array_unique and retains order
		foreach ($this->index as $k => $v)
			$this->index[$k] = array_flip(array_flip($v));
		return parent::serialize();
	}
}

class GBTagsByPopularityIndexRebuilder extends GBContentIndexRebuilder {
	public $min = 0;
	public $max = 0;
	
	function __construct($forceFullRebuild=false) {
		parent::__construct('tags-by-popularity', $forceFullRebuild);
	}
	
	function onObject($obj) {
		if (!$obj->tags)
			return;
		foreach ($obj->tags as $tag) {
			if (!isset($this->index[$tag]))
				$this->index[$tag] = 1;
			else {
				$this->index[$tag]++;
			}	
			$this->max = max($this->max, $this->index[$tag]);
			$this->min = min($this->min, $this->index[$tag]);
		}
	}
	
	function serialize() {
		# normalize
		$max = floatval($this->max - $this->min);
		foreach ($this->index as $k => $v)
			$this->index[$k] = floatval($v) / $max;
		# sort most popular -> least popular
		arsort($this->index, SORT_NUMERIC);
		# reset
		$this->max = $this->min = 0;
		# lower precision of float serialization since we do not need fine granularity.
		$orig = ini_set('serialize_precision', '4');
		$data = parent::serialize();
		ini_set('serialize_precision', $orig);
		return $data;
	}
}

class GBRecentCommentsIndexRebuilder extends GBContentIndexRebuilder {
	public $limit = 10; # todo: make configurable through gb::data
	
	function __construct($forceFullRebuild=false) {
		parent::__construct('recent-comments', $forceFullRebuild);
	}
	
	function serialize() {
		$comments = array();
		$i = 0;
		
		# find
		foreach ($this->index as $commentObject) {
			$name = gb_filenoext($commentObject->name);
			foreach ($commentObject as $approvedComment)
				$comments[strval($approvedComment->date->time).'0'.$i++] = array($approvedComment, $name);
		}
		
		# sort
		krsort($comments);
		$this->index = array_values($comments);
		
		# limit
		if (count($this->index) > $this->limit)
			$this->index = array_slice($this->index, 0, $this->limit);
		
		# attach objects
		foreach ($this->index as $k => $tuple) {
			$object = null;
			if (isset(GBContentFinalizer::$objectsByName[$tuple[1]])) {
				$object = GBContentFinalizer::$objectsByName[$tuple[1]]->condensedVersion();
				$object->body = null;
			}
			$tuple[1] = $object;
			$this->index[$k] = $tuple;
		}
		
		return parent::serialize();
	}
}

class GBUnapprovedCommentsIndexRebuilder extends GBContentIndexRebuilder {
	public $unapprovedComments = array();
	public $spamComments = array();
	
	function __construct($forceFullRebuild=false) {
		parent::__construct('unapproved-comments', $forceFullRebuild);
	}
	
	function onObject(GBComments $commentObject) {
		$i = 0;
		foreach ($commentObject->getIterator(false) as $c) {
			if ($c->approved === false)
				$this->index[] = array($c, gb_filenoext($commentObject->name));
		}
	}
	
	function serialize() {
		uasort($this->index, create_function('$a, $b', 'return $b[0]->date->time - $a[0]->date->time;'));
		
		# mux comments with content
		foreach ($this->index as $i => $tuple) {
			$object = null;
			$objname = $tuple[1];
			if (isset(GBContentFinalizer::$objectsByName[$objname])) {
				$object = GBContentFinalizer::$objectsByName[$objname]->condensedVersion();
				$object->body = null;
			}
			$tuple[1] = $object;
			$this->index[$i] = $tuple;
		}
		
		return parent::serialize();
	}
}

function _gb_page_sortfunc($a, $b) {
	if ($a->order === $b->order)
		return strcasecmp($a->slug, $b->slug);
	if ($a->order === null)
		return 1; # B
	if ($b->order === null)
		return -1; # A
	return $a->order - $b->order;
}

class GBPagesIndexRebuilder extends GBContentIndexRebuilder {
	function __construct($forceFullRebuild=false) {
		parent::__construct('pages', $forceFullRebuild);
	}
	
	function onObject($obj) {
		if (!($obj instanceof GBPage))
			return;
		$this->index[] = $obj;
	}
	
	function serialize() {
		# sort on ASC($order), strcasecmp($slug)
		usort($this->index, '_gb_page_sortfunc');
		# key the index with slug and save condensed versions
		$v = $this->index;
		$this->index = array();
		foreach ($v as $obj)
			$this->index[$obj->slug] = $obj->condensedVersion();
		# ...
		return parent::serialize();
	}
}

class GBDraftPostsIndexRebuilder extends GBContentIndexRebuilder {
	function __construct($forceFullRebuild=false) {
		parent::__construct('draft-posts', $forceFullRebuild);
	}
	
	function onObject($obj) {
		if (($obj instanceof GBPost) && $obj->draft === true)
			$this->index[] = $obj;
	}
	
	function serialize() {
		# key the index with name and save condensed versions
		$v = $this->index;
		$this->index = array();
		foreach ($v as $obj)
			$this->index[$obj->name] = $obj->condensedVersion();
		return parent::serialize();
	}
}


function init_rebuilder_content(&$rebuilders) {
	$rebuilders[] = 'GBPostsRebuilder';
	$rebuilders[] = 'GBPagesRebuilder';
	# this must be added after the other ones above
	$rebuilders[] = 'GBContentFinalizer';
	
	# sub-rebuilders
	GBContentFinalizer::$objectIndexRebuilders[] = 'GBTagToObjsIndexRebuilder';
	GBContentFinalizer::$objectIndexRebuilders[] = 'GBTagsByPopularityIndexRebuilder';
	GBContentFinalizer::$objectIndexRebuilders[] = 'GBCategoryToObjsIndexRebuilder';
	GBContentFinalizer::$objectIndexRebuilders[] = 'GBPagesIndexRebuilder';
	GBContentFinalizer::$objectIndexRebuilders[] = 'GBDraftPostsIndexRebuilder';
	
	GBContentFinalizer::$commentIndexRebuilders[] = 'GBRecentCommentsIndexRebuilder';
	GBContentFinalizer::$commentIndexRebuilders[] = 'GBUnapprovedCommentsIndexRebuilder';
}
?>