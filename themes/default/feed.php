<?
header('Content-Type: application/atom+xml; charset=utf-8');
header('Last-Modified: '.date('r', $postspage->posts ? $postspage->posts[0]->modified : time()));
echo '<?xml version="1.0" encoding="UTF-8"?>'.PHP_EOL;
?>
<feed xmlns="http://www.w3.org/2005/Atom" 
      xmlns:thr="http://purl.org/syndication/thread/1.0"
      xmlns:gb="http://gitblog.se/ns/atom/1.0"
      xml:lang="en"
      xml:base="<?= GB_SITE_URL.gb::$feed_prefix ?>">
	<id><?= GB_SITE_URL.gb::$feed_prefix ?></id>
	<title><?= gb::$site_title ?></title>
	<link rel="alternate" type="text/html" href="<?= GB_SITE_URL ?>" />
	<updated><?= date('c', $postspage->posts ? $postspage->posts[0]->modified : time()) ?></updated>
	<generator uri="http://gitblog.se/" version="<?= GB_VERSION ?>">Gitblog</generator>
<? foreach ($postspage->posts as $post): ?>
	<entry>
		<title type="html"><?= $post->title ?></title>
		<author>
			<name><?= $post->author->name ?></name>
			<uri><?= GB_SITE_URL ?></uri>
		</author>
		<link rel="alternate" type="text/html" href="<?= $post->url() ?>" />
		<id><?= $post->url() ?></id>
		<published><?= date('c', $post->published) ?></published>
		<updated><?= date('c', $post->modified) ?></updated>
		<?= $post->tagLinks('<category scheme="'.GB_SITE_URL.gb::$tags_prefix.'" term="%n" />',
			"\n\t\t", "\n\t\t").($post->tags ? "\n\t\t" : '') ?>
		<?= $post->categoryLinks('<category scheme="'.GB_SITE_URL.gb::$categories_prefix.'" term="%n" />',
			"\n\t\t", "\n\t\t").($post->categories ? "\n\t\t" : '') ?>
		<comments><?= $post->comments ?></comments>
		<gb:version><?= $post->id ?></gb:version>
		<? if ($post->excerpt): ?>
		<summary type="html"><![CDATA[<?= $post->excerpt ?>]]></summary>
		<? endif ?>
		<content type="html" xml:base="<?= $post->url() ?>"><![CDATA[<?= $post->body ?><? if ($post->excerpt): ?>
			<p><a href="<?= $post->url() ?>#<?= $post->domID() ?>-more">Read more...</a></p>
		<? endif; ?>]]></content>
		<link rel="replies" type="text/html" href="<?= $post->url() ?>#comments" thr:count="<?= $post->comments ?>" />
		<link rel="replies" type="application/atom+xml" href="<?= GB_SITE_URL.gb::$feed_prefix ?>" thr:count="<?= $post->comments ?>" />
		<thr:total><?= $post->comments ?></thr:total>
	</entry>
<? endforeach ?>
</feed>
