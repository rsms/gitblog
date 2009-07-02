<?
require '../gitblog.php';

GitBlog::verifyConfig();

if ($_SERVER['HTTP_X_GB_SHARED_SECRET'] != gb::$secret) {
	header('Status: 401 Unauthorized');
	exit('401 Unauthorized');
}

GBRebuilder::rebuild(!isset($_REQUEST['force-full-rebuild']));
?>