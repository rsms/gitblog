<?
#header('content-type: text/plain; charset=utf-8');
header('content-type: text/html; charset=utf-8');
require 'gb/git.php';

$posts = GitContent::publishedPosts($repo, 10);
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
		<h1>Some blog</h1>
		<? foreach ($posts as $post): ?>
			<h2><?= $post->meta->title ?></h2>
			<div id="post-meta">
				<h3>Details</h3>
				<ul>
					<li>Published: <?= date('c', $post->meta->published) ?></li>
					<li>Modified: <?= date('c', $post->commits[0]->comitter->date) ?></li>
					<li>Author: <a href="mailto:<?= $post->ccommit->author->email ?>"><?= $post->ccommit->author->name ?></a></li>
					<li>Revision (current object): <?= $post->id ?></li>
					<li>Initial commit: <?= $post->ccommit->id ?></li>
					<li>Last commit: <?= $post->commits[0]->id ?></li>
					<li>
						Log messages:
						<ul style="list-style-type:decimal;padding-left:30px">
						<? foreach ($post->commits as $c): ?>
							<li>
								<?= nl2br(htmlentities($c->message)) ?>
								<small><em>by <?= $c->author->name ?> at <?= date('c', $c->author->date) ?></em></small>
							</li>
						<? endforeach ?>
						</ul>
					</li>
				</ul>
			</div>
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