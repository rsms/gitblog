<?
header('Content-Type: application/xhtml+xml; charset=utf-8');

?><!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
	<head>
		<meta http-equiv="Content-Type" content="application/xhtml+xml; charset=utf-8"/>
		<title><?= gb_title() ?></title>
		<link href="<?= gb_admin::$url ?>res/screen.css" type="text/css" rel="stylesheet" media="screen" />
		<script type="text/javascript" src="<?= gb_admin::$url ?>res/jquery-1.3.2.min.js"></script>
		<script type="text/javascript" src="<?= gb_admin::$url ?>res/gb-admin.js"></script>
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
		<div id="gb-errors" <?= (!gb::$errors) ? 'style="display:none"':'' ?>>
			<div class="wrapper">
				<a class="close" 
					href="javascript:ui.hideAlert()"
					title="Hide this message"><span>X</span></a>
				<div class="icon"></div>
				<ul>
					<li class="title">
						<?= count(gb::$errors) === 1 ? 'An error occured' : counted(count(gb::$errors), '','errors occured') ?>
					</li>
				<? foreach (gb::$errors as $error): ?>
					<li><?= h($error) ?></li>
				<? endforeach ?>
				</ul>
			</div>
		</div>
		<div id="menu" <? if($integrity == 2) echo 'class="disabled"' ?>>
			<?= gb_admin::render_menu($integrity == 2) ?>
		</div>