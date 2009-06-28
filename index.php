<?
require 'gb-config.php';
require 'gitblog/gitblog.php';

$gb_urlpath = isset($_SERVER['PATH_INFO']) ? trim($_SERVER['PATH_INFO'], '/') : '';
$gb_is_404 = false;
$gb_is_page = false;
$gb_is_post = false;
$gb_is_posts = false;
$gb_is_search = false;
$gb_is_tags = false;
$gb_is_categories = false;

if ($gb_urlpath) {
	if (strpos($gb_urlpath, 'gitblog/') === 0) {
		if ($_SERVER['HTTP_X_GB_SHARED_SECRET'] != $gb_config['secret']) {
			header('Status: 401 Unauthorized');
			exit('401 Unauthorized');
		}
		$gb_urlpath = rtrim(substr($gb_urlpath, 8), '/');
		if (strpos($gb_urlpath, 'hooks/') === 0) {
			require GITBLOG_DIR.'/hooks/'.str_replace('..','',substr($gb_urlpath, 6)).'.php';
		}
		else {
			exit('unknown gitblog action '.var_export($gb_urlpath,1));
		}
		exit(0);
	}
	elseif (strpos($gb_urlpath, $gb_config['tags-prefix']) === 0) {
		# tag(s)
		$tags = array_map('urldecode', explode(',', substr($gb_urlpath, strlen($gb_config['tags-prefix']))));
		$posts = $gitblog->postsByTags($tags);
		$gb_is_tags = true;
	}
	elseif (strpos($gb_urlpath, $gb_config['categories-prefix']) === 0) {
		# category(ies)
		$cats = array_map('urldecode', explode(',', substr($gb_urlpath, strlen($gb_config['categories-prefix']))));
		$posts = $gitblog->postsByCategories($cats);
		$gb_is_categories = true;
	}
	elseif (preg_match($gb_config['posts']['slug-prefix-re'], $gb_urlpath)) {
		# post
		$post = $gitblog->postBySlug(urldecode($gb_urlpath));
		if ($post === false)
			$gb_is_404 = true;
		$gb_is_post = true;
	}
	else {
		# page
		$post = $gitblog->pageBySlug(urldecode($gb_urlpath));
		if ($post === false)
			$gb_is_404 = true;
		$gb_is_page = true;
	}
}
else {
	# posts
	$pageno = isset($_REQUEST['page']) ? intval($_REQUEST['page']) : 0;
	$postspage = $gitblog->postsPageByPageno($pageno);
	$gb_is_posts = true;
}
require $gitblog->pathToTheme('index.php');
?>