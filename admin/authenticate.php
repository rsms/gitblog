<?
require '_base.php';
gb::verify();
$authed = gb::authenticate(false);

if ($authed) {
	gb::log('client authorized: '.$authed);
	gb_author_cookie::set($authed->email, $authed->name, gb::$site_url);
	gb::event('client-authorized', $authed);
	$url = ((isset($_POST['referrer']) && $_POST['referrer']) 
		? $_POST['referrer'] : GITBLOG_ADMIN_URL);
	header('HTTP/1.1 303 See Other');
	header('Location: '.$url);
	exit('<html><body>See Other <a href="'.$url.'"></a></body></html>');
}

if ($authed === CHAP::BAD_USER) {
	$errors[] = 'No such user';
}
elseif ($authed === CHAP::BAD_RESPONSE) {
	$errors[] = 'Bad password';
}

$auth = gb::authenticator();
include '_header.php';
?>
<script type="text/javascript" src="sha1-min.js"></script>
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
<h2>Authenticate</h2>
<form action="authenticate.php" method="POST" 
	onsubmit="chap.submit('<?= $auth->nonce() ?>','<?= $auth->opaque() ?>','<?= $auth->context ?>')">
	<input type="hidden" id="chap-response" name="chap-response" value="" />
	<input type="hidden" name="referrer" value="<?= isset($_REQUEST['referrer']) ? h($_REQUEST['referrer']) : '' ?>" />
	<p>
		Username: <input type="text" id="chap-username" name="chap-username" 
			value="<?= isset($_REQUEST['chap-username']) ? $_REQUEST['chap-username'] : gb_author_cookie::get('email'); ?>" /><br />
		Password: <input type="password" id="chap-password" name="chap-password" />
	</p>
	<p>
		<input type="submit" value="Login" />
	</p>
</form>
<? include '_footer.php' ?>