<?
require_once '../_base.php';
gb::authenticate();
gb::$title[] = 'Posts';
include '../_header.php';

$unapproved_comments = gb::index('unapproved-comments', array());

?>
<div id="content" class="<?= gb_admin::$current_domid ?> manage items">
	<h2>Pending comments</h2>
	<table class="items comments">
	<? foreach ($unapproved_comments as $t): list($comment, $post) = $t; ?>
		<? $post_editurl = gb_admin::$url.'edit/post.php?name='.urlencode($post->name); ?>
		<tr class="<?=($comment->spam === true ? 'spam':'')?>">
			<td class="avatar">
				<img src="<?= h($comment->avatarURL(48, gb_admin::$url.'res/default-avatar.png')) ?>" alt="Avatar" width="48" height="48" />
			</td>
			<td class="info">
				<div class="actions">
					<?= h($comment->date->condensed()) ?>
					&nbsp;
					<a class="badge button approve" href="<?= h($comment->approveURL()) ?>"
						<? if ($comment->spam): ?>
							onclick="return confirm('Really approve this comment which is probably spam?')"
						<? endif ?>
						title="Approve this comment"><span>&#x2713;</span></a>
					<a class="badge button rm" href="<?= h($comment->removeURL()) ?>"
						<? if (!$comment->spam): ?>
							onclick="return confirm('Really delete this comment?')"
						<? endif ?>
						title="Permanently remove this comment"><span>&#x2715;</span></a>
					<? if (!$comment->spam): ?>
						<a class="badge button spam" href="<?= h($comment->approveURL()) ?>"
							title="Flag this comment as spam and remove it"><span>&#x2691;</span></a>
					<? endif ?>
				</div>
				<span class="author">
					<?= $comment->nameLink('class="name"') ?>
				</span>
				on
				<span class="post-title">
					<a href="<?=h($post->url())?>" title="Edit post written by <?= h($post->author->shortName()) ?>">
						<?= h($post->title ? $post->title : '('.substr($post->name,strlen('content/posts/')).')') ?>
					</a>
				</span>
				<span class="badge <?=($comment->spam === true ? 'spam':'ham')?>">
					<?=($comment->spam === true ? 'Spam':'Ham')?>
				</span>
				<p class="excerpt">
					<?= h(gb_strlimit($comment->textBody(), 300)) ?>
				</p>
			</td>
		</tr>
	<? endforeach ?>
	</table>
</div>
<? include '../_footer.php' ?>
