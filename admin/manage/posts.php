<?php
require_once '../_base.php';
gb::authenticate();
gb::$title[] = 'Posts';
include '../_header.php';

$muxed_posts = array();

class st {
	const STAGED    = 't';
	const UNSTAGED  = 'c';
	const UNTRACKED = 'u';
	
	const DRAFT     = 'd';
	const SCHEDULED = 's';
	const MODIFIED  = 'm';
	const REMOVED   = 'r';
	const RENAMED   = 'e';
	const ADDED     = 'a';
}

$st = git::status();

function _mkflags($post, $status='') {
	$flags = '';
	if ($post->draft)
		$flags .= st::DRAFT;
	switch ($status) {
		# added'|'modified'|'deleted renamed
		case 'added':
			$flags .= st::ADDED;
			break;
		case 'modified':
			$flags .= st::MODIFIED;
			break;
		case 'deleted':
			$flags .= st::REMOVED;
			break;
		case 'renamed':
			$flags .= st::RENAMED;
			break;
	}
	return $flags;
}

function _add_posts_from_status($st, $prefixmatch, $stage, $stageflag) {
	global $muxed_posts;
	if (isset($st[$stage])) {
		foreach ($st[$stage] as $name => $t) {
			if (substr($name,0,strlen($prefixmatch)) !== $prefixmatch)
				continue;
			$status = is_array($t) ? $t['status'] : '';
			$post = GBPost::findByName($name, $status === 'deleted' ? null : 'work', false);
			if ($post) {
				if (!isset($muxed_posts[$post->name]))
					$muxed_posts[$post->name] = array();
				$muxed_posts[$post->name][] = array($post, $stageflag._mkflags($post, $status));
			}
		}
	}
}

# Add dirty staged, unstaged and untracked files
_add_posts_from_status($st, 'content/posts/', 'staged', st::STAGED);
_add_posts_from_status($st, 'content/posts/', 'unstaged', st::UNSTAGED);
_add_posts_from_status($st, 'content/posts/', 'untracked', st::UNTRACKED);

# Add clean drafts
foreach (gb::index('draft-posts') as $post) {
	if (!isset($muxed_posts[$post->name]))
		$muxed_posts[$post->name] = array();
	$muxed_posts[$post->name][] = array($post, st::DRAFT);
}

function _post_tuple_sortfunc($a,$b) {
	return $b[0]->modified->time - $a[0]->modified->time;
}
function _muxed_posts_sortfunc($a, $b) {
	return $b[0][0]->modified->time - $a[0][0]->modified->time;
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
			uasort($muxed_posts[$post->name], '_post_tuple_sortfunc');
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

# sort by modified desc
uasort($muxed_posts, '_muxed_posts_sortfunc');

?>
<script type="text/javascript" charset="utf-8">
	function update_visible_rows() {
		var hiddenClasses = [];
		$('div.filter-flags input[type=checkbox]').each(function(){
			if (!this.checked)
				hiddenClasses.push(this.value);
		});
		$('table.posts tr').each(function(){
			var tr = $(this);
			var hidden = false;
			console.log(hiddenClasses);
			for (var k in hiddenClasses) {
				hidden = tr.hasClass(hiddenClasses[k]);
				if (hidden)
					break;
			}
			if (hidden)
				tr.hide();
			else
				tr.show();
		});
	}
	$(function(){
		$('div.filter-flags input[type=checkbox]').change(update_visible_rows);
		update_visible_rows();
	});
</script>
<div id="content" class="<?php echo gb_admin::$current_domid ?> manage items">
	<div class="head">
		<h2>Posts</h2>
		<div class="options filter-flags">
			Show:
			<label class="d">
				<input type="checkbox" value="d" checked>
				Drafts
			</label>
			<label class="s">
				<input type="checkbox" value="s" checked>
				Scheduled
			</label>
			<label class="m">
				<input type="checkbox" value="m" checked>
				Modified
			</label>
			<label class="u">
				<input type="checkbox" value="u" checked>
				Untracked
			</label>
			<label class="r">
				<input type="checkbox" value="r">
				Removed
			</label>
		</div>
	</div>
	<table class="items posts offline">
	<?php foreach ($muxed_posts as $name => $posts): $childcount = 0; ?>
		<?php foreach ($posts as $v): $post = $v[0]; $flags = $v[1]; ?>
			<?php $editurl = gb_admin::$url.'edit/post.php?name='.urlencode($post->name); ?>
			<tr onclick="document.location.href='<?php echo $editurl ?>'" 
					class="<?php echo implode(' ',str_split($flags)) . ($childcount ? ' child' : (count($posts)>1 ? ' parent' : '')) ?>">
				<td class="name">
					<span class="title">
						<?php echo h($post->title ? $post->title : '('.substr($post->name,strlen('content/posts/')).')') ?>
					</span>
					<?php if (strpos($flags, st::SCHEDULED) !== false): ?>
						<span class="scheduled">
							<?php echo h($post->published->age(null, null, null, '', null, 'a second', 'in ')) ?>
						</span>
					<?php endif ?>
					<?php if (strpos($flags, st::DRAFT) !== false): ?>
						<span class="badge <?php echo st::DRAFT ?>">Draft</span>
					<?php endif ?>
					<?php if (strpos($flags, st::UNTRACKED) !== false): ?>
						<span class="badge <?php echo st::UNTRACKED ?>">Untracked</span>
					<?php endif ?>
					<span class="excerpt">
						<?php $s=h(gb_strlimit($post->textBody(), 80));echo $s ? ' – '.$s : '' ?>
					</span>
				</td>
				<td class="author"><?php echo h($post->author->shortName()) ?></td>
				<td class="date modified type-number"><?php echo h($post->modified->condensed()) ?></td>
			</tr>
		<?php
		
		# comment-out this to show parent (staged) versions below the dirty version
		break;
		
		?>
		<?php $childcount++; endforeach ?>
	<?php endforeach ?>
	</table>
	<div class="paged-nav">
		<?php if ($num_more_postpages): ?>
		<a href="javascript:alert('Paging not yet implemented')">Load <?php echo $num_more_postpages ?> more pages</a>
		<?php endif ?>
	</div>
</div>
<?php include '../_footer.php' ?>
