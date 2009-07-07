<?
error_reporting(E_ALL);
$debug_time_started = microtime(true);

# constants
define('GB_VERSION', '0.1.0');
define('GB_DIR', dirname(__FILE__));
$u = dirname($_SERVER['SCRIPT_NAME']);
$s = dirname($_SERVER['SCRIPT_FILENAME']);
if (substr($_SERVER['SCRIPT_FILENAME'], -20) === '/gitblog/gitblog.php')
	exit(0);
$ingb = (strpos($s, '/gitblog/') !== false || substr($s, -8) === '/gitblog') 
	&& (strpos(realpath($s), realpath(GB_DIR)) === 0);
if (!defined('GB_SITE_DIR')) {
	if ($ingb) {
		# confirmed: inside gitblog -- back up to before the gitblog dir and 
		# assume that's the site dir.
		$max = 20;
		while($s !== '/' && $max--) {
			if (substr($s, -7) === 'gitblog') {
				$s = dirname($s);
				$u = dirname($u);
				break;
			}
			$s = dirname($s);
			$u = dirname($u);
		}
	}
	define('GB_SITE_DIR', realpath($s));
	if (!defined('GB_SITE_URL')) {
		# URL to the base of the site.
		# Must end with a slash ("/").
		define('GB_SITE_URL', 
			(isset($_SERVER['HTTPS']) ? 'https://' : 'http://')
			.$_SERVER['SERVER_NAME'] . ($u === '/' ? $u : $u.'/'));
	}
}
if (!$ingb) {
	# we only need (and can deduce) these while running the theme
	if (!defined('GB_THEME_DIR')) {
		$bt = debug_backtrace();
		define('GB_THEME_DIR', dirname($bt[0]['file']));
	}
	if (!defined('GB_THEME_URL')) {
		$relpath = gb_relpath(GB_SITE_DIR, GB_THEME_DIR);
		if ($relpath === '' || $relpath === '.') {
			define('GB_THEME_URL', GB_SITE_URL);
		}
		elseif ($relpath{0} === '.' || $relpath{0} === '/') {
			$uplevels = $max_uplevels = 0;
			if ($relpath{0} === '/') {
				$uplevels = 1;
			}
			if ($relpath{0} === '.') {
				function _empty($x) { return empty($x); }
				$max_uplevels = count(explode('/',trim(parse_url(GB_SITE_URL, PHP_URL_PATH), '/')));
				$uplevels = count(array_filter(explode('../', $relpath), '_empty'));
			}
			if ($uplevels > $max_uplevels) {
				trigger_error('GB_THEME_URL could not be deduced since the theme you are '.
					'using ('.GB_THEME_DIR.') is not reachable from '.GB_SITE_URL.
					'. You need to manually define GB_THEME_URL before including gitblog.php',
					E_USER_ERROR);
			}
		}
		else {
			define('GB_THEME_URL', GB_SITE_URL.$relpath.'/');
		}
	}
}
unset($s);
unset($u);
unset($ingb);

/**
 * Configuration.
 *
 * These values can be overridden in gb-config.php (or somewhere else for that matter).
 */
class gb {
	/** URL prefix for tags */
	static public $tags_prefix = 'tags/';

	/** URL prefix for categories */
	static public $categories_prefix = 'categories/';

	/** URL prefix for the feed */
	static public $feed_prefix = 'feed';

	/** URL prefix (strftime pattern) */
	static public $posts_url_prefix = '%Y/%m/%d/';
	
	/** URL prefix (pcre pattern) */
	static public $posts_url_prefix_re = '/^\d{4}\/\d{2}\/\d{2}\//';

	/**
	 * Number of posts per page.
	 * Changing this requires a rebuild before actually activated.
	 */
	static public $posts_pagesize = 10;
	
	/** URL to gitblog index relative to GB_SITE_URL (request handler) */
	static public $index_url = 'index.php/';
	
	/**
	 * Log messages of priority >=$log_filter will be sent to syslog.
	 * Disable logging by setting this to -1.
	 * See the "Logging" section in gitblog.php for more information.
	 */
	static public $log_filter = LOG_WARNING;
	
	# --------------------------------------------------------------------------
	# The following are by default set in the gb-config.php file.
	# See gb-config.php for detailed documentation.
	
	/** Site title */
	static public $site_title = null;
	
	/** Shared secret */
	static public $secret = '';
	
	# --------------------------------------------------------------------------
	# The following are used at runtime.
	
	static public $title;
	
	static public $is_404 = false;
	static public $is_page = false;
	static public $is_post = false;
	static public $is_posts = false;
	static public $is_search = false;
	static public $is_tags = false;
	static public $is_categories = false;
	static public $is_feed = false;
	
	# --------------------------------------------------------------------------
	# Filters
	static public $filters = array();
	
	/**
	 * Add a filter
	 * 
	 * Lower number for $priority means earlier execution of $func.
	 * 
	 * If $func returns boolean FALSE the filter chain is broken, not applying
	 * any more filter after the one returning FALSE. Returning anything else
	 * have no effect.
	 */
	static function add_filter($tag, $func, $priority=100) {
		if (!isset(self::$filters[$tag]))
			self::$filters[$tag] = array($priority => array($func));
		elseif (!isset(self::$filters[$tag][$priority]))
			self::$filters[$tag][$priority] = array($func);
		else
			self::$filters[$tag][$priority][] = $func;
	}
	
	/** Apply filters for $tag on $value */
	static function apply_filters($tag, $value) {
		$a = @self::$filters[$tag];
		if ($a === null)
			return $value;
		ksort($a, SORT_NUMERIC);
		foreach ($a as $funcs)
			foreach ($funcs as $func)
				$value = call_user_func($func, $value);
		return $value;
	}
	
	# --------------------------------------------------------------------------
	# Logging
	static public $log_open = false;
	
	static function openlog($ident=null, $options=LOG_PID, $facility=LOG_USER) {
		if ($ident === null) {
			$u = parse_url(GB_SITE_URL);
			$ident = 'gitblog.'.isset($u['host']) ? $u['host'] .'.' : '';
			if (isset($u['path']))
				$ident .= str_replace('/', '.', trim($u['path'],'/'));
		}
		self::$log_open = openlog($ident, $options, $facility);
		return self::$log_open;
	}
	
	static function vlog($priority, $vargs, $btoffset=1) {
		if ($priority > self::$log_filter)
			return true;
		$bt = debug_backtrace();
		$bt = $bt[$btoffset];
		$msg = '['.gb_relpath(GB_SITE_DIR, $bt['file']).':'.$bt['line'].'] ';
		if(count($vargs) > 1) {
			$fmt = array_shift($vargs);
			$msg .= vsprintf($fmt, $vargs);
		}
		elseif ($vargs) {
			$msg .= $vargs[0];
		}
		if (!self::$log_open && !self::openlog() && $priority < LOG_WARNING) {
			trigger_error($msg, E_USER_ERROR);
			return $msg;
		}
		return syslog($priority, $msg) ? $msg : false;
	}
	
	/**
	 * Send a message to syslog.
	 * 
	 * INT  CONSTANT    DESCRIPTION
	 * ---- ----------- ----------------------------------
	 * 0    LOG_EMERG   system is unusable
	 * 1    LOG_ALERT   action must be taken immediately
	 * 2    LOG_CRIT    critical conditions
	 * 3    LOG_ERR     error conditions
	 * 4    LOG_WARNING warning conditions
	 * 5    LOG_NOTICE  normal, but significant, condition
	 * 6    LOG_INFO    informational message
	 * 7    LOG_DEBUG   debug-level message
	 */
	static function log($priority, $fmt/* [mixed ..] */) {
		$vargs = func_get_args();
		$priority = array_shift($vargs);
		return self::vlog($priority, $vargs);
	}
}

if (file_exists(GB_SITE_DIR.'/gb-config.php'))
	include GB_SITE_DIR.'/gb-config.php';

# no config? -- read defaults
if (gb::$site_title === null) {
	require GB_DIR.'/skeleton/gb-config.php';
}

ini_set('include_path', ini_get('include_path') . ':' . GB_DIR . '/lib');

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
function gb_atomic_write($filename, $data, $chmod=null) {
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
	$p = strrpos($path, '.', strrpos($path, '/'));
	return $p > 0 ? substr($path, 0, $p) : $path;
}


/** split path into array("path w/o extension", "extension") */
function gb_fnsplit($path) {
	$p = strrpos($path, '.', strrpos($path, '/'));
	return array($p > 0 ? substr($path, 0, $p) : $path,
		$p !== false ? substr($path, $p+1) : '');
}


/** Like readline, but acts on a byte array. Keeps state with $p */
function gb_sreadline(&$p, $str, $sep="\n") {
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
	return ($name && $name{0} === '"') ? gb_utf8_unescape(substr($name, 1, -1)) : $name;
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


/** Normalize $time (any format strtotime can handle) to a ISO timestamp. */
function gb_strtoisotime($time) {
	$d = new DateTime($time);
	return $d->format('c');
}


/**
 * Parse a date string to UNIX timestamp.
 * If no timezone information is present, UTC is assumed.
 * Returns false on error.
 */
function gb_utcstrtotime($input, $fallbacktime=false) {
	$t = date_parse($input);
	if ($t['error_count']) {
		trigger_error(__FUNCTION__.'('.var_export($input,1).'): '.implode(', ', $t['errors']));
		return false;
	}
	if ($fallbacktime === false)
		$fallbacktime = time();
	$ts = gmmktime($t['hour'], $t['minute'], $t['second'], 
		$t['month'] === false ? date('n', $fallbacktime) : $t['month'],
		$t['day'] === false ? date('j', $fallbacktime) : $t['day'],
		$t['year'] === false ? date('Y', $fallbacktime) : $t['year'], 
		isset($t['is_dst']) ? ($t['is_dst'] ? 1 : 0) : -1);
	if (isset($t['zone']))
		$ts += $t['zone']*60;
	return $ts;
}


/**
 * Calculate relative path.
 * 
 * Example cases:
 * 
 * /var/gitblog/site/theme, /var/gitblog/gitblog/themes/default => "../gitblog/themes/default"
 * /var/gitblog/gitblog/themes/default, /var/gitblog/site/theme => "../../site/theme"
 * /var/gitblog/site/theme, /etc/gitblog/gitblog/themes/default => "/etc/gitblog/gitblog/themes/default"
 * /var/gitblog, gitblog/themes/default                         => "gitblog/themes/default"
 * /var/gitblog/site/theme, /var/gitblog/site/theme             => ""
 */
function gb_relpath($from, $to) {
	if ($from === $to)
		return '.';
	$fromv = explode('/', trim($from,'/'));
	$tov = explode('/', trim($to,'/'));
	$len = min(count($fromv), count($tov));
	$r = array();
	$likes = $back = 0;
	
	for (; $likes<$len; $likes++)
		if ($fromv[$likes] != $tov[$likes])
			break;
	
	if ((!$likes) && $to{0} === '/')
		return $to;
	
	if ($likes) {
		array_pop($fromv);
		$back = count($fromv) - $likes;
		for ($x=0; $x<$back; $x++)
			$r[] = '..';
		$r = array_merge($r, array_slice($tov, $likes));
	}
	else {
		$r = $tov;
	}
	
	return implode('/', $r);
}

function gb_hms_from_time($ts) {
	$p = date('his', $ts);
	return (intval($p{0}.$p{1})*60*60) + (intval($p{2}.$p{3})*60) + intval($p{4}.$p{5});
}

#------------------------------------------------------------------------------
# Helper functions for themes/templates

gb::$title = array(gb::$site_title);

function gb_title($glue=' — ', $html=true) {
	$s = implode($glue, array_reverse(gb::$title));
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
	static public $rebuilders = array();
	static public $gitQueryCount = 0;
	
	/** Execute a git command */
	static function exec($cmd, $input=null) {
		# build cmd
		$cmd = 'git --git-dir='.escapeshellarg(GB_SITE_DIR.'/.git')
			.' --work-tree='.escapeshellarg(GB_SITE_DIR)
			.' '.$cmd;
		#var_dump($cmd);
		$r = self::shell($cmd, $input, GB_SITE_DIR);
		self::$gitQueryCount++;
		# fail?
		if ($r === null)
			return null;
		# check for errors
		if ($r[0] != 0) {
			if (strpos($r[2], 'Not a git repository') !== false)
				throw new GitUninitializedRepoError($r[2]);
			else
				throw new GitError($r[2]);
		}
		return $r[1];
	}
	
	/** Execute a command inside a shell */
	static function shell($cmd, $input=null, $cwd=null, $env=null) {
		#var_dump($cmd);
		# start process
		$ps = gb_popen($cmd, $cwd, $env === null ? $_ENV : $env);
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
		# wait and return
		return array(proc_close($ps['handle']), $output, $errors);
	}
	
	static function pathToTheme($file='') {
		return GB_SITE_DIR.'/theme/'.$file;
	}
	
	static function pathToCachedContent($dirname, $slug) {
		return GB_SITE_DIR.'/.git/info/gitblog/content/'.$dirname.'/'.$slug;
	}
	
	static function pathToPostsPage($pageno) {
		return GB_SITE_DIR.sprintf('/.git/info/gitblog/content-paged-posts/%011d', $pageno);
	}
	
	static function pathToPost($slug) {
		$st = strptime($slug, gb::$posts_url_prefix);
		$date = gmmktime($st['tm_hour'], $st['tm_min'], $st['tm_sec'], 
			$st['tm_mon']+1, $st['tm_mday'], 1900+$st['tm_year']);
		$slug = $st['unparsed'];
		$cachename = date('Y/m-d-', $date).$slug;
		return self::pathToCachedContent('posts', $cachename);
	}
	
	static function pageBySlug($slug) {
		$path = self::pathToCachedContent('pages', $slug);
		return @unserialize(file_get_contents($path));
	}
	
	static function postBySlug($slug) {
		$path = self::pathToPost($slug);
		return @unserialize(file_get_contents($path));
	}
	
	static function postsPageByPageno($pageno) {
		$path = self::pathToPostsPage($pageno);
		return @unserialize(file_get_contents($path));
	}
	
	static function urlToTags($tags) {
		return GB_SITE_URL . gb::$index_url . gb::$tags_prefix 
			. implode(',', array_map('urlencode', $tags));
	}
	
	static function urlToTag($tag) {
		return GB_SITE_URL . gb::$index_url . gb::$tags_prefix 
			. urlencode($tag);
	}
	
	static function urlToCategories($categories) {
		return GB_SITE_URL . gb::$index_url . gb::$categories_prefix 
			. implode(',', array_map('urlencode', $categories));
	}
	
	static function urlToCategory($category) {
		return GB_SITE_URL . gb::$index_url . gb::$categories_prefix 
			. urlencode($category);
	}
	
	static function init($add_sample_content=true, $shared='true', $theme='default') {
		$mkdirmode = $shared === 'all' ? 0777 : 0775;
		$shared = $shared ? "--shared=$shared" : '';
		
		# sanity check
		$themedir = GB_DIR.'/themes/'.$theme;
		if (!is_dir($themedir))
			throw new InvalidArgumentException(
				'no theme named '.$theme.' ('.$themedir.'not found or not a directory)');
		
		# create directories and chmod
		if (!is_dir(GB_SITE_DIR.'/.git') && !mkdir(GB_SITE_DIR.'/.git', $mkdirmode, true))
			return false;
		chmod(GB_SITE_DIR, $mkdirmode);
		chmod(GB_SITE_DIR.'/.git', $mkdirmode);
		
		# git init
		self::exec('init --quiet '.$shared);
		
		# Create empty standard directories
		mkdir(GB_SITE_DIR.'/content/posts', $mkdirmode, true);
		mkdir(GB_SITE_DIR.'/content/pages', $mkdirmode);
		chmod(GB_SITE_DIR.'/content', $mkdirmode);
		chmod(GB_SITE_DIR.'/content/posts', $mkdirmode);
		chmod(GB_SITE_DIR.'/content/pages', $mkdirmode);
		
		# Copy post-commit hook
		copy(GB_DIR.'/skeleton/hooks/post-commit', GB_SITE_DIR.'/.git/hooks/post-commit');
		chmod(GB_SITE_DIR.'/.git/hooks/post-commit', 0774);
		
		# Copy .gitignore
		copy(GB_DIR.'/skeleton/gitignore', GB_SITE_DIR.'/.gitignore');
		chmod(GB_SITE_DIR.'/.gitignore', 0664);
		self::add('.gitignore');
		
		# Copy theme
		$lnname = GB_SITE_DIR.'/index.php';
		$lntarget = gb_relpath($lnname, $themedir.'/index.php');
		symlink($lntarget, $lnname);
		self::add('index.php');
		
		# Add gb-config.php (might been added already, might be missing and/or
		# might be ignored by custom .gitignore -- doesn't really matter)
		self::add('gb-config.php', false);
		
		# Add sample content
		if ($add_sample_content) {
			# Copy example "about" page
			copy(GB_DIR.'/skeleton/content/pages/about.html', GB_SITE_DIR.'/content/pages/about.html');
			chmod(GB_SITE_DIR.'/content/pages/about.html', 0664);
			self::add('content/pages/about.html');
		
			# Copy example "hello world" post
			$today = time();
			$s = file_get_contents(GB_DIR.'/skeleton/content/posts/0000-00-00-hello-world.html');
			$name = 'content/posts/'.date('Y/m-d').'-hello-world.html';
			$path = GB_SITE_DIR.'/'.$name;
			@mkdir(dirname($path), 0775, true);
			chmod(dirname($path), 0775);
			$s = str_replace('0000/00-00-hello-world.html', basename(dirname($name)).'/'.basename($name), $s);
			file_put_contents($path, $s);
			chmod($path, 0664);
			self::add($name);
		}
		
		return true;
	}
	
	static function add($pathspec, $forceIncludeIgnored=true) {
		self::exec(($forceIncludeIgnored ? 'add --force ' : 'add ').escapeshellarg($pathspec));
	}
	
	static function commit($message, $author=null) {
		$author = $author ? '--author='.escapeshellarg($author) : '';
		self::exec('commit -m '.escapeshellarg($message).' --quiet '.$author);
		@chmod(GB_SITE_DIR.'/.git/COMMIT_EDITMSG', 0664);
		return true;
	}
	
	static function syncSiteURLcache() {
		if (@file_get_contents(GB_SITE_DIR.'/.git/info/gitblog-site-url') !== GB_SITE_URL)
			gb_atomic_write(GB_SITE_DIR.'/.git/info/gitblog-site-url', GB_SITE_URL, 0664);
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
	static function verifyIntegrity() {
		$r = 0;
		if (!is_dir(GB_SITE_DIR.'/.git/info/gitblog')) {
			if (!is_dir(GB_SITE_DIR.'/.git'))
				return 2;
			GBRebuilder::rebuild(true);
			$r = 1;
		}
		self::syncSiteURLcache();
		return $r;
	}
	
	static function verifyConfig() {
		if (!gb::$secret || strlen(gb::$secret) < 62) {
			header('Status: 503 Service Unavailable');
			header('Content-Type: text/plain; charset=utf-8');
			exit("\n\ngb::\$secret is not set or too short.\n\nPlease edit your gb-config.php file.\n");
		}
	}
}


# -----------------------------------------------------------------------------
# Content (posts, pages, etc)


class GBContent {
	public $name; # relative to root tree
	public $id;
	public $mimeType = null;
	public $author = null;
	public $modified = false; # timestamp
	public $published = false; # timestamp
	
	function __construct($name=null, $id=null) {
		$this->name = $name;
		$this->id = $id;
	}
	
	function cachename() {
		return gb_filenoext($this->name);
	}
	
	static function getCached($name) {
		$path = GB_SITE_DIR.'/.git/info/gitblog/'.gb_filenoext($name);
		return @unserialize(file_get_contents($path));
	}
	
	function writeCache() {
		$path = GB_SITE_DIR.'/.git/info/gitblog/'.$this->cachename();
		$dirname = dirname($path);
		
		if (!is_dir($dirname)) {
			$p = GB_SITE_DIR.'/.git/info';
			$parts = array_merge(array('gitblog'),explode('/',trim(dirname($this->cachename()),'/')));
			foreach ($parts as $part) {
				$p .= '/'.$part;
				@mkdir($p, 0775, true);
				chmod($p, 0775);
			}
		}
		return gb_atomic_write($path, serialize($this), 0664);
	}
	
	function reload($data, $commits) {
		$this->mimeType = GBMimeType::forFilename($this->name);
	}
	
	protected function applyInfoFromCommits($commits) {
		if (!$commits)
			return;
		
		# latest one is last modified
		$this->modified = $commits[0]->authorDate;
		
		# first one is when the content was created
		$initial = $commits[count($commits)-1];
		if ($this->published === false) {
			$this->published = $initial->authorDate;
		}
		else {
			#	combine day from published with time from authorDate
			$this->published = strtotime(
				date('Y-m-d', $this->published).'T'.date('H:i:sO', $initial->authorDate));
		}
		
		if (!$this->author) {
			$this->author = (object)array(
				'name' => $initial->authorName,
				'email' => $initial->authorEmail
			);
		}
	}
	
	function __sleep() {
		return array('name','id','mimeType','author','modified','published');
	}
}


class GBExposedContent extends GBContent {
	public $slug;
	public $meta;
	public $title;
	public $body;
	public $tags = array();
	public $categories = array();
	public $comments;
	public $commentsOpen = true;
	public $pingbackOpen = true;
	
	# 2038-01-19 03:14:07 UTC ("distant future" on 32bit systems)
	const NOT_PUBLISHED = 2147483647;
	
	static public $filters = array(
		'application/xhtml+xml' => array('gb_html_postprocess_filter'),
		'text/html' => array('gb_html_postprocess_filter'),
	);
	
	function __construct($name=null, $id=null, $slug=null, $meta=array(), $body=null) {
		parent::__construct($name, $id);
		$this->slug = $slug;
		$this->meta = $meta;
		$this->body = $body;
	}
	
	function applyFilters() {
		if (isset(self::$filters[$this->mimeType])) {
			foreach (self::$filters[$this->mimeType] as $filter)
				if ($filter($this) === false)
					break;
		}
		else
			echo "no filters for content of type $this->mimeType\n";
	}
	
	function reload($data, $commits) {
		parent::reload($data, $commits);
		
		$bodystart = strpos($data, "\n\n");
		if ($bodystart === false)
			$bodystart = 0;
			#trigger_error("malformed content object '{$this->name}' missing header");
		
		$this->body = null;
		$this->meta = array();
		
		if ($bodystart > 0)
			gb_parse_content_obj_headers(substr($data, 0, $bodystart), $this->meta);
		
		# lift lists from meta to this
		static $special_lists = array('tag'=>'tags', 'category'=>'categories');
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
		
		# use meta for title if absent
		if ($this->title === null)
			$this->title = $this->slug;
		
		# set body
		$this->body = substr($data, $bodystart+2);
		
		# apply and translate info from commits
		$this->applyInfoFromCommits($commits);
		
		# specific publish (date and) time?
		$mp = isset($this->meta['publish']) ? $this->meta['publish'] : 
			(isset($this->meta['published']) ? $this->meta['published'] : false);
		$mp = $mp ? strtoupper($mp) : $mp;
		if ($mp === 'FALSE' || $mp === 'NO')
			$this->published = false;
		elseif ($mp && $mp !== false && $mp !== 'TRUE' && $mp !== 'YES')
			$this->published = gb_utcstrtotime($mp, $this->published);
		if ($this->published === false)
			$this->published = self::NOT_PUBLISHED;
		
		# apply filters
		$fnext = array_pop(gb_fnsplit($this->name));
		gb::apply_filters('post-reload-GBExposedContent', $this);
		gb::apply_filters('post-reload-GBExposedContent.'.$fnext, $this);
		$cls = get_class($this);
		if ($cls !== 'GBExposedContent') {
			gb::apply_filters('post-reload-'.$cls, $this);
			gb::apply_filters('post-reload-'.$cls.'.'.$fnext, $this);
		}
	}
	
	function urlpath() {
		return str_replace('%2F', '/', urlencode($this->slug));
	}
	
	function url() {
		return GB_SITE_URL . gb::$index_url . $this->urlpath();
	}
	
	function tagLinks($template='<a href="%u">%n</a>', $nglue=', ', $endglue=' and ') {
		return $this->collLinks('tags', $template, $nglue, $endglue);
	}
	
	function categoryLinks($template='<a href="%u">%n</a>', $nglue=', ', $endglue=' and ') {
		return $this->collLinks('categories', $template, $nglue, $endglue);
	}
	
	function collLinks($what, $template='<a href="%u">%n</a>', $nglue=', ', $endglue=' and ', $htmlescape=true) {
		static $needles = array('%u', '%n');
		$links = array();
		$vn = $what.'_prefix';
		$u = GB_SITE_URL . gb::$index_url . gb::$$vn;
		foreach ($this->$what as $tag)
			$links[] = str_replace($needles, array($u.urlencode($tag), $htmlescape ? h($tag) : $tag), $template);
		return $nglue !== null ? sentenceize($links, null, $nglue, $endglue) : $links;
	}
	
	function numberOfComments($topological=true, $sone='comment', $smany='comments', $zero='No', $one='One') {
		return counted($this->comments ? $this->comments->countApproved($topological) : 0,
			$sone, $smany, $zero, $one);
	}
	
	function numberOfShadowedComments($sone='comment', $smany='comments', $zero='No', $one='One') {
		return counted($this->comments ? $this->comments->countShadowed() : 0,
			$sone, $smany, $zero, $one);
	}
	
	function numberOfUnapprovedComments($sone='comment', $smany='comments', $zero='No', $one='One') {
		return counted($this->comments ? $this->comments->countUnapproved() : 0,
			$sone, $smany, $zero, $one);
	}
	
	function __sleep() {
		return array_merge(parent::__sleep(), array(
			'slug','meta','title','body','tags','categories','comments'));
	}
}

/** trim(c->body) */
function gb_filter_post_reload_content(GBExposedContent $c) {
	if ($c->body)
		$c->body = trim($c->body);
	return $c;
}

gb::add_filter('post-reload-GBExposedContent', 'gb_filter_post_reload_content');

/** Converts LF to <br/>LF and extracts excerpt for GBPost objects */
function gb_filter_post_reload_content_html(GBExposedContent $c) {
	if ($c->body) {
		# create excerpt for GBPosts if not already set
		if ($c instanceof GBPost && !$c->excerpt) {
			$p = strpos($c->body, '<!--more-->');
			if ($p !== false) {
				$c->excerpt = substr($c->body, 0, $p);
				$c->body = $c->excerpt
					.'<div id="'.$c->domID().'-more" class="post-more-anchor"></div>'
					.substr($c->body, $p+strlen('<!--more-->'));
			}
		}
		$c->body = gb::apply_filters('body.html', $c->body);
	}
	if ($c instanceof GBPost && $c->excerpt)
		$c->excerpt = gb::apply_filters('excerpt.html', $c->excerpt);
	return $c;
}

gb::add_filter('post-reload-GBExposedContent.html', 'gb_filter_post_reload_content_html');


class GBPage extends GBExposedContent {
	static function mkCachename($slug) {
		return 'content/pages/'.$slug;
	}
	
	static function getCached($slug) {
		$path = GB_SITE_DIR.'/.git/info/gitblog/'.self::mkCachename($slug);
		return @unserialize(file_get_contents($path));
	}
	
	protected function getCachedComments() {
		return GBPageComments::getCached(self::mkCachename());
	}
}


class GBPost extends GBExposedContent {
	public $excerpt;
	
	/**
	 * Return a, possibly cloned, version of this post which contains a minimal
	 * set of information. Primarily used for paged posts pages.
	*/
	function condensedVersion() {
		$c = clone $this;
		# excerpt member turns into a boolean "is ->body an excerpt?"
		if ($c->excerpt) {
			$c->body = $c->excerpt;
			$c->excerpt = true;
		}
		else {
			$c->excerpt = false;
		}
		# comments member turns into an integer "number of comments"
		$c->comments = $c->comments ? $c->comments->countApproved() : 0;
		
		return $c;
	}
	
	function urlpath() {
		return gmstrftime(gb::$posts_url_prefix, $this->published)
			. str_replace('%2F', '/', urlencode($this->slug));
	}
	
	function domID() {
		return 'post-'.preg_replace('/[^A-Za-z0-9_-]+/', '-', 
			gmdate('Y-m-d-', $this->published).$this->slug);
	}
	
	static function mkCachename($published, $slug) {
		# Note: the path prefix is a dependency for GBContentFinalizer::finalize
		return 'content/posts/'.gmdate('Y/m-d-', $published).$slug;
	}
	
	function cachename() {
		return self::mkCachename($this->published, $this->slug);
	}
	
	static function getCached($published, $slug) {
		$path = GB_SITE_DIR.'/.git/info/gitblog/'.self::mkCachename($published, $slug);
		return @unserialize(file_get_contents($path));
	}
	
	function __sleep() {
		return array_merge(parent::__sleep(), array('excerpt'));
	}
}


class GBComments extends GBContent implements IteratorAggregate {
	/** [GBComment, ..] */
	public $comments = array();
	public $cachenamePrefix;
	
	function __construct($name=null, $id=null, $cachenamePrefix=null) {
		parent::__construct($name, $id);
		$this->cachenamePrefix = $cachenamePrefix;
	}
	
	function reload($data, $commits) {
		parent::reload($data, $commits);
		
		# apply info from commits, like publish date and author
		$this->applyInfoFromCommits($commits);
		
		# load actual comments
		$db = new GBCommentDB();
		$db->loadString($data);
		$this->comments = $db->get();
	}
	
	# these two are not serialized, but lazy-initialized by count()
	public $_countTotal;
	public $_countApproved;
	public $_countApprovedTopo;
	
	/** Recursively count how many comments are in the $comments member */
	function count($c=null) {
		if ($c === null)
			$c = $this;
		if ($c->_countTotal !== null)
			return $c->_countTotal;
		$c->_countTotal = $c->_countApproved = $c->_countApprovedTopo = 0;
		if (!$c->comments)
			return 0;
		foreach ($c->comments as $comment) {
			$c->_countTotal++;
			if ($comment->approved) {
				$c->_countApproved++;
				$c->_countApprovedTopo++;
			}
			$this->count($comment);
			$c->_countTotal += $comment->_countTotal;
			if ($comment->approved)
				$c->_countApprovedTopo += $comment->_countApprovedTopo;
			$c->_countApproved += $comment->_countApproved;
		}
		return $c->_countTotal;
	}
	
	function countApproved($topological=true) {
		$this->count();
		return $topological ? $this->_countApprovedTopo : $this->_countApproved;
	}
	
	/**
	 * Number of comments which are approved -- by their parent comments are 
	 * not -- thus they are topologically speaking shadowed, or hidden.
	 */
	function countShadowed() {
		$this->count();
		return $this->_countTotal - ($this->_countApprovedTopo + ($this->_countTotal - $this->_countApproved));
	}
	
	function countUnapproved() {
		$this->count();
		return $this->_countTotal - $this->_countApproved;
	}
	
	function cachename() {
		if (!$this->cachenamePrefix)
			throw new UnexpectedValueException('cachenamePrefix is empty or null');
		return $this->cachenamePrefix.'.comments';
	}
	
	static function getCached($cachenamePrefix) {
		$path = GB_SITE_DIR.'/.git/info/gitblog/'.$cachenamePrefix.'.comments';
		return @unserialize(file_get_contents($path));
	}
	
	# implementation of IteratorAggregate
	public function getIterator($onlyApproved=true) {
		return new GBCommentsIterator($this, $onlyApproved);
	}
	
	function __sleep() {
		return array_merge(parent::__sleep(), array('comments', 'cachenamePrefix'));
	}
}

/**
 * Comments iterator. Accessible from GBComments::iterator
 */
class GBCommentsIterator implements Iterator {
	protected $initialComment;
	protected $comments;
	protected $stack;
	protected $idstack;
	public $onlyApproved;
	public $maxStackDepth;
	
	function __construct($comment, $onlyApproved=false, $maxStackDepth=50) {
		$this->initialComment = $comment;
		$this->onlyApproved = $onlyApproved;
		$this->maxStackDepth = $maxStackDepth;
	}
	
	function rewind() {
		$this->comments = $this->initialComment->comments;
		reset($this->comments);
		$this->stack = array();
		$this->idstack = array();			
	}

	function current() {
		$comment = current($this->comments);
		if ($comment && $comment->id === null) {
			$comment->id = $this->idstack;
			$comment->id[] = key($this->comments);
			$comment->id = implode('.', $comment->id);
		}
		return $comment;
	}

	function key() {
		return count($this->idstack);
	}
	
	function next() {
		$comment = current($this->comments);
		if ($comment === false)
			return;
		if ($comment->comments && (!$this->onlyApproved || ($this->onlyApproved && $comment->approved)) ) {
			# push current comments on stack
			if (count($this->stack) === $this->maxStackDepth)
				throw new OverflowException('stack depth > '.$this->maxStackDepth);
			array_push($this->stack, $this->comments);
			array_push($this->idstack, key($this->comments));
			$this->comments = $comment->comments;
		}
		else {
			next($this->comments);
		}
		
		# fast-forward to next approved comment, if applicable
		if ($this->onlyApproved) {
			$comment = current($this->comments);
			if ($comment && !$comment->approved) {
				#var_dump('FWD from unapproved comment '.$this->current()->id);
				$this->next();
			}
			elseif (!$comment) {
				#var_dump('VALIDATE');
				$this->valid();
				$comment = current($this->comments);
				if ($comment === false || !$comment->approved)
					$this->next();
			}
		}
	}

	function valid() {
		if (key($this->comments) === null) {
			if ($this->stack) {
				# end of branch -- pop the stack
				while ($this->stack) {
					array_pop($this->idstack);
					$this->comments = array_pop($this->stack);
					next($this->comments);
					if (key($this->comments) !== null || !$this->stack)
						break;
				}
				return key($this->comments) !== null;
			}
			return false;
		}
		return true;
	}
}


/**
 * Comments object.
 * 
 * Accessible from GBComments
 * Managed through GBCommentDB
 */
class GBComment {
	const TYPE_COMMENT = 'c';
	const TYPE_PINGBACK = 'p';
	
	public $date;
	public $ipAddress;
	public $email;
	public $uri;
	public $name;
	public $message;
	public $approved;
	public $comments;
	public $type;
	
	# members below are not serialized
	
	/** String id (indexpath), available during iteration */
	public $id;
	
	/* these two are not serialized, but lazy-initialized by GBComments::count() */
	public $_countTotal;
	public $_countApproved;
	public $_countApprovedTopo;
	
	function __construct($state=array()) {
		$this->type = self::TYPE_COMMENT;
		if ($state) {
			foreach ($state as $k => $v) {
				if ($k === 'comments' && $v !== null)
					foreach ($v as $k2 => $v2)
						$v[$k2] = new self($v2);
				$this->$k = $v;
			}
		}
	}
	
	function gitAuthor() {
		return ($this->name ? $this->name.' ' : '').($this->email ? '<'.$this->email.'>' : '');
	}
	
	function append(GBComment $comment) {
		if ($this->comments === null)
			$this->comments = array(1 => $comment);
		else
			$this->comments[array_pop(array_keys($this->comments))+1] = $comment;
	}
	
	function __sleep() {
		return array('date','ipAddress','email','uri','name','message','approved','comments');
	}
}

# -----------------------------------------------------------------------------
# Users

# todo: use JSONDB for GBUserAccount
class GBUserAccount {
	public $name;
	public $email;
	public $passhash;
	
	function __construct($name=null, $email=null, $passhash=null) {
		$this->name = $name;
		$this->email = $email;
		$this->passhash = $passhash;
	}
	
	static function __set_state($state) {
		$o = new self;
		foreach ($state as $k => $v)
			$o->$k = $v;
		return $o;
	}
	
	static public $db = null;
	
	static function _reload() {
		if (file_exists(GB_SITE_DIR.'/.git/info/gitblog-users.php')) {
			include GB_SITE_DIR.'/.git/info/gitblog-users.php';
			self::$db = $db;
		}
		else {
			self::$db = array();
		}
	}
	
	static function sync() {
		if (self::$db === null)
			return;
		$r = file_put_contents(GB_SITE_DIR.'/.git/info/gitblog-users.php', 
			'<? $db = '.var_export(self::$db, 1).'; ?>', LOCK_EX);
		chmod(GB_SITE_DIR.'/.git/info/gitblog-users.php', 0660);
		return $r;
	}
	
	static function passhash($email, $passphrase) {
		return sha1($email . ' ' . $passphrase);# . ' ' . gb::$secret);
	}
	
	static function create($email, $passphrase, $name, $admin=false) {
		if (self::$db === null)
			self::_reload();
		$email = strtolower($email);
		self::$db[$email] = new GBUserAccount($name, $email, self::passhash($email, $passphrase));
		if ($admin && !self::setAdmin($email))
			return false;
		return self::sync() ? true : false;
	}
	
	static function setAdmin($email) {
		if (self::$db === null)
			self::_reload();
		$email = strtolower($email);
		if (!isset(self::$db[$email])) {
			trigger_error('no such user '.var_export($email,1));
			return false;
		}
		self::$db['_admin'] = $email;
		return true;
	}
	
	static function &getAdmin() {
		static $n = null;
		if (self::$db === null)
			self::_reload();
		if (!isset(self::$db['_admin']))
			return $n;
		$email = self::$db['_admin'];
		if (isset(self::$db[$email]))
		 	return self::$db[$email];
		return $n;
	}
	
	static function &get($email) {
		static $n = null;
		if (self::$db === null)
			self::_reload();
		$email = strtolower($email);
		if (isset(self::$db[$email]))
		 	return self::$db[$email];
		return $n;
	}
	
	static function formatGitAuthor($account) {
		if (!$account) {
			trigger_error('invalid account');
			return '';
		}
		$s = '';
		if ($account->name)
			$s = $account->name . ' ';
		return $s . '<'.$account->email.'>';
	}
	
	function gitAuthor() {
		return self::formatGitAuthor($this);
	}
}

# -----------------------------------------------------------------------------
# General filters

# Convert short-hands to nice unicode characters.
# Shamelessly borrowed from my worst nightmare Wordpress.
function gb_texturize_html($text) {
	$next = true;
	$has_pre_parent = false;
	$output = '';
	$curl = '';
	$textarr = preg_split('/(<.*>|\[.*\])/Us', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
	$stop = count($textarr);
	
	static $static_characters = array(
		'---', ' -- ', '--', "xn\xe2\x80\x93", '...', '``', '\'s', '\'\'', ' (tm)',
		# cockney:
		"'tain't","'twere","'twas","'tis","'twill","'til","'bout",
		"'nuff","'round","'cause");
	static $static_replacements = array("\xe2\x80\x94"," \xe2\x80\x94 ",
		"\xe2\x80\x93","xn--","\xe2\x80\xa6","\xe2\x80\x9c","\xe2\x80\x99s",
		"\xe2\x80\x9d"," \xe2\x84\xa2",
		# cockney
		"\xe2\x80\x99tain\xe2\x80\x99t","\xe2\x80\x99twere",
		"\xe2\x80\x99twas","\xe2\x80\x99tis","\xe2\x80\x99twill","\xe2\x80\x99til",
		"\xe2\x80\x99bout","\xe2\x80\x99nuff","\xe2\x80\x99round","\xe2\x80\x99cause");
	
	static $dynamic_characters = array('/\'(\d\d(?:&#8217;|\')?s)/', '/(\s|\A|")\'/', '/(\d+)"/', '/(\d+)\'/',
	 	'/(\S)\'([^\'\s])/', '/(\s|\A)"(?!\s)/', '/"(\s|\S|\Z)/', '/\'([\s.]|\Z)/', '/(\d+)x(\d+)/');
	static $dynamic_replacements = array("\xe2\x80\x99\$1","\$1\xe2\x80\x98","\$1\xe2\x80\xb3","\$1\xe2\x80\xb2",
		"\$1\xe2\x80\x99$2","\$1\xe2\x80\x9c\$2","\xe2\x80\x9d\$1","\xe2\x80\x99\$1","\$1\xc3\x97\$2");
	
	for ( $i = 0; $i < $stop; $i++ ) {
		$curl = $textarr[$i];
		
		if (isset($curl{0}) && '<' != $curl{0} && '[' != $curl{0} && $next && !$has_pre_parent)
		{ # If it's not a tag
			# static strings
			$curl = str_replace($static_characters, $static_replacements, $curl);
			# regular expressions
			$curl = preg_replace($dynamic_characters, $dynamic_replacements, $curl);
		} elseif (strpos($curl, '<code') !== false || strpos($curl, '<kbd') !== false
			|| strpos($curl, '<style') !== false || strpos($curl, '<script') !== false)
		{
			$next = false;
		} elseif (strpos($curl, '<pre') !== false) {
			$has_pre_parent = true;
		} elseif (strpos($curl, '</pre>') !== false) {
			$has_pre_parent = false;
		} else {
			$next = true;
		}
		
		$curl = preg_replace('/&([^#])(?![a-zA-Z1-4]{1,8};)/', '&#038;$1', $curl);
		$output .= $curl;
	}
	
	return $output;
}

function gb_convert_html_chars($content) {
	# Translation of invalid Unicode references range to valid range,
	# often added by Windows programs after a copy-paste.
	static $wp_htmltranswinuni = array(
	'&#128;' => '&#8364;', # the Euro sign
	'&#129;' => '',
	'&#130;' => '&#8218;', # these are Windows CP1252 specific characters
	'&#131;' => '&#402;',  # they would look weird on non-Windows browsers
	'&#132;' => '&#8222;',
	'&#133;' => '&#8230;',
	'&#134;' => '&#8224;',
	'&#135;' => '&#8225;',
	'&#136;' => '&#710;',
	'&#137;' => '&#8240;',
	'&#138;' => '&#352;',
	'&#139;' => '&#8249;',
	'&#140;' => '&#338;',
	'&#141;' => '',
	'&#142;' => '&#382;',
	'&#143;' => '',
	'&#144;' => '',
	'&#145;' => '&#8216;',
	'&#146;' => '&#8217;',
	'&#147;' => '&#8220;',
	'&#148;' => '&#8221;',
	'&#149;' => '&#8226;',
	'&#150;' => '&#8211;',
	'&#151;' => '&#8212;',
	'&#152;' => '&#732;',
	'&#153;' => '&#8482;',
	'&#154;' => '&#353;',
	'&#155;' => '&#8250;',
	'&#156;' => '&#339;',
	'&#157;' => '',
	'&#158;' => '',
	'&#159;' => '&#376;'
	);
	
	# Converts lone & characters into &#38; (a.k.a. &amp;)
	$content = preg_replace('/&([^#])(?![a-z1-4]{1,8};)/i', '&#038;$1', $content);
	
	# Fix Microsoft Word pastes
	$content = strtr($content, $wp_htmltranswinuni);
	
	return $content;
}

# HTML -> XHTML
function gb_html_to_xhtml($content) {
	return str_replace(array('<br>','<hr>'), array('<br />','<hr />'), $content);
}

# LF => <br />, etc
function gb_normalize_html_structure($pee, $br = 1) {
	$pee = $pee . "\n"; // just to make things a little easier, pad the end
	$pee = preg_replace('|<br />\s*<br />|', "\n\n", $pee);
	// Space things out a little
	$allblocks = '(?:table|thead|tfoot|caption|colgroup|tbody|tr|td|th|div|dl|dd|dt|ul|ol|li|pre|select|form|map|area|blockquote|address|math|style|input|p|h[1-6]|hr)';
	$pee = preg_replace('!(<' . $allblocks . '[^>]*>)!', "\n$1", $pee);
	$pee = preg_replace('!(</' . $allblocks . '>)!', "$1\n\n", $pee);
	$pee = str_replace(array("\r\n", "\r"), "\n", $pee); // cross-platform newlines
	if ( strpos($pee, '<object') !== false ) {
		$pee = preg_replace('|\s*<param([^>]*)>\s*|', "<param$1>", $pee); // no pee inside object/embed
		$pee = preg_replace('|\s*</embed>\s*|', '</embed>', $pee);
	}
	$pee = preg_replace("/\n\n+/", "\n\n", $pee); // take care of duplicates
	$pee = preg_replace('/\n?(.+?)(?:\n\s*\n|\z)/s', "<p>$1</p>\n", $pee); // make paragraphs, including one at the end
	$pee = preg_replace('|<p>\s*?</p>|', '', $pee); // under certain strange conditions it could create a P of entirely whitespace
	$pee = preg_replace('!<p>([^<]+)\s*?(</(?:div|address|form)[^>]*>)!', "<p>$1</p>$2", $pee);
	$pee = preg_replace( '|<p>|', "$1<p>", $pee );
	$pee = preg_replace('!<p>\s*(</?' . $allblocks . '[^>]*>)\s*</p>!', "$1", $pee); // don't pee all over a tag
	$pee = preg_replace("|<p>(<li.+?)</p>|", "$1", $pee); // problem with nested lists
	$pee = preg_replace('|<p><blockquote([^>]*)>|i', "<blockquote$1><p>", $pee);
	$pee = str_replace('</blockquote></p>', '</p></blockquote>', $pee);
	$pee = preg_replace('!<p>\s*(</?' . $allblocks . '[^>]*>)!', "$1", $pee);
	$pee = preg_replace('!(</?' . $allblocks . '[^>]*>)\s*</p>!', "$1", $pee);
	if ($br) {
		$pee = preg_replace_callback('/<(script|style).*?<\/\\1>/s', create_function('$matches', 'return str_replace("\n", "<WPPreserveNewline />", $matches[0]);'), $pee);
		$pee = preg_replace('|(?<!<br />)\s*\n|', "<br />\n", $pee); // optionally make line breaks
		$pee = str_replace('<WPPreserveNewline />', "\n", $pee);
	}
	$pee = preg_replace('!(</?' . $allblocks . '[^>]*>)\s*<br />!', "$1", $pee);
	$pee = preg_replace('!<br />(\s*</?(?:p|li|div|dl|dd|dt|th|pre|td|ul|ol)[^>]*>)!', '$1', $pee);
	if (strpos($pee, '<pre') !== false)
		$pee = preg_replace_callback('!(<pre.*?>)(.*?)</pre>!is', 'clean_pre', $pee );
	$pee = preg_replace( "|\n</p>$|", '</p>', $pee );
	#$pee = preg_replace('/<p>\s*?(' . get_shortcode_regex() . ')\s*<\/p>/s', '$1', $pee); // don't auto-p wrap shortcodes that stand alone

	return $pee;
}

# Applied to GBExposedContent->body
gb::add_filter('body.html', 'gb_texturize_html');
gb::add_filter('body.html', 'gb_convert_html_chars');
gb::add_filter('body.html', 'gb_html_to_xhtml');
gb::add_filter('body.html', 'gb_normalize_html_structure');

# Applied to GBExposedContent->excerpt
gb::add_filter('excerpt.html', 'gb_texturize_html');
gb::add_filter('excerpt.html', 'gb_convert_html_chars');
gb::add_filter('excerpt.html', 'gb_html_to_xhtml');
gb::add_filter('excerpt.html', 'gb_normalize_html_structure');

# -----------------------------------------------------------------------------
# Template helpers

/**
 * Ordinalize turns a number into an ordinal string used to denote the
 * position in an ordered sequence such as 1st, 2nd, 3rd, 4th.
 * 
 * Examples
 *  ordinalize(1)     -> "1st"
 *  ordinalize(2)     -> "2nd"
 *  ordinalize(1002)  -> "1002nd"
 *  ordinalize(1003)  -> "1003rd"
 */
function ordinalize($number) {
	$i = intval($number);
	$h = $i % 100;
	if ($h === 11 || $h === 12 || $h === 13)
		return $i.'th';
	else {
		$x = $i % 10;
		if ($x === 1)
			return $i.'st';
		elseif ($x === 2)
			return $i.'nd';
		elseif ($x === 3)
			return $i.'rd';
		else
			return $i.'th';
	}
}

/**
 * Counted turns a number into $zero if $n is 0, $one if $n is 1 or
 * otherwise $n
 * 
 * Examples:
 *  counted(0)  -> "No"
 *  counted(1, 'No', 'One', 'comment', 'comments')  -> "One comment"
 *  counted(7, '', '', ' comments')  -> "7 comments"
 */
function counted($n, $sone='', $smany='', $zero='No', $one='One') {
	if ($sone)
		$sone = ' '.ltrim($sone);
	if ($smany)
		$smany = ' '.ltrim($smany);
	return $n === 0 ? $zero.$smany : ($n === 1 ? $one.$sone : strval($n).$smany);
}

function sentenceize($collection, $applyfunc=null, $nglue=', ', $endglue=' and ') {
	if (!$collection)
		return '';
	if ($applyfunc)
		$collection = array_map($applyfunc, $collection);
	$n = count($collection);
	if ($n === 1)
		return $collection[0];
	else {
		$end = array_pop($collection);
		return implode($nglue, $collection).$endglue.$end;
	}
}


# -----------------------------------------------------------------------------
# Request handler

if (isset($gb_handle_request) && $gb_handle_request) {
	$gb_urlpath = isset($_SERVER['PATH_INFO']) ? trim($_SERVER['PATH_INFO'], '/') : '';

	# verify integrity, implicitly rebuilding gitblog cache or need serious initing.
	if (GitBlog::verifyIntegrity() === 2) {
		header("Location: ".GB_SITE_URL."gitblog/admin/setup.php");
		exit(0);
	}

	# verify configuration, like validity of the secret key.
	GitBlog::verifyConfig();

	if ($gb_urlpath) {
		if (strpos($gb_urlpath, gb::$tags_prefix) === 0) {
			# tag(s)
			$tags = array_map('urldecode', explode(',', substr($gb_urlpath, strlen(gb::$tags_prefix))));
			$posts = GitBlog::postsByTags($tags);
			gb::$is_tags = true;
		}
		elseif (strpos($gb_urlpath, gb::$categories_prefix) === 0) {
			# category(ies)
			$cats = array_map('urldecode', explode(',', substr($gb_urlpath, strlen(gb::$categories_prefix))));
			$posts = GitBlog::postsByCategories($cats);
			gb::$is_categories = true;
		}
		elseif (strpos($gb_urlpath, gb::$feed_prefix) === 0) {
			# feed
			$postspage = GitBlog::postsPageByPageno(0);
			gb::$is_feed = true;
			# todo: check if theme has a custom feed, else use a standard feed renderer
		}
		elseif (preg_match(gb::$posts_url_prefix_re, $gb_urlpath)) {
			# post
			$post = GitBlog::postBySlug(urldecode($gb_urlpath));
			if ($post === false)
				gb::$is_404 = true;
			elseif ($post->published > time())
				gb::$is_404 = true;
			else
				gb::$title[] = $post->title;
			gb::$is_post = true;
		}
		else {
			# page
			$post = GitBlog::pageBySlug(urldecode($gb_urlpath));
			if ($post === false)
				gb::$is_404 = true;
			else
				gb::$title[] = $post->title;
			gb::$is_page = true;
		}
	}
	else {
		# posts
		$pageno = isset($_REQUEST['page']) ? intval($_REQUEST['page']) : 0;
		$postspage = GitBlog::postsPageByPageno($pageno);
		gb::$is_posts = true;
	}
	# from here on, the caller will have to do the rest
}
?>