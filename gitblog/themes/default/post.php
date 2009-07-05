<h2><?= $post->title ?></h2>
	<div id="post-meta">
		<h3>Details</h3>
		<ul>
			<li>Author: <a href="mailto:<?= $post->author->email ?>"><?= $post->author->name ?></a></li>
			<li>Published: <?= date('c', $post->published) ?></li>
			<li>Modified: <?= date('c', $post->modified) ?></li>
			<li>Tags: <?= $post->tagLinks() ?></li>
			<li>Categories: <?= $post->categoryLinks() ?></li>
			<li>Version: <?= $post->id ?></li>
		</ul>
	</div>
<?= $post->body ?>
<hr />
<h3><?= $post->numberOfComments() ?></h3>
<? if ($post->comments): ?>
	<ul>
	<? foreach($post->comments as $level => $comment): ?>
		<li>
			level <?= $level ?>, id <?= $comment->id ?> -- <?= h($comment->name) ?><br/>
		</li>
	<? endforeach; ?>
	</ul>
	<? if ($post->comments->countUnapproved()): ?>
		<p>
			<?= $post->numberOfUnapprovedComments() ?> awaiting approval
			<? if ($post->comments->countShadowed()): ?>
				—
				<?= $post->numberOfShadowedComments('approved comment is', 'approved comments are', 'no', 'one') ?>
				therefore not visible.
			<? endif; ?>
		</p>
	<? endif; ?>
<? endif; # comments ?>