<?
$gb_handle_request = true;
require './gitblog/gitblog.php';

# context_date is used on top-right to display the "freshness" of the current content
$context_date = time();

header('Content-Type: application/xhtml+xml; charset=utf-8');
if (gb::$is_404)
	header('HTTP/1.1 404 Not Found');
elseif (gb::$is_post || gb::$is_page)
	$context_date = $post->published->time;
elseif ((gb::$is_posts || gb::$is_tags || gb::$is_categories) && $postspage->posts) {
	if (is_object($postspage->posts)) {
		# workaround for picking up first item from an iterator
		foreach ($postspage->posts as $p) {
			$context_date = $p->published->time;
			break;
		}
	}
	else
		$context_date = $postspage->posts[0]->published->time;
}

?><!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
	<head>
		<meta http-equiv="Content-Type" content="application/xhtml+xml; charset=utf-8"/>
		<meta http-equiv="imagetoolbar" content="no" />
		<meta name="viewport" content="width=940" />
		<title><?= gb_title() ?></title>
		<link rel="stylesheet" type="text/css" href="<?= gb::$theme_url ?>style.css" media="screen" />
		<link rel="alternate" type="application/atom+xml" href="<?= h(gb::url_to('feed')) ?>" title="Atom feed" />
		<? gb_head() ?>
	</head>
	<? gb_flush() ?>
	<body>
		<? if (gb::$errors): ?>
		<div id="gb-errors">
			<div class="wrapper">
				<a class="close" 
					href="javascript:document.getElementById('gb-errors').style.display='none'"
					title="Hide this message"><span>X</span></a>
				<div class="icon"></div>
				<ul>
					<li class="title">
						<?= count(gb::$errors) === 1 ? 'An error occured' : counted(count(gb::$errors), '','errors occured') ?>
					</li>
				<? foreach (gb::$errors as $error): ?>
					<li><?= h($error) ?></li>
				<? endforeach ?>
				</ul>
			</div>
		</div>
		<? endif ?>
		<div id="header">
			<div class="wrapper">
				<div class="date" title="Date published"><?= gmdate('F j, Y', $context_date) ?></div>
				<div class="title">
					<div class="name"><?= gb_site_title() ?></div>
					<div class="description"><?= h(gb::$site_description) ?></div>
				</div>
				<ul>
					<li><a href="<?= gb::url_to() ?>" <? if (gb::$is_posts) echo 'class="current"' ?>>Home</a></li>
					<!--li>
						<a href="<?= gb::url_to('/archive/') ?>" 
							<? if (strpos(gb::url()->path, '/archive/')!==false) echo 'class="current"' ?>>Archive</a>
					</li-->
					<li class="divider"></li>
					<? foreach (gb::index('pages') as $page): if ($page->hidden) continue; ?>
						<li class="page">
							<a href="<?= h($page->url()) ?>" <?= $page->isCurrent() ? 'class="current"':'' ?>><?= h($page->title) ?></a>
						</li>
					<? endforeach ?>
					<li class="divider"></li>
					<? foreach (gb::categories() as $name => $objnames): ?>
						<li class="category">
							<a href="<?= gb::url_to('categories') . h($name) ?>"
								<?= (gb::$is_categories && in_array($name, $categories)) ? 'class="current"':'' ?>
								><?= h(ucfirst($name)) ?></a>
						</li>
					<? endforeach ?>
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
					The page <b><?= h(gb::url()->toString(false)) ?></b> does not exist.
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
		<? gb_footer() ?>
	</body>
</html>
