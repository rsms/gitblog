<?
error_reporting(E_ALL);
$gb_time_started = microtime(true);

/**
 * Configuration.
 *
 * These values can be overridden in gb-config.php (or somewhere else for that matter).
 */
class gb {
	/** URL prefix for tags */
	static public $tags_prefix = 'tags/';

	/** URL prefix for categories */
	static public $categories_prefix = 'category/';

	/** URL prefix for the feed */
	static public $feed_prefix = 'feed';

	/**
	 * URL prefix (strftime pattern).
	 * Need to specify at least year and month. Day, time and so on is optional.
	 * Changing this parameter does not affect the cache.
	 */
	static public $posts_prefix = '%Y/%m/';
	
	/** URL prefix for pages */
	static public $pages_prefix = '';

	/** Number of posts per page. */
	static public $posts_pagesize = 10;
	
	/** URL to gitblog index _relative_ to gb::$site_url */
	static public $index_prefix = 'index.php/';
	
	/**
	 * When this query string key is set and the client is authorized, the work
	 * copy of a viewed post is displayed rather than the live version.
	 * 
	 * i.e. gb::$is_preview is set to true.
	 */
	static public $preview_query_key = 'preview';
	
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
	
	/** Site description */
	static public $site_description =
		'Change this fancy description by editing gb::$site_description in gb-config.php';
	
	/** Shared secret */
	static public $secret = '';
	
	# --------------------------------------------------------------------------
	# Constants
	
	static public $version = '0.1.3';
	
	/** Absolute path to the gitblog directory */
	static public $dir;
	
	
	/** Absolute path to the site root */
	static public $site_dir;
	
	/** Absolute URL to the site root, not including gb::$index_prefix */
	static public $site_url;
	
	/** Absolute URL path (i.e. starts with a slash) to the site root */
	static public $site_path;
	
	
	/** Absolute path to current theme. Available when running a theme. */
	static public $theme_dir;
	
	/** Absolute URL to current theme. Available when running a theme. */
	static public $theme_url;
	
	
	static public $content_cache_fnext = '.content';
	static public $comments_cache_fnext = '.comments';
	static public $index_cache_fnext = '.index';
	
	/**
	 * The strftime pattern used to build posts cachename.
	 * 
	 * The granularity of this date is the "bottleneck", or "limiter", for
	 * $posts_prefix. If you specify "%Y", $posts_prefix can define patterns with
	 * granularity ranging from year to second. But if you set this parameter to
	 * "%Y/%m/%d-" the minimum granularity of $posts_prefix goes up to day, which
	 * means that this: $posts_prefix = '%Y/%m/' will not work, as day is 
	 * missing. However this: $posts_prefix = '%y-%m-%e/' and
	 * $posts_prefix = '%y/%m/%e/%H/%M/' works fine, as they both have a 
	 * granularity of one day or more.
	 * 
	 * It's recommended not to alter this value. The only viable case where
	 * altering this is if you are posting many many posts every day, thus adding
	 * day ($posts_cn_pattern = '%Y/%m/%d-') would give a slight file system
	 * performance improvement on most file systems.
	 */
	static public $posts_cn_pattern = '%Y/%m-';
	
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
	
	/**
	 * Preview mode -- work content is loaded rather than live versions.
	 * 
	 * This is automatically set to true by the request handler (end of this
	 * file) when all of the following are true:
	 * 
	 *  - gb::$preview_query_key is set in the query string (i.e. "?preview")
	 *  - Client is authorized (gb::$authorized is non-false)
	 */
	static public $is_preview = false;
	
	/**
	 * A universal list of error messages (simple strings) which occured during
	 * the current request handling.
	 * 
	 * Themes should take care of this and display these error messages where
	 * appropriate.
	 */
	static public $errors = array();
	
	/** True if some part of gitblog (inside the gitblog directory) is the initial invoker */
	static public $is_internal_call = false;
	
	/** Contains the site.json structure or null if not loaded */
	static public $site_state = null;
	
	# --------------------------------------------------------------------------
	# Logging
	static public $log_open = false;
	static public $log_cb = null;
	
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
	static function log(/* [$priority,] $fmt [mixed ..] */) {
		$vargs = func_get_args();
		$priority = count($vargs) === 1 || !is_int($vargs[0]) ? LOG_NOTICE : array_shift($vargs);
		return self::vlog($priority, $vargs);
	}
	
	static function vlog($priority, $vargs, $btoffset=1, $prefix=null) {
		if ($priority > self::$log_filter)
			return true;
		if ($prefix === null) {
			$bt = debug_backtrace();
			while (!isset($bt[$btoffset]) && $btoffset >= 0)
				$btoffset--;
			$bt = isset($bt[$btoffset]) ? $bt[$btoffset] : $bt[$btoffset-1];
			$prefix = '['.(isset($bt['file']) ? gb_relpath(gb::$site_dir, $bt['file']).':'.$bt['line'] : '?').'] ';
		}
		$msg = $prefix;
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
		if (self::$log_cb) {
			$fnc = self::$log_cb;
			$fnc($priority, $msg);
		}
		if (syslog($priority, $msg))
		 	return $msg;
		return error_log($msg, 4) ? $msg : false;
	}
	
	static function openlog($ident=null, $options=LOG_PID, $facility=LOG_USER) {
		if ($ident === null) {
			$u = parse_url(gb::$site_url);
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
	
	static function url_to($part=null, $htmlsafe=true) {
		$s = gb::$site_url.self::$index_prefix;
		if ($part) {
			if ($part{0} === '/') {
				$s .= strlen($part) > 1 ? substr($part, 1) : '';
			}
			else {
				$v = $part.'_prefix';
				$s .= self::$$v;
			}
		}
		return $htmlsafe ? h($s) : $s;
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
			$u->query = $_GET;
			$u->path = $u->query ? substr(@$_SERVER['REQUEST_URI'], 0, strpos($_SERVER['REQUEST_URI'],'?')) 
				: rtrim(@$_SERVER['REQUEST_URI'],'?');
			self::$current_url = $u;
		}
		return self::$current_url;
	}
	
	static function referrer_url($fallback_on_http_referer=false) {
		$dest = isset($_REQUEST['gb-referrer']) ? $_REQUEST['gb-referrer'] 
			: (isset($_REQUEST['referrer']) ? $_REQUEST['referrer'] : false);
		if ($fallback_on_http_referer && $dest === false && isset($_SERVER['HTTP_REFERER']))
			$dest = $_SERVER['HTTP_REFERER'];
		if ($dest) {
			$dest = new GBURL($dest);
			unset($dest['gb-error']);
			return $dest;
		}
		return false;
	}
	
	# --------------------------------------------------------------------------
	# Admin authentication
	
	static public $authorized = null;
	static public $_authenticators = null;
	
	static function authenticator($context='gb-admin') {
		if (self::$_authenticators === null)
			self::$_authenticators = array();
		elseif (isset(self::$_authenticators[$context])) 
			return self::$_authenticators[$context];
		$users = array();
		foreach (GBUserAccount::get() as $email => $account) {
			# only include actual users
			if (strpos($email, '@') !== false)
				$users[$email] = $account->passhash;
		}
		$chap = new CHAP($users, $context);
		self::$_authenticators[$context] = $chap;
		return $chap;
	}
	
	static function deauthorize($redirect=true, $context='gb-admin') {
		$old_authorized = self::$authorized;
		self::$authorized = null;
		if (self::authenticator($context)->deauthorize()) {
			if ($old_authorized)
				self::log('client deauthorized: '.$old_authorized->email);
			self::event('client-deauthorized', $old_authorized);
		}
		if ($redirect) {
			header('HTTP/1.1 303 See Other');
			header('Location: '.(isset($_REQUEST['referrer']) ? $_REQUEST['referrer'] : gb::$site_url));
			exit(0);
		}
	}
	
	static function authenticate($force=true, $context='gb-admin') {
		$auth = self::authenticator($context);
		self::$authorized = null;
		if (($authed = $auth->authenticate())) {
			self::$authorized = GBUserAccount::get($authed);
			return self::$authorized;
		}
		elseif ($force) {
			$url = self::$site_url . 'gitblog/admin/authenticate.php'
				.'?referrer='.urlencode(self::url());
			header('HTTP/1.1 303 See Other');
			header('Location: '.$url);
			exit('<html><body>See Other <a href="'.$url.'"></a></body></html>');
		}
		return $authed;
	}
	
	# --------------------------------------------------------------------------
	# Error handling
	
	static public $orig_err_handler = null;
	static public $orig_err_html = null;
	
	static function catch_errors($handler=null, $filter=null) {
		if ($handler === null)
			$handler = array('gb', 'catch_error');
		self::$orig_err_html = ini_set('html_errors', '0');
		if ($filter === null)
			$filter = E_ALL;
		self::$orig_err_handler = set_error_handler($handler, $filter);
	}

	static function end_catch_errors() {
		set_error_handler(array('gb', 'catch_error'), E_ALL);
		if (self::$orig_err_handler)
			set_error_handler(self::$orig_err_handler);
		ini_set('html_errors', self::$orig_err_html);
		self::$orig_err_handler = null;
	}
	
	# int $errno , string $errstr [, string $errfile [, int $errline [, array $errcontext ]]]
	static function catch_error($errno, $errstr, $errfile=null, $errline=-1, $errcontext=null) {
		if(error_reporting() === 0)
			return;
		try { self::vlog(LOG_WARNING, array($errstr), 2); } catch (Exception $e) {}
		throw new PHPException($errstr, $errno, $errfile, $errline);
	}
	
	# --------------------------------------------------------------------------
	# Plugins
	
	static public $plugins_loaded = array();
	
	static function load_plugins($context) {
		if (self::$site_state === null)
			gb::verify_integrity();
		
		# bail out if no plugins
		if (!isset(self::$site_state['plugins']))
			return false;
		$plugins = self::$site_state['plugins'];
		if (!isset($plugins[$context]))
			return false;
		$plugins = $plugins[$context];
		
		# loaded list
		if (isset(self::$plugins_loaded[$context]))
			$loaded =& self::$plugins_loaded[$context];
		else {
			$loaded = array();
			self::$plugins_loaded[$context] =& $loaded;
		}
		
		if (!is_array($plugins))
			return;
		
		# load plugins
		foreach ($plugins as $path) {
			# expand gitblog plugins
			if ($path{0} !== '/')
				$path = gb::$dir . '/plugins/'.$path;
			
			# skip already loaded plugins
			$loaded_but_not_inited = false;
			if (isset($loaded[$path]) && ($loaded_but_not_inited = $loaded[$path]))
				continue;
			
			# load
			if (!$loaded_but_not_inited)
				require $path;
			
			# call plugin_init
			$name = str_replace(array('-', '.'), '_', substr(basename($path), 0, -4)); # assume .php
			$init_func_name = $name.'_init';
			$loaded[$path] = $init_func_name($context);
		}
	}
	
	/** A JSONDict */
	static public $settings = null;
	# initialized after the gb class
	
	# --------------------------------------------------------------------------
	# Events
	
	static public $events = array();
	
	/** Register $callable for receiving $event s */
	static function observe($event, $callable) {
		if(isset(self::$events[$event]))
			self::$events[$event][] = $callable;
		else
			self::$events[$event] = array($callable);
	}
	
	/** Dispatch an event, optionally with arguments. */
	static function event(/* $event [, $arg ..] */ ) {
		$args = func_get_args();
		$event = array_shift($args);
		if(isset(self::$events[$event])) {
			foreach(self::$events[$event] as $callable) {
				if (call_user_func_array($callable, $args) === true)
					break;
			}
		}
	}
	
	/** Unregister $callable from receiving $event s */
	static function stop_observing($callable, $event=null) {
		if($event !== null) {
			if(isset(self::$events[$event])) {
				$a =& self::$events[$event];
				if(($i = array_search($callable, $a)) !== false) {
					unset($a[$i]);
					if(!$a)
						unset(self::$events[$event]);
					return true;
				}
			}
		}
		else {
			foreach(self::$events as $n => $a) {
				if(($i = array_search($callable, $a)) !== false) {
					unset(self::$events[$n][$i]);
					if(!self::$events[$n])
						unset(self::$events[$n]);
					return true;
				}
			}
		}
		return false;
	}
	
	# --------------------------------------------------------------------------
	# defer -- Delayed execution
	
	static public $deferred = null;
	static public $deferred_time_limit = 30;
	
	/**
	 * Schedule $callable for delayed execution.
	 * 
	 * $callable will be executed after the response has been sent to the client.
	 * This is useful for expensive operations which do not need to send
	 * anything to the client.
	 * 
	 * At the first call to defer, deferring will be "activated". This means that
	 * output buffering is enabled, keepalive disabled and user-abort is ignored.
	 * You can check to see if deferring is enabled by doing a truth check on
	 * gb::$deferred. The event "did-activate-deferring" is also posted.
	 * 
	 * Use deferring wth caution.
	 * 
	 * A good example of when delayed execution is a good idea, is how the
	 * email-notification plugin defers the mail action (this is actually part of
	 * GBMail but this plugin makes good use of it).
	 * 
	 * Events:
	 * 
	 *  - "did-activate-deferring"
	 *    Posted when defer is activated.
	 * 
	 */
	static function defer($callable /* [$arg, .. ] */) {
		if (self::$deferred === null) {
			if (headers_sent())
				return false;
			ob_start();
			header('Transfer-Encoding: identity');
			header('Connection: close');
			self::$deferred = array();
			register_shutdown_function(array('gb','run_deferred'));
			ignore_user_abort(true);
			gb::event('did-activate-deferring');
		}
		self::$deferred[] = array($callable, array_slice(func_get_args(), 1));
		return true;
	}
	
	static function run_deferred() {
		try {
			# allow for self::$deferred_time_limit more seconds of processing
			global $gb_time_started;
			$time_spent = time()-$gb_time_started;
			@set_time_limit(self::$deferred_time_limit + $time_spent);
			
			if (headers_sent()) {
				# issue warning if output already started
				gb::log(LOG_WARNING,
					'defer: output already started -- using interleaved execution');
			}
			else {
				# tell client the request is done
				$size = ob_get_length();
				header('Content-Length: '.$size);
				ob_end_flush();
			}
			
			# flush any pending output
			flush();
			
			# call deferred code
			foreach (self::$deferred as $f) {
				try {
					call_user_func_array($f[0], $f[1]);
				}
				catch (Exception $e) {
					gb::log(LOG_ERR, 'deferred %s failed with %s: %s', 
						json_encode($f), get_class($e), $e->__toString());
				}
			}
		}
		catch (Exception $e) {
			gb::log(LOG_ERR, 'run_deferred failed with %s: %s', get_class($e), $e->__toString());
		}
	}
	
	# --------------------------------------------------------------------------
	# GitBlog
	
	static public $rebuilders = array();
	static public $gitQueryCount = 0;
	
	/** Execute a git command */
	static function exec($cmd, $input=null, $gitdir=null, $worktree=null) {
		# build cmd
		if ($gitdir === null)
			$gitdir = gb::$site_dir.'/.git';
		if ($worktree === null)
			$worktree = gb::$site_dir;
		$cmd = 'git --git-dir='.escapeshellarg($gitdir)
			.' --work-tree='.escapeshellarg($worktree)
			.' '.$cmd;
		#var_dump($cmd);
		gb::log(LOG_DEBUG, 'exec$ '.$cmd);
		$r = self::shell($cmd, $input, gb::$site_dir);
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
	
	/** Glob with PCRE skip filter which defaults to skipping directories. */
	static function glob($pattern, $skip='/\/$/') {
		foreach (glob($pattern, GLOB_MARK|GLOB_BRACE) as $path)
			if ( ($skip && !preg_match($skip, $path)) || !$skip )
				return $path;
		return null;
	}
	
	static function pathToTheme($file='') {
		return gb::$site_dir.'/theme/'.$file;
	}
	
	static function tags($indexname='tags-by-popularity') {
		return GBObjectIndex::getNamed($indexname);
	}
	
	static function categories($indexname='category-to-objs') {
		return GBObjectIndex::getNamed($indexname);
	}
	
	static function urlToTags($tags) {
		return gb::$site_url . gb::$index_prefix . gb::$tags_prefix 
			. implode(',', array_map('urlencode', $tags));
	}
	
	static function urlToTag($tag) {
		return gb::$site_url . gb::$index_prefix . gb::$tags_prefix 
			. urlencode($tag);
	}
	
	static function urlToCategories($categories) {
		return gb::$site_url . gb::$index_prefix . gb::$categories_prefix 
			. implode(',', array_map('urlencode', $categories));
	}
	
	static function urlToCategory($category) {
		return gb::$site_url . gb::$index_prefix . gb::$categories_prefix 
			. urlencode($category);
	}
	
	static function init($add_sample_content=true, $shared='true', $theme='default') {
		$mkdirmode = $shared === 'all' ? 0777 : 0775;
		$shared = $shared ? "--shared=$shared" : '';
		
		# sanity check
		$themedir = gb::$dir.'/themes/'.$theme;
		if (!is_dir($themedir))
			throw new InvalidArgumentException(
				'no theme named '.$theme.' ('.$themedir.'not found or not a directory)');
		
		# create directories and chmod
		if (!is_dir(gb::$site_dir.'/.git') && !mkdir(gb::$site_dir.'/.git', $mkdirmode, true))
			return false;
		chmod(gb::$site_dir, $mkdirmode);
		chmod(gb::$site_dir.'/.git', $mkdirmode);
		
		# git init
		self::exec('init --quiet '.$shared);
		
		# Create empty standard directories
		mkdir(gb::$site_dir.'/content/posts', $mkdirmode, true);
		mkdir(gb::$site_dir.'/content/pages', $mkdirmode);
		chmod(gb::$site_dir.'/content', $mkdirmode);
		chmod(gb::$site_dir.'/content/posts', $mkdirmode);
		chmod(gb::$site_dir.'/content/pages', $mkdirmode);
		
		# Copy post-* hooks
		foreach (array('post-commit', 'post-update') as $name) {
			copy(gb::$dir.'/skeleton/hooks/'.$name, gb::$site_dir.'/.git/hooks/'.$name);
			chmod(gb::$site_dir.'/.git/hooks/'.$name, 0774);
		}
		
		# Enable remote pushing with a checked-out copy
		self::exec('config receive.denyCurrentBranch ignore');
		
		# Copy .gitignore
		copy(gb::$dir.'/skeleton/gitignore', gb::$site_dir.'/.gitignore');
		chmod(gb::$site_dir.'/.gitignore', 0664);
		self::add('.gitignore');
		
		# Copy theme
		$lnname = gb::$site_dir.'/index.php';
		$lntarget = gb_relpath($lnname, $themedir.'/index.php');
		symlink($lntarget, $lnname) or exit($lntarget);
		self::add('index.php');
		
		# Add gb-config.php (might been added already, might be missing and/or
		# might be ignored by custom .gitignore -- doesn't really matter)
		self::add('gb-config.php', false);
		
		# Add sample content
		if ($add_sample_content) {
			# Copy example "about" page
			copy(gb::$dir.'/skeleton/content/pages/about.html', gb::$site_dir.'/content/pages/about.html');
			chmod(gb::$site_dir.'/content/pages/about.html', 0664);
			self::add('content/pages/about.html');
			
			# Copy example "about/intro" snippet
			mkdir(gb::$site_dir.'/content/pages/about', $mkdirmode);
			chmod(gb::$site_dir.'/content/pages/about', $mkdirmode);
			copy(gb::$dir.'/skeleton/content/pages/about/intro.html', gb::$site_dir.'/content/pages/about/intro.html');
			chmod(gb::$site_dir.'/content/pages/about/intro.html', 0664);
			self::add('content/pages/about/intro.html');
		
			# Copy example "hello world" post
			$s = file_get_contents(gb::$dir.'/skeleton/content/posts/0000-00-00-hello-world.html');
			$s = preg_replace('/published:.+/', 'published: '.date('H:i:s O'), $s);
			$name = 'content/posts/'.gmdate('Y/m-d').'-hello-world.html';
			$path = gb::$site_dir.'/'.$name;
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
	
	static function commit($message, $author=null, $pathspec=null, $deferred=false) {
		if ($deferred && gb::defer(array('gb', 'commit'), $message, $author, $pathspec, false)) {
			$pathspec = $pathspec ? r($pathspec) : '';
			gb::log('deferred commit -m %s --author %s %s',
				escapeshellarg($message), escapeshellarg($author), $pathspec);
			if (!$pathspec) {
				gb::log(LOG_WARNING,
					'deferred commits without pathspec might cause unexpected changesets');
			}
			return true;
		}
		
		if ($pathspec) {
			if (is_array($pathspec))
				$pathspec = implode(' ', array_map('escapeshellarg',$pathspec));
			else
				$pathspec = escapeshellarg($pathspec);
			$pathspec = ' '.$pathspec;
		}
		else {
			$pathspec = '';
		}
		$author = $author ? '--author='.escapeshellarg($author) : '';
		self::exec('commit -m '.escapeshellarg($message).' --quiet '.$author.$pathspec);
		@chmod(gb::$site_dir.'/.git/COMMIT_EDITMSG', 0664);
		return true;
	}
	
	static function version_parse($s) {
		if (is_int($s))
			return $s;
		$v = array_map('intval', explode('.', $s));
		if (count($v) < 3)
			$v = array(0,0,0);
		list($a, $i, $b) = $v;
		return ($a << 16) + ($i << 8) + $b;
	}

	static function version_format($v) {
		return sprintf('%d.%d.%d', $v >> 16, ($v << 16) >> 24, ($v << 24) >> 24);
	}
	
	
	
	static function loadSiteState() {
		$data = @file_get_contents(gb::$site_dir.'/site.json');
		if ($data === false)
			return false;
		gb::$site_state = json_decode($data, true);
		if (gb::$site_state === null || is_string(gb::$site_state)) {
			self::log(LOG_WARNING, 'syntax error in site.json -- moved to site.json.broken and creating new');
			if (!rename(gb::$site_dir.'/site.json', gb::$site_dir.'/site.json.broken')) {
				self::log(LOG_WARNING, 'failed to move "%s" to "%s"', 
					gb::$site_dir.'/site.json', gb::$site_dir.'/site.json.broken');
			}
			gb::$site_state = null;
			return false;
		}
		return true;
	}
	
	/**
	 * Verify integrity of the site, automatically taking any actions to restore
	 * it if broken.
	 * 
	 * Return values:
	 *   0  Nothing done (everything is probably OK).
	 *   -1 Error (the error has been logged through trigger_error).
	 *   1  gitblog cache was updated.
	 *   2  gitdir is missing and need to be created (git init).
	 *   3  upgrade performed
	 */
	static function verify_integrity() {
		$r = 0;
		if (!is_dir(gb::$site_dir.'/.git/info/gitblog')) {
			if (!is_dir(gb::$site_dir.'/.git')) {
				# 2: no repo/not initialized
				return 2; 
			}
			# 1: gitblog cache updated
			gb_maint::sync_site_state();
			GBRebuilder::rebuild(true);
			return 1;
		}
		
		# load site.json
		$r = self::loadSiteState();
		
		# check site state
		if ( $r === false || (gb::$site_state['url'] !== gb::$site_url
			&& strpos(gb::$site_url, '://localhost') === false
			&& strpos(gb::$site_url, '://127.0.0.1') === false) || !gb::$site_state['url'] )
		{
			return gb_maint::sync_site_state() === false ? -1 : 0;
		}
		elseif (gb::$site_state['version'] !== gb::$version) {
			return gb_maint::upgrade(gb::$site_state['version']) ? 0 : -1;
		}
		elseif (gb::$site_state['posts_pagesize'] !== gb::$posts_pagesize) {
			gb_maint::sync_site_state();
			GBRebuilder::rebuild(true);
			return 1;
		}
		
		return 0;
	}
	
	static function verify_config() {
		if (!gb::$secret || strlen(gb::$secret) < 62) {
			header('Status: 503 Service Unavailable');
			header('Content-Type: text/plain; charset=utf-8');
			exit("\n\ngb::\$secret is not set or too short.\n\nPlease edit your gb-config.php file.\n");
		}
	}
	
	static function verify() {
		if (self::verify_integrity() === 2) {
			header("Location: ".gb::$site_url."gitblog/admin/setup.php");
			exit(0);
		}
		gb::verify_config();
	}
}

#------------------------------------------------------------------------------
# Initialize constants

gb::$dir = dirname(__FILE__);

$u = dirname($_SERVER['SCRIPT_NAME']);
$s = dirname($_SERVER['SCRIPT_FILENAME']);
if (substr($_SERVER['SCRIPT_FILENAME'], -20) === '/gitblog/gitblog.php')
	exit('you can not run gitblog.php directly');
gb::$is_internal_call = ((strpos($s, '/gitblog/') !== false || substr($s, -8) === '/gitblog') 
	&& (strpos(realpath($s), realpath(gb::$dir)) === 0));

# gb::$site_dir
if (isset($gb_site_dir)) {
	gb::$site_dir = $gb_site_dir;
	unset($gb_site_dir);
}
else {
	if (gb::$is_internal_call) {
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
	gb::$site_dir = realpath($s);
}

# gb::$site_path -- must end in a slash ("/").
if (isset($gb_site_path)) {
	gb::$site_path = $gb_site_path;
	unset($gb_site_path);
}
else {
	gb::$site_path = ($u === '/' ? $u : $u.'/');
}

# gb::$site_url -- URL to the base of the site. Must end in a slash ("/").
if (isset($gb_site_url)) {
	gb::$site_url = $gb_site_url;
	unset($gb_site_url);
}
else {
	gb::$site_url = (isset($_SERVER['HTTPS']) ? 'https://' : 'http://')
		.$_SERVER['SERVER_NAME'] . gb::$site_path;
}

# only set the following when called externally
if (!gb::$is_internal_call) {
	
	# gb::$theme_dir
	if (isset($gb_theme_dir)) {
		gb::$theme_dir = $gb_theme_dir;
		unset($gb_theme_dir);
	}
	else {
		$bt = debug_backtrace();
		gb::$theme_dir = dirname($bt[0]['file']);
	}
	
	# gb::$theme_url
	if (isset($gb_theme_url)) {
		gb::$theme_url = $gb_theme_url;
		unset($gb_theme_url);
	}
	else {
		$relpath = gb_relpath(gb::$site_dir, gb::$theme_dir);
		if ($relpath === '' || $relpath === '.') {
			gb::$theme_url = gb::$site_url;
		}
		elseif ($relpath{0} === '.' || $relpath{0} === '/') {
			$uplevels = $max_uplevels = 0;
			if ($relpath{0} === '/') {
				$uplevels = 1;
			}
			if ($relpath{0} === '.') {
				function _empty($x) { return empty($x); }
				$max_uplevels = count(explode('/',trim(parse_url(gb::$site_url, PHP_URL_PATH), '/')));
				$uplevels = count(array_filter(explode('../', $relpath), '_empty'));
			}
			if ($uplevels > $max_uplevels) {
				trigger_error('gb::$theme_url could not be deduced since the theme you are '.
					'using ('.gb::$theme_dir.') is not reachable from '.gb::$site_url.
					'. You need to manually define $gb_theme_url before including gitblog.php',
					E_USER_ERROR);
			}
		}
		else {
			gb::$theme_url = gb::$site_url . $relpath . '/';
		}
	}
}
unset($s);
unset($u);

#------------------------------------------------------------------------------
# Load configuration

if (file_exists(gb::$site_dir.'/gb-config.php'))
	include gb::$site_dir.'/gb-config.php';

# no config? -- read defaults
if (gb::$site_title === null) {
	require gb::$dir.'/skeleton/gb-config.php';
}

#------------------------------------------------------------------------------
# Setup autoload

ini_set('include_path', ini_get('include_path') . ':' . gb::$dir . '/lib');

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

# PATH patches: macports git. todo: move to admin/setup.php
$_ENV['PATH'] .= ':/opt/local/bin';


#------------------------------------------------------------------------------
# Utilities

class JSONDict implements ArrayAccess, Countable {
	public $file;
	public $skeleton_file;
	public $cache;
	public $storage;
	
	function __construct($file, $skeleton_file=null) {
		$this->file = $file;
		$this->cache = null;
		$this->storage = null;
		$this->skeleton_file = $skeleton_file;
	}
	
	/**
	 * Higher level GET operation able to read deep values, which keys are
	 * separated by $sep.
	 */
	function get($key, $default=null, $sep='/') {
		if (!$sep)
			return $this->offsetGet($key);
		$keys = explode($sep, trim($key,$sep));
		if (($count = count($keys)) < 2)
			return $this->offsetGet($key);
		$value = $this->offsetGet($keys[0]);
		for ($i=1; $i<$count; $i++) {
			$key = $keys[$i];
			if (!is_array($value) || !isset($value[$key]))
				return null;
			$value = $value[$key];
		}
		return $value;
	}
	
	/**
	 * Higher level PUT operation able to set deep values, which keys are
	 * separated by $sep.
	 */
	function put($key, $value, $sep='/') {
		$keys = explode($sep, trim($key, $sep));
		if (($count = count($keys)) < 2)
			return $this->offsetSet($key, $value);
		
		$this->cache === null;
		$storage = $this->storage();
		$storage->begin();
		try {
			$storage->get();
			
			# two-key optimisation
			if ($count === 2) {
				$key1 = $keys[0];
				$d =& $storage->data[$key1];
				if (!isset($d))
					$d = array($keys[1] => $value);
				elseif (!is_array($d))
					$d = array($storage->data[$key1], $keys[1] => $value);
				else
					$d[$keys[1]] = $value;
			}
			else {
				$patch = null;
				$n = array();
				$leaf_key = array_pop($keys);
				$eroot = null;
				$e = $storage->data;
				$ef = true;
			
				# build patch
				foreach ($keys as $key) {
					$n[$key] = array();
					if ($patch === null) {
						$patch =& $n;
						$eroot =& $e;
					}
					if ($ef !== false) {
						if (isset($e[$key]) && is_array($e[$key]))
							$e =& $e[$key];
						else
							$ef = false;
					}
					$n =& $n[$key];
				}
			
				# apply
				if ($ef !== false) {
					# quick patch (simply replace or set value)
					if (!is_array($e))
						$e = array($leaf_key => $value);
					else
						$e[$leaf_key] = $value;
					$storage->data = $eroot;
				}
				else {
					# merge patch
					$n[$leaf_key] = $value;
					$storage->data = array_merge_recursive($storage->data, $patch);
				}
			}
			
			# commit changes
			$this->cache = $storage->data;
			$storage->commit();
		}
		catch (Exception $e) {
			$storage->rollback();
		}
	}
	
	/** Retrieve the underlying JSONStore storage */
	function storage() {
		if ($this->storage === null)
			$this->storage = new JSONStore($this->file, $this->skeleton_file);
		return $this->storage;
	}
	
	function offsetGet($k) {
		if ($this->cache === null)
			$this->cache = $this->storage()->get();
		return isset($this->cache[$k]) ? $this->cache[$k] : null;
	}
	
	function offsetSet($k, $v) {
		$this->storage()->set($k, $v);
		$this->cache = null; # will be reloaded at next call to get
	}
	
	function offsetExists($k) {
		if ($this->cache === null)
			$this->cache = $this->storage()->get();
		return isset($this->cache[$k]);
	}
	
	function offsetUnset($k) {
		$this->storage()->set($k, null);
		$this->cache = null; # will be reloaded at next call to get
	}
	
	function count() {
		if ($this->cache === null)
			$this->cache = $this->storage()->get();
		return count($this->cache);
	}
	
	function __toString() {
		if ($this->cache === null)
			$this->cache = $this->storage()->get();
		return var_export($this->cache ,1);
	}
}

# settings.json
gb::$settings = new JSONDict(gb::$site_dir.'/settings.json', gb::$dir.'/skeleton/settings.json');

class PHPException extends RuntimeException {
	function __construct($msg=null, $errno=0, $file=null, $line=-1, $cause=null) {
		if ($msg instanceof Exception) {
			if (is_string($errno) && $file == null && $line == -1 && $cause == null) {
				$this->cause = $msg;
				$msg = $errno;
				$errno = 0;
			}
			else {
				$line = $msg->getLine();
				$file = $msg->getFile();
				$errno = $msg->getCode();
				$msg = $msg->getMessage();
				if (isset($msg->errorInfo))
					$this->errorInfo = $msg->errorInfo;
			}
		}
		parent::__construct($msg, $errno);
		if ($file != null)  $this->file = $file;
		if ($line != -1)    $this->line = $line;
		if ($cause != null) $this->cause = $cause;
	}
}

class GBURL implements ArrayAccess, Countable {
	public $scheme;
	public $host;
	public $secure;
	public $port;
	public $path = '/';
	public $query;
	public $fragment;
	
	static function parse($str) {
		return new self($str);
	}
	
	function __construct($url=null) {
		if ($url !== null) {
			$p = @parse_url($url);
			if ($p === false)
				throw new InvalidArgumentException('unable to parse URL '.var_export($url,1));
			foreach ($p as $k => $v) {
				if ($k === 'query')
					parse_str($v, $this->query);
				else
					$this->$k = $v;
			}
			$this->secure = $this->scheme === 'https';
			if ($this->port === null)
				$this->port = $this->scheme === 'https' ? 443 : ($this->scheme === 'http' ? 80 : null);
		}
	}
	
	function __toString($scheme=true, $host=true, $port=true, $path=true, $query=true, $fragment=true) {
		$s = '';
		
		if ($scheme !== false) {
			if ($scheme === true) {
				if ($this->scheme)
					$s = $this->scheme . '://';
			}
			else
				$s = $scheme . '://';
		}
		
		if ($host !== false) {
			if ($host === true)
				$s .= $this->host;
			else
				$s .= $host;
			
			if ($port === true && $this->port !== null && (
				($this->secure === true && $this->port !== 443) 
				|| ($this->secure === false && $this->port !== 80)
			))
				$s .= ':' . $this->port;
			elseif ($port !== true && $port !== false)
				$s .= ':' . $port;
		}
		
		if ($path !== false) {
			if ($path === true)
				$s .= $this->path;
			else
				$s .= $path;
		}
			
		if ($query === true && $this->query) {
			if (($query = is_string($this->query) ? $this->query : http_build_query($this->query)))
				$s .= '?'.$query;
		}
		elseif ($query !== true && $query !== false && $query)
			$s .= '?'.(is_string($query) ? $query : http_build_query($query));
		
		if ($fragment === true && $this->fragment)
			$s .= '#'.$this->fragment;
		elseif ($fragment !== true && $fragment !== false && $fragment)
			$s .= '#'.$fragment;
		
		return $s;
	}

	function __sleep() {
		$this->query = http_build_query($this->query);
		return get_object_vars($this);
	}
	
	function __wakeup() {
		$v = $this->query;
		$this->query = array();
		parse_str($v, $this->query);
	}
	
	# ArrayAccess
	function offsetGet($k) { return $this->query[$k]; }
	function offsetSet($k, $v) { $this->query[$k] = $v; }
	function offsetExists($k) { return isset($this->query[$k]); }
	function offsetUnset($k) { unset($this->query[$k]); }
	
	# Countable
	function count() { return count($this->query); }
}

/** Human-readable representation of $var */
function r($var) {
	$r = json_encode($var);
	if ($r === null)
		$r = print_r($var, true);
	return $r;
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


/** Evaluate an escaped UTF-8 sequence, like the ones generated by git */
function gb_utf8_unescape($s) {
	eval('$s = "'.$s.'";');
	return $s;
}


function gb_normalize_git_name($name) {
	return ($name && $name{0} === '"') ? gb_utf8_unescape(substr($name, 1, -1)) : $name;
}

function gb_parse_author($gitauthor) {
	$gitauthor = trim($gitauthor);
	$p = strpos($gitauthor, '<');
	if ($p === 0)
		return (object)array('name' => '', 'email' => trim($gitauthor, '<>'));
	elseif ($p === false)
		return (object)array('name' => $gitauthor, 'email' => '');
	return (object)array('name' => rtrim(substr($gitauthor, 0, $p)), 'email' => trim(substr($gitauthor, $p+1), '<>'));
}

/** Normalize $time (any format strtotime can handle) to a ISO timestamp. */
function gb_strtoisotime($time) {
	$d = new DateTime($time);
	return $d->format('c');
}

function gb_mkutctime($st) {
	return gmmktime($st['tm_hour'], $st['tm_min'], $st['tm_sec'],
		$st['tm_mon']+1, ($st['tm_mday'] === 0) ? 1 : $st['tm_mday'], 1900+$st['tm_year']);
}

function gb_format_duration($seconds, $format='%H:%M:%S.') {
	$i = intval($seconds);
	return gmstrftime($format, $i).sprintf('%03d', round($seconds*1000.0)-($i*1000));
}

function gb_hash($data) {
	return base_convert(hash_hmac('sha1', $data, gb::$secret), 16, 36);
}

function gb_flush() {
	if (gb::$deferred === null)
		flush();
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

function gb_tokenize_html($html) {
	return preg_split('/(<.*>|\[.*\])/Us', $html, -1, 
		PREG_SPLIT_DELIM_CAPTURE|PREG_SPLIT_NO_EMPTY);
}

#------------------------------------------------------------------------------
# Exceptions

class GitError extends Exception {}
class GitUninitializedRepoError extends GitError {}

#------------------------------------------------------------------------------

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
	
	function age($abs_threshold=31536000, $abs_format='%B %e, %Y', $suffix=' ago', 
		$compared_to=null, $momentago='A second')
	{
		if ($compared_to === null)
			$diff = time() - $this->time;
		elseif (is_int($compared_to))
			$diff = $compared_to - $this->time;
		else
			$diff = $compared_to->time - $this->time;
		
		if ($diff >= $abs_threshold)
			return $this->utcformat($abs_format);
		
		if ($diff < 5)
			return $momentago.$suffix;
		elseif ($diff < 50)
			return $diff.' '.($diff === 1 ? 'second' : 'seconds').$suffix;
		elseif ($diff < 3000) {
			$diff = (int)round($diff / 60);
			return $diff.' '.($diff === 1 ? 'minute' : 'minutes').$suffix;
		}
		elseif ($diff < 83600) {
			$diff = (int)round($diff / 3600);
			return $diff.' '.($diff === 1 ? 'hour' : 'hours').$suffix;
		}
		elseif ($diff < 604800) {
			$diff = (int)round($diff / 86400);
			return $diff.' '.($diff === 1 ? 'day' : 'days').$suffix;
		}
		elseif ($diff < 2628000) {
			$diff = (int)round($diff / 604800);
			return $diff.' '.($diff === 1 ? 'week' : 'weeks').$suffix;
		}
		elseif ($diff < 31536000) {
			$diff = (int)round($diff / 2628000);
			return $diff.' '.($diff === 1 ? 'month' : 'months').$suffix;
		}
		$diff = (int)round($diff / 31536000);
		return $diff.' '.($diff === 1 ? 'year' : 'years').$suffix;
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
		#$this->d = gmstrftime('%FT%TZ', $this->time);
		return array('time', 'offset');
	}
	
	function __wakeup() {
		#$this->time = gb_mkutctime(strptime($this->d, '%FT%TZ'));
		#unset($this->d);
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
			$ds = $this->utcformat('%T');
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
		return gb_filenoext($this->name).gb::$content_cache_fnext;
	}
	
	function writeCache() {
		$path = gb::$site_dir.'/.git/info/gitblog/'.$this->cachename();
		$dirname = dirname($path);
		
		if (!is_dir($dirname)) {
			$p = gb::$site_dir.'/.git/info';
			$parts = array_merge(array('gitblog'),explode('/',trim(dirname($this->cachename()),'/')));
			foreach ($parts as $part) {
				$p .= '/'.$part;
				@mkdir($p, 0775);
				@chmod($p, 0775);
			}
		}
		$bw = file_put_contents($path, serialize($this), LOCK_EX);
		chmod($path, 0664);
		return $bw;
	}
	
	function reload($data, $commits=null) {
		$this->mimeType = GBMimeType::forFilename($this->name);
	}
	
	protected function applyInfoFromCommits($commits) {
		if (!$commits)
			return;
		
		# latest one is last modified
		$this->modified = $commits[0]->authorDate;
		
		# first one is when the content was created
		$initial = $commits[count($commits)-1];
		$this->published = $initial->authorDate;
		
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
	public $excerpt;
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
	
	/* Get path to cached content where the slug is static. */
	static function pathToCached($subdir, $slug) {
		return gb::$site_dir.'/.git/info/gitblog/content/'.$subdir.'/'.$slug;
	}
	
	/** Find path to work content where the slug is static. Returns null if not found. */
	static function pathToWork($subdir, $slug_fnpattern) {
		$base = gb::$site_dir.'/content/'.$subdir.'/'.$slug_fnpattern;
		return gb::glob($base . '*', '/(\.comments|\/)$/');
	}
	
	static function pathspecFromAbsPath($path) {
		return substr($path, strlen(gb::$site_dir)+1);
	}
	
	static function find($slug, $subdir='', $class='GBExposedContent', $work_version=null) {
		if ($work_version === null)
			$work_version = gb::$is_preview;
		if ($work_version === true) {
			# find path to raw content
			if (($path = self::pathToWork($subdir, $slug)) === null)
				return false;
			
			# find cached
			$cached = self::find($slug, $subdir, $class, false);
			
			# load work
			return self::loadWork($path, $cached, $class, null, $slug);
		}
		else {
			$path = self::pathToCached($subdir, $slug . gb::$content_cache_fnext);
			$data = @file_get_contents($path);
			return $data === false ? false : unserialize($data);
		}
	}
	
	static function loadWork($path, $post=false, $class='GBExposedContent', $id=null, $slug=null) {
		if ($post === false)
			$post = new $class(self::pathspecFromAbsPath($path), $id, $slug);
		
		# load rebuild plugins before calling reload
		gb::load_plugins('rebuild');
	
		# reload post with work data
		$post->reload(file_get_contents($path));
	
		# set publish and modified date
		$post->published = new GBDateTime(filectime($path));
		$post->modified = new GBDateTime(filemtime($path));
		
		# set author if needed
		if (!$post->author) {
			# GBUserAccount have the same properties as the regular class-less author
			# object, so it's safe to just pass it on here, as a clone.
			if (gb::$authorized) {
				$post->author = clone gb::$authorized;
				unset($post->passhash);
			}
			elseif (($padmin = GBUserAccount::getAdmin())) {
				$post->author = clone $padmin;
				unset($post->passhash);
			}
			else {
				$post->author = (object)array('name' => '', 'email' => '');
			}
		}
	
		return $post;
	}
	
	function reload($data, $commits=null) {
		parent::reload($data, $commits);
		
		$bodystart = strpos($data, "\n\n");
		if ($bodystart === false)
			$bodystart = 0;
			#trigger_error("malformed content object '{$this->name}' missing header");
		
		$this->body = null;
		$this->meta = array();
		
		# extract base date from name
		if (!$commits && $this instanceof GBPost)
			GBPost::parsePathspec($this->name, $this->published, $this->slug, $fnext);
		
		if ($bodystart > 0)
			self::parseMetaHeaders(substr($data, 0, $bodystart), $this->meta);
		
		# lift lists from meta to this
		static $special_lists = array('tag'=>'tags', 'category'=>'categories');
		foreach ($special_lists as $singular => $plural) {
			$s = false;
			if (isset($this->meta[$plural])) {
				$s = $this->meta[$plural];
				unset($this->meta[$plural]);
			}
			elseif (isset($this->meta[$singular])) {
				$s = $this->meta[$singular];
				unset($this->meta[$singular]);
			}
			
			if ($s)
				$this->$plural = GBFilter::apply('parse-tags', $s);
		}
		
		# lift specials, like title, from meta to this
		static $special_singles = array('title');
		foreach ($special_singles as $singular) {
			if (isset($this->meta[$singular])) {
				$this->$singular = $this->meta[$singular];
				unset($this->meta[$singular]);
			}
		}
		
		# transfer author meta tag
		if (isset($this->meta['author'])) {
			$this->author = gb_parse_author($this->meta['author']);
			unset($this->meta['author']);
		}
		
		# Page-specific meta tags. todo: do this in the GBPage subclass in some nice way
		if ($this instanceof GBPage) {
			# transfer order meta tag
			static $order_aliases = array('order', 'sort', 'priority');
			foreach ($order_aliases as $singular) {
				if (isset($this->meta[$singular])) {
					$this->order = $this->meta[$singular];
					unset($this->meta[$singular]);
				}
			}
			
			# transfer hidden meta tag
			static $hidden_aliases = array('hidden', 'hide', 'invisible');
			foreach ($hidden_aliases as $singular) {
				if (isset($this->meta[$singular])) {
					$s = $this->meta[$singular];
					$this->hidden = ($s === '' || gb_strbool($s));
					unset($this->meta[$singular]);
				}
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
		return str_replace('%2F', '/', urlencode($this->slug));
	}
	
	function url() {
		return gb::$site_url . gb::$index_prefix . $this->urlpath();
	}
	
	function commentsStageName() {
		# not the same as gb::$comments_cache_fnext
		return gb_filenoext($this->name).'.comments';
	}
	
	function getCommentsDB() {
		return new GBCommentDB(gb::$site_dir.'/'.$this->commentsStageName(), $this);
	}
	
	function commentsLink($prefix='', $suffix='', $template='<a href="%u" class="numcomments" title="%t">%n</a>') {
		if (!$this->comments)
		 	return '';
		return strtr($template, array(
			'%u' => h($this->url()).'#comments',
			'%n' => is_int($this->comments) ? $this->comments : $this->comments->countApproved(),
			'%t' => $this->numberOfComments()
		));
	}
	
	function tagLinks($prefix='', $suffix='', $template='<a href="%u">%n</a>', $nglue=', ', $endglue=' and ') {
		return $this->collLinks('tags', $prefix, $suffix, $template, $nglue, $endglue);
	}
	
	function categoryLinks($prefix='', $suffix='', $template='<a href="%u">%n</a>', $nglue=', ', $endglue=' and ') {
		return $this->collLinks('categories', $prefix, $suffix, $template, $nglue, $endglue);
	}
	
	function collLinks($what, $prefix='', $suffix='', $template='<a href="%u">%n</a>', $nglue=', ', $endglue=' and ', $htmlescape=true) {
		if (!$this->$what)
			return '';
		$links = array();
		$vn = $what.'_prefix';
		$u = gb::$site_url . gb::$index_prefix . gb::$$vn;
		foreach ($this->$what as $tag)
			$links[] = strtr($template, array('%u' => $u.urlencode($tag), '%n' => h($tag)));
		return $nglue !== null ? $prefix.sentenceize($links, null, $nglue, $endglue).$suffix : $links;
	}
	
	function numberOfComments($topological=true, $sone='comment', $smany='comments', $zero='No', $one='One') {
		return counted($this->comments ? (is_int($this->comments) ? $this->comments : $this->comments->countApproved($topological)) : 0,
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
		static $members = array(
			'slug','meta','title','body','excerpt',
			'tags','categories',
			'comments',
			'commentsOpen','pingbackOpen',
			'draft');
		return array_merge(parent::__sleep(), $members);
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
		return @unserialize(file_get_contents(gb::$site_dir.'/.git/info/gitblog/'.$cachename));
	}
	
	static function findByTags($tags, $pageno=0) {
		return self::findByMetaIndex($tags, 'tag-to-objs', $pageno);
	}
	
	static function findByCategories($cats, $pageno=0) {
		return self::findByMetaIndex($cats, 'category-to-objs', $pageno);
	}
	
	static function findByMetaIndex($tags, $indexname, $pageno) {
		$index = GBObjectIndex::getNamed($indexname);
		$objs = self::_findByMetaIndex($tags, $index);
		if (!$objs)
			return false;
		$page = GBPagedObjects::split($objs, $pageno);
		if ($page !== false) {
			if ($pageno === null) {
				# no pageno specified returns a list of all pages
				foreach ($page as $p)
					$p->posts = new GBLazyObjectsIterator($p->posts);
			}
			else {
				# specific, single page
				$page->posts = new GBLazyObjectsIterator($page->posts);
			}
		}
		return $page;
	}
	
	static function _findByMetaIndex($tags, $index, $op='and') {
		$objs = array();
		
		# no tags, no objects
		if (!$tags)
			return $objs;
		
		# single tag is a simple operation
		if (count($tags) === 1)
			return isset($index[$tags[0]]) ? $index[$tags[0]] : $objs;
		
		# multiple AND
		if ($op === 'and')
			return self::_findByMetaIndexAND($tags, $index);
		else
			throw new InvalidArgumentException('currently the only supported operation is "and"');
	}
	
	static function _findByMetaIndexAND($tags, $index) {
		$cachenames = array();
		$intersection = array_intersect(array_keys($index), $tags);
		$rindex = array();
		$seen = array();
		$iteration = 0;
		
		foreach ($intersection as $tag) {
			$found = 0;
			foreach ($index[$tag] as $objcachename) {
				if (!isset($rindex[$objcachename])) {
					if ($iteration)
						break;
					$rindex[$objcachename] = array($tag);
				}
				else
					$rindex[$objcachename][] = $tag;
				$found++;
			}
			if ($found === 0)
				break;
			$iteration++;
		}
		
		# only keep cachenames which matched at least all $tags
		$len = count($tags);
		foreach ($rindex as $cachename => $matched_tags)
			if (count($matched_tags) >= $len)
				$cachenames[] = $cachename;
		
		return $cachenames;
	}
	
	function domID() {
		return 'post-'.$this->published->utcformat('%Y-%m-%d-')
			. preg_replace('/[^A-Za-z0-9_-]+/', '-', $this->slug);
	}
}


class GBLazyObjectsIterator implements Iterator {
	public $objects;
	
	function __construct($cachenames, $condensed=true) {
		$this->objects = array_flip($cachenames);
		$this->condensed = $condensed;
	}
	
	public function rewind() {
		reset($this->objects);
	}

	public function current() {
		$v = current($this->objects);
		if (!is_object($v)) {
			$v = GBExposedContent::findByCacheName(key($this->objects));
			if ($this->condensed)
				$v = $v->condensedVersion();
			$this->objects[key($this->objects)] = $v;
		}
		return $v;
	}

	public function key() {
		return key($this->objects);
	}

	public function next() {
		return next($this->objects);
	}

	public function valid() {
		return current($this->objects) !== false;
	}
}


class GBPagedObjects {
	public $posts;
	public $nextpage = -1;
	public $prevpage = -1;
	public $numpages = 0;
	public $numtotal = 0;
	
	function __construct($posts, $nextpage=-1, $prevpage=-1, $numpages=0, $numtotal=0) {
		$this->posts    = $posts;
		$this->nextpage = $nextpage;
		$this->prevpage = $prevpage;
		$this->numpages = $numpages;
		$this->numtotal = $numtotal;
	}
	
	static function split($posts, $onlypageno=null, $pagesize=null) {
		$numtotal = count($posts);
		$pages = array_chunk($posts, $pagesize === null ? gb::$posts_pagesize : $pagesize);
		$numpages = count($pages);
		
		if ($onlypageno !== null && $onlypageno > $numpages)
			return false;
		
		foreach ($pages as $pageno => $page) {
			if ($onlypageno !== null && $onlypageno !== $pageno)
				continue;
			$page = new self($page, -1, $pageno-1, $numpages, $numtotal);
			if ($pageno < $numpages-1)
				$page->nextpage = $pageno+1;
			if ($onlypageno !== null)
				return $page;
			$pages[$pageno] = $page;
		}
		
		return $pages;
	}
}


class GBPage extends GBExposedContent {
	public $order = null; # order in menu, etc.
	public $hidden = false; # hidden from menu, but still accessible (i.e. not the same thing as $draft)
	
	function isCurrent() {
		if (!gb::$is_page)
			return false;
		$url = gb::url();
		return (strcasecmp(rtrim(substr($url->path, 
			strlen(gb::$site_path.gb::$index_prefix.gb::$pages_prefix)),'/'), $this->slug) === 0);
	}
	
	static function mkCachename($slug) {
		return 'content/pages/'.$slug.gb::$content_cache_fnext;
	}
	
	static function pathToCached($slug) {
		return parent::pathToCached('pages', $slug . gb::$content_cache_fnext);
	}
	
	static function find($slug, $work_version=null) {
		return parent::find($slug, 'pages', $class='GBPage', $work_version);
	}
	
	static function urlTo($slug) {
		return gb::$site_url . gb::$index_prefix . gb::$pages_prefix . $slug;
	}
	
	function __sleep() {
		return array_merge(parent::__sleep(), array('order', 'hidden'));
	}
}


class GBPost extends GBExposedContent {
	function urlpath() {
		return $this->published->utcformat(gb::$posts_prefix)
			. str_replace('%2F', '/', urlencode($this->slug));
	}
	
	function cachename() {
		return self::mkCachename($this->published, $this->slug);
	}
	
	static function mkCachename($published, $slug) {
		# Note: the path prefix is a dependency for GBContentFinalizer::finalize
		return 'content/posts/'.$published->utcformat(gb::$posts_cn_pattern).$slug.gb::$content_cache_fnext;
	}
	
	static function cachenameFromURI($slug, &$strptime) {
		if ($strptime === null || $strptime === false)
			$strptime = strptime($slug, gb::$posts_prefix);
		return gmstrftime(gb::$posts_cn_pattern, gb_mkutctime($strptime))
			.$strptime['unparsed'];
	}
	
	static function pageByPageno($pageno) {
		$path = self::pathToPage($pageno);
		$data = @file_get_contents($path);
		return $data === false ? false : unserialize($data);
	}
	
	static function pathToPage($pageno) {
		return gb::$site_dir . sprintf('/.git/info/gitblog/content-paged-posts/%011d', $pageno);
	}
	
	static function pathToCached($slug, $strptime=null) {
		$path = self::cachenameFromURI($slug, $strptime);
		return GBExposedContent::pathToCached('posts', $path . gb::$content_cache_fnext);
	}
	
	/** Find path to work content of a post. Returns null if not found. */
	static function pathToWork($slug, $strptime=null) {
		$cachename = self::cachenameFromURI($slug, $strptime);
		$basedir = gb::$site_dir.'/content/posts/';
		$glob_skip = '/(\.comments|\/)$/';
		
		# first, try if the post resides under the cachename, but in the workspace:
		$path = $basedir . $cachename;
		if (is_file($path))
			return $path;
		
		# try any file with the cachename as prefix
		if ( ($path = gb::glob($path . '*', $glob_skip)) )
			return $path;
		
		# next, try a wider glob search
		# todo: optimise: find minimum time resolution by examining $posts_cn_pattern
		#                 for now we will assume the default resolution/granularity of 1 month.
		$path = $basedir
			. gmstrftime('{%Y,%y,%G,%g}{?,/}%m', gb_mkutctime($strptime))
			. '{*,*/*,*/*/*,*/*/*/*}' . $strptime['unparsed'] . '*';
		if ( ($path = gb::glob($path, $glob_skip)) )
			return $path;
		
		# we're out of luck :(
		return null;
	}
	
	static function findByDateAndSlug($published, $slug) {
		$path = gb::$site_dir.'/.git/info/gitblog/'.self::mkCachename($published, $slug);
		return @unserialize(file_get_contents($path));
	}
	
	static function find($slug, $work_version=null, $strptime=null) {
		if ($work_version === null)
			$work_version = gb::$is_preview;
		if ($work_version) {
			# find path to raw content
			if (($path = self::pathToWork($slug, $strptime)) === null)
				return false;
			
			# parse pathspec, producing date and actual slug needed to look up cached
			self::parsePathspec(self::pathspecFromAbsPath($path), $date, $slug, $fnext);
			
			# try to find a cached version
			$post = self::findByDateAndSlug($date, $slug);
			
			# load work
			return self::loadWork($path, $post, 'GBPost', null, $slug);
		}
		else {
			$path = self::pathToCached($slug, $strptime);
			$data = @file_get_contents($path);
			return $data === false ? false : unserialize($data);
		}
	}
	
	/**
	 * Parse a pathspec for a post into date, slug and file extension.
	 * 
	 * Example:
	 * 
	 *  content/posts/2008-08/29-reading-a-book.html
	 *    date: GBDateTime(2008-08-29T00:00:00Z) (resolution restricted by gb::$posts_cn_pattern)
	 *    slug: "reading-a-book"
	 *    fnext: "html"
	 */
	static function parsePathspec($pathspec, &$date, &$slug, &$fnext) {
		# cut away prefix "content/posts/"
		$name = substr($pathspec, 14);
		
		# split filename from filename extension
		$lastdot = strrpos($name, '.', strrpos($name, '/'));
		if ($lastdot !== false) {
			$fnext = substr($name, $lastdot+1);
			$name = substr($name, 0, $lastdot);
		}
		else {
			$fnext = null;
		}
		
		# parse date and slug
		static $subchars = '._/';
		static $repchars = '---';
		static $ptimes = array(
			'%Y-%m-%d' => 10, '%y-%m-%d' => 8, '%G-%m-%d' => 10, '%g-%m-%d' => 8,
			'%Y-%m' => 7, '%y-%m' => 5, '%G-%m' => 7, '%g-%m' => 5,
			'%Y' => 4, '%y' => 2, '%G' => 4, '%g' => 2
		);
		$nametest = strtr($name, $subchars, $repchars);
		foreach ($ptimes as $pattern => $patter_len) {
			if (($st = strptime($nametest, $pattern)) !== false) {
				$slug = ltrim(substr($name, $patter_len), $subchars . '-');
				break;
			}
		}
		
		# failed to parse	
		if ($st === false)
			throw new UnexpectedValueException('unable to parse date from '.var_export($pathspec,1));
		
		$date = gmstrftime('%FT%T+00:00', gb_mkutctime($st));
		#$slug = ltrim($st['unparsed'], '-');
		$date = new GBDateTime($date.'T00:00:00Z');
	}
}


class GBComments extends GBContent implements IteratorAggregate, Countable {
	/** [GBComment, ..] */
	public $comments = array();
	public $cachenamePrefix;
	
	function __construct($name=null, $id=null, $cachenamePrefix=null, $comments=null) {
		parent::__construct($name, $id);
		$this->cachenamePrefix = $cachenamePrefix;
		if ($comments !== null)
			$this->comments = $comments;
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
		return gb_filenoext($this->cachenamePrefix).gb::$comments_cache_fnext;
	}
	
	static function find($cachenamePrefix) {
		$path = gb::$site_dir.'/.git/info/gitblog/'.gb_filenoext($cachenamePrefix).gb::$comments_cache_fnext;
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
			else {
				return false;
			}
		}
		if ($this->onlyApproved) {
			$comment = current($this->comments);
			while ($comment && !$comment->approved) {
				$this->next();
				$comment = current($this->comments);
			}
			return $comment ? true : false;
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
	
	# members below are only serialized when non-null
	public $spam;
	
	# members below are never serialized
	
	/** GBExposedContent set when appropriate. Might be null. */
	public $post;
	
	/** String id (indexpath), available during iteration, etc. */
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
	
	function duplicate(GBComment $other) {
		return (($this->email === $other->email) && ($this->body === $other->body));
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
	
	function avatarURL($size=48, $fallback_url='') {
		$s = 'http://www.gravatar.com/avatar.php?gravatar_id='
			.md5($this->email) 
			.'&size='.$size;
		if ($fallback_url) {
			if ($fallback_url{0} !== '/')
				$fallback_url = gb::$theme_url . $fallback_url;
			$s .= '&default='. urlencode($fallback_url);
		}
		return $s;
	}
	
	function _post($post=null) {
		if ($post === null) {
			if ($this->post !== null) {
				$post = $this->post;
			}
			else {
				unset($post);
				global $post;
			}
		}
		if (!$post)
			throw new UnexpectedValueException('unable to deduce $post needed to build url');
		return $post;
	}
	
	function commentsObject($post=null) {
		return $this->_post($post)->comments;
	}
	
	function _bounceURL($relpath, $post=null, $include_referrer=true) {
		$post = $this->_post($post);
		if ($this->id === null)
			throw new UnexpectedValueException('$this->id is null');
		$object = strpos(gb::$content_cache_fnext,'.') !== false ? gb_filenoext($post->cachename()) : $post->cachename();
		return gb::$site_url.$relpath.'object='
			.urlencode($object)
			.'&comment='.$this->id
			.($include_referrer ? '&referrer='.urlencode(gb::url()) : '');
	}
	
	function approveURL($post=null, $include_referrer=true) {
		return $this->_bounceURL('gitblog/admin/approve-comment.php?action=approve&',
			$post, $include_referrer);
	}
	
	function unapproveURL($post=null, $include_referrer=true) {
		return $this->_bounceURL('gitblog/admin/approve-comment.php?action=unapprove&',
			$post, $include_referrer);
	}
	
	function hamURL($post=null, $include_referrer=true) {
		return $this->_bounceURL('gitblog/admin/spam-comment.php?action=ham&',
			$post, $include_referrer);
	}
	
	function spamURL($post=null, $include_referrer=true) {
		return $this->_bounceURL('gitblog/admin/spam-comment.php?action=spam&',
			$post, $include_referrer);
	}
	
	function removeURL($post=null, $include_referrer=true) {
		return $this->_bounceURL('gitblog/admin/remove-comment.php?',
			$post, $include_referrer);
	}
	
	function commentURL($post=null) {
		$post = $this->_post($post);
		if ($this->id === null)
			throw new UnexpectedValueException('$this->id is null');
		return $post->url().'#comment-'.$this->id;
	}
	
	function __sleep() {
		$members = array('date','ipAddress','email','uri','name','body',
			'approved','comments','type','id');
		if ($this->spam !== null)
			$members[] = 'spam';
		return $members;
	}
}


class GBObjectIndex {
	static public $loadcache = array();
	
	static function mkCachename($name) {
		return $name.'.index';
	}
	
	static function pathForName($name) {
		return gb::$site_dir.'/.git/info/gitblog/'.self::mkCachename($name);
	}
	
	static function getNamed($name) {
		if (isset(self::$loadcache[$name]))
			return self::$loadcache[$name];
		gb::catch_errors();
		$obj = unserialize(file_get_contents(self::pathForName($name)));
		self::$loadcache[$name] =& $obj;
		return $obj;
	}
}


# -----------------------------------------------------------------------------
# Users

# todo: use JSONStore for GBUserAccount
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
		if (file_exists(gb::$site_dir.'/gb-users.php')) {
			include gb::$site_dir.'/gb-users.php';
			if (isset($db))
				self::$db = $db;
		}
		else {
			self::$db = array();
		}
	}
	
	static function sync() {
		if (self::$db === null)
			return;
		$r = file_put_contents(gb::$site_dir.'/gb-users.php', 
			'<? $db = '.var_export(self::$db, 1).'; ?>', LOCK_EX);
		chmod(gb::$site_dir.'/gb-users.php', 0660);
		return $r;
	}
	
	static function passhash($email, $passphrase, $context='gb-admin') {
		return chap::shadow($email, $passphrase, $context);
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
	
	static function &get($email=null) {
		static $n = null;
		if (self::$db === null)
			self::_reload();
		if ($email === null)
			return self::$db;
		$email = strtolower($email);
		if (isset(self::$db[$email]))
		 	return self::$db[$email];
		return $n;
	}
	
	static function formatGitAuthor($account, $fallback=null) {
		if (!$account)
			throw new InvalidArgumentException('first argument is empty');
		$s = '';
		if ($account->name)
			$s = $account->name . ' ';
		if ($account->email)
			$s .= '<'.$account->email.'>';
		if (!$s) {
			if ($fallback === null)
				throw new InvalidArgumentException('neither name nor email is set');
			$s = $fallback;
		}
		return $s;
	}
	
	function gitAuthor() {
		return self::formatGitAuthor($this);
	}
	
	function __toString() {
		return $this->gitAuthor();
	}
}

# -----------------------------------------------------------------------------
# Nonce

function gb_nonce_time($ttl) {
	return (int)ceil(time() / ($ttl / 2));
}

function gb_nonce_make($context='', $ttl=86400) {
	return gb_hash(gb_nonce_time($ttl) . $context . $_SERVER['REMOTE_ADDR']);
}

function gb_nonce_verify($nonce, $context='', $ttl=86400) {
	$nts = gb_nonce_time($ttl);
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
	
	static function set($email=null, $name=null, $url=null, $cookiename='gb-author') {
		if (self::$cookie === null)
			self::$cookie = array();
		if ($email !== null) self::$cookie['email'] = $email;
		if ($name !== null) self::$cookie['name'] = $name;
		if ($url !== null) self::$cookie['url'] = $url;
		$cookie = rawurlencode(serialize(self::$cookie));
		$cookieurl = new GBURL(gb::$site_url);
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

function gb_site_title($link=true, $linkattrs='') {
	if (!$link)
		return h(gb::$site_title);
	return '<a href="'.gb::$site_url.'"'.$linkattrs.'>'.h(gb::$site_title).'</a>';
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
		$html .= '<input type="hidden" name="gb-referrer" value="'. h(gb::url()) . '" />';
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
	if (!$value) {
		$value = $default_value;
		$attrs .= ' class="default" ';
	}
	return '<input type="text" id="'.$id_prefix.'author-'.$what.'" name="author-'
		.$what.'" value="'.h($value).'"'
		.' onfocus="if(this.value==unescape(\''.rawurlencode($default_value).'\')){this.value=\'\';this.className=\'\';}"'
		.' onblur="if(this.value==\'\'){this.value=unescape(\''.rawurlencode($default_value).'\');this.className=\'default\';}"'
		.' '.$attrs.' />';
}

function gb_comment_fields($post=null, $id_prefix='comment-') {
	if ($post === null) {
		unset($post);
		global $post;
	}
	$post_cachename = strpos(gb::$content_cache_fnext,'.') !== false ? gb_filenoext($post->cachename()) : $post->cachename();
	$nonce_context = 'post-comment-'.$post_cachename;
	return gb_nonce_field($nonce_context, true, $id_prefix)
		. gb_timezone_offset_field($id_prefix.'client-timezone-offset')
		. '<input type="hidden" id="'.$id_prefix.'reply-post" name="reply-post" value="'.h($post_cachename).'" />'
		. '<input type="hidden" id="'.$id_prefix.'reply-to" name="reply-to" value="" />';
}

function gb_tag_link($tag, $template='<a href="%u">%n</a>') {
	$u = gb::$site_url . gb::$index_prefix . gb::$tags_prefix;
	return strtr($template, array('%u' => $u.urlencode($tag), '%n' => h($tag)));
}

function sorted($iterable, $reverse_or_sortfunc=null, $sort_flags=SORT_REGULAR) {
	if ($reverse_or_sortfunc === null || $reverse_or_sortfunc === false)
		asort($iterable, $sort_flags);
	elseif ($reverse_or_sortfunc === true)
		arsort($iterable, $sort_flags);
	else
		uasort($iterable, $reverse_or_sortfunc);
	return $iterable;
}

function ksorted($iterable, $reverse_or_sortfunc=null, $sort_flags=SORT_REGULAR) {
	if ($reverse_or_sortfunc === null || $reverse_or_sortfunc === false)
		ksort($iterable, $sort_flags);
	elseif ($reverse_or_sortfunc === true)
		krsort($iterable, $sort_flags);
	else
		uksort($iterable, $reverse_or_sortfunc);
	return $iterable;
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
/**
 * Request handler.
 * 
 * Activated by setting $gb_handle_request = true before loading gitblog.php.
 * This construction is intended for themes.
 * 
 * Example of a theme:
 * 
 *   <?
 *   $gb_handle_request = true;
 *   require './gitblog/gitblog.php';
 *   # send response based on gb::$is_* properties ...
 *   ?>
 * 
 * Global variables:
 * 
 *  - $gb_request_uri
 *    Always available when parsing the request and contains the requested
 *    path -- anything after gb::$index_prefix. For example: "2009/07/some-post"
 *    when the full url (aquireable through gb::url()) might be
 *    "http://host/blog/2009/07/some-post"
 * 
 *  - $gb_time_started
 *    Always available and houses the microtime(true) when gitblog started to
 *    execute. This is used by various internal mechanisms, so please do not
 *    alter the value or Bad Things (TM) might happen.
 * 
 *  - $post
 *    Available for requests of posts and pages in which case its value is an
 *    instance of GBExposedContent (or a subclass thereof). However; the value
 *    is false if gb::$is_404 is set.
 * 
 *  - $postspage
 *    Available for requests which involve pages of content: the home page,
 *    feed (gb::$feed_prefix), content filed under a category/ies
 *    (gb::$categories_prefix), content taged with certain tags
 *    (gb::$tags_prefix).
 * 
 *  - $tags
 *    Available for requests listing content taged with certain tags
 *    (gb::$tags_prefix).
 * 
 *  - $categories
 *    Available for requests listing content taged with certain tags
 *    (gb::$categories_prefix).
 * 
 * Events:
 * 
 *  - "will-parse-request"
 *    Posted before gitblog parses the request. For example, altering the
 *    global variable $gb_request_uri will cause gitblog to handle a
 *    different request than initially intended.
 * 
 *  - "will-handle-request"
 *    Posted after the request has been parsed but before gitblog handles it.
 * 
 *  - "did-handle-request"
 *    Posted after the request have been handled but before any deferred code
 *    is executed.
 * 
 * When observing these events, the Global variables and the gb::$is_*
 * properties should provide good grounds for taking descisions and/or changing
 * the outcome.
 * 
 * Before the request is parsed, any activated "online" plugins will be given
 * a chance to initialize.
 */
if (isset($gb_handle_request) && $gb_handle_request === true) {
	$gb_request_uri = isset($_SERVER['PATH_INFO']) ? trim($_SERVER['PATH_INFO'], '/') : '';
	
	# verify integrity and config
	gb::verify();
	
	# authed?
	if (isset($_COOKIE['gb-chap']) && $_COOKIE['gb-chap']) {
		gb::authenticate(false);
		# now, gb::$authorized (a GBUserAccount) is set (authed ok) or a CHAP
		# constant (not authed).
	}
	
	# transfer errors from ?gb-error to gb::$errors
	if (isset($_GET['gb-error']) && $_GET['gb-error']) {
		if (is_array($_GET['gb-error']))
			gb::$errors = array_merge(gb::$errors, $_GET['gb-error']);
		else
			gb::$errors[] = $_GET['gb-error'];
	}
	
	# preview mode?
	if (isset($_GET[gb::$preview_query_key]) && gb::$authorized)
		gb::$is_preview = true;
	
	# load plugins
	gb::load_plugins('online');
	
	gb::event('will-parse-request');
	
	if ($gb_request_uri) {
		if (strpos($gb_request_uri, gb::$categories_prefix) === 0) {
			# category(ies)
			$categories = array_map('urldecode', explode(',', 
				substr($gb_request_uri, strlen(gb::$categories_prefix))));
			$postspage = GBExposedContent::findByCategories($categories, 
				isset($_REQUEST['page']) ? intval($_REQUEST['page']) : 0);
			gb::$is_categories = true;
			gb::$is_404 = $postspage === false;
		}
		elseif (strpos($gb_request_uri, gb::$tags_prefix) === 0) {
			# tag(s)
			$tags = array_map('urldecode', explode(',',
				substr($gb_request_uri, strlen(gb::$tags_prefix))));
			$postspage = GBExposedContent::findByTags($tags,
				isset($_REQUEST['page']) ? intval($_REQUEST['page']) : 0);
			gb::$is_tags = true;
			gb::$is_404 = $postspage === false;
		}
		elseif (strpos($gb_request_uri, gb::$feed_prefix) === 0) {
			# feed
			$postspage = GBPost::pageByPageno(0);
			gb::$is_feed = true;
			gb::event('will-handle-request');
			# if the theme has a "feed.php" file, include that one
			if (is_file(gb::$theme_dir.'/feed.php')) {
				require gb::$theme_dir.'/feed.php';
			}
			# otherwise we'll handle the feed
			else {
				require gb::$dir.'/helpers/feed.php';
			}
			exit(0);
		}
		elseif (($strptime = strptime($gb_request_uri, gb::$posts_prefix)) !== false) {
			# post
			$post = GBPost::find(urldecode($gb_request_uri), gb::$is_preview, $strptime);
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
			$post = GBPage::find(urldecode($gb_request_uri), gb::$is_preview);
			if ($post === false)
				gb::$is_404 = true;
			else
				gb::$title[] = $post->title;
			gb::$is_page = true;
		}
	}
	else {
		# posts
		$postspage = GBPost::pageByPageno(isset($_REQUEST['page']) ? intval($_REQUEST['page']) : 0);
		gb::$is_posts = true;
		gb::$is_404 = $postspage === false;
	}
	
	gb::event('will-handle-request');
	register_shutdown_function(array('gb','event'), 'did-handle-request');
	
	# from here on, the caller will have to do the rest
}
?>
