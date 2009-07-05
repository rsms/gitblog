<?
header('Content-Type: application/rss+xml; charset=utf-8');
?>
<channel>
	<title><?= gb_title() ?></title>
<? foreach ($postspage->posts as $post): ?>
	<item>
		<url><?= $post->url() ?></url>
		<title><?= $post->title ?></title>
		<author><?= $post->author->name ?></author>
		<date><?= date('c', $post->published) ?></date>
		<modified><?= date('c', $post->modified) ?></modified>
		<?= $post->tagLinks('<tag href="%u">%n</tag>', "\n\t\t", "\n\t\t") ?>

		<?= $post->categoryLinks('<category href="%u">%n</tag>', "\n\t\t", "\n\t\t") ?>

		<comments><?= $post->comments ?></comments>
		<gb:version><?= $post->id ?></gb:version>
		<content><![CDATA[
			<?= $post->body ?>
			<? if ($post->excerpt): ?>
				<p><a href="<?= $post->url() ?>#<?= $post->domID() ?>-more">Read more...</a></p>
			<? endif; ?>
		]]></content>
	</item>
<? endforeach ?>
</channel>
