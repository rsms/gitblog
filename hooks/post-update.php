<?
require '../gitblog.php';
ini_set('html_errors', '0');

set_error_handler(array('gb', 'catch_error'), E_ALL & ~E_NOTICE);
gb::verify_config();

if (!isset($_SERVER['HTTP_X_GB_SHARED_SECRET']) || $_SERVER['HTTP_X_GB_SHARED_SECRET'] !== gb::$secret) {
	header('Status: 401 Unauthorized');
	exit('error: 401 Unauthorized');
}
try {
	GBRebuilder::rebuild(isset($_REQUEST['force-full-rebuild']));
}
catch (Exception $e) {
	echo 'error:';
	throw $e;
}
?>