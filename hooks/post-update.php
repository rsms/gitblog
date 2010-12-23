<?php
require '../gitblog.php';
ini_set('html_errors', '0');

gb::verify();

if (!isset($_SERVER['HTTP_X_GB_SHARED_SECRET']) || $_SERVER['HTTP_X_GB_SHARED_SECRET'] !== gb::$secret) {
	header('HTTP/1.1 401 Unauthorized');
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