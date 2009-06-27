<?
#header('content-type: text/plain; charset=utf-8');
header('content-type: text/html; charset=utf-8');
require 'gitblog/gitblog.php';

function gb_find_post_by_urlpath($urlpath) {
	$dateprefixpat = '%Y/%m/%d/'; # xxx todo move to config (also in GBPost)
	#$dateprefixlen = strlen(gmstrftime($dateprefixpat));
	#$st = strptime(substr($urlpath, 0, $dateprefixlen), $dateprefixpat);
	$st = strptime($urlpath, $dateprefixpat);
	$date = gmmktime($st['tm_hour'], $st['tm_min'], $st['tm_sec'], 
		$st['tm_mon']+1, $st['tm_mday'], 1900+$st['tm_year']);
	#$slug = substr($urlpath, $dateprefixlen);
	$slug = $st['unparsed'];
	$cachename = date('Y/m/d/', $date).$slug;
	
	return unserialize(file_get_contents('db/.git/info/gitblog/content/posts/'.$cachename));
}

if (!isset($_GET['slug'])) {
	header('Status: 400 Bad Request');
	exit('missing slug');
}

$post = gb_find_post_by_urlpath($_GET['slug']);

if (!$post or $post->published > time()) {
	header('Status: 404 Not Found');
	exit('post not found');
}
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
		<title><?= $post->title ?> — My gitblog</title>
		<style type="text/css" media="screen">
			body { font-family:sans-serif; }
			#post-meta { float:right; font-size:80%; background:#ddd; padding:10px; }
			#post-meta ul { list-style: none; padding:5px 10px;}
		</style>
	</head>
	<body>
		<h2><?= $post->title ?></h2>
			<div id="post-meta">
				<h3>Details</h3>
				<ul>
					<li>Author: <a href="mailto:<?= $post->author->email ?>"><?= $post->author->name ?></a></li>
					<li>Published: <?= date('c', $post->published) ?></li>
					<li>Modified: <?= date('c', $post->modified) ?></li>
					<li>Tags: <?= implode(', ', $post->tags) ?></li>
					<li>Categories: <?= implode(', ', $post->categories) ?></li>
					<li>Revision (current object): <?= $post->id ?></li>
				</ul>
			</div>
		<p>
			<?= $post->body ?>
		</p>
		<hr/>
		<address>
			<? $s = (microtime(true)-$debug_time_started); printf('%.3f ms, %d rps', 1000.0 * $s, 1.0/$s) ?>
		</address>
	</body>
</html>
