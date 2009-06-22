<?
#header('content-type: text/plain; charset=utf-8');
header('content-type: text/html; charset=utf-8');

$_ENV['GIT_DIR'] = '/Library/WebServer/Documents/gitblog/repos/work/.git';
$_ENV['PATH'] .= ':/opt/local/bin';

function gitproc($cmd, &$pipes) {
	$cmd = 'git '.$cmd;
	$fds = array(array("pipe", "r"), array("pipe", "w"), array("pipe", "w"));
	$ps = proc_open($cmd, $fds, $pipes, null, $_ENV);
	return $ps;
}

function gitblob_read($name) {
	$ps = gitproc('cat-file --batch', $pipes);
	fwrite($pipes[0], "HEAD:$name");
	fclose($pipes[0]);
	$out = stream_get_contents($pipes[1]);
	fclose($pipes[1]);
	$err = stream_get_contents($pipes[2]);
	fclose($pipes[2]);
	$st = proc_close($ps);
	if ($st != 0) {
		trigger_error("git: $err");
		return null;
	}
	$p = strpos($out, "\n");
	if (substr($out, -($p+8), 8) == ' missing')
		return null;
	return substr($out, $p+1);
}

function gitblob_func_nocache($name) {
	static $gitblobs = array();
	if (!isset($gitblobs[$name]))
		$gitblobs[$name] = gitblob_read($name);
	return $gitblobs[$name];
}

$gitblob_func = gitblob_func_nocache;

function gitblob($name) {
	global $gitblob_func;
	return $gitblob_func($name);
}

function gitblobs_find($file=null) {
	$s = file_get_contents($file);
	if (preg_match_all('/[^_a-z]gitblob[ \t\n\r]*\([\'"]([^\)]+)[\'"]\)/im', $s, $m))
		return array_unique($m[1]);
	return array();
}

$time_started = microtime(true);

?><!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
		<title><?= gitblob('site/title') ?></title>
	</head>
	<body>
		<?= gitblob('pages/hello.html') ?>
		<?= gitblob('pages/hello') ?>
		<?= gitblob('pages/hello.html') ?>
		<?= gitblob('pages/hello') ?>
		<?= gitblob('pages/hello.html') ?>
		<?= gitblob('pages/hello') ?>
		<?= gitblob('pages/hello.html') ?>
		<?= gitblob('pages/hello') ?>
		<?= gitblob('pages/hello.html') ?>
		<?= gitblob('pages/hello') ?>
		<?= gitblob('pages/hello.html') ?>
		<?= gitblob('pages/hello') ?><?= gitblob('pages/hello') ?><?= gitblob('pages/hello') ?><?= gitblob('pages/hello') ?><?= gitblob('pages/hello') ?><?= gitblob('pages/hello') ?><?= gitblob('pages/hello') ?><?= gitblob('pages/hello') ?><?= gitblob('pages/hello') ?><?= gitblob('pages/hello') ?><?= gitblob('pages/hello') ?><?= gitblob('pages/hello') ?><?= gitblob('pages/hello') ?><?= gitblob('pages/hello') ?><?= gitblob('pages/hello') ?><?= gitblob('pages/hello') ?><?= gitblob('pages/hello') ?><?= gitblob('pages/hello') ?><?= gitblob('pages/hello') ?><?= gitblob('pages/hello') ?>
		<address><?= microtime(true)-$time_started ?> s</address>
	</body>
</html>