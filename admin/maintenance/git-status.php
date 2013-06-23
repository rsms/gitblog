<?php
require_once '../_base.php';
gb::authenticate();
gb::$title[] = 'Repository status';
include '../_header.php';
$st = git::status();
?>
<div id="content" class="<?php echo gb_admin::$current_domid ?> margins git-status">
	<h2>Status</h2>
	<p class="repo-state">
		On branch <span class="git-branch"><?php echo h($st['branch'])?></span>
		<?php if (isset($st['upstream'])): ?>
			&larr; ahead of <span class="git-branch"><?php echo h($st['upstream']['name']) ?></span>
			by <?php echo $st['upstream']['distance'] ?> commits.
		<?php endif ?>
	</p>
	<?php if (isset($st['staged'])): ?>
		<h3>Changes to be committed</h3>
		<ul class="staged">
		<?php foreach ($st['staged'] as $name => $t): $status = $t['status']; ?>
			<li class="<?php echo h($status) ?>">
				<span class="wrapper">
					<?php echo h($name) ?>
					<?php if ($status === 'renamed'): ?>
						&rarr; <?php echo h($t['newname']) ?>
					<?php endif ?>
				</span>
				<span class="badge"><?php echo h(ucfirst($status)) ?></span>
			</li>
		<?php endforeach ?>
		</ul>
		<p class="tips">
			Use <code>git reset HEAD &lt;file&gt;...</code> to unstage.
		</p>
	<?php endif ?>
	<?php if (isset($st['unstaged'])): ?>
		<h3>Changed but not updated</h3>
		<ul class="unstaged">
		<?php foreach ($st['unstaged'] as $name => $t): $status = $t['status']; ?>
			<li class="<?php echo h($status) ?>">
				<span class="wrapper">
					<?php echo h($name) ?>
					<?php if ($status === 'renamed'): ?>
						&rarr; <?php echo h($t['newname']) ?>
					<?php endif ?>
				</span>
				<span class="badge"><?php echo h(ucfirst($status)) ?></span>
			</li>
		<?php endforeach ?>
		</ul>
		<p class="tips">
			Use <code>git add/rm &lt;file&gt;...</code> to update what will be committed.<br>
			Use <code>git checkout -- &lt;file&gt;...</code> to discard changes in working directory.
		</p>
	<?php endif ?>
	<?php if (isset($st['untracked'])): ?>
		<h3>Untracked files</h3>
		<ul class="untracked">
		<?php foreach ($st['untracked'] as $name => $status): ?>
			<li><?php echo h($name) ?></li>
		<?php endforeach ?>
		</ul>
		<p class="tips">
			Use <code>git add &lt;file&gt;...</code> to include in what will be committed.
		</p>
	<?php endif ?>
	<pre><?#= h(var_export($st,1)) ?></pre>
</div>
<?php include '../_footer.php' ?>
