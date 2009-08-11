<?
require_once '_base.php';
gb::authenticate();
gb::$title[] = 'Admin';
include '_header.php';
?>
<div id="content" class="margins dashboard">
	<h2>Dashboard</h2>
	<p>
		This is work in progress
	</p>
	<h3>Common tasks</h3>
	<ul>
		<li><a href="import-wordpress.php">Import a Wordpress blog</a></li>
		<li><a href="rebuild.php">Rebuild cache</a></li>
		<li><a href="helpers/deauthorize.php">Log out</a></li>
	</ul>
</div>
<? include '_footer.php' ?>