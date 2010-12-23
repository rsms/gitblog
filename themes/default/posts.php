<!-- ========================== top columns/info ========================== -->
<?php if (gb::$is_posts): ?>
<div id="summary" class="columns c3">
	<div class="wrapper">
		<div class="col">
		<?php if (($snippet = GBPage::find('about/intro'))): ?>
			<h2><?php echo h($snippet->title) ?></h2>
			<?php echo $snippet->body() ?>
		<?php endif; ?>
		</div>
		<div class="col recent-posts">
			<h2>Recent posts</h2>
			<ol>
				<?php foreach ($postspage->posts as $rank => $post):
					if ($post->published->time > $time_now) continue;
					if ($rank === 6) break; ?>
					<li>
						<a href="<?php echo h($post->url()) ?>"><?php echo h($post->title) ?></a>
						<span class="age"><?php echo $post->published->age() ?></span>
					</li>
				<?php endforeach ?>
			</ol>
		</div>
		<div class="col">
			<h2>Popular tags</h2>
			<ol class="tags">
			<?php foreach (gb::tags() as $tag => $popularity): if ($popularity < 0.2) break; ?>
				<li class="p<?php echo intval(round($popularity * 10.0)) ?>"><?php echo gb_tag_link($tag) ?></li>
			<?php endforeach; ?>
			</ol>
		</div>
	</div>
	<div class="breaker"></div>
</div>
<?php elseif ( (gb::$is_categories && count($categories) > 1) || gb::$is_tags): ?>
<div id="summary" class="breadcrumb">
	<div class="wrapper">
		<p>
			<?php echo counted($postspage->numtotal, 'post', 'posts') ?>
		<?php if (gb::$is_categories): ?>
			filed under <span class="highlight"><?php echo sentenceize($categories, 'h') ?></span>
		<?php elseif (gb::$is_tags): ?>
			tagged with <span class="highlight"><?php echo sentenceize($tags, 'h') ?></span>
		<?php endif ?>
		</p>
	</div>
	<div class="breaker"></div>
</div>
<?php endif ?>
<div class="wrapper">
	<!-- =========================== sidebar =========================== -->
	<?php include gb::$theme_dir . '/sidebar.php' ?>
	<!-- =========================== posts =========================== -->
	<div class="posts">
		<!-- For SEO purposes. Not actually displayed: -->
		<h1><?php echo gb_site_title() ?></h1>
	<?php foreach ($postspage->posts as $post): if ($post->published->time > $time_now) continue; ?>
		<div class="post">
			<?php echo $post->commentsLink() ?>
			<h2><a href="<?php echo h($post->url()) ?>"><?php echo h($post->title) ?></a></h2>
			<p class="meta">
				<abbr title="<?php echo $post->published ?>"><?php echo $post->published->age() ?></abbr>
				by <?php echo h($post->author->name) . $post->tagLinks(', tagged ') . $post->categoryLinks(', filed under ')  ?>
			</p>
			<div class="body">
				<?php echo $post->body() ?>
				<?php if ($post->excerpt): ?>
					<p class="read-more"><a href="<?php echo h($post->url()) ?>#read-more">Continue reading...</a></p>
				<?php endif; ?>
			</div>
		</div>
		<div class="breaker"></div>
	<?php endforeach ?>
	<?php if (!$postspage->posts): /* todo: support for scheduled posts */ ?>
		<p>
			There is no published content here at the moment. Check back later my friend.
		</p>
	<?php endif; ?>
	</div>
	<div class="breaker"></div>
</div>
<!-- =========================== paged nav =========================== -->
<div id="paged-footer">
	<div class="wrapper">
	<?php if ($postspage->nextpage != -1 || $postspage->prevpage != -1): ?>
		<?php if ($postspage->nextpage != -1): ?>
			<a href="?page=<?php echo $postspage->nextpage ?>">« Older posts</a>
		<?php endif; ?>
		(total <?php echo $postspage->numtotal ?> posts on <?php echo $postspage->numpages ?> pages)
		<?php if ($postspage->prevpage != -1): ?>
			<a href="?page=<?php echo $postspage->prevpage ?>">Newer posts »</a>
		<?php endif; ?>
	<?php endif; ?>
	</div>
</div>