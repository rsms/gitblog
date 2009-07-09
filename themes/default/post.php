<h2><?= $post->title ?></h2>
	<div id="post-meta">
		<h3>Details</h3>
		<ul>
			<li>Author: <a href="mailto:<?= $post->author->email ?>"><?= $post->author->name ?></a></li>
			<li>Published: <?= $post->published ?></li>
			<li>Modified: <?= $post->modified ?></li>
			<li>Tags: <?= $post->tagLinks() ?></li>
			<li>Categories: <?= $post->categoryLinks() ?></li>
			<li>Version: <?= $post->id ?></li>
		</ul>
	</div>
<?= $post->body ?>
<div id="comments">
	<h3><?= $post->numberOfComments() ?></h3>
<? if ($post->comments): ?>
	<ul>
	<? $prevlevel = 0; foreach($post->comments as $level => $comment):
			if ($level > $prevlevel)
				echo '<ul>';
			elseif ($level < $prevlevel)
				echo str_repeat('</ul>', $prevlevel-$level);
			$prevlevel = $level;
		?>
		<li class="comment" id="comment-<?= $comment->id ?>">
			<img class="avatar" 
				src="http://www.gravatar.com/avatar.php?gravatar_id=<?= md5($comment->email) ?>&amp;size=48" />
			<div>
				<a href="#comment-<?= $comment->id ?>"><?= h($comment->name) ?></a> says:
				<p>
					<?= h($comment->message) ?>
				</p>
				<a href="#comment-<?= $comment->id ?>"><?= counted(13, 'day', 'days') ?> ago</a>
				<? if ($post->commentsOpen || $level): ?>
					<small>
					<? if ($level): ?>
						<a href="#comment-<?= substr($comment->id, 0, strrpos($comment->id, '.')) ?>">&uarr;</a>
					<? endif; ?>
					<? if ($post->commentsOpen): ?>
						 <a href="javascript:void(0);">&#x21A9;</a>
					<? endif; ?>
					</small>
				<? endif; ?>
			</div>
		</li>
		<li class="breaker"></li>
	<? endforeach; echo str_repeat('</ul>', $prevlevel); ?>
	</ul>
	<div class="breaker"></div>
	<? if ($post->comments->countUnapproved()): ?>
		<p><small>
			<?= $post->numberOfUnapprovedComments() ?> awaiting approval
			<? if ($post->comments->countShadowed()): ?>
				— <?= $post->numberOfShadowedComments(
					'approved comment is', 'approved comments are', 'no', 'one') ?>
				therefore not visible.
			<? endif; ?>
		</small></p>
	<? endif; ?>
<? endif; # comments ?>
<? if ($post->commentsOpen): ?>
	<h3 id="reply">Add a comment</h3>
	<form id="reply-form" action="<?= GB_SITE_URL ?>gitblog/actions/post-comment.php" method="POST">
		<input type="hidden" name="reply-id" value="" />
		<p>
			<textarea name="message" id="reply-message"></textarea>
		</p>
		<p>
			<input type="text" name="email" value="Email" />
			<input type="text" name="name" value="Name" />
			<input type="text" name="uri" value="Website" />
		</p>
		<p>
			<input type="submit" value="Add comment" />
		</p>
	</form>
<? else: ?>
	<p>Comments are closed.</p>
<? endif; ?>
</div>