<?
#header('Content-Type: text/plain; charset=utf-8');
header('Content-Type: application/xhtml+xml; charset=utf-8');
if ($gb_is_404)
	header('Status: 404 Not Found');

?><!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
		<title>Some blog</title>
		<style type="text/css" media="screen">
			body { font-family:sans-serif; }
			#post-meta {  font-size:80%; background:#ddd; padding:10px; }
			#post-meta ul { list-style: none; padding:5px 10px;}
			.breaker { clear:both; }
		</style>
	</head>
	<body>
		<?
		
		if ($gb_is_404) {
			echo '<h1>404 Not Found</h1>';
		}
		elseif ($gb_is_post or $gb_is_page) {
			require 'post.php';
		}
		elseif ($gb_is_posts) {
			require 'posts.php';
		}
		
		?>
		<address>
			(<? $s = (microtime(true)-$debug_time_started); printf('%d ms, %d rps', intval(1000.0 * $s), 1.0/$s) ?>)
		</address>
	</body>
</html>