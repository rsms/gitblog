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
<h3>Fancy menu</h3>
<p>
	<a href="import-wordpress.php">Import a Wordpress blog</a>
</p>
<? include '_footer.php' ?>