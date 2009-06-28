<?
header('Content-Type: application/xhtml+xml; charset=utf-8');

?><!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
	<head>
		<meta http-equiv="Content-Type" content="application/xhtml+xml; charset=utf-8"/>
		<title><?= gb_title() ?></title>
    <link href="<?= GITBLOG_ADMIN_URL ?>screen.css" type="text/css" rel="stylesheet" media="screen" />
	</head>
	<body>
		<div id="head">
			<h1><?= h(gb::$site_title) ?></h1>
		</div>
		<? if ($errors): ?>
			<div id="errormsg">
				<p>
					<?= implode('</p><p>', $errors) ?>
				</p>
			</div>
		<? endif; ?>		
		<div id="content">