<?
$gb_urlpath = isset($_SERVER['PATH_INFO']) ? trim($_SERVER['PATH_INFO'], '/') : '';

require 'gitblog/gitblog.php';

# verify integrity, implicitly rebuilding gitblog cache or need serious initing.
if (GitBlog::verifyIntegrity() === 2) {
	header("Location: ".GITBLOG_SITE_URL."gitblog/admin/setup.php");
	exit(0);
}

# verify configuration, like validity of the secret key.
GitBlog::verifyConfig();

if ($gb_urlpath) {
	if (strpos($gb_urlpath, gb::$tags_prefix) === 0) {
		# tag(s)
		$tags = array_map('urldecode', explode(',', substr($gb_urlpath, strlen(gb::$tags_prefix))));
		$posts = GitBlog::postsByTags($tags);
		gb::$is_tags = true;
	}
	elseif (strpos($gb_urlpath, gb::$categories_prefix) === 0) {
		# category(ies)
		$cats = array_map('urldecode', explode(',', substr($gb_urlpath, strlen(gb::$categories_prefix))));
		$posts = GitBlog::postsByCategories($cats);
		gb::$is_categories = true;
	}
	elseif (preg_match(gb::$posts_url_prefix_re, $gb_urlpath)) {
		# post
		$post = GitBlog::postBySlug(urldecode($gb_urlpath));
		if ($post === false)
			gb::$is_404 = true;
		elseif ($post->published > time())
			gb::$is_404 = true;
		gb::$is_post = true;
	}
	else {
		# page
		$post = GitBlog::pageBySlug(urldecode($gb_urlpath));
		if ($post === false)
			gb::$is_404 = true;
		gb::$is_page = true;
	}
}
else {
	# posts
	$pageno = isset($_REQUEST['page']) ? intval($_REQUEST['page']) : 0;
	$postspage = GitBlog::postsPageByPageno($pageno);
	gb::$is_posts = true;
}
require GitBlog::pathToTheme('index.php');
?>