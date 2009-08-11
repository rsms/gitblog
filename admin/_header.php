<?
header('Content-Type: application/xhtml+xml; charset=utf-8');

?><!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
	<head>
		<meta http-equiv="Content-Type" content="application/xhtml+xml; charset=utf-8"/>
		<title><?= gb_title() ?></title>
		<link href="<?= gb_admin::$url ?>res/screen.css" type="text/css" rel="stylesheet" media="screen" />
	</head>
	<? gb_flush(); ?>
	<body>
		<div id="head">
			<? if (gb::$authorized): ?>
			<div class="user">
				Logged in as <?= h(gb::$authorized) ?> &mdash;
				<a href="<?= gb_admin::$url ?>helpers/deauthorize.php?referrer=<?= urlencode(gb::url()) ?>">Log out</a>
			</div>
			<? endif ?>
			<h1>
				<a href="<?= gb::$site_url ?>"><?= h(gb::$site_title) ?> <span class="note">‹ visit site</span></a>
			</h1>
		</div>
		<? if (gb_admin::$errors): ?>
			<div id="errormsg">
				<p>
					<?= implode('</p><p>', gb_admin::$errors) ?>
				</p>
			</div>
		<? endif; ?>
		<div id="menu">
			<?= gb_admin::render_menu() ?>
		</div>