<?
require_once '../_base.php';
gb::authenticate();

$paths = isset($_GET['paths']) ? $_GET['paths'] : null;
$id = isset($_GET['id']) ? $_GET['id'] : '';

# Find commit
list($commits, $existing, $ntoc) = GitCommit::find(array(
	'treeish' => $id.'^'.'..'.$id,
	'names' => $paths
));
if (!count($commits)) {
	header('HTTP/1.1 404 Not Found');
	exit('commit "'.h($_GET['id']).'" not found');
}
$commit = $commits[key($commits)];
$patches = $commit->loadPatches($paths);

gb::$title[] = 'Commit '.$commit->id;
include '../_header.php';
?>
<style type="text/css" media="screen">
	.helpers-view-commit h2 span.id { font-weight:normal; }
	.helpers-view-commit h2 span.id.tail { color:#ccc; }
	.git-patch { border:1px solid #ccc; margin-bottom:20px; }
	.git-patch.a { border-color:#a9eda9; }
	.git-patch.d { border-color:#f2b4b4; }
		.git-patch .header { background-color:#ddd; color:#000; padding:5px 0 5px 5px; border-bottom:1px solid #ccc; }
		.git-patch.d .header { background-color:#fdd; }
		.git-patch.a .header { background-color:#cfc; }
		.git-patch.d .header, .git-patch.a .header, .git-patch.r .header, .git-patch.c .header { border:none; }
			.git-patch .header .action {
				display:inline-block;
				margin-right:5px;
				border-radius:2px; -webkit-border-radius:2px; -moz-border-radius:2px;
				background:#bbb;
				padding:1px 4px;
				min-width:12px;
				text-align:center;
			}
			.git-patch.a .header .action { background-color:#50e150; }
			.git-patch.d .header .action { background-color:#ff958a; }
			.git-patch .header .filename { font-weight:bold; }
		.git-patch .lines { overflow:auto; width:700px; }
			.git-patch .lines .line { line-height:1.4em; color:#000; }
			.git-patch .lines .line.add { background-color:#dfd; }
			.git-patch .lines .line.rm { background-color:#fdd; }
			.git-patch .lines .line.ctx { background-color:#eee; color:#666; }
</style>
<div id="content" class="<?= gb_admin::$current_domid ?> helpers-view-commit margins">
	<h2>Commit <span class="id"><?=substr($commit->id,0,7)?></span><span class="id tail"><?=substr($commit->id,7)?></span></h2>
	<p class="repo-state">
		<span class="date"><?= $commit->authorDate->condensed() ?></span>
		by <span class="git-author" title="<?= h($commit->authorName) ?> &lt;<?= h($commit->authorEmail) ?>&gt;">
			<?= h($commit->authorName) ?>
		</span>
	</p>
	
	<? foreach ($patches as $patch): ?>
	<div class="git-patch <?= h(strtolower($patch->action)) ?>">
		<div class="header">
			<span class="action"><?= h($patch->action) ?></span>
			<?# var_dump($patch) ?>
			<span class="filename"><?= h($patch->action === GitPatch::DELETE ? $patch->prevname : $patch->currname) ?></span>
		</div>
		<? if ($patch->action === GitPatch::EDIT_IN_PLACE): ?>
			<div class="lines">
				<? foreach ($patch->lines as $i => $line):
					$fc = $line ? $line{0} : '';
					$cc = '';
					if ($fc === '+')     $cc = 'add';
					elseif ($fc === '-') $cc = 'rm';
					elseif ($fc === '@') $cc = 'ctx';
					?>
					<pre class="line <?= $cc ?>"> <?= h($line) ?></pre>
				<? endforeach ?>
			</div>
		<? endif ?>
	</div>
	<? endforeach ?>
</div>
<? include '../_footer.php' ?>
