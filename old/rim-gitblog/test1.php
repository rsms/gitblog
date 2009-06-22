<?
#header('content-type: text/plain; charset=utf-8');
header('content-type: text/html; charset=utf-8');

$_ENV['GIT_DIR'] = '/Library/WebServer/Documents/gitblog/repos/work/.git';
$_ENV['PATH'] .= ':/opt/local/bin';

$gitblobs = array();

function gitproc($cmd, &$pipes) {
	$cmd = 'git '.$cmd;
	$fds = array(array("pipe", "r"), array("pipe", "w"), array("pipe", "w"));
	$ps = proc_open($cmd, $fds, $pipes, null, $_ENV);
	return $ps;
}

function gitblob($name) {
	global $gitblobs;
	
	if (isset($gitblobs[$name]))
		return $gitblobs[$name]['data'];
	
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
	
	return substr($out, strpos($out, "\n")+1);
}

function gitblobs_load($names) {
	global $gitblobs;
	
	$ps = gitproc('cat-file --batch', $pipes);
	foreach ($names as $name)
		fwrite($pipes[0], "HEAD:$name\n");
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
	
	$p = 0;
	
	foreach ($names as $name) {
		# <sha1> SP <type> SP <size> LF
		# <contents> LF
		$hend = strpos($out, "\n", $p);
		$h = explode(' ', substr($out, $p, $hend-$p));
		#var_dump($h);
		
		$missing = ($h[1] == 'missing');
		$size = 0;
		$data = null;
		$dstart = $hend + 1;
		if (!$missing) {
			$size = intval($h[2]);
			$data = substr($out, $dstart, $size);
		}
		else {
			$data = '[git error: missing '.var_export($name,1).']';
		}
		
		$p = $dstart + $size + 1;
		
		$gitblobs[$name] = array(
			'id' => $h[0],
			'type' => $h[1],
			'size' => $size,
			'data' => $data
		);
	}
}


function gitblobs_find($file=null) {
	$s = file_get_contents($file);
	if (preg_match_all('/[^_a-z]gitblob[ \t\n\r]*\([\'"]([^\)]+)[\'"]\)/im', $s, $m))
		return array_unique($m[1]);
	return array();
}


$time_started = microtime(true);

#gitblobs_load(array('site/title', 'pages/hello.html', 'pages/hello'));
gitblobs_load(gitblobs_find(__FILE__));
#var_dump($gitblobs);

?><!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
		<title><?= gitblob('site/title') ?></title>
	</head>
	<body>
		<?= gitblob('pages/hello.html') ?>
		<?= gitblob('pages/hello') ?>
		<address><?= microtime(true)-$time_started ?> s</address>
	</body>
</html>