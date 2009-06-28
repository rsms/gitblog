<?
#header('content-type: text/plain; charset=utf-8');
header('content-type: text/html; charset=utf-8');

?><!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
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
		<h1>Some blog</h1>
		<? foreach ($page->posts as $post): ?>
			<h2><a href="<?= $post->url() ?>"><?= $post->title ?></a></h2>
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
			<div class="breaker"></div>
		<? endforeach ?>
		<hr/>
		<? if ($page->nextpage != -1): ?>
			<a href="?page=<?= $page->nextpage ?>">« Older posts</a>
		<? endif; ?>
		<? if ($page->prevpage != -1): ?>
			<a href="?page=<?= $page->prevpage ?>">Newer posts »</a>
		<? endif; ?>
		<address>
			(<? $s = (microtime(true)-$debug_time_started); printf('%d ms, %d rps', intval(1000.0 * $s), 1.0/$s) ?>)
		</address>
	</body>
</html>