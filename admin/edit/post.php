<?
require '../_base.php';
gb::authenticate();
gb::$title[] = 'New post';

# array( string name => filterspec , .. )
$fields = array(
	'name' => FILTER_REQUIRE_SCALAR,
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
if ($q['name']) {
	if (!($post = GBPost::findByName($q['name'], $q['version'])))
		gb::$errors[] = 'No post could be found at path '.r($q['name']);
}
elseif ($q['uri']) {
	$q['uri'] = ltrim($q['uri'], '/');
	if (!($post = GBPost::find($q['uri'], $q['version'])))
		gb::$errors[] = 'No post could be found for URI '.r($q['uri']);
}

# no post found or new post
if (!$post) {
	$post = new GBPost();
	$post->published = new GBDateTime();
	$post->author = gb::$authorized;
	$post->mimeType = $admin_conf->get('composing/default_mime_type', 'text/html');
}

include '../_header.php';
?>
<script type="text/javascript" charset="utf-8">//<![CDATA[
	var post = {
		savedState: {},
		currentState: {},
		isModified: false,
		
		checkStateTimer: null,
		checkStateInterval: 10000,
		
		autoSaveTimer: null,
		autoSaveLatency: 1000,
		autoSaveEnabled: true, // setting
		_autoSaveEnabled: true, // runtime
		
		checkModifiedQueue: {},
		checkModifiedLatency: 100,
		checkModifiedAdjustThreshold: 50, // if checkTracked run faster then this, adjust checkModifiedLatency accordingly.
		
		exists: <?= $post->exists() ? 'true' : 'false' ?>,
		isTracked: <?= $post->isTracked() ? 'true' : 'false' ?>,
		isDirty: <?= $post->isDirty() ? 'true' : 'false' ?>,
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
					j.keyup(function(ev){ post.queueCheckTracked(this); });
					j.keydown(function(ev){ post.delayAutoSaveTimer(); });
				}
			});
		},
		
		fieldGetFilters: {},
		fieldPutFilters: {},
		
		standardFilters: {	
			csv_to_list: function(s){
				if (typeof s != 'string')
					return [];
				s = s.replace(/(^[ \t\s\n\r,]+|[ \t\s\n\r,]+$)/g, '');
				if (s == '')
					return [];
				return s.split(/,[ \s\t]*/);
			},
			list_to_csv: function(l){ return l.join(', '); },
			empty_to_null: function(s){ return s.length ? s : null; },
			null_to_empty: function(s){ return s == null ? "" : s; }
		},
		
		setupFieldFilters: function() {
			// comma separated values <--> list of strings
			$('.transform-csv').each(function(i){
				var t = post.getField(this, false);
				post.fieldGetFilters[t.name] = post.standardFilters.csv_to_list;
				post.fieldPutFilters[t.name] = post.standardFilters.list_to_csv;
			});
			// empty means null
			$('.transform-null').each(function(i){
				var t = post.getField(this, false);
				post.fieldGetFilters[t.name] = post.standardFilters.empty_to_null;
				post.fieldPutFilters[t.name] = post.standardFilters.null_to_empty;
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
			var e = j.length ? j.get(0) : null;
			p.haveValue = typeof e.value != 'undefined';
			p.haveChecked = (typeof p.type != 'undefined' && p.type == 'checkbox');//typeof e.checked != 'undefined';
			return p;
		},
		
		putField: function(name, value, applyFilters) {
			if (typeof applyFilters == 'undefined' || applyFilters)
				value = post.applyFilters(name, value, post.fieldPutFilters);
			var j = $('*[name='+name+']');
			if (j.length == 0) {
				c.log('putField for non-existing field', name);
				return;
			}
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
			el = $(el);
			var p = post.investigateField(el);
			el = el.get(0);
			var t = {name: null, value: null};
			if (typeof el.name != 'undefined') {
				t.name = el.name;
				if (p.haveChecked)
					t.value = el.checked;
				else if (p.haveValue)
					t.value = el.value;
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
			return post.save(false);
		},
		
		save: function(commit, params) {
			post.stopAutoSaveTimer();
			
			if (typeof commit == 'undefined')
				commit = false;
			
			if (typeof params == 'undefined') {
				var modified = post.findModified();
				if (modified[0] == 0 && !commit)
					return false;
				params = modified[1];
			}
			
			if (commit)
				params.commit = true;
			
			if (post.name)
				params.name = post.name;
			
			post.setSaveButton('Saving', false);
			
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
				return post.onSaveDidFail(null);
			}
			
			// transfer updated data
			var was_modified = false;
			if (typeof rsp == 'object') {
				// spec changed?
				var spec = {
					'exists':post.onExistsChanged,
					'isTracked':post.onIsTrackedChanged,
					'isDirty':post.onIsDirtyChanged,
					'name':post.onNameChanged,
					'version':post.onVersionChanged,
				};
				for (var k in spec) {
					if (typeof rsp[k] != 'undefined' && rsp[k] != post[k]) {
						var oldval = post[k];
						post[k] = rsp[k];
						if (typeof spec[k] == 'function')
							spec[k](oldval);
					}
				}
				c.log($('*[name=body]'));
				// fields
				if (typeof rsp.state == 'object') {
					for (var name in rsp.state) {
						var value = rsp.state[name];
						if (typeof post.savedState[name] == 'undefined' || !post.eq(post.savedState[name], value)) {
							// todo: if changed remotely, merge in changes here: post.putField(name, value);
							post.savedState[name] = value;
							post.currentState[name] = value;
							was_modified = true;
							c.log(name+' was saved');
						}
					}
				}
			}
			
			// tidy up
			post._autoSaveEnabled = post.autoSaveEnabled;
			post.setSaveButton('Saved', false);
			
			// no longer modified
			if (post.isModified)
				post.onNotModified();
			
			// reload iframe
			if (was_modified) {
				$('#preview iframe').each(function(i){
					var yoffset = $(this.contentDocument.documentElement.getElementsByTagName('body').item(0)).scrollTop();
					c.log('reloading preview');
					$(this).one('load', function(){
						$(this.contentDocument.documentElement.getElementsByTagName('body').item(0)).scrollTop(yoffset);
					});
					this.contentDocument.location.hash = null;
					this.contentDocument.location.search = 
						'?preview='+escape((new Date()).toUTCString())+
						'&version='+post.version;
				});
			}
		},
		
		onSaveDidFail: function(req, type, exc, params) {
			var obj = null;
			try {
				obj = eval('('+req.responseText+')');
			}catch(e){}
			msg = $.trim(String(obj ? obj : (req ? req.responseText : '')));
			if (msg.length == 0)
				msg = 'Unspecified remote error when trying to save post.';
			if (post._autoSaveEnabled) {
				c.log('disabling auto-save');
				msg += "\n\n -- disabling auto-save";
				post._autoSaveEnabled = false;
			}
			ui.alert(msg);
			if (req)
				c.log('failed to save post', req.status, req.statusText, req.responseText, type, exc);
			else
				c.log('failed to save post', type, exc);
			if (!post.isModified)
				post.onModified();
			post.setSaveButton('Save', true);
		},
		
		findModified: function() {
			var modified = {};
			var count = 0;
			for (var name in post.currentState) {
				if (post.savedState[name] != post.currentState[name]) {
					modified[name] = post.currentState[name];
					count++;
				}
			}
			return [count, modified];
		},
		
		queueCheckTracked: function(el) {
			post.delayAutoSaveTimer();
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
			
			if (modified)
				post.currentState[t.name] = t.value;
			
			if (modified && !post.isModified) {
				post.onModified([t.name]);
			}
			else if (!modified && post.isModified) {
				if (post.findModified()[0] == 0)
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
		
		setSaveButton: function(label, enabled) {
			var j = $('input.save');
			var label_transl = j.data('_'+label);
			if (typeof label_transl != 'undefined')
				label = label_transl;
			if (typeof enabled != 'undefined') {
				if (enabled)
					j.removeClass('disabled').removeAttr('disabled');
				else
					j.addClass('disabled').attr('disabled', 'disabled');
			}
			j.attr('value', label);
		},
		
		setCommitButton: function(label, enabled) {
			var j = $('input.commit');
			if (label) {
				var label_transl = j.data('_'+label);
				if (typeof label_transl != 'undefined')
					label = label_transl;
				j.attr('value', label);
			}
			if (typeof enabled != 'undefined') {
				if (enabled)
					j.removeClass('disabled').removeAttr('disabled');
				else
					j.addClass('disabled').attr('disabled', 'disabled');
			}
		},
		
		loadPost: function(name, version) {
			document.location.search = '?name='+name+'&version='+version;
		},
		
		setCommitButtonAccordingToTrackedState: function() {
			if (post.isTracked) {
				$('#commit-button').css('display', 'inline-block');
				$('#publish-button').css('display', 'none');
				$('input.discard').css('display', 'none');
			}
			else {
				$('#commit-button').css('display', 'none');
				$('#publish-button').css('display', 'inline-block');
				$('input.discard').css('display', 'inline-block');
			}
		},
		
		setSaveButtonAccordingToExistingState: function() {
			var j = $('input.save');
			if (post.exists) {
				j.removeData('_Save');
				j.removeData('_Saving...');
			}
			else {
				j.data('_Save', 'Create');
				j.data('_Saved', 'Create');
				j.data('_Saving...', 'Creating...');
			}
			if (post.isModified)
				post.setSaveButton('Save');
			else
				post.setSaveButton('Saved');
		},
		
		onIsTrackedChanged: function(oldval) {
			post.setCommitButtonAccordingToTrackedState();
		},
		
		onIsDirtyChanged: function(oldval) {
			post.setCommitButton(null, post.isDirty);
		},
		
		onExistsChanged: function(oldval) {
			post.setSaveButtonAccordingToExistingState();
		},
		
		onNameChanged: function(oldval) {
			c.log('new name:', post.name);
			post.loadPost(post.name, post.version);
		},
		
		onVersionChanged: function(oldval) {
			c.log('new version:', post.version);
			post.loadPost(post.name, post.version);
		},
		
		onModified: function(modifiedFields) {
			c.log('detected unsaved modifications', modifiedFields);
			post.isModified = true;
			post.setSaveButton('Save', true);
			post.resetCheckStateTimer();
			post.startAutoSaveTimer();
		},
		
		onNotModified: function() {
			post.stopAutoSaveTimer();
			post.isModified = false;
			post.resetCheckStateTimer();
		}
	};
	
	$(function(){
		// track state
		post.trackState();
		post.setCommitButtonAccordingToTrackedState();
		post.setSaveButtonAccordingToExistingState();

		// select/give focus to the body text area for new posts
		if (!post.name) {
			$('textarea[name=body]').get(0).select();
			setTimeout(function(){$('textarea[name=body]').get(0).select();}, 500);
		}
		
		// the #post anchor in the iframe might cause the browser to scroll
		// down. We reset scrolling after the first load.
		$('#preview iframe').one('load', function(){ window.scrollTo(0,0); });
		
		// bind ui
		post.setSaveButton('Saved', false);
		post.setCommitButton(null, post.isDirty);
		$('input.save').click(function(){ post.save(false); });
		$('input.commit').click(function(){ post.save(true); });
	});
//]]></script>
<div id="content" class="<?= gb_admin::$current_domid ?> form">
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
				<input type="button" class="discard" value="Discard"
				 	<?= $post->isTracked() ? 'style="display:none"' : '' ?> />
				<input type="button" class="save" value="Save" />
				<input type="button" id="commit-button" class="commit" value="Commit"
					title="Execute git commit and push changes live"
					<?= $post->isTracked() ? '' : 'style="display:none"' ?> />
				<input type="button" id="publish-button" class="commit publish" value="Publish"
					title="Make this post appear on your site (after the time specified by 'Publish date')."
					<?= $post->isTracked() ? 'style="display:none"' : '' ?> />
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
				<input type="text" name="published" class="dep-save transform-null" value="<?= h($post->published) ?>" />
			</p>
		</div>
		<div class="component slug c3">
			<h4>URL slug</h4>
			<p>
				<input type="text" name="slug" class="dep-save transform-null" value="<?= h($post->slug) ?>" />
			</p>
		</div>
		<div class="component slug c3">
			<h4>Author</h4>
			<p>
				<input type="text" name="author" class="dep-save transform-null" value="<?= h($post->author) ?>" />
			</p>
		</div>
		<div class="breaker"></div>
	</div>
	<!-- options -->
	<div class="section">
		<div class="component c2a">
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
		<div class="component categories c3" title="Type of content. Leave unchanged if you are unsure about it">
			<h4>Content type</h4>
			<p>
				<input type="text" name="mimeType" class="dep-save" 
					value="<?= h(isset($post->meta['content-type']) && $post->meta['content-type'] ? $post->meta['content-type'] : $post->mimeType) ?>" />
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