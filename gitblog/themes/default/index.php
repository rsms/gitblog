<?
if (gb::$is_feed) {
	require 'feed.php';
	exit(0);
}

header('Content-Type: application/xhtml+xml; charset=utf-8');
if (gb::$is_404)
	header('Status: 404 Not Found');
elseif ((gb::$is_post || gb::$is_page) && $post->commentsOpen)
	session_start(); # for nonces

?><!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
		<title><?= gb_title() ?></title>
		<style type="text/css" media="screen">
			body { font-family:sans-serif; }
			#post-meta {  font-size:80%; background:#ddd; padding:10px; }
			#post-meta ul { list-style: none; padding:5px 10px;}
			.breaker { clear:both; }
			
			/* Comments */
			#comments { border-top:1px solid #ccc; }
			#comments ul { list-style:none; margin:0; padding:0 30px; }
			li.comment { width:500px; margin:0; }
			li.comment > img.avatar { display:block; float:left; }
			li.comment > div { float:left; margin:0 0 10px 10px; }
			li.comment p { margin:0; }
		</style>
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
			(<? $s = (microtime(true)-$debug_time_started); printf('%.3f ms, %d rps', 1000.0 * $s, 1.0/$s) ?>)
		</address>
	</body>
</html>