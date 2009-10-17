<?
require_once '../_base.php';
gb::authenticate();
gb::$title[] = 'Settings';
include '../_header.php';

?>
<script type="text/javascript" charset="utf-8">//<![CDATA[
	var gb = {};
	
	gb.data = function(doc_id, data) {
		return new gb.RemoteDict(doc_id, data);
	};
	
	gb.RemoteDict = function(doc_id, data) {
		var self = this;
		this.restServiceURL = '../helpers/data.php/';
		this.docID = doc_id;
		this.data = (typeof data == 'object' && data) ? data : {};
		
		this.get = function(keypath, fallback) {
			var path = keypath.replace(/(^[ \t\r\n]+|[ \t\r\n]+$)/, '').split(/\/+/);
			if (path.length == 0 || typeof this.data != 'object')
				return fallback;
			var v = this.data[path[0]];
			if (path.length == 1)
				return v;
			for (var i=1; i<path.length; i++) {
				if (typeof v != 'object')
					return fallback;
				v = v[path[i]];
				if (typeof v == 'undefined')
					return fallback;
			}
			return v;
		}
		
		this.set = function(pairs_or_key, value_if_key) {
			var pairs = pairs_or_key;
			if (typeof pairs_or_key == 'string') {
				pairs = {};
				pairs[pairs_or_key] = value_if_key;
			}
			if (typeof pairs != 'object') {
				alert('pairs must be a dict');
				return;
			}
			if (!this.onSet(pairs))
				return;
			var empty = true;
			for (var k in pairs) { empty = false; break; }
			if (empty)
				return;
			return $.ajax({
				type: 'POST',
				url: this.restServiceURL+this.docID,
				data: $.toJSON(pairs, false),
				contentType: 'application/json',
				dataType: 'json',
				success: function(data, textStatus){ self._onData(data); },
				error: function(xhr, textStatus, errorThrown){ self._onRequestError(xhr, textStatus, errorThrown); },
				beforeSend: function(xhr) { self._onRequestStart(xhr); },
				complete: function(xhr, textStatus) { self._onRequestComplete(xhr, textStatus); }
			});
		}
		
		this.reload = function(callback) {
			return $.ajax({
				type: 'GET',
				url: this.restServiceURL+this.docID,
				dataType: 'json',
				success: function(data, textStatus){ self._onData(data); },
				error: function(xhr, textStatus, errorThrown){ self._onRequestError(xhr, textStatus, errorThrown); }
			});
		}
		
		this._onData = function(data) {
			this.data = data;
			this.onData();
		}
		
		this._onRequestStart = function(xhr) { this.onRequestStart(xhr); };
		this._onRequestComplete = function(xhr, textStatus) { this.onRequestComplete(xhr, textStatus); };
		this._onRequestError = function(xhr, textStatus, errorThrown) {
			this.onRequestError(xhr, textStatus, errorThrown);
		};
		
		// user event callbacks
		this.onSet = function(pairs) { return true; };
		this.onRequestStart = function(xhr) {};
		this.onRequestComplete = function(xhr, textStatus) {};
		this.onRequestError = function(xhr, textStatus, errorThrown) {
			console.log('gb.RemoteDict request error: '+textStatus+' '+errorThrown);
		};
		this.onData = function() {};
	};
	
	gb.UIThrobber = function(element, size) {
		this.element = $(element);
		this.hideWhileInactive = this.element.hasClass('hidden-while-inactive');
		this.timer = null;
		this.stack = [];
		this.size = (typeof size == 'undefined') ? 24 : size;
		this.segments = 12;
		this._x = 0;
		
		this.push = function(userdata) {
			this.stack.push(userdata);
			if (this.timer == null) {
				var self = this;
				var el = this.element.get(0);
				this.timer = setInterval(function(){
					if (self._x == (self.size*self.segments))
						self._x = 0;
					el.style.backgroundPosition = '-'+self._x+'px 0';
					self._x += self.size;
				}, 1000/15);
				if (this.hideWhileInactive) {
					// only show when more than 100 ms has passed
					this._hideWhileInactiveTimeout = setTimeout(function(){ self.element.show(); }, 50);
				}
				this.onStart();
			}
		}
		this.pop = function() {
			var userdata = this.stack.pop();
			if (this.stack.length < 1 && this.timer != null) {
				clearInterval(this.timer);
				this.timer = null;
				if (this.hideWhileInactive) {
					try{ clearInterval(this._hideWhileInactiveTimeout);}catch(e){}
					this.element.hide();
				}
				this.onStop();
			}
			return userdata;
		}
		this.draw = function() {
			this.element
		}
		this.onStart = function() {}
		this.onStop = function() {}
	}
	
	var settings = gb.data('admin', <?= gb::data('admin')->toJSON() ?>);
	var activeRequestCount = 0;
	var throbber = null;
	
	settings.onRequestStart = function(xhr) { throbber.push(); }
	settings.onRequestComplete = function(xhr, textStatus) { throbber.pop(); console.log(xhr.responseText); }
	settings.onData = function(delta) {
		console.log('settings updated =>', this.data);
	}
	settings.onSet = function(pairs) {
		// don't set stuff we believe is already set
		var p = pairs;
		for (var k in p)
			if (this.get(k) == p[k])
				delete pairs[k];
		
		// check custom "composing/default_mime_type"
		var default_mime_type = pairs["composing/default_mime_type"];
		if (typeof default_mime_type != 'undefined' && !default_mime_type.match(/[a-zA-Z0-9\.-]+/)) {
			$('#custom_default_mime_type').select();
			return false;
		}
		return pairs != {} ? true : false;
	};
	
	// inti
	$(function(){
		throbber = new gb.UIThrobber('#settings-throbber');
		
		// set active radio button for "composing/default_mime_type"
		var q = $('input[name=composing/default_mime_type]');
		var is_custom = true;
		var v = settings.get('composing/default_mime_type');
		q.filter('input[value=]').each(function(){this.checked = true;});
		q.filter('input[value='+v+']').each(function(){
			this.checked = true;
			is_custom = false;
		});
		if (is_custom)
			$('#custom_default_mime_type').each(function(){ this.value = v });
	});
//]]></script>
<div id="content" class="<?= gb_admin::$current_domid ?> form">
	<!-- title and save/commit controls -->
	<div class="section title">
		<div class="component title c2">
			<h2 style="margin:0">Settings</h2>
		</div>
		<div class="component controls">
			<div id="settings-throbber" class="throbber small hidden-while-inactive" title="Settings are being saved..."></div>
		</div>
		<div class="breaker"></div>
	</div>
	<div class="section">
		<div class="component">
			<h4>Default content type for new posts and pages</h4>
			<label class="inline">
				<input type="radio" name="composing/default_mime_type" value="text/html"
					onchange="if(this.checked) settings.set('composing/default_mime_type',this.value)" />
				HTML
			</label>
			<label class="inline">
				<input type="radio" name="composing/default_mime_type" value="text/x-markdown"
					onchange="if(this.checked) settings.set('composing/default_mime_type',this.value)" />
				Markdown
			</label>
			<label class="inline">
				<input type="radio" name="composing/default_mime_type" value=""
				 	onchange="if(this.checked) settings.set('composing/default_mime_type',$('#custom_default_mime_type').get(0).value)" />
				Custom MIME type:
				<input type="text" id="custom_default_mime_type" value=""
					onchange="settings.set('composing/default_mime_type',this.value)" />
			</label>
		</div>
		<div class="breaker"></div>
	</div>
</div>
<? include '../_footer.php' ?>
