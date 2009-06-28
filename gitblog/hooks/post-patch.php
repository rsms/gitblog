<?
require '../../gb-config.php';
require '../gitblog.php';

$gitblog->verifyConfig();

if ($_SERVER['HTTP_X_GB_SHARED_SECRET'] != $gb_config['secret']) {
	header('Status: 401 Unauthorized');
	exit('401 Unauthorized');
}

GBRebuilder::rebuild($gitblog, isset($_REQUEST['force-full-rebuild']));
?>