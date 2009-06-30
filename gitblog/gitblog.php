<?
error_reporting(E_ALL);

# constants
define('GITBLOG_VERSION', '0.1.0');
define('GITBLOG_DIR', dirname(__FILE__));
if (!defined('GITBLOG_SITE_DIR')) {
	$u = dirname($_SERVER['SCRIPT_NAME']);
	$s = dirname($_SERVER['SCRIPT_FILENAME']);
	if (strpos($s, '/gitblog/') !== false) {
		if (strpos(realpath($s), realpath(dirname(__FILE__))) === 0) {
			# confirmed: inside gitblog
			$max = 20;
			while($s !== '/' and $max--) {
				if (substr($s, -7) === 'gitblog') {
					$s = dirname($s);
					$u = dirname($u);
					break;
				}
				$s = dirname($s);
				$u = dirname($u);
			}
		}
	}
	define('GITBLOG_SITE_DIR', $s);
	if (!defined('GITBLOG_SITE_URL')) {
		# URL to the base of the site.
		#
		# If your blog is hosted on it's own domain, for example 
		# http://my.blog.com/, the value of this parameter could be either "/" or the
		# complete url "http://my.blog.com/".
		#
		# If your blog is hosted in a subdirectory, for example
		# http://somesite.com/blogs/user/ the value of this parameter could be either
		# "/blogs/user/" or the complete url "http://somesite.com/blogs/user/".
		#
		# Must end with a slash ("/").
		define('GITBLOG_SITE_URL', 
			(isset($_SERVER['HTTPS']) ? 'https://' : 'http://')
			.$_SERVER['SERVER_NAME'] . ($u === '/' ? $u : $u.'/'));
	}
	unset($s);
	unset($u);
}

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

	/** URL prefix (strftime pattern) */
	static public $posts_url_prefix = '%Y/%m/%d/';
	
	/** URL prefix (pcre pattern) */
	static public $posts_url_prefix_re = '/^\d{4}\/\d{2}\/\d{2}\//';

	/**
	 * Number of posts per page.
	 * Changing this requires a rebuild before actually activated.
	 */
	static public $posts_pagesize = 10;
	
	/**
	 * Absolute path to git repository.
	 * 
	 * Normally the default value is good, but in the case "site/" creates URL
	 * clashes for you, you might want to change this.
	 *
	 * This should be the path to a non-bare repository, i.e. a directory in which
	 * a working tree will be checked out and contain a regular .git-directory.
	 *
	 * The path must be writable by the web server and the contents will have 
	 * umask 0220 (i.e. user and group writable) thus you can, after letting 
	 * gitblog create the repo for you, chgrp or chown to allow for remote pushing
	 * by other user(s) than the web server user.
	 */
	static public $repo;
	
	/** URL to gitblog index relative to GITBLOG_SITE_URL (request handler) */
	static public $index_url = 'index.php/';
	
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
}

gb::$repo = GITBLOG_SITE_DIR.'/site';

if (file_exists(GITBLOG_SITE_DIR.'/gb-config.php'))
	include GITBLOG_SITE_DIR.'/gb-config.php';

# no config? -- read defaults
if (gb::$site_title === null) {
	require GITBLOG_DIR.'/skeleton/gb-config.php';
}

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
 * /var/gitblog/site/theme, /var/gitblog/gitblog/themes/default => "../gitblog/themes/default"
 * /var/gitblog/gitblog/themes/default, /var/gitblog/site/theme => "../../site/theme"
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
		array_pop($fromv);
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
	public $rebuilders = array();
	public $gitQueryCount = 0;
	
	/** Execute a git command */
	function exec($cmd, $input=null) {
		# build cmd
		$cmd = 'git --git-dir='.escapeshellarg(gb::$repo.'/.git')
			.' --work-tree='.escapeshellarg(gb::$repo)
			.' '.$cmd;
		#var_dump($cmd);
		$r = $this->shell($cmd, $input);
		$this->gitQueryCount++;
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
	function shell($cmd, $input=null, $env=null) {
		#var_dump($cmd);
		# start process
		$ps = gb_popen($cmd, null, $env === null ? $_ENV : $env);
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
	
	function pathToTheme($file='') {
		return gb::$repo."/theme/$file";
	}
	
	function pathToCachedContent($dirname, $slug) {
		return gb::$repo."/.git/info/gitblog/content/$dirname/$slug";
	}
	
	function pathToPostsPage($pageno) {
		return gb::$repo."/.git/info/gitblog/content-paged-posts/".sprintf('%011d', $pageno);
	}
	
	function pathToPost($slug) {
		$st = strptime($slug, gb::$posts_url_prefix);
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
		return GITBLOG_SITE_URL . gb::$index_url . gb::$tags_prefix 
			. implode(',', array_map('urlencode', $tags));
	}
	
	function urlToTag($tag) {
		return GITBLOG_SITE_URL . gb::$index_url . gb::$tags_prefix 
			. urlencode($tag);
	}
	
	function urlToCategories($categories) {
		return GITBLOG_SITE_URL . gb::$index_url . gb::$categories_prefix 
			. implode(',', array_map('urlencode', $categories));
	}
	
	function urlToCategory($category) {
		return GITBLOG_SITE_URL . gb::$index_url . gb::$categories_prefix 
			. urlencode($category);
	}
	
	function init($add_sample_content=true, $shared='true') {
		$mkdirmode = $shared === 'all' ? 0777 : 0775;
		$shared = $shared ? "--shared=$shared" : '';
		
		# create directories and chmod
		if (!is_dir(gb::$repo.'/.git') && !mkdir(gb::$repo.'/.git', $mkdirmode, true))
			return false;
		chmod(gb::$repo, $mkdirmode);
		chmod(gb::$repo.'/.git', $mkdirmode);
		
		# git init
		$this->exec("init --quiet $shared");
		
		# Create empty standard directories
		mkdir(gb::$repo.'/content/posts', $mkdirmode, true);
		mkdir(gb::$repo.'/content/pages', $mkdirmode);
		chmod(gb::$repo.'/content', $mkdirmode);
		chmod(gb::$repo.'/content/posts', $mkdirmode);
		chmod(gb::$repo.'/content/pages', $mkdirmode);
		
		# Copy post-commit hook
		copy(GITBLOG_DIR.'/skeleton/hooks/post-commit', gb::$repo."/.git/hooks/post-commit");
		chmod(gb::$repo."/.git/hooks/post-commit", 0774);
		
		# Enable default theme (todo: maybe a php-native recursive copy function for this?)
		$r = $this->shell('cp -Rp '.escapeshellarg(GITBLOG_DIR.'/themes/default')
			.' '.escapeshellarg(gb::$repo."/theme"));
		if ($r[0] != 0)
			return false;
		$r = $this->shell('chmod -R g+rw '.escapeshellarg(gb::$repo."/theme"));
		# we don't care if the above failed
		$this->exec("add theme");
		
		# Add sample content
		if ($add_sample_content) {
			# Copy example "about" page
			copy(GITBLOG_DIR.'/skeleton/content/pages/about.html', gb::$repo."/content/pages/about.html");
			chmod(gb::$repo."/content/pages/about.html", 0664);
			$this->exec("add content/pages/about.html");
		
			# Copy example "hello world" post
			$today = time();
			$s = file_get_contents(GITBLOG_DIR.'/skeleton/content/posts/0000-00-00-hello-world.html');
			$name = 'content/posts/'.date('Y/m-d').'-hello-world.html';
			$path = gb::$repo."/$name";
			@mkdir(dirname($path), 0775, true);
			chmod(dirname($path), 0775);
			$s = str_replace('0000/00-00-hello-world.html', basename(dirname($name)).'/'.basename($name), $s);
			file_put_contents($path, $s);
			chmod($path, 0664);
			$this->exec("add $name");
		}
		
		return true;
	}
	
	function commit($message, $author_account_or_email) {
		if (is_string($author_account_or_email))
			$author_account_or_email = GBUserAccount::get($author_account_or_email);
		if (!$author_account_or_email) {
			trigger_error('no author');
			return false;
		}
		$author = GBUserAccount::formatGitAuthor($author_account_or_email);
		$this->exec('commit -m '.escapeshellarg($message)
			. ' --quiet --author='.escapeshellarg($author));
		@chmod(gb::$repo."/.git/COMMIT_EDITMSG", 0664);
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
		if (is_dir(gb::$repo."/.git/info/gitblog")) {
			if (@file_get_contents(gb::$repo.'/.git/info/gitblog-site-url') !== GITBLOG_SITE_URL) {
				$s = GITBLOG_SITE_URL; # because gb_atomic_write need a reference
				gb_atomic_write(gb::$repo.'/.git/info/gitblog-site-url', $s, 0664);
			}
			return 0;
		}
		if (!is_dir(gb::$repo."/.git"))
			return 2;
		GBRebuilder::rebuild($this, true);
		return 1;
	}
	
	function verifyConfig() {
		if (!gb::$secret or strlen(gb::$secret) < 62) {
			header('Status: 503 Service Unavailable');
			header('Content-Type: text/plain; charset=utf-8');
			exit("\n\ngb::\$secret is not set or too short.\n\nPlease edit your gb-config.php file.\n");
		}
	}
}


# -----------------------------------------------------------------------------
# Content (posts, pages, etc)

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
		
		# specific publish time?
		#$publish_time = null;
		#if (isset($this->meta['publish'])) {
		#	$st = date_parse($this->meta['publish']);
		#	# todo wip
		#	unset($this->meta['publish']);
		#}
		
		# use meta for title if absent
		if ($this->title === null)
			$this->title = $this->slug;
		
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
			if ($this->published === false) {
				$this->published = $initial->authorDate;
			}
			else {
				#	add hours, mins and secs from authorDate
				$this->published += gb_hms_from_time($initial->authorDate);
			}
			
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
	
	static function getCached($name) {
		$path = gb::$repo."/.git/info/gitblog/".gb_filenoext($name);
		return @unserialize(file_get_contents($path));
	}
	
	function writeCache() {
		$path = gb::$repo."/.git/info/gitblog/".$this->cachename();
		$dirname = dirname($path);
		
		if (!is_dir($dirname)) {
			$p = gb::$repo."/.git/info";
			$parts = array_merge(array('gitblog'),explode('/',trim(dirname($this->cachename()),'/')));
			foreach ($parts as $part) {
				$p .= '/'.$part;
				@mkdir($p, 0775, true);
				chmod($p, 0775);
			}
		}
		
		$data = serialize($this);
		return gb_atomic_write($path, $data, 0664);
	}
	
	function url() {
		return GITBLOG_SITE_URL . gb::$index_url . $this->urlpath();
	}
	
	function tagLinks($separator=', ', $template='<a href="%u">%n</a>', $htmlescape=true) {
		return $this->collLinks('tags', $separator, $template, $htmlescape);
	}
	
	function categoryLinks($separator=', ', $template='<a href="%u">%n</a>', $htmlescape=true) {
		return $this->collLinks('categories', $separator, $template, $htmlescape);
	}
	
	function collLinks($what, $separator=', ', $template='<a href="%u">%n</a>', $htmlescape=true) {
		static $needles = array('%u', '%n');
		$links = array();
		$vn = "{$what}_prefix";
		$u = GITBLOG_SITE_URL . gb::$index_url . gb::$$vn;
		
		foreach ($this->$what as $tag) {
			$n = $htmlescape ? htmlentities($tag) : $tag;
			$links[] = str_replace($needles, array($u.urlencode($tag), $n), $template);
		}
		
		return $separator !== null ? implode($separator, $links) : $links;
	}
}


class GBPage extends GBContent {
	static function getCached($slug) {
		$path = gb::$repo."/.git/info/gitblog/content/pages/".$slug;
		return @unserialize(file_get_contents($path));
	}
}


class GBPost extends GBContent {
	function urlpath() {
		return gmstrftime(gb::$posts_url_prefix, $this->published)
			. str_replace('%2F', '/', urlencode($this->slug));
	}
	
	function cachename() {
		return 'content/posts/'.gmdate("Y/m/d/", $this->published).$this->slug;
	}
	
	static function getCached($published, $slug) {
		$path = gb::$repo."/.git/info/gitblog/content/posts/".gmdate("Y/m/d/", $published).$slug;
		return @unserialize(file_get_contents($path));
	}
}

# -----------------------------------------------------------------------------
# Users

class GBUserAccount {
	static public $db = null;
	
	static function _reload() {
		if (file_exists(gb::$repo.'/.git/info/gitblog-users.php')) {
			include gb::$repo.'/.git/info/gitblog-users.php';
			self::$db = $db;
		}
		else {
			self::$db = array();
		}
	}
	
	static function sync() {
		if (self::$db === null)
			return;
		$r = file_put_contents(gb::$repo.'/.git/info/gitblog-users.php', 
			'<? $db = '.var_export(self::$db, 1).'; ?>', LOCK_EX);
		chmod(gb::$repo.'/.git/info/gitblog-users.php', 0660);
		return $r;
	}
	
	static function passhash($email, $passphrase) {
		return sha1($email . ' ' . $passphrase);# . ' ' . gb::$secret);
	}
	
	static function create($email, $passphrase, $name, $admin=false) {
		if (self::$db === null)
			self::_reload();
		$email = strtolower($email);
		self::$db[$email] = array(
			'email' => $email,
			'passhash' => self::passhash($email, $passphrase),
			'name' => $name
		);
		if ($admin and !self::setAdmin($email))
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
	
	static function formatGitAuthor(&$account) {
		if (!$account) {
			trigger_error('invalid account');
			return '';
		}
		$s = '';
		if ($account['name'])
			$s = $account['name'] . ' ';
		return $s . '<'.$account['email'].'>';
	}
}

$debug_time_started = microtime(true);
$gitblog = new GitBlog();
?>