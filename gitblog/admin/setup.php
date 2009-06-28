<?
require_once '_base.php';

# do not render this page unless there is no repo
if ($integrity !== 2) {
	header('Location: '.$gb_config['base-url'].'gitblog/admin/');
	exit(0);
}

# Got POST
if (isset($_POST['submit'])) {
	if (!trim($_POST['password'])) {
		$errors[] = '<b>Empty pass phrase.</b>
			The pass phrase is empty or contains only spaces.';
	}
	if ($_POST['password'] !== $_POST['password2']) {
		$errors[] = '<b>Pass phrases not matching.</b>
			You need to type in the same pass phrase in the two input fields below.';
	}
	else {
		# first, copy gb-config.php
		$s = file_get_contents(GITBLOG_DIR.'/skeleton/gb-config.php');
		# title
		$s = preg_replace('/([\t ]+\'title\'[\t ]*=>[\t ]*\')[^\']*\',/', '$1'.$_POST['title']."',", $s, 1);
		# secret
		header('content-type: text/plain; charset=utf-8');
		$secret = '';
		while (strlen($secret) < 62) {
			mt_srand();
			$secret .= base_convert(mt_rand(), 10, 36);
		}
		$s = preg_replace('/([\t ]+\'secret\'[\t ]*=>[\t ]*\')\',/', '${1}'.$secret."',", $s, 1);
		var_dump($s);exit(0);
	}
}

# ------------------------------------------------------------------------------------------------
# prepare for rendering

$gb_title[] = 'Setup';
$is_writable_dir = dirname($gitblog->repo);
$is_writable = is_writable(file_exists($gitblog->repo) ? $gitblog->repo : $is_writable_dir);

if (!$is_writable) {
	$errors[] = "<b>Ooops.</b> The directory <code>".h($is_writable_dir)."</code> is not writable.
		Gitblog need to write some files and create a few directories in this directory.
		Please make this writable and reload this page.";
}

header('Content-Type: application/xhtml+xml; charset=utf-8');

?><!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
	<head>
		<meta http-equiv="Content-Type" content="application/xhtml+xml; charset=utf-8"/>
		<title><?= gb_title() ?></title>
		<style type="text/css">
			* { margin:0; padding:0; }
			body {
				font-family:'helvetica neue',helvetica,arial,sans-serif;
				background-color:#fff;
				color:#333;
			}
			body, p, li, td, div { font-size:13px; }
			p { margin:15px 0; }
			hr { border:none; height:1px; background-color:#ddd; }
			div.breaker { clear:both; }
			
			#head { background-color:#ff9; padding:10px 20px; }
			#head h1 { font-size:14px; color:#640; }
			
			#content { background-color:#fff; margin:0 20px; }
			#content h2 { margin:15px 0 10px 0; font-size:24px; }
			
			body > address {
				border-top:1px solid #ddd;
				color:#aaa;
				padding:10px 20px;
				margin-top:20px;
				font-size:11px;
			}
			
			
			label {
				display:block;
				margin-bottom:10px;
				float:left;
				margin-right:10px;
				padding-right:10px;
				width:300px;
				border-right:1px solid #ddd;
			}
			label > h4 { font-size:100%; margin-bottom:4px; }
			label > p { margin:2px 0 4px 0; }
			label > input { margin:2px 0; }
			label > input[type=password] { width:130px; }
			label > input[type=text] { width:290px; }
			
			#errormsg {
				background-color:#fa9;
				border-top:1px solid #c77;
			}
			#errormsg > p {
				padding:15px 20px;
				margin:0;
				color:#400;
				font-size:16px;
				border-bottom:1px solid #c77;
			}
			#errormsg code {
				background-color:#fdd;
			}
			
		</style>
	</head>
	<body>
		<div id="head">
			<h1><?= htmlentities($gb_config['title']) ?></h1>
		</div>
		<? if ($errors): ?>
			<div id="errormsg">
				<p>
					<?= implode('</p><p>', $errors) ?>
				</p>
			</div>
		<? endif; ?>		
		<div id="content">
			<h2>Setup your gitblog</h2>
			<p>
				It's time to setup your new gitblog.
			</p>
			<form action="setup.php" method="post">
				<label>
					<h4>Site title:</h4>
					<input type="text" name="title" value="<?= htmlentities($gb_config['title']) ?>" />
					<p>Choose a title for your new site. This can be changed later at any time.</p>
				</label>
				<label>
					<h4>Administrator pass phrase:</h4>
					<input type="password" name="password" />
					<input type="password" name="password2" />
					<p>Choose a pass phrase used to authenticate as administrator.</p>
				</label>
				<div class="breaker"></div>
				<p>
				<? if (!$is_writable): ?>
					<input type="button" value="Setup" disabled="true"/>
				<? else: ?>
					<input type="submit" name="submit" value="Setup"/>
				<? endif; ?>
				</p>
			</form>
			<div class="breaker"></div>
		</div>
		<address>
			Gitblog/<?= GITBLOG_VERSION ?> (processing time <? $s = (microtime(true)-$debug_time_started); printf('%.3f ms', 1000.0 * $s) ?>)
		</address>
	</body>
</html>