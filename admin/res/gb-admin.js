if(typeof console=='undefined')console={};
if(typeof console.log=='undefined')console.log=function(){};
var c = console;
var gb = {};

var ui = {
	alert: function(msg) {
		var ul = $('#gb-errors ul');
		ul.html('');
		ul.append('<li class="title">An error occured</li>');
		ul.append('<li class="msg"></li>');
		$('#gb-errors ul li.msg').text(msg);
		$('#gb-errors').slideDown("fast");
	},
	
	hideAlert: function() {
		$('#gb-errors').slideUp("fast");
	}
};

var http = {
	postForm: function(kw) {
		kw.data = http.toFormParams(kw.data);
		return http.post(kw);
	},
	
	post: function(kw) {
		kw.type = "post";
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

/** JS equivalent of gb::data() */
gb.data = function(doc_id, default_data) {
	return new gb.RemoteDict(doc_id, default_data);
};

/** JS equivalent of the JSONDict */
gb.RemoteDict = function(doc_id, default_data) {
	var self = this;
	this.restServiceURL = '../helpers/data.php/';
	this.docID = doc_id;
	this.data = (typeof default_data == 'object' && default_data) ? default_data : {};
	
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

/** Throbber */
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