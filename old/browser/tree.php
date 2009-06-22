<? require '../gb/git.php'; ?>
<h2>Tree /<?= isset($_GET['name']) ? htmlentities($_GET['name']) : '' ?></h2>
<ul>
<? 
$objects = $repo->ls(isset($_GET['name']) ? $_GET['name'] : '', isset($_GET['recursive']) ? true : false);

foreach ($objects as $object): ?>
	<?
		$name = $object->name ? $object->name : '';
		if (isset($_GET['name']))
		 	$name = substr($name, strlen($_GET['name']));
	?>
	<li>
	<? if ($object instanceof GitBlob): ?>
		<a href="blob.php?name=<?= htmlentities($object->name) ?>"><?= $name ?></a>
		<?= $object->size ?> (<?= $object->mimeType ?>)
	<? else: ?>
		<a href="tree.php?name=<?= htmlentities($object->name) ?>/"><?= $name ?>/</a>
	<? endif ?>
	</li>
<? endforeach ?>
</ul>
<hr/>
<address>Total git queries: <?= $repo->gitQueryCount ?></address>