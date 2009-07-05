<?
require '../gitblog.php';

define('GITBLOG_ADMIN_URL', GITBLOG_SITE_URL.'/gitblog/admin/');

$integrity = GitBlog::verifyIntegrity();
$errors = array();

if ($integrity === 2 && strpos($_SERVER['SCRIPT_NAME'], '/admin/setup.php') === false) {
	header("Location: ".GITBLOG_SITE_URL."gitblog/admin/setup.php");
	exit(0);
}

?>