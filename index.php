<?
require 'gb-config.php';
require 'gitblog/gitblog.php';

$urlpath = isset($_SERVER['PATH_INFO']) ? trim($_SERVER['PATH_INFO'], '/') : '';

if ($urlpath) {
	if (strpos($urlpath, 'gitblog/') === 0) {
		if ($_SERVER['HTTP_X_GB_SHARED_SECRET'] != $gb_config['secret']) {
			header('Status: 401 Unauthorized');
			exit('401 Unauthorized');
		}
		$urlpath = rtrim(substr($urlpath, 8), '/');
		if (strpos($urlpath, 'hooks/') === 0) {
			require GITBLOG_DIR.'/hooks/'.str_replace('..','',substr($urlpath, 6)).'.php';
		}
		else {
			exit('unknown gitblog action '.var_export($urlpath,1));
		}
	}
	elseif (strpos($urlpath, $gb_config['tags-prefix']) === 0) {
		# tag(s)
		$tags = explode(',', substr($urlpath, strlen($gb_config['tags-prefix'])));
		$posts = $gitblog->postsByTags($tags);
		require $gitblog->pathToTheme('tags.php');
	}
	elseif (strpos($urlpath, $gb_config['categories-prefix']) === 0) {
		# category(ies)
		$cats = explode(',', substr($urlpath, strlen($gb_config['categories-prefix'])));
		$posts = $gitblog->postsByCategories($cats);
		require $gitblog->pathToTheme('categories.php');
	}
	elseif (preg_match($gb_config['posts']['slug-prefix-re'], $urlpath)) {
		# post
		$post = $gitblog->postBySlug($urlpath);
		require $gitblog->pathToTheme('post.php');
	}
	else {
		# page
		$post = $gitblog->pageBySlug($urlpath);
		require $gitblog->pathToTheme('page.php');
	}
}
else {
	# posts
	$pageno = isset($_REQUEST['page']) ? intval($_REQUEST['page']) : 0;
	$path = $gitblog->pathToPostsPage($pageno);
	$page = @unserialize(file_get_contents($path));
	require $gitblog->pathToTheme('posts.php');
}
?>