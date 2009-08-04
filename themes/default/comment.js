function trim(s) {
	return s.replace(/(^[ \t\s\n\r]+|[ \t\s\n\r]+$)/g, '');
}

document.getElementById('comment-form').onsubmit = function(e) {
	function check_filled(id, default_value) {
		var elem = document.getElementById(id);
		if (!elem)
			return false;
		elem.value = trim(elem.value);
		if (elem.value == default_value || elem.value == '') {
			elem.select();
			return false;
		}
		return true;
	}
	if (!check_filled('comment-reply-message', ''))
		return false;
	if (!check_filled('comment-author-name', 'Name'))
		return false;
	if (!check_filled('comment-author-email', 'Email'))
		return false;
	return true;
}

// reply-to
var reply_to_comment = null;

function reply(comment_id) {
	reply_to_comment = document.getElementById('comment-'+comment_id);
	document.getElementById('comment-reply-to').value = comment_id;
}

var reply_to = document.getElementById('comment-reply-to');
var reply_to_lastval = "";
var form_parent = null;
var cancel_button = null;

reply_to.onchange = function(e) {
	reply_to.value = trim(reply_to.value);
	var title = document.getElementById('reply-title');
	var form = document.getElementById('comment-form');

	// remove any cancel button
	if (cancel_button != null) {
		if (cancel_button.parentNode)
			cancel_button.parentNode.removeChild(cancel_button);
		cancel_button = null;
	}

	if (reply_to.value != "") {
		if (reply_to_comment == null) {
			reply_to.value = "";
			return;
		}

		if (form_parent == null)
			form_parent = form.parentNode;

		cancel_button = document.createElement('input');
		cancel_button.setAttribute('type', 'button');
		cancel_button.setAttribute('value', 'Cancel');
		cancel_button.onclick = function(e) { document.getElementById('comment-reply-to').value = ""; };

		// find submit button and append the form to its parent
		var inputs = form.getElementsByTagName("input");
		for (var i=0; i<inputs.length; i++) {
			var elem = inputs.item(i);
			if (elem.getAttribute('type') == 'submit') {
				elem.parentNode.appendChild(cancel_button);
				break;
			}
		}

		form.className = "inline-reply";
		reply_to_comment.appendChild(form);
		title.style.display = 'none';

		//document.location.hash = "comment-"+reply_to.value;
	}
	else {
		form.className = "";
		if (form_parent != null)
			form_parent.appendChild(form);
		title.style.display = '';
		document.location.hash = "reply";
	}	
	setTimeout(function(){
		document.getElementById('comment-reply-message').select()
	},100);
}
setInterval(function(e){
	if (reply_to_lastval != reply_to.value)
		reply_to.onchange(e);
	reply_to_lastval = reply_to.value;
},200);

// select message on reply
setTimeout(function(){if (document.location.hash == '#reply')
	document.getElementById('comment-reply-message').select();
},100);
