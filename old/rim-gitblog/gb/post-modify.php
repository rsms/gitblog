<?
if ($_SERVER['HTTP_X_GB_SHARED_SECRET'] != 'xyz') {
	header('Status: 401 Unauthorized');
	exit('401 Unauthorized');
}
$udiff = file_get_contents("php://input");
var_dump($udiff);
?>