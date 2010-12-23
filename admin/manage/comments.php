<?php
require_once '../_base.php';
gb::authenticate();
gb::$title[] = 'Posts';
include '../_header.php';

$unapproved_comments = gb::index('unapproved-comments', array());

?>
<div id="content" class="<?php echo gb_admin::$current_domid ?> manage items">
	<h2>Pending comments</h2>
	<table class="items comments">
	<?php foreach ($unapproved_comments as $t): list($comment, $post) = $t; ?>
		<?php $post_editurl = gb_admin::$url.'edit/post.php?name='.urlencode($post->name); ?>
		<tr class="<?php echo($comment->spam === true ? 'spam':'')?>">
			<td class="avatar">
				<img src="<?php echo h($comment->avatarURL(48, gb_admin::$url.'res/default-avatar.png')) ?>" alt="Avatar" width="48" height="48" />
			</td>
			<td class="info">
				<div class="actions">
					<?php echo h($comment->date->condensed()) ?>
					&nbsp;
					<a class="badge button approve" href="<?php echo h($comment->approveURL()) ?>"
						<?php if ($comment->spam): ?>
							onclick="return confirm('Really approve this comment which is probably spam?')"
						<?php endif ?>
						title="Approve this comment"><span>&#x2713;</span></a>
					<a class="badge button rm" href="<?php echo h($comment->removeURL()) ?>"
						<?php if (!$comment->spam): ?>
							onclick="return confirm('Really delete this comment?')"
						<?php endif ?>
						title="Permanently remove this comment"><span>&#x2715;</span></a>
					<?php if (!$comment->spam): ?>
						<a class="badge button spam" href="<?php echo h($comment->approveURL()) ?>"
							title="Flag this comment as spam and remove it"><span>&#x2691;</span></a>
					<?php endif ?>
				</div>
				<span class="author">
					<?php echo $comment->nameLink('class="name"') ?>
				</span>
				on
				<span class="post-title">
					<a href="<?php echoh($post->url())?>" title="Edit post written by <?php echo h($post->author->shortName()) ?>">
						<?php echo h($post->title ? $post->title : '('.substr($post->name,strlen('content/posts/')).')') ?>
					</a>
				</span>
				<span class="badge <?php echo($comment->spam === true ? 'spam':'ham')?>">
					<?php echo($comment->spam === true ? 'Spam':'Ham')?>
				</span>
				<p class="excerpt">
					<?php echo h(gb_strlimit($comment->textBody(), 300)) ?>
				</p>
			</td>
		</tr>
	<?php endforeach ?>
	</table>
</div>
<?php include '../_footer.php' ?>
