<? if (gb::$is_posts): ?>
<div id="summary" class="columns c3">
	<div class="wrapper">
		<div class="col">
		<? if (($snippet = GBPage::find('about/intro'))): ?>
			<h2><?= h($snippet->title) ?></h2>
			<?= $snippet->body ?>
		<? endif; ?>
		</div>
		<div class="col recent-posts">
			<h2>Recent posts</h2>
			<ol>
				<? foreach ($postspage->posts as $rank => $post): if ($rank === 6) break; ?>
					<li>
						<a href="<?= h($post->url()) ?>"><?= h($post->title) ?></a>
						<?= $post->published->age() ?>
					</li>
				<? endforeach ?>
			</ol>
		</div>
		<div class="col">
			<h2>Popular tags</h2>
			<ol class="tags">
			<? foreach (gb::tags() as $tag => $popularity): if ($popularity < 0.2) break; ?>
				<li class="p<?= intval(round($popularity * 10.0)) ?>"><?= gb_tag_link($tag) ?></li>
			<? endforeach; ?>
			</ol>
		</div>
	</div>
	<div class="breaker"></div>
</div>
<? elseif ( (gb::$is_categories && count($categories) > 1) || gb::$is_tags): ?>
<div id="summary" class="breadcrumb">
	<div class="wrapper">
		<p>
			<?= counted($postspage->numtotal, 'post', 'posts') ?>
		<? if (gb::$is_categories): ?>
			filed under <?= sentenceize($categories, 'h') ?>
		<? elseif (gb::$is_tags): ?>
			tagged with <?= sentenceize($tags, 'h') ?>
		<? endif ?>
		</p>
	</div>
	<div class="breaker"></div>
</div>
<? endif ?>
<div class="wrapper">
	<div class="posts">
		<h1><?= gb_site_title() ?></h1>
	<? foreach ($postspage->posts as $post): ?>
		<div class="post">
			<?= $post->commentsLink() ?>
			<h1><a href="<?= h($post->url()) ?>"><?= h($post->title) ?></a></h1>
			<p class="meta">
				<?= $post->published->age() ?>
				by <?= h($post->author->name) . $post->tagLinks(', tagged ') . $post->categoryLinks(', filed under ')  ?>
			</p>
			<div class="body">
				<?= $post->body ?>
			</div>
			<? if ($post->excerpt): ?>
				<p class="read-more"><a href="<?= h($post->url()) ?>#read-more">Continue reading...</a></p>
			<? endif; ?>
		</div>
		<div class="breaker"></div>
	<? endforeach ?>
	<? if (!$postspage->posts): ?>
		<p>
			There is no published content here at the moment. Check back later my friend.
		</p>
	<? endif; ?>
	</div>
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
