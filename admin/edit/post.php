<?
require '../_base.php';
gb::authenticate();
gb::$title[] = 'New post';

# array( string name => filterspec , .. )
$fields = array(
	'path' => FILTER_REQUIRE_SCALAR,
	'uri' => FILTER_REQUIRE_SCALAR,
	'version' => array(
		'filter' => FILTER_SANITIZE_STRING,
		'flags'  => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
	)
);
if (($q = filter_input_array(INPUT_GET, $fields)) === null)
	$q = array_fill_keys(array_keys($fields), null);

$post = false;
$body = '';

# default version
if ($q['version'] === null)
	$q['version'] = 'work';

# load existing post
if ($q['path']) {
	if (substr($q['path'], 'content/posts/') !== 0)
		$q['path'] = 'content/posts/' . $q['path'];
	if (!($post = GBPost::find($q['path'], $q['version'])))
		gb_admin::$errors[] = 'No post could be found at path '.r($q['path']);
}
elseif ($q['uri']) {
	$q['uri'] = ltrim($q['uri'], '/');
	if (!($post = GBPost::find($q['uri'], $q['version'])))
		gb_admin::$errors[] = 'No post could be found for URI '.r($q['uri']);
}

# no post found or new post
if (!$post) {
	$post = new GBPost();
	$post->published = new GBDateTime();
}

include '../_header.php';
?>
<div id="content" class="<?= gb_admin::$current_domid ?>">
	<!-- title and save/commit controls -->
	<div class="section title">
		<div class="component title c2">
			<h4>Title</h4>
			<p>
				<input type="text" name="title" value="<?= h($post->title) ?>" />
			</p>
		</div>
		<div class="component save-changes">
			<h4>&nbsp;</h4>
			<p>
				<? # todo  ?>
				<? if ($post->isTracked()): ?>
					<input type="button" value="Commit" title="Execute git commit and push changes live" />
				<? else: ?>
					<input type="button" value="Publish" 
					title="Make this post appear on your site (after the time specified by 'Publish date')." />
				<? endif ?>
				<input type="button" value="Saved" disabled="disabled" />
			</p>
		</div>
		<div class="breaker"></div>
	</div>
	<!-- body -->
	<div class="section body">
		<div class="component body">
			<textarea name="body"><?= h($post->rawBody()) ?></textarea>
		</div>
		<div class="breaker"></div>
	</div>
	<!-- tags and categories -->
	<div class="section">
		<div class="component tags c2a">
			<h4>Tags <small>(comma separated)</small></h4>
			<p>
				<input type="text" name="tags" value="<?= implode(', ',array_map('h', $post->tags)) ?>" />
			</p>
		</div>
		<div class="component categories c3">
			<h4>Categories <small>(comma separated)</small></h4>
			<p>
				<input type="text" name="categories" value="<?= implode(', ',array_map('h', $post->categories)) ?>" />
			</p>
		</div>
		<div class="breaker"></div>
	</div>
	<!-- publish and slug -->
	<div class="section">
		<div class="component publish-date c3">
			<h4>Publish date</h4>
			<p>
				<input type="text" name="slug" value="<?= h($post->published) ?>" />
			</p>
		</div>
		<div class="component slug c3">
			<h4>URL slug</h4>
			<p>
				<input type="text" name="slug" value="<?= h($post->slug) ?>" />
			</p>
		</div>
		<div class="component slug c3">
			<h4>Author</h4>
			<p>
				<input type="text" name="author" value="<?= h($post->author) ?>" />
			</p>
		</div>
		<div class="breaker"></div>
	</div>
	<!-- options -->
	<div class="section">
		<div class="component">
			<h4>Options</h4>
			<p>
				<label>
					<input type="checkbox" name="commentsOpen" value="1" <?= $post->commentsOpen ? 'checked="checked"' : '' ?> />
					Allow comments
				</label>
				&nbsp;&nbsp;
				<label>
					<input type="checkbox" name="pingbacksOpen" value="1" <?= $post->pingbackOpen ? 'checked="checked"' : '' ?> />
					Allow pingbacks
				</label>
				&nbsp;&nbsp;
				<label>
					<input type="checkbox" name="pingbacksOpen" value="1" <?= $post->draft ? 'checked="checked"' : '' ?> />
					Draft
				</label>
			</p>
		</div>
		<div class="breaker"></div>
	</div>
	<!-- versions -->
	<? if ($post->isTracked()): ?>
	<div class="section">
		<div class="component">
			<h4>Versions</h4>
			<ol>
			<? foreach ($post->findCommits() as $commit): ?>
				<? $user = $commit->authorUser(); ?>
				<li>
					<a href="<?= gb_admin::$url ?>helpers/commit.php?id=<?= $commit->id ?>&amp;paths[]=<?= urlencode($post->name) ?>">
						<samp><?= substr($commit->id, 0, 7) ?></samp>
						by <?= h($user ? $user->name : $commit->authorName) ?>
						at <?= $commit->authorDate ?>
					</a>
				</li>
			<? endforeach ?>
			</ol>
		</div>
		<div class="breaker"></div>
	</div>
	<? endif ?>
</div>
<? if ($post->exists()): ?>
<div id="preview">
	<iframe src="<?= $post->url($q['version']) ?>#post"></iframe>
</div>
<? endif ?>
<div class="breaker"></div>
<? include '../_footer.php' ?>