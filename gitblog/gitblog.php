<?
error_reporting(E_ALL);

if (!isset($gb_config)) {
	@include 'gb-config.php';
	if (!isset($gb_config))
		require realpath(dirname(__FILE__)).'/skeleton/gb-config.php';
}

define('GITBLOG_VERSION', '0.1.0');

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


/** Evaluate an escaped UTF-8 sequence */
function gb_utf8_unescape($s) {
	eval('$s = "'.$s.'";');
	return $s;
}


function gb_normalize_git_name($name) {
	return ($name and $name{0} === '"') ? gb_utf8_unescape(substr($name, 1, -1)) : $name;
}


#function gb_utf8_wildcard_escape($name) {
#	$s = '';
#	$len = strlen($name);
#	$starcount = 0;
#	
#	for ($i=0; $i<$len; $i++) {
#		if (ord($name{$i}) > 127) {
#			$starcount++;
#			if ($i)
#				$s = substr($s, 0, -$starcount).'*';
#			else
#				$s .= '*';
#		}
#		else {
#			$s .= $name{$i};
#			$starcount = 0;
#		}
#	}
#	return $s;
#}


/**
 * Calculate relative path.
 * 
 * Example cases:
 * 
 * /var/gitblog/site/theme, /var/gitblog/gitblog/themes/default => "../../gitblog/themes/default"
 * /var/gitblog/gitblog/themes/default, /var/gitblog/site/theme => "../../../site/theme"
 * /var/gitblog/site/theme, /etc/gitblog/gitblog/themes/default => "/etc/gitblog/gitblog/themes/default"
 * /var/gitblog, gitblog/themes/default                         => "gitblog/themes/default"
 * /var/gitblog/site/theme, /var/gitblog/site/theme             => ""
 */
function gb_relpath($from, $to) {
	$fromv = explode('/', trim($from,'/'));
	$tov = explode('/', trim($to,'/'));
	$len = min(count($fromv), count($tov));
	$r = array();
	$likes = $back = 0;
	
	for (; $likes<$len; $likes++)
		if ($fromv[$likes] != $tov[$likes])
			break;
	
	if (!$likes and $to{0} === '/')
		return $to;
	
	if ($likes) {
		$back = count($fromv) - $likes;
		for ($x=0; $x<$back; $x++)
			$r[] = '..';
		$r = array_merge($r, array_slice($tov, $likes));
	}
	else {
		$r =& $tov;
	}
	
	return implode('/', $r);
}

#------------------------------------------------------------------------------
# Helper functions for themes/templates

$gb_title = array($gb_config['title']);

function gb_title($glue=' — ', $html=true) {
	global $gb_title;
	$s = implode($glue, array_reverse($gb_title));
	return $html ? h($s) : $s;
}

function h($s) {
	return htmlentities($s, ENT_COMPAT, 'UTF-8');
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
		$st = strptime($slug, $gb_config['posts']['url-prefix']);
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
	
	function postsPageByPageno($pageno) {
		$path = $this->pathToPostsPage($pageno);
		return @unserialize(file_get_contents($path));
	}
	
	function urlToTags($tags) {
		global $gb_config;
		return $gb_config['index-url'] . $gb_config['tags-prefix']
			. implode(',', array_map('urlencode', $tags));
	}
	
	function urlToTag($tag) {
		global $gb_config;
		return $gb_config['index-url'] . $gb_config['tags-prefix']
			. urlencode($tag);
	}
	
	function urlToCategories($categories) {
		global $gb_config;
		return $gb_config['index-url'] . $gb_config['categories-prefix']
			. implode(',', array_map('urlencode', $categories));
	}
	
	function urlToCategory($category) {
		global $gb_config;
		return $gb_config['index-url'] . $gb_config['categories-prefix'] 
			. urlencode($category);
	}
	
	function init($shared=true) {
		$mkdirmode = 0755;
		if ($shared) {
			$shared = 'true';
			$mkdirmode = 0775;
		}
		else
			$shared = 'false';
		
		# git init
		$cmd = "init --quiet --shared=$shared";
		if (!is_dir($this->gitdir) && !mkdir($this->gitdir, $mkdirmode, true))
			return false;
		$this->exec($cmd);
		
		# Create empty standard directories
		mkdir("{$this->repo}/content/posts", $mkdirmode, true);
		mkdir("{$this->repo}/content/pages", $mkdirmode);
		
		# Enable default theme
		$target = gb_relpath("{$this->repo}/theme", GITBLOG_DIR.'/themes/default');
		symlink($target, "{$this->repo}/theme");
		
		# Copy hooks
		$skeleton = GITBLOG_DIR.'/skeleton';
		copy("$skeleton/hooks/post-commit", "{$this->gitdir}/hooks/post-commit");
		
		return true;
	}
	
	/**
	 * Verify integrity of repository and gitblog cache.
	 * 
	 * Return values:
	 *   0  Nothing was done (everything is probably OK).
	 *   -1 Error (the error has been logged through trigger_error).
	 *   1  gitblog cache was updated.
	 *   2  gitdir is missing and need to be created (git init).
	 */
	function verifyIntegrity() {
		if (is_dir("{$this->gitdir}/info/gitblog"))
			return 0;
		if (!is_dir($this->gitdir))
			return 2;
		GBRebuilder::rebuild($this, true);
		return 1;
	}
	
	function verifyConfig() {
		global $gb_config;
		if (!$gb_config['secret'] or strlen($gb_config['secret']) < 62) {
			header('Status: 503 Service Unavailable');
			header('Content-Type: text/plain; charset=utf-8');
			exit("\n\n\$gb_config['secret'] is not set or too short.\n\nPlease edit your gb-config.php file.\n");
		}
	}
}


function gb_html_postprocess_filter(&$body) {
	$body = nl2br(trim($body));
	return true;
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
	
	static public $filters = array(
		'application/xhtml+xml' => array('gb_html_postprocess_filter'),
		'text/html' => array('gb_html_postprocess_filter'),
	);
	
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
		$this->mimeType = GBMimeType::forFilename($this->name);
		
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
		if ($this->body)
			$this->applyFilters();
		
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
	
	function applyFilters() {
		if (isset(self::$filters[$this->mimeType])) {
			foreach (self::$filters[$this->mimeType] as $filter)
				if (!$filter($this->body))
					break;
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
		return $gb_config['index-url'].$this->urlpath();
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
		$u = $gb_config['index-url'] . $gb_config["$what-prefix"];
		
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
		return gmstrftime($gb_config['posts']['url-prefix'], $this->published)
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
?>