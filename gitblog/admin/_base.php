<?
@include '../../gb-config.php';
require '../gitblog.php';

$integrity = $gitblog->verifyIntegrity();
$errors = array();
#$gb_title[] = 'Admin';

?>