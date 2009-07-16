<?
require '../gitblog.php';

define('GITBLOG_ADMIN_URL', gb::$site_url.'/gitblog/admin/');

$integrity = gb::verifyIntegrity();
$errors = array();

if ($integrity === 2 && strpos($_SERVER['SCRIPT_NAME'], '/admin/setup.php') === false) {
	header("Location: ".gb::$site_url."gitblog/admin/setup.php");
	exit(0);
}

?>