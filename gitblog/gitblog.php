<?
error_reporting(E_ALL);

if (!isset($gb_config)) {
	require 'gb-config.php';
	if (!isset($gb_config)) {
		$msg = "\$gb_config is not set";
		trigger_error($msg);
		exit($msg);
	}
}

if (!defined('GITBLOG_DIR'))
	define('GITBLOG_DIR', realpath(dirname(__FILE__)));

ini_set('include_path', ini_get('include_path') . ':' . GITBLOG_DIR . '/lib');

/** @ignore */
function __autoload($c) {
  # we use include instead of include_once since it's alot faster
  # and the probability of including an allready included file is
  # very small.
  if((include $c . '.php') === false) {
    $t = debug_backtrace();
    if(@$t[1]['function'] != 'class_exists')
      trigger_error("failed to load class $c");
  }
}
ini_set('unserialize_callback_func', '__autoload');

# xxx macports git
$_ENV['PATH'] .= ':/opt/local/bin';

#------------------------------------------------------------------------------
# Universal functions

/** Atomic write */
function gb_atomic_write($filename, &$data, $chmod=null) {
	$tempnam = tempnam(dirname($filename), basename($filename));
	$f = fopen($tempnam, 'w');
	fwrite($f, $data);
	fclose($f);
	if ($chmod !== null)
		chmod($tempnam, $chmod);
	if (!rename($tempnam, $filename)) {
		unlink($tempnam);
		return false;
	}
	return true;
}


/** Boiler plate popen */
function gb_popen($cmd, $cwd=null, $env=null) {
	$fds = array(array("pipe", "r"), array("pipe", "w"), array("pipe", "w"));
	$ps = proc_open($cmd, $fds, $pipes, $cwd, $env);
	if (!is_resource($ps)) {
		trigger_error('gb_popen('.var_export($cmd,1).') failed in '.__FILE__.':'.__LINE__);
		return null;
	}
	return array('handle'=>$ps, 'pipes'=>$pipes);
}


/** Parse MIME-like headers. */
function gb_parse_content_obj_headers($lines, &$out) {
	$lines = explode("\n", $lines);
	$k = null;
	foreach ($lines as $line) {
		if (!$line)
			continue;
		if ($line{0} === ' ' || $line{0} === "\t") {
			# continuation
			if ($k !== null)
				$out[$k] .= ltrim($line);
			continue;
		}
		$line = explode(':', $line, 2);
		if (isset($line[1])) {
			$k = $line[0];
			$out[$k] = ltrim($line[1]);
		}
	}
}


/** path w/o extension */
function gb_filenoext($path) {
	$p = strpos($path, '.', strrpos($path, '/'));
	return $p > 0 ? substr($path, 0, $p) : $path;
}


/** Like readline, but acts on a byte array. Keeps state with $p */
function gb_sreadline(&$p, &$str, $sep="\n") {
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

#------------------------------------------------------------------------------
# Exceptions

class GitError extends Exception {}
class GitUninitializedRepoError extends GitError {}

#------------------------------------------------------------------------------
# Main class

class GitBlog {
	public $repo = './site';
	public $gitdir = './site/.git';
	public $rebuilders = array();
	public $gitQueryCount = 0;
	
	function __construct($repo) {
		$this->repo = $repo;
		$this->gitdir = $repo.'/.git';
	}
	
	/** Execute a git command */
	function exec($cmd, $input=null) {
		# build cmd
		$cmd = "GIT_DIR='{$this->gitdir}' git $cmd";
		#var_dump($cmd);
		# start process
		$ps = gb_popen($cmd, null, $_ENV);
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
	
	function pathToTheme($file='') {
		return $this->repo.'/theme/'.$file;
	}
	
	function pathToCachedContent($dirname, $slug) {
		return "{$this->gitdir}/info/gitblog/content/$dirname/$slug";
	}
	
	function pathToPostsPage($pageno) {
		return "{$this->gitdir}/info/gitblog/content-paged-posts/".sprintf('%011d', $pageno);
	}
	
	function pathToPost($slug) {
		global $gb_config;
		$st = strptime($slug, $gb_config['posts']['slug-prefix']);
		$date = gmmktime($st['tm_hour'], $st['tm_min'], $st['tm_sec'], 
			$st['tm_mon']+1, $st['tm_mday'], 1900+$st['tm_year']);
		$slug = $st['unparsed'];
		$cachename = date('Y/m/d/', $date).$slug;
		return $this->pathToCachedContent('posts', $cachename);
	}
	
	function pageBySlug($slug) {
		$path = $this->pathToCachedContent('pages', $slug);
		return @unserialize(file_get_contents($path));
	}
	
	function postBySlug($slug) {
		$path = $this->pathToPost($slug);
		return @unserialize(file_get_contents($path));
	}
	
	function urlToTags($tags) {
		global $gb_config;
		return $gb_config['url-prefix'] . $gb_config['tags-prefix']
			. implode(',', array_map('urlencode', $tags));
	}
	
	function urlToTag($tag) {
		global $gb_config;
		return $gb_config['url-prefix'] . $gb_config['tags-prefix']
			. urlencode($tag);
	}
	
	function urlToCategories($categories) {
		global $gb_config;
		return $gb_config['url-prefix'] . $gb_config['categories-prefix']
			. implode(',', array_map('urlencode', $categories));
	}
	
	function urlToCategory($category) {
		global $gb_config;
		return $gb_config['url-prefix'] . $gb_config['categories-prefix'] 
			. urlencode($category);
	}
}


class GBContent {
	public $name;
	public $id;
	public $slug;
	public $meta;
	public $title;
	public $body;
	public $mimeType = null;
	public $tags = array();
	public $categories = array();
	public $author = null;
	public $published = false; # timestamp
	public $modified = false; # timestamp
	
	function __construct($name, $id, $slug, $meta=array(), $body=null) {
		$this->name = $name;
		$this->id = $id;
		$this->slug = $slug;
		$this->meta = $meta;
		$this->body = $body;
	}
	
	function reload(&$data, $commits) {
		$bodystart = strpos($data, "\n\n");
		
		if ($bodystart === false) {
			trigger_error("malformed content object '{$this->name}' missing header");
			return;
		}
		
		$this->body = null;
		$this->meta = array();
		$this->mimeType = GBMimeType::forFilename($self->name);
		
		gb_parse_content_obj_headers(substr($data, 0, $bodystart), $this->meta);
		
		# lift lists from meta to this
		static $special_lists = array('tag'=>'tags', 'categry'=>'categories');
		foreach ($special_lists as $singular => $plural) {
			if (isset($this->meta[$plural])) {
				$this->$plural = array_unique(preg_split('/[, ]+/', $this->meta[$plural]));
				unset($this->meta[$plural]);
			}
			elseif (isset($this->meta[$singular])) {
				$this->$plural = array($this->meta[$singular]);
				unset($this->meta[$singular]);
			}
		}
		
		# lift specials, like title, from meta to this
		static $special_singles = array('title');
		foreach ($special_singles as $singular) {
			if (isset($this->meta[$singular])) {
				$this->$singular = $this->meta[$singular];
				unset($this->meta[$singular]);
			}
		}
		
		# freeze meta
		$this->meta = (object)$this->meta;
		
		# set body
		$this->body = substr($data, $bodystart+2);
		#if ($this->body)
		#	$this->applyFilters('body', $body);
		
		# translate info from commits
		if ($commits) {
			# latest one is last modified
			$this->modified = $commits[0]->authorDate;
			
			# first one is when the content was created
			$initial = $commits[count($commits)-1];
			if ($this->published === false)
				$this->published = $initial->authorDate;
			if (!$this->author) {
				$this->author = (object)array(
					'name' => $initial->authorName,
					'email' => $initial->authorEmail
				);
			}
		}
	}
	
	function urlpath() {
		return str_replace('%2F', '/', urlencode($this->slug));
	}
	
	function cachename() {
		return gb_filenoext($this->name);
	}
	
	static function getCached($cachebase, $name) {
		$path = $cachebase.'/'.gb_filenoext($name);
		return @unserialize(file_get_contents($path));
	}
	
	function writeCache($cachebase) {
		$path = $cachebase.'/'.$this->cachename();
		$dirname = dirname($path);
		if (!is_dir($dirname))
			mkdir($dirname, 0775, true);
		$data = serialize($this);
		return gb_atomic_write($path, $data, 0664);
	}
	
	function url() {
		global $gb_config;
		return $gb_config['url-prefix'].$this->urlpath();
	}
	
	function tagLinks($separator=', ', $template='<a href="%u">%n</a>', $htmlescape=true) {
		return $this->collLinks('tags', $separator, $template, $htmlescape);
	}
	
	function categoryLinks($separator=', ', $template='<a href="%u">%n</a>', $htmlescape=true) {
		return $this->collLinks('categories', $separator, $template, $htmlescape);
	}
	
	function collLinks($what, $separator=', ', $template='<a href="%u">%n</a>', $htmlescape=true) {
		global $gb_config;
		static $needles = array('%u', '%n');
		$links = array();
		$u = $gb_config['url-prefix'] . $gb_config["$what-prefix"];
		
		foreach ($this->$what as $tag) {
			$n = $htmlescape ? htmlentities($tag) : $tag;
			$links[] = str_replace($needles, array($u.urlencode($tag), $n), $template);
		}
		
		return $separator !== null ? implode($separator, $links) : $links;
	}
}


class GBPage extends GBContent {
}


class GBPost extends GBContent {
	function urlpath() {
		global $gb_config;
		return gmstrftime($gb_config['posts']['slug-prefix'], $this->published)
			. str_replace('%2F', '/', urlencode($this->slug));
	}
	
	function cachename() {
		return 'content/posts/'.gmdate("Y/m/d/", $this->published).$this->slug;
	}
	
	static function getCached($cachebase, $published, $slug) {
		$path = $cachebase.'/content/posts/'.gmdate("Y/m/d/", $published).$slug;
		return @unserialize(file_get_contents($path));
	}
}

$debug_time_started = microtime(true);

$gitblog = new GitBlog($gb_config['repo']);
#GBRebuilder::rebuild($gb, true);

?>