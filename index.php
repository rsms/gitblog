<?
header('content-type: text/plain; charset=utf-8');
#header('content-type: text/html; charset=utf-8');
require 'gb/git.php';


$posts = GitBlogPost::findPublished($repo, 10);

$repo->batchLoadPending();
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
		<title>Some blog</title>
		<style type="text/css" media="screen">
			body { font-family:sans-serif; }
			#post-meta {  font-size:80%; background:#ddd; padding:10px; }
			#post-meta ul { list-style: none; padding:5px 10px;}
			.breaker { clear:both; }
		</style>
	</head>
	<body>
		<h2>Latest posts</h2>
		<? foreach ($posts as $post): ?>
			<div id="post-meta">
				<h3>Details</h3>
				<ul>
					<li>Published: <?= date('c', $post->datePublished) ?></li>
					<li>Modified: <?= date('c', $post->dateModified) ?></li>
					<li>Tags: <?= implode(', ', $post->tags) ?></li>
					<? if (isset($_GET['extended-details']) and $_GET['extended-details'] == 'yes'): ?>
						<li>Author: <a href="mailto:<?= $post->authorEmail ?>"><?= $post->authorName ?></a></li>
						<li>Object: <?= $post->id ?></li>
						<li>Commit: <?= $post->object->commit->id ?></li>
						<li>
							<a href="?">Hide extended details</a>
							<small>(Do not fetch commits)</small>
						</li>
					<? else: ?>
						<li>
							<a href="?extended-details=yes">Show extended details</a>
							<small>(Fetch commits)</small>
						</li>
					<? endif ?>
				</ul>
			</div>
			<h2><a href="post.php?slug=<?= $post->slug ?>"><?= $post->title ?></a></h2>
			<p>
				<?= $post->body ?>
			</p>
			<div class="breaker"></div>
		<? endforeach ?>
		<hr/>
		<address>
			<?= $repo->gitQueryCount ?> git queries
			(<? $s = (microtime(true)-$debug_time_started); printf('%d ms, %d rps', intval(1000.0 * $s), 1.0/$s) ?>)
		</address>
	</body>
</html>