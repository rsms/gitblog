<?
require_once '../_base.php';
gb::authenticate();
gb::$title[] = 'Posts';
include '../_header.php';

$unapproved_comments = gb::index('unapproved-comments');
$spam_comments = array();
$pending_comments = array();

foreach ($unapproved_comments as $comment_post_tuple) {
	if ($comment_post_tuple[0]->spam === true)
		$spam_comments[] = $comment_post_tuple;
	else
		$pending_comments[] = $comment_post_tuple;
}

exit('work in progress');
?>
<div id="content" class="<?= gb_admin::$current_domid ?>">
	<h2>Pending comments</h2>
	<table class="posts offline">
	<? foreach ($pending_comments as $t): list($comment, $post) = $t; ?>
		<? $editurl = gb_admin::$url.'edit/post.php?name='.urlencode($post->name); ?>
		<tr>
			<td class="name">
				<span class="title">
					<?= h($post->title ? $post->title : '('.substr($post->name,strlen('content/posts/')).')') ?>
				</span>
				<span class="excerpt">
					<? $s=h(gb_strlimit($post->textBody(), 80));echo $s ? ' – '.$s : '' ?>
				</span>
			</td>
			<td class="author"><?= h($post->author->shortName()) ?></td>
			<td class="date modified type-number"><?= h($post->modified->condensed()) ?></td>
		</tr>
	<? endforeach ?>
	</table>
	<div class="paged-nav">
		<? if ($num_more_postpages): ?>
		<a href="javascript:alert('Paging not yet implemented')">Load <?= $num_more_postpages ?> more pages</a>
		<? endif ?>
	</div>
</div>
<? include '../_footer.php' ?>
