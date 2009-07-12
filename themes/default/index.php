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
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
		<title><?= gb_title() ?></title>
		<link href="<?= GB_THEME_URL ?>style.css" type="text/css" rel="stylesheet" media="screen" />
	</head>
	<body>
		<?
		
		if (gb::$is_404) {
			echo '<h1>404 Not Found</h1>';
		}
		elseif (gb::$is_post || gb::$is_page) {
			require 'post.php';
		}
		elseif (gb::$is_posts) {
			require 'posts.php';
		}
		
		?>
		<address>
			(<? $s = (microtime(true)-$gb_time_started); printf('%.3f ms, %d rps', 1000.0 * $s, 1.0/$s) ?>)
		</address>
	</body>
</html>