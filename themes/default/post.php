<div class="wrapper">
	<!-- =========================== sidebar =========================== -->
	<? include gb::$theme_dir . '/sidebar.php' ?>
	<!-- =========================== post =========================== -->
	<div class="posts single">
		<div class="post">
			<?= $post->commentsLink() ?>
			<h1><?= $post->title ?></h1>
			<p class="meta">
				<? if (gb::$is_post): ?>
					<?= $post->published->age() ?>
					by <?= h($post->author->name) . $post->tagLinks(', tagged ') . $post->categoryLinks(', filed under ')  ?>
				<? else: ?>
					<? $s=$post->tagLinks('tagged '); echo $s;  echo ($s ? ', ':'') . $post->categoryLinks('filed under ') ?>
				<? endif ?>
			</p>
			<div class="body">
				<?= $post->body ?>
			</div>
		</div>
		<div class="breaker"></div>
	</div>
	<!-- =========================== comments =========================== -->
	<? if ($post->commentsOpen || count($post->comments)): ?>
	<div id="comments">
		<hr/>
		<div class="wrapper">
		<? if (isset($_GET['skipped-duplicate-comment'])): ?>
			<div class="notification">
				<p>
					You posted a duplicate comment. Naughty! It was rejected.
				</p>
			</div>
		<? elseif (isset($_GET['comment-pending-approval'])): ?>
			<div class="notification">
				<p>
					Your comment is being held for approval.
				</p>
				<p>
					It might have failed to be verified as "ham" or the author
					wants to manually approve of new comments.
				</p>
				<p>
					Hold in there!
				</p>
			</div>
		<? endif ?>
		<? if (count($post->comments)): ?>
			<h2><?= $post->numberOfComments() ?></h2>
			<ul>
			<?
				$prevlevel = 0;
				foreach($post->comments as $level => $comment):
					if ($level > $prevlevel)
						echo '<ul class="l'.$level.'">';
					elseif ($level < $prevlevel)
						echo str_repeat('</ul>', $prevlevel-$level);
					$prevlevel = $level;
				?>
				<li class="comment<?
					if ($comment->type === GBComment::TYPE_PINGBACK) echo ' pingback';
					if ($comment->email === $post->author->email) echo ' post-author';
			 		?>" id="comment-<?= $comment->id ?>">
					<div class="avatar">
						<img src="<?= h($comment->avatarURL(48, 'default-avatar.png')) ?>" />
					</div>
					<div class="message-wrapper">
						<? if ($post->commentsOpen): ?>
						<div class="actions">
							<? if (gb::$authorized): ?>
								<a class="rm" href="<?= h($comment->removeURL()) ?>"
									onclick="return confirm('Really delete this comment?')"
									title="Remove this comment and hide any replies to it"><span>&otimes;</span></a>
							<? endif ?>
							<a class="reply" href="javascript:reply('<?= $comment->id ?>');" 
								title="Reply to this comment"><span>&#x21A9;</span></a>
							<div class="breaker"></div>
						</div>
						<? endif; ?>
						<div class="author">
							<?= $comment->nameLink('class="name"') ?>
							<a href="#comment-<?= $comment->id ?>" class="age"><?= $comment->date->age() ?></a>
						</div>
						<div class="breaker"></div>
						<div class="message">
							<?= $comment->body ?>
						</div>
					</div>
					<div class="breaker"></div>
				</li>
				<li class="breaker"></li>
			<? endforeach; echo str_repeat('</ul>', $prevlevel); ?>
			</ul>
			<!-- =========================== unapproved info =========================== -->
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
		<!-- =========================== post comment form =========================== -->
		<? if ($post->commentsOpen): ?>
			<div id="reply"></div>
			<h2 id="reply-title">Comment</h2>
			<form id="comment-form" action="<?= h(gb::$site_url) ?>gitblog/helpers/post-comment.php" method="POST">
				<?= gb_comment_fields() ?>
				<p>
					<textarea id="comment-reply-message" name="reply-message" rows="3"></textarea>
				</p>
				<p>
					<?= gb_comment_author_field('email', 'Email') 
						. gb_comment_author_field('name', 'Name')
					 	. gb_comment_author_field('url', 'Website (optional)') ?>
				</p>
				<p class="buttons">
					<input type="submit" value="Add comment" />
				</p>
				<div class="breaker"></div>
			</form>
			<script type="text/javascript" src="<?= gb::$theme_url ?>comment.js"></script>
		<? else: ?>
			<p>Comments are closed.</p>
		<? endif; ?>
		</div>
	</div>
	<? endif; ?>
	<div class="breaker"></div>
</div>
<? gb_flush() ?>
