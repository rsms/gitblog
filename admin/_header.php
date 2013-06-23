<?php
header('Content-Type: text/html; charset=utf-8');

?><!DOCTYPE html>
<html lang="en">
	<head>
		<meta charset="utf-8">
		<title><?php echo gb_title() ?></title>
		<link href="<?php echo gb_admin::$url ?>res/screen.css" type="text/css" rel="stylesheet" media="screen">
		<script type="text/javascript" src="<?php echo gb_admin::$url ?>res/jquery-1.3.2.min.js?v=<?php echo gb_headid() ?>"></script>
		<script type="text/javascript" src="<?php echo gb_admin::$url ?>res/jquery.json-1.3.min.js?v=<?php echo gb_headid() ?>"></script>
		<script type="text/javascript" src="<?php echo gb_admin::$url ?>res/gb-admin.js?v=<?php echo gb_headid() ?>"></script>
	</head>
	<?php gb_flush(); ?>
	<body>
		<div id="head">
			<?php if (gb::$authorized): ?>
			<div class="user">
				Logged in as <?php echo h(gb::$authorized) ?> &mdash;
				<a href="<?php echo gb_admin::$url ?>helpers/deauthorize.php?referrer=<?php echo urlencode(gb::url()) ?>">Log out</a>
			</div>
			<?php endif ?>
			<h1>
				<a href="<?php echo gb::$site_url ?>"><?php echo h(gb::$site_title) ?> <span class="note">‹ visit site</span></a>
			</h1>
		</div>
		<div id="gb-errors" <?php echo (!gb::$errors) ? 'style="display:none"':'' ?>>
			<div class="wrapper">
				<a class="close" 
					href="javascript:ui.hideAlert()"
					title="Hide this message"><span>X</span></a>
				<div class="icon"></div>
				<ul>
					<li class="title">
						<?php echo count(gb::$errors) === 1 ? 'An error occured' : counted(count(gb::$errors), '','errors occured') ?>
					</li>
				<?php foreach (gb::$errors as $error): ?>
					<li><?php echo h($error) ?></li>
				<?php endforeach ?>
				</ul>
			</div>
		</div>
		<div id="menu" <?php if($integrity == 2) echo 'class="disabled"' ?>>
			<?php echo gb_admin::render_menu($integrity == 2) ?>
		</div>