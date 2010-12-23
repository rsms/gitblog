<?php
require dirname(__FILE__).'/../gitblog.php';

$integrity = gb::verify_integrity();

if (strpos($_SERVER['SCRIPT_NAME'], '/admin/setup.php') === false) {
	if ($integrity === 2) {
		header('Location: '.gb_admin::$url.'setup.php');
		exit(0);
	}
	else {
		gb::verify_config();
	}
}

$admin_conf = gb::data('admin');

?>