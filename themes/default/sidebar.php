<div id="sidebar">
	<?php if (($recent_comments = gb::index('recent-comments'))): ?>
	<h2>Recent comments</h2>
	<ol class="recent-comments">
	<?php foreach ($recent_comments as $tuple): list($comment, $_post) = $tuple; ?>
		<li>
			<a href="<?php echo h($_post->url()) ?>#comment-<?php echo $comment->id ?>"><?php echo h($comment->name) ?>
				on <em><?php echo h($_post->title) ?></em></a>
			<small><?php echo $comment->date->age() ?></small>
		</li>
	<?php endforeach ?>
	</ol>
	<?php endif ?>
	
	<!--h2>Popular</h2>
	<p>Posts &amp; pages</p>
	
	<h2>Friends</h2>
	<p>Who invented the weird word "blogroll"?</p>
	
	<h2>Archive</h2>
	<p>Some kind of calendar or maybe a list of months?</p>
	
	<h2>Bookmarks</h2>
	<p>Delicious</p-->
</div>