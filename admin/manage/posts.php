<?
require_once '../_base.php';
gb::authenticate();
gb::$title[] = 'Posts';
include '../_header.php';

$muxed_posts = array();

class st {
	const UNTRACKED = 'u';
	const DRAFT     = 'd';
	const SCHEDULED = 's';
	const MODIFIED  = 'm';
	const STAGED    = 't';
	const REMOVED   = 'r';
}

# Add untracked drafts
foreach (git::ls_untracked('content/posts/', '.*') as $filename) {
	$post = GBPost::findByName($filename, 'work', false);
	if ($post) {
		if (!isset($muxed_posts[$post->name]))
			$muxed_posts[$post->name] = array();
		$muxed_posts[$post->name][] = array($post, st::UNTRACKED.($post->draft ? st::DRAFT : ''));
	}
}

# Add tracked dirty
foreach (git::ls_modified('content/posts/', '.*') as $filename) {
	$post = GBPost::findByName($filename, 'work', false);
	if ($post) {
		if (!isset($muxed_posts[$post->name]))
			$muxed_posts[$post->name] = array();
		$muxed_posts[$post->name][] = array($post, st::MODIFIED.($post->draft ? st::DRAFT : ''));
	}
}

# Add removed
foreach (git::ls_removed('content/posts/', '.*') as $filename) {
	$post = GBPost::findByName($filename);
	if ($post) {
		if (!isset($muxed_posts[$post->name]))
			$muxed_posts[$post->name] = array();
		$muxed_posts[$post->name][] = array($post, st::REMOVED);
	}
}

# Add tracked drafts
foreach (gb::index('draft-posts') as $post) {
	if (!isset($muxed_posts[$post->name]))
		$muxed_posts[$post->name] = array();
	$muxed_posts[$post->name][] = array($post, st::DRAFT);
}

function sf($a,$b) {
	return $b[0]->modified->time - $a[0]->modified->time;
}

# Add published and scheduled posts
$pageno = 0; # pages are 0 (zero) indiced
$maxpages = 5;
$num_more_postpages = 0;
do {
	$postspage = GBPost::pageByPageno($pageno);
	if (!$postspage)
		break;
	foreach ($postspage->posts as $rank => $post) {
		if (!isset($muxed_posts[$post->name]))
			$muxed_posts[$post->name] = array();
		if ($post->published->time > time()) {
			$muxed_posts[$post->name][] = array($post, st::SCHEDULED.($post->draft ? st::DRAFT : ''));
			uasort($muxed_posts[$post->name], 'sf');
		}
		else {
			#$online[] = $post;
			$muxed_posts[$post->name][] = array($post, st::STAGED.($post->draft ? st::DRAFT : ''));
		}
	}
	if ($pageno == $maxpages-1) {
		$num_more_postpages = $postspage->numpages - $maxpages;
		break;
	}
} while ($pageno++ < $postspage->numpages);


uasort($muxed_posts, create_function('$a, $b', 'return $b[0][0]->modified->time - $a[0][0]->modified->time;'));

?>
<div id="content" class="<?= gb_admin::$current_domid ?>">
	<h2>Posts</h2>
	<table class="posts offline">
	<? foreach ($muxed_posts as $name => $posts): $childcount = 0; ?>
		<? foreach ($posts as $v): $post = $v[0]; $st = $v[1]; ?>
			<? $editurl = gb_admin::$url.'edit/post.php?name='.urlencode($post->name); ?>
			<tr onclick="document.location.href='<?= $editurl ?>'" 
					class="<?= implode(' ',str_split($st)) . ($childcount ? ' child' : (count($posts)>1 ? ' parent' : '')) ?>">
				<td class="name">
					<span class="title">
						<?= h($post->title ? $post->title : '('.substr($post->name,strlen('content/posts/')).')') ?>
					</span>
					<span class="excerpt">
						<? $s=h(gb_strlimit($post->textBody(), 80));echo $s ? ' – '.$s : '' ?>
					</span>
				</td>
				<td class="author"><?= h($post->author->shortName()) ?></td>
				<td class="date modified type-number"><?= h($post->modified->condensed()) ?><?/*= h($st == st::STAGED ? $post->published->condensed() : $post->modified->condensed()) */?></td>
			</tr>
		<? $childcount++; endforeach ?>
	<? endforeach ?>
	</table>
	<div class="paged-nav">
		<? if ($num_more_postpages): ?>
		<a href="javascript:alert('Paging not yet implemented')">Load <?= $num_more_postpages ?> more pages</a>
		<? endif ?>
	</div>
</div>
<? include '../_footer.php' ?>
