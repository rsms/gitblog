<?
require 'gb-config.php';
require 'gitblog/gitblog.php';

$urlpath = isset($_SERVER['PATH_INFO']) ? trim($_SERVER['PATH_INFO'], '/') : '';

if ($urlpath) {
	if (strpos($urlpath, $gb_config['tags-prefix']) === 0) {
		# tag(s)
		$tags = explode(',', substr($urlpath, strlen($gb_config['tags-prefix'])));
		$posts = $gitblog->postsByTags($tags);
		include $gitblog->pathToTheme('tags.php');
	}
	elseif (strpos($urlpath, $gb_config['categories-prefix']) === 0) {
		# category(ies)
		$cats = explode(',', substr($urlpath, strlen($gb_config['categories-prefix'])));
		$posts = $gitblog->postsByCategories($cats);
		include $gitblog->pathToTheme('categories.php');
	}
	elseif (preg_match($gb_config['posts']['slug-prefix-re'], $urlpath)) {
		# post
		$post = $gitblog->postBySlug($urlpath);
		include $gitblog->pathToTheme('post.php');
	}
	else {
		# page
		$post = $gitblog->pageBySlug($urlpath);
		include $gitblog->pathToTheme('page.php');
	}
}
else {
	# posts
	$pageno = isset($_REQUEST['page']) ? intval($_REQUEST['page']) : 0;
	$path = $gitblog->pathToPostsPage($pageno);
	$page = @unserialize(file_get_contents($path));
	include $gitblog->pathToTheme('posts.php');
}
?>