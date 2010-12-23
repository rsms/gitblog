<?php
require_once '../_base.php';
gb::authenticate();
gb::$title[] = 'Settings';
include '../_header.php';

?>
<script type="text/javascript" charset="utf-8">//<![CDATA[
	var settings = gb.data('admin', <?php echo gb::data('admin')->toJSON() ?>);
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
	
	// init
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
		
		// Preview when composing
		$('input[name=composing/preview/enabled]').each(function() {
			this.checked = settings.get('composing/preview/enabled', true);
		});
		
	});
//]]></script>
<div id="content" class="<?php echo gb_admin::$current_domid ?> form">
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
	
	
	<div class="section">
		<div class="component">
			<h4>Live preview while editing posts and pages</h4>
			<label class="inline">
				<input type="checkbox" name="composing/preview/enabled" value="1"
					onchange="settings.set('composing/preview/enabled',this.checked)" />
				Enabled
			</label>
		</div>
		<div class="breaker"></div>
	</div>
</div>
<?php include '../_footer.php' ?>
