<?php
require '../_base.php';
gb::verify();
$authed = gb::authenticate(false);

if ($authed) {
	gb::log('client authorized: '.$authed);
	gb_author_cookie::set($authed->email, $authed->name, gb::$site_url);
	gb::event('client-authorized', $authed);
	$url = ((isset($_REQUEST['referrer']) && $_REQUEST['referrer']) ? $_REQUEST['referrer'] : gb_admin::$url);
	header('HTTP/1.1 303 See Other');
	header('Location: '.$url);
	exit('<html><body>See Other <a href="'.$url.'"></a></body></html>');
}

if (isset($_POST['chap-username'])) {
	if ($authed === CHAP::BAD_USER) {
		gb::$errors[] = 'No such user';
	}
	elseif ($authed === CHAP::BAD_RESPONSE) {
		gb::$errors[] = 'Bad password';
	}
	else {
		gb::$errors[] = 'Unknown error';
	}
}

$auth = gb::authenticator();
include '../_header.php';
?>
<script type="text/javascript" src="<?php echo gb_admin::$url ?>res/sha1-min.js"></script>
<script type="text/javascript">
	//<![CDATA[
	var chap = {
		submit: function(nonce, opaque, context) {
			if (typeof context == 'undefined')
				context = '';
			var username = $('#chap-username').get(0);
			var password = $('#chap-password').get(0);
			var shadow = hex_sha1(username.value+':'+context+':'+password.value);
			var a = hex_hmac_sha1(opaque, shadow);
			document.getElementById('chap-response').value = hex_hmac_sha1(nonce, a);
			password.value = '';
			return true;
		}
	};
	$(function(){
		// give username or password field focus when dom has loaded
		var username = $('#chap-username').get(0);
		var password = $('#chap-password').get(0);
		if (username.value.length)
			password.select();
		else
			username.select();
	});
	//]]>
</script>
<div id="content" class="authorize margins">
	<h2>Authorize</h2>
	<form action="<?php echo gb::url() ?>" method="post" 
		onsubmit="chap.submit('<?php echo $auth->nonce() ?>','<?php echo $auth->opaque() ?>','<?php echo $auth->context ?>')">
		<div>
			<input type="hidden" id="chap-response" name="chap-response" value="" />
		</div>
		<p>
			Username: <input type="text" id="chap-username" name="chap-username" 
				value="<?php echo isset($_REQUEST['chap-username']) ? $_REQUEST['chap-username'] : gb_author_cookie::get('email'); ?>" /><br />
			Password: <input type="password" id="chap-password" name="chap-password" />
		</p>
		<p>
			<input type="submit" value="Login" />
		</p>
	</form>
</div>
<?php include '../_footer.php' ?>