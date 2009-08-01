<?
require '../gitblog.php';

define('GITBLOG_ADMIN_URL', gb::$site_url.'gitblog/admin/');

$integrity = gb::verify_integrity();
$errors = array();

# do NOT call gb::verify_config() here (becase of setup.php)

if ($integrity === 2 && strpos($_SERVER['SCRIPT_NAME'], '/admin/setup.php') === false) {
	header("Location: ".gb::$site_url."gitblog/admin/setup.php");
	exit(0);
}

?>