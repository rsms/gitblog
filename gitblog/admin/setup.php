<?
require_once '_base.php';

# do not render this page unless there is no repo
if ($integrity !== 2) {
	header('Location: '.$gb_config['base-url'].'gitblog/admin/');
	exit(0);
}

$gb_title[] = 'Setup';

?><!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
		<title><?= gb_title() ?></title>
	</head>
	<body>
		<h1><?= htmlentities($gb_config['title']) ?></h1>
		
		<h2>Setup your gitblog</h2>
		<form action="setup.php" method="post">
			<p>
				<label>
					Site title:
					<input type="text" name="title" value="<?= htmlentities($gb_config['title']) ?>" />
				</label>
			</p>
			<p>
				<label>
					Administrator password:
					<input type="password" name="password" />
					<input type="password" name="password2" />
				</label>
			</p>
		</form>
		
		<hr/>
		<address>
			Gitblog/<?= GITBLOG_VERSION ?> (processing time <? $s = (microtime(true)-$debug_time_started); printf('%.3f ms', 1000.0 * $s) ?>)
		</address>
	</body>
</html>