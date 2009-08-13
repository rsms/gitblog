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
	if (!($post = GBPost::findByName($q['path'], $q['version'])))
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
	
	var http = {
		postForm: function(kw) {
			kw.data = http.toFormParams(kw.data);
			return http.post(kw);
		},
		
		post: function(kw) {
			kw.type = "POST";
			c.log(kw.type, kw.url, kw.data);
			return $.ajax(kw);
		},
		
		toFormParams: function(params) {
			if (typeof params == 'object')
				for (var k in params)
					http._flattenComplexFormParams(params, k, params[k]);
			return params;
		},
		
		_flattenComplexFormParams: function(params, k, v) {
			if (typeof v == 'object') {
				for (var x in v) {
					var k2 = k+'['+x+']';
					var v2 = v[x];
					if (!http._flattenComplexFormParams(params, k2, v2))
						params[k2] = v2;
				}
				delete(params[k]);
				return true;
			}
			return false;
		}
	};
	
	var post = {
		savedState: {},
		currentState: {},
		isModified: false,
		
		checkStateTimer: null,
		checkStateInterval: 10000,
		
		autoSaveTimer: null,
		autoSaveLatency: 4000,
		autoSaveActivityLatency: 1000,
		autoSaveEnabled: true, // setting
		_autoSaveEnabled: true, // runtime
		
		checkModifiedQueue: {},
		checkModifiedLatency: 100,
		checkModifiedAdjustThreshold: 50, // if checkTracked run faster then this, adjust checkModifiedLatency accordingly.
		
		name: <?= $post->exists() ? '"'.str_replace('"', '\"',$post->name).'"' : 'null' ?>,
		version: <?= $post->exists() ? '"'.str_replace('"', '\"',$post->id).'"' : 'null' ?>,
		
		trackState: function() {
			post.setupFieldFilters();
			post.recordCurrentState(true);
			post.onNotModified();
			post.resetCheckStateTimer();
			post._autoSaveEnabled = post.autoSaveEnabled && post.name != null; // no auto save for new (non-existing) posts
		},
		
		recordCurrentState: function(setSavedState) {
			if (typeof setSavedState == 'undefined')
				setSavedState = false;
			$('.dep-save').each(function(i){
				var t = post.getField(this);
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
		
		fieldGetFilters: {},
		fieldPutFilters: {},
		
		standardFilters: {	
			csv_to_list: function(s){
				return s.replace(/(^[ \t\s\n\r,]+|[ \t\s\n\r,]+$)/g, '').split(/,[ \s\t]*/);
			},
			list_to_csv: function(l){
				return l.join(', ');
			}
		},
		
		setupFieldFilters: function() {
			// comma separated values <--> list of strings
			$('.transform-csv').each(function(i){
				var t = post.getField(this, false);
				post.fieldGetFilters[t.name] = post.standardFilters.csv_to_list;
				post.fieldPutFilters[t.name] = post.standardFilters.list_to_csv;
			});
		},
		
		applyFilters: function(name, value, filterCollection) {
			if (name in filterCollection) {
				var filters = filterCollection[name];
				if (typeof filters == 'function')
					filters = [filters];
				for (var i in filters) {
					var filter = filters[i];
					//c.log('filtering value', value);
					value = filter(value);
					//c.log('filter returned', value);
				}
			}
			return value;
		},
		
		investigateField: function(j) {
			var p = {type: j.attr('type')};
			p.haveValue = p.type == 'text' || (p.type == null && j.get(0).nodeName.toLowerCase() == 'textarea');
			p.haveChecked = !p.haveValue && j.attr('type') == 'checkbox';
			return p;
		},
		
		putField: function(name, value, applyFilters) {
			if (typeof applyFilters == 'undefined' || applyFilters)
				value = post.applyFilters(name, value, post.fieldPutFilters);
			var j = $('input[name='+name+']');
			var p = post.investigateField(j);
			var el = j.get(0);
			if (p.haveValue) {
				// check eq first to avoid browser bugs when setting values
				if (el.value != value)
					el.value = value;
			}
			else if (p.haveChecked) {
				el.checked = value;
			}
			else {
				c.log('todo post.putField other type');
			}
		},
		
		getField: function(el, applyFilters) {
			var p = post.investigateField($(el));
			var t = {name: null, value: null};
			if (typeof el.name != 'undefined') {
				t.name = el.name;
				if (p.haveValue)
					t.value = el.value;
				else if (p.haveChecked)
					t.value = el.checked;
				else
					c.log('todo post.getField other type');
			}
			if (typeof applyFilters == 'undefined' || applyFilters)
				t.value = post.applyFilters(t.name, t.value, post.fieldGetFilters);
			return t;
		},
		
		resetCheckStateTimer: function() {
			if (post.checkStateTimer != null)
				clearInterval(post.checkStateTimer);
			post.checkStateTimer = setInterval(post.checkFullState, post.checkStateInterval);
		},
		
		stopAutoSaveTimer: function() {
			clearInterval(post.autoSaveTimer);
			post.autoSaveTimer = null;
		},
		
		startAutoSaveTimer: function(interval) {
			if (!post._autoSaveEnabled)
				return false;
			if (typeof interval == 'undefined')
				interval = post.autoSaveLatency;
			post.stopAutoSaveTimer();
			post.autoSaveTimer = setInterval(post.performAutosave, interval);
			return true;
		},
		
		delayAutoSaveTimer: function(delayInterval) {
			if (post.autoSaveTimer == null)
				return;
			post.stopAutoSaveTimer();
			post.startAutoSaveTimer(delayInterval);
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
			if (post.name)
				params.name = post.name;
			// disable save button
			$('input.save').addClass('disabled').attr('disabled', 'disabled').attr('value', 'Saving...');
			// send
			return http.postForm({
				url: "../helpers/save-post.php",
				data: params,
				success: function(rsp) { post.onSaveDidSucceed(params, rsp); },
				error: function (req, type, exc) { post.onSaveDidFail(req, type, exc, params); }
			});
		},
		
		onSaveDidSucceed: function(params, rsp) {
			try {
				if (rsp.length)
					rsp = eval('('+rsp+')');
				else
					rsp = null;
				c.log('saved changes', rsp);
			}
			catch(e) {
				c.log('failed to parse response from save-post', rsp);
			}
			
			// transfer updated data
			var updated = {};
			if (typeof rsp == 'object') {
				// spec changed?
				var spec = {'name':post.onNameChanged, 'version':post.onVersionChanged};
				for (var k in spec) {
					if (typeof rsp[k] != 'undefined' && rsp[k] != post[k]) {
						post[k] = rsp[k];
						if (typeof spec[k] == 'function')
							spec[k]();
					}
				}
				// fields
				if (typeof rsp.state == 'object') {
					for (var name in rsp.state) {
						var value = rsp.state[name];
						if (typeof post.savedState[name] == 'undefined' || !post.eq(post.savedState[name], value)) {
							post.savedState[name] = value;
							post.currentState[name] = value;
							post.putField(name, value);
							updated[name] = value;
							c.log(name+' was updated. is now:', value);
						}
					}
				}
			}
			
			// tidy up
			post._autoSaveEnabled = post.autoSaveEnabled;
			$('input.save').attr('value', 'Saved');
			
			// no longer modified
			if (post.isModified)
				post.onNotModified();
			
			// reload iframe
			if (updated.length)
				$('#preview iframe')[0].contentDocument.location.reload();
		},
		
		onSaveDidFail: function(req, type, exc, params) {
			c.log('failed to save post with status '+req.status+' '+req.statusText+': '
				+req.responseText+' '
				+(typeof exc != 'undefined' ? exc : ''));
			c.log('disabling autosave');
			post._autoSaveEnabled = false;
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
			var t = post.getField(el);
			clearTimeout(post.checkModifiedQueue[t.name]);
			post.checkModifiedQueue[t.name] = setTimeout(function(){ post.checkTracked(el); }, post.checkModifiedLatency);
		},
		
		eq: function(a, b) {
			if (a == b)
				return true;
			var at = typeof a;
			var bt = typeof b;
			if (at != bt)
				return false;
			if (at == 'object') {
				if ($.isArray(a))
					a = $.extend({}, a);
				if ($.isArray(b))
					b = $.extend({}, b);
				return $.param(http.toFormParams(a)) == $.param(http.toFormParams(b));
			}
			return false;
		},
		
		checkTracked: function(el) {
			var startTime = (new Date()).getTime();
			var t = post.getField(el);
			clearTimeout(post.checkModifiedQueue[t.name]);
			post.checkModifiedQueue[t.name] = null;
			var modified = !post.eq(post.currentState[t.name], t.value);
			
			if (modified) {
				post.currentState[t.name] = t.value;
				if (post.isModified)
					post.delayAutoSaveTimer(post.autoSaveActivityLatency);
			}
			
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
				var t = post.getField(this);
				if (!post.eq(post.savedState[t.name], t.value)) {
					post.currentState[t.name] = t.value;
					modifiedFields.push(t.name);
				}
			});
			if (modifiedFields.length && !post.isModified)
				post.onModified(modifiedFields);
			else if (modifiedFields.length == 0 && post.isModified)
				post.onNotModified();
		},
		
		onNameChanged: function() {
			c.log('new name:', post.name);
		},
		
		onVersionChanged: function() {
			c.log('new version:', post.version);
		},
		
		onModified: function(modifiedFields) {
			c.log('detected unsaved modifications');
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
		}
	};
	
	$(function(){
		// track state
		post.trackState();

		// select/give focus to the body text area for new posts
		if (!post.name) {
			$('textarea[name=body]').get(0).select();
			setTimeout(function(){$('textarea[name=body]').get(0).select();}, 500);
		}
		
		// the #post anchor in the iframe might cause the browser to scroll
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
				<input type="text" class="dep-save transform-csv" name="tags" value="<?= implode(', ',array_map('h', $post->tags)) ?>" />
			</p>
		</div>
		<div class="component categories c3">
			<h4>Categories <small>(comma separated)</small></h4>
			<p>
				<input type="text" class="dep-save transform-csv" name="categories" value="<?= implode(', ',array_map('h', $post->categories)) ?>" />
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