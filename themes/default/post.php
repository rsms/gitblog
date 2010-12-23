<div class="wrapper">
	<!-- =========================== sidebar =========================== -->
	<?php include gb::$theme_dir . '/sidebar.php' ?>
	<!-- =========================== post =========================== -->
	<div class="posts single" id="post">
		<div class="post">
			<?php echo $post->commentsLink() ?>
			<h1><?php echo $post->title ?></h1>
			<p class="meta">
				<?php if (gb::$is_post): ?>
					<abbr title="<?php echo $post->published ?>"><?php echo $post->published->age() ?></abbr>
					by <?php echo h($post->author->name) . $post->tagLinks(', tagged ') . $post->categoryLinks(', filed under ')  ?>
				<?php else: ?>
					<?php $s=$post->tagLinks('tagged '); echo $s;  echo ($s ? ', ':'') . $post->categoryLinks('filed under ') ?>
				<?php endif ?>
			</p>
			<div class="body">
				<?php echo $post->body() ?>
			</div>
		</div>
		<div class="breaker"></div>
	</div>
	<!-- =========================== comments =========================== -->
	<?php if ($post->commentsOpen || count($post->comments)): ?>
	<div id="comments">
		<hr/>
		<?php if (isset($_GET['skipped-duplicate-comment'])): ?>
			<div class="notification">
				<p>
					You posted a duplicate comment. Naughty! It was rejected.
				</p>
			</div>
		<?php elseif (isset($_GET['comment-pending-approval'])): ?>
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
		<?php endif ?>
		<?php if (count($post->comments)): ?>
			<h2><?php echo $post->numberOfComments() ?></h2>
			<ul>
			<?php
				$prevlevel = 0;
				foreach($post->comments as $level => $comment):
					if ($level > $prevlevel)
						echo '<ul class="l'.$level.'">';
					elseif ($level < $prevlevel)
						echo str_repeat('</ul>', $prevlevel-$level);
					$prevlevel = $level;
				?>
				<li class="comment<?php
					if ($comment->type === GBComment::TYPE_PINGBACK) echo ' pingback';
					if ($comment->email === $post->author->email) echo ' post-author';
			 		?>" id="comment-<?php echo $comment->id ?>">
					<div class="avatar">
						<img src="<?php echo h($comment->avatarURL(48, 'default-avatar.png')) ?>" alt="Avatar" />
					</div>
					<div class="message-wrapper">
						<?php if ($post->commentsOpen): ?>
						<div class="actions">
							<?php if (gb::$authorized): ?>
								<a class="rm" href="<?php echo h($comment->removeURL()) ?>"
									onclick="return confirm('Really delete this comment?')"
									title="Remove this comment and hide any replies to it"><span>&otimes;</span></a>
							<?php endif ?>
							<a class="reply" href="javascript:reply('<?php echo $comment->id ?>');" 
								title="Reply to this comment"><span>&#x21A9;</span></a>
							<div class="breaker"></div>
						</div>
						<?php endif; ?>
						<div class="author">
							<?php echo $comment->nameLink('class="name"') ?>
							<a href="#comment-<?php echo $comment->id ?>" class="age"><?php echo $comment->date->age() ?></a>
						</div>
						<div class="breaker"></div>
						<div class="message">
							<?php echo $comment->body() ?>
						</div>
					</div>
					<div class="breaker"></div>
				</li>
				<li class="breaker"></li>
			<?php endforeach; echo str_repeat('</ul>', $prevlevel); ?>
			</ul>
			<!-- =========================== unapproved info =========================== -->
			<div class="breaker"></div>
			<?php if ($post->comments->countUnapproved()): ?>
				<p><small>
					<?php echo $post->numberOfUnapprovedComments() ?> awaiting approval
					<?php if ($post->comments->countShadowed()): ?>
						— <?php echo $post->numberOfShadowedComments(
							'approved comment is', 'approved comments are', 'no', 'one') ?>
						therefore not visible.
					<?php endif; ?>
				</small></p>
			<?php endif; ?>
		<?php endif; # comments ?>
		<!-- =========================== post comment form =========================== -->
		<?php if ($post->commentsOpen): ?>
			<div id="reply"></div>
			<h2 id="reply-title">Comment</h2>
			<form id="comment-form" action="<?php echo h(gb::$site_url) ?>gitblog/helpers/post-comment.php" method="post">
				<div><?php echo gb_comment_fields() ?></div>
				<p>
					<textarea id="comment-reply-message" name="reply-message" cols="80" rows="3"></textarea>
				</p>
				<p>
					<?php echo gb_comment_author_field('email', 'Email') 
						. gb_comment_author_field('name', 'Name')
					 	. gb_comment_author_field('url', 'Website (optional)') ?>
				</p>
				<p class="buttons">
					<input type="submit" value="Add comment" />
				</p>
				<div class="breaker"></div>
			</form>
			<script type="text/javascript" src="<?php echo gb::$theme_url ?>comment.js"></script>
		<?php else: ?>
			<p>Comments are closed.</p>
		<?php endif; ?>
		</div>
	<?php endif; ?>
	<div class="breaker"></div>
</div>
<?php gb_flush() ?>