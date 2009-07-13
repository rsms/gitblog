<div class="posts">
<? foreach ($postspage->posts as $post): ?>
	<div class="post">
		<div class="wrapper">
			<h1><a href="<?= h($post->url()) ?>"><?= h($post->title) ?></a></h1>
			<p class="meta">
				<?= $post->published->utcformat('%B %e, %Y') ?>
				by <?= h($post->author->name) . $post->tagLinks(', tagged ') . $post->categoryLinks(', categorized as ')  ?>
				<?= $post->comments ? '('.$post->numberOfComments().')' : '' ?>
			</p>
			<div class="body">
				<?= $post->body ?>
			</div>
			<? if ($post->excerpt): ?>
				<p><a href="<?= h($post->url()) ?>#<?= $post->domID() ?>-more">Read more...</a></p>
			<? endif; ?>
		</div>
	</div>
	<div class="breaker"></div>
<? endforeach ?>
</div>
<? if ($postspage->nextpage != -1 || $postspage->prevpage != -1): ?>
<div class="wrapper">
	<? if ($postspage->nextpage != -1): ?>
		<a href="?page=<?= $postspage->nextpage ?>">« Older posts</a>
	<? endif; ?>
	(total <?= $postspage->numtotal ?> posts on <?= $postspage->numpages ?> pages)
	<? if ($postspage->prevpage != -1): ?>
		<a href="?page=<?= $postspage->prevpage ?>">Newer posts »</a>
	<? endif; ?>
</div>
<? endif; ?>