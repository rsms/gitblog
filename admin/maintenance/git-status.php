<?
require_once '../_base.php';
gb::authenticate();
gb::$title[] = 'Repository status';
include '../_header.php';
$st = git::status();
?>
<div id="content" class="<?= gb_admin::$current_domid ?> margins git-status">
	<h2>Status</h2>
	<p class="repo-state">
		On branch <span class="git-branch"><?= h($st['branch'])?></span>
		<? if (isset($st['upstream'])): ?>
			&larr; ahead of <span class="git-branch"><?= h($st['upstream']['name']) ?></span>
			by <?= $st['upstream']['distance'] ?> commits.
		<? endif ?>
	</p>
	<? if (isset($st['staged'])): ?>
		<h3>Changes to be committed</h3>
		<ul class="staged">
		<? foreach ($st['staged'] as $name => $t): $status = $t['status']; ?>
			<li class="<?= h($status) ?>">
				<span class="wrapper">
					<?= h($name) ?>
					<? if ($status === 'renamed'): ?>
						&rarr; <?= h($t['newname']) ?>
					<? endif ?>
				</span>
				<span class="badge"><?= h(ucfirst($status)) ?></span>
			</li>
		<? endforeach ?>
		</ul>
		<p class="tips">
			Use <code>git reset HEAD &lt;file&gt;...</code> to unstage.
		</p>
	<? endif ?>
	<? if (isset($st['unstaged'])): ?>
		<h3>Changed but not updated</h3>
		<ul class="unstaged">
		<? foreach ($st['unstaged'] as $name => $t): $status = $t['status']; ?>
			<li class="<?= h($status) ?>">
				<span class="wrapper">
					<?= h($name) ?>
					<? if ($status === 'renamed'): ?>
						&rarr; <?= h($t['newname']) ?>
					<? endif ?>
				</span>
				<span class="badge"><?= h(ucfirst($status)) ?></span>
			</li>
		<? endforeach ?>
		</ul>
		<p class="tips">
			Use <code>git add/rm &lt;file&gt;...</code> to update what will be committed.<br>
			Use <code>git checkout -- &lt;file&gt;...</code> to discard changes in working directory.
		</p>
	<? endif ?>
	<? if (isset($st['untracked'])): ?>
		<h3>Untracked files</h3>
		<ul class="untracked">
		<? foreach ($st['untracked'] as $name => $status): ?>
			<li><?= h($name) ?></li>
		<? endforeach ?>
		</ul>
		<p class="tips">
			Use <code>git add &lt;file&gt;...</code> to include in what will be committed.
		</p>
	<? endif ?>
	<pre><?#= h(var_export($st,1)) ?></pre>
</div>
<? include '../_footer.php' ?>
