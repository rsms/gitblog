<?
header('Content-Type: text/html; charset=utf-8');

?><!DOCTYPE html>
<html lang="en">
	<head>
		<meta charset="utf-8">
		<title><?= gb_title() ?></title>
		<link href="<?= gb_admin::$url ?>res/screen.css" type="text/css" rel="stylesheet" media="screen">
		<script type="text/javascript" src="<?= gb_admin::$url ?>res/jquery-1.3.2.min.js?v=<?= gb_headid() ?>"></script>
		<script type="text/javascript" src="<?= gb_admin::$url ?>res/jquery.json-1.3.min.js?v=<?= gb_headid() ?>"></script>
		<script type="text/javascript" src="<?= gb_admin::$url ?>res/gb-admin.js?v=<?= gb_headid() ?>"></script>
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