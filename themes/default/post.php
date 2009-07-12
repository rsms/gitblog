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
				<div class="body">
					<?= $comment->body ?>
				</div>
				<a href="#comment-<?= $comment->id ?>"><?= counted(13, 'day', 'days') ?> ago</a>
				<? if ($post->commentsOpen || $level): ?>
					<small>
					<? if ($level): ?>
						<a href="#comment-<?= substr($comment->id, 0, strrpos($comment->id, '.')) ?>">&uarr;</a>
					<? endif; ?>
					<? if ($post->commentsOpen): ?>
						 <a href="javascript:reply('<?= $comment->id ?>');">&#x21A9;</a>
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
	<form id="comment-form" action="<?= GB_SITE_URL ?>gitblog/helpers/post-comment.php" method="POST">
		<?= gb_nonce_field('post-comment-'.$post->name) ?>
		<input type="hidden" id="comment-client-timezone-offset" name="client-timezone-offset" value="" />
		<input type="hidden" name="reply-post" value="<?= h($post->name) ?>" />
		<input type="hidden" name="reply-to" value="" />
		<p>
			<textarea id="comment-reply-message" name="reply-message"></textarea>
		</p>
		<p>
			<input type="text" id="comment-author-email" name="author-email" value="Email" />
			<input type="text" id="comment-author-name" name="author-name" value="Name" />
			<input type="text" id="comment-author-url" name="author-url" value="Website" />
		</p>
		<p>
			<input type="submit" value="Add comment" />
		</p>
	</form>
	<script type="text/javascript" charset="utf-8">
		document.getElementById('comment-form').onsubmit = function(e) {
			function trim(s) {
				return 
			}
			function check_filled(id, default_value) {
				var elem = document.getElementById(id);
				if (!elem)
					return false;
				elem.value = elem.value.replace(/(^[ \t\s\n\r]+|[ \t\s\n\r]+$)/g, '');
				if (elem.value == default_value || elem.value == '') {
					elem.select();
					return false;
				}
				return true;
			}
			document.getElementById('comment-client-timezone-offset').value = -((new Date()).getTimezoneOffset()*60);
			if (!check_filled('comment-reply-message', ''))
				return false;
			if (!check_filled('comment-author-name', 'Name'))
				return false;
			if (!check_filled('comment-author-email', 'Email'))
				return false;
			return true;
		}
	</script>
<? else: ?>
	<p>Comments are closed.</p>
<? endif; ?>
</div>