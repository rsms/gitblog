<?
error_reporting(E_ALL);
$gb_time_started = microtime(true);

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
	static public $posts_prefix = '%Y/%m/%d/';

	/** URL prefix for pages */
	static public $pages_prefix = '';

	/** Number of posts per page. */
	static public $posts_pagesize = 10;
	
	/** URL to gitblog index _relative_ to GB_SITE_URL */
	static public $index_url = 'index.php/';
	
	/**
	 * Log messages of priority >=$log_filter will be sent to syslog.
	 * Disable logging by setting this to -1.
	 * See the "Logging" section in gitblog.php for more information.
	 */
	static public $log_filter = LOG_NOTICE;
	
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
	# Logging
	static public $log_open = false;
	
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
	
	# --------------------------------------------------------------------------
	# Info about the Request
	
	static protected $current_url = null;
	
	static function url_to($part) {
		$v = $part.'_prefix';
		return GB_SITE_URL.self::$index_url.self::$$v;
	}
	
	static function url() {
		if (self::$current_url === null) {
			$u = new GBURL();
			$u->secure = isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] == 'on');
			$u->scheme = $u->secure ? 'https' : 'http';
			$u->host = isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] :
			  	(isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost');
			if(($p = strpos($u->host,':')) !== false) {
				$u->port = intval(substr($u->host, $p+1));
				$u->host = substr($u->host, 0, $p);
			}
			elseif(isset($_SERVER['SERVER_PORT'])) {
				$u->port = intval($_SERVER['SERVER_PORT']);
			}
			else {
				$u->port = $u->secure ? 443 : 80;
			}
			$u->query = isset($_SERVER['QUERY_STRING']) ? $_SERVER['QUERY_STRING'] : '';
			$u->path = $u->query ? substr(@$_SERVER['REQUEST_URI'], 0, strpos($_SERVER['REQUEST_URI'],'?')) 
				: rtrim(@$_SERVER['REQUEST_URI'],'?');
			self::$current_url = $u;
		}
		return self::$current_url;
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
# Utilities

class GBURL {
	public $scheme;
	public $host;
	public $secure;
	public $port;
	public $path = '/';
	public $query;
	public $fragment;
	
	function __construct($url=null) {
		if ($url !== null) {
			$p = @parse_url($url);
			if ($p === false)
				throw new InvalidArgumentException('unable to parse URL '.var_export($url,1));
			foreach ($p as $k => $v)
				$this->$k = $v;
			$this->secure = $this->scheme === 'https';
			if ($this->port === null)
				$this->port = $this->scheme === 'https' ? 443 : ($this->scheme === 'http' ? 80 : null);
		}
	}
	
	function __toString($query=true, $path=true, $host=true, $port=true) {
		$s = $this->scheme . '://';
		
		if ($host === true)
			$s .= $this->host;
		elseif ($host !== false)
			$s .= $host;
		
		if ($port === true && $this->port !== null && ($this->secure === true && $this->port !== 443) || ($this->secure === false && $this->port !== 80))
			$s .= ':' . $this->port;
		elseif ($port !== true && $port !== false)
			$s .= ':' . $port;
		
		if ($path === true)
			$s .= $this->path;
		elseif ($path !== false)
			$s .= $path;
		
		if ($query === true && $this->query)
			$s .= '?'.$this->query;
		elseif ($port !== true && $query !== false && $query)
			$s .= '?'.$query;
		
		return $s;
	}
}

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


/** Normalize $time (any format strtotime can handle) to a ISO timestamp. */
function gb_strtoisotime($time) {
	$d = new DateTime($time);
	return $d->format('c');
}

function gb_hash($data) {
	return base_convert(hash_hmac('sha1', $data, gb::$secret), 16, 36);
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

function gb_strbool($s) {
	$s = strtoupper($s);
	return ($s === 'TRUE' || $s === 'YES' || $s === '1' || $s === 'ON');
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
			$msg = trim($r[1]."\n".$r[2]);
			if (strpos($r[2], 'Not a git repository') !== false)
				throw new GitUninitializedRepoError($msg);
			else
				throw new GitError($msg);
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
	
	static function pathToPost($path, $strptime=null) {
		$st = ($strptime !== null) ? $strptime : strptime($path, gb::$posts_prefix);
		$date = gmmktime($st['tm_hour'], $st['tm_min'], $st['tm_sec'], 
			$st['tm_mon']+1, $st['tm_mday'], 1900+$st['tm_year']);
		$cachename = gmstrftime('%Y/%m-%d-', $date).$st['unparsed'];
		return self::pathToCachedContent('posts', $cachename);
	}
	
	static function pageBySlug($slug) {
		$path = self::pathToCachedContent('pages', $slug);
		return @unserialize(file_get_contents($path));
	}
	
	static function postBySlug($slug, $strptime=null) {
		$path = self::pathToPost($slug, $strptime);
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
	
	static function reset($pathspec=null, $commitobj=null, $flags='-q') {
		if ($pathspec) {
			if (is_array($pathspec))
				$pathspec = implode(' ', array_map('escapeshellarg',$pathspec));
			else
				$pathspec = escapeshellarg($pathspec);
			$pathspec = ' '.$pathspec;
		}
		$commitargs = '';
		if ($commitobj) {
			$badtype = false;
			if (!is_array($commitobj))
				$commitobj = array($commitobj);
			foreach ($commitobj as $c) {
				if (is_object($c)) {
					if (strtolower(get_class($c)) !== 'GitCommit')
						$badtype = true;
					else
						$commitargs .= ' '.escapeshellarg($c->id);
				}
				elseif (is_string($c))
					$commitargs .= escapeshellarg($c);
				else
					$badtype = true;
				if ($badtype)
					throw new InvalidArgumentException('$commitobj argument must be a string, a GitCommit '
						.'object or an array of any of the two mentioned types');
			}
		}
		self::exec('reset '.$flags.' '.$commitargs.' --'.$pathspec);
	}
	
	static function commit($message, $author=null) {
		$author = $author ? '--author='.escapeshellarg($author) : '';
		self::exec('commit -m '.escapeshellarg($message).' --quiet '.$author);
		@chmod(GB_SITE_DIR.'/.git/COMMIT_EDITMSG', 0664);
		return true;
	}
	
	static function writeSiteStateCache() {
		# format: <0 site url> SP <1 version> SP <2 urlencoded posts_prefix> SP <3 posts_pagesize>
		$state = implode(' ',array(
			GB_SITE_URL,
			GB_VERSION,
			urlencode(gb::$posts_prefix),
			gb::$posts_pagesize
		));
		$dst = GB_SITE_DIR.'/.git/info/gitblog-site-state';
		gb::log(LOG_NOTICE, 'wrote site state to '.$dst);
		return gb_atomic_write($dst, $state, 0664);
	}
	
	static function upgradeCache($fromVersion, $rebuild) {
		gb::log(LOG_NOTICE, 'upgrading cache from gitblog '.$fromVersion.' -> gitblog '.GB_VERSION);
		self::writeSiteStateCache();
		if ($rebuild)
			GBRebuilder::rebuild(true);
		gb::log(LOG_NOTICE, 'upgrade of cache to gitblog '.GB_VERSION.' complete');
	}
	
	/**
	 * Verify integrity of repository and gitblog cache.
	 * 
	 * Return values:
	 *   0  Nothing was done (everything is probably OK).
	 *   -1 Error (the error has been logged through trigger_error).
	 *   1  gitblog cache was updated.
	 *   2  gitdir is missing and need to be created (git init).
	 *   3  upgrade performed
	 */
	static function verifyIntegrity() {
		$r = 0;
		if (!is_dir(GB_SITE_DIR.'/.git/info/gitblog')) {
			if (!is_dir(GB_SITE_DIR.'/.git'))
				return 2; # no repo/not initialized
			self::writeSiteStateCache();
			GBRebuilder::rebuild(true);
			$r = 1;
		}
		
		# check site state
		# format: <0 site url> SP <1 version> SP <2 urlencoded posts_prefix> SP <3 posts_pagesize>
		$state = @file_get_contents(GB_SITE_DIR.'/.git/info/gitblog-site-state');
		$state = $state ? explode(' ', $state) : false;
		if (!$state) {
			# here the version MIGHT have changed. We don't really know, but we're optimistic.
			self::writeSiteStateCache();
		}
		# prio 1: version mismatch causes cache upgrade and possibly a rebuild.
		elseif ($state[1] !== GB_VERSION) {
			self::upgradeCache($state[1], $r !== 1);
			$r = 3;
		}
		# prio 2: some part which do affect cache state have changed and we need
		#         to issue a rebuild to some extent (currently we perform a full
		#         rebuild).
		elseif (@intval($state[3]) !== gb::$posts_pagesize) {
			self::writeSiteStateCache();
			GBRebuilder::rebuild(true);
			$r = 1;
		}
		# prio 3: some part which does not affect cache state have changed. We only
		#         need to write an updated state file.
		elseif ($state[0] !== GB_SITE_URL) {
			self::writeSiteStateCache();
		}
		
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

class GBDateTime {
	public $time;
	public $offset;
	
	function __construct($time=null, $offset=null) {
		if ($time === null || is_int($time)) {
			$this->time = ($time === null) ? time() : $time;
			$this->offset = ($offset === null) ? self::localTimezoneOffset() : $offset;
		}
		else {
			$st = date_parse($time);
			if (isset($st['zone']) && $st['zone'] !== 0)
				$this->offset = -$st['zone']*60;
			if (isset($st['is_dst']) && $st['is_dst'] === true)
				$this->offset += 3600;
			$this->time = gmmktime($st['hour'], $st['minute'], $st['second'], 
				$st['month'], $st['day'], $st['year']);
			if ($this->offset !== null)
				$this->time -= $this->offset;
			else
				$this->offset = 0;
		}
	}
	
	function format($format='%FT%H:%M:%S%z') {
		return strftime($format, $this->time);
	}

	function utcformat($format='%FT%H:%M:%SZ') {
		return gmstrftime($format, $this->time);
	}
	
	function origformat($format='%FT%H:%M:%S', $tzformat='H:i') {
		return gmstrftime($format, $this->time + $this->offset)
			. self::formatTimezoneOffset($this->offset, $tzformat);
	}
	
	/**
	 * The offset for timezones west of UTC is always negative, and for those
	 * east of UTC is always positive.
	 */
	static function localTimezoneOffset() {
		$tod = gettimeofday();
		return -($tod['minuteswest']*60);
	}
	
	static function formatTimezoneOffset($offset, $format='H:i') {
		return ($offset < 0) ? '-'.gmdate($format, -$offset) : '+'.gmdate($format, $offset);
	}
	
	function __toString() {
		return $this->origformat();
	}
	
	function __sleep() {
		$this->d = gmstrftime('%FT%H:%M:%SZ', $this->time);
		return array('d', 'offset');
	}
	
	function __wakeup() {
		$st = strptime($this->d, '%FT%H:%M:%SZ');
		$this->time = gmmktime($st['tm_hour'], $st['tm_min'], $st['tm_sec'], 
			$st['tm_mon']+1, $st['tm_mday'], 1900+$st['tm_year']);
		unset($this->d);
	}
	
	function reintrepretTimezone($tzoffset) {
		$gmts = $this->offset === 0 ? $this->time : strtotime($this->utcformat());
		$ds = gmstrftime('%FT%H:%M:%S', $gmts+$tzoffset) . self::formatTimezoneOffset($tzoffset);
		return new GBDateTime($ds);
	}
	
	function mergeString($s, $adjustTimezone=false) {
		$t = date_parse($s);
		$ds = '';
		if ($t['hour'] !== false)
			$ds = sprintf('%02d:%02d:%02d', $t['hour'],$t['minute'],$t['second']);
		else
			$ds = $this->utcformat('%H:%M:%S');
		$tzoffset = 0;
		if (isset($t['zone'])) {
			$tzoffset = -($t['zone']*60);
			$ds .= self::formatTimezoneOffset($tzoffset);
		}
		else {
			$ds .= self::formatTimezoneOffset($this->offset);
		}
		
		if ($adjustTimezone)
			$default = explode('-',gmstrftime('%F', strtotime($this->utcformat('%F'))+$tzoffset));
		else
			$default = explode('-',$this->utcformat('%F'));
		
		$ds = (($t['year'] !== false) ? $t['year'] : $default[0]). '-'
			. (($t['month'] !== false) ? $t['month'] : $default[1]). '-'
			. (($t['day'] !== false) ? $t['day'] : $default[2])
			. 'T' . $ds;
		
		return new GBDateTime($ds);
	}
}


# -----------------------------------------------------------------------------
# Content (posts, pages, etc)


class GBContent {
	public $name; # relative to root tree
	public $id;
	public $mimeType = null;
	public $author = null;
	public $modified = false; # GBDateTime
	public $published = false; # GBDateTime
	
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
			$this->published = new GBDateTime(
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
	public $draft = false;
	
	function __construct($name=null, $id=null, $slug=null, $meta=array(), $body=null) {
		parent::__construct($name, $id);
		$this->slug = $slug;
		$this->meta = $meta;
		$this->body = $body;
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
			self::parseMetaHeaders(substr($data, 0, $bodystart), $this->meta);
		
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
		$mp = false;
		if (isset($this->meta['publish'])) {
			$mp = $this->meta['publish'];
			unset($this->meta['publish']);
		}
		elseif (isset($this->meta['published'])) {
			$mp = $this->meta['published'];
			unset($this->meta['published']);
		}
		if ($mp) {
			$mp = strtoupper($mp);
			if ($mp === 'FALSE' || $mp === 'NO' || $mp === '0')
				$this->draft = true;
			elseif ($mp && $mp !== false && $mp !== 'TRUE' && $mp !== 'YES' && $mp !== '1')
				$this->published = $this->published->mergeString($mp);
		}
		
		# handle draft meta tag
		if (isset($this->meta['draft'])) {
			$s = rtrim($this->meta['draft']);
			unset($this->meta['draft']);
			$this->draft = ($s === '' || gb_strbool($s));
		}
		
		# apply filters
		$fnext = array_pop(gb_fnsplit($this->name));
		GBFilter::apply('post-reload-GBExposedContent', $this);
		GBFilter::apply('post-reload-GBExposedContent.'.$fnext, $this);
		$cls = get_class($this);
		if ($cls !== 'GBExposedContent') {
			GBFilter::apply('post-reload-'.$cls, $this);
			GBFilter::apply('post-reload-'.$cls.'.'.$fnext, $this);
		}
	}
	
	function urlpath() {
		return str_replace('%2F', '/', urlencode($this->slug));
	}
	
	function url() {
		return GB_SITE_URL . gb::$index_url . $this->urlpath();
	}
	
	function commentsStageName() {
		return gb_filenoext($this->name).'.comments';
	}
	
	function getCommentsDB() {
		return new GBCommentDB(GB_SITE_DIR.'/'.$this->commentsStageName());
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
	
	static function parseMetaHeaders($lines, &$out) {
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
				$out[strtolower($k)] = ltrim($line[1]);
			}
		}
	}
	
	static function findByCacheName($cachename) {
		return @unserialize(file_get_contents(GB_SITE_DIR.'/.git/info/gitblog/'.$cachename));
	}
}


class GBPage extends GBExposedContent {
	public $order = 0; # order in menu, etc.
	
	static function mkCachename($slug) {
		return 'content/pages/'.$slug;
	}
	
	static function getCached($slug) {
		$path = GB_SITE_DIR.'/.git/info/gitblog/'.self::mkCachename($slug);
		return @unserialize(file_get_contents($path));
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
		return $this->published->utcformat(gb::$posts_prefix)
			. str_replace('%2F', '/', urlencode($this->slug));
	}
	
	function domID() {
		return 'post-'.$this->published->utcformat('%Y-%m-%d-')
			. preg_replace('/[^A-Za-z0-9_-]+/', '-', $this->slug);
	}
	
	static function mkCachename($published, $slug) {
		# Note: the path prefix is a dependency for GBContentFinalizer::finalize
		return 'content/posts/'.$published->utcformat('%Y/%m-%d-').$slug;
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
		
		# apply filters
		GBFilter::apply('post-reload-comments', $this);
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
	public $body;
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
				if ($k === 'comments' && $v !== null) {
					foreach ($v as $k2 => $v2)
						$v[$k2] = new self($v2);
				}
				elseif ($k === 'date' && $v !== null) {
					if (is_string($v))
					 	$v = new GBDateTime($v);
				 	elseif (is_array($v))
						$v = new GBDateTime($v['time'], $v['offset']);
				}
				$this->$k = $v;
			}
		}
	}
	
	function same(GBComment $comment) {
		return (($this->email === $comment->email) && ($this->body === $comment->body));
	}
	
	function gitAuthor() {
		return ($this->name ? $this->name.' ' : '').($this->email ? '<'.$this->email.'>' : '');
	}
	
	function append(GBComment $comment) {
		if ($this->comments === null) {
			$k = 1;
			$this->comments = array(1 => $comment);
		}
		else {
			$k = array_pop(array_keys($this->comments))+1;
			$this->comments[$k] = $comment;
		}
		return $k;
	}
	
	function nameLink($attrs='') {
		if ($this->uri)
			return '<a href="'.h($this->uri).'" '.$attrs.'>'.h($this->name).'</a>';
		elseif ($attrs)
			return '<span '.$attrs.'>'.h($this->name).'</span>';
		else
			return h($this->name);
	}
	
	function __sleep() {
		return array('date','ipAddress','email','uri','name','body','approved','comments');
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
		return gb_hash($email . ' ' . $passphrase);
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
# Nonce

function gb_nonce_time() {
	static $nonce_life = 86400;
	return (int)ceil(time() / ( $nonce_life / 2 ));
}

function gb_nonce_make($context='') {
	return gb_hash(gb_nonce_time() . $context . $_SERVER['REMOTE_ADDR']);
}

function gb_nonce_verify($nonce, $context='') {
	$nts = gb_nonce_time();
	# generated (0-12] hours ago
	if ( gb_hash($nts . $context . $_SERVER['REMOTE_ADDR']) === $nonce )
		return 1;
	# generated (12-24) hours ago
	if ( gb_hash(($nts - 1) . $context . $_SERVER['REMOTE_ADDR']) === $nonce )
		return 2;
	# Invalid nonce
	return false;
}

# -----------------------------------------------------------------------------
# Author cookie

class gb_author_cookie {
	static public $cookie;
	
	static function set($email=null, $name=null, $uri=null, $cookiename='gb-author') {
		if (self::$cookie === null)
			self::$cookie = array();
		if ($email !== null) self::$cookie['email'] = $email;
		if ($name !== null) self::$cookie['name'] = $name;
		if ($uri !== null) self::$cookie['uri'] = $uri;
		$cookie = rawurlencode(serialize(self::$cookie));
		$cookieurl = new GBURL(GB_SITE_URL);
		setrawcookie($cookiename, $cookie, time()+(3600*24*365), $cookieurl->path, $cookieurl->host, $cookieurl->secure);
	}

	static function get($part=null, $cookiename='gb-author') {
		if (self::$cookie === null) {
			if (isset($_COOKIE[$cookiename])) {
				$s = get_magic_quotes_gpc() ? stripslashes($_COOKIE[$cookiename]) : $_COOKIE[$cookiename];
				self::$cookie = @unserialize($s);
			}
			if (!self::$cookie)
				self::$cookie = array();
		}
		if ($part === null)
			return self::$cookie;
		return isset(self::$cookie[$part]) ? self::$cookie[$part] : null;
	}
}

# -----------------------------------------------------------------------------
# Template helpers

gb::$title = array(gb::$site_title);

function gb_title($glue=' — ', $html=true) {
	$s = implode($glue, array_reverse(gb::$title));
	return $html ? h($s) : $s;
}

function h($s) {
	return filter_var($s, FILTER_SANITIZE_SPECIAL_CHARS);
}

function gb_nonce_field($context='', $referrer=true, $id_prefix='', $name='gb-nonce') {
	$nonce = gb_nonce_make($context);
	$name = h($name);
	$html = '<input type="hidden" id="' . $id_prefix.$name . '" name="' . $name 
		. '" value="' . $nonce . '" />';
	if ($referrer)
		$html .= '<input type="hidden" name="gb-referrer" value="'. h($_SERVER['REQUEST_URI']) . '" />';
	return $html;
}

function gb_timezone_offset_field($id='client-timezone-offset') {
	return '<input type="hidden" id="'.$id.'" name="client-timezone-offset" value="" />'
		. '<script type="text/javascript">'."\n//<![CDATA[\n"
		. 'document.getElementById("'.$id.'").value = -((new Date()).getTimezoneOffset()*60);'
		."\n//]]></script>";
}

function gb_comment_author_field($what, $default_value='', $id_prefix='comment-', $attrs='') {
	$value = gb_author_cookie::get($what);
	if (!$value)
		$value = $default_value;
	return '<input type="text" id="'.$id_prefix.'author-'.$what.'" name="author-'
		.$what.'" value="'.h($value).'"'
		.' onfocus="if(this.value==unescape(\''.rawurlencode($default_value).'\'))this.value=\'\';"'
		.' onblur="if(this.value==\'\')this.value=unescape(\''.rawurlencode($default_value).'\');"'
		.' '.$attrs.' />';
}

function gb_comment_fields($post=null, $id_prefix='comment-') {
	if ($post === null) {
		unset($post);
		global $post;
	}
	$post_cachename = $post->cachename();
	$nonce_context = 'post-comment-'.$post_cachename;
	return gb_nonce_field($nonce_context, true, $id_prefix)
		. gb_timezone_offset_field($id_prefix.'client-timezone-offset')
		. '<input type="hidden" id="'.$id_prefix.'reply-post" name="reply-post" value="'.h($post_cachename).'" />'
		. '<input type="hidden" id="'.$id_prefix.'reply-to" name="reply-to" value="" />';
}

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
 *  counted(1, 'comment', 'comments', 'No', 'One')  -> "One comment"
 *  counted(7, 'comment', 'comments')  -> "7 comments"
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
			# if the theme has a "feed.php" file, include that one
			if (is_file(GB_THEME_DIR.'/feed.php')) {
				require GB_THEME_DIR.'/feed.php';
			}
			# otherwise we'll handle the feed
			else {
				require GB_DIR.'/helpers/feed.php';
			}
			exit(0);
		}
		elseif (($strptime = strptime($gb_urlpath, gb::$posts_prefix)) !== false) {
			# post
			$post = GitBlog::postBySlug(urldecode($gb_urlpath), $strptime);
			if ($post === false)
				gb::$is_404 = true;
			elseif ($post->draft === true || $post->published->time > time())
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