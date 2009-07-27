<?
require_once '_base.php';
gb::authenticate();
gb::$title[] = 'Admin';
include '_header.php';
?>
<h2>Administration</h2>
<p>
	Authenticated as <?= h(gb::$authorized) ?>
</p>
<h3>Fancy menu</h3>
<ul>
	<li><a href="import-wordpress.php">Import a Wordpress blog</a></li>
	<li><a href="rebuild.php">Rebuild cache</a></li>
	<li><a href="deauthorize.php">Log out / Deauthorize</a></li>
</ul>
<? include '_footer.php' ?>