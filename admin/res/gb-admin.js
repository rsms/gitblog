if(typeof console=='undefined')console={};
if(typeof console.log=='undefined')console.log=function(){};
var c = console;

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
