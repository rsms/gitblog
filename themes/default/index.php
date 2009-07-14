<?
$gb_handle_request = true;
require 'gitblog/gitblog.php';

if (gb::$is_feed) {
	require 'feed.php';
	exit(0);
}

header('Content-Type: application/xhtml+xml; charset=utf-8');
if (gb::$is_404)
	header('Status: 404 Not Found');

?><!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
	<head>
		<meta http-equiv="Content-Type" content="application/xhtml+xml; charset=utf-8"/>
		<title><?= gb_title() ?></title>
		<link href="<?= GB_THEME_URL ?>style.css" type="text/css" rel="stylesheet" media="screen" />
		<link href="<?= h(gb::url_to('feed')) ?>" rel="alternate" title="Atom feed" type="application/atom+xml" />
	</head>
	<? flush() ?>
	<body>
		<div id="header">
			<div class="wrapper">
				<h1><?= gb_site_title() ?></h1>
				<ul>
					<li><a href="<?= GBPage::urlTo('about') ?>">About</a></li>
						<li><a href="<?= GB_SITE_URL ?>" class="current">Recent entries</a></li>
				</ul>
			</div>
		</div>
		<div id="main">
		<?
		
		if (gb::$is_404) {
			echo '<h1>404 Not Found</h1>';
		}
		elseif (gb::$is_post || gb::$is_page) {
			require 'post.php';
		}
		elseif (gb::$is_posts || gb::$is_tags || gb::$is_categories) {
			require 'posts.php';
		}
		
		?>
		<?/* Example of a tag cloud:
		<ol id="tags">
		<? foreach (GitBlog::tags() as $tag => $popularity): ?>
			<li class="p<?= intval(round($popularity * 10.0)) ?>"><?= gb_tag_link($tag) ?></li>
		<? endforeach; ?>
		</ol>
		*/?>
		</div>
		<address>
			<div class="wrapper">
				<? printf('%.1f ms', 1000.0 * (microtime(true)-$gb_time_started)) ?>
				was no match for <a href="">Gitblog <?= GB_VERSION ?></a>
			</div>
		</address>
	</body>
</html>