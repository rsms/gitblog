<?
error_reporting(E_ALL);
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
function gb_atomic_write($filename, &$data) {
	$tempnam = tempnam(dirname($filename), basename($filename));
	$f = fopen($tempnam, 'w');
	fwrite($f, $data);
	fclose($f);
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
	public $gitdir = '.git';
	public $rebuilders = array();
	public $gitQueryCount = 0;
	
	function __construct($gitdir) {
		$this->gitdir = $gitdir;
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
}


$gb = new GitBlog('/Library/WebServer/Documents/gitblog/db/.git');
GBRebuilder::rebuild($gb);

?>