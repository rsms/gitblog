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
<p>
	<?= $post->body ?>
</p>
