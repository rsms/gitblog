<h2><?= $post->title ?></h2>
	<div id="post-meta">
		<h3>Details</h3>
		<ul>
			<li>Author: <a href="mailto:<?= $post->author->email ?>"><?= $post->author->name ?></a></li>
			<li>Published: <?= date('c', $post->published) ?></li>
			<li>Modified: <?= date('c', $post->modified) ?></li>
			<li>Tags: <?= $post->tagLinks() ?></li>
			<li>Categories: <?= $post->categoryLinks() ?></li>
			<li>Revision (current object): <?= $post->id ?></li>
		</ul>
	</div>
<?= $post->body ?>
<hr />
<h3>Comments</h3>
<ul>
<?
function draw_comments($c) {
	foreach ($c->comments as $comment) {
		#if (!$comment->approved) continue;
		echo '<li><em>'. h($comment->name) .' says</em>';
		echo '<p>'. nl2br(h($comment->message)) .'</p>';
		if ($comment->comments) {
			echo '<ul>';
			draw_comments($comment);
			echo '</ul>';
		}
		echo '</li>';
	}
}
?>
<? draw_comments($post->comments); ?>
</ul>