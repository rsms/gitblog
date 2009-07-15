<div id="sidebar">
	<h2>About</h2>
	<?= @GBPage::find('about-intro')->body ?>
	<hr />
	<h2>Popular tags</h2>
	<ol id="tags">
	<? foreach (GitBlog::tags() as $tag => $popularity): if ($popularity < 0.1) break; ?>
		<li class="p<?= intval(round($popularity * 10.0)) ?>"><?= gb_tag_link($tag) ?></li>
	<? endforeach; ?>
	</ol>
	<div class="breaker"></div>
</div>
