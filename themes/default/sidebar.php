<div id="sidebar">
	<? if (($recent_comments = gb::index('recent-comments'))): ?>
	<h2>Recent comments</h2>
	<ol class="recent-comments">
	<? foreach ($recent_comments as $tuple): list($comment, $_post) = $tuple; ?>
		<li>
			<a href="<?= h($_post->url()) ?>#comment-<?= $comment->id ?>"><?= h($comment->name) ?>
				on <em><?= h($_post->title) ?></em></a>
			<small><?= $comment->date->age() ?></small>
		</li>
	<? endforeach ?>
	</ol>
	<? endif ?>
	
	<!--h2>Popular</h2>
	<p>Posts &amp; pages</p>
	
	<h2>Friends</h2>
	<p>Who invented the weird word "blogroll"?</p>
	
	<h2>Archive</h2>
	<p>Some kind of calendar or maybe a list of months?</p>
	
	<h2>Bookmarks</h2>
	<p>Delicious</p-->
</div>