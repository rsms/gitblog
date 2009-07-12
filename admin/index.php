<?
require_once '_base.php';
gb::authenticate();
gb::$title[] = 'Admin';
include '_header.php';
?>
<h2>Administration</h2>
<p>
	Authenticated as <?= h(gb::$authenticated) ?>
</p>
<? include '_footer.php' ?>