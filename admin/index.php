<?php
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
		<li><a href="edit/post.php">Write a new post</a></li>
		<li><a href="manage/posts.php">Manage posts</a></li>
		<li><a href="maintenance/rebuild.php">Rebuild cache</a></li>
		<li><a href="maintenance/import-wordpress.php">Import a Wordpress blog</a></li>
	</ul>
</div>
<?php include '_footer.php' ?>