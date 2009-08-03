<?
$updated_time = $postspage->posts ? $postspage->posts[0]->modified->time : time();

header('Content-Type: application/atom+xml; charset=utf-8');
header('Last-Modified: '.date('r', $updated_time));
echo '<?xml version="1.0" encoding="UTF-8"?>'.PHP_EOL;
?>
<feed xmlns="http://www.w3.org/2005/Atom" 
      xmlns:thr="http://purl.org/syndication/thread/1.0"
      xmlns:gb="http://gitblog.se/ns/atom/1.0"
      xml:lang="en"
      xml:base="<?= h(gb::$site_url) ?>">
	<id><?= h(gb::url()) ?></id>
	<title><?= h(gb::$site_title) ?></title>
	<link rel="alternate" type="text/html" href="<?= h(gb::$site_url) ?>" />
	<updated><?= date('c', $updated_time) ?></updated>
	<generator uri="http://gitblog.se/" version="<?= gb::$version ?>">Gitblog</generator>
<? foreach ($postspage->posts as $post): ?>
	<entry>
		<title type="html"><?= h($post->title) ?></title>
		<author>
			<name><?= h($post->author->name) ?></name>
			<uri><?= h(gb::$site_url) ?></uri>
		</author>
		<link rel="alternate" type="text/html" href="<?= h($post->url()) ?>" />
		<id><?= h($post->url()) ?></id>
		<published><?= $post->published ?></published>
		<updated><?= $post->modified ?></updated>
		<?= $post->tagLinks('', '', '<category scheme="'.gb::url_to('tags').'" term="%n" />',
			"\n\t\t", "\n\t\t").($post->tags ? "\n" : '') ?>
		<?= $post->categoryLinks('', '', '<category scheme="'.gb::url_to('categories').'" term="%n" />',
			"\n\t\t", "\n\t\t").($post->categories ? "\n" : '') ?>
		<comments><?= $post->comments ?></comments>
		<gb:version><?= $post->id ?></gb:version>
		<? if ($post->excerpt): ?>
		<summary type="html"><![CDATA[<?= $post->excerpt ?>]]></summary>
		<? endif ?>
		<content type="html" xml:base="<?= h($post->url()) ?>"><![CDATA[<?= $post->body ?><? if ($post->excerpt): ?>
			<p><a href="<?= h($post->url()) ?>#<?= $post->domID() ?>-more">Read more...</a></p>
		<? endif; ?>]]></content>
		<link rel="replies" type="text/html" href="<?= h($post->url()) ?>#comments" thr:count="<?= $post->comments ?>" />
		<link rel="replies" type="application/atom+xml" href="<?= h(gb::url_to('feed')) ?>" thr:count="<?= $post->comments ?>" />
		<thr:total><?= $post->comments ?></thr:total>
	</entry>
<? endforeach ?>
</feed>
