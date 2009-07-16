<?
$gb_handle_request = true;
require './gitblog/gitblog.php';

header('Content-Type: application/xhtml+xml; charset=utf-8');
if (gb::$is_404)
	header('Status: 404 Not Found');

?><!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
	<head>
		<meta http-equiv="Content-Type" content="application/xhtml+xml; charset=utf-8"/>
		<title><?= gb_title() ?></title>
		<link href="<?= gb::$theme_url ?>style.css" type="text/css" rel="stylesheet" media="screen" />
		<link href="<?= h(gb::url_to('feed')) ?>" rel="alternate" title="Atom feed" type="application/atom+xml" />
	</head>
	<? flush() ?>
	<body>
		<div id="header">
			<div class="wrapper">
				<h1><?= gb_site_title() ?></h1>
				<ul>
					<? $c=null; foreach (GBObjectIndex::loadNamed('pages') as $slug => $page): if ($page->hidden) continue; ?>
						<li><a href="<?= h($page->url()) ?>" <? if ($page->isCurrent()){ $c=1; echo 'class="current"'; } ?>><?= h($page->title) ?></a></li>
					<? endforeach; ?>
					<li><a href="<?= gb::$site_url ?>" <? if (!$c) echo 'class="current"' ?>>Recent entries</a></li>
				</ul>
			</div>
		</div>
		<div id="main">
		<?
		
		if (gb::$is_404) {
			?>
			<div id="error404">
				<div class="wrapper">
					<h1>404 Not Found</h1>
					The page <b><?= h(gb::url()->__toString(false)) ?></b> does not exist.
				</div>
			</div>
			<?
		}
		elseif (gb::$is_post || gb::$is_page) {
			require gb::$theme_dir.'/post.php';
		}
		elseif (gb::$is_posts || gb::$is_tags || gb::$is_categories) {
			require gb::$theme_dir.'/posts.php';
		}
		
		?>
		</div>
		<address>
			<div class="wrapper">
				<? printf('%.1f ms', 1000.0 * (microtime(true)-$gb_time_started)) ?>
				was no match for <a href="http://gitblog.se/">Gitblog <?= gb::$version ?></a>
			</div>
		</address>
	</body>
</html>