<div class="wrapper">
	<div class="posts">
	<? foreach ($postspage->posts as $post): ?>
		<div class="post">
			<?= $post->commentsLink() ?>
			<h1><a href="<?= h($post->url()) ?>"><?= h($post->title) ?></a></h1>
			<p class="meta">
				<?= $post->published->age() ?>
				by <?= h($post->author->name) . $post->tagLinks(', tagged ') . $post->categoryLinks(', categorized as ')  ?>
			</p>
			<div class="body">
				<?= $post->body ?>
			</div>
			<? if ($post->excerpt): ?>
				<p><a href="<?= h($post->url()) ?>#<?= $post->domID() ?>-more">Read more...</a></p>
			<? endif; ?>
		</div>
		<div class="breaker"></div>
		<hr />
	<? endforeach ?>
	<? if (!$postspage->posts): ?>
		<p>
			There is no published content here at the moment. Check back later my friend.
		</p>
	<? endif; ?>
	</div>
	<? include 'sidebar.php' ?>
	<div class="breaker"></div>
</div>
<div id="paged-footer">
	<div class="wrapper">
	<? if ($postspage->nextpage != -1 || $postspage->prevpage != -1): ?>
		<? if ($postspage->nextpage != -1): ?>
			<a href="?page=<?= $postspage->nextpage ?>">« Older posts</a>
		<? endif; ?>
		(total <?= $postspage->numtotal ?> posts on <?= $postspage->numpages ?> pages)
		<? if ($postspage->prevpage != -1): ?>
			<a href="?page=<?= $postspage->prevpage ?>">Newer posts »</a>
		<? endif; ?>
	<? endif; ?>
	</div>
</div>
