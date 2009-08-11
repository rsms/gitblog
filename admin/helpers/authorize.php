<?
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
		gb_admin::$errors[] = 'No such user';
	}
	elseif ($authed === CHAP::BAD_RESPONSE) {
		gb_admin::$errors[] = 'Bad password';
	}
	else {
		gb_admin::$errors[] = 'Unknown error';
	}
}

$auth = gb::authenticator();
include '../_header.php';
?>
<script type="text/javascript" src="<?= gb_admin::$url ?>res/sha1-min.js"></script>
<script type="text/javascript">
	var chap = {
		submit: function(nonce, opaque, context) {
			if (typeof context == 'undefined')
				context = '';
			var username = document.getElementById('chap-username');
			var password = document.getElementById('chap-password');
			var shadow = hex_sha1(username.value+':'+context+':'+password.value);
			var a = hex_hmac_sha1(opaque, shadow);
			document.getElementById('chap-response').value = hex_hmac_sha1(nonce, a);
			password.value = '';
			return true;
		}
	};
</script>
<h2>Authorize</h2>
<form action="<?= gb::url() ?>" method="POST" 
	onsubmit="chap.submit('<?= $auth->nonce() ?>','<?= $auth->opaque() ?>','<?= $auth->context ?>')">
	<input type="hidden" id="chap-response" name="chap-response" value="" />
	<p>
		Username: <input type="text" id="chap-username" name="chap-username" 
			value="<?= isset($_REQUEST['chap-username']) ? $_REQUEST['chap-username'] : gb_author_cookie::get('email'); ?>" /><br />
		Password: <input type="password" id="chap-password" name="chap-password" />
	</p>
	<p>
		<input type="submit" value="Login" />
	</p>
</form>
<? include '../_footer.php' ?>