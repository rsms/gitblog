<?
$gb_config = array(
	'repo' => dirname(realpath(__FILE__)).'/site',
	'url-prefix' => '/gitblog/index.php/',
	'tags-prefix' => 'tags/',
	'categories-prefix' => 'categories/',
	'posts' => array(
		'slug-prefix' => '%Y/%m/%d/',
		'slug-prefix-re' => '/^\d{4}\/\d{2}\/\d{2}\//',
	)
);
?>