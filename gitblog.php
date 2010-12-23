<?php
error_reporting(E_ALL);
$gb_time_started = microtime(true);
date_default_timezone_set(@date_default_timezone_get());

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
	
	/** Enables fuzzy URI matching of posts */
	static public $posts_fuzzy_lookup = true;
	
	/** URL to gitblog index _relative_ to gb::$site_url */
	static public $index_prefix = 'index.php';

	/** 'PATH_INFO' or any other string which will then be matched in $_GET[string] */
	static public $request_query = 'PATH_INFO';
	
	/**
	 * When this query string key is set and the client is authorized,
	 * the same effect as setting $version_query_key to "work" is achieved.
	 */
	static public $preview_query_key = 'preview';
	
	/**
	 * When this query string key is set and the client is authorized, the
	 * specified version of a viewed post is displayed rather than the live
	 * version.
	 */
	static public $version_query_key = 'version';
	
	/**
	 * When this query string key is set and gb::$is_preview is true, the
	 * object specified by pathspec is loaded. This overrides parsing the URI
	 * and is needed in cases where there are multiple posts with the same
	 * name but with different file extensions (content types).
	 */
	static public $pathspec_query_key = 'pathspec';
	
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
	
	static public $version = '0.1.6';
	
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
		foreach (GBUser::find() as $email => $account) {
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
			gb::event('client-deauthorized', $old_authorized);
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
			self::$authorized = GBUser::find($authed);
			return self::$authorized;
		}
		elseif ($force) {
			$url = gb_admin::$url . 'helpers/authorize.php?referrer='.urlencode(self::url());
			header('HTTP/1.1 303 See Other');
			header('Location: '.$url);
			exit('<html><body>See Other <a href="'.$url.'"></a></body></html>');
		}
		return $authed;
	}
	
	# --------------------------------------------------------------------------
	# Plugins
	
	static public $plugins_loaded = array();
	
	static function plugin_check_enabled($context, $name) {
		$plugin_config = self::data('plugins');
		if (!isset($plugin_config[$context]))
			return false;
		$name = str_replace(array('-', '.'), '_', $name);
		foreach ($plugin_config[$context] as $path) {
			$plugin_name = str_replace(array('-', '.'), '_', substr(basename($path), 0, -4));
			if ($plugin_name == $name);
				return true;
		}
		return false;
	}
	
	static function load_plugins($context) {
		$plugin_config = self::data('plugins');
		if (!isset($plugin_config[$context]))
			return;
		$plugins = $plugin_config[$context];
		
		if (!is_array($plugins))
			return;
		
		# load plugins
		foreach ($plugins as $path) {
			if (!$path)
				continue;
			
			# expand gitblog plugins
			if ($path{0} !== '/')
				$path = gb::$dir . '/plugins/'.$path;
			
			# get loadstate
			$loadstate = null;
			if (isset(self::$plugins_loaded[$path]))
				$loadstate = self::$plugins_loaded[$path];
			
			# check loadstate
			if ($loadstate === null) {
				# load if not loaded
				require $path;
			}
			elseif (in_array($context, $loadstate, true)) {
				# already loaded and inited in this context
				continue;
			}
			
			# call name_plugin::init($context)
			$name = str_replace(array('-', '.'), '_', substr(basename($path), 0, -4)); # assume .xxx
			$did_init = call_user_func(array($name.'_plugin', 'init'), $context);
			if ($loadstate === null)
				self::$plugins_loaded[$path] = $did_init ? array($context) : array();
			elseif ($did_init)
				self::$plugins_loaded[$path][] = $context;
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
	static function filter($tag, $value/*, [arg ..] */) {
		$vargs = func_get_args();
		$tag = array_shift($vargs);
		if (!isset(self::$filters[$tag]))
			return $value;
		$a = self::$filters[$tag];
		if ($a === null)
			return $value;
		ksort($a, SORT_NUMERIC);
		foreach ($a as $funcs) {
			foreach ($funcs as $func) {
				$value = call_user_func_array($func, $vargs);
				$vargs[0] = $value;
			}
		}
		return $vargs[0];
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
						gb_strlimit(json_encode($f),40), get_class($e), $e->__toString());
				}
			}
		}
		catch (Exception $e) {
			gb::log(LOG_ERR, 'run_deferred failed with %s: %s', get_class($e), $e->__toString());
		}
	}
	
	# --------------------------------------------------------------------------
	# data -- arbitrary key-value storage
	
	static public $data_store_class = 'JSONDict';
	static public $data_stores = array();
	
	static function data($name, $default=null) {
		if (isset(self::$data_stores[$name]))
			return self::$data_stores[$name];
		$cls = self::$data_store_class;
		$store = new $cls($name);
		self::$data_stores[$name] = $store;
		if ($default && !is_array($store->storage()->get()))
			$store->storage()->set($default);
		return $store;
	}
	
	# --------------------------------------------------------------------------
	# reading object indices
	
	static public $object_indices = array();
	
	static function index($name, $fallback=null) {
		if (isset(self::$object_indices[$name]))
			return self::$object_indices[$name];
		if ($fallback !== null) {
			$obj = @unserialize(file_get_contents(self::index_path($name)));
			if ($obj === false)
				return $fallback;
		}
		else
			$obj = unserialize(file_get_contents(self::index_path($name)));
		self::$object_indices[$name] = $obj;
		return $obj;
	}
	
	static function index_cachename($name) {
		return $name.'.index';
	}
	
	static function index_path($name) {
		return gb::$site_dir.'/.git/info/gitblog/'.self::index_cachename($name);
	}
	
	# --------------------------------------------------------------------------
	# GitBlog
	
	static public $rebuilders = array();
	
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
		return gb::index($indexname);
	}
	
	static function categories($indexname='category-to-objs') {
		return gb::index($indexname);
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
	
	static function init($add_sample_content=true, $shared='true', $theme='default', $mkdirmode=0775) {
		# sanity check
		$themedir = gb::$dir.'/themes/'.$theme;
		if (!is_dir($themedir)) {
			throw new InvalidArgumentException(
				'no theme named '.$theme.' ('.$themedir.'not found or not a directory)');
		}
		
		# git init
		git::init(null, null, $shared);
		
		# Create empty standard directories
		mkdir(gb::$site_dir.'/content/posts', $mkdirmode, true);
		chmod(gb::$site_dir.'/content', $mkdirmode);
		chmod(gb::$site_dir.'/content/posts', $mkdirmode);
		mkdir(gb::$site_dir.'/content/pages', $mkdirmode);
		chmod(gb::$site_dir.'/content/pages', $mkdirmode);
		mkdir(gb::$site_dir.'/data', $mkdirmode);
		chmod(gb::$site_dir.'/data', $mkdirmode);
		
		# Create hooks and set basic config
		gb_maint::repair_repo_setup();
		
		# Copy default data sets
		$data_skeleton_dir = gb::$dir.'/skeleton/data';
		foreach (scandir($data_skeleton_dir) as $name) {
			if ($name{0} !== '.') {
				$path = $data_skeleton_dir.'/'.$name;
				if (is_file($path)) {
					copy($path, gb::$site_dir.'/data/'.$name);
					chmod(gb::$site_dir.'/data/'.$name, 0664);
				}
			}
		}
		
		# Copy .gitignore
		copy(gb::$dir.'/skeleton/gitignore', gb::$site_dir.'/.gitignore');
		chmod(gb::$site_dir.'/.gitignore', 0664);
		git::add('.gitignore');
		
		# Copy theme
		$lnname = gb::$site_dir.'/index.php';
		$lntarget = gb_relpath($lnname, $themedir.'/index.php');
		symlink($lntarget, $lnname) or exit($lntarget);
		git::add('index.php');
		
		# Add gb-config.php (might been added already, might be missing and/or
		# might be ignored by custom .gitignore -- doesn't really matter)
		git::add('gb-config.php', false);
		
		# Add sample content
		if ($add_sample_content) {
			# Copy example "about" page
			copy(gb::$dir.'/skeleton/content/pages/about.html', gb::$site_dir.'/content/pages/about.html');
			chmod(gb::$site_dir.'/content/pages/about.html', 0664);
			git::add('content/pages/about.html');
			
			# Copy example "about/intro" snippet
			mkdir(gb::$site_dir.'/content/pages/about', $mkdirmode);
			chmod(gb::$site_dir.'/content/pages/about', $mkdirmode);
			copy(gb::$dir.'/skeleton/content/pages/about/intro.html', gb::$site_dir.'/content/pages/about/intro.html');
			chmod(gb::$site_dir.'/content/pages/about/intro.html', 0664);
			git::add('content/pages/about/intro.html');
		
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
			git::add($name);
		}
		
		return true;
	}
	
	static function version_parse($s) {
		if (is_int($s))
			return $s;
		$v = array_map('intval', explode('.', $s));
		if (count($v) < 3)
			return 0;
		return ($v[0] << 16) + ($v[1] << 8) + $v[2];
	}

	static function version_format($v) {
		return sprintf('%d.%d.%d', $v >> 16, ($v & 0x00ff00) >> 8, $v & 0x0000ff);
	}
	
	/** Load the site state */
	static function load_site_state() {
		$path = self::$site_dir.'/data/site.json';
		$data = @file_get_contents($path);
		if ($data === false) {
			# version <= 0.1.3 ?
			if (is_readable(gb::$site_dir.'/site.json'))
				gb::$site_state = @json_decode(file_get_contents(gb::$site_dir.'/site.json'), true);
			return gb::$site_state !== null;
		}
		gb::$site_state = json_decode($data, true);
		if (gb::$site_state === null || is_string(gb::$site_state)) {
			self::log(LOG_WARNING, 'syntax error in site.json -- moved to site.json.broken and creating new');
			if (!rename($path, $path.'.broken'))
				self::log(LOG_WARNING, 'failed to move "%s" to "%s"', $path, $path.'.broken');
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
		$r = self::load_site_state();
		
		# check site state
		if ( $r === false 
			|| !isset(gb::$site_state['url'])
			|| !gb::$site_state['url']
			|| (
				gb::$site_state['url'] !== gb::$site_url
				&& strpos(gb::$site_url, '://localhost') === false
				&& strpos(gb::$site_url, '://127.0.0.1') === false
				)
			)
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
			header('HTTP/1.1 503 Service Unavailable');
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
ini_set('include_path', ini_get('include_path') . PATH_SEPARATOR. gb::$dir . '/lib');

if (gb::$request_query === 'PATH_INFO')
  gb::$index_prefix = rtrim(gb::$index_prefix, '/').'/';

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
		.$_SERVER['SERVER_NAME']
		.($_SERVER['SERVER_PORT'] !== '80' && $_SERVER['SERVER_PORT'] !== '443' ? ':'.$_SERVER['SERVER_PORT'] : '')
		.gb::$site_path;
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
# Define error handler which throws PHPException s

function gb_throw_php_error($errno, $errstr, $errfile=null, $errline=-1, $errcontext=null) {
	if(error_reporting() === 0)
		return;
	try { gb::vlog(LOG_WARNING, array($errstr), 2); } catch (Exception $e) {}
	if ($errstr)
		$errstr = html_entity_decode(strip_tags($errstr), ENT_QUOTES, 'UTF-8');
	throw new PHPException($errstr, $errno, $errfile, $errline);
}

set_error_handler('gb_throw_php_error', E_ALL);

#------------------------------------------------------------------------------
# Load configuration

if (file_exists(gb::$site_dir.'/gb-config.php'))
	include gb::$site_dir.'/gb-config.php';

# no config? -- read defaults
if (gb::$site_title === null) {
	require gb::$dir.'/skeleton/gb-config.php';
}

#------------------------------------------------------------------------------
# Setup autoload and exception handler

# Lazy class loader
function __autoload($classname) {
	require $classname . '.php';
}

function gb_exception_handler($e) {
	if (ini_get('html_errors')) {
		if (headers_sent())
			$msg = GBException::formatHTMLBlock($e);
		else
			$msg = GBException::formatHTMLDocument($e);
	}
	else
		$msg = GBException::format($e, true, false, null, 0);
	exit($msg);
}
set_exception_handler('gb_exception_handler');

# PATH patches: macports git. todo: move to admin/setup.php
//$_ENV['PATH'] .= ':/opt/local/bin';


#------------------------------------------------------------------------------
# Utilities

# These classes and functions are used in >=90% of all use cases -- that's why
# they are defined inline here in gitblog.php and not put in lazy files inside
# lib/.

/** Dictionary backed by a JSONStore */
class JSONDict implements ArrayAccess, Countable {
	public $file;
	public $skeleton_file;
	public $cache;
	public $storage;
	
	function __construct($name_or_path, $is_path=false, $skeleton_file=null) {
		$this->file = ($is_path === false) ? gb::$site_dir.'/data/'.$name_or_path.'.json' : $name_or_path;
		$this->cache = null;
		$this->storage = null;
		$this->skeleton_file = $skeleton_file;
	}
	
	/** Retrieve the underlying JSONStore storage */
	function storage() {
		if ($this->storage === null)
			$this->storage = new JSONStore($this->file, $this->skeleton_file);
		return $this->storage;
	}
	
	/**
	 * Higher level GET operation able to read deep values, which keys are
	 * separated by $sep.
	 */
	function get($key, $default=null, $sep='/') {
		if (!$sep) {
			if (($value = $this->offsetGet($key)) === null)
				return $default;
			return $value;
		}
		$keys = explode($sep, trim($key,$sep));
		if (($count = count($keys)) < 2) {
			if (($value = $this->offsetGet($key)) === null)
				return $default;
			return $value;
		}
		$value = $this->offsetGet($keys[0]);
		for ($i=1; $i<$count; $i++) {
			$key = $keys[$i];
			if (!is_array($value) || !isset($value[$key]))
				return $default;
			$value = $value[$key];
		}
		return $value;
	}
	
	/**
	 * Higher level PUT operation able to set deep values, which keys are
	 * separated by $sep.
	 */
	function put($key, $value, $sep='/') {
		$temp_tx = false;
		$keys = explode($sep, trim($key, $sep));
		if (($count = count($keys)) < 2)
			return $this->offsetSet($key, $value);
		
		$this->cache === null;
		$storage = $this->storage();
		if (!$storage->transactionActive()) {
			$storage->begin();
			$temp_tx = true;
		}
		try {
			$storage->get(); # make sure $storage->data is loaded
			
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
			if ($temp_tx === true)
				$storage->commit();
		}
		catch (Exception $e) {
			if ($temp_tx === true)
				$storage->rollback();
			throw $e;
		}
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
	
	function toJSON() {
		$json = trim(file_get_contents($this->file));
		return (!$json || $json{0} !== '{') ? '{}' : $json;
	}
	
	function __toString() {
		if ($this->cache === null)
			$this->cache = $this->storage()->get();
		return var_export($this->cache ,1);
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
	
	function __toString() {
		return $this->toString();
	}
	
	function toString($scheme=true, $host=true, $port=true, $path=true, $query=true, $fragment=true) {
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
	return var_export($var, true);
}

/** Ture $path is an absolute path */
function gb_isabspath($path) {
	return ($path && $path{0} === '/');
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

/** Sort GBContent objects on published, descending */
function gb_sortfunc_cobj_date_published_r(GBContent $a, GBContent $b) {
	return $b->published->time - $a->published->time;
}
function gb_sortfunc_cobj_date_modified_r(GBContent $a, GBContent $b) {
	return $b->modified->time - $a->modified->time;
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

/**
 * Commit id of current gitblog head. Used for URLs which should be
 * cached in relation to gitblog versions.
 */
function gb_headid() {
	if (gb::$site_state !== null && @isset(gb::$site_state['gitblog']) && @isset(gb::$site_state['gitblog']['head']))
		return gb::$site_state['gitblog']['head'];
	return null;
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

if (function_exists('mb_substr')) {
	function gb_strlimit($str, $limit=20, $ellipsis='…') {
		if (mb_strlen($str, 'utf-8') > $limit)
			return rtrim(mb_substr($str,0,$limit-mb_strlen($ellipsis, 'utf-8'), 'utf-8')).$ellipsis;
		return $str;
	}	
}
else {
	function gb_strlimit($str, $limit=20, $ellipsis='…') {
		if (strlen($str) > $limit)
			return rtrim(substr($str,0,$limit-strlen($ellipsis))).$ellipsis;
		return $str;
	}
}

function gb_strbool($s, $empty_is_true=false) {
	$s = strtoupper($s);
	return ( $s === 'TRUE' || $s === 'YES' || $s === '1' || $s === 'ON' || 
		($s === '' && $empty_is_true) );
}

function gb_strtodomid($s) {
	return trim(preg_replace('/[^A-Za-z0-9_-]+/m', '-', $s), '-');
}

function gb_tokenize_html($html) {
	return preg_split('/(<.*>|\[.*\])/Us', $html, -1, 
		PREG_SPLIT_DELIM_CAPTURE|PREG_SPLIT_NO_EMPTY);
}

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
			. ($tzformat ? self::formatTimezoneOffset($this->offset, $tzformat) : '');
	}
	
	function condensed($ranges=array(86400=>'%H:%M', 31536000=>'%b %e'), $compared_to=null) {
		if ($compared_to === null)
			$diff = time() - $this->time;
		elseif (is_int($compared_to))
			$diff = $compared_to - $this->time;
		else
			$diff = $compared_to->time - $this->time;
		ksort($ranges);
		$default_format = isset($ranges[0]) ? array_shift($ranges) : '%Y-%m-%d';
		# 1, 4, 129
		foreach ($ranges as $threshold => $format) {
			#printf('test %d vs %d (%s, %s)', $diff, $threshold, $format, $this);
			if ($diff < $threshold)
				return $this->origformat($format, false);
		}
		return $this->origformat($default_format, false);
	}
	
	/** Relative age */
	function age($threshold=null, $yformat=null, $absformat=null, $suffix=null, 
		           $compared_to=null, $momentago=null, $prefix=null)
	{
		if ($threshold === null) $threshold = 2592000; # 30 days
		if ($yformat === null) $yformat='%B %e';
		if ($absformat === null) $absformat='%B %e, %Y';
		if ($suffix === null) $suffix=' ago';
		if ($prefix === null) $prefix='';
		if ($momentago === null) $momentago='A second';
		
		if ($compared_to === null)
			$diff = time() - $this->time;
		elseif (is_int($compared_to))
			$diff = $compared_to - $this->time;
		else
			$diff = $compared_to->time - $this->time;
		
		if ($diff < 0)
			$diff = -$diff;
		
		if ($diff >= $threshold)
			return $this->origformat($diff < 31536000 ? $yformat : $absformat, false);
		
		if ($diff < 5)
			return $prefix.$momentago.$suffix;
		elseif ($diff < 50)
			return $prefix.$diff.' '.($diff === 1 ? 'second' : 'seconds').$suffix;
		elseif ($diff < 3000) {
			$diff = (int)round($diff / 60);
			return $prefix.$diff.' '.($diff === 1 ? 'minute' : 'minutes').$suffix;
		}
		elseif ($diff < 83600) {
			$diff = (int)round($diff / 3600);
			return $prefix.$diff.' '.($diff === 1 ? 'hour' : 'hours').$suffix;
		}
		elseif ($diff < 604800) {
			$diff = (int)round($diff / 86400);
			return $prefix.$diff.' '.($diff === 1 ? 'day' : 'days').$suffix;
		}
		elseif ($diff < 2628000) {
			$diff = (int)round($diff / 604800);
			return $prefix.$diff.' '.($diff === 1 ? 'week' : 'weeks').$suffix;
		}
		elseif ($diff < 31536000) {
			$diff = (int)round($diff / 2628000);
			return $prefix.$diff.' '.($diff === 1 ? 'month' : 'months').$suffix;
		}
		$diff = (int)round($diff / 31536000);
		return $prefix.$diff.' '.($diff === 1 ? 'year' : 'years').$suffix;
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
	
	static function __set_state($state) {
		if (is_array($state)) {
			$o = new self;
			foreach ($state as $k => $v)
				$o->$k = $v;
			return $o;
		}
		return new self($state);
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

class GBAuthor {
	public $name;
	public $email;
	
	function __construct($name=null, $email=null) {
		$this->name = $name;
		$this->email = $email;
	}
	
	static function parse($gitauthor) {
		$gitauthor = trim($gitauthor);
		$p = strpos($gitauthor, '<');
		$name = '';
		$email = '';
		if ($p === 0) {
			$email = trim($gitauthor, '<>');
		}
		elseif ($p === false) {
			if (strpos($gitauthor, '@') !== false)
				$email = $gitauthor;
			else
				$name = $gitauthor;
		}
		else {
			$name = rtrim(substr($gitauthor, 0, $p));
			$email = trim(substr($gitauthor, $p+1), '<>');
		}
		return new self($name, $email);
	}
	
	static function __set_state($state) {
		if (is_array($state)) {
			$o = new self;
			foreach ($state as $k => $v)
				$o->$k = $v;
			return $o;
		}
		elseif (is_object($state) && $state instanceof self) {
			return $state;
		}
		else {
			return self::parse($state);
		}
	}
	
	static function gitFormat($author, $fallback=null) {
		if (!$author)
			throw new InvalidArgumentException('first argument is empty');
		if (!is_object($author))
			$author = self::parse($author);
		$s = '';
		if ($author->name)
			$s = $author->name . ' ';
		if ($author->email)
			$s .= '<'.$author->email.'>';
		if (!$s) {
			if ($fallback === null)
				throw new InvalidArgumentException('neither name nor email is set');
			$s = $fallback;
		}
		return $s;
	}
	
	function shortName() {
		return array_shift(explode(' ', $this->gitAuthor()));
	}
	
	function gitAuthor() {
		return self::gitFormat($this);
	}
	
	function __toString() {
		return $this->gitAuthor();
	}
}

class GBContent {
	public $name; # relative to root tree
	public $id;
	public $mimeType = null;
	public $author = null;
	public $modified = null; # GBDateTime
	public $published = false; # GBDateTime
	
	function __construct($name=null, $id=null) {
		$this->name = $name;
		$this->id = $id;
	}
	
	function cachename() {
		return gb_filenoext($this->name).gb::$content_cache_fnext;
	}
	
	function writeCache() {
		gb::event('will-write-object-cache', $this);
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
		gb::event('did-write-object-cache', $this);
		return $bw;
	}
	
	function reload($data, $commits=null) {
		gb::event('will-reload-object', $this, $commits);
		$this->mimeType = GBMimeType::forFilename($this->name);
	}
	
	function findCommits() {
		if (!$this->name)
			throw new UnexpectedValueException('name property is empty');
		$v = GitCommit::find(array('names' => array($this->name)));
		if ($v)
			return $v[0];
		return array();
	}
	
	protected function applyInfoFromCommits($commits) {
		if (!$commits)
			return;
		
		gb::event('will-apply-info-from-commits', $this, $commits);
		
		# latest one is last modified
		$this->modified = $commits[0]->authorDate;
		
		# first one is when the content was created
		$initial = $commits[count($commits)-1];
		$this->published = $initial->authorDate;
		
		if (!$this->author)
			$this->author = new GBAuthor($initial->authorName, $initial->authorEmail);
		
		gb::event('did-apply-info-from-commits', $this, $commits);
	}
	
	function __sleep() {
		return array('name','id','mimeType','author','modified','published');
	}
	
	function toBlob() {
		// subclasses should implement this
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
	
	# not serialized but only used during runtime
	public $_rawBody = null;
	
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
	
	static function find($slug, $subdir='', $class='GBExposedContent', $version=null, $applyBodyFilters=true) {
		$version = self::parseVersion($version);
		if ($version === 'work') {
			# find path to raw content
			if (($path = self::pathToWork($subdir, $slug)) === null)
				return false;
			
			# find cached
			$cached = self::find($slug, $subdir, $class, false);
			
			# load work
			return self::loadWork($path, $cached, $class, null, $slug, $applyBodyFilters);
		}
		else {
			$path = self::pathToCached($subdir, $slug . gb::$content_cache_fnext);
			$data = @file_get_contents($path);
			return $data === false ? false : unserialize($data);
		}
	}
	
	static function parseVersion($version) {
		if ($version === null) {
			return gb::$is_preview ? 'work' : null;
		}
		elseif ($version === true) {
			return 'work';
		}
		else {
			$s = strtolower($version);
			if ($s === 'live' || $s === 'head' || $s === 'current')
				return null;
			return $version;
		}
	}
	
	static function loadWork($path, $post=false, $class='GBExposedContent', $id=null, $slug=null, $applyBodyFilters=true) {
		if ($post === false)
			$post = new $class(self::pathspecFromAbsPath($path), $id, $slug);
		
		$post->id = $id;
		
		gb::event('will-load-work-object', $post);
		
		# load rebuild plugins before calling reload
		gb::load_plugins('rebuild');
	
		# reload post with work data
		$post->reload(file_get_contents($path), null, $applyBodyFilters);
	
		# set modified date from file
		$post->modified = new GBDateTime(filemtime($path));
		
		# set author if needed
		if (!$post->author) {
			# GBUser have the same properties as the regular class-less author
			# object, so it's safe to just pass it on here, as a clone.
			if (gb::$authorized) {
				$post->author = clone gb::$authorized;
				unset($post->passhash);
			}
			elseif (($padmin = GBUser::findAdmin())) {
				$post->author = clone $padmin;
				unset($post->passhash);
			}
			else {
				$post->author = new GBAuthor();
			}
		}
		
		gb::event('did-load-work-object', $post);
	
		return $post;
	}
	
	static function findHeaderTerminatorOffset($data) {
		if (($offset = strpos($data, "\n\n")) !== false)
			return $offset+2;
		if (($offset = strpos($data, "\r\n\r\n")) !== false)
			return $offset+4;
		return false;
	}
	
	function parseData($data) {
		$this->body = null;
		$this->meta = array();
		
		# use meta for title if absent
		if ($this->title === null)
			$this->title = $this->slug;
		
		# find header terminator
		$bodystart = self::findHeaderTerminatorOffset($data);
		if ($bodystart === false) {
			$bodystart = 0;
			gb::log(LOG_WARNING,
				'malformed exposed content object %s: missing header and/or body (LFLF or CRLFCRLF not found)',
				$this->name);
		}
		else {
			$this->body = substr($data, $bodystart);
		}
		
		if ($bodystart !== false && $bodystart > 0)
			self::parseHeader(rtrim(substr($data, 0, $bodystart)), $this->meta);
	}
	
	static function parseHeader($lines, &$out) {
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
	
	function parseHeaderFields() {
		gb::event('will-parse-object-meta', $this);
		
		# lift lists
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
				$this->$plural = gb_cfilter::apply('parse-tags', $s);
		}
		
		# lift specials, like title, from meta to this
		static $special_singles = array('title');
		foreach ($special_singles as $singular) {
			if (isset($this->meta['title'])) {
				$this->title = $this->meta['title'];
				unset($this->meta[$singular]);
			}
		}
		
		# lift content type
		$charset = 'utf-8';
		if (isset($this->meta['content-type'])) {
			$this->mimeType = $this->meta['content-type'];
			if (preg_match('/^([^ ;\s\t]+)(?:[ \s\t]*;[ \s\t]*charset=([a-zA-Z0-9_-]+)|).*$/', $this->mimeType, $m)) {
				if (isset($m[2]) && $m[2])
					$charset = strtolower($m[2]);
				$this->mimeType = $m[1];
			}
			unset($this->meta['content-type']);
		}
		
		# lift charset or encoding
		if (isset($this->meta['charset'])) {
			$charset = strtolower(trim($this->meta['charset']));
			# we do not unset this, because it need to propagate to buildHeaderFields
		}
		elseif (isset($this->meta['encoding'])) {
			$charset = strtolower(trim($this->meta['encoding']));
			# we do not unset this, because it need to propagate to buildHeaderFields
		}
		
		# convert body text encoding?
		if ($charset && $charset !== 'utf-8' && $charset !== 'utf8' && $charset !== 'ascii') {
			if (function_exists('mb_convert_encoding')) {
				$this->title = mb_convert_encoding($this->title, 'utf-8', $charset);
				$this->body = mb_convert_encoding($this->body, 'utf-8', $charset);
			}
			elseif (function_exists('iconv')) {
				$this->title = iconv($charset, 'utf-8', $this->title);
				$this->body = iconv($charset, 'utf-8', $this->body);
			}
			else {
				gb::log(LOG_ERR,
					'failed to convert text encoding of %s -- neither mbstring nor iconv extension is available.',
					$this->name);
			}
		}
		
		# transfer author meta tag
		if (isset($this->meta['author'])) {
			$this->author = GBAuthor::parse($this->meta['author']);
			unset($this->meta['author']);
		}
		
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
		
		# handle booleans
		static $bools = array('draft' => 'draft', 'comments' => 'commentsOpen', 'pingback' => 'pingbackOpen');
		foreach ($bools as $mk => $ok) {
			if (isset($this->meta[$mk])) {
				$s = trim($this->meta[$mk]);
				unset($this->meta[$mk]);
				$this->$ok = ($s === '' || gb_strbool($s));
			}
		}
		
		gb::event('did-parse-object-meta', $this);
	}
	
	function fnext() {
		$fnext = null;
		if ($this->mimeType) {
			if (strpos($this->mimeType, '/') !== false)
				$fnext = GBMimeType::forType($this->mimeType);
			else
				$fnext = $this->mimeType;
		}
		if (!$fnext)
			$fnext = array_pop(gb_fnsplit($this->name));
		return $fnext;
	}
	
	function reload($data, $commits=null, $applyBodyFilters=true) {
		parent::reload($data, $commits);
		
		$this->parseData($data);
		$this->applyInfoFromCommits($commits);
		$path = gb::$site_dir.'/'.$this->name;
		if (is_file($path))
			$this->modified = new GBDateTime(filemtime($path));
		$this->parseHeaderFields();
		if ($this->modified === null)
			$this->modified = $this->published;
		
		# apply filters
		if ($applyBodyFilters) {
			$fnext = $this->fnext();
			gb_cfilter::apply('post-reload-GBExposedContent', $this);
			gb_cfilter::apply('post-reload-GBExposedContent.'.$fnext, $this);
			$cls = get_class($this);
			if ($cls !== 'GBExposedContent') {
				gb_cfilter::apply('post-reload-'.$cls, $this);
				gb_cfilter::apply('post-reload-'.$cls.'.'.$fnext, $this);
			}
		}
		
		gb::event('did-reload-object', $this);
	}
	
	function isWorkVersion() {
		return (!$this->id || $this->id === 'work');
	}
	
	function isTracked() {
		# if it has a real ID, it's tracked
		if (!$this->isWorkVersion())
			return true;
		# ask git
		try {
			if ($this->name && git::id_for_pathspec($this->name))
				return true;
		}
		catch (GitError $e) {}
		return false;
	}
	
	/** True if there are local, uncommitted modifications */
	function isDirty() {
		if (!$this->isTracked())
			return true;
		$st = git::status();
		return (isset($st['staged'][$this->name]) || isset($st['unstaged'][$this->name]));
	}
	
	function exists() {
		if (!$this->name)
			return false;
		return is_file(gb::$site_dir.'/'.$this->name);
	}
	
	function recommendedFilenameExtension($default='') {
		if (($ext = GBMimeType::forType($this->mimeType)))
			return '.'.$ext;
		return $default;
	}
	
	function recommendedName() {
		return 'content/objects/'.$this->slug.$this->recommendedFilenameExtension();
	}
	
	function setRawBody($body) {
		$this->_rawBody = $body;
	}
	
	function rawBody($data=null) {
		if ($this->_rawBody === null) {
			if ($data === null) {
				if ($this->isWorkVersion())
					$data = file_get_contents(gb::$site_dir.'/'.$this->name);
				else
					$data = git::cat_file($this->id);
			}
			$p = self::findHeaderTerminatorOffset($data);
			if ($p === false)
				return '';
			$this->_rawBody = substr($data, $p);
		}
		return $this->_rawBody;
	}
	
	function body() {
		return gb::filter('post-body', $this->body);
	}
	
	function textBody() {
		return trim(preg_replace('/<[^>]*>/m', ' ', $this->body()));
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
		
		gb::event('did-create-condensed-object', $this, $c);
		
		return $c;
	}
	
	function urlpath() {
		return str_replace('%2F', '/', urlencode($this->slug));
	}
	
	function url($include_version=false, $include_pathspec=false) {
		$url = gb::$site_url . gb::$index_prefix . $this->urlpath();
		if ($include_version !== false) {
			if ($include_version === true)
				$include_version = $this->id ? $this->id : 'work';
			$url .= (strpos($url, '?') === false ? '?' : '&')
				. gb::$version_query_key.'='.urlencode($include_version);
		}
		if ($include_pathspec === true) {
			$url .= (strpos($url, '?') === false ? '?' : '&')
				. gb::$pathspec_query_key.'='.urlencode($this->name);
		}
		return $url;
	}
	
	function commentsStageName() {
		# not the same as gb::$comments_cache_fnext
		return gb_filenoext($this->name).'.comments';
	}
	
	function getCommentsDB() {
		return new GBCommentDB(gb::$site_dir.'/'.$this->commentsStageName(), $this);
	}
	
	function commentsLink($prefix='', $suffix='', $template='<a href="%u" class="numcomments" title="%t">%n</a>') {
		if (!$this->comments || !($count = is_int($this->comments) ? $this->comments : $this->comments->countApproved()))
		 	return '';
		return strtr($template, array(
			'%u' => h($this->url()).'#comments',
			'%n' => $count,
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
		$index = gb::index($indexname);
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
		return 'post-'.$this->published->utcformat('%Y-%m-%d-').gb_strtodomid($this->slug);
	}
	
	function buildHeaderFields() {
		$header = array_merge(array(
			'title' => $this->title
		), $this->meta);
		# optional fields
		if ($this->published)
			$header['published'] = $this->published->__toString();
		if ($this->draft !== null)
			$header['draft'] = $this->draft ? 'yes' : 'no';
		if ($this->commentsOpen !== null)
			$header['comments'] = $this->commentsOpen ? 'yes' : 'no';
		if ($this->pingbackOpen !== null)
			$header['pingback'] = $this->pingbackOpen ? 'yes' : 'no';
		if ($this->author)
			$header['author'] = GBAuthor::gitFormat($this->author);
		if ($this->tags)
			$header['tags'] = implode(', ', $this->tags);
		if ($this->categories)
			$header['categories'] = implode(', ', $this->categories);
		
		# only set content-type if mimeType is not the same as default for file ext
		if ($this->mimeType !== GBMimeType::forFilename($this->name))
			$header['content-type'] = $this->mimeType;
		
		# charset and encoding should be preserved in $this->meta if
		# they existed in the first place.
		return $header;
	}
	
	function __toString() {
		return $this->name;
	}
	
	function toBlob() {
		# build header
		$header = $this->buildHeaderFields();
		
		# mux header and body
		$data = '';
		foreach ($header as $k => $v) {
			$k = trim($k);
			$v = trim($v);
			if (!$k || !$v)
				continue;
			$data .= $k.': '.str_replace(array("\n","\r\n"), "\n\t", $v)."\n";
		}
		
		# no header? still need LFLF
		if (!$data)
			$data .= "\n";
		
		# append body
		$data .= "\n".$this->rawBody();
		if ($data{strlen($data)-1} !== "\n")
			$data .= "\n";
		
		return $data;
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
	public $hidden = null; # hidden from menu, but still accessible (i.e. not the same thing as $draft)
	
	function parseHeaderFields() {
		parent::parseHeaderFields();
		
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
	
	function isCurrent() {
		if (!gb::$is_page)
			return false;
		$url = gb::url();
		return (strcasecmp(rtrim(substr($url->path, 
			strlen(gb::$site_path.gb::$index_prefix.gb::$pages_prefix)),'/'), $this->slug) === 0);
	}
	
	function recommendedName() {
		return 'content/pages/'.$this->slug.$this->recommendedFilenameExtension();
	}
	
	static function mkCachename($slug) {
		return 'content/pages/'.$slug.gb::$content_cache_fnext;
	}
	
	static function pathToCached($slug) {
		return parent::pathToCached('pages', $slug . gb::$content_cache_fnext);
	}
	
	static function findByName($name, $version=null, $applyBodyFilters=true) {
		if (strpos($name, 'content/pages/') !== 0)
			$name = 'content/pages/' . $name;
		return self::find($name, $version, null, $applyBodyFilters);
	}
	
	static function find($uri_path_or_slug, $version=null, $applyBodyFilters=true) {
		return parent::find($uri_path_or_slug, 'pages', 'GBPage', $version, $applyBodyFilters);
	}
	
	static function urlTo($slug) {
		return gb::$site_url . gb::$index_prefix . gb::$pages_prefix . $slug;
	}
	
	function __sleep() {
		return array_merge(parent::__sleep(), array('order', 'hidden'));
	}
	
	function buildHeaderFields() {
		$header = parent::buildHeaderFields();
		if ($this->order !== null)
			$header['order'] = $this->order;
		if ($this->hidden !== null)
			$header['hidden'] = $this->hidden;
		return $header;
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
	
	function parseData($data) {
		if ($this->name)
			self::parsePathspec($this->name, $this->published, $this->slug, $fnext);
		return parent::parseData($data);
	}
	
	function recommendedName() {
		if ($this->published === null || !($this->published instanceof GBDateTime))
			throw new UnexpectedValueException('$this->published is not a valid GBDateTime and needed to create name');
		return 'content/posts/'
			. $this->published->utcformat(gb::$posts_cn_pattern)
			. $this->slug . $this->recommendedFilenameExtension();
	}
	
	static function mkCachename($published, $slug) {
		# Note: the path prefix is a dependency for GBContentFinalizer::finalize
		return 'content/posts/'.$published->utcformat(gb::$posts_cn_pattern).$slug.gb::$content_cache_fnext;
	}
	
	static function cachenameFromURI($slug, &$strptime, $return_struct=false) {
		if ($strptime === null || $strptime === false)
			$strptime = strptime($slug, gb::$posts_prefix);
		$prefix = gmstrftime(gb::$posts_cn_pattern, gb_mkutctime($strptime));
		$suffix = $strptime['unparsed'];
		if ($return_struct === true)
			return array($prefix, $suffix);
		return $prefix.$suffix;
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
	
	static function findByName($name, $version=null, $applyBodyFilters=true) {
		if (strpos($name, 'content/posts/') !== 0)
			$name = 'content/posts/' . $name;
		return self::find($name, $version, null, $applyBodyFilters);
	}
	
	static function find($uri_path_or_slug, $version=null, $strptime=null, $applyBodyFilters=true) {
		$version = self::parseVersion($version);
		$path = false;
		
		if (strpos($uri_path_or_slug, 'content/posts/') !== false) {
			$path = $uri_path_or_slug;
			if ($path{0} !== '/')
				$path = gb::$site_dir.'/'.$path;
		}
			
		if ($version === 'work') {
			# find path to raw content
			if ((!$path || !is_file($path)) && ($path = self::pathToWork($uri_path_or_slug, $strptime)) === null)
				return false;
			
			# parse pathspec, producing date and actual slug needed to look up cached
			try {
				self::parsePathspec(self::pathspecFromAbsPath($path), $date, $slug, $fnext);
			}
			catch(UnexpectedValueException $e) {
				return null;
			}
			
			# try to find a cached version
			$post = self::findByDateAndSlug($date, $slug);
			
			# load work
			return self::loadWork($path, $post, 'GBPost', $version, $slug, $applyBodyFilters);
		}
		elseif ($version === null) {
			if ($path) {
				self::parsePathspec(self::pathspecFromAbsPath($path), $date, $slug, $fnext);
				return self::findByDateAndSlug($date, $slug);
			}
			$path = self::pathToCached($uri_path_or_slug, $strptime);
			$data = @file_get_contents($path);
			if ($data === false && gb::$posts_fuzzy_lookup === true) {
				# exact match failed -- try fuzzy matching using glob and our knowledge of patterns
				list($prefix, $suffix) = self::cachenameFromURI($uri_path_or_slug, $strptime, true);
				$path = strtr($prefix, array('/'=>'{/,*,.}','-'=>'{/,*,.}','.'=>'{/,*,.}')).'*'.$suffix;
				$path = GBExposedContent::pathToCached('posts', $path . gb::$content_cache_fnext);
				# try any file with the cachename as prefix
				if ( ($path = gb::glob($path)) ) {
					$data = @file_get_contents($path);
					/*
					Send premanent redirect if we found a valid article.
					 
					Discussion: This is where things might go really wrong -- imagine we did find an 
					            article on fuzzy matching but it's _another_ article. Fail. But that
					            case is almost negligible since we only expand the time prefix, not
					            the slug.
					*/
					global $gb_handle_request;
					if ($data !== false 
						&& isset($gb_handle_request) && $gb_handle_request === true 
						&& ($post = unserialize($data)) && headers_sent() === false )
					{
						header('HTTP/1.1 301 Moved Permanently');
						header('Location: '.$post->url());
						exit('Moved to <a href="'.h($post->url()).'">'.h($post->url()).'</a>');
					}
				}
			}
			return $data === false ? false : unserialize($data);
		}
		throw new Exception('arbitrary version retrieval not yet implemented');
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
		
		# first, test gb::$posts_cn_pattern
		if (($st = strptime($nametest, strtr(gb::$posts_cn_pattern, $subchars, $repchars))) !== false) {
			$slug = ltrim($st['unparsed'], $subchars . '-');
		}
		else {
			# next, test common patterns with as many items as gb::$posts_cn_pattern
			$ptimes1 = array();
			$n = 0;
			$slug = false;
			if (preg_match_all('/%\w/', gb::$posts_cn_pattern, $m))
				$n = count($m[0]);
			if ($n) {
				if ($n == 1) $ptimes1 = array('%Y' => 4, '%y' => 2, '%G' => 4, '%g' => 2);
				else if ($n == 2) $ptimes1 = array('%Y-%m' => 7, '%y-%m' => 5, '%G-%m' => 7, '%g-%m' => 5);
				else if ($n == 3) $ptimes1 = array('%Y-%m-%d' => 10, '%y-%m-%d' => 8, '%G-%m-%d' => 10, '%g-%m-%d' => 8);
				foreach ($ptimes1 as $pattern => $pattern_len) {
					if (($st = strptime($nametest, $pattern)) !== false) {
						$slug = ltrim(substr($name, $pattern_len), $subchars . '-');
						break;
					}
				}
			}
			if ($slug === false) {
				# finally, try a series of common patterns
				foreach ($ptimes as $pattern => $pattern_len) {
					if (($st = strptime($nametest, $pattern)) !== false) {
						$slug = ltrim(substr($name, $pattern_len), $subchars . '-');
						break;
					}
				}
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
		gb_cfilter::apply('post-reload-comments', $this);
	}
	
	# these two are not serialized, but lazy-initialized by count()
	public $_countTotal;
	public $_countSpam;
	public $_countApproved;
	public $_countApprovedTopo;
	
	/** Recursively count how many comments are in the $comments member */
	function count($c=null) {
		if ($c === null)
			$c = $this;
		if ($c->_countTotal !== null)
			return $c->_countTotal;
		$c->_countTotal = $c->_countApproved = $c->_countApprovedTopo = $c->_countSpam = 0;
		if (!$c->comments)
			return 0;
		foreach ($c->comments as $comment) {
			$c->_countTotal++;
			if ($comment->approved) {
				$c->_countApproved++;
				$c->_countApprovedTopo++;
			}
			if ($comment->spam === true)
				$c->_countSpam++;
			$this->count($comment);
			$c->_countTotal += $comment->_countTotal;
			if ($comment->approved)
				$c->_countApprovedTopo += $comment->_countApprovedTopo;
			$c->_countSpam += $comment->_countSpam;
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
	
	function countUnapproved($excluding_spam=true) {
		$this->count();
		return $this->_countTotal - $this->_countApproved - ($excluding_spam ? $this->_countSpam : 0);
	}
	
	function countSpam() {
		$this->count();
		return $this->_countSpam;
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
	
	function toBlob() {
		$db = new GBCommentDB();
		$db->set($this->comments);
		return $db->encodeData();
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
	
	/**
	 * Allowed tags, primarily used by GBFilter but here so that themes and
	 * other stuff can read it.
	 */
	static public $allowedTags = array(
		# tagname => allowed attributes
		'a' => array('href', 'target', 'rel', 'name'),
		'strong' => array(),
		'b' => array(),
		'blockquote' => array(),
		'em' => array(),
		'i' => array(),
		'img' => array('src', 'width', 'height', 'alt', 'title'),
		'u' => array(),
		's' => array(),
		'del' => array()
	);
	
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
	
	function body() {
		return gb::filter('comment-body', $this->body);
	}
	
	function textBody() {
		return trim(preg_replace('/<[^>]*>/m', ' ', $this->body()));
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
		return $this->_bounceURL('gitblog/admin/helpers/approve-comment.php?action=approve&',
			$post, $include_referrer);
	}
	
	function unapproveURL($post=null, $include_referrer=true) {
		return $this->_bounceURL('gitblog/admin/helpers/approve-comment.php?action=unapprove&',
			$post, $include_referrer);
	}
	
	function hamURL($post=null, $include_referrer=true) {
		return $this->_bounceURL('gitblog/admin/helpers/spam-comment.php?action=ham&',
			$post, $include_referrer);
	}
	
	function spamURL($post=null, $include_referrer=true) {
		return $this->_bounceURL('gitblog/admin/helpers/spam-comment.php?action=spam&',
			$post, $include_referrer);
	}
	
	function removeURL($post=null, $include_referrer=true) {
		return $this->_bounceURL('gitblog/admin/helpers/remove-comment.php?',
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


# -----------------------------------------------------------------------------
# Nonce

function gb_nonce_time($ttl) {
	return (int)ceil(time() / $ttl);
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

function gb_head() {
	echo '<meta name="generator" content="Gitblog '.gb::$version."\" />\n";
	gb::event('on-html-head');
}

function gb_footer() {
	gb::event('on-html-footer');
}

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
	return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
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
	if (gb::$request_query === 'PATH_INFO')
		$gb_request_uri = isset($_SERVER['PATH_INFO']) ? trim($_SERVER['PATH_INFO'], '/') : '';
	else
		$gb_request_uri = isset($_GET[gb::$request_query]) ? trim($_GET[gb::$request_query], '/') : '';
	
	# temporary, non-exported variables
	$version = null;
	$strptime = null;
	$preview_pathspec = null;
	
	# verify integrity and config
	gb::verify();
	
	# authed?
	if (isset($_COOKIE['gb-chap']) && $_COOKIE['gb-chap']) {
		gb::authenticate(false);
		# now, gb::$authorized (a GBUser) is set (authed ok) or a CHAP
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
	if (isset($_GET[gb::$preview_query_key]) && gb::$authorized) {
		gb::$is_preview = true;
		$version = 'work';
	}
	elseif (isset($_GET[gb::$version_query_key]) && gb::$authorized) {
		gb::$is_preview = true;
		$version = $_GET[gb::$version_query_key];
	}
	if (gb::$is_preview === true && isset($_GET[gb::$pathspec_query_key])) {
		$preview_pathspec = $_GET[gb::$pathspec_query_key];
	}
	if (gb::$is_preview)
		header('Cache-Control: no-cache');
	
	# load plugins
	gb::load_plugins('request');
	
	gb::event('will-parse-request');
	register_shutdown_function(array('gb','event'), 'did-handle-request');
	
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
			# if we got this far it means no event observers took over this task, so
			# we run the built-in feed (Atom) code:
			require gb::$dir.'/helpers/feed.php';
			exit;
		}
		elseif (gb::$posts_prefix === '' || ($strptime = strptime($gb_request_uri, gb::$posts_prefix)) !== false) {
			# post
			if ($preview_pathspec !== null)
				$post = GBPost::findByName($preview_pathspec, $version);
			else
				$post = GBPost::find(urldecode($gb_request_uri), $version, $strptime);
			if ($post === false)
				gb::$is_404 = true;
			else
				gb::$title[] = $post->title;
			gb::$is_post = true;
			
			# empty prefix and 404 -- try page
			if (gb::$is_404 === true && gb::$posts_prefix === '') {
				if ($preview_pathspec !== null)
					$post = GBPage::findByName($preview_pathspec, $version);
				else
					$post = GBPage::find(urldecode($gb_request_uri), $version);
				if ($post !== false) {
					gb::$title[] = $post->title;
					gb::$is_404 = false;
				}
				gb::$is_post = false;
				gb::$is_page = true;
			}
		}
		else {
			# page
			if ($preview_pathspec !== null)
				$post = GBPage::findByName($preview_pathspec, $version);
			else
				$post = GBPage::find(urldecode($gb_request_uri), $version);
			if ($post === false)
				gb::$is_404 = true;
			else
				gb::$title[] = $post->title;
			gb::$is_page = true;
		}
		
		# post 404?
		if (isset($post) && $post && gb::$is_preview === false && ($post->draft === true || $post->published->time > time()))
			gb::$is_404 = true;
	}
	else {
		# posts
		$postspage = GBPost::pageByPageno(isset($_REQUEST['page']) ? intval($_REQUEST['page']) : 0);
		gb::$is_posts = true;
		gb::$is_404 = $postspage === false;
	}
	
	# unset temporary variables (not polluting global namespace)
	unset($preview_pathspec);
	unset($strptime);
	unset($version);
	
	gb::event('will-handle-request');
	
	# from here on, the caller will have to do the rest
}
?>
