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
<script type="text/javascript" charset="utf-8">//<![CDATA[
	var c = {log:function(){}};
	if (typeof console != 'undefined' && typeof console.log != 'undefined')
		c = console;
	
	var post = {
		savedState: {},
		currentState: {},
		isModified: false,
		
		checkStateTimer: null,
		checkStateInterval: 10000,
		
		autoSaveTimer: null,
		autoSaveLatency: 2000,
		autoSaveEnabled: true, // setting
		_autoSaveEnabled: true, // runtime
		
		checkModifiedQueue: {},
		checkModifiedLatency: 100,
		checkModifiedAdjustThreshold: 50, // if checkTracked run faster then this, adjust checkModifiedLatency accordingly.
		
		pathspec: <?= $post->exists() ? '"'.str_replace('"', '\"',$post->name).'"' : 'null' ?>,
		
		trackState: function() {
			post.recordCurrentState(true);
			post.onNotModified();
			post.resetCheckStateTimer();
			post._autoSaveEnabled = post.autoSaveEnabled && post.pathspec != null; // no auto save for new (non-existing) posts
		},
		
		resetCheckStateTimer: function() {
			if (post.checkStateTimer != null)
				clearInterval(post.checkStateTimer);
			post.checkStateTimer = setInterval(post.checkFullState, post.checkStateInterval);
		},
		
		stopAutoSaveTimer: function() {
			if (post.autoSaveTimer != null)
				clearInterval(post.autoSaveTimer);
		},
		
		startAutoSaveTimer: function() {
			if (!post._autoSaveEnabled)
				return false;
			post.stopAutoSaveTimer();
			post.autoSaveTimer = setInterval(post.performAutosave, post.autoSaveLatency);
			return true;
		},
		
		performAutosave: function() {
			post.stopAutoSaveTimer();
			if (!post._autoSaveEnabled)
				return false;
			if (!post.isModified)
				return false;
			var modified = post.findModified();
			if (modified.length == 0)
				return false;
			post.save(modified);
			return true;
		},
		
		save: function(params) {
			if (typeof params == 'undefined')
				params = post.currentState;
			// disable save button
			$('input.save').addClass('disabled').attr('disabled', 'disabled').attr('value', 'Saving...');
			var postid = post.pathspec ? '?pathspec='+post.pathspec : '';
			// send request
			return $.ajax({
				type: "POST",
				url: "save-post.php"+postid,
				data: params,
				success: function(rsp) { post.onSaveDidSucceed(params, rsp); },
				error: function (req, status, exc) { post.onSaveDidFail(params, status, exc); }
			});
		},
		
		onSaveDidSucceed: function(params, rsp) {
			c.log('saved post >> '+rsp);
			// set post.*
			//post.pathspec = rsp.pathspec;
			
			// tidy up
			post._autoSaveEnabled = post.autoSaveEnabled;
			var nmodified = post.findModified().length;
			if (nmodified == 0 && post.isModified)
				post.onNotModified();
			else if (nmodified != 0 && !post.isModified)
				post.onModified();
			else
				$('input.save').attr('value', 'Saved');
		},
		
		onSaveDidFail: function(params, status, exc) {
			c.log('failed to save ('+status+'): '+exc+' -- disabling autosave');
			post._autoSaveEnabled = false;
			post.onModified();
		},
		
		recordCurrentState: function(setSavedState) {
			if (typeof setSavedState == 'undefined')
				setSavedState = false;
			$('.dep-save').each(function(i){
				var t = post.getNameValue(this);
				post.currentState[t.name] = t.value;
				if (setSavedState)
					post.savedState[t.name] = t.value;
				var j = $(this);
				j.change(function(ev){ post.checkTracked(this);});
				var type = j.attr('type');
				if (type == 'text' || (type == null && this.nodeName.toLowerCase() == 'textarea')) {
					j.keyup(function(ev){ post.queueCheckTracked(this);});
				}
			});
		},
		
		findModified: function() {
			var modified = {};
			for (var name in post.currentState) {
				if (post.savedState[name] != post.currentState[name])
					modified[name] = post.currentState[name];
			}
			return modified;
		},
		
		queueCheckTracked: function(el) {
			if (post.checkModifiedLatency < 1)
				return post.checkTracked(el);
			var t = post.getNameValue(el);
			clearTimeout(post.checkModifiedQueue[t.name]);
			post.checkModifiedQueue[t.name] = setTimeout(function(){ post.checkTracked(el); }, post.checkModifiedLatency);
		},
		
		checkTracked: function(el) {
			var startTime = (new Date()).getTime();
			var t = post.getNameValue(el);
			clearTimeout(post.checkModifiedQueue[t.name]);
			post.checkModifiedQueue[t.name] = null;
			post.currentState[t.name] = t.value;
			var modified = post.savedState[t.name] != t.value;
			if (modified && !post.isModified) {
				post.onModified([t.name]);
			}
			else if (!modified && post.isModified) {
				if (post.findModified().length == 0)
					post.onNotModified();
			}
			// this logic tunes latency and use of a queue in order to give good
			// performance while providing quick UI response.
			var rt = (new Date()).getTime() - startTime;
			if (rt < post.checkModifiedAdjustThreshold)
				post.checkModifiedLatency = rt > 1 ? rt * 2 : 0;
			else
				post.checkModifiedLatency = rt * 10;
		},
		
		checkFullState: function() {
			var modifiedFields = [];
			$('.dep-save').each(function(i){
				var t = post.getNameValue(this);
				if (post.savedState[t.name] != t.value) {
					post.currentState[t.name] = t.value;
					modifiedFields.push(t.name);
				}
			});
			if (modifiedFields.length && !post.isModified)
				post.onModified(modifiedFields);
			else if (modifiedFields.length == 0 && post.isModified)
				post.onNotModified();
		},
		
		onModified: function(modifiedFields) {
			post.isModified = true;
			$('input.save').removeClass('disabled').removeAttr('disabled').attr('value', 'Save');
			post.resetCheckStateTimer();
			post.startAutoSaveTimer();
		},
		
		onNotModified: function() {
			post.stopAutoSaveTimer();
			post.isModified = false;
			$('input.save').addClass('disabled').attr('disabled', 'disabled').attr('value', 'Saved');
			post.resetCheckStateTimer();
		},
		
		getNameValue: function(el) {
			var j = $(el);
			var type = j.attr('type');
			var haveValue = type == 'text' || (type == null && el.nodeName.toLowerCase() == 'textarea');
			var haveChecked = !haveValue && j.attr('type') == 'checkbox';
			if (typeof el.name != 'undefined') {
				if (haveValue)
					return {name: el.name, value: el.value};
				else if (haveChecked)
					return {name: el.name, value: el.checked};
			}
			return {name: null, value: null};
		}
	};
	
	$(function(){
		// track state
		post.trackState();

		// select/give focus to the body text area for new posts
		if (!post.pathspec) {
			$('textarea[name=body]').get(0).select();
			setTimeout(function(){$('textarea[name=body]').get(0).select();}, 500);
		}
		
		// the #post anchor in the iframe cases might cause the browser to scroll
		// down. We reset scrolling after the first load.
		$('#preview iframe').one('load', function(){ window.scrollTo(0,0); });
		
		// bind save button to post.save
		$('input.save').click(function(){ post.save(); });
	});
//]]></script>
<div id="content" class="<?= gb_admin::$current_domid ?>">
	<!-- title and save/commit controls -->
	<div class="section title">
		<div class="component title c2">
			<h4>Title</h4>
			<p>
				<input type="text" name="title" class="dep-save" value="<?= h($post->title) ?>" />
			</p>
		</div>
		<div class="component controls">
			<h4>&nbsp;</h4>
			<p>
				<? if (!$post->isTracked()): ?>
					<input type="button" class="discard" value="Discard" />
				<? endif ?>
				<input type="button" class="save" value="Save" />
				<? if ($post->isTracked()): ?>
					<input type="button" class="commit" value="Commit"
						title="Execute git commit and push changes live" />
				<? else: ?>
					<input type="button" class="commit publish" value="Publish"
						title="Make this post appear on your site (after the time specified by 'Publish date')." />
				<? endif ?>
			</p>
		</div>
		<div class="breaker"></div>
	</div>
	<!-- body -->
	<div class="section body">
		<div class="component body">
			<textarea name="body" class="dep-save"><?= h($post->rawBody()) ?></textarea>
		</div>
		<div class="breaker"></div>
	</div>
	<!-- tags and categories -->
	<div class="section">
		<div class="component tags c2a">
			<h4>Tags <small>(comma separated)</small></h4>
			<p>
				<input type="text" class="dep-save" name="tags" value="<?= implode(', ',array_map('h', $post->tags)) ?>" />
			</p>
		</div>
		<div class="component categories c3">
			<h4>Categories <small>(comma separated)</small></h4>
			<p>
				<input type="text" class="dep-save" name="categories" value="<?= implode(', ',array_map('h', $post->categories)) ?>" />
			</p>
		</div>
		<div class="breaker"></div>
	</div>
	<!-- publish and slug -->
	<div class="section">
		<div class="component publish-date c3">
			<h4>Publish date</h4>
			<p>
				<input type="text" name="published" class="dep-save" value="<?= h($post->published) ?>" />
			</p>
		</div>
		<div class="component slug c3">
			<h4>URL slug</h4>
			<p>
				<input type="text" name="slug" class="dep-save" value="<?= h($post->slug) ?>" />
			</p>
		</div>
		<div class="component slug c3">
			<h4>Author</h4>
			<p>
				<input type="text" name="author" class="dep-save" value="<?= h($post->author) ?>" />
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
					<input type="checkbox" name="commentsOpen" class="dep-save" 
						value="1" <?= $post->commentsOpen ? 'checked="checked"' : '' ?> />
					Allow comments
				</label>
				&nbsp;&nbsp;
				<label>
					<input type="checkbox" name="pingbackOpen" class="dep-save"
						value="1" <?= $post->pingbackOpen ? 'checked="checked"' : '' ?> />
					Allow pingbacks
				</label>
				&nbsp;&nbsp;
				<label>
					<input type="checkbox" name="draft" class="dep-save"
						value="1" <?= $post->draft ? 'checked="checked"' : '' ?> />
					Draft
				</label>
			</p>
		</div>
		<div class="breaker"></div>
	</div>
	<!-- commits -->
	<? if ($post->isTracked()): ?>
	<div class="section">
		<div class="component commits">
			<h4>Commits</h4>
			<ol>
			<? foreach ($post->findCommits() as $commit): ?>
				<? $user = $commit->authorUser(); ?>
				<li>
					<a href="<?= gb_admin::$url ?>helpers/commit.php?id=<?= $commit->id ?>&amp;paths[]=<?= urlencode($post->name) ?>">
						<samp><?= substr($commit->id, 0, 7) ?></samp>
					</a>
					<abbr title="<?= $commit->authorDate ?>"><?= $commit->authorDate->age(60*60*24, '%a, %B %e, %H:%M') ?></abbr>
					by <?= h($user ? $user->name : $commit->authorName) ?>
					—
					<a href="#revert">Undo</a>
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
<? include '../_footer.php' ?>