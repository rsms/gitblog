<?
require '../gb/git.php';
$object = $repo->getObjectByName($_GET['name']);
if (!$object)
	exit('not found');
?>
<h2>Blob /<?= htmlentities($object->name) ?></h2>
<? if ($object instanceof GitBlob): ?>
	<pre><?= htmlentities($object->data) ?></pre>
<? endif ?>
<hr/>
<address>Total git queries: <?= $repo->gitQueryCount ?></address>